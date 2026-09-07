<?php
/**
 * Prompt Builder Section Base Class
 *
 * Holds the collaborator wiring that the prompt-builder section classes were
 * each duplicating, and resolves those collaborators through AIPS_Container
 * with a `new` fallback.
 *
 * Resolution is lazy: a section that never touches a collaborator never pays
 * to construct it. This matters for AIPS_Prompt_Builder_Diversity_Injector,
 * whose own constructor instantiates two repositories -- sections such as
 * AIPS_Prompt_Builder_Authors and AIPS_Prompt_Builder_Taxonomy inherit the
 * accessor without ever triggering that cost.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.5
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Abstract Class AIPS_Prompt_Builder_Section_Base
 *
 * Shared dependency resolution for prompt-builder sections.
 */
abstract class AIPS_Prompt_Builder_Section_Base implements AIPS_Prompt_Builder_Section {

	/**
	 * Injected or lazily resolved template processor.
	 *
	 * @var AIPS_Template_Processor|null
	 */
	private $template_processor;

	/**
	 * Injected or lazily resolved diversity injector.
	 *
	 * @var AIPS_Prompt_Builder_Diversity_Injector|null
	 */
	private $diversity_injector;

	/**
	 * Constructor.
	 *
	 * Both collaborators stay null until first use unless explicitly injected.
	 *
	 * @param AIPS_Template_Processor|null                $template_processor Optional template processor.
	 * @param AIPS_Prompt_Builder_Diversity_Injector|null $diversity_injector Optional diversity injector.
	 */
	public function __construct($template_processor = null, $diversity_injector = null) {
		$this->template_processor = $template_processor;
		$this->diversity_injector = $diversity_injector;
	}

	/**
	 * Get the template processor, resolving it on first use.
	 *
	 * @return AIPS_Template_Processor
	 */
	protected function get_template_processor() {
		if ($this->template_processor === null) {
			$this->template_processor = self::resolve_collaborator('AIPS_Template_Processor');
		}

		return $this->template_processor;
	}

	/**
	 * Get the diversity injector, resolving it on first use.
	 *
	 * @return AIPS_Prompt_Builder_Diversity_Injector
	 */
	protected function get_diversity_injector() {
		if ($this->diversity_injector === null) {
			$this->diversity_injector = self::resolve_collaborator('AIPS_Prompt_Builder_Diversity_Injector');
		}

		return $this->diversity_injector;
	}

	/**
	 * Resolve a collaborator from the container, falling back to direct construction.
	 *
	 * @param string $class_name Collaborator class name.
	 * @return object
	 */
	private static function resolve_collaborator($class_name) {
		if (class_exists('AIPS_Container')) {
			return AIPS_Container::get_instance()->makeIfExists($class_name, $class_name);
		}

		return new $class_name();
	}
}
