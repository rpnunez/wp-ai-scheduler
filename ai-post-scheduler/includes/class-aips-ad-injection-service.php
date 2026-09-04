<?php
/**
 * Ad Injection Service
 *
 * Intelligently parses HTML post content and injects ad slots and sponsor units
 * based on paragraph pacing, content density, and flow safeguards.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Ad_Injection_Service {

	/**
	 * Default minimum word count required to inject in-article ads.
	 */
	const DEFAULT_MIN_WORDS = 250;

	/**
	 * Inject active ad slots and optional sponsor disclosure into post content.
	 *
	 * @param string      $content            Post HTML content.
	 * @param object[]    $slots              Array of ad slot objects from repository.
	 * @param int         $post_id            Current WordPress post ID.
	 * @param object|null $sponsor_campaign   Optional active sponsor campaign object.
	 * @param int[]       $suppressed_slot_ids Optional array of slot IDs to exclude.
	 * @return string Modified content with injected ad units.
	 */
	public function inject_runtime_ads( $content, array $slots, $post_id = 0, $sponsor_campaign = null, array $suppressed_slot_ids = array() ) {
		if ( empty( $content ) ) {
			return $content;
		}

		$post_id = absint( $post_id );

		// 1. Inject Sponsor FTC Disclosure if campaign exists
		if ( $sponsor_campaign ) {
			$disclosure_html = $this->render_sponsor_disclosure( $sponsor_campaign );
			$content         = $disclosure_html . "\n" . $content;
		}

		if ( empty( $slots ) ) {
			return $content;
		}

		// Calculate approximate word count
		$word_count = str_word_count( wp_strip_all_tags( $content ) );

		// Filter out suppressed or ineligible slots
		$eligible_slots = array();
		foreach ( $slots as $slot ) {
			$slot_id = (int) $slot->id;
			if ( in_array( $slot_id, $suppressed_slot_ids, true ) ) {
				continue;
			}
			$min_words = ! empty( $slot->min_word_count ) ? (int) $slot->min_word_count : self::DEFAULT_MIN_WORDS;
			if ( $word_count < $min_words ) {
				continue;
			}
			$eligible_slots[] = $slot;
		}

		if ( empty( $eligible_slots ) ) {
			return $content;
		}

		// Group slots by position
		$after_p_slots = array();
		$mid_slots     = array();
		$end_slots     = array();

		foreach ( $eligible_slots as $slot ) {
			switch ( $slot->position ) {
				case 'after_paragraph':
					$after_p_slots[] = $slot;
					break;
				case 'mid_content':
					$mid_slots[] = $slot;
					break;
				case 'end_of_post':
					$end_slots[] = $slot;
					break;
			}
		}

		// Split content into top-level paragraphs / blocks
		$content = $this->inject_paragraph_and_mid_ads( $content, $after_p_slots, $mid_slots, $post_id, $sponsor_campaign );

		// Inject end of post ads
		if ( ! empty( $end_slots ) ) {
			$end_html = '';
			foreach ( $end_slots as $slot ) {
				$end_html .= "\n" . $this->render_ad_slot( $slot, $post_id, $sponsor_campaign );
			}
			$content .= $end_html;
		}

		return $content;
	}

	/**
	 * Inject after_paragraph and mid_content ads using regex paragraph boundaries.
	 *
	 * @param string   $content
	 * @param object[] $after_p_slots
	 * @param object[] $mid_slots
	 * @param int      $post_id
	 * @param object|null $sponsor_campaign
	 * @return string
	 */
	private function inject_paragraph_and_mid_ads( $content, array $after_p_slots, array $mid_slots, $post_id, $sponsor_campaign ) {
		// Match closing </p> tags
		$parts = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts || count( $parts ) < 2 ) {
			// Fallback: If no </p> tags, append ads at end
			$all_inline = array_merge( $after_p_slots, $mid_slots );
			foreach ( $all_inline as $slot ) {
				$content .= "\n" . $this->render_ad_slot( $slot, $post_id, $sponsor_campaign );
			}
			return $content;
		}

		// Reconstruct paragraphs array
		$paragraphs = array();
		for ( $i = 0; $i < count( $parts ); $i += 2 ) {
			$p_content = $parts[ $i ];
			$closing   = $parts[ $i + 1 ] ?? '';
			if ( ! empty( $p_content ) || ! empty( $closing ) ) {
				$paragraphs[] = $p_content . $closing;
			}
		}

		$total_p = count( $paragraphs );
		$mid_p   = max( 1, (int) floor( $total_p / 2 ) );

		// Map insertions: paragraph_index (1-based) => rendered ad html
		$insertions = array();

		// Schedule after_paragraph slots
		foreach ( $after_p_slots as $slot ) {
			$target_p = max( 1, (int) $slot->paragraph_offset );
			if ( $target_p <= $total_p ) {
				$insertions[ $target_p ][] = $this->render_ad_slot( $slot, $post_id, $sponsor_campaign );
			}
		}

		// Schedule mid_content slots
		foreach ( $mid_slots as $slot ) {
			$insertions[ $mid_p ][] = $this->render_ad_slot( $slot, $post_id, $sponsor_campaign );
		}

		// Reassemble content with inserted ads
		$output = '';
		$current_p = 1;

		foreach ( $paragraphs as $paragraph ) {
			$output .= $paragraph;

			if ( ! empty( $insertions[ $current_p ] ) ) {
				foreach ( $insertions[ $current_p ] as $ad_html ) {
					$output .= "\n" . $ad_html;
				}
			}

			$current_p++;
		}

		return $output;
	}

	/**
	 * Render an ad slot markup.
	 *
	 * @param object      $slot
	 * @param int         $post_id
	 * @param object|null $sponsor_campaign
	 * @return string
	 */
	public function render_ad_slot( $slot, $post_id = 0, $sponsor_campaign = null ) {
		$slot_id     = (int) $slot->id;
		$campaign_id = $sponsor_campaign ? (int) $sponsor_campaign->id : 0;
		$device      = ! empty( $slot->device_targeting ) ? $slot->device_targeting : 'all';
		$device_cls  = ( 'all' !== $device ) ? 'aips-device-' . esc_attr( $device ) : '';
		$custom_cls  = ! empty( $slot->css_classes ) ? esc_attr( $slot->css_classes ) : '';

		$inner_code = '';

		if ( 'sponsor_card' === $slot->slot_type && $sponsor_campaign ) {
			$inner_code = $this->render_sponsor_card( $sponsor_campaign );
		} elseif ( 'image_banner' === $slot->slot_type && ! empty( $slot->code ) ) {
			$inner_code = $this->render_image_banner( $slot );
		} else {
			// custom_html or adsense
			$inner_code = (string) $slot->code;
		}

		$output  = '<div class="aips-ad-container aips-ad-slot-' . $slot_id . ' ' . $device_cls . ' ' . $custom_cls . '"';
		$output .= ' data-slot-id="' . $slot_id . '"';
		$output .= ' data-post-id="' . absint( $post_id ) . '"';
		$output .= ' data-campaign-id="' . $campaign_id . '">';
		$output .= '<span class="aips-ad-label">' . esc_html__( 'Advertisement', 'ai-post-scheduler' ) . '</span>';
		$output .= '<div class="aips-ad-inner">' . $inner_code . '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render FTC disclosure notice.
	 *
	 * @param object $campaign
	 * @return string
	 */
	public function render_sponsor_disclosure( $campaign ) {
		$text = ! empty( $campaign->disclosure_text ) 
			? $campaign->disclosure_text 
			: AIPS_Config::get_instance()->get_option( 'aips_default_ftc_disclosure' );

		$output  = '<div class="aips-sponsor-disclosure" data-campaign-id="' . absint( $campaign->id ) . '">';
		$output .= '<span class="aips-sponsor-disclosure-badge">' . esc_html__( 'Sponsored', 'ai-post-scheduler' ) . '</span> ';
		$output .= '<span class="aips-sponsor-disclosure-text">' . esc_html( $text ) . '</span>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render sponsor card block.
	 *
	 * @param object $campaign
	 * @return string
	 */
	public function render_sponsor_card( $campaign ) {
		$brand = esc_html( $campaign->brand_name );
		$url   = esc_url( $campaign->target_url );
		$cta   = ! empty( $campaign->cta_text ) ? esc_html( $campaign->cta_text ) : esc_html__( 'Learn More', 'ai-post-scheduler' );

		$output  = '<div class="aips-sponsor-card">';
		if ( ! empty( $campaign->logo_url ) ) {
			$output .= '<div class="aips-sponsor-logo"><img src="' . esc_url( $campaign->logo_url ) . '" alt="' . $brand . ' logo" /></div>';
		}
		$output .= '<div class="aips-sponsor-details">';
		$output .= '<h4 class="aips-sponsor-title">' . sprintf( esc_html__( 'Featured Partner: %s', 'ai-post-scheduler' ), $brand ) . '</h4>';
		$output .= '<a href="' . $url . '" target="_blank" rel="nofollow sponsored noopener" class="aips-sponsor-button">' . $cta . ' &rarr;</a>';
		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render image banner unit.
	 *
	 * @param object $slot
	 * @return string
	 */
	private function render_image_banner( $slot ) {
		// slot code contains either JSON {img: '', url: '', alt: ''} or raw image tag
		$data = json_decode( (string) $slot->code, true );
		if ( is_array( $data ) && ! empty( $data['img'] ) ) {
			$img = esc_url( $data['img'] );
			$alt = esc_attr( $data['alt'] ?? $slot->name );
			$url = ! empty( $data['url'] ) ? esc_url( $data['url'] ) : '';

			$tag = '<img src="' . $img . '" alt="' . $alt . '" class="aips-ad-banner-img" />';
			if ( ! empty( $url ) ) {
				return '<a href="' . $url . '" target="_blank" rel="nofollow sponsored noopener">' . $tag . '</a>';
			}
			return $tag;
		}

		return (string) $slot->code;
	}
}
