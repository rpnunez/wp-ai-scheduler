<?php
/**
 * Tests for AIPS_Prompt_Profile_Version_Service.
 *
 * @package AI_Post_Scheduler
 */

class Mock_Profiles_Repo_For_Version_Service {
	public $profiles = array();

	public function get_by_id($id) {
		$id = (int) $id;
		return isset($this->profiles[$id]) ? $this->profiles[$id] : null;
	}

	public function update($id, array $data) {
		$id = (int) $id;
		if (!isset($this->profiles[$id])) {
			return new WP_Error('not_found', 'Prompt profile not found.');
		}
		foreach ($data as $k => $v) {
			$this->profiles[$id]->$k = $v;
		}
		return true;
	}
}

class Mock_Versions_Repo_For_Version_Service {
	public $versions = array();
	public $next_id = 1;

	public function create_snapshot($profile_id, array $stages, array $parameters, $summary, $user_id = null) {
		$id = $this->next_id++;
		$version_number = $this->get_latest_version_number($profile_id) + 1;

		$this->versions[$id] = (object) array(
			'id'             => $id,
			'profile_id'     => (int) $profile_id,
			'version_number' => $version_number,
			'stages'         => $stages,
			'parameters'     => $parameters,
			'change_summary' => $summary,
			'created_by'     => $user_id,
			'created_at'     => time(),
		);

		return $id;
	}

	public function get_version($id) {
		$id = (int) $id;
		return isset($this->versions[$id]) ? $this->versions[$id] : null;
	}

	public function get_versions($profile_id) {
		$profile_id = (int) $profile_id;
		$result = array();
		foreach ($this->versions as $v) {
			if ((int) $v->profile_id === $profile_id) {
				$result[] = $v;
			}
		}
		usort($result, function ($a, $b) {
			return $b->version_number - $a->version_number;
		});
		return $result;
	}

	public function get_latest_version_number($profile_id) {
		$profile_id = (int) $profile_id;
		$max = 0;
		foreach ($this->versions as $v) {
			if ((int) $v->profile_id === $profile_id) {
				$max = max($max, $v->version_number);
			}
		}
		return $max;
	}
}

class Test_AIPS_Prompt_Profile_Version_Service extends WP_UnitTestCase {

	/** @var Mock_Profiles_Repo_For_Version_Service */
	private $profiles_repo;

	/** @var Mock_Versions_Repo_For_Version_Service */
	private $versions_repo;

	/** @var AIPS_Prompt_Profile_Version_Service */
	private $service;

	private function make_profile($id, array $overrides = array()) {
		$defaults = array(
			'id'                      => $id,
			'name'                    => 'Test Profile',
			'slug'                    => 'test-profile',
			'title_prompt'            => 'Original title prompt.',
			'title_followup_prompt'   => 'Original title followup prompt.',
			'content_prompt'          => 'Original content prompt.',
			'excerpt_prompt'          => 'Original excerpt prompt.',
			'excerpt_followup_prompt' => 'Original excerpt followup prompt.',
			'featured_image_prompt'   => 'Original featured image prompt.',
			'topic_ideas_prompt'      => 'Original topic ideas prompt.',
			'metadata_prompt'         => 'Original metadata prompt.',
			'taxonomy_prompt'         => 'Original taxonomy prompt.',
		);

		return (object) array_merge($defaults, $overrides);
	}

	public function setUp(): void {
		parent::setUp();
		$this->profiles_repo = new Mock_Profiles_Repo_For_Version_Service();
		$this->versions_repo = new Mock_Versions_Repo_For_Version_Service();
		$this->service = new AIPS_Prompt_Profile_Version_Service(
			$this->profiles_repo,
			$this->versions_repo,
			new AIPS_Prompt_Profile_Diff_Engine()
		);
	}

