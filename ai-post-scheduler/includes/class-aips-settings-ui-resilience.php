<?php
/**
 * Settings UI Presenter: Resilience & Fault Tolerance
 *
 * Handles rendering and callbacks for retries, delays, rate limiting,
 * circuit breaker, and batch resume cooldown.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Settings_UI_Resilience
 */
class AIPS_Settings_UI_Resilience {

	/**
	 * Render the resilience section description.
	 *
	 * @return void
	 */
	public function resilience_section_callback() {
		echo '<p>' . esc_html__('Configure advanced resilience options to protect the application from failing and being blocked when external services return errors.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render the enable retry setting field.
	 *
	 * @return void
	 */
	public function enable_retry_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_enable_retry');
		?>
		<input type="hidden" name="aips_enable_retry" value="0">
		<input type="checkbox" name="aips_enable_retry" value="1" <?php checked(1, $value); ?>>
		<p class="description"><?php esc_html_e('Enable exponential backoff and retry logic for failed AI requests.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the max retries setting field.
	 *
	 * @return void
	 */
	public function max_retries_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_retry_max_attempts');
		?>
		<input type="number" name="aips_retry_max_attempts" value="<?php echo esc_attr($value); ?>" min="0" max="10" class="small-text">
		<p class="description"><?php esc_html_e('Number of retry attempts if generation fails.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the retry initial delay setting field.
	 *
	 * @return void
	 */
	public function retry_initial_delay_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_retry_initial_delay');
		?>
		<input type="number" name="aips_retry_initial_delay" value="<?php echo esc_attr($value); ?>" min="1" max="60" class="small-text">
		<p class="description"><?php esc_html_e('Initial delay (in seconds) before the first retry attempt.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the enable rate limiting setting field.
	 *
	 * @return void
	 */
	public function enable_rate_limiting_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_enable_rate_limiting');
		?>
		<input type="hidden" name="aips_enable_rate_limiting" value="0">
		<input type="checkbox" name="aips_enable_rate_limiting" value="1" <?php checked(1, $value); ?>>
		<p class="description"><?php esc_html_e('Limit the number of AI requests per time period.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the rate limit requests setting field.
	 *
	 * @return void
	 */
	public function rate_limit_requests_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_rate_limit_requests');
		?>
		<input type="number" name="aips_rate_limit_requests" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
		<p class="description"><?php esc_html_e('Maximum number of allowed requests within the defined period.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the rate limit period setting field.
	 *
	 * @return void
	 */
	public function rate_limit_period_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_rate_limit_period');
		?>
		<input type="number" name="aips_rate_limit_period" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
		<p class="description"><?php esc_html_e('Period (in seconds) for rate limiting (e.g., 60 = 1 minute).', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the generation pacing delay setting field.
	 *
	 * @return void
	 */
	public function generation_delay_seconds_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_generation_delay_seconds');
		?>
		<input type="number" name="aips_generation_delay_seconds" value="<?php echo esc_attr($value); ?>" min="0" max="30" class="small-text">
		<p class="description"><?php esc_html_e('Pause between posts in scheduled batch runs to smooth server load and stay under provider rate limits. Manual runs are not delayed. 0 disables the pause.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the batch resume cooldown setting field.
	 *
	 * @return void
	 */
	public function batch_resume_cooldown_minutes_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_batch_resume_cooldown_minutes');
		?>
		<input type="number" name="aips_batch_resume_cooldown_minutes" value="<?php echo esc_attr($value); ?>" min="1" max="1440" class="small-text">
		<p class="description"><?php esc_html_e('When a scheduled batch stops early to avoid a PHP timeout, wait this many minutes before generating its remaining posts.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the enable circuit breaker setting field.
	 *
	 * @return void
	 */
	public function enable_circuit_breaker_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_enable_circuit_breaker');
		?>
		<input type="hidden" name="aips_enable_circuit_breaker" value="0">
		<input type="checkbox" name="aips_enable_circuit_breaker" value="1" <?php checked(1, $value); ?>>
		<p class="description"><?php esc_html_e('Enable circuit breaker to temporarily halt requests after a number of consecutive failures.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the circuit breaker threshold setting field.
	 *
	 * @return void
	 */
	public function circuit_breaker_threshold_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_circuit_breaker_threshold');
		?>
		<input type="number" name="aips_circuit_breaker_threshold" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
		<p class="description"><?php esc_html_e('Number of consecutive failures required to open the circuit.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the circuit breaker timeout setting field.
	 *
	 * @return void
	 */
	public function circuit_breaker_timeout_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_circuit_breaker_timeout');
		?>
		<input type="number" name="aips_circuit_breaker_timeout" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
		<p class="description"><?php esc_html_e('Time (in seconds) to keep the circuit open before attempting to recover.', 'ai-post-scheduler'); ?></p>
		<?php
	}
}
