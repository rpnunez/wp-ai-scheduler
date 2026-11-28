<?php
/**
 * Content Audit Generation Context
 *
 * Wraps a single AI audit pass (topic gaps, cannibalization, decay, source
 * trends, or legacy gap fallback) for AIPS_Content_Audit_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Audit_Generation_Context
 */
class AIPS_Content_Audit_Generation_Context extends AIPS_Abstract_Generation_Context {

	/**
	 * @var string Audit pass identifier (e.g. 'topic_gaps', 'cannibalization', 'decay', 'source_trends', 'gap_fallback').
	 */
	private $pass_type;

	/**
	 * @var string Prompt to send to AI.
	 */
	private $prompt;

	/**
	 * @var array Primary AI call options (temperature, json_schema, etc.).
	 */
	private $ai_options;

	/**
	 * @var float Fallback temperature for generate_text() when generate_json() fails.
	 */
	private $fallback_temperature;

	/**
	 * @var bool When true, uses generate_text() directly instead of generate_json() with text fallback.
	 */
	private $text_only;

	/**
	 * Constructor.
	 *
	 * @param string $pass_type            Audit pass name.
	 * @param string $prompt               Prompt string.
	 * @param array  $ai_options           AI provider options.
	 * @param float  $fallback_temperature Temperature for text fallback.
	 * @param bool   $text_only            Use text mode directly.
	 */
	public function __construct(
		string $pass_type,
		string $prompt,
		array $ai_options = array(),
		float $fallback_temperature = 0.5,
		bool $text_only = false
	) {
		$this->pass_type            = sanitize_key($pass_type);
		$this->prompt               = $prompt;
		$this->ai_options           = $ai_options;
		$this->fallback_temperature = $fallback_temperature;
		$this->text_only            = $text_only;
	}

	/**
	 * Return audit pass key.
	 *
	 * @return string
	 */
	public function get_pass_type(): string {
		return $this->pass_type;
	}

	/**
	 * Return prompt string.
	 *
	 * @return string
	 */
	public function get_prompt(): string {
		return $this->prompt;
	}

	/**
	 * Return AI options.
	 *
	 * @return array
	 */
	public function get_ai_options(): array {
		return $this->ai_options;
	}

	/**
	 * Return fallback temperature.
	 *
	 * @return float
	 */
	public function get_fallback_temperature(): float {
		return $this->fallback_temperature;
	}

	/**
	 * Whether this pass uses generate_text() directly.
	 *
	 * @return bool
	 */
	public function is_text_only(): bool {
		return $this->text_only;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_type() {
		return 'content_audit';
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
		return $this->pass_type;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_content_prompt() {
		return $this->prompt;
	}

	/**
	 * {@inheritdoc}
	 */
	public function to_array() {
		return array(
			'type'      => $this->get_type(),
			'pass_type' => $this->pass_type,
			'text_only' => $this->text_only,
		);
	}
}
