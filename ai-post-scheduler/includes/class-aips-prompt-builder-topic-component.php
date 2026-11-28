<?php
/**
 * Topic Component Prompt Builder
 *
 * Assembles the single-field prompts used by the author topic generation
 * components (title, editorial prompt, keywords) when they generate or
 * regenerate one field outside the batch topic-ideation call.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Topic_Component
 */
class AIPS_Prompt_Builder_Topic_Component {

	/**
	 * Build the prompt for a single topic title.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @param array                   $parts   Optional 'current_title' and 'directive'.
	 * @return string
	 */
	public function build_title(AIPS_Generation_Context $context, array $parts = array()): string {
		$name   = (string) $context->get_name();
		$prompt = sprintf(
			'Generate a single compelling, specific blog post topic title for author "%s" in the "%s" niche. Return only the title text without quotes.',
			$name !== '' ? $name : 'Author',
			$this->niche($context)
		);

		return $this->assemble($prompt, array(
			$this->line('Current topic title to improve: %s', $parts, 'current_title'),
		), $parts);
	}

	/**
	 * Build the prompt for a topic's editorial instructions.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @param array                   $parts   Optional 'topic_title', 'current_prompt' and 'directive'.
	 * @return string
	 */
	public function build_editorial_prompt(AIPS_Generation_Context $context, array $parts = array()): string {
		$prompt = sprintf(
			'Write concise editorial instructions and angle guidance for an article in the "%s" niche.',
			$this->niche($context)
		);

		return $this->assemble($prompt, array(
			$this->line('Topic title: %s', $parts, 'topic_title'),
			$this->line('Current prompt to refine: %s', $parts, 'current_prompt'),
		), $parts);
	}

	/**
	 * Build the prompt for a topic's SEO keywords.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @param array                   $parts   Optional 'topic_title' and 'directive'.
	 * @return string
	 */
	public function build_keywords(AIPS_Generation_Context $context, array $parts = array()): string {
		$prompt = sprintf(
			'Return 3-5 comma-separated SEO keywords for a blog topic in the "%s" niche.',
			$this->niche($context)
		);

		return $this->assemble($prompt, array(
			$this->line('Topic title: %s', $parts, 'topic_title'),
		), $parts);
	}

	/**
	 * Niche label from the context.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return string
	 */
	private function niche(AIPS_Generation_Context $context): string {
		$niche = (string) $context->get_topic();

		return $niche !== '' ? $niche : 'General';
	}

	/**
	 * Format one optional context line, or '' when the part is empty.
	 *
	 * @param string $format Line format with one %s.
	 * @param array  $parts  Prompt parts.
	 * @param string $key    Part key.
	 * @return string
	 */
	private function line(string $format, array $parts, string $key): string {
		$value = isset($parts[$key]) ? trim((string) $parts[$key]) : '';

		return $value !== '' ? sprintf($format, $value) : '';
	}

	/**
	 * Join the base instruction, context lines and the user directive.
	 *
	 * @param string   $base  Base instruction.
	 * @param string[] $lines Context lines ('' entries are skipped).
	 * @param array    $parts Prompt parts (reads 'directive').
	 * @return string
	 */
	private function assemble(string $base, array $lines, array $parts): string {
		$sections = array($base);

		$context_lines = array_values(array_filter($lines, 'strlen'));
		if (!empty($context_lines)) {
			$sections[] = implode("\n", $context_lines);
		}

		$directive = isset($parts['directive']) ? trim((string) $parts['directive']) : '';
		if ($directive !== '') {
			/* translators: %s: user-supplied directive for the AI. */
			$sections[] = sprintf(__('Follow this directive: %s', 'ai-post-scheduler'), $directive);
		}

		return implode("\n\n", $sections);
	}
}
