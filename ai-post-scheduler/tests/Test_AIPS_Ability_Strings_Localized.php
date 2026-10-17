<?php
/**
 * Guards the Abilities integration against user-facing strings that are not
 * translatable.
 *
 * Scans the PHP of every ability class for sentence-like string literals that are
 * not an argument of a translation function. Strings passed to a logger are
 * developer diagnostics and are left alone, as are the few listed in
 * ALLOWED_FRAGMENTS (sample data, not interface text).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Strings_Localized extends WP_UnitTestCase {

	/**
	 * Functions whose arguments are translatable (or deliberately not shown to users).
	 */
	const SAFE_CALLS = array(
		'__', '_e', '_n', '_x', '_nx', 'esc_html__', 'esc_attr__', 'esc_html_e', 'esc_attr_e', '_n_noop',
		'log',
	);

	/**
	 * Literals that are data, not interface text.
	 */
	const ALLOWED_FRAGMENTS = array(
		'Repairing a flat bicycle tyre',
		'Simulated transient failure.',
		'Stress test alt text',
		'Stress soak alt text',
		'Stress enrichment post',
	);

	/**
	 * Files that make up the Abilities integration.
	 *
	 * @return string[]
	 */
	private function ability_files() {
		$includes = dirname( __DIR__ ) . '/includes/';
		$files    = glob( $includes . 'class-aips-ability-*.php' );
		$files[]  = $includes . 'class-aips-stress-test-ability-cases.php';

		return array_unique( $files );
	}

	/**
	 * Sentence-like literals outside translation calls.
	 *
	 * @param string $file File path.
	 * @return string[] "file:line: text" entries.
	 */
	private function untranslated_literals( $file ) {
		$tokens     = token_get_all( file_get_contents( $file ) );
		$call_stack = array();
		$depth      = 0;
		$last_name  = '';
		$found      = array();

		foreach ( $tokens as $token ) {
			if ( is_array( $token ) ) {
				if ( T_STRING === $token[0] ) {
					$last_name = $token[1];
				} elseif ( T_CONSTANT_ENCAPSED_STRING === $token[0] && empty( $call_stack ) ) {
					$text = substr( $token[1], 1, -1 );

					if ( strlen( $text ) > 12 && preg_match( '/^[A-Z][a-z]+(\s+\S+){2,}/', $text ) && ! $this->is_allowed( $text ) ) {
						$found[] = basename( $file ) . ':' . $token[2] . ': ' . substr( $text, 0, 90 );
					}
				}

				continue;
			}

			if ( '(' === $token ) {
				++$depth;

				if ( in_array( $last_name, self::SAFE_CALLS, true ) ) {
					$call_stack[] = $depth;
				}
			} elseif ( ')' === $token ) {
				if ( ! empty( $call_stack ) && end( $call_stack ) === $depth ) {
					array_pop( $call_stack );
				}

				--$depth;
			}
		}

		return $found;
	}

	private function is_allowed( $text ) {
		foreach ( self::ALLOWED_FRAGMENTS as $fragment ) {
			if ( false !== strpos( $text, $fragment ) ) {
				return true;
			}
		}

		return false;
	}

	public function test_every_user_facing_string_in_the_ability_classes_is_translatable() {
		$offenders = array();

		foreach ( $this->ability_files() as $file ) {
			$offenders = array_merge( $offenders, $this->untranslated_literals( $file ) );
		}

		$this->assertSame( array(), $offenders, "Wrap these in __() (and add a translators comment):\n" . implode( "\n", $offenders ) );
	}

	public function test_the_scanner_would_catch_an_untranslated_message() {
		$file = tempnam( sys_get_temp_dir(), 'aips' );
		file_put_contents( $file, "<?php\n\$e = new WP_Error( 'x', 'This message is not translatable.' );\n\$f = __( 'But this message is translatable.', 'ai-post-scheduler' );\n\$logger->log( 'Developer diagnostics are fine here.' );\n" );

		$found = $this->untranslated_literals( $file );
		unlink( $file );

		$this->assertCount( 1, $found );
		$this->assertStringContainsString( 'This message is not translatable.', $found[0] );
	}

	public function test_validator_messages_are_translated() {
		$filter = function ( $translation, $text ) {
			return 0 === strpos( $text, 'Duplicate step_key' ) ? 'TRANSLATED' : $translation;
		};
		add_filter( 'gettext', $filter, 10, 2 );

		$result = ( new AIPS_Ability_Workflow_Document_Validator() )->validate(
			array(
				'steps' => array(
					array( 'step_key' => 'a', 'ability_name' => 'x/y' ),
					array( 'step_key' => 'a', 'ability_name' => 'x/y' ),
				),
			),
			true
		);

		remove_filter( 'gettext', $filter, 10 );

		$this->assertWPError( $result );
		$this->assertContains( 'TRANSLATED', $result->get_error_data()['errors'] );
	}
}
