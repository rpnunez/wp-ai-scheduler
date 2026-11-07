<?php
/**
 * Admin Pointers JavaScript Initialization Partial
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		var activePointers = <?php echo wp_json_encode($active); ?>;
		$.each(activePointers, function(pointerId, pointerData) {
			var $target = $(pointerData.target);
			if (!$target.length) {
				return;
			}

			var contentHtml = '<h3>' + pointerData.title + '</h3><p>' + pointerData.content + '</p>';

			$target.pointer({
				content: contentHtml,
				position: {
					edge: pointerData.edge,
					align: pointerData.align
				},
				close: function() {
					$.post(ajaxurl, {
						action: 'dismiss-wp-pointer',
						pointer: pointerId,
						_ajax_nonce: pointerData.nonce
					});
				}
			}).pointer('open');
		});
	});
</script>
