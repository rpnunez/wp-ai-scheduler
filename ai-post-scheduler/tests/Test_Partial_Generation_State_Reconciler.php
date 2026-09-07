<?php
/**
 * Tests for AIPS_Partial_Generation_State_Reconciler
 *
 * Covers the metadata_exists() fast-path short-circuit added in the
 * on_save_post() handler to avoid 3 get_post_meta() calls for posts that
 * have no AIPS generation metadata at all.
 *
 * @package AI_Post_Scheduler
 */

class Test_Partial_Generation_State_Reconciler extends WP_UnitTestCase {

	/** @var AIPS_Partial_Generation_State_Reconciler */
	private $reconciler;

	public function setUp(): void {
		parent::setUp();
		$this->reconciler = new AIPS_Partial_Generation_State_Reconciler();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Helper
	// -----------------------------------------------------------------------

	/**
	 * Build a minimal WP_Post-like object.
	 *
	 * @param string $post_type
	 * @return object
	 */
	private function make_post($post_type = 'post') {
		$p = new stdClass();
		$p->post_type = $post_type;
		$p->ID = 42;
		return $p;
	}

	// -----------------------------------------------------------------------
	// Early-out guards (unchanged logic, regression checks)
	// -----------------------------------------------------------------------

	/**
	 * on_save_post() must do nothing when $update is false (new post).
	 */
	public function test_skips_when_not_update() {
		$actions_fired = array();
		add_action('aips_partial_generation_state_reconciled', function() use (&$actions_fired) {
			$actions_fired[] = true;
		});

		$this->reconciler->on_save_post(42, $this->make_post(), false);

		$this->assertEmpty($actions_fired, 'Should not fire hook for new posts.');
	}

	/**
	 * The reconciler should listen to the later wp_after_insert_post hook too.
	 */
	public function test_registers_after_insert_post_hook() {
		$this->assertNotFalse(has_action('wp_after_insert_post', array($this->reconciler, 'on_after_insert_post')));
	}

	/**
	 * on_save_post() must do nothing for non-"post" post types.
	 */
	public function test_skips_for_non_post_type() {
		$actions_fired = array();
		add_action('aips_partial_generation_state_reconciled', function() use (&$actions_fired) {
			$actions_fired[] = true;
		});

		$this->reconciler->on_save_post(42, $this->make_post('page'), true);

		$this->assertEmpty($actions_fired, 'Should not fire hook for page post type.');
	}

	// -----------------------------------------------------------------------
	// metadata_exists() fast-path (the new short-circuit)
	// -----------------------------------------------------------------------

	/**
	 * When the primary key (_aips_post_generation_component_statuses) does NOT
	 * exist in post meta, on_save_post() must return early without calling
	 * reconcile_generation_status_meta_from_post() or firing the hook.
	 */
	public function test_fast_path_skips_when_primary_meta_absent() {
		global $aips_test_meta;
		$aips_test_meta = array(); // no AIPS meta on this post

		$actions_fired = array();
		add_action('aips_partial_generation_state_reconciled', function() use (&$actions_fired) {
			$actions_fired[] = true;
		});

		$this->reconciler->on_save_post(42, $this->make_post(), true);

		$this->assertEmpty($actions_fired, 'Hook must not fire when primary meta key is absent.');
		$this->assertEmpty($aips_test_meta, 'No meta must be written (no side effects) when the fast-path short-circuits.');
	}

	/**
	 * When metadata_exists() returns true (primary key is set), the handler
	 * must proceed past the fast-path check and attempt reconciliation.
	 * Because all three get_post_meta() values are non-empty, $has_generation_meta
	 * is true and reconcile_generation_status_meta_from_post() is called.
	 */
	public function test_proceeds_when_primary_meta_exists() {
		global $aips_test_meta;
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Meta Exists Post',
		));
		$aips_test_meta = array(
			$post_id => array(
				AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES => 'some_value',
			),
		);

		$actions_fired = array();
		add_action('aips_partial_generation_state_reconciled', function() use (&$actions_fired) {
			$actions_fired[] = true;
		});

		$this->reconciler->on_save_post($post_id, get_post($post_id), true);

