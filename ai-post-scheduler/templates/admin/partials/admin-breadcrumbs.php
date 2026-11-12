<?php
/**
 * Admin Breadcrumbs Partial
 *
 * Renders an accessible, semantic breadcrumb navigation trail for page headers.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 *
 * @var array<int, array{label:string, url?:string, icon?:string}> $args
 */

if (!defined('ABSPATH')) {
	exit;
}

$crumbs = is_array($args) ? $args : array();
if (empty($crumbs)) {
	return;
}

$total = count($crumbs);
?>
<nav class="aips-title-breadcrumb-trail" aria-label="<?php esc_attr_e('Page Location', 'ai-post-scheduler'); ?>">
	<?php foreach ($crumbs as $index => $crumb) : ?>
		<?php
		$is_last = ($index === $total - 1);
		$label   = isset($crumb['label']) ? $crumb['label'] : '';
		$url     = isset($crumb['url']) ? $crumb['url'] : '';
		?>
		<?php if (!empty($url) && !$is_last) : ?>
			<a href="<?php echo esc_url($url); ?>" class="aips-title-breadcrumb-link"><?php echo esc_html($label); ?></a>
		<?php else : ?>
			<span class="aips-title-breadcrumb-current"><?php echo esc_html($label); ?></span>
		<?php endif; ?>
		<?php if (!$is_last) : ?>
			<span class="aips-page-context-separator" aria-hidden="true">/</span>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>
