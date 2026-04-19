<?php
/**
 * Token budget utility.
 *
 * Centralizes prompt token estimation, buffer application, and max-token
 * clamping while allowing callers to keep feature-specific response sizing.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Token_Budget
 */
class AIPS_Token_Budget {

	/**
	 * Default estimate: 1 token ~= 4 characters.
	 */
	const DEFAULT_CHARS_PER_TOKEN = 4;

	/**
	 * Calculate a max-token budget from prompt and expected output sizing.
	 *
	 * @param string $prompt        Prompt text.
	 * @param int    $output_tokens Expected response token count.
	 * @param array  $options       Optional calculation settings.
	 * @return int
	 */
	public static function calculate($prompt, $output_tokens, $options = array()) {
		$options = wp_parse_args(
			$options,
			array(
				'chars_per_token'      => self::DEFAULT_CHARS_PER_TOKEN,
				'buffer_ratio'         => 0,
				'minimum_tokens'       => 1,
				'maximum_tokens'       => 0,
				'respect_config_limit' => false,
				'config_limit_option'  => 'aips_max_tokens_limit',
			)
		);

		$prompt_tokens = self::estimate_prompt_tokens($prompt, (int) $options['chars_per_token']);
		$calculated    = $prompt_tokens + max(0, (int) $output_tokens);

		if ((float) $options['buffer_ratio'] > 0) {
			$calculated += (int) ceil($calculated * (float) $options['buffer_ratio']);
		}

		$maximum_tokens = max(0, (int) $options['maximum_tokens']);

		if (!empty($options['respect_config_limit'])) {
			$config_limit = (int) AIPS_Config::get_instance()->get_option($options['config_limit_option']);

			if ($config_limit > 0 && (0 === $maximum_tokens || $config_limit < $maximum_tokens)) {
				$maximum_tokens = $config_limit;
			}
		}

		return self::clamp((int) $calculated, (int) $options['minimum_tokens'], $maximum_tokens);
	}

	/**
	 * Estimate prompt token count from character length.
	 *
	 * @param string $prompt          Prompt text.
	 * @param int    $chars_per_token Character-to-token ratio.
	 * @return int
	 */
	public static function estimate_prompt_tokens($prompt, $chars_per_token = self::DEFAULT_CHARS_PER_TOKEN) {
		$chars_per_token = max(1, (int) $chars_per_token);

		return (int) ceil(strlen((string) $prompt) / $chars_per_token);
	}

	/**
	 * Clamp a token count to the configured min/max bounds.
	 *
	 * @param int $tokens  Calculated token count.
	 * @param int $minimum Minimum token count.
	 * @param int $maximum Maximum token count, or 0 for uncapped.
	 * @return int
	 */
	public static function clamp($tokens, $minimum = 1, $maximum = 0) {
		$tokens  = max(0, (int) $tokens);
		$minimum = max(1, (int) $minimum);

		$tokens = max($minimum, $tokens);

		if ((int) $maximum > 0) {
			$tokens = min($tokens, (int) $maximum);
		}

		return $tokens;
	}
}