<?php
/**
 * Shared Test Harness & Fixtures for Generation Framework Suites
 *
 * Provides a deterministic FIFO AI service test double and reusable domain
 * fixtures for components, strategies, and contexts.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Deterministic FIFO test double implementing AIPS_AI_Service_Interface.
 */
class AIPS_Generation_Test_AI_Service implements AIPS_AI_Service_Interface {

	/** @var bool */
	private $available = true;

	/** @var bool */
	private $embeddings_supported = true;

	/** @var bool */
	private $conversation_supported = true;

	/** @var array<int, mixed> */
	private $text_queue = array();

	/** @var array<int, mixed> */
	private $json_queue = array();

	/** @var array<int, mixed> */
	private $image_queue = array();

	/** @var array<int, mixed> */
	private $embedding_queue = array();

	/** @var array<int, array<string, mixed>> */
	private $call_log = array();

	/** @var mixed */
	private $default_text = 'Generated test response';

	/** @var mixed */
	private $default_json = array();

	/** @var mixed */
	private $default_image = 'https://example.com/generated-image.jpg';

	/** @var mixed */
	private $default_embedding = array(0.5, 0.5, 0.5);

	/**
	 * Toggle AI availability.
	 *
	 * @param bool $available Whether AI is available.
	 * @return self
	 */
	public function set_available(bool $available): self {
		$this->available = $available;
		return $this;
	}

	/**
	 * Toggle embeddings support.
	 *
	 * @param bool $supported Whether embeddings are supported.
	 * @return self
	 */
	public function set_supports_embeddings(bool $supported): self {
		$this->embeddings_supported = $supported;
		return $this;
	}

	/**
	 * Toggle conversation history support.
	 *
	 * @param bool $supported Whether conversation replay is supported.
	 * @return self
	 */
	public function set_supports_conversation(bool $supported): self {
		$this->conversation_supported = $supported;
		return $this;
	}

	/**
	 * Enqueue a response (scalar, array, WP_Error, or Closure) for generate_text().
	 *
	 * @param mixed $response Queued response or callback.
	 * @return self
	 */
	public function queue_text($response): self {
		$this->text_queue[] = $response;
		return $this;
	}

	/**
	 * Enqueue a response (array, WP_Error, null, or Closure) for generate_json().
	 *
	 * @param mixed $response Queued response or callback.
	 * @return self
	 */
	public function queue_json($response): self {
		$this->json_queue[] = $response;
		return $this;
	}

	/**
	 * Enqueue a response (URL string, WP_Error, or Closure) for generate_image().
	 *
	 * @param mixed $response Queued response or callback.
	 * @return self
	 */
	public function queue_image($response): self {
		$this->image_queue[] = $response;
		return $this;
	}

	/**
	 * Enqueue a response (float[], WP_Error, or Closure) for generate_embedding().
	 *
	 * @param mixed $response Queued response or callback.
	 * @return self
	 */
	public function queue_embedding($response): self {
		$this->embedding_queue[] = $response;
		return $this;
	}

	/**
	 * Set fallback text when the text queue is empty.
	 *
	 * @param mixed $default Fallback value.
	 * @return self
	 */
	public function set_default_text($default): self {
		$this->default_text = $default;
		return $this;
	}

	/**
	 * Set fallback JSON value when the JSON queue is empty.
	 *
	 * @param mixed $default Fallback value.
	 * @return self
	 */
	public function set_default_json($default): self {
		$this->default_json = $default;
		return $this;
	}

	/**
	 * Clear recorded call log entries.
	 *
	 * @return void
	 */
	public function clear_call_log(): void {
		$this->call_log = array();
	}

