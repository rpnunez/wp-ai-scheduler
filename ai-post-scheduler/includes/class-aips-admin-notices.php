<?php
/**
 * Admin Notices Manager Class
 *
 * Centralizes administration notices and flash messages using WordPress core
 * wp_admin_notice() (WP 6.4+) with fallback rendering, user-level dismissal persistence,
 * and accessibility support.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Notices
 */
class AIPS_Admin_Notices {

	/**
	 * In-memory notice queue.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static $notices = array();

	/**
	 * Flash transient prefix.
	 */
	const FLASH_TRANSIENT_PREFIX = 'aips_flash_notices_';

	/**
	 * User meta key for dismissed notices.
	 */
	const DISMISSED_META_KEY = 'aips_dismissed_notices';

	/**
	 * Constructor for AJAX / DI instantiation.
	 */
	public function __construct() {
		add_action('wp_ajax_aips_dismiss_notice', array($this, 'ajax_dismiss_notice'));
	}

	/**
	 * Initialize notice hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('admin_notices', array(__CLASS__, 'render_notices'));
	}

	/**
	 * Queue an admin notice for rendering.
	 *
	 * @param string               $id      Unique identifier for the notice (used for dismissal).
	 * @param string               $message Notice content (HTML allowed if properly escaped).
	 * @param array<string, mixed> $args    Notice options:
	 *   - 'type': (string) 'success', 'warning', 'error', 'info'. Default 'info'.
	 *   - 'dismissible': (bool) Whether the notice can be dismissed. Default true.
	 *   - 'capability': (string) User capability required to view. Default 'manage_options'.
	 *   - 'screen': (string|null) Specific screen ID where the notice should appear, or null for all.
	 * @return void
	 */
	public static function add($id, $message, $args = array()) {
		$defaults = array(
			'type'        => 'info',
			'dismissible' => true,
			'capability'  => 'manage_options',
			'screen'      => null,
		);
		$parsed_args = wp_parse_args($args, $defaults);
		$parsed_args['id']      = sanitize_key($id);
		$parsed_args['message'] = $message;

		self::$notices[$parsed_args['id']] = $parsed_args;
	}

	/**
	 * Store a one-time flash notice to be displayed on the next admin page load.
	 *
	 * @param string $message     Notice message.
	 * @param string $type        Notice type ('success', 'warning', 'error', 'info').
	 * @param bool   $dismissible Whether the notice is dismissible.
	 * @param int    $user_id     Target user ID (0 for current user).
	 * @return void
	 */
	public static function add_flash($message, $type = 'success', $dismissible = true, $user_id = 0) {
		$target_user = $user_id > 0 ? $user_id : get_current_user_id();
		if (!$target_user) {
			return;
		}

		$transient_key = self::FLASH_TRANSIENT_PREFIX . $target_user;
		$flashes       = get_transient($transient_key);
		if (!is_array($flashes)) {
			$flashes = array();
		}

		$flashes[] = array(
			'message'     => $message,
			'type'        => $type,
			'dismissible' => $dismissible,
		);

		set_transient($transient_key, $flashes, 60);
	}

	/**
	 * Check if a specific notice has been dismissed by a user.
	 *
	 * @param string $id      Notice ID.
	 * @param int    $user_id Target user ID (0 for current user).
	 * @return bool True if dismissed, false otherwise.
	 */
	public static function is_dismissed($id, $user_id = 0) {
		$target_user = $user_id > 0 ? $user_id : get_current_user_id();
		if (!$target_user) {
			return false;
		}

		$dismissed = get_user_meta($target_user, self::DISMISSED_META_KEY, true);
		if (!is_array($dismissed)) {
			return false;
		}

		return in_array(sanitize_key($id), $dismissed, true);
	}

