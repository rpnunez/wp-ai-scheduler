<?php
/**
 * Tests for AIPS_Content_Auditor_Scanner
 *
 * @package AI_Post_Scheduler
 */
class Test_AIPS_Content_Auditor_Scanner extends WP_UnitTestCase {

	/**
	 * @var AIPS_Content_Auditor_Scanner
	 */
	private $scanner;

	public function setUp(): void {
		parent::setUp();
		$this->scanner = new AIPS_Content_Auditor_Scanner();
	}

	public function test_extract_post_fingerprint_structure() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'Understanding Dependency Injection in PHP',
			'post_name'    => 'understanding-dependency-injection-php',
			'post_content' => '<p>This is an introductory paragraph explaining concepts in depth.</p>'
				. '<h2>Why Dependency Injection Matters</h2>'
				. '<p>Detailed discussion and code samples for enterprise applications.</p>'
				. '<h3>Best Practices for Containers</h3>'
				. '<p>Learn how to wire containers properly. Check our <a href="/php-design-patterns/">design patterns guide</a> for more info.</p>',
			'post_status'  => 'publish',
			'post_date'    => '2026-01-01 10:00:00',
		));

		$fingerprint = $this->scanner->extract_post_fingerprint($post_id);

		$this->assertSame($post_id, $fingerprint['id']);
		$this->assertSame('Understanding Dependency Injection in PHP', $fingerprint['title']);
		$this->assertSame('understanding-dependency-injection-php', $fingerprint['slug']);
		$this->assertGreaterThan(0, $fingerprint['word_count']);
		$this->assertContains('Why Dependency Injection Matters', $fingerprint['headings']);
		$this->assertContains('Best Practices for Containers', $fingerprint['headings']);
		$this->assertContains('/php-design-patterns/', $fingerprint['outbound_internal_links']);
		$this->assertSame(1, $fingerprint['outbound_link_count']);
		$this->assertNotEmpty($fingerprint['keyphrases']);
		$this->assertFalse($fingerprint['is_decayed']);
	}

	public function test_build_link_graph_and_orphan_detection() {
		$fingerprints = array(
			array(
				'id'                      => 101,
				'title'                   => 'Post A',
				'slug'                    => 'post-a',
				'url'                     => 'https://example.org/post-a/',
				'categories'              => array('PHP'),
				'outbound_internal_links' => array('https://example.org/post-b/'),
				'outbound_link_count'     => 1,
				'word_count'              => 800,
				'is_decayed'              => false,
				'is_thin'                 => false,
				'keyphrases'              => array('php', 'tutorial'),
			),
			array(
				'id'                      => 102,
				'title'                   => 'Post B',
				'slug'                    => 'post-b',
				'url'                     => 'https://example.org/post-b/',
				'categories'              => array('PHP'),
				'outbound_internal_links' => array('https://example.org/post-a/'),
				'outbound_link_count'     => 1,
				'word_count'              => 900,
				'is_decayed'              => false,
				'is_thin'                 => false,
				'keyphrases'              => array('php', 'framework'),
			),
			array(
				'id'                      => 103,
				'title'                   => 'Post C (Orphan)',
				'slug'                    => 'post-c-orphan',
				'url'                     => 'https://example.org/post-c-orphan/',
				'categories'              => array('DevOps'),
				'outbound_internal_links' => array(),
				'outbound_link_count'     => 0,
				'word_count'              => 750,
				'is_decayed'              => false,
				'is_thin'                 => false,
				'keyphrases'              => array('devops', 'docker'),
			),
		);

		$graph = $this->scanner->build_link_graph($fingerprints);

		$this->assertSame(1, $graph['orphan_count']);
		$this->assertSame(array(103), $graph['orphan_post_ids']);
		$this->assertSame(2, $graph['total_internal_connections']);

		$fp_map = array_column($graph['fingerprints'], null, 'id');
		$this->assertContains(101, $fp_map[102]['inbound_internal_links']);
		$this->assertContains(102, $fp_map[101]['inbound_internal_links']);
		$this->assertSame(0, $fp_map[103]['inbound_link_count']);
		$this->assertTrue($fp_map[103]['is_orphan']);
	}

	public function test_build_entity_clusters_decay_and_cannibalization() {
		$fingerprints = array(
			array(
				'id'                      => 201,
				'title'                   => 'Ultimate Guide to Docker Containers',
				'slug'                    => 'ultimate-guide-docker-containers',
				'url'                     => 'https://example.org/docker-containers/',
				'categories'              => array('DevOps'),
				'tags'                    => array('Docker', 'Containers'),
				'word_count'              => 1200,
				'age_days'                => 200,
				'modified_date'           => '2025-06-01 12:00:00',
				'is_decayed'              => true,
				'is_thin'                 => false,
				'keyphrases'              => array('docker containers', 'docker', 'containers', 'guide'),
			),
			array(
				'id'                      => 202,
				'title'                   => 'Complete Guide for Docker Containers Setup',
				'slug'                    => 'complete-guide-docker-containers-setup',
				'url'                     => 'https://example.org/docker-containers-setup/',
				'categories'              => array('DevOps'),
				'tags'                    => array('Docker'),
				'word_count'              => 450,
				'age_days'                => 30,
				'modified_date'           => '2026-07-25 12:00:00',
				'is_decayed'              => false,
				'is_thin'                 => true,
				'keyphrases'              => array('docker containers', 'docker', 'containers', 'setup'),
			),
		);

		$clusters = $this->scanner->build_entity_clusters($fingerprints);

		$this->assertSame(2, $clusters['total_posts']);
		$this->assertSame(1650, $clusters['total_words']);
		$this->assertSame(825, $clusters['avg_word_count']);
		$this->assertSame(array('DevOps' => 2), $clusters['category_distribution']);

		// Decay & Thin counts
		$this->assertSame(1, $clusters['decay_count']);
		$this->assertSame(201, $clusters['decayed_posts'][0]['id']);
		$this->assertSame(1, $clusters['thin_count']);
		$this->assertSame(202, $clusters['thin_posts'][0]['id']);

		// Cannibalization candidate detection
		$this->assertSame(1, $clusters['cannibalization_count']);
		$this->assertSame(201, $clusters['cannibalization_candidates'][0]['post_a']['id']);
		$this->assertSame(202, $clusters['cannibalization_candidates'][0]['post_b']['id']);
		$this->assertContains('docker containers', $clusters['cannibalization_candidates'][0]['shared_keyphrases']);
	}

	public function test_content_auditor_facade_delegation() {
		$auditor = new AIPS_Content_Auditor();

		$this->assertInstanceOf(AIPS_Content_Auditor_Scanner::class, $auditor->get_scanner());
		$fingerprints = $auditor->scan_site_library(5);
		$this->assertIsArray($fingerprints);

		$graph = $auditor->get_link_graph($fingerprints);
		$this->assertArrayHasKey('orphan_post_ids', $graph);

		$clusters = $auditor->get_entity_clusters($fingerprints);
		$this->assertArrayHasKey('category_distribution', $clusters);
	}
}
