<?php
/**
 * Prompt Preview Service
 *
 * Centralizes the logic for generating prompt previews to avoid code duplication
 * between the preview endpoint and actual generation process.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Preview_Service
 *
 * Provides a unified way to preview prompts that will be sent to the AI service.
 * This ensures consistency between preview and actual generation.
 */
class AIPS_Prompt_Preview_Service {

	/**
	 * @var AIPS_Template_Processor Template variable processor.
	 */
	private $template_processor;

	/**
	 * @var AIPS_Article_Structure_Manager Article structure manager.
	 */
	private $structure_manager;

	/**
	 * @var AIPS_Prompt_Builder Prompt builder for constructing AI prompts.
	 */
	private $prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Template_Processor|null        $template_processor Template processor instance.
	 * @param AIPS_Article_Structure_Manager|null $structure_manager  Structure manager instance.
	 * @param AIPS_Prompt_Builder|null            $prompt_builder     Prompt builder instance.
	 */
	public function __construct($template_processor = null, $structure_manager = null, $prompt_builder = null) {
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
		$this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
		$this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder($this->template_processor, $this->structure_manager);
	}

	/**
	 * Generate preview of all prompts for a template configuration.
	 *
	 * @param object      $template_data Template data object with configuration.
	 * @param string|null $sample_topic  Sample topic to use for preview (default: 'Example Topic').
	 * @param object|null $voice         Optional voice object.
	 * @return array Array containing 'prompts' and 'metadata' keys.
	 */
	public function preview_prompts($template_data, $sample_topic = null, $voice = null) {
		if (empty($sample_topic)) {
			$sample_topic = 'Example Topic';
		}

		// Build content prompt
		$content_prompt = $this->prompt_builder->build_content_prompt($template_data, $sample_topic, $voice);

		// Build title prompt
		$sample_content = '[Generated article content would appear here]';
		$title_prompt = $this->prompt_builder->build_title_prompt($template_data, $sample_topic, $voice, $sample_content);

		// Build excerpt prompt (requires title and content)
		$sample_title = '[Generated title would appear here]';
		$excerpt_prompt = $this->prompt_builder->build_excerpt_prompt($sample_title, $sample_content, $voice, $sample_topic);

		// Build image prompt if enabled
		$image_prompt_processed = '';
		if (isset($template_data->generate_featured_image) && $template_data->generate_featured_image 
			&& isset($template_data->featured_image_source) && $template_data->featured_image_source === 'ai_prompt' 
			&& !empty($template_data->image_prompt)) {
			$image_prompt_processed = $this->template_processor->process($template_data->image_prompt, $sample_topic);
		}

		// Get voice name if applicable
		$voice_name = '';
		if ($voice && isset($voice->name)) {
			$voice_name = $voice->name;
		}

		// Get article structure name if applicable
		$structure_name = '';
		if (isset($template_data->article_structure_id) && $template_data->article_structure_id > 0) {
			$structure = $this->structure_manager->get_structure($template_data->article_structure_id);
			if ($structure && !is_wp_error($structure) && isset($structure['name'])) {
				$structure_name = $structure['name'];
			}
		}

		return array(
			'prompts' => array(
				'content' => $content_prompt,
				'title' => $title_prompt,
				'excerpt' => $excerpt_prompt,
				'image' => $image_prompt_processed,
			),
			'metadata' => array(
				'voice' => $voice_name,
				'article_structure' => $structure_name,
				'sample_topic' => $sample_topic,
			),
		);
	}

	/**
	 * Get voice object by ID.
	 *
	 * @param int $voice_id Voice ID.
	 * @return object|null Voice object or null if not found.
	 */
	public function get_voice($voice_id) {
		if ($voice_id <= 0) {
			return null;
		}

		$voice_service = new AIPS_Voices();
		return $voice_service->get($voice_id);
	}
}
