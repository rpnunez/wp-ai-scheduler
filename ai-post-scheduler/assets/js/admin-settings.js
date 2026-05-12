/**
 * Settings page field behavior.
 *
 * The hub shell now owns settings navigation, so this file only manages
 * conditional field visibility within the currently rendered settings panel.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	/**
	 * Toggle visibility of cache-system-specific setting rows.
	 *
	 * @return {void}
	 */
	function updateCacheSystemFields() {
		var enabled = $('input[name="aips_enable_cache_system"]:checked').val() === '1';

		$('.aips-cache-system-fields').each(function() {
			$(this).closest('tr').toggle(enabled);
		});

		if (enabled) {
			updateCacheDriverFields();
		} else {
			$('.aips-cache-redis-fields, .aips-cache-db-fields').each(function() {
				$(this).closest('tr').hide();
			});
		}
	}

	/**
	 * Toggle visibility of driver-specific cache rows.
	 *
	 * @return {void}
	 */
	function updateCacheDriverFields() {
		var driver = $('#aips_cache_driver').val();

		$('.aips-cache-redis-fields').each(function() {
			$(this).closest('tr').toggle(driver === 'redis');
		});

		$('.aips-cache-db-fields').each(function() {
			$(this).closest('tr').toggle(driver === 'db');
		});
	}

	$(document).ready(function() {
		if ($('input[name="aips_enable_cache_system"]').length) {
			updateCacheSystemFields();
			$(document).on('change', 'input[name="aips_enable_cache_system"]', updateCacheSystemFields);
		}

		if ($('#aips_cache_driver').length) {
			$(document).on('change', '#aips_cache_driver', updateCacheDriverFields);
		}
	});

})(jQuery);
