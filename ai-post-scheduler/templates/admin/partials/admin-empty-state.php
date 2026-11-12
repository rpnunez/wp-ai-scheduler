<?php
/**
 * Admin Empty State Partial
 *
 * Renders consistent empty state indicators with icon, title, description, and primary/secondary CTAs.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
	exit;
}

$icon          = isset($args['icon']) ? $args['icon'] : 'dashicons-info';
$title         = isset($args['title']) ? $args['title'] : __('No items found', 'ai-post-scheduler');
$message       = isset($args['message']) ? $args['message'] : '';
$cta_label     = isset($args['cta_label']) ? $args['cta_label'] : '';
$cta_url       = isset($args['cta_url']) ? $args['cta_url'] : '';
$cta_id        = isset($args['cta_id']) ? $args['cta_id'] : '';
$cta_icon      = isset($args['cta_icon']) ? $args['cta_icon'] : '';
$cta_class     = isset($args['cta_class']) ? $args['cta_class'] : 'aips-btn aips-btn-primary';
$secondary_cta = isset($args['secondary_cta']) && is_array($args['secondary_cta']) ? $args['secondary_cta'] : array();
$empty_id      = isset($args['id']) ? $args['id'] : '';
?>
<div class="aips-empty-state"<?php echo $empty_id ? ' id="' . esc_attr($empty_id) . '"' : ''; ?>>
	<div class="aips-empty-state-icon">
		<span class="dashicons <?php echo esc_attr($icon); ?> aips-empty-state-icon-dashicon" aria-hidden="true"></span>
	</div>
	<h3 class="aips-empty-state-title"><?php echo esc_html($title); ?></h3>
	<?php if (!empty($message)) : ?>
		<p class="aips-empty-state-text"><?php echo esc_html($message); ?></p>
	<?php endif; ?>

	<?php if (!empty($cta_label) || !empty($secondary_cta)) : ?>
		<div class="aips-empty-state-actions">
			<?php if (!empty($cta_label)) : ?>
				<?php if (!empty($cta_url)) : ?>
					<a href="<?php echo esc_url($cta_url); ?>" class="<?php echo esc_attr($cta_class); ?>"<?php echo $cta_id ? ' id="' . esc_attr($cta_id) . '"' : ''; ?>>
						<?php if (!empty($cta_icon)) : ?>
							<span class="dashicons <?php echo esc_attr($cta_icon); ?>" aria-hidden="true"></span>
						<?php endif; ?>
						<?php echo esc_html($cta_label); ?>
					</a>
				<?php else : ?>
					<button type="button" class="<?php echo esc_attr($cta_class); ?>"<?php echo $cta_id ? ' id="' . esc_attr($cta_id) . '"' : ''; ?>>
						<?php if (!empty($cta_icon)) : ?>
							<span class="dashicons <?php echo esc_attr($cta_icon); ?>" aria-hidden="true"></span>
						<?php endif; ?>
						<?php echo esc_html($cta_label); ?>
					</button>
				<?php endif; ?>
			<?php endif; ?>

			<?php if (!empty($secondary_cta['label'])) : ?>
				<?php
				$sec_url   = isset($secondary_cta['url']) ? $secondary_cta['url'] : '';
				$sec_id    = isset($secondary_cta['id']) ? $secondary_cta['id'] : '';
				$sec_icon  = isset($secondary_cta['icon']) ? $secondary_cta['icon'] : '';
				$sec_class = isset($secondary_cta['class']) ? $secondary_cta['class'] : 'aips-btn aips-btn-secondary';
				?>
				<?php if (!empty($sec_url)) : ?>
					<a href="<?php echo esc_url($sec_url); ?>" class="<?php echo esc_attr($sec_class); ?>"<?php echo $sec_id ? ' id="' . esc_attr($sec_id) . '"' : ''; ?>>
						<?php if (!empty($sec_icon)) : ?>
							<span class="dashicons <?php echo esc_attr($sec_icon); ?>" aria-hidden="true"></span>
						<?php endif; ?>
						<?php echo esc_html($secondary_cta['label']); ?>
					</a>
				<?php else : ?>
					<button type="button" class="<?php echo esc_attr($sec_class); ?>"<?php echo $sec_id ? ' id="' . esc_attr($sec_id) . '"' : ''; ?>>
						<?php if (!empty($sec_icon)) : ?>
							<span class="dashicons <?php echo esc_attr($sec_icon); ?>" aria-hidden="true"></span>
						<?php endif; ?>
						<?php echo esc_html($secondary_cta['label']); ?>
					</button>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
