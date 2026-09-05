<?php
/**
 * AI Assistance Repository
 *
 * Database abstraction layer for AI field suggestion history.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.1
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

if (!trait_exists('AIPS_Repository_Tables')) {
	require_once __DIR__ . '/trait-aips-repository-tables.php';
}

/**
 * Class AIPS_AI_Assistance_Repository
 *
 * Repository pattern implementation for AI assistance data access.
 * Encapsulates all database operations related to AI field suggestions.
 */
class AIPS_AI_Assistance_Repository {
	use AIPS_Cacheable_Repository;
	use AIPS_Repository_Tables;

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * @var string The ai_assistance table name (with prefix).
	 */
	private $table_name;

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $this->table('aips_ai_assistance');
	}

	/**
	 * Insert a new AI assistance record.
	 *
	 * @param array $data {
	 *     Record data.
	 *     @type string   $session_id      Browser session identifier.
	 *     @type int|null $user_id         WordPress user ID, or null.
	 *     @type string   $form_context    Form identifier (e.g. 'authors').
	 *     @type string   $field_key       Field HTML ID (e.g. 'author_name').
	 *     @type string   $request_object  JSON-encoded request payload.
	 *     @type string   $prompt          Full prompt sent to the AI.
	 *     @type string   $response        AI-generated response text.
	 * }
	 * @return int|false New record ID on success, false on failure.
	 */
	public function create( array $data ) {
		$result = $this->wpdb->insert(
			$this->table_name,
			array(
				'session_id'     => sanitize_text_field( $data['session_id'] ),
				'user_id'        => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : null,
				'form_context'   => sanitize_text_field( $data['form_context'] ),
				'field_key'      => sanitize_text_field( $data['field_key'] ),
				'request_object' => isset( $data['request_object'] ) ? $data['request_object'] : '',
				'prompt'         => $data['prompt'],
				'response'       => $data['response'],
				'created_at'     => AIPS_DateTime::now()->timestamp(),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( $result ) {
			$this->invalidate_cache_tags( array( 'ai_assistance' ), 'ai_assistance_created' );
		}

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Get AI suggestions for a given session and field, newest first.
	 *
	 * @param string $session_id   Browser session identifier.
	 * @param string $form_context Form identifier.
	 * @param string $field_key    Field HTML ID.
	 * @param int    $limit        Maximum number of records to return. Default 15.
	 * @return array Array of record objects.
	 */
	public function get_by_session_and_field( string $session_id, string $form_context, string $field_key, int $limit = 15 ): array {
		return $this->cache_read(
			'ai_assistance.get_by_session_and_field',
			array(
				'session_id'   => $session_id,
				'form_context' => $form_context,
				'field_key'    => $field_key,
				'limit'        => $limit,
			),
			function() use ( $session_id, $form_context, $field_key, $limit ) {
				return $this->wpdb->get_results(
					$this->wpdb->prepare(
						"SELECT id, session_id, form_context, field_key, response, created_at FROM {$this->table_name}
						WHERE session_id = %s
						AND form_context = %s
						AND field_key = %s
						ORDER BY created_at DESC
						LIMIT %d",
						$session_id,
						$form_context,
						$field_key,
						$limit
					)
				);
			}
		);
	}

	/**
	 * Get all-time AI suggestions for a field, newest first.
	 *
	 * @param string $form_context Form identifier.
	 * @param string $field_key    Field HTML ID.
	 * @param int    $limit        Maximum number of records to return. Default 20.
	 * @return array Array of record objects.
	 */
	public function get_by_field( string $form_context, string $field_key, int $limit = 20 ): array {
		return $this->cache_read(
			'ai_assistance.get_by_field',
			array(
				'form_context' => $form_context,
				'field_key'    => $field_key,
				'limit'        => $limit,
			),
			function() use ( $form_context, $field_key, $limit ) {
				return $this->wpdb->get_results(
					$this->wpdb->prepare(
						"SELECT id, session_id, form_context, field_key, response, created_at FROM {$this->table_name}
						WHERE form_context = %s
						AND field_key = %s
						ORDER BY created_at DESC
						LIMIT %d",
						$form_context,
						$field_key,
						$limit
					)
				);
			}
		);
	}

	/**
	 * Return the repository cache group for AI assistance reads.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_ai_assistance';
	}

	/**
	 * Return the explicit repository cache policies for AI assistance reads.
	 *
	 * Both suggestion-history reads are cached under the broad `ai_assistance` tag
	 * that create() invalidates.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'ai_assistance.get_by_session_and_field' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'ai_assistance' ),
				'description' => 'Cache per-session, per-field suggestion history reads.',
			),
			'ai_assistance.get_by_field' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'ai_assistance' ),
				'description' => 'Cache all-time per-field suggestion history reads.',
			),
		);
	}
}
