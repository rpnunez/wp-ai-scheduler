<?php
/**
 * Yoast SEO Premium Redirect Provider
 *
 * Stores redirects with Yoast SEO Premium's redirect manager (plain format).
 * Yoast keys redirects by their origin, so the origin is the reference.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Redirect_Provider_Yoast
 */
class AIPS_Redirect_Provider_Yoast implements AIPS_Redirect_Provider {

	const KEY = 'yoast';

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Yoast SEO Premium Redirects', 'ai-post-scheduler');
	}

	public function is_available(): bool {
		return class_exists('WPSEO_Redirect_Manager') && class_exists('WPSEO_Redirect');
	}

	public function create(string $source_path, string $target_url, int $status_code) {
		$origin  = ltrim($source_path, '/');
		$manager = new WPSEO_Redirect_Manager('plain');
		$created = $manager->create_redirect(new WPSEO_Redirect($origin, $status_code === 410 ? '' : $target_url, $status_code, 'plain'));

		if (!$created) {
			return new WP_Error('aips_yoast_redirect_failed', __('Yoast SEO Premium did not save the redirect (it may already have one for this URL).', 'ai-post-scheduler'));
		}

		return $origin;
	}

	public function delete(string $provider_ref, string $source_path): bool {
		$origin = $provider_ref !== '' ? $provider_ref : ltrim($source_path, '/');
		if (!$this->is_available()) {
			return false;
		}

		$manager = new WPSEO_Redirect_Manager('plain');
		$manager->delete_redirects(array(new WPSEO_Redirect($origin)));

		return true;
	}
}
