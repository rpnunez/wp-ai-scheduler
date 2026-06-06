<?php
/**
 * Generation Instructions Prompt Builder
 *
 * Injects site-wide admin-defined generation instructions into every AI
 * content, title, and excerpt prompt. The instructions are stored as a
 * WordPress option and can be enabled or disabled from the Settings page.
 *
 * When enabled, the instructions block is prepended to the prompt so that
 * the AI sees global directives before any template- or topic-specific
 * instructions.
 *
 * @package AI_Post_Scheduler
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Generation_Instructions
 *
 * Reads the generation instructions option and injects it via
 * WordPress prompt filters when the feature is enabled.
 */
class AIPS_Prompt_Builder_Generation_Instructions {

	/**
	 * Priority at which the filter callbacks run.
	 * Higher than default (10) so this prepend runs late and remains the first block.
	 */
	const FILTER_PRIORITY = 20;

	/**
	 * Whether the filter hooks have been registered in this request.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Register filter hooks.
	 *
	 * Safe to call multiple times; hooks are only registered once per request.
	 *
	 * @return void
	 */
	public function register_hooks() {
		if (self::$registered) {
			return;
		}

		add_filter('aips_content_prompt', array($this, 'inject'), self::FILTER_PRIORITY, 1);
		add_filter('aips_title_prompt',   array($this, 'inject'), self::FILTER_PRIORITY, 1);
		add_filter('aips_excerpt_prompt', array($this, 'inject'), self::FILTER_PRIORITY, 1);

		self::$registered = true;
	}

	/**
	 * Reset the registration flag.
	 *
	 * Intended for use in unit tests only.
	 *
	 * @return void
	 */
	public static function reset_registration() {
		self::$registered = false;
	}

	/**
	 * Inject the generation instructions block at the start of a prompt.
	 *
	 * Called by the aips_content_prompt, aips_title_prompt, and
	 * aips_excerpt_prompt filters. Returns the prompt unchanged when the
	 * feature is disabled or the instructions text is empty.
	 *
	 * @param string $prompt The existing prompt text.
	 * @return string Prompt with the instructions block prepended, or the
	 *               original prompt when the feature is inactive.
	 */
	public function inject($prompt) {
		$block = $this->build();

		if (empty($block)) {
			return $prompt;
		}

		return $block . $prompt;
	}

	/**
	 * Build the formatted generation instructions block.
	 *
	 * Returns an empty string when the feature is disabled or the
	 * instructions text has not been configured.
	 *
	 * @return string Formatted instructions block ending with two newlines,
	 *               or empty string.
	 */
	public function build() {
		$enabled = (bool) AIPS_Config::get_instance()->get_option('aips_generation_instructions_enabled');

		if (!$enabled) {
			return '';
		}

		$instructions = trim((string) AIPS_Config::get_instance()->get_option('aips_generation_instructions'));

		if ($instructions === '') {
			return '';
		}

		$block = "### GENERATION INSTRUCTIONS:\n" . $instructions . "\n\n";

		/**
		 * Filters the generation instructions block before it is prepended to a prompt.
		 *
		 * Return an empty string to suppress the block entirely.
		 *
		 * @since 2.7.0
		 *
		 * @param string $block        Formatted instructions block.
		 * @param string $instructions Raw instructions text.
		 */
		return apply_filters('aips_generation_instructions_block', $block, $instructions);
	}
}
