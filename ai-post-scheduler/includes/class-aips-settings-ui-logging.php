<?php
/**
 * Settings UI Presenter: Logging, Telemetry & Cache
 *
 * Handles rendering and sanitization for logging levels, telemetry,
 * table filter persistence, and cache driver settings.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Settings_UI_Logging
 */
class AIPS_Settings_UI_Logging {

	/**
	 * Render the logging enable setting field.
	 *
	 * @return void
	 */
	public function logging_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_enable_logging');
		?>
		<input type="hidden" name="aips_enable_logging" value="0">
		<label>
			<input type="checkbox" name="aips_enable_logging" value="1" <?php checked($value, 1); ?>>
			<?php esc_html_e('Enable detailed logging for debugging', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	/**
	 * Render the table filter persistence setting field.
	 *
	 * @return void
	 */
	public function persist_table_filters_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_persist_table_filters');
		?>
		<input type="hidden" name="aips_persist_table_filters" value="0">
		<label>
			<input type="checkbox" name="aips_persist_table_filters" value="1" <?php checked($value, 1); ?>>
			<?php esc_html_e('Persist table filter and search selections across sessions', 'ai-post-scheduler'); ?>
		</label>
		<p class="description">
			<?php esc_html_e('When enabled, your active search terms, status views, and filter dropdowns are automatically remembered in your browser for each table.', 'ai-post-scheduler'); ?>
		</p>
		<?php
	}

	/**
	 * Render the enable telemetry setting field.
	 *
	 * @return void
	 */
	public function enable_telemetry_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_enable_telemetry');
		?>
		<input type="hidden" name="aips_enable_telemetry" value="0">
		<label>
			<input type="checkbox" name="aips_enable_telemetry" value="1" <?php checked($value, 1); ?>>
			<?php esc_html_e('Enable request-level telemetry (staging/dev only)', 'ai-post-scheduler'); ?>
		</label>
		<p class="description"><?php esc_html_e('Logs query counts, memory usage, elapsed time, and events for each request to the aips_telemetry table. Not recommended for production.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the cache monitor setting field.
	 *
	 * @return void
	 */
	public function cache_monitor_enabled_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_cache_monitor_enabled');
		?>
		<input type="hidden" name="aips_cache_monitor_enabled" value="0">
		<label>
			<input type="checkbox" name="aips_cache_monitor_enabled" value="1" <?php checked($value, 1); ?>>
			<?php esc_html_e('Enable Cache Monitor (internal cache introspection tool)', 'ai-post-scheduler'); ?>
		</label>
		<p class="description"><?php esc_html_e('Exposes an admin page and AJAX endpoints for inspecting and flushing internal cache entries. Not recommended for production.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the cache section description.
	 *
	 * @return void
	 */
	public function cache_section_callback() {
		echo '<p>' . esc_html__('Configure the caching layer used by the plugin. Choose between Array, WP Object Cache, or Database.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render the Enable Cache System radio field.
	 *
	 * @return void
	 */
	public function enable_cache_system_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_enable_cache_system');
		$enabled = ($value !== '0' && $value !== 0 && $value !== false);
		?>
		<fieldset>
			<label>
				<input type="radio" name="aips_enable_cache_system" value="1" <?php checked($enabled, true); ?>>
				<?php esc_html_e('Yes', 'ai-post-scheduler'); ?>
			</label>
			&nbsp;&nbsp;
			<label>
				<input type="radio" name="aips_enable_cache_system" value="0" <?php checked($enabled, false); ?>>
				<?php esc_html_e('No', 'ai-post-scheduler'); ?>
			</label>
		</fieldset>
		<p class="description">
			<?php esc_html_e('Enable the plugin\'s internal cache layer to improve performance. When enabled, the cache stores repeated database reads, compiled template data, and other expensive computations in memory (or another backend) so they are not recalculated on every page load or cron run. This can significantly reduce database queries and speed up content generation, admin pages, and scheduled tasks. When disabled, every operation reads directly from the database and no data is stored in cache — useful for debugging or troubleshooting cache-related issues.', 'ai-post-scheduler'); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize the Enable Cache System value.
	 *
	 * @param mixed $value
	 * @return string '1' or '0'.
	 */
	public function sanitize_enable_cache_system($value) {
		return ($value === '1' || $value === 1 || $value === true) ? '1' : '0';
	}

	/**
	 * Render the Cache Driver selector.
	 *
	 * @return void
	 */
	public function cache_driver_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_cache_driver');
		$drivers = array(
			'array'           => __('Array (in-memory, request-scoped) (default)', 'ai-post-scheduler'),
			'wp_object_cache' => __('WP Object Cache (uses wp_cache_* functions)', 'ai-post-scheduler'),
			'db'              => __('Database (persistent, uses plugin DB table)', 'ai-post-scheduler'),
		);
		?>
		<div class="aips-cache-system-fields">
			<select name="aips_cache_driver" id="aips_cache_driver">
				<?php foreach ($drivers as $key => $label) : ?>
					<option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e('Select which cache backend to use. Array is the safe default and requires no configuration. WP Object Cache is recommended when your site has a persistent object cache backend. Database provides persistent plugin-managed storage.', 'ai-post-scheduler'); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the Default TTL field.
	 *
	 * @return void
	 */
	public function cache_default_ttl_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_cache_default_ttl');
		?>
		<div class="aips-cache-system-fields">
			<input type="number" name="aips_cache_default_ttl" value="<?php echo esc_attr($value); ?>" min="0" class="small-text">
			<p class="description"><?php esc_html_e('Default time-to-live in seconds for cached values. 0 = no expiration. Default: 3600 (1 hour).', 'ai-post-scheduler'); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the DB Cache Key Prefix field.
	 *
	 * @return void
	 */
	public function cache_db_prefix_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_cache_db_prefix');
		?>
		<div class="aips-cache-db-fields">
			<input type="text" name="aips_cache_db_prefix" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php esc_attr_e('Optional — e.g. mysite', 'ai-post-scheduler'); ?>">
			<p class="description"><?php esc_html_e('Optional prefix applied to every cache key in the database table. Useful when multiple environments share the same DB.', 'ai-post-scheduler'); ?></p>
		</div>
		<?php
	}

	/**
	 * Sanitize and validate the selected cache driver value.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_cache_driver($value) {
		$allowed = array('array', 'db', 'wp_object_cache');
		$legacy  = array('session', 'redis');
		$value   = sanitize_text_field((string) $value);

		if (in_array($value, $legacy, true)) {
			return 'wp_object_cache';
		}

		return in_array($value, $allowed, true) ? $value : 'array';
	}
}
