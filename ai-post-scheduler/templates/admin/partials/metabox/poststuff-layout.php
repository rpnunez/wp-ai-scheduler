<?php
/**
 * Poststuff 2-Column Layout Partial
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div id="poststuff" class="aips-poststuff">
	<div id="post-body" class="metabox-holder <?php echo esc_attr($columns_class); ?>">
		<?php if (2 === (int) $columns) : ?>
			<div id="postbox-container-1" class="postbox-container aips-postbox-side">
				<?php do_meta_boxes($screen_id, 'side', $data); ?>
			</div>
		<?php endif; ?>

		<div id="postbox-container-2" class="postbox-container aips-postbox-main">
			<?php
			do_meta_boxes($screen_id, 'normal', $data);
			do_meta_boxes($screen_id, 'advanced', $data);
			if (1 === (int) $columns) {
				do_meta_boxes($screen_id, 'side', $data);
			}
			?>
		</div>
	</div>
	<br class="clear">
</div>
