<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AIPS_AS_Worker
 *
 * Registers and handles the Action Scheduler worker callback for AI post
 * generation. When Action Scheduler dispatches the 'aips_action_generate_post'
 * action this class validates the incoming args and delegates to
 * AIPS_Generator::generate_post() using a schedule template context.
 *
 * If generation fails the handler throws an Exception so that Action Scheduler
 * records the failure and can apply its built-in retry policy.
 *
 * @package AI_Post_Scheduler
 * @since   1.7.1
 */
class AIPS_AS_Worker {

    /**
     * Action Scheduler action hook name.
     */
    const ACTION_HOOK = 'aips_action_generate_post';

    /**
     * Action Scheduler group identifier for all AIPS actions.
     */
    const ACTION_GROUP = 'aips';

    /**
     * @var AIPS_Logger Logger instance.
     */
    private $logger;

    /**
     * @var AIPS_Template_Repository Repository used to load templates by ID.
     */
    private $template_repository;

    /**
     * @var AIPS_Generator Generator instance (injectable for testing).
     */
    private $generator;

    /**
     * Constructor.
     *
     * Registers the Action Scheduler hook callback and wires up dependencies.
     *
     * @param AIPS_Generator|null           $generator           Optional generator for DI.
     * @param AIPS_Template_Repository|null $template_repository Optional repository for DI.
     * @param object|null                   $logger              Optional logger for DI.
     */
    public function __construct($generator = null, $template_repository = null, $logger = null) {
        $this->logger              = $logger ?: new AIPS_Logger();
        $this->template_repository = $template_repository ?: new AIPS_Template_Repository();
        $this->generator           = $generator ?: new AIPS_Generator();

        add_action(self::ACTION_HOOK, array($this, 'handle'), 10, 1);
    }

    /**
     * Handle the 'aips_action_generate_post' Action Scheduler action.
     *
     * Validates $args, loads the template, and invokes the generator.
     * Throws an Exception on failure so Action Scheduler records the error.
     *
     * @param array $args Primitive args array: expects at least 'template_id'.
     * @throws Exception On validation failure or generation error.
     */
    public function handle($args) {
        if (!is_array($args)) {
            throw new \Exception('AIPS_AS_Worker: invalid args — expected array, received: ' . gettype($args));
        }

        $template_id = isset($args['template_id']) ? absint($args['template_id']) : 0;

        if ($template_id <= 0) {
            throw new \Exception('AIPS_AS_Worker: missing or invalid template_id in args.');
        }

        $this->logger->log(
            sprintf('AIPS_AS_Worker: running generate_post for template_id=%d', $template_id),
            'info'
        );

        // Load the template to build a context.
        $template = $this->template_repository->get_by_id($template_id);

        if (!$template) {
            throw new \Exception(
                sprintf('AIPS_AS_Worker: template %d not found.', $template_id)
            );
        }

        // Optional topic passed through args.
        $topic = isset($args['topic']) ? sanitize_text_field($args['topic']) : null;

        // Invoke the generator using the safest available public method.
        $result = $this->run_generator($template, $topic);

        if (is_wp_error($result)) {
            throw new \Exception(
                sprintf(
                    'AIPS_AS_Worker: generation failed for template_id=%d — %s',
                    $template_id,
                    $result->get_error_message()
                )
            );
        }

        $this->logger->log(
            sprintf('AIPS_AS_Worker: post generated successfully for template_id=%d (post_id=%s)', $template_id, $result),
            'info'
        );
    }

    /**
     * Invoke the generator via AIPS_Generator::generate_post().
     *
     * Throws an Exception if the expected public method is not present so that
     * Action Scheduler records the failure rather than silently doing nothing.
     *
     * @param object      $template Template object.
     * @param string|null $topic    Optional topic override.
     * @return int|WP_Error WordPress post ID on success, WP_Error on failure.
     * @throws Exception When generate_post() is not available on the generator.
     */
    private function run_generator($template, $topic = null) {
        $generator = $this->generator;

        if (!method_exists($generator, 'generate_post')) {
            throw new \Exception('AIPS_AS_Worker: AIPS_Generator::generate_post() not found.');
        }

        return $generator->generate_post($template, null, $topic);
    }
}
