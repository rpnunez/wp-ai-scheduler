<?php
/**
 * Null AI Provider
 *
 * Returned by AIPS_AI_Provider_Factory when no AI backend is available. It
 * reports is_available() === false so AIPS_AI_Service produces the existing
 * 'ai_unavailable' WP_Error rather than fataling. Its generate_* methods are
 * never reached in practice (the service guards on is_available() first), but
 * they throw defensively to keep the contract total.
 *
 * @package AI_Post_Scheduler
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Null_AI_Provider implements AIPS_AI_Provider_Interface {

    public function get_id(): string {
        return 'none';
    }

    public function get_label(): string {
        return __('No AI provider available', 'ai-post-scheduler');
    }

    public function is_available(): bool {
        return false;
    }

    public function get_unavailable_reason(): string {
        return __('No AI provider is currently available.', 'ai-post-scheduler');
    }

    public function generate_text(string $prompt, array $params): string {
        throw new Exception(__('No AI provider is currently available.', 'ai-post-scheduler'));
    }

    public function generate_json(?string $prompt, array $params): ?array {
        throw new Exception(__('No AI provider is currently available.', 'ai-post-scheduler'));
    }

    public function generate_image(string $prompt, array $params): string {
        throw new Exception(__('No AI provider is currently available.', 'ai-post-scheduler'));
    }

    public function generate_embedding(string $text, array $params): array {
        throw new Exception(__('No AI provider is currently available.', 'ai-post-scheduler'));
    }

    public function supports_native_json(): bool {
        return false;
    }

    public function supports_embeddings(): bool {
        return false;
    }

    public function supports_conversation(): bool {
        return false;
    }

    public function extract_error_code(string $message): string {
        return '';
    }
}
