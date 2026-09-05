<?php
/**
 * Monetization AI Service
 *
 * Provides commercial intent scoring, high-RPM niche optimization,
 * and generation prompt structure enhancements to maximize viewability and dwell time.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Monetization_AI_Service {

	/**
	 * @var AIPS_Sponsor_Campaigns_Repository
	 */
	private $campaigns_repo;

	public function __construct( ?AIPS_Sponsor_Campaigns_Repository $campaigns_repo = null ) {
		$container            = AIPS_Container::get_instance();
		$this->campaigns_repo = $campaigns_repo ?: ( $container->has( AIPS_Sponsor_Campaigns_Repository::class ) ? $container->make( AIPS_Sponsor_Campaigns_Repository::class ) : new AIPS_Sponsor_Campaigns_Repository() );
	}

	/**
	 * Evaluate commercial intent for a topic or article title.
	 *
	 * @param string $topic
	 * @param string $outline
	 * @return array
	 */
	public function analyze_commercial_intent( $topic, $outline = '' ) {
		$text = strtolower( $topic . ' ' . $outline );

		$transactional_words = array( 'buy', 'discount', 'deal', 'coupon', 'pricing', 'order', 'cheap', 'best price', 'purchase', 'promo' );
		$commercial_words    = array( 'best', 'review', 'comparison', 'vs', 'top', 'alternative', 'guide', 'features', 'worth it', 'pros and cons' );

		$trans_matches = 0;
		foreach ( $transactional_words as $w ) {
			if ( strpos( $text, $w ) !== false ) {
				$trans_matches++;
			}
		}

		$comm_matches = 0;
		foreach ( $commercial_words as $w ) {
			if ( strpos( $text, $w ) !== false ) {
				$comm_matches++;
			}
		}

		if ( $trans_matches >= 1 ) {
			return array(
				'intent'        => 'Transactional',
				'rpm_potential' => 'High ($30 - $60+ RPM)',
				'badge_color'   => '#10b981',
				'score'         => 90,
			);
		}

		if ( $comm_matches >= 1 ) {
			return array(
				'intent'        => 'Commercial Investigation',
				'rpm_potential' => 'Medium-High ($20 - $40 RPM)',
				'badge_color'   => '#8b5cf6',
				'score'         => 75,
			);
		}

		return array(
			'intent'        => 'Informational',
			'rpm_potential' => 'Standard ($10 - $20 RPM)',
			'badge_color'   => '#3b82f6',
			'score'         => 40,
		);
	}

	/**
	 * Generate prompt structure enhancement directives for high-RPM content.
	 *
	 * Injects formatting requirements (comparison tables, FAQ blocks, key buying criteria)
	 * that directly boost average session duration and ad viewability.
	 *
	 * @param string      $intent
	 * @param object|null $sponsor_campaign
	 * @return string
	 */
	public function get_prompt_structure_enhancements( $intent, $sponsor_campaign = null ) {
		$directives = array();

		if ( in_array( $intent, array( 'Transactional', 'Commercial Investigation' ), true ) ) {
			$directives[] = '- Structure: Include a comprehensive comparison table or structured feature breakdown to help readers evaluate alternatives.';
			$directives[] = '- Decision Factors: Add a distinct "Key Factors to Consider Before Choosing" section.';
			$directives[] = '- Engagement: Add an FAQ section with at least 3 high-intent questions to maximize reader dwell time and viewability.';
		}

		if ( $sponsor_campaign ) {
			$directives[] = sprintf(
				'- Featured Partner Context: Subtly highlight %s as a recommended solution where contextually natural, without aggressive sales pitches.',
				esc_html( $sponsor_campaign->brand_name )
			);
		}

		return ! empty( $directives ) ? implode( "\n", $directives ) : '';
	}

	/**
	 * Match active sponsor campaign for generation context.
	 *
	 * @param array $tags
	 * @param array $categories
	 * @return object|null
	 */
	public function match_sponsor( array $tags = array(), array $categories = array() ) {
		return $this->campaigns_repo->match_campaign( $tags, $categories );
	}
}
