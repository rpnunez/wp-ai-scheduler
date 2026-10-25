<?php
/**
 * Settings UI Presenter: Integrations & Notifications
 *
 * Handles rendering and sanitization for Unsplash API keys, Google Search Console,
 * and notification preferences and channels.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Settings_UI_Integrations
 */
class AIPS_Settings_UI_Integrations {

	/**
	 * Render the description for the API keys settings section.
	 *
	 * @return void
	 */
	public function api_keys_section_callback() {
		echo '<p>' . esc_html__('Enter API keys for third-party services used by the plugin.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render Unsplash access key field.
	 *
	 * @return void
	 */
	public function unsplash_access_key_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_unsplash_access_key');
		?>
		<input type="text" name="aips_unsplash_access_key" value="<?php echo esc_attr($value); ?>" class="regular-text" autocomplete="new-password">
		<p class="description"><?php esc_html_e('Required for fetching images from Unsplash. Generate a Client ID at unsplash.com/developers.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the Google Search Console connection (API Keys tab).
	 *
	 * @return void
	 */
	public function gsc_field_callback() {
		$config     = AIPS_Config::get_instance();
		$service    = AIPS_Container::get_instance()->make(AIPS_GSC_Keywords_Service::class);
		$client     = $service->get_client();
		$email      = $client->get_client_email();
		$unreadable = $client->has_unreadable_credentials();
		$property   = $client->get_property();
		$anchor     = (bool) $config->get_option('aips_gsc_anchor_enabled', true);
		$status     = $service->get_status();
		?>
		<div class="aips-gsc-field" id="aips-gsc-field">
			<p class="aips-gsc-status">
				<?php if ($email !== '') : ?>
					<span class="aips-badge aips-badge-success"><?php esc_html_e('Key saved', 'ai-post-scheduler'); ?></span>
					<?php
					/* translators: %s: service account email */
					printf(esc_html__('Service account: %s', 'ai-post-scheduler'), '<code>' . esc_html($email) . '</code>');
					?>
				<?php elseif ($unreadable) : ?>
					<span class="aips-badge aips-badge-danger"><?php esc_html_e('Key unreadable', 'ai-post-scheduler'); ?></span>
					<?php esc_html_e('The saved key can no longer be decrypted (the site\'s security salts changed). Paste the JSON key again.', 'ai-post-scheduler'); ?>
				<?php else : ?>
					<span class="aips-badge aips-badge-neutral"><?php esc_html_e('Not connected', 'ai-post-scheduler'); ?></span>
				<?php endif; ?>
			</p>

			<p>
				<label for="aips_gsc_service_account"><strong><?php esc_html_e('Service account JSON key', 'ai-post-scheduler'); ?></strong></label><br>
				<textarea name="aips_gsc_service_account" id="aips_gsc_service_account" rows="4" class="large-text code" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr($email !== '' ? __('Leave empty to keep the saved key, or paste a new JSON key to replace it.', 'ai-post-scheduler') : __('Paste the contents of the downloaded JSON key file ({ "type": "service_account", ... })', 'ai-post-scheduler')); ?>"></textarea>
			</p>

			<p>
				<label for="aips_gsc_property"><strong><?php esc_html_e('Property', 'ai-post-scheduler'); ?></strong></label><br>
				<input type="text" name="aips_gsc_property" id="aips_gsc_property" value="<?php echo esc_attr($property); ?>" class="regular-text" placeholder="<?php echo esc_attr('sc-domain:' . wp_parse_url(home_url(), PHP_URL_HOST)); ?>">
				<span class="description"><?php esc_html_e('Exactly as shown in Search Console: sc-domain:example.com for a Domain property, or https://example.com/ for a URL-prefix property.', 'ai-post-scheduler'); ?></span>
			</p>

			<p>
				<label for="aips_gsc_anchor_enabled">
					<input type="checkbox" name="aips_gsc_anchor_enabled" id="aips_gsc_anchor_enabled" value="1" <?php checked($anchor); ?>>
					<?php esc_html_e('Prefer the search queries each post ranks for as anchor text in internal link suggestions', 'ai-post-scheduler'); ?>
				</label>
			</p>

			<p class="aips-gsc-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-gsc-test"><?php esc_html_e('Test Connection', 'ai-post-scheduler'); ?></button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-gsc-sync"><?php esc_html_e('Sync Keywords Now', 'ai-post-scheduler'); ?></button>
				<?php if ($email !== '' || $unreadable) : ?>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost" id="aips-gsc-disconnect"><?php esc_html_e('Disconnect', 'ai-post-scheduler'); ?></button>
				<?php endif; ?>
			</p>

			<p class="description" id="aips-gsc-last-sync">
				<?php
				if ($status && $status['error'] !== '') {
					/* translators: 1: time ago, 2: error message */
					printf(esc_html__('Last sync failed %1$s ago: %2$s', 'ai-post-scheduler'), esc_html(human_time_diff((int) $status['time'])), esc_html($status['error']));
				} elseif ($status) {
					/* translators: 1: time ago, 2: number of keywords, 3: number of posts */
					printf(esc_html__('Last synced %1$s ago: %2$d target keywords for %3$d posts. Syncs daily.', 'ai-post-scheduler'), esc_html(human_time_diff((int) $status['time'])), (int) $status['queries'], (int) $status['posts']);
				} else {
					esc_html_e('Not synced yet. Save your settings first, then test and sync.', 'ai-post-scheduler');
				}
				?>
			</p>

			<details class="aips-gsc-help">
				<summary><?php esc_html_e('How to connect (why not an API key?)', 'ai-post-scheduler'); ?></summary>
				<p><?php esc_html_e('Search Console data is private to verified site owners, so Google does not allow reading it with a plain API key. A service account is a robot Google user you create once and grant read-only access:', 'ai-post-scheduler'); ?></p>
				<ol>
					<li><?php esc_html_e('In Google Cloud Console, create (or pick) a project and enable the "Google Search Console API".', 'ai-post-scheduler'); ?></li>
					<li><?php esc_html_e('Go to IAM & Admin → Service Accounts → Create service account (no roles needed).', 'ai-post-scheduler'); ?></li>
					<li><?php esc_html_e('Open it → Keys → Add key → Create new key → JSON. Paste the downloaded file above.', 'ai-post-scheduler'); ?></li>
					<li><?php esc_html_e('In Search Console → Settings → Users and permissions, add the service account email as a user with Restricted permission.', 'ai-post-scheduler'); ?></li>
					<li><?php esc_html_e('Enter the property, save settings, then click Test Connection and Sync Keywords Now.', 'ai-post-scheduler'); ?></li>
				</ol>
				<p><?php esc_html_e('The key is stored encrypted and only has read-only access. Only the top search queries per post are stored.', 'ai-post-scheduler'); ?></p>
			</details>
		</div>
		<?php
	}

	/**
	 * Render the review notifications email setting field.
	 *
	 * @return void
	 */
	public function review_notifications_email_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_review_notifications_email') ?: get_option('admin_email');
		?>
		<input type="text" name="aips_review_notifications_email" value="<?php echo esc_attr($value); ?>" class="regular-text">
		<p class="description"><?php esc_html_e('Comma-separated email addresses used for system notification emails.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the notifications section description.
	 *
	 * @return void
	 */
	public function notifications_section_callback() {
		echo '<p>' . esc_html__('Configure the notification email address and delivery channels for all plugin notifications. Email is sent to the notification email addresses configured below.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render a notification channel preference select field.
	 *
	 * @param array $args Field configuration.
	 * @return void
	 */
	public function notification_preference_field_callback($args) {
		$type               = isset($args['type']) ? sanitize_key($args['type']) : '';
		$preferences_stored = AIPS_Config::get_instance()->get_option('aips_notification_preferences');
		$preferences        = is_array($preferences_stored) ? $preferences_stored : array();
		$defaults           = AIPS_Config::get_instance()->get_option('aips_notification_preferences');
		$defaults           = is_array($defaults) ? $defaults : array();
		$registry           = AIPS_Notifications::get_notification_type_registry();
		$registry_default   = isset($registry[$type]['default_mode']) ? $registry[$type]['default_mode'] : AIPS_Notifications::MODE_BOTH;
		$value              = isset($preferences[$type]) ? $preferences[$type] : (isset($defaults[$type]) ? $defaults[$type] : $registry_default);
		?>
		<select name="aips_notification_preferences[<?php echo esc_attr($type); ?>]">
			<?php foreach (AIPS_Notifications::get_channel_mode_options() as $mode => $label) : ?>
				<option value="<?php echo esc_attr($mode); ?>" <?php selected($value, $mode); ?>><?php echo esc_html($label); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if (!empty($args['description'])) : ?>
			<p class="description"><?php echo esc_html($args['description']); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Sanitize comma-separated notification email addresses.
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_notification_emails($value) {
		$emails    = preg_split('/\s*,\s*/', (string) $value);
		$emails    = is_array($emails) ? $emails : array();
		$sanitized = array();

		foreach ($emails as $email) {
			$email = sanitize_email($email);
			if (!empty($email) && is_email($email)) {
				$sanitized[] = $email;
			}
		}

		$sanitized = array_values(array_unique($sanitized));
		return implode(', ', $sanitized);
	}

	/**
	 * Sanitize notification preference channel modes.
	 *
	 * @param mixed $value Raw option value.
	 * @return array
	 */
	public function sanitize_notification_preferences($value) {
		$preferences   = is_array($value) ? $value : array();
		$defaults      = AIPS_Config::get_instance()->get_option('aips_notification_preferences');
		$allowed_modes = array_keys(AIPS_Notifications::get_channel_mode_options());
		$sanitized     = array();

		foreach (AIPS_Notifications::get_notification_type_registry() as $type => $meta) {
			$fallback_mode = isset($defaults[$type]) ? $defaults[$type] : (isset($meta['default_mode']) ? $meta['default_mode'] : 'both');
			$mode          = isset($preferences[$type]) ? sanitize_key($preferences[$type]) : $fallback_mode;

			if (!in_array($mode, $allowed_modes, true)) {
				$mode = $fallback_mode;
			}

			$sanitized[$type] = $mode;
		}

		return $sanitized;
	}
}
