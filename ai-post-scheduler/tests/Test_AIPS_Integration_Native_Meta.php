<?php
/**
 * Tests for AIPS_Integration_Native_Meta
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Integration_Native_Meta extends WP_UnitTestCase {

	/** @var AIPS_Integration_Native_Meta */
	private $adapter;

	/** @var array<int, string> Meta keys registered during a test, unregistered in tearDown. */
	private $registered_keys = array();

	public function setUp(): void {
		parent::setUp();
		$this->adapter = new AIPS_Integration_Native_Meta();
		$this->registered_keys = array();
	}

	public function tearDown(): void {
		foreach ($this->registered_keys as $key) {
			unregister_meta_key('post', $key);
		}
		parent::tearDown();
	}

	private function register_test_meta($key, $args) {
		register_post_meta('post', $key, $args);
		$this->registered_keys[] = $key;
	}

	private function fields_by_key($fields) {
		$by_key = array();
		foreach ($fields as $field) {
			$by_key[$field['key']] = $field;
		}
		return $by_key;
	}

	public function test_is_available_always_true() {
		$this->assertTrue($this->adapter->is_available());
	}

	public function test_supports_custom_field_keys_is_true() {
		$this->assertTrue($this->adapter->supports_custom_field_keys());
	}

	public function test_get_field_groups_returns_one_synthetic_group_scoped_to_post_type() {
		$groups = $this->adapter->get_field_groups('product_review');
		$this->assertCount(1, $groups);
		$this->assertSame('product_review', $groups[0]['id']);
	}

	public function test_get_fields_surfaces_registered_meta_key() {
		$this->register_test_meta('contact_phone', array(
			'type'         => 'string',
			'description'  => 'Contact Phone',
			'single'       => true,
			'show_in_rest' => true,
		));

		$fields = $this->fields_by_key($this->adapter->get_fields('post'));

		$this->assertArrayHasKey('contact_phone', $fields);
		$this->assertSame('Contact Phone', $fields['contact_phone']['label']);
		$this->assertSame(AIPS_Integration_Interface::SHAPE_LONG_TEXT, $fields['contact_phone']['shape']);
	}

	public function test_get_fields_humanizes_label_when_no_description_registered() {
		$this->register_test_meta('contact_phone_number', array(
			'type'   => 'string',
			'single' => true,
		));

		$fields = $this->fields_by_key($this->adapter->get_fields('post'));

		$this->assertSame('Contact Phone Number', $fields['contact_phone_number']['label']);
	}

	public function test_get_fields_excludes_protected_meta_key_by_default() {
		$this->register_test_meta('_internal_flag', array(
			'type'   => 'string',
			'single' => true,
		));

		$fields = $this->fields_by_key($this->adapter->get_fields('post'));

		$this->assertArrayNotHasKey('_internal_flag', $fields);
	}

	public function test_get_fields_includes_protected_meta_key_when_requested() {
		$this->register_test_meta('_internal_flag', array(
			'type'   => 'string',
			'single' => true,
		));

		$fields = $this->fields_by_key($this->adapter->get_fields('post', array('include_protected' => true)));

		$this->assertArrayHasKey('_internal_flag', $fields);
	}

	public function test_get_supported_field_types_maps_registered_and_freeform_types() {
		$type_map = $this->adapter->get_supported_field_types();

		$this->assertSame(AIPS_Integration_Interface::SHAPE_LONG_TEXT, $type_map['string']);
		$this->assertSame(AIPS_Integration_Interface::SHAPE_SHORT_TEXT, $type_map['integer']);
		$this->assertSame(AIPS_Integration_Interface::SHAPE_STRUCTURED_LIST, $type_map['array']);
		$this->assertSame(AIPS_Integration_Interface::SHAPE_SHORT_TEXT, $type_map['freeform_short_text']);
		$this->assertSame(AIPS_Integration_Interface::SHAPE_LONG_TEXT, $type_map['freeform_long_text']);
		$this->assertSame(AIPS_Integration_Interface::SHAPE_HTML, $type_map['freeform_html']);
	}

	public function test_validate_field_key_allows_protected_key() {
		// Protected/internal keys are only hidden from discovery by default —
		// once an admin has explicitly selected or typed one via the "Show
		// Advanced Custom Meta Fields" toggle, it must be saveable.
		$this->assertTrue($this->adapter->validate_field_key('_secret'));
	}

	public function test_validate_field_key_rejects_invalid_characters() {
		$result = $this->adapter->validate_field_key('my key!');
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('invalid_meta_key', $result->get_error_code());
	}

	public function test_validate_field_key_accepts_normal_key() {
		$this->assertTrue($this->adapter->validate_field_key('contact_phone_number'));
	}

	/**
	 * @dataProvider reserved_key_provider
	 */
	public function test_validate_field_key_rejects_reserved_key($reserved_key) {
		$result = $this->adapter->validate_field_key($reserved_key);
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('protected_meta_key', $result->get_error_code());
	}

	public function reserved_key_provider() {
		return array(
			'AIPS own meta'       => array('_aips_generated_post'),
			'WP core internal'    => array('_wp_page_template'),
			'edit lock'           => array('_edit_lock'),
			'oEmbed cache'        => array('_oembed_abc123'),
			'menu item wiring'    => array('_menu_item_object_id'),
			'thumbnail id'        => array('_thumbnail_id'),
		);
	}

	public function test_get_fields_excludes_reserved_key_even_when_include_protected() {
		$this->register_test_meta('_wp_reserved_demo', array(
			'type'   => 'string',
			'single' => true,
		));

		$fields = $this->fields_by_key($this->adapter->get_fields('post', array('include_protected' => true)));

		$this->assertArrayNotHasKey('_wp_reserved_demo', $fields);
	}

	public function test_write_field_value_rejects_reserved_key() {
		$post_id = self::factory()->post->create();

		$result = $this->adapter->write_field_value($post_id, '_aips_generated_post', 'x');

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('protected_meta_key', $result->get_error_code());
		$this->assertSame('', get_post_meta($post_id, '_aips_generated_post', true));
	}

	public function test_write_field_value_writes_post_meta() {
		$post_id = self::factory()->post->create();

		$result = $this->adapter->write_field_value($post_id, 'contact_phone', '555-1234');

		$this->assertTrue($result);
		$this->assertSame('555-1234', get_post_meta($post_id, 'contact_phone', true));
	}

	public function test_write_field_value_treats_unchanged_value_as_success() {
		$post_id = self::factory()->post->create();
		update_post_meta($post_id, 'contact_phone', 'same-value');

		$result = $this->adapter->write_field_value($post_id, 'contact_phone', 'same-value');

		$this->assertTrue($result);
	}

	public function test_write_field_value_writes_protected_key() {
		$post_id = self::factory()->post->create();

		$result = $this->adapter->write_field_value($post_id, '_secret_meta', 'x');

		$this->assertTrue($result);
		$this->assertSame('x', get_post_meta($post_id, '_secret_meta', true));
	}

	public function test_write_field_value_rejects_invalid_post_id() {
		$result = $this->adapter->write_field_value(0, 'contact_phone', 'x');
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('invalid_post', $result->get_error_code());
	}
}
