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
					<p class="aips-page-description"><?php esc_html_e('Inspect request-level telemetry, filter records, and compare request trends at a glance.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>

		<div class="aips-telemetry-dashboard-grid" id="aips-telemetry-panel">
			<div class="aips-telemetry-dashboard-main">
				<div class="aips-content-panel aips-telemetry-records-panel">
					<div class="aips-panel-header aips-telemetry-records-header">
						<h2>
							<span class="dashicons dashicons-list-view"></span>
							<?php esc_html_e('Records', 'ai-post-scheduler'); ?>
						</h2>
						<div class="aips-telemetry-header-actions">
							<label class="aips-filter-label-inline" for="aips-telemetry-per-page">
								<span><?php esc_html_e('Rows per page', 'ai-post-scheduler'); ?></span>
								<select id="aips-telemetry-per-page" class="aips-form-select">
									<option value="25" <?php selected($per_page, 25); ?>>25</option>
									<option value="50" <?php selected($per_page, 50); ?>>50</option>
									<option value="100" <?php selected($per_page, 100); ?>>100</option>
								</select>
							</label>
							<button type="button" class="aips-btn aips-btn-secondary aips-telemetry-refresh">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e('Refresh', 'ai-post-scheduler'); ?>
							</button>
						</div>
					</div>
					<div class="aips-panel-body aips-telemetry-records-body">
						<div class="aips-telemetry-filter-shell">
							<div class="aips-telemetry-filter-grid">
								<label class="aips-filter-label-inline aips-telemetry-filter-type" for="aips-telemetry-type-filter">
									<span><?php esc_html_e('Type', 'ai-post-scheduler'); ?></span>
									<select id="aips-telemetry-type-filter" class="aips-form-select">
										<option value=""><?php esc_html_e('All Types', 'ai-post-scheduler'); ?></option>
										<?php foreach ($filter_options['types'] as $option) : ?>
											<option value="<?php echo esc_attr($option['value']); ?>"><?php echo esc_html($option['label']); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="aips-filter-label-inline aips-telemetry-filter-category" for="aips-telemetry-category-filter">
									<span><?php esc_html_e('Category', 'ai-post-scheduler'); ?></span>
									<select id="aips-telemetry-category-filter" class="aips-form-select">
										<option value=""><?php esc_html_e('All Categories', 'ai-post-scheduler'); ?></option>
										<?php foreach ($filter_options['event_categories'] as $option) : ?>
											<option value="<?php echo esc_attr($option['value']); ?>"><?php echo esc_html($option['label']); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="aips-filter-label-inline aips-telemetry-filter-method" for="aips-telemetry-method-filter">
									<span><?php esc_html_e('Method', 'ai-post-scheduler'); ?></span>
									<select id="aips-telemetry-method-filter" class="aips-form-select">
										<option value=""><?php esc_html_e('All Methods', 'ai-post-scheduler'); ?></option>
										<?php foreach ($filter_options['request_methods'] as $option) : ?>
											<option value="<?php echo esc_attr($option['value']); ?>"><?php echo esc_html($option['label']); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="aips-filter-label-inline aips-telemetry-page-filter" for="aips-telemetry-page-filter">
									<span><?php esc_html_e('Page Contains', 'ai-post-scheduler'); ?></span>
									<input type="search" id="aips-telemetry-page-filter" class="aips-form-input" placeholder="<?php echo esc_attr__('admin:aips', 'ai-post-scheduler'); ?>">
								</label>
								<label class="aips-filter-label-inline aips-telemetry-filter-start" for="aips-telemetry-start-date">
									<span><?php esc_html_e('Start Date', 'ai-post-scheduler'); ?></span>
									<input type="date" id="aips-telemetry-start-date" class="aips-form-input" value="<?php echo esc_attr($start_date); ?>">
								</label>
								<label class="aips-filter-label-inline aips-telemetry-filter-end" for="aips-telemetry-end-date">
									<span><?php esc_html_e('End Date', 'ai-post-scheduler'); ?></span>
									<input type="date" id="aips-telemetry-end-date" class="aips-form-input" value="<?php echo esc_attr($end_date); ?>">
								</label>
								<label class="aips-filter-label-inline aips-telemetry-issues-toggle" for="aips-telemetry-issues-only">
									<span><?php esc_html_e('Issues Only', 'ai-post-scheduler'); ?></span>
									<input type="checkbox" id="aips-telemetry-issues-only" value="1">
								</label>
							</div>
							<div class="aips-telemetry-filter-actions">
								<div class="aips-telemetry-filter-buttons">
									<button type="button" class="aips-btn aips-btn-primary" id="aips-telemetry-apply-filters">
										<span class="dashicons dashicons-filter"></span>
										<?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
									</button>
									<button type="button" class="aips-btn aips-btn-secondary" id="aips-telemetry-reset-filters">
										<span class="dashicons dashicons-update"></span>
										<?php esc_html_e('Reset Filters', 'ai-post-scheduler'); ?>
									</button>
								</div>
								<span id="aips-telemetry-count" class="aips-telemetry-count aips-telemetry-count--summary"></span>
							</div>
						</div>
						<div class="aips-panel-body no-padding">
							<div class="aips-telemetry-table-wrap">
								<table class="aips-table aips-telemetry-table">
									<thead>
										<tr>
											<th><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
											<th><?php esc_html_e('Page', 'ai-post-scheduler'); ?></th>
											<th><?php esc_html_e('Categories', 'ai-post-scheduler'); ?></th>
											<th><?php esc_html_e('Method', 'ai-post-scheduler'); ?></th>
											<th><?php esc_html_e('Queries', 'ai-post-scheduler'); ?></th>
											<th><?php esc_html_e('Elapsed', 'ai-post-scheduler'); ?></th>
											<th><?php esc_html_e('Inserted At', 'ai-post-scheduler'); ?></th>
											<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
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
			<div class="aips-telemetry-dashboard-side">
				<div class="aips-content-panel aips-telemetry-charts-panel">
					<div class="aips-panel-header">
						<h2>
							<span class="dashicons dashicons-chart-area"></span>
							<?php esc_html_e('Charts', 'ai-post-scheduler'); ?>
						</h2>
					</div>
					<div class="aips-panel-body">
						<div class="aips-telemetry-charts-stack">
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
			</div>
		</div>

		<div id="aips-telemetry-details-modal" class="aips-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="aips-telemetry-details-title">
			<div class="aips-modal-content aips-modal-large">
				<div class="aips-modal-header">
					<h2 id="aips-telemetry-details-title"><?php esc_html_e('Telemetry Details', 'ai-post-scheduler'); ?></h2>
					<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
				</div>
				<div class="aips-modal-body" id="aips-telemetry-details-content">
					<p class="aips-telemetry-loading"><?php esc_html_e('Select a telemetry row to view its details.', 'ai-post-scheduler'); ?></p>
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
		<td>{{type}}</td>
		<td>{{page}}</td>
		<td>{{event_categories_html}}</td>
		<td>{{request_method}}</td>
		<td>{{num_queries}}</td>
		<td>{{elapsed_ms}}</td>
		<td>{{inserted_at}}</td>
		<td>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-telemetry-view-details" data-telemetry-id="{{raw_id}}" aria-label="{{view_details_label}}">
				<span class="dashicons dashicons-visibility"></span>
			</button>
		</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-telemetry-category-badge">
	<span class="aips-telemetry-category-badge aips-telemetry-category-badge--{{class_name}}">{{label}}</span>
</script>

<script type="text/html" id="aips-tmpl-telemetry-category-list">
	<div class="aips-telemetry-category-list">{{badges}}</div>
</script>

<script type="text/html" id="aips-tmpl-telemetry-category-empty">
	<span class="aips-telemetry-category-empty">{{label}}</span>
</script>

<script type="text/html" id="aips-tmpl-telemetry-detail-row">
	<tr>
		<th scope="row">{{label}}</th>
		<td>{{value}}</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-telemetry-details-loading">
	<div class="aips-details-section">
		<p class="aips-telemetry-loading">{{message}}</p>
	</div>
</script>

<script type="text/html" id="aips-tmpl-telemetry-details-modal-body">
	<div class="aips-details-section">
		<h3><?php esc_html_e('Request Details', 'ai-post-scheduler'); ?></h3>
		<table class="aips-details-table aips-telemetry-details-table-grid">
			<tbody>{{detail_rows}}</tbody>
		</table>
	</div>
	<div class="aips-details-section">
		<h3><?php esc_html_e('Payload', 'ai-post-scheduler'); ?></h3>
		{{payload_section}}
	</div>
</script>

<script type="text/html" id="aips-tmpl-telemetry-detail-pair-row">
	<tr>
		<th scope="row">{{label_1}}</th>
		<td>{{value_1}}</td>
		<th scope="row">{{label_2}}</th>
		<td>{{value_2}}</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-telemetry-payload-group">
	<div class="aips-telemetry-payload-section {{is_collapsed}}" id="aips-telemetry-section-{{section_key}}">
		<div class="aips-telemetry-payload-section-header">
			<div class="aips-telemetry-payload-section-copy">
				<h4>{{title}}</h4>
				<p>{{help_text}}</p>
			</div>
			<button type="button" class="aips-btn aips-btn-secondary aips-telemetry-payload-toggle" data-target="#aips-telemetry-section-{{section_key}}" aria-expanded="{{aria_expanded}}">
				<span class="aips-telemetry-payload-toggle-expand">{{button_expand_label}}</span>
				<span class="aips-telemetry-payload-toggle-collapse">{{button_collapse_label}}</span>
			</button>
		</div>
		<div class="aips-telemetry-payload-section-body">
			{{content_html}}
		</div>
	</div>
</script>

<script type="text/html" id="aips-tmpl-telemetry-payload-empty">
	<p class="aips-telemetry-loading">{{message}}</p>
</script>

<script type="text/html" id="aips-tmpl-telemetry-details-payload">
	<div class="aips-telemetry-payload-sections">
		{{payload_sections}}
	</div>
	<div class="aips-telemetry-payload-section aips-telemetry-payload-section--raw is-collapsed" id="aips-telemetry-raw-payload-section">
		<div class="aips-telemetry-payload-section-header">
			<div class="aips-telemetry-payload-section-copy">
				<h4>{{raw_payload_label}}</h4>
				<p>{{raw_payload_help}}</p>
			</div>
			<button type="button" class="aips-btn aips-btn-secondary aips-telemetry-payload-toggle" data-target="#aips-telemetry-raw-payload-section" aria-expanded="false">
				<span class="aips-telemetry-payload-toggle-expand">{{expand_label}}</span>
				<span class="aips-telemetry-payload-toggle-collapse">{{collapse_label}}</span>
			</button>
		</div>
		<div class="aips-telemetry-payload-section-body">
			<div class="aips-json-viewer"><pre>{{raw_payload_json}}</pre></div>
		</div>
	</div>
</script>