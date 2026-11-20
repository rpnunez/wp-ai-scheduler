<?php
/**
 * Component Regeneration Coordinator
 *
 * Unified entry point for single-component regeneration across strategies.
 * Resolves the target component from AIPS_Generation_Component_Registry,
 * handles conversation transcript rehydration (persisted or synthetic),
 * attaches the existing history container, and delegates execution to the
 * component itself so initial generation and regeneration share one source
 * of truth.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Component_Regeneration_Coordinator
 */
class AIPS_Component_Regeneration_Coordinator {

	/**
	 * @var AIPS_Generation_Component_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_Generator
	 */
	private $generator;

	/**
	 * @var AIPS_History_Repository_Interface
	 */
	private $history_repository;

	/**
	 * @var AIPS_Prompt_Builder_Post_Content
	 */
	private $post_content_prompt_builder;

	/**
	 * @var AIPS_Prompt_Builder_Post_Title
	 */
	private $post_title_prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generation_Component_Registry|null $registry                    Optional component registry.
	 * @param AIPS_Generator|null                     $generator                   Optional generator instance.
	 * @param AIPS_History_Repository_Interface|null  $history_repository          Optional history repository.
	 * @param AIPS_Prompt_Builder_Post_Content|null   $post_content_prompt_builder Optional content prompt builder.
	 * @param AIPS_Prompt_Builder_Post_Title|null     $post_title_prompt_builder   Optional title prompt builder.
	 */
	public function __construct(
		?AIPS_Generation_Component_Registry $registry = null,
		?AIPS_Generator $generator = null,
		?AIPS_History_Repository_Interface $history_repository = null,
		?AIPS_Prompt_Builder_Post_Content $post_content_prompt_builder = null,
		?AIPS_Prompt_Builder_Post_Title $post_title_prompt_builder = null
	) {
		$this->generator          = $generator ?: new AIPS_Generator();
		$this->history_repository = $history_repository ?: new AIPS_History_Repository();

		$template_processor = new AIPS_Template_Processor();
		$structure_manager  = new AIPS_Article_Structure_Manager();

		$this->post_content_prompt_builder = $post_content_prompt_builder ?: new AIPS_Prompt_Builder_Post_Content(
			$template_processor,
			new AIPS_Prompt_Builder_Article_Structure_Section($structure_manager, null, $template_processor)
		);

		if ($post_title_prompt_builder !== null) {
			$this->post_title_prompt_builder = $post_title_prompt_builder;
		} else {
			$prompt_builder                  = new AIPS_Prompt_Builder($template_processor, $structure_manager);
			$this->post_title_prompt_builder = $prompt_builder->get_post_title_builder();
		}

		if ($registry !== null) {
			$this->registry = $registry;
		} else {
			$this->registry = new AIPS_Generation_Component_Registry();
			$this->registry->register_default_post_components($this->generator);
		}
	}

	/**
	 * Return the component registry.
	 *
	 * @return AIPS_Generation_Component_Registry
	 */
	public function get_registry(): AIPS_Generation_Component_Registry {
		return $this->registry;
	}

	/**
	 * Regenerate a single component and return a typed AIPS_Generation_Result.
	 *
	 * @param string                  $strategy_key  Strategy key (e.g. 'posts').
	 * @param string                  $component_key Component key or alias (e.g. 'post_title' or 'title').
	 * @param int|string              $target_id     Target entity ID (e.g. post ID).
	 * @param AIPS_Generation_Context $context       Original generation context.
	 * @param string                  $directive     Optional custom AI Edit directive.
	 * @param array                   $options       Optional runtime options.
	 * @return AIPS_Generation_Result
	 */
	public function regenerate(
		string $strategy_key,
		string $component_key,
		$target_id,
		AIPS_Generation_Context $context,
		string $directive = '',
		array $options = array()
	): AIPS_Generation_Result {
		$start      = microtime(true);
		$canonical  = AIPS_Generation_Component_Registry::normalize_key($component_key, $strategy_key);
		$history_id = isset($options['history_id']) ? absint($options['history_id']) : 0;
		$raw        = $this->regenerate_raw($strategy_key, $component_key, $target_id, $context, $directive, $options);
		$elapsed    = microtime(true) - $start;

		if (is_wp_error($raw)) {
			return AIPS_Generation_Result::failure(
				array($raw->get_error_message()),
				$elapsed,
				$history_id,
				array($canonical => false)
			);
		}

		return AIPS_Generation_Result::from_data(
			$raw,
			AIPS_Generation_Result::STATUS_COMPLETED,
			array($canonical => true),
			array(),
			$history_id,
			$elapsed,
			is_numeric($target_id) ? absint($target_id) : null
		);
	}

