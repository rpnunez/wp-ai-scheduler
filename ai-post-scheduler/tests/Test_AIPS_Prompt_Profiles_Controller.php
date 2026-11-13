<?php
/**
 * Tests for the Prompt Profile Version History / Diff / Rollback AJAX
 * endpoints on AIPS_Prompt_Profiles_Controller.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Profiles_Controller extends WP_UnitTestCase {

	/** @var AIPS_Prompt_Profiles_Controller */
	private $controller;

	/** @var AIPS_Prompt_Profiles_Repository */
	private $profiles_repo;

	/** @var AIPS_Prompt_Profile_Versions_Repository */
	private $versions_repo;

	public function setUp(): void {
		parent::setUp();

		// Reset singletons so each test starts from a clean repository state.
		foreach (array('AIPS_Prompt_Profiles_Repository', 'AIPS_Prompt_Profile_Versions_Repository') as $cls) {
			if (class_exists($cls)) {
				$ref = new ReflectionProperty($cls, 'instance');
				$ref->setAccessible(true);
				$ref->setValue(null, null);
			}
		}

		AIPS_DB_Manager::install_tables();

		$this->profiles_repo = AIPS_Prompt_Profiles_Repository::instance();
		$this->versions_repo = AIPS_Prompt_Profile_Versions_Repository::instance();

		$version_service = new AIPS_Prompt_Profile_Version_Service(
			$this->profiles_repo,
			$this->versions_repo,
			new AIPS_Prompt_Profile_Diff_Engine()
		);

		$this->controller = new AIPS_Prompt_Profiles_Controller($this->profiles_repo, null, $version_service, $this->versions_repo);

		$admin_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_id);
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}aips_prompt_profiles");
		$wpdb->query("DELETE FROM {$wpdb->prefix}aips_prompt_profile_versions");
		parent::tearDown();
	}

	private function create_profile(array $overrides = array()) {
		$data = array_merge(array(
			'name'           => 'Test Profile',
			'title_prompt'   => 'Original title prompt.',
			'content_prompt' => 'Original content prompt.',
			'is_active'      => 1,
		), $overrides);

		$id = $this->profiles_repo->create($data);
		$this->assertIsInt($id);
		return $id;
	}

	public function getActualOutput(): string {
		$output = ob_get_contents();
		if ($output === false) {
			return '';
		}
		ob_clean();
		return $output;
	}

	// ------------------------------------------------------------------
	// aips_get_prompt_profile_versions
	// ------------------------------------------------------------------

	public function test_ajax_get_prompt_profile_versions_success() {
		$profile_id = $this->create_profile();
		$this->versions_repo->create_snapshot($profile_id, array('title_prompt' => 'Snapshot one.'), array(), 'First snapshot', get_current_user_id());
		$this->versions_repo->create_snapshot($profile_id, array('title_prompt' => 'Snapshot two.'), array(), 'Second snapshot', get_current_user_id());

		$_POST['profile_id'] = $profile_id;
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];

		try {
			$this->controller->ajax_get_prompt_profile_versions();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$response = json_decode($this->getActualOutput(), true);

		$this->assertTrue($response['success']);
		$this->assertCount(2, $response['data']['versions']);
		$this->assertSame(2, $response['data']['versions'][0]['version_number']);
		$this->assertSame('Second snapshot', $response['data']['versions'][0]['change_summary']);
	}

	public function test_ajax_get_prompt_profile_versions_invalid_nonce() {
		$profile_id = $this->create_profile();

		$_POST['profile_id'] = $profile_id;
		$_POST['nonce'] = 'invalid_nonce';
		$_REQUEST['nonce'] = $_POST['nonce'];

		$this->expectException(WPAjaxDieStopException::class);
		$this->controller->ajax_get_prompt_profile_versions();
	}

	public function test_ajax_get_prompt_profile_versions_no_permissions() {
		$profile_id = $this->create_profile();

		$subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber_id);

		$_POST['profile_id'] = $profile_id;
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];

		try {
			$this->controller->ajax_get_prompt_profile_versions();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$response = json_decode($this->getActualOutput(), true);
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
	}

	// ------------------------------------------------------------------
	// aips_get_prompt_profile_version_diff
	// ------------------------------------------------------------------

	/**
	 * Primary path: compare two arbitrary EXPLICIT historical versions
	 * (version_id_a and version_id_b both real, stored version IDs).
	 */
	public function test_ajax_get_prompt_profile_version_diff_compares_two_explicit_historical_versions() {
		$profile_id = $this->create_profile();

		$version_a_id = $this->versions_repo->create_snapshot($profile_id, array(
			'title_prompt'   => 'Alpha title.',
			'content_prompt' => 'Shared content.',
		), array(), 'Snapshot A', get_current_user_id());

		$version_b_id = $this->versions_repo->create_snapshot($profile_id, array(
			'title_prompt'   => 'Beta title.',
			'content_prompt' => 'Shared content.',
		), array(), 'Snapshot B', get_current_user_id());

		$_POST['profile_id']   = $profile_id;
		$_POST['version_id_a'] = $version_a_id;
		$_POST['version_id_b'] = $version_b_id;
		$_POST['nonce']        = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce']     = $_POST['nonce'];

		try {
			$this->controller->ajax_get_prompt_profile_version_diff();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$response = json_decode($this->getActualOutput(), true);

		$this->assertTrue($response['success']);
		$this->assertSame(1, $response['data']['diff']['version_a']);
		$this->assertSame(2, $response['data']['diff']['version_b']);
		$this->assertTrue($response['data']['diff']['stages']['title_prompt']['changed']);
		$this->assertNotEmpty($response['data']['diff']['stages']['title_prompt']['segments']);
		$this->assertFalse($response['data']['diff']['stages']['content_prompt']['changed']);
	}

	/**
	 * Convenience path: version_id_b omitted/'current' diffs against the
	 * live profile state instead of a stored version.
	 */
	public function test_ajax_get_prompt_profile_version_diff_against_current_live_state() {
		$profile_id = $this->create_profile(array('title_prompt' => 'Live current title.'));

		$version_id = $this->versions_repo->create_snapshot($profile_id, array(
			'title_prompt' => 'Historical title.',
		), array(), 'Historical snapshot', get_current_user_id());

		$_POST['profile_id']   = $profile_id;
		$_POST['version_id_a'] = $version_id;
		$_POST['nonce']        = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce']     = $_POST['nonce'];
		unset($_POST['version_id_b']);

		try {
			$this->controller->ajax_get_prompt_profile_version_diff();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$response = json_decode($this->getActualOutput(), true);

		$this->assertTrue($response['success']);
		$this->assertSame('current', $response['data']['diff']['version_b']);
		$this->assertTrue($response['data']['diff']['stages']['title_prompt']['changed']);
	}

	public function test_ajax_get_prompt_profile_version_diff_invalid_nonce() {
		$profile_id = $this->create_profile();
		$version_id = $this->versions_repo->create_snapshot($profile_id, array('title_prompt' => 'x'), array(), 'Summary', 1);

		$_POST['profile_id']   = $profile_id;
		$_POST['version_id_a'] = $version_id;
		$_POST['nonce']        = 'invalid_nonce';
		$_REQUEST['nonce']     = $_POST['nonce'];

		$this->expectException(WPAjaxDieStopException::class);
		$this->controller->ajax_get_prompt_profile_version_diff();
	}

	public function test_ajax_get_prompt_profile_version_diff_no_permissions() {
		$profile_id = $this->create_profile();
		$version_id = $this->versions_repo->create_snapshot($profile_id, array('title_prompt' => 'x'), array(), 'Summary', 1);

		$subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber_id);

		$_POST['profile_id']   = $profile_id;
		$_POST['version_id_a'] = $version_id;
		$_POST['nonce']        = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce']     = $_POST['nonce'];

		try {
			$this->controller->ajax_get_prompt_profile_version_diff();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$response = json_decode($this->getActualOutput(), true);
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
	}

	// ------------------------------------------------------------------
	// aips_restore_prompt_profile_version
	// ------------------------------------------------------------------

	public function test_ajax_restore_prompt_profile_version_success() {
		$profile_id = $this->create_profile(array(
			'title_prompt'   => 'Current title.',
			'content_prompt' => 'Current content.',
		));

		$version_id = $this->versions_repo->create_snapshot($profile_id, array(
			'title_prompt'   => 'Historical title.',
			'content_prompt' => 'Historical content.',
		), array(), 'Historical snapshot', get_current_user_id());

		$_POST['profile_id'] = $profile_id;
		$_POST['version_id'] = $version_id;
		$_POST['nonce']      = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce']   = $_POST['nonce'];

		try {
			$this->controller->ajax_restore_prompt_profile_version();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$response = json_decode($this->getActualOutput(), true);

		$this->assertTrue($response['success']);
		$this->assertSame('Historical title.', $response['data']['profile']['title_prompt']);
		$this->assertSame('Historical content.', $response['data']['profile']['content_prompt']);

		$reloaded = $this->profiles_repo->get_by_id($profile_id);
		$this->assertSame('Historical title.', $reloaded->title_prompt);

		// Restoring itself must have created an undoable pre-restore snapshot.
		$versions = $this->versions_repo->get_versions($profile_id);
		$this->assertCount(2, $versions);
	}

	public function test_ajax_restore_prompt_profile_version_no_permissions() {
		$profile_id = $this->create_profile();
		$version_id = $this->versions_repo->create_snapshot($profile_id, array('title_prompt' => 'x'), array(), 'Summary', 1);

		$subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber_id);

		$_POST['profile_id'] = $profile_id;
		$_POST['version_id'] = $version_id;
		$_POST['nonce']      = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce']   = $_POST['nonce'];

		try {
			$this->controller->ajax_restore_prompt_profile_version();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$response = json_decode($this->getActualOutput(), true);
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
	}

	public function test_ajax_restore_prompt_profile_version_invalid_nonce() {
		$profile_id = $this->create_profile();
		$version_id = $this->versions_repo->create_snapshot($profile_id, array('title_prompt' => 'x'), array(), 'Summary', 1);

		$_POST['profile_id'] = $profile_id;
		$_POST['version_id'] = $version_id;
		$_POST['nonce']      = 'invalid_nonce';
		$_REQUEST['nonce']   = $_POST['nonce'];

		$this->expectException(WPAjaxDieStopException::class);
		$this->controller->ajax_restore_prompt_profile_version();
	}

	// ------------------------------------------------------------------
	// aips_restore_prompt_profile_stage
	// ------------------------------------------------------------------

	public function test_ajax_restore_prompt_profile_stage_success() {
		$profile_id = $this->create_profile(array(
			'title_prompt'   => 'Current title.',
			'content_prompt' => 'Current content.',
		));

		$version_id = $this->versions_repo->create_snapshot($profile_id, array(
			'title_prompt'   => 'Historical title.',
			'content_prompt' => 'Historical content.',
		), array(), 'Historical snapshot', get_current_user_id());

		$_POST['profile_id'] = $profile_id;
		$_POST['version_id'] = $version_id;
		$_POST['stage']      = 'content_prompt';
		$_POST['nonce']      = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce']   = $_POST['nonce'];

		try {
			$this->controller->ajax_restore_prompt_profile_stage();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$response = json_decode($this->getActualOutput(), true);

		$this->assertTrue($response['success']);
		$this->assertSame('Historical content.', $response['data']['profile']['content_prompt']);
		// title_prompt was NOT touched by the stage-scoped restore.
		$this->assertSame('Current title.', $response['data']['profile']['title_prompt']);
	}

	public function test_ajax_restore_prompt_profile_stage_invalid_stage_key() {
		$profile_id = $this->create_profile();
		$version_id = $this->versions_repo->create_snapshot($profile_id, array('title_prompt' => 'x'), array(), 'Summary', 1);

		$_POST['profile_id'] = $profile_id;
		$_POST['version_id'] = $version_id;
		$_POST['stage']      = 'not_a_real_stage';
		$_POST['nonce']      = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce']   = $_POST['nonce'];

		try {
			$this->controller->ajax_restore_prompt_profile_stage();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$response = json_decode($this->getActualOutput(), true);
		$this->assertFalse($response['success']);
	}

	public function test_ajax_restore_prompt_profile_stage_no_permissions() {
		$profile_id = $this->create_profile();
		$version_id = $this->versions_repo->create_snapshot($profile_id, array('title_prompt' => 'x'), array(), 'Summary', 1);

		$subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber_id);

		$_POST['profile_id'] = $profile_id;
		$_POST['version_id'] = $version_id;
		$_POST['stage']      = 'title_prompt';
		$_POST['nonce']      = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce']   = $_POST['nonce'];

		try {
			$this->controller->ajax_restore_prompt_profile_stage();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$response = json_decode($this->getActualOutput(), true);
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
	}

	// ------------------------------------------------------------------
	// ajax_save_prompt_profile now auto-versions updates
	// ------------------------------------------------------------------

	public function test_ajax_save_prompt_profile_update_auto_creates_version_snapshot() {
		$profile_id = $this->create_profile(array(
			'title_prompt'   => 'Original title.',
			'content_prompt' => 'Original content.',
		));

		$_POST = array(
			'action'         => 'aips_save_prompt_profile',
			'id'             => $profile_id,
			'name'           => 'Test Profile',
			'title_prompt'   => 'Updated title.',
			'content_prompt' => 'Original content.',
			'nonce'          => wp_create_nonce('aips_ajax_nonce'),
		);
		$_REQUEST['nonce'] = $_POST['nonce'];

		try {
			$this->controller->ajax_save_prompt_profile();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$response = json_decode($this->getActualOutput(), true);
		$this->assertTrue($response['success']);

		$versions = $this->versions_repo->get_versions($profile_id);
		$this->assertCount(1, $versions, 'Saving an update through the AJAX controller must auto-create a version snapshot.');
		$this->assertSame('Original title.', $versions[0]->stages['title_prompt']);
		$this->assertStringContainsString('Post Title', $versions[0]->change_summary);
	}
}
