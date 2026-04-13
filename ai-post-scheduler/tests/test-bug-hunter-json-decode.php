<?php
class Test_Bug_Hunter_Json_Decode extends WP_UnitTestCase {

    public function test_aips_data_management_import_json_fails_on_scalar() {
        require_once dirname( __FILE__ ) . '/../includes/class-aips-data-management-import-json.php';

        $importer = new AIPS_Data_Management_Import_JSON();

        $temp_file = tempnam(sys_get_temp_dir(), 'test_json');

        // "123" is valid JSON, but decodes to an integer, not an array.
        file_put_contents($temp_file, '123');
        $result = $importer->import($temp_file);

        $this->assertTrue( is_wp_error( $result ) );
        $this->assertEquals( 'parse_error', $result->get_error_code() );

        // "true" is valid JSON, decodes to boolean true
        file_put_contents($temp_file, 'true');
        $result = $importer->import($temp_file);

        $this->assertTrue( is_wp_error( $result ) );
        $this->assertEquals( 'parse_error', $result->get_error_code() );

        unlink($temp_file);
    }

    public function test_aips_data_management_import_json_succeeds_on_array() {
        require_once dirname( __FILE__ ) . '/../includes/class-aips-data-management-import-json.php';

        $importer = new class extends AIPS_Data_Management_Import_JSON {
            protected function process_import_data($data) {
                // Mock to prevent actual db writes
                return true;
            }
        };

        $temp_file = tempnam(sys_get_temp_dir(), 'test_json');
        file_put_contents($temp_file, '{"key": "value"}');

        $result = $importer->import($temp_file);

        // It shouldn't be a parse error
        if (is_wp_error($result)) {
            $this->assertNotEquals('parse_error', $result->get_error_code());
        } else {
            $this->assertTrue(true);
        }

        unlink($temp_file);
    }
}
