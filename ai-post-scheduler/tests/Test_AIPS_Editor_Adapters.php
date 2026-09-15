<?php
/**
 * Tests for AIPS Editor Adapters
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Editor_Adapters extends WP_UnitTestCase {

	public function test_editor_registry_resolves_gutenberg_by_default() {
		$registry = new AIPS_Editor_Registry();
		$post_id  = $this->factory->post->create(array('post_title' => 'Standard Post'));

		$adapter = $registry->get_active_adapter_for_post($post_id);
		$this->assertInstanceOf(AIPS_Gutenberg_Editor_Adapter::class, $adapter);
		$this->assertSame('gutenberg', $adapter->get_id());
	}

	public function test_editor_registry_resolves_elementor_when_active() {
		$registry = new AIPS_Editor_Registry();
		$post_id  = $this->factory->post->create(array('post_title' => 'Elementor Page'));
		update_post_meta($post_id, '_elementor_edit_mode', 'builder');

		$adapter = $registry->get_active_adapter_for_post($post_id);
		$this->assertInstanceOf(AIPS_Elementor_Editor_Adapter::class, $adapter);
		$this->assertSame('elementor', $adapter->get_id());
	}

	public function test_elementor_adapter_extracts_json_tree_content_and_links() {
		$target_post = $this->factory->post->create(array('post_title' => 'Target Article'));
		$target_url  = get_permalink($target_post);

		$elementor_data = array(
			array(
				'id'       => 'sec_1',
				'elType'   => 'section',
				'elements' => array(
					array(
						'id'       => 'col_1',
						'elType'   => 'column',
						'elements' => array(
							array(
								'id'         => 'w_text',
								'elType'     => 'widget',
								'widgetType' => 'text-editor',
								'settings'   => array(
									'editor' => '<p>Here is custom text linking to <a href="' . $target_url . '">Target</a>.</p>'
								),
							),
							array(
								'id'         => 'w_btn',
								'elType'     => 'widget',
								'widgetType' => 'button',
								'settings'   => array(
									'text' => 'Click Here',
									'link' => array('url' => $target_url)
								),
							),
						),
					),
				),
			),
		);

		$source_post = $this->factory->post->create(array('post_title' => 'Elementor Landing Page'));
		update_post_meta($source_post, '_elementor_edit_mode', 'builder');
		update_post_meta($source_post, '_elementor_data', wp_slash(json_encode($elementor_data)));

		$adapter = new AIPS_Elementor_Editor_Adapter();
		$content = $adapter->extract_content($source_post);

		$this->assertStringContainsString('Here is custom text linking to', $content);
		$this->assertStringContainsString('Click Here', $content);

		$links = $adapter->extract_links($source_post);
		$this->assertNotEmpty($links);
		$this->assertSame($target_post, $links[0]['target_id']);
	}
}
