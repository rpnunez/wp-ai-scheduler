<?php
/**
 * SEO & Schema Pipeline Stage
 *
 * Generates SEO metadata, meta descriptions, and excerpt summarization.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Stage implements AIPS_Generation_Stage_Interface {

	public function get_id(): string {
		return 'seo';
	}

	public function get_label(): string {
		return __('SEO Optimization & Excerpt', 'ai-post-scheduler');
	}

	public function get_priority(): int {
		return 50;
	}

	public function should_run(AIPS_Generation_Context $context, AIPS_Generation_Pipeline_Payload $payload): bool {
		return !empty($payload->raw_content);
	}

	public function process(
		AIPS_Generation_Context $context,
		AIPS_Generation_Pipeline_Payload $payload,
		callable $next
	): AIPS_Generation_Pipeline_Payload {
		if (empty($payload->excerpt)) {
			$clean = wp_strip_all_tags($payload->raw_content);
			$payload->excerpt = wp_trim_words($clean, 35, '...');
		}

		$payload->seo_metadata = array(
			'meta_title'       => $payload->title,
			'meta_description' => $payload->excerpt,
			'schema_type'      => 'Article',
			'generated_at'     => current_time('mysql'),
		);

		return $next($payload);
	}
}
