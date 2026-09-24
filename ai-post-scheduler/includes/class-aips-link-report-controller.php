<?php
/**
 * Link Report Controller
 *
 * View data and AJAX endpoints for the Link Report (Content hub): per-post
 * inbound / outbound / external / broken link counts, true orphans, a
 * per-post drill-down, and the link index backfill job.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Report_Controller
 */
class AIPS_Link_Report_Controller {

	/**
	 * Rows per report page.
	 */
	const PER_PAGE = 20;

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

		add_action('wp_ajax_aips_link_report_get', array($this, 'ajax_get_report'));
		add_action('wp_ajax_aips_link_report_get_post_links', array($this, 'ajax_get_post_links'));
		add_action('wp_ajax_aips_link_report_start_backfill', array($this, 'ajax_start_backfill'));
		add_action('wp_ajax_aips_link_report_backfill_status', array($this, 'ajax_backfill_status'));
	}

	/**
	 * Data for the initial server render of templates/admin/link-report.php.
	 *
	 * @return array{summary:array, orphan_count:int, total_posts:int, post_types:array<string,string>, enabled:bool, backfill:array|null}
	 */
	public function get_view_data(): array {
		$post_types = array();
		foreach ($this->service->get_post_types() as $type) {
			$object             = get_post_type_object($type);
			$post_types[$type] = $object ? $object->labels->name : $type;
		}

		$scope = array('post_types' => array_keys($post_types));

		return array(
			'summary'      => $this->repository->get_summary(),
			'orphan_count' => $this->repository->get_report_count($scope + array('orphans_only' => true)),
			'total_posts'  => $this->repository->get_report_count($scope),
			'post_types'   => $post_types,
			'enabled'      => $this->service->is_enabled(),
			'backfill'     => $this->service->get_backfill_status(),
		);
	}

	/**
	 * AJAX: one page of the Link Report.
	 *
	 * @return void
	 */
	public function ajax_get_report() {
		$this->verify_request();

		$allowed_types = $this->service->get_post_types();
		$post_type     = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
		$post_types    = in_array($post_type, $allowed_types, true) ? array($post_type) : $allowed_types;

		$args = array(
			'post_types'   => $post_types,
			'orphans_only' => isset($_POST['orphans_only']) && filter_var(wp_unslash($_POST['orphans_only']), FILTER_VALIDATE_BOOLEAN),
			'search'       => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
			'orderby'      => isset($_POST['orderby']) ? sanitize_key(wp_unslash($_POST['orderby'])) : 'inbound',
			'order'        => isset($_POST['order']) ? sanitize_key(wp_unslash($_POST['order'])) : 'asc',
			'per_page'     => self::PER_PAGE,
			'page'         => isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1,
		);

		$total = $this->repository->get_report_count($args);
		$rows  = array();

		foreach ($this->repository->get_report_page($args) as $row) {
			$type_object = get_post_type_object($row->post_type);

			$rows[] = array(
				'id'         => (int) $row->ID,
				'title'      => $row->post_title !== '' ? $row->post_title : __('(no title)', 'ai-post-scheduler'),
				'post_type'  => $type_object ? $type_object->labels->singular_name : $row->post_type,
				'inbound'    => (int) $row->inbound,
				'outbound'   => (int) $row->outbound,
				'external'   => (int) $row->external,
				'broken'     => (int) $row->broken,
				'is_orphan'  => ((int) $row->inbound === 0),
				'edit_url'   => (string) get_edit_post_link((int) $row->ID, 'raw'),
				'view_url'   => (string) get_permalink((int) $row->ID),
			);
		}

		AIPS_Ajax_Response::success(array(
			'rows'        => $rows,
			'total'       => $total,
			'page'        => $args['page'],
			'total_pages' => max(1, (int) ceil($total / self::PER_PAGE)),
			'summary'     => $this->repository->get_summary(),
		));
	}

	/**
	 * AJAX: the links in a post (outbound) and the posts linking to it (inbound).
	 *
	 * @return void
	 */
	public function ajax_get_post_links() {
		$this->verify_request();

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$post    = $post_id ? get_post($post_id) : null;

		if (!$post) {
			AIPS_Ajax_Response::error(__('Post not found.', 'ai-post-scheduler'), 'not_found');
		}

		$outbound = array();
		foreach ($this->repository->get_outbound($post_id) as $link) {
			$target_id  = (int) $link->target_post_id;
			$outbound[] = array(
				'anchor'       => (string) $link->anchor_text,
				'url'          => (string) $link->target_url,
				'type'         => (string) $link->link_type,
				'is_broken'    => ($link->link_type === AIPS_Link_Index_Repository::TYPE_INTERNAL && $target_id === 0),
				'is_nofollow'  => !empty($link->is_nofollow),
				'target_title' => $target_id ? get_the_title($target_id) : '',
				'target_edit'  => $target_id ? (string) get_edit_post_link($target_id, 'raw') : '',
			);
		}

		$inbound = array();
		foreach ($this->repository->get_inbound($post_id) as $link) {
			$source_id = (int) $link->source_post_id;
			$inbound[] = array(
				'anchor'       => (string) $link->anchor_text,
				'source_title' => get_the_title($source_id),
				'source_edit'  => (string) get_edit_post_link($source_id, 'raw'),
			);
		}

		AIPS_Ajax_Response::success(array(
			'post_id'  => $post_id,
			'title'    => get_the_title($post_id),
			'outbound' => $outbound,
			'inbound'  => $inbound,
		));
	}

	/**
	 * AJAX: queue a full link index rebuild.
	 *
	 * @return void
	 */
	public function ajax_start_backfill() {
		$this->verify_request();

		if (!$this->service->is_enabled()) {
			AIPS_Ajax_Response::error(__('The link index is disabled in Settings.', 'ai-post-scheduler'), 'disabled');
		}

		$result = $this->service->start_backfill();

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array(
			'message'  => sprintf(
				/* translators: %d: number of posts queued */
				_n('Indexing %d post in the background.', 'Indexing %d posts in the background.', (int) $result['total'], 'ai-post-scheduler'),
				(int) $result['total']
			),
			'backfill' => $this->service->get_backfill_status(),
		));
	}

	/**
	 * AJAX: progress of the most recent backfill.
	 *
	 * @return void
	 */
	public function ajax_backfill_status() {
		$this->verify_request();

		AIPS_Ajax_Response::success(array(
			'backfill' => $this->service->get_backfill_status(),
			'summary'  => $this->repository->get_summary(),
		));
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
