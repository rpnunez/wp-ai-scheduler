<?php
/**
 * Post Content Prompt Builder
 *
 * Responsible for assembling AI prompts that are used exclusively for
 * post content generation. Extracted from AIPS_Prompt_Builder to keep
 * content prompt construction isolated as the prompt builder layer is
 * progressively split into focused classes.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.3
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Post_Content
 *
 * Builds the AI prompt for post content generation.
 */
class AIPS_Prompt_Builder_Post_Content {
	/** Return the core content-stage wrapper. */
	public static function get_default_prompt() {
		return "{{voice_instructions}}\n\n{{content_prompt}}";
	}

	private $sources_selection = array();

	public function get_sources_selection() {
		return $this->sources_selection;
	}

	public function set_sources_selection(array $selection) {
		$this->sources_selection = $selection;
	}


	/**
	 * @var AIPS_Template_Processor Template processor for prompt variables.
	 */
	private $template_processor;

	/**
	 * @var AIPS_Prompt_Builder_Article_Structure_Section Builder for structured section prompts.
	 */
	private $article_structure_section_builder;

	/**
	 * @var AIPS_Prompt_Builder_Diversity_Injector Diversity block builder.
	 */
	private $diversity_injector;

	/** @var AIPS_Prompt_Profile_Resolver */
	private $profile_resolver;

	/**
	 * @param AIPS_Template_Processor|null                 $template_processor             Optional template processor.
	 * @param AIPS_Prompt_Builder_Article_Structure_Section|null $article_structure_section_builder Optional section prompt builder.
	 * @param AIPS_Prompt_Builder_Diversity_Injector|null  $diversity_injector            Optional diversity injector.
	 * @param AIPS_Prompt_Profile_Resolver|null             $profile_resolver              Optional profile resolver.
	 */
	public function __construct($template_processor = null, $article_structure_section_builder = null, $diversity_injector = null, $profile_resolver = null) {
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
		$this->article_structure_section_builder = $article_structure_section_builder ?: new AIPS_Prompt_Builder_Article_Structure_Section(null, null, $this->template_processor);
		$this->diversity_injector = $diversity_injector ?: new AIPS_Prompt_Builder_Diversity_Injector();
		$this->profile_resolver = $profile_resolver ?: AIPS_Prompt_Profile_Resolver::instance();
	}

	/**
	 * Build the complete content prompt based on context.
	 *
	 * Supports both legacy template-based and generation-context-based flows.
	 *
	 * @param object|AIPS_Generation_Context $template_or_context Template object (legacy) or Generation Context.
	 * @param string|null                    $topic The topic for the post in legacy flows.
	 * @param object|null                    $voice Optional voice object in legacy flows.
	 * @return string
	 */
	public function build($template_or_context, $topic = null, $voice = null) {
		$this->sources_selection = array();
		if ($template_or_context instanceof AIPS_Generation_Context) {
			return $this->build_from_context($template_or_context);
		}

		return $this->build_from_template($template_or_context, $topic, $voice);
	}

	/**
	 * Build content prompt from a generation context.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return string
	 */
	private function build_from_context($context) {
		do_action('aips_before_build_content_prompt', $context, null);

		$user_instructions = $context->get_content_prompt();
		$processed_prompt = $user_instructions;
		$article_structure_id = $context->get_article_structure_id();
		$topic = $context->get_topic();
		$used_structured_prompt = false;

		if ($article_structure_id && $topic) {
			$structured_prompt = $this->article_structure_section_builder->build($article_structure_id, $topic);

			if (!is_wp_error($structured_prompt)) {
				$processed_prompt = $structured_prompt;
				$used_structured_prompt = true;
			}
		}

		if (!$used_structured_prompt) {
			// Always process template variables, even when topic is empty.
			// This prevents raw placeholders like {{topic}} from leaking into prompts.
			$processed_prompt = $this->template_processor->process($processed_prompt, $topic);
		}

		$voice_instructions = '';
		if ($context->get_type() === 'template' && $context->get_voice_id()) {
			$voice = $context->get_voice();
			if ($voice && !empty($voice->content_instructions)) {
				$voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
			}
		}

		$diversity_block = $this->build_diversity_block($context);
		$post_images_block = $this->build_post_images_block($context);
		$related_context = $this->get_related_context($topic);
		$stage_template = $this->profile_resolver->get_stage_prompt('content_prompt', $context);
		$placeholders = array(
			'voice_instructions' => $voice_instructions,
			'user_instructions'  => $this->template_processor->process($user_instructions, $topic),
			'content_prompt'      => $processed_prompt,
			'structured_content'  => $processed_prompt,
			'sections'            => $processed_prompt,
			'topic'               => $topic,
			'internal_links'      => $related_context,
			'diversity_blocks'    => $diversity_block,
			'related_context'     => $related_context,
			'post_images'         => $post_images_block,
		);
		$mandatory = array(
			'content_prompt'   => $processed_prompt,
			'diversity_blocks' => $diversity_block,
			'related_context'  => $related_context,
			'post_images'      => $post_images_block,
		);

		$final_prompt = $this->profile_resolver->interpolate($stage_template, $placeholders, $mandatory);
		return apply_filters('aips_content_prompt', $final_prompt, $context, $topic, $this);
	}

