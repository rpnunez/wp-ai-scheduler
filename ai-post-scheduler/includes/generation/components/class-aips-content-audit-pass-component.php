<?php
/**
 * Content Audit Pass Component
 *
 * Unifies structured JSON execution with text-parsing fallback for Content
 * Auditor AI passes (topic gaps, cannibalization, content decay, source
 * trends, and gap fallback).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Audit_Pass_Component
 */
class AIPS_Content_Audit_Pass_Component implements AIPS_Generation_Component_Interface {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null $ai_service Optional AI service.
	 */
	public function __construct(?AIPS_AI_Service_Interface $ai_service = null) {
		$container        = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'content_audit_pass';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Content Audit Pass', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_strategy_key(): string {
		return 'content_audit';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool {
		return $context instanceof AIPS_Content_Audit_Generation_Context;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		return ($context instanceof AIPS_Content_Audit_Generation_Context) ? $context->get_prompt() : '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		if (!($context instanceof AIPS_Content_Audit_Generation_Context)) {
			return new WP_Error('invalid_audit_context', __('Content audit context is required.', 'ai-post-scheduler'));
		}

		$prompt     = $context->get_prompt();
		$ai_options = $context->get_ai_options();

		if ($context->is_text_only()) {
			return $this->ai_service->generate_text($prompt, $ai_options);
		}

		$result = $this->ai_service->generate_json($prompt, $ai_options);
		if (!is_wp_error($result) && is_array($result)) {
			return $result;
		}

		$text_res = $this->ai_service->generate_text($prompt, array(
			'temperature' => $context->get_fallback_temperature(),
		));
		if (is_wp_error($text_res)) {
			return $text_res;
		}

		$parsed = $this->parse_json_fallback((string) $text_res);
		if (is_wp_error($parsed)) {
			return $parsed;
		}

		return $parsed;
	}

	/**
	 * Parse JSON from a raw text response, stripping optional Markdown fences.
	 *
	 * @param string $response Raw text response.
	 * @return array|WP_Error
	 */
	public function parse_json_fallback(string $response) {
		$text = trim($response);
		if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $text, $matches)) {
			$text = trim($matches[1]);
		}

		$decoded = json_decode($text, true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
			return $decoded;
		}

		if (preg_match('/(\[[\s\S]*\]|\{[\s\S]*\})/', $text, $matches)) {
			$decoded = json_decode($matches[1], true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				return $decoded;
			}
		}

		return new WP_Error('audit_json_parse_error', __('Failed to parse JSON response from AI.', 'ai-post-scheduler'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		return $this->generate($context, $session, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply($target, $value): bool {
		return is_array($value) || is_string($value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_ai_edit(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		return $value;
	}
}
