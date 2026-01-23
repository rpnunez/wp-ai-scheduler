<?php
/**
 * Tests for Data Management functionality
 */
class Test_AIPS_Data_Management extends WP_UnitTestCase {

	private $data_management;
	private $export_mysql;
	private $import_mysql;
	private $export_json;
	private $import_json;

	public function setUp(): void {
		parent::setUp();
		
		// Create instances
		$this->export_mysql = new AIPS_Data_Management_Export_MySQL();
		$this->import_mysql = new AIPS_Data_Management_Import_MySQL();
		$this->export_json = new AIPS_Data_Management_Export_JSON();
		$this->import_json = new AIPS_Data_Management_Import_JSON();
		$this->data_management = new AIPS_Data_Management();
	}

	public function test_export_mysql_format_name() {
		$this->assertEquals('MySQL Dump', $this->export_mysql->get_format_name());
	}

	public function test_export_mysql_file_extension() {
		$this->assertEquals('sql', $this->export_mysql->get_file_extension());
	}

	public function test_export_mysql_mime_type() {
		$this->assertEquals('application/sql', $this->export_mysql->get_mime_type());
	}

	public function test_export_json_format_name() {
		$this->assertEquals('JSON', $this->export_json->get_format_name());
	}

	public function test_export_json_file_extension() {
		$this->assertEquals('json', $this->export_json->get_file_extension());
	}

	public function test_export_json_mime_type() {
		$this->assertEquals('application/json', $this->export_json->get_mime_type());
	}

	public function test_import_mysql_format_name() {
		$this->assertEquals('MySQL Dump', $this->import_mysql->get_format_name());
	}

	public function test_import_mysql_file_extension() {
		$this->assertEquals('sql', $this->import_mysql->get_file_extension());
	}

	public function test_import_json_format_name() {
		$this->assertEquals('JSON', $this->import_json->get_format_name());
	}

	public function test_import_json_file_extension() {
		$this->assertEquals('json', $this->import_json->get_file_extension());
	}

	public function test_data_management_get_export_formats() {
		$formats = $this->data_management->get_export_formats();
		
		$this->assertIsArray($formats);
		$this->assertArrayHasKey('mysql', $formats);
		$this->assertArrayHasKey('json', $formats);
		$this->assertEquals('MySQL Dump', $formats['mysql']);
		$this->assertEquals('JSON', $formats['json']);
	}

	public function test_data_management_get_import_formats() {
		$formats = $this->data_management->get_import_formats();
		
		$this->assertIsArray($formats);
		$this->assertArrayHasKey('mysql', $formats);
		$this->assertArrayHasKey('json', $formats);
		$this->assertEquals('MySQL Dump', $formats['mysql']);
		$this->assertEquals('JSON', $formats['json']);
	}

	public function test_export_mysql_generates_sql() {
		// Ensure tables exist
		AIPS_DB_Manager::install_tables();
		
		// Export the data
		$sql = $this->export_mysql->export();
		
		$this->assertIsString($sql);
		$this->assertStringContainsString('AI Post Scheduler Data Export', $sql);
		$this->assertStringContainsString('CREATE TABLE', $sql);
		$this->assertStringContainsString('SET SQL_MODE', $sql);
		
		// Check for plugin tables
		$tables = AIPS_DB_Manager::get_table_names();
		foreach ($tables as $table) {
			$this->assertStringContainsString($table, $sql);
		}
	}

	public function test_export_json_generates_json() {
		// Ensure tables exist
		AIPS_DB_Manager::install_tables();
		
		// Export the data
		$json = $this->export_json->export();
		
		$this->assertIsString($json);
		
		// Parse JSON to ensure it's valid
		$data = json_decode($json, true);
		$this->assertIsArray($data);
		$this->assertArrayHasKey('version', $data);
		$this->assertArrayHasKey('exported_at', $data);
		$this->assertArrayHasKey('tables', $data);
		$this->assertIsArray($data['tables']);
	}

	public function test_import_mysql_validates_file_extension() {
		$file = array(
			'error' => UPLOAD_ERR_OK,
			'name' => 'test.txt',
			'size' => 1024,
			'tmp_name' => '/tmp/test.txt',
		);
		
		$result = $this->import_mysql->validate_file($file);
		$this->assertWPError($result);
		$this->assertEquals('invalid_extension', $result->get_error_code());
	}

	public function test_import_json_validates_file_extension() {
		$file = array(
			'error' => UPLOAD_ERR_OK,
			'name' => 'test.txt',
			'size' => 1024,
			'tmp_name' => '/tmp/test.txt',
		);
		
		$result = $this->import_json->validate_file($file);
		$this->assertWPError($result);
		$this->assertEquals('invalid_extension', $result->get_error_code());
	}

	public function test_import_mysql_validates_file_size() {
		$file = array(
			'error' => UPLOAD_ERR_OK,
			'name' => 'test.sql',
			'size' => 100 * 1024 * 1024, // 100MB - too large
			'tmp_name' => '/tmp/test.sql',
		);
		
		$result = $this->import_mysql->validate_file($file);
		$this->assertWPError($result);
		$this->assertEquals('file_too_large', $result->get_error_code());
	}

	public function test_import_json_validates_file_size() {
		$file = array(
			'error' => UPLOAD_ERR_OK,
			'name' => 'test.json',
			'size' => 100 * 1024 * 1024, // 100MB - too large
			'tmp_name' => '/tmp/test.json',
		);
		
		$result = $this->import_json->validate_file($file);
		$this->assertWPError($result);
		$this->assertEquals('file_too_large', $result->get_error_code());
	}
}
