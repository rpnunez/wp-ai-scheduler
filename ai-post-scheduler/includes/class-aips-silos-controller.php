<?php
/**
 * Silos Controller
 *
 * AJAX endpoints for Content → Silos: overview, confirm a pillar, fix a
 * silo and re-detect clusters.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Silos_Controller
 */
class AIPS_Silos_Controller {

	/**
	 * @var AIPS_Silo_Service
	 */
	private $service;

	/**
	 * @param AIPS_Silo_Service|null $service Silo service.
	 */
	public function __construct(?AIPS_Silo_Service $service = null) {
		$container     = AIPS_Container::get_instance();
		$this->service = $service ?: ($container->has(AIPS_Silo_Service::class) ? $container->make(AIPS_Silo_Service::class) : new AIPS_Silo_Service());

		add_action('wp_ajax_aips_silos_overview', array($this, 'ajax_overview'));
		add_action('wp_ajax_aips_silos_confirm_pillar', array($this, 'ajax_confirm_pillar'));
		add_action('wp_ajax_aips_silos_fix', array($this, 'ajax_fix'));
		add_action('wp_ajax_aips_silos_refresh', array($this, 'ajax_refresh'));
	}

	/**
	 * Data for the initial render of templates/admin/silos.php.
	 *
	 * @return array{guide_enabled:bool, settings_url:string, review_url:string, report_url:string}
	 */
	public function get_view_data(): array {
		return array(
			'guide_enabled' => $this->service->is_guide_enabled(),
			'settings_url'  => admin_url('admin.php?page=aips-settings&tab=settings-linking'),
			'review_url'    => admin_url('admin.php?page=aips-automations&tab=internal-links&origin=inbound&status=pending'),
			'report_url'    => admin_url('admin.php?page=aips-generated-posts&tab=link-report'),
		);
	}

	/**
	 * AJAX: silos and clusters without a pillar.
	 *
	 * @return void
	 */
	public function ajax_overview() {
		$this->verify_request();

		AIPS_Ajax_Response::success($this->service->get_overview());
	}

	/**
	 * AJAX: confirm or change a cluster's pillar.
	 *
	 * @return void
	 */
	public function ajax_confirm_pillar() {
		$this->verify_request();

		$cluster_id = isset($_POST['cluster_id']) ? sanitize_key(wp_unslash($_POST['cluster_id'])) : '';
		$post_id    = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		$result = $this->service->confirm_pillar($cluster_id, $post_id);
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array_merge(
			array(
				/* translators: %s: post title */
				'message' => sprintf(__('"%s" is now the pillar of this silo.', 'ai-post-scheduler'), get_the_title($post_id)),
			),
			$this->service->get_overview()
		));
	}

	/**
	 * AJAX: fix a silo (member → pillar links).
	 *
	 * @return void
	 */
	public function ajax_fix() {
		$this->verify_request();

		$cluster_id = isset($_POST['cluster_id']) ? sanitize_key(wp_unslash($_POST['cluster_id'])) : '';
		$run        = $this->service->fix_silo($cluster_id);
		if (is_wp_error($run)) {
			AIPS_Ajax_Response::error($run->get_error_message(), $run->get_error_code());
		}

		$message = sprintf(
			/* translators: 1: links inserted, 2: suggestions sent to review, 3: articles checked */
			__('Checked %3$d articles without a link to the pillar: %1$d link(s) inserted, %2$d sent to review.', 'ai-post-scheduler'),
			(int) $run['applied'],
			(int) $run['review'],
			(int) $run['missing']
		);
		$unplaced = (int) $run['missing'] - (int) $run['applied'] - (int) $run['review'];
		if ($unplaced > 0) {
			/* translators: %d: number of articles */
			$message .= ' ' . sprintf(_n('%d article has no phrase that fits a link to the pillar; add one by hand.', '%d articles have no phrase that fits a link to the pillar; add them by hand.', $unplaced, 'ai-post-scheduler'), $unplaced);
		}
		if (!$run['apply']) {
			$message .= ' ' . __('Bulk Auto-Linking is off, so everything went to review.', 'ai-post-scheduler');
		}

		AIPS_Ajax_Response::success(array_merge(
			array(
				'message' => $message,
				'run'     => $run,
			),
			$this->service->get_overview()
		));
	}

	/**
	 * AJAX: re-detect Topic Clusters (needs embeddings).
	 *
	 * @return void
	 */
	public function ajax_refresh() {
		$this->verify_request();

		$container = AIPS_Container::get_instance();
		$evaluator = $container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator();
		$clusters  = $evaluator->detect_post_clusters();

		AIPS_Ajax_Response::success(array_merge(
			array(
				'message' => empty($clusters)
					? __('No clusters found. Clusters need post embeddings: index your posts in Content → Content Indexer first.', 'ai-post-scheduler')
					/* translators: %d: number of clusters */
					: sprintf(_n('%d cluster found.', '%d clusters found.', count($clusters), 'ai-post-scheduler'), count($clusters)),
			),
			$this->service->get_overview()
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
