<?php
/**
 * Content Component Fingerprint Service
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Content_Component_Fingerprint_Service {

	/**
	 * Generate an idempotency fingerprint for an injected block.
	 *
	 * @param int    $component_id Component ID.
	 * @param string $placement Placement key.
	 * @param string $rendered_content Rendered HTML.
	 * @return string
	 */
	public function generate( $component_id, $placement, $rendered_content ) {
		return hash(
			'sha256',
			wp_json_encode(
				array(
					'component_id' => absint( $component_id ),
					'placement'    => sanitize_key( str_replace( ':', '_', (string) $placement ) ),
					'content'      => (string) $rendered_content,
				)
			)
		);
	}
}
