<?php
/**
 * AI Provider Interface
 *
 * Defines the low-level transport contract for a single AI backend (e.g. Meow
 * Apps AI Engine, the WordPress core AI Client). Providers translate the
 * plugin's canonical request parameters into a specific backend's native API and
 * perform the raw call. All cross-cutting concerns (resilience, retries, logging,
 * token budgeting, JSON extraction, notifications) live in AIPS_AI_Service, not
 * here — a provider should remain a thin adapter.
 *
 * To add a new AI backend, implement this interface and register the class in
 * AIPS_AI_Provider_Factory.
 *
 * @package AI_Post_Scheduler
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

interface AIPS_AI_Provider_Interface {

    /**
     * Stable machine identifier for this provider (e.g. 'meow', 'wp_ai_client').
     *
     * Persisted in the aips_ai_provider option, so it must not change between
     * releases once shipped.
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Human-readable label for the settings dropdown.
     *
     * @return string
     */
    public function get_label(): string;

    /**
     * Whether the backing plugin/API is present and ready to serve requests.
     *
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Generate text from a prompt.
     *
     * Returns the raw text payload on success. On failure, throw an Exception
     * whose message carries the backend error — AIPS_AI_Service wraps every call
     * in a try/catch and classifies the error via extract_error_code().
     *
     * @param string $prompt Prompt text.
     * @param array  $params Canonical parameters (model, max_tokens, temperature,
     *                       context, instructions, messages, env_id, api_key, ...).
     * @return string Generated text.
     * @throws Exception On backend failure.
     */
    public function generate_text(string $prompt, array $params);

    /**
     * Generate structured JSON output natively.
     *
     * Implementations that have a native structured-JSON path return a decoded
     * array. Implementations without one MUST return null so the service falls
     * back to text-based JSON extraction. Throw an Exception on backend failure.
     *
     * @param string|null $prompt Prompt text.
     * @param array       $params Canonical parameters (may include 'json_schema').
     * @return array|null Decoded JSON array, or null to request the text fallback.
     * @throws Exception On backend failure.
     */
    public function generate_json(?string $prompt, array $params);

    /**
     * Generate an image from a prompt.
     *
     * Returns an image URL (or data URI) string on success; throw an Exception on
     * failure.
     *
     * @param string $prompt Prompt text.
     * @param array  $params Canonical parameters.
     * @return string Image URL or data URI.
     * @throws Exception On backend failure.
     */
    public function generate_image(string $prompt, array $params);

    /**
     * Generate an embedding vector for a text string.
     *
     * Returns the embedding vector as an array on success; throw an Exception on
     * failure (use code 'embeddings_not_supported' when the backend cannot do
     * embeddings).
     *
     * @param string $text   Text to embed.
     * @param array  $params Canonical parameters (may include 'embeddings_env_id').
     * @return array Embedding vector.
     * @throws Exception On backend failure or when embeddings are unsupported.
     */
    public function generate_embedding(string $text, array $params);

    /**
     * Whether this provider exposes a native structured-JSON generation path.
     *
     * When false, AIPS_AI_Service uses text-based JSON generation + extraction.
     *
     * @return bool
     */
    public function supports_native_json(): bool;

    /**
     * Whether this provider can generate embeddings.
     *
     * @return bool
     */
    public function supports_embeddings(): bool;

    /**
     * Classify a backend error message into a canonical provider error code.
     *
     * Used by the resilience layer to decide whether to retry, open the circuit
     * breaker, or abort. Return '' when no known code can be identified.
     *
     * @param string $message Raw exception/error message.
     * @return string Canonical provider error code, or ''.
     */
    public function extract_error_code(string $message): string;
}
