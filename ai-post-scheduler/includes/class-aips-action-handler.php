<?php
/**
 * Action Handler
 *
 * Listens to generic domain hooks (actions and filters) and routes them
 * to the History system and database logs. Decouples business logic
 * from direct history database writes.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Action_Handler
 */
class AIPS_Action_Handler {

	/**
	 * @var self|null Singleton instance
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Registers action and filter hook bindings.
	 */
	public function __construct() {
		// Register filters
		add_filter('aips_create_history_container', array($this, 'create_history_container'), 10, 4);

		// Register actions
		add_action('aips_ai_call_started', array($this, 'log_ai_call_started'), 10, 4);
		add_action('aips_ai_call_completed', array($this, 'log_ai_call_completed'), 10, 5);
		add_action('aips_ai_call_failed', array($this, 'log_ai_call_failed'), 10, 5);

		add_action('aips_post_generation_started', array($this, 'log_post_generation_started'), 10, 2);
		add_action('aips_post_generation_completed', array($this, 'log_post_generation_completed'), 10, 8);
		add_action('aips_post_generation_failed', array($this, 'log_post_generation_failed'), 10, 4);
		add_action('aips_generation_log', array($this, 'log_generation_step'), 10, 6);
	}

	/**
	 * Filter: create or resolve a history container instance for a run.
	 *
	 * @param AIPS_History_Container|null $container Active container if already resolved.
	 * @param string                      $type      Container type.
	 * @param array                       $metadata  Initial metadata.
	 * @param AIPS_Generation_Context     $context   Active context.
	 * @return AIPS_History_Container Resolved history container.
	 */
	public function create_history_container($container, $type, $metadata, $context) {
		if ($container !== null) {
			return $container;
		}

		$history_service = AIPS_Container::get_instance()->make(AIPS_History_Service_Interface::class);
		return $history_service->create($type, $metadata)->with_session($context);
	}

	/**
	 * Action: log an AI call when it starts.
	 *
	 * @param string                      $prompt    AI prompt.
	 * @param array                       $options   AI query options.
	 * @param string                      $log_type  Log type/component label.
	 * @param AIPS_History_Container|null $container Associated container.
	 * @return void
	 */
	public function log_ai_call_started($prompt, $options, $log_type, $container) {
		if ($container instanceof AIPS_History_Container) {
			$container->record(
				'ai_request',
				sprintf(__('Requesting AI generation for %s', 'ai-post-scheduler'), $log_type),
				array(
					'prompt'  => $prompt,
					'options' => $options,
				),
				null,
				array('component' => $log_type)
			);
		}
	}

	/**
	 * Action: log a completed AI call response.
	 *
	 * @param mixed                       $response  AI output text.
	 * @param string                      $prompt    AI prompt.
	 * @param array                       $options   AI query options.
	 * @param string                      $log_type  Log type/component label.
	 * @param AIPS_History_Container|null $container Associated container.
	 * @return void
	 */
	public function log_ai_call_completed($response, $prompt, $options, $log_type, $container) {
		if ($container instanceof AIPS_History_Container) {
			$container->record(
				'ai_response',
				sprintf(__('AI generation successful for %s', 'ai-post-scheduler'), $log_type),
				null,
				$response,
				array('component' => $log_type)
			);
		}
	}

	/**
	 * Action: log a failed AI call attempt.
	 *
	 * @param WP_Error|string             $error     Error description.
	 * @param string                      $prompt    AI prompt.
	 * @param array                       $options   AI query options.
	 * @param string                      $log_type  Log type/component label.
	 * @param AIPS_History_Container|null $container Associated container.
	 * @return void
	 */
	public function log_ai_call_failed($error, $prompt, $options, $log_type, $container) {
		if ($container instanceof AIPS_History_Container) {
			$error_message = is_wp_error($error) ? $error->get_error_message() : (string) $error;
			$container->record(
				'error',
				sprintf(__('AI generation failed for %s: %s', 'ai-post-scheduler'), $log_type, $error_message),
				array(
					'prompt'  => $prompt,
					'options' => $options,
				),
				null,
				array(
					'component' => $log_type,
					'error'     => $error_message,
				)
			);
		}
	}

