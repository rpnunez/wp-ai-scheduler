<?php
/**
 * Admin Page Header Partial
 *
 * Renders consistent page headers with Dashicon, title, contextual trail, breadcrumbs, summary chips, badges, and action toolbar.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
	exit;
}

$title         = isset($args['title']) ? $args['title'] : '';
$context_title = isset($args['context_title']) ? $args['context_title'] : '';
$icon          = isset($args['icon']) ? $args['icon'] : '';
$icon_color    = isset($args['icon_color']) ? $args['icon_color'] : '#2271b1';
$description   = isset($args['description']) ? $args['description'] : '';
$badges        = isset($args['badges']) && is_array($args['badges']) ? $args['badges'] : array();
$breadcrumbs   = isset($args['breadcrumbs']) && is_array($args['breadcrumbs']) ? $args['breadcrumbs'] : array();
$summary_items = isset($args['summary_items']) && is_array($args['summary_items']) ? $args['summary_items'] : array();
$actions       = isset($args['actions']) && is_array($args['actions']) ? $args['actions'] : array();
?>
<div class="aips-page-header">
	<div class="aips-page-header-top">
		<div class="aips-page-header-info">
			<h1 class="aips-page-title">
				<?php if (!empty($icon)) : ?>
					<span class="dashicons <?php echo esc_attr($icon); ?> aips-page-title-icon" aria-hidden="true"<?php echo (!empty($args['icon_color']) && $args['icon_color'] !== '#2271b1') ? ' style="color:' . esc_attr($args['icon_color']) . ';"' : ''; ?>></span>
				<?php endif; ?>

				<?php if (!empty($breadcrumbs) && is_array($breadcrumbs)) : ?>
					<?php AIPS_Admin_UI_Primitives::render_breadcrumbs($breadcrumbs); ?>
				<?php else : ?>
					<span><?php echo esc_html($title); ?></span>
					<?php if (!empty($context_title) && $context_title !== $title) : ?>
						<span class="aips-page-context-separator" aria-hidden="true">/</span>
						<span class="aips-page-context-label"><?php echo esc_html($context_title); ?></span>
					<?php endif; ?>
				<?php endif; ?>

				<?php foreach ($badges as $badge) : ?>
					<?php echo AIPS_Admin_UI_Primitives::render_status_badge($badge); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</h1>
			<?php if (!empty($description)) : ?>
				<p class="aips-page-description"><?php echo esc_html($description); ?></p>
			<?php endif; ?>

			<?php if (!empty($summary_items)) : ?>
				<div class="aips-page-summary-strip" role="region" aria-label="<?php esc_attr_e('Section Metrics Summary', 'ai-post-scheduler'); ?>">
					<?php foreach ($summary_items as $chip) : ?>
						<?php
						$chip_label = isset($chip['label']) ? $chip['label'] : '';
						$chip_value = isset($chip['value']) ? $chip['value'] : '';
						$chip_type  = isset($chip['type']) ? $chip['type'] : 'neutral';
						$chip_icon  = isset($chip['icon']) ? $chip['icon'] : '';
						?>
						<span class="aips-summary-chip aips-summary-chip-<?php echo esc_attr(sanitize_key($chip_type)); ?>">
							<?php if (!empty($chip_icon)) : ?>
								<span class="dashicons <?php echo esc_attr($chip_icon); ?>" aria-hidden="true"></span>
							<?php endif; ?>
							<?php if ('' !== (string) $chip_value) : ?>
								<strong class="aips-summary-chip-value"><?php echo esc_html($chip_value); ?></strong>
							<?php endif; ?>
							<span class="aips-summary-chip-label"><?php echo esc_html($chip_label); ?></span>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if (!empty($actions)) : ?>
			<div class="aips-page-actions">
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
</div>
