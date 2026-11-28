<?php
/**
 * AI payload capture: provider decorator.
 *
 * Sits between AIPS_AI_Service and the real provider and records the canonical
 * parameters of every transport call. It adds nothing and changes nothing: each
 * call is forwarded to the wrapped provider as-is.
 *
 * Dev tooling only: loaded by bin/ai-payload-capture.php, never by the plugin.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Payload_Capture_Provider implements AIPS_AI_Provider_Interface {

	/** @var AIPS_AI_Provider_Interface */
	private $inner;

	/** @var AIPS_Payload_Capture_Recorder */
	private $recorder;

	public function __construct(AIPS_AI_Provider_Interface $inner, AIPS_Payload_Capture_Recorder $recorder) {
		$this->inner    = $inner;
		$this->recorder = $recorder;
	}

	/**
	 * @return AIPS_AI_Provider_Interface The real provider.
	 */
	public function get_inner() {
		return $this->inner;
	}

	/**
	 * Forward provider-specific public methods that are not part of the interface.
	 */
	public function __call($name, $arguments) {
		return $this->inner->$name(...$arguments);
	}

	public function get_id(): string {
		return $this->inner->get_id();
	}

	public function get_label(): string {
		return $this->inner->get_label();
	}

	public function is_available(): bool {
		return $this->inner->is_available();
	}

	public function get_unavailable_reason(): string {
		return $this->inner->get_unavailable_reason();
	}

	public function generate_text(string $prompt, array $params): string {
		return $this->record('generate_text', $prompt, $params);
	}

	public function generate_json(?string $prompt, array $params): ?array {
		return $this->record('generate_json', $prompt, $params);
	}

	public function generate_image(string $prompt, array $params): string {
		return $this->record('generate_image', $prompt, $params);
	}

	public function generate_embedding(string $text, array $params): array {
		return $this->record('generate_embedding', $text, $params);
	}

	public function supports_native_json(): bool {
		return $this->inner->supports_native_json();
	}

	public function supports_embeddings(): bool {
		return $this->inner->supports_embeddings();
	}

	public function supports_conversation(): bool {
		return $this->inner->supports_conversation();
	}

	public function extract_error_code(string $message): string {
		return $this->inner->extract_error_code($message);
	}

	/**
	 * Forward one transport call, recording what went in and what came out.
	 *
	 * @param string      $method Provider method.
	 * @param string|null $prompt Prompt or text.
	 * @param array       $params Canonical parameters.
	 * @return mixed
	 * @throws Throwable Whatever the wrapped provider throws.
	 */
	private function record($method, $prompt, array $params) {
		$handle = $this->recorder->open_provider_call($method, $prompt, $params);

		try {
			$result = $this->inner->$method($prompt, $params);
		} catch (Throwable $e) {
			$this->recorder->close_provider_call($handle, null, $e);

			throw $e;
		}

		$this->recorder->close_provider_call($handle, $result);

		return $result;
	}
}
