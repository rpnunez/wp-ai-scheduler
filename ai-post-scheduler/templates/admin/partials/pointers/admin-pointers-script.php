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
		var activePointers = <?php echo wp_json_encode($active, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
		$.each(activePointers, function(pointerId, pointerData) {
			var $target = $(pointerData.target);
			if (!$target.length) {
				return;
			}

			var title = $('<div>').text(pointerData.title).html();
			var content = $('<div>').text(pointerData.content).html();
			var contentHtml = '<h3>' + title + '</h3><p>' + content + '</p>';

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