	/**
	 * Action: log when post generation starts.
	 *
	 * @param AIPS_Generation_Context     $context   Generation context.
	 * @param AIPS_History_Container|null $container Associated container.
	 * @return void
	 */
	public function log_post_generation_started($context, $container) {
		if ($container instanceof AIPS_History_Container) {
			$container->record(
				'log',
				__('Generation pipeline started', 'ai-post-scheduler'),
				null,
				null,
				array('context_type' => $context->get_type())
			);
		}
	}

	/**
	 * Action: log when post generation completes successfully.
	 *
	 * @param int                         $post_id               WordPress Post ID.
	 * @param string                      $title                 Generated title.
	 * @param string                      $content               Generated content.
	 * @param bool                        $generation_incomplete Flag if any component failed.
	 * @param array                       $component_statuses   Statuses of title/content/excerpt/image.
	 * @param AIPS_Generation_Context     $context               Generation context.
	 * @param AIPS_History_Container|null $container             Associated container.
	 * @param int|null                    $duration_seconds      Elapsed generation duration in seconds.
	 * @return void
	 */
	public function log_post_generation_completed(
		$post_id,
		$title,
		$content,
		$generation_incomplete,
		$component_statuses,
		$context,
		$container,
		$duration_seconds = null
	) {
		if ($container instanceof AIPS_History_Container) {
			$container->complete_success(array(
				'post_id'               => $post_id,
				'generated_title'       => $title,
				'generated_content'     => $content,
				'generation_incomplete' => $generation_incomplete,
				'component_statuses'    => $component_statuses,
			));

			// Record structured metrics
			$image_was_attempted = $context->should_generate_featured_image();
			$image_success       = isset($component_statuses['featured_image']) ? (bool) $component_statuses['featured_image'] : null;

			$container->record(
				'metric_generation_result',
				__('Generation metric snapshot', 'ai-post-scheduler'),
				array(
					'outcome'          => $generation_incomplete ? 'partial' : 'completed',
					'duration_seconds' => $duration_seconds,
					'image_attempted'  => $image_was_attempted,
					'image_success'    => $image_was_attempted ? $image_success : null,
				)
			);
			if ($context instanceof AIPS_Template_Context) {
				$template = $context->get_template();
				if ($template && !empty($template->campaign_id)) {
					delete_transient('aips_campaign_' . (int) $template->campaign_id . '_data');
				}
			}
		}
	}

	/**
	 * Action: log when post generation fails completely.
	 *
	 * @param WP_Error|string             $error            Error description.
	 * @param AIPS_Generation_Context     $context          Generation context.
	 * @param AIPS_History_Container|null $container        Associated container.
	 * @param int|null                    $duration_seconds Elapsed generation duration in seconds.
	 * @return void
	 */
	public function log_post_generation_failed($error, $context, $container, $duration_seconds = null) {
		if ($container instanceof AIPS_History_Container) {
			$error_message = is_wp_error($error) ? $error->get_error_message() : (string) $error;
			$container->complete_failure($error_message, array(
				'component' => 'post_creation',
			));

			$container->record(
				'metric_generation_result',
				__('Generation failed — post could not be created', 'ai-post-scheduler'),
				array(
					'outcome'          => 'failed',
					'duration_seconds' => $duration_seconds,
					'image_attempted'  => false,
					'image_success'    => null,
				)
			);
		}
	}

	/**
	 * Action: log a generic custom/details pipeline step.
	 *
	 * @param string                      $log_type  Type (log, error, warning, info, debug).
	 * @param string                      $message   Human-readable message.
	 * @param mixed                       $input     Input parameters.
	 * @param mixed                       $output    Output parameters.
	 * @param array                       $context   Log context.
	 * @param AIPS_History_Container|null $container Associated container.
	 * @return void
	 */
	public function log_generation_step($log_type, $message, $input, $output, $context, $container) {
		if ($container instanceof AIPS_History_Container) {
			$container->record($log_type, $message, $input, $output, $context);
		}
	}
}
