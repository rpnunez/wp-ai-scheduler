<?php
/**
 * Redirects Service
 *
 * A small redirect manager. Every redirect AIPS creates (by hand in
 * Content → Redirects, or by features such as consolidation) is recorded in
 * aips_redirects and served by the active provider:
 *
 * - Redirection, Yoast SEO Premium or Rank Math when one is installed, or
 * - AIPS itself (native: template_redirect lookup against a cached index).
 *
 * Because AIPS keeps its own record (with the provider's ID), redirects can
 * be listed, disabled, deleted and moved to another provider at any time.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Redirects_Service
 */
class AIPS_Redirects_Service {

	/**
	 * Provider setting: 'auto' or a provider key.
	 */
	const PROVIDER_OPTION = 'aips_redirect_provider';

	/**
	 * Option caching the source hashes AIPS serves natively.
	 */
	const NATIVE_INDEX_OPTION = 'aips_redirects_native_index';

	/**
	 * Allowed status codes.
	 */
	const STATUS_CODES = array(301, 302, 307, 308, 410);

	/**
	 * Origins.
	 */
	const ORIGIN_MANUAL        = 'manual';
	const ORIGIN_CONSOLIDATION = 'consolidation';

	/**
	 * @var AIPS_Redirects_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Redirect_Provider[]|null
	 */
	private $providers = null;

	/**
	 * @param AIPS_Redirects_Repository|null $repository Repository.
	 * @param AIPS_Config|null               $config     Config.
	 */
	public function __construct(?AIPS_Redirects_Repository $repository = null, ?AIPS_Config $config = null) {
		$this->repository = $repository ?: new AIPS_Redirects_Repository();
		$this->config     = $config ?: AIPS_Config::get_instance();
	}

	/**
	 * @return AIPS_Redirects_Repository
	 */
	public function get_repository(): AIPS_Redirects_Repository {
		return $this->repository;
	}

	/**
	 * All known providers, keyed by provider key.
	 *
	 * @return AIPS_Redirect_Provider[]
	 */
	public function get_providers(): array {
		if ($this->providers === null) {
			$providers = array(
				new AIPS_Redirect_Provider_Redirection(),
				new AIPS_Redirect_Provider_Yoast(),
				new AIPS_Redirect_Provider_Rankmath(),
				new AIPS_Redirect_Provider_Native(),
			);

			/**
			 * Filters the redirect providers AIPS can use. Order matters for
			 * "automatic": the first available one wins.
			 *
			 * @param AIPS_Redirect_Provider[] $providers Providers.
			 */
			$providers = (array) apply_filters('aips_redirect_providers', $providers);

			$this->providers = array();
			foreach ($providers as $provider) {
				if ($provider instanceof AIPS_Redirect_Provider) {
					$this->providers[$provider->get_key()] = $provider;
				}
			}

			if (!isset($this->providers[AIPS_Redirect_Provider_Native::KEY])) {
				$this->providers[AIPS_Redirect_Provider_Native::KEY] = new AIPS_Redirect_Provider_Native();
			}
		}

		return $this->providers;
	}

	/**
	 * The configured provider setting ('auto' or a key).
	 *
	 * @return string
	 */
	public function get_provider_setting(): string {
		$setting = (string) $this->config->get_option(self::PROVIDER_OPTION, 'auto');
		return ($setting === 'auto' || isset($this->get_providers()[$setting])) ? $setting : 'auto';
	}

	/**
	 * Provider new redirects go to: the chosen one if available, otherwise
	 * the first available (Redirection, Yoast Premium, Rank Math), otherwise
	 * AIPS itself.
	 *
	 * @return AIPS_Redirect_Provider
	 */
	public function get_active_provider(): AIPS_Redirect_Provider {
		$providers = $this->get_providers();
		$setting   = $this->get_provider_setting();

		if ($setting !== 'auto' && isset($providers[$setting]) && $providers[$setting]->is_available()) {
			return $providers[$setting];
		}

		foreach ($providers as $provider) {
			if ($provider->is_available()) {
				return $provider;
			}
		}

		return $providers[AIPS_Redirect_Provider_Native::KEY];
	}

	/**
	 * Provider list for the UI.
	 *
	 * @return array[] key, label, available, active, count.
	 */
	public function get_provider_summary(): array {
		$active = $this->get_active_provider()->get_key();
		$counts = $this->repository->count_by_provider();
		$rows   = array();

		foreach ($this->get_providers() as $key => $provider) {
			$rows[] = array(
				'key'       => $key,
				'label'     => $provider->get_label(),
				'available' => $provider->is_available(),
				'active'    => $key === $active,
				'count'     => isset($counts[$key]) ? $counts[$key] : 0,
			);
		}

		return $rows;
	}

