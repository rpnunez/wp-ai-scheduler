<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles presentation and transformation of History container data for the modal.
 *
 * Encapsulates modal container preparation, log normalization, AI request/response
 * pairing, timeline metadata assembly, JSON tree generation, and diagnostic cards.
 */
class AIPS_History_Modal_Presenter {

	/**
     * Build a normalized modal payload for a history container.
     *
     * Centralizes log normalization, AI request/response pairing, filter counts,
     * and JSON tree preparation so both modal entry points render the same data.
     *
     * @param object $history_item History container row with attached log rows.
     * @param bool   $format_dates Whether to format created/completed times for display.
     * @return array<string,mixed>
     */
	public function prepare_history_modal_view_data($history_item, $format_dates = false) {
		$logs = $this->normalize_history_logs(isset($history_item->log) ? $history_item->log : array());
		$container = $this->build_history_modal_container($history_item, $format_dates);
		$container['trigger_info'] = $this->extract_history_trigger_info($logs, $history_item, $container);
		$analysis = $this->analyze_history_modal_summary($container, $logs);
		$container = $this->finalize_history_modal_container($container, $analysis);
		$container_id = isset($container['id']) ? (int) $container['id'] : 0;
		$detail_id_prefix = 'aips-log-detail-' . $container_id . '-' . (int) (microtime(true) * 1000000) . '-' . mt_rand(1000, 9999);
		$display_logs = $this->build_history_display_logs($logs, $detail_id_prefix);
		$filter_counts = $this->build_history_filter_counts($display_logs);
		$timeline_data = $this->build_history_timeline_data($container, $history_item, $display_logs);

		return array(
			'container'     => $container,
			'logs'          => $logs,
			'display_logs'  => $display_logs,
			'filter_counts' => $filter_counts,
			'timeline_data' => $timeline_data,
		);
	}

	/**
     * Build rich milestone and chronological data for the History modal Timeline tab.
     *
     * @param array<string,mixed>            $container    Prepared container payload.
     * @param object                         $history_item History container database object.
     * @param array<int,array<string,mixed>> $display_logs Prepared display logs.
     * @return array<string,mixed>
     */
	public function build_history_timeline_data($container, $history_item, $display_logs) {
		$post_id = !empty($history_item->post_id) ? (int) $history_item->post_id : (!empty($container['post_id']) ? (int) $container['post_id'] : 0);
		$post = $post_id ? get_post($post_id) : null;

		// Only generation runs own the post's components; event rows (publish,
		// delete, lifecycle, ...) just link to the post.
		$creation_method = isset($history_item->creation_method) ? (string) $history_item->creation_method : '';
		$is_generation   = isset($history_item->record_kind) && AIPS_History_Record_Kind::is_valid($history_item->record_kind)
			? ($history_item->record_kind === AIPS_History_Record_Kind::GENERATION)
			: AIPS_History::is_generation_method($creation_method);
		$show_milestones = $post_id > 0 && $is_generation;

		$post_type = '';
		if ($post) {
			$post_type = $post->post_type;
		} elseif (!empty($history_item->post_type)) {
			$post_type = $history_item->post_type;
		} elseif (!empty($container['post_type'])) {
			$post_type = $container['post_type'];
		}
		$post_type_label = AIPS_History::humanize_post_type_label($post_type);

		// Title
		$generated_title = '';
		if ($post && !empty($post->post_title)) {
			$generated_title = $post->post_title;
		} elseif (!empty($history_item->generated_title)) {
			$generated_title = $history_item->generated_title;
		} elseif (!empty($container['generated_title'])) {
			$generated_title = $container['generated_title'];
		}

		// Excerpt snippet (up to 30 chars)
		$excerpt_raw = '';
		if ($post && !empty($post->post_excerpt)) {
			$excerpt_raw = $post->post_excerpt;
		}
		if (empty($excerpt_raw) && $post && !empty($post->post_content)) {
			$excerpt_raw = wp_strip_all_tags($post->post_content);
		}
		$excerpt_clean = trim(preg_replace('/\s+/', ' ', (string) $excerpt_raw));
		$excerpt_30 = mb_substr($excerpt_clean, 0, 30);
		if (mb_strlen($excerpt_clean) > 30) {
			$excerpt_30 .= '…';
		}

		// Featured image
		$thumb_id = $post ? get_post_thumbnail_id($post->ID) : 0;
		$thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '';
		$thumb_edit_url = $thumb_id ? admin_url('post.php?post=' . $thumb_id . '&action=edit') : '';

		// Content
		$has_content = false;
		if ($post && !empty(trim($post->post_content))) {
			$has_content = true;
		}

		// Component statuses recorded by the generator (decoded from JSON post meta).
		$component_statuses = array();
		$incomplete_components = array();
		if ($show_milestones && $post) {
			$raw_statuses = get_post_meta($post->ID, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, true);
			$decoded      = is_string($raw_statuses) ? json_decode($raw_statuses, true) : $raw_statuses;
			if (is_array($decoded)) {
				$component_statuses = $decoded;
			}

			$component_labels = array(
				'post_title'     => __('title', 'ai-post-scheduler'),
				'post_excerpt'   => __('excerpt', 'ai-post-scheduler'),
				'featured_image' => __('featured image', 'ai-post-scheduler'),
				'post_content'   => __('content', 'ai-post-scheduler'),
			);
			foreach ($component_statuses as $component => $ok) {
				if ($ok === false) {
					$incomplete_components[] = isset($component_labels[$component]) ? $component_labels[$component] : (string) $component;
				}
			}
		}

		// Featured image: distinguish "failed" from "not generated / not requested".
		$image_status = 'none';
		if (!empty($thumb_url)) {
			$image_status = 'present';
		} elseif (array_key_exists('featured_image', $component_statuses) && $component_statuses['featured_image'] === false) {
			$image_status = 'failed';
		}

		// Custom fields: user-facing meta only. Underscore-prefixed meta (including
		// every _aips_* bookkeeping key) is internal and never shown here.
		$custom_fields = array();
		if ($show_milestones && $post) {
			$hidden_meta_keys = (array) apply_filters('aips_history_timeline_hidden_meta_keys', array('aips_source_snapshots_used'), $post->ID);
			$all_meta = get_post_meta($post->ID);
			if (is_array($all_meta)) {
				foreach ($all_meta as $meta_key => $meta_vals) {
					if (strpos($meta_key, '_') === 0 || in_array($meta_key, $hidden_meta_keys, true)) {
						continue;
					}
					$custom_fields[] = array(
						'name'    => $meta_key,
						'label'   => ucwords(str_replace(array('_', '-'), ' ', $meta_key)),
						'success' => !empty($meta_vals[0]),
					);
				}
			}
		}

		// Errors & Warnings
		$error_text = !empty($container['root_issue']) ? $container['root_issue'] : (!empty($history_item->error_message) ? $history_item->error_message : '');
		$warning_text = '';
		if (!empty($incomplete_components)) {
			/* translators: %s: comma-separated list of components that did not generate */
			$warning_text = sprintf(__('Generation incomplete: %s did not generate.', 'ai-post-scheduler'), implode(', ', $incomplete_components));
		}

		// Format chronological events
		$formatted_events = array();
		foreach ($display_logs as $log) {
			$raw_type = !empty($log['log_type']) ? $log['log_type'] : (!empty($log['type_label']) ? $log['type_label'] : '');
			$type_label = $this->humanize_timeline_event_label($raw_type);

			$ts = isset($log['timestamp']) ? $log['timestamp'] : null;
			$human_time = '';
			if ($ts) {
				if (is_numeric($ts)) {
					$human_time = wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $ts);
				} else {
					$parsed = strtotime((string) $ts);
					$human_time = $parsed ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $parsed) : (string) $ts;
				}
			}

