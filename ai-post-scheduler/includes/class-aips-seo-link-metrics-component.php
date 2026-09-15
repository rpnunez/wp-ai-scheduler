<?php
/**
 * SEO Link Metrics Reusable Component
 *
 * Provides batch priming, metric calculations, and presentation badges/cards
 * for internal link graph data across all administrative views.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_SEO_Link_Metrics_Component
 */
class AIPS_SEO_Link_Metrics_Component {

	/**
	 * Inbound link counts cache keyed by post ID.
	 *
	 * @var array<int, int>
	 */
	private static $inbound_cache = array();

	/**
	 * Outbound link counts cache keyed by post ID.
	 *
	 * @var array<int, int>
	 */
	private static $outbound_cache = array();

	/**
	 * Memoized summary statistics.
	 *
	 * @var array|null
	 */
	private static $summary_cache = null;

	/**
	 * Flag indicating whether inline CSS has been rendered.
	 *
	 * @var bool
	 */
	private static $styles_rendered = false;

	/**
	 * Get Content Links Repository instance.
	 *
	 * @return AIPS_Content_Links_Repository
	 */
	protected static function get_repository() {
		$container = AIPS_Container::get_instance();
		if ($container->has(AIPS_Content_Links_Repository::class)) {
			return $container->make(AIPS_Content_Links_Repository::class);
		}
		return new AIPS_Content_Links_Repository();
	}

	/**
	 * Batch prime link counts for an array of post IDs to avoid N+1 queries.
	 *
	 * Executes at most 2 queries regardless of the number of post IDs.
	 *
	 * @param array $post_ids List of post IDs.
	 * @return void
	 */
	public static function prime_batch_counts(array $post_ids) {
		$clean_ids = array_values(array_unique(array_filter(array_map('absint', $post_ids))));
		if (empty($clean_ids)) {
			return;
		}

		$needed_in  = array();
		$needed_out = array();

		foreach ($clean_ids as $id) {
			if (!isset(self::$inbound_cache[$id])) {
				$needed_in[] = $id;
			}
			if (!isset(self::$outbound_cache[$id])) {
				$needed_out[] = $id;
			}
		}

		if (empty($needed_in) && empty($needed_out)) {
			return;
		}

		$repo = self::get_repository();

		if (!empty($needed_in)) {
			$inbound_map = $repo->get_inbound_counts($needed_in);
			foreach ($inbound_map as $id => $cnt) {
				self::$inbound_cache[$id] = (int) $cnt;
			}
		}

		if (!empty($needed_out)) {
			$outbound_map = $repo->get_outbound_counts($needed_out);
			foreach ($outbound_map as $id => $cnt) {
				self::$outbound_cache[$id] = (int) $cnt;
			}
		}
	}

	/**
	 * Retrieve calculated SEO metrics for a specific post.
	 *
	 * @param int|WP_Post $post Post ID or WP_Post instance.
	 * @return array Metric descriptor.
	 */
	public static function get_post_metrics($post) {
		$post_id = ($post instanceof WP_Post) ? (int) $post->ID : absint($post);
		if ($post_id <= 0) {
			return array(
				'post_id'        => 0,
				'inbound_count'  => 0,
				'outbound_count' => 0,
				'is_orphan'      => true,
				'equity_tier'    => 'orphan',
			);
		}

		if (!isset(self::$inbound_cache[$post_id]) || !isset(self::$outbound_cache[$post_id])) {
			self::prime_batch_counts(array($post_id));
		}

		$inbound  = self::$inbound_cache[$post_id] ?? 0;
		$outbound = self::$outbound_cache[$post_id] ?? 0;
		$is_orphan = ($inbound === 0);

		return array(
			'post_id'        => $post_id,
			'inbound_count'  => $inbound,
			'outbound_count' => $outbound,
			'is_orphan'      => $is_orphan,
			'equity_tier'    => self::calculate_equity_tier($inbound),
		);
	}

	/**
	 * Determine link equity tier from inbound link count.
	 *
	 * @param int $inbound Inbound count.
	 * @return string Tier identifier.
	 */
	public static function calculate_equity_tier($inbound) {
		$inbound = (int) $inbound;
		if ($inbound <= 0) {
			return 'orphan';
		}
		if ($inbound <= 2) {
			return 'low';
		}
		if ($inbound <= 5) {
			return 'moderate';
		}
		return 'high_hub';
	}

	/**
	 * Count orphan published posts for a given post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return int Total orphan posts.
	 */
	public static function get_orphan_count($post_type = 'post') {
		$repo = self::get_repository();
		return $repo->count_orphan_posts($post_type);
	}

