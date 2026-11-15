<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Voice_Tone_Middleware
 *
 * Enriches a prompt document with the selected author/voice persona's
 * content instructions, when one is configured on the generation context.
 *
 * This is additive scaffolding for the AIPS_Prompt_Composer pipeline; it
 * does not replace the equivalent logic already applied inline by
 * AIPS_Prompt_Builder_Post_Content and friends.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
class AIPS_Voice_Tone_Middleware implements AIPS_Prompt_Middleware_Interface {

    /**
     * @param AIPS_Prompt_Document    $document
     * @param AIPS_Generation_Context $context
     * @return AIPS_Prompt_Document
     */
    public function process(AIPS_Prompt_Document $document, AIPS_Generation_Context $context) {
        $voice = $context->get_voice();

        if (!empty($voice) && !empty($voice->content_instructions)) {
            $document->add_context_payload('Voice/tone instructions: ' . $voice->content_instructions);
        }

        return $document;
    }
}
