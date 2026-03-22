<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Planner {

	public function __construct() {
		add_action('wp_ajax_aips_generate_topics', array($this, 'ajax_generate_topics'));
		add_action('wp_ajax_aips_bulk_schedule', array($this, 'ajax_bulk_schedule'));
		add_action('wp_ajax_aips_score_planner_mix', array($this, 'ajax_score_planner_mix'));
	}

	public function ajax_generate_topics() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : '';
		$count = isset($_POST['count']) ? absint($_POST['count']) : 10;

		if (empty($niche)) {
			wp_send_json_error(array('message' => __('Please provide a niche or topic.', 'ai-post-scheduler')));
		}

		if ($count < 1 || $count > 50) {
			$count = 10;
		}

		$generator = new AIPS_Generator();
		if (!$generator->is_available()) {
			wp_send_json_error(array('message' => __('AI Engine is not available.', 'ai-post-scheduler')));
		}

		$prompt  = "Generate a list of {$count} unique, engaging blog post titles/topics about '{$niche}'. \n";
		$prompt .= "Return ONLY a valid JSON array of strings. Do not include any other text, markdown formatting, or numbering. \n";
		$prompt .= "Example: [\"Topic 1\", \"Topic 2\", \"Topic 3\"]";

		$result = $generator->generate_content($prompt, array('temperature' => 0.7, 'max_tokens' => 1000), 'planner_topics');

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		$json_str = trim($result);
		$json_str = preg_replace('/^```json/', '', $json_str);
		$json_str = preg_replace('/^```/', '', $json_str);
		$json_str = preg_replace('/```$/', '', $json_str);
		$json_str = trim($json_str);

		$topics = json_decode($json_str);

		if (JSON_ERROR_NONE !== json_last_error() || !is_array($topics)) {
			$topics = array_filter(array_map('trim', explode("\n", $json_str)));
			if (empty($topics)) {
				wp_send_json_error(
					array(
						'message' => __('Failed to parse AI response. Raw response: ', 'ai-post-scheduler') . substr($json_str, 0, 100) . '...',
					)
				);
			}
		}

		do_action('aips_planner_topics_generated', $topics, $niche);

		wp_send_json_success(array('topics' => $topics));
	}

	public function ajax_score_planner_mix() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$topics      = $this->get_posted_topics();
		$template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
		$start_date  = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
		$frequency   = isset($_POST['frequency']) ? sanitize_key(wp_unslash($_POST['frequency'])) : 'daily';

		$service         = new AIPS_Unified_Schedule_Service();
		$template_label  = $this->get_template_label($template_id);
		$candidate_items = $service->build_candidate_items_from_topics(
			$topics,
			array(
				'template_id'    => $template_id,
				'template_label' => $template_label,
				'start_date'     => $start_date,
				'frequency'      => $frequency,
			)
		);
		$insights = $service->get_planner_mix_insights($candidate_items, array('window_days' => 7));

		wp_send_json_success($insights);
	}

	public function ajax_bulk_schedule() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$topics      = $this->get_posted_topics();
		$template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
		$start_date  = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
		$frequency   = isset($_POST['frequency']) ? sanitize_key(wp_unslash($_POST['frequency'])) : 'daily';

		if (empty($topics) || empty($template_id) || empty($start_date)) {
			wp_send_json_error(array('message' => __('Missing required fields.', 'ai-post-scheduler')));
		}

		$scheduler = new AIPS_Scheduler();
		$base_time = strtotime($start_date);
		if (!$base_time) {
			wp_send_json_error(array('message' => __('Please provide a valid start date.', 'ai-post-scheduler')));
		}

		$intervals = $scheduler->get_intervals();
		$interval  = 86400;
		if (isset($intervals[ $frequency ]['interval'])) {
			$interval = absint($intervals[ $frequency ]['interval']);
		}

		$schedules = array();
		foreach ($topics as $index => $topic) {
			$next_run_timestamp = $base_time + ($index * $interval);
			$schedules[] = array(
				'template_id' => $template_id,
				'frequency'   => 'once',
				'next_run'    => gmdate('Y-m-d H:i:s', $next_run_timestamp),
				'is_active'   => 1,
				'topic'       => $topic,
				'title'       => $topic,
			);
		}

		$service      = new AIPS_Unified_Schedule_Service();
		$template_lbl = $this->get_template_label($template_id);
		$candidates   = $service->build_candidate_items_from_topics(
			$topics,
			array(
				'template_id'    => $template_id,
				'template_label' => $template_lbl,
				'start_date'     => $start_date,
				'frequency'      => $frequency,
			)
		);
		$mix_insights = $service->get_planner_mix_insights($candidates, array('window_days' => 7));

		$count = $scheduler->save_schedule_bulk($schedules);
		if (false === $count || 0 === $count) {
			wp_send_json_error(array('message' => __('Failed to schedule topics.', 'ai-post-scheduler')));
		}

		$this->record_mix_analytics($template_id, $topics, $mix_insights);

		do_action('aips_planner_bulk_scheduled', $count, $template_id, $mix_insights);

		$summary = isset($mix_insights['candidate_scores']['summary']) ? $mix_insights['candidate_scores']['summary'] : array();
		$message = sprintf(__('%d topics scheduled successfully.', 'ai-post-scheduler'), $count);
		if (!empty($summary)) {
			$message .= ' ' . sprintf(
				__('Mix impact: %1$d improved, %2$d worsened, %3$d neutral.', 'ai-post-scheduler'),
				isset($summary['improved']) ? absint($summary['improved']) : 0,
				isset($summary['worsened']) ? absint($summary['worsened']) : 0,
				isset($summary['neutral']) ? absint($summary['neutral']) : 0
			);
		}

		wp_send_json_success(
			array(
				'message'      => $message,
				'count'        => $count,
				'mix_insights' => $mix_insights,
			)
		);
	}

	public function render_page() {
		$templates_obj    = new AIPS_Templates();
		$templates        = $templates_obj->get_all(true);
		$planner_service  = new AIPS_Unified_Schedule_Service();
		$planner_insights = $planner_service->get_planner_mix_insights();

		include AIPS_PLUGIN_DIR . 'templates/admin/planner.php';
	}

	/**
	 * Sanitize the planner topics payload.
	 *
	 * @return array
	 */
	private function get_posted_topics() {
		$topics = isset($_POST['topics']) ? (array) wp_unslash($_POST['topics']) : array();
		$topics = array_filter(array_map('sanitize_text_field', $topics));
		return array_values($topics);
	}

	/**
	 * Resolve a template label for planner analytics.
	 *
	 * @param int $template_id Template ID.
	 * @return string
	 */
	private function get_template_label($template_id) {
		if (empty($template_id)) {
			return __('Planner Candidate', 'ai-post-scheduler');
		}
		$templates_obj = new AIPS_Templates();
		$template      = $templates_obj->get($template_id);
		if ($template && !empty($template->name)) {
			return $template->name;
		}
		return __('Planner Candidate', 'ai-post-scheduler');
	}

	/**
	 * Record structured planner mix analytics for later reporting.
	 *
	 * @param int   $template_id  Template ID.
	 * @param array $topics       Scheduled topics.
	 * @param array $mix_insights Planner scoring payload.
	 * @return void
	 */
	private function record_mix_analytics($template_id, $topics, $mix_insights) {
		$history_service = new AIPS_History_Service();
		$history         = $history_service->create(
			'planner_mix_analysis',
			array(
				'template_id'      => absint($template_id),
				'creation_method'  => 'manual_ui',
			)
		);

		$summary = isset($mix_insights['candidate_scores']['summary']) ? $mix_insights['candidate_scores']['summary'] : array();
		$history->record(
			'activity',
			__('Planner bulk scheduling mix analysis recorded.', 'ai-post-scheduler'),
			array(
				'event_type'   => 'planner_mix_analysis',
				'event_status' => 'completed',
			),
			array(
				'topic_count' => count($topics),
				'summary'     => $summary,
			),
			array(
				'candidate_scores' => isset($mix_insights['candidate_scores']['items']) ? $mix_insights['candidate_scores']['items'] : array(),
				'projected_report' => isset($mix_insights['projected_report']) ? $mix_insights['projected_report'] : array(),
			)
		);
		$history->complete_success(array());
	}
}