	/**
	 * Compute global SEO link graph summary statistics.
	 *
	 * @return array Summary dictionary.
	 */
	public static function get_summary_stats() {
		if (self::$summary_cache !== null) {
			return self::$summary_cache;
		}

		$repo = self::get_repository();

		// 1. Published posts count
		$post_counts = wp_count_posts('post');
		$page_counts = wp_count_posts('page');
		$total_published = (int) ($post_counts->publish ?? 0) + (int) ($page_counts->publish ?? 0);

		// 2. Directed internal link edges
		$total_edges = $repo->get_total_links_count();

		// 3. Total orphan content
		$orphan_posts = $repo->count_orphan_posts('post');
		$orphan_pages = $repo->count_orphan_posts('page');
		$total_orphans = $orphan_posts + $orphan_pages;

		// 4. Graph depths / deep hierarchy
		$deep_count = 0;
		try {
			$graph_service = new AIPS_Link_Graph_Service($repo);
			$depths        = $graph_service->get_all_graph_depths();
			foreach ($depths as $d) {
				if ($d >= 3) {
					$deep_count++;
				}
			}
		} catch (Exception $e) {
			$deep_count = 0;
		}

		self::$summary_cache = array(
			'total_published' => $total_published,
			'total_edges'     => $total_edges,
			'total_orphans'   => $total_orphans,
			'deep_count'      => $deep_count,
		);

		return self::$summary_cache;
	}

	/**
	 * Render compact internal links badge markup.
	 *
	 * @param int    $inbound     Inbound link count.
	 * @param int    $outbound    Outbound link count.
	 * @param bool   $is_orphan   Whether post is an orphan.
	 * @param string $equity_tier Link equity tier.
	 * @param bool   $echo        Whether to echo output.
	 * @return string Badge HTML.
	 */
	public static function render_badge($inbound, $outbound, $is_orphan = false, $equity_tier = '', $echo = true) {
		$inbound  = max(0, (int) $inbound);
		$outbound = max(0, (int) $outbound);

		if (empty($equity_tier)) {
			$equity_tier = self::calculate_equity_tier($inbound);
		}

		ob_start();
		?>
		<div class="aips-link-metrics-badge" data-equity-tier="<?php echo esc_attr($equity_tier); ?>">
			<span class="aips-badge-counts">
				<span class="aips-link-count aips-link-in" title="<?php esc_attr_e('Inbound internal links', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					<span class="aips-count-label"><?php esc_html_e('In:', 'ai-post-scheduler'); ?></span>
					<strong><?php echo esc_html($inbound); ?></strong>
				</span>
				<span class="aips-link-sep" aria-hidden="true">|</span>
				<span class="aips-link-count aips-link-out" title="<?php esc_attr_e('Outbound internal links', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
					<span class="aips-count-label"><?php esc_html_e('Out:', 'ai-post-scheduler'); ?></span>
					<strong><?php echo esc_html($outbound); ?></strong>
				</span>
			</span>
			<?php if ($is_orphan): ?>
				<span class="aips-badge aips-badge-error aips-pill-status aips-pill-orphan" title="<?php esc_attr_e('Orphan Content: 0 inbound internal links pointing to this post', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<?php esc_html_e('Orphan', 'ai-post-scheduler'); ?>
				</span>
			<?php elseif ($inbound >= 5): ?>
				<span class="aips-badge aips-badge-success aips-pill-status aips-pill-hub" title="<?php esc_attr_e('Internal Link Hub: 5+ inbound internal links receive high PageRank equity', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
					<?php esc_html_e('Hub', 'ai-post-scheduler'); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php
		$html = ob_get_clean();

		if ($echo) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $html;
	}

	/**
	 * Render badge for a specific post.
	 *
	 * @param int|WP_Post $post Post ID or WP_Post instance.
	 * @param bool        $echo Whether to echo output.
	 * @return string Badge HTML.
	 */
	public static function render_post_badge($post, $echo = true) {
		$metrics = self::get_post_metrics($post);
		return self::render_badge(
			$metrics['inbound_count'],
			$metrics['outbound_count'],
			$metrics['is_orphan'],
			$metrics['equity_tier'],
			$echo
		);
	}

