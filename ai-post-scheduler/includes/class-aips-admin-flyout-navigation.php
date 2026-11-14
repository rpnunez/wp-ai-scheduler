<?php
/**
 * Admin flyout navigation payload builder.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Builds the normalized navigation model consumed by the shared admin flyout.
 */
class AIPS_Admin_Flyout_Navigation {

	/**
	 * Hubs that use flyout navigation, in WordPress submenu order.
	 *
	 * @var array<int, string>
	 */
	private const HUBS = array(
		AIPS_Admin_Page_Context::HUB_AUTOMATIONS,
		AIPS_Admin_Page_Context::HUB_STUDIO,
		AIPS_Admin_Page_Context::HUB_RESEARCH,
		AIPS_Admin_Page_Context::HUB_CONTENT,
		AIPS_Admin_Page_Context::HUB_DIAGNOSTICS,
	);

	/**
	 * Build the flyout payload for the current request.
	 *
	 * @param string|null $page_slug Optional page slug override.
	 * @param string|null $tab_key   Optional tab key override.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_payload($page_slug = null, $tab_key = null) {
		if (!current_user_can('manage_options')) {
			return array();
		}

		$context = AIPS_Admin_Page_Context::resolve($page_slug, $tab_key);
		$hubs    = AIPS_Admin_Page_Context::get_canonical_hubs();
		$payload = array();

		foreach (self::HUBS as $hub_key) {
			if (empty($hubs[$hub_key]['flyout_groups'])) {
				continue;
			}

			$hub    = $hubs[$hub_key];
			$groups = array();
			$active = self::get_active_section($hub_key, $context);

			foreach ($hub['flyout_groups'] as $group_label => $section_keys) {
				$items = array();
				foreach ($section_keys as $section_key) {
					if (empty($hub['sections'][$section_key]) || !self::is_available($hub_key, $section_key)) {
						continue;
					}

					$section = $hub['sections'][$section_key];
					$item    = array(
						'key'         => $section_key,
						'label'       => $section['label'],
						'icon'        => isset($section['icon']) ? $section['icon'] : 'dashicons-admin-generic',
						'description' => isset($section['short_description']) ? $section['short_description'] : (isset($section['description']) ? $section['description'] : ''),
						'url'         => add_query_arg(array('page' => $hub['slug'], 'tab' => $section_key), admin_url('admin.php')),
						'active'      => $active === $section_key,
					);

					if (AIPS_Admin_Page_Context::HUB_CONTENT === $hub_key && 'aips-pending-review' === $section_key) {
						$badge = self::get_pending_review_count();
						if ($badge > 0) {
							$item['badge'] = $badge;
						}
					}

					$items[] = $item;
				}

				if (!empty($items)) {
					$groups[] = array(
						'label' => $group_label,
						'items' => $items,
					);
				}
			}

			$payload[] = array(
				'key'           => $hub_key,
				'slug'          => $hub['slug'],
				'label'         => $hub['label'],
				'defaultUrl'    => add_query_arg(array('page' => $hub['slug'], 'tab' => $hub['default_tab']), admin_url('admin.php')),
				'activeSection' => $active,
				'groups'        => $groups,
			);
		}

		return $payload;
	}

	/**
	 * Resolve the active public flyout item for a hub.
	 *
	 * @param string                  $hub_key Hub key.
	 * @param AIPS_Admin_Page_Context $context Current page context.
	 * @return string
	 */
	private static function get_active_section($hub_key, $context) {
		if ($context->hub_key !== $hub_key) {
			return '';
		}

		$section = $context->section_key;
		if (AIPS_Admin_Page_Context::HUB_AUTOMATIONS === $hub_key) {
			if ('author-topics' === $section) {
				return 'authors';
			}
			if ('source-data' === $section) {
				return 'sources';
			}
		}

		return $section;
	}

	/**
	 * Check runtime availability for feature-gated destinations.
	 *
	 * @param string $hub_key     Hub key.
	 * @param string $section_key Section key.
	 * @return bool
	 */
	private static function is_available($hub_key, $section_key) {
		if (AIPS_Admin_Page_Context::HUB_DIAGNOSTICS !== $hub_key) {
			return true;
		}

		return AIPS_Diagnostics_Controller::is_tab_available($section_key);
	}

	/**
	 * Read the pending-review badge without allowing a count failure to break navigation.
	 *
	 * @return int
	 */
	private static function get_pending_review_count() {
		try {
			if (!class_exists('AIPS_Post_Review_Repository')) {
				return 0;
			}
			return max(0, (int) (new AIPS_Post_Review_Repository())->get_draft_count());
		} catch (Throwable $e) {
			return 0;
		}
	}
}
