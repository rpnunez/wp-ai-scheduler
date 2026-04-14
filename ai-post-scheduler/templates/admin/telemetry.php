<?php
/**
 * Telemetry Admin Template
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Telemetry', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Inspect request-level performance trends, refresh charts by date range, and review raw telemetry rows.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<a class="aips-btn aips-btn-secondary" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('system_status')); ?>">
						<span class="dashicons dashicons-admin-tools"></span>
						<?php esc_html_e('Back to System Status', 'ai-post-scheduler'); ?>
					</a>
				</div>
			</div>
		</div>

		<div class="aips-content-panel aips-telemetry-overview-panel" id="aips-telemetry-panel">
			<div class="aips-panel-header">
				<h2>
					<span class="dashicons dashicons-chart-area"></span>
					<?php esc_html_e('Stats Overview', 'ai-post-scheduler'); ?>
				</h2>
			</div>
			<div class="aips-filter-bar aips-telemetry-filter-bar">
				<div class="aips-filter-left">
					<label class="aips-filter-label-inline" for="aips-telemetry-start-date">
						<span><?php esc_html_e('Start Date', 'ai-post-scheduler'); ?></span>
						<input type="date" id="aips-telemetry-start-date" class="aips-form-input" value="<?php echo esc_attr($start_date); ?>">
					</label>
					<label class="aips-filter-label-inline" for="aips-telemetry-end-date">
						<span><?php esc_html_e('End Date', 'ai-post-scheduler'); ?></span>
						<input type="date" id="aips-telemetry-end-date" class="aips-form-input" value="<?php echo esc_attr($end_date); ?>">
					</label>
					<button type="button" class="aips-btn aips-btn-secondary aips-telemetry-refresh">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e('Refresh', 'ai-post-scheduler'); ?>
					</button>
				</div>
				<div class="aips-filter-right">
					<p id="aips-telemetry-range-summary" class="aips-telemetry-range-summary"></p>
				</div>
			</div>
			<div class="aips-panel-body">
				<div class="aips-telemetry-charts-grid">
					<div class="aips-telemetry-chart-card">
						<div class="aips-telemetry-chart-canvas-wrap">
							<canvas id="aips-telemetry-chart-queries" aria-label="<?php esc_attr_e('Queries Executed per Day chart', 'ai-post-scheduler'); ?>"></canvas>
						</div>
					</div>
					<div class="aips-telemetry-chart-card">
						<div class="aips-telemetry-chart-canvas-wrap">
							<canvas id="aips-telemetry-chart-memory" aria-label="<?php esc_attr_e('Peak Memory per Day chart', 'ai-post-scheduler'); ?>"></canvas>
						</div>
					</div>
					<div class="aips-telemetry-chart-card">
						<div class="aips-telemetry-chart-canvas-wrap">
							<canvas id="aips-telemetry-chart-elapsed" aria-label="<?php esc_attr_e('Average Elapsed Time per Day chart', 'ai-post-scheduler'); ?>"></canvas>
						</div>
					</div>
					<div class="aips-telemetry-chart-card">
						<div class="aips-telemetry-chart-canvas-wrap">
							<canvas id="aips-telemetry-chart-requests" aria-label="<?php esc_attr_e('Requests Logged per Day chart', 'ai-post-scheduler'); ?>"></canvas>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="aips-content-panel aips-telemetry-table-panel">
			<div class="aips-panel-header">
				<h2>
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e('Telemetry', 'ai-post-scheduler'); ?>
				</h2>
			</div>
			<div class="aips-panel-toolbar aips-telemetry-table-toolbar">
				<div class="aips-toolbar-left">
					<span id="aips-telemetry-count" class="aips-telemetry-count"></span>
				</div>
				<div class="aips-toolbar-right">
					<label class="aips-filter-label-inline" for="aips-telemetry-per-page">
						<span><?php esc_html_e('Rows per page', 'ai-post-scheduler'); ?></span>
						<select id="aips-telemetry-per-page" class="aips-form-select">
							<option value="25" <?php selected($per_page, 25); ?>>25</option>
							<option value="50">50</option>
							<option value="100">100</option>
						</select>
					</label>
					<button type="button" class="aips-btn aips-btn-secondary aips-telemetry-refresh">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e('Refresh', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
			<div class="aips-panel-body no-padding">
				<div class="aips-telemetry-table-wrap">
					<table class="aips-table aips-telemetry-table">
						<thead>
							<tr>
								<th><?php esc_html_e('ID', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Page', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Method', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('User ID', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Queries', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Peak Memory', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Elapsed (ms)', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Inserted At', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody id="aips-telemetry-tbody">
							<tr>
								<td colspan="8" class="aips-telemetry-loading"><?php esc_html_e('Loading…', 'ai-post-scheduler'); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="aips-panel-toolbar aips-telemetry-pagination">
				<div class="aips-toolbar-left">
					<span id="aips-telemetry-page-label" class="aips-telemetry-page-label"></span>
				</div>
				<div class="aips-toolbar-right aips-btn-group">
					<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-telemetry-prev" disabled>
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<span class="screen-reader-text"><?php esc_html_e('Previous page', 'ai-post-scheduler'); ?></span>
					</button>
					<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-telemetry-next" disabled>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
						<span class="screen-reader-text"><?php esc_html_e('Next page', 'ai-post-scheduler'); ?></span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<script type="text/html" id="aips-tmpl-telemetry-message-row">
	<tr>
		<td colspan="8" class="{{class_name}}">{{message}}</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-telemetry-refresh-button-content">
	<span class="dashicons dashicons-update"></span>
	{{label}}
</script>

<script type="text/html" id="aips-tmpl-telemetry-data-row">
	<tr>
		<td>{{id}}</td>
		<td>{{page}}</td>
		<td>{{request_method}}</td>
		<td>{{user_id}}</td>
		<td>{{num_queries}}</td>
		<td>{{peak_memory}}</td>
		<td>{{elapsed_ms}}</td>
		<td>{{inserted_at}}</td>
	</tr>
</script>