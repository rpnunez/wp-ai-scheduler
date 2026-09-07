<?php
/**
 * Tests shared JSON parsing in the content auditor.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Content_Auditor_JSON_Parsing extends WP_UnitTestCase {

	public function test_legacy_gap_fallback_ignores_non_json_brackets() {
		$ai_service = $this->createMock( AIPS_AI_Service_Interface::class );
		$ai_service->expects( $this->once() )
			->method( 'generate_text' )
			->willReturn( 'Analysis [draft]: [{"missing_topic":"Container security"}]' );

		$logger = $this->createMock( AIPS_Logger_Interface::class );
		$auditor = new class() extends AIPS_Content_Auditor {
			public function __construct() {}

			public function get_site_content_summary( $limit = 100 ) {
				return array();
			}
		};

		$reflection = new ReflectionClass( AIPS_Content_Auditor::class );
		$reflection->getProperty( 'ai_service' )->setValue( $auditor, $ai_service );
		$reflection->getProperty( 'logger' )->setValue( $auditor, $logger );

		$result = $auditor->perform_gap_analysis_fallback( 'DevOps' );

		$this->assertSame( array( array( 'missing_topic' => 'Container security' ) ), $result );
	}

	public function test_topic_gap_fallback_ignores_non_json_brackets() {
		$ai_service = $this->createMock( AIPS_AI_Service_Interface::class );
		$ai_service->expects( $this->once() )
			->method( 'generate_json' )
			->willReturn( new WP_Error( 'structured_output_unavailable', 'Unavailable.' ) );
		$ai_service->expects( $this->once() )
			->method( 'generate_text' )
			->willReturn( 'Analysis [draft]: [{"missing_topic":"Container security"}]' );

		$reflection = new ReflectionClass( AIPS_Content_Auditor_Engine::class );
		$engine     = $reflection->newInstanceWithoutConstructor();

		$ai_service_property = $reflection->getProperty( 'ai_service' );
		$ai_service_property->setValue( $engine, $ai_service );

		$result = $engine->analyze_topic_gaps(
			'DevOps',
			array(),
			array(
				'top_keyphrases'        => array(),
				'category_distribution' => array(),
			)
		);

		$this->assertSame( 1, $result['gap_count'] );
		$this->assertSame( 'Container security', $result['gaps'][0]['missing_topic'] );
	}
}
