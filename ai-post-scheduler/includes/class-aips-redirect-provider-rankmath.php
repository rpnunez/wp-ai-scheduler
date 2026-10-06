<?php
/**
 * Rank Math Redirect Provider
 *
 * Stores redirects in Rank Math's Redirections module.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Redirect_Provider_Rankmath
 */
class AIPS_Redirect_Provider_Rankmath implements AIPS_Redirect_Provider {

	const KEY = 'rankmath';

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Rank Math Redirections', 'ai-post-scheduler');
	}

	public function is_available(): bool {
		if (!class_exists('\RankMath\Redirections\Redirection') || !class_exists('\RankMath\Redirections\DB')) {
			return false;
		}

		// Only check the module setting: the module registry is not built
		// until Rank Math has fully loaded.
		return !class_exists('\RankMath\Helper') || !method_exists('\RankMath\Helper', 'is_module_active') || \RankMath\Helper::is_module_active('redirections', false);
	}

	public function create(string $source_path, string $target_url, int $status_code) {
		$redirection = \RankMath\Redirections\Redirection::from(array(
			'sources'     => array(
				array(
					'pattern'    => ltrim($source_path, '/'),
					'comparison' => 'exact',
				),
			),
			'url_to'      => $status_code === 410 ? '' : $target_url,
			'header_code' => $status_code,
			'status'      => 'active',
		));

		$id = $redirection->save();
		if (!$id) {
			return new WP_Error('aips_rankmath_redirect_failed', __('Rank Math did not save the redirect.', 'ai-post-scheduler'));
		}

		return (string) $id;
	}

	public function delete(string $provider_ref, string $source_path): bool {
		$id = (int) $provider_ref;
		if ($id <= 0) {
			return true;
		}

		\RankMath\Redirections\DB::delete(array($id));

		return true;
	}
}
