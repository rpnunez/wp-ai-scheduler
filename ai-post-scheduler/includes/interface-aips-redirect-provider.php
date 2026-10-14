<?php
/**
 * Redirect Provider Interface
 *
 * A plugin that can serve redirects AIPS creates: AIPS itself (native), or
 * Redirection, Yoast SEO Premium or Rank Math when installed. AIPS keeps its
 * own record of every redirect (AIPS_Redirects_Repository), so redirects can
 * be moved between providers.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Redirect_Provider
 */
interface AIPS_Redirect_Provider {

	/**
	 * Stable key stored with each redirect (aips, redirection, yoast, rankmath).
	 *
	 * @return string
	 */
	public function get_key(): string;

	/**
	 * Human-readable name.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Whether the provider's plugin is active and able to store redirects.
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Create a redirect in the provider.
	 *
	 * @param string $source_path Site-relative source path, e.g. /old-post/.
	 * @param string $target_url  Absolute target URL.
	 * @param int    $status_code 301, 302, 307, 308 or 410.
	 * @return string|WP_Error The provider's reference (ID or key) for later deletion.
	 */
	public function create(string $source_path, string $target_url, int $status_code);

	/**
	 * Delete a redirect from the provider.
	 *
	 * @param string $provider_ref Reference returned by create().
	 * @param string $source_path  Source path (for providers keyed by path).
	 * @return bool Whether it was deleted (true when it no longer exists).
	 */
	public function delete(string $provider_ref, string $source_path): bool;
}
