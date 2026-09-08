<?php
/**
 * Authors List Table Class
 *
 * Implements WordPress WP_List_Table for the Authors hub.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Authors_List_Table
 */
class AIPS_Authors_List_Table extends AIPS_List_Table {

	/**
	 * Authors repository instance.
	 *
	 * @var AIPS_Authors_Repository
	 */
	protected $authors_repository;

	/**
	 * Topics repository instance.
	 *
	 * @var AIPS_Author_Topics_Repository
	 */
	protected $topics_repository;

	/**
	 * Logs repository instance.
	 *
	 * @var AIPS_Author_Topic_Logs_Repository
	 */
	protected $logs_repository;

	/**
	 * Bulk feedback statistics.
	 *
	 * @var array<int, array{total:int, approved:int, rejected:int}>
	 */
	protected $all_feedback_stats = array();

	/**
	 * Total items count across statuses.
	 *
	 * @var array{all:int, active:int, inactive:int}
	 */
	protected $counts = array(
		'all'      => 0,
		'active'   => 0,
		'inactive' => 0,
	);

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $args Configuration arguments.
	 */
	public function __construct($args = array()) {
		parent::__construct(wp_parse_args($args, array(
			'singular'  => 'author',
			'plural'    => 'authors',
			'page_slug' => 'aips-automations',
			'tab_slug'  => 'authors',
		)));

		$this->authors_repository = new AIPS_Authors_Repository();
		$this->topics_repository  = new AIPS_Author_Topics_Repository();
		$this->logs_repository    = new AIPS_Author_Topic_Logs_Repository();
	}

	/**
	 * Define columns for the Authors table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" id="aips-authors-select-all" />',
			'quality' => '<span class="screen-reader-text">' . esc_html__('Quality', 'ai-post-scheduler') . '</span>',
			'name'    => esc_html__('Author Name', 'ai-post-scheduler'),
			'status'  => esc_html__('Status', 'ai-post-scheduler'),
			'topics'  => esc_html__('Topics', 'ai-post-scheduler'),
			'posts'   => esc_html__('Posts', 'ai-post-scheduler'),
			'actions' => esc_html__('Actions', 'ai-post-scheduler'),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @return array<string, array{string, bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'name'   => array('name', false),
			'status' => array('status', false),
		);
	}

	/**
	 * Define bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'generate_topics' => __('Generate Topics', 'ai-post-scheduler'),
			'delete'          => __('Delete', 'ai-post-scheduler'),
		);
	}

	/**
	 * Define status filter views.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current_view = isset($_GET['author_status']) ? sanitize_key(wp_unslash($_GET['author_status'])) : 'all';
		$base_url     = add_query_arg(array('page' => $this->page_slug, 'tab' => $this->tab_slug), admin_url('admin.php'));

		$views = array();

		// All view
		$all_class = ('all' === $current_view) ? 'current' : '';
		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url(remove_query_arg('author_status', $base_url)),
			$all_class,
			esc_html__('All', 'ai-post-scheduler'),
			$this->counts['all']
		);

		// Active view
		$active_class = ('active' === $current_view) ? 'current' : '';
		$views['active'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url(add_query_arg('author_status', 'active', $base_url)),
			$active_class,
			esc_html__('Active', 'ai-post-scheduler'),
			$this->counts['active']
		);

		// Inactive view
		$inactive_class = ('inactive' === $current_view) ? 'current' : '';
		$views['inactive'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url(add_query_arg('author_status', 'inactive', $base_url)),
			$inactive_class,
			esc_html__('Inactive', 'ai-post-scheduler'),
			$this->counts['inactive']
		);

		return $views;
	}

	/**
	 * Prepare authors data items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$all_authors = $this->authors_repository->get_all();

		// Count statuses
		$this->counts['all'] = count($all_authors);
		$active_count = 0;
		$inactive_count = 0;
		foreach ($all_authors as $author) {
			if (!empty($author->status) && 'active' === $author->status) {
				$active_count++;
			} else {
				$inactive_count++;
			}
		}
		$this->counts['active']   = $active_count;
		$this->counts['inactive'] = $inactive_count;

		// Bulk prefetch feedback statistics to avoid N+1 queries
		if (!empty($all_authors)) {
			$feedback_repository = new AIPS_Feedback_Repository();
			$author_ids = array_map(function($a) { return (int) $a->id; }, $all_authors);
			$this->all_feedback_stats = $feedback_repository->get_statistics_bulk($author_ids);
		}

		// Filter by status if requested
		$status_filter = isset($_GET['author_status']) ? sanitize_key(wp_unslash($_GET['author_status'])) : 'all';
		$filtered_authors = array();
		foreach ($all_authors as $author) {
			$is_active = (!empty($author->status) && 'active' === $author->status);
			if ('active' === $status_filter && !$is_active) {
				continue;
			}
			if ('inactive' === $status_filter && $is_active) {
				continue;
			}

			// Filter by search query if present
			$search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
			if (!empty($search)) {
				$term = strtolower($search);
				$author_name = strtolower($author->name ?? '');
				$author_desc = strtolower($author->description ?? '');
				if (strpos($author_name, $term) === false && strpos($author_desc, $term) === false) {
					continue;
				}
			}

			$filtered_authors[] = $author;
		}

		// Sorting
		$orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'name';
		$order   = isset($_GET['order']) && 'desc' === strtolower(sanitize_key(wp_unslash($_GET['order']))) ? 'desc' : 'asc';

		usort($filtered_authors, function($a, $b) use ($orderby, $order) {
			if ('status' === $orderby) {
				$res = strcmp($a->status ?? '', $b->status ?? '');
			} else {
				$res = strcasecmp($a->name ?? '', $b->name ?? '');
			}
			return ('desc' === $order) ? -$res : $res;
		});

		$this->items = $filtered_authors;

		$this->set_pagination_args(array(
			'total_items' => count($filtered_authors),
			'per_page'    => count($filtered_authors),
			'total_pages' => 1,
		));
	}

	/**
	 * Checkbox column rendering.
	 *
	 * @param object $item Author row object.
	 * @return string
	 */
	protected function column_cb($item) {
		return sprintf(
			'<input type="checkbox" name="author_ids[]" value="%d" class="aips-author-checkbox" aria-label="%s" />',
			absint($item->id),
			esc_attr(sprintf(__('Select %s', 'ai-post-scheduler'), $item->name))
		);
	}