	/**
	 * Render overview summary cards for dashboard and monitoring views.
	 *
	 * @param array $summary Optional summary dictionary.
	 * @param bool  $echo    Whether to echo output.
	 * @return string Cards HTML markup.
	 */
	public static function render_summary_cards(array $summary = array(), $echo = true) {
		if (empty($summary)) {
			$summary = self::get_summary_stats();
		}

		$total_published = (int) ($summary['total_published'] ?? 0);
		$total_edges     = (int) ($summary['total_edges'] ?? 0);
		$total_orphans   = (int) ($summary['total_orphans'] ?? 0);
		$deep_count      = (int) ($summary['deep_count'] ?? 0);

		ob_start();
		?>
		<div class="aips-grid aips-grid-cols-4 aips-seo-summary-grid">
			<!-- Published Content -->
			<div class="aips-stat-card glass-morphic aips-stat-info">
				<div class="aips-stat-icon-wrap">
					<span class="dashicons dashicons-admin-post" aria-hidden="true"></span>
				</div>
				<div class="aips-stat-content">
					<span class="aips-stat-label"><?php esc_html_e('Published Content', 'ai-post-scheduler'); ?></span>
					<strong class="aips-stat-value"><?php echo esc_html($total_published); ?></strong>
					<span class="aips-stat-sub-meta">
						<?php esc_html_e('Monitored for SEO Link Flow', 'ai-post-scheduler'); ?>
					</span>
				</div>
			</div>

			<!-- Directed Edges -->
			<div class="aips-stat-card glass-morphic aips-stat-success">
				<div class="aips-stat-icon-wrap">
					<span class="dashicons dashicons-networking" aria-hidden="true"></span>
				</div>
				<div class="aips-stat-content">
					<span class="aips-stat-label"><?php esc_html_e('Internal Links', 'ai-post-scheduler'); ?></span>
					<strong class="aips-stat-value"><?php echo esc_html($total_edges); ?></strong>
					<span class="aips-stat-sub-meta">
						<?php esc_html_e('Directed Content Links', 'ai-post-scheduler'); ?>
					</span>
				</div>
			</div>

			<!-- Orphan Posts -->
			<div class="aips-stat-card glass-morphic aips-stat-danger">
				<div class="aips-stat-icon-wrap">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				</div>
				<div class="aips-stat-content">
					<span class="aips-stat-label"><?php esc_html_e('Orphan Posts', 'ai-post-scheduler'); ?></span>
					<strong class="aips-stat-value"><?php echo esc_html($total_orphans); ?></strong>
					<span class="aips-stat-sub-meta">
						<?php esc_html_e('0 Inbound Links Detected', 'ai-post-scheduler'); ?>
					</span>
				</div>
			</div>

			<!-- Deep Hierarchy -->
			<div class="aips-stat-card glass-morphic aips-stat-warning">
				<div class="aips-stat-icon-wrap">
					<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
				</div>
				<div class="aips-stat-content">
					<span class="aips-stat-label"><?php esc_html_e('Deep / Disconnected', 'ai-post-scheduler'); ?></span>
					<strong class="aips-stat-value"><?php echo esc_html($deep_count); ?></strong>
					<span class="aips-stat-sub-meta">
						<?php esc_html_e('Depth ≥ 3 or Unreachable', 'ai-post-scheduler'); ?>
					</span>
				</div>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		if ($echo) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $html;
	}

	/**
	 * Print compact CSS styles once for WordPress list tables and admin views.
	 *
	 * @return void
	 */
	public static function render_inline_styles() {
		if (self::$styles_rendered) {
			return;
		}
		self::$styles_rendered = true;
		?>
		<style id="aips-seo-link-metrics-css">
			.column-aips_internal_links { width: 140px; text-align: left; }
			.aips-link-metrics-badge {
				display: inline-flex;
				flex-direction: column;
				align-items: flex-start;
				gap: 3px;
				font-size: 11px;
				line-height: 1.3;
			}
			.aips-badge-counts {
				display: inline-flex;
				align-items: center;
				gap: 4px;
				background: #f0f0f1;
				padding: 2px 6px;
				border-radius: 4px;
				color: #2c3338;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.aips-link-count {
				display: inline-flex;
				align-items: center;
				gap: 2px;
			}
			.aips-link-count .dashicons {
				font-size: 12px;
				width: 12px;
				height: 12px;
			}
			.aips-link-in .dashicons { color: #2271b1; }
			.aips-link-out .dashicons { color: #135e96; }
			.aips-link-sep { color: #c3c4c7; font-weight: normal; }
			.aips-pill-status {
				display: inline-flex;
				align-items: center;
				gap: 3px;
				padding: 1px 5px;
				font-size: 10px;
				font-weight: 600;
				border-radius: 3px;
				text-transform: uppercase;
				letter-spacing: 0.3px;
			}
			.aips-pill-orphan {
				background: #fcf0f1;
				color: #d63638;
				border: 1px solid #f8d7da;
			}
			.aips-pill-orphan .dashicons {
				font-size: 11px;
				width: 11px;
				height: 11px;
			}
			.aips-pill-hub {
				background: #edfaef;
				color: #008a20;
				border: 1px solid #c3e6cb;
			}
			.aips-pill-hub .dashicons {
				font-size: 11px;
				width: 11px;
				height: 11px;
			}
		</style>
		<?php
	}
}
