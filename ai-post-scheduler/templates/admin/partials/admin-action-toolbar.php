<?php
/**
 * Admin Action Toolbar Partial
 *
 * Renders consistent action toolbars with primary/secondary buttons, search boxes, and filter dropdowns.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
	exit;
}

$toolbar_class = isset($args['class']) ? ' ' . $args['class'] : '';
$actions       = isset($args['actions']) && is_array($args['actions']) ? $args['actions'] : array();
$search        = isset($args['search']) && is_array($args['search']) ? $args['search'] : array();
$filters       = isset($args['filters']) && is_array($args['filters']) ? $args['filters'] : array();
?>
<div class="aips-action-toolbar<?php echo esc_attr($toolbar_class); ?>">
	<?php if (!empty($search)) : ?>
		<?php
		$search_id          = isset($search['id']) ? $search['id'] : 'aips-search-input';
		$search_placeholder = isset($search['placeholder']) ? $search['placeholder'] : __('Search...', 'ai-post-scheduler');
		$search_value       = isset($search['value']) ? $search['value'] : '';
		$search_name        = isset($search['name']) ? $search['name'] : 's';
		?>
		<div class="aips-toolbar-search">
			<label for="<?php echo esc_attr($search_id); ?>" class="screen-reader-text"><?php echo esc_html($search_placeholder); ?></label>
			<span class="dashicons dashicons-search aips-toolbar-search-icon"></span>
			<input type="search" id="<?php echo esc_attr($search_id); ?>" name="<?php echo esc_attr($search_name); ?>" value="<?php echo esc_attr($search_value); ?>" class="aips-form-input aips-toolbar-search-input" placeholder="<?php echo esc_attr($search_placeholder); ?>">
		</div>
	<?php endif; ?>

	<?php if (!empty($filters)) : ?>
		<div class="aips-toolbar-filters">
			<?php foreach ($filters as $filter) : ?>
				<?php
				$filter_id      = isset($filter['id']) ? $filter['id'] : '';
				$filter_name    = isset($filter['name']) ? $filter['name'] : '';
				$filter_options = isset($filter['options']) && is_array($filter['options']) ? $filter['options'] : array();
				$filter_selected = isset($filter['selected']) ? (string) $filter['selected'] : '';
				$filter_class   = isset($filter['class']) ? $filter['class'] : 'aips-form-select';
				?>
				<select<?php echo $filter_id ? ' id="' . esc_attr($filter_id) . '"' : ''; ?><?php echo $filter_name ? ' name="' . esc_attr($filter_name) . '"' : ''; ?> class="<?php echo esc_attr($filter_class); ?>">
					<?php foreach ($filter_options as $opt_val => $opt_label) : ?>
						<option value="<?php echo esc_attr($opt_val); ?>" <?php selected($filter_selected, (string) $opt_val); ?>>
							<?php echo esc_html($opt_label); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if (!empty($actions)) : ?>
		<div class="aips-toolbar-actions">
			<?php foreach ($actions as $action) : ?>
				<?php
				$action_type  = isset($action['type']) ? $action['type'] : 'button';
				$action_class = isset($action['class']) ? $action['class'] : 'aips-btn aips-btn-secondary';
				$action_icon  = isset($action['icon']) ? $action['icon'] : '';
				$action_id    = isset($action['id']) ? $action['id'] : '';
				$action_label = isset($action['label']) ? $action['label'] : '';
				$data_attrs   = isset($action['data_attrs']) && is_array($action['data_attrs']) ? $action['data_attrs'] : array();
				$data_attr_str = '';
				foreach ($data_attrs as $dk => $dv) {
					$data_attr_str .= ' data-' . esc_attr(sanitize_key($dk)) . '="' . esc_attr($dv) . '"';
				}
				?>
				<?php if ('link' === $action_type && !empty($action['url'])) : ?>
					<a href="<?php echo esc_url($action['url']); ?>" class="<?php echo esc_attr($action_class); ?>"<?php echo $action_id ? ' id="' . esc_attr($action_id) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $data_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php if ($action_icon) : ?>
							<span class="dashicons <?php echo esc_attr($action_icon); ?>"></span>
						<?php endif; ?>
						<?php echo esc_html($action_label); ?>
					</a>
				<?php else : ?>
					<button type="button" class="<?php echo esc_attr($action_class); ?>"<?php echo $action_id ? ' id="' . esc_attr($action_id) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $data_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php if ($action_icon) : ?>
							<span class="dashicons <?php echo esc_attr($action_icon); ?>"></span>
						<?php endif; ?>
						<?php echo esc_html($action_label); ?>
					</button>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
