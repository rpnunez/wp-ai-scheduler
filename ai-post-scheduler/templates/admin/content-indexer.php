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

		<!-- Dimension Mismatch Notice -->
		<?php if (!empty($dimension_mismatch)) : ?>
		<div class="notice notice-warning inline aips-dimension-mismatch-banner" style="margin: 0 0 20px; padding: 16px; border-left-color: #dba617; background: #fff8e5; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
			<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
				<div>
					<h4 style="margin:0 0 4px;font-size:15px;color:#614700;display:flex;align-items:center;gap:6px;">
						<span class="dashicons dashicons-warning" style="color:#dba617;font-size:20px;width:20px;height:20px;"></span>
						<?php esc_html_e('Vector Dimension Mismatch Detected', 'ai-post-scheduler'); ?>
					</h4>
					<p style="margin:0;font-size:13px;color:#705300;">
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

		<!-- Status / Metric Cards -->
		<div class="aips-stats-row" style="display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap;">

			<div class="aips-content-panel" style="flex:1.4;min-width:240px;">
				<div class="aips-panel-body" style="padding:20px;">
					<p class="aips-stat-label" style="margin:0 0 4px;font-size:12px;text-transform:uppercase;color:#777;font-weight:600;">
						<?php esc_html_e('Indexed Posts & CPTs', 'ai-post-scheduler'); ?>
					</p>
					<div style="display:flex;align-items:baseline;gap:8px;">
						<p class="aips-stat-value" style="margin:0;font-size:32px;font-weight:800;color:#1d2327;" id="aips-stat-indexed">
							<?php echo esc_html($indexed); ?>
						</p>
						<span style="font-size:15px;color:#888;">/ <span id="aips-stat-total"><?php echo esc_html($total_posts); ?></span></span>
						<span id="aips-stat-percent" style="margin-left:auto;font-size:14px;font-weight:700;color:#2271b1;"><?php echo esc_html($percent); ?>%</span>
					</div>
					<div style="margin-top:12px;background:#e2e4e7;border-radius:6px;height:8px;overflow:hidden;">
						<div id="aips-index-progress-bar" style="width:<?php echo esc_attr($percent); ?>%;background:linear-gradient(90deg, #2271b1, #3858e9);height:100%;border-radius:6px;transition:width .4s ease;"></div>
					</div>
				</div>
			</div>

			<div class="aips-content-panel" style="flex:1;min-width:180px;">
				<div class="aips-panel-body" style="padding:20px;">
					<p class="aips-stat-label" style="margin:0 0 4px;font-size:12px;text-transform:uppercase;color:#777;font-weight:600;">
						<?php esc_html_e('Unindexed Content', 'ai-post-scheduler'); ?>
					</p>
					<p class="aips-stat-value" style="margin:0;font-size:32px;font-weight:800;color:#d67500;" id="aips-stat-unindexed">
						<?php echo esc_html($unindexed); ?>
					</p>
					<p style="margin:8px 0 0;font-size:12px;color:#888;">
						<?php esc_html_e('Ready for vector generation', 'ai-post-scheduler'); ?>
					</p>
				</div>
			</div>

			<div class="aips-content-panel" style="flex:1;min-width:180px;">
				<div class="aips-panel-body" style="padding:20px;">
					<p class="aips-stat-label" style="margin:0 0 4px;font-size:12px;text-transform:uppercase;color:#777;font-weight:600;">
						<?php esc_html_e('Topic Embeddings', 'ai-post-scheduler'); ?>
					</p>
					<p class="aips-stat-value" style="margin:0;font-size:32px;font-weight:800;color:#00a32a;" id="aips-stat-topics">
						<?php echo esc_html($topic_count); ?>
					</p>
					<p style="margin:8px 0 0;font-size:12px;color:#888;">
						<?php esc_html_e('Deduplication ready', 'ai-post-scheduler'); ?>
					</p>
				</div>
			</div>

			<div class="aips-content-panel" style="flex:1;min-width:200px;">
				<div class="aips-panel-body" style="padding:20px;">
					<p class="aips-stat-label" style="margin:0 0 4px;font-size:12px;text-transform:uppercase;color:#777;font-weight:600;">
						<?php esc_html_e('Vector Model & Dims', 'ai-post-scheduler'); ?>
					</p>
					<p class="aips-stat-value" style="margin:0;font-size:20px;font-weight:700;color:#2271b1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
						<?php echo esc_html($active_model); ?>
					</p>
					<p style="margin:8px 0 0;font-size:12px;color:#666;">
						<strong><?php echo esc_html($active_dims); ?></strong> <?php esc_html_e('dimensions', 'ai-post-scheduler'); ?>
					</p>
				</div>
			</div>

		</div><!-- /.aips-stats-row -->

		<!-- Live Progress Banner (Hidden by default, shown while batch running) -->
		<div id="aips-indexer-live-banner" class="aips-indexer-banner" style="display:none;">
			<div class="aips-indexer-banner-spinner">
				<span class="spinner is-active" style="float:none;margin:0;"></span>
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
			<a href="#settings" class="aips-tab-link" data-tab="settings">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e('Settings & Thresholds', 'ai-post-scheduler'); ?>
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
						<label for="aips-graph-post-select" class="screen-reader-text"><?php esc_html_e('Select Post to Inspect:', 'ai-post-scheduler'); ?></label>
						<input type="text" id="aips-graph-post-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search post title to inspect node network…', 'ai-post-scheduler'); ?>" autocomplete="off">
						<div id="aips-graph-post-dropdown" class="aips-autocomplete-dropdown" style="display:none;"></div>
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

				<!-- Graph Canvas Area -->
				<div class="aips-graph-viewport-container">
					<div id="aips-graph-canvas-wrap" class="aips-graph-canvas-wrap">
						<svg id="aips-graph-svg" width="100%" height="560"></svg>
						<div id="aips-graph-empty" class="aips-graph-placeholder" style="display:none;">
							<span class="dashicons dashicons-share" style="font-size:48px;width:48px;height:48px;color:#c3c4c7;"></span>
							<h3><?php esc_html_e('Select an indexed post to explore its semantic network', 'ai-post-scheduler'); ?></h3>
							<p><?php esc_html_e('Nodes represent related posts and topics with edge weights proportional to cosine similarity.', 'ai-post-scheduler'); ?></p>
						</div>
					</div>

					<!-- Node Detail Flyout Drawer -->
					<div id="aips-node-drawer" class="aips-node-drawer" style="display:none;">
						<div class="aips-node-drawer-header">
							<h4 id="aips-drawer-title"><?php esc_html_e('Post Title', 'ai-post-scheduler'); ?></h4>
							<button type="button" id="aips-drawer-close" class="aips-btn aips-btn-ghost aips-btn-sm">&times;</button>
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
					<p class="description" style="margin-bottom:16px;">
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
										<div style="display:flex;align-items:center;gap:8px;">
											<div style="flex:1;background:#e2e4e7;border-radius:4px;height:6px;overflow:hidden;">
												<div style="width:<?php echo esc_attr($pt_pct); ?>%;background:#2271b1;height:100%;"></div>
											</div>
											<span style="font-size:12px;font-weight:600;min-width:36px;"><?php echo esc_html($pt_pct); ?>%</span>
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
				<div class="aips-panel-header" style="display:flex;justify-content:space-between;align-items:center;">
					<div>
						<h3 class="aips-panel-title"><?php esc_html_e('Content Cannibalization & Semantic Duplicate Audit', 'ai-post-scheduler'); ?></h3>
						<p class="description" style="margin:4px 0 0;"><?php esc_html_e('Identifies published posts with unusually high semantic similarity that may compete against each other in search engines.', 'ai-post-scheduler'); ?></p>
					</div>
					<button type="button" id="aips-run-audit-btn" class="aips-btn aips-btn-secondary">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e('Run Audit Scan', 'ai-post-scheduler'); ?>
					</button>
				</div>
				<div class="aips-panel-body no-padding">
					<div id="aips-audit-loading" style="padding:40px;text-align:center;display:none;">
						<span class="spinner is-active" style="float:none;margin:0 8px 0 0;vertical-align:middle;"></span>
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
								<td colspan="5" style="text-align:center;padding:32px;color:#888;">
									<?php esc_html_e('Click "Run Audit Scan" above to analyze potential duplicate and cannibalizing posts.', 'ai-post-scheduler'); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- =====================================================================
		     TAB 4: SETTINGS & THRESHOLDS
		     ===================================================================== -->
		<div id="settings-tab" class="aips-tab-content" role="tabpanel">
			<form id="aips-indexer-settings-form">
				<div class="aips-content-panel" style="margin-bottom:20px;">
					<div class="aips-panel-header">
						<h3 class="aips-panel-title"><?php esc_html_e('Embeddings Provider & Connection Configuration', 'ai-post-scheduler'); ?></h3>
					</div>
					<div class="aips-panel-body">
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e('Vector Embeddings Provider', 'ai-post-scheduler'); ?></th>
								<td>
									<select name="embeddings_provider" id="aips_embeddings_provider" class="regular-text">
										<option value="" <?php selected($settings['embeddings_provider'], ''); ?>><?php esc_html_e('Auto-detect (Meow Apps AI Engine preferred)', 'ai-post-scheduler'); ?></option>
										<option value="meow" <?php selected($settings['embeddings_provider'], 'meow'); ?>><?php esc_html_e('Meow Apps AI Engine', 'ai-post-scheduler'); ?></option>
										<option value="wp_ai_client" <?php selected($settings['embeddings_provider'], 'wp_ai_client'); ?>><?php esc_html_e('WordPress AI Client (WP AI API)', 'ai-post-scheduler'); ?></option>
									</select>
									<p class="description"><?php esc_html_e('Select which AI subsystem produces vector embeddings. Decoupled from the primary post generation provider.', 'ai-post-scheduler'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Embedding Model', 'ai-post-scheduler'); ?></th>
								<td>
									<input type="text" name="embeddings_model" id="aips_embeddings_model" value="<?php echo esc_attr($settings['embeddings_model']); ?>" class="regular-text" placeholder="text-embedding-3-small">
									<p class="description"><?php esc_html_e('Model identifier (e.g. text-embedding-3-small, text-embedding-3-large, text-embedding-ada-002, all-minilm).', 'ai-post-scheduler'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Meow AI Engine Environment', 'ai-post-scheduler'); ?></th>
								<td>
									<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
										<input type="text" name="embeddings_env_id" id="aips_embeddings_env_id" value="<?php echo esc_attr($settings['embeddings_env_id']); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g. default, percona_db, pinecone_env', 'ai-post-scheduler'); ?>">
										<button type="button" id="aips-fetch-meow-envs-btn" class="aips-btn aips-btn-secondary aips-btn-sm">
											<span class="dashicons dashicons-rest-api"></span>
											<?php esc_html_e('Fetch Environments from Meow Apps', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div id="aips-meow-envs-dropdown-container" style="display:none;margin-top:8px;">
										<select id="aips-meow-envs-select" class="regular-text">
											<option value=""><?php esc_html_e('— Select a discovered environment —', 'ai-post-scheduler'); ?></option>
										</select>
									</div>
									<p class="description"><?php esc_html_e('Optional connection / environment ID from Meow AI Engine (e.g. Percona Server vector database, OpenAI, Pinecone, Qdrant, Ollama).', 'ai-post-scheduler'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Vector Dimensions', 'ai-post-scheduler'); ?></th>
								<td>
									<input type="number" name="embeddings_dimensions" id="aips_embeddings_dimensions" value="<?php echo esc_attr($settings['embeddings_dimensions']); ?>" min="1" step="1" class="small-text">
									<p class="description"><?php esc_html_e('Number of vector dimensions produced by the model (e.g. 1536 for OpenAI, 768 for Percona / Sentence-Transformers, 384 for all-MiniLM).', 'ai-post-scheduler'); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<div class="aips-content-panel" style="margin-bottom:20px;">
					<div class="aips-panel-header">
						<h3 class="aips-panel-title"><?php esc_html_e('Index Scope & Continuous Sync', 'ai-post-scheduler'); ?></h3>
					</div>
					<div class="aips-panel-body">
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e('Indexed Post Types', 'ai-post-scheduler'); ?></th>
								<td>
									<fieldset>
										<?php foreach ($all_post_types as $pt_slug => $pt_obj) : ?>
											<label style="display:block;margin-bottom:8px;">
												<input type="checkbox" name="post_types[]" value="<?php echo esc_attr($pt_slug); ?>" <?php checked(in_array($pt_slug, $settings['post_types'], true)); ?>>
												<strong><?php echo esc_html($pt_obj->labels->name); ?></strong> <code>(<?php echo esc_html($pt_slug); ?>)</code>
											</label>
										<?php endforeach; ?>
										<p class="description"><?php esc_html_e('Select which post types to generate embeddings for and include in Related Posts.', 'ai-post-scheduler'); ?></p>
									</fieldset>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Automatic Continuous Indexing', 'ai-post-scheduler'); ?></th>
								<td>
									<label>
										<input type="checkbox" name="auto_index_on_publish" value="1" <?php checked($settings['auto_index_on_publish']); ?>>
										<?php esc_html_e('Automatically generate embeddings and compute relationships whenever a post is published or updated.', 'ai-post-scheduler'); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Related Posts Similarity Threshold', 'ai-post-scheduler'); ?></th>
								<td>
									<input type="number" step="0.05" min="0.40" max="0.95" name="similarity_threshold" value="<?php echo esc_attr($settings['similarity_threshold']); ?>" class="small-text">
									<p class="description"><?php esc_html_e('Minimum cosine similarity (0.40 - 0.95) for two posts to be considered related. Default: 0.65', 'ai-post-scheduler'); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<div class="aips-content-panel" style="margin-bottom:20px;">
					<div class="aips-panel-header">
						<h3 class="aips-panel-title"><?php esc_html_e('Frontend Related Posts Display', 'ai-post-scheduler'); ?></h3>
					</div>
					<div class="aips-panel-body">
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e('Enable Related Posts', 'ai-post-scheduler'); ?></th>
								<td>
									<label>
										<input type="checkbox" name="related_posts_enabled" value="1" <?php checked($settings['related_posts_enabled']); ?>>
										<?php esc_html_e('Enable the Related Posts engine, shortcode, and Gutenberg block.', 'ai-post-scheduler'); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Automatic Content Injection', 'ai-post-scheduler'); ?></th>
								<td>
									<label>
										<input type="checkbox" name="related_posts_auto_append" value="1" <?php checked($settings['related_posts_auto_append']); ?>>
										<?php esc_html_e('Automatically append Related Posts to the bottom of single post content.', 'ai-post-scheduler'); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Heading Title', 'ai-post-scheduler'); ?></th>
								<td>
									<input type="text" name="related_posts_heading" value="<?php echo esc_attr($settings['related_posts_heading']); ?>" class="regular-text">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Default Count & Layout', 'ai-post-scheduler'); ?></th>
								<td>
									<input type="number" min="1" max="12" name="related_posts_count" value="<?php echo esc_attr($settings['related_posts_count']); ?>" class="small-text"> <?php esc_html_e('articles', 'ai-post-scheduler'); ?>
									<select name="related_posts_layout" style="margin-left:12px;">
										<option value="grid" <?php selected($settings['related_posts_layout'], 'grid'); ?>><?php esc_html_e('Card Grid', 'ai-post-scheduler'); ?></option>
										<option value="list" <?php selected($settings['related_posts_layout'], 'list'); ?>><?php esc_html_e('List Layout', 'ai-post-scheduler'); ?></option>
									</select>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<div class="aips-content-panel" style="margin-bottom:20px;">
					<div class="aips-panel-header">
						<h3 class="aips-panel-title"><?php esc_html_e('Duplicate Detection & Gatekeeper Guard', 'ai-post-scheduler'); ?></h3>
					</div>
					<div class="aips-panel-body">
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e('Gatekeeper Action on Duplicate', 'ai-post-scheduler'); ?></th>
								<td>
									<select name="deduplication_mode">
										<option value="warn" <?php selected($settings['deduplication_mode'], 'warn'); ?>><?php esc_html_e('Warn & Flag (Lowers Score & shows Duplicate Badge)', 'ai-post-scheduler'); ?></option>
										<option value="block" <?php selected($settings['deduplication_mode'], 'block'); ?>><?php esc_html_e('Strict Block (Skip automated generation if duplicate exists)', 'ai-post-scheduler'); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Duplicate Similarity Threshold', 'ai-post-scheduler'); ?></th>
								<td>
									<input type="number" step="0.05" min="0.70" max="0.99" name="deduplication_threshold" value="<?php echo esc_attr($settings['deduplication_threshold']); ?>" class="small-text">
									<p class="description"><?php esc_html_e('Cosine similarity threshold to classify a topic or post as a duplicate candidate. Default: 0.85', 'ai-post-scheduler'); ?></p>
								</td>
							</tr>
						</table>

						<div style="margin-top:20px;padding-top:16px;border-top:1px solid #eee;">
							<button type="submit" class="aips-btn aips-btn-primary">
								<?php esc_html_e('Save Configuration', 'ai-post-scheduler'); ?>
							</button>
						</div>
					</div>
				</div>
			</form>
		</div>
