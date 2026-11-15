<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Token_Budget_Middleware
 *
 * Enforces a character-count budget on a prompt document, truncating the
 * lowest-priority sections first so the compiled prompt never silently
 * overflows a provider's context window.
 *
 * Trim order (lowest priority first): few-shot examples, then context
 * payload entries (most recently added first), then constraints (most
 * recently added first). system_instructions and output_schema are never
 * removed or truncated by this middleware — they carry the persona/
 * format contract the rest of the prompt depends on. task_directive is
 * truncated, delimiter-safely, only as a last resort once every other
 * section has already been dropped and the document is still over
 * budget.
 *
 * This mirrors the character-budget approach already used in
 * AIPS_Prompt_Builder::build_sources_selection() rather than introducing
 * a token-counting dependency; it does not modify that method.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
class AIPS_Token_Budget_Middleware implements AIPS_Prompt_Middleware_Interface {

    /** Default character budget applied when none is supplied. */
    const DEFAULT_MAX_CHARS = 24000;

    /** @var int */
    private $max_chars;

    /**
     * @param int|null $max_chars Maximum allowed length, in characters, of compile_text(). Defaults to DEFAULT_MAX_CHARS.
     */
    public function __construct($max_chars = null) {
        $max_chars = (int) $max_chars;
        $this->max_chars = $max_chars > 0 ? $max_chars : self::DEFAULT_MAX_CHARS;
    }

    /**
     * @param AIPS_Prompt_Document    $document
     * @param AIPS_Generation_Context $context
     * @return AIPS_Prompt_Document
     */
    public function process(AIPS_Prompt_Document $document, AIPS_Generation_Context $context) {
        if ($this->length_of($document) <= $this->max_chars) {
            return $document;
        }

        foreach ($this->build_removal_queue($document) as $removal) {
            if ($this->length_of($document) <= $this->max_chars) {
                break;
            }

            $this->remove($document, $removal['section'], $removal['index']);
        }

        if ($this->length_of($document) > $this->max_chars) {
            $this->truncate_task_directive($document);
        }

        return $document;
    }

    /**
     * Build the ordered list of (section, index) removals to apply, from
     * lowest priority to highest. Indexes are captured up front against
     * the document's current state so later removals can be applied by
     * position without re-deriving priority as the document shrinks.
     *
     * @param AIPS_Prompt_Document $document
     * @return array<int,array{section:string,index:int}>
     */
    private function build_removal_queue(AIPS_Prompt_Document $document) {
        $queue = array();

        for ($i = count($document->get_few_shot_examples()) - 1; $i >= 0; $i--) {
            $queue[] = array('section' => 'few_shot_examples', 'index' => $i);
        }

        for ($i = count($document->get_context_payload()) - 1; $i >= 0; $i--) {
            $queue[] = array('section' => 'context_payload', 'index' => $i);
        }

        for ($i = count($document->get_constraints()) - 1; $i >= 0; $i--) {
            $queue[] = array('section' => 'constraints', 'index' => $i);
        }

        return $queue;
    }

    /**
     * Remove a single entry from a repeatable section by its original
     * index, tolerating indexes already removed by an earlier step.
     *
     * @param AIPS_Prompt_Document $document
     * @param string                $section
     * @param int                   $index
     * @return void
     */
    private function remove(AIPS_Prompt_Document $document, $section, $index) {
        switch ($section) {
            case 'few_shot_examples':
                $items = $document->get_few_shot_examples();
                if (array_key_exists($index, $items)) {
                    unset($items[$index]);
                    $document->set_few_shot_examples($items);
                }
                break;

            case 'context_payload':
                $items = $document->get_context_payload();
                if (array_key_exists($index, $items)) {
                    unset($items[$index]);
                    $document->set_context_payload($items);
                }
                break;

            case 'constraints':
                $items = $document->get_constraints();
                if (array_key_exists($index, $items)) {
                    unset($items[$index]);
                    $document->set_constraints($items);
                }
                break;
        }
    }

    /**
     * Last-resort trim of the task directive once every lower-priority
     * section has already been removed and the document is still over
     * budget. Shrinks only as far as needed to fit the remaining budget.
     *
     * @param AIPS_Prompt_Document $document
     * @return void
     */
    private function truncate_task_directive(AIPS_Prompt_Document $document) {
        $overage = $this->length_of($document) - $this->max_chars;
        if ($overage <= 0) {
            return;
        }

        $task_directive = trim($document->get_task_directive());
        $target_len      = max(0, mb_strlen($task_directive) - $overage);

        $document->set_task_directive($this->safe_truncate($task_directive, $target_len));
    }

    /**
     * @param AIPS_Prompt_Document $document
     * @return int
     */
    private function length_of(AIPS_Prompt_Document $document) {
        return mb_strlen($document->compile_text());
    }

    /**
     * Truncate text to at most $max_len characters without leaving an
     * unclosed HTML tag or an odd number of unescaped double quotes
     * (as would corrupt inline JSON), and without splitting a multi-byte
     * character.
     *
     * @param string $text
     * @param int    $max_len
     * @return string
     */
    private function safe_truncate($text, $max_len) {
        $text = (string) $text;

        if (mb_strlen($text) <= $max_len) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $max_len);

        $last_open  = mb_strrpos($truncated, '<');
        $last_close = mb_strrpos($truncated, '>');
        if (false !== $last_open && (false === $last_close || $last_open > $last_close)) {
            $truncated = mb_substr($truncated, 0, $last_open);
        }

        if ($this->has_unbalanced_quote($truncated)) {
            $last_quote = mb_strrpos($truncated, '"');
            if (false !== $last_quote) {
                $truncated = mb_substr($truncated, 0, $last_quote);
            }
        }

        return rtrim($truncated);
    }

    /**
     * @param string $text
     * @return bool True if $text contains an odd number of unescaped double quotes.
     */
    private function has_unbalanced_quote($text) {
        $count = 0;
        $len   = mb_strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1);
            if ('"' !== $char) {
                continue;
            }

            $escaped = $i > 0 && '\\' === mb_substr($text, $i - 1, 1);
            if (!$escaped) {
                $count++;
            }
        }

        return 1 === ($count % 2);
    }
}
