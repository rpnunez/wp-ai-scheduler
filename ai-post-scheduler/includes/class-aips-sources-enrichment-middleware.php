<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Sources_Enrichment_Middleware
 *
 * Injects the trusted-sources reference block into a prompt document's
 * context payload, when the generation context has sources enabled.
 *
 * This reuses AIPS_Prompt_Builder::build_sources_selection() by
 * composition rather than re-implementing source selection, snippet
 * truncation, and usage diagnostics. It does not modify that method or
 * any other part of AIPS_Prompt_Builder.
 *
 * This is additive scaffolding for the AIPS_Prompt_Composer pipeline; it
 * does not replace AIPS_Prompt_Builder::inject_sources_into_content_prompt().
 *
 * A caller that needs to record sources usage after a successful AI
 * submission (as AIPS_Generator does today, via
 * AIPS_Prompt_Builder_Post_Content::get_sources_selection()) can pass a
 * $selection_sink array by reference; it is populated with the same
 * selection array build_sources_selection() returns, so the caller does
 * not need to recompute or re-select sources after composing.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
class AIPS_Sources_Enrichment_Middleware implements AIPS_Prompt_Middleware_Interface {

    /** @var AIPS_Prompt_Builder */
    private $prompt_builder;

    /** @var array */
    private $selection_sink;

    /**
     * @param AIPS_Prompt_Builder|null $prompt_builder Optional prompt builder used for source selection.
     * @param array|null               $selection_sink Optional array, passed by reference, populated with the
     *                                                  last selection computed by process().
     */
    public function __construct($prompt_builder = null, &$selection_sink = null) {
        $this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder();
        $this->selection_sink = &$selection_sink;
    }

    /**
     * @param AIPS_Prompt_Document    $document
     * @param AIPS_Generation_Context $context
     * @return AIPS_Prompt_Document
     */
    public function process(AIPS_Prompt_Document $document, AIPS_Generation_Context $context) {
        if (!$context->get_include_sources()) {
            return $document;
        }

        $group_ids = $context->get_source_group_ids();
        if (empty($group_ids)) {
            return $document;
        }

        $selection = $this->prompt_builder->build_sources_selection($group_ids);
        $this->selection_sink = $selection;

        if (!empty($selection['block'])) {
            $document->add_context_payload($selection['block']);
        }

        return $document;
    }
}