	/**
	 * Create a redirect.
	 *
	 * @param array $args source (path or URL), target (URL) or target_post_id,
	 *                    status_code, origin, origin_ref.
	 * @return object|WP_Error The stored redirect row.
	 */
	public function create(array $args) {
		$source = $this->normalize_source((string) ($args['source'] ?? ''));
		if (is_wp_error($source)) {
			return $source;
		}

		$code = (int) ($args['status_code'] ?? 301);
		if (!in_array($code, self::STATUS_CODES, true)) {
			$code = 301;
		}

		$target_post_id = absint($args['target_post_id'] ?? 0);
		$target         = $target_post_id ? (string) get_permalink($target_post_id) : trim((string) ($args['target'] ?? ''));

		if ($code !== 410) {
			$target = $this->normalize_target($target);
			if (is_wp_error($target)) {
				return $target;
			}

			if ($this->hash($this->path_of($target)) === $this->hash($source) && $this->is_local($target)) {
				return new WP_Error('aips_redirect_loop', __('A redirect cannot point to its own URL.', 'ai-post-scheduler'));
			}
		} else {
			$target = '';
		}

		$hash = $this->hash($source);
		if ($this->repository->get_by_hash($hash)) {
			return new WP_Error('aips_redirect_exists', __('AI Post Scheduler already has a redirect for this URL.', 'ai-post-scheduler'));
		}

		list($provider_key, $provider_ref, $provider_error) = $this->push_to_provider($this->get_active_provider(), $source, $target, $code);

		$id = $this->repository->insert(array(
			'source_path'    => $source,
			'source_hash'    => $hash,
			'target_url'     => $target,
			'target_post_id' => $target_post_id ?: (int) url_to_postid($target),
			'status_code'    => $code,
			'provider'       => $provider_key,
			'provider_ref'   => $provider_ref,
			'provider_error' => $provider_error,
			'origin'         => sanitize_key((string) ($args['origin'] ?? self::ORIGIN_MANUAL)),
			'origin_ref'     => absint($args['origin_ref'] ?? 0),
			'enabled'        => 1,
			'created_by'     => get_current_user_id(),
		));

		if (!$id) {
			// Keep the provider clean if our own record could not be written.
			$this->get_providers()[$provider_key]->delete($provider_ref, $source);
			return new WP_Error('aips_redirect_save_failed', __('The redirect could not be saved.', 'ai-post-scheduler'));
		}

		$this->refresh_native_index();

		/**
		 * Fires after AIPS created a redirect.
		 *
		 * @param object $redirect Stored redirect row.
		 */
		do_action('aips_redirect_created', $this->repository->get($id));

		return $this->repository->get($id);
	}

	/**
	 * Delete a redirect (from its provider and from AIPS).
	 *
	 * @param int $id Redirect ID.
	 * @return bool|WP_Error
	 */
	public function delete(int $id) {
		$row = $this->repository->get($id);
		if (!$row) {
			return new WP_Error('aips_redirect_not_found', __('Redirect not found.', 'ai-post-scheduler'));
		}

		$this->remove_from_provider($row);
		$this->repository->delete($id);
		$this->refresh_native_index();

		return true;
	}

	/**
	 * Enable or disable a redirect. Disabling removes it from an external
	 * provider; enabling adds it back to the active provider.
	 *
	 * @param int  $id      Redirect ID.
	 * @param bool $enabled New state.
	 * @return object|WP_Error Updated row.
	 */
	public function set_enabled(int $id, bool $enabled) {
		$row = $this->repository->get($id);
		if (!$row) {
			return new WP_Error('aips_redirect_not_found', __('Redirect not found.', 'ai-post-scheduler'));
		}

		if ((bool) $row->enabled === $enabled) {
			return $row;
		}

		if ($enabled) {
			list($key, $ref, $error) = $this->push_to_provider($this->get_active_provider(), $row->source_path, $row->target_url, (int) $row->status_code);
			$this->repository->update($id, array('enabled' => 1, 'provider' => $key, 'provider_ref' => $ref, 'provider_error' => $error));
		} else {
			$this->remove_from_provider($row);
			$this->repository->update($id, array('enabled' => 0, 'provider_ref' => ''));
		}

		$this->refresh_native_index();

		return $this->repository->get($id);
	}

