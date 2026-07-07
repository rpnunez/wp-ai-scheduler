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
	 * Maximum notifications rendered in the flyout at once.
	 */
	private const NOTIFICATION_LIMIT = 20;

	/**
	 * Lazily-resolved notifications repository.
	 * Null until the first rendering hook actually needs it.
	 *
	 * @var AIPS_Notifications_Repository_Interface|null
	 */
	private $repository = null;

	/**
	 * Constructor.
	 *
	 * The notifications repository is NOT resolved here so that it is never
	 * instantiated for non-admin users or non-admin-bar contexts.
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
			'contextLabel'    => __('Context', 'ai-post-scheduler'),
			'markReadError'   => __('Could not mark notification as read.', 'ai-post-scheduler'),
			'markAllReadError' => __('Could not mark all notifications as read.', 'ai-post-scheduler'),
			'unreadSummaryLabel' => __('unread', 'ai-post-scheduler'),
			'latestSummaryTemplate' => __('Latest %1$d of %2$d unread', 'ai-post-scheduler'),
			'showingSummaryTemplate' => __('Showing %1$d of %2$d unread', 'ai-post-scheduler'),
		));
	}

	/**
	 * Lazily resolve and return the notifications repository.
	 *
	 * The singleton is only fetched on the first call, which happens inside a
	 * rendering or AJAX hook — never in the constructor.
	 *
	 * @return AIPS_Notifications_Repository_Interface
	 */
	private function get_repository(): AIPS_Notifications_Repository_Interface {
		if ($this->repository === null) {
			$this->repository = AIPS_Notifications_Repository::instance();
		}
		return $this->repository;
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
		$cache_key    = AIPS_Cache_Policy::key( AIPS_Cache_Policy::SUBSYSTEM_ADMIN_BAR, 'unread_count', array('user_id' => get_current_user_id()) );
		$unread_count = $cache->remember(
			$cache_key,
			AIPS_Cache_Policy::default_ttl( AIPS_Cache_Policy::SUBSYSTEM_ADMIN_BAR ),
			function() {
				return $this->get_repository()->count_unread();
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
				'id'    => 'aips-toolbar-dashboard',
				'title' => $this->build_quick_link_title('dashicons-chart-bar', __('Dashboard', 'ai-post-scheduler')),
				'href'  => AIPS_Admin_Menu_Helper::get_page_url('dashboard'),
			),
			array(
				'id'    => 'aips-toolbar-automations',
				'title' => $this->build_quick_link_title('dashicons-controls-repeat', __('Automations', 'ai-post-scheduler')),
				'href'  => AIPS_Admin_Menu_Helper::get_page_url('automations'),
			),
			array(
				'id'    => 'aips-toolbar-history',
				'title' => $this->build_quick_link_title('dashicons-backup', __('History', 'ai-post-scheduler')),
				'href'  => AIPS_Admin_Menu_Helper::get_page_url('history'),
			),
			array(
				'id'    => 'aips-toolbar-templates',
				'title' => $this->build_quick_link_title('dashicons-media-document', __('Templates', 'ai-post-scheduler')),
				'href'  => AIPS_Admin_Menu_Helper::get_page_url('templates'),
			),
			array(
				'id'    => 'aips-toolbar-authors',
				'title' => $this->build_quick_link_title('dashicons-admin-users', __('Authors', 'ai-post-scheduler')),
				'href'  => AIPS_Admin_Menu_Helper::get_page_url('authors'),
			),
			array(
				'id'    => 'aips-toolbar-schedules',
				'title' => $this->build_quick_link_title('dashicons-calendar-alt', __('Schedules', 'ai-post-scheduler')),
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
		$notifications = ($unread_count > 0) ? $this->get_repository()->get_unread(self::NOTIFICATION_LIMIT) : array();

		$wp_admin_bar->add_group(array(
			'id'     => 'aips-toolbar-notifications',
			'parent' => 'aips-toolbar',
			'meta'   => array('class' => 'aips-toolbar-group-notifications'),
		));

		if (empty($notifications)) {
			// "No notifications" placeholder
			$wp_admin_bar->add_node(array(
				'id'     => 'aips-toolbar-no-notifications',
				'parent' => 'aips-toolbar-notifications',
				'title'  => '<span class="aips-toolbar-empty">' . esc_html__('No notifications', 'ai-post-scheduler') . '</span>',
				'href'   => false,
				'meta'   => array('class' => 'aips-toolbar-no-notifications ab-empty-item'),
			));
		} else {
			// Header row with "Mark all as read"
			$wp_admin_bar->add_node(array(
				'id'     => 'aips-toolbar-notifications-header',
				'parent' => 'aips-toolbar-notifications',
				'title'  => $this->build_notifications_header_title($unread_count, count($notifications)),
				'href'   => false,
				'meta'   => array('class' => 'aips-toolbar-notif-header ab-empty-item'),
			));

			foreach ($notifications as $notif) {
				$level_class = '';
				if (!empty($notif->level) && in_array($notif->level, array('warning', 'error'), true)) {
					$level_class = ' aips-notif-level-' . $notif->level;
				}

				$wp_admin_bar->add_node(array(
					'id'     => 'aips-notif-' . absint($notif->id),
					'parent' => 'aips-toolbar-notifications',
					'title'  => $this->build_notification_row_title($notif),
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
		if ( ! check_ajax_referer('aips_admin_bar_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$id = isset($_POST['id']) ? absint($_POST['id']) : 0;
		if (!$id) {
			AIPS_Ajax_Response::invalid_request(__('Invalid notification ID.', 'ai-post-scheduler'));
		}

		$updated = $this->get_repository()->mark_as_read($id);

		if (!$updated) {
			AIPS_Ajax_Response::error(__('Notification could not be updated or was already read.', 'ai-post-scheduler'));
		}

		$cache_key    = AIPS_Cache_Policy::key( AIPS_Cache_Policy::SUBSYSTEM_ADMIN_BAR, 'unread_count', array('user_id' => get_current_user_id()) );
		$unread_count = $this->get_repository()->count_unread();

		AIPS_Cache_Factory::instance()->set($cache_key, $unread_count, AIPS_Cache_Policy::default_ttl( AIPS_Cache_Policy::SUBSYSTEM_ADMIN_BAR ), 'aips_admin_bar');

		AIPS_Ajax_Response::success(array(
			'unread_count' => $unread_count,
		));
	}

	/**
	 * AJAX: Mark all notifications as read.
	 */
	public function ajax_mark_all_read() {
		if ( ! check_ajax_referer('aips_admin_bar_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$result       = $this->get_repository()->mark_all_as_read();

		$cache_key    = AIPS_Cache_Policy::key( AIPS_Cache_Policy::SUBSYSTEM_ADMIN_BAR, 'unread_count', array('user_id' => get_current_user_id()) );
		$unread_count = $this->get_repository()->count_unread();

		AIPS_Cache_Factory::instance()->set($cache_key, $unread_count, AIPS_Cache_Policy::default_ttl( AIPS_Cache_Policy::SUBSYSTEM_ADMIN_BAR ), 'aips_admin_bar');

		// If the repository reported a failure and there are still unread notifications, return an error.
		if (false === $result && $unread_count > 0) {
			AIPS_Ajax_Response::error(
				__('Failed to mark notifications as read. Please try again.', 'ai-post-scheduler'),
				'mark_all_read_failed',
				200,
				array('unread_count' => $unread_count)
			);
		}

		AIPS_Ajax_Response::success(array(
			'unread_count' => $unread_count,
		));
	}

	/**
	 * Build the formatted quick-link label.
	 *
	 * @param string $icon Dashicon class suffix.
	 * @param string $label Human-readable label.
	 * @return string
	 */
	private function build_quick_link_title($icon, $label) {
		return '<span class="aips-toolbar-link-inner">'
			. '<span class="aips-toolbar-link-icon dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span>'
			. '<span class="aips-toolbar-link-label">' . esc_html($label) . '</span>'
			. '</span>';
	}

	/**
	 * Build the notifications header row HTML.
	 *
	 * @param int $unread_count Total unread notifications for the current user.
	 * @param int $displayed_count Number of notifications rendered in the flyout.
	 * @return string
	 */
	private function build_notifications_header_title($unread_count, $displayed_count) {
		$summary = sprintf(
			_n('%d unread', '%d unread', $unread_count, 'ai-post-scheduler'),
			$unread_count
		);

		if ($unread_count > $displayed_count) {
			$summary = sprintf(
				__('Latest %1$d of %2$d unread', 'ai-post-scheduler'),
				$displayed_count,
				$unread_count
			);
		}

		return '<div class="aips-toolbar-notif-header-inner">'
			. '<div class="aips-toolbar-notif-header-copy">'
			. '<span class="aips-toolbar-notif-heading">' . esc_html__('Notifications', 'ai-post-scheduler') . '</span>'
			. '<span class="aips-toolbar-notif-summary">' . esc_html($summary) . '</span>'
			. '</div>'
			. '<button class="aips-mark-all-read" data-nonce="' . esc_attr(wp_create_nonce('aips_admin_bar_nonce')) . '">'
			. esc_html__('Mark all as read', 'ai-post-scheduler')
			. '</button>'
			. '</div>';
	}

	/**
	 * Build one notification flyout row.
	 *
	 * @param object $notif Notification row.
	 * @return string
	 */
	private function build_notification_row_title($notif) {
		$title_markup = '';
		if (!empty($notif->title)) {
			$title_markup = '<span class="aips-notif-title">' . esc_html($notif->title) . '</span>';
		}

		$context_markup = '<span class="aips-notif-context aips-notif-context-disabled">'
			. esc_html__('Context', 'ai-post-scheduler')
			. '</span>';

		if (!empty($notif->url)) {
			$context_markup = '<a class="aips-notif-context" href="' . esc_url($notif->url) . '">'
				. esc_html__('Context', 'ai-post-scheduler')
				. '</a>';
		}

		return '<div class="aips-notif-grid">'
			. '<div class="aips-notif-body">'
			. $title_markup
			. '<span class="aips-notif-message">' . esc_html($notif->message) . '</span>'
			. '</div>'
			. '<div class="aips-notif-actions">'
			. $context_markup
			. '<button class="aips-mark-read" data-id="' . esc_attr($notif->id) . '" data-nonce="' . esc_attr(wp_create_nonce('aips_admin_bar_nonce')) . '" title="' . esc_attr__('Mark as read', 'ai-post-scheduler') . '">'
			. '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>'
			. '<span class="screen-reader-text">' . esc_html__('Mark as read', 'ai-post-scheduler') . '</span>'
			. '</button>'
			. '</div>'
			. '</div>';
	}

}
