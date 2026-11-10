<?php
/**
 * Link Report Query Handler
 *
 * Handles AJAX query / read endpoints for Link Report.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Report_Query_Handler
 */
class AIPS_Link_Report_Query_Handler {

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
	 * @var AIPS_Inbound_Links_Service|null
	 */
	private $inbound;

	public function __construct(?AIPS_Link_Index_Service $service = null, ?AIPS_Inbound_Links_Service $inbound = null) {
		$this->inbound = $inbound;
		$container     = AIPS_Container::get_instance();
		$this->service = $service ?: ($container->has(AIPS_Link_Index_Service::class) ? $container->make(AIPS_Link_Index_Service::class) : new AIPS_Link_Index_Service());
		$this->repository = $this->service->get_repository();
	}

	public function get_report() {
		AIPS_Ajax_Guard::check_admin();

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

		$total   = $this->repository->get_report_count($args);
		$rows    = array();
		$page    = $this->repository->get_report_page($args);
		$pending = (new AIPS_Internal_Links_Repository())->count_pending_by_targets(wp_list_pluck($page, 'ID'), AIPS_Inbound_Links_Service::ORIGIN);
		$tracking = $this->get_click_tracking();
		$clicks   = $tracking->is_enabled() ? $tracking->get_inbound_clicks(wp_list_pluck($page, 'ID')) : array();

		foreach ($page as $row) {
			$type_object = get_post_type_object($row->post_type);

			$rows[] = array(
				'id'         => (int) $row->ID,
				'title'      => $row->post_title !== '' ? $row->post_title : __('(no title)', 'ai-post-scheduler'),
				'post_type'  => $type_object ? $type_object->labels->singular_name : $row->post_type,
				'inbound'    => (int) $row->inbound,
				'outbound'   => (int) $row->outbound,
				'external'   => (int) $row->external,
				'broken'     => (int) $row->broken,
				'clicks'     => isset($clicks[(int) $row->ID]) ? $clicks[(int) $row->ID] : 0,
				'is_orphan'  => ((int) $row->inbound === 0),
				'suggestions' => isset($pending[(int) $row->ID]) ? $pending[(int) $row->ID] : 0,
				'can_suggest' => AIPS_Inbound_Links_Service::should_suggest((int) $row->inbound),
				'edit_url'   => (string) get_edit_post_link((int) $row->ID, 'raw'),
				'view_url'   => (string) get_permalink((int) $row->ID),
			);
		}

		AIPS_Ajax_Response::success(array(
			'rows'        => $rows,
			'total'       => $total,
			'page'        => $args['page'],
			'total_pages' => max(1, (int) ceil($total / self::PER_PAGE)),
			'summary'     => $this->get_totals(),
		));
	}

