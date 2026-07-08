<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Monitor_Controller
 *
 * Handles the Cache Monitor admin page and all AJAX endpoints.
 *
 * Registered AJAX actions (all in AIPS_Ajax_Registry):
 *   aips_cache_monitor_summary
 *   aips_cache_monitor_entries
 *   aips_cache_monitor_inspect
 *   aips_cache_monitor_delete_entry
 *   aips_cache_monitor_flush_group
 *   aips_cache_monitor_invalidate_tag
 *   aips_cache_monitor_invalidate_domain
 *   aips_cache_monitor_operations
 *   aips_cache_monitor_events
 *   aips_cache_monitor_maintenance
 *   aips_cache_monitor_flush_all
 *   aips_cache_monitor_flush_expired
 *   aips_cache_monitor_delete_bulk
 *
 * @package AI_Post_Scheduler
 * @since   2.9.0
 */
class AIPS_Cache_Monitor_Controller {

	/**
	 * Admin page slug.
	 */
	const PAGE_SLUG = 'aips-cache-monitor';

	/**
	 * @var AIPS_Cache_Monitor_Service
	 */
	private $service;

	/**
	 * Resolve dependencies and register AJAX hooks.
	 */
	public function __construct() {
		if (!self::is_enabled()) {
			return;
		}
		$repository  = new AIPS_Cache_Monitor_Repository();
		$cache_index = new AIPS_Cache_Index();
		$this->service = new AIPS_Cache_Monitor_Service( $repository, $cache_index );

		add_action('wp_ajax_aips_cache_monitor_summary',          array($this, 'ajax_summary'));
		add_action('wp_ajax_aips_cache_monitor_entries',          array($this, 'ajax_entries'));
		add_action('wp_ajax_aips_cache_monitor_inspect',          array($this, 'ajax_inspect'));
		add_action('wp_ajax_aips_cache_monitor_delete_entry',     array($this, 'ajax_delete_entry'));
		add_action('wp_ajax_aips_cache_monitor_delete_bulk',      array($this, 'ajax_delete_bulk'));
		add_action('wp_ajax_aips_cache_monitor_flush_group',      array($this, 'ajax_flush_group'));
		add_action('wp_ajax_aips_cache_monitor_flush_expired',    array($this, 'ajax_flush_expired'));
		add_action('wp_ajax_aips_cache_monitor_flush_all',        array($this, 'ajax_flush_all'));
		add_action('wp_ajax_aips_cache_monitor_invalidate_tag',   array($this, 'ajax_invalidate_tag'));
		add_action('wp_ajax_aips_cache_monitor_invalidate_domain', array($this, 'ajax_invalidate_domain'));
		add_action('wp_ajax_aips_cache_monitor_operations',       array($this, 'ajax_operations'));
		add_action('wp_ajax_aips_cache_monitor_events',           array($this, 'ajax_events'));
		add_action('wp_ajax_aips_cache_monitor_maintenance',      array($this, 'ajax_maintenance'));
	}

	/**
	 * Whether the Cache Monitor feature is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$value = AIPS_Config::get_instance()->get_option( 'aips_cache_monitor_enabled' );
		return ($value !== '0' && $value !== 0 && $value !== false && $value !== null && $value !== '');
	}

	// -----------------------------------------------------------------------
	// Admin page render
	// -----------------------------------------------------------------------

	/**
	 * Render the Cache Monitor admin page.
	 *
	 * @return void
	 */
	public function render_page( bool $embedded = false ): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		if (!self::is_enabled() || !$this->service) {
			echo '<div class="notice notice-info"><p>' .
				esc_html__( 'The Cache Monitor is disabled. Enable it under Settings → Performance.', 'ai-post-scheduler' ) .
				'</p></div>';
			return;
		}

		$service      = $this->service;
		$summary      = $service->get_summary();
		$nonce        = wp_create_nonce('aips_cache_monitor');
		$dev_mode     = (bool) AIPS_Config::get_instance()->get_option('aips_developer_mode', false);
		$monitor_enabled = (bool) AIPS_Config::get_instance()->get_option('aips_cache_monitor_enabled', true);
		$tab_query_key = $embedded ? 'cache_tab' : 'tab';
		$active_tab   = isset($_GET[ $tab_query_key ]) ? sanitize_key(wp_unslash($_GET[ $tab_query_key ])) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tags_data    = 'tags' === $active_tab ? $service->list_tags() : array();
		$domains_data = 'domains' === $active_tab ? $service->list_domains() : array();