			$message = '';
			if (!empty($log['sections'][0]['message_html'])) {
				$message = $log['sections'][0]['message_html'];
			} elseif (!empty($log['sections'][0]['label'])) {
				$message = $log['sections'][0]['label'];
			}

			if (empty($warning_text) && in_array('warning', (array) ($log['type_ids'] ?? array()), true)) {
				$warning_text = wp_strip_all_tags($message);
			}

			$formatted_events[] = array(
				'title'      => $type_label,
				'time'       => $human_time,
				'message'    => $message,
				'type_class' => isset($log['type_class']) ? $log['type_class'] : 'neutral',
			);
		}

		return array(
			'post_type_label' => $post_type_label,
			'attempt_text'    => sprintf(__('Attempting to create a %s', 'ai-post-scheduler'), $post_type_label),
			'post_title'      => $generated_title,
			'post_excerpt'    => $excerpt_30,
			'show_milestones' => $show_milestones,
			'has_image'       => !empty($thumb_url),
			'image_status'    => $image_status,
			'image_url'       => $thumb_url,
			'image_edit_url'  => $thumb_edit_url,
			'has_content'     => $has_content,
			'custom_fields'   => $custom_fields,
			'error_text'      => $error_text,
			'warning_text'    => $warning_text,
			'events'          => $formatted_events,
		);
	}

	/**
     * Map raw log type keys to user-facing Title Cased labels for the Timeline.
     *
     * @param string $raw_type Raw log type string.
     * @return string
     */
	public function humanize_timeline_event_label($raw_type) {
		$map = array(
			'metric_generation_result' => __('Generation Metric', 'ai-post-scheduler'),
			'log'                      => __('Activity Log', 'ai-post-scheduler'),
			'info'                     => __('Information', 'ai-post-scheduler'),
			'error'                    => __('Error', 'ai-post-scheduler'),
			'warning'                  => __('Warning', 'ai-post-scheduler'),
			'session_metadata'         => __('Session Metadata', 'ai-post-scheduler'),
			'trigger_source'           => __('Trigger Source', 'ai-post-scheduler'),
			'trigger_method'           => __('Trigger Method', 'ai-post-scheduler'),
			'post_content'             => __('Post Content', 'ai-post-scheduler'),
			'post_title'               => __('Post Title', 'ai-post-scheduler'),
			'post_excerpt'             => __('Post Excerpt', 'ai-post-scheduler'),
			'featured_image'           => __('Featured Image', 'ai-post-scheduler'),
			'metadata'                 => __('Metadata', 'ai-post-scheduler'),
			'ai_request'               => __('AI Request', 'ai-post-scheduler'),
			'ai_response'              => __('AI Response', 'ai-post-scheduler'),
		);
		$key = strtolower(trim((string) $raw_type));
		if (isset($map[$key])) {
			return $map[$key];
		}
		return ucwords(str_replace(array('_', '-'), ' ', (string) $raw_type));
	}

	/**
     * Convert the base container + derived analysis into the final modal payload.
     *
     * @param array<string,mixed> $container Base container metadata.
     * @param array<string,mixed> $analysis  Derived summary analysis.
     * @return array<string,mixed>
     */
	public function finalize_history_modal_container($container, $analysis) {
		return array(
			'id'               => isset($container['id']) ? (int) $container['id'] : 0,
			'header_title'     => $this->build_history_modal_header_title($container),
			'status'           => isset($container['status']) ? (string) $container['status'] : '',
			'status_class'     => isset($container['status_class']) ? (string) $container['status_class'] : '',
			'header_actions'   => $this->build_history_modal_header_actions($container),
			'trigger_info'     => isset($container['trigger_info']) ? $container['trigger_info'] : array(),
			'summary_lines'    => $this->build_history_summary_lines($analysis),
			'summary_meta'     => $this->build_history_summary_meta($container),
			'detail_cards'     => $this->build_history_detail_cards($container),
			'root_issue'       => !empty($container['error_message']) ? (string) $container['error_message'] : '',
			'suggested_action' => $this->build_history_suggested_action($container),
			'diagnostic_text'  => $this->build_history_diagnostic_text($container, $analysis),
		);
	}

	/**
     * Work out what triggered a run and how, for the Overview tab.
     *
     * @param array<int,array<string,mixed>> $logs         Normalized logs.
     * @param object                         $history_item History container row.
     * @param array<string,mixed>            $container    Base container metadata.
     * @return array{source_message:string,method_label:string,method:string,source:array}
     */
	public function extract_history_trigger_info($logs, $history_item, $container) {
		$source        = null;
		$method_label  = '';
		$method        = '';
		$source_msg    = '';

		foreach ($logs as $log) {
			$details = !empty($log['details']) && is_array($log['details']) ? $log['details'] : array();
			if ($log['log_type'] === 'trigger_source' && $source === null) {
				$source     = isset($details['input']) && is_array($details['input']) ? $details['input'] : array();
				$source_msg = AIPS_Generation_Trigger::format_source_message($source);
			} elseif ($log['log_type'] === 'trigger_method' && $method === '') {
				$input        = isset($details['input']) && is_array($details['input']) ? $details['input'] : array();
				$method       = isset($input['method']) ? (string) $input['method'] : '';
				$method_label = isset($input['method_label']) ? (string) $input['method_label'] : '';
			}
		}

		if ($method === '' || $method_label === '') {
			$creation_method = isset($container['creation_method']) ? $container['creation_method'] : '';
			if (AIPS_Generation_Trigger::classify_creation_method($creation_method) !== AIPS_Generation_Trigger::METHOD_UNKNOWN) {
				$described    = AIPS_Generation_Trigger::describe_method($creation_method);
				$method       = $described['method'];
				$method_label = $described['label'];
			} else {
				$method       = '';
				$method_label = '';
			}
		}

		if ($source === null) {
			$source     = $this->build_legacy_history_trigger_source($history_item);
			$source_msg = AIPS_Generation_Trigger::format_source_message($source);
		}

		return array(
			'source_message' => $source_msg,
			'method_label'   => $method_label,
			'method'         => $method,
			'source'         => $source,
		);
	}

	/**
     * Best-effort trigger source for runs recorded before trigger entries existed.
     *
     * @param object $history_item History container row.
     * @return array<string,mixed>
     */
	public function build_legacy_history_trigger_source($history_item) {
		$source = array();

		try {
			if (!empty($history_item->template_id)) {
				$source['template_id'] = (int) $history_item->template_id;
				$template = AIPS_Template_Repository::instance()->get_by_id((int) $history_item->template_id);
				if ($template && !empty($template->name)) {
					$source['template_name'] = (string) $template->name;
				}
			}

			if (!empty($history_item->campaign_id)) {
				$source['campaign_id'] = (int) $history_item->campaign_id;
				$campaign = AIPS_Campaigns_Repository::instance()->get_campaign_by_id((int) $history_item->campaign_id);
				if ($campaign && !empty($campaign->name)) {
					$source['campaign_name'] = (string) $campaign->name;
				}
			}

			if (!empty($history_item->author_id)) {
				$source['author_id'] = (int) $history_item->author_id;
				$author = AIPS_Authors_Repository::instance()->get_by_id((int) $history_item->author_id);
				if ($author && !empty($author->name)) {
					$source['author_name'] = (string) $author->name;
				}
			}
		} catch (\Throwable $e) {
			// Names are a nicety; IDs alone are still useful.
		}

		if (!empty($history_item->topic_id)) {
			$source['topic_id'] = (int) $history_item->topic_id;
		}

		return $source;
	}

	/**
     * Derive high-level summary insights from the container and its logs.
     *
     * @param array<string,mixed>            $container Prepared container payload.
     * @param array<int,array<string,mixed>> $logs      Normalized logs.
     * @return array<string,string>
     */
	public function analyze_history_modal_summary($container, $logs) {
		$text = strtolower(((string) $container['creation_method']) . ' ' . ((string) $container['template_name']));
		if (strpos($text, 'content_index') !== false || strpos($text, 'content index') !== false) {
			$what_happened = __('Content indexing', 'ai-post-scheduler');
		} elseif (strpos($text, 'research') !== false) {
			$what_happened = __('Research run', 'ai-post-scheduler');
		} elseif (strpos($text, 'embedding') !== false) {
			$what_happened = __('Embeddings processing', 'ai-post-scheduler');
		} elseif (strpos($text, 'author') !== false && strpos($text, 'topic') !== false && strpos((string) $container['creation_method'], '_post') === false) {
			$what_happened = __('Author topic generation', 'ai-post-scheduler');
		} elseif (strpos($text, 'schedule') !== false) {
			$what_happened = __('Scheduled post generation', 'ai-post-scheduler');
		} else {
			$what_happened = $this->history_logs_include_ai_activity($logs)
				? __('Post generation', 'ai-post-scheduler')
				: __('Automation task', 'ai-post-scheduler');
		}

		$outcome = in_array($container['status'], array('completed', 'indexed'), true)
			? __('Success', 'ai-post-scheduler')
			: ($container['status'] === 'failed'
				? __('Failed', 'ai-post-scheduler')
				: ($container['status'] === 'stalled'
					? __('Stalled', 'ai-post-scheduler')
					: ($container['status'] === 'partial'
						? __('Partial', 'ai-post-scheduler')
						: ($container['status'] === 'terminated'
							? __('Terminated', 'ai-post-scheduler')
							: __('In progress', 'ai-post-scheduler')))));

		$saw_title_change = false;
		$saw_content_change = false;
		$saw_image_change = false;
		$saw_published_result = false;
		$saw_draft_result = false;
		$saw_embedding = false;
		$saw_relationships = false;
		$embedding_dims = 0;

		foreach ($logs as $log) {
			if (isset($log['log_type']) && in_array($log['log_type'], array('trigger_source', 'trigger_method'), true)) {
				continue;
			}
			$details = !empty($log['details']) && is_array($log['details']) ? $log['details'] : array();
			$dimensions_found = false;
			$dimensions = $this->find_history_detail_value($details, 'dimensions', $dimensions_found);
			if ($dimensions_found && (int) $dimensions > 0) {
				$saw_embedding = true;
				$embedding_dims = (int) $dimensions;
			}

			$relationships_found = false;
			$this->find_history_detail_value($details, 'relationships_saved', $relationships_found);
			if ($relationships_found) {
				$saw_relationships = true;
			}
			$this->scan_history_values_for_changes($details, $saw_title_change, $saw_content_change, $saw_image_change, $saw_published_result, $saw_draft_result);
		}

		$changes = array();
		if ($saw_embedding) {
			if ($embedding_dims > 0) {
				$changes[] = sprintf(__('Generated %d-dimension vector', 'ai-post-scheduler'), $embedding_dims);
			} else {
				$changes[] = __('Generated embedding vector', 'ai-post-scheduler');
			}
		}
		if ($saw_relationships) {
			$changes[] = __('Recomputed related posts', 'ai-post-scheduler');
		}
		if ($saw_title_change) {
			$changes[] = __('Title updated', 'ai-post-scheduler');
		}
		if ($saw_content_change) {
			$changes[] = __('Content updated', 'ai-post-scheduler');
		}
		if ($saw_image_change) {
			$changes[] = __('Image generated/updated', 'ai-post-scheduler');
		}
		if ($saw_published_result) {
			$changes[] = __('Published result', 'ai-post-scheduler');
		} elseif ($saw_draft_result) {
			$changes[] = __('Draft result', 'ai-post-scheduler');
		}
		if ($container['status'] === 'failed') {
			$changes[] = __('Run ended with an error', 'ai-post-scheduler');
		}

		return array(
			'what_happened' => $what_happened,
			'outcome_label' => $outcome,
			'what_changed'  => !empty($changes) ? implode('; ', $changes) : __('No major content changes detected', 'ai-post-scheduler'),
		);
	}

	/**
     * Find a keyed value anywhere in nested History log details.
     *
     * @param mixed  $value Current value to inspect.
     * @param string $key   Key to find.
     * @param bool   $found Whether the key was found.
     * @return mixed|null
     */
	public function find_history_detail_value($value, $key, &$found) {
		if (!is_array($value)) {
			return null;
		}

		if (array_key_exists($key, $value)) {
			$found = true;
			return $value[$key];
		}

		foreach ($value as $child) {
			$result = $this->find_history_detail_value($child, $key, $found);
			if ($found) {
				return $result;
			}
		}

		return null;
	}

	/**
     * Build the modal header title shown above the log content.
     *
     * @param array<string,mixed> $container Base container metadata.
     * @return string
     */
	public function build_history_modal_header_title($container) {
		$id = isset($container['id']) ? (int) $container['id'] : 0;
		$title = isset($container['generated_title']) ? trim((string) $container['generated_title']) : '';

		if ($title !== '') {
			return $title . ' #' . $id;
		}

		return __('History Details', 'ai-post-scheduler') . ($id > 0 ? ' #' . $id : '');
	}

	/**
     * Build top-of-modal action links.
     *
     * @param array<string,mixed> $container Base container metadata.
     * @return array<int,array<string,string>>
     */
	public function build_history_modal_header_actions($container) {
		$actions = array();

		if (!empty($container['post_url'])) {
			$actions[] = array(
				'label' => !empty($container['post_id'])
					? sprintf(__('View Post (ID: %d)', 'ai-post-scheduler'), (int) $container['post_id'])
					: __('View Post', 'ai-post-scheduler'),
				'url'   => (string) $container['post_url'],
			);
		}

		if (!empty($container['post_edit_url'])) {
			$actions[] = array(
				'label' => __('Edit', 'ai-post-scheduler'),
				'url'   => (string) $container['post_edit_url'],
			);
		}

		return $actions;
	}

	/**
     * Build the combined Summary section lines.
     *
     * @param array<string,mixed> $analysis Derived analysis payload.
     * @return array<int,array<string,string>>
     */
	public function build_history_summary_lines($analysis) {
		$lines = array();

		if (!empty($analysis['outcome_label'])) {
			$lines[] = array(
				'label' => __('Outcome', 'ai-post-scheduler'),
				'value' => (string) $analysis['outcome_label'],
			);
		}

		if (!empty($analysis['what_happened'])) {
			$lines[] = array(
				'label' => __('What happened', 'ai-post-scheduler'),
				'value' => (string) $analysis['what_happened'],
			);
		}

		if (!empty($analysis['what_changed'])) {
			$lines[] = array(
				'label' => __('What changed', 'ai-post-scheduler'),
				'value' => (string) $analysis['what_changed'],
			);
		}

		return $lines;
	}

	/**
     * Build stacked Summary metadata values.
     *
     * @param array<string,mixed> $container Final base container metadata.
     * @return array<int,array<string,string>>
     */
	public function build_history_summary_meta($container) {
		$items = array();

		if (!empty($container['created_at'])) {
			$items[] = array(
				'label' => __('Created', 'ai-post-scheduler'),
				'value' => (string) $container['created_at'],
			);
		}

		if (!empty($container['duration_label'])) {
			$items[] = array(
				'label' => __('Duration', 'ai-post-scheduler'),
				'value' => (string) $container['duration_label'],
			);
		}

		return $items;
	}

	/**
     * Build the remaining detail cards shown beneath Summary.
     *
     * @param array<string,mixed> $container Base container metadata.
     * @return array<int,array<string,string>>
     */
	public function build_history_detail_cards($container) {
		$cards = array();

		if (!empty($container['trigger_info']['source_message'])) {
			$cards[] = array(
				'label' => __('Triggered By', 'ai-post-scheduler'),
				'value' => (string) $container['trigger_info']['source_message'],
				'class' => '',
			);
		}

		if (!empty($container['trigger_info']['method_label'])) {
			$cards[] = array(
				'label' => __('Trigger Type', 'ai-post-scheduler'),
				'value' => (string) $container['trigger_info']['method_label'],
				'class' => '',
			);
		}

		if (!empty($container['template_name'])) {
			$cards[] = array(
				'label' => __('Template', 'ai-post-scheduler'),
				'value' => (string) $container['template_name'],
				'class' => '',
			);
		}

		if (!empty($container['creation_method'])) {
			$cards[] = array(
				'label' => __('Method', 'ai-post-scheduler'),
				'value' => ucfirst(str_replace('_', ' ', (string) $container['creation_method'])),
				'class' => '',
			);
		}

		foreach (array(
			'actor_type'     => __('Actor', 'ai-post-scheduler'),
			'topic_id'       => __('Topic ID', 'ai-post-scheduler'),
			'completed_at'   => __('Completed', 'ai-post-scheduler'),
			'correlation_id' => __('Correlation ID', 'ai-post-scheduler'),
			'id'             => __('Container ID', 'ai-post-scheduler'),
		) as $key => $label) {
			if (!empty($container[$key])) {
				$cards[] = array(
					'label' => $label,
					'value' => (string) $container[$key],
					'class' => '',
				);
			}
		}

		if (!empty($container['post_id']) && empty($container['post_url']) && empty($container['post_edit_url'])) {
			$cards[] = array(
				'label' => __('Post ID', 'ai-post-scheduler'),
				'value' => (string) $container['post_id'],
				'class' => '',
			);
		}

		if (!empty($container['error_message'])) {
			$cards[] = array(
				'label' => __('Error', 'ai-post-scheduler'),
				'value' => (string) $container['error_message'],
				'class' => 'aips-history-summary-item-error',
			);
		}

		return $cards;
	}

	/**
     * Build suggested action string for a container.
     *
     * @param array<string,mixed> $container Container data.
     * @return string
     */
	public function build_history_suggested_action($container) {
		if ($container['status'] === 'failed') {
			return !empty($container['template_name'])
				? __('Review the error and retry this run after correcting its template or AI configuration.', 'ai-post-scheduler')
				: __('Review the error and technical log before retrying the originating workflow.', 'ai-post-scheduler');
		}
		if ($container['status'] === 'stalled') {
			return __('No activity has been logged for a while. If it does not resume, use Clear In-Progress on the History page to close it, then retry the originating workflow.', 'ai-post-scheduler');
		}
		if ($container['status'] === 'processing') {
			return __('Allow the run to finish; reload History if it remains in progress unexpectedly.', 'ai-post-scheduler');
		}
		return '';
	}

	/**
     * Build multiline diagnostic text for a container.
     *
     * @param array<string,mixed> $container Container data.
     * @param array<string,mixed> $analysis  Analysis data.
     * @return string
     */
	public function build_history_diagnostic_text($container, $analysis) {
		$lines = array(
			sprintf(__('History #%d', 'ai-post-scheduler'), (int) $container['id']),
			sprintf(__('Status: %s', 'ai-post-scheduler'), (string) $container['status']),
			sprintf(__('What happened: %s', 'ai-post-scheduler'), (string) $analysis['what_happened']),
			sprintf(__('What changed: %s', 'ai-post-scheduler'), (string) $analysis['what_changed']),
		);
		if (!empty($container['trigger_info']['source_message'])) {
			$lines[] = sprintf(__('Triggered by: %s', 'ai-post-scheduler'), (string) $container['trigger_info']['source_message']);
		}
		if (!empty($container['trigger_info']['method_label'])) {
			$lines[] = sprintf(__('Trigger type: %s', 'ai-post-scheduler'), (string) $container['trigger_info']['method_label']);
		}
		if (!empty($container['error_message'])) {
			$lines[] = sprintf(__('Error: %s', 'ai-post-scheduler'), (string) $container['error_message']);
		}
		if (!empty($container['correlation_id'])) {
			$lines[] = sprintf(__('Correlation ID: %s', 'ai-post-scheduler'), (string) $container['correlation_id']);
		}
		return implode("\n", $lines);
	}

	/**
     * Determine whether the log set contains AI activity.
     *
     * @param array<int,array<string,mixed>> $logs Normalized logs.
     * @return bool
     */
	public function history_logs_include_ai_activity($logs) {
		foreach ($logs as $log) {
			if ($this->is_ai_request_history_log($log) || $this->is_ai_response_history_log($log)) {
				return true;
			}
		}

		return false;
	}

	/**
     * Recursively scan log detail values for coarse content change indicators.
     *
     * @param mixed $value Current value to scan.
     * @param bool  $saw_title_change
     * @param bool  $saw_content_change
     * @param bool  $saw_image_change
     * @param bool  $saw_published_result
     * @param bool  $saw_draft_result
     * @return void
     */
	public function scan_history_values_for_changes($value, &$saw_title_change, &$saw_content_change, &$saw_image_change, &$saw_published_result, &$saw_draft_result) {
		if (is_string($value) && $value !== '') {
			$normalized = strtolower($value);
			if (strpos($normalized, 'title') !== false) {
				$saw_title_change = true;
			}
			if (strpos($normalized, 'content') !== false || strpos($normalized, 'body') !== false) {
				$saw_content_change = true;
			}
			if (strpos($normalized, 'featured image') !== false || strpos($normalized, 'image') !== false) {
				$saw_image_change = true;
			}
			if (strpos($normalized, 'publish') !== false) {
				$saw_published_result = true;
			}
			if (strpos($normalized, 'draft') !== false) {
				$saw_draft_result = true;
			}
			return;
		}

		if (!is_array($value)) {
			return;
		}

		foreach ($value as $child) {
			$this->scan_history_values_for_changes($child, $saw_title_change, $saw_content_change, $saw_image_change, $saw_published_result, $saw_draft_result);
		}
	}

	/**
     * Normalize raw DB log rows into a consistent array structure.
     *
     * @param array $raw_logs Raw aips_history_log rows.
     * @return array<int,array<string,mixed>>
     */
	public function normalize_history_logs($raw_logs) {
		$logs = array();

		foreach ($raw_logs as $log) {
			$details = array();
			if (!empty($log->details)) {
				$decoded = json_decode($log->details, true);
				if (is_array($decoded)) {
					$details = $decoded;
				}
			}

			$log_type_label = isset($details['log_subtype']) ? (string) $details['log_subtype'] : '';

			$logs[] = array(
				'id'              => (int) $log->id,
				'log_type'        => $log_type_label,
				'history_type_id' => (int) $log->history_type_id,
				'type_label'      => AIPS_History_Type::get_label((int) $log->history_type_id),
				'type_class'      => $this->get_history_type_class((int) $log->history_type_id),
				'timestamp'       => isset($log->timestamp) ? (string) $log->timestamp : '',
				'details'         => $details,
			);
		}

		return $logs;
	}

	/**
     * Build container metadata for the logs modal.
     *
     * @param object $history_item  History container row.
     * @param bool   $format_dates  Whether to convert times to human-readable strings.
     * @return array<string,mixed>
     */
	public function build_history_modal_container($history_item, $format_dates = false) {
		$duration_seconds = $this->calculate_history_duration_seconds($history_item);
		$post_id = !empty($history_item->post_id) ? (int) $history_item->post_id : null;
		$post_urls = $this->build_history_post_urls($post_id);
		$created_at = isset($history_item->created_at) ? $history_item->created_at : '';
		if ($format_dates) {
			$created_at = !empty($created_at) ? AIPS_DateTime::formatRelativeOrAbsolute($created_at) : '';
		}
		$completed_at = isset($history_item->completed_at) ? $history_item->completed_at : '';
		if ($format_dates) {
			$completed_at = !empty($completed_at) ? AIPS_DateTime::formatRelativeOrAbsolute($completed_at) : '';
		}
		$creation_method = isset($history_item->creation_method) ? (string) $history_item->creation_method : '';
		// An idle in-progress run is shown as Stalled rather than Processing.
		$display_status = isset($history_item->effective_status)
			? (string) $history_item->effective_status
			: (isset($history_item->status) ? (string) $history_item->status : '');
		$actor_type = isset($history_item->actor_type) ? (string) $history_item->actor_type : '';
		if ($actor_type === '') {
			$actor_type = strpos($creation_method, 'manual') !== false || strpos($creation_method, 'admin') !== false ? 'admin' : 'system';
		}

		return array(
			'id'               => (int) $history_item->id,
			'status'           => $display_status,
			'status_class'     => $this->get_history_status_class($display_status),
			'generated_title'  => isset($history_item->generated_title) ? $history_item->generated_title : '',
			'template_name'    => isset($history_item->template_name) ? $history_item->template_name : '',
			'created_at'       => $created_at,
			'error_message'    => isset($history_item->error_message) ? $history_item->error_message : '',
			'post_id'          => $post_id,
			'post_url'         => $post_urls['post_url'],
			'post_edit_url'    => $post_urls['post_edit_url'],
			'creation_method'  => $creation_method,
			'actor_type'       => $actor_type,
			'topic_id'         => !empty($history_item->topic_id) ? (int) $history_item->topic_id : null,
			'correlation_id'   => isset($history_item->correlation_id) ? (string) $history_item->correlation_id : '',
			'completed_at'     => $completed_at,
			'duration_seconds' => $duration_seconds,
			'duration_label'   => $this->format_history_duration_label($duration_seconds),
		);
	}

	/**
     * Calculate duration for a history container from its timestamps.
     *
     * @param object $history_item History container row.
     * @return int|null
     */
	public function calculate_history_duration_seconds($history_item) {
		$start = $this->normalize_history_timestamp_for_math(isset($history_item->created_at) ? $history_item->created_at : null);
		$end = $this->normalize_history_timestamp_for_math(isset($history_item->completed_at) ? $history_item->completed_at : null);

		if ($start === null || $end === null || $end < $start) {
			return null;
		}

		return $end - $start;
	}

	/**
     * Convert stored timestamps into a unix timestamp when possible.
     *
     * @param mixed $value Raw timestamp value.
     * @return int|null
     */
	public function normalize_history_timestamp_for_math($value) {
		if ($value === null || $value === '') {
			return null;
		}

		if (is_numeric($value)) {
			$int_value = (int) $value;
			if ($int_value > 100000000) {
				return $int_value;
			}
		}

		$parsed = strtotime((string) $value);
		return $parsed !== false ? $parsed : null;
	}

	/**
     * Build post and edit URLs for a linked post.
     *
     * @param int|null $post_id Linked post ID.
     * @return array<string,string|null>
     */
	public function build_history_post_urls($post_id) {
		$post_url = null;
		$post_edit_url = null;

		if (!empty($post_id)) {
			$raw_post_url = get_permalink((int) $post_id);
			if (!empty($raw_post_url)) {
				$sanitized_post_url = esc_url_raw($raw_post_url);
				$post_url = !empty($sanitized_post_url) ? $sanitized_post_url : null;
			}

			$raw_post_edit_url = get_edit_post_link((int) $post_id, 'raw');
			if (!empty($raw_post_edit_url)) {
				$sanitized_post_edit_url = esc_url_raw($raw_post_edit_url);
				$post_edit_url = !empty($sanitized_post_edit_url) ? $sanitized_post_edit_url : null;
			}
		}

		return array(
			'post_url'      => $post_url,
			'post_edit_url' => $post_edit_url,
		);
	}

	/**
     * Format a stored log timestamp (unix or datetime string) for display.
     *
     * @param mixed $value Raw timestamp value.
     * @return string
     */
	public function format_history_log_timestamp($value) {
		$ts = $this->normalize_history_timestamp_for_math($value);
		if ($ts === null) {
			return (string) $value;
		}

		return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $ts);
	}

	/**
     * Pair AI request/response logs in a single pass and prepare display rows.
     *
     * @param array<int,array<string,mixed>> $logs             Normalized logs.
     * @param string                         $detail_id_prefix Unique prefix for detail panel IDs in this render.
     * @return array<int,array<string,mixed>>
     */
	public function build_history_display_logs($logs, $detail_id_prefix) {
		$display_logs = array();
		$pending_requests = array();
		$detail_sequence = 0;

		foreach ($logs as $log) {
			if ($this->is_ai_request_history_log($log)) {
				$phase_key = $this->derive_ai_phase_key($log);
				$display_logs[] = array(
					'timestamp'         => $log['timestamp'],
					'timestamp_display' => $this->format_history_log_timestamp($log['timestamp']),
					'type_label'        => __('AI Request / Response', 'ai-post-scheduler'),
					'type_class'        => $this->get_history_type_class(5),
					'log_type'          => $this->humanize_ai_phase_label($phase_key),
					'type_ids'          => array('5'),
					'sections'          => array(
						$this->build_history_log_section($log, __('AI Request', 'ai-post-scheduler'), $detail_sequence, $detail_id_prefix),
					),
				);
				$display_index = count($display_logs) - 1;
				if (!isset($pending_requests[$phase_key])) {
					$pending_requests[$phase_key] = array();
				}
				$pending_requests[$phase_key][] = $display_index;
				$detail_sequence++;
				continue;
			}

			if ($this->is_ai_response_history_log($log)) {
				$phase_key = $this->derive_ai_phase_key($log);
				if (!empty($pending_requests[$phase_key])) {
					$display_index = array_shift($pending_requests[$phase_key]);
					$display_logs[$display_index]['sections'][] = $this->build_history_log_section($log, __('AI Response', 'ai-post-scheduler'), $detail_sequence, $detail_id_prefix);
					if (!in_array('6', $display_logs[$display_index]['type_ids'], true)) {
						$display_logs[$display_index]['type_ids'][] = '6';
					}
				} else {
					$display_logs[] = array(
						'timestamp'         => $log['timestamp'],
						'timestamp_display' => $this->format_history_log_timestamp($log['timestamp']),
						'type_label'        => __('AI Response', 'ai-post-scheduler'),
						'type_class'        => $this->get_history_type_class(6),
						'log_type'          => $this->humanize_ai_phase_label($phase_key),
						'type_ids'          => array('6'),
						'sections'          => array(
							$this->build_history_log_section($log, __('AI Response', 'ai-post-scheduler'), $detail_sequence, $detail_id_prefix),
						),
					);
				}
				$detail_sequence++;
				continue;
			}

			$display_logs[] = array(
				'timestamp'         => $log['timestamp'],
				'timestamp_display' => $this->format_history_log_timestamp($log['timestamp']),
				'type_label'        => $log['type_label'],
				'type_class'        => $log['type_class'],
				'log_type'          => $log['log_type'],
				'type_ids'          => array((string) $log['history_type_id']),
				'sections'          => array(
					$this->build_history_log_section($log, '', $detail_sequence, $detail_id_prefix),
				),
			);
			$detail_sequence++;
		}

		return $display_logs;
	}

	/**
     * Prepare the detail-section payload for one rendered log section.
     *
     * @param array<string,mixed> $log             Normalized log entry.
     * @param string              $section_label   Label displayed above the section.
     * @param int                 $detail_sequence Unique detail panel sequence.
     * @param string              $detail_id_prefix Unique detail panel prefix for this render.
     * @return array<string,mixed>
     */
	public function build_history_log_section($log, $section_label, $detail_sequence, $detail_id_prefix) {
		$extra = $this->extract_history_log_extra_details($log);
		$raw_json = !empty($extra)
			? wp_json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
			: '';

		return array(
			'label'             => $section_label,
			'show_header'       => $section_label !== '',
			'timestamp'         => isset($log['timestamp']) ? $log['timestamp'] : '',
			'timestamp_display' => $this->format_history_log_timestamp(isset($log['timestamp']) ? $log['timestamp'] : ''),
			'message_html'      => !empty($log['details']['message'])
				? $this->format_history_multiline_text($log['details']['message'])
				: '',
			'has_extra'         => !empty($extra),
			'detail_id'         => $detail_id_prefix . '-' . (int) $detail_sequence,
			'tree_html'         => !empty($extra) ? $this->render_history_json_tree_html($extra) : '',
			'raw_json'          => $raw_json ? $raw_json : '',
		);
	}

	/**
     * Build filter counts for rendered display rows.
     *
     * @param array<int,array<string,mixed>> $display_logs Prepared display rows.
     * @return array<string,int>
     */
	public function build_history_filter_counts($display_logs) {
		$counts = array(
			'all'                 => count($display_logs),
			'ai_request_response' => 0,
		);

		foreach ($display_logs as $display_log) {
			if (empty($display_log['type_ids']) || !is_array($display_log['type_ids'])) {
				continue;
			}

			$row_has_ai_pair_type = false;

			foreach ($display_log['type_ids'] as $type_id) {
				$type_key = (string) $type_id;
				$counts[$type_key] = isset($counts[$type_key]) ? $counts[$type_key] + 1 : 1;

				if ($type_key === '5' || $type_key === '6') {
					$row_has_ai_pair_type = true;
				}
			}

			if ($row_has_ai_pair_type) {
				$counts['ai_request_response']++;
			}
		}

		return $counts;
	}

	/**
     * Return the CSS class for a history type badge.
     *
     * @param int $type_id History type ID.
     * @return string
     */
	public function get_history_type_class($type_id) {
		$map = array(
			1  => 'aips-badge-neutral',
			2  => 'aips-badge-error',
			3  => 'aips-badge-warning',
			4  => 'aips-badge-info',
			5  => 'aips-badge-ai',
			6  => 'aips-badge-ai',
			7  => 'aips-badge-neutral',
			8  => 'aips-badge-success',
			9  => 'aips-badge-neutral',
			10 => 'aips-badge-neutral',
		);

		return isset($map[$type_id]) ? $map[$type_id] : 'aips-badge-neutral';
	}

	/**
     * Return the CSS class for a history container status badge.
     *
     * @param string $status Container status.
     * @return string
     */
	public function get_history_status_class($status) {
		if ($status === 'completed' || $status === 'indexed') {
			return 'aips-badge-success';
		}

		if ($status === 'failed') {
			return 'aips-badge-error';
		}

		if ($status === 'processing') {
			return 'aips-badge-info';
		}

		if ($status === 'stalled' || $status === 'partial') {
			return 'aips-badge-warning';
		}

		return 'aips-badge-neutral';
	}

	/**
     * Format a duration label for display.
     *
     * @param int|null $duration_seconds Duration in seconds.
     * @return string
     */
	public function format_history_duration_label($duration_seconds) {
		if ($duration_seconds === null) {
			return '';
		}

		if ($duration_seconds < 60) {
			return sprintf(__('%d seconds', 'ai-post-scheduler'), $duration_seconds);
		}

		return sprintf(
			__('%d min %d sec', 'ai-post-scheduler'),
			intdiv((int) $duration_seconds, 60),
			((int) $duration_seconds) % 60
		);
	}

	/**
     * Determine whether a normalized log entry is an AI request.
     *
     * @param array<string,mixed> $log Normalized log entry.
     * @return bool
     */
	public function is_ai_request_history_log($log) {
		return (int) $log['history_type_id'] === AIPS_History_Type::AI_REQUEST;
	}

	/**
     * Determine whether a normalized log entry is an AI response.
     *
     * @param array<string,mixed> $log Normalized log entry.
     * @return bool
     */
	public function is_ai_response_history_log($log) {
		return (int) $log['history_type_id'] === AIPS_History_Type::AI_RESPONSE;
	}

	/**
     * Infer the AI phase key for a log entry.
     *
     * @param array<string,mixed> $log Normalized log entry.
     * @return string
     */
	public function derive_ai_phase_key($log) {
		$details = !empty($log['details']) && is_array($log['details']) ? $log['details'] : array();
		$context = isset($details['context']) && is_array($details['context']) ? $details['context'] : array();
		$input = isset($details['input']) && is_array($details['input']) ? $details['input'] : array();
		$input_context = isset($input['context']) && is_array($input['context']) ? $input['context'] : array();
		$output = isset($details['output']) && is_array($details['output']) ? $details['output'] : array();

		$candidates = array(
			isset($context['component']) ? $context['component'] : '',
			isset($input_context['component']) ? $input_context['component'] : '',
			isset($details['phase']) ? $details['phase'] : '',
			isset($details['component']) ? $details['component'] : '',
			isset($details['content_type']) ? $details['content_type'] : '',
			isset($details['request_type']) ? $details['request_type'] : '',
			isset($details['target']) ? $details['target'] : '',
			isset($details['section']) ? $details['section'] : '',
			isset($details['field']) ? $details['field'] : '',
			isset($details['item_type']) ? $details['item_type'] : '',
			isset($details['stage']) ? $details['stage'] : '',
			isset($output['component']) ? $output['component'] : '',
		);

		foreach ($candidates as $candidate) {
			$normalized = $this->normalize_ai_phase_key($candidate);
			if ($normalized !== '') {
				return $normalized;
			}
		}

		$message = isset($details['message']) ? strtolower((string) $details['message']) : '';
		if (preg_match('/for\s+(.+?)(?:[\.:]|$)/i', $message, $matches)) {
			$normalized = $this->normalize_ai_phase_key($matches[1]);
			if ($normalized !== '') {
				return $normalized;
			}
		}

		if (strpos($message, 'title') !== false) {
			return 'post_title';
		}
		if (strpos($message, 'excerpt') !== false) {
			return 'post_excerpt';
		}
		if (strpos($message, 'featured image') !== false || strpos($message, 'image') !== false) {
			return 'featured_image';
		}
		if (strpos($message, 'content') !== false || strpos($message, 'article') !== false) {
			return 'post_content';
		}

		return 'general';
	}

	/**
     * Normalize a freeform phase string into a stable key.
     *
     * @param mixed $value Raw phase value.
     * @return string
     */
	public function normalize_ai_phase_key($value) {
		return trim((string) preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $value))), '_');
	}

	/**
     * Convert a normalized AI phase key into a user-friendly label.
     *
     * @param string $phase_key Normalized phase key.
     * @return string
     */
	public function humanize_ai_phase_label($phase_key) {
		$normalized = $this->normalize_ai_phase_key($phase_key ?: 'general');
		$map = array(
			'post_title'     => __('Post Title', 'ai-post-scheduler'),
			'title'          => __('Post Title', 'ai-post-scheduler'),
			'post_content'   => __('Post Content', 'ai-post-scheduler'),
			'content'        => __('Post Content', 'ai-post-scheduler'),
			'article'        => __('Post Content', 'ai-post-scheduler'),
			'body'           => __('Post Content', 'ai-post-scheduler'),
			'post_excerpt'   => __('Post Excerpt', 'ai-post-scheduler'),
			'excerpt'        => __('Post Excerpt', 'ai-post-scheduler'),
			'featured_image' => __('Featured Image', 'ai-post-scheduler'),
			'image'          => __('Featured Image', 'ai-post-scheduler'),
			'topic'          => __('Topic', 'ai-post-scheduler'),
			'research'       => __('Research', 'ai-post-scheduler'),
			'general'        => __('General', 'ai-post-scheduler'),
		);

		if (isset($map[$normalized])) {
			return $map[$normalized];
		}

		return ucwords(str_replace('_', ' ', $normalized));
	}

	/**
     * Extract non-message details from a log entry.
     *
     * @param array<string,mixed> $log Normalized log entry.
     * @return array<string,mixed>
     */
	public function extract_history_log_extra_details($log) {
		$details = !empty($log['details']) && is_array($log['details']) ? $log['details'] : array();
		unset($details['message'], $details['timestamp']);
		return $details;
	}

	/**
     * Format a multiline text value for HTML display.
     *
     * @param mixed $value Raw text value.
     * @return string
     */
	public function format_history_multiline_text($value) {
		return nl2br(esc_html((string) $value));
	}

	/**
     * Render a JSON tree block for the structured details viewer.
     *
     * @param mixed       $value JSON-compatible value.
     * @param string|null $label Optional key label.
     * @param int         $depth Current nesting depth.
     * @return string
     */
	public function render_history_json_tree_html($value, $label = null, $depth = 0) {
		$label_html = $label !== null
			? '<span class="aips-json-key">' . esc_html((string) $label) . '</span>: '
			: '';

		if (!is_array($value)) {
			return '<div class="aips-json-leaf">' . $label_html . $this->render_history_json_scalar_html($value) . '</div>';
		}

		if (empty($value)) {
			$is_list = array_values($value) === $value;
			return '<div class="aips-json-leaf">' . $label_html . '<span class="aips-json-value aips-json-value-empty">' . ($is_list ? '[]' : '{}') . '</span></div>';
		}

		$is_list = array_values($value) === $value;
		$summary = '<span class="aips-json-summary-label">' . $label_html . '</span>'
			. '<span class="aips-json-meta">' . ($is_list ? 'Array[' . count($value) . ']' : 'Object{' . count($value) . '}') . '</span>';

		$html = '<details class="aips-json-node"' . ($depth <= 1 ? ' open' : '') . '>';
		$html .= '<summary class="aips-json-summary">' . $summary . '</summary>';
		$html .= '<div class="aips-json-children">';

		foreach ($value as $child_key => $child_value) {
			$html .= $this->render_history_json_tree_html($child_value, $child_key, $depth + 1);
		}

		$html .= '</div></details>';

		return $html;
	}

	/**
     * Render one scalar JSON value with semantic styling.
     *
     * @param mixed $value Scalar value.
     * @return string
     */
	public function render_history_json_scalar_html($value) {
		if ($value === null) {
			return '<span class="aips-json-value aips-json-value-null">null</span>';
		}

		if (is_string($value)) {
			return '<span class="aips-json-value aips-json-value-string">"' . $this->format_history_multiline_text($value) . '"</span>';
		}

		if (is_bool($value)) {
			return '<span class="aips-json-value aips-json-value-boolean">' . esc_html($value ? 'true' : 'false') . '</span>';
		}

		if (is_numeric($value)) {
			return '<span class="aips-json-value aips-json-value-number">' . esc_html((string) $value) . '</span>';
		}

		return '<span class="aips-json-value">' . esc_html((string) $value) . '</span>';
	}
}
