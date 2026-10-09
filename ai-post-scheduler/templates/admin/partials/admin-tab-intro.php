<?php
/**
 * Admin Tab Intro Section Partial
 *
 * Renders a consistent icon + title + help-text introduction for a single
 * tab/section on a rail-layout admin page, visually separated (raised) from
 * the content panel rendered below it.
 *
 * @package AI_Post_Scheduler
 * @since   3.8.0
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
	exit;
}

$icon        = isset($args['icon']) ? $args['icon'] : '';
$title       = isset($args['title']) ? $args['title'] : '';
$description = isset($args['description']) ? $args['description'] : '';
$actions     = isset($args['actions']) && is_array($args['actions']) ? $args['actions'] : array();

if (empty($title) && empty($description)) {
	return;
}
?>
<div class="aips-tab-intro">
	<div class="aips-tab-intro-content">
		<?php if (!empty($icon)) : ?>
			<span class="dashicons <?php echo esc_attr($icon); ?> aips-tab-intro-icon" aria-hidden="true"></span>
		<?php endif; ?>
		<div class="aips-tab-intro-text">
			<?php if (!empty($title)) : ?>
				<h2 class="aips-tab-intro-title"><?php echo esc_html($title); ?></h2>
			<?php endif; ?>
			<?php if (!empty($description)) : ?>
				<p class="aips-tab-intro-description"><?php echo esc_html($description); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<?php if (!empty($actions)) : ?>
		<div class="aips-tab-intro-actions">
			<?php foreach ($actions as $action) : ?>
				<?php
				$action_type  = isset($action['type']) ? $action['type'] : 'button';
				$action_class = isset($action['class']) ? $action['class'] : 'aips-btn aips-btn-secondary aips-btn-sm';
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
							<span class="dashicons <?php echo esc_attr($action_icon); ?>" aria-hidden="true"></span>
						<?php endif; ?>
						<?php echo esc_html($action_label); ?>
					</a>
				<?php else : ?>
					<button type="button" class="<?php echo esc_attr($action_class); ?>"<?php echo $action_id ? ' id="' . esc_attr($action_id) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $data_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php if ($action_icon) : ?>
							<span class="dashicons <?php echo esc_attr($action_icon); ?>" aria-hidden="true"></span>
						<?php endif; ?>
						<?php echo esc_html($action_label); ?>
					</button>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
