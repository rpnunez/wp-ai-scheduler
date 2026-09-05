<?php
/**
 * Generated Posts Unified Content Template
 *
 * Displays all AI-generated content (Published, Scheduled, Pending Review drafts,
 * and Incomplete generations) with 5 interactive KPI quick-filter cards, dynamic grouping,
 * hybrid view modes (Grouped Accordions, Table, Cards), telemetry metrics, and contextual actions.
 *
 * @var AIPS_Generated_Posts_Controller $controller
 * @var array $authors
 * @var array $templates
 * @var array $campaigns
 * @var int $author_id
 * @var int $template_id
 * @var int $campaign_id
 * @var string $search_query
 * @var string $post_status
 * @var string $group_by
 * @var string $view_mode
 * @var int $per_page
 * @var array $kpis
 * @var array $posts_data
 * @var array $grouped_posts
 * @var int $current_page
 * @var array $pagination
 * @var array $selectable_post_types
 * @var string $post_type_filter
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$active_status = isset($active_status) ? $active_status : '';
$post_type_filter = isset($post_type_filter) ? $post_type_filter : '';
$selectable_post_types = isset($selectable_post_types) ? $selectable_post_types : array();
$current_group_by = isset($current_group_by) ? $current_group_by : 'campaign';
$current_view_mode = isset($current_view_mode) ? $current_view_mode : 'grouped';
$current_per_page = isset($current_per_page) ? $current_per_page : 20;
?>

<!-- 5 KPI Summary Metrics Quick-Filter Bar -->
<div class="aips-kpi-grid">
	<a href="<?php echo esc_url($controller->build_generated_posts_page_url(1, array('post_status' => ''))); ?>" class="aips-kpi-card aips-kpi-blue <?php echo empty($active_status) ? 'active' : ''; ?>" title="<?php esc_attr_e('Show all generated content', 'ai-post-scheduler'); ?>">
		<div class="aips-kpi-icon-wrap">
			<span class="dashicons dashicons-admin-post" aria-hidden="true"></span>
		</div>
		<div class="aips-kpi-info">
			<span class="aips-kpi-value"><?php echo esc_html(number_format_i18n(isset($kpis['total_content']) ? $kpis['total_content'] : 0)); ?></span>
			<span class="aips-kpi-label"><?php esc_html_e('Total Content', 'ai-post-scheduler'); ?></span>
		</div>
	</a>

	<a href="<?php echo esc_url($controller->build_generated_posts_page_url(1, array('post_status' => 'publish'))); ?>" class="aips-kpi-card aips-kpi-green <?php echo ($active_status === 'publish') ? 'active' : ''; ?>" title="<?php esc_attr_e('Filter published posts', 'ai-post-scheduler'); ?>">
		<div class="aips-kpi-icon-wrap">
			<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
		</div>
		<div class="aips-kpi-info">
			<span class="aips-kpi-value"><?php echo esc_html(number_format_i18n(isset($kpis['total_published']) ? $kpis['total_published'] : 0)); ?></span>
			<span class="aips-kpi-label"><?php esc_html_e('Published Posts', 'ai-post-scheduler'); ?></span>
		</div>
	</a>

	<a href="<?php echo esc_url($controller->build_generated_posts_page_url(1, array('post_status' => 'future'))); ?>" class="aips-kpi-card aips-kpi-purple <?php echo ($active_status === 'future') ? 'active' : ''; ?>" title="<?php esc_attr_e('Filter scheduled posts', 'ai-post-scheduler'); ?>">
		<div class="aips-kpi-icon-wrap">
			<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
		</div>
		<div class="aips-kpi-info">
			<span class="aips-kpi-value"><?php echo esc_html(number_format_i18n(isset($kpis['total_scheduled']) ? $kpis['total_scheduled'] : 0)); ?></span>
			<span class="aips-kpi-label"><?php esc_html_e('Scheduled / Queued', 'ai-post-scheduler'); ?></span>
		</div>
	</a>

	<a href="<?php echo esc_url($controller->build_generated_posts_page_url(1, array('post_status' => 'draft'))); ?>" class="aips-kpi-card aips-kpi-amber <?php echo ($active_status === 'draft' || $active_status === 'review') ? 'active' : ''; ?>" title="<?php esc_attr_e('Filter drafts pending review', 'ai-post-scheduler'); ?>">
		<div class="aips-kpi-icon-wrap">
			<span class="dashicons dashicons-edit-page" aria-hidden="true"></span>
		</div>
		<div class="aips-kpi-info">
			<span class="aips-kpi-value"><?php echo esc_html(number_format_i18n(isset($kpis['total_pending_review']) ? $kpis['total_pending_review'] : 0)); ?></span>
			<span class="aips-kpi-label"><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></span>
		</div>
	</a>

	<a href="<?php echo esc_url($controller->build_generated_posts_page_url(1, array('post_status' => 'incomplete'))); ?>" class="aips-kpi-card aips-kpi-rose <?php echo ($active_status === 'incomplete') ? 'active' : ''; ?>" title="<?php esc_attr_e('Filter posts with missing components or errors', 'ai-post-scheduler'); ?>">
		<div class="aips-kpi-icon-wrap">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		</div>
		<div class="aips-kpi-info">
			<span class="aips-kpi-value"><?php echo esc_html(number_format_i18n(isset($kpis['total_incomplete']) ? $kpis['total_incomplete'] : 0)); ?></span>
			<span class="aips-kpi-label"><?php esc_html_e('Incomplete / Partial', 'ai-post-scheduler'); ?></span>
		</div>
	</a>
</div>

<!-- Controls & Filter Toolbar -->
<div class="aips-posts-toolbar">
	<form method="get" class="aips-posts-filter-form" id="aips-posts-filter-form">
		<input type="hidden" name="page" value="aips-generated-posts">
		<input type="hidden" name="view_mode" id="aips-view-mode-input" value="<?php echo esc_attr($current_view_mode); ?>">
		
		<div class="aips-posts-filter-group">
			<!-- Search Input -->
			<div class="aips-search-input-wrap">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<input type="search" id="aips-post-search-input" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search generated content...', 'ai-post-scheduler'); ?>" autocomplete="off">
				<?php if (!empty($search_query)): ?>
				<a href="<?php echo esc_url($controller->build_generated_posts_page_url(1, array('s' => ''))); ?>" class="aips-search-clear" title="<?php esc_attr_e('Clear search', 'ai-post-scheduler'); ?>">&times;</a>
				<?php endif; ?>
			</div>

			<!-- Author Filter -->
			<?php if (!empty($authors)): ?>
			<select name="author_id" id="aips-filter-author" class="aips-form-select">
				<option value=""><?php esc_html_e('All Authors', 'ai-post-scheduler'); ?></option>
				<?php foreach ($authors as $a): ?>
				<option value="<?php echo esc_attr($a->id); ?>" <?php selected($author_id, $a->id); ?>>
					<?php echo esc_html($a->name); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>

			<!-- Template Filter -->
			<?php if (!empty($templates)): ?>
			<select name="template_id" id="aips-filter-template" class="aips-form-select">
				<option value=""><?php esc_html_e('All Templates', 'ai-post-scheduler'); ?></option>
				<?php foreach ($templates as $template): ?>
				<option value="<?php echo esc_attr($template->id); ?>" <?php selected($template_id, $template->id); ?>>
					<?php echo esc_html($template->name); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>

			<!-- Campaign Filter -->
			<?php if (!empty($campaigns)): ?>
			<select name="campaign_id" id="aips-filter-campaign" class="aips-form-select">
				<option value=""><?php esc_html_e('All Campaigns', 'ai-post-scheduler'); ?></option>
				<?php foreach ($campaigns as $campaign): ?>
				<option value="<?php echo esc_attr($campaign->id); ?>" <?php selected($campaign_id, $campaign->id); ?>>
					<?php echo esc_html($campaign->name); ?><?php echo !empty($campaign->is_archived) ? esc_html__(' (Archived)', 'ai-post-scheduler') : ''; ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>

			<!-- Post Type Filter -->
			<?php if (!empty($selectable_post_types)): ?>
			<select name="post_type" id="aips-filter-post-type" class="aips-form-select">
				<option value=""><?php esc_html_e('All Post Types', 'ai-post-scheduler'); ?></option>
				<?php foreach ($selectable_post_types as $post_type_key => $post_type_info): ?>
				<option value="<?php echo esc_attr($post_type_key); ?>" <?php selected($post_type_filter, $post_type_key); ?>>
					<?php echo esc_html($post_type_info['label']); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>

			<!-- Post Status / State Filter -->
			<select name="post_status" id="aips-filter-status" class="aips-form-select">
				<option value=""><?php esc_html_e('All Statuses', 'ai-post-scheduler'); ?></option>
				<option value="publish" <?php selected($active_status, 'publish'); ?>><?php esc_html_e('Published', 'ai-post-scheduler'); ?></option>
				<option value="future" <?php selected($active_status, 'future'); ?>><?php esc_html_e('Scheduled', 'ai-post-scheduler'); ?></option>
				<option value="draft" <?php selected($active_status, 'draft'); ?>><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></option>
				<option value="incomplete" <?php selected($active_status, 'incomplete'); ?>><?php esc_html_e('Incomplete / Partial', 'ai-post-scheduler'); ?></option>
				<option value="trash" <?php selected($active_status, 'trash'); ?>><?php esc_html_e('Trash', 'ai-post-scheduler'); ?></option>
			</select>

			<button type="submit" id="aips-filter-submit" class="aips-btn aips-btn-sm aips-btn-secondary">
				<span class="dashicons dashicons-filter" aria-hidden="true"></span>
				<?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
			</button>

			<?php if (!empty($author_id) || !empty($template_id) || !empty($campaign_id) || !empty($active_status) || !empty($post_type_filter) || !empty($search_query)): ?>
			<a href="<?php echo esc_url($controller->build_generated_posts_page_url(1, array('author_id' => 0, 'template_id' => 0, 'campaign_id' => 0, 'post_status' => '', 'post_type' => '', 's' => ''))); ?>" class="aips-btn aips-btn-sm aips-btn-ghost">
				<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
				<?php esc_html_e('Reset Filters', 'ai-post-scheduler'); ?>
			</a>
			<?php endif; ?>
		</div>

		<!-- Toolbar Controls: Group By, Per Page, View Switcher -->
		<div class="aips-toolbar-controls">
			<div class="aips-control-item">
				<label for="aips-group-by-select"><?php esc_html_e('Group by:', 'ai-post-scheduler'); ?></label>
				<select name="group_by" id="aips-group-by-select" class="aips-form-select">
					<option value="status" <?php selected($current_group_by, 'status'); ?>><?php esc_html_e('Status / State', 'ai-post-scheduler'); ?></option>
					<option value="campaign" <?php selected($current_group_by, 'campaign'); ?>><?php esc_html_e('Campaign', 'ai-post-scheduler'); ?></option>
					<option value="template" <?php selected($current_group_by, 'template'); ?>><?php esc_html_e('Template', 'ai-post-scheduler'); ?></option>
					<option value="author" <?php selected($current_group_by, 'author'); ?>><?php esc_html_e('Author', 'ai-post-scheduler'); ?></option>
					<option value="date" <?php selected($current_group_by, 'date'); ?>><?php esc_html_e('Date', 'ai-post-scheduler'); ?></option>
					<option value="none" <?php selected($current_group_by, 'none'); ?>><?php esc_html_e('None (Flat)', 'ai-post-scheduler'); ?></option>
				</select>
			</div>

			<div class="aips-control-item">
				<label for="aips-per-page-select"><?php esc_html_e('Show:', 'ai-post-scheduler'); ?></label>
				<select name="per_page" id="aips-per-page-select" class="aips-form-select">
					<option value="20" <?php selected($current_per_page, 20); ?>>20</option>
					<option value="50" <?php selected($current_per_page, 50); ?>>50</option>
					<option value="100" <?php selected($current_per_page, 100); ?>>100</option>
				</select>
			</div>

			<!-- View Switcher -->
			<div class="aips-view-switcher" role="group" aria-label="<?php esc_attr_e('View Mode', 'ai-post-scheduler'); ?>">
				<button type="button" class="aips-view-btn <?php echo ($current_view_mode === 'grouped') ? 'active' : ''; ?>" data-view="grouped" title="<?php esc_attr_e('Grouped View', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
				</button>
				<button type="button" class="aips-view-btn <?php echo ($current_view_mode === 'table') ? 'active' : ''; ?>" data-view="table" title="<?php esc_attr_e('Table View', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
				</button>
				<button type="button" class="aips-view-btn <?php echo ($current_view_mode === 'cards') ? 'active' : ''; ?>" data-view="cards" title="<?php esc_attr_e('Card Grid View', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-grid-view" aria-hidden="true"></span>
				</button>
			</div>
		</div>
	</form>
</div>

<!-- Bulk Actions Bar -->
<div class="aips-bulk-bar">
	<div class="aips-bulk-left">
		<label>
			<input type="checkbox" id="aips-select-all-posts" class="aips-form-checkbox">
			<span class="screen-reader-text"><?php esc_html_e('Select all posts', 'ai-post-scheduler'); ?></span>
		</label>
		<select id="aips-bulk-action-select" class="aips-form-select">
			<option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
			<option value="publish"><?php esc_html_e('Publish Selected', 'ai-post-scheduler'); ?></option>
			<option value="draft"><?php esc_html_e('Set to Draft', 'ai-post-scheduler'); ?></option>
			<option value="trash"><?php esc_html_e('Move to Trash', 'ai-post-scheduler'); ?></option>
		</select>
		<button type="button" id="aips-apply-bulk-action" class="aips-btn aips-btn-sm aips-btn-secondary">
			<?php esc_html_e('Apply', 'ai-post-scheduler'); ?>
		</button>
		<span id="aips-selected-count" class="aips-bulk-selected-counter" style="display: none;">0 selected</span>
	</div>

	<?php if ($current_view_mode === 'grouped' && !empty($grouped_posts)): ?>
	<div class="aips-bulk-right">
		<button type="button" id="aips-toggle-all-groups" class="aips-btn aips-btn-sm aips-btn-ghost">
			<span class="dashicons dashicons-sort" aria-hidden="true"></span>
			<span class="aips-toggle-all-text"><?php esc_html_e('Collapse All', 'ai-post-scheduler'); ?></span>
		</button>
	</div>
	<?php endif; ?>
</div>

<!-- Posts Content Container -->
<?php if (!empty($posts_data)): ?>

	<!-- VIEW MODE 1: GROUPED ACCORDION VIEW -->
	<?php if ($current_view_mode === 'grouped'): ?>
	<div class="aips-groups-container">
		<?php foreach ($grouped_posts as $group_key => $group): ?>
		<div class="aips-group-section" data-group-key="<?php echo esc_attr($group_key); ?>">
			<div class="aips-group-header" role="button" tabindex="0" aria-expanded="true">
				<div class="aips-group-title-wrap">
					<span class="dashicons <?php echo esc_attr($group['icon']); ?> aips-group-icon" aria-hidden="true"></span>
					<h3 class="aips-group-title"><?php echo esc_html($group['title']); ?></h3>
					<span class="aips-group-count-badge"><?php echo count($group['posts']); ?></span>
				</div>
				<div class="aips-group-header-actions">
					<span class="dashicons dashicons-arrow-down-alt2 aips-group-toggle-icon" aria-hidden="true"></span>
				</div>
			</div>
			<div class="aips-group-content">
				<?php foreach ($group['posts'] as $post_item): ?>
					<?php include AIPS_PLUGIN_DIR . 'templates/partials/post-row-item.php'; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- VIEW MODE 2: TABLE VIEW -->
	<?php elseif ($current_view_mode === 'table'): ?>
	<div class="aips-panel-body no-padding aips-posts-table-panel">
		<table class="aips-table">
			<thead>
				<tr>
					<th scope="col" style="width: 32px;"><span class="screen-reader-text"><?php esc_html_e('Select', 'ai-post-scheduler'); ?></span></th>
					<th scope="col" style="width: 60px;"><?php esc_html_e('Media', 'ai-post-scheduler'); ?></th>
					<th scope="col"><?php esc_html_e('Title & Meta', 'ai-post-scheduler'); ?></th>
					<th scope="col"><?php esc_html_e('Status / State', 'ai-post-scheduler'); ?></th>
					<th scope="col"><?php esc_html_e('Telemetry', 'ai-post-scheduler'); ?></th>
					<th scope="col"><?php esc_html_e('Dates', 'ai-post-scheduler'); ?></th>
					<th scope="col" style="text-align: right;"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($posts_data as $post_item): ?>
				<tr>
					<td>
						<?php if (!empty($post_item['post_id'])): ?>
						<input type="checkbox" class="aips-post-checkbox" value="<?php echo esc_attr($post_item['post_id']); ?>">
						<?php endif; ?>
					</td>
					<td>
						<div class="aips-post-thumb-box">
							<?php if (!empty($post_item['thumb_url'])): ?>
							<img src="<?php echo esc_url($post_item['thumb_url']); ?>" alt="" class="aips-post-thumb-img">
							<?php elseif (!empty($post_item['is_incomplete'])): ?>
							<div class="aips-post-thumb-fallback aips-thumb-warning">
								<span class="dashicons dashicons-warning" aria-hidden="true"></span>
							</div>
							<?php else: ?>
							<div class="aips-post-thumb-fallback">
								<span class="dashicons dashicons-format-aside" aria-hidden="true"></span>
							</div>
							<?php endif; ?>
						</div>
					</td>
					<td>
						<?php if (!empty($post_item['edit_link'])): ?>
						<a href="<?php echo esc_url($post_item['edit_link']); ?>" class="cell-primary">
							<?php echo esc_html($post_item['title']); ?>
						</a>
						<?php else: ?>
						<span class="cell-primary"><?php echo esc_html($post_item['title']); ?></span>
						<?php endif; ?>

						<div class="aips-post-meta-pills">
							<?php if (!empty($post_item['is_incomplete']) && !empty($post_item['missing_components'])): ?>
								<?php foreach ($post_item['missing_components'] as $missing_lbl): ?>
								<span class="aips-pill aips-pill-danger">
									<span class="dashicons dashicons-warning" aria-hidden="true"></span>
									<?php printf(esc_html__('Missing %s', 'ai-post-scheduler'), esc_html($missing_lbl)); ?>
								</span>
								<?php endforeach; ?>
							<?php endif; ?>

							<?php if (!empty($post_item['campaign_name'])): ?>
							<span class="aips-pill aips-pill-campaign">
								<span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
								<?php echo esc_html($post_item['campaign_name']); ?>
							</span>
							<?php endif; ?>
							<?php if (!empty($post_item['template_name'])): ?>
							<span class="aips-pill aips-pill-template">
								<span class="dashicons dashicons-layout" aria-hidden="true"></span>
								<?php echo esc_html($post_item['template_name']); ?>
							</span>
							<?php endif; ?>
							<?php if (!empty($post_item['topic_title'])): ?>
							<span class="aips-pill aips-pill-topic">
								<span class="dashicons dashicons-tag" aria-hidden="true"></span>
								<?php echo esc_html($post_item['topic_title']); ?>
							</span>
							<?php endif; ?>
							<?php if (!empty($post_item['author_name'])): ?>
							<span class="aips-pill aips-pill-author">
								<?php if (!empty($post_item['author_avatar'])): ?>
								<img src="<?php echo esc_url($post_item['author_avatar']); ?>" class="aips-author-avatar-img" alt="">
								<?php endif; ?>
								<?php echo esc_html($post_item['author_name']); ?>
							</span>
							<?php endif; ?>
						</div>
					</td>
					<td>
						<div class="aips-status-box">
							<?php if (!empty($post_item['is_incomplete'])): ?>
							<span class="aips-status-pill status-incomplete">
								<span class="dashicons dashicons-warning" aria-hidden="true"></span>
								<?php esc_html_e('Incomplete', 'ai-post-scheduler'); ?>
							</span>
							<?php else: ?>
							<span class="aips-status-pill status-<?php echo esc_attr($post_item['post_status']); ?>">
								<?php echo esc_html($post_item['post_status_label']); ?>
							</span>
							<?php if (!empty($post_item['post_id'])): ?>
							<select class="aips-quick-status-select" data-post-id="<?php echo esc_attr($post_item['post_id']); ?>">
								<option value="publish" <?php selected($post_item['post_status'], 'publish'); ?>><?php esc_html_e('Publish', 'ai-post-scheduler'); ?></option>
								<option value="draft" <?php selected($post_item['post_status'], 'draft'); ?>><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
								<option value="trash" <?php selected($post_item['post_status'], 'trash'); ?>><?php esc_html_e('Trash', 'ai-post-scheduler'); ?></option>
							</select>
							<?php endif; ?>
							<?php endif; ?>
						</div>
					</td>
					<td>
						<div class="aips-telemetry-box">
							<?php if (!empty($post_item['word_count'])): ?>
							<span class="aips-telemetry-chip">
								<span class="dashicons dashicons-text-page" aria-hidden="true"></span>
								<span><?php echo esc_html(number_format_i18n($post_item['word_count'])); ?> <?php esc_html_e('words', 'ai-post-scheduler'); ?></span>
								<span class="aips-telemetry-tooltip"><?php printf(esc_html__('%1$d words • ~%2$d min read', 'ai-post-scheduler'), (int) $post_item['word_count'], (int) $post_item['reading_time']); ?></span>
							</span>
							<?php endif; ?>
							<?php if ($post_item['duration_seconds'] !== null): ?>
							<span class="aips-telemetry-chip">
								<span class="dashicons dashicons-performance" aria-hidden="true"></span>
								<span><?php echo esc_html($post_item['duration_seconds']); ?>s</span>
								<span class="aips-telemetry-tooltip"><?php printf(esc_html__('Generated in %ss', 'ai-post-scheduler'), esc_html($post_item['duration_seconds'])); ?></span>
							</span>
							<?php endif; ?>
						</div>
					</td>
					<td>
						<div class="cell-meta">
							<div><strong><?php esc_html_e('Gen:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html($post_item['date_generated']); ?></div>
							<?php if (!empty($post_item['date_published'])): ?>
							<div><strong><?php esc_html_e('Pub:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html($post_item['date_published']); ?></div>
							<?php endif; ?>
						</div>
					</td>
					<td style="text-align: right;">
						<div class="cell-actions aips-post-row-actions">
							<div class="aips-row-action-group">
								<?php if (!empty($post_item['is_incomplete'])): ?>
								<button type="button" class="aips-btn aips-btn-sm aips-btn-warning aips-view-session"
									data-history-id="<?php echo esc_attr($post_item['history_id']); ?>"
									title="<?php esc_attr_e('View error and session logs', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-warning" aria-hidden="true"></span>
									<?php esc_html_e('View Error', 'ai-post-scheduler'); ?>
								</button>
								<?php elseif (!empty($post_item['is_pending_review'])): ?>
								<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-preview-post"
									data-post-id="<?php echo esc_attr($post_item['post_id']); ?>"
									title="<?php esc_attr_e('Review and publish draft', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
									<?php esc_html_e('Review', 'ai-post-scheduler'); ?>
								</button>
								<?php else: ?>
								<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-post"
									data-edit-url="<?php echo esc_url($post_item['edit_link']); ?>"
									title="<?php esc_attr_e('Edit this post', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-edit" aria-hidden="true"></span>
									<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
								</button>
								<?php endif; ?>

								<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle"
									aria-haspopup="true"
									aria-expanded="false"
									aria-controls="aips-gen-actions-<?php echo esc_attr($post_item['history_id']); ?>"
									title="<?php esc_attr_e('More actions', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
								</button>
							</div>
							<div id="aips-gen-actions-<?php echo esc_attr($post_item['history_id']); ?>" class="aips-row-action-menu" hidden>
								<?php if (!empty($post_item['post_id'])): ?>
								<button type="button" class="aips-row-action-item aips-preview-post"
									data-post-id="<?php echo esc_attr($post_item['post_id']); ?>">
									<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
									<span><?php esc_html_e('Preview', 'ai-post-scheduler'); ?></span>
								</button>
								<button type="button" class="aips-row-action-item aips-ai-edit-btn"
									data-post-id="<?php echo esc_attr($post_item['post_id']); ?>"
									data-history-id="<?php echo esc_attr($post_item['history_id']); ?>">
									<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
									<span><?php esc_html_e('AI Edit', 'ai-post-scheduler'); ?></span>
								</button>
								<?php endif; ?>

								<button type="button" class="aips-row-action-item aips-view-session"
									data-history-id="<?php echo esc_attr($post_item['history_id']); ?>">
									<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
									<span><?php esc_html_e('View Session', 'ai-post-scheduler'); ?></span>
								</button>

								<?php if ($post_item['post_status'] === 'publish' && !empty($post_item['permalink'])): ?>
								<a class="aips-row-action-item" href="<?php echo esc_url($post_item['permalink']); ?>" target="_blank" rel="noopener">
									<span class="dashicons dashicons-external" aria-hidden="true"></span>
									<span><?php esc_html_e('View Live Post', 'ai-post-scheduler'); ?></span>
								</a>
								<?php endif; ?>
							</div>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- VIEW MODE 3: CARD GRID VIEW -->
	<?php elseif ($current_view_mode === 'cards'): ?>
	<div class="aips-posts-grid-container">
		<?php foreach ($posts_data as $post_item): ?>
		<div class="aips-post-card <?php echo !empty($post_item['is_incomplete']) ? 'aips-card-incomplete' : ''; ?>" data-post-id="<?php echo esc_attr($post_item['post_id']); ?>" data-history-id="<?php echo esc_attr($post_item['history_id']); ?>">
			<div class="aips-card-thumb-wrap">
				<?php if (!empty($post_item['post_id'])): ?>
				<div class="aips-card-select-overlay">
					<input type="checkbox" class="aips-post-checkbox" value="<?php echo esc_attr($post_item['post_id']); ?>">
				</div>
				<?php endif; ?>

				<div class="aips-card-status-overlay">
					<?php if (!empty($post_item['is_incomplete'])): ?>
					<span class="aips-status-pill status-incomplete">
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<?php esc_html_e('Incomplete', 'ai-post-scheduler'); ?>
					</span>
					<?php else: ?>
					<span class="aips-status-pill status-<?php echo esc_attr($post_item['post_status']); ?>">
						<?php echo esc_html($post_item['post_status_label']); ?>
					</span>
					<?php endif; ?>
				</div>

				<?php if (!empty($post_item['thumb_medium_url']) || !empty($post_item['thumb_url'])): ?>
				<img src="<?php echo esc_url(!empty($post_item['thumb_medium_url']) ? $post_item['thumb_medium_url'] : $post_item['thumb_url']); ?>" alt="" class="aips-card-thumb-img">
				<?php elseif (!empty($post_item['is_incomplete'])): ?>
				<div class="aips-post-thumb-fallback aips-thumb-warning">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				</div>
				<?php else: ?>
				<div class="aips-post-thumb-fallback">
					<span class="dashicons dashicons-format-aside" aria-hidden="true"></span>
				</div>
				<?php endif; ?>
			</div>

			<div class="aips-card-body">
				<?php if (!empty($post_item['edit_link'])): ?>
				<a href="<?php echo esc_url($post_item['edit_link']); ?>" class="aips-card-title">
					<?php echo esc_html($post_item['title']); ?>
				</a>
				<?php else: ?>
				<h4 class="aips-card-title"><?php echo esc_html($post_item['title']); ?></h4>
				<?php endif; ?>

				<div class="aips-card-meta-row">
					<?php if (!empty($post_item['is_incomplete']) && !empty($post_item['missing_components'])): ?>
						<?php foreach ($post_item['missing_components'] as $missing_lbl): ?>
						<span class="aips-pill aips-pill-danger">
							<span class="dashicons dashicons-warning" aria-hidden="true"></span>
							<?php printf(esc_html__('Missing %s', 'ai-post-scheduler'), esc_html($missing_lbl)); ?>
						</span>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if (!empty($post_item['campaign_name'])): ?>
					<span class="aips-pill aips-pill-campaign">
						<span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
						<?php echo esc_html($post_item['campaign_name']); ?>
					</span>
					<?php endif; ?>
					<?php if (!empty($post_item['template_name'])): ?>
					<span class="aips-pill aips-pill-template">
						<span class="dashicons dashicons-layout" aria-hidden="true"></span>
						<?php echo esc_html($post_item['template_name']); ?>
					</span>
					<?php endif; ?>
					<?php if (!empty($post_item['author_name'])): ?>
					<span class="aips-pill aips-pill-author">
						<?php if (!empty($post_item['author_avatar'])): ?>
						<img src="<?php echo esc_url($post_item['author_avatar']); ?>" class="aips-author-avatar-img" alt="">
						<?php endif; ?>
						<?php echo esc_html($post_item['author_name']); ?>
					</span>
					<?php endif; ?>
				</div>

				<div class="aips-card-stats-row">
					<?php if (!empty($post_item['word_count'])): ?>
					<span>
						<span class="dashicons dashicons-text-page" aria-hidden="true"></span>
						<?php echo esc_html(number_format_i18n($post_item['word_count'])); ?> <?php esc_html_e('words', 'ai-post-scheduler'); ?>
					</span>
					<?php endif; ?>
					<?php if ($post_item['duration_seconds'] !== null): ?>
					<span>
						<span class="dashicons dashicons-performance" aria-hidden="true"></span>
						<?php echo esc_html($post_item['duration_seconds']); ?>s
					</span>
					<?php endif; ?>
				</div>
			</div>

			<div class="aips-card-actions">
				<?php if (!empty($post_item['post_id'])): ?>
				<select class="aips-quick-status-select" data-post-id="<?php echo esc_attr($post_item['post_id']); ?>">
					<option value="publish" <?php selected($post_item['post_status'], 'publish'); ?>><?php esc_html_e('Publish', 'ai-post-scheduler'); ?></option>
					<option value="draft" <?php selected($post_item['post_status'], 'draft'); ?>><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
					<option value="trash" <?php selected($post_item['post_status'], 'trash'); ?>><?php esc_html_e('Trash', 'ai-post-scheduler'); ?></option>
				</select>
				<?php else: ?>
				<span class="aips-pill aips-pill-danger"><?php esc_html_e('Needs Action', 'ai-post-scheduler'); ?></span>
				<?php endif; ?>

				<div class="aips-row-action-group">
					<?php if (!empty($post_item['is_incomplete'])): ?>
					<button type="button" class="aips-btn aips-btn-sm aips-btn-warning aips-view-session"
						data-history-id="<?php echo esc_attr($post_item['history_id']); ?>"
						title="<?php esc_attr_e('View session logs', 'ai-post-scheduler'); ?>">
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<?php esc_html_e('Logs', 'ai-post-scheduler'); ?>
					</button>
					<?php else: ?>
					<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-post"
						data-edit-url="<?php echo esc_url($post_item['edit_link']); ?>"
						title="<?php esc_attr_e('Edit this post', 'ai-post-scheduler'); ?>">
						<span class="dashicons dashicons-edit" aria-hidden="true"></span>
						<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
					</button>
					<?php endif; ?>

					<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle"
						aria-haspopup="true"
						aria-expanded="false"
						aria-controls="aips-card-actions-<?php echo esc_attr($post_item['history_id']); ?>"
						title="<?php esc_attr_e('More actions', 'ai-post-scheduler'); ?>">
						<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					</button>
				</div>
				<div id="aips-card-actions-<?php echo esc_attr($post_item['history_id']); ?>" class="aips-row-action-menu" hidden>
					<?php if (!empty($post_item['post_id'])): ?>
					<button type="button" class="aips-row-action-item aips-preview-post"
						data-post-id="<?php echo esc_attr($post_item['post_id']); ?>">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
						<span><?php esc_html_e('Preview', 'ai-post-scheduler'); ?></span>
					</button>
					<button type="button" class="aips-row-action-item aips-ai-edit-btn"
						data-post-id="<?php echo esc_attr($post_item['post_id']); ?>"
						data-history-id="<?php echo esc_attr($post_item['history_id']); ?>">
						<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
						<span><?php esc_html_e('AI Edit', 'ai-post-scheduler'); ?></span>
					</button>
					<?php endif; ?>
					<button type="button" class="aips-row-action-item aips-view-session"
						data-history-id="<?php echo esc_attr($post_item['history_id']); ?>">
						<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
						<span><?php esc_html_e('View Session', 'ai-post-scheduler'); ?></span>
					</button>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

<?php else: ?>
	<!-- Empty State -->
	<div class="aips-panel-body aips-posts-empty-panel">
		<?php if (!empty($search_query) || !empty($active_status) || !empty($author_id) || !empty($template_id) || !empty($campaign_id)): ?>
		<div class="aips-empty-state">
			<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
			<h3 class="aips-empty-state-title"><?php esc_html_e('No Content Found', 'ai-post-scheduler'); ?></h3>
			<p class="aips-empty-state-description"><?php esc_html_e('No generated content matches your current filter criteria. Try adjusting or clearing your filters.', 'ai-post-scheduler'); ?></p>
			<div class="aips-empty-state-actions">
				<a href="<?php echo esc_url($controller->build_generated_posts_page_url(1, array('author_id' => 0, 'template_id' => 0, 'campaign_id' => 0, 'post_status' => '', 'post_type' => '', 's' => ''))); ?>" class="aips-btn aips-btn-primary">
					<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
					<?php esc_html_e('Clear All Filters', 'ai-post-scheduler'); ?>
				</a>
			</div>
		</div>
		<?php else: ?>
		<div class="aips-empty-state">
			<div class="dashicons dashicons-admin-post aips-empty-state-icon" aria-hidden="true"></div>
			<h3 class="aips-empty-state-title"><?php esc_html_e('No Generated Content Yet', 'ai-post-scheduler'); ?></h3>
			<p class="aips-empty-state-description"><?php esc_html_e('Start creating automated AI content by setting up templates, campaigns, and schedules.', 'ai-post-scheduler'); ?></p>
			<div class="aips-empty-state-actions">
				<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('campaign_wizard')); ?>" class="aips-btn aips-btn-primary">
					<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
					<?php esc_html_e('Launch Campaign Wizard', 'ai-post-scheduler'); ?>
				</a>
				<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('templates')); ?>" class="aips-btn aips-btn-secondary">
					<span class="dashicons dashicons-layout" aria-hidden="true"></span>
					<?php esc_html_e('Create Template', 'ai-post-scheduler'); ?>
				</a>
			</div>
		</div>
		<?php endif; ?>
	</div>
<?php endif; ?>

<!-- Table Navigation & Pagination -->
<div class="tablenav aips-posts-pagination-nav">
	<span class="aips-table-footer-count">
		<?php printf( esc_html( _n( '%s item', '%s items', isset($pagination['total']) ? $pagination['total'] : 0, 'ai-post-scheduler' ) ), number_format_i18n( isset($pagination['total']) ? $pagination['total'] : 0 ) ); ?>
	</span>
	<?php if (!empty($pagination['pages']) && $pagination['pages'] > 1): ?>
	<div class="aips-history-pagination-links">
		<?php if ($pagination['current'] > 1): ?>
			<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-prev" href="<?php echo esc_url($controller->build_generated_posts_page_url($pagination['current'] - 1)); ?>" aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
			</a>
		<?php else: ?>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-prev" disabled aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
			</button>
		<?php endif; ?>

		<span class="aips-history-page-numbers">
			<?php if ($pagination['start'] > 1): ?>
				<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url($controller->build_generated_posts_page_url(1)); ?>">1</a>
				<?php if ($pagination['start'] > 2): ?><span class="aips-page-dots">&hellip;</span><?php endif; ?>
			<?php endif; ?>

			<?php for ($i = $pagination['start']; $i <= $pagination['end']; $i++): ?>
				<?php if ($i === $pagination['current']): ?>
					<span class="aips-btn aips-btn-sm aips-btn-primary" aria-current="page"><?php echo $i; ?></span>
				<?php else: ?>
					<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url($controller->build_generated_posts_page_url($i)); ?>"><?php echo $i; ?></a>
				<?php endif; ?>
			<?php endfor; ?>

			<?php if ($pagination['end'] < $pagination['pages']): ?>
				<?php if ($pagination['end'] < $pagination['pages'] - 1): ?><span class="aips-page-dots">&hellip;</span><?php endif; ?>
				<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url($controller->build_generated_posts_page_url($pagination['pages'])); ?>"><?php echo $pagination['pages']; ?></a>
			<?php endif; ?>
		</span>

		<?php if ($pagination['current'] < $pagination['pages']): ?>
			<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-next" href="<?php echo esc_url($controller->build_generated_posts_page_url($pagination['current'] + 1)); ?>" aria-label="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
			</a>
		<?php else: ?>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-next" disabled aria-label="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
			</button>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
