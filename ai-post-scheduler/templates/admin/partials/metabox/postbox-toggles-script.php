<?php
/**
 * Postbox Toggles Footer Script Partial
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		if (typeof postboxes !== 'undefined' && typeof postboxes.add_postbox_toggles === 'function') {
			postboxes.add_postbox_toggles('<?php echo esc_js($screen_id); ?>');
		}
	});
</script>
