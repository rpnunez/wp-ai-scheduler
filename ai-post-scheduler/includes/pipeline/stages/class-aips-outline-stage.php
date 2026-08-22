<?php
/**
 * Outline Generation Pipeline Stage
 *
 * Formulates structured section headings and outline layout.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Outline_Stage implements AIPS_Generation_Stage_Interface {

	public function get_id(): string {
		return 'outline';
	}

	public function get_label(): string {
		return __('Section Outline Planning', 'ai-post-scheduler');
	}

	public function get_priority(): int {
		return 30;
	}

	public function should_run(AIPS_Generation_Context $context, AIPS_Generation_Pipeline_Payload $payload): bool {
		return $payload->outline === null && (bool) $context->get_article_structure_id();
	}

	public function process(
		AIPS_Generation_Context $context,
		AIPS_Generation_Pipeline_Payload $payload,
		callable $next
	): AIPS_Generation_Pipeline_Payload {
		$structure_id = (int) $context->get_article_structure_id();

		if ($structure_id > 0 && class_exists('AIPS_Article_Structure_Manager')) {
			$container = AIPS_Container::get_instance();
			$manager = $container->has(AIPS_Article_Structure_Manager::class)
				? $container->make(AIPS_Article_Structure_Manager::class)
				: new AIPS_Article_Structure_Manager();

			$structure = $manager->get_structure($structure_id);
			if ($structure && !empty($structure->structure)) {
				$payload->outline = (string) $structure->structure;
			}
		}

		return $next($payload);
	}
}
