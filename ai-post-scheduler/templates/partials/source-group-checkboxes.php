<?php
/**
 * Source Group Checkbox List Partial.
 *
 * @var array<int, object> $source_groups
 * @var string             $checkbox_name
 * @var string             $checkbox_class
 * @var string             $label_class
 * @var string             $description_text
 */
if (!defined('ABSPATH')) {
	exit;
}

$source_groups = (isset($source_groups) && is_array($source_groups)) ? $source_groups : array();
$checkbox_name = isset($checkbox_name) ? (string) $checkbox_name : 'source_group_ids[]';
$checkbox_class = isset($checkbox_class) ? (string) $checkbox_class : '';
$label_class = isset($label_class) ? (string) $label_class : 'aips-checkbox-label aips-checkbox-label-block';
$description_text = isset($description_text) ? (string) $description_text : '';
?>
<?php if (!empty($source_groups)): ?>
	<div class="aips-checkbox-group">
		<?php foreach ($source_groups as $source_group): ?>
			<label class="<?php echo esc_attr($label_class); ?>">
				<input type="checkbox"
					name="<?php echo esc_attr($checkbox_name); ?>"
					<?php if (!empty($checkbox_class)) : ?>class="<?php echo esc_attr($checkbox_class); ?>"<?php endif; ?>
					value="<?php echo esc_attr($source_group->term_id); ?>">
				<?php echo esc_html($source_group->name); ?>
			</label>
		<?php endforeach; ?>
	</div>
	<?php if (!empty($description_text)): ?>
		<p class="description"><?php echo esc_html($description_text); ?></p>
	<?php endif; ?>
<?php else: ?>
	<p class="description">
		<?php esc_html_e('No Source Groups found. Create groups on the', 'ai-post-scheduler'); ?>
		<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('aips-sources')); ?>" target="_blank"><?php esc_html_e('Trusted Sources page', 'ai-post-scheduler'); ?></a>.
	</p>
<?php endif; ?>
