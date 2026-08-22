<?php
/**
 * Review Preparation Pipeline Stage
 *
 * Persists the generated post in WordPress, updates taxonomy, attaches post meta,
 * and sets appropriate review status.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Review_Preparation_Stage implements AIPS_Generation_Stage_Interface {

	public function get_id(): string {
		return 'review_preparation';
	}

	public function get_label(): string {
		return __('Post Persistence & Review Setup', 'ai-post-scheduler');
	}

	public function get_priority(): int {
		return 70;
	}

	public function should_run(AIPS_Generation_Context $context, AIPS_Generation_Pipeline_Payload $payload): bool {
		return !empty($payload->formatted_content) || !empty($payload->raw_content);
	}

	public function process(
		AIPS_Generation_Context $context,
		AIPS_Generation_Pipeline_Payload $payload,
		callable $next
	): AIPS_Generation_Pipeline_Payload {
		if ($payload->post_id === null && function_exists('wp_insert_post')) {
			$post_arr = array(
				'post_title'    => $payload->title ?: 'Untitled Post',
				'post_content'  => $payload->formatted_content ?: $payload->raw_content,
				'post_excerpt'  => $payload->excerpt,
				'post_status'   => $payload->post_status ?: 'draft',
				'post_type'     => $context->get_post_type() ?: 'post',
				'post_author'   => (int) ($context->get_post_author() ?: get_current_user_id()),
			);

			// wp_insert_post() unslashes its input, so slash the generated values
			// first or backslashes in the content are silently stripped.
			$inserted_id = wp_insert_post(wp_slash($post_arr), true);

			if (is_wp_error($inserted_id)) {
				$payload->add_error($inserted_id->get_error_message());
			} elseif ($inserted_id > 0) {
				$payload->post_id = (int) $inserted_id;

				// Assign categories & tags.
				if (!empty($payload->categories)) {
					wp_set_post_categories($payload->post_id, (array) $payload->categories);
				}
				if (!empty($payload->tags)) {
					wp_set_post_tags($payload->post_id, (array) $payload->tags);
				}

				// Assign metadata.
				update_post_meta($payload->post_id, 'aips_generation_completed_at', current_time('mysql'));
				update_post_meta($payload->post_id, 'aips_pipeline_stages', wp_json_encode($payload->stage_timings));

				if (!empty($payload->seo_metadata)) {
					update_post_meta($payload->post_id, 'aips_seo_metadata', $payload->seo_metadata);
				}

				$component_statuses = array(
					'title'   => !empty($payload->title),
					'content' => !empty($payload->formatted_content ?: $payload->raw_content),
					'excerpt' => !empty($payload->excerpt),
					'seo'     => !empty($payload->seo_metadata),
				);
				update_post_meta($payload->post_id, 'aips_post_generation_component_statuses', wp_json_encode($component_statuses));
				update_post_meta($payload->post_id, 'aips_post_generation_incomplete', 'false');
			}
		}

		return $next($payload);
	}
}
