<?php
/**
 * Tests for AIPS_Link_Extractor.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Link_Extractor extends WP_UnitTestCase {

	/** @var AIPS_Link_Extractor */
	private $extractor;

	public function setUp(): void {
		parent::setUp();
		$this->extractor = new AIPS_Link_Extractor();
	}

	private function hrefs( string $html ): array {
		return wp_list_pluck( $this->extractor->extract( $html ), 'href' );
	}

	public function test_empty_and_linkless_content() {
		$this->assertSame( array(), $this->extractor->extract( '' ) );
		$this->assertSame( array(), $this->extractor->extract( '<p>No links here.</p>' ) );
	}

	public function test_quote_styles_and_attribute_order() {
		$html = '<p><a href="/double/">A</a> <a class="x" href=\'/single/\'>B</a> <a href=/unquoted/ target=_blank>C</a></p>';

		$this->assertSame( array( '/double/', '/single/', '/unquoted/' ), $this->hrefs( $html ) );
	}

	public function test_decodes_entities_in_href_and_anchor() {
		$links = $this->extractor->extract( '<a href="/search/?a=1&amp;b=2">Tips &amp; <strong>Tricks</strong>&nbsp;now</a>' );

		$this->assertSame( '/search/?a=1&b=2', $links[0]['href'] );
		$this->assertSame( 'Tips & Tricks now', $links[0]['anchor_text'] );
	}

	public function test_attribute_value_containing_greater_than() {
		$links = $this->extractor->extract( '<a title="a > b" href="/target/">Target</a>' );

		$this->assertSame( '/target/', $links[0]['href'] );
		$this->assertSame( 'Target', $links[0]['anchor_text'] );
	}

	public function test_skips_non_navigational_hrefs() {
		$html = '<a href="#top">Top</a><a href="mailto:a@b.c">Mail</a><a href="tel:123">Call</a>'
			. '<a href=" JavaScript:void(0)">JS</a><a href="">Empty</a><a name="anchor">No href</a><a href="/real/">Real</a>';

		$this->assertSame( array( '/real/' ), $this->hrefs( $html ) );
	}

	public function test_ignores_comments_scripts_and_block_json() {
		$html = '<!-- wp:button {"url":"https://example.com/from-json/"} -->'
			. '<div class="wp-block-button"><a class="wp-block-button__link" href="https://example.com/rendered/">Go</a></div>'
			. '<!-- /wp:button -->'
			. '<!-- <a href="/commented/">x</a> -->'
			. '<script>var s = \'<a href="/in-script/">x</a>\';</script>'
			. '<style>a[href="/in-style/"]{}</style>';

		$this->assertSame( array( 'https://example.com/rendered/' ), $this->hrefs( $html ) );
	}

	public function test_rel_nofollow_marker_and_positions() {
		$html = '<a href="/one/" rel="Sponsored  NoFollow">1</a> <a href="#skip">x</a> <a href="/two/" data-aips-link="42">2</a>';
		$links = $this->extractor->extract( $html );

		$this->assertCount( 2, $links );
		$this->assertSame( 'sponsored nofollow', $links[0]['rel'] );
		$this->assertTrue( $links[0]['is_nofollow'] );
		$this->assertFalse( $links[0]['inserted_by_aips'] );
		$this->assertSame( 0, $links[0]['position'] );

		$this->assertFalse( $links[1]['is_nofollow'] );
		$this->assertTrue( $links[1]['inserted_by_aips'] );
		$this->assertSame( 1, $links[1]['position'] );
	}

	public function test_first_duplicate_attribute_wins_and_case_insensitive_tags() {
		$links = $this->extractor->extract( '<A HREF="/first/" href="/second/">Upper</A>' );

		$this->assertSame( '/first/', $links[0]['href'] );
	}

	public function test_does_not_match_abbr_or_other_a_prefixed_tags() {
		$this->assertSame( array(), $this->hrefs( '<abbr href="/nope/">x</abbr><article href="/nope/">y</article>' ) );
	}
}
