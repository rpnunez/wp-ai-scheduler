<?php
/**
 * Tests for AIPS_Link_Insertion_Engine.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Link_Insertion_Engine extends WP_UnitTestCase {

	/** @var AIPS_Link_Insertion_Engine */
	private $engine;

	public function setUp(): void {
		parent::setUp();
		$this->engine = new AIPS_Link_Insertion_Engine();
	}

	public function tearDown(): void {
		remove_shortcode( 'aips_test_box' );
		remove_all_filters( 'aips_link_insertion_skip_blocks' );
		parent::tearDown();
	}

	private function texts( string $html, array $phrases, array $options = array() ): array {
		return wp_list_pluck( $this->engine->find_phrase_occurrences( $html, $phrases, $options ), 'raw' );
	}

	public function test_finds_whole_word_case_insensitive_matches() {
		$html = '<p>Internal linking helps. Relinking is different. INTERNAL LINKING matters.</p>';

		$this->assertSame( array( 'Internal linking', 'INTERNAL LINKING' ), $this->texts( $html, array( 'internal linking' ) ) );
		$this->assertSame( array(), $this->texts( $html, array( 'link' ) ) );
	}

	public function test_skips_existing_links_headings_code_and_attributes() {
		$html = '<h2>Vector search basics</h2>'
			. '<p>See <a href="/x/" title="vector search">vector search</a> and <img alt="vector search" src="a.png"> too.</p>'
			. '<pre><code>vector search()</code></pre>'
			. '<p>Plain vector search here.</p>';

		$occurrences = $this->engine->find_phrase_occurrences( $html, array( 'vector search' ) );

		$this->assertCount( 1, $occurrences );
		$this->assertSame( 1, $occurrences[0]['paragraph_index'] );
		$this->assertSame( 'vector search', substr( $html, $occurrences[0]['offset'], $occurrences[0]['length'] ) );
		$this->assertGreaterThan( strpos( $html, 'Plain' ), $occurrences[0]['offset'] );
	}

	public function test_skips_gutenberg_heading_html_and_button_blocks_and_block_json() {
		$html = '<!-- wp:heading {"content":"topic clusters"} --><h2 class="wp-block-heading">Topic clusters</h2><!-- /wp:heading -->'
			. '<!-- wp:html --><div>topic clusters raw</div><!-- /wp:html -->'
			. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><span>topic clusters</span></div><!-- /wp:button --></div><!-- /wp:buttons -->'
			. '<!-- wp:paragraph --><p>Build topic clusters today.</p><!-- /wp:paragraph -->'
			. '<!-- wp:separator /--><!-- wp:paragraph --><p>More topic clusters.</p><!-- /wp:paragraph -->';

		$this->assertSame( array( 'topic clusters', 'topic clusters' ), $this->texts( $html, array( 'topic clusters' ) ) );
	}

	public function test_skip_blocks_filter() {
		add_filter( 'aips_link_insertion_skip_blocks', function ( $blocks ) {
			$blocks[] = 'core/quote';
			return $blocks;
		} );

		$html = '<!-- wp:quote --><blockquote><p>semantic search</p></blockquote><!-- /wp:quote --><p>semantic search</p>';

		$this->assertCount( 1, $this->texts( $html, array( 'semantic search' ) ) );
	}

	public function test_skips_first_paragraph_when_requested() {
		$html = '<p>Content audit intro.</p><ul><li>content audit list</li></ul><p>Second content audit.</p>';

		$occurrences = $this->engine->find_phrase_occurrences( $html, array( 'content audit' ), array( 'skip_first_paragraph' => true ) );

		$this->assertSame( array( -1, 1 ), wp_list_pluck( $occurrences, 'paragraph_index' ) );
	}

	public function test_matches_across_entities_and_rejects_split_entities() {
		$html = '<p>Our Tips &amp; Tricks guide.</p><p>AT&amp;T news</p>';

		$occurrences = $this->engine->find_phrase_occurrences( $html, array( 'tips & tricks' ) );

		$this->assertCount( 1, $occurrences );
		$this->assertSame( 'Tips &amp; Tricks', $occurrences[0]['raw'] );
		$this->assertSame( 'Tips & Tricks', $occurrences[0]['text'] );
		$this->assertSame( array(), $this->texts( $html, array( 'T&' ) ) );
	}

	public function test_multibyte_text_offsets() {
		$html = '<p>Über die Café Strategie für Einsteiger.</p>';

		$occurrences = $this->engine->find_phrase_occurrences( $html, array( 'café strategie' ) );

		$this->assertCount( 1, $occurrences );
		$this->assertSame( 'Café Strategie', substr( $html, $occurrences[0]['offset'], $occurrences[0]['length'] ) );
	}

	public function test_phrase_split_by_inline_tag_is_not_matched() {
		$this->assertSame( array(), $this->texts( '<p>semantic <strong>search</strong></p>', array( 'semantic search' ) ) );
	}

	public function test_skips_registered_shortcodes() {
		add_shortcode( 'aips_test_box', '__return_empty_string' );

		$html = '<p>[aips_test_box title="link building"]link building inside[/aips_test_box] outside link building.</p>';

		$occurrences = $this->engine->find_phrase_occurrences( $html, array( 'link building' ) );

		$this->assertCount( 1, $occurrences );
		$this->assertGreaterThan( strpos( $html, 'outside' ), $occurrences[0]['offset'] );
	}

	public function test_lone_less_than_sign_stays_text() {
		$this->assertCount( 1, $this->texts( '<p>if a < b then use anchor text wisely > c</p>', array( 'anchor text' ) ) );
	}

	public function test_overlaps_prefer_earlier_phrases_and_limit() {
		$html = '<p>internal linking strategy and internal linking.</p>';

		$occurrences = $this->engine->find_phrase_occurrences( $html, array( 'internal linking strategy', 'internal linking' ) );
		$this->assertSame( array( 'internal linking strategy', 'internal linking' ), wp_list_pluck( $occurrences, 'raw' ) );

		$this->assertCount( 1, $this->engine->find_phrase_occurrences( $html, array( 'internal linking' ), array( 'limit' => 1 ) ) );
	}

	public function test_insert_wraps_raw_text_and_preserves_markup() {
		$html        = '<!-- wp:paragraph --><p>Read about <em>x</em> Tips &amp; Tricks now.</p><!-- /wp:paragraph -->';
		$occurrences = $this->engine->find_phrase_occurrences( $html, array( 'tips & tricks' ) );

		$result = $this->engine->insert( $html, $occurrences[0], 'https://example.org/tips/?a=1&b=2', array( 'marker' => 7, 'rel' => 'nofollow', 'target' => '_blank' ) );

		$this->assertIsArray( $result );
		$this->assertSame(
			'<!-- wp:paragraph --><p>Read about <em>x</em> <a href="https://example.org/tips/?a=1&#038;b=2" target="_blank" rel="nofollow noopener" data-aips-link="7">Tips &amp; Tricks</a> now.</p><!-- /wp:paragraph -->',
			$result['content']
		);
		$this->assertTrue( $this->engine->has_link_to( $result['content'], 'http://www.example.org/tips/?a=1&b=2' ) );
	}

	public function test_insert_rejects_stale_occurrence_and_bad_url() {
		$html        = '<p>Anchor text here.</p>';
		$occurrences = $this->engine->find_phrase_occurrences( $html, array( 'anchor text' ) );

		$this->assertWPError( $this->engine->insert( '<p>Changed content.</p>', $occurrences[0], 'https://example.org/' ) );
		$this->assertWPError( $this->engine->insert( $html, $occurrences[0], 'javascript:alert(1)' ) );
	}

	public function test_insert_then_revert_round_trip() {
		$html        = '<p>Repeat. Repeat. Semantic linking is here. Repeat. Repeat.</p>';
		$occurrences = $this->engine->find_phrase_occurrences( $html, array( 'semantic linking' ) );
		$inserted    = $this->engine->insert( $html, $occurrences[0], 'https://example.org/semantic/' );

		$this->assertSame( 1, substr_count( $html, $inserted['before_snippet'] ) );
		$this->assertSame( 1, substr_count( $inserted['content'], $inserted['after_snippet'] ) );

		$reverted = $this->engine->revert( $inserted['content'], $inserted['before_snippet'], $inserted['after_snippet'] );

		$this->assertSame( AIPS_Link_Insertion_Engine::REVERT_OK, $reverted['status'] );
		$this->assertSame( $html, $reverted['content'] );
	}

	public function test_snippets_widen_until_unique() {
		$block = '<p>' . str_repeat( 'filler text ', 30 ) . 'target phrase ' . str_repeat( 'filler text ', 30 ) . '</p>';
		$html  = $block . str_replace( 'target phrase', 'other words', $block );

		$occurrences = $this->engine->find_phrase_occurrences( $html, array( 'target phrase' ) );
		$inserted    = $this->engine->insert( $html, $occurrences[0], 'https://example.org/t/' );

		$this->assertSame( 1, substr_count( $html, $inserted['before_snippet'] ) );
		$this->assertSame( 1, substr_count( $inserted['content'], $inserted['after_snippet'] ) );
	}

	public function test_revert_reports_not_found_and_ambiguous() {
		$html     = '<p>Edited semantic linking content.</p>';
		$inserted = $this->engine->insert( $html, $this->engine->find_phrase_occurrences( $html, array( 'semantic linking' ) )[0], 'https://example.org/s/' );

		$edited = str_replace( 'Edited', 'Rewritten', $inserted['content'] );
		$this->assertSame( AIPS_Link_Insertion_Engine::REVERT_NOT_FOUND, $this->engine->revert( $edited, $inserted['before_snippet'], $inserted['after_snippet'] )['status'] );

		$duplicated = $inserted['content'] . $inserted['content'];
		$result     = $this->engine->revert( $duplicated, $inserted['before_snippet'], $inserted['after_snippet'] );
		$this->assertSame( AIPS_Link_Insertion_Engine::REVERT_AMBIGUOUS, $result['status'] );
		$this->assertSame( $duplicated, $result['content'] );
	}

	public function test_has_link_to_normalizes_urls() {
		$html = '<p><a class="x" href="https://www.example.org/guide/#part">Guide</a> <a href=\'/local-page\'>Local</a></p>';

		$this->assertTrue( $this->engine->has_link_to( $html, 'http://example.org/guide' ) );
		$this->assertTrue( $this->engine->has_link_to( $html, home_url( '/local-page/' ) ) );
		$this->assertFalse( $this->engine->has_link_to( $html, 'https://example.org/other/' ) );
	}
}
