<?php
/**
 * Content Enhancement domain object.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Enhancement
 */
class AIPS_Content_Enhancement {

	/**
	 * Build a normalized content enhancement array.
	 *
	 * @param array<string, mixed> $data Raw enhancement data.
	 * @return array<string, mixed>
	 */
	public static function from_array( array $data ): array {
		$name = sanitize_text_field( $data['name'] ?? '' );
		$slug = sanitize_title( $data['slug'] ?? $name );

		return array(
			'id'              => sanitize_key( $data['id'] ?? '' ),
			'slug'            => $slug ? $slug : sanitize_key( $data['id'] ?? '' ),
			'name'            => $name,
			'type'            => sanitize_key( $data['type'] ?? $data['provider'] ?? 'embed' ),
			'provider'        => sanitize_key( $data['provider'] ?? 'custom' ),
			'use_case'        => sanitize_textarea_field( $data['use_case'] ?? $data['description'] ?? '' ),
			'disclosure_text' => sanitize_textarea_field( $data['disclosure_text'] ?? '' ),
			'cta_text'        => sanitize_text_field( $data['cta_text'] ?? '' ),
			'endpoint_url'    => esc_url_raw( $data['endpoint_url'] ?? '' ),
			'is_active'       => ! empty( $data['is_active'] ),
			'created_at'      => absint( $data['created_at'] ?? 0 ),
			'updated_at'      => absint( $data['updated_at'] ?? 0 ),
		);
	}
}