		$this->assertNotEmpty($actions_fired, 'Hook must fire when primary meta key exists and reconcile returns data.');
	}

	/**
	 * When the primary key exists but its value is an empty string (e.g. the
	 * value was cleared after metadata_exists() became true), the reconciler
	 * must still run — the metadata_exists() guard is the sole gate, so any
	 * post that ever had AIPS meta will be reconciled and stale/empty values
	 * will be repaired with current post-content state.
	 */
	public function test_reconciles_when_meta_key_exists_with_empty_value() {
		global $aips_test_meta;
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Meta Exists Empty Value Post',
		));
		// metadata_exists returns true because the row exists (even with an empty value).
		$aips_test_meta = array(
			$post_id => array(
				AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES => '',
				AIPS_Post_Manager::META_GENERATION_INCOMPLETE         => '',
				AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL        => '',
			),
		);

		$actions_fired = array();
		add_action('aips_partial_generation_state_reconciled', function() use (&$actions_fired) {
			$actions_fired[] = true;
		});

		$this->reconciler->on_save_post($post_id, get_post($post_id), true);

		// The metadata_exists fast-path passes → reconcile runs → hook fires.
		$this->assertNotEmpty($actions_fired, 'Hook must fire when a meta key exists, even with an empty value.');
	}

	/**
	 * A partially generated post should be marked resolved once the stored post
	 * content is complete, even if the title still carries the AI prefix.
	 */
	public function test_reconciles_partial_post_after_manual_save() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'AI Generated Post: Observability Stack Review',
			'post_excerpt' => '',
			'post_content' => '',
			'post_status'  => 'draft',
		));

		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode(array(
			'post_title'     => false,
			'post_excerpt'   => false,
			'featured_image' => true,
			'post_content'   => false,
		)));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, 'true');

		wp_update_post(array(
			'ID'           => $post_id,
			'post_title'   => 'AI Generated Post: Observability Stack Review',
			'post_excerpt' => 'Resolved excerpt',
			'post_content' => 'Resolved content',
		));

		$this->reconciler->on_save_post($post_id, get_post($post_id), true);

		$this->assertSame('false', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, true));
		$this->assertSame('true', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, true));

		$statuses = json_decode((string) get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, true), true);
		$this->assertIsArray($statuses);
		$this->assertTrue($statuses['post_title']);
		$this->assertTrue($statuses['post_excerpt']);
		$this->assertTrue($statuses['post_content']);
		$this->assertTrue($statuses['featured_image']);
	}

	/**
	 * The later after-insert hook should also reconcile the current post state.
	 */
	public function test_reconciles_partial_post_after_after_insert_hook() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'AI Generated Post: Edit Screen Refresh',
			'post_excerpt' => '',
			'post_content' => '',
			'post_status'  => 'draft',
		));

		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode(array(
			'post_title'     => false,
			'post_excerpt'   => false,
			'featured_image' => true,
			'post_content'   => false,
		)));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, 'true');

		wp_update_post(array(
			'ID'           => $post_id,
			'post_title'   => 'AI Generated Post: Edit Screen Refresh',
			'post_excerpt' => 'Edit screen excerpt',
			'post_content' => 'Edit screen content',
		));

		$this->reconciler->on_after_insert_post($post_id, get_post($post_id), true, null);

		$this->assertSame('false', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, true));
		$this->assertSame('true', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, true));

		$statuses = json_decode((string) get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, true), true);
		$this->assertIsArray($statuses);
		$this->assertTrue($statuses['post_title']);
		$this->assertTrue($statuses['post_excerpt']);
		$this->assertTrue($statuses['post_content']);
	}

	/**
	 * The after-insert callback should remain callable when WordPress does not
	 * provide a post_before argument.
	 */
	public function test_after_insert_hook_accepts_optional_post_before_argument() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'AI Generated Post: Optional Argument',
			'post_excerpt' => '',
			'post_content' => '',
			'post_status'  => 'draft',
		));

		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode(array(
			'post_title'     => false,
			'post_excerpt'   => false,
			'featured_image' => true,
			'post_content'   => false,
		)));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, 'true');

		wp_update_post(array(
			'ID'           => $post_id,
			'post_excerpt' => 'Optional argument excerpt',
			'post_content' => 'Optional argument content',
		));

		$this->reconciler->on_after_insert_post($post_id, get_post($post_id), true);

		$this->assertSame('false', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, true));
		$statuses = json_decode((string) get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, true), true);
		$this->assertIsArray($statuses);
		$this->assertTrue($statuses['post_excerpt']);
		$this->assertTrue($statuses['post_content']);
	}

	/**
	 * The featured-image path must reconcile after the thumbnail is persisted.
	 */
	public function test_reconciles_featured_image_after_thumbnail_update() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'AI Generated Post: Featured Image Repair',
			'post_excerpt' => 'Resolved excerpt',
			'post_content' => 'Resolved content',
			'post_status'  => 'draft',
		));

		$attachment_id = $this->factory->post->create(array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
		));

		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode(array(
			'post_title'     => true,
			'post_excerpt'   => true,
			'featured_image' => false,
			'post_content'   => true,
		)));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, 'true');

		$this->reconciler->on_save_post($post_id, get_post($post_id), true);
		$this->assertSame('true', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, true));

		update_post_meta($post_id, '_thumbnail_id', $attachment_id);
		$this->reconciler->on_post_components_updated($post_id, array('featured_image'), array(
			'featured_image_id' => $attachment_id,
		));

		$this->assertSame('false', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, true));
		$this->assertSame('true', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL, true));

		$statuses = json_decode((string) get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, true), true);
		$this->assertIsArray($statuses);
		$this->assertTrue($statuses['featured_image']);
	}
}
