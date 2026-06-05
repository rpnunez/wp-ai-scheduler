<?php
/**
 * Tests for AIPS_Repository_Cache_Observer.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Test_Repository_Cache_Observer_Logger implements AIPS_Logger_Interface {
	public $entries = array();

	public function log($message, $level = 'info', $context = array()) {
		$this->entries[] = array(
			'message' => $message,
			'level'   => $level,
			'context' => $context,
		);
	}

	public function addSeparator($text) {}
}

class AIPS_Test_Repository_Cache_Observer_Failing_Logger implements AIPS_Logger_Interface {
	public function log($message, $level = 'info', $context = array()) {
		throw new RuntimeException('logger failed');
	}

	public function addSeparator($text) {}
}

class Test_AIPS_Repository_Cache_Observer extends WP_UnitTestCase {

	public function tearDown(): void {
		if (class_exists('AIPS_Correlation_ID')) {
			AIPS_Correlation_ID::reset();
		}

		parent::tearDown();
	}

	public function test_record_read_logs_normalized_context_without_raw_key() {
		$logger   = new AIPS_Test_Repository_Cache_Observer_Logger();
		$observer = new AIPS_Repository_Cache_Observer($logger);

		AIPS_Correlation_ID::set('repository-cache-test');

		$observer->record_read(array(
			'repository'   => 'AIPS_Authors_Repository',
			'operation_id' => 'authors.get_by_id',
			'cache_group'  => 'aips_authors',
			'key'          => 'author:42:private-key-material',
			'tags'         => array('authors', 'author_42', 'authors'),
			'tier'         => 'memory',
			'hit'          => true,
			'elapsed_ms'   => 1.23456,
		));

		$this->assertCount(1, $logger->entries);
		$entry   = $logger->entries[0];
		$context = $entry['context'];

		$this->assertSame('Repository cache read', $entry['message']);
		$this->assertSame('debug', $entry['level']);
		$this->assertSame('repository_cache_read', $context['type']);
		$this->assertSame('read', $context['event_type']);
		$this->assertSame('AIPS_Authors_Repository', $context['repository']);
		$this->assertSame('authors.get_by_id', $context['cache_operation_id']);
		$this->assertSame('aips_authors', $context['cache_group']);
		$this->assertSame(hash('sha256', 'author:42:private-key-material'), $context['key_hash']);
		$this->assertArrayNotHasKey('key', $context);
		$this->assertSame(array('authors', 'author_42'), $context['tags']);
		$this->assertSame('memory', $context['tier']);
		$this->assertTrue($context['hit']);
		$this->assertSame(1.235, $context['elapsed_ms']);
		$this->assertSame('repository-cache-test', $context['correlation_id']);
	}

	public function test_record_invalidation_preserves_reason_and_key_hash() {
		$logger   = new AIPS_Test_Repository_Cache_Observer_Logger();
		$observer = new AIPS_Repository_Cache_Observer($logger);

		$observer->record_invalidation(array(
			'repository_class'      => 'AIPS_Template_Repository',
			'cache_operation_id'    => 'templates.update',
			'group'                 => 'aips_templates',
			'key_hash'              => 'abc123',
			'invalidation_reason'   => 'template_saved',
		));

		$context = $logger->entries[0]['context'];

		$this->assertSame('repository_cache_invalidation', $context['type']);
		$this->assertSame('invalidation', $context['event_type']);
		$this->assertSame('AIPS_Template_Repository', $context['repository']);
		$this->assertSame('templates.update', $context['cache_operation_id']);
		$this->assertSame('aips_templates', $context['cache_group']);
		$this->assertSame('abc123', $context['key_hash']);
		$this->assertSame('template_saved', $context['invalidation_reason']);
	}

	public function test_record_bypass_marks_bypass() {
		$logger   = new AIPS_Test_Repository_Cache_Observer_Logger();
		$observer = new AIPS_Repository_Cache_Observer($logger);

		$observer->record_bypass(array(
			'repository'   => 'AIPS_Schedule_Repository',
			'operation_id' => 'schedule.due',
			'cache_group'  => 'aips_schedule',
			'reason'       => 'cache_disabled',
		));

		$context = $logger->entries[0]['context'];

		$this->assertSame('repository_cache_bypass', $context['type']);
		$this->assertTrue($context['bypass']);
		$this->assertSame('cache_disabled', $context['invalidation_reason']);
	}

	public function test_observer_does_not_throw_when_logger_fails() {
		$observer = new AIPS_Repository_Cache_Observer(new AIPS_Test_Repository_Cache_Observer_Failing_Logger());

		$observer->record_write(array(
			'repository'   => 'AIPS_Authors_Repository',
			'operation_id' => 'authors.save',
			'cache_group'  => 'aips_authors',
			'key_hash'     => 'abc123',
		));

		$this->assertTrue(true);
	}
}
