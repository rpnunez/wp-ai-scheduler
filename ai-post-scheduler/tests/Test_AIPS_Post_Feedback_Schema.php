<?php

class Test_AIPS_Post_Feedback_Schema extends WP_UnitTestCase {
	public function test_schema_registers_post_feedback_table_and_scope_columns() {
		$manager = new AIPS_DB_Manager();
		$schema = implode("\n", $manager->get_schema());

		$this->assertContains('aips_post_feedback', AIPS_DB_Manager::get_table_names());
		$this->assertStringContainsString('CREATE TABLE ' . $GLOBALS['wpdb']->prefix . 'aips_post_feedback', $schema);
		$this->assertStringContainsString('embedding_text longtext', $schema);
		$this->assertStringContainsString('embedding longtext', $schema);
		$this->assertSame(2, substr_count($schema, 'feedback_enabled tinyint(1) DEFAULT NULL'));
		$this->assertSame(2, substr_count($schema, 'feedback_config longtext DEFAULT NULL'));
	}

	public function test_feedback_datetime_is_registered() {
		$map = AIPS_DB_Manager::get_datetime_column_map();
		$this->assertArrayHasKey('aips_post_feedback', $map);
		$this->assertContains(array('created_at', false), $map['aips_post_feedback']);
	}

	public function test_feedback_defaults_are_opt_in_and_bounded() {
		$defaults = AIPS_Config::get_instance()->get_default_options();
		$this->assertFalse($defaults['aips_post_feedback_enabled']);
		$this->assertSame(1.0, $defaults['aips_post_feedback_like_weight']);
		$this->assertSame(1.25, $defaults['aips_post_feedback_dislike_weight']);
		$this->assertSame(6, $defaults['aips_post_feedback_max_examples']);
		$this->assertSame(4000, $defaults['aips_post_feedback_prompt_budget_chars']);
	}
}
