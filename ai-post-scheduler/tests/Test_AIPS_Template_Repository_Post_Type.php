<?php
/**
 * Tests for AIPS_Template_Repository post_type handling: validated on
 * create, immutable on update.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Template_Repository_Post_Type extends WP_UnitTestCase {

	/** @var AIPS_Template_Repository */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		$this->repo = new AIPS_Template_Repository();

		register_post_type('aips_test_cpt', array(
			'public' => true,
			'label'  => 'AIPS Test CPT',
		));
	}

	public function tearDown(): void {
		unregister_post_type('aips_test_cpt');
		parent::tearDown();
	}

	private function minimal_template_data($overrides = array()) {
		return array_merge(array(
			'name'            => 'Post Type Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'post_status'     => 'draft',
		), $overrides);
	}

	public function test_create_persists_a_valid_custom_post_type() {
		$id = $this->repo->create($this->minimal_template_data(array('post_type' => 'aips_test_cpt')));
		$template = $this->repo->get_by_id($id);

		$this->assertSame('aips_test_cpt', $template->post_type);
	}

	public function test_create_defaults_to_post_for_unregistered_post_type() {
		$id = $this->repo->create($this->minimal_template_data(array('post_type' => 'does_not_exist')));
		$template = $this->repo->get_by_id($id);

		$this->assertSame('post', $template->post_type);
	}

	public function test_create_defaults_to_post_when_omitted() {
		$id = $this->repo->create($this->minimal_template_data());
		$template = $this->repo->get_by_id($id);

		$this->assertSame('post', $template->post_type);
	}

	public function test_create_rejects_attachment_post_type() {
		$id = $this->repo->create($this->minimal_template_data(array('post_type' => 'attachment')));
		$template = $this->repo->get_by_id($id);

		$this->assertSame('post', $template->post_type);
	}

	public function test_update_ignores_post_type_changes() {
		$id = $this->repo->create($this->minimal_template_data(array('post_type' => 'aips_test_cpt')));

		$this->repo->update($id, array('post_type' => 'post', 'name' => 'Renamed'));

		$template = $this->repo->get_by_id($id);
		$this->assertSame('aips_test_cpt', $template->post_type);
		$this->assertSame('Renamed', $template->name);
	}
}
