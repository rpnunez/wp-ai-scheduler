<?php
/**
 * Tests for anchor phrase generation in AIPS_Inbound_Links_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Inbound_Links_Anchor_Phrases extends WP_UnitTestCase {

	/**
	 * Phrases for a post title, without needing the service's collaborators.
	 *
	 * @param string $title Post title.
	 * @return string[]
	 */
	private function phrases_for( string $title ): array {
		$post_id = $this->factory->post->create( array( 'post_title' => $title ) );

		$reflection = new ReflectionClass( AIPS_Inbound_Links_Service::class );
		$service    = $reflection->newInstanceWithoutConstructor();

		// Skip the Search Console lookup.
		$property = $reflection->getProperty( 'search_queries' );
		$property->setAccessible( true );
		$property->setValue( $service, array( $post_id => array() ) );

		return $service->get_anchor_phrases( get_post( $post_id ) );
	}

	public function test_version_numbers_stay_whole_in_phrases() {
		$phrases = $this->phrases_for( 'Rolling Out Diagnostic Agent v0.9.2 Safely' );

		$this->assertContains( 'Diagnostic Agent v0.9.2', $phrases );
		foreach ( $phrases as $phrase ) {
			$this->assertDoesNotMatchRegularExpression( '/\\bv0$/i', $phrase, 'A phrase must not stop inside a version number.' );
			$this->assertDoesNotMatchRegularExpression( '/\\bv0\\s/i', $phrase );
		}
	}

	public function test_sentence_punctuation_is_still_stripped() {
		$phrases = $this->phrases_for( 'Why Vector Search Matters.' );

		$this->assertContains( 'Vector Search Matters', $phrases );
		$this->assertNotContains( 'Vector Search Matters.', $phrases );
	}
}