	/**
	 * Quality score indicator column rendering.
	 *
	 * @param object $item Author row object.
	 * @return string
	 */
	protected function column_quality($item) {
		$author_details = !empty($item->details) ? json_decode($item->details, true) : array();
		$policy_flags   = (is_array($author_details) && isset($author_details['policy_flags']) && is_array($author_details['policy_flags'])) ? $author_details['policy_flags'] : array();
		$policy_flags_count = count($policy_flags);

		$feedback_stats    = isset($this->all_feedback_stats[$item->id]) ? $this->all_feedback_stats[$item->id] : array('total' => 0, 'approved' => 0, 'rejected' => 0);
		$feedback_total    = (int) $feedback_stats['total'];
		$feedback_approved = (int) $feedback_stats['approved'];
		$approval_rate     = $feedback_total > 0 ? round(($feedback_approved / $feedback_total) * 100) : null;
		$approval_comp     = $approval_rate !== null ? (int) $approval_rate : 50;
		$policy_penalty    = min(60, $policy_flags_count * 20);
		$quality_score     = max(0, min(100, $approval_comp - $policy_penalty));

		if ($policy_flags_count >= 3 || ($approval_rate !== null && $approval_rate < 50)) {
			$quality_state = 'critical';
			$quality_color = '#d63638';
			$quality_label = __('Critical Quality Issue', 'ai-post-scheduler');
		} elseif ($policy_flags_count >= 1 || ($approval_rate !== null && $approval_rate < 75)) {
			$quality_state = 'warning';
			$quality_color = '#dba617';
			$quality_label = __('Quality Attention Needed', 'ai-post-scheduler');
		} else {
			$quality_state = 'healthy';
			$quality_color = '#00a32a';
			$quality_label = __('Healthy Author', 'ai-post-scheduler');
		}

		return sprintf(
			'<span class="aips-quality-indicator aips-quality-%s" title="%s" style="display:inline-block;width:10px;height:10px;border-radius:50%%;background-color:%s;" aria-label="%s"></span>',
			esc_attr($quality_state),
			esc_attr($quality_label),
			esc_attr($quality_color),
			esc_attr($quality_label)
		);
	}

	/**
	 * Author name column rendering with persona details and row actions.
	 *
	 * @param object $item Author row object.
	 * @return string
	 */
	protected function column_name($item) {
		$author_id = (int) $item->id;
		$topics_url = add_query_arg(array(
			'page'      => 'aips-automations',
			'tab'       => 'author-topics',
			'author_id' => $author_id,
		), admin_url('admin.php'));

		$html  = '<div class="cell-primary">';
		$html .= '<strong><a href="' . esc_url($topics_url) . '">' . esc_html($item->name) . '</a></strong>';
		$html .= '</div>';

		if (!empty($item->description)) {
			$html .= '<div class="cell-meta aips-muted">' . esc_html($item->description) . '</div>';
		}

		// WordPress native row actions
		$actions = array(
			'topics'         => sprintf('<a href="%s">%s</a>', esc_url($topics_url), esc_html__('Manage Topics', 'ai-post-scheduler')),
			'generate_topics'=> sprintf('<a href="#" class="aips-generate-topics-now" data-author-id="%d" data-author-name="%s">%s</a>', $author_id, esc_attr($item->name), esc_html__('Generate Topics', 'ai-post-scheduler')),
			'generate_posts' => sprintf('<a href="#" class="aips-generate-author-posts-now" data-author-id="%d" data-author-name="%s">%s</a>', $author_id, esc_attr($item->name), esc_html__('Generate Posts', 'ai-post-scheduler')),
			'edit'           => sprintf('<a href="#" class="aips-edit-author" data-author-id="%d">%s</a>', $author_id, esc_html__('Edit', 'ai-post-scheduler')),
			'delete'         => sprintf('<a href="#" class="aips-delete-author aips-link-danger" data-author-id="%d" data-author-name="%s">%s</a>', $author_id, esc_attr($item->name), esc_html__('Delete', 'ai-post-scheduler')),
		);

		$html .= $this->row_actions($actions);

		return $html;
	}

