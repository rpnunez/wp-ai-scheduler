<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Bar
 *
 * Adds an "AI Post Scheduler" node to the WordPress admin toolbar.
 * Requires the `manage_options` capability.
 *
 * The toolbar node renders:
 *  - An icon with an unread-notification badge.
 *  - Quick links to Templates, Authors, and Schedules.
 *  - A list of unread system notifications; each can be marked as read.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */
class AIPS_Admin_Bar {

	/**
	 * @var AIPS_Notifications_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new AIPS_Notifications_Repository();

		add_action('admin_bar_menu', array($this, 'add_toolbar_node'), 100);
		add_action('wp_ajax_aips_mark_notification_read', array($this, 'ajax_mark_read'));
		add_action('wp_ajax_aips_mark_all_notifications_read', array($this, 'ajax_mark_all_read'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets')); // toolbar visible on front-end too
	}

	/**
	 * Enqueue CSS and JS needed for the toolbar dropdown.
	 */
	public function enqueue_assets() {
		if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
			return;
		}

		wp_enqueue_style(
			'aips-admin-bar',
			AIPS_PLUGIN_URL . 'assets/css/admin-bar.css',
			array(),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-admin-bar',
			AIPS_PLUGIN_URL . 'assets/js/admin-bar.js',
			array('jquery'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-bar', 'aipsAdminBarL10n', array(
			'ajaxUrl'         => admin_url('admin-ajax.php'),
			'nonce'           => wp_create_nonce('aips_admin_bar_nonce'),
			'markReadError'   => __('Could not mark notification as read.', 'ai-post-scheduler'),
			'markAllReadError' => __('Could not mark all notifications as read.', 'ai-post-scheduler'),
		));
	}

	/**
	 * Add the AI Post Scheduler node to the admin toolbar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
	 */
	public function add_toolbar_node($wp_admin_bar) {
		if (!current_user_can('manage_options')) {
			return;
		}

		$cache_key   = 'aips_unread_count_' . get_current_user_id();
		$unread_count = wp_cache_get($cache_key, 'aips_admin_bar');
		if (false === $unread_count) {
			$unread_count = $this->repository->count_unread();
			wp_cache_set($cache_key, $unread_count, 'aips_admin_bar');
		}

		// ---------- Root node (icon + badge) ----------
		$badge = '';
		if ($unread_count > 0) {
			$badge = '<span class="aips-toolbar-badge">' . esc_html(min($unread_count, 99)) . ($unread_count > 99 ? '+' : '') . '</span>';
		}

		$title = '<span class="ab-icon dashicons dashicons-schedule aips-toolbar-icon"></span>'
			. '<span class="ab-label">' . esc_html__('AI Scheduler', 'ai-post-scheduler') . '</span>'
			. $badge;

		$wp_admin_bar->add_node(array(
			'id'    => 'aips-toolbar',
			'title' => $title,
			'href'  => AIPS_Admin_Menu_Helper::get_page_url('dashboard'),
			'meta'  => array(
				'class' => 'aips-toolbar-root' . ($unread_count > 0 ? ' aips-has-notifications' : ''),
				'title' => esc_attr__('AI Post Scheduler', 'ai-post-scheduler'),
			),
		));

		// ---------- Quick links group ----------
		$wp_admin_bar->add_group(array(
			'id'     => 'aips-toolbar-links',
			'parent' => 'aips-toolbar',
			'meta'   => array('class' => 'aips-toolbar-group-links'),
		));

		$quick_links = array(
			array(
				'id'    => 'aips-toolbar-templates',
				'title' => '<span class="aips-toolbar-link-icon dashicons dashicons-admin-page" aria-hidden="true"></span><span class="aips-toolbar-link-label">' . esc_html__('Templates', 'ai-post-scheduler') . '</span>',
				'href'  => AIPS_Admin_Menu_Helper::get_page_url('templates'),
			),
			array(
				'id'    => 'aips-toolbar-authors',
				'title' => '<span class="aips-toolbar-link-icon dashicons dashicons-admin-users" aria-hidden="true"></span><span class="aips-toolbar-link-label">' . esc_html__('Authors', 'ai-post-scheduler') . '</span>',
				'href'  => AIPS_Admin_Menu_Helper::get_page_url('authors'),
			),
			array(
				'id'    => 'aips-toolbar-schedules',
				'title' => '<span class="aips-toolbar-link-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span><span class="aips-toolbar-link-label">' . esc_html__('Schedules', 'ai-post-scheduler') . '</span>',
				'href'  => AIPS_Admin_Menu_Helper::get_page_url('schedule'),
			),
		);

		foreach ($quick_links as $link) {
			$wp_admin_bar->add_node(array(
				'id'     => $link['id'],
				'parent' => 'aips-toolbar-links',
				'title'  => $link['title'],
				'href'   => $link['href'],
			));
		}

		// ---------- Notifications group ----------
		$notifications = $this->repository->get_unread(20);

		$wp_admin_bar->add_group(array(
			'id'     => 'aips-toolbar-notifications',
			'parent' => 'aips-toolbar',
			'meta'   => array('class' => 'aips-toolbar-group-notifications'),
		));

		if (empty($notifications)) {
			// "No new notifications" placeholder
			$wp_admin_bar->add_node(array(
				'id'     => 'aips-toolbar-no-notifications',
				'parent' => 'aips-toolbar-notifications',
				'title'  => '<span class="aips-toolbar-empty">' . esc_html__('No new notifications', 'ai-post-scheduler') . '</span>',
				'href'   => false,
				'meta'   => array('class' => 'aips-toolbar-no-notifications ab-empty-item'),
			));
		} else {
			// Header row with "Mark all as read"
			$wp_admin_bar->add_node(array(
				'id'     => 'aips-toolbar-notifications-header',
				'parent' => 'aips-toolbar-notifications',
				'title'  => '<span class="aips-toolbar-notif-heading">'
					. esc_html__('Notifications', 'ai-post-scheduler')
					. '</span>'
					. '<span class="aips-toolbar-notif-columns">'
					. '<span class="aips-toolbar-notif-column-label">' . esc_html__('Content', 'ai-post-scheduler') . '</span>'
					. '<span class="aips-toolbar-notif-column-label">' . esc_html__('Date/Time', 'ai-post-scheduler') . '</span>'
					. '<span class="aips-toolbar-notif-column-label">' . esc_html__('Action', 'ai-post-scheduler') . '</span>'
					. '</span>'
					. '<button class="aips-mark-all-read" data-nonce="' . esc_attr(wp_create_nonce('aips_admin_bar_nonce')) . '">'
					. esc_html__('Mark all as read', 'ai-post-scheduler')
					. '</button>',
				'href'   => false,
				'meta'   => array('class' => 'aips-toolbar-notif-header ab-empty-item'),
			));

			foreach ($notifications as $notif) {
				$notification_title_parts = $this->get_notification_title_parts($notif);
				$event_label = $notification_title_parts['event'];
				$context_label = $notification_title_parts['context'];
				$timestamp_label = $this->format_notification_timestamp($notif);

				$node_title  = '<span class="aips-notif-grid">';
				$node_title .= '<span class="aips-notif-content">';
				$node_title .= '<span class="aips-notif-event">' . esc_html($event_label) . '</span>';

				if ('' !== $context_label) {
					$node_title .= '<span class="aips-notif-context">' . esc_html($context_label) . '</span>';
				}

				if (!empty($notif->message)) {
					$node_title .= '<span class="aips-notif-message">' . esc_html($notif->message) . '</span>';
				}

				$node_title .= '</span>';
				$node_title .= '<span class="aips-notif-datetime">' . esc_html($timestamp_label) . '</span>';

				if (!empty($notif->url)) {
					$node_title .= '<span class="aips-notif-action-cell"><a class="aips-notif-action" href="' . esc_url($notif->url) . '">' . esc_html__('View Post', 'ai-post-scheduler') . '</a></span>';
				} else {
					$node_title .= '<span class="aips-notif-action-cell"></span>';
				}

				$node_title .= '</span>'
					. '<button class="aips-mark-read" data-id="' . esc_attr($notif->id) . '" data-nonce="' . esc_attr(wp_create_nonce('aips_admin_bar_nonce')) . '" title="' . esc_attr__('Mark as read', 'ai-post-scheduler') . '">'
					. '<span class="dashicons dashicons-yes-alt"></span>'
					. '</button>';

				$level_class = '';
				if (!empty($notif->level) && in_array($notif->level, array('warning', 'error'), true)) {
					$level_class = ' aips-notif-level-' . $notif->level;
				}

				$wp_admin_bar->add_node(array(
					'id'     => 'aips-notif-' . absint($notif->id),
					'parent' => 'aips-toolbar-notifications',
					'title'  => $node_title,
					'href'   => false,
					'meta'   => array(
						'class'         => 'aips-toolbar-notification ab-empty-item' . $level_class,
						'data-notif-id' => absint($notif->id),
					),
				));
			}
		}
	}

	/**
	 * Build display-friendly event/context labels from a notification title.
	 *
	 * @param object $notification Notification row object.
	 * @return array<string, string>
	 */
	private function get_notification_title_parts($notification) {
		$title = isset($notification->title) ? wp_strip_all_tags((string) $notification->title) : '';
		$context = $this->get_notification_context_from_meta($notification);

		if ('' !== $title) {
			$event = trim($title);

			if ('' !== $event) {
				return array(
					'event'   => $event,
					'context' => $context,
				);
			}
		}

		$type_label = isset($notification->type) ? str_replace('_', ' ', (string) $notification->type) : '';

		return array(
			'event'   => '' !== $type_label ? ucwords($type_label) : __('Notification', 'ai-post-scheduler'),
			'context' => $context,
		);
	}

	/**
	 * Extract notification context from persisted meta JSON.
	 *
	 * @param object $notification Notification row object.
	 * @return string
	 */
	private function get_notification_context_from_meta($notification) {
		if (empty($notification->meta) || !is_string($notification->meta)) {
			return '';
		}

		$meta = json_decode($notification->meta, true);

		if (!is_array($meta) || empty($meta['notification_context'])) {
			return '';
		}

		return sanitize_text_field((string) $meta['notification_context']);
	}

	/**
	 * Format notification timestamp using the site timezone settings.
	 *
	 * @param object $notification Notification row object.
	 * @return string
	 */
	private function format_notification_timestamp($notification) {
		if (empty($notification->created_at) || !is_string($notification->created_at)) {
			return __('Unknown time', 'ai-post-scheduler');
		}

		$format = get_option('date_format') . ' ' . get_option('time_format');

		return get_date_from_gmt($notification->created_at, $format);
	}

	/**
	 * AJAX: Mark a single notification as read.
	 */
	public function ajax_mark_read() {
		check_ajax_referer('aips_admin_bar_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$id = isset($_POST['id']) ? absint($_POST['id']) : 0;
		if (!$id) {
			wp_send_json_error(array('message' => __('Invalid notification ID.', 'ai-post-scheduler')));
		}

		$updated = $this->repository->mark_as_read($id);

		if (!$updated) {
			wp_send_json_error(array('message' => __('Notification could not be updated or was already read.', 'ai-post-scheduler')));
		}
		wp_send_json_success(array(
			'unread_count' => $this->repository->count_unread(),
		));
	}

	/**
	 * AJAX: Mark all notifications as read.
	 */
	public function ajax_mark_all_read() {
		check_ajax_referer('aips_admin_bar_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$result       = $this->repository->mark_all_as_read();
		$unread_count = $this->repository->count_unread();

		// If the repository reported a failure and there are still unread notifications, return an error.
		if (false === $result && $unread_count > 0) {
			wp_send_json_error(
				array(
					'message'      => __('Failed to mark notifications as read. Please try again.', 'ai-post-scheduler'),
					'unread_count' => $unread_count,
				)
			);
		}

		wp_send_json_success(
			array(
				'unread_count' => $unread_count,
			)
		);
	}

}
