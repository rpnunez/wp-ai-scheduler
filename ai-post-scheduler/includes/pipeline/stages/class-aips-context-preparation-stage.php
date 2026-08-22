<?php
/**
 * Context Preparation Pipeline Stage
 *
 * Resolves context constraints, author persona, voice profile, and structure settings
 * prior to generation.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Context_Preparation_Stage implements AIPS_Generation_Stage_Interface {

	public function get_id(): string {
		return 'context_preparation';
	}

	public function get_label(): string {
		return __('Context & Voice Preparation', 'ai-post-scheduler');
	}

	public function get_priority(): int {
		return 10;
	}

	public function should_run(AIPS_Generation_Context $context, AIPS_Generation_Pipeline_Payload $payload): bool {
		return true;
	}

	public function process(
		AIPS_Generation_Context $context,
		AIPS_Generation_Pipeline_Payload $payload,
		callable $next
	): AIPS_Generation_Pipeline_Payload {
		if (empty($payload->title) && $context->get_topic()) {
			$payload->title = (string) $context->get_topic();
		}

		if (empty($payload->post_status)) {
			$payload->post_status = (string) ($context->get_post_status() ?: 'draft');
		}

		$categories = $context->get_post_category();
		if (!empty($categories) && empty($payload->categories)) {
			$payload->categories = is_array($categories) ? $categories : array_filter(array_map('trim', explode(',', (string) $categories)));
		}

		$tags = $context->get_post_tags();
		if (!empty($tags) && empty($payload->tags)) {
			$payload->tags = is_array($tags) ? $tags : array_filter(array_map('trim', explode(',', (string) $tags)));
		}

		return $next($payload);
	}
}