	/**
	 * Record a notice as dismissed for a user.
	 *
	 * @param string $id      Notice ID.
	 * @param int    $user_id Target user ID (0 for current user).
	 * @return bool True on success, false on failure.
	 */
	public static function dismiss($id, $user_id = 0) {
		$target_user = $user_id > 0 ? $user_id : get_current_user_id();
		if (!$target_user) {
			return false;
		}

		$dismissed = get_user_meta($target_user, self::DISMISSED_META_KEY, true);
		if (!is_array($dismissed)) {
			$dismissed = array();
		}

		$clean_id = sanitize_key($id);
		if (!in_array($clean_id, $dismissed, true)) {
			$dismissed[] = $clean_id;
			return (bool) update_user_meta($target_user, self::DISMISSED_META_KEY, $dismissed);
		}

		return true;
	}

	/**
	 * Render queued notices and pending flash messages on admin_notices hook.
	 *
	 * @return void
	 */
	public static function render_notices() {
		$user_id = get_current_user_id();
		$screen  = get_current_screen();

		// 1. Render and purge flash notices.
		if ($user_id) {
			$transient_key = self::FLASH_TRANSIENT_PREFIX . $user_id;
			$flashes       = get_transient($transient_key);
			if (is_array($flashes)) {
				delete_transient($transient_key);
				foreach ($flashes as $flash) {
					self::render_single_notice(
						$flash['message'],
						isset($flash['type']) ? $flash['type'] : 'success',
						!empty($flash['dismissible'])
					);
				}
			}
		}

		// 2. Render queued in-memory notices.
		foreach (self::$notices as $id => $notice) {
			if (!empty($notice['capability']) && !current_user_can($notice['capability'])) {
				continue;
			}

			if (!empty($notice['screen']) && $screen && $screen->id !== $notice['screen']) {
				continue;
			}

			if (!empty($notice['dismissible']) && self::is_dismissed($id, $user_id)) {
				continue;
			}

			self::render_single_notice(
				$notice['message'],
				$notice['type'],
				!empty($notice['dismissible']),
				$id
			);
		}
	}

	/**
	 * Output a single notice using wp_admin_notice() or accessible fallback HTML.
	 *
	 * @param string      $message     Notice message.
	 * @param string      $type        Notice type ('success', 'warning', 'error', 'info').
	 * @param bool        $dismissible Whether dismissible.
	 * @param string|null $id          Optional notice ID for persistence tracking.
	 * @return void
	 */
	public static function render_single_notice($message, $type = 'info', $dismissible = true, $id = null) {
		$allowed_types = array('success', 'warning', 'error', 'info');
		if (!in_array($type, $allowed_types, true)) {
			$type = 'info';
		}

		$attributes = array();
		if (!empty($id)) {
			$attributes['data-aips-notice-id'] = esc_attr(sanitize_key($id));
		}

		$classes = 'notice notice-' . sanitize_html_class($type) . ' aips-notice';
		if ($dismissible) {
			$classes .= ' is-dismissible';
		}
		$attr_str = '';
		foreach ($attributes as $attr_k => $attr_v) {
			$attr_str .= ' ' . esc_attr($attr_k) . '="' . esc_attr($attr_v) . '"';
		}

		$partials_path = defined('AIPS_PLUGIN_DIR')
			? AIPS_PLUGIN_DIR . 'templates/admin/partials/notices/admin-notice.php'
			: dirname(__DIR__) . '/templates/admin/partials/notices/admin-notice.php';
		include $partials_path;
	}

	/**
	 * AJAX endpoint to persist dismissal of a notice.
	 *
	 * @return void
	 */
	public static function ajax_dismiss_notice() {
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::forbidden(__('Permission denied.', 'ai-post-scheduler'));
		}

		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (!wp_verify_nonce($nonce, 'aips_ajax_nonce') && !wp_verify_nonce($nonce, 'aips_dismiss_notice_nonce')) {
			AIPS_Ajax_Response::invalid_nonce(__('Invalid security token.', 'ai-post-scheduler'));
		}

		$notice_id = isset($_POST['notice_id']) ? sanitize_key(wp_unslash($_POST['notice_id'])) : '';
		if (empty($notice_id)) {
			AIPS_Ajax_Response::invalid_request(__('Missing notice identifier.', 'ai-post-scheduler'));
		}

		self::dismiss($notice_id);
		AIPS_Ajax_Response::success(array('dismissed' => $notice_id));
	}
}
