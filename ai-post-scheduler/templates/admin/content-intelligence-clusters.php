<?php
/**
 * Topic Clusters & Content Gap Discovery Admin Tab Partial
 *
 * Dedicated interface for semantic topic clustering, pillar post designation,
 * cohesion metrics, and AI bridge idea generation.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 *
 * @var array $cluster_config
 * @var array $authors
 * @var array $banners
 * @var array $settings
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="aips-content-clusters-tab">

	<!-- Embeddings Disabled Notice -->
	<?php if (!empty($banners['embeddings_disabled'])) : ?>
	<div class="notice notice-info inline aips-banner aips-embeddings-disabled-banner">
		<div class="aips-banner-inner">
			<div>
				<h4 class="aips-banner-title">
					<span class="dashicons dashicons-info aips-banner-icon" aria-hidden="true"></span>
					<?php esc_html_e('Vector Embeddings System is Currently Disabled', 'ai-post-scheduler'); ?>
				</h4>
				<p class="aips-banner-desc">
					<?php esc_html_e('Vector clustering requires active vector embeddings. Please enable embeddings in settings to scan clusters.', 'ai-post-scheduler'); ?>
				</p>
			</div>
			<div>
				<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings#settings-ai')); ?>" class="aips-btn aips-btn-sm aips-btn-primary">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<?php esc_html_e('Enable Embeddings', 'ai-post-scheduler'); ?>
				</a>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Cooldown Alert Banner -->
	<?php
	$cooldown_banner = isset($banners['cooldown']) ? $banners['cooldown'] : array();
	$cooldown_until  = isset($cooldown_banner['until']) ? (int) $cooldown_banner['until'] : 0;
	?>
	<div id="aips-cooldown-banner" class="notice notice-warning inline aips-banner aips-cooldown-banner <?php echo !empty($cooldown_banner['active']) ? '' : 'aips-hidden'; ?>" data-until="<?php echo esc_attr((string) $cooldown_until); ?>">
		<div class="aips-banner-inner">
			<div class="aips-cooldown-content">
				<span class="dashicons dashicons-clock aips-cooldown-icon" aria-hidden="true"></span>
				<div>
					<h4 class="aips-cooldown-title">
						<strong><?php esc_html_e('Embedding API Auto-Cooldown Active', 'ai-post-scheduler'); ?></strong>
						<span id="aips-cooldown-timer-badge" class="aips-badge aips-badge-warning aips-cooldown-badge">
							<?php esc_html_e('Resuming in:', 'ai-post-scheduler'); ?> <span id="aips-cooldown-countdown"><?php echo esc_html(isset($cooldown_banner['remaining_formatted']) ? $cooldown_banner['remaining_formatted'] : '00:00'); ?></span>
						</span>
					</h4>
					<p class="aips-banner-desc aips-cooldown-reason-msg" id="aips-cooldown-reason-msg">
						<?php echo esc_html(isset($cooldown_banner['message']) ? $cooldown_banner['message'] : ''); ?>
					</p>
				</div>
			</div>
			<div>
				<button type="button" id="aips-resume-cooldown-btn" class="aips-btn aips-btn-sm aips-btn-primary">
					<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
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
			<div class="aips-clusters-toolbar aips-toolbar-right">
				<div class="aips-slider-control">
					<span class="aips-control-label"><?php esc_html_e('Cluster Threshold:', 'ai-post-scheduler'); ?> <strong id="aips-cluster-sim-val"><?php echo esc_html((string) $cluster_config['threshold_percent']); ?>%</strong></span>
					<input type="range" id="aips-cluster-sim-threshold" min="0.40" max="0.90" step="0.05" value="<?php echo esc_attr((string) $cluster_config['threshold']); ?>" aria-label="<?php esc_attr_e('Cluster Similarity Threshold', 'ai-post-scheduler'); ?>">
				</div>
				<div class="aips-clusters-toolbar-group">
					<label for="aips-cluster-min-size" class="aips-control-label"><?php esc_html_e('Min Size:', 'ai-post-scheduler'); ?></label>
					<select id="aips-cluster-min-size" class="aips-form-select aips-cluster-select-size" aria-label="<?php esc_attr_e('Minimum Cluster Size', 'ai-post-scheduler'); ?>">
						<option value="2">2</option>
						<option value="3" selected>3</option>
						<option value="5">5</option>
						<option value="10">10</option>
					</select>
				</div>
				<button type="button" id="aips-refresh-clusters-btn" class="aips-btn aips-btn-primary">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e('Scan & Build Clusters', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>

		<div class="aips-panel-body">
			<!-- Clusters Summary Metrics Row -->
			<div class="aips-stats-grid aips-cluster-metrics-grid">
				<div class="aips-stat-card">
					<div class="aips-stat-header">
						<span class="aips-stat-label"><?php esc_html_e('Thematic Clusters', 'ai-post-scheduler'); ?></span>
						<span class="dashicons dashicons-category aips-stat-icon" aria-hidden="true"></span>
					</div>
					<div class="aips-stat-value-wrap">
						<span id="aips-metric-clusters-count" class="aips-stat-value aips-text-primary">0</span>
					</div>
				</div>
				<div class="aips-stat-card">
					<div class="aips-stat-header">
						<span class="aips-stat-label"><?php esc_html_e('Clustered Posts', 'ai-post-scheduler'); ?></span>
						<span class="dashicons dashicons-admin-post aips-stat-icon" aria-hidden="true"></span>
					</div>
					<div class="aips-stat-value-wrap">
						<span id="aips-metric-posts-in-clusters" class="aips-stat-value">0</span>
					</div>
				</div>
				<div class="aips-stat-card">
					<div class="aips-stat-header">
						<span class="aips-stat-label"><?php esc_html_e('Avg Cohesion', 'ai-post-scheduler'); ?></span>
						<span class="dashicons dashicons-chart-area aips-stat-icon" aria-hidden="true"></span>
					</div>
					<div class="aips-stat-value-wrap">
						<span id="aips-metric-avg-cohesion" class="aips-stat-value aips-text-success">--</span>
					</div>
				</div>
				<div class="aips-stat-card">
					<div class="aips-stat-header">
						<span class="aips-stat-label"><?php esc_html_e('Hybrid Orphans', 'ai-post-scheduler'); ?></span>
						<span class="dashicons dashicons-warning aips-stat-icon" aria-hidden="true"></span>
					</div>
					<div class="aips-stat-value-wrap">
						<span id="aips-metric-orphans-count" class="aips-stat-value aips-text-warning">0</span>
					</div>
				</div>
			</div>

			<div id="aips-clusters-loading" class="aips-audit-loading aips-hidden">
				<span class="spinner is-active"></span>
				<?php esc_html_e('Analyzing community graph and generating cluster centroids…', 'ai-post-scheduler'); ?>
			</div>

			<!-- Clusters Accordion Container -->
			<div id="aips-clusters-accordion" class="aips-clusters-accordion">
				<div class="aips-cluster-empty-cell">
					<span class="dashicons dashicons-category aips-cluster-empty-icon" aria-hidden="true"></span>
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
					<span class="aips-badge aips-badge-warning" id="aips-orphans-badge">0 Posts</span>
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
	<div id="aips-gap-modal" class="aips-modal-backdrop aips-hidden">
		<div class="aips-modal aips-modal-lg" role="dialog" aria-modal="true" aria-labelledby="aips-gap-modal-title-text">
			<div class="aips-modal-header aips-gap-modal-header">
				<div>
					<h3 class="aips-modal-title" id="aips-gap-modal-title-text">
						<span class="dashicons dashicons-lightbulb aips-gap-modal-icon" aria-hidden="true"></span>
						<?php esc_html_e('AI Content Gap Ideas for Cluster', 'ai-post-scheduler'); ?>
					</h3>
					<span id="aips-gap-modal-cluster-name" class="aips-badge aips-badge-primary aips-gap-modal-cluster-name"></span>
				</div>
				<button type="button" id="aips-gap-modal-close" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
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
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e('Add Selected Topics to Author', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>

</div><!-- /.aips-content-clusters-tab -->

<!-- =====================================================================
     CLIENT-SIDE HTML TEMPLATES
     ===================================================================== -->

<!-- Template: Cluster card -->
<script type="text/html" id="aips-tmpl-indexer-cluster-card">
	<div class="aips-cluster-card" data-cluster-id="{{id}}">
		<div class="aips-cluster-card-header" data-toggle-target="#aips-cluster-body-{{id}}">
			<div class="aips-cluster-header-left">
				<span class="dashicons dashicons-arrow-down-alt2 aips-cluster-toggle-icon" aria-hidden="true"></span>
				<strong class="aips-cluster-title">{{name}}</strong>
				<button type="button" class="aips-cluster-rename-btn aips-btn-icon" data-cluster-id="{{id}}" data-cluster-name="{{name}}" title="<?php esc_attr_e('Rename Cluster', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Rename Cluster', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				</button>
				<span class="aips-badge aips-badge-neutral">{{postCount}} posts</span>
				<span class="aips-badge {{cohesionBadgeClass}}" title="<?php esc_attr_e('Cluster internal cohesion score', 'ai-post-scheduler'); ?>">Cohesion: {{cohesionPct}}%</span>
				{{pillarBadgeHtml}}
			</div>
			<div class="aips-cluster-header-right">
				<button type="button" class="aips-btn aips-btn-xs aips-btn-secondary aips-cluster-gaps-btn" data-cluster-id="{{id}}" data-cluster-name="{{name}}">
					<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
					<?php esc_html_e('Find Content Gaps', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
		<div class="aips-cluster-card-body" id="aips-cluster-body-{{id}}">
			<table class="aips-table">
				<thead>
					<tr>
						<th class="aips-col-pillar"><?php esc_html_e('Pillar', 'ai-post-scheduler'); ?></th>
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
		<td class="aips-col-pillar">
			<button type="button" class="aips-pillar-toggle-btn {{isPillarClass}}" data-cluster-id="{{clusterId}}" data-post-id="{{postId}}" title="<?php esc_attr_e('Toggle as Cluster Pillar Post', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Toggle as Cluster Pillar Post', 'ai-post-scheduler'); ?>">
				<span class="dashicons {{starIconClass}}" aria-hidden="true"></span>
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
			<div class="aips-row-action-group">
				<a href="{{viewUrl}}" class="aips-btn aips-btn-xs aips-btn-ghost" target="_blank"><?php esc_html_e('View', 'ai-post-scheduler'); ?></a>
				<a href="{{editUrl}}" class="aips-btn aips-btn-xs aips-btn-secondary" target="_blank"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></a>
			</div>
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
		<td><span class="aips-badge aips-badge-warning">{{proximityPct}}%</span></td>
		<td>
			<a href="{{editUrl}}" class="aips-btn aips-btn-xs aips-btn-secondary" target="_blank"><?php esc_html_e('Edit Post', 'ai-post-scheduler'); ?></a>
		</td>
	</tr>
</script>

<!-- Template: Gap idea item -->
<script type="text/html" id="aips-tmpl-indexer-gap-item">
	<div class="aips-gap-item">
		<input type="checkbox" class="aips-gap-checkbox aips-form-checkbox" value="{{title}}" id="aips-gap-{{index}}" checked>
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
	<span class="aips-badge aips-badge-primary aips-pillar-tag">
		<span class="dashicons dashicons-star-filled aips-pillar-icon" aria-hidden="true"></span>
		<?php esc_html_e('Pillar:', 'ai-post-scheduler'); ?> {{title}}
	</span>
</script>

<!-- Template: Gap suggestion status message -->
<script type="text/html" id="aips-tmpl-indexer-gap-msg">
	<p class="{{msgClass}}">{{message}}</p>
</script>
