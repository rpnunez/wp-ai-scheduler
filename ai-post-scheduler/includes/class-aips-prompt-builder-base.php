<?php
if (!defined('ABSPATH')) {
	exit;
}

abstract class AIPS_Prompt_Builder_Base implements AIPS_Prompt_Builder_Interface {

	/**
	 * @var AIPS_Template_Processor
	 */
	private $template_processor;

	/**
	 * @var AIPS_Prompt_Builder
	 */
	private $base_builder;

	/**
	 * @var AIPS_Article_Structure_Manager
	 */
	private $structure_manager;

	/**
	 * @var AIPS_Prompt_Section_Repository
	 */
	private $section_repository;

	/**
	 * @var AIPS_Prompt_Builder_Article_Structure_Section|null
	 */
	private $article_structure_section_builder;

	/**
	 * @param AIPS_Template_Processor|null                 $template_processor Optional template processor.
	 * @param AIPS_Prompt_Builder|null                     $base_builder Optional shared base prompt builder.
	 * @param AIPS_Article_Structure_Manager|null          $structure_manager Optional structure manager.
	 * @param AIPS_Prompt_Section_Repository|null          $section_repository Optional prompt section repository.
	 * @param AIPS_Prompt_Builder_Article_Structure_Section|null $article_structure_section_builder Optional article structure section builder.
	 */
	public function __construct($template_processor = null, $base_builder = null, $structure_manager = null, $section_repository = null, $article_structure_section_builder = null) {
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
		$this->base_builder = $base_builder;
		$this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
		$this->section_repository = $section_repository;
		$this->article_structure_section_builder = $article_structure_section_builder;
	}

	/**
	 * @return AIPS_Template_Processor
	 */
	protected function get_template_processor() {
		return $this->template_processor;
	}

	/**
	 * @return AIPS_Prompt_Builder
	 */
	protected function get_base_builder() {
		if (null === $this->base_builder) {
			$this->base_builder = new AIPS_Prompt_Builder();
		}

		return $this->base_builder;
	}

	/**
	 * @return AIPS_Article_Structure_Manager
	 */
	protected function get_structure_manager() {
		return $this->structure_manager;
	}

	/**
	 * @return AIPS_Prompt_Section_Repository
	 */
	protected function get_section_repository() {
		if (null === $this->section_repository) {
			$this->section_repository = new AIPS_Prompt_Section_Repository();
		}

		return $this->section_repository;
	}

	/**
	 * @return AIPS_Prompt_Builder_Article_Structure_Section
	 */
	protected function get_article_structure_section_builder() {
		if (null === $this->article_structure_section_builder) {
			$this->article_structure_section_builder = new AIPS_Prompt_Builder_Article_Structure_Section(
				$this->template_processor,
				$this->get_base_builder(),
				$this->structure_manager,
				$this->get_section_repository()
			);
		}

		return $this->article_structure_section_builder;
	}
}
