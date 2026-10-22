<?php
/**
 * Tests for AIPS_Autolink_Policy and the Internal Link Automation settings.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Autolink_Policy extends WP_UnitTestCase {

	/** @var string[] */
	private static $options = array(
		'aips_autolink_enabled',
		'aips_autolink_auto_apply_threshold',
		'aips_autolink_review_threshold',
		'aips_autolink_max_links_per_post',
		'aips_autolink_max_total_internal_per_post',
		'aips_autolink_max_inbound_per_target',
		'aips_autolink_skip_first_paragraph',
		'aips_autolink_rel',
		'aips_autolink_target_blank',
	);

	public function tearDown(): void {
		foreach ( self::$options as $option ) {
			delete_option( $option );
		}
		parent::tearDown();
	}

	private function suggestion( float $confidence, array $extra = array() ): array {
		return array_merge(
			array(
				'source_post_id' => 10,
				'target_post_id' => 20,
				'confidence'     => $confidence,
				'has_anchor'     => true,
			),
			$extra
		);
	}

	private function policy(): AIPS_Autolink_Policy {
		return new AIPS_Autolink_Policy( AIPS_Config::get_instance() );
	}

	public function test_defaults_are_conservative() {
		$policy   = $this->policy();
		$settings = $policy->get_settings();

		$this->assertFalse( $policy->is_enabled() );
		$this->assertSame( 0.85, $settings['auto_apply_threshold'] );
		$this->assertSame( 0.70, $settings['review_threshold'] );
		$this->assertSame( 3, $settings['max_links_per_post'] );
		$this->assertTrue( $settings['skip_first_paragraph'] );
		$this->assertSame( array(), $policy->get_link_attributes() );
		$this->assertSame( array( 'skip_first_paragraph' => true ), $policy->get_engine_options() );
	}

	public function test_threshold_corridor() {
		$policy = $this->policy();

		$this->assertSame( 'apply', $policy->evaluate( $this->suggestion( 0.90 ) )['decision'] );
		$this->assertSame( 'apply', $policy->evaluate( $this->suggestion( 0.85 ) )['decision'] );

		$review = $policy->evaluate( $this->suggestion( 0.75 ) );
		$this->assertSame( 'review', $review['decision'] );
		$this->assertSame( 'review_band', $review['reason'] );

		$skip = $policy->evaluate( $this->suggestion( 0.60 ) );
		$this->assertSame( 'skip', $skip['decision'] );
		$this->assertSame( 'below_review_threshold', $skip['reason'] );
	}

	public function test_missing_anchor_goes_to_review_even_when_confident() {
		$result = $this->policy()->evaluate( $this->suggestion( 0.95, array( 'has_anchor' => false ) ) );

		$this->assertSame( 'review', $result['decision'] );
		$this->assertSame( 'no_anchor', $result['reason'] );
	}

	/**
	 * @dataProvider skip_rules
	 */
	public function test_skip_rules( array $suggestion_extra, array $context, string $reason ) {
		$result = $this->policy()->evaluate( $this->suggestion( 0.95, $suggestion_extra ), $context );

		$this->assertSame( 'skip', $result['decision'] );
		$this->assertSame( $reason, $result['reason'] );
	}

	public function skip_rules(): array {
		return array(
			'invalid'         => array( array( 'source_post_id' => 0 ), array(), 'invalid_suggestion' ),
			'self link'       => array( array( 'target_post_id' => 10 ), array(), 'self_link' ),
			'already linked'  => array( array(), array( 'source_links_to_target' => true ), 'already_linked' ),
			'source total'    => array( array(), array( 'source_internal_links' => 15 ), 'source_link_cap' ),
			'source run cap'  => array( array(), array( 'source_new_links' => 3 ), 'source_run_cap' ),
			'target run cap'  => array( array(), array( 'target_new_inbound' => 5 ), 'target_run_cap' ),
			'first paragraph' => array( array( 'paragraph_index' => 0 ), array(), 'first_paragraph' ),
		);
	}

	public function test_caps_allow_links_below_the_limit() {
		$context = array(
			'source_internal_links' => 14,
			'source_new_links'      => 2,
			'target_new_inbound'    => 4,
		);

		$this->assertSame( 'apply', $this->policy()->evaluate( $this->suggestion( 0.95, array( 'paragraph_index' => 1 ) ), $context )['decision'] );
	}

	public function test_custom_settings_are_normalized() {
		update_option( 'aips_autolink_auto_apply_threshold', 0.6 );
		update_option( 'aips_autolink_review_threshold', 0.9 );
		update_option( 'aips_autolink_max_links_per_post', 0 );
		update_option( 'aips_autolink_skip_first_paragraph', 0 );
		update_option( 'aips_autolink_rel', 'bogus' );
		update_option( 'aips_autolink_target_blank', 1 );

		$policy   = $this->policy();
		$settings = $policy->get_settings();

		$this->assertSame( 0.6, $settings['auto_apply_threshold'] );
		$this->assertSame( 0.6, $settings['review_threshold'], 'Review threshold never exceeds auto-apply.' );
		$this->assertSame( 1, $settings['max_links_per_post'] );
		$this->assertSame( '', $settings['rel'] );
		$this->assertSame( array( 'target' => '_blank' ), $policy->get_link_attributes() );
		$this->assertSame( 'apply', $policy->evaluate( $this->suggestion( 0.7, array( 'paragraph_index' => 0 ) ) )['decision'] );
	}

	public function test_rel_attribute_passthrough() {
		update_option( 'aips_autolink_rel', 'nofollow' );

		$this->assertSame( array( 'rel' => 'nofollow' ), $this->policy()->get_link_attributes() );
	}

	public function test_settings_ui_sanitizers() {
		$ui = new AIPS_Settings_UI();

		$this->assertSame( 0.5, $ui->sanitize_autolink_threshold( 0.1 ) );
		$this->assertSame( 1.0, $ui->sanitize_autolink_threshold( '7' ) );
		$this->assertSame( 0.85, $ui->sanitize_autolink_threshold( 'abc' ) );
		$this->assertSame( 1, $ui->sanitize_autolink_limit( 0 ) );
		$this->assertSame( 100, $ui->sanitize_autolink_limit( 5000 ) );
		$this->assertSame( 'sponsored', $ui->sanitize_autolink_rel( 'Sponsored' ) );
		$this->assertSame( '', $ui->sanitize_autolink_rel( 'noopener' ) );
	}

	public function test_settings_are_registered_with_defaults() {
		( new AIPS_Settings() )->register_settings();

		$registered = get_registered_settings();
		foreach ( self::$options as $option ) {
			$this->assertArrayHasKey( $option, $registered, "{$option} should be registered" );
		}
		$this->assertSame( 0.85, $registered['aips_autolink_auto_apply_threshold']['default'] );
	}

	public function test_settings_fields_render() {
		$ui = new AIPS_Settings_UI();

		ob_start();
		$ui->autolink_enabled_field_callback();
		$ui->autolink_thresholds_field_callback();
		$ui->autolink_limits_field_callback();
		$ui->autolink_placement_field_callback();
		$html = ob_get_clean();

		foreach ( self::$options as $option ) {
			$this->assertStringContainsString( 'name="' . $option . '"', $html );
		}
	}
}
