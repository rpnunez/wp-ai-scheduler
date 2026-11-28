<?php
/**
 * Topic Embedding Component
 *
 * Owns author composite baseline embedding calculation and continuous vector
 * indexing enqueueing for newly generated topics within
 * AIPS_Topic_Generation_Strategy.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Topic_Embedding_Component
 */
class AIPS_Topic_Embedding_Component implements AIPS_Generation_Component_Interface {

	/**
	 * @var AIPS_Embeddings_Service|null
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_Embeddings_Repository|null
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Embeddings_Rate_Limiter|null
	 */
	private $rate_limiter;

	/**
	 * @var AIPS_Content_Indexer_Service|null
	 */
	private $indexer_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Embeddings_Service|null      $embeddings_service Optional embeddings service.
	 * @param AIPS_Embeddings_Repository|null   $embeddings_repo    Optional embeddings repository.
	 * @param AIPS_Embeddings_Rate_Limiter|null $rate_limiter       Optional rate limiter.
	 * @param AIPS_Content_Indexer_Service|null $indexer_service    Optional content indexer service.
	 */
	public function __construct(
		$embeddings_service = null,
		$embeddings_repo = null,
		?AIPS_Embeddings_Rate_Limiter $rate_limiter = null,
		?AIPS_Content_Indexer_Service $indexer_service = null
	) {
		$this->embeddings_service = $embeddings_service;
		$this->embeddings_repo    = $embeddings_repo;
		$this->rate_limiter       = $rate_limiter;
		$this->indexer_service    = $indexer_service;
	}

	/**
	 * @return AIPS_Embeddings_Repository
	 */
	private function get_embeddings_repo() {
		if ($this->embeddings_repo === null) {
			$container             = AIPS_Container::get_instance();
			$this->embeddings_repo = $container->has(AIPS_Embeddings_Repository::class)
				? $container->make(AIPS_Embeddings_Repository::class)
				: new AIPS_Embeddings_Repository();
		}

		return $this->embeddings_repo;
	}

	/**
	 * @return AIPS_Embeddings_Rate_Limiter
	 */
	private function get_rate_limiter() {
		if ($this->rate_limiter === null) {
			$container          = AIPS_Container::get_instance();
			$this->rate_limiter = $container->has(AIPS_Embeddings_Rate_Limiter::class)
				? $container->make(AIPS_Embeddings_Rate_Limiter::class)
				: new AIPS_Embeddings_Rate_Limiter();
		}

		return $this->rate_limiter;
	}

	/**
	 * @return AIPS_Embeddings_Service
	 */
	private function get_embeddings_service() {
		if ($this->embeddings_service === null) {
			$container                = AIPS_Container::get_instance();
			$this->embeddings_service = $container->has(AIPS_Embeddings_Service::class)
				? $container->make(AIPS_Embeddings_Service::class)
				: new AIPS_Embeddings_Service(null, null, null, $this->get_rate_limiter(), $this->get_embeddings_repo());
		}

		return $this->embeddings_service;
	}

