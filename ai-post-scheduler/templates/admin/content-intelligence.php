<?php
/**
 * Content Intelligence Hub Admin Page
 *
 * Primary hub featuring the Interactive Semantic Graph Visualizer,
 * live vector health cards, backfill scanner controls, and knowledge graph exploration.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// Variables injected by AIPS_Content_Indexer_Controller:
// $status, $stats, $all_post_types, $settings, $dimension_mismatch, $stored_dims, $active_dims

$total_posts    = isset($status['total_posts']) ? (int) $status['total_posts'] : 0;
$indexed        = isset($status['indexed']) ? (int) $status['indexed'] : 0;
$unindexed      = isset($status['unindexed']) ? (int) $status['unindexed'] : 0;
$percent        = isset($status['percent']) ? (int) $status['percent'] : 0;

$total_topics       = isset($status['total_topics']) ? (int) $status['total_topics'] : 0;
$indexed_topics     = isset($status['indexed_topics']) ? (int) $status['indexed_topics'] : 0;
$unindexed_topics   = isset($status['unindexed_topics']) ? (int) $status['unindexed_topics'] : 0;
$topics_percent     = isset($status['topics_percent']) ? (int) $status['topics_percent'] : 0;
$combined_unindexed = $unindexed + $unindexed_topics;

$active_model = !empty($stats['models']) ? $stats['models'][0]->model : 'Default (AI Engine)';
$active_dims  = !empty($stats['models']) ? (int) $stats['models'][0]->dimensions : 1536;
?>

<div class="wrap aips-wrap aips-indexer-page aips-intelligence-hub">
	<div class="aips-page-container">

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title">
						<span class="dashicons dashicons-networking aips-indexer-title-icon"></span>
						<?php esc_html_e('Content Intelligence Hub', 'ai-post-scheduler'); ?>
					</h1>
					<p class="aips-page-description">
						<?php esc_html_e('Explore your site\'s semantic knowledge graph, inspect multi-dimensional vector relationships, and manage vector index coverage.', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<div class="aips-page-actions">
					<div class="aips-scan-scope-wrap">
						<label for="aips-scan-entity-scope" class="screen-reader-text"><?php esc_html_e('Scan Entity Scope', 'ai-post-scheduler'); ?></label>
						<select id="aips-scan-entity-scope" class="aips-select aips-select-sm" title="<?php esc_attr_e('Select entity scope to backfill', 'ai-post-scheduler'); ?>">
							<option value="all" <?php selected(!empty($settings['scan_entity_scope']) ? $settings['scan_entity_scope'] : 'all', 'all'); ?>><?php esc_html_e('All Content (Posts & Topics)', 'ai-post-scheduler'); ?></option>
							<option value="posts" <?php selected(!empty($settings['scan_entity_scope']) ? $settings['scan_entity_scope'] : 'all', 'posts'); ?>><?php esc_html_e('Posts & Pages Only', 'ai-post-scheduler'); ?></option>
							<option value="topics" <?php selected(!empty($settings['scan_entity_scope']) ? $settings['scan_entity_scope'] : 'all', 'topics'); ?>><?php esc_html_e('Author Topics Only', 'ai-post-scheduler'); ?></option>
						</select>
					</div>
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

		<!-- Suite Navigation Bar -->
		<div class="aips-tab-nav aips-suite-nav">
			<a href="<?php echo esc_url(admin_url('admin.php?page=aips-content-intelligence')); ?>" class="aips-tab-link active">
				<span class="dashicons dashicons-networking"></span>
				<?php esc_html_e('Semantic Graph Hub', 'ai-post-scheduler'); ?>
			</a>
			<a href="<?php echo esc_url(admin_url('admin.php?page=aips-post-clusters')); ?>" class="aips-tab-link">
				<span class="dashicons dashicons-category"></span>
				<?php esc_html_e('Topic Clusters & Gaps', 'ai-post-scheduler'); ?>
			</a>
			<a href="<?php echo esc_url(admin_url('admin.php?page=aips-cannibalization')); ?>" class="aips-tab-link">
				<span class="dashicons dashicons-shield"></span>
				<?php esc_html_e('Cannibalization Audit', 'ai-post-scheduler'); ?>
			</a>
			<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings#settings-ai')); ?>" class="aips-tab-link">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e('AI & Embeddings Settings', 'ai-post-scheduler'); ?>
			</a>
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
						<?php esc_html_e('Automatic indexing, continuous sync, and semantic vector similarity checks are turned off. You can re-enable the embeddings engine anytime in AI & Embeddings Settings.', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<div>
					<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings#settings-ai')); ?>" class="aips-btn aips-btn-sm aips-btn-primary">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php esc_html_e('Enable Embeddings', 'ai-post-scheduler'); ?>
					</a>
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
						<?php esc_html_e('Unindexed Items', 'ai-post-scheduler'); ?>
					</p>
					<p class="aips-stat-value aips-stat-value-warning" id="aips-stat-unindexed">
						<?php echo esc_html($combined_unindexed); ?>
					</p>
					<p class="aips-stat-subtext" id="aips-stat-unindexed-breakdown">
						<?php
						printf(
							/* translators: 1: unindexed posts, 2: unindexed topics */
							esc_html__('%1$d posts, %2$d topics pending', 'ai-post-scheduler'),
							$unindexed,
							$unindexed_topics
						);
						?>
					</p>
				</div>
			</div>

			<div class="aips-content-panel aips-stat-card-lg">
				<div class="aips-panel-body">
					<p class="aips-stat-label">
						<?php esc_html_e('Indexed Author Topics', 'ai-post-scheduler'); ?>
					</p>
					<div class="aips-stat-value-wrap">
						<p class="aips-stat-value aips-stat-value-success" id="aips-stat-topics-indexed">
							<?php echo esc_html($indexed_topics); ?>
						</p>
						<span class="aips-stat-total">/ <span id="aips-stat-topics-total"><?php echo esc_html($total_topics); ?></span></span>
						<span id="aips-stat-topics-percent" class="aips-stat-percent"><?php echo esc_html($topics_percent); ?>%</span>
					</div>
					<div class="aips-stat-progress-track">
						<div id="aips-topics-progress-bar" class="aips-stat-progress-bar aips-stat-progress-bar-topic" style="width:<?php echo esc_attr($topics_percent); ?>%;"></div>
					</div>
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

		<!-- Main Semantic Graph Visualizer -->
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

					<label class="aips-checkbox-control">
						<input type="checkbox" id="aips-toggle-topics" value="1" checked>
						<span class="aips-control-label"><?php esc_html_e('Show Author Topics', 'ai-post-scheduler'); ?></span>
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

		</div><!-- /.aips-visualizer-panel -->

		<!-- Backfill Scanner & Post Types Coverage Panel -->
		<div class="aips-content-panel aips-scope-breakdown-panel">
			<div class="aips-panel-header aips-panel-header-flex">
				<div>
					<h3 class="aips-panel-title"><?php esc_html_e('Vector Indexing Breakdown by Post Type', 'ai-post-scheduler'); ?></h3>
					<p class="description aips-panel-header-desc">
						<?php esc_html_e('Coverage across registered public post types included in vector similarity indexing.', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<div>
					<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings#settings-ai')); ?>" class="aips-btn aips-btn-sm aips-btn-secondary">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php esc_html_e('Configure Included Post Types', 'ai-post-scheduler'); ?>
					</a>
				</div>
			</div>
			<div class="aips-panel-body">
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

	</div><!-- /.aips-page-container -->
</div><!-- /.aips-wrap -->

<!-- =====================================================================
     CLIENT-SIDE HTML TEMPLATES
     ===================================================================== -->

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

<!-- Template: Rate limit meter counter -->
<script type="text/html" id="aips-tmpl-indexer-meter-count">
	<strong>{{count}}</strong> / {{limit}}
</script>

<!-- Template: Entity badge -->
<script type="text/html" id="aips-tmpl-indexer-entity-badge">
	<span class="aips-entity-badge aips-entity-badge-{{type}}">{{label}}</span>
</script>
