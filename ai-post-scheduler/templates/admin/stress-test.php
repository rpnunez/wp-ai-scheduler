<?php
/**
 * Stress Test admin page.
 *
 * Provided by AIPS_Stress_Test_Controller::render_page():
 *   $cases       (array) – Case definitions from AIPS_Stress_Test_Service::get_cases()
 *   $environment (array) – Provider/model snapshot
 *   $test_data   (array) – Counts of leftover posts/attachments
 *   $embedded    (bool)  – Whether rendered inside a Diagnostics tab
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$is_embedded  = !empty($embedded);
$creates_data = false;

foreach ($cases as $case) {
	if (!empty($case['creates'])) {
		$creates_data = true;
		break;
	}
}
?>
<?php if (!$is_embedded) : ?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">

		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title">
						<span class="dashicons dashicons-performance" style="font-size:30px;vertical-align:middle;margin-right:6px;"></span>
						<?php esc_html_e('Stress Test', 'ai-post-scheduler'); ?>
					</h1>
					<p class="aips-page-description">
						<?php esc_html_e('Exercise the configured AI provider end to end. Each case shows what the provider returned alongside what the plugin produced from it.', 'ai-post-scheduler'); ?>
					</p>
				</div>
			</div>
		</div>
<?php endif; ?>

		<div class="aips-content-panel aips-stress-test" id="aips-stress-test">

			<?php
			// Structured snapshot the Export Results button folds into its download,
			// so shared output identifies the exact provider/model/version it ran on.
			$export_meta = array(
				'plugin_version' => defined('AIPS_VERSION') ? AIPS_VERSION : '',
				'environment'    => $environment,
			);
			?>
			<script type="application/json" id="aips-stress-export-meta"><?php echo wp_json_encode($export_meta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>

			<div class="aips-panel-header">
				<h2><?php esc_html_e('Stress Test', 'ai-post-scheduler'); ?></h2>
				<div class="aips-btn-group" style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;">
					<div class="aips-stress-history-picker" style="display:inline-flex;align-items:center;gap:6px;margin-right:4px;">
						<label for="aips-stress-history-select" class="screen-reader-text"><?php esc_html_e('Prior Runs', 'ai-post-scheduler'); ?></label>
						<select id="aips-stress-history-select" class="aips-select aips-select-sm" style="max-width:210px;height:32px;font-size:12px;">
							<option value=""><?php esc_html_e('— History / Prior Runs —', 'ai-post-scheduler'); ?></option>
						</select>
						<button type="button" class="aips-btn aips-btn-secondary aips-btn-sm" id="aips-stress-compare-btn" title="<?php esc_attr_e('Compare selected run against current results', 'ai-post-scheduler'); ?>" disabled>
							<span class="dashicons dashicons-forms"></span>
							<?php esc_html_e('Compare', 'ai-post-scheduler'); ?>
						</button>
					</div>
					<button type="button" class="aips-btn aips-btn-primary" id="aips-stress-run-all">
						<span class="dashicons dashicons-controls-play"></span>
						<?php esc_html_e('Run All', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-btn aips-btn-secondary" id="aips-stress-reset">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e('Reset', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-btn aips-btn-secondary" id="aips-stress-export" disabled>
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e('Export Results', 'ai-post-scheduler'); ?>
					</button>
					<?php if ($creates_data) : ?>
						<button type="button" class="aips-btn aips-btn-danger" id="aips-stress-cleanup">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e('Delete Test Data', 'ai-post-scheduler'); ?>
							<span class="aips-stress-testdata-count"<?php echo ($test_data['posts'] + $test_data['attachments']) > 0 ? '' : ' hidden'; ?>>
								<?php echo esc_html((string) ($test_data['posts'] + $test_data['attachments'])); ?>
							</span>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<!-- Environment -->
			<div class="aips-stress-env">
				<div class="aips-stress-env-item">
					<span class="aips-stress-env-label"><?php esc_html_e('Provider', 'ai-post-scheduler'); ?></span>
					<span class="aips-stress-env-value">
						<?php echo esc_html($environment['provider']); ?>
						<?php if ($environment['available']) : ?>
							<span class="aips-badge aips-badge-success"><?php esc_html_e('Ready', 'ai-post-scheduler'); ?></span>
						<?php else : ?>
							<span class="aips-badge aips-badge-error"><?php esc_html_e('Unavailable', 'ai-post-scheduler'); ?></span>
						<?php endif; ?>
					</span>
				</div>
				<div class="aips-stress-env-item">
					<span class="aips-stress-env-label"><?php esc_html_e('Model', 'ai-post-scheduler'); ?></span>
					<span class="aips-stress-env-value">
						<?php echo $environment['model'] !== '' ? esc_html($environment['model']) : esc_html__('Provider default', 'ai-post-scheduler'); ?>
					</span>
				</div>
				<div class="aips-stress-env-item">
					<span class="aips-stress-env-label"><?php esc_html_e('Native JSON', 'ai-post-scheduler'); ?></span>
					<span class="aips-stress-env-value">
						<?php echo $environment['native_json'] ? esc_html__('Supported', 'ai-post-scheduler') : esc_html__('Text fallback', 'ai-post-scheduler'); ?>
					</span>
				</div>
				<div class="aips-stress-env-item">
					<span class="aips-stress-env-label"><?php esc_html_e('Conversation', 'ai-post-scheduler'); ?></span>
					<span class="aips-stress-env-value">
						<?php if (!$environment['conversation']) : ?>
							<?php esc_html_e('Not supported', 'ai-post-scheduler'); ?>
						<?php elseif ($environment['conversational']) : ?>
							<?php echo $environment['metadata_turn'] ? esc_html__('On + combined turn', 'ai-post-scheduler') : esc_html__('On', 'ai-post-scheduler'); ?>
						<?php else : ?>
							<?php esc_html_e('Available, disabled', 'ai-post-scheduler'); ?>
						<?php endif; ?>
					</span>
				</div>
			</div>

			<?php if (!$environment['available']) : ?>
				<div class="aips-stress-notice aips-stress-notice-danger">
					<span class="dashicons dashicons-warning"></span>
					<span><?php echo esc_html($environment['reason']); ?></span>
				</div>
			<?php endif; ?>

			<!-- Progress -->
			<div class="aips-stress-progress" id="aips-stress-progress" hidden>
				<div class="aips-stress-progress-bar"><span></span></div>
				<div class="aips-stress-progress-label"></div>
			</div>

			<!-- Results summary -->
			<div class="aips-stress-summary" id="aips-stress-summary" hidden>
				<div class="aips-stress-summary-banner">
					<div class="aips-stress-summary-icon"></div>
					<div class="aips-stress-summary-text">
						<h3></h3>
						<p></p>
					</div>
				</div>
				<ul class="aips-stress-summary-list"></ul>
			</div>

			<!-- Cases -->
			<table class="aips-table aips-stress-table">
				<thead>
					<tr>
						<th class="aips-stress-col-status"><span class="screen-reader-text"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></span></th>
						<th><?php esc_html_e('Test Case', 'ai-post-scheduler'); ?></th>
						<th class="aips-stress-col-result"><?php esc_html_e('Result', 'ai-post-scheduler'); ?></th>
						<th class="aips-stress-col-time"><?php esc_html_e('Time', 'ai-post-scheduler'); ?></th>
						<th class="aips-stress-col-actions"><span class="screen-reader-text"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($cases as $case) : ?>
						<tr class="aips-stress-row" data-case="<?php echo esc_attr($case['id']); ?>" data-status="idle">
							<td class="aips-stress-col-status">
								<span class="aips-stress-indicator" aria-hidden="true"></span>
							</td>
							<td>
								<button type="button" class="aips-stress-toggle" aria-expanded="false" aria-controls="aips-stress-details-<?php echo esc_attr($case['id']); ?>">
									<span class="dashicons dashicons-arrow-right-alt2 aips-stress-caret"></span>
									<span class="aips-stress-case-label"><?php echo esc_html($case['label']); ?></span>
								</button>
								<p class="aips-stress-case-desc"><?php echo esc_html($case['description']); ?></p>
								<?php if (!empty($case['creates'])) : ?>
									<span class="aips-badge aips-badge-warning aips-stress-creates">
										<?php esc_html_e('Creates data', 'ai-post-scheduler'); ?>
									</span>
								<?php endif; ?>
							</td>
							<td class="aips-stress-col-result">
								<span class="aips-stress-result-text"><?php esc_html_e('Not run', 'ai-post-scheduler'); ?></span>
							</td>
							<td class="aips-stress-col-time">
								<span class="aips-stress-duration">—</span>
							</td>
							<td class="aips-stress-col-actions">
								<button type="button" class="aips-btn aips-btn-secondary aips-btn-sm aips-stress-run-one">
									<?php esc_html_e('Run', 'ai-post-scheduler'); ?>
								</button>
							</td>
						</tr>
						<tr class="aips-stress-details-row" id="aips-stress-details-<?php echo esc_attr($case['id']); ?>" hidden>
							<td colspan="5">
								<div class="aips-stress-details"></div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Side-by-Side Diff Modal -->
			<div class="aips-modal" id="aips-stress-diff-modal" hidden>
				<div class="aips-modal-backdrop"></div>
				<div class="aips-modal-dialog aips-modal-lg" style="max-width:980px;width:95vw;max-height:90vh;display:flex;flex-direction:column;">
					<div class="aips-modal-header" style="display:flex;justify-content:space-between;align-items:center;">
						<h3 class="aips-modal-title" style="margin:0;display:flex;align-items:center;gap:6px;">
							<span class="dashicons dashicons-forms"></span>
							<?php esc_html_e('Stress Test Comparison & Diff', 'ai-post-scheduler'); ?>
						</h3>
						<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>" style="background:none;border:none;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
					</div>
					<div class="aips-modal-body" id="aips-stress-diff-body" style="overflow-y:auto;flex:1;padding:16px;">
						<div class="aips-spinner"></div>
					</div>
					<div class="aips-modal-footer" style="padding:12px 16px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;">
						<button type="button" class="aips-btn aips-btn-secondary aips-modal-close-btn"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
					</div>
				</div>
			</div>

			<!-- Client-Side Templates for AIPS.Templates Engine -->
			<script type="text/html" id="aips-tmpl-stress-summary-item">
				<li data-status="{{status}}">
					<span class="aips-stress-summary-dot"></span>
					<span class="aips-stress-summary-label">{{label}}</span>
					<span class="aips-stress-summary-detail">{{detail}}</span>
					<span class="aips-stress-summary-time">{{time}}</span>
				</li>
			</script>

			<script type="text/html" id="aips-tmpl-stress-spinner">
				<div class="aips-spinner" style="text-align:center;padding:32px;">
					<span class="dashicons dashicons-update" style="animation:spin 1s infinite linear;font-size:28px;"></span>
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
					<td><strong>{{label}}</strong><div style="font-size:11px;color:#64748b;">{{summary}}</div></td>
					<td><span class="aips-badge {{statusABadgeClass}}">{{statusA}}</span> ({{durationA}} ms)</td>
					<td><span class="aips-badge {{statusBBadgeClass}}">{{statusB}}</span> ({{durationB}} ms)</td>
					<td>{{deltaBadgeHtml}}</td>
					<td>{{changeBadgeHtml}}</td>
				</tr>
			</script>

			<script type="text/html" id="aips-tmpl-stress-single-run">
				<div class="aips-stress-diff-card" style="margin-bottom:16px;">
					<h4>Stress Test Run #{{id}} <span class="aips-badge {{badgeClass}}">{{status}}</span></h4>
					<div class="aips-stress-diff-meta-list">
						<div><strong>Timestamp:</strong> {{date}}</div>
						<div><strong>Provider:</strong> {{provider}} | <strong>Model:</strong> {{model}}</div>
						<div><strong>Results:</strong> {{passed}} passed, {{failed}} failed across {{total}} cases</div>
						<div><strong>Total Execution Time:</strong> {{duration}}</div>
					</div>
				</div>
				<table class="aips-table" style="width:100%;">
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

<?php if (!$is_embedded) : ?>
	</div>
</div>
<?php endif; ?>
