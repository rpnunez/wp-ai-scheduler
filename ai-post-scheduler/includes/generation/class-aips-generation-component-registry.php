<?php
/**
 * Generation Component Registry
 *
 * Central registry for all AIPS_Generation_Component_Interface implementations
 * across strategies. Supports lookup by strategy + component key, stage filtering,
 * metadata-turn filtering, and AI Edit capability discovery.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generation_Component_Registry
 */
class AIPS_Generation_Component_Registry {

	/**
	 * Alias map translating short AI Edit keys to canonical post component keys.
	 *
	 * @var array<string, string>
	 */
	private static $key_aliases = array(
		'title'   => 'post_title',
		'content' => 'post_content',
		'excerpt' => 'post_excerpt',
		'slug'    => 'post_slug',
	);

	/**
	 * Registered components keyed by strategy_key => component_key => instance.
	 *
	 * @var array<string, array<string, AIPS_Generation_Component_Interface>>
	 */
	private $components = array();

	/**
	 * Normalize a component key (resolving short aliases for the 'posts' strategy).
	 *
	 * @param string $component_key Raw component key or alias.
	 * @param string $strategy_key  Strategy key.
	 * @return string Canonical component key.
	 */
	public static function normalize_key(string $component_key, string $strategy_key = 'posts'): string {
		$component_key = sanitize_key($component_key);
		if ($strategy_key === 'posts' && isset(self::$key_aliases[$component_key])) {
			return self::$key_aliases[$component_key];
		}

		return $component_key;
	}

	/**
	 * Register a generation component.
	 *
	 * @param AIPS_Generation_Component_Interface $component Component instance.
	 * @return void
	 */
	public function register(AIPS_Generation_Component_Interface $component): void {
		$strategy_key  = $component->get_strategy_key();
		$component_key = $component->get_key();

		if (!isset($this->components[$strategy_key])) {
			$this->components[$strategy_key] = array();
		}

		$this->components[$strategy_key][$component_key] = $component;
	}

	/**
	 * Retrieve a registered component by key and optional strategy.
	 *
	 * @param string      $component_key Component key (or short alias for 'posts').
	 * @param string|null $strategy_key  Strategy key; defaults to 'posts' if omitted, or searches all strategies when null.
	 * @return AIPS_Generation_Component_Interface|null
	 */
	public function get(string $component_key, ?string $strategy_key = 'posts'): ?AIPS_Generation_Component_Interface {
		if ($strategy_key !== null) {
			$normalized = self::normalize_key($component_key, $strategy_key);
			return $this->components[$strategy_key][$normalized] ?? null;
		}

		foreach ($this->components as $strat => $map) {
			$normalized = self::normalize_key($component_key, $strat);
			if (isset($map[$normalized])) {
				return $map[$normalized];
			}
		}

		return null;
	}

	/**
	 * Check whether a component is registered.
	 *
	 * @param string      $component_key Component key (or alias).
	 * @param string|null $strategy_key  Strategy key.
	 * @return bool
	 */
	public function has(string $component_key, ?string $strategy_key = 'posts'): bool {
		return $this->get($component_key, $strategy_key) !== null;
	}

	/**
	 * Return all components registered for a strategy.
	 *
	 * @param string $strategy_key Strategy key.
	 * @return array<string, AIPS_Generation_Component_Interface>
	 */
	public function get_by_strategy(string $strategy_key): array {
		return $this->components[$strategy_key] ?? array();
	}

	/**
	 * Return all post components belonging to a specific pipeline stage.
	 *
	 * @param string $stage        Stage key (AIPS_Post_Component_Interface::STAGE_*).
	 * @param string $strategy_key Strategy key (default 'posts').
	 * @return AIPS_Post_Component_Interface[]
	 */
	public function get_by_stage(string $stage, string $strategy_key = 'posts'): array {
		$matched = array();
		foreach ($this->get_by_strategy($strategy_key) as $component) {
			if ($component instanceof AIPS_Post_Component_Interface && $component->get_stage() === $stage) {
				$matched[] = $component;
			}
		}

		return $matched;
	}

	/**
	 * Return all post components that participate in the combined metadata turn.
	 *
	 * @param string $strategy_key Strategy key (default 'posts').
	 * @return AIPS_Post_Component_Interface[]
	 */
	public function get_metadata_turn_components(string $strategy_key = 'posts'): array {
		$matched = array();
		foreach ($this->get_by_strategy($strategy_key) as $component) {
			if ($component instanceof AIPS_Post_Component_Interface && $component->participates_in_metadata_turn()) {
				$matched[] = $component;
			}
		}

		return $matched;
	}

