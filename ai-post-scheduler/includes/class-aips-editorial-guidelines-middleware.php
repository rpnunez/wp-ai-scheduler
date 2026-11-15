<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Editorial_Guidelines_Middleware
 *
 * Injects global content guidelines and excluded/negative topics into a
 * prompt document's constraints, read from AIPS_Site_Context.
 *
 * This is additive scaffolding for the AIPS_Prompt_Composer pipeline; it
 * does not replace the equivalent lines in
 * AIPS_Prompt_Builder::build_site_context_block().
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
class AIPS_Editorial_Guidelines_Middleware implements AIPS_Prompt_Middleware_Interface {

    /**
     * @param AIPS_Prompt_Document    $document
     * @param AIPS_Generation_Context $context
     * @return AIPS_Prompt_Document
     */
    public function process(AIPS_Prompt_Document $document, AIPS_Generation_Context $context) {
        $site_context = AIPS_Site_Context::get();

        if (!empty($site_context['content_guidelines'])) {
            $document->add_constraint('Content guidelines: ' . $site_context['content_guidelines']);
        }

        if (!empty($site_context['excluded_topics'])) {
            $document->add_constraint('Topics to avoid globally: ' . $site_context['excluded_topics']);
        }

        return $document;
    }
}
