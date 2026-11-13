<?php
/**
 * Tests for scripts/export-aips-snapshot.php.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('AIPS_SNAPSHOT_EXPORT_NO_RUN')) {
	define('AIPS_SNAPSHOT_EXPORT_NO_RUN', true);
}

require_once dirname(__DIR__) . '/scripts/export-aips-snapshot.php';

class Test_AIPS_Snapshot_Exporter extends WP_UnitTestCase {

	public function test_redacts_url_userinfo_and_sensitive_query_parameters() {
		$exporter = new AIPS_Snapshot_Exporter(sys_get_temp_dir());
		$method = new ReflectionMethod($exporter, 'redact_row');
		$method->setAccessible(true);

		$row = $method->invoke($exporter, array(
			'url' => 'https://api-user:api-pass@example.com/feed?api_key=secret-value&category=php&access_token=token-value',
		));

		$this->assertSame(
			'https://example.com/feed?api_key=%5BREDACTED%5D&category=php&access_token=%5BREDACTED%5D',
			$row['url']
		);
	}
}
