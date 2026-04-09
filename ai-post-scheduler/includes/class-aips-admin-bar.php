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
	 * Constructor.
	 */
	public function __construct() {
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

		$cache        = AIPS_Cache_Factory::instance();
		$cache_key    = 'aips_unread_count_' . get_current_user_id();
		$unread_count = $cache->remember(
			$cache_key,
			MINUTE_IN_SECONDS,
			function() {
				return $this->repository->count_unread();
			},
			'aips_admin_bar'
		);

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
				'title' => '<span class="dashicons dashicons-media-document"></span> ' . esc_html__('Templates', 'ai-post-scheduler'),
				'href'  => AIPS_Admin_Menu_Helper::get_page_url('templates'),
			),
			array(
				'id'    => 'aips-toolbar-authors',
				'title' => '<span class="dashicons dashicons-admin-users"></span> ' . esc_html__('Authors', 'ai-post-scheduler'),
				'href'  => AIPS_Admin_Menu_Helper::get_page_url('authors'),
			),
			array(
				'id'    => 'aips-toolbar-schedules',
				'title' => '<span class="dashicons dashicons-calendar-alt"></span> ' . esc_html__('Schedules', 'ai-post-scheduler'),
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
		$notifications = ($unread_count > 0) ? AIPS_Notifications_Repository::instance()->get_unread(20) : array();

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
					. '<button class="aips-mark-all-read" data-nonce="' . esc_attr(wp_create_nonce('aips_admin_bar_nonce')) . '">'
					. esc_html__('Mark all as read', 'ai-post-scheduler')
					. '</button>',
				'href'   => false,
				'meta'   => array('class' => 'aips-toolbar-notif-header ab-empty-item'),
			));

			foreach ($notifications as $notif) {
				$title_markup = '';
				if (!empty($notif->title)) {
					$title_markup = '<span class="aips-notif-title">' . esc_html($notif->title) . '</span>';
				}

				$node_title = $title_markup . '<span class="aips-notif-message">';

				if (!empty($notif->url)) {
					$node_title .= '<a href="' . esc_url($notif->url) . '">' . esc_html($notif->message) . '</a>';
				} else {
					$node_title .= esc_html($notif->message);
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

		$updated = AIPS_Notifications_Repository::instance()->mark_as_read($id);

		if (!$updated) {
			wp_send_json_error(array('message' => __('Notification could not be updated or was already read.', 'ai-post-scheduler')));
		}

		$cache_key    = 'aips_unread_count_' . get_current_user_id();
		$unread_count = AIPS_Notifications_Repository::instance()->count_unread();
    
		AIPS_Cache_Factory::instance()->set($cache_key, $unread_count, MINUTE_IN_SECONDS, 'aips_admin_bar');

		wp_send_json_success(array(
			'unread_count' => $unread_count,
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

		$result       = AIPS_Notifications_Repository::instance()->mark_all_as_read();

		$cache_key    = 'aips_unread_count_' . get_current_user_id();
		$unread_count = AIPS_Notifications_Repository::instance()->count_unread();
    
		AIPS_Cache_Factory::instance()->set($cache_key, $unread_count, MINUTE_IN_SECONDS, 'aips_admin_bar');

		// If the repository reported a failure and there are still unread notifications, return an error.
		if (false === $result && $unread_count > 0) {
			wp_send_json_error(
				array(
					'message'      => __('Failed to mark notifications as read. Please try again.', 'ai-post-scheduler'),
					'unread_count' => $unread_count,
				)
			);
		}

		AIPS_Cache_Factory::instance()->set($cache_key, $unread_count, MINUTE_IN_SECONDS, 'aips_admin_bar');

		wp_send_json_success(
			array(
				'unread_count' => $unread_count,
			)
		);
	}

}