		include AIPS_PLUGIN_DIR . 'templates/admin/cache-monitor.php';
	}

	// -----------------------------------------------------------------------
	// AJAX: read-only
	// -----------------------------------------------------------------------

	/**
	 * AJAX: return overview summary.
	 *
	 * @return void
	 */
	public function ajax_summary(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor');
		AIPS_Ajax_Response::success($this->service->get_summary());
	}

	/**
	 * AJAX: return paginated entries from the index.
	 *
	 * @return void
	 */
	public function ajax_entries(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor');

		$filters  = $this->extract_filters();
		$orderby  = isset($_POST['orderby']) ? sanitize_key(wp_unslash($_POST['orderby'])) : 'updated_at'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order    = isset($_POST['order']) && strtoupper(sanitize_text_field(wp_unslash($_POST['order']))) === 'ASC' ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 50; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$page     = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		AIPS_Ajax_Response::success($this->service->get_entries($filters, $orderby, $order, $per_page, $page));
	}

	/**
	 * AJAX: inspect a single entry.
	 *
	 * @return void
	 */
	public function ajax_inspect(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor');

		$key_hash = isset($_POST['key_hash']) ? sanitize_text_field(wp_unslash($_POST['key_hash'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (empty($key_hash)) {
			AIPS_Ajax_Response::invalid_request(__('key_hash is required.', 'ai-post-scheduler'));
		}

		$dev_mode  = (bool) AIPS_Config::get_instance()->get_option('aips_developer_mode', false);
		$full_view = $dev_mode && !empty($_POST['full_view']); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		AIPS_Ajax_Response::success($this->service->inspect_entry($key_hash, $full_view));
	}

	/**
	 * AJAX: return operations analytics.
	 *
	 * @return void
	 */
	public function ajax_operations(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor');
		$filters = array();

		if (!empty($_POST['repository_class'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$filters['repository_class'] = sanitize_text_field(wp_unslash($_POST['repository_class'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if (!empty($_POST['tier'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$filters['tier'] = sanitize_key(wp_unslash($_POST['tier'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		AIPS_Ajax_Response::success(array('operations' => $this->service->get_operations($filters)));
	}

	/**
	 * AJAX: return paginated cache events.
	 *
	 * @return void
	 */
	public function ajax_events(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor');

		$filters  = array();
		$per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 50; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$page     = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if (!empty($_POST['event_type'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$filters['event_type'] = sanitize_key(wp_unslash($_POST['event_type'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		AIPS_Ajax_Response::success($this->service->get_events($filters, $per_page, $page));
	}

	// -----------------------------------------------------------------------
	// AJAX: destructive (nonce + capability verified in method bodies)
	// -----------------------------------------------------------------------

	/**
	 * AJAX: delete a single cache entry.
	 *
	 * @return void
	 */
	public function ajax_delete_entry(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor_action');

		$key_hash = isset($_POST['key_hash']) ? sanitize_text_field(wp_unslash($_POST['key_hash'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (empty($key_hash)) {
			AIPS_Ajax_Response::invalid_request(__('key_hash is required.', 'ai-post-scheduler'));
		}

		$affected = $this->service->delete_entry($key_hash);
		AIPS_Ajax_Response::success(
			array('affected' => $affected),
			/* translators: %d: number of entries deleted */
			sprintf(_n('%d cache entry deleted.', '%d cache entries deleted.', $affected, 'ai-post-scheduler'), $affected)
		);
	}

	/**
	 * AJAX: delete multiple cache entries (bulk).
	 *
	 * @return void
	 */
	public function ajax_delete_bulk(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor_action');

		$raw_hashes = isset($_POST['key_hashes']) ? wp_unslash($_POST['key_hashes']) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$key_hashes = is_array($raw_hashes)
			? array_map('sanitize_text_field', $raw_hashes)
			: array_map('sanitize_text_field', explode(',', (string) $raw_hashes));

		$key_hashes = array_filter($key_hashes);

		if (empty($key_hashes)) {
			AIPS_Ajax_Response::invalid_request(__('No key hashes provided.', 'ai-post-scheduler'));
		}

		$affected = $this->service->delete_entries_bulk($key_hashes);
		AIPS_Ajax_Response::success(
			array('affected' => $affected),
			/* translators: %d: number of entries deleted */
			sprintf(_n('%d cache entry deleted.', '%d cache entries deleted.', $affected, 'ai-post-scheduler'), $affected)
		);
	}

	/**
	 * AJAX: flush a cache group.
	 *
	 * @return void
	 */
	public function ajax_flush_group(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor_action');

		$group = isset($_POST['cache_group']) ? sanitize_key(wp_unslash($_POST['cache_group'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (empty($group)) {
			AIPS_Ajax_Response::invalid_request(__('cache_group is required.', 'ai-post-scheduler'));
		}

		$affected = $this->service->flush_group($group);
		AIPS_Ajax_Response::success(
			array('affected' => $affected, 'group' => $group),
			/* translators: %s: cache group name, %d: entries affected */
			sprintf(__('Cache group "%s" flushed (%d entries).', 'ai-post-scheduler'), $group, $affected)
		);
	}

	/**
	 * AJAX: flush expired entries.
	 *
	 * @return void
	 */
	public function ajax_flush_expired(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor_action');

		$affected = $this->service->flush_expired();
		AIPS_Ajax_Response::success(
			array('affected' => $affected),
			/* translators: %d: entries pruned */
			sprintf(_n('%d expired entry removed.', '%d expired entries removed.', $affected, 'ai-post-scheduler'), $affected)
		);
	}

	/**
	 * AJAX: flush all plugin-owned cache.
	 *
	 * Requires explicit confirmation checkbox.
	 *
	 * @return void
	 */
	public function ajax_flush_all(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor_action');

		$confirmed = !empty($_POST['confirmed']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (!$confirmed) {
			AIPS_Ajax_Response::error(
				__('Flush all requires explicit confirmation.', 'ai-post-scheduler'),
				'confirmation_required'
			);
		}

		$this->service->flush_all_plugin_cache();
		AIPS_Ajax_Response::success(array(), __('All plugin-owned cache flushed.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: invalidate a tag.
	 *
	 * @return void
	 */
	public function ajax_invalidate_tag(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor_action');

		$tag = isset($_POST['tag']) ? sanitize_text_field(wp_unslash($_POST['tag'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (empty($tag)) {
			AIPS_Ajax_Response::invalid_request(__('tag is required.', 'ai-post-scheduler'));
		}

		$new_version = $this->service->invalidate_tag($tag);
		AIPS_Ajax_Response::success(
			array('tag' => $tag, 'new_version' => $new_version),
			/* translators: %s: tag name, %d: new version */
			sprintf(__('Tag "%s" invalidated (new version: %d).', 'ai-post-scheduler'), esc_html($tag), $new_version)
		);
	}

	/**
	 * AJAX: invalidate a dependency domain.
	 *
	 * @return void
	 */
	public function ajax_invalidate_domain(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor_action');

		$domain = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (empty($domain)) {
			AIPS_Ajax_Response::invalid_request(__('domain is required.', 'ai-post-scheduler'));
		}

		$bumped = $this->service->invalidate_domain($domain);
		AIPS_Ajax_Response::success(
			array('domain' => $domain, 'bumped_tags' => $bumped),
			/* translators: %s: domain name, %d: tags bumped */
			sprintf(__('Domain "%s" invalidated (%d tags bumped).', 'ai-post-scheduler'), esc_html($domain), count($bumped))
		);
	}

	/**
	 * AJAX: run maintenance action.
	 *
	 * @return void
	 */
	public function ajax_maintenance(): void {
		$this->verify_nonce_and_cap('aips_cache_monitor_action');

		$action = isset($_POST['maintenance_action']) ? sanitize_key(wp_unslash($_POST['maintenance_action'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		switch ($action) {
			case 'prune_expired':
				$result = array('pruned' => $this->service->flush_expired());
				$msg    = sprintf(
					/* translators: %d: rows pruned */
					_n('%d expired entry pruned.', '%d expired entries pruned.', $result['pruned'], 'ai-post-scheduler'),
					$result['pruned']
				);
				break;

			case 'prune_orphans':
				$index   = new AIPS_Cache_Index();
				$pruned  = $index->prune_orphans();
				$result  = array('pruned' => $pruned);
				$msg     = sprintf(
					/* translators: %d: rows pruned */
					_n('%d orphaned index entry removed.', '%d orphaned index entries removed.', $pruned, 'ai-post-scheduler'),
					$pruned
				);
				break;

			case 'rebuild_index':
				$inserted = $this->service->rebuild_index();
				$result   = array('inserted' => $inserted);
				$msg      = sprintf(
					/* translators: %d: rows inserted */
					_n('%d index row rebuilt.', '%d index rows rebuilt.', $inserted, 'ai-post-scheduler'),
					$inserted
				);
				break;

			case 'run_all':
				$result = $this->service->run_maintenance();
				$msg    = __('Maintenance complete.', 'ai-post-scheduler');
				break;

			case 'export_diagnostics':
				$diagnostics = $this->service->export_diagnostics();
				AIPS_Ajax_Response::success(array('diagnostics' => $diagnostics));
				return;

			default:
				AIPS_Ajax_Response::invalid_request(
					/* translators: %s: action name */
					sprintf(__('Unknown maintenance action: %s', 'ai-post-scheduler'), esc_html($action))
				);
				return;
		}

		AIPS_Ajax_Response::success($result, $msg);
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Verify nonce and manage_options capability. Dies on failure.
	 *
	 * @param string $nonce_action Nonce action name.
	 * @return void
	 */
	private function verify_nonce_and_cap( string $nonce_action ): void {
		if (!check_ajax_referer($nonce_action, 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'), 'invalid_nonce', 403);
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}

	/**
	 * Extract and sanitize filter parameters from $_POST.
	 *
	 * @return array<string, string>
	 */
	private function extract_filters(): array {
		$filters = array();
		$keys    = array('group', 'tier', 'driver', 'operation_id', 'repository_class', 'tag', 'ttl_state', 'search');

		foreach ($keys as $key) {
			if (!empty($_POST[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$filters[$key] = sanitize_text_field(wp_unslash($_POST[$key])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		return $filters;
	}
}
