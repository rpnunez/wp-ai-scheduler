<?php
/**
 * AI Assistance Generation Context
 *
 * Wraps admin form field configuration, browser session ID, and user ID for
 * AIPS_AI_Assistance_Strategy and AIPS_Field_Suggestion_Component.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_AI_Assistance_Generation_Context
 */
class AIPS_AI_Assistance_Generation_Context extends AIPS_Abstract_Generation_Context {

	/**
	 * @var array<string, mixed>
	 */
	private $field_config;

	/**
	 * @var string
	 */
	private $session_id;

	/**
	 * @var int
	 */
	private $user_id;

	/**
	 * Constructor.
	 *
	 * @param array  $field_config Field configuration map.
	 * @param string $session_id   Browser session identifier.
	 * @param int    $user_id      WordPress user ID.
	 */
	public function __construct(array $field_config, string $session_id = '', int $user_id = 0) {
		$this->field_config = $field_config;
		$this->session_id   = $session_id;
		$this->user_id      = $user_id;
	}

	/**
	 * Return the raw field configuration map.
	 *
	 * @return array<string, mixed>
	 */
	public function get_field_config(): array {
		return $this->field_config;
	}

	/**
	 * Return the form context identifier (e.g. 'authors', 'templates', 'sources', 'content_strategy').
	 *
	 * @return string
	 */
	public function get_form_context(): string {
		return isset($this->field_config['form_context']) ? (string) $this->field_config['form_context'] : '';
	}

	/**
	 * Return the target form field HTML ID / key.
	 *
	 * @return string
	 */
	public function get_form_field_id(): string {
		return isset($this->field_config['form_field_id']) ? (string) $this->field_config['form_field_id'] : '';
	}

	/**
	 * Return the human-readable field label.
	 *
	 * @return string
	 */
	public function get_field_name(): string {
		return isset($this->field_config['field_name']) ? (string) $this->field_config['field_name'] : '';
	}

	/**
	 * Return the browser session identifier.
	 *
	 * @return string
	 */
	public function get_session_id(): string {
		return $this->session_id;
	}

	/**
	 * Return the WordPress user ID.
	 *
	 * @return int
	 */
	public function get_user_id(): int {
		return $this->user_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_type() {
		return 'ai_assistance';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id() {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name() {
		return $this->get_field_name();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_content_prompt() {
		return isset($this->field_config['description']) ? (string) $this->field_config['description'] : '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function to_array() {
		return array(
			'type'         => $this->get_type(),
			'field_config' => $this->field_config,
			'session_id'   => $this->session_id,
			'user_id'      => $this->user_id,
		);
	}
}
