<?php
/**
 * Explainability Builder
 *
 * Builds a structured, versioned explainability payload from history/session data.
 * Used both for on-demand reconstruction (View Session modal) and for storing
 * immutable snapshots at generation time.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.1
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Explainability_Builder {
	/**
	 * Schema version for the explainability payload.
	 */
	public const SCHEMA_VERSION = '1.1.0';

	/**
	 * Maximum prompt preview length.
	 */
	private const PROMPT_PREVIEW_MAX_LENGTH = 800;

	/**
	 * Build explainability payload.
	 *
	 * @param object $history_item History item (includes ->log entries when loaded via repository).
	 * @param array  $entries Parsed log entries (see AIPS_Generated_Posts_Controller::ajax_get_post_session()).
	 * @param array  $ai_calls AI request/response calls grouped by component (indexed array).
	 * @param array  $component_revisions Component revisions grouped by component.
	 * @return array
	 */
	public function build($history_item, $entries, $ai_calls, $component_revisions) {
		$stored_snapshot = $this->extract_stored_snapshot($entries);
		if (!empty($stored_snapshot)) {
			$stored_snapshot['snapshot'] = array(
				'stored' => true,
				'source' => 'history_log',
			);
			return $stored_snapshot;
		}

		$redaction_count = 0;
		$redacted_ai_calls = $this->redact_sensitive_data($ai_calls, $redaction_count);
		$redacted_revisions = $this->redact_sensitive_data($component_revisions, $redaction_count);
		$redacted_entries = $this->redact_sensitive_data($entries, $redaction_count);

		$timeline = array();
		$sources_considered = array();
		$sources_used = array();
		$validation_checks = array();
		$transformations = array();

		foreach ($redacted_entries as $entry) {
			$timeline[] = array(
				'stage' => $this->map_log_to_timeline_stage($entry),
				'timestamp' => isset($entry['timestamp']) ? $entry['timestamp'] : '',
				'log_type' => isset($entry['log_type']) ? $entry['log_type'] : '',
				'summary' => $this->build_entry_summary($entry),
			);

			$entry_sources = $this->extract_sources_from_entry($entry);
			if (!empty($entry_sources)) {
				$sources_considered = array_merge($sources_considered, $entry_sources);
				if ($this->entry_indicates_source_usage($entry)) {
					$sources_used = array_merge($sources_used, $entry_sources);
				}
			}

			$check = $this->extract_validation_check($entry);
			if (!empty($check)) {
				$validation_checks[] = $check;
			}

			$transform = $this->extract_transformation($entry);
			if (!empty($transform)) {
				$transformations[] = $transform;
			}
		}

		$sources_considered = $this->unique_multidimensional($sources_considered);
		$sources_used = $this->unique_multidimensional($sources_used);

		$attempt_count = 1;
		$retry_count = 0;
		foreach ($redacted_revisions as $component_revisions_list) {
			if (is_array($component_revisions_list)) {
				$retry_count += count($component_revisions_list);
			}
		}
		if ($retry_count > 0) {
			$attempt_count += $retry_count;
		}

		$model_runs = array();
		foreach ($redacted_ai_calls as $call) {
			$request = isset($call['request']) && is_array($call['request']) ? $call['request'] : array();
			$response = isset($call['response']) && is_array($call['response']) ? $call['response'] : array();
			$input = isset($request['input']) && is_array($request['input']) ? $request['input'] : array();
			$options = isset($input['options']) && is_array($input['options']) ? $input['options'] : array();

			$model_runs[] = array(
				'step' => isset($call['type']) ? $call['type'] : 'unknown',
				'provider' => isset($options['provider']) ? $options['provider'] : (isset($request['provider']) ? $request['provider'] : (isset($request['context']['provider']) ? $request['context']['provider'] : '')),
				'model' => isset($options['model']) ? $options['model'] : (isset($request['model']) ? $request['model'] : (isset($request['context']['model']) ? $request['context']['model'] : '')),
				'status' => !empty($response) ? 'completed' : 'requested',
				'has_request' => !empty($request),
				'has_response' => !empty($response),
				'source_ref' => 'ai_calls',
			);
		}

		$component_revision_counts = array();
		foreach ($redacted_revisions as $component_key => $component_revisions_list) {
			$component_revision_counts[$component_key] = is_array($component_revisions_list) ? count($component_revisions_list) : 0;
		}

		$used_urls = array();
		foreach ($sources_used as $used_source) {
			if (!empty($used_source['url'])) {
				$used_urls[(string) $used_source['url']] = true;
			}
		}
		$sources_excluded = array();
		foreach ($sources_considered as $source_row) {
			$url = isset($source_row['url']) ? (string) $source_row['url'] : '';
			if ('' === $url || !isset($used_urls[$url])) {
				$sources_excluded[] = $source_row;
			}
		}

		$payload = array(
			'schema_version' => self::SCHEMA_VERSION,
			'generation' => array(
				'history_id' => isset($history_item->id) ? (int) $history_item->id : 0,
				'status' => isset($history_item->status) ? (string) $history_item->status : '',
				'post_id' => isset($history_item->post_id) ? (int) $history_item->post_id : 0,
				'created_at' => isset($history_item->created_at) ? $history_item->created_at : '',
				'completed_at' => isset($history_item->completed_at) ? $history_item->completed_at : '',
				'correlation_id' => isset($history_item->uuid) ? (string) $history_item->uuid : '',
			),
			'trigger' => array(
				'origin' => $this->derive_trigger_origin($history_item),
				'creation_method' => isset($history_item->creation_method) ? (string) $history_item->creation_method : '',
				'template_id' => isset($history_item->template_id) ? (int) $history_item->template_id : 0,
				'author_id' => isset($history_item->author_id) ? (int) $history_item->author_id : 0,
				'topic_id' => isset($history_item->topic_id) ? (int) $history_item->topic_id : 0,
			),
			'context_snapshot' => array(
				'generated_title' => isset($history_item->generated_title) ? (string) $history_item->generated_title : '',
				'error_message' => isset($history_item->error_message) ? (string) $history_item->error_message : '',
				'template_id' => isset($history_item->template_id) ? (int) $history_item->template_id : 0,
				'author_id' => isset($history_item->author_id) ? (int) $history_item->author_id : 0,
				'topic_id' => isset($history_item->topic_id) ? (int) $history_item->topic_id : 0,
			),
			'prompt_components' => $this->build_prompt_components($redacted_ai_calls),
			'sources' => array(
				'considered' => $sources_considered,
				'used' => $sources_used,
				'excluded' => $sources_excluded,
			),
			'model_runs' => $model_runs,
			'validation_checks' => $validation_checks,
			'transformations' => $transformations,
			'attempts' => array(
				'total_attempts' => $attempt_count,
				'retries_or_regenerations' => $retry_count,
				'component_revision_counts' => $component_revision_counts,
				'component_revisions_ref' => 'component_revisions',
			),
			'timeline' => $timeline,
			'final_outcome' => array(
				'status' => isset($history_item->status) ? (string) $history_item->status : '',
				'post_id' => isset($history_item->post_id) ? (int) $history_item->post_id : 0,
				'post_status' => (!empty($history_item->post_id) && function_exists('get_post_status')) ? (string) get_post_status((int) $history_item->post_id) : '',
				'post_edit_link' => !empty($history_item->post_id) ? esc_url_raw(get_edit_post_link((int) $history_item->post_id, 'raw')) : '',
			),
			'redactions' => array(
				'count' => $redaction_count,
				'note' => $redaction_count > 0 ? __('Some sensitive fields were redacted for safety.', 'ai-post-scheduler') : '',
			),
			'warnings' => $this->build_explainability_warnings($redacted_entries, $sources_used, $validation_checks, $retry_count),
		);

		$payload['why'] = $this->build_plain_english_why($payload);

		return $payload;
	}

	/**
	 * Convenience: build explainability payload from a fully loaded history item.
	 *
	 * @param object $history_item History item with ->log entries.
	 * @param array  $component_revisions Optional component revisions.
	 * @return array
	 */
	public function build_from_history_item($history_item, $component_revisions = array()) {
		$entries = array();
		$ai_calls = array();

		if (isset($history_item->log) && is_array($history_item->log)) {
			foreach ($history_item->log as $log_entry) {
				$details = isset($log_entry->details) ? json_decode($log_entry->details, true) : array();
				if (!is_array($details)) {
					$details = array();
				}

				$type_id = isset($log_entry->history_type_id) ? (int) $log_entry->history_type_id : AIPS_History_Type::LOG;
				$entries[] = array(
					'type_id' => $type_id,
					'log_type' => (string) $log_entry->log_type,
					'timestamp' => (string) $log_entry->timestamp,
					'details' => $details,
				);

				if (AIPS_History_Type::AI_REQUEST === $type_id || AIPS_History_Type::AI_RESPONSE === $type_id) {
					$component_type = isset($details['context']['component']) ? $details['context']['component'] : 'unknown';

					if (!isset($ai_calls[$component_type])) {
						$ai_calls[$component_type] = array(
							'type' => $component_type,
							'label' => ucfirst(str_replace('_', ' ', $component_type)),
							'request' => null,
							'response' => null,
						);
					}

					if (AIPS_History_Type::AI_REQUEST === $type_id) {
						$ai_calls[$component_type]['request'] = $details;
					} else {
						if (isset($details['output']) && !empty($details['output_encoded'])) {
							$details['output'] = base64_decode($details['output']);
						}
						$ai_calls[$component_type]['response'] = $details;
					}
				}
			}
		}

		return $this->build($history_item, $entries, array_values($ai_calls), $component_revisions);
	}

	/**
	 * Compact an explainability payload for immutable snapshot storage.
	 *
	 * @param array $payload Full explainability payload.
	 * @return array
	 */
	public function compact_snapshot($payload) {
		if (empty($payload) || !is_array($payload)) {
			return array();
		}

		$compact = array(
			'schema_version' => isset($payload['schema_version']) ? $payload['schema_version'] : self::SCHEMA_VERSION,
			'generation' => isset($payload['generation']) ? $payload['generation'] : array(),
			'trigger' => isset($payload['trigger']) ? $payload['trigger'] : array(),
			'why' => isset($payload['why']) ? $payload['why'] : array(),
			'prompt_components' => isset($payload['prompt_components']) ? $payload['prompt_components'] : array(),
			'sources' => isset($payload['sources']) ? $payload['sources'] : array(),
			'model_runs' => isset($payload['model_runs']) ? $payload['model_runs'] : array(),
			'validation_checks' => isset($payload['validation_checks']) ? $payload['validation_checks'] : array(),
			'attempts' => isset($payload['attempts']) ? $payload['attempts'] : array(),
			'timeline' => isset($payload['timeline']) ? array_slice((array) $payload['timeline'], 0, 50) : array(),
			'final_outcome' => isset($payload['final_outcome']) ? $payload['final_outcome'] : array(),
			'redactions' => isset($payload['redactions']) ? $payload['redactions'] : array(),
			'warnings' => isset($payload['warnings']) ? $payload['warnings'] : array(),
		);

		return $compact;
	}

	/**
	 * Extract an immutable stored snapshot from entries, when present.
	 *
	 * @param array $entries Parsed entries.
	 * @return array
	 */
	private function extract_stored_snapshot($entries) {
		if (empty($entries) || !is_array($entries)) {
			return array();
		}

		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			if (!isset($entry['log_type']) || 'explainability_snapshot' !== (string) $entry['log_type']) {
				continue;
			}
			$details = isset($entry['details']) && is_array($entry['details']) ? $entry['details'] : array();
			if (isset($details['snapshot']) && is_array($details['snapshot']) && !empty($details['snapshot']['generation'])) {
				$snapshot = $details['snapshot'];
				$snapshot['snapshot'] = array(
					'stored_at' => isset($entry['timestamp']) ? (string) $entry['timestamp'] : '',
					'history_log_type' => 'explainability_snapshot',
				);
				return $snapshot;
			}
		}

		return array();
	}

	/**
	 * Build plain-English "why this happened" narrative plus structured facts.
	 *
	 * @param array $payload Explainability payload.
	 * @return array
	 */
	private function build_plain_english_why($payload) {
		$trigger = isset($payload['trigger']) && is_array($payload['trigger']) ? $payload['trigger'] : array();
		$sources = isset($payload['sources']) && is_array($payload['sources']) ? $payload['sources'] : array();
		$attempts = isset($payload['attempts']) && is_array($payload['attempts']) ? $payload['attempts'] : array();
		$final = isset($payload['final_outcome']) && is_array($payload['final_outcome']) ? $payload['final_outcome'] : array();

		$origin = isset($trigger['origin']) ? (string) $trigger['origin'] : 'unknown';
		$creation_method = isset($trigger['creation_method']) ? (string) $trigger['creation_method'] : '';
		$template_id = isset($trigger['template_id']) ? (int) $trigger['template_id'] : 0;
		$author_id = isset($trigger['author_id']) ? (int) $trigger['author_id'] : 0;
		$topic_id = isset($trigger['topic_id']) ? (int) $trigger['topic_id'] : 0;

		$sources_used_count = isset($sources['used']) && is_array($sources['used']) ? count($sources['used']) : 0;
		$retries = isset($attempts['retries_or_regenerations']) ? (int) $attempts['retries_or_regenerations'] : 0;

		$validation_counts = array(
			'passed' => 0,
			'warning' => 0,
			'failed' => 0,
			'skipped' => 0,
			'info' => 0,
		);
		if (isset($payload['validation_checks']) && is_array($payload['validation_checks'])) {
			foreach ($payload['validation_checks'] as $check) {
				if (!is_array($check)) {
					continue;
				}
				$status = isset($check['status']) ? (string) $check['status'] : 'info';
				if (!isset($validation_counts[$status])) {
					$status = 'info';
				}
				$validation_counts[$status]++;
			}
		}

		$trigger_phrase = __('an unknown trigger', 'ai-post-scheduler');
		if ('author_topic' === $origin && $author_id && $topic_id) {
			$trigger_phrase = sprintf(
				/* translators: 1: author id, 2: topic id */
				__('author #%1$d topic #%2$d', 'ai-post-scheduler'),
				$author_id,
				$topic_id
			);
		} elseif (in_array($origin, array('scheduled_post', 'manual_generation'), true) && $template_id) {
			$trigger_phrase = sprintf(
				/* translators: %d: template id */
				__('template #%d', 'ai-post-scheduler'),
				$template_id
			);
			if ('scheduled_post' === $origin || 'scheduled' === $creation_method) {
				$trigger_phrase = sprintf(
					/* translators: %s: template phrase */
					__('scheduled %s', 'ai-post-scheduler'),
					$trigger_phrase
				);
			} elseif ('manual_generation' === $origin || 'manual' === $creation_method) {
				$trigger_phrase = sprintf(
					/* translators: %s: template phrase */
					__('manual %s', 'ai-post-scheduler'),
					$trigger_phrase
				);
			}
		}

		$post_id = isset($final['post_id']) ? (int) $final['post_id'] : 0;
		$post_status = isset($final['post_status']) ? (string) $final['post_status'] : '';

		$sentences = array();
		$sentences[] = sprintf(
			/* translators: %s: trigger phrase */
			__('This post was generated by %s.', 'ai-post-scheduler'),
			$trigger_phrase
		);
		$sentences[] = sprintf(
			/* translators: %d: used sources count */
			__('Used %d source(s).', 'ai-post-scheduler'),
			$sources_used_count
		);

		$total_checks = array_sum($validation_counts);
		if ($total_checks > 0) {
			$sentences[] = sprintf(
				/* translators: 1: total checks, 2: passed, 3: warnings, 4: failed, 5: skipped */
				__('Validation: %1$d check(s) — %2$d passed, %3$d warning(s), %4$d failed, %5$d skipped.', 'ai-post-scheduler'),
				$total_checks,
				$validation_counts['passed'],
				$validation_counts['warning'],
				$validation_counts['failed'],
				$validation_counts['skipped']
			);
		}

		if ($retries > 0) {
			$sentences[] = sprintf(
				/* translators: %d: retry/regeneration count */
				__('Retried/regenerated %d time(s).', 'ai-post-scheduler'),
				$retries
			);
		}

		if ($post_id > 0) {
			if ($post_status) {
				$sentences[] = sprintf(
					/* translators: 1: post id, 2: post status */
					__('Created post #%1$d (%2$s).', 'ai-post-scheduler'),
					$post_id,
					$post_status
				);
			} else {
				$sentences[] = sprintf(
					/* translators: %d: post id */
					__('Created post #%d.', 'ai-post-scheduler'),
					$post_id
				);
			}
		}

		return array(
			'summary' => trim(implode(' ', $sentences)),
			'facts' => array(
				'origin' => $origin,
				'creation_method' => $creation_method,
				'template_id' => $template_id,
				'author_id' => $author_id,
				'topic_id' => $topic_id,
				'sources_used' => $sources_used_count,
				'validation_counts' => $validation_counts,
				'retries_or_regenerations' => $retries,
				'post_id' => $post_id,
				'post_status' => $post_status,
			),
		);
	}

	/**
	 * Build prompt component records from AI call data.
	 *
	 * @param array $ai_calls Redacted AI calls.
	 * @return array
	 */
	private function build_prompt_components($ai_calls) {
		$components = array();

		foreach ($ai_calls as $call) {
			$request = isset($call['request']) && is_array($call['request']) ? $call['request'] : array();
			$input = isset($request['input']) && is_array($request['input']) ? $request['input'] : array();
			$options = isset($input['options']) && is_array($input['options']) ? $input['options'] : array();

			$prompt_text = '';
			if (isset($input['prompt']) && is_string($input['prompt'])) {
				$prompt_text = $input['prompt'];
			} elseif (isset($request['prompt']) && is_string($request['prompt'])) {
				$prompt_text = $request['prompt'];
			} elseif (isset($options['messages']) && is_array($options['messages'])) {
				$prompt_text = wp_json_encode($options['messages']);
			} elseif (isset($request['messages']) && is_array($request['messages'])) {
				$prompt_text = wp_json_encode($request['messages']);
			}

			$prompt_text = trim((string) $prompt_text);
			$prompt_length = function_exists('mb_strlen')
				? mb_strlen($prompt_text)
				: strlen($prompt_text);

			$preview = $prompt_text;
			$preview_truncated = false;
			if ($prompt_length > self::PROMPT_PREVIEW_MAX_LENGTH) {
				$preview_truncated = true;
				$preview = function_exists('mb_substr')
					? mb_substr($preview, 0, self::PROMPT_PREVIEW_MAX_LENGTH)
					: substr($preview, 0, self::PROMPT_PREVIEW_MAX_LENGTH);
				$preview .= '...';
			}

			$context = isset($request['context']) && is_array($request['context']) ? $request['context'] : array();
			$token_budget = isset($context['token_budget']) && is_array($context['token_budget']) ? $context['token_budget'] : array();
			$ai_variables = isset($context['ai_variables']) && is_array($context['ai_variables']) ? $context['ai_variables'] : array();

			$site_context = array();
			if (isset($options['context']) && is_string($options['context']) && '' !== trim($options['context'])) {
				$ctx = trim($options['context']);
				$site_context = array(
					'included' => true,
					'length' => function_exists('mb_strlen') ? mb_strlen($ctx) : strlen($ctx),
					'preview' => (function_exists('mb_substr') ? mb_substr($ctx, 0, 240) : substr($ctx, 0, 240)) . ((function_exists('mb_strlen') ? mb_strlen($ctx) : strlen($ctx)) > 240 ? '...' : ''),
				);
			} else {
				$site_context = array(
					'included' => false,
					'length' => 0,
					'preview' => '',
				);
			}

			$components[] = array(
				'name' => isset($call['type']) ? (string) $call['type'] : 'unknown',
				'label' => isset($call['label']) ? (string) $call['label'] : ucfirst(str_replace('_', ' ', isset($call['type']) ? (string) $call['type'] : 'unknown')),
				'included' => !empty($request),
				'prompt_preview' => $preview,
				'prompt_length' => $prompt_length,
				'prompt_preview_length' => function_exists('mb_strlen') ? mb_strlen($preview) : strlen($preview),
				'prompt_preview_truncated' => $preview_truncated,
				'source' => isset($context['source']) ? (string) $context['source'] : 'ai_request',
				'context' => array(
					'component' => isset($context['component']) ? (string) $context['component'] : '',
					'context_type' => isset($context['context_type']) ? (string) $context['context_type'] : '',
					'context_id' => isset($context['context_id']) ? (int) $context['context_id'] : 0,
					'template_id' => isset($context['template_id']) ? (int) $context['template_id'] : 0,
					'author_id' => isset($context['author_id']) ? (int) $context['author_id'] : 0,
					'topic_id' => isset($context['topic_id']) ? (int) $context['topic_id'] : 0,
					'voice_id' => isset($context['voice_id']) ? (int) $context['voice_id'] : 0,
					'article_structure_id' => isset($context['article_structure_id']) ? (int) $context['article_structure_id'] : 0,
					'creation_method' => isset($context['creation_method']) ? (string) $context['creation_method'] : '',
					'topic' => isset($context['topic']) ? (string) $context['topic'] : '',
				),
				'options' => array(
					'request_type' => isset($options['request_type']) ? (string) $options['request_type'] : '',
					'model' => isset($options['model']) ? (string) $options['model'] : '',
					'env_id' => isset($options['env_id']) ? (string) $options['env_id'] : (isset($options['envId']) ? (string) $options['envId'] : ''),
					'temperature' => isset($options['temperature']) ? $options['temperature'] : null,
				),
				'token_budget' => $token_budget,
				'ai_variables' => $ai_variables,
				'site_context' => $site_context,
			);
		}

		return $components;
	}

	private function redact_sensitive_data($value, &$redaction_count, $parent_key = '') {
		if (is_array($value)) {
			$clean = array();
			foreach ($value as $key => $item) {
				$key_name = is_string($key) ? $key : $parent_key;
				if ($this->is_sensitive_key($key_name)) {
					$redaction_count++;
					$clean[$key] = '[REDACTED]';
					continue;
				}
				$clean[$key] = $this->redact_sensitive_data($item, $redaction_count, (string) $key_name);
			}
			return $clean;
		}

		if (is_string($value)) {
			if ($this->is_sensitive_key($parent_key) || $this->contains_sensitive_token($value)) {
				$redaction_count++;
				return '[REDACTED]';
			}
		}

		return $value;
	}

	private function is_sensitive_key($key) {
		$key = strtolower((string) $key);
		return preg_match('/(password|token|secret|api[_-]?key|authorization|cookie|nonce|bearer|private[_-]?key|client[_-]?secret|access[_-]?token|refresh[_-]?token)/', $key) === 1;
	}

	private function contains_sensitive_token($value) {
		$value = (string) $value;
		return preg_match('/(sk-[a-z0-9]{16,}|akia[a-z0-9]{16}|aiza[a-z0-9\-_]{20,}|gh[pousr]_[a-z0-9]{20,}|bearer\s+[a-z0-9\-_\.]{16,}|-----begin[^-]*private key-----)/i', $value) === 1;
	}

	private function derive_trigger_origin($history_item) {
		if (!empty($history_item->author_id) && !empty($history_item->topic_id)) {
			return 'author_topic';
		}
		if (!empty($history_item->template_id) && isset($history_item->creation_method) && 'scheduled' === $history_item->creation_method) {
			return 'scheduled_post';
		}
		if (!empty($history_item->template_id) && isset($history_item->creation_method) && 'manual' === $history_item->creation_method) {
			return 'manual_generation';
		}
		return 'unknown';
	}

	private function map_log_to_timeline_stage($entry) {
		$log_type = strtolower(isset($entry['log_type']) ? (string) $entry['log_type'] : '');
		$type_id = isset($entry['type_id']) ? (int) $entry['type_id'] : 0;

		if (AIPS_History_Type::AI_REQUEST === $type_id) {
			return 'model_called';
		}
		if (AIPS_History_Type::AI_RESPONSE === $type_id) {
			return 'model_responded';
		}
		if (strpos($log_type, 'source') !== false || strpos($log_type, 'fetch') !== false) {
			return 'sources_fetched';
		}
		if (strpos($log_type, 'prompt') !== false) {
			return 'prompt_built';
		}
		if (strpos($log_type, 'validat') !== false || strpos($log_type, 'check') !== false) {
			return 'validation';
		}
		if (strpos($log_type, 'retry') !== false || strpos($log_type, 'regener') !== false) {
			return 'retry_or_regeneration';
		}
		if (strpos($log_type, 'save') !== false || strpos($log_type, 'post') !== false) {
			return 'final_save';
		}
		return 'activity';
	}

	private function build_entry_summary($entry) {
		$details = isset($entry['details']) && is_array($entry['details']) ? $entry['details'] : array();
		if (isset($details['message']) && is_string($details['message']) && '' !== trim($details['message'])) {
			return trim($details['message']);
		}
		if (isset($details['error']) && is_string($details['error']) && '' !== trim($details['error'])) {
			return trim($details['error']);
		}
		return isset($entry['log_type']) ? (string) $entry['log_type'] : '';
	}

	private function extract_sources_from_entry($entry) {
		$details = isset($entry['details']) && is_array($entry['details']) ? $entry['details'] : array();
		$found = array();
		$this->collect_sources_recursive($details, $found);
		return $found;
	}

	private function collect_sources_recursive($node, &$found) {
		if (is_array($node)) {
			if (isset($node['url']) && is_string($node['url'])) {
				$url = esc_url_raw($node['url']);
				if ('' !== $url) {
					$domain = parse_url($url, PHP_URL_HOST);
					if ($domain) {
						$domain = preg_replace('/^www\./', '', strtolower((string) $domain));
					}

					$row = array(
						'url' => $url,
						'domain' => $domain ? $domain : '',
						'title' => isset($node['title']) ? (string) $node['title'] : '',
						'type' => isset($node['type']) ? (string) $node['type'] : '',
					);
					$key = wp_json_encode($row);
					$found[$key] = $row;
				}
			}

			foreach ($node as $child) {
				if (is_array($child)) {
					$this->collect_sources_recursive($child, $found);
				}
			}
		}
	}

	private function entry_indicates_source_usage($entry) {
		$log_type = strtolower(isset($entry['log_type']) ? (string) $entry['log_type'] : '');
		$details = isset($entry['details']) && is_array($entry['details']) ? $entry['details'] : array();

		if (strpos($log_type, 'used') !== false || strpos($log_type, 'selected') !== false) {
			return true;
		}

		if (isset($details['used']) && true === $details['used']) {
			return true;
		}

		return false;
	}

	private function extract_validation_check($entry) {
		$log_type = strtolower(isset($entry['log_type']) ? (string) $entry['log_type'] : '');
		if (strpos($log_type, 'validat') === false && strpos($log_type, 'check') === false && strpos($log_type, 'policy') === false && strpos($log_type, 'guard') === false) {
			return array();
		}

		$details = isset($entry['details']) && is_array($entry['details']) ? $entry['details'] : array();
		$status = isset($details['status']) ? strtolower((string) $details['status']) : 'info';
		if (!in_array($status, array('passed', 'failed', 'warning', 'skipped', 'info'), true)) {
			$status = 'info';
		}

		$name = isset($details['check_name']) ? (string) $details['check_name'] : (isset($entry['log_type']) ? (string) $entry['log_type'] : 'validation_check');
		$stage = $this->map_log_to_timeline_stage($entry);
		$remediation = $this->get_validation_remediation($name, $status);

		return array(
			'name' => $name,
			'status' => $status,
			'severity' => $this->map_validation_severity($status),
			'stage' => $stage,
			'reason' => $this->build_entry_summary($entry),
			'suggested_fix' => $remediation['suggested_fix'],
			'settings' => $remediation['settings'],
			'timestamp' => isset($entry['timestamp']) ? (string) $entry['timestamp'] : '',
		);
	}

	private function map_validation_severity($status) {
		switch ((string) $status) {
			case 'passed':
				return 'success';
			case 'warning':
				return 'warning';
			case 'failed':
				return 'error';
			case 'skipped':
				return 'neutral';
			default:
				return 'neutral';
		}
	}

	private function get_validation_remediation($name, $status) {
		$name_lc = strtolower((string) $name);
		$suggested_fix = '';
		$settings = array();

		if (false !== strpos($name_lc, 'length') || false !== strpos($name_lc, 'word') || false !== strpos($name_lc, 'count')) {
			$suggested_fix = __('Length check: consider increasing template detail or minimum section count.', 'ai-post-scheduler');
			$settings = array(
				'label' => __('Content strategy settings', 'ai-post-scheduler'),
				'url' => function_exists('admin_url') ? esc_url_raw(admin_url('admin.php?page=aips-settings')) : '',
			);
		} elseif (false !== strpos($name_lc, 'policy') || false !== strpos($name_lc, 'guard')) {
			$suggested_fix = __('Policy check: review automation/policy settings and ensure the content meets the configured guardrails.', 'ai-post-scheduler');
			$settings = array(
				'label' => __('Policy settings', 'ai-post-scheduler'),
				'url' => function_exists('admin_url') ? esc_url_raw(admin_url('admin.php?page=aips-settings')) : '',
			);
		} elseif ('failed' === (string) $status || 'warning' === (string) $status) {
			$suggested_fix = __('Review the check details and adjust the template/inputs to address the issue.', 'ai-post-scheduler');
			$settings = array(
				'label' => __('Settings', 'ai-post-scheduler'),
				'url' => function_exists('admin_url') ? esc_url_raw(admin_url('admin.php?page=aips-settings')) : '',
			);
		}

		return array(
			'suggested_fix' => $suggested_fix,
			'settings' => $settings,
		);
	}

	private function extract_transformation($entry) {
		$log_type = strtolower(isset($entry['log_type']) ? (string) $entry['log_type'] : '');
		if (strpos($log_type, 'transform') === false && strpos($log_type, 'normalize') === false && strpos($log_type, 'sanitize') === false && strpos($log_type, 'internal_link') === false && strpos($log_type, 'citation') === false) {
			return array();
		}

		return array(
			'stage' => isset($entry['log_type']) ? (string) $entry['log_type'] : 'transformation',
			'summary' => $this->build_entry_summary($entry),
			'timestamp' => isset($entry['timestamp']) ? (string) $entry['timestamp'] : '',
		);
	}

	private function build_explainability_warnings($entries, $sources_used, $validation_checks, $retry_count) {
		$warnings = array();

		if (empty($entries)) {
			$warnings[] = __('Limited lineage data is available for this generation.', 'ai-post-scheduler');
		}

		if (empty($sources_used)) {
			$warnings[] = __('No explicitly used sources were detected in available logs.', 'ai-post-scheduler');
		}

		if ($retry_count > 0) {
			$warnings[] = sprintf(
				/* translators: %d: retry/regeneration count */
				__('This generation included %d retry/regeneration attempts.', 'ai-post-scheduler'),
				$retry_count
			);
		}

		foreach ($validation_checks as $check) {
			if (isset($check['status']) && 'failed' === $check['status']) {
				$warnings[] = __('At least one validation check failed during generation.', 'ai-post-scheduler');
				break;
			}
		}

		return array_values(array_unique($warnings));
	}

	private function unique_multidimensional($rows) {
		$unique = array();
		$seen = array();

		foreach ($rows as $row) {
			$key = wp_json_encode($row);
			if (!isset($seen[$key])) {
				$seen[$key] = true;
				$unique[] = $row;
			}
		}

		return $unique;
	}
}
