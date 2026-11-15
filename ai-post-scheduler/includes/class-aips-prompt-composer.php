<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Prompt_Composer
 *
 * Runs an AIPS_Prompt_Document through an ordered stack of typed
 * AIPS_Prompt_Middleware_Interface implementations. Each middleware
 * receives the document produced by the previous one, so the pipeline's
 * output is fully determined by the middleware array's order.
 *
 * This replaces the ad hoc `add_filter('aips_content_prompt', ...)` style
 * chains with an explicit, inspectable list that is set once (typically
 * via the DI container) rather than accumulated through side effects.
 *
 * This is additive scaffolding: it does not call into, or get called by,
 * AIPS_Prompt_Builder or any existing generation flow.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
class AIPS_Prompt_Composer {

    /** @var AIPS_Prompt_Middleware_Interface[] */
    private $middlewares;

    /**
     * @param AIPS_Prompt_Middleware_Interface[] $middlewares Ordered middleware stack. Defaults to empty.
     */
    public function __construct(array $middlewares = array()) {
        $this->middlewares = array();

        foreach ($middlewares as $middleware) {
            $this->add_middleware($middleware);
        }
    }

    /**
     * Append a middleware to the end of the pipeline.
     *
     * @param AIPS_Prompt_Middleware_Interface $middleware
     * @return $this
     */
    public function add_middleware(AIPS_Prompt_Middleware_Interface $middleware) {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * @return AIPS_Prompt_Middleware_Interface[]
     */
    public function get_middlewares() {
        return $this->middlewares;
    }

    /**
     * Run the document through every registered middleware, in order.
     *
     * With no middlewares registered, the document is returned unchanged.
     *
     * @param AIPS_Prompt_Document    $document The starting document (e.g. from AIPS_Prompt_Registry).
     * @param AIPS_Generation_Context $context  The generation context driving this prompt.
     * @return AIPS_Prompt_Document
     */
    public function compose(AIPS_Prompt_Document $document, AIPS_Generation_Context $context) {
        foreach ($this->middlewares as $middleware) {
            $document = $middleware->process($document, $context);
        }

        return $document;
    }
}
