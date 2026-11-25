<?php
/**
 * Compact in-page hub section navigation for viewports where flyouts are disabled.
 *
 * @package AI_Post_Scheduler
 *
 * @var array<string, mixed> $hub Current hub payload from AIPS_Admin_Flyout_Navigation.
 */

if (!defined('ABSPATH')) {
	exit;
}

$label = isset($hub['label']) ? (string) $hub['label'] : '';
$groups = isset($hub['groups']) && is_array($hub['groups']) ? $hub['groups'] : array();
?>
<nav class="aips-admin-hub-nav" aria-label="<?php echo esc_attr(sprintf(
	/* translators: %s: hub name, e.g. Studio */
	__('%s sections', 'ai-post-scheduler'),
	$label
)); ?>">
	<?php foreach ($groups as $group) : ?>
		<?php
		$group_label = isset($group['label']) ? (string) $group['label'] : '';
		$items       = isset($group['items']) && is_array($group['items']) ? $group['items'] : array();
		if (empty($items)) {
			continue;
		}
		?>
		<section class="aips-admin-hub-nav-group">
			<?php if ('' !== $group_label) : ?>
				<h2 class="aips-admin-hub-nav-heading"><?php echo esc_html($group_label); ?></h2>
			<?php endif; ?>
			<ul class="aips-admin-hub-nav-items">
				<?php foreach ($items as $item) : ?>
					<?php
					$item_url    = isset($item['url']) ? (string) $item['url'] : '';
					$item_label  = isset($item['label']) ? (string) $item['label'] : '';
					$item_icon   = isset($item['icon']) ? (string) $item['icon'] : 'dashicons-admin-generic';
					$item_active = !empty($item['active']);
					$item_badge  = isset($item['badge']) ? (int) $item['badge'] : 0;
					if ('' === $item_url || '' === $item_label) {
						continue;
					}
					?>
					<li>
						<a class="aips-admin-hub-nav-item<?php echo $item_active ? ' is-active' : ''; ?>" href="<?php echo esc_url($item_url); ?>"<?php echo $item_active ? ' aria-current="page"' : ''; ?>>
							<span class="dashicons <?php echo esc_attr($item_icon); ?>" aria-hidden="true"></span>
							<span class="aips-admin-hub-nav-label"><?php echo esc_html($item_label); ?></span>
							<?php if ($item_badge > 0) : ?>
								<span class="aips-admin-flyout-badge" aria-label="<?php echo esc_attr(sprintf(
									/* translators: %d: pending review count */
									_n('%d pending review', '%d pending reviews', $item_badge, 'ai-post-scheduler'),
									$item_badge
								)); ?>"><?php echo esc_html((string) $item_badge); ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>
</nav>
