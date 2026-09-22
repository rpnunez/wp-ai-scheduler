<?php
/**
 * Topic Clusters & Content Gap Discovery Admin Page
 *
 * Dedicated interface for semantic topic clustering, pillar post designation,
 * cohesion metrics, and AI bridge idea generation.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// Variables injected by AIPS_Content_Indexer_Controller:
// $status, $stats, $settings, $authors

$cooldown           = isset($settings['cooldown']) ? $settings['cooldown'] : array();
$is_cooldown_active = !empty($cooldown['active']);
$cooldown_remaining = isset($cooldown['remaining_seconds']) ? (int) $cooldown['remaining_seconds'] : 0;
$cooldown_until     = isset($cooldown['until']) ? (int) $cooldown['until'] : 0;
$cooldown_reason    = isset($cooldown['reason']) ? $cooldown['reason'] : '';
$cluster_threshold  = isset($settings['post_cluster_threshold']) ? (float) $settings['post_cluster_threshold'] : 0.65;
?>

<div class="wrap aips-wrap aips-indexer-page aips-clusters-page">
	<div class="aips-page-container">

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title">
						<span class="dashicons dashicons-category aips-indexer-title-icon"></span>
						<?php esc_html_e('Topic Clusters & Content Gap Discovery', 'ai-post-scheduler'); ?>
					</h1>
					<p class="aips-page-description">
						<?php esc_html_e('Automatically organize published articles into semantic topic communities, model pillar posts, evaluate cohesion, and discover editorial gaps with AI.', 'ai-post-scheduler'); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Suite Navigation Bar -->
		<div class="aips-tab-nav aips-suite-nav">
			<a href="<?php echo esc_url(admin_url('admin.php?page=aips-content-intelligence')); ?>" class="aips-tab-link">
				<span class="dashicons dashicons-networking"></span>
				<?php esc_html_e('Semantic Graph Hub', 'ai-post-scheduler'); ?>
			</a>
			<a href="<?php echo esc_url(admin_url('admin.php?page=aips-post-clusters')); ?>" class="aips-tab-link active">
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
						<?php esc_html_e('Vector clustering requires active vector embeddings. Please enable embeddings in settings to scan clusters.', 'ai-post-scheduler'); ?>
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

		<!-- Cooldown Alert Banner -->
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
									esc_html__('Remote provider reported: "%s". Operations are temporarily halted.', 'ai-post-scheduler'),
									esc_html($cooldown_reason)
								);
							} else {
								esc_html_e('Remote rate limits encountered. Operations temporarily paused.', 'ai-post-scheduler');
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

		<!-- Main Post Clusters Panel -->
		<div class="aips-content-panel">
			<div class="aips-panel-header aips-panel-header-flex">
				<div>
					<h3 class="aips-panel-title"><?php esc_html_e('Semantic Post Communities & Topic Hubs', 'ai-post-scheduler'); ?></h3>
					<p class="description aips-panel-header-desc">
						<?php esc_html_e('Group posts based on multi-dimensional cosine proximity. Star a post to designate it as the cluster\'s Core Pillar.', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<div class="aips-clusters-toolbar">
					<div class="aips-slider-control">
						<span class="aips-control-label"><?php esc_html_e('Cluster Threshold:', 'ai-post-scheduler'); ?> <strong id="aips-cluster-sim-val"><?php echo esc_html((string) round($cluster_threshold * 100)); ?>%</strong></span>
						<input type="range" id="aips-cluster-sim-threshold" min="0.40" max="0.90" step="0.05" value="<?php echo esc_attr((string) $cluster_threshold); ?>">
					</div>
					<div class="aips-clusters-toolbar-group">
						<label for="aips-cluster-min-size" class="aips-control-label"><?php esc_html_e('Min Size:', 'ai-post-scheduler'); ?></label>
						<select id="aips-cluster-min-size" class="aips-form-select aips-cluster-select-size">
							<option value="2">2</option>
							<option value="3" selected>3</option>
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
