<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Site_Context_Middleware
 *
 * Injects site-wide niche, target audience, and content goals into a
 * prompt document's context payload, read from AIPS_Site_Context.
 *
 * Brand voice/tone and content language are handled by
 * AIPS_Voice_Tone_Middleware and AIPS_Localization_Middleware
 * respectively, so they are intentionally excluded here.
 *
 * This is additive scaffolding for the AIPS_Prompt_Composer pipeline; it
 * does not replace AIPS_Prompt_Builder::build_site_context_block().
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
class AIPS_Site_Context_Middleware implements AIPS_Prompt_Middleware_Interface {

    /**
     * @param AIPS_Prompt_Document    $document
     * @param AIPS_Generation_Context $context
     * @return AIPS_Prompt_Document
     */
    public function process(AIPS_Prompt_Document $document, AIPS_Generation_Context $context) {
        $site_context = AIPS_Site_Context::get();
        $lines        = array();

        if (!empty($site_context['niche'])) {
            $lines[] = 'Site niche: ' . $site_context['niche'];
        }

        if (!empty($site_context['target_audience'])) {
            $lines[] = 'Target audience: ' . $site_context['target_audience'];
        }

        if (!empty($site_context['content_goals'])) {
            $lines[] = 'Content goals: ' . $site_context['content_goals'];
        }

        if (!empty($lines)) {
            $document->add_context_payload("Site-wide content context:\n" . implode("\n", $lines));
        }

        return $document;
    }
}
