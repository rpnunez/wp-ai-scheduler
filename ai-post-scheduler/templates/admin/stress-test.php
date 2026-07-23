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

			<div class="aips-panel-header">
				<h2><?php esc_html_e('Stress Test', 'ai-post-scheduler'); ?></h2>
				<div class="aips-btn-group">
					<button type="button" class="aips-btn aips-btn-primary" id="aips-stress-run-all">
						<span class="dashicons dashicons-controls-play"></span>
						<?php esc_html_e('Run All', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-btn aips-btn-secondary" id="aips-stress-reset">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e('Reset', 'ai-post-scheduler'); ?>
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

		</div>

<?php if (!$is_embedded) : ?>
	</div>
</div>
<?php endif; ?>
