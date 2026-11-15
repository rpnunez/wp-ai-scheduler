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
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
class AIPS_Sources_Enrichment_Middleware implements AIPS_Prompt_Middleware_Interface {

    /** @var AIPS_Prompt_Builder */
    private $prompt_builder;

    /**
     * @param AIPS_Prompt_Builder|null $prompt_builder Optional prompt builder used for source selection.
     */
    public function __construct($prompt_builder = null) {
        $this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder();
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

        if (!empty($selection['block'])) {
            $document->add_context_payload($selection['block']);
        }

        return $document;
    }
}
