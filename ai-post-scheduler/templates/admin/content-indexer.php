<?php
/**
 * Content Indexer Admin Page
 *
 * Provides central embeddings management: backfill scanner, interactive semantic graph visualizer,
 * cannibalization audit, and post type configuration.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// Variables injected by AIPS_Content_Indexer_Controller:
// $status, $stats, $all_post_types, $settings

$total_posts = isset($status['total_posts']) ? (int) $status['total_posts'] : 0;
$indexed     = isset($status['indexed']) ? (int) $status['indexed'] : 0;
$unindexed   = isset($status['unindexed']) ? (int) $status['unindexed'] : 0;
$percent     = isset($status['percent']) ? (int) $status['percent'] : 0;

$topic_count = isset($stats['topics']) ? (int) $stats['topics'] : 0;
$active_model = !empty($stats['models']) ? $stats['models'][0]->model : 'Default (AI Engine)';
$active_dims  = !empty($stats['models']) ? (int) $stats['models'][0]->dimensions : 1536;
?>

<div class="wrap aips-wrap aips-indexer-page">
	<div class="aips-page-container">

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title">
						<span class="dashicons dashicons-networking aips-indexer-title-icon"></span>
						<?php esc_html_e('Content Indexer & Semantic Intelligence', 'ai-post-scheduler'); ?>
					</h1>
					<p class="aips-page-description">
						<?php esc_html_e('Centralized semantic vector store for backfilling existing posts, exploring relationship graphs, detecting duplicate content, and powering related posts.', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<div class="aips-page-actions">
					<button type="button" id="aips-start-indexing-btn" class="aips-btn aips-btn-primary">
						<span class="dashicons dashicons-database-import"></span>
						<span class="btn-text"><?php esc_html_e('Start Backfill Scan', 'ai-post-scheduler'); ?></span>
					</button>
					<button type="button" id="aips-pause-indexing-btn" class="aips-btn aips-btn-secondary aips-hidden">
						<span class="dashicons dashicons-controls-pause"></span>
						<?php esc_html_e('Pause', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" id="aips-clear-index-btn" class="aips-btn aips-btn-ghost aips-btn-danger">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e('Clear Index', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Embeddings Disabled Notice -->
		<?php if (empty($settings['embeddings_enabled'])) : ?>
		<div class="notice notice-info inline aips-embeddings-disabled-banner">
			<div class="aips-banner-inner">
				<div>
					<h4 class="aips-banner-title">
						<span class="dashicons dashicons-info aips-banner-icon"></span>
						<?php esc_html_e('Vector Embeddings System is Currently Disabled', 'ai-post-scheduler'); ?>
					</h4>
					<p class="aips-banner-desc">
						<?php esc_html_e('Automatic indexing, continuous sync, and semantic vector similarity checks are turned off. You can re-enable the embeddings engine anytime in the Settings & Thresholds tab.', 'ai-post-scheduler'); ?>
					</p>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Dimension Mismatch Notice -->
		<?php if (!empty($dimension_mismatch)) : ?>
		<div class="notice notice-warning inline aips-dimension-mismatch-banner">
			<div class="aips-banner-inner">
				<div>
					<h4 class="aips-banner-title">
						<span class="dashicons dashicons-warning aips-banner-icon"></span>
						<?php esc_html_e('Vector Dimension Mismatch Detected', 'ai-post-scheduler'); ?>
					</h4>
					<p class="aips-banner-desc">
						<?php
						printf(
							/* translators: 1: stored dimensions, 2: active dimensions */
							esc_html__('Stored vector embeddings use %1$s dimensions, but your active environment is configured for %2$s dimensions. Cosine similarity comparisons cannot cross mismatched dimensions.', 'ai-post-scheduler'),
							'<strong>' . esc_html(implode(', ', (array) $stored_dims)) . '</strong>',
							'<strong>' . esc_html($active_dims) . '</strong>'
						);
						?>
					</p>
				</div>
				<div>
					<button type="button" id="aips-reindex-dimension-btn" class="aips-btn aips-btn-sm aips-btn-primary">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e('Re-index All Content with Active Model', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Rate Limit Notice -->
		<?php
		$rate_limits_info = isset($status['rate_limits']) ? $status['rate_limits'] : array();
		$is_rate_limited  = !empty($rate_limits_info['is_rate_limited']);
		?>
		<div id="aips-rate-limit-warning-banner" class="notice notice-error inline aips-quota-alert-banner <?php echo $is_rate_limited ? '' : 'aips-hidden'; ?>">
			<div class="aips-banner-inner">
				<div>
					<h4 class="aips-banner-title">
						<span class="dashicons dashicons-shield-alt aips-banner-icon"></span>
						<?php esc_html_e('Embedding Generation Rate Limit Reached', 'ai-post-scheduler'); ?>
					</h4>
					<p class="aips-banner-desc" id="aips-rate-limit-warning-msg">
						<?php
						if (!empty($rate_limits_info['exceeded_limit'])) {
							printf(
								/* translators: 1: period */
								esc_html__('The %1$s vector embedding rate limit quota has been reached to protect your API budget. Background scanning is paused.', 'ai-post-scheduler'),
								esc_html($rate_limits_info['exceeded_limit'])
							);
						} else {
							esc_html_e('Vector embedding rate limit quota reached. Scanning paused.', 'ai-post-scheduler');
						}
						?>
					</p>
				</div>
				<div>
					<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings#settings-ai')); ?>" class="aips-btn aips-btn-sm aips-btn-secondary">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php esc_html_e('Adjust Limits in Settings', 'ai-post-scheduler'); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- Cooldown Alert Banner -->
		<?php
		$cooldown           = isset($settings['cooldown']) ? $settings['cooldown'] : array();
		$is_cooldown_active = !empty($cooldown['active']);
		$cooldown_remaining = isset($cooldown['remaining_seconds']) ? (int) $cooldown['remaining_seconds'] : 0;
		$cooldown_until     = isset($cooldown['until']) ? (int) $cooldown['until'] : 0;
		$cooldown_reason    = isset($cooldown['reason']) ? $cooldown['reason'] : '';
		?>
		<div id="aips-cooldown-banner" class="notice notice-warning inline aips-cooldown-banner <?php echo $is_cooldown_active ? '' : 'aips-hidden'; ?>" data-until="<?php echo esc_attr((string) $cooldown_until); ?>">
			<div class="aips-banner-inner">
				<div class="aips-cooldown-content">
					<span class="dashicons dashicons-clock aips-cooldown-icon"></span>
					<div>
						<h4 class="aips-cooldown-title">
							<strong><?php esc_html_e('Embedding API Auto-Cooldown Active', 'ai-post-scheduler'); ?></strong>
							<span id="aips-cooldown-timer-badge" class="aips-cooldown-badge">
								<?php esc_html_e('Resuming in:', 'ai-post-scheduler'); ?> <span id="aips-cooldown-countdown"><?php echo esc_html(gmdate('i:s', $cooldown_remaining)); ?></span>
							</span>
						</h4>
						<p class="aips-banner-desc aips-cooldown-reason-msg" id="aips-cooldown-reason-msg">
							<?php
							if (!empty($cooldown_reason)) {
								printf(
									/* translators: 1: reason */
									esc_html__('Remote provider reported: "%s". Indexing operations are temporarily halted to respect remote rate limits.', 'ai-post-scheduler'),
									esc_html($cooldown_reason)
								);
							} else {
								esc_html_e('Remote rate limits encountered. Indexing operations are temporarily paused.', 'ai-post-scheduler');
							}
							?>
						</p>
					</div>
				</div>
				<div>
					<button type="button" id="aips-resume-cooldown-btn" class="aips-btn aips-btn-sm aips-btn-primary">
						<span class="dashicons dashicons-controls-play"></span>
						<?php esc_html_e('Resume Now', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Status / Metric Cards -->
		<div class="aips-stats-row">

			<div class="aips-content-panel aips-stat-card-lg">
				<div class="aips-panel-body">
					<p class="aips-stat-label">
						<?php esc_html_e('Indexed Posts & CPTs', 'ai-post-scheduler'); ?>
					</p>
					<div class="aips-stat-value-wrap">
						<p class="aips-stat-value" id="aips-stat-indexed">
							<?php echo esc_html($indexed); ?>
						</p>
						<span class="aips-stat-total">/ <span id="aips-stat-total"><?php echo esc_html($total_posts); ?></span></span>
						<span id="aips-stat-percent" class="aips-stat-percent"><?php echo esc_html($percent); ?>%</span>
					</div>
					<div class="aips-stat-progress-track">
						<div id="aips-index-progress-bar" class="aips-stat-progress-bar" style="width:<?php echo esc_attr($percent); ?>%;"></div>
					</div>
				</div>
			</div>

			<div class="aips-content-panel aips-stat-card-md">
				<div class="aips-panel-body">
					<p class="aips-stat-label">
						<?php esc_html_e('Unindexed Content', 'ai-post-scheduler'); ?>
					</p>
					<p class="aips-stat-value aips-stat-value-warning" id="aips-stat-unindexed">
						<?php echo esc_html($unindexed); ?>
					</p>
					<p class="aips-stat-subtext">
						<?php esc_html_e('Ready for vector generation', 'ai-post-scheduler'); ?>
					</p>
				</div>
			</div>

			<div class="aips-content-panel aips-stat-card-md">
				<div class="aips-panel-body">
					<p class="aips-stat-label">
						<?php esc_html_e('Topic Embeddings', 'ai-post-scheduler'); ?>
					</p>
					<p class="aips-stat-value aips-stat-value-success" id="aips-stat-topics">
						<?php echo esc_html($topic_count); ?>
					</p>
					<p class="aips-stat-subtext">
						<?php esc_html_e('Deduplication ready', 'ai-post-scheduler'); ?>
					</p>
				</div>
			</div>

			<div class="aips-content-panel aips-stat-card-dims">
				<div class="aips-panel-body">
					<p class="aips-stat-label">
						<?php esc_html_e('Vector Model & Dims', 'ai-post-scheduler'); ?>
					</p>
					<p class="aips-stat-value aips-stat-value-dims">
						<?php echo esc_html($active_model); ?>
					</p>
					<p class="aips-stat-subtext-dims">
						<strong><?php echo esc_html($active_dims); ?></strong> <?php esc_html_e('dimensions', 'ai-post-scheduler'); ?>
					</p>
				</div>
			</div>

		</div><!-- /.aips-stats-row -->

		<!-- Live Progress Banner (Hidden by default, shown while batch running) -->
		<div id="aips-indexer-live-banner" class="aips-indexer-banner aips-hidden">
			<div class="aips-indexer-banner-spinner">
				<span class="spinner is-active"></span>
			</div>
			<div class="aips-indexer-banner-text">
				<strong id="aips-indexer-banner-title"><?php esc_html_e('Indexing Content in Progress…', 'ai-post-scheduler'); ?></strong>
				<span id="aips-indexer-banner-desc"><?php esc_html_e('Processing batch slices safely without browser timeouts.', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-indexer-banner-counter">
				<span id="aips-indexer-slice-count">0 / 0</span>
			</div>
		</div>

		<!-- Tab Navigation -->
		<div class="aips-tab-nav">
			<a href="#visualizer" class="aips-tab-link active" data-tab="visualizer">
				<span class="dashicons dashicons-networking"></span>
				<?php esc_html_e('Semantic Graph Visualizer', 'ai-post-scheduler'); ?>
			</a>
			<a href="#scanner" class="aips-tab-link" data-tab="scanner">
				<span class="dashicons dashicons-database-view"></span>
				<?php esc_html_e('Backfill Scanner & Scope', 'ai-post-scheduler'); ?>
			</a>
			<a href="#cannibalization" class="aips-tab-link" data-tab="cannibalization">
				<span class="dashicons dashicons-shield"></span>
				<?php esc_html_e('Duplicate & Cannibalization Audit', 'ai-post-scheduler'); ?>
			</a>
			<a href="#clusters" class="aips-tab-link" data-tab="clusters">
				<span class="dashicons dashicons-category"></span>
				<?php esc_html_e('Post Clusters & Content Gaps', 'ai-post-scheduler'); ?>
			</a>
		</div>

		<!-- =====================================================================
		     TAB 1: SEMANTIC GRAPH VISUALIZER
		     ===================================================================== -->
		<div id="visualizer-tab" class="aips-tab-content active" role="tabpanel">
			<div class="aips-content-panel aips-visualizer-panel">
				
				<!-- Graph Toolbar -->
				<div class="aips-visualizer-toolbar">
					<div class="aips-visualizer-search-wrap">
						<label for="aips-graph-post-search" class="screen-reader-text"><?php esc_html_e('Select Post to Inspect:', 'ai-post-scheduler'); ?></label>
						<span class="dashicons dashicons-search aips-search-input-icon"></span>
						<input type="text" id="aips-graph-post-search" class="aips-form-input aips-search-with-icon" placeholder="<?php esc_attr_e('Search post title to inspect node network…', 'ai-post-scheduler'); ?>" autocomplete="off">
						<button type="button" id="aips-graph-search-clear" class="aips-search-clear-btn aips-hidden" title="<?php esc_attr_e('Clear search', 'ai-post-scheduler'); ?>">&times;</button>
						<div id="aips-graph-post-dropdown" class="aips-autocomplete-dropdown aips-hidden"></div>
						<input type="hidden" id="aips-graph-selected-post-id" value="">
					</div>

					<div class="aips-visualizer-controls">
						<div class="aips-slider-control">
							<span class="aips-control-label"><?php esc_html_e('Min Similarity:', 'ai-post-scheduler'); ?> <strong id="aips-sim-val">60%</strong></span>
							<input type="range" id="aips-graph-sim-threshold" min="0.40" max="0.95" step="0.05" value="0.60">
						</div>

						<div class="aips-slider-control">
							<span class="aips-control-label"><?php esc_html_e('Max Nodes:', 'ai-post-scheduler'); ?> <strong id="aips-nodes-val">15</strong></span>
							<input type="range" id="aips-graph-max-nodes" min="5" max="30" step="1" value="15">
						</div>

						<label class="aips-checkbox-control">
							<input type="checkbox" id="aips-toggle-clusters" value="1">
							<span class="aips-control-label"><?php esc_html_e('Show Post Clusters', 'ai-post-scheduler'); ?></span>
						</label>

						<button type="button" id="aips-refresh-graph-btn" class="aips-btn aips-btn-sm aips-btn-secondary">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e('Re-render', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</div>

				<!-- Active Post Banner (Full Title Display & Exploration Trail) -->
				<div id="aips-active-post-bar" class="aips-active-post-bar aips-hidden">
					<div class="aips-active-post-left">
						<button type="button" id="aips-history-back-btn" class="aips-btn aips-btn-xs aips-btn-secondary aips-hidden" title="<?php esc_attr_e('Back to Previous Post', 'ai-post-scheduler'); ?>">
							<span class="dashicons dashicons-arrow-left-alt"></span>
							<?php esc_html_e('Back', 'ai-post-scheduler'); ?>
						</button>
						<div class="aips-active-post-info">
							<span class="aips-active-post-tag"><?php esc_html_e('Inspecting:', 'ai-post-scheduler'); ?></span>
							<strong id="aips-active-post-title" class="aips-active-post-title"></strong>
							<span id="aips-active-post-meta" class="aips-active-post-meta"></span>
						</div>
						<div id="aips-graph-breadcrumbs" class="aips-graph-breadcrumbs aips-hidden"></div>
					</div>
					<div class="aips-active-post-actions">
						<button type="button" id="aips-drawer-open-btn" class="aips-btn aips-btn-xs aips-btn-secondary" title="<?php esc_attr_e('View Node Details', 'ai-post-scheduler'); ?>">
							<span class="dashicons dashicons-info-outline"></span>
							<?php esc_html_e('Inspect Details', 'ai-post-scheduler'); ?>
						</button>
						<button type="button" id="aips-active-post-clear" class="aips-btn aips-btn-ghost aips-btn-xs" title="<?php esc_attr_e('Reset Selection', 'ai-post-scheduler'); ?>">
							<span class="dashicons dashicons-dismiss"></span>
							<?php esc_html_e('Clear Selection', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</div>

				<!-- Graph Canvas Area -->
				<div class="aips-graph-viewport-container">
					
					<!-- Floating Zoom Toolbar -->
					<div id="aips-graph-zoom-toolbar" class="aips-graph-zoom-toolbar">
						<button type="button" id="aips-zoom-in" class="aips-zoom-btn" title="<?php esc_attr_e('Zoom In', 'ai-post-scheduler'); ?>">
							<span class="dashicons dashicons-plus-alt2"></span>
						</button>
						<span id="aips-zoom-level" class="aips-zoom-level">100%</span>
						<button type="button" id="aips-zoom-out" class="aips-zoom-btn" title="<?php esc_attr_e('Zoom Out', 'ai-post-scheduler'); ?>">
							<span class="dashicons dashicons-minus"></span>
						</button>
						<button type="button" id="aips-zoom-reset" class="aips-zoom-btn" title="<?php esc_attr_e('Reset Zoom & Position', 'ai-post-scheduler'); ?>">
							<span class="dashicons dashicons-image-rotate"></span>
						</button>
					</div>

					<div id="aips-graph-canvas-wrap" class="aips-graph-canvas-wrap">
						<svg id="aips-graph-svg" width="100%" height="560"></svg>
						<div id="aips-graph-empty" class="aips-graph-placeholder aips-hidden">
							<span class="dashicons dashicons-share"></span>
							<h3><?php esc_html_e('Select an indexed post to explore its semantic network', 'ai-post-scheduler'); ?></h3>
							<p><?php esc_html_e('Nodes represent related posts and topics with edge weights proportional to cosine similarity.', 'ai-post-scheduler'); ?></p>
						</div>
					</div>

					<!-- Rich Hover Tooltip Card -->
					<div id="aips-graph-tooltip" class="aips-graph-tooltip aips-hidden">
						<div class="aips-tooltip-badge" id="aips-tooltip-badge"></div>
						<div class="aips-tooltip-title" id="aips-tooltip-title"></div>
						<div class="aips-tooltip-meta" id="aips-tooltip-meta"></div>
						<div class="aips-tooltip-hint"><?php esc_html_e('Click node to drill down and explore neighborhood', 'ai-post-scheduler'); ?></div>
					</div>

					<!-- Node Detail Flyout Drawer -->
					<div id="aips-node-drawer" class="aips-node-drawer aips-hidden">
						<div class="aips-node-drawer-header">
							<h4 id="aips-drawer-title"><?php esc_html_e('Post Title', 'ai-post-scheduler'); ?></h4>
							<button type="button" id="aips-drawer-close" class="aips-btn aips-btn-ghost aips-btn-sm" aria-label="<?php esc_attr_e('Close drawer', 'ai-post-scheduler'); ?>">&times;</button>
						</div>
						<div class="aips-node-drawer-body">
							<div class="aips-node-stat-badge" id="aips-drawer-sim-badge">
								<span id="aips-drawer-sim-text">85% Similarity</span>
							</div>
							<p class="aips-node-drawer-type"><strong id="aips-drawer-type">post</strong> #<span id="aips-drawer-id">0</span></p>
							
							<div class="aips-drawer-actions">
								<a href="#" id="aips-drawer-edit-link" class="aips-btn aips-btn-sm aips-btn-secondary" target="_blank">
									<span class="dashicons dashicons-edit"></span>
									<?php esc_html_e('Edit Post', 'ai-post-scheduler'); ?>
								</a>
								<a href="#" id="aips-drawer-view-link" class="aips-btn aips-btn-sm aips-btn-ghost" target="_blank">
									<span class="dashicons dashicons-external"></span>
									<?php esc_html_e('View Live', 'ai-post-scheduler'); ?>
								</a>
								<button type="button" id="aips-drawer-focus-btn" class="aips-btn aips-btn-sm aips-btn-primary">
									<span class="dashicons dashicons-networking"></span>
									<?php esc_html_e('Focus Node', 'ai-post-scheduler'); ?>
								</button>
							</div>
						</div>
					</div>
				</div><!-- /.aips-graph-viewport-container -->

				<div class="aips-graph-legend">
					<span class="legend-item"><span class="dot dot-center"></span> <?php esc_html_e('Target Post (Center)', 'ai-post-scheduler'); ?></span>
					<span class="legend-item"><span class="dot dot-high"></span> <?php esc_html_e('High Similarity (≥80%)', 'ai-post-scheduler'); ?></span>
					<span class="legend-item"><span class="dot dot-med"></span> <?php esc_html_e('Moderate (65–79%)', 'ai-post-scheduler'); ?></span>
					<span class="legend-item"><span class="dot dot-low"></span> <?php esc_html_e('Related (<65%)', 'ai-post-scheduler'); ?></span>
				</div>

			</div>
		</div>

		<!-- =====================================================================
		     TAB 2: BACKFILL SCANNER & SCOPE
		     ===================================================================== -->
		<div id="scanner-tab" class="aips-tab-content" role="tabpanel" style="display: none;">
			<div class="aips-content-panel">
				<div class="aips-panel-header">
					<h3 class="aips-panel-title"><?php esc_html_e('Backfill Indexing Status & Breakdown', 'ai-post-scheduler'); ?></h3>
				</div>
				<div class="aips-panel-body">
					<p class="description aips-panel-desc">
						<?php esc_html_e('Unlike traditional plugins, AI Post Scheduler backfills existing posts and custom post types with vector embeddings. Indexing runs progressively to avoid API rate limits and PHP execution timeouts.', 'ai-post-scheduler'); ?>
					</p>

					<table class="aips-table widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e('Post Type', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Scope Status', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Total Published', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Indexed with Vectors', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Coverage', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($all_post_types as $pt_slug => $pt_obj) : 
								$in_scope = in_array($pt_slug, $settings['post_types'], true);
								$pt_counts = wp_count_posts($pt_slug);
								$pt_published = isset($pt_counts->publish) ? (int) $pt_counts->publish : 0;
								$pt_indexed = isset($stats['by_post_type'][$pt_slug]) ? (int) $stats['by_post_type'][$pt_slug] : 0;
								$pt_pct = $pt_published > 0 ? min(100, round(($pt_indexed / $pt_published) * 100)) : 0;
							?>
								<tr>
									<td><strong><?php echo esc_html($pt_obj->labels->singular_name); ?></strong> <code>(<?php echo esc_html($pt_slug); ?>)</code></td>
									<td>
										<?php if ($in_scope) : ?>
											<span class="aips-badge aips-badge-success"><?php esc_html_e('Included in Index', 'ai-post-scheduler'); ?></span>
										<?php else : ?>
											<span class="aips-badge aips-badge-secondary"><?php esc_html_e('Excluded', 'ai-post-scheduler'); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html($pt_published); ?></td>
									<td><?php echo esc_html($pt_indexed); ?></td>
									<td>
										<div class="aips-coverage-cell">
											<div class="aips-coverage-track">
												<div class="aips-coverage-bar" style="width:<?php echo esc_attr($pt_pct); ?>%;"></div>
											</div>
											<span class="aips-coverage-pct"><?php echo esc_html($pt_pct); ?>%</span>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- =====================================================================
		     TAB 3: CANNIBALIZATION & DUPLICATE AUDIT
		     ===================================================================== -->
		<div id="cannibalization-tab" class="aips-tab-content" role="tabpanel" style="display: none;">
			<div class="aips-content-panel">
				<div class="aips-panel-header aips-panel-header-flex">
					<div>
						<h3 class="aips-panel-title"><?php esc_html_e('Content Cannibalization & Semantic Duplicate Audit', 'ai-post-scheduler'); ?></h3>
						<p class="description aips-panel-header-desc"><?php esc_html_e('Identifies published posts with unusually high semantic similarity that may compete against each other in search engines.', 'ai-post-scheduler'); ?></p>
					</div>
					<button type="button" id="aips-run-audit-btn" class="aips-btn aips-btn-secondary">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e('Run Audit Scan', 'ai-post-scheduler'); ?>
					</button>
				</div>
				<div class="aips-panel-body no-padding">
					<div id="aips-audit-loading" class="aips-audit-loading aips-hidden">
						<span class="spinner is-active"></span>
						<?php esc_html_e('Scanning relationship matrix for cannibalization clusters…', 'ai-post-scheduler'); ?>
					</div>

					<table class="aips-table" id="aips-cannibalization-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Post A (Source)', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Post B (Candidate Duplicate)', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Similarity Score', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Risk Level', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody id="aips-cannibalization-tbody">
							<tr>
								<td colspan="5" class="aips-table-empty-cell">
									<?php esc_html_e('Click "Run Audit Scan" above to analyze potential duplicate and cannibalizing posts.', 'ai-post-scheduler'); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- =====================================================================
		     TAB 4: POST CLUSTERS & CONTENT GAPS
		     ===================================================================== -->
		<div id="clusters-tab" class="aips-tab-content" role="tabpanel" style="display: none;">
			<div class="aips-content-panel">
				<div class="aips-panel-header aips-panel-header-flex">
					<div>
						<h3 class="aips-panel-title"><?php esc_html_e('Thematic Post Clusters & Content Gap Discovery', 'ai-post-scheduler'); ?></h3>
						<p class="description aips-panel-header-desc"><?php esc_html_e('Automatically organizes your indexed posts into semantic community clusters, calculates cohesion scores, designates Pillar Posts, and discovers content gaps with AI ideas.', 'ai-post-scheduler'); ?></p>
					</div>
					<div class="aips-clusters-toolbar">
						<div class="aips-slider-control">
							<span class="aips-control-label"><?php esc_html_e('Cluster Threshold:', 'ai-post-scheduler'); ?> <strong id="aips-cluster-sim-val"><?php echo esc_html((string) ($settings['post_cluster_threshold'] * 100)); ?>%</strong></span>
							<input type="range" id="aips-cluster-sim-threshold" min="0.40" max="0.90" step="0.05" value="<?php echo esc_attr((string) $settings['post_cluster_threshold']); ?>">
						</div>
						<div class="aips-clusters-toolbar-group">
							<label for="aips-cluster-min-size" class="aips-control-label"><?php esc_html_e('Min Size:', 'ai-post-scheduler'); ?></label>
							<select id="aips-cluster-min-size" class="aips-form-select aips-cluster-select-size">
								<option value="2">2</option>
								<option value="3">3</option>
								<option value="5">5</option>
								<option value="10">10</option>
							</select>
						</div>
						<button type="button" id="aips-refresh-clusters-btn" class="aips-btn aips-btn-primary">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e('Scan & Build Clusters', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</div>

				<div class="aips-panel-body">
					<!-- Clusters Summary Metrics Row -->
					<div class="aips-cluster-metrics-row">
						<div class="aips-metric-box">
							<span class="aips-metric-box-label"><?php esc_html_e('Thematic Clusters', 'ai-post-scheduler'); ?></span>
							<strong id="aips-metric-clusters-count" class="aips-metric-box-val aips-metric-val-clusters">0</strong>
						</div>
						<div class="aips-metric-box">
							<span class="aips-metric-box-label"><?php esc_html_e('Clustered Posts', 'ai-post-scheduler'); ?></span>
							<strong id="aips-metric-posts-in-clusters" class="aips-metric-box-val aips-metric-val-posts">0</strong>
						</div>
						<div class="aips-metric-box">
							<span class="aips-metric-box-label"><?php esc_html_e('Avg Cohesion', 'ai-post-scheduler'); ?></span>
							<strong id="aips-metric-avg-cohesion" class="aips-metric-box-val aips-metric-val-cohesion">--</strong>
						</div>
						<div class="aips-metric-box">
							<span class="aips-metric-box-label"><?php esc_html_e('Hybrid Orphans', 'ai-post-scheduler'); ?></span>
							<strong id="aips-metric-orphans-count" class="aips-metric-box-val aips-metric-val-orphans">0</strong>
						</div>
					</div>

					<div id="aips-clusters-loading" class="aips-audit-loading aips-hidden">
						<span class="spinner is-active"></span>
						<?php esc_html_e('Analyzing community graph and generating cluster centroids…', 'ai-post-scheduler'); ?>
					</div>

					<!-- Clusters Accordion Container -->
					<div id="aips-clusters-accordion" class="aips-clusters-accordion">
						<div class="aips-cluster-empty-cell">
							<span class="dashicons dashicons-category aips-cluster-empty-icon"></span>
							<h4 class="aips-cluster-empty-title"><?php esc_html_e('No Post Clusters Generated Yet', 'ai-post-scheduler'); ?></h4>
							<p class="aips-cluster-empty-desc"><?php esc_html_e('Click "Scan & Build Clusters" above to group your published articles into thematic topic pillars.', 'ai-post-scheduler'); ?></p>
						</div>
					</div>

					<!-- Hybrid Orphans Section -->
					<div id="aips-orphans-card" class="aips-content-panel aips-orphans-card aips-hidden">
						<div class="aips-panel-header aips-orphans-header">
							<div>
								<h4 class="aips-orphans-title"><?php esc_html_e('Hybrid Orphan Posts (Isolated Content)', 'ai-post-scheduler'); ?></h4>
								<p class="description aips-orphans-desc"><?php esc_html_e('Posts with weak or no thematic ties to existing clusters. Consider expanding coverage or writing bridge articles.', 'ai-post-scheduler'); ?></p>
							</div>
							<span class="aips-risk-badge aips-risk-medium" id="aips-orphans-badge">0 Posts</span>
						</div>
						<div class="aips-panel-body no-padding">
							<table class="aips-table" id="aips-orphans-table">
								<thead>
									<tr>
										<th><?php esc_html_e('Post Title', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Published Date', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Closest Cluster', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Proximity', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Action', 'ai-post-scheduler'); ?></th>
									</tr>
								</thead>
								<tbody id="aips-orphans-tbody">
								</tbody>
							</table>
						</div>
					</div>

				</div>
			</div>
		</div>

		<!-- Modal: Content Gap Suggestions -->
		<div id="aips-gap-modal" class="aips-modal-overlay aips-hidden">
			<div class="aips-modal-card aips-gap-modal-card">
				<div class="aips-modal-header aips-gap-modal-header">
					<div>
						<h3 class="aips-gap-modal-title">
							<span class="dashicons dashicons-lightbulb aips-gap-modal-icon"></span>
							<?php esc_html_e('AI Content Gap Ideas for Cluster', 'ai-post-scheduler'); ?>
						</h3>
						<span id="aips-gap-modal-cluster-name" class="aips-gap-modal-cluster-name"></span>
					</div>
					<button type="button" id="aips-gap-modal-close" class="aips-btn aips-btn-ghost aips-btn-xs aips-gap-modal-close-btn">&times;</button>
				</div>
				<div class="aips-modal-body aips-gap-modal-body">
					<div id="aips-gap-modal-loading" class="aips-audit-loading aips-hidden">
						<span class="spinner is-active"></span>
						<?php esc_html_e('Consulting AI model to detect topic opportunities in this cluster…', 'ai-post-scheduler'); ?>
					</div>
					<div id="aips-gap-suggestions-list" class="aips-gap-suggestions-list">
					</div>
					<div class="aips-gap-modal-author-row">
						<label for="aips-gap-author-select" class="aips-gap-modal-author-label">
							<?php esc_html_e('Assign to Author:', 'ai-post-scheduler'); ?>
						</label>
						<select id="aips-gap-author-select" class="aips-form-select aips-gap-modal-author-select">
							<option value="0"><?php esc_html_e('— Select an Author —', 'ai-post-scheduler'); ?></option>
							<?php if (!empty($authors)) : ?>
								<?php foreach ($authors as $auth) : ?>
									<option value="<?php echo esc_attr((string) $auth->id); ?>"><?php echo esc_html($auth->name); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</div>
				</div>
				<div class="aips-modal-footer aips-gap-modal-footer">
					<button type="button" id="aips-gap-modal-cancel" class="aips-btn aips-btn-secondary"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
					<button type="button" id="aips-commit-gap-topics-btn" class="aips-btn aips-btn-primary" disabled>
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e('Add Selected Topics to Author', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

	</div><!-- /.aips-page-container -->
</div><!-- /.aips-wrap -->

<!-- =====================================================================
     CLIENT-SIDE HTML TEMPLATES
     These <script type="text/html"> elements are read by AIPS.Templates.render()
     and AIPS.Templates.renderRaw(). They are never executed as JavaScript.
     ===================================================================== -->

<!-- Template: Cluster card -->
<script type="text/html" id="aips-tmpl-indexer-cluster-card">
	<div class="aips-cluster-card" data-cluster-id="{{id}}">
		<div class="aips-cluster-card-header" data-toggle-target="#aips-cluster-body-{{id}}">
			<div class="aips-cluster-header-left">
				<span class="dashicons dashicons-arrow-down-alt2 aips-cluster-toggle-icon"></span>
				<strong class="aips-cluster-title">{{name}}</strong>
				<button type="button" class="aips-cluster-rename-btn aips-btn-icon" data-cluster-id="{{id}}" data-cluster-name="{{name}}" title="<?php esc_attr_e('Rename Cluster', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-edit"></span>
				</button>
				<span class="aips-risk-badge aips-risk-low">{{postCount}} posts</span>
				<span class="aips-risk-badge {{cohesionBadgeClass}}" title="<?php esc_attr_e('Cluster internal cohesion score', 'ai-post-scheduler'); ?>">Cohesion: {{cohesionPct}}%</span>
				{{pillarBadgeHtml}}
			</div>
			<div class="aips-cluster-header-right">
				<button type="button" class="aips-btn aips-btn-xs aips-btn-secondary aips-cluster-gaps-btn" data-cluster-id="{{id}}" data-cluster-name="{{name}}">
					<span class="dashicons dashicons-lightbulb"></span>
					<?php esc_html_e('Find Content Gaps', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
		<div class="aips-cluster-card-body" id="aips-cluster-body-{{id}}">
			<table class="aips-table">
				<thead>
					<tr>
						<th style="width:50px;text-align:center;"><?php esc_html_e('Pillar', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Post Title', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Published Date', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Similarity to Centroid', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody class="aips-cluster-posts-tbody">
					{{postsHtml}}
				</tbody>
			</table>
		</div>
	</div>
</script>

<!-- Template: Cluster post row -->
<script type="text/html" id="aips-tmpl-indexer-cluster-post-row">
	<tr class="{{rowClass}}" data-post-id="{{postId}}">
		<td style="text-align:center;">
			<button type="button" class="aips-pillar-toggle-btn {{isPillarClass}}" data-cluster-id="{{clusterId}}" data-post-id="{{postId}}" title="<?php esc_attr_e('Toggle as Cluster Pillar Post', 'ai-post-scheduler'); ?>">
				<span class="dashicons {{starIconClass}}"></span>
			</button>
		</td>
		<td>
			<strong>{{title}}</strong>
			<small class="aips-audit-target-meta">#{{postId}} ({{postType}})</small>
		</td>
		<td>{{date}}</td>
		<td>
			<strong class="aips-audit-similarity-score">{{similarityPct}}%</strong>
		</td>
		<td>
			<a href="{{viewUrl}}" class="button button-small" target="_blank"><?php esc_html_e('View', 'ai-post-scheduler'); ?></a>
			<a href="{{editUrl}}" class="button button-small" target="_blank"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></a>
		</td>
	</tr>
</script>

<!-- Template: Orphan post row -->
<script type="text/html" id="aips-tmpl-indexer-orphan-row">
	<tr data-post-id="{{postId}}">
		<td>
			<strong>{{title}}</strong>
			<small class="aips-audit-target-meta">#{{postId}} ({{postType}})</small>
		</td>
		<td>{{date}}</td>
		<td><em>{{closestCluster}}</em></td>
		<td><span class="aips-risk-badge aips-risk-low">{{proximityPct}}%</span></td>
		<td>
			<a href="{{editUrl}}" class="button button-small" target="_blank"><?php esc_html_e('Edit Post', 'ai-post-scheduler'); ?></a>
		</td>
	</tr>
</script>

<!-- Template: Gap idea item -->
<script type="text/html" id="aips-tmpl-indexer-gap-item">
	<div class="aips-gap-item">
		<input type="checkbox" class="aips-gap-checkbox" value="{{title}}" id="aips-gap-{{index}}" checked>
		<div class="aips-gap-content">
			<label for="aips-gap-{{index}}" class="aips-gap-title">{{title}}</label>
			<p class="aips-gap-desc">{{rationale}}</p>
		</div>
	</div>
</script>

<!-- Template: Audit empty state row -->
<script type="text/html" id="aips-tmpl-indexer-audit-empty">
	<tr>
		<td colspan="5" class="aips-audit-empty-cell">
			{{message}}
		</td>
	</tr>
</script>

<!-- Template: Audit group header for Post A -->
<script type="text/html" id="aips-tmpl-indexer-audit-group-header">
	<tr class="aips-audit-group-header" data-toggle-target=".{{groupId}}">
		<td colspan="5">
			<span class="dashicons dashicons-arrow-down-alt2 aips-group-toggle-icon aips-audit-group-toggle-icon"></span>
			<strong class="aips-audit-group-title">{{title}}</strong>
			<span class="aips-audit-group-meta">{{postType}} #{{sourceId}}</span>
			<span class="aips-risk-badge {{riskClass}} aips-audit-group-badge">Max: {{riskLabel}} ({{maxSimilarityPct}}%)</span>
		</td>
	</tr>
</script>

<!-- Template: Audit risk tier header -->
<script type="text/html" id="aips-tmpl-indexer-audit-risk-header">
	<tr class="aips-audit-risk-header {{groupId}}" data-toggle-target=".{{riskGroupId}}">
		<td colspan="5" class="aips-audit-risk-cell">
			<span class="dashicons {{iconClass}} aips-group-toggle-icon aips-audit-risk-icon"></span>
			<span class="aips-risk-badge {{riskClass}} aips-audit-risk-badge">{{riskLabel}}</span>
			<span class="aips-audit-risk-count">{{count}} {{candidateLabel}}</span>
		</td>
	</tr>
</script>

<!-- Template: Audit candidate duplicate row -->
<script type="text/html" id="aips-tmpl-indexer-audit-row">
	<tr class="aips-audit-row {{groupId}} {{riskGroupId}} {{collapseClass}}">
		<td class="aips-audit-tree-indent">&rdsh;</td>
		<td><strong>{{title}}</strong><br><small class="aips-audit-target-meta">{{postType}} #{{targetId}} ({{date}})</small></td>
		<td><strong class="aips-audit-similarity-score">{{similarityPct}}%</strong></td>
		<td><span class="aips-risk-badge {{riskClass}}">{{riskLabel}}</span></td>
		<td>{{actions}}</td>
	</tr>
</script>

<!-- Template: Audit action link button -->
<script type="text/html" id="aips-tmpl-indexer-audit-action-btn">
	<a href="{{url}}" class="button button-small aips-audit-action-btn" target="_blank">{{label}}</a>
</script>

<!-- Template: Rate limit meter counter -->
<script type="text/html" id="aips-tmpl-indexer-meter-count">
	<strong>{{count}}</strong> / {{limit}}
</script>

<!-- Template: Autocomplete search result item -->
<script type="text/html" id="aips-tmpl-indexer-autocomplete-item">
	<div class="aips-autocomplete-item" data-id="{{id}}" data-title="{{title}}" data-type="{{type}}" data-indexed="{{indexed}}">
		<span class="aips-autocomplete-title">{{title}}<small class="aips-autocomplete-meta"> ({{type}} #{{id}})</small></span>
		<span class="{{badgeClass}}">{{badgeText}}</span>
	</div>
</script>

<!-- Template: Breadcrumb separator -->
<script type="text/html" id="aips-tmpl-indexer-breadcrumb-sep">
	<span class="aips-breadcrumb-sep">&rsaquo;</span>
</script>

<!-- Template: Cluster empty state -->
<script type="text/html" id="aips-tmpl-indexer-cluster-empty">
	<div class="aips-cluster-empty-cell">
		<h4 class="aips-cluster-empty-title">{{title}}</h4>
		<p class="aips-cluster-empty-desc">{{description}}</p>
	</div>
</script>

<!-- Template: Pillar badge tag -->
<script type="text/html" id="aips-tmpl-indexer-pillar-tag">
	<span class="aips-pillar-tag">
		<span class="dashicons dashicons-star-filled aips-pillar-icon"></span>
		<?php esc_html_e('Pillar:', 'ai-post-scheduler'); ?> {{title}}
	</span>
</script>

<!-- Template: Gap suggestion status message -->
<script type="text/html" id="aips-tmpl-indexer-gap-msg">
	<p class="{{msgClass}}">{{message}}</p>
</script>

<!-- Template: Clean audit scan result row -->
<script type="text/html" id="aips-tmpl-indexer-audit-clean">
	<tr>
		<td colspan="5" class="aips-audit-clean-cell">
			<strong>{{message}}</strong>
		</td>
	</tr>
</script>