	public function test_update_with_snapshot_creates_version_before_mutating_and_updates_profile() {
		$this->profiles_repo->profiles[1] = $this->make_profile(1);

		$result = $this->service->update_with_snapshot(1, array(
			'title_prompt'   => 'New title prompt.',
			'content_prompt' => 'New content prompt.',
		), 9);

		$this->assertTrue($result);

		// Live profile reflects the new values.
		$this->assertSame('New title prompt.', $this->profiles_repo->profiles[1]->title_prompt);
		$this->assertSame('New content prompt.', $this->profiles_repo->profiles[1]->content_prompt);
		// Untouched fields remain as-is.
		$this->assertSame('Original excerpt prompt.', $this->profiles_repo->profiles[1]->excerpt_prompt);

		// Exactly one version snapshot was recorded, capturing the PRE-mutation state.
		$versions = $this->versions_repo->get_versions(1);
		$this->assertCount(1, $versions);
		$this->assertSame('Original title prompt.', $versions[0]->stages['title_prompt']);
		$this->assertSame('Original content prompt.', $versions[0]->stages['content_prompt']);
		$this->assertSame(9, $versions[0]->created_by);

		// Change summary names both changed stages, in stage order.
		$this->assertStringContainsString('Post Title', $versions[0]->change_summary);
		$this->assertStringContainsString('Content Shell', $versions[0]->change_summary);
		$this->assertStringNotContainsString('Post Excerpt', $versions[0]->change_summary);
	}

	public function test_update_with_snapshot_returns_error_for_missing_profile() {
		$result = $this->service->update_with_snapshot(404, array('title_prompt' => 'x'), 1);

		$this->assertWPError($result);
		$this->assertSame('not_found', $result->get_error_code());
		$this->assertCount(0, $this->versions_repo->get_versions(404));
	}

	public function test_restore_version_restores_all_stages_and_snapshots_current_state_first() {
		$this->profiles_repo->profiles[1] = $this->make_profile(1, array(
			'title_prompt'   => 'Current title.',
			'content_prompt' => 'Current content.',
		));

		// A historical snapshot representing an older state.
		$older_stages = array(
			'title_prompt'            => 'Older title.',
			'title_followup_prompt'   => 'Older title followup.',
			'content_prompt'          => 'Older content.',
			'excerpt_prompt'          => 'Older excerpt.',
			'excerpt_followup_prompt' => 'Older excerpt followup.',
			'featured_image_prompt'   => 'Older featured image.',
			'topic_ideas_prompt'      => 'Older topic ideas.',
			'metadata_prompt'         => 'Older metadata.',
			'taxonomy_prompt'         => 'Older taxonomy.',
		);
		$version_id = $this->versions_repo->create_snapshot(1, $older_stages, array(), 'Historical snapshot', 1);

		$result = $this->service->restore_version(1, $version_id, 5);
		$this->assertTrue($result);

		// All 9 stages were restored from the version.
		foreach ($older_stages as $key => $value) {
			$this->assertSame($value, $this->profiles_repo->profiles[1]->$key);
		}

		// Restoring itself created a NEW pre-restore snapshot of what was live
		// immediately before the restore (i.e. "Current title." / "Current content.").
		$versions = $this->versions_repo->get_versions(1);
		$this->assertCount(2, $versions); // the historical one + the new pre-restore snapshot
		$pre_restore_snapshot = $versions[0]; // newest first
		$this->assertSame('Current title.', $pre_restore_snapshot->stages['title_prompt']);
		$this->assertSame('Current content.', $pre_restore_snapshot->stages['content_prompt']);
		$this->assertStringContainsString('restoring version', $pre_restore_snapshot->change_summary);
	}

	public function test_restore_version_rejects_version_not_belonging_to_profile() {
		$this->profiles_repo->profiles[1] = $this->make_profile(1);
		$this->profiles_repo->profiles[2] = $this->make_profile(2);

		$version_id = $this->versions_repo->create_snapshot(2, array('title_prompt' => 'Profile 2 title.'), array(), 'For profile 2', 1);

		$result = $this->service->restore_version(1, $version_id, 1);

		$this->assertWPError($result);
		$this->assertSame('version_profile_mismatch', $result->get_error_code());
	}

	public function test_restore_version_rejects_missing_profile() {
		$result = $this->service->restore_version(999, 1, 1);

		$this->assertWPError($result);
		$this->assertSame('not_found', $result->get_error_code());
	}

	public function test_restore_version_rejects_missing_version() {
		$this->profiles_repo->profiles[1] = $this->make_profile(1);

		$result = $this->service->restore_version(1, 99999, 1);

		$this->assertWPError($result);
		$this->assertSame('version_not_found', $result->get_error_code());
	}

