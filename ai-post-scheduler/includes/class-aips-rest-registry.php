<?php
/**
 * REST Registry
 *
 * Centralized registry mapping REST resource bases to their controller classes.
 * The REST counterpart of AIPS_Ajax_Registry: a single source of truth for
 * which controllers own which `aips/v1/{resource}` routes.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Rest_Registry
 *
 * Maps a resource base (the first path segment under the plugin namespace,
 * e.g. `templates` for `/aips/v1/templates/{id}`) to the AIPS_Rest_Controller
 * subclass that registers routes for it.
 *
 * Unlike admin-ajax, the REST server needs every route registered before it can
 * dispatch, so boot_rest() cannot construct only one controller per request the
 * way boot_ajax() does. Instead, it inspects the requested route and constructs
 * only the controllers whose resource base matches — falling back to all
 * controllers when the route is unknown (e.g. the `/aips/v1` index/discovery
 * request, or a route whose first segment is not in the map).
 */
class AIPS_Rest_Registry {

	/**
	 * Plugin REST namespace shared by every controller.
	 */
	const NAMESPACE_V1 = 'aips/v1';

	/**
	 * Map of resource base => controller class name.
	 *
	 * Format: 'resource_base' => Controller_Class::class
	 *
	 * Populated one slice at a time as endpoints migrate off admin-ajax.
	 *
	 * @var array<string, string>
	 */
	private static $map = array(
		// Slice 1: read-only reporters
		'dashboard'       => 'AIPS_Dashboard_Rest_Controller',
		'telemetry'       => 'AIPS_Telemetry_Rest_Controller',
		'calendar'        => 'AIPS_Calendar_Rest_Controller',
		// Slice 2: simple CRUD singletons
		'voices'          => 'AIPS_Voices_Rest_Controller',
		'structures'      => 'AIPS_Structures_Rest_Controller',
		'prompt-sections' => 'AIPS_Prompt_Sections_Rest_Controller',
		'post-slices'     => 'AIPS_Post_Slices_Rest_Controller',
		// Slice 3a: templates + sources CRUD (schedules, authors, campaigns land in later slices)
		'templates'       => 'AIPS_Templates_Rest_Controller',
		'sources'         => 'AIPS_Sources_Rest_Controller',
		'source-groups'   => 'AIPS_Source_Groups_Rest_Controller',
		'source-data'     => 'AIPS_Source_Data_Rest_Controller',
	);

	/**
	 * Register a controller for a resource base at runtime.
	 *
	 * Core plugin controllers belong in the static $map above (single source of
	 * truth). This method exists for tests and for add-ons extending the
	 * `aips/v1` namespace; call it before `rest_api_init` fires.
	 *
	 * @param string $resource_base    Resource base (e.g. 'templates').
	 * @param string $controller_class AIPS_Rest_Controller subclass name.
	 * @return void
	 */
	public static function register($resource_base, $controller_class) {
		self::$map[(string) $resource_base] = (string) $controller_class;
	}

	/**
	 * Remove a runtime-registered controller. Intended for test teardown.
	 *
	 * @param string $resource_base Resource base.
	 * @return void
	 */
	public static function unregister($resource_base) {
		unset(self::$map[(string) $resource_base]);
	}

	/**
	 * Get the controller class for a resource base.
	 *
	 * @param string $resource_base First path segment under the namespace (e.g. 'templates').
	 * @return string|null Controller class name, or null if not registered.
	 */
	public static function get_controller_for($resource_base) {
		return isset(self::$map[$resource_base]) ? self::$map[$resource_base] : null;
	}

	/**
	 * Get every registered controller class name (de-duplicated).
	 *
	 * @return array<string>
	 */
	public static function all_controllers() {
		return array_values(array_unique(array_values(self::$map)));
	}

	/**
	 * Get every registered resource base.
	 *
	 * @return array<string>
	 */
	public static function all_resource_bases() {
		return array_keys(self::$map);
	}

	/**
	 * Check whether a resource base is registered.
	 *
	 * @param string $resource_base Resource base.
	 * @return bool
	 */
	public static function has_resource($resource_base) {
		return isset(self::$map[$resource_base]);
	}

	/**
	 * Total number of registered resource bases.
	 *
	 * @return int
	 */
	public static function count() {
		return count(self::$map);
	}

	/**
	 * Resolve the controllers that must be constructed to serve a REST route.
	 *
	 * Given a route such as `/aips/v1/templates/12`, returns only the controller
	 * owning the `templates` resource. Routes outside the plugin namespace
	 * return an empty list; routes inside the namespace whose resource base is
	 * not in the map (or the bare namespace index) return every controller so
	 * discovery and any not-yet-mapped routes still work.
	 *
	 * @param string $route The REST route (leading slash optional), e.g. '/aips/v1/templates/12'.
	 * @return array<string> Controller class names to construct.
	 */
	public static function controllers_for_route($route) {
		$route     = '/' . ltrim((string) $route, '/');
		$namespace = '/' . self::NAMESPACE_V1;

		// The API root index (`/wp-json/`) enumerates namespaces; register everything
		// so the plugin namespace is discoverable there.
		if ('/' === $route) {
			return self::all_controllers();
		}

		if ($route !== $namespace && strncmp($route, $namespace . '/', strlen($namespace) + 1) !== 0) {
			return array();
		}

		$remainder = trim(substr($route, strlen($namespace)), '/');
		if ('' === $remainder) {
			return self::all_controllers();
		}

		$segments      = explode('/', $remainder);
		$resource_base = $segments[0];

		$controller = self::get_controller_for($resource_base);
		if ($controller) {
			return array($controller);
		}

		return self::all_controllers();
	}

	/**
	 * Extract the requested REST route from the current request, if any.
	 *
	 * Mirrors the detection WordPress itself performs in rest_api_loaded():
	 * the `rest_route` query var (plain permalinks) or a REQUEST_URI that begins
	 * with the REST prefix (pretty permalinks). Returns null when the current
	 * request does not target the REST API at all.
	 *
	 * @return string|null Route with a leading slash, or null.
	 */
	public static function detect_current_route() {
		if (isset($_GET['rest_route'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$route = sanitize_text_field(wp_unslash($_GET['rest_route'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '/' . ltrim($route, '/');
		}

		if (empty($_SERVER['REQUEST_URI'])) {
			return null;
		}

		$request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
		$path        = wp_parse_url($request_uri, PHP_URL_PATH);
		if (!is_string($path) || '' === $path) {
			return null;
		}

		$prefix = function_exists('rest_get_url_prefix') ? rest_get_url_prefix() : 'wp-json';

		// Strip the site's own path (for sub-directory installs) before matching the prefix.
		$home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
		if (is_string($home_path) && '/' !== $home_path && strncmp($path, $home_path, strlen($home_path)) === 0) {
			$path = '/' . ltrim(substr($path, strlen($home_path)), '/');
		}

		$prefix_with_slashes = '/' . trim($prefix, '/') . '/';
		if ($path === '/' . trim($prefix, '/')) {
			return '/';
		}
		if (strncmp($path, $prefix_with_slashes, strlen($prefix_with_slashes)) !== 0) {
			return null;
		}

		return '/' . ltrim(substr($path, strlen($prefix_with_slashes)), '/');
	}
}