	/**
	 * Return the most recent call log entry or null.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_last_call(): ?array {
		if (empty($this->call_log)) {
			return null;
		}

		return $this->call_log[count($this->call_log) - 1];
	}

	/**
	 * Count recorded calls, optionally filtered by method name.
	 *
	 * @param string|null $method Optional method filter.
	 * @return int
	 */
	public function count_calls(?string $method = null): int {
		if ($method === null) {
			return count($this->call_log);
		}

		$count = 0;
		foreach ($this->call_log as $entry) {
			if ($entry['method'] === $method) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available() {
		return $this->available;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_embeddings() {
		return $this->embeddings_supported;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_conversation() {
		return $this->conversation_supported;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_call_log() {
		return $this->call_log;
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_text($prompt, $options = array()) {
		return $this->dispatch_call('generate_text', (string) $prompt, $options, $this->text_queue, $this->default_text);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_json($prompt, $options = array()) {
		return $this->dispatch_call('generate_json', (string) $prompt, $options, $this->json_queue, $this->default_json);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_image($prompt, $options = array()) {
		return $this->dispatch_call('generate_image', (string) $prompt, $options, $this->image_queue, $this->default_image);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_embedding($text, $options = array()) {
		return $this->dispatch_call('generate_embedding', (string) $text, $options, $this->embedding_queue, $this->default_embedding);
	}

	/**
	 * Record call, invoke one-shot submission callback if present, and dequeue response.
	 *
	 * @param string $method   Interface method name.
	 * @param string $input    Prompt or input text.
	 * @param array  $options  Request options.
	 * @param array  $queue    FIFO response queue (by reference).
	 * @param mixed  $fallback Default value when queue is empty.
	 * @return mixed
	 */
	private function dispatch_call(string $method, string $input, array $options, array &$queue, $fallback) {
		if (!$this->available) {
			return new WP_Error('ai_unavailable', 'AI service is unavailable in test double.');
		}

		$logged_options = $options;
		if (isset($options['_aips_on_submission']) && is_callable($options['_aips_on_submission'])) {
			unset($logged_options['_aips_on_submission']);
			call_user_func($options['_aips_on_submission']);
		}

		$this->call_log[] = array(
			'method'  => $method,
			'prompt'  => $input,
			'options' => $logged_options,
		);

		$next = !empty($queue) ? array_shift($queue) : $fallback;
		if ($next instanceof Closure) {
			return $next($input, $logged_options);
		}

		return $next;
	}
}

/**
 * Minimal concrete subclass of AIPS_Abstract_Generation_Context that leaves all
 * 24 default methods un-overridden for contract testing.
 */
class AIPS_Test_Abstract_Generation_Context_Stub extends AIPS_Abstract_Generation_Context {

	/**
	 * {@inheritdoc}
	 */
	public function get_type() {
		return 'abstract_stub';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id() {
		return 99;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name() {
		return 'Abstract Stub Context';
	}

	/**
	 * {@inheritdoc}
	 */
	public function to_array() {
		return array(
			'type' => $this->get_type(),
			'id'   => $this->get_id(),
			'name' => $this->get_name(),
		);
	}
}

/**
 * Reusable fixture builders for generation strategy and component tests.
 */
class AIPS_Generation_Test_Fixtures {

	/**
	 * Create a WordPress draft post for component persistence tests.
	 *
	 * @param array $overrides Optional wp_insert_post overrides.
	 * @return int Post ID.
	 */
	public static function create_draft_post(array $overrides = array()): int {
		$defaults = array(
			'post_title'   => 'Initial Draft Title',
			'post_content' => '<p>Initial paragraph one about WordPress caching.</p><p>Second paragraph about Redis object cache performance.</p>',
			'post_excerpt' => 'Initial draft excerpt.',
			'post_name'    => 'initial-draft-title',
			'post_status'  => 'draft',
			'post_type'    => 'post',
		);

		$post_id = wp_insert_post(array_merge($defaults, $overrides), true);
		return is_wp_error($post_id) ? 0 : (int) $post_id;
	}

	/**
	 * Create a real WordPress attachment with the given MIME type.
	 *
	 * @param int    $parent_post_id Optional parent post ID.
	 * @param string $mime_type      Attachment MIME type.
	 * @return int Attachment post ID.
	 */
	public static function create_attachment(int $parent_post_id = 0, string $mime_type = 'image/jpeg'): int {
		$ext = (0 === strpos($mime_type, 'image/')) ? 'jpg' : 'txt';
		$attachment_id = wp_insert_attachment(
			array(
				'post_title'     => 'Test Attachment ' . $ext,
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_mime_type' => $mime_type,
				'guid'           => 'https://example.com/wp-content/uploads/2026/11/test-attachment.' . $ext,
			),
			'2026/11/test-attachment.' . $ext,
			$parent_post_id
		);

		return is_wp_error($attachment_id) ? 0 : (int) $attachment_id;
	}

	/**
	 * Build a standard template stdClass object for AIPS_Template_Context.
	 *
	 * @param array $overrides Field overrides.
	 * @return object
	 */
	public static function make_template_object(array $overrides = array()) {
		$defaults = array(
			'id'                      => 101,
			'name'                    => 'Technical Deep Dive Template',
			'prompt_template'         => 'Write an in-depth technical guide about {{topic}}.',
			'title_prompt'            => 'Write a compelling SEO title for {{topic}}.',
			'slug_prompt'             => 'Generate a concise hyphenated URL slug for {{topic}}.',
			'generate_slug'           => 1,
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => 'Minimalist vector illustration representing {{topic}}',
			'unsplash_keywords'       => 'wordpress,engineering',
			'media_library_ids'       => '',
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'post_category'           => 1,
			'post_tags'               => 'wordpress,php',
			'post_author'             => 1,
			'article_structure_id'    => null,
			'voice_id'                => null,
			'include_sources'         => 0,
			'source_group_ids'        => '[]',
			'affiliate_links_enabled' => 0,
			'is_active'               => 1,
		);

		return (object) array_merge($defaults, $overrides);
	}

	/**
	 * Build an AIPS_Template_Context instance.
	 *
	 * @param array       $overrides Template field overrides.
	 * @param string|null $topic     Optional topic string.
	 * @return AIPS_Template_Context
	 */
	public static function make_template_context(array $overrides = array(), ?string $topic = 'WordPress Query Optimization'): AIPS_Template_Context {
		return new AIPS_Template_Context(self::make_template_object($overrides), null, $topic);
	}

	/**
	 * Build an author stdClass object.
	 *
	 * @param array $overrides Field overrides.
	 * @return object
	 */
	public static function make_author_object(array $overrides = array()) {
		$defaults = array(
			'id'                        => 21,
			'name'                      => 'Elena Backend',
			'field_niche'               => 'WordPress Performance Engineering',
			'description'               => ' writes pragmatic guides on database indexing and caching.',
			'keywords'                  => 'mysql,redis,object-cache',
			'details'                   => 'Target senior WordPress developers.',
			'voice_tone'                => 'analytical and concise',
			'writing_style'             => 'code-first tutorials',
			'target_audience'           => 'WordPress engineers',
			'expertise_level'           => 'senior',
			'topic_generation_prompt'   => 'Focus on measurable latency wins.',
			'topic_generation_quantity' => 3,
			'generate_slug'             => 1,
			'generate_featured_image'   => 0,
			'featured_image_source'     => 'ai_prompt',
			'post_status'               => 'draft',
			'post_type'                 => 'post',
			'post_category'             => 1,
			'post_tags'                 => '',
			'post_author'               => 1,
			'article_structure_id'      => null,
			'include_sources'           => 0,
			'source_group_ids'          => '[]',
			'is_active'                 => 1,
		);

		return (object) array_merge($defaults, $overrides);
	}

	/**
	 * Persist a real author + topic row pair in the test DB for Author Topic AI Edit tests.
	 *
	 * @param array $author_overrides Author column overrides.
	 * @param array $topic_overrides  Topic column overrides.
	 * @return array{author_id:int,topic_id:int,author:object,topic:object}
	 */
	public static function create_persisted_author_and_topic(array $author_overrides = array(), array $topic_overrides = array()): array {
		$authors_repo = new AIPS_Authors_Repository();
		$topics_repo  = new AIPS_Author_Topics_Repository();

		$author_id = (int) $authors_repo->create(array_merge(array(
			'name'                      => 'Topic Test Author ' . wp_generate_password(4, false, false),
			'field_niche'               => 'WordPress Security & Performance',
			'keywords'                  => 'nonces,capabilities,caching',
			'voice_tone'                => 'authoritative',
			'writing_style'             => 'practical guides',
			'topic_generation_quantity' => 3,
			'is_active'                 => 1,
		), $author_overrides));

		$topic_id = (int) $topics_repo->create(array_merge(array(
			'author_id'    => $author_id,
			'topic_title'  => 'Hardening REST API Endpoints in WordPress',
			'topic_prompt' => 'Explain permission_callback patterns and nonce verification.',
			'status'       => 'pending',
			'score'        => 72,
			'metadata'     => wp_json_encode(array('keywords' => array('rest-api', 'security'))),
		), $topic_overrides));

		return array(
			'author_id' => $author_id,
			'topic_id'  => $topic_id,
			'author'    => $authors_repo->get_by_id($author_id),
			'topic'     => $topics_repo->get_by_id($topic_id),
		);
	}

	/**
	 * Build a map of domain contexts keyed by strategy/context identifier.
	 *
	 * @return array<string, AIPS_Generation_Context>
	 */
	public static function make_all_contexts(): array {
		$keep_id   = self::create_draft_post(array('post_title' => 'Canonical Caching Guide'));
		$retire_id = self::create_draft_post(array('post_title' => 'Short Caching Note'));
		$keep_post   = get_post($keep_id);
		$retire_post = get_post($retire_id);

		$author = self::make_author_object();
		$topic  = (object) array(
			'id'           => 55,
			'author_id'    => $author->id,
			'topic_title'  => 'Understanding Object Cache Groups',
			'topic_prompt' => 'Cover persistent vs non-persistent groups.',
			'status'       => 'approved',
		);

		return array(
			'abstract_stub'   => new AIPS_Test_Abstract_Generation_Context_Stub(),
			'posts'           => self::make_template_context(),
			'topic_post'      => new AIPS_Topic_Context($author, $topic, 'Related topic A'),
			'author_topics'   => new AIPS_Author_Topic_Generation_Context(
				self::make_author_object(array(
					'include_sources'  => 1,
					'source_group_ids' => '[3,7]',
				)),
				true,
				'scheduled'
			),
			'author_personas' => new AIPS_Author_Persona_Generation_Context(
				array(
					'site_niche'      => 'Cloud Native WordPress',
					'target_audience' => 'Platform Engineers',
					'content_goals'   => 'Deep technical tutorials',
				),
				3
			),
			'campaigns'       => new AIPS_Campaign_Generation_Context(
				array(
					'topic_niche'      => 'WooCommerce Scaling',
					'campaign_goal'    => 'Organic search authority',
					'target_audience'  => 'Store Engineers',
					'pillar_count'     => 2,
					'spokes_per_pillar' => 3,
				),
				array('daily', 'weekly'),
				array('post'),
				array('how_to' => 'How-To Guide')
			),
			'research'        => new AIPS_Research_Generation_Context(
				AIPS_Research_Generation_Context::MODE_TRENDING,
				array(
					'niche'             => 'PHP 8.3 Modernization',
					'count'             => 5,
					'keywords'          => array('readonly', 'enums'),
					'term_ids'          => array(4),
					'topic'             => array('topic' => 'PHP 8.3 Readonly Classes', 'keywords' => array('php')),
					'comparison_topics' => array(
						array('topic' => 'A', 'score' => 90, 'keywords' => array('x')),
						array('topic' => 'B', 'score' => 80, 'keywords' => array('y')),
					),
				)
			),
			'consolidation'   => new AIPS_Consolidation_Generation_Context(
				$keep_post,
				$retire_post,
				'Preserve code benchmarks from both posts.'
			),
			'content_audit'   => new AIPS_Content_Audit_Generation_Context(
				'topic_gaps',
				'Identify missing security topics across current posts.',
				array('temperature' => 0.3),
				0.4,
				false
			),
			'taxonomy'        => new AIPS_Taxonomy_Generation_Context(
				'category',
				array($keep_id, $retire_id),
				'Focus on technical developer categories.'
			),
			'planner_topics'  => new AIPS_Planner_Topic_Generation_Context('WordPress Block Hooks API', 5),
			'ai_assistance'   => new AIPS_AI_Assistance_Generation_Context(
				array(
					'form_context'   => 'authors',
					'form_field_id'  => 'field_niche',
					'field_name'     => 'Author Niche',
					'current_value'  => 'WordPress',
					'context_fields' => array('name' => 'Alex'),
				),
				'sess_test_123',
				1
			),
		);
	}
}
