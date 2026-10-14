<?php
/**
 * Ability Enrichment Service
 *
 * Optional post-generation enrichment driven by WordPress Abilities. After a
 * post has been generated and saved, each registered enrichment step may call
 * an installed ability (for example the WordPress AI plugin's
 * `ai/alt-text-generation`) and write the result back to the post.
 *
 * Design rules:
 *  - Opt-in: nothing runs unless `aips_enable_ability_enrichment` is on.
 *  - Never affects generation: every failure is logged and swallowed.
 *  - Never bypasses WP_Ability permission checks: steps run as a real user
 *    (the current user if they can edit the post, otherwise the configured
 *    service user) for the duration of the step only.
 *  - Payloads are trimmed to the ability's registered input schema, so the
 *    scheduler never sends keys an ability does not declare.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Ability_Enrichment_Service {

	/**
	 * Slug of the WordPress AI alt-text ability.
	 */
	const ALT_TEXT_ABILITY = 'ai/alt-text-generation';

	/**
	 * @var AIPS_Ability_Service
	 */
	private $abilities;

	/**
	 * @var AIPS_Ability_Catalog_Service
	 */
	private $catalog;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * @param AIPS_Ability_Service|null         $abilities Ability adapter.
	 * @param AIPS_Ability_Catalog_Service|null $catalog   Ability catalog.
	 * @param AIPS_Logger_Interface|null        $logger    Logger.
	 */
	public function __construct(?AIPS_Ability_Service $abilities = null, ?AIPS_Ability_Catalog_Service $catalog = null, ?AIPS_Logger_Interface $logger = null) {
		$container       = AIPS_Container::get_instance();
		$this->abilities = $abilities ?: $container->make(AIPS_Ability_Service::class);
		$this->catalog   = $catalog ?: $container->make(AIPS_Ability_Catalog_Service::class);
		$this->logger    = $logger ?: ($container->has(AIPS_Logger_Interface::class)
			? $container->make(AIPS_Logger_Interface::class)
			: AIPS_Logger::instance());
	}

	/**
	 * Whether post-generation enrichment is switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) AIPS_Config::get_instance()->get_option('aips_enable_ability_enrichment');
	}

	/**
	 * `aips_post_generated` listener. Never throws.
	 *
	 * @param int $post_id Generated post ID.
	 * @return void
	 */
	public function handle_post_generated($post_id) {
		$post_id = absint($post_id);

		if ($post_id <= 0 || !self::is_enabled()) {
			return;
		}

		try {
			$this->enrich_post($post_id);
		} catch (Throwable $e) {
			$this->logger->log('Ability enrichment aborted: ' . $e->getMessage(), 'error', array('post_id' => $post_id));
		}
	}

	/**
	 * Run every enrichment step against a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,string> Step key => outcome (`applied`, `skipped:<reason>` or `error:<code>`).
	 */
	public function enrich_post($post_id) {
		$post_id = absint($post_id);
		$results = array();

		if ($post_id <= 0 || !get_post($post_id)) {
			return $results;
		}

		/**
		 * Filters the enrichment steps run after a post is generated.
		 *
		 * @param array<string,callable> $steps   Step key => callable( int $post_id ): string|WP_Error.
		 * @param int                    $post_id Generated post ID.
		 */
		$steps = apply_filters('aips_ability_enrichment_steps', array(
			'alt_text' => array($this, 'step_featured_image_alt_text'),
		), $post_id);

		$principal = $this->resolve_principal($post_id);

		if (!$principal) {
			$this->logger->log('Ability enrichment skipped: no user with permission to edit the post.', 'warning', array('post_id' => $post_id));
			return array('*' => 'skipped:no_principal');
		}

		$previous_user_id = get_current_user_id();
		wp_set_current_user($principal);

		try {
			foreach ((array) $steps as $key => $callback) {
				if (!is_callable($callback)) {
					continue;
				}

				$outcome = call_user_func($callback, $post_id);

				if (is_wp_error($outcome)) {
					$results[$key] = 'error:' . $outcome->get_error_code();
					$this->logger->log('Ability enrichment step failed.', 'warning', array(
						'post_id' => $post_id,
						'step'    => $key,
						'code'    => $outcome->get_error_code(),
						'message' => $outcome->get_error_message(),
					));
					continue;
				}

				$results[$key] = (string) $outcome;
			}
		} finally {
			wp_set_current_user($previous_user_id);
		}

		return $results;
	}

	/**
	 * Generate alt text for the post's featured image when it has none.
	 *
	 * @param int $post_id Post ID.
	 * @return string|WP_Error Outcome string, or WP_Error when the ability fails.
	 */
	public function step_featured_image_alt_text($post_id) {
		$attachment_id = (int) get_post_thumbnail_id($post_id);

		if ($attachment_id <= 0) {
			return 'skipped:no_featured_image';
		}

		if ('' !== trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true))) {
			return 'skipped:alt_text_present';
		}

		if (true !== $this->abilities->is_available(self::ALT_TEXT_ABILITY)) {
			return 'skipped:ability_unavailable';
		}

		$payload = $this->build_payload(self::ALT_TEXT_ABILITY, array(
			'attachment_id' => $attachment_id,
			'image_url'     => (string) wp_get_attachment_url($attachment_id),
			'context'       => get_the_title($post_id),
		), array('attachment_id', 'image_url'));

		$response = $this->abilities->invoke(self::ALT_TEXT_ABILITY, $payload);

		if (is_wp_error($response)) {
			return $response;
		}

		$alt = $this->extract_string($response, array('alt_text', 'alt'));

		if ('' === $alt) {
			return new WP_Error('ability_enrichment_empty_output', __('The alt text ability returned no alt text.', 'ai-post-scheduler'));
		}

		update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));

		return 'applied';
	}

	/**
	 * Pick the user steps run as: the current user when they may edit the
	 * post, otherwise the configured service user (re-validated every run).
	 *
	 * @param int $post_id Post ID.
	 * @return int User ID, or 0 when nobody qualifies.
	 */
	private function resolve_principal($post_id) {
		$current = get_current_user_id();

		if ($current > 0 && user_can($current, 'edit_post', $post_id)) {
			return $current;
		}

		/**
		 * Filters the service user for post-generation ability enrichment.
		 *
		 * @param int $user_id Configured service user ID (default from settings).
		 * @param int $post_id Generated post ID.
		 */
		$service_id = (int) apply_filters(
			'aips_ability_enrichment_user_id',
			(int) AIPS_Config::get_instance()->get_option('aips_ability_enrichment_user_id'),
			$post_id
		);

		if ($service_id > 0 && get_userdata($service_id) && user_can($service_id, 'edit_post', $post_id)) {
			return $service_id;
		}

		return 0;
	}

	/**
	 * Trim a candidate payload to the keys the ability's input schema declares.
	 *
	 * When the schema declares none of the preferred keys, the first preferred
	 * key is kept so the ability can still report its own validation error.
	 * With no schema available, only the preferred keys are sent.
	 *
	 * @param string   $slug      Ability slug.
	 * @param array    $candidate Candidate payload.
	 * @param string[] $preferred Keys to fall back to when the schema is unknown.
	 * @return array
	 */
	private function build_payload($slug, array $candidate, array $preferred) {
		$schema     = $this->catalog->get_input_schema($slug);
		$properties = (is_array($schema) && isset($schema['properties']) && is_array($schema['properties']))
			? array_keys($schema['properties'])
			: array();

		if (empty($properties)) {
			return array_intersect_key($candidate, array_flip(array_slice($preferred, 0, 1)));
		}

		$payload = array_intersect_key($candidate, array_flip($properties));

		return empty($payload) ? array_intersect_key($candidate, array_flip(array_slice($preferred, 0, 1))) : $payload;
	}

	/**
	 * Read the first non-empty string among the given keys from an ability response.
	 *
	 * @param mixed    $response Normalized response.
	 * @param string[] $keys     Candidate keys.
	 * @return string
	 */
	private function extract_string($response, array $keys) {
		if (!is_array($response)) {
			return is_string($response) ? trim($response) : '';
		}

		foreach ($keys as $key) {
			if (isset($response[$key]) && is_string($response[$key]) && '' !== trim($response[$key])) {
				return trim($response[$key]);
			}
		}

		return '';
	}
}
