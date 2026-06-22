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
		$slug = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $name );

		return array(
			'id'              => sanitize_key( $data['id'] ?? '' ),
			'slug'            => $slug ? $slug : sanitize_key( $data['id'] ?? '' ),
			'name'            => $name,
			'type'            => sanitize_key( ! empty( $data['type'] ) ? $data['type'] : ( ! empty( $data['provider'] ) ? $data['provider'] : 'embed' ) ),
			'provider'        => sanitize_key( ! empty( $data['provider'] ) ? $data['provider'] : 'custom' ),
			'use_case'        => sanitize_textarea_field( $data['use_case'] ?? $data['description'] ?? '' ),
			'disclosure_text' => sanitize_textarea_field( $data['disclosure_text'] ?? '' ),
			'cta_text'        => sanitize_text_field( $data['cta_text'] ?? $data['cta_label'] ?? '' ),
			'cta_label'       => sanitize_text_field( $data['cta_label'] ?? $data['cta_text'] ?? '' ),
			'endpoint_url'    => esc_url_raw( $data['endpoint_url'] ?? '' ),
			'referral_url'    => esc_url_raw( $data['referral_url'] ?? $data['endpoint_url'] ?? '' ),
			'utm_campaign'    => sanitize_key( $data['utm_campaign'] ?? '' ),
			'utm_source'      => sanitize_key( $data['utm_source'] ?? '' ),
			'utm_medium'      => sanitize_key( $data['utm_medium'] ?? '' ),
			'rel_attributes'  => AIPS_Referral_Link_Builder::sanitize_rel( $data['rel_attributes'] ?? '' ),
			'is_active'       => ! empty( $data['is_active'] ),
			'created_at'      => absint( $data['created_at'] ?? 0 ),
			'updated_at'      => absint( $data['updated_at'] ?? 0 ),
		);
	}

	/**
	 * Get supported enhancement types and labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_types(): array {
		return array(
			'embed'            => __( 'Embed', 'ai-post-scheduler' ),
			'calculator'       => __( 'Calculator', 'ai-post-scheduler' ),
			'ticker'           => __( 'Ticker', 'ai-post-scheduler' ),
			'code_playground'  => __( 'Code Playground', 'ai-post-scheduler' ),
			'cta_card'         => __( 'CTA Card', 'ai-post-scheduler' ),
			'comparison_table' => __( 'Comparison Table', 'ai-post-scheduler' ),
			'shortcode'        => __( 'Shortcode', 'ai-post-scheduler' ),
		);
	}
}
