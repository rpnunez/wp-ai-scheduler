<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Diversity_Injection_Middleware
 *
 * Injects anti-repetition guidance into a prompt document: titles to
 * avoid as a constraint, and a run-specific uniqueness seed as context
 * payload, so repeated generations don't converge on the same output.
 *
 * This reuses AIPS_Prompt_Builder_Diversity_Injector by composition
 * rather than re-implementing history lookups and rotation logic.
 *
 * This is additive scaffolding for the AIPS_Prompt_Composer pipeline; it
 * does not replace the diversity injector calls already made inline by
 * AIPS_Prompt_Builder_Post_Content and friends.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
class AIPS_Diversity_Injection_Middleware implements AIPS_Prompt_Middleware_Interface {

    /** @var AIPS_Prompt_Builder_Diversity_Injector */
    private $diversity_injector;

    /**
     * @param AIPS_Prompt_Builder_Diversity_Injector|null $diversity_injector Optional diversity injector.
     */
    public function __construct($diversity_injector = null) {
        $this->diversity_injector = $diversity_injector ?: new AIPS_Prompt_Builder_Diversity_Injector();
    }

    /**
     * @param AIPS_Prompt_Document    $document
     * @param AIPS_Generation_Context $context
     * @return AIPS_Prompt_Document
     */
    public function process(AIPS_Prompt_Document $document, AIPS_Generation_Context $context) {
        $avoid_titles_block = $this->diversity_injector->build_avoid_titles_block($context);
        if (!empty($avoid_titles_block)) {
            $document->add_constraint($avoid_titles_block);
        }

        $seed_block = $this->diversity_injector->build_uniqueness_seed_line_block($context);
        if (!empty($seed_block)) {
            $document->add_context_payload($seed_block);
        }

        return $document;
    }
}
