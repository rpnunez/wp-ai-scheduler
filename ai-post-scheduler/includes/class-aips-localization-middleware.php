<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Localization_Middleware
 *
 * Injects a target-language directive into a prompt document's
 * constraints when the site content language differs from the default
 * ('en'), read from AIPS_Site_Context.
 *
 * This is additive scaffolding for the AIPS_Prompt_Composer pipeline; it
 * does not replace the equivalent 'Language: ...' line in
 * AIPS_Prompt_Builder::build_site_context_block().
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
class AIPS_Localization_Middleware implements AIPS_Prompt_Middleware_Interface {

    /**
     * @param AIPS_Prompt_Document    $document
     * @param AIPS_Generation_Context $context
     * @return AIPS_Prompt_Document
     */
    public function process(AIPS_Prompt_Document $document, AIPS_Generation_Context $context) {
        $site_context = AIPS_Site_Context::get();
        $language     = isset($site_context['content_language']) ? $site_context['content_language'] : '';

        if (!empty($language) && 'en' !== $language) {
            $document->add_constraint('Write the output in this language: ' . $language);
        }

        return $document;
    }
}
