<?php
/**
 * Native Redirect Provider
 *
 * Redirects served by AIPS itself from the aips_redirects table (see
 * AIPS_Redirects_Service::maybe_redirect()). Always available.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Redirect_Provider_Native
 */
class AIPS_Redirect_Provider_Native implements AIPS_Redirect_Provider {

	const KEY = 'aips';

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('AI Post Scheduler (built in)', 'ai-post-scheduler');
	}

	public function is_available(): bool {
		return true;
	}

	/**
	 * Nothing to create elsewhere: the aips_redirects row is the redirect.
	 */
	public function create(string $source_path, string $target_url, int $status_code) {
		return '';
	}

	public function delete(string $provider_ref, string $source_path): bool {
		return true;
	}
}
