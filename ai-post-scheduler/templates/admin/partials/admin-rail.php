<?php
/**
 * Admin Vertical Rail Navigation Partial
 *
 * Renders consistent vertical rail navigation sidebars with icons, titles, descriptions, and counter badges.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
	exit;
}

$aria_label = isset($args['aria_label']) ? $args['aria_label'] : __('Section Navigation', 'ai-post-scheduler');
$items      = isset($args['items']) && is_array($args['items']) ? $args['items'] : array();
$nav_id     = isset($args['id']) ? $args['id'] : '';
?>
<nav class="aips-rail-sidebar" aria-label="<?php echo esc_attr($aria_label); ?>"<?php echo $nav_id ? ' id="' . esc_attr($nav_id) . '"' : ''; ?>>
	<ul class="aips-rail-nav">
		<?php foreach ($items as $item) : ?>
			<?php
			$item_key      = isset($item['key']) ? $item['key'] : '';
			$item_label    = isset($item['label']) ? $item['label'] : '';
			$item_icon     = isset($item['icon']) ? $item['icon'] : 'dashicons-admin-generic';
			$item_desc     = isset($item['description']) ? $item['description'] : '';
			$item_url      = isset($item['url']) ? $item['url'] : '';
			$item_badge    = isset($item['badge']) ? $item['badge'] : '';
			$item_badge_cl = isset($item['badge_class']) ? $item['badge_class'] : 'aips-badge-secondary';
			$is_active     = !empty($item['active']);
			$is_special    = !empty($item['special']);

			$class_names = 'aips-rail-item';
			if ($is_active) {
				$class_names .= ' active';
			}
			if ($is_special) {
				$class_names .= ' aips-rail-item-special';
			}
			?>
			<li>
				<?php if (!empty($item_url)) : ?>
					<a href="<?php echo esc_url($item_url); ?>" class="<?php echo esc_attr($class_names); ?>" data-tab="<?php echo esc_attr($item_key); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr($item_icon); ?> aips-rail-icon" aria-hidden="true"></span>
						<span class="aips-rail-text">
							<span class="aips-rail-title"><?php echo esc_html($item_label); ?></span>
							<?php if (!empty($item_desc)) : ?>
								<span class="aips-rail-desc"><?php echo esc_html($item_desc); ?></span>
							<?php endif; ?>
						</span>
						<?php if ('' !== $item_badge) : ?>
							<span class="aips-badge <?php echo esc_attr($item_badge_cl); ?>"><?php echo esc_html($item_badge); ?></span>
						<?php endif; ?>
						<span class="dashicons dashicons-arrow-right-alt2 aips-rail-arrow" aria-hidden="true"></span>
					</a>
				<?php else : ?>
					<button type="button" class="<?php echo esc_attr($class_names); ?>" data-tab="<?php echo esc_attr($item_key); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr($item_icon); ?> aips-rail-icon" aria-hidden="true"></span>
						<span class="aips-rail-text">
							<span class="aips-rail-title"><?php echo esc_html($item_label); ?></span>
							<?php if (!empty($item_desc)) : ?>
								<span class="aips-rail-desc"><?php echo esc_html($item_desc); ?></span>
							<?php endif; ?>
						</span>
						<?php if ('' !== $item_badge) : ?>
							<span class="aips-badge <?php echo esc_attr($item_badge_cl); ?>"><?php echo esc_html($item_badge); ?></span>
						<?php endif; ?>
						<span class="dashicons dashicons-arrow-right-alt2 aips-rail-arrow" aria-hidden="true"></span>
					</button>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