	/**
	 * Build content prompt from a legacy template object.
	 *
	 * @param object      $template Template object.
	 * @param string|null $topic Topic string.
	 * @param object|null $voice Voice object.
	 * @return string
	 */
	private function build_from_template($template, $topic = null, $voice = null) {
		do_action('aips_before_build_content_prompt', $template, $topic);
		$user_instructions = $this->template_processor->process($template->prompt_template, $topic);
		$processed_prompt = $user_instructions;
		$voice_instructions = '';
		if ($voice && !empty($voice->content_instructions)) {
			$voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
		}

		$diversity_block = $this->build_diversity_block($template);
		$post_images_block = $this->build_post_images_block($template);
		$related_context = $this->get_related_context($topic);
		$stage_template = $this->profile_resolver->get_stage_prompt('content_prompt', $template);
		$placeholders = array(
			'voice_instructions' => $voice_instructions,
			'user_instructions'  => $user_instructions,
			'content_prompt'      => $processed_prompt,
			'structured_content'  => $processed_prompt,
			'sections'            => $processed_prompt,
			'topic'               => (string) $topic,
			'internal_links'      => $related_context,
			'diversity_blocks'    => $diversity_block,
			'related_context'     => $related_context,
			'post_images'         => $post_images_block,
		);
		$mandatory = array(
			'content_prompt'   => $processed_prompt,
			'diversity_blocks' => $diversity_block,
			'related_context'  => $related_context,
			'post_images'      => $post_images_block,
		);

		$final_prompt = $this->profile_resolver->interpolate($stage_template, $placeholders, $mandatory);
		return apply_filters('aips_content_prompt', $final_prompt, $template, $topic, $this);
	}

	/** Build all diversity instructions for a content prompt. */
	private function build_diversity_block($subject) {
		$blocks = array(
			$this->diversity_injector->build_avoid_titles_block($subject),
			$this->diversity_injector->build_content_format_block($subject),
			$this->diversity_injector->build_post_slice_block($subject),
			$this->diversity_injector->build_uniqueness_seed_line_block($subject),
		);

		return implode("\n\n", array_filter($blocks));
	}

	/** Build related-post context used by both profile tags and mandatory fallback. */
	private function get_related_context($topic) {
		if (empty($topic) || !AIPS_Config::get_instance()->get_option('aips_generation_inject_related_context', true)) {
			return '';
		}

		$container = AIPS_Container::get_instance();
		if (!$container->has(AIPS_Related_Posts_Service::class)) {
			return '';
		}

		$related = $container->make(AIPS_Related_Posts_Service::class)->get_related_posts_for_topic($topic, 3, 0.55);
		if (empty($related)) {
			return '';
		}

		$block = "Context & Related Published Articles (you may naturally reference or link to these where relevant):\n";
		foreach ($related as $related_post) {
			$block .= "- \"{$related_post['title']}\" (URL: {$related_post['url']})\n";
		}
		return $block;
	}

	/**
	 * Inject semantically related published articles into prompt context when enabled.
	 *
	 * @param string      $prompt Current assembled prompt.
	 * @param string|null $topic  Target topic.
	 * @return string
	 */
	private function inject_related_context($prompt, $topic) {
		if (empty($topic) || !AIPS_Config::get_instance()->get_option('aips_generation_inject_related_context', true)) {
			return $prompt;
		}

		$container = AIPS_Container::get_instance();
		if (!$container->has(AIPS_Related_Posts_Service::class)) {
			return $prompt;
		}

		$related_service = $container->make(AIPS_Related_Posts_Service::class);
		$related = $related_service->get_related_posts_for_topic($topic, 3, 0.55);

		if (empty($related)) {
			return $prompt;
		}

		$rel_context = "Context & Related Published Articles (you may naturally reference or link to these where relevant):\n";
		foreach ($related as $rel_post) {
			$rel_context .= "- \"{$rel_post['title']}\" (URL: {$rel_post['url']})\n";
		}

		return $prompt . "\n\n" . $rel_context;
	}

	/**
	 * Build post content images instructions block.
	 *
	 * @param object|AIPS_Generation_Context $template_or_context
	 * @return string
	 */
	private function build_post_images_block($template_or_context) {
		$images = array();
		if ($template_or_context instanceof AIPS_Generation_Context) {
			if ($template_or_context->has_content_images()) {
				$images = $template_or_context->get_content_images();
			}
		} elseif (is_object($template_or_context)) {
			if (!empty($template_or_context->post_images) && is_array($template_or_context->post_images)) {
				$images = $template_or_context->post_images;
			} elseif (!empty($template_or_context->id)) {
				$repo = new AIPS_Post_Images_Repository();
				$images = $repo->get_content_images_for_entity('template', (int) $template_or_context->id);
			}
		}

		$images = AIPS_Post_Images_Service::limited_content_images($images);

		if (empty($images)) {
			return '';
		}

		$lines = array(
			'Image Placement Instructions:',
			'You must include the following image placeholders on their own line between paragraphs or sections in the article body:'
		);

		foreach ($images as $img) {
			$img_order = (int) $img->placeholder_index;
			$placement = !empty($img->placement) ? $img->placement : 'auto';
			$desc = "Placeholder: [post_image index=\"{$img_order}\"]";
			if ($placement !== 'auto' && preg_match('/^after_section_(\d+)$/', $placement, $m)) {
				$desc .= " (place immediately after Section {$m[1]} / Heading {$m[1]})";
			} else {
				$desc .= " (place naturally where most relevant within the article)";
			}
			$lines[] = "- {$desc}";
		}

		$lines[] = 'Do not wrap these placeholders in code blocks, quotes, or markdown formatting. Place each on its own separate line.';

		return implode("\n", $lines);
	}
}
