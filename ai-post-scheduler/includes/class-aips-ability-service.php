<?php
/**
 * Ability Service
 *
 * Single adapter for discovering and invoking runtime AI abilities.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Service
 */
class AIPS_Ability_Service {

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * @var array|null
	 */
	private $provider = null;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Logger_Interface|null $logger Logger.
	 */
	public function __construct(?AIPS_Logger_Interface $logger = null) {
		if ($logger) {
			$this->logger = $logger;
			return;
		}

		$container = AIPS_Container::get_instance();
		$this->logger = $container->has(AIPS_Logger_Interface::class)
			? $container->make(AIPS_Logger_Interface::class)
			: AIPS_Logger::instance();
	}

	/**
	 * List available abilities.
	 *
	 * @return array|WP_Error Normalized ability arrays keyed by slug, or WP_Error.
	 */
	public function list_available() {
		$provider = $this->get_provider();

		if (is_wp_error($provider)) {
			$this->log_response('list', '', array(), $provider);
			return $provider;
		}

		$this->log_request('list', '', array(), array('provider' => $provider['name']));

		try {
			$response = call_user_func($provider['list']);
		} catch (Throwable $e) {
			$error = new WP_Error('ability_discovery_failed', $e->getMessage());
			$this->log_response('list', '', array(), $error);
			return $error;
		}

		$abilities = $this->normalize_abilities($response);

		if (is_wp_error($abilities)) {
			$this->log_response('list', '', array(), $abilities);
			return $abilities;
		}

		$this->log_response('list', '', array(), $abilities);
		return $abilities;
	}

	/**
	 * Check whether an ability slug is available.
	 *
	 * @param string $slug Ability slug.
	 * @return bool|WP_Error True/false, or WP_Error when provider is missing/malformed.
	 */
	public function is_available($slug) {
		$slug = $this->normalize_slug($slug);

		if ($slug === '') {
			return new WP_Error('ability_slug_invalid', __('Ability slug is required.', 'ai-post-scheduler'));
		}

		$abilities = $this->list_available();

		if (is_wp_error($abilities)) {
			return $abilities;
		}

		return isset($abilities[$slug]);
	}

	/**
	 * Invoke an ability and normalize its response.
	 *
	 * @param string $slug    Ability slug.
	 * @param array  $payload Ability payload.
	 * @param array  $options Invocation options.
	 * @return array|WP_Error Normalized response array, or WP_Error.
	 */
	public function invoke($slug, $payload, $options = array()) {
		$slug = $this->normalize_slug($slug);

		if ($slug === '') {
			return new WP_Error('ability_slug_invalid', __('Ability slug is required.', 'ai-post-scheduler'));
		}

		if (!is_array($payload)) {
			return new WP_Error('ability_payload_invalid', __('Ability payload must be an array.', 'ai-post-scheduler'));
		}

		$provider = $this->get_provider();

		if (is_wp_error($provider)) {
			$this->log_response('invoke', $slug, $payload, $provider);
			return $provider;
		}

		$available = $this->is_available($slug);

		if (is_wp_error($available)) {
			return $available;
		}

		if (!$available) {
			$error = new WP_Error('ability_unavailable', sprintf(__('Ability "%s" is not available.', 'ai-post-scheduler'), $slug));
			$this->log_response('invoke', $slug, $payload, $error);
			return $error;
		}

		$this->log_request('invoke', $slug, $payload, array('provider' => $provider['name'], 'options' => $options));

		try {
			$response = call_user_func($provider['invoke'], $slug, $payload, $options);
		} catch (Throwable $e) {
			$error = new WP_Error('ability_invocation_failed', $e->getMessage());
			$this->log_response('invoke', $slug, $payload, $error);
			return $error;
		}

		$normalized = $this->normalize_response($response);

		if (is_wp_error($normalized)) {
			$this->log_response('invoke', $slug, $payload, $normalized);
			return $normalized;
		}

		$this->log_response('invoke', $slug, $payload, $normalized);
		return $normalized;
	}

	/**
	 * Resolve the active runtime provider.
	 *
	 * @return array|WP_Error Provider descriptor, or WP_Error.
	 */
	private function get_provider() {
		if ($this->provider !== null) {
			return $this->provider;
		}

		if (function_exists('apply_filters')) {
			$filtered = apply_filters('aips_ability_provider', null);
			$provider = $this->provider_from_candidate($filtered, 'aips_ability_provider');
			if (!is_wp_error($provider)) {
				$this->provider = $provider;
				return $provider;
			}
		}

		global $mwai;
		$provider = $this->provider_from_candidate($mwai, 'global_mwai');
		if (!is_wp_error($provider)) {
			$this->provider = $provider;
			return $provider;
		}

		$candidates = array(
			array('function', 'wp_get_abilities', 'wp_invoke_ability', 'wordpress_abilities'),
			array('function', 'wp_list_abilities', 'wp_invoke_ability', 'wordpress_abilities'),
			array('function', 'mwai_get_abilities', 'mwai_invoke_ability', 'mwai_abilities'),
			array('class', 'WP_Abilities', array('get_instance', 'list_abilities'), array('get_instance', 'invoke'), 'WP_Abilities'),
			array('class', 'Meow_MWAI_Abilities', array('instance', 'list_available'), array('instance', 'invoke'), 'Meow_MWAI_Abilities'),
		);

		foreach ($candidates as $candidate) {
			$provider = $this->provider_from_candidate($candidate, 'runtime');
			if (!is_wp_error($provider)) {
				$this->provider = $provider;
				return $provider;
			}
		}

		return new WP_Error('ability_provider_missing', __('No ability provider is available.', 'ai-post-scheduler'));
	}

