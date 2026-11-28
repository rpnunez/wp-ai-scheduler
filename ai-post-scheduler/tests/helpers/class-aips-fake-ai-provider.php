<?php
/**
 * Shared Fake AI Provider for Mirror Golden Tests
 *
 * Implements AIPS_AI_Provider_Interface to record all calls (prompt, params, call type, order)
 * and return scripted canned responses without making real AI network calls.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/interface-aips-ai-provider-interface.php';

if (!class_exists('AIPS_Fake_AI_Provider')) {

    class AIPS_Fake_AI_Provider implements AIPS_AI_Provider_Interface {

        /**
         * Log of all calls received by this fake provider.
         *
         * @var array
         */
        public $calls = array();

        /**
         * Scripted text responses for generate_text calls.
         *
         * @var array
         */
        private $scripted_text_responses = array();

        /**
         * Scripted json responses for generate_json calls.
         *
         * @var array
         */
        private $scripted_json_responses = array();

        /**
         * Scripted image responses for generate_image calls.
         *
         * @var array
         */
        private $scripted_image_responses = array();

        /**
         * Scripted embedding responses for generate_embedding calls.
         *
         * @var array
         */
        private $scripted_embedding_responses = array();

        /**
         * Default text response when script queue is empty.
         *
         * @var string
         */
        public $default_text_response = 'Default fake AI generated text response.';

        /**
         * Default image response when script queue is empty.
         *
         * @var string
         */
        public $default_image_response = 'https://example.com/fake-image.jpg';

        /**
         * Default embedding response when script queue is empty.
         *
         * @var array
         */
        public $default_embedding_response = array(0.1, 0.2, 0.3);

        /**
         * Exception to throw on generate call if set.
         *
         * @var Exception|null
         */
        public $throw_exception = null;

        public function get_id(): string {
            return 'fake_mirror_provider';
        }

        public function get_label(): string {
            return 'Fake Mirror AI Provider';
        }

        public function is_available(): bool {
            return true;
        }

        public function get_unavailable_reason(): string {
            return '';
        }

        public function supports_native_json(): bool {
            return true;
        }

        public function supports_embeddings(): bool {
            return true;
        }

        public function supports_conversation(): bool {
            return true;
        }

        public function extract_error_code(string $message): string {
            if (strpos($message, 'rate_limit') !== false) {
                return 'rate_limit_exceeded';
            }
            return '';
        }

        /**
         * Script a text response for the queue.
         *
         * @param string|Exception $response
         * @return self
         */
        public function script_text_response($response): self {
            $this->scripted_text_responses[] = $response;
            return $this;
        }

        /**
         * Script a JSON response for the queue.
         *
         * @param array|null|Exception $response
         * @return self
         */
        public function script_json_response($response): self {
            $this->scripted_json_responses[] = $response;
            return $this;
        }

        /**
         * Script an image response for the queue.
         *
         * @param string|Exception $response
         * @return self
         */
        public function script_image_response($response): self {
            $this->scripted_image_responses[] = $response;
            return $this;
        }

        public function generate_text(string $prompt, array $params): string {
            if ($this->throw_exception) {
                throw $this->throw_exception;
            }

            $this->calls[] = array(
                'type'   => 'text',
                'order'  => count($this->calls) + 1,
                'prompt' => $prompt,
                'params' => $params,
            );

            if (!empty($this->scripted_text_responses)) {
                $response = array_shift($this->scripted_text_responses);
                if ($response instanceof Exception) {
                    throw $response;
                }
                return (string) $response;
            }

            return $this->default_text_response;
        }

        public function generate_json(?string $prompt, array $params): ?array {
            if ($this->throw_exception) {
                throw $this->throw_exception;
            }

            $this->calls[] = array(
                'type'   => 'json',
                'order'  => count($this->calls) + 1,
                'prompt' => $prompt,
                'params' => $params,
            );

            if (!empty($this->scripted_json_responses)) {
                $response = array_shift($this->scripted_json_responses);
                if ($response instanceof Exception) {
                    throw $response;
                }
                return $response;
            }

            return null;
        }

        public function generate_image(string $prompt, array $params): string {
            if ($this->throw_exception) {
                throw $this->throw_exception;
            }

            $this->calls[] = array(
                'type'   => 'image',
                'order'  => count($this->calls) + 1,
                'prompt' => $prompt,
                'params' => $params,
            );

            if (!empty($this->scripted_image_responses)) {
                $response = array_shift($this->scripted_image_responses);
                if ($response instanceof Exception) {
                    throw $response;
                }
                return (string) $response;
            }

            return $this->default_image_response;
        }

        public function generate_embedding(string $text, array $params): array {
            if ($this->throw_exception) {
                throw $this->throw_exception;
            }

            $this->calls[] = array(
                'type'   => 'embedding',
                'order'  => count($this->calls) + 1,
                'text'   => $text,
                'params' => $params,
            );

            if (!empty($this->scripted_embedding_responses)) {
                $response = array_shift($this->scripted_embedding_responses);
                if ($response instanceof Exception) {
                    throw $response;
                }
                return (array) $response;
            }

            return $this->default_embedding_response;
        }

        /**
         * Reset call log and queues.
         */
        public function reset(): void {
            $this->calls = array();
            $this->scripted_text_responses = array();
            $this->scripted_json_responses = array();
            $this->scripted_image_responses = array();
            $this->scripted_embedding_responses = array();
            $this->throw_exception = null;
        }
    }
}