	public function get_post_links() {
		AIPS_Ajax_Guard::check_admin();

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$post    = $post_id ? get_post($post_id) : null;

		if (!$post) {
			AIPS_Ajax_Response::error(__('Post not found.', 'ai-post-scheduler'), 'not_found');
		}

		$tracking      = $this->get_click_tracking();
		$clicks_out    = $tracking->is_enabled() ? $tracking->get_clicks_by_target($post_id) : array();
		$clicks_in     = $tracking->is_enabled() ? $tracking->get_clicks_by_source($post_id) : array();

		$outbound_links = $this->repository->get_outbound($post_id);
		$inbound_links  = $this->repository->get_inbound($post_id);

		$prefetch_ids = array();
		foreach ($outbound_links as $link) {
			if (!empty($link->target_post_id)) {
				$prefetch_ids[] = (int) $link->target_post_id;
			}
		}
		foreach ($inbound_links as $link) {
			if (!empty($link->source_post_id)) {
				$prefetch_ids[] = (int) $link->source_post_id;
			}
		}

		if (!empty($prefetch_ids) && function_exists('_prime_post_caches')) {
			_prime_post_caches(array_unique($prefetch_ids), false, true);
		}

		$outbound = array();
		foreach ($outbound_links as $link) {
			$target_id  = (int) $link->target_post_id;
			$outbound[] = array(
				'anchor'       => (string) $link->anchor_text,
				'url'          => (string) $link->target_url,
				'type'         => (string) $link->link_type,
				'is_broken'    => ($link->link_type === AIPS_Link_Index_Repository::TYPE_INTERNAL && $target_id === 0),
				'is_nofollow'  => !empty($link->is_nofollow),
				'target_title' => $target_id ? get_the_title($target_id) : '',
				'target_edit'  => $target_id ? (string) get_edit_post_link($target_id, 'raw') : '',
				'clicks'       => ($target_id && isset($clicks_out[$target_id])) ? $clicks_out[$target_id] : 0,
			);
		}

		$inbound = array();
		foreach ($inbound_links as $link) {
			$source_id = (int) $link->source_post_id;
			$inbound[] = array(
				'anchor'       => (string) $link->anchor_text,
				'source_title' => get_the_title($source_id),
				'source_edit'  => (string) get_edit_post_link($source_id, 'raw'),
				'clicks'       => isset($clicks_in[$source_id]) ? $clicks_in[$source_id] : 0,
			);
		}

		AIPS_Ajax_Response::success(array(
			'post_id'  => $post_id,
			'title'    => get_the_title($post_id),
			'outbound' => $outbound,
			'inbound'  => $inbound,
		));
	}

	public function get_suggestions() {
		AIPS_Ajax_Guard::check_admin();

		$target_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		if (!$target_id || !get_post($target_id)) {
			AIPS_Ajax_Response::error(__('Post not found.', 'ai-post-scheduler'), 'not_found');
		}

		AIPS_Ajax_Response::success(array(
			'post_id'     => $target_id,
			'title'       => get_the_title($target_id),
			'suggestions' => $this->get_inbound()->get_suggestions($target_id),
		));
	}

	public function get_post_panel() {
		AIPS_Ajax_Guard::check_admin();

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$post    = $post_id ? get_post($post_id) : null;
		if (!$post) {
			AIPS_Ajax_Response::error(__('Post not found.', 'ai-post-scheduler'), 'not_found');
		}

		AIPS_Ajax_Response::success($this->get_post_panel_data($post));
	}

	public function backfill_status() {
		AIPS_Ajax_Guard::check_admin();

		$this->service->ensure_scan_scheduled();

		AIPS_Ajax_Response::success(array(
			'backfill' => $this->service->get_backfill_status(),
			'summary'  => $this->get_totals(),
		));
	}

	public function autolink_status() {
		AIPS_Ajax_Guard::check_admin();
		$this->get_runs()->ensure_scheduled();

		AIPS_Ajax_Response::success(array(
			'autolink' => $this->get_autolink_payload(),
			'summary'  => $this->get_totals(),
		));
	}

	public function get_view_data(): array {
		$post_types = array();
		foreach ($this->service->get_post_types() as $type) {
			$object             = get_post_type_object($type);
			$post_types[$type] = $object ? $object->labels->name : $type;
		}

		$scope = array('post_types' => array_keys($post_types));

		return array(
			'summary'      => $this->repository->get_summary(),
			'is_built'     => $this->service->is_built(),
			'orphan_count' => $this->repository->get_report_count($scope + array('orphans_only' => true)),
			'total_posts'  => $this->repository->get_report_count($scope),
			'post_types'   => $post_types,
			'enabled'      => $this->service->is_enabled(),
			'backfill'     => $this->service->get_backfill_status(),
			'autolink'     => $this->get_autolink_payload(),
			'clicks'       => $this->get_clicks_payload(),
		);
	}

