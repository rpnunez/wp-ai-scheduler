<?php
/**
 * Admin Card / Content Panel Partial
 *
 * Renders consistent content cards with headers, actions, badges, body, and footers.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 *
 * @var array<string, mixed> $args
 * @var callable|null        $body_callback
 */

if (!defined('ABSPATH')) {
	exit;
}

$card_id       = isset($args['id']) ? $args['id'] : '';
$card_class    = isset($args['class']) ? ' ' . $args['class'] : '';
$card_title    = isset($args['title']) ? $args['title'] : '';
$card_icon     = isset($args['icon']) ? $args['icon'] : '';
$card_desc     = isset($args['description']) ? $args['description'] : '';
$card_badge    = isset($args['badge']) && is_array($args['badge']) ? $args['badge'] : array();
$card_actions  = isset($args['actions']) && is_array($args['actions']) ? $args['actions'] : array();
$card_body     = isset($args['body']) ? $args['body'] : '';
$card_body_cl  = isset($args['body_class']) ? ' ' . $args['body_class'] : '';
$card_footer   = isset($args['footer']) ? $args['footer'] : '';
$callback      = isset($args['body_callback']) && is_callable($args['body_callback'])
	? $args['body_callback']
	: $body_callback;

$has_header = !empty($card_title) || !empty($card_actions) || !empty($card_badge);
?>
<div class="aips-content-panel<?php echo esc_attr($card_class); ?>"<?php echo $card_id ? ' id="' . esc_attr($card_id) . '"' : ''; ?>>
	<?php if ($has_header) : ?>
		<div class="aips-panel-header">
			<div class="aips-panel-header-content">
				<?php if (!empty($card_icon)) : ?>
					<span class="dashicons <?php echo esc_attr($card_icon); ?> dashicons-icon-lg" aria-hidden="true"></span>
				<?php endif; ?>
				<div>
					<?php if (!empty($card_title)) : ?>
						<h3 class="aips-panel-title">
							<?php echo esc_html($card_title); ?>
							<?php if (!empty($card_badge)) : ?>
								<?php echo AIPS_Admin_UI_Primitives::render_status_badge($card_badge); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</h3>
					<?php endif; ?>
					<?php if (!empty($card_desc)) : ?>
						<p class="aips-panel-description"><?php echo esc_html($card_desc); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if (!empty($card_actions)) : ?>
				<div class="aips-panel-header-actions">
					<?php foreach ($card_actions as $action) : ?>
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
						$aria_label   = isset($action['aria_label']) ? ' aria-label="' . esc_attr($action['aria_label']) . '"' : '';
						?>
						<?php if ('link' === $action_type && !empty($action['url'])) : ?>
							<a href="<?php echo esc_url($action['url']); ?>" class="<?php echo esc_attr($action_class); ?>"<?php echo $action_id ? ' id="' . esc_attr($action_id) . '"' : ''; ?><?php echo $data_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $aria_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<?php if ($action_icon) : ?>
									<span class="dashicons <?php echo esc_attr($action_icon); ?>" aria-hidden="true"></span>
								<?php endif; ?>
								<?php echo esc_html($action_label); ?>
							</a>
						<?php else : ?>
							<button type="button" class="<?php echo esc_attr($action_class); ?>"<?php echo $action_id ? ' id="' . esc_attr($action_id) . '"' : ''; ?><?php echo $data_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $aria_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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
	<?php endif; ?>

	<div class="aips-panel-body<?php echo esc_attr($card_body_cl); ?>">
		<?php
		if (is_callable($callback)) {
			call_user_func($callback);
		} else {
			echo wp_kses_post($card_body);
		}
		?>
	</div>

	<?php if (!empty($card_footer)) : ?>
		<div class="aips-panel-footer">
			<?php echo wp_kses_post($card_footer); ?>
		</div>
	<?php endif; ?>
</div>
