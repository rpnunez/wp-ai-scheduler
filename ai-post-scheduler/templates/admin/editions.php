<?php
/**
 * Editions admin template.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

$editions_repository = new AIPS_Editions_Repository();
$editions = $editions_repository->get_all();
$default_slot_types = AIPS_Editions_Repository::get_default_slot_types();
$cadence_options = array(
	'daily' => __('Daily', 'ai-post-scheduler'),
	'weekly' => __('Weekly', 'ai-post-scheduler'),
	'biweekly' => __('Bi-weekly', 'ai-post-scheduler'),
	'monthly' => __('Monthly', 'ai-post-scheduler'),
	'quarterly' => __('Quarterly', 'ai-post-scheduler'),
);
$channel_types = array(
	'web' => __('Web', 'ai-post-scheduler'),
	'newsletter' => __('Newsletter', 'ai-post-scheduler'),
	'social' => __('Social', 'ai-post-scheduler'),
	'multi-channel' => __('Multi-channel', 'ai-post-scheduler'),
);
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Editions', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Manage coordinated editorial packages with shared themes, ownership, package slots, and completeness indicators.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>

		<div class="aips-content-panel" style="margin-bottom:24px;">
			<div class="aips-panel-header">
				<div class="aips-panel-header-content">
					<span class="dashicons dashicons-portfolio dashicons-icon-lg"></span>
					<div>
						<h3 class="aips-panel-title"><?php esc_html_e('Edition Package Builder', 'ai-post-scheduler'); ?></h3>
						<p class="aips-panel-description"><?php esc_html_e('Define the package theme, cadence, launch target, and the package slots your editorial team needs to fill.', 'ai-post-scheduler'); ?></p>
					</div>
				</div>
			</div>
			<div class="aips-panel-body">
				<form id="aips-edition-form">
					<input type="hidden" name="edition_id" id="aips-edition-id" value="0">
					<div class="aips-form-grid" style="grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
						<div class="aips-form-field">
							<label for="aips-edition-name" class="aips-form-label"><?php esc_html_e('Edition Name', 'ai-post-scheduler'); ?></label>
							<input type="text" class="aips-form-input" id="aips-edition-name" name="name" required>
						</div>
						<div class="aips-form-field">
							<label for="aips-edition-theme" class="aips-form-label"><?php esc_html_e('Edition Theme', 'ai-post-scheduler'); ?></label>
							<input type="text" class="aips-form-input" id="aips-edition-theme" name="theme" placeholder="<?php echo esc_attr__('Optional. Defaults to the edition name.', 'ai-post-scheduler'); ?>">
						</div>
						<div class="aips-form-field">
							<label for="aips-edition-cadence" class="aips-form-label"><?php esc_html_e('Cadence', 'ai-post-scheduler'); ?></label>
							<select id="aips-edition-cadence" name="cadence" class="aips-form-input">
								<?php foreach ($cadence_options as $value => $label): ?>
								<option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="aips-form-field">
							<label for="aips-edition-target" class="aips-form-label"><?php esc_html_e('Target Publish Date', 'ai-post-scheduler'); ?></label>
							<input type="datetime-local" class="aips-form-input" id="aips-edition-target" name="target_publish_date" value="<?php echo esc_attr(gmdate('Y-m-d\TH:i')); ?>" required>
						</div>
						<div class="aips-form-field">
							<label for="aips-edition-required-slots" class="aips-form-label"><?php esc_html_e('Required Slots', 'ai-post-scheduler'); ?></label>
							<input type="number" min="1" class="aips-form-input" id="aips-edition-required-slots" name="required_slots" value="5" required>
						</div>
						<div class="aips-form-field">
							<label for="aips-edition-owner" class="aips-form-label"><?php esc_html_e('Owner', 'ai-post-scheduler'); ?></label>
							<input type="text" class="aips-form-input" id="aips-edition-owner" name="owner" required>
						</div>
						<div class="aips-form-field">
							<label for="aips-edition-channel" class="aips-form-label"><?php esc_html_e('Channel Type', 'ai-post-scheduler'); ?></label>
							<select id="aips-edition-channel" name="channel_type" class="aips-form-input">
								<?php foreach ($channel_types as $value => $label): ?>
								<option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="aips-form-field" style="align-self:end;">
							<label><input type="checkbox" name="is_active" id="aips-edition-active" checked> <?php esc_html_e('Edition is active', 'ai-post-scheduler'); ?></label>
						</div>
					</div>

					<h4 style="margin-top:24px;"><?php esc_html_e('Story Slots', 'ai-post-scheduler'); ?></h4>
					<p class="aips-field-description"><?php esc_html_e('These package slots can later be filled from Planner and will roll up into schedule, generated post, and review queue views.', 'ai-post-scheduler'); ?></p>
					<table class="aips-table" id="aips-edition-slot-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Include', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Slot Type', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Custom Label', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Sourcing', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Notes', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php $slot_index = 0; foreach ($default_slot_types as $slot_key => $slot_label): ?>
							<tr data-slot-key="<?php echo esc_attr($slot_key); ?>">
								<td>
									<input type="checkbox" class="aips-edition-slot-enabled" checked>
									<input type="hidden" name="slot_key[]" value="<?php echo esc_attr($slot_key); ?>">
									<input type="hidden" name="slot_assigned_topic[]" value="">
									<input type="hidden" name="slot_template_id[]" value="0">
									<input type="hidden" name="slot_schedule_id[]" value="0">
									<input type="hidden" name="slot_post_id[]" value="0">
								</td>
								<td><?php echo esc_html($slot_label); ?></td>
								<td><input type="text" class="aips-form-input" name="slot_label[]" value="<?php echo esc_attr($slot_label); ?>"></td>
								<td>
									<select name="slot_sourcing_status[]" class="aips-form-input">
										<option value="ready"><?php esc_html_e('Ready', 'ai-post-scheduler'); ?></option>
										<option value="missing"><?php esc_html_e('Missing sourcing', 'ai-post-scheduler'); ?></option>
									</select>
								</td>
								<td><input type="text" class="aips-form-input" name="slot_notes[]" value="" placeholder="<?php esc_attr_e('Optional slot guidance', 'ai-post-scheduler'); ?>"></td>
							</tr>
							<?php $slot_index++; endforeach; ?>
						</tbody>
					</table>

					<div style="margin-top:20px; display:flex; gap:12px; align-items:center;">
						<button type="submit" class="aips-btn aips-btn-primary" id="aips-save-edition-btn">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e('Save Edition', 'ai-post-scheduler'); ?>
						</button>
						<button type="button" class="aips-btn aips-btn-secondary" id="aips-reset-edition-btn"><?php esc_html_e('Reset Form', 'ai-post-scheduler'); ?></button>
						<span class="spinner"></span>
					</div>
				</form>
			</div>
		</div>

		<div class="aips-content-panel">
			<div class="aips-panel-header">
				<div class="aips-panel-header-content">
					<span class="dashicons dashicons-list-view dashicons-icon-lg"></span>
					<div>
						<h3 class="aips-panel-title"><?php esc_html_e('Current Editions', 'ai-post-scheduler'); ?></h3>
						<p class="aips-panel-description"><?php esc_html_e('Track package readiness across filled slots, review readiness, sourcing blockers, and publish readiness.', 'ai-post-scheduler'); ?></p>
					</div>
				</div>
			</div>
			<div class="aips-panel-body">
				<?php if (!empty($editions)): ?>
					<div class="aips-edition-cards" style="display:grid; gap:16px;">
						<?php foreach ($editions as $edition): ?>
							<div class="aips-content-panel" style="margin:0; border:1px solid #dcdcde; box-shadow:none;" data-edition='<?php echo esc_attr(wp_json_encode($edition)); ?>'>
								<div class="aips-panel-body">
									<div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:12px;">
										<div>
											<h3 style="margin:0 0 6px;"><?php echo esc_html($edition->name); ?></h3>
											<p style="margin:0; color:#646970;">
												<?php echo esc_html($edition->theme); ?> ·
												<?php echo esc_html(ucfirst($edition->cadence)); ?> ·
												<?php echo esc_html($edition->channel_type); ?>
											</p>
											<p style="margin:6px 0 0; color:#646970;">
												<?php echo esc_html(sprintf(__('Owner: %1$s · Target: %2$s', 'ai-post-scheduler'), $edition->owner, date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($edition->target_publish_date)))); ?>
											</p>
										</div>
										<div style="display:flex; gap:8px; align-items:flex-start;">
											<span class="aips-badge <?php echo !empty($edition->is_active) ? 'aips-badge-success' : 'aips-badge-neutral'; ?>"><?php echo !empty($edition->is_active) ? esc_html__('Active', 'ai-post-scheduler') : esc_html__('Paused', 'ai-post-scheduler'); ?></span>
											<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-edition-btn"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></button>
											<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-delete-edition-btn" data-edition-id="<?php echo esc_attr($edition->id); ?>"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
										</div>
									</div>
									<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
										<div class="aips-stat-box"><strong><?php echo esc_html($edition->completeness['slots_filled']); ?>/<?php echo esc_html($edition->required_slots); ?></strong><br><span><?php esc_html_e('Slots Filled', 'ai-post-scheduler'); ?></span></div>
										<div class="aips-stat-box"><strong><?php echo esc_html($edition->completeness['ready_for_review']); ?></strong><br><span><?php esc_html_e('Ready for Review', 'ai-post-scheduler'); ?></span></div>
										<div class="aips-stat-box"><strong><?php echo esc_html($edition->completeness['blocked_by_missing_sourcing']); ?></strong><br><span><?php esc_html_e('Blocked by Missing Sourcing', 'ai-post-scheduler'); ?></span></div>
										<div class="aips-stat-box"><strong><?php echo esc_html($edition->completeness['ready_to_publish']); ?></strong><br><span><?php esc_html_e('Ready to Publish', 'ai-post-scheduler'); ?></span></div>
									</div>
									<table class="aips-table">
										<thead>
											<tr>
												<th><?php esc_html_e('Slot', 'ai-post-scheduler'); ?></th>
												<th><?php esc_html_e('Assigned Story', 'ai-post-scheduler'); ?></th>
												<th><?php esc_html_e('Sourcing', 'ai-post-scheduler'); ?></th>
												<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($edition->slots as $slot): ?>
											<tr>
												<td><?php echo esc_html($slot->slot_label); ?></td>
												<td><?php echo !empty($slot->assigned_topic) ? esc_html($slot->assigned_topic) : '<span class="aips-muted">' . esc_html__('Unassigned', 'ai-post-scheduler') . '</span>'; ?></td>
												<td>
													<span class="aips-badge <?php echo $slot->sourcing_status === 'missing' ? 'aips-badge-warning' : 'aips-badge-success'; ?>">
														<?php echo $slot->sourcing_status === 'missing' ? esc_html__('Missing', 'ai-post-scheduler') : esc_html__('Ready', 'ai-post-scheduler'); ?>
													</span>
												</td>
												<td>
													<?php if (!empty($slot->post_id) && in_array($slot->post_status, array('future', 'publish'), true)): ?>
														<span class="aips-badge aips-badge-success"><?php esc_html_e('Ready to publish', 'ai-post-scheduler'); ?></span>
													<?php elseif (!empty($slot->post_id) && in_array($slot->post_status, array('draft', 'pending'), true)): ?>
														<span class="aips-badge aips-badge-info"><?php esc_html_e('Ready for review', 'ai-post-scheduler'); ?></span>
													<?php elseif (!empty($slot->schedule_id)): ?>
														<span class="aips-badge aips-badge-neutral"><?php esc_html_e('Scheduled', 'ai-post-scheduler'); ?></span>
													<?php else: ?>
														<span class="aips-badge aips-badge-neutral"><?php esc_html_e('Open', 'ai-post-scheduler'); ?></span>
													<?php endif; ?>
												</td>
											</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else: ?>
					<div class="aips-empty-state">
						<div class="dashicons dashicons-portfolio aips-empty-state-icon"></div>
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Editions Yet', 'ai-post-scheduler'); ?></h3>
						<p class="aips-empty-state-description"><?php esc_html_e('Create your first edition package to coordinate lead stories, analysis pieces, roundups, sidebars, and newsletter components as a set.', 'ai-post-scheduler'); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
<script>
jQuery(function($) {
	var $form = $('#aips-edition-form');
	var $spinner = $form.find('.spinner');
	var nonce = (window.aipsAjax && window.aipsAjax.nonce) ? window.aipsAjax.nonce : '';

	function resetForm() {
		$form[0].reset();
		$('#aips-edition-id').val(0);
		$('#aips-edition-target').val('<?php echo esc_js(gmdate('Y-m-d\TH:i')); ?>');
		$('.aips-edition-slot-enabled').prop('checked', true).trigger('change');
	}

	$(document).on('change', '.aips-edition-slot-enabled', function() {
		var $row = $(this).closest('tr');
		var enabled = $(this).is(':checked');
		$row.find('input, select').not(this).prop('disabled', !enabled);
	});

	$(document).on('click', '#aips-reset-edition-btn', function() {
		resetForm();
	});

	$(document).on('click', '.aips-edit-edition-btn', function() {
		var raw = $(this).closest('[data-edition]').attr('data-edition');
		var data = raw ? JSON.parse(raw) : null;
		if (!data) {
			return;
		}

		resetForm();
		$('#aips-edition-id').val(data.id);
		$('#aips-edition-name').val(data.name);
		$('#aips-edition-theme').val(data.theme || '');
		$('#aips-edition-cadence').val(data.cadence);
		$('#aips-edition-target').val((data.target_publish_date || '').replace(' ', 'T').slice(0, 16));
		$('#aips-edition-required-slots').val(data.required_slots);
		$('#aips-edition-owner').val(data.owner);
		$('#aips-edition-channel').val(data.channel_type);
		$('#aips-edition-active').prop('checked', !!parseInt(data.is_active, 10));

		var slots = data.slots || [];
		$('#aips-edition-slot-table tbody tr').each(function(index) {
			var $row = $(this);
			var rowSlot = slots[index];
			if (!rowSlot) {
				$row.find('.aips-edition-slot-enabled').prop('checked', false).trigger('change');
				return;
			}

			$row.find('.aips-edition-slot-enabled').prop('checked', true).trigger('change');
			$row.find('input[name="slot_key[]"]').val(rowSlot.slot_key);
			$row.find('input[name="slot_label[]"]').val(rowSlot.slot_label);
			$row.find('input[name="slot_notes[]"]').val(rowSlot.notes || '');
			$row.find('select[name="slot_sourcing_status[]"]').val(rowSlot.sourcing_status || 'ready');
			$row.find('input[name="slot_assigned_topic[]"]').val(rowSlot.assigned_topic || '');
			$row.find('input[name="slot_template_id[]"]').val(rowSlot.template_id || 0);
			$row.find('input[name="slot_schedule_id[]"]').val(rowSlot.schedule_id || 0);
			$row.find('input[name="slot_post_id[]"]').val(rowSlot.post_id || 0);
		});

		window.scrollTo({ top: 0, behavior: 'smooth' });
	});

	$form.on('submit', function(event) {
		event.preventDefault();
		var formData = $form.serializeArray();
		formData.push({ name: 'action', value: 'aips_save_edition' });
		formData.push({ name: 'nonce', value: nonce });

		$spinner.addClass('is-active');
		$.post(ajaxurl, formData).done(function(response) {
			if (response && response.success) {
				window.location.reload();
				return;
			}
			window.alert(response && response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Failed to save edition.', 'ai-post-scheduler')); ?>');
		}).fail(function() {
			window.alert('<?php echo esc_js(__('Failed to save edition.', 'ai-post-scheduler')); ?>');
		}).always(function() {
			$spinner.removeClass('is-active');
		});
	});

	$(document).on('click', '.aips-delete-edition-btn', function() {
		if (!window.confirm('<?php echo esc_js(__('Delete this edition package and all slot assignments?', 'ai-post-scheduler')); ?>')) {
			return;
		}

		var editionId = $(this).data('edition-id');
		$.post(ajaxurl, {
			action: 'aips_delete_edition',
			nonce: nonce,
			edition_id: editionId
		}).done(function(response) {
			if (response && response.success) {
				window.location.reload();
				return;
			}
			window.alert(response && response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Failed to delete edition.', 'ai-post-scheduler')); ?>');
		}).fail(function() {
			window.alert('<?php echo esc_js(__('Failed to delete edition.', 'ai-post-scheduler')); ?>');
		});
	});

	resetForm();
});
</script>