	/**
	 * Move every enabled redirect to a provider (e.g. after installing or
	 * removing Redirection / Yoast / Rank Math).
	 *
	 * @param string $key Target provider key.
	 * @return array{moved:int, failed:int, errors:string[]}|WP_Error
	 */
	public function move_all(string $key) {
		$providers = $this->get_providers();
		if (!isset($providers[$key]) || !$providers[$key]->is_available()) {
			return new WP_Error('aips_redirect_provider_unavailable', __('That redirect provider is not available.', 'ai-post-scheduler'));
		}

		$moved  = 0;
		$failed = 0;
		$errors = array();

		foreach (array_keys($providers) as $from) {
			if ($from === $key) {
				continue;
			}

			foreach ($this->repository->get_ids_by_provider($from) as $id) {
				$row = $this->repository->get($id);
				if (!$row) {
					continue;
				}

				if (!(int) $row->enabled) {
					$this->repository->update($id, array('provider' => $key, 'provider_ref' => ''));
					$moved++;
					continue;
				}

				$this->remove_from_provider($row);
				list($new_key, $ref, $error) = $this->push_to_provider($providers[$key], $row->source_path, $row->target_url, (int) $row->status_code);
				$this->repository->update($id, array('provider' => $new_key, 'provider_ref' => $ref, 'provider_error' => $error));

				if ($new_key === $key) {
					$moved++;
				} else {
					$failed++;
					$errors[] = $row->source_path . ': ' . $error;
				}
			}
		}

		$this->refresh_native_index();

		return array('moved' => $moved, 'failed' => $failed, 'errors' => array_slice($errors, 0, 10));
	}

	/**
	 * template_redirect: serve AIPS's own (native) redirects.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		$index = $this->config->get_option(self::NATIVE_INDEX_OPTION, array());
		if (empty($index) || !is_array($index) || is_admin()) {
			return;
		}

		$path = $this->current_request_path();
		if ($path === '') {
			return;
		}

		$hash = $this->hash($path);
		if (!isset($index[$hash])) {
			return;
		}

		$row = $this->repository->get_by_hash($hash);
		if (!$row || !(int) $row->enabled || $row->provider !== AIPS_Redirect_Provider_Native::KEY) {
			return;
		}

		$this->repository->record_hit((int) $row->id);

		if ((int) $row->status_code === 410) {
			global $wp_query;
			if ($wp_query instanceof WP_Query) {
				$wp_query->set_404();
			}
			status_header(410);
			nocache_headers();
			return;
		}

		// Keep the visitor's query string (utm tags etc.).
		$target = (string) $row->target_url;
		$query  = isset($_SERVER['REQUEST_URI']) ? (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_QUERY) : '';
		if ($query !== '' && strpos($target, '?') === false) {
			$target .= '?' . $query;
		}

		if (wp_redirect($target, (int) $row->status_code, 'AI Post Scheduler')) { // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			$this->finish_request();
		}
	}

	/**
	 * Redirects for the admin list, with target titles.
	 *
	 * @param array $args Filters and paging.
	 * @return array{rows:array[], total:int}
	 */
	public function get_list(array $args): array {
		$providers = $this->get_providers();
		$rows      = array();

		foreach ($this->repository->get_page($args) as $row) {
			$provider = isset($providers[$row->provider]) ? $providers[$row->provider] : null;
			$rows[]   = array(
				'id'             => (int) $row->id,
				'source_path'    => (string) $row->source_path,
				'source_url'     => home_url((string) $row->source_path),
				'target_url'     => (string) $row->target_url,
				'target_title'   => (int) $row->target_post_id ? get_the_title((int) $row->target_post_id) : '',
				'status_code'    => (int) $row->status_code,
				'provider'       => (string) $row->provider,
				'provider_label' => $provider ? $provider->get_label() : (string) $row->provider,
				'provider_error' => (string) $row->provider_error,
				'origin'         => (string) $row->origin,
				'enabled'        => (bool) $row->enabled,
				'hits'           => (int) $row->hits,
				'created_at'     => (int) $row->created_at,
			);
		}

		return array('rows' => $rows, 'total' => $this->repository->count($args));
	}

