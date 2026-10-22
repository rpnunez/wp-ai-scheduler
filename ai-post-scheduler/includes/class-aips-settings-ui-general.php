<?php
/**
 * Settings UI Presenter: General
 *
 * Handles rendering and sanitization for General settings (post status, category,
 * bulk publish thresholds, source archive retention, developer mode).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Settings_UI_General
 */
class AIPS_Settings_UI_General {

	/**
	 * Retention is disabled at zero; never convert a negative value into deletion.
	 *
	 * @param mixed $value
	 * @return int
	 */
	public function sanitize_source_archive_retention_days($value) {
		return max(0, min(3650, (int) $value));
	}

	/**
	 * Render source archive retention field.
	 *
	 * @return void
	 */
	public function source_archive_retention_field_callback() {
		$days = $this->sanitize_source_archive_retention_days(AIPS_Config::get_instance()->get_option('aips_source_archive_retention_days'));
		echo '<input type="number" min="0" max="3650" step="1" id="aips_source_archive_retention_days" name="aips_source_archive_retention_days" value="' . esc_attr((string) $days) . '" class="small-text">';
		echo '<p class="description">' . esc_html__('Days to retain source snapshots. 0 disables deletion. Daily cleanup removes up to 100 expired snapshots per batch and always keeps the newest usable snapshot for each current source URL. Generation history keeps its metadata, but full archived content becomes unavailable after deletion.', 'ai-post-scheduler') . '</p>';

		$review_days = isset($_GET['aips_retention_review_days']) ? $this->sanitize_source_archive_retention_days(wp_unslash($_GET['aips_retention_review_days'])) : $days;
		echo '<label for="aips-retention-review-days">' . esc_html__('Review cutoff (days):', 'ai-post-scheduler') . '</label> ';
		echo '<input type="number" id="aips-retention-review-days" min="1" max="3650" value="' . esc_attr((string) max(1, $review_days)) . '" class="small-text" oninput="this.nextElementSibling.href=this.nextElementSibling.dataset.base+encodeURIComponent(this.value)"> ';
		$base = admin_url('admin.php?page=aips-settings&tab=settings-general&aips_retention_review_days=');
		echo '<a class="button" data-base="' . esc_url($base) . '" href="' . esc_url($base . max(1, $review_days)) . '">' . esc_html__('Review eligible snapshots', 'ai-post-scheduler') . '</a>';
		if ($review_days > 0) {
			try {
				$count = (new AIPS_Sources_Data_Repository())->count_prunable_archive(AIPS_DateTime::now()->timestamp() - $review_days * DAY_IN_SECONDS);
			} catch (Throwable $error) {
				echo '<p class="description">' . esc_html__('The eligible snapshot count is unavailable. Check the database before enabling retention.', 'ai-post-scheduler') . '</p>';
				return;
			}
			/* translators: 1: snapshot count, 2: retention days. */
			echo '<p class="description">' . esc_html(sprintf(__('%1$d snapshots are eligible for deletion with a %2$d-day cutoff. Review this count before saving a nonzero retention period.', 'ai-post-scheduler'), $count, $review_days)) . '</p>';
		}
	}

	/**
	 * Render the description for the general settings section.
	 *
	 * @return void
	 */
	public function general_section_callback() {
		echo '<p>' . esc_html__('Configure default settings for AI-generated posts.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render the description for the developers settings section.
	 *
	 * @return void
	 */
	public function developers_section_callback() {
		echo '<p>' . esc_html__('Options for debugging and plugin development. Not recommended for production use.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render the default post status setting field.
	 *
	 * @return void
	 */
	public function post_status_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_default_post_status');
		?>
		<select name="aips_default_post_status">
			<option value="draft" <?php selected($value, 'draft'); ?>><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
			<option value="pending" <?php selected($value, 'pending'); ?>><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></option>
			<option value="publish" <?php selected($value, 'publish'); ?>><?php esc_html_e('Published', 'ai-post-scheduler'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Default status for newly generated posts.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the default category setting field.
	 *
	 * @return void
	 */
	public function category_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_default_category');
		wp_dropdown_categories(array(
			'name'             => 'aips_default_category',
			'selected'         => $value,
			'show_option_none' => __('Select a category', 'ai-post-scheduler'),
			'option_none_value' => 0,
			'hide_empty'       => false,
		));
		echo '<p class="description">' . esc_html__('Default category for generated posts.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render the bulk-publish background threshold field.
	 *
	 * @return void
	 */
	public function bulk_publish_async_threshold_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_bulk_publish_async_threshold');
		?>
		<input type="number" name="aips_bulk_publish_async_threshold" value="<?php echo esc_attr($value); ?>" min="2" max="500" step="1" class="small-text">
		<p class="description">
			<?php esc_html_e('On the Content → Pending Review screen, when you bulk-publish this many drafts or more, they are published in the background (via WP-Cron) so the page responds instantly instead of waiting for every post. You can watch progress under Diagnostics → Background Processes. Smaller selections publish immediately. Raise this to keep more selections publishing instantly; lower it to offload medium-sized batches to the background sooner. Default: 30.', 'ai-post-scheduler'); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize the bulk-publish background threshold.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return int
	 */
	public function sanitize_bulk_publish_async_threshold($value) {
		$value = absint($value);
		if ($value < 2) {
			$value = 2;
		} elseif ($value > 500) {
			$value = 500;
		}
		return $value;
	}

	/**
	 * Render developer mode field.
	 *
	 * @return void
	 */
	public function developer_mode_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_developer_mode');
		?>
		<input type="hidden" name="aips_developer_mode" value="0">
		<label>
			<input type="checkbox" name="aips_developer_mode" value="1" <?php checked($value, 1); ?>>
			<?php esc_html_e('Enable developer tools and features', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}
}
