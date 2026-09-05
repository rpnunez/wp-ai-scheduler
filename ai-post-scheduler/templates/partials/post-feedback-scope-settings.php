<?php
if (!defined('ABSPATH')) {
	exit;
}
$feedback_scope_prefix = isset($feedback_scope_prefix) ? sanitize_key($feedback_scope_prefix) : 'scope';
?>
<fieldset class="aips-form-row aips-feedback-scope-settings">
	<legend><strong><?php esc_html_e('Generated Post Feedback', 'ai-post-scheduler'); ?></strong></legend>
	<select id="<?php echo esc_attr($feedback_scope_prefix); ?>_feedback_enabled" name="feedback_enabled" class="aips-feedback-enabled">
		<option value="inherit"><?php esc_html_e('Inherit parent setting', 'ai-post-scheduler'); ?></option>
		<option value="enabled"><?php esc_html_e('Enabled', 'ai-post-scheduler'); ?></option>
		<option value="disabled"><?php esc_html_e('Disabled', 'ai-post-scheduler'); ?></option>
	</select>
	<button type="button" class="button-link aips-feedback-overrides-toggle" aria-expanded="false"><?php esc_html_e('Override weights', 'ai-post-scheduler'); ?></button>
	<div class="aips-feedback-overrides" hidden>
		<?php foreach (AIPS_Post_Feedback_Settings::fields() as $feedback_key => $feedback_bounds): ?>
		<label class="aips-feedback-override-field">
			<span><?php echo esc_html(ucwords(str_replace('_', ' ', $feedback_key))); ?></span>
			<input type="number" name="feedback_config[<?php echo esc_attr($feedback_key); ?>]" data-feedback-key="<?php echo esc_attr($feedback_key); ?>" min="<?php echo esc_attr($feedback_bounds[0]); ?>" max="<?php echo esc_attr($feedback_bounds[1]); ?>" step="<?php echo esc_attr(in_array($feedback_key, array('max_examples', 'min_samples', 'prompt_budget_chars'), true) ? 1 : 0.05); ?>" placeholder="<?php esc_attr_e('Inherit', 'ai-post-scheduler'); ?>">
		</label>
		<?php endforeach; ?>
	</div>
	<p class="description"><?php esc_html_e('Unspecified values inherit. The global master switch must be enabled.', 'ai-post-scheduler'); ?></p>
</fieldset>
