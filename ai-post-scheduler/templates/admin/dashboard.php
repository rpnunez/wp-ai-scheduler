<?php
if (!defined('ABSPATH')) {
	exit;
}

$page_context = AIPS_Admin_Page_Context::resolve('ai-post-scheduler');
$screen_id = $page_context->get_screen_id();
if (empty($screen_id)) {
	$screen_id = 'toplevel_page_ai-post-scheduler';
}

AIPS_Dashboard_Controller::register_meta_boxes($screen_id);
?>
<div class="wrap aips-wrap">
	<?php if (empty($ai_provider_available)): ?>
	<div class="notice notice-error">
		<p><?php esc_html_e('No AI provider is available. Install the Meow Apps AI Engine plugin or configure a WordPress AI Client connector (WordPress 7.0+).', 'ai-post-scheduler'); ?></p>
	</div>
	<?php endif; ?>

	<div class="aips-page-container" id="aips-dashboard-panel">
		<div class="aips-dashboard-spinner-overlay"><span class="spinner is-active"></span></div>

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div class="aips-page-header-info">
					<h1 class="aips-page-title">
						<span class="dashicons dashicons-chart-pie aips-page-title-icon" aria-hidden="true"></span>
						<span><?php esc_html_e('Analytics Dashboard', 'ai-post-scheduler'); ?></span>
					</h1>
					<p class="aips-page-description"><?php esc_html_e('Observe your AI content generation pipelines, success rates, and upcoming schedules.', 'ai-post-scheduler'); ?></p>
				</div>
				
				<!-- Date Filter Popover & Quick Links -->
				<div class="aips-header-controls">
					<!-- Date Range Filter Trigger Button -->
					<div class="aips-date-filter-container">
						<button type="button" class="aips-btn aips-btn-secondary id-aips-date-filter-btn" id="aips-date-filter-trigger">
							<span class="dashicons dashicons-calendar-alt"></span>
							<span class="aips-date-range-label"><?php echo esc_html($date_from) . ' – ' . esc_html($date_to); ?></span>
							<span class="dashicons dashicons-arrow-down-alt2"></span>
						</button>
						
						<!-- Popover Panel -->
						<div class="aips-date-popover" id="aips-date-popover-panel">
							<form method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>" id="aips-dashboard-date-form">
								<input type="hidden" name="page" value="ai-post-scheduler" />
								<div class="aips-popover-body">
									<div class="aips-form-group">
										<label class="aips-form-label" for="aips-input-date-from"><?php esc_html_e('Start Date', 'ai-post-scheduler'); ?></label>
										<input type="date" id="aips-input-date-from" name="date_from" class="aips-form-input" value="<?php echo esc_attr($date_from); ?>" max="<?php echo esc_attr($date_to); ?>" required />
									</div>
									<div class="aips-form-group">
										<label class="aips-form-label" for="aips-input-date-to"><?php esc_html_e('End Date', 'ai-post-scheduler'); ?></label>
										<input type="date" id="aips-input-date-to" name="date_to" class="aips-form-input" value="<?php echo esc_attr($date_to); ?>" min="<?php echo esc_attr($date_from); ?>" required />
									</div>
								</div>
								<div class="aips-popover-footer">
									<button type="button" class="aips-btn aips-btn-ghost aips-btn-sm" id="aips-date-popover-cancel"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
									<button type="submit" class="aips-btn aips-btn-primary aips-btn-sm" id="aips-date-popover-apply"><?php esc_html_e('Apply', 'ai-post-scheduler'); ?></button>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Render Native WordPress Meta Box 2-Column Layout -->
		<?php AIPS_Screen_Meta_Box_Helper::render_layout($screen_id, $dashboard_data ?? get_defined_vars()); ?>

	</div>
</div>

<script type="text/html" id="aips-tmpl-dashboard-posts-row">
	<tr>
		<td>
			{{title_html}}
		</td>
		<td>
			<span class="cell-primary">{{template_name}}</span>
			<span class="cell-meta">{{creation_method}}</span>
		</td>
		<td>
			<span class="aips-badge aips-badge-{{status_badge}}">
				{{status}}
			</span>
		</td>
		<td class="cell-meta">
			{{created_at_formatted}}
			{{actions_html}}
		</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-dashboard-topics-row">
	<tr data-id="{{id}}">
		<td>
			<div class="cell-primary">{{topic_title}}</div>
		</td>
		<td>
			<div class="cell-primary">{{author_name}}</div>
		</td>
		<td>
			<strong class="topic-score-badge">{{score}}</strong>
		</td>
		<td>
			<span class="aips-badge aips-badge-{{status_badge}} status-badge">
				{{status}}
			</span>
		</td>
		<td class="cell-meta">
			{{generated_at_formatted}}
			<span class="actions-container">
				{{actions_html}}
			</span>
		</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-dashboard-topic-posts-row">
	<tr>
		<td>
			{{title_html}}
		</td>
		<td>
			<div class="cell-primary">{{topic_title}}</div>
		</td>
		<td>
			<div class="cell-primary">{{author_name}}</div>
		</td>
		<td class="cell-meta">{{completed_at_formatted}}</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-dashboard-schedules-row">
	<tr data-id="{{id}}">
		<td>
			<div class="cell-primary">{{display_title}}</div>
		</td>
		<td>
			<span class="aips-badge aips-badge-neutral">{{method_label}}</span>
		</td>
		<td>
			<span class="aips-badge aips-badge-{{status_badge}}">
				{{status}}
			</span>
		</td>
		<td class="cell-meta">
			{{created_at_formatted}}
			<span class="actions-container">
				{{actions_html}}
			</span>
		</td>
	</tr>
</script>

<script>
window.aipsDashboardChartData = <?php echo wp_json_encode($chart_data); ?>;
</script>
