<?php
/**
 * Title Post Component
 *
 * Owns initial post title generation, combined metadata turn participation,
 * safe fallback title synthesis, and AI Edit title regeneration.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Title_Post_Component
 */
class AIPS_Title_Post_Component extends AIPS_Abstract_Post_Component {

	/**
	 * @var AIPS_Prompt_Builder_Post_Title|null
	 */
	private $title_prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generator|null                 $generator            Optional generator instance.
	 * @param AIPS_Prompt_Builder_Post_Title|null $title_prompt_builder Optional title prompt builder.
	 */
	public function __construct(
		?AIPS_Generator $generator = null,
		?AIPS_Prompt_Builder_Post_Title $title_prompt_builder = null
	) {
		parent::__construct($generator);
		$this->title_prompt_builder = $title_prompt_builder;
	}

	/**
	 * Resolve the title prompt builder lazily.
	 *
	 * @return AIPS_Prompt_Builder_Post_Title
	 */
	private function get_title_prompt_builder(): AIPS_Prompt_Builder_Post_Title {
		if ($this->title_prompt_builder === null) {
			$prompt_builder             = new AIPS_Prompt_Builder(new AIPS_Template_Processor(), new AIPS_Article_Structure_Manager());
			$this->title_prompt_builder = $prompt_builder->get_post_title_builder();
		}

		return $this->title_prompt_builder;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'post_title';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Title', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stage(): string {
		return self::STAGE_METADATA;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function participates_in_metadata_turn(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_metadata_turn_schema(): array {
		return array(
			'title' => array(
				'type'        => 'string',
				'description' => 'The post title, without surrounding quotes or Markdown formatting.',
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		$builder = $this->get_title_prompt_builder();
		if ($session !== null) {
			return $builder->build_followup($context);
		}

		return $builder->build($context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		$generator    = $this->get_generator();
		$content      = isset($options['content']) ? (string) $options['content'] : '';
		$ai_variables = isset($options['ai_variables']) && is_array($options['ai_variables']) ? $options['ai_variables'] : array();
		$ai_options   = $this->extract_provider_options($options);

		$previous_conversation = null;
		if ($session !== null) {
			$generator->set_conversation($session);
		}

		$result = $generator->generate_title_for_context($context, $content, $ai_variables, $ai_options);

		if ($session !== null) {
			$generator->set_conversation($previous_conversation);
		}

		if (is_wp_error($result)) {
			return $result;
		}

		return trim((string) $result);
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		$generator = $this->get_generator();
		$post_id   = absint($target_id);

		$ai_options = $this->merge_directive_option($this->extract_provider_options($options), $directive);

		if ($session !== null) {
			$generator->set_conversation($session);
			$result = $generator->generate_title_for_context($context, '', array(), $ai_options);
			$generator->set_conversation(null);
		} else {
			$post_content = isset($options['current_content']) && is_string($options['current_content']) && '' !== trim($options['current_content'])
				? $options['current_content']
				: ($post_id ? (string) get_post_field('post_content', $post_id) : '');
			$result = $generator->generate_title_for_context($context, $post_content, array(), $ai_options);
		}

		if (is_wp_error($result)) {
			return $result;
		}

		return trim((string) $result);
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply_to_post(int $post_id, $value): bool {
		if ($post_id <= 0) {
			return false;
		}

		$title = $this->sanitize_value($value);
		if ($title === '') {
			return false;
		}

		$updated = wp_update_post(array(
			'ID'         => $post_id,
			'post_title' => $title,
		), true);

		return !is_wp_error($updated) && (int) $updated === $post_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		$post_id = absint($target_id);
		if ($post_id <= 0) {
			return false;
		}

		$title = (string) get_the_title($post_id);
		return '' !== trim(wp_strip_all_tags($title));
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		$clean = trim(sanitize_text_field((string) $value));
		return preg_replace('/^["\']|["\']$/', '', $clean);
	}
}
