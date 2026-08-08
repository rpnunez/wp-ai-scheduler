<?php
/**
 * Integration Manager
 *
 * Orchestrates the third-party plugin bridge: for a generated post, loads
 * the field mappings saved against its Template, resolves the relevant
 * AIPS_Integration_Interface adapter(s) via AIPS_Integration_Registry,
 * generates a value per mapped field, and writes each value back through
 * the adapter.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Integration_Manager {

	/**
	 * Field shapes this manager knows how to generate text for. Boolean,
	 * structured-list (repeater/flexible content), and choice (select/radio —
	 * generation would need the field's defined options passed to the AI and
	 * the output validated against them, not implemented yet) shapes are
	 * schema-discoverable today but generation for them is deferred to a
	 * later phase.
	 *
	 * @var array<int, string>
	 */
	private static $generatable_shapes = array(
		AIPS_Integration_Interface::SHAPE_SHORT_TEXT,
		AIPS_Integration_Interface::SHAPE_LONG_TEXT,
		AIPS_Integration_Interface::SHAPE_HTML,
	);

	/**
	 * @var AIPS_Integration_Mappings_Repository
	 */
	private $mappings_repository;

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Integration_Field_Prompt_Builder
	 */
	private $prompt_builder;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @param AIPS_Integration_Mappings_Repository|null  $mappings_repository Optional (injectable for tests).
	 * @param AIPS_AI_Service_Interface|null              $ai_service          Optional (injectable for tests).
	 * @param AIPS_Integration_Field_Prompt_Builder|null  $prompt_builder      Optional (injectable for tests).
	 * @param AIPS_Logger|null                            $logger              Optional (injectable for tests).
	 */
	public function __construct($mappings_repository = null, $ai_service = null, $prompt_builder = null, $logger = null) {
		$container = AIPS_Container::get_instance();

		$this->mappings_repository = $mappings_repository ?: new AIPS_Integration_Mappings_Repository();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->prompt_builder = $prompt_builder ?: new AIPS_Integration_Field_Prompt_Builder();
		$this->logger = $logger ?: new AIPS_Logger();
	}

	/**
	 * Handle the 'aips_post_generated' action: generate and apply any mapped
	 * third-party fields for the Template that drove this post.
	 *
	 * MVP scope: only template-driven generation carries field mappings
	 * (mappings are keyed by template_id). Topic/planner/research-driven
	 * generation is not covered yet.
	 *
	 * @param int                       $post_id             Generated post ID.
	 * @param object                    $template_or_context Template object or context (unused; kept for hook signature parity).
	 * @param int|string|null           $history_id           History entry ID (unused here).
	 * @param AIPS_Generation_Context   $context              Generation context that drove this post.
	 * @return void
	 */
	public function handle_post_generated($post_id, $template_or_context, $history_id, $context) {
		if (!($context instanceof AIPS_Generation_Context) || $context->get_type() !== 'template') {
			return;
		}

		$template_id = $context->get_id();

		if (!$template_id) {
			return;
		}

		$mappings = $this->mappings_repository->get_by_template($template_id);

		if (empty($mappings)) {
			return;
		}

		$by_integration = array();
		foreach ($mappings as $mapping) {
			$by_integration[$mapping->integration_id][] = $mapping;
		}

		foreach ($by_integration as $integration_id => $integration_mappings) {
			$this->apply_integration($integration_id, $integration_mappings, $post_id, $context);
		}
	}

	/**
	 * Handle the 'aips_template_changed' action: clean up field mappings when
	 * their owning Template is deleted.
	 *
	 * @param array $args {
	 *     @type string $action      One of 'created', 'updated', 'cloned', 'deleted'.
	 *     @type int    $template_id Template ID.
	 * }
	 * @return void
	 */
	public function handle_template_deleted($args) {
		if (!is_array($args) || !isset($args['action'], $args['template_id']) || $args['action'] !== 'deleted') {
			return;
		}

		$this->mappings_repository->delete_by_template((int) $args['template_id']);
	}

	/**
	 * Generate and write every mapped field for one integration.
	 *
	 * Generatable-shape fields are generated in a single batched
	 * generate_json() call (see apply_generatable_batch()) rather than one
	 * AI call per field — for a template with several mapped fields this
	 * cuts N round-trips down to one, reduces repeated context/token
	 * overhead, and lets the model keep the fields consistent with each
	 * other. Unsupported-shape fields never reach the AI at all.
	 *
	 * @param string                  $integration_id      Integration identifier.
	 * @param array<int, object>      $integration_mappings Mapping rows for this integration.
	 * @param int                     $post_id              Target post ID.
	 * @param AIPS_Generation_Context $context              Generation context.
	 * @return void
	 */
	private function apply_integration($integration_id, $integration_mappings, $post_id, $context) {
		$adapter = AIPS_Integration_Registry::get($integration_id);

		if (!$adapter instanceof AIPS_Integration_Interface || !$adapter->is_available()) {
			$this->logger->log(
				sprintf('AIPS_Integration_Manager: skipping "%s" — adapter not available on this site.', $integration_id),
				'warning',
				array('post_id' => $post_id)
			);
			return;
		}

		$type_map = $adapter->get_supported_field_types();
		$field_defs_by_source = array();
		$generatable = array();
		$results = array();

		foreach ($integration_mappings as $mapping) {
			$shape = isset($type_map[$mapping->field_type]) ? $type_map[$mapping->field_type] : '';

			if (!in_array($shape, self::$generatable_shapes, true)) {
				$error = new WP_Error(
					'unsupported_shape',
					sprintf(
						/* translators: %s: native field type. */
						__('Field type "%s" is not yet supported for generation.', 'ai-post-scheduler'),
						$mapping->field_type
					)
				);
				$this->logger->log($error->get_error_message(), 'notice', array('post_id' => $post_id, 'field_key' => $mapping->field_key));
				$results[$mapping->field_key] = $error;
				continue;
			}

			$generatable[] = array(
				'mapping'   => $mapping,
				'shape'     => $shape,
				'field_def' => $this->resolve_field_def($adapter, $mapping, $field_defs_by_source),
			);
		}

		if (!empty($generatable)) {
			$results = array_merge($results, $this->apply_generatable_batch($adapter, $generatable, $post_id, $context));
		}

		/**
		 * Fires after a batch of integration fields has been generated and
		 * written for a post.
		 *
		 * @param int    $post_id        Post the fields were written to.
		 * @param string $integration_id Integration identifier (e.g. 'acf').
		 * @param array  $results        field_key => true|WP_Error.
		 */
		do_action('aips_integration_fields_applied', $post_id, $integration_id, $results);
	}

	/**
	 * Resolve a field's live definition (for its ACF "instructions" text)
	 * from the adapter, memoized per source_key so a group of N mapped
	 * fields only costs one get_fields() call, not N.
	 *
	 * @param AIPS_Integration_Interface $adapter              Resolved integration adapter.
	 * @param object                     $mapping              Mapping row.
	 * @param array                      $field_defs_by_source Memoization cache, keyed by source_key then field_key.
	 * @return array Field definition with 'key', 'label', 'native_type', 'instructions'.
	 */
	private function resolve_field_def($adapter, $mapping, &$field_defs_by_source) {
		if (!isset($field_defs_by_source[$mapping->source_key])) {
			$indexed = array();
			foreach ($adapter->get_fields($mapping->source_key) as $field) {
				if (isset($field['key'])) {
					$indexed[$field['key']] = $field;
				}
			}
			$field_defs_by_source[$mapping->source_key] = $indexed;
		}

		$live = isset($field_defs_by_source[$mapping->source_key][$mapping->field_key])
			? $field_defs_by_source[$mapping->source_key][$mapping->field_key]
			: array();

		return array(
			'key'          => $mapping->field_key,
			'label'        => $mapping->field_label,
			'native_type'  => $mapping->field_type,
			'instructions' => isset($live['instructions']) ? $live['instructions'] : '',
		);
	}

	/**
	 * Generate every generatable field's value in one batched
	 * generate_json() call and write each one back. Falls back to one
	 * generate_text() call per field if the batch call itself fails, so a
	 * single transient AI error doesn't lose every field in the group.
	 *
	 * @param AIPS_Integration_Interface $adapter     Resolved integration adapter.
	 * @param array                       $generatable List of {mapping, shape, field_def} for generatable fields.
	 * @param int                         $post_id     Target post ID.
	 * @param AIPS_Generation_Context     $context     Generation context.
	 * @return array<string, true|WP_Error> field_key => outcome.
	 */
	private function apply_generatable_batch($adapter, $generatable, $post_id, $context) {
		$prompt = $this->prompt_builder->build_batch($generatable, $context);
		$batch_response = $this->ai_service->generate_json($prompt);

		if (is_wp_error($batch_response)) {
			$this->logger->log(
				sprintf(
					'AIPS_Integration_Manager: batch generation failed — %s. Falling back to one call per field.',
					$batch_response->get_error_message()
				),
				'warning',
				array('post_id' => $post_id)
			);
			return $this->apply_fields_individually($adapter, $generatable, $post_id, $context);
		}

		$results = array();

		foreach ($generatable as $item) {
			$field_key = $item['mapping']->field_key;

			if (!is_array($batch_response) || !array_key_exists($field_key, $batch_response)) {
				$error = new WP_Error(
					'missing_batch_value',
					sprintf(
						/* translators: %s: field key. */
						__('The AI response did not include a value for field "%s".', 'ai-post-scheduler'),
						$field_key
					)
				);
				$this->logger->log($error->get_error_message(), 'warning', array('post_id' => $post_id));
				$results[$field_key] = $error;
				continue;
			}

			$results[$field_key] = $this->write_field($adapter, $item['mapping'], $item['shape'], $batch_response[$field_key], $post_id);
		}

		return $results;
	}

	/**
	 * Fallback path: generate and write each field with its own
	 * generate_text() call. Used only when the batched call itself fails.
	 *
	 * @param AIPS_Integration_Interface $adapter     Resolved integration adapter.
	 * @param array                       $generatable List of {mapping, shape, field_def} for generatable fields.
	 * @param int                         $post_id     Target post ID.
	 * @param AIPS_Generation_Context     $context     Generation context.
	 * @return array<string, true|WP_Error> field_key => outcome.
	 */
	private function apply_fields_individually($adapter, $generatable, $post_id, $context) {
		$results = array();

		foreach ($generatable as $item) {
			$mapping = $item['mapping'];
			$prompt = $this->prompt_builder->build($item['field_def'], $context, $mapping->custom_prompt);
			$value = $this->ai_service->generate_text($prompt);

			if (is_wp_error($value)) {
				$this->logger->log(
					sprintf('AIPS_Integration_Manager: generation failed for field "%s" — %s', $mapping->field_key, $value->get_error_message()),
					'warning',
					array('post_id' => $post_id)
				);
				$results[$mapping->field_key] = $value;
				continue;
			}

			$results[$mapping->field_key] = $this->write_field($adapter, $mapping, $item['shape'], $value, $post_id);
		}

		return $results;
	}

	/**
	 * Sanitize a generated value per field shape and write it via the adapter.
	 *
	 * @param AIPS_Integration_Interface $adapter   Resolved integration adapter.
	 * @param object                     $mapping   Mapping row.
	 * @param string                     $shape     Field shape (one of AIPS_Integration_Interface::SHAPE_*).
	 * @param mixed                      $raw_value Raw generated value.
	 * @param int                        $post_id   Target post ID.
	 * @return true|WP_Error
	 */
	private function write_field($adapter, $mapping, $shape, $raw_value, $post_id) {
		$value = $shape === AIPS_Integration_Interface::SHAPE_HTML
			? wp_kses_post(trim((string) $raw_value))
			: sanitize_textarea_field(trim((string) $raw_value));

		$write_result = $adapter->write_field_value($post_id, $mapping->field_key, $value);

		if (is_wp_error($write_result)) {
			$this->logger->log(
				sprintf('AIPS_Integration_Manager: write failed for field "%s" — %s', $mapping->field_key, $write_result->get_error_message()),
				'warning',
				array('post_id' => $post_id)
			);
			return $write_result;
		}

		return true;
	}
}