	/**
	 * Status column rendering.
	 *
	 * @param object $item Author row object.
	 * @return string
	 */
	protected function column_status($item) {
		$is_active = (!empty($item->status) && 'active' === $item->status);
		if ($is_active) {
			return $this->render_status_badge(__('Active', 'ai-post-scheduler'), 'success', 'dashicons-yes');
		}
		return $this->render_status_badge(__('Inactive', 'ai-post-scheduler'), 'neutral', 'dashicons-minus');
	}

	/**
	 * Topics count breakdown column rendering.
	 *
	 * @param object $item Author row object.
	 * @return string
	 */
	protected function column_topics($item) {
		$author_id     = (int) $item->id;
		$status_counts = $this->topics_repository->get_status_counts($author_id);
		$total_topics  = $status_counts['pending'] + $status_counts['approved'] + $status_counts['rejected'];

		$topics_url = add_query_arg(array(
			'page'      => 'aips-automations',
			'tab'       => 'author-topics',
			'author_id' => $author_id,
		), admin_url('admin.php'));

		$html  = '<div class="aips-topic-pills">';
		$html .= '<a href="' . esc_url($topics_url) . '" class="aips-badge aips-badge-secondary" title="' . esc_attr__('View all topics', 'ai-post-scheduler') . '">';
		$html .= '<span class="dashicons dashicons-visibility" aria-hidden="true"></span> ' . sprintf(esc_html__('%d Topics', 'ai-post-scheduler'), $total_topics);
		$html .= '</a>';
		$html .= '<div class="cell-meta" style="font-size:11px;margin-top:4px;">';
		$html .= '<span style="color:#d63638;">' . sprintf(esc_html__('%d pending', 'ai-post-scheduler'), $status_counts['pending']) . '</span> | ';
		$html .= '<span style="color:#00a32a;">' . sprintf(esc_html__('%d approved', 'ai-post-scheduler'), $status_counts['approved']) . '</span> | ';
		$html .= '<span style="color:#646970;">' . sprintf(esc_html__('%d rejected', 'ai-post-scheduler'), $status_counts['rejected']) . '</span>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generated posts count column rendering.
	 *
	 * @param object $item Author row object.
	 * @return string
	 */
	protected function column_posts($item) {
		$posts_count = $this->logs_repository->count_generated_posts_by_author((int) $item->id);
		return sprintf(
			'<span class="aips-badge aips-badge-neutral"><span class="dashicons dashicons-admin-post" aria-hidden="true"></span> %s</span>',
			sprintf(esc_html__('%d Posts', 'ai-post-scheduler'), $posts_count)
		);
	}

	/**
	 * Action buttons column rendering.
	 *
	 * @param object $item Author row object.
	 * @return string
	 */
	protected function column_actions($item) {
		$author_id   = (int) $item->id;
		$author_name = esc_attr($item->name ?? '');

		$html  = '<div class="cell-actions" style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-generate-topics-now" data-author-id="' . $author_id . '" data-author-name="' . $author_name . '" title="' . esc_attr__('Generate Topics Now', 'ai-post-scheduler') . '"><span class="dashicons dashicons-update" aria-hidden="true"></span> ' . esc_html__('Generate Topics', 'ai-post-scheduler') . '</button>';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-success aips-generate-author-posts-now" data-author-id="' . $author_id . '" data-author-name="' . $author_name . '" title="' . esc_attr__('Generate Posts Now', 'ai-post-scheduler') . '"><span class="dashicons dashicons-admin-post" aria-hidden="true"></span> ' . esc_html__('Generate Posts', 'ai-post-scheduler') . '</button>';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-author" data-author-id="' . $author_id . '" title="' . esc_attr__('Edit Author', 'ai-post-scheduler') . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span> ' . esc_html__('Edit', 'ai-post-scheduler') . '</button>';
		$html .= '<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-delete-author" data-author-id="' . $author_id . '" data-author-name="' . $author_name . '" title="' . esc_attr__('Delete Author', 'ai-post-scheduler') . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Default column fallback.
	 *
	 * @param object $item        Author row object.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default($item, $column_name) {
		return isset($item->$column_name) ? esc_html($item->$column_name) : '—';
	}
}
