<?php
/**
 * Stress Test History Admin Tab Template.
 *
 * Provided by AIPS_Diagnostics_Controller::render_stress_test_history_tab():
 *   $runs (array) – Array of historical stress test runs from AIPS_Stress_Test_Service::get_run_history()
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-content-panel aips-stress-test-history" id="aips-stress-test-history">
	<div class="aips-panel-header aips-flex aips-justify-between aips-items-center aips-flex-wrap aips-gap-md">
		<div>
			<h2><?php esc_html_e('Stress Test Run History & Diffs', 'ai-post-scheduler'); ?></h2>
			<p class="aips-panel-subtitle aips-text-muted aips-text-sm aips-mt-xs">
				<?php esc_html_e('Historical record of stress test runs tracked in the unified History API. Select any two runs to inspect changes, regressions, or duration deltas.', 'ai-post-scheduler'); ?>
			</p>
		</div>
		<div class="aips-btn-group aips-flex aips-items-center aips-gap-sm">
			<button type="button" class="aips-btn aips-btn-primary" id="aips-stress-history-diff-btn" disabled>
				<span class="dashicons dashicons-forms"></span>
				<?php esc_html_e('Compare 2 Selected Runs', 'ai-post-scheduler'); ?>
			</button>
			<a href="<?php echo esc_url(add_query_arg(array('page' => 'aips-diagnostics', 'tab' => 'stress-test'), admin_url('admin.php'))); ?>" class="aips-btn aips-btn-secondary">
				<span class="dashicons dashicons-performance"></span>
				<?php esc_html_e('Run New Stress Test', 'ai-post-scheduler'); ?>
			</a>
		</div>
	</div>

	<?php if (empty($runs)) : ?>
		<div class="aips-empty-state aips-text-center aips-p-xl">
			<span class="dashicons dashicons-backup aips-empty-state-icon aips-mb-sm"></span>
			<h3 class="aips-empty-state-title aips-mb-xs"><?php esc_html_e('No Stress Test Runs Recorded Yet', 'ai-post-scheduler'); ?></h3>
			<p class="aips-empty-state-description aips-text-muted aips-mb-md">
				<?php esc_html_e('Run the Stress Test suite to automatically record execution metrics, raw AI responses, and provider performance snapshots.', 'ai-post-scheduler'); ?>
			</p>
			<a href="<?php echo esc_url(add_query_arg(array('page' => 'aips-diagnostics', 'tab' => 'stress-test'), admin_url('admin.php'))); ?>" class="aips-btn aips-btn-primary">
				<?php esc_html_e('Go to Stress Test', 'ai-post-scheduler'); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="aips-table aips-stress-history-table aips-w-full">
			<thead>
				<tr>
					<th class="aips-text-center" style="width:40px;"><span class="screen-reader-text"><?php esc_html_e('Select for comparison', 'ai-post-scheduler'); ?></span></th>
					<th><?php esc_html_e('Date & Time', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Provider & Model', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Passed / Total', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Duration', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
					<th class="aips-text-right"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($runs as $index => $run) : ?>
					<tr class="aips-stress-history-row" data-history-id="<?php echo esc_attr((string) $run['id']); ?>">
						<td class="aips-text-center">
							<input type="checkbox" class="aips-stress-history-checkbox" value="<?php echo esc_attr((string) $run['id']); ?>" aria-label="<?php esc_attr_e('Select run for diff comparison', 'ai-post-scheduler'); ?>" />
						</td>
						<td>
							<strong><?php echo esc_html($run['formatted_date']); ?></strong>
							<div class="aips-text-xs aips-text-muted">#<?php echo esc_html((string) $run['id']); ?> (<?php echo esc_html(substr($run['uuid'], 0, 8)); ?>)</div>
						</td>
						<td>
							<div><?php echo esc_html($run['provider']); ?></div>
							<div class="aips-text-sm aips-text-muted"><?php echo esc_html($run['model'] ?: __('Default', 'ai-post-scheduler')); ?></div>
						</td>
						<td>
							<span class="aips-badge <?php echo $run['passed'] === $run['total_cases'] ? 'aips-badge-success' : ($run['passed'] > 0 ? 'aips-badge-warning' : 'aips-badge-error'); ?>">
								<?php echo esc_html(sprintf('%d / %d', $run['passed'], $run['total_cases'])); ?>
							</span>
						</td>
						<td>
							<?php echo esc_html(sprintf('%.2f s', $run['duration_ms'] / 1000)); ?>
						</td>
						<td>
							<span class="aips-badge <?php echo $run['status'] === 'completed' ? 'aips-badge-success' : ($run['status'] === 'partial' ? 'aips-badge-warning' : 'aips-badge-error'); ?>">
								<?php echo esc_html(ucfirst($run['status'])); ?>
							</span>
						</td>
						<td class="aips-text-right">
							<button type="button" class="aips-btn aips-btn-secondary aips-btn-sm aips-stress-view-run-btn" data-history-id="<?php echo esc_attr((string) $run['id']); ?>">
								<span class="dashicons dashicons-visibility"></span>
								<?php esc_html_e('View', 'ai-post-scheduler'); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<!-- Side-by-Side Diff Modal -->
	<div class="aips-modal" id="aips-stress-diff-modal" hidden style="display:none;">
		<div class="aips-modal-backdrop"></div>
		<div class="aips-modal-dialog aips-modal-lg">
			<div class="aips-modal-header aips-flex aips-justify-between aips-items-center">
				<h3 class="aips-modal-title aips-flex aips-items-center aips-gap-xs">
					<span class="dashicons dashicons-forms"></span>
					<?php esc_html_e('Stress Test Comparison & Diff', 'ai-post-scheduler'); ?>
				</h3>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">&times;</button>
			</div>
			<div class="aips-modal-body" id="aips-stress-diff-body">
				<div class="aips-spinner"></div>
			</div>
			<div class="aips-modal-footer aips-flex aips-justify-end">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-close-btn"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
			</div>
		</div>
	</div>

	<!-- Client-Side Templates for AIPS.Templates Engine -->
	<script type="text/html" id="aips-tmpl-stress-spinner">
		<div class="aips-spinner-wrap aips-text-center aips-p-lg">
			<span class="dashicons dashicons-update aips-spin aips-text-xl"></span>
		</div>
	</script>

	<script type="text/html" id="aips-tmpl-stress-notice">
		<div class="aips-stress-notice aips-stress-notice-{{type}}">
			<p>{{message}}</p>
		</div>
	</script>

	<script type="text/html" id="aips-tmpl-stress-diff-modal">
		<div class="aips-stress-diff-meta-grid">
			<div class="aips-stress-diff-card">
				<h4>Base Run (#{{runAId}}) <span class="aips-badge {{runABadgeClass}}">{{runAStatus}}</span></h4>
				<div class="aips-stress-diff-meta-list">
					<div><strong>Date:</strong> {{runADate}}</div>
					<div><strong>Provider:</strong> {{runAProvider}} ({{runAModel}})</div>
					<div><strong>Passed:</strong> {{runAPassed}} / {{runATotal}} cases</div>
					<div><strong>Total Duration:</strong> {{runADuration}}</div>
				</div>
			</div>
			<div class="aips-stress-diff-card">
				<h4>Target / Comparison Run (#{{runBId}}) <span class="aips-badge {{runBBadgeClass}}">{{runBStatus}}</span></h4>
				<div class="aips-stress-diff-meta-list">
					<div><strong>Date:</strong> {{runBDate}}</div>
					<div><strong>Provider:</strong> {{runBProvider}} ({{runBModel}})</div>
					<div><strong>Passed:</strong> {{runBPassed}} / {{runBTotal}} cases</div>
					<div><strong>Total Duration:</strong> {{runBDuration}} {{durBadgeHtml}}</div>
				</div>
			</div>
		</div>
		<table class="aips-stress-diff-table">
			<thead>
				<tr>
					<th>Test Case</th>
					<th>Base Run Status</th>
					<th>Target Run Status</th>
					<th>Duration Diff</th>
					<th>Outcome Delta</th>
				</tr>
			</thead>
			<tbody>
				{{rowsHtml}}
			</tbody>
		</table>
	</script>

	<script type="text/html" id="aips-tmpl-stress-diff-row">
		<tr class="{{changeClass}}">
			<td><strong>{{label}}</strong><div class="aips-text-xs aips-text-muted">{{summary}}</div></td>
			<td><span class="aips-badge {{statusABadgeClass}}">{{statusA}}</span> ({{durationA}} ms)</td>
			<td><span class="aips-badge {{statusBBadgeClass}}">{{statusB}}</span> ({{durationB}} ms)</td>
			<td>{{deltaBadgeHtml}}</td>
			<td>{{changeBadgeHtml}}</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-stress-single-run">
		<div class="aips-stress-diff-card aips-mb-md">
			<h4>Stress Test Run #{{id}} <span class="aips-badge {{badgeClass}}">{{status}}</span></h4>
			<div class="aips-stress-diff-meta-list">
				<div><strong>Timestamp:</strong> {{date}}</div>
				<div><strong>Provider:</strong> {{provider}} | <strong>Model:</strong> {{model}}</div>
				<div><strong>Results:</strong> {{passed}} passed, {{failed}} failed across {{total}} cases</div>
				<div><strong>Total Execution Time:</strong> {{duration}}</div>
			</div>
		</div>
		<table class="aips-table aips-w-full">
			<thead>
				<tr>
					<th>Case</th>
					<th>Status</th>
					<th>Duration</th>
					<th>Summary</th>
				</tr>
			</thead>
			<tbody>
				{{rowsHtml}}
			</tbody>
		</table>
	</script>

	<script type="text/html" id="aips-tmpl-stress-single-run-row">
		<tr>
			<td><strong>{{label}}</strong></td>
			<td><span class="aips-badge {{badgeClass}}">{{status}}</span></td>
			<td>{{duration}} ms</td>
			<td>{{summary}}</td>
		</tr>
	</script>
</div>
