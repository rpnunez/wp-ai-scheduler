<?php
/**
 * Broken Links Controller
 *
 * View data and AJAX endpoints for the Broken Links tab (Content hub):
 * internal links pointing at pages that no longer exist, with suggested
 * replacements, manual re-pointing/removal, and undo.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Broken_Links_Controller
 */
class AIPS_Broken_Links_Controller {

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $service;

	/**
	 * @var AIPS_Link_Index_Repository
	 */
	private $repository;

	/**
	 * @param AIPS_Link_Index_Service|null $service Link index service.
	 */
	public function __construct(?AIPS_Link_Index_Service $service = null) {
		$container        = AIPS_Container::get_instance();
		$this->service    = $service ?: ($container->has(AIPS_Link_Index_Service::class) ? $container->make(AIPS_Link_Index_Service::class) : new AIPS_Link_Index_Service());
		$this->repository = $this->service->get_repository();

		add_action('wp_ajax_aips_broken_links_get', array($this, 'ajax_get_broken'));
		add_action('wp_ajax_aips_broken_links_fix', array($this, 'ajax_fix_broken'));
		add_action('wp_ajax_aips_broken_links_undo_fix', array($this, 'ajax_undo_broken_fix'));
		add_action('wp_ajax_aips_broken_links_search_posts', array($this, 'ajax_search_posts'));
	}

	/**
	 * Data for the initial server render of templates/admin/broken-links.php.
	 *
	 * @return array{broken_count:int}
	 */
	public function get_view_data(): array {
		$summary = $this->repository->get_summary();

		return array(
			'broken_count' => (int) $summary['broken'],
		);
	}

	/**
	 * AJAX: one page of broken internal links with replacement suggestions.
	 *
	 * @return void
	 */
	public function ajax_get_broken() {
		$this->verify_request();

		$page   = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
		$search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
		$broken = $this->get_broken_service();

		AIPS_Ajax_Response::success(array_merge(
			$broken->get_page($page, $search),
			array('fixes' => $broken->get_recent_fixes())
		));
	}

	/**
	 * AJAX: re-point or remove a broken link.
	 *
	 * @return void
	 */
	public function ajax_fix_broken() {
		$this->verify_request();

		$source_id = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
		$url       = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
		$target_id = isset($_POST['target_id']) ? absint($_POST['target_id']) : 0;
		$mode      = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'repoint';

		if (!$source_id || $url === '') {
			AIPS_Ajax_Response::error(__('Missing post or link.', 'ai-post-scheduler'), 'invalid_request');
		}

		$broken = $this->get_broken_service();
		$result = ($mode === 'unlink') ? $broken->unlink($source_id, $url) : $broken->repoint($source_id, $url, $target_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array(
			'message' => ($mode === 'unlink')
				? __('Link removed; its text was kept.', 'ai-post-scheduler')
				: __('Link re-pointed to the selected post.', 'ai-post-scheduler'),
			'fix_id'  => $result['fix_id'],
			'summary' => $this->repository->get_summary(),
		));
	}

	/**
	 * AJAX: undo a broken-link fix.
	 *
	 * @return void
	 */
	public function ajax_undo_broken_fix() {
		$this->verify_request();

		$fix_id = isset($_POST['fix_id']) ? sanitize_text_field(wp_unslash($_POST['fix_id'])) : '';
		$result = $this->get_broken_service()->undo($fix_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array(
			'message' => __('Fix undone; the original link is back.', 'ai-post-scheduler'),
			'summary' => $this->repository->get_summary(),
		));
	}

	/**
	 * AJAX: find published posts by title for a manual replacement target.
	 *
	 * @return void
	 */
	public function ajax_search_posts() {
		$this->verify_request();

		$term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
		if (mb_strlen($term) < 2) {
			AIPS_Ajax_Response::success(array('posts' => array()));
		}

		$ids = get_posts(array(
			's'              => $term,
			'post_type'      => $this->service->get_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		));

		$posts = array();
		foreach ((array) $ids as $id) {
			$posts[] = array(
				'id'    => (int) $id,
				'title' => get_the_title($id),
				'url'   => (string) get_permalink($id),
			);
		}

		AIPS_Ajax_Response::success(array('posts' => $posts));
	}

	/**
	 * @return AIPS_Broken_Links_Service
	 */
	private function get_broken_service(): AIPS_Broken_Links_Service {
		return new AIPS_Broken_Links_Service($this->service);
	}

	/**
	 * Nonce and capability gate shared by every endpoint.
	 *
	 * @return void
	 */
	private function verify_request() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Security check failed. Please refresh the page.', 'ai-post-scheduler'), 'invalid_nonce');
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}
}
