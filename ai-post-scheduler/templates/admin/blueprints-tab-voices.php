<?php
/**
 * Blueprints tab: Voices
 *
 * Renders the Voices list within the Blueprints tabbed page.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!isset($voices) || !is_array($voices)) {
	$voices = array();
}
?>

<div class="aips-content-panel">
	<div class="aips-voices-container">
		<?php if (!empty($voices)): ?>
		<div class="aips-filter-bar">
			<div class="aips-filter-right">
				<label class="screen-reader-text" for="aips-voice-search"><?php esc_html_e('Search Voices:', 'ai-post-scheduler'); ?></label>
				<input type="search" id="aips-voice-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search voices...', 'ai-post-scheduler'); ?>">
				<button type="button" id="aips-voice-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
			</div>
		</div>

		<div class="aips-panel-body no-padding">
			<table class="aips-table aips-voices-list">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
						<th class="column-title-prompt"><?php esc_html_e('Title Prompt', 'ai-post-scheduler'); ?></th>
						<th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
						<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($voices as $voice): ?>
					<tr data-voice-id="<?php echo esc_attr($voice->id); ?>">
						<td class="column-name">
							<strong><?php echo esc_html($voice->name); ?></strong>
						</td>
						<td class="column-title-prompt">
							<div class="aips-table-meta">
								<?php echo esc_html(substr($voice->title_prompt, 0, 80)) . (strlen($voice->title_prompt) > 80 ? '...' : ''); ?>
							</div>
						</td>
						<td class="column-status">
							<?php if ($voice->is_active): ?>
								<span class="aips-badge aips-badge-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Active', 'ai-post-scheduler'); ?></span>
							<?php else: ?>
								<span class="aips-badge aips-badge-neutral"><span class="dashicons dashicons-minus"></span> <?php esc_html_e('Inactive', 'ai-post-scheduler'); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-actions">
							<div class="aips-action-buttons">
								<button class="aips-btn aips-btn-sm aips-edit-voice" data-id="<?php echo esc_attr($voice->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-edit"></span>
								</button>
								<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-voice" data-id="<?php echo esc_attr($voice->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="tablenav">
			<span class="aips-table-footer-count">
				<?php printf(esc_html(_n('%d voice', '%d voices', count($voices), 'ai-post-scheduler')), count($voices)); ?>
			</span>
		</div>
		<?php else: ?>
		<div class="aips-empty-state">
			<div class="dashicons dashicons-format-quote aips-empty-state-icon" aria-hidden="true"></div>
			<h3 class="aips-empty-state-title"><?php esc_html_e('No Voices Yet', 'ai-post-scheduler'); ?></h3>
			<p class="aips-empty-state-description"><?php esc_html_e('Create a voice to establish consistent tone and style for your generated posts.', 'ai-post-scheduler'); ?></p>
			<div class="aips-empty-state-actions">
				<button class="aips-btn aips-btn-primary aips-btn-lg aips-add-voice-btn"><?php esc_html_e('Create Voice', 'ai-post-scheduler'); ?></button>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<!-- Voice Modal -->
<div id="aips-voice-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-voice-modal-title"><?php esc_html_e('Add New Voice', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-voice-form">
				<input type="hidden" name="voice_id" id="voice_id" value="">
				<div class="aips-form-row">
					<label for="voice_name"><?php esc_html_e('Voice Name', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
					<input type="text" id="voice_name" name="name" required class="regular-text" placeholder="e.g., Professional, Casual, Humorous">
				</div>
				<div class="aips-form-row">
					<label for="voice_title_prompt"><?php esc_html_e('Title Prompt', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
					<textarea id="voice_title_prompt" name="title_prompt" rows="3" required class="large-text"></textarea>
				</div>
				<div class="aips-form-row">
					<label for="voice_content_instructions"><?php esc_html_e('Content Instructions', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
					<textarea id="voice_content_instructions" name="content_instructions" rows="4" required class="large-text"></textarea>
				</div>
				<div class="aips-form-row">
					<label for="voice_excerpt_instructions"><?php esc_html_e('Excerpt Instructions (Optional)', 'ai-post-scheduler'); ?></label>
					<textarea id="voice_excerpt_instructions" name="excerpt_instructions" rows="3" class="large-text"></textarea>
				</div>
				<div class="aips-form-row">
					<label class="aips-checkbox-label">
						<input type="checkbox" id="voice_is_active" name="is_active" value="1" checked>
						<?php esc_html_e('Voice is active', 'ai-post-scheduler'); ?>
					</label>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="button button-primary aips-save-voice"><?php esc_html_e('Save Voice', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>