	/**
	 * @return AIPS_Content_Indexer_Service|null
	 */
	private function get_indexer_service() {
		if ($this->indexer_service === null) {
			$container             = AIPS_Container::get_instance();
			$this->indexer_service = $container->has(AIPS_Content_Indexer_Service::class)
				? $container->make(AIPS_Content_Indexer_Service::class)
				: null;
		}

		return $this->indexer_service;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'topic_embedding';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Topic Embeddings', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_strategy_key(): string {
		return 'author_topics';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool {
		return (bool) $this->get_embeddings_service()->is_enabled();
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		if (!($context instanceof AIPS_Author_Topic_Generation_Context)) {
			return null;
		}

		$author = $context->get_author();
		if (!$author) {
			return null;
		}

		return $this->compute_author_composite_embedding($author);
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		return $this->generate($context, $session, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) {
		return array();
	}

	/**
	 * Enqueue saved non-rejected topic IDs for continuous background vector indexing.
	 *
	 * @param mixed $target Array of saved topic arrays (or topic IDs).
	 * @param mixed $value  Unused or optional override list of IDs.
	 * @return bool True when queued or indexing is not required/enabled.
	 */
	public function apply($target, $value): bool {
		$config      = AIPS_Config::get_instance();
		$sync_topics = (bool) $config->get_option('aips_indexer_topics_continuous_sync', true);

		if (!$sync_topics || !$this->get_embeddings_service()->is_enabled() || !$this->get_indexer_service()) {
			return true;
		}

		$topic_ids = array();
		if (is_array($target)) {
			foreach ($target as $item) {
				if (is_array($item)) {
					$status = isset($item['status']) ? $item['status'] : 'pending';
					if ($status !== 'rejected' && !empty($item['id'])) {
						$topic_ids[] = (int) $item['id'];
					}
				} elseif (is_numeric($item) && (int) $item > 0) {
					$topic_ids[] = (int) $item;
				}
			}
		}

		if (empty($topic_ids)) {
			return true;
		}

		$this->get_indexer_service()->enqueue_topics_for_indexing($topic_ids);
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		$topic_id = absint($target_id);
		if ($topic_id <= 0 || !$this->get_embeddings_repo()) {
			return false;
		}

		$existing = $this->get_embeddings_repo()->get_by_object('topic', $topic_id);
		return !empty($existing);
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_ai_edit(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		return is_array($value) ? array_map('floatval', $value) : null;
	}

	/**
	 * Compute or retrieve the cached composite reference embedding vector for an Author.
	 *
	 * @param object $author Author object.
	 * @return array|null Composite vector array or null if unavailable.
	 */
	public function compute_author_composite_embedding($author): ?array {
		if (!$this->get_embeddings_service()->is_enabled()) {
			return null;
		}

		$author_id = isset($author->id) ? (int) $author->id : 0;

		$profile_parts = array(
			!empty($author->name) ? $author->name : '',
			!empty($author->author_persona) ? $author->author_persona : (!empty($author->persona) ? $author->persona : ''),
			!empty($author->author_bio) ? $author->author_bio : (!empty($author->bio) ? $author->bio : ''),
			!empty($author->writing_style) ? $author->writing_style : '',
			!empty($author->content_goals) ? $author->content_goals : '',
			!empty($author->target_audience) ? $author->target_audience : '',
			!empty($author->niche) ? $author->niche : '',
		);
		$profile_text = trim(implode(' ', array_filter($profile_parts)));
		if (empty($profile_text)) {
			return null;
		}

		$content_hash     = md5($profile_text);
		$cache_key        = 'aips_author_composite_vec_' . $author_id . '_' . substr($content_hash, 0, 12);
		$legacy_cache_key = 'aips_author_composite_vec_' . $author_id;

		$cached = get_transient($cache_key);
		if (is_array($cached) && !empty($cached)) {
			return $cached;
		}

		if ($this->get_embeddings_repo() && $author_id > 0) {
			$existing = $this->get_embeddings_repo()->get_by_object('author', $author_id);
			if ($existing && !empty($existing->content_hash) && $existing->content_hash === $content_hash) {
				$decoded = $this->get_embeddings_repo()->decode_embedding($existing->embedding, 'author', $author_id, $content_hash);
				if (!empty($decoded) && is_array($decoded)) {
					set_transient($cache_key, $decoded, 12 * HOUR_IN_SECONDS);
					set_transient($legacy_cache_key, $decoded, 12 * HOUR_IN_SECONDS);
					return $decoded;
				}
			}
		}

		if ($this->get_rate_limiter()->is_in_cooldown()) {
			return null;
		}

		$profile_vec = $this->get_embeddings_service()->generate_embedding($profile_text);
		if (is_wp_error($profile_vec) || !is_array($profile_vec)) {
			return null;
		}

		$vectors = array($profile_vec);

		if (!empty($author->post_author) || !empty($author->wp_user_id)) {
			$user_id  = !empty($author->post_author) ? (int) $author->post_author : (int) $author->wp_user_id;
			$post_ids = get_posts(array(
				'author'         => $user_id,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
			));

			if (!empty($post_ids)) {
				foreach ($post_ids as $pid) {
					$row = $this->get_embeddings_repo()->get_by_post_id((int) $pid);
					if ($row && !empty($row->embedding)) {
						$pvec = $this->get_embeddings_repo()->decode_embedding($row->embedding, 'post', (int) $pid, !empty($row->content_hash) ? $row->content_hash : '');
						if (!empty($pvec) && count($pvec) === count($profile_vec)) {
							$vectors[] = $pvec;
						}
					}
				}
			}
		}

		$dimensions = count($profile_vec);
		$count      = count($vectors);
		$composite  = array_fill(0, $dimensions, 0.0);

		foreach ($vectors as $v) {
			for ($i = 0; $i < $dimensions; $i++) {
				$composite[$i] += (float) $v[$i];
			}
		}

		for ($i = 0; $i < $dimensions; $i++) {
			$composite[$i] /= $count;
		}

		if ($this->get_embeddings_repo() && $author_id > 0) {
			$model = $this->get_embeddings_service()->get_active_model();
			$this->get_embeddings_repo()->upsert(
				'author',
				$author_id,
				$composite,
				$model,
				$dimensions,
				$content_hash
			);
		}

		set_transient($cache_key, $composite, 12 * HOUR_IN_SECONDS);
		set_transient($legacy_cache_key, $composite, 12 * HOUR_IN_SECONDS);
		return $composite;
	}
}
