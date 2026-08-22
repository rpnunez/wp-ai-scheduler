<?php
/**
 * Drafting Pipeline Stage
 *
 * Calls the AI provider to synthesize the article content and title.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Drafting_Stage implements AIPS_Generation_Stage_Interface {

	/**
	 * @var AIPS_AI_Service|null
	 */
	private $ai_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service|null $ai_service AI Service.
	 */
	public function __construct(?AIPS_AI_Service $ai_service = null) {
		$container = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service::class) ? $container->make(AIPS_AI_Service::class) : new AIPS_AI_Service());
	}

	public function get_id(): string {
		return 'drafting';
	}

	public function get_label(): string {
		return __('Content Synthesis & Drafting', 'ai-post-scheduler');
	}

	public function get_priority(): int {
		return 40;
	}

	public function should_run(AIPS_Generation_Context $context, AIPS_Generation_Pipeline_Payload $payload): bool {
		return empty($payload->raw_content);
	}

	public function process(
		AIPS_Generation_Context $context,
		AIPS_Generation_Pipeline_Payload $payload,
		callable $next
	): AIPS_Generation_Pipeline_Payload {
		$prompt = $this->build_drafting_prompt($context, $payload);

		$params = array(
			'temperature' => 0.7,
			'context'     => 'post_generation',
		);

		$generated_text = $this->ai_service->generate_text($prompt, $params);

		if (is_wp_error($generated_text)) {
			$payload->add_error($generated_text->get_error_message());
			return $next($payload);
		}

		$payload->raw_content = (string) $generated_text;
		$payload->formatted_content = (string) $generated_text;

		if (empty($payload->title)) {
			$payload->title = (string) ($context->get_topic() ?: 'Untitled Post');
		}

		return $next($payload);
	}

	/**
	 * Assemble the prompt including outline and retrieved knowledge sources.
	 *
	 * @param AIPS_Generation_Context          $context Generation context.
	 * @param AIPS_Generation_Pipeline_Payload $payload Pipeline payload.
	 * @return string Complete prompt string.
	 */
	private function build_drafting_prompt(AIPS_Generation_Context $context, AIPS_Generation_Pipeline_Payload $payload): string {
		$parts = array();

		$base_prompt = $context->get_content_prompt();
		if (!empty($base_prompt)) {
			$parts[] = $base_prompt;
		}

		if (!empty($payload->outline)) {
			$parts[] = "Use the following outline structure for the article:\n" . $payload->outline;
		}

		if (!empty($payload->retrieved_sources)) {
			$sources_text = "Factual Knowledge Sources to incorporate:\n";
			foreach ($payload->retrieved_sources as $src) {
				$sources_text .= "- Source [" . esc_html($src['title']) . "]: " . $src['snippet'] . "\n";
			}
			$parts[] = $sources_text;
		}

		return implode("\n\n", $parts);
	}
}
