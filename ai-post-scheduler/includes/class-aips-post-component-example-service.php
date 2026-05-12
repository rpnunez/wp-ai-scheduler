<?php
/**
 * Post Component Example Service
 *
 * Provides deterministic runtime-generated starter examples for the
 * "Add New Post Component" flow.
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Post_Component_Example_Service {

	/**
	 * Return a random set of starter examples.
	 *
	 * @param int $count Number of examples to return.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_random_examples( $count = 5 ) {
		$examples = $this->get_capability_map();
		shuffle( $examples );

		return array_slice( $examples, 0, max( 1, absint( $count ) ) );
	}

	/**
	 * Return the full example catalog.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_capability_map() {
		return array(
			array(
				'key'               => 'sales_cta_banner',
				'name'              => __( 'Sales Deal CTA', 'ai-post-scheduler' ),
				'description'       => __( 'Promote a category-specific offer after the introduction of sales-focused posts.', 'ai-post-scheduler' ),
				'component_type'    => 'cta',
				'content'           => '<div class="aips-cta-banner"><strong>Need help closing more deals?</strong><p>Book a free strategy session and get a custom sales workflow audit.</p><p><a href="/contact">Schedule your consult</a></p></div>',
				'rules'             => array(
					'logic'      => 'and',
					'action'     => 'prepend_intro',
					'conditions' => array(
						array(
							'field'    => 'category',
							'operator' => 'is',
							'values'   => array( 'sales' ),
						),
					),
				),
				'rule_hints'        => array( 'category' => 'sales', 'placement' => 'after intro' ),
			),
			array(
				'key'               => 'persona_demo_cta',
				'name'              => __( 'Persona Demo CTA', 'ai-post-scheduler' ),
				'description'       => __( 'Tailor a CTA to a specific author persona or niche voice.', 'ai-post-scheduler' ),
				'component_type'    => 'cta',
				'content'           => '<aside class="aips-persona-cta"><h3>Want the full playbook?</h3><p>This checklist was created for founders who need practical next steps, not theory.</p><p><a href="/resources/founder-playbook">Download the founder playbook</a></p></aside>',
				'rules'             => array(
					'logic'      => 'and',
					'action'     => 'add_middle_paragraph',
					'conditions' => array(
						array(
							'field'    => 'author_persona',
							'operator' => 'contains',
							'values'   => array( 'founder' ),
						),
					),
				),
				'rule_hints'        => array( 'persona' => 'founder', 'placement' => 'after second H2' ),
			),
			array(
				'key'               => 'regulated_disclaimer_block',
				'name'              => __( 'Financial Disclaimer', 'ai-post-scheduler' ),
				'description'       => __( 'Append a compliance disclaimer to sensitive posts.', 'ai-post-scheduler' ),
				'component_type'    => 'disclaimer',
				'content'           => '<div class="aips-disclaimer"><strong>Disclosure:</strong> This article is for educational purposes only and should not be considered financial advice. Consult a licensed professional before making decisions.</div>',
				'rules'             => array(
					'logic'      => 'or',
					'action'     => 'add_at_end',
					'conditions' => array(
						array(
							'field'    => 'tag',
							'operator' => 'is',
							'values'   => array( 'investing', 'finance' ),
						),
					),
				),
				'rule_hints'        => array( 'tag' => 'investing', 'placement' => 'end of post' ),
			),
			array(
				'key'               => 'regional_offer_strip',
				'name'              => __( 'Regional Offer Strip', 'ai-post-scheduler' ),
				'description'       => __( 'Show an offer variation only for a chosen region or locale.', 'ai-post-scheduler' ),
				'component_type'    => 'cta',
				'content'           => '<div class="aips-regional-strip"><strong>US readers:</strong> Get next-business-day onboarding when you start your trial this week.</div>',
				'rules'             => array(
					'logic'      => 'and',
					'action'     => 'add_before_first_heading',
					'conditions' => array(
						array(
							'field'    => 'region',
							'operator' => 'is',
							'values'   => array( 'US' ),
						),
					),
				),
				'rule_hints'        => array( 'region' => 'US', 'placement' => 'before content' ),
			),
			array(
				'key'               => 'seasonal_campaign_box',
				'name'              => __( 'Holiday Campaign Box', 'ai-post-scheduler' ),
				'description'       => __( 'Turn on a seasonal campaign during a specific date window.', 'ai-post-scheduler' ),
				'component_type'    => 'cta',
				'content'           => '<section class="aips-seasonal-box"><h3>Holiday Prep Toolkit</h3><p>Use our holiday planning template to map promotions, staffing, and fulfillment before the rush.</p></section>',
				'rules'             => array(
					'logic'      => 'and',
					'action'     => 'replace_summary',
					'conditions' => array(
						array(
							'field'    => 'category',
							'operator' => 'is',
							'values'   => array( 'marketing' ),
						),
					),
					'date_window' => array(
						'start'    => gmdate( 'Y' ) . '-11-20 00:00:00',
						'end'      => gmdate( 'Y' ) . '-12-01 23:59:59',
						'timezone' => wp_timezone_string(),
					),
				),
				'rule_hints'        => array( 'category' => 'marketing', 'window' => 'Nov 20-Dec 1', 'placement' => 'before conclusion' ),
			),
			array(
				'key'               => 'content_enrichment_faq',
				'name'              => __( 'Quick FAQ Enrichment', 'ai-post-scheduler' ),
				'description'       => __( 'Add a compact FAQ to longer educational posts.', 'ai-post-scheduler' ),
				'component_type'    => 'faq',
				'content'           => '<div class="aips-faq"><h3>Quick FAQ</h3><p><strong>What should I do first?</strong> Start with the highest-impact audit item and document the baseline.</p><p><strong>How fast should I iterate?</strong> Review weekly and adjust once you have enough signal.</p></div>',
				'rules'             => array(
					'logic'      => 'and',
					'action'     => 'add_middle_paragraph',
					'conditions' => array(
						array(
							'field'    => 'content_length',
							'operator' => 'gte',
							'values'   => array( '1200' ),
						),
						array(
							'field'    => 'heading_presence',
							'operator' => 'is',
							'values'   => array( 'true' ),
						),
					),
				),
				'rule_hints'        => array( 'content_length' => '1200+', 'placement' => 'after second H2' ),
			),
			array(
				'key'               => 'internal_link_pod',
				'name'              => __( 'Related Reading Pod', 'ai-post-scheduler' ),
				'description'       => __( 'Render a related-links pod from accepted or suggested internal links.', 'ai-post-scheduler' ),
				'component_type'    => 'internal_link_pod',
				'content'           => '<div class="aips-related-reading"><h3>Related Reading</h3><p>Automatically surface your best related posts here.</p></div>',
				'rules'             => array(
					'logic'      => 'and',
					'action'     => 'add_at_end',
					'conditions' => array(
						array(
							'field'    => 'has_h2',
							'operator' => 'is',
							'values'   => array( 'true' ),
						),
					),
				),
				'rule_hints'        => array( 'capability' => 'internal links', 'placement' => 'end of post' ),
			),
		);
	}
}
