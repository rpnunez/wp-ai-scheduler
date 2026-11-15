<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface AIPS_Prompt_Middleware_Interface
 *
 * Contract for a single, deterministic step in the AIPS_Prompt_Composer
 * pipeline. A middleware receives the AIPS_Prompt_Document assembled by
 * earlier middlewares (or the Registry template, for the first step) and
 * the generation context driving the request, and returns a document with
 * its own enrichment applied.
 *
 * Middlewares must be pure with respect to the document: they return a
 * (possibly mutated) AIPS_Prompt_Document rather than relying on global
 * state, so the pipeline's behavior is fully determined by its ordered
 * middleware list.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
interface AIPS_Prompt_Middleware_Interface {

    /**
     * Apply this middleware's enrichment to the prompt document.
     *
     * @param AIPS_Prompt_Document     $document The document produced so far.
     * @param AIPS_Generation_Context  $context  The generation context driving this prompt.
     * @return AIPS_Prompt_Document The document after this middleware's enrichment.
     */
    public function process(AIPS_Prompt_Document $document, AIPS_Generation_Context $context);
}
