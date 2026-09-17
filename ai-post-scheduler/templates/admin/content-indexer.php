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

						<button type="button" id="aips-refresh-graph-btn" class="aips-btn aips-btn-sm aips-btn-secondary">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e('Re-render', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</div>

				<!-- Active Post Banner (Full Title Display) -->
				<div id="aips-active-post-bar" class="aips-active-post-bar aips-hidden">
					<div class="aips-active-post-info">
						<span class="aips-active-post-tag"><?php esc_html_e('Inspecting:', 'ai-post-scheduler'); ?></span>
						<strong id="aips-active-post-title" class="aips-active-post-title"></strong>
						<span id="aips-active-post-meta" class="aips-active-post-meta"></span>
					</div>
					<button type="button" id="aips-active-post-clear" class="aips-btn aips-btn-ghost aips-btn-xs" title="<?php esc_attr_e('Reset Selection', 'ai-post-scheduler'); ?>">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e('Clear Selection', 'ai-post-scheduler'); ?>
					</button>
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
						<div class="aips-tooltip-hint"><?php esc_html_e('Click node to open flyout details & actions', 'ai-post-scheduler'); ?></div>
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
		<div id="scanner-tab" class="aips-tab-content" role="tabpanel">
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
		<div id="cannibalization-tab" class="aips-tab-content" role="tabpanel">
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

	</div><!-- /.aips-page-container -->
</div><!-- /.aips-wrap -->
