<?php
/**
 * Reusable post row actions partial.
 *
 * @var array $aips_action_config
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$defaults = array(
	'container_classes' => 'cell-actions',
	'primary_action' => array(),
	'overflow_actions' => array(),
	'row_identifiers' => array(),
	'state_flags' => array(),
);

$config = wp_parse_args(is_array($aips_action_config) ? $aips_action_config : array(), $defaults);
$row_identifiers = is_array($config['row_identifiers']) ? $config['row_identifiers'] : array();
$state_flags = is_array($config['state_flags']) ? $config['state_flags'] : array();

$render_action = static function($action, $row_identifiers, $is_primary) {
	if (empty($action) || !is_array($action) || empty($action['label'])) {
		return;
	}

	$button_type = !empty($action['button_type']) ? $action['button_type'] : 'button';
	$classes = !empty($action['classes']) ? $action['classes'] : 'aips-btn aips-btn-sm aips-btn-secondary';
	$icon = !empty($action['icon']) ? $action['icon'] : '';
	$title = !empty($action['title']) ? $action['title'] : '';
	$data = !empty($action['data']) && is_array($action['data']) ? $action['data'] : array();
	$extra_attributes = !empty($action['attributes']) && is_array($action['attributes']) ? $action['attributes'] : array();

	echo '<button type="' . esc_attr($button_type) . '" class="' . esc_attr($classes) . '"';

	foreach ($data as $key => $value) {
		$resolved_value = (is_string($value) && isset($row_identifiers[$value])) ? $row_identifiers[$value] : $value;
		if ('' === $resolved_value || null === $resolved_value) {
			continue;
		}
		echo ' data-' . esc_attr($key) . '="' . esc_attr($resolved_value) . '"';
	}

	foreach ($extra_attributes as $key => $value) {
		if ('' === $value || null === $value) {
			continue;
		}
		echo ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
	}

	if (!empty($title)) {
		echo ' title="' . esc_attr($title) . '"';
	}

	echo ' data-action-tier="' . esc_attr($is_primary ? 'primary' : 'overflow') . '">';

	if (!empty($icon)) {
		echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
	}

	echo esc_html($action['label']);
	echo '</button>';
};
?>
<div class="<?php echo esc_attr($config['container_classes']); ?>"<?php echo !empty($state_flags['pending_review']) ? ' data-pending-review="1"' : ''; ?>>
	<?php $render_action($config['primary_action'], $row_identifiers, true); ?>
	<?php foreach ($config['overflow_actions'] as $overflow_action): ?>
		<?php $render_action($overflow_action, $row_identifiers, false); ?>
	<?php endforeach; ?>
</div>
