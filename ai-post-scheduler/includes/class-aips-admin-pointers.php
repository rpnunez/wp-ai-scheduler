<?php
/**
 * Admin Pointers Class
 *
 * Implements native WordPress Core wp-pointer tours to guide new administrators
 * through configuring AI engines, creating prompt templates, and activating schedules.
 * Dismissal states are persisted natively in user meta via WordPress Core's dismiss-wp-pointer handler.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Pointers
 */
class AIPS_Admin_Pointers {

	/**
	 * Initialize admin pointer hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_pointers'));
	}

	/**
	 * Get definitions for all registered AIPS admin pointers.
	 *
	 * @return array<string, array{target: string, screens: string[], title: string, content: string, edge: string, align: string}>
	 */
	public static function get_registered_pointers() {
		return array(
			'aips_pointer_dashboard' => array(
				'screens' => array('toplevel_page_ai-post-scheduler', 'ai-post-scheduler_page_ai-post-scheduler'),
				'target'  => '#aips-date-filter-trigger',
				'title'   => __('Analytics Dashboard', 'ai-post-scheduler'),
				'content' => __('Welcome to AI Post Scheduler! Observe your post generation pipelines, success metrics, and upcoming schedules right here.', 'ai-post-scheduler'),
				'edge'    => 'top',
				'align'   => 'left',
			),
			'aips_pointer_studio' => array(
				'screens' => array('ai-post-scheduler_page_aips-studio'),
				'target'  => '.aips-add-template-btn',
				'title'   => __('Prompt Templates', 'ai-post-scheduler'),
				'content' => __('Create reusable templates defining prompt strategies, dynamic tokens like {topic}, formatting constraints, and author personas.', 'ai-post-scheduler'),
				'edge'    => 'left',
				'align'   => 'center',
			),
			'aips_pointer_automations' => array(
				'screens' => array('ai-post-scheduler_page_aips-automations'),
				'target'  => '#aips-add-schedule-btn',
				'title'   => __('Automated Scheduling', 'ai-post-scheduler'),
				'content' => __('Set recurring publication schedules (daily, weekly, custom cron) to autonomously generate and publish fresh content.', 'ai-post-scheduler'),
				'edge'    => 'top',
				'align'   => 'center',
			),
			'aips_pointer_settings' => array(
				'screens' => array('ai-post-scheduler_page_aips-settings'),
				'target'  => '#aips-settings-tabs',
				'title'   => __('AI Engine & Models', 'ai-post-scheduler'),
				'content' => __('Configure your AI connector (Meow Apps AI Engine or WordPress AI Client), default models, and token headroom budgets.', 'ai-post-scheduler'),
				'edge'    => 'top',
				'align'   => 'left',
			),
		);
	}

	/**
	 * Enqueue and render active pointers on the current admin screen.
	 *
	 * @param string $hook_suffix Admin page hook.
	 * @return void
	 */
	public static function enqueue_pointers($hook_suffix) {
		if (!current_user_can('manage_options')) {
			return;
		}

		$screen = get_current_screen();
		if (!$screen) {
			return;
		}

		// Check user's dismissed pointers in user meta
		$dismissed_raw = (string) get_user_meta(get_current_user_id(), 'dismissed_wp_pointers', true);
		$dismissed = array_filter(explode(',', $dismissed_raw));

		$registered = self::get_registered_pointers();
		$active = array();

		foreach ($registered as $pointer_id => $data) {
			if (in_array($pointer_id, $dismissed, true)) {
				continue;
			}

			$screens = isset($data['screens']) ? (array) $data['screens'] : array();
			if (!in_array($screen->id, $screens, true) && !in_array($hook_suffix, $screens, true)) {
				continue;
			}

			$active[$pointer_id] = array(
				'target'  => $data['target'],
				'title'   => $data['title'],
				'content' => $data['content'],
				'edge'    => $data['edge'],
				'align'   => $data['align'],
				'nonce'   => wp_create_nonce('dismiss-pointer_' . $pointer_id),
			);
		}

		if (empty($active)) {
			return;
		}

		// Enqueue native WordPress pointer assets
		wp_enqueue_style('wp-pointer');
		wp_enqueue_script('wp-pointer');

		// Inject pointer initialization script
		add_action('admin_footer', function() use ($active) {
			include AIPS_PLUGIN_DIR . 'templates/admin/partials/pointers/admin-pointers-script.php';
		}, 30);
	}
}
