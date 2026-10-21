<?php
/**
 * AI Assistance Service
 *
 * Business logic for generating AI field suggestions.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_AI_Assistance_Service
 *
 * Builds prompts, calls the AI engine, and persists AI field suggestions.
 */
class AIPS_AI_Assistance_Service {

	/**
	 * @var AIPS_AI_Service AI service instance.
	 */
	private $ai_service;

	/**
	 * @var AIPS_AI_Assistance_Repository Repository instance.
	 */
	private $repository;

	/**
	 * Prompt preamble describing the kind of form being filled in, keyed by
	 * form_context. Falls back to a generic description for an unrecognised
	 * context so a new form never gets Author-specific wording by default.
	 *
	 * @var array<string, string>
	 */
	private $preambles = array(
		'authors'          => 'an AI author persona',
		'templates'        => 'an AI content-generation template',
		'sources'          => 'a tracked external content source',
		'content_strategy' => 'a site-wide AI content strategy',
	);

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service               $ai_service AI service.
	 * @param AIPS_AI_Assistance_Repository $repository Repository.
	 */
	public function __construct( $ai_service, AIPS_AI_Assistance_Repository $repository ) {
		$this->ai_service = $ai_service;
		$this->repository = $repository;
	}

	/**
	 * Generate an AI suggestion for a form field.
	 *
	 * @param array  $field_config {
	 *     Field configuration.
	 *     @type string $field_name        Human-readable field label.
	 *     @type string $form_field_id     HTML id of the field.
	 *     @type string $form_context      Form name (e.g. 'authors').
	 *     @type string $description       Purpose of the field.
	 *     @type string $influence         How this field influences AI content.
	 *     @type string $expected_response Format hint for the AI response.
	 *     @type string $current_value     Current value in the field.
	 *     @type array  $context           Optional: map of sibling field id => value,
	 *                                     as declared by that field's contextFields
	 *                                     entry in the form's field map.
	 * }
	 * @param string $session_id  Browser session identifier.
	 * @param int    $user_id     WordPress user ID.
	 * @return array|WP_Error Array with 'response', 'record_id', 'prompt', or WP_Error on failure.
	 */
	public function get_field_suggestion( array $field_config, string $session_id, int $user_id ) {
		$prompt = $this->build_prompt( $field_config );

		$response = $this->ai_service->generate_text( $prompt );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$record_id = $this->repository->create( array(
			'session_id'     => $session_id,
			'user_id'        => $user_id,
			'form_context'   => sanitize_text_field( $field_config['form_context'] ),
			'field_key'      => sanitize_text_field( $field_config['form_field_id'] ),
			'request_object' => wp_json_encode( $field_config ),
			'prompt'         => $prompt,
			'response'       => $response,
		) );

		if ( $record_id === false ) {
			return new WP_Error(
				'db_insert_failed',
				__( 'AI suggestion was generated but could not be saved to history.', 'ai-post-scheduler' )
			);
		}

		return array(
			'response'  => $response,
			'record_id' => $record_id,
			'prompt'    => $prompt,
		);
	}

	/**
	 * Build a structured prompt from the field configuration.
	 *
	 * @param array $field_config Field configuration (see get_field_suggestion()).
	 * @return string The prompt string.
	 */
	private function build_prompt( array $field_config ): string {
		$form_context = $field_config['form_context'] ?? '';
		$preamble     = $this->preambles[ $form_context ] ?? 'a WordPress admin form';

		$lines = array(
			'You are a helpful assistant for an AI content creation WordPress plugin.',
			'Help fill in a form field for ' . $preamble . '.',
			'',
			'Field: ' . ( $field_config['field_name'] ?? '' ),
			'Purpose: ' . ( $field_config['description'] ?? '' ),
			'How it influences AI content generation: ' . ( $field_config['influence'] ?? '' ),
			'Current value: ' . ( $field_config['current_value'] ?? '' ),
		);

		foreach ( (array) ( $field_config['context'] ?? array() ) as $context_key => $context_value ) {
			if ( '' === $context_value ) {
				continue;
			}
			$lines[] = ucwords( str_replace( array( '_', '-' ), ' ', $context_key ) ) . ': ' . $context_value;
		}

		$lines[] = '';
		$lines[] = 'Respond with ONLY the suggested value for this field. No explanation, no quotes, no prefix. Expected format: ' . ( $field_config['expected_response'] ?? '' );

		return implode( "\n", $lines );
	}
}
