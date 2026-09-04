<?php
/**
 * Tests for AIPS_Integration_ACF
 *
 * ACF is not installed in the test environment, so acf_get_field_groups(),
 * acf_get_fields(), and update_field() are stubbed here to exercise the
 * adapter's own logic (location-rule matching, field-type mapping,
 * write-back error handling) independently of the real plugin.
 *
 * @package AI_Post_Scheduler
 */

if (!function_exists('acf_get_field_groups')) {
	function acf_get_field_groups() {
		return $GLOBALS['aips_test_acf_field_groups'];
	}
}

if (!function_exists('acf_get_fields')) {
	function acf_get_fields($group_id) {
		return isset($GLOBALS['aips_test_acf_fields'][$group_id]) ? $GLOBALS['aips_test_acf_fields'][$group_id] : array();
	}
}

if (!function_exists('update_field')) {
	function update_field($field_key, $value, $post_id) {
		if ($GLOBALS['aips_test_acf_write_should_fail']) {
			return false;
		}
		$GLOBALS['aips_test_acf_written'][$post_id][$field_key] = $value;
		return true;
	}
}

if (!function_exists('acf_get_field')) {
	function acf_get_field($field_key) {
		return in_array($field_key, $GLOBALS['aips_test_acf_known_field_keys'], true)
			? array('key' => $field_key)
			: false;
	}
}

class Test_AIPS_Integration_ACF extends WP_UnitTestCase {

	/** @var AIPS_Integration_ACF */
	private $adapter;

	public function setUp(): void {
		parent::setUp();
		$this->adapter = new AIPS_Integration_ACF();

		$GLOBALS['aips_test_acf_field_groups'] = array(
			array(
				'key'      => 'group_posts_only',
				'title'    => 'Post Fields',
				'location' => array(
					array(
						array('param' => 'post_type', 'operator' => '==', 'value' => 'post'),
					),
				),
			),
			array(
				'key'      => 'group_no_location',
				'title'    => 'Unrestricted Fields',
				'location' => array(),
			),
		);

		$GLOBALS['aips_test_acf_fields'] = array(
			'group_posts_only' => array(
				array('key' => 'field_headline', 'name' => 'headline', 'label' => 'Headline', 'type' => 'text', 'instructions' => 'Keep it short.'),
				array('key' => 'field_body', 'name' => 'body', 'label' => 'Body', 'type' => 'wysiwyg', 'instructions' => ''),
				array('key' => 'field_team', 'name' => 'team', 'label' => 'Team', 'type' => 'repeater', 'instructions' => ''),
				array('key' => 'field_color', 'name' => 'color', 'label' => 'Color', 'type' => 'color_picker', 'instructions' => ''),
			),
		);

		$GLOBALS['aips_test_acf_write_should_fail'] = false;
		$GLOBALS['aips_test_acf_written'] = array();
		$GLOBALS['aips_test_acf_known_field_keys'] = array('field_headline', 'field_body', 'field_team', 'field_color');
	}

	public function test_is_available_true_when_acf_functions_exist() {
		$this->assertTrue($this->adapter->is_available());
	}

	public function test_get_field_groups_returns_all_when_no_post_type_filter() {
		$groups = $this->adapter->get_field_groups();
		$this->assertCount(2, $groups);
	}

	public function test_get_field_groups_filters_by_post_type() {
		$groups = $this->adapter->get_field_groups('post');
		$this->assertCount(2, $groups); // matching group + unrestricted group
		$ids = wp_list_pluck($groups, 'id');
		$this->assertContains('group_posts_only', $ids);
		$this->assertContains('group_no_location', $ids);
	}

	public function test_get_field_groups_excludes_non_matching_post_type() {
		$groups = $this->adapter->get_field_groups('page');
		$ids = wp_list_pluck($groups, 'id');
		$this->assertNotContains('group_posts_only', $ids);
		$this->assertContains('group_no_location', $ids); // unrestricted groups always match
	}

	public function test_get_fields_maps_known_types_to_shapes() {
		$fields = $this->adapter->get_fields('group_posts_only');
		$by_key = array();
		foreach ($fields as $field) {
			$by_key[$field['key']] = $field;
		}

		$this->assertSame(AIPS_Integration_Interface::SHAPE_SHORT_TEXT, $by_key['field_headline']['shape']);
		$this->assertSame(AIPS_Integration_Interface::SHAPE_HTML, $by_key['field_body']['shape']);
		$this->assertSame(AIPS_Integration_Interface::SHAPE_STRUCTURED_LIST, $by_key['field_team']['shape']);
	}

	public function test_get_fields_marks_unknown_type_as_unsupported() {
		$fields = $this->adapter->get_fields('group_posts_only');
		$by_key = array();
		foreach ($fields as $field) {
			$by_key[$field['key']] = $field;
		}

		$this->assertSame('', $by_key['field_color']['shape']);
	}

	public function test_write_field_value_success() {
		$result = $this->adapter->write_field_value(123, 'field_headline', 'Generated headline');
		$this->assertTrue($result);
		$this->assertSame('Generated headline', $GLOBALS['aips_test_acf_written'][123]['field_headline']);
	}

	public function test_write_field_value_returns_wp_error_on_failure() {
		$GLOBALS['aips_test_acf_write_should_fail'] = true;
		$result = $this->adapter->write_field_value(123, 'field_headline', 'Generated headline');
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('acf_write_failed', $result->get_error_code());
	}

	public function test_write_field_value_rejects_invalid_post_id() {
		$result = $this->adapter->write_field_value(0, 'field_headline', 'value');
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('invalid_post', $result->get_error_code());
	}

	public function test_write_field_value_returns_wp_error_for_deleted_or_renamed_field() {
		$result = $this->adapter->write_field_value(123, 'field_no_longer_exists', 'value');
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('acf_field_not_found', $result->get_error_code());
		$this->assertArrayNotHasKey('field_no_longer_exists', isset($GLOBALS['aips_test_acf_written'][123]) ? $GLOBALS['aips_test_acf_written'][123] : array());
	}
}