	public function test_restore_stage_restores_only_named_stage_leaving_others_untouched() {
		$this->profiles_repo->profiles[1] = $this->make_profile(1, array(
			'title_prompt'   => 'Current title.',
			'content_prompt' => 'Current content.',
		));

		$version_id = $this->versions_repo->create_snapshot(1, array(
			'title_prompt'   => 'Version title.',
			'content_prompt' => 'Version content.',
		), array(), 'Historical', 1);

		$result = $this->service->restore_stage(1, $version_id, 'content_prompt', 1);
		$this->assertTrue($result);

		$this->assertSame('Version content.', $this->profiles_repo->profiles[1]->content_prompt);
		// The title stage was NOT touched by a stage-scoped restore.
		$this->assertSame('Current title.', $this->profiles_repo->profiles[1]->title_prompt);
	}

	public function test_restore_stage_rejects_invalid_stage_key() {
		$this->profiles_repo->profiles[1] = $this->make_profile(1);
		$version_id = $this->versions_repo->create_snapshot(1, array('title_prompt' => 'x'), array(), 'Historical', 1);

		$result = $this->service->restore_stage(1, $version_id, 'not_a_real_stage', 1);

		$this->assertWPError($result);
		$this->assertSame('invalid_stage', $result->get_error_code());
	}

	public function test_restore_stage_rejects_version_not_belonging_to_profile() {
		$this->profiles_repo->profiles[1] = $this->make_profile(1);
		$this->profiles_repo->profiles[2] = $this->make_profile(2);

		$version_id = $this->versions_repo->create_snapshot(2, array('title_prompt' => 'Profile 2 title.'), array(), 'For profile 2', 1);

		$result = $this->service->restore_stage(1, $version_id, 'title_prompt', 1);

		$this->assertWPError($result);
		$this->assertSame('version_profile_mismatch', $result->get_error_code());
	}

	/**
	 * Primary diff_versions() path: comparing two arbitrary EXPLICIT historical
	 * versions (both real, stored version IDs — not a "vs current" comparison).
	 */
	public function test_diff_versions_compares_two_explicit_historical_versions() {
		$this->profiles_repo->profiles[1] = $this->make_profile(1);

		$version_a_id = $this->versions_repo->create_snapshot(1, array(
			'title_prompt'   => 'Alpha title.',
			'content_prompt' => 'Shared content.',
		), array(), 'Snapshot A', 1);

		$version_b_id = $this->versions_repo->create_snapshot(1, array(
			'title_prompt'   => 'Beta title.',
			'content_prompt' => 'Shared content.',
		), array(), 'Snapshot B', 1);

		$result = $this->service->diff_versions(1, $version_a_id, $version_b_id);

		$this->assertIsArray($result);
		$this->assertSame(1, $result['version_a']);
		$this->assertSame(2, $result['version_b']);

		$this->assertTrue($result['stages']['title_prompt']['changed']);
		$this->assertNotEmpty($result['stages']['title_prompt']['segments']);

		// content_prompt was identical between the two historical versions.
		$this->assertFalse($result['stages']['content_prompt']['changed']);
	}

	public function test_diff_versions_against_current_live_profile_state() {
		$this->profiles_repo->profiles[1] = $this->make_profile(1, array(
			'title_prompt' => 'Live current title.',
		));

		$version_id = $this->versions_repo->create_snapshot(1, array(
			'title_prompt' => 'Historical title.',
		), array(), 'Historical', 1);

		// Omitted second argument means "diff against current".
		$result_omitted = $this->service->diff_versions(1, $version_id);
		$this->assertSame('current', $result_omitted['version_b']);
		$this->assertTrue($result_omitted['stages']['title_prompt']['changed']);

		// Explicit 'current' string behaves the same way.
		$result_explicit = $this->service->diff_versions(1, $version_id, 'current');
		$this->assertSame('current', $result_explicit['version_b']);
		$this->assertTrue($result_explicit['stages']['title_prompt']['changed']);
	}

	public function test_diff_versions_rejects_version_not_belonging_to_profile() {
		$this->profiles_repo->profiles[1] = $this->make_profile(1);
		$this->profiles_repo->profiles[2] = $this->make_profile(2);

		$version_a_id = $this->versions_repo->create_snapshot(1, array('title_prompt' => 'A'), array(), 'A', 1);
		$version_b_id = $this->versions_repo->create_snapshot(2, array('title_prompt' => 'B'), array(), 'B', 1);

		$result = $this->service->diff_versions(1, $version_a_id, $version_b_id);

		$this->assertWPError($result);
		$this->assertSame('version_profile_mismatch', $result->get_error_code());
	}

	public function test_diff_versions_rejects_missing_profile() {
		$result = $this->service->diff_versions(999, 1, 2);

		$this->assertWPError($result);
		$this->assertSame('not_found', $result->get_error_code());
	}
}