	public function get_post_panel_data(WP_Post $post): array {
		$post_id  = (int) $post->ID;
		$in_scope = $this->service->is_post_in_scope($post);
		$indexed  = $in_scope && get_post_meta($post_id, AIPS_Link_Index_Service::HASH_META_KEY, true) !== '';
		$counts   = $this->repository->get_counts_for_posts(array($post_id));
		$counts   = isset($counts[$post_id]) ? $counts[$post_id] : array('inbound' => 0, 'outbound' => 0, 'external' => 0, 'broken' => 0);

		$sources = array();
		$seen    = array();
		foreach ($this->repository->get_inbound($post_id) as $link) {
			$source_id = (int) $link->source_post_id;
			if (isset($seen[$source_id])) {
				continue;
			}
			$seen[$source_id] = true;
			$sources[]        = array(
				'title'  => get_the_title($source_id),
				'edit'   => (string) get_edit_post_link($source_id, 'raw'),
				'anchor' => (string) $link->anchor_text,
			);
		}

		return array(
			'post_id'     => $post_id,
			'in_scope'    => $in_scope,
			'indexed'     => $indexed,
			'counts'      => $counts,
			'is_orphan'   => $indexed && $counts['inbound'] === 0,
			'can_suggest' => $in_scope && AIPS_Inbound_Links_Service::should_suggest($counts['inbound']),
			'sources'     => array_slice($sources, 0, 20),
			'suggestions' => $in_scope ? $this->get_inbound()->get_suggestions($post_id) : array(),
			'report_url'  => admin_url('admin.php?page=aips-generated-posts&tab=link-report'),
		);
	}

	public function get_clicks_payload(): array {
		$tracking = $this->get_click_tracking();

		if (!$tracking->is_enabled()) {
			return array('enabled' => false, 'days' => $tracking->get_report_days(), 'total' => 0, 'top' => array());
		}

		return array(
			'enabled' => true,
			'days'    => $tracking->get_report_days(),
			'total'   => $tracking->get_total(),
			'top'     => $tracking->get_top_links(10),
		);
	}

	public function get_click_tracking(): AIPS_Link_Click_Tracking_Service {
		$container = AIPS_Container::get_instance();
		return $container->has(AIPS_Link_Click_Tracking_Service::class)
			? $container->make(AIPS_Link_Click_Tracking_Service::class)
			: new AIPS_Link_Click_Tracking_Service();
	}

	public function get_totals(): array {
		$scope = array('post_types' => $this->service->get_post_types());

		return array_merge(
			$this->repository->get_summary(),
			array(
				'orphans' => $this->repository->get_report_count($scope + array('orphans_only' => true)),
				'posts'   => $this->repository->get_report_count($scope),
				'built'   => $this->service->is_built(),
			)
		);
	}

	public function get_autolink_payload(): array {
		$runs = $this->get_runs();

		return array(
			'enabled'        => (new AIPS_Autolink_Policy())->is_enabled(),
			'current'        => $runs->get_current(),
			'history'        => $runs->get_history(),
			'pending_review' => (new AIPS_Internal_Links_Repository())->count_pending_inbound(),
			'review_url'     => admin_url('admin.php?page=aips-automations&tab=internal-links&origin=inbound&status=pending'),
		);
	}

	public function get_runs(): AIPS_Autolink_Run_Service {
		$container = AIPS_Container::get_instance();
		return $container->has(AIPS_Autolink_Run_Service::class)
			? $container->make(AIPS_Autolink_Run_Service::class)
			: new AIPS_Autolink_Run_Service($this->get_inbound(), $this->service);
	}

	public function get_inbound(): AIPS_Inbound_Links_Service {
		if ($this->inbound === null) {
			$container     = AIPS_Container::get_instance();
			$this->inbound = $container->has(AIPS_Inbound_Links_Service::class)
				? $container->make(AIPS_Inbound_Links_Service::class)
				: new AIPS_Inbound_Links_Service(null, $this->service);
		}
		return $this->inbound;
	}
}