	/**
	 * Create provider descriptor from supported candidate shapes.
	 *
	 * @param mixed  $candidate Candidate provider.
	 * @param string $source    Source label.
	 * @return array|WP_Error
	 */
	private function provider_from_candidate($candidate, $source) {
		if (is_array($candidate) && isset($candidate['list'], $candidate['invoke']) && is_callable($candidate['list']) && is_callable($candidate['invoke'])) {
			return array('name' => isset($candidate['name']) ? (string) $candidate['name'] : $source, 'list' => $candidate['list'], 'invoke' => $candidate['invoke']);
		}

		if (is_object($candidate)) {
			foreach (array('list_available', 'listAvailable', 'list_abilities', 'get_abilities') as $list_method) {
				foreach (array('invoke', 'invoke_ability', 'call') as $invoke_method) {
					if (method_exists($candidate, $list_method) && method_exists($candidate, $invoke_method)) {
						return array('name' => get_class($candidate), 'list' => array($candidate, $list_method), 'invoke' => array($candidate, $invoke_method));
					}
				}
			}
		}

		if (is_array($candidate) && isset($candidate[0]) && $candidate[0] === 'function' && function_exists($candidate[1]) && function_exists($candidate[2])) {
			return array('name' => $candidate[3], 'list' => $candidate[1], 'invoke' => $candidate[2]);
		}

		if (is_array($candidate) && isset($candidate[0]) && $candidate[0] === 'class' && class_exists($candidate[1])) {
			$object = $this->resolve_object($candidate[1], $candidate[2][0]);
			if ($object && method_exists($object, $candidate[2][1]) && method_exists($object, $candidate[3][1])) {
				return array('name' => $candidate[4], 'list' => array($object, $candidate[2][1]), 'invoke' => array($object, $candidate[3][1]));
			}
		}

		return new WP_Error('ability_provider_missing', __('No ability provider is available.', 'ai-post-scheduler'));
	}

	/**
	 * Resolve singleton-style object.
	 *
	 * @param string $class  Class name.
	 * @param string $method Static resolver method.
	 * @return object|null
	 */
	private function resolve_object($class, $method) {
		if (method_exists($class, $method)) {
			return call_user_func(array($class, $method));
		}

		return null;
	}

	/**
	 * Normalize provider abilities into slug-keyed arrays.
	 *
	 * @param mixed $response Raw response.
	 * @return array|WP_Error
	 */
	private function normalize_abilities($response) {
		if (is_wp_error($response)) {
			return $response;
		}

		if (!is_array($response)) {
			return new WP_Error('ability_response_malformed', __('Ability provider returned a malformed ability list.', 'ai-post-scheduler'));
		}

		$normalized = array();

		foreach ($response as $key => $ability) {
			if (is_string($ability)) {
				$slug = $this->normalize_slug($ability);
				$ability = array('slug' => $slug);
			} elseif (is_object($ability)) {
				$ability = get_object_vars($ability);
			}

			if (!is_array($ability)) {
				continue;
			}

			$slug = isset($ability['slug']) ? $this->normalize_slug($ability['slug']) : $this->normalize_slug($key);
			if ($slug === '') {
				continue;
			}

			$normalized[$slug] = array_merge(array('slug' => $slug), $ability);
		}

		return $normalized;
	}

	/**
	 * Normalize invocation response to an array.
	 *
	 * @param mixed $response Raw response.
	 * @return array|WP_Error
	 */
	private function normalize_response($response) {
		if (is_wp_error($response)) {
			return $response;
		}

		if (is_object($response)) {
			$response = get_object_vars($response);
		}

		if (is_array($response)) {
			if (isset($response['error']) && !empty($response['error'])) {
				$message = is_string($response['error']) ? $response['error'] : wp_json_encode($response['error']);
				return new WP_Error('ability_invocation_failed', $message);
			}

			return $response;
		}

		if (is_scalar($response)) {
			return array('content' => $response);
		}

		return new WP_Error('ability_response_malformed', __('Ability provider returned a malformed response.', 'ai-post-scheduler'));
	}

	/**
	 * Normalize a slug.
	 *
	 * @param mixed $slug Raw slug.
	 * @return string
	 */
	private function normalize_slug($slug) {
		if (!is_scalar($slug)) {
			return '';
		}

		$slug = strtolower(trim((string) $slug));

		return preg_replace('/[^a-z0-9_\-\.\:\/]/', '', $slug);
	}

	private function log_request($operation, $slug, $payload, $context = array()) {
		$this->logger->log('Ability service request', 'info', array_merge(array('operation' => $operation, 'slug' => $slug, 'payload_keys' => is_array($payload) ? array_keys($payload) : array()), $context));
	}

	private function log_response($operation, $slug, $payload, $response) {
		$context = array('operation' => $operation, 'slug' => $slug, 'payload_keys' => is_array($payload) ? array_keys($payload) : array());

		if (is_wp_error($response)) {
			$context['error_code'] = $response->get_error_code();
			$context['error_message'] = $response->get_error_message();
			$this->logger->log('Ability service response error', 'error', $context);
			return;
		}

		$context['response_keys'] = is_array($response) ? array_keys($response) : array();
		$this->logger->log('Ability service response', 'info', $context);
	}
}