	/**
	 * Return all components that support AI Edit revisions.
	 *
	 * @param string|null $strategy_key Optional strategy filter.
	 * @return AIPS_Generation_Component_Interface[]
	 */
	public function get_ai_editable(?string $strategy_key = null): array {
		$matched = array();
		$sources = $strategy_key !== null
			? array($strategy_key => $this->get_by_strategy($strategy_key))
			: $this->components;

		foreach ($sources as $map) {
			foreach ($map as $component) {
				if ($component->supports_ai_edit()) {
					$matched[] = $component;
				}
			}
		}

		return $matched;
	}

	/**
	 * Return all registered components across all strategies.
	 *
	 * @return array<string, array<string, AIPS_Generation_Component_Interface>>
	 */
	public function all(): array {
		return $this->components;
	}

	/**
	 * Register the default set of built-in Post Generation Components.
	 *
	 * @param AIPS_Generator|null     $generator     Optional generator instance shared by components.
	 * @param AIPS_Image_Service|null $image_service Optional image service instance.
	 * @param AIPS_Post_Manager|null  $post_manager  Optional post manager instance.
	 * @return self
	 */
	public function register_default_post_components(
		?AIPS_Generator $generator = null,
		?AIPS_Image_Service $image_service = null,
		?AIPS_Post_Manager $post_manager = null
	): self {
		$this->register(new AIPS_Content_Post_Component($generator));
		$this->register(new AIPS_Title_Post_Component($generator));
		$this->register(new AIPS_Excerpt_Post_Component($generator));
		$this->register(new AIPS_Slug_Post_Component($generator));
		$this->register(new AIPS_Featured_Image_Post_Component($generator, $image_service, $post_manager));
		$this->register(new AIPS_Content_Images_Post_Component($generator));
		$this->register(new AIPS_Taxonomy_Post_Component($generator));
		$this->register(new AIPS_Custom_Meta_Post_Component($generator));

		/**
		 * Allow extensions to register additional generation components.
		 *
		 * @param AIPS_Generation_Component_Registry $registry Component registry instance.
		 */
		do_action('aips_register_generation_components', $this);

		return $this;
	}

	/**
	 * Register the default set of built-in Author Topic Generation Components.
	 *
	 * @param AIPS_AI_Service_Interface|null      $ai_service         Optional AI service.
	 * @param AIPS_Author_Topics_Repository|null  $topics_repository  Optional topics repository.
	 * @param AIPS_Embeddings_Service|null        $embeddings_service Optional embeddings service.
	 * @param AIPS_Embeddings_Repository|null     $embeddings_repo    Optional embeddings repository.
	 * @param AIPS_Embeddings_Rate_Limiter|null   $rate_limiter       Optional rate limiter.
	 * @param AIPS_Content_Indexer_Service|null   $indexer_service    Optional content indexer service.
	 * @return self
	 */
	public function register_default_topic_components(
		?AIPS_AI_Service_Interface $ai_service = null,
		$topics_repository = null,
		$embeddings_service = null,
		$embeddings_repo = null,
		?AIPS_Embeddings_Rate_Limiter $rate_limiter = null,
		?AIPS_Content_Indexer_Service $indexer_service = null
	): self {
		$typed_topics_repo = ($topics_repository instanceof AIPS_Author_Topics_Repository) ? $topics_repository : null;
		$this->register(new AIPS_Topic_Title_Component($ai_service, $typed_topics_repo));
		$this->register(new AIPS_Topic_Prompt_Component($ai_service, $typed_topics_repo));
		$this->register(new AIPS_Topic_Keywords_Component($ai_service, $typed_topics_repo));
		$this->register(new AIPS_Topic_Embedding_Component($embeddings_service, $embeddings_repo, $rate_limiter, $indexer_service));

		return $this;
	}

	/**
	 * Register the default set of built-in Author Persona Generation Components.
	 *
	 * @param AIPS_AI_Service_Interface|null   $ai_service     Optional AI service.
	 * @param AIPS_Prompt_Builder_Authors|null $prompt_builder Optional authors prompt builder.
	 * @return self
	 */
	public function register_default_persona_components(
		?AIPS_AI_Service_Interface $ai_service = null,
		$prompt_builder = null
	): self {
		$this->register(new AIPS_Author_Persona_Component($ai_service, $prompt_builder));

		return $this;
	}
}
