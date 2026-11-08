<?php
/**
 * Topic-silo campaign provenance and atomic pillar claims.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Campaign_Silo_Safety extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
			$this->markTestSkipped('Campaign silo tests require the full WordPress test library.');
		}

		AIPS_DB_Manager::install_tables();
		$this->reset_campaigns_repository();
	}

	public function tearDown(): void {
		global $wpdb;

		$wpdb->query('DELETE FROM ' . $wpdb->prefix . 'aips_internal_links');
		$wpdb->query('DELETE FROM ' . $wpdb->prefix . 'aips_campaign_templates');
		$wpdb->query('DELETE FROM ' . $wpdb->prefix . 'aips_campaigns');
		$this->reset_campaigns_repository();

		parent::tearDown();
	}

	public function test_shared_template_without_campaign_meta_is_not_linked_to_either_pillar() {
		$pillar_a = self::factory()->post->create(array(
			'post_status' => 'publish',
			'post_title'  => 'Pillar A',
		));
		$pillar_b = self::factory()->post->create(array(
			'post_status' => 'publish',
			'post_title'  => 'Pillar B',
		));
		$spoke = self::factory()->post->create(array(
			'post_status' => 'publish',
			'post_title'  => 'Unscoped spoke',
		));

		$template_id = 6401;
		$campaign_a = $this->create_silo_campaign('Campaign A', $pillar_a);
		$campaign_b = $this->create_silo_campaign('Campaign B', $pillar_b);
		$repository = new AIPS_Campaigns_Repository();
		$this->assertTrue($repository->link_template($campaign_a, $template_id));
		$this->assertTrue($repository->link_template($campaign_b, $template_id));

		update_post_meta($spoke, '_aips_template_id', $template_id);

		$this->queue_silo_inbound($spoke);

		$this->assertSame(array(), $this->silo_links_for($spoke));
	}

	public function test_single_campaign_template_without_campaign_meta_is_not_linked() {
		$pillar = self::factory()->post->create(array(
			'post_status' => 'publish',
			'post_title'  => 'Only Pillar',
		));
		$spoke = self::factory()->post->create(array(
			'post_status' => 'publish',
			'post_title'  => 'Standalone template post',
		));

		$template_id = 6402;
		$campaign_id = $this->create_silo_campaign('Only Campaign', $pillar);
		$repository = new AIPS_Campaigns_Repository();
		$this->assertTrue($repository->link_template($campaign_id, $template_id));
		update_post_meta($spoke, '_aips_template_id', $template_id);

		$this->queue_silo_inbound($spoke);

		$this->assertSame(array(), $this->silo_links_for($spoke));
	}

	public function test_explicit_campaign_meta_links_shared_template_post_to_that_pillar_only() {
		$pillar_a = self::factory()->post->create(array(
			'post_status' => 'publish',
			'post_title'  => 'Pillar A',
		));
		$pillar_b = self::factory()->post->create(array(
			'post_status' => 'publish',
			'post_title'  => 'Pillar B',
		));
		$spoke = self::factory()->post->create(array(
			'post_status' => 'publish',
			'post_title'  => 'Campaign B spoke',
		));

		$template_id = 6403;
		$campaign_a = $this->create_silo_campaign('Campaign A', $pillar_a);
		$campaign_b = $this->create_silo_campaign('Campaign B', $pillar_b);
		$repository = new AIPS_Campaigns_Repository();
		$this->assertTrue($repository->link_template($campaign_a, $template_id));
		$this->assertTrue($repository->link_template($campaign_b, $template_id));

		update_post_meta($spoke, '_aips_template_id', $template_id);
		update_post_meta($spoke, '_aips_campaign_id', $campaign_b);

		$this->queue_silo_inbound($spoke);

		$links = $this->silo_links_for($spoke);
		$this->assertCount(1, $links);
		$this->assertSame($pillar_b, (int) $links[0]->source_post_id);
		$this->assertSame($spoke, (int) $links[0]->target_post_id);
	}

	public function test_stale_empty_pillar_snapshot_does_not_overwrite_the_first_claim() {
		$template_id = 77;
		$repository = new AIPS_Campaigns_Repository();
		$campaign_id = $repository->create_campaign(array(
			'name'               => 'Race Silo',
			'silo_enabled'       => 1,
			'pillar_template_id' => $template_id,
		));
		$this->assertGreaterThan(0, $campaign_id);

		$inner = $this->createMock(AIPS_Generation_Context::class);
		$inner->method('get_id')->willReturn($template_id);
		$snapshot = (object) array(
			'id'                 => $campaign_id,
			'name'               => 'Race Silo',
			'silo_enabled'       => 1,
			'pillar_post_id'     => null,
			'pillar_template_id' => $template_id,
		);
		$context = new AIPS_Campaign_Context($inner, $snapshot);

		$first = self::factory()->post->create();
		$second = self::factory()->post->create();
		$this->claim_pillar($context, $first);
		// The same snapshot still reports an empty pillar, as a second in-flight generation would.
		$this->claim_pillar($context, $second);

		$this->assertSame($first, $this->stored_pillar_post_id($campaign_id));
	}

	public function test_claim_pillar_post_if_unset_leaves_an_existing_pillar_in_place() {
		$repository = new AIPS_Campaigns_Repository();
		$open_id = $repository->create_campaign(array(
			'name'         => 'Open Silo',
			'silo_enabled' => 1,
		));
		$claimed_id = $repository->create_campaign(array(
			'name'           => 'Claimed Silo',
			'silo_enabled'   => 1,
			'pillar_post_id' => 501,
		));
		$this->assertGreaterThan(0, $open_id);
		$this->assertGreaterThan(0, $claimed_id);

		$this->assertTrue($repository->claim_pillar_post_if_unset($open_id, 700));
		$this->assertSame(700, $this->stored_pillar_post_id($open_id));
		$this->assertFalse($repository->claim_pillar_post_if_unset($open_id, 701));
		$this->assertSame(700, $this->stored_pillar_post_id($open_id));

		$this->assertFalse($repository->claim_pillar_post_if_unset($claimed_id, 802));
		$this->assertSame(501, $this->stored_pillar_post_id($claimed_id));
	}

	/**
	 * @param string $name          Campaign name.
	 * @param int    $pillar_post_id Published pillar post ID.
	 * @return int
	 */
	private function create_silo_campaign($name, $pillar_post_id) {
		$campaign_id = (new AIPS_Campaigns_Repository())->create_campaign(array(
			'name'           => $name,
			'silo_enabled'   => 1,
			'pillar_post_id' => $pillar_post_id,
		));
		$this->assertGreaterThan(0, $campaign_id);

		return (int) $campaign_id;
	}

	/**
	 * @param int $post_id Spoke post ID.
	 * @return void
	 */
	private function queue_silo_inbound($post_id) {
		$service = new AIPS_Publish_Linking_Service();
		$method = new ReflectionMethod(AIPS_Publish_Linking_Service::class, 'maybe_queue_campaign_silo_inbound');
		$method->setAccessible(true);
		$method->invoke($service, $post_id);
	}

	/**
	 * @param AIPS_Campaign_Context $context Campaign context snapshot.
	 * @param int                   $post_id Post ID attempting the claim.
	 * @return void
	 */
	private function claim_pillar(AIPS_Campaign_Context $context, $post_id) {
		$generator = new AIPS_Generator();
		$method = new ReflectionMethod(AIPS_Generator::class, 'maybe_claim_campaign_pillar');
		$method->setAccessible(true);
		$method->invoke($generator, $context, $post_id);
	}

	/**
	 * @param int $post_id Target post ID.
	 * @return array
	 */
	private function silo_links_for($post_id) {
		global $wpdb;

		$links = $wpdb->get_results($wpdb->prepare(
			"SELECT source_post_id, target_post_id FROM {$wpdb->prefix}aips_internal_links WHERE target_post_id = %d AND anchor_source = %s",
			$post_id,
			'campaign_silo'
		));

		return is_array($links) ? $links : array();
	}

	/**
	 * @param int $campaign_id Campaign ID.
	 * @return int|null
	 */
	private function stored_pillar_post_id($campaign_id) {
		global $wpdb;

		$value = $wpdb->get_var($wpdb->prepare(
			"SELECT pillar_post_id FROM {$wpdb->prefix}aips_campaigns WHERE id = %d",
			$campaign_id
		));

		if (null === $value || '' === $value) {
			return null;
		}

		return (int) $value;
	}

	/**
	 * @return void
	 */
	private function reset_campaigns_repository() {
		$reflection = new ReflectionClass(AIPS_Campaigns_Repository::class);
		if (!$reflection->hasProperty('instance')) {
			return;
		}

		$instance = $reflection->getProperty('instance');
		$instance->setAccessible(true);
		$instance->setValue(null, null);
	}
}
