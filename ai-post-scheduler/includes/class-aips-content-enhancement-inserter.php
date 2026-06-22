<?php
/**
 * Content Enhancement Inserter
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Enhancement_Inserter
 */
class AIPS_Content_Enhancement_Inserter {

	/**
	 * @var AIPS_Content_Enhancement_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Content_Enhancement_Repository|null $repository Optional repository override.
	 * @param AIPS_Content_Enhancement_Renderer|null     $renderer   Deprecated renderer override retained for backwards compatibility.
	 */
	public function __construct( ?AIPS_Content_Enhancement_Repository $repository = null, ?AIPS_Content_Enhancement_Renderer $renderer = null ) {
		unset( $renderer );
		$this->repository = $repository ?: new AIPS_Content_Enhancement_Repository();
	}

	/**
	 * Replace generated placeholders with the frontend shortcode for known slugs.
	 *
	 * This keeps generated post content safe and lets request-aware rendering happen
	 * later in AIPS_Content_Enhancement_Renderer, where feeds, AMP, and provider
	 * allowlists can be evaluated for the current response.
	 *
	 * @param string $content Generated post content.
	 * @return string
	 */
	public function replace_placeholders( string $content ): string {
		$replaced = preg_replace_callback( '/\{\{aips_enhancement:([a-z0-9_-]+)\}\}/i', function( $matches ) {
			$slug        = sanitize_title( $matches[1] );
			$enhancement = $this->repository->find_by_slug( $slug );

			if ( ! $enhancement ) {
				return $matches[0];
			}

			return '[aips_ce_tool slug="' . esc_attr( $slug ) . '"]';
		}, $content );

		return is_string( $replaced ) ? $replaced : $content;
	}
}
