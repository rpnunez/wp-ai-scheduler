<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_History_Run_Presenter {
	public function present_container($container, $logs) {
		$task_type = !empty($container->creation_method) ? str_replace('_', ' ', (string) $container->creation_method) : __('Generation run', 'ai-post-scheduler');
		$task_type = ucwords($task_type);

		$timeline = array();
		$result = array(
			'template' => isset($container->template_name) ? $container->template_name : '',
			'author' => '',
			'topic' => '',
			'source' => '',
		);
		$failure_reason = isset($container->error_message) ? (string) $container->error_message : '';

		foreach ($logs as $log) {
			$details = array();
			if (!empty($log->details)) {
				$decoded = json_decode($log->details, true);
				if (is_array($decoded)) {
					$details = $decoded;
				}
			}

			if (empty($failure_reason) && !empty($details['error'])) {
				$failure_reason = (string) $details['error'];
			}

			foreach (array('author_name' => 'author', 'topic' => 'topic', 'source' => 'source', 'source_name' => 'source') as $key => $slot) {
				if (empty($result[$slot]) && !empty($details[$key])) {
					$result[$slot] = is_scalar($details[$key]) ? (string) $details[$key] : '';
				}
			}

			$severity = $this->map_severity((int) $log->history_type_id);
			$friendly = $this->friendly_event_label((string) $log->log_type, (int) $log->history_type_id);
			$timeline[] = array(
				'timestamp' => isset($log->timestamp) ? $log->timestamp : '',
				'label' => $friendly,
				'severity' => $severity,
			);
		}

		return array(
			'task_type' => $task_type,
			'timeline' => $timeline,
			'result' => $result,
			'failure_reason' => $failure_reason,
			'next_action' => $this->suggest_next_action($container, $failure_reason),
		);
	}

	public function present_log($log) {
		$history_type_id = (int) $log->history_type_id;
		return array(
			'id' => (int) $log->id,
			'log_type' => $log->log_type,
			'history_type_id' => $history_type_id,
			'type_label' => AIPS_History_Type::get_label($history_type_id),
			'friendly_label' => $this->friendly_event_label((string) $log->log_type, $history_type_id),
			'severity' => $this->map_severity($history_type_id),
			'timestamp' => $log->timestamp,
		);
	}

	private function map_severity($history_type_id) {
		if ($history_type_id === AIPS_History_Type::ERROR) {
			return 'error';
		}
		if ($history_type_id === AIPS_History_Type::WARNING) {
			return 'warning';
		}
		if ($history_type_id === AIPS_History_Type::ACTIVITY || $history_type_id === AIPS_History_Type::INFO) {
			return 'success';
		}
		return 'neutral';
	}

	private function friendly_event_label($log_type, $history_type_id) {
		$map = array(
			'generation_started' => __('Generation started', 'ai-post-scheduler'),
			'post_created' => __('Post created', 'ai-post-scheduler'),
			'post_published' => __('Post published', 'ai-post-scheduler'),
			'ai_request' => __('AI request sent', 'ai-post-scheduler'),
			'ai_response' => __('AI response received', 'ai-post-scheduler'),
			'generation_completed' => __('Generation completed', 'ai-post-scheduler'),
			'generation_failed' => __('Generation failed', 'ai-post-scheduler'),
		);
		if (isset($map[$log_type])) {
			return $map[$log_type];
		}
		if ($history_type_id === AIPS_History_Type::ERROR) {
			return __('Error event', 'ai-post-scheduler');
		}
		return ucwords(str_replace('_', ' ', $log_type));
	}

	private function suggest_next_action($container, $failure_reason) {
		if (!isset($container->status) || $container->status !== 'failed') {
			return '';
		}
		$reason = strtolower((string) $failure_reason);
		if (false !== strpos($reason, 'source')) {
			return __('Check source availability/settings, then retry.', 'ai-post-scheduler');
		}
		if (false !== strpos($reason, 'prompt') || false !== strpos($reason, 'token')) {
			return __('Review and adjust the prompt/template, then retry.', 'ai-post-scheduler');
		}
		return __('Retry the run. If it fails again, review template inputs and AI settings.', 'ai-post-scheduler');
	}
}
