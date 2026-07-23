<?php
/**
 * AI Conversation Transcript
 *
 * An ordered, provider-neutral record of the turns exchanged with an AI model
 * during a single generation run. Passing this to AIPS_AI_Service lets follow-up
 * calls (title, excerpt, image prompt) reference the article the model already
 * wrote instead of pasting a copy of it into every prompt.
 *
 * The canonical role names are 'user' and 'model', matching the WordPress AI
 * Client's MessageRoleEnum. Providers translate outward as needed (Meow's AI
 * Engine, for example, calls the second role 'assistant').
 *
 * Invariants enforced by this class:
 * - Turns strictly alternate user, model, user, model, ...
 * - The transcript always starts on a 'user' turn.
 * - The transcript always ends on a 'model' turn.
 *
 * That last invariant matters: the WordPress AI Client validates that the final
 * message in a request comes from the user, and the pending prompt supplies it.
 * A transcript ending on a user turn would produce two consecutive user messages
 * and fail validation inside the SDK.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_AI_Conversation {

    /**
     * Canonical role for prompts sent by the plugin.
     */
    const ROLE_USER = 'user';

    /**
     * Canonical role for text produced by the AI model.
     */
    const ROLE_MODEL = 'model';

    /**
     * @var array<int, array{role: string, text: string}> Ordered turns.
     */
    private $turns = array();

    /**
     * Append a complete request/response exchange.
     *
     * This is the primary API. It appends both turns atomically so the
     * alternation invariant cannot be broken by a caller that forgets to record
     * one half of an exchange (for example when a call returns WP_Error).
     *
     * @param string $user_text  Prompt that was sent.
     * @param string $model_text Text the model returned.
     * @return bool True when the exchange was recorded.
     */
    public function add_exchange($user_text, $model_text) {
        $user_text  = $this->normalize_text($user_text);
        $model_text = $this->normalize_text($model_text);

        // A half-exchange would break alternation, so both sides must be present.
        if ($user_text === '' || $model_text === '') {
            return false;
        }

        $this->turns[] = array('role' => self::ROLE_USER, 'text' => $user_text);
        $this->turns[] = array('role' => self::ROLE_MODEL, 'text' => $model_text);

        return true;
    }

    /**
     * Append a user turn.
     *
     * Rejected when the transcript already ends on a user turn, which would
     * produce two consecutive same-role messages.
     *
     * @param string $text Prompt text.
     * @return bool True when the turn was recorded.
     */
    public function add_user($text) {
        return $this->add_turn(self::ROLE_USER, $text);
    }

    /**
     * Append a model turn.
     *
     * Rejected when the transcript is empty or already ends on a model turn.
     *
     * @param string $text Model response text.
     * @return bool True when the turn was recorded.
     */
    public function add_model($text) {
        return $this->add_turn(self::ROLE_MODEL, $text);
    }

    /**
     * Append a single turn, enforcing role alternation.
     *
     * @param string $role Canonical role.
     * @param string $text Turn text.
     * @return bool True when the turn was recorded.
     */
    private function add_turn($role, $text) {
        $text = $this->normalize_text($text);

        if ($text === '') {
            return false;
        }

        $last_role = $this->get_last_role();

        // The transcript must open on a user turn, then strictly alternate.
        if ($last_role === $role) {
            return false;
        }

        if ($last_role === null && $role !== self::ROLE_USER) {
            return false;
        }

        $this->turns[] = array('role' => $role, 'text' => $text);

        return true;
    }

    /**
     * Role of the most recent turn.
     *
     * @return string|null Canonical role, or null when the transcript is empty.
     */
    public function get_last_role() {
        if (empty($this->turns)) {
            return null;
        }

        $last = end($this->turns);
        reset($this->turns);

        return $last['role'];
    }

    /**
     * All recorded turns, in order.
     *
     * Only complete exchanges are returned: a trailing user turn is dropped so
     * the result can always be combined with a pending prompt without producing
     * two consecutive user messages.
     *
     * @return array<int, array{role: string, text: string}>
     */
    public function get_turns() {
        $turns = $this->turns;

        if (!empty($turns) && end($turns)['role'] === self::ROLE_USER) {
            array_pop($turns);
        }

        return array_values($turns);
    }

    /**
     * Whether the transcript carries no usable history.
     *
     * @return bool
     */
    public function is_empty() {
        return empty($this->get_turns());
    }

    /**
     * Number of complete exchanges recorded.
     *
     * @return int
     */
    public function count_exchanges() {
        return (int) floor(count($this->get_turns()) / 2);
    }

    /**
     * Estimated token cost of replaying this transcript.
     *
     * Uses the plugin's shared 1-token-per-4-characters approximation so callers
     * can budget follow-up requests consistently with AIPS_AI_Service.
     *
     * @return int
     */
    public function estimated_tokens() {
        $total = 0;

        foreach ($this->get_turns() as $turn) {
            $total += AIPS_Token_Budget::estimate_prompt_tokens($turn['text']);
        }

        return $total;
    }

    /**
     * Export the transcript as a plain array.
     *
     * @return array<int, array{role: string, text: string}>
     */
    public function to_array() {
        return $this->get_turns();
    }

    /**
     * Rebuild a transcript from exported data.
     *
     * Malformed or out-of-order entries are skipped rather than throwing, so a
     * transcript persisted by an older release can never fatal a generation run.
     *
     * @param mixed $data Previously exported turns.
     * @return AIPS_AI_Conversation
     */
    public static function from_array($data) {
        $conversation = new self();

        if (!is_array($data)) {
            return $conversation;
        }

        foreach ($data as $turn) {
            if (!is_array($turn) || !isset($turn['role'], $turn['text'])) {
                continue;
            }

            if ($turn['role'] === self::ROLE_USER) {
                $conversation->add_user($turn['text']);
            } elseif ($turn['role'] === self::ROLE_MODEL) {
                $conversation->add_model($turn['text']);
            }
        }

        return $conversation;
    }

    /**
     * Coerce a turn value to a trimmed string.
     *
     * @param mixed $text Raw value.
     * @return string
     */
    private function normalize_text($text) {
        if (!is_string($text)) {
            return '';
        }

        return trim($text);
    }
}
