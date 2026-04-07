<?php
/**
 * Tests for AIPS_Notification_Senders
 *
 * Uses spy callables to capture dispatched notifications without requiring
 * a real AIPS_Notifications instance or database connection.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Notification_Senders extends WP_UnitTestCase {

	/** @var array Captured dispatch calls: each element is ['type'=>string,'options'=>array] */
	private $dispatched = array();

	/** @var array Captured vars-builder calls */
	private $vars_built = array();

	/** @var AIPS_Notification_Senders */
	private $senders;

	public function setUp(): void {
		parent::setUp();

		$this->dispatched = array();
		$this->vars_built = array();

		$self = $this;

		$dispatcher = function( $type, $options ) use ( $self ) {
			$self->dispatched[] = array(
				'type'    => $type,
				'options' => $options,
			);
			return true;
		};

		$vars_builder = function( $title, $message, $details, $url = '', $label = '' ) use ( $self ) {
			$self->vars_built[] = array(
				'title'   => $title,
				'message' => $message,
				'details' => $details,
				'url'     => $url,
				'label'   => $label,
			);
			return array( '{{title}}' => $title );
		};

		$this->senders = new AIPS_Notification_Senders( $dispatcher, $vars_builder );
	}

	// -----------------------------------------------------------------------
	// Helper
	// -----------------------------------------------------------------------

	private function last_dispatch() {
		return end($this->dispatched);
	}

	// -----------------------------------------------------------------------
	// author_topics_generated
	// -----------------------------------------------------------------------

	public function test_author_topics_generated_dispatches_correct_type() {
		$this->senders->author_topics_generated('Jane', 5, 42);
		$this->assertCount(1, $this->dispatched);
		$this->assertSame('author_topics_generated', $this->last_dispatch()['type']);
	}

	public function test_author_topics_generated_uses_db_channel() {
		$this->senders->author_topics_generated('Jane', 5, 42);
		$options = $this->last_dispatch()['options'];
		$this->assertContains(AIPS_Notifications::CHANNEL_DB, $options['channels']);
	}

	public function test_author_topics_generated_includes_message() {
		$this->senders->author_topics_generated('Jane', 3, 1);
		$options = $this->last_dispatch()['options'];
		$this->assertStringContainsString('Jane', $options['message']);
		$this->assertStringContainsString('3', $options['message']);
	}

	// -----------------------------------------------------------------------
	// generation_failed
	// -----------------------------------------------------------------------

	public function test_generation_failed_dispatches_correct_type() {
		$this->senders->generation_failed(array(
			'resource_label' => 'Test post',
			'error_message'  => 'Timeout',
		));
		$this->assertSame('generation_failed', $this->last_dispatch()['type']);
	}

	public function test_generation_failed_uses_vars_builder() {
		$this->senders->generation_failed(array(
			'resource_label' => 'Test post',
			'error_message'  => 'Timeout',
		));
		$this->assertCount(1, $this->vars_built);
		$this->assertStringContainsString('Test post', $this->vars_built[0]['title']);
	}

	public function test_generation_failed_sets_error_level() {
		$this->senders->generation_failed(array('error_message' => 'err'));
		$this->assertSame('error', $this->last_dispatch()['options']['level']);
	}

	public function test_generation_failed_uses_payload_dedupe_key() {
		$this->senders->generation_failed(array('dedupe_key' => 'my-key'));
		$this->assertSame('my-key', $this->last_dispatch()['options']['dedupe_key']);
	}

	// -----------------------------------------------------------------------
	// quota_alert
	// -----------------------------------------------------------------------

	public function test_quota_alert_dispatches_correct_type() {
		$this->senders->quota_alert(array('request_type' => 'completion'));
		$this->assertSame('quota_alert', $this->last_dispatch()['type']);
	}

	public function test_quota_alert_sets_error_level() {
		$this->senders->quota_alert(array());
		$this->assertSame('error', $this->last_dispatch()['options']['level']);
	}

	// -----------------------------------------------------------------------
	// integration_error
	// -----------------------------------------------------------------------

	public function test_integration_error_dispatches_correct_type() {
		$this->senders->integration_error(array('error_message' => 'Not configured'));
		$this->assertSame('integration_error', $this->last_dispatch()['type']);
	}

	// -----------------------------------------------------------------------
	// scheduler_error
	// -----------------------------------------------------------------------

	public function test_scheduler_error_dispatches_correct_type() {
		$this->senders->scheduler_error(array('schedule_name' => 'Daily posts'));
		$this->assertSame('scheduler_error', $this->last_dispatch()['type']);
	}

	public function test_scheduler_error_includes_schedule_name_in_title() {
		$this->senders->scheduler_error(array('schedule_name' => 'Weekly digest'));
		$options = $this->last_dispatch()['options'];
		$this->assertStringContainsString('Weekly digest', $options['title']);
	}

	// -----------------------------------------------------------------------
	// system_error
	// -----------------------------------------------------------------------

	public function test_system_error_dispatches_correct_type() {
		$this->senders->system_error(array('error_message' => 'DB failure'));
		$this->assertSame('system_error', $this->last_dispatch()['type']);
	}

	public function test_system_error_uses_payload_title() {
		$this->senders->system_error(array(
			'title'         => 'Custom title',
			'error_message' => 'err',
		));
		$this->assertSame('Custom title', $this->last_dispatch()['options']['title']);
	}

	// -----------------------------------------------------------------------
	// template_generated
	// -----------------------------------------------------------------------

	public function test_template_generated_dispatches_correct_type() {
		$this->senders->template_generated(array(
			'post_ids'      => array(1, 2),
			'template_name' => 'My Template',
		));
		$this->assertSame('template_generated', $this->last_dispatch()['type']);
	}

	public function test_template_generated_includes_post_count_in_title() {
		$this->senders->template_generated(array(
			'post_ids'      => array(10, 20, 30),
			'template_name' => 'T',
		));
		$title = $this->last_dispatch()['options']['title'];
		$this->assertStringContainsString('3', $title);
	}

	// -----------------------------------------------------------------------
	// manual_generation_completed
	// -----------------------------------------------------------------------

	public function test_manual_generation_completed_dispatches_correct_type() {
		$this->senders->manual_generation_completed(array('post_id' => 0));
		$this->assertSame('manual_generation_completed', $this->last_dispatch()['type']);
	}

	// -----------------------------------------------------------------------
	// post_ready_for_review
	// -----------------------------------------------------------------------

	public function test_post_ready_for_review_dispatches_correct_type() {
		$this->senders->post_ready_for_review(array('post_id' => 0));
		$this->assertSame('post_ready_for_review', $this->last_dispatch()['type']);
	}

	// -----------------------------------------------------------------------
	// post_rejected
	// -----------------------------------------------------------------------

	public function test_post_rejected_dispatches_correct_type() {
		$this->senders->post_rejected(array('post_title' => 'Draft post'));
		$this->assertSame('post_rejected', $this->last_dispatch()['type']);
	}

	public function test_post_rejected_sets_warning_level() {
		$this->senders->post_rejected(array('post_title' => 'Draft'));
		$this->assertSame('warning', $this->last_dispatch()['options']['level']);
	}

	// -----------------------------------------------------------------------
	// partial_generation_completed
	// -----------------------------------------------------------------------

	public function test_partial_generation_completed_dispatches_correct_type() {
		$this->senders->partial_generation_completed(array('post_id' => 0));
		$this->assertSame('partial_generation_completed', $this->last_dispatch()['type']);
	}

	// -----------------------------------------------------------------------
	// daily_digest
	// -----------------------------------------------------------------------

	public function test_daily_digest_dispatches_correct_type() {
		$this->senders->daily_digest(array('generated' => 5, 'review_ready' => 2, 'errors' => 0));
		$this->assertSame('daily_digest', $this->last_dispatch()['type']);
	}

	public function test_daily_digest_uses_email_channel() {
		$this->senders->daily_digest(array());
		$options = $this->last_dispatch()['options'];
		$this->assertContains(AIPS_Notifications::CHANNEL_EMAIL, $options['channels']);
	}

	public function test_daily_digest_message_includes_counts() {
		$this->senders->daily_digest(array('generated' => 4, 'review_ready' => 1, 'errors' => 2));
		$message = $this->last_dispatch()['options']['message'];
		$this->assertStringContainsString('4', $message);
		$this->assertStringContainsString('1', $message);
		$this->assertStringContainsString('2', $message);
	}

	// -----------------------------------------------------------------------
	// weekly_summary
	// -----------------------------------------------------------------------

	public function test_weekly_summary_dispatches_correct_type() {
		$this->senders->weekly_summary(array());
		$this->assertSame('weekly_summary', $this->last_dispatch()['type']);
	}

	public function test_weekly_summary_uses_email_channel() {
		$this->senders->weekly_summary(array());
		$options = $this->last_dispatch()['options'];
		$this->assertContains(AIPS_Notifications::CHANNEL_EMAIL, $options['channels']);
	}

	// -----------------------------------------------------------------------
	// monthly_report
	// -----------------------------------------------------------------------

	public function test_monthly_report_dispatches_correct_type() {
		$this->senders->monthly_report(array());
		$this->assertSame('monthly_report', $this->last_dispatch()['type']);
	}

	// -----------------------------------------------------------------------
	// history_cleanup
	// -----------------------------------------------------------------------

	public function test_history_cleanup_dispatches_correct_type() {
		$this->senders->history_cleanup(array('deleted' => 10, 'errors' => 0));
		$this->assertSame('history_cleanup', $this->last_dispatch()['type']);
	}

	public function test_history_cleanup_sets_warning_level_when_errors() {
		$this->senders->history_cleanup(array('deleted' => 5, 'errors' => 2));
		$this->assertSame('warning', $this->last_dispatch()['options']['level']);
	}

	public function test_history_cleanup_sets_info_level_when_no_errors() {
		$this->senders->history_cleanup(array('deleted' => 5, 'errors' => 0));
		$this->assertSame('info', $this->last_dispatch()['options']['level']);
	}

	// -----------------------------------------------------------------------
	// seeder_complete
	// -----------------------------------------------------------------------

	public function test_seeder_complete_dispatches_correct_type() {
		$this->senders->seeder_complete(array('type' => 'templates', 'message' => 'Done'));
		$this->assertSame('seeder_complete', $this->last_dispatch()['type']);
	}

	// -----------------------------------------------------------------------
	// template_change
	// -----------------------------------------------------------------------

	public function test_template_change_dispatches_correct_type() {
		$this->senders->template_change(array('action' => 'created', 'template_name' => 'My Template'));
		$this->assertSame('template_change', $this->last_dispatch()['type']);
	}

	public function test_template_change_includes_action_in_title() {
		$this->senders->template_change(array('action' => 'deleted', 'template_name' => 'T'));
		$title = $this->last_dispatch()['options']['title'];
		$this->assertStringContainsString('deleted', $title);
	}

	// -----------------------------------------------------------------------
	// author_suggestions
	// -----------------------------------------------------------------------

	public function test_author_suggestions_dispatches_correct_type() {
		$this->senders->author_suggestions(array('count' => 3, 'site_niche' => 'Tech'));
		$this->assertSame('author_suggestions', $this->last_dispatch()['type']);
	}

	// -----------------------------------------------------------------------
	// circuit_breaker_opened
	// -----------------------------------------------------------------------

	public function test_circuit_breaker_opened_dispatches_correct_type() {
		$this->senders->circuit_breaker_opened(array('failures' => 5, 'threshold' => 3));
		$this->assertSame('circuit_breaker_opened', $this->last_dispatch()['type']);
	}

	public function test_circuit_breaker_opened_sets_error_level() {
		$this->senders->circuit_breaker_opened(array());
		$this->assertSame('error', $this->last_dispatch()['options']['level']);
	}

	public function test_circuit_breaker_opened_default_dedupe_key() {
		$this->senders->circuit_breaker_opened(array());
		$this->assertSame('circuit_breaker_opened', $this->last_dispatch()['options']['dedupe_key']);
	}

	public function test_circuit_breaker_opened_uses_vars_builder() {
		$this->senders->circuit_breaker_opened(array('failures' => 2));
		$this->assertCount(1, $this->vars_built);
	}

	public function test_circuit_breaker_opened_immediate_open_reason() {
		$this->senders->circuit_breaker_opened(array(
			'reason_code' => 'immediate_open',
			'error_code'  => 'insufficient_quota',
		));
		$message = $this->last_dispatch()['options']['message'];
		$this->assertStringContainsString('insufficient_quota', $message);
	}

	public function test_circuit_breaker_opened_threshold_reason() {
		$this->senders->circuit_breaker_opened(array(
			'reason_code' => 'threshold_reached',
			'failures'    => 5,
			'threshold'   => 3,
		));
		$message = $this->last_dispatch()['options']['message'];
		$this->assertStringContainsString('5', $message);
	}

	// -----------------------------------------------------------------------
	// rate_limit_reached
	// -----------------------------------------------------------------------

	public function test_rate_limit_reached_dispatches_correct_type() {
		$this->senders->rate_limit_reached(array(
			'current_requests' => 10,
			'max_requests'     => 5,
			'period_seconds'   => 60,
		));
		$this->assertSame('rate_limit_reached', $this->last_dispatch()['type']);
	}

	public function test_rate_limit_reached_sets_warning_level() {
		$this->senders->rate_limit_reached(array());
		$this->assertSame('warning', $this->last_dispatch()['options']['level']);
	}

	public function test_rate_limit_reached_default_dedupe_key() {
		$this->senders->rate_limit_reached(array());
		$this->assertSame('rate_limit_reached', $this->last_dispatch()['options']['dedupe_key']);
	}

	// -----------------------------------------------------------------------
	// research_topics_ready
	// -----------------------------------------------------------------------

	public function test_research_topics_ready_dispatches_correct_type() {
		$this->senders->research_topics_ready(array('count' => 8, 'niche' => 'Finance'));
		$this->assertSame('research_topics_ready', $this->last_dispatch()['type']);
	}

	public function test_research_topics_ready_includes_count_in_title() {
		$this->senders->research_topics_ready(array('count' => 7, 'niche' => 'Tech'));
		$title = $this->last_dispatch()['options']['title'];
		$this->assertStringContainsString('7', $title);
	}

	public function test_research_topics_ready_generates_dedupe_key() {
		$this->senders->research_topics_ready(array('count' => 1, 'niche' => 'Health'));
		$options = $this->last_dispatch()['options'];
		$this->assertNotEmpty($options['dedupe_key']);
		$this->assertStringContainsString('research_topics_ready_', $options['dedupe_key']);
	}

	// -----------------------------------------------------------------------
	// Callable injection sanity
	// -----------------------------------------------------------------------

	public function test_each_sender_calls_dispatcher_exactly_once() {
		$methods_with_payload = array(
			'generation_failed', 'quota_alert', 'integration_error',
			'scheduler_error', 'system_error', 'template_generated',
			'manual_generation_completed', 'post_ready_for_review',
			'post_rejected', 'partial_generation_completed',
			'daily_digest', 'weekly_summary', 'monthly_report',
			'history_cleanup', 'seeder_complete', 'template_change',
			'author_suggestions', 'circuit_breaker_opened',
			'rate_limit_reached', 'research_topics_ready',
		);

		foreach ($methods_with_payload as $method) {
			$before = count($this->dispatched);
			$this->senders->$method(array());
			$after = count($this->dispatched);
			$this->assertSame(1, $after - $before, "Method {$method} should dispatch exactly once");
		}

		$before = count($this->dispatched);
		$this->senders->author_topics_generated('Jane', 3, 1);
		$after = count($this->dispatched);
		$this->assertSame(1, $after - $before, "author_topics_generated should dispatch exactly once");
	}
}