	/**
	 * Regenerate a single component and return its raw value or WP_Error.
	 *
	 * Used by AIPS_Component_Regeneration_Service to maintain exact backward-compatible
	 * return shapes for AI Edit controllers and existing tests.
	 *
	 * @param string                  $strategy_key  Strategy key (e.g. 'posts').
	 * @param string                  $component_key Component key or alias.
	 * @param int|string              $target_id     Target entity ID.
	 * @param AIPS_Generation_Context $context       Original generation context.
	 * @param string                  $directive     Optional custom AI Edit directive.
	 * @param array                   $options       Optional runtime options.
	 * @return mixed Raw regenerated value or WP_Error.
	 */
	public function regenerate_raw(
		string $strategy_key,
		string $component_key,
		$target_id,
		AIPS_Generation_Context $context,
		string $directive = '',
		array $options = array()
	) {
		$component = $this->registry->get($component_key, $strategy_key);
		if (!$component) {
			/* translators: %s: Component key identifier */
			return new WP_Error(
				'unknown_component',
				sprintf(__('Unknown generation component: %s', 'ai-post-scheduler'), sanitize_key($component_key))
			);
		}

		$post_id    = is_numeric($target_id) ? absint($target_id) : 0;
		$history_id = isset($options['history_id']) ? absint($options['history_id']) : 0;

		// Attach history container when provided or resolvable.
		if (isset($options['history_container']) && $options['history_container'] instanceof AIPS_History_Container) {
			$this->generator->set_history_container($options['history_container']);
		}

		// Determine conversational session when applicable.
		$session = null;
		if (array_key_exists('session', $options)) {
			$session = ($options['session'] instanceof AIPS_AI_Conversation) ? $options['session'] : null;
		} elseif ($strategy_key === 'posts' && $post_id > 0) {
			$canonical = $component->get_key();
			if ($canonical === 'post_title') {
				$session = $this->build_conversation($context, $post_id, false);
			} elseif ($canonical === 'post_slug') {
				$session = $this->build_conversation($context, $post_id, true);
			} elseif ($canonical === 'post_excerpt') {
				$body_override  = (isset($options['current_content']) && is_string($options['current_content']) && '' !== trim($options['current_content'])) ? $options['current_content'] : null;
				$title_override = (isset($options['current_title']) && is_string($options['current_title']) && '' !== trim($options['current_title'])) ? $options['current_title'] : null;
				$session        = $this->build_conversation($context, $post_id, true, $body_override, $title_override);
			}
		}

		return $component->regenerate($target_id, $context, $session, $directive, $options);
	}

	/**
	 * Rebuild or rehydrate the original generation conversation for a post.
	 *
	 * Prefers the persisted conversation transcript from wp_aips_history_log
	 * (PR #2409) and falls back to synthetic reconstruction when absent.
	 *
	 * @param AIPS_Generation_Context $generation_context Original generation context.
	 * @param int                     $post_id            Post being regenerated.
	 * @param bool                    $include_title      Whether to append the title exchange.
	 * @param string|null             $body_override      Article body override.
	 * @param string|null             $title_override     Title override.
	 * @return AIPS_AI_Conversation|null
	 */
	public function build_conversation(
		AIPS_Generation_Context $generation_context,
		int $post_id,
		bool $include_title = false,
		?string $body_override = null,
		?string $title_override = null
	): ?AIPS_AI_Conversation {
		if (!$post_id || !$this->generator->supports_conversation()) {
			return null;
		}

		$body = is_string($body_override) ? $body_override : (string) get_post_field('post_content', $post_id);
		if (trim($body) === '') {
			return null;
		}

		$stored_transcript = $this->history_repository->get_conversation_transcript_for_post($post_id);

		if (!empty($stored_transcript) && is_array($stored_transcript) && count($stored_transcript) >= 2) {
			$initial_prompt = isset($stored_transcript[0]['text']) ? $stored_transcript[0]['text'] : '';
			if ($initial_prompt !== '') {
				$conversation = new AIPS_AI_Conversation();
				if ($conversation->add_exchange($initial_prompt, $body)) {
					if ($include_title) {
						$title = is_string($title_override) ? $title_override : (string) get_the_title($post_id);
						if (trim($title) !== '') {
							$is_discrete_title_turn = isset($stored_transcript[2], $stored_transcript[3])
								&& stripos($stored_transcript[2]['text'], 'Now generate a title') !== false
								&& strpos(trim($stored_transcript[3]['text']), '{') !== 0;

							if ($is_discrete_title_turn && $title_override === null) {
								$conversation->add_exchange($stored_transcript[2]['text'], $stored_transcript[3]['text']);
							} else {
								$conversation->add_exchange(
									$this->post_title_prompt_builder->build_followup($generation_context),
									$title
								);
							}
						}
					}
					return $conversation;
				}
			}
		}

		$conversation = new AIPS_AI_Conversation();
		if (!$conversation->add_exchange($this->post_content_prompt_builder->build($generation_context), $body)) {
			return null;
		}

		if ($include_title) {
			$title = is_string($title_override) ? $title_override : (string) get_the_title($post_id);
			if (trim($title) !== '') {
				$conversation->add_exchange(
					$this->post_title_prompt_builder->build_followup($generation_context),
					$title
				);
			}
		}

		return $conversation;
	}
}
