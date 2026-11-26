<?php

class Test_AIPS_Data_Management_JSON extends WP_UnitTestCase {

	public function test_json_export_reads_every_declared_table() {
		$repository = new Test_AIPS_Data_Management_JSON_Repository_Stub();
		$repository->rows_by_table = array(
			'wp_aips_alpha' => array(
				array(
					'id' => 1,
					'name' => 'Alpha',
				),
			),
		);

		$exporter = new class($repository) extends AIPS_Data_Management_Export_JSON {
			protected function get_tables() {
				return array(
					'alpha' => 'wp_aips_alpha',
					'beta' => 'wp_aips_beta',
				);
			}
		};

		$payload = json_decode($exporter->export(), true);

		$this->assertSame(array('wp_aips_alpha', 'wp_aips_beta'), $repository->read_tables);
		$this->assertArrayHasKey('alpha', $payload['tables']);
		$this->assertSame(array(), $payload['tables']['beta']);
		$this->assertSame('Alpha', $payload['tables']['alpha'][0]['name']);
	}

	public function test_json_import_uses_repository_for_bulk_write_flow() {
		$repository = new Test_AIPS_Data_Management_JSON_Repository_Stub();
		$importer = new class($repository) extends AIPS_Data_Management_Import_JSON {
			protected function get_tables() {
				return array(
					'alpha' => 'wp_aips_alpha',
				);
			}
		};

		$file_path = tempnam(sys_get_temp_dir(), 'aips-json-import-');
		file_put_contents(
			$file_path,
			wp_json_encode(
				array(
					'tables' => array(
						'alpha' => array(
							array('id' => 1, 'name' => 'First'),
							array('id' => 2, 'name' => 'Second'),
						),
						'unknown' => array(
							array('id' => 3, 'name' => 'Ignored'),
						),
					),
				)
			)
		);

		$result = $importer->import($file_path);

		@unlink($file_path);

		$this->assertTrue($result);
		$this->assertSame(1, $repository->disable_calls);
		$this->assertSame(1, $repository->enable_calls);
		$this->assertSame(array('wp_aips_alpha'), $repository->truncated_tables);
		$this->assertCount(2, $repository->insert_calls);
		$this->assertSame('wp_aips_alpha', $repository->insert_calls[0]['table']);
		$this->assertSame('Second', $repository->insert_calls[1]['row']['name']);
	}

	public function test_json_import_reenables_foreign_keys_when_insert_fails() {
		$repository = new Test_AIPS_Data_Management_JSON_Repository_Stub();
		$repository->insert_results = array(false);
		$importer = new class($repository) extends AIPS_Data_Management_Import_JSON {
			protected function get_tables() {
				return array(
					'alpha' => 'wp_aips_alpha',
				);
			}
		};

		$file_path = tempnam(sys_get_temp_dir(), 'aips-json-import-');
		file_put_contents(
			$file_path,
			wp_json_encode(
				array(
					'tables' => array(
						'alpha' => array(
							array('id' => 1, 'name' => 'Broken'),
						),
					),
				)
			)
		);

		$result = $importer->import($file_path);

		@unlink($file_path);

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('import_errors', $result->get_error_code());
		$this->assertSame(1, $repository->disable_calls);
		$this->assertSame(1, $repository->enable_calls);
	}

	public function test_mysql_export_reads_every_declared_table() {
		$repository = new Test_AIPS_Data_Management_JSON_Repository_Stub();
		$repository->rows_by_table = array(
			'wp_aips_alpha' => array(
				array(
					'id' => 7,
					'name' => 'Alpha',
				),
			),
		);
		$repository->create_statements = array(
			'wp_aips_alpha' => 'CREATE TABLE `wp_aips_alpha` (`id` bigint(20) NOT NULL)',
			'wp_aips_beta' => 'CREATE TABLE `wp_aips_beta` (`id` bigint(20) NOT NULL)',
		);

		$exporter = new class($repository) extends AIPS_Data_Management_Export_MySQL {
			protected function get_tables() {
				return array(
					'alpha' => 'wp_aips_alpha',
					'beta' => 'wp_aips_beta',
				);
			}
		};

		$dump = $exporter->export();

		$this->assertSame(array('wp_aips_alpha', 'wp_aips_beta'), $repository->create_statement_tables);
		$this->assertSame(array('wp_aips_alpha', 'wp_aips_beta'), $repository->read_tables);
		$this->assertStringContainsString('DROP TABLE IF EXISTS `wp_aips_alpha`;', $dump);
		$this->assertStringContainsString('INSERT INTO `wp_aips_alpha` (`id`, `name`) VALUES (\'7\', \'Alpha\');', $dump);
		$this->assertStringContainsString('-- Table structure for table `wp_aips_beta`', $dump);
	}

	public function test_mysql_export_skips_drop_when_create_statement_missing() {
		$repository = new Test_AIPS_Data_Management_JSON_Repository_Stub();
		$repository->rows_by_table = array(
			'wp_aips_alpha' => array(),
		);

		$exporter = new class($repository) extends AIPS_Data_Management_Export_MySQL {
			protected function get_tables() {
				return array(
					'alpha' => 'wp_aips_alpha',
				);
			}
		};

		$dump = $exporter->export();

		$this->assertStringNotContainsString('DROP TABLE IF EXISTS `wp_aips_alpha`;', $dump);
		$this->assertStringContainsString('-- Unable to export table wp_aips_alpha: schema read failed', $dump);
		$this->assertStringNotContainsString('-- No data in table', $dump);
	}
}

class Test_AIPS_Data_Management_JSON_Repository_Stub {

	public $rows_by_table = array();
	public $insert_results = array();
	public $read_tables = array();
	public $create_statements = array();
	public $create_statement_tables = array();
	public $truncated_tables = array();
	public $insert_calls = array();
	public $disable_calls = 0;
	public $enable_calls = 0;

	public function get_table_rows($full_table_name) {
		$this->read_tables[] = $full_table_name;
		return isset($this->rows_by_table[$full_table_name]) ? $this->rows_by_table[$full_table_name] : array();
	}

	public function get_create_table_statement($full_table_name) {
		$this->create_statement_tables[] = $full_table_name;

		if (isset($this->create_statements[$full_table_name])) {
			return $this->create_statements[$full_table_name];
		}

		return null;
	}

	public function disable_foreign_key_checks() {
		$this->disable_calls++;
	}

	public function enable_foreign_key_checks() {
		$this->enable_calls++;
	}

	public function truncate_table($full_table_name) {
		$this->truncated_tables[] = $full_table_name;
	}

	public function insert_row($full_table_name, $row) {
		$this->insert_calls[] = array(
			'table' => $full_table_name,
			'row' => $row,
		);

		if (!empty($this->insert_results)) {
			return array_shift($this->insert_results);
		}

		return true;
	}
}