	/**
	 * Normalize a source to a site-relative path ("/old-post/").
	 *
	 * @param string $source Path or URL on this site.
	 * @return string|WP_Error
	 */
	public function normalize_source(string $source) {
		$source = trim($source);
		if ($source === '') {
			return new WP_Error('aips_redirect_source', __('Enter the old URL or path to redirect.', 'ai-post-scheduler'));
		}

		if (preg_match('#^https?://#i', $source)) {
			if (!$this->is_local($source)) {
				return new WP_Error('aips_redirect_source_external', __('The old URL must be on this site.', 'ai-post-scheduler'));
			}
			$source = $this->path_of($source);
		}

		$source = '/' . ltrim((string) strtok($source, '?#'), '/');
		$source = preg_replace('#/+#', '/', $source);

		if ($source === '/' || preg_match('#^/(wp-admin|wp-login\.php|wp-json)(/|$)#i', $source)) {
			return new WP_Error('aips_redirect_source_reserved', __('The home page and WordPress system URLs cannot be redirected.', 'ai-post-scheduler'));
		}

		return substr($source, 0, 500);
	}

	/**
	 * Push a redirect to a provider, falling back to native on failure.
	 *
	 * @param AIPS_Redirect_Provider $provider Provider.
	 * @param string                 $source   Source path.
	 * @param string                 $target   Target URL.
	 * @param int                    $code     Status code.
	 * @return array{0:string, 1:string, 2:string} provider key, provider ref, error.
	 */
	private function push_to_provider(AIPS_Redirect_Provider $provider, string $source, string $target, int $code): array {
		if ($provider->get_key() === AIPS_Redirect_Provider_Native::KEY) {
			return array(AIPS_Redirect_Provider_Native::KEY, '', '');
		}

		try {
			$ref = $provider->create($source, $target, $code);
		} catch (\Throwable $e) {
			$ref = new WP_Error('aips_redirect_provider_exception', $e->getMessage());
		}

		if (is_wp_error($ref)) {
			/* translators: 1: provider name, 2: error message */
			$error = sprintf(__('%1$s refused the redirect (%2$s); AI Post Scheduler serves it instead.', 'ai-post-scheduler'), $provider->get_label(), $ref->get_error_message());
			return array(AIPS_Redirect_Provider_Native::KEY, '', substr($error, 0, 255));
		}

		return array($provider->get_key(), (string) $ref, '');
	}

	/**
	 * Remove a stored redirect from its provider.
	 *
	 * @param object $row Redirect row.
	 * @return void
	 */
	private function remove_from_provider($row): void {
		$providers = $this->get_providers();
		if (!isset($providers[$row->provider]) || $row->provider === AIPS_Redirect_Provider_Native::KEY) {
			return;
		}

		try {
			$providers[$row->provider]->delete((string) $row->provider_ref, (string) $row->source_path);
		} catch (\Throwable $e) {
			// The provider's plugin may have been removed; our record is still deleted.
		}
	}

	/**
	 * Rebuild the cached set of natively served source hashes.
	 *
	 * @return void
	 */
	private function refresh_native_index(): void {
		$hashes = $this->repository->get_enabled_hashes(AIPS_Redirect_Provider_Native::KEY);
		$this->config->set_option(self::NATIVE_INDEX_OPTION, empty($hashes) ? array() : array_fill_keys($hashes, 1), true);
	}

	/**
	 * Validate a target URL (relative paths become absolute on this site).
	 *
	 * @param string $target Target.
	 * @return string|WP_Error
	 */
	private function normalize_target(string $target) {
		if ($target === '') {
			return new WP_Error('aips_redirect_target', __('Choose a post or enter the URL to redirect to.', 'ai-post-scheduler'));
		}

		if (strpos($target, '/') === 0 && strpos($target, '//') !== 0) {
			$target = home_url($target);
		}

		$target = esc_url_raw($target, array('http', 'https'));
		if ($target === '') {
			return new WP_Error('aips_redirect_target_invalid', __('The target must be an http(s) URL.', 'ai-post-scheduler'));
		}

		return $target;
	}

	/**
	 * Path of the current request.
	 *
	 * @return string
	 */
	private function current_request_path(): string {
		$uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
		$path = (string) wp_parse_url($uri, PHP_URL_PATH);
		return $path === '' ? '' : '/' . ltrim(rawurldecode($path), '/');
	}

	/**
	 * Lookup key: case-insensitive, trailing slash ignored.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function hash(string $path): string {
		return md5(strtolower(untrailingslashit('/' . ltrim($path, '/'))));
	}

	/**
	 * Path of a URL ("/a/b/"), decoded.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function path_of(string $url): string {
		return '/' . ltrim(rawurldecode((string) wp_parse_url($url, PHP_URL_PATH)), '/');
	}

	/**
	 * Whether a URL is on this site.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_local(string $url): bool {
		$host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
		$home = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
		return $host === '' || preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', $home);
	}

	/**
	 * End the request after a redirect (overridable in tests).
	 *
	 * @return void
	 */
	protected function finish_request(): void {
		exit;
	}
}
