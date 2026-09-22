<?php
/**
 * Post History & Insights UI Integration.
 *
 * Injects AI Insights, Semantic Duplication metrics, and Generation Context
 * into native WordPress Post list tables (edit.php) and Post editors
 * (both Gutenberg Block Editor and Classic Editor) with zero-N+1 prefetching.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 * @since 3.6.7 Upgraded to comprehensive AI Insights with Gutenberg and Table integration.
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_History_UI {

	/**
	 * @var AIPS_History_Repository_Interface
	 */
	private $history_repository;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Request-level cache of history objects keyed by post_id.
	 *
	 * @var array
	 */
	private $history_cache = array();

	/**
	 * Request-level cache of compiled post insights keyed by post_id.
	 *
	 * @var array
	 */
	private $insights_cache = array();

	/**
	 * @var AIPS_Post_Insights_Repository
	 */
	private $insights_repo;

	/**
	 * Constructor.
	 *
	 * @param AIPS_History_Repository_Interface|null $history_repository Optional repository override.
	 * @param AIPS_Config|null                       $config             Optional config override.
	 * @param AIPS_Post_Insights_Repository|null     $insights_repo      Optional repository override.
	 */
	public function __construct($history_repository = null, $config = null, ?AIPS_Post_Insights_Repository $insights_repo = null) {
		$container = AIPS_Container::get_instance();
		$this->history_repository = $history_repository instanceof AIPS_History_Repository_Interface
			? $history_repository
			: ($container->has(AIPS_History_Repository_Interface::class) ? $container->make(AIPS_History_Repository_Interface::class) : new AIPS_History_Repository());
		$this->config = $config ?: AIPS_Config::get_instance();
		$this->insights_repo = $insights_repo ?: ($container->has(AIPS_Post_Insights_Repository::class) ? $container->make(AIPS_Post_Insights_Repository::class) : new AIPS_Post_Insights_Repository());

		// Row actions & Classic Editor submit box
		add_filter('post_row_actions', array($this, 'add_post_row_action'), 10, 2);
		add_filter('page_row_actions', array($this, 'add_post_row_action'), 10, 2);
		add_action('post_submitbox_misc_actions', array($this, 'render_submitbox_action'));

		// Only register extended UI hooks if master toggle is enabled and in admin
		if (!is_admin() || !$this->is_ui_enabled()) {
			return;
		}

		// N+1 Prevention: Bulk pre-fetch all visible post data in a single DB query batch
		add_filter('the_posts', array($this, 'bulk_prefetch_posts_data'), 10, 2);

		// Posts List Table Integration
		add_action('admin_init', array($this, 'register_post_type_table_hooks'));
		add_action('restrict_manage_posts', array($this, 'render_table_filters'));
		add_action('pre_get_posts', array($this, 'handle_table_query_filters'));
		add_action('admin_notices', array($this, 'render_admin_notices'));

		// Classic Editor Meta Box
		add_action('add_meta_boxes', array($this, 'register_sidebar_metabox'));

		// Asset Enqueues (edit.php, Classic post.php, and Gutenberg Block Editor)
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
	}

	/**
	 * Check if the Post Insights UI feature is enabled.
	 *
	 * @return bool
	 */
	public function is_ui_enabled(): bool {
		return (bool) $this->config->get_option('aips_enable_post_insights_ui', true);
	}

	/**
	 * Get supported post types for AI Insights integration.
	 *
	 * @return string[]
	 */
	public function get_supported_post_types(): array {
		$types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		if (!in_array('post', $types, true)) {
			$types[] = 'post';
		}
		if (!in_array('page', $types, true)) {
			$types[] = 'page';
		}
		return array_unique(array_filter($types));
	}

	/**
	 * Register column and bulk action hooks for all supported post types.
	 *
	 * @return void
	 */
	public function register_post_type_table_hooks(): void {
		$post_types = $this->get_supported_post_types();

		foreach ($post_types as $pt) {
			// Column registration
			add_filter("manage_{$pt}_posts_columns", array($this, 'register_insights_column'));
			add_action("manage_{$pt}_posts_custom_column", array($this, 'render_insights_column'), 10, 2);

			// Sortable column
			add_filter("manage_edit-{$pt}_sortable_columns", array($this, 'register_sortable_column'));

			// Bulk actions
			add_filter("bulk_actions-edit-{$pt}", array($this, 'register_bulk_actions'));
			add_filter("handle_bulk_actions-edit-{$pt}", array($this, 'handle_bulk_actions'), 10, 3);
		}
	}

	/**
	 * Bulk pre-fetch AI history, embeddings, relationships, and cluster data
	 * for all posts retrieved in the current WP_Query on edit.php.
	 *
	 * Guarantees zero N+1 database queries during table row rendering.
	 *
	 * @param WP_Post[] $posts Array of post objects.
	 * @param WP_Query  $query Current query instance.
	 * @return WP_Post[]
	 */
	public function bulk_prefetch_posts_data($posts, $query) {
		if (!is_admin() || empty($posts) || !is_array($posts)) {
			return $posts;
		}

		global $pagenow;
		if ($pagenow !== 'edit.php') {
			return $posts;
		}

		$post_ids = array();
		foreach ($posts as $p) {
			if ($p instanceof WP_Post) {
				$post_ids[] = (int) $p->ID;
			}
		}

		if (empty($post_ids)) {
			return $posts;
		}

		$post_ids = array_unique($post_ids);

		// 1. Bulk pre-fetch History records via repository
		$history_map = $this->insights_repo->get_bulk_generation_details($post_ids);
		if (!empty($history_map)) {
			foreach ($history_map as $pid => $hr) {
				$this->history_cache[$pid] = (object) $hr;
			}
		}

		// 2. Bulk pre-fetch Embeddings status via repository
		$emb_map = $this->insights_repo->get_bulk_embeddings_status($post_ids);

		// 3. Bulk pre-fetch Top Duplicate Relationships via repository
		$rel_map = $this->insights_repo->get_bulk_top_duplicates($post_ids);

		// 4. Cluster membership map
		$saved_clusters = (array) $this->config->get_option('aips_post_clusters', array());

		// Populate in-memory insights cache for each post
		foreach ($post_ids as $pid) {
			$has_emb = isset($emb_map[$pid]);
			$hist = isset($history_map[$pid]) ? $history_map[$pid] : null;
			$rel = isset($rel_map[$pid]) ? $rel_map[$pid] : null;

			// Cluster lookup
			$cluster_info = null;
			foreach ($saved_clusters as $c_id => $cl) {
				$m_ids = isset($cl['member_ids']) ? (array) $cl['member_ids'] : array();
				if (in_array($pid, $m_ids, true)) {
					$is_p = isset($cl['pillar_id']) && ((int) $cl['pillar_id'] === $pid);
					$cluster_info = array(
						'cluster_id'   => $c_id,
						'name'         => isset($cl['name']) ? $cl['name'] : __('Cluster', 'ai-post-scheduler'),
						'is_pillar'    => $is_p,
						'pillar_id'    => isset($cl['pillar_id']) ? (int) $cl['pillar_id'] : 0,
					);
					break;
				}
			}

			// Duplicate risk evaluation
			$max_sim = $rel ? (float) (isset($rel['similarity']) ? $rel['similarity'] : (isset($rel['similarity_score']) ? $rel['similarity_score'] : 0.0)) : 0.0;
			$max_sim_pct = round($max_sim * 100);
			$overall_risk = 'clean';
			$overall_label = __('Clean', 'ai-post-scheduler');

			if (!$has_emb) {
				$overall_risk  = 'unindexed';
				$overall_label = __('Unindexed', 'ai-post-scheduler');
			} elseif ($max_sim >= 0.90) {
				$overall_risk  = 'critical';
				$overall_label = __('Critical Risk', 'ai-post-scheduler');
			} elseif ($max_sim >= 0.80) {
				$overall_risk  = 'high';
				$overall_label = __('High Risk', 'ai-post-scheduler');
			} elseif ($max_sim >= 0.65) {
				$overall_risk  = 'medium';
				$overall_label = __('Moderate Risk', 'ai-post-scheduler');
			}

			$this->insights_cache[$pid] = array(
				'is_indexed'         => $has_emb,
				'dimensions'         => $has_emb ? (int) $emb_map[$pid]->dimensions : 0,
				'indexed_at'         => $has_emb ? (!empty($emb_map[$pid]->indexed_at) ? $emb_map[$pid]->indexed_at : (isset($emb_map[$pid]->created_at) ? $emb_map[$pid]->created_at : null)) : null,
				'history'            => $hist,
				'cluster'            => $cluster_info,
				'matched_id'         => $rel ? (int) $rel['matched_id'] : 0,
				'max_similarity'     => $max_sim,
				'max_similarity_pct' => $max_sim_pct,
				'overall_risk'       => $overall_risk,
				'overall_label'      => $overall_label,
			);
		}

		return $posts;
	}

	/**
	 * Register the AI Insights column on native WP Posts table.
	 *
	 * Integrated with WordPress Screen Options automatically.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function register_insights_column($columns): array {
		if (!current_user_can('edit_posts')) {
			return $columns;
		}

		$new_columns = array();
		foreach ($columns as $key => $title) {
			if ($key === 'date') {
				$new_columns['aips_insights'] = __('AI Insights', 'ai-post-scheduler');
			}
			$new_columns[$key] = $title;
		}

		if (!isset($new_columns['aips_insights'])) {
			$new_columns['aips_insights'] = __('AI Insights', 'ai-post-scheduler');
		}

		return $new_columns;
	}

	/**
	 * Render the AI Insights column cell with badges and popover flyout.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id     Post ID.
	 * @return void
	 */
	public function render_insights_column($column_name, $post_id): void {
		if ($column_name !== 'aips_insights') {
			return;
		}

		$insights = isset($this->insights_cache[$post_id])
			? $this->insights_cache[$post_id]
			: $this->compile_single_post_insights($post_id);

		$overall_risk  = isset($insights['overall_risk']) ? $insights['overall_risk'] : 'clean';
		$overall_label = isset($insights['overall_label']) ? $insights['overall_label'] : __('Clean', 'ai-post-scheduler');
		$max_pct       = isset($insights['max_similarity_pct']) ? (int) $insights['max_similarity_pct'] : 0;
		$cluster       = isset($insights['cluster']) ? $insights['cluster'] : null;
		$history       = isset($insights['history']) ? $insights['history'] : null;
		$is_indexed    = !empty($insights['is_indexed']);

		$label_text = $overall_label;
		if ($max_pct > 0 && $overall_risk !== 'unindexed') {
			$label_text .= ' (' . $max_pct . '%)';
		}
		?>
		<div class="aips-insights-col-cell" data-post-id="<?php echo esc_attr((string) $post_id); ?>">
			
			<span class="aips-risk-pill aips-risk-<?php echo esc_attr($overall_risk); ?>">
				<span class="dashicons dashicons-shield"></span>
				<?php echo esc_html($label_text); ?>
			</span>

			<?php if (!empty($cluster['is_pillar'])) : ?>
				<span class="dashicons dashicons-star-filled aips-pillar-star" title="<?php esc_attr_e('Designated Pillar Post for Topic Cluster', 'ai-post-scheduler'); ?>"></span>
			<?php endif; ?>

			<button type="button" class="aips-popover-trigger" title="<?php esc_attr_e('View AI Insights Preview', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-info-outline"></span>
			</button>

			<!-- Flyout Popover Card -->
			<div class="aips-insights-popover">
				<div class="aips-popover-header">
					<strong class="aips-popover-title"><?php esc_html_e('AI Insights & Context', 'ai-post-scheduler'); ?></strong>
					<button type="button" class="aips-popover-close" title="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">&times;</button>
				</div>

				<div class="aips-popover-row">
					<span class="aips-popover-label"><?php esc_html_e('Vector Store:', 'ai-post-scheduler'); ?></span>
					<strong><?php echo $is_indexed ? esc_html__('Indexed', 'ai-post-scheduler') : esc_html__('Not Indexed', 'ai-post-scheduler'); ?></strong>
				</div>

				<?php if ($cluster) : ?>
					<div class="aips-popover-row">
						<span class="aips-popover-label"><?php esc_html_e('Post Cluster:', 'ai-post-scheduler'); ?></span>
						<span><?php echo esc_html($cluster['name']); ?><?php echo !empty($cluster['is_pillar']) ? ' <em>(' . esc_html__('Pillar', 'ai-post-scheduler') . ')</em>' : ''; ?></span>
					</div>
				<?php endif; ?>

				<?php if ($max_pct > 0 && !empty($insights['matched_id'])) : ?>
					<div class="aips-popover-row">
						<span class="aips-popover-label"><?php esc_html_e('Top Match:', 'ai-post-scheduler'); ?></span>
						<a href="<?php echo esc_url(get_edit_post_link($insights['matched_id'])); ?>" target="_blank">
							<?php echo esc_html(wp_trim_words(get_the_title($insights['matched_id']), 6)); ?>
						</a>
						<span class="aips-risk-badge aips-risk-<?php echo esc_attr($overall_risk); ?>"><?php echo esc_html((string) $max_pct); ?>%</span>
					</div>
				<?php endif; ?>

				<?php if ($history) : ?>
					<div class="aips-popover-row">
						<span class="aips-popover-label"><?php esc_html_e('Generated:', 'ai-post-scheduler'); ?></span>
						<span><?php echo esc_html(human_time_diff(strtotime($history->created_at), time())); ?> <?php esc_html_e('ago', 'ai-post-scheduler'); ?></span>
					</div>
				<?php endif; ?>

				<div class="aips-popover-actions">
					<button type="button" class="button button-small aips-reindex-post-btn" data-post-id="<?php echo esc_attr((string) $post_id); ?>">
						<span class="dashicons dashicons-update"></span>
						<?php echo $is_indexed ? esc_html__('Re-Index', 'ai-post-scheduler') : esc_html__('Index Now', 'ai-post-scheduler'); ?>
					</button>

					<?php if ($history) : 
						$hist_url = AIPS_Admin_Menu_Helper::get_page_url('history', array(
							'post_id'    => $post_id,
							'history_id' => (int) $history->id,
						));
					?>
						<a href="<?php echo esc_url($hist_url); ?>" class="button button-small aips-open-history-modal" data-history-id="<?php echo esc_attr((string) $history->id); ?>" data-post-id="<?php echo esc_attr((string) $post_id); ?>">
							<?php esc_html_e('History', 'ai-post-scheduler'); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Register sortable column for similarity.
	 *
	 * @param array $sortable_columns Existing sortables.
	 * @return array
	 */
	public function register_sortable_column($sortable_columns): array {
		$sortable_columns['aips_insights'] = 'aips_similarity';
		return $sortable_columns;
	}

	/**
	 * Render filter dropdown in table header (restrict_manage_posts).
	 *
	 * @param string $post_type
	 * @return void
	 */
	public function render_table_filters($post_type): void {
		if (!in_array($post_type, $this->get_supported_post_types(), true)) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_filter = isset($_GET['aips_status_filter']) ? sanitize_text_field(wp_unslash($_GET['aips_status_filter'])) : '';
		?>
		<select name="aips_status_filter" id="aips-status-filter">
			<option value=""><?php esc_html_e('All AI Statuses', 'ai-post-scheduler'); ?></option>
			<option value="high_risk" <?php selected($current_filter, 'high_risk'); ?>><?php esc_html_e('High Duplicate Risk (≥80%)', 'ai-post-scheduler'); ?></option>
			<option value="med_risk" <?php selected($current_filter, 'med_risk'); ?>><?php esc_html_e('Moderate Risk (65–79%)', 'ai-post-scheduler'); ?></option>
			<option value="clean" <?php selected($current_filter, 'clean'); ?>><?php esc_html_e('Clean / Low Risk (<65%)', 'ai-post-scheduler'); ?></option>
			<option value="pillar" <?php selected($current_filter, 'pillar'); ?>><?php esc_html_e('Post Cluster Pillars', 'ai-post-scheduler'); ?></option>
			<option value="ai_generated" <?php selected($current_filter, 'ai_generated'); ?>><?php esc_html_e('AI Generated Posts', 'ai-post-scheduler'); ?></option>
			<option value="unindexed" <?php selected($current_filter, 'unindexed'); ?>><?php esc_html_e('Needs Vector Indexing', 'ai-post-scheduler'); ?></option>
		</select>
		<?php
	}

	/**
	 * Handle custom query filtering & sorting on edit.php.
	 *
	 * @param WP_Query $query
	 * @return void
	 */
	public function handle_table_query_filters($query): void {
		if (!is_admin() || !$query->is_main_query()) {
			return;
		}

		global $pagenow, $wpdb;
		if ($pagenow !== 'edit.php') {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter = isset($_GET['aips_status_filter']) ? sanitize_text_field(wp_unslash($_GET['aips_status_filter'])) : '';

		if (!empty($filter)) {
			$rel_table = $wpdb->prefix . 'aips_relationships';
			$emb_table = $wpdb->prefix . 'aips_embeddings';
			$hist_table = $wpdb->prefix . 'aips_history';

			if ($filter === 'high_risk') {
				$ids = $wpdb->get_col("SELECT DISTINCT post_id_1 FROM {$rel_table} WHERE relation_type = 'similar' AND similarity_score >= 0.80 UNION SELECT DISTINCT post_id_2 FROM {$rel_table} WHERE relation_type = 'similar' AND similarity_score >= 0.80");
				$query->set('post__in', !empty($ids) ? array_map('intval', $ids) : array(0));
			} elseif ($filter === 'med_risk') {
				$ids = $wpdb->get_col("SELECT DISTINCT post_id_1 FROM {$rel_table} WHERE relation_type = 'similar' AND similarity_score >= 0.65 AND similarity_score < 0.80 UNION SELECT DISTINCT post_id_2 FROM {$rel_table} WHERE relation_type = 'similar' AND similarity_score >= 0.65 AND similarity_score < 0.80");
				$query->set('post__in', !empty($ids) ? array_map('intval', $ids) : array(0));
			} elseif ($filter === 'pillar') {
				$saved_clusters = (array) $this->config->get_option('aips_post_clusters', array());
				$pillar_ids = array();
				foreach ($saved_clusters as $c) {
					if (!empty($c['pillar_id'])) {
						$pillar_ids[] = (int) $c['pillar_id'];
					}
				}
				$query->set('post__in', !empty($pillar_ids) ? $pillar_ids : array(0));
			} elseif ($filter === 'ai_generated') {
				$ids = $wpdb->get_col("SELECT DISTINCT post_id FROM {$hist_table} WHERE post_id IS NOT NULL AND post_id > 0");
				$query->set('post__in', !empty($ids) ? array_map('intval', $ids) : array(0));
			} elseif ($filter === 'unindexed') {
				$indexed_ids = $wpdb->get_col("SELECT DISTINCT post_id FROM {$emb_table} WHERE post_id > 0");
				$query->set('post__not_in', !empty($indexed_ids) ? array_map('intval', $indexed_ids) : array(0));
			}
		}

		// Sorting
		if ($query->get('orderby') === 'aips_similarity') {
			$order = strtoupper($query->get('order')) === 'ASC' ? 'ASC' : 'DESC';
			add_filter('posts_clauses', function ($clauses) use ($order, $wpdb) {
				$rel_table = $wpdb->prefix . 'aips_relationships';
				$clauses['join'] .= " LEFT JOIN (
					SELECT post_id_1 AS rel_post_id, MAX(similarity_score) AS max_sim 
					FROM {$rel_table} 
					WHERE relation_type = 'similar' 
					GROUP BY post_id_1
				) AS aips_sim ON aips_sim.rel_post_id = {$wpdb->posts}.ID";
				$clauses['orderby'] = "COALESCE(aips_sim.max_sim, 0) {$order}, {$wpdb->posts}.post_date DESC";
				return $clauses;
			});
		}
	}

	/**
	 * Register native Bulk Actions on edit.php.
	 *
	 * @param array $bulk_actions Existing actions.
	 * @return array
	 */
	public function register_bulk_actions($bulk_actions): array {
		if (current_user_can('edit_posts')) {
			$bulk_actions['aips_bulk_index'] = __('Queue for Vector Indexing', 'ai-post-scheduler');
			$bulk_actions['aips_bulk_audit'] = __('Audit Duplicates in Content Indexer', 'ai-post-scheduler');
		}
		return $bulk_actions;
	}

	/**
	 * Handle bulk actions execution.
	 *
	 * @param string $redirect_to
	 * @param string $action
	 * @param int[]  $post_ids
	 * @return string
	 */
	public function handle_bulk_actions($redirect_to, $action, $post_ids): string {
		if ($action === 'aips_bulk_index') {
			if (!empty($post_ids)) {
				$container = AIPS_Container::get_instance();
				/** @var AIPS_Content_Indexer_Service $indexer */
				$indexer = $container->has(AIPS_Content_Indexer_Service::class)
					? $container->make(AIPS_Content_Indexer_Service::class)
					: new AIPS_Content_Indexer_Service();

				foreach ($post_ids as $pid) {
					$indexer->enqueue_post_for_indexing(absint($pid));
				}

				$redirect_to = add_query_arg('aips_queued_count', count($post_ids), $redirect_to);
			}
		} elseif ($action === 'aips_bulk_audit') {
			$redirect_to = admin_url('admin.php?page=aips-content-indexer#cannibalization-tab');
		}

		return $redirect_to;
	}

	/**
	 * Render feedback notices after bulk operations.
	 *
	 * @return void
	 */
	public function render_admin_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (!empty($_GET['aips_queued_count'])) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = absint($_GET['aips_queued_count']);
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of posts */
						esc_html__('%d posts queued for background vector indexing. Processing will begin shortly.', 'ai-post-scheduler'),
						$count
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Add History & AI quick links to native post list row actions.
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post Current post object.
	 * @return array
	 */
	public function add_post_row_action($actions, $post) {
		if (!$this->can_render_for_post($post)) {
			return $actions;
		}

		$post_id = (int) $post->ID;
		$history = $this->get_cached_history($post_id);
		$history_id = (is_object($history) && !empty($history->id)) ? absint($history->id) : 0;

		if ($history_id) {
			$history_url = $this->get_post_history_url($post_id);

			$actions['aips_history'] = sprintf(
				'<a href="%1$s" class="aips-open-history-modal" data-history-id="%2$s" data-post-id="%3$s">%4$s</a>',
				esc_url($history_url),
				esc_attr((string) $history_id),
				esc_attr((string) $post_id),
				esc_html__('History', 'ai-post-scheduler')
			);
		}

		if ($this->is_ui_enabled() && current_user_can('edit_post', $post_id)) {
			$actions['aips_scan'] = sprintf(
				'<a href="%1$s" title="%2$s">%3$s</a>',
				esc_url(admin_url('admin.php?page=aips-content-indexer#cannibalization-tab')),
				esc_attr__('Scan Semantic Duplicate Audit', 'ai-post-scheduler'),
				esc_html__('Audit Duplicates', 'ai-post-scheduler')
			);
		}

		return $actions;
	}

	/**
	 * Render History link in Classic Editor post submit box.
	 *
	 * @return void
	 */
	public function render_submitbox_action() {
		global $post;

		if (!$this->can_render_for_post($post)) {
			return;
		}

		$post_id = (int) $post->ID;
		$history = $this->get_cached_history($post_id);
		$history_id = (is_object($history) && !empty($history->id)) ? absint($history->id) : 0;

		if (!$history_id) {
			return;
		}

		$history_url = $this->get_post_history_url($post_id);
		?>
		<div class="misc-pub-section aips-post-history-link">
			<span class="dashicons dashicons-backup" aria-hidden="true"></span>
			<a href="<?php echo esc_url($history_url); ?>"
			   class="aips-open-history-modal"
			   data-history-id="<?php echo esc_attr((string) $history_id); ?>"
			   data-post-id="<?php echo esc_attr((string) $post_id); ?>">
				<?php esc_html_e('View AI History', 'ai-post-scheduler'); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Register sidebar Meta Box for the Classic Editor.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function register_sidebar_metabox($post_type): void {
		if (!in_array($post_type, $this->get_supported_post_types(), true)) {
			return;
		}

		add_meta_box(
			'aips_post_ai_insights',
			__('AI Insights & Duplication', 'ai-post-scheduler'),
			array($this, 'render_sidebar_metabox'),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * Render the Classic Editor sidebar meta box.
	 *
	 * @param WP_Post $post
	 * @return void
	 */
	public function render_sidebar_metabox($post): void {
		$post_id = (int) $post->ID;
		$insights = $this->compile_single_post_insights($post_id);

		$template_path = AIPS_PLUGIN_DIR . 'templates/admin/post-insights-metabox.php';
		if (file_exists($template_path)) {
			include $template_path;
		}
	}

	/**
	 * Enqueue assets for edit.php and Classic Editor post.php.
	 *
	 * @param string $hook_suffix
	 * @return void
	 */
	public function enqueue_admin_assets($hook_suffix): void {
		if ($hook_suffix !== 'edit.php' && $hook_suffix !== 'post.php') {
			return;
		}

		wp_enqueue_style(
			'aips-admin-post-insights',
			AIPS_PLUGIN_URL . 'assets/css/admin-post-insights.css',
			array('dashicons'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-admin-post-insights-table',
			AIPS_PLUGIN_URL . 'assets/js/admin-post-insights-table.js',
			array('jquery'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-post-insights-table', 'aipsPostInsightsL10n', array(
			'nonce'   => wp_create_nonce('aips_insights_nonce'),
			'ajaxurl' => admin_url('admin-ajax.php'),
		));
	}

	/**
	 * Enqueue assets for the Gutenberg Block Editor.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets(): void {
		global $post;
		if (!($post instanceof WP_Post) || !current_user_can('edit_post', $post->ID)) {
			return;
		}

		if (!in_array($post->post_type, $this->get_supported_post_types(), true)) {
			return;
		}

		wp_enqueue_style(
			'aips-admin-post-insights',
			AIPS_PLUGIN_URL . 'assets/css/admin-post-insights.css',
			array('wp-components'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-admin-post-insights-gutenberg',
			AIPS_PLUGIN_URL . 'assets/js/admin-post-insights-gutenberg.js',
			array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'jquery'),
			AIPS_VERSION,
			true
		);

		$insights = $this->compile_single_post_insights($post->ID);

		wp_localize_script('aips-admin-post-insights-gutenberg', 'aipsPostInsightsL10n', array(
			'postId'            => $post->ID,
			'nonce'             => wp_create_nonce('aips_insights_nonce'),
			'ajaxurl'           => admin_url('admin-ajax.php'),
			'insights'          => $insights,
			'panelTitle'        => __('AI Insights & Duplication', 'ai-post-scheduler'),
			'vectorTitle'       => __('Vector Embedding', 'ai-post-scheduler'),
			'clusterTitle'      => __('Post Cluster', 'ai-post-scheduler'),
			'duplicatesTitle'   => __('Semantic Duplicate Risk', 'ai-post-scheduler'),
			'contextTitle'      => __('AI Generation Context', 'ai-post-scheduler'),
			'cleanLabel'        => __('Clean', 'ai-post-scheduler'),
			'pillarLabel'       => __('Pillar Post', 'ai-post-scheduler'),
			'reindexBtn'        => __('Re-Index Vector', 'ai-post-scheduler'),
			'indexNowBtn'       => __('Index Now', 'ai-post-scheduler'),
			'designatedPillar'  => __('Designated Pillar', 'ai-post-scheduler'),
			'setAsPillar'       => __('Set as Pillar', 'ai-post-scheduler'),
			'noDuplicates'      => __('No conflicting duplicates detected.', 'ai-post-scheduler'),
			'viewHistoryBtn'    => __('View AI Generation History', 'ai-post-scheduler'),
			'reindexedSuccess'  => __('Vector re-indexed successfully.', 'ai-post-scheduler'),
		));
	}

	/**
	 * Compile insights for a single post on demand.
	 *
	 * @param int $post_id
	 * @return array
	 */
	public function compile_single_post_insights(int $post_id): array {
		$controller = new AIPS_Post_Insights_Controller();
		return $controller->compile_post_insights($post_id);
	}

	/**
	 * Return a cached history object for a given post, performing the DB
	 * lookup at most once per post per request.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	private function get_cached_history($post_id) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return null;
		}
		if (!array_key_exists($post_id, $this->history_cache)) {
			$this->history_cache[$post_id] = $this->history_repository->get_by_post_id($post_id);
		}
		return $this->history_cache[$post_id];
	}

	/**
	 * Build the History admin page URL for a post, including query args for
	 * deep-linking to the correct history entry.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_post_history_url($post_id) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return '';
		}

		$history = $this->get_cached_history($post_id);
		$args = array(
			'post_id' => $post_id,
		);

		if (is_object($history) && !empty($history->id)) {
			$args['history_id'] = absint($history->id);
		}

		return AIPS_Admin_Menu_Helper::get_page_url('history', $args);
	}

	/**
	 * Determine whether History UI should be shown for a post.
	 *
	 * @param WP_Post|mixed $post Post object.
	 * @return bool
	 */
	private function can_render_for_post($post) {
		if (!current_user_can('edit_posts')) {
			return false;
		}

		if (!($post instanceof WP_Post)) {
			return false;
		}

		return true;
	}
}

// Class alias for architectural clarity
if (!class_exists('AIPS_Post_Insights_UI', false)) {
	class_alias('AIPS_Post_History_UI', 'AIPS_Post_Insights_UI');
}
