<?php
/**
 * Shared Fake AI Provider for Mirror Golden Tests
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('AIPS_Fake_AI_Provider')) {

	/**
	 * Class AIPS_Fake_AI_Provider
	 */
	class AIPS_Fake_AI_Provider implements AIPS_AI_Provider_Interface {

		/**
		 * @var array Recorded AI provider calls.
		 */
		public array $calls = array();

		/**
		 * @var array Scripted responses by call index or turn.
		 */
		private array $scripted_responses = array();

		/**
		 * @var \Throwable|null Exception or Error to throw on generate call.
		 */
		private ?\Throwable $error_to_throw = null;

		/**
		 * Set canned responses to return sequentially.
		 *
		 * @param array $responses Canned response strings or arrays.
		 * @return void
		 */
		public function set_scripted_responses(array $responses): void {
			$this->scripted_responses = array_values($responses);
		}

		/**
		 * Script provider to throw an exception on generate call.
		 *
		 * @param \Throwable $throwable Exception to throw.
		 * @return void
		 */
		public function set_error_to_throw(\Throwable $throwable): void {
			$this->error_to_throw = $throwable;
		}

		/**
		 * Get list of recorded calls.
		 *
		 * @return array
		 */
		public function get_calls(): array {
			return $this->calls;
		}

		/**
		 * Stable machine identifier for this provider.
		 *
		 * @return string
		 */
		public function get_id(): string {
			return 'fake_provider';
		}

		/**
		 * Human-readable label.
		 *
		 * @return string
		 */
		public function get_label(): string {
			return 'Fake AI Provider';
		}

		/**
		 * Check if provider is available.
		 *
		 * @return bool
		 */
		public function is_available(): bool {
			return true;
		}

		/**
		 * Explain why provider cannot currently serve requests.
		 *
		 * @return string
		 */
		public function get_unavailable_reason(): string {
			return '';
		}

		/**
		 * Generate text from a prompt.
		 *
		 * @param string $prompt Prompt text.
		 * @param array  $params Canonical parameters.
		 * @return string
		 * @throws Exception On scripted failure.
		 */
		public function generate_text(string $prompt, array $params): string {
			if ($this->error_to_throw !== null) {
				throw $this->error_to_throw;
			}

			$index = count($this->calls);

			$this->calls[] = array(
				'index'  => $index,
				'type'   => 'text',
				'prompt' => $prompt,
				'params' => $params,
			);

			if (isset($this->scripted_responses[$index])) {
				return (string) $this->scripted_responses[$index];
			}

			return 'Canned response #' . ($index + 1);
		}

		/**
		 * Generate structured JSON output natively.
		 *
		 * @param string|null $prompt Prompt text.
		 * @param array       $params Canonical parameters.
		 * @return array|null
		 */
		public function generate_json(?string $prompt, array $params): ?array {
			if ($this->error_to_throw !== null) {
				throw $this->error_to_throw;
			}

			$index = count($this->calls);

			$this->calls[] = array(
				'index'  => $index,
				'type'   => 'json',
				'prompt' => $prompt,
				'params' => $params,
			);

			if (isset($this->scripted_responses[$index]) && is_array($this->scripted_responses[$index])) {
				return $this->scripted_responses[$index];
			}

			return null;
		}

		/**
		 * Generate an image from a prompt.
		 *
		 * @param string $prompt Prompt text.
		 * @param array  $params Canonical parameters.
		 * @return string
		 */
		public function generate_image(string $prompt, array $params): string {
			if ($this->error_to_throw !== null) {
				throw $this->error_to_throw;
			}

			$index = count($this->calls);

			$this->calls[] = array(
				'index'  => $index,
				'type'   => 'image',
				'prompt' => $prompt,
				'params' => $params,
			);

			if (isset($this->scripted_responses[$index])) {
				return (string) $this->scripted_responses[$index];
			}

			return 'https://example.com/fake-generated-image.jpg';
		}

		/**
		 * Generate an embedding vector for a text string.
		 *
		 * @param string $text   Text to embed.
		 * @param array  $params Canonical parameters.
		 * @return array
		 */
		public function generate_embedding(string $text, array $params): array {
			if ($this->error_to_throw !== null) {
				throw $this->error_to_throw;
			}

			$index = count($this->calls);

			$this->calls[] = array(
				'index'  => $index,
				'type'   => 'embedding',
				'prompt' => $text,
				'params' => $params,
			);

			if (isset($this->scripted_responses[$index]) && is_array($this->scripted_responses[$index])) {
				return $this->scripted_responses[$index];
			}

			return array(0.1, 0.2, 0.3, 0.4);
		}

		/**
		 * Whether this provider exposes a native structured-JSON generation path.
		 *
		 * @return bool
		 */
		public function supports_native_json(): bool {
			return false;
		}

		/**
		 * Whether this provider can generate embeddings.
		 *
		 * @return bool
		 */
		public function supports_embeddings(): bool {
			return true;
		}

		/**
		 * Whether this provider can replay prior turns as conversation history.
		 *
		 * @return bool
		 */
		public function supports_conversation(): bool {
			return true;
		}

		/**
		 * Classify a backend error message into a canonical provider error code.
		 *
		 * @param string $message Raw exception/error message.
		 * @return string
		 */
		public function extract_error_code(string $message): string {
			return '';
		}
	}
}
