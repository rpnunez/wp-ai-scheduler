<?php

class Test_AIPS_Post_Feedback_Lifecycle extends WP_UnitTestCase {

	public function test_feedback_table_is_in_central_lifecycle_catalog() {
		$tables = AIPS_DB_Manager::get_full_table_names();

		$this->assertArrayHasKey('aips_post_feedback', $tables);
		$this->assertSame($GLOBALS['wpdb']->prefix . 'aips_post_feedback', $tables['aips_post_feedback']);
	}

	public function test_json_export_includes_feedback_audit_events() {
		$repository = new Test_AIPS_Post_Feedback_Lifecycle_Repository_Stub();
		$repository->existing_tables = array('aips_post_feedback' => 'wp_aips_post_feedback');
		$repository->rows_by_table['wp_aips_post_feedback'] = array(
			array('id' => 17, 'post_id' => 42, 'reaction' => 'liked'),
		);
		$exporter = new class($repository) extends AIPS_Data_Management_Export_JSON {
			protected function get_tables() {
				return array('aips_post_feedback' => 'wp_aips_post_feedback');
			}
		};

		$payload = json_decode($exporter->export(), true);

		$this->assertContains('aips_post_feedback', $payload['audit_tables']);
		$this->assertSame('liked', $payload['tables']['aips_post_feedback'][0]['reaction']);
	}

	public function test_json_import_restores_feedback_audit_events() {
		$repository = new Test_AIPS_Post_Feedback_Lifecycle_Repository_Stub();
		$importer = new class($repository) extends AIPS_Data_Management_Import_JSON {
			protected function get_tables() {
				return array('aips_post_feedback' => 'wp_aips_post_feedback');
			}
		};
		$file = tempnam(sys_get_temp_dir(), 'aips-feedback-');
		file_put_contents($file, wp_json_encode(array('tables' => array(
			'aips_post_feedback' => array(array('id' => 17, 'post_id' => 42, 'reaction' => 'liked')),
		))));

		$result = $importer->import($file);
		@unlink($file);

		$this->assertTrue($result);
		$this->assertSame(array('wp_aips_post_feedback'), $repository->truncated_tables);
		$this->assertSame('liked', $repository->insert_calls[0]['row']['reaction']);
	}

	public function test_mysql_export_includes_feedback_table_and_rows() {
		$repository = new Test_AIPS_Post_Feedback_Lifecycle_Repository_Stub();
		$repository->existing_tables = array('aips_post_feedback' => 'wp_aips_post_feedback');
		$repository->create_statements['wp_aips_post_feedback'] = 'CREATE TABLE `wp_aips_post_feedback` (`id` bigint(20) NOT NULL)';
		$repository->rows_by_table['wp_aips_post_feedback'] = array(array('id' => 17, 'reaction' => 'disliked'));
		$exporter = new class($repository) extends AIPS_Data_Management_Export_MySQL {
			protected function get_tables() {
				return array('aips_post_feedback' => 'wp_aips_post_feedback');
			}
		};

		$dump = $exporter->export();

		$this->assertStringContainsString('CREATE TABLE `wp_aips_post_feedback`', $dump);
		$this->assertStringContainsString('Append-only audit tables', $dump);
		$this->assertStringContainsString("'disliked'", $dump);
	}

	public function test_deleting_a_post_does_not_delete_its_feedback_audit_event() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_post_feedback';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
			$this->markTestSkipped('Post feedback table is not installed.');
		}
		$post_id = self::factory()->post->create();
		$wpdb->insert($table, array(
			'post_id' => $post_id,
			'user_id' => 1,
			'reaction' => 'liked',
			'created_at' => time(),
		));
		$event_id = (int) $wpdb->insert_id;

		wp_delete_post($post_id, true);

		$this->assertSame('liked', $wpdb->get_var($wpdb->prepare("SELECT reaction FROM {$table} WHERE id = %d", $event_id)));
	}
}

class Test_AIPS_Post_Feedback_Lifecycle_Repository_Stub {
	public $existing_tables = array();
	public $rows_by_table = array();
	public $create_statements = array();
	public $truncated_tables = array();
	public $insert_calls = array();

	public function get_existing_tables($tables) { return $this->existing_tables; }
	public function get_table_rows($table) { return $this->rows_by_table[$table] ?? array(); }
	public function get_create_table_statement($table) { return $this->create_statements[$table] ?? null; }
	public function disable_foreign_key_checks() {}
	public function enable_foreign_key_checks() {}
	public function truncate_table($table) { $this->truncated_tables[] = $table; }
	public function insert_row($table, $row) {
		$this->insert_calls[] = array('table' => $table, 'row' => $row);
		return true;
	}
}
