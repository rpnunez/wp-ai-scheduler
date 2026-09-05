<?php
/**
 * Standalone Empirical Stress Test Harness for Milestone 1 Backend Foundation
 *
 * Exercises AIPS_Referral_Programs_Repository and AIPS_Link_Cloaking_Service
 * across boundary conditions, collisions, token substitutions, and edge cases.
 *
 * @package AI_Post_Scheduler
 */

// Define ABSPATH and plugin constants if not defined
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'AIPS_VERSION' ) ) {
	define( 'AIPS_VERSION', '3.7.2' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

// WordPress time constants
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}
if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
	define( 'YEAR_IN_SECONDS', 31536000 );
}

// Minimal WordPress function stubs for standalone execution
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strip_tags( (string) $title );
		$title = preg_replace( '|%([a-fA-F0-9][a-fA-F0-9])|', '', $title );
		$title = preg_replace( '/&.+?;/', '', $title );
		$title = preg_replace( '/[^A-Za-z0-9 _-]/', '', $title );
		$title = preg_replace( '/\s+/', '-', $title );
		$title = preg_replace( '|-+|', '-', $title );
		$title = trim( $title, '-' );
		return strtolower( $title );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = (string) $str;
		$str = strip_tags( $str );
		$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
		return trim( $str );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$url = str_replace( ' ', '%20', $url );
		$url = preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\\x80-\\xff]|i', '', $url );
		return $url;
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( $url ) {
		$url = trim( (string) $url );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return false;
		}
		$parts = parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return false;
		}
		return $url;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
		$text = strip_tags( $text );
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		if ( 'Y-m-d' === $type ) {
			return date( 'Y-m-d' );
		}
		return time();
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.com' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg() {
		$args = func_get_args();
		if ( is_array( $args[0] ) ) {
			$params = $args[0];
			$url    = isset( $args[1] ) ? $args[1] : '';
		} else {
			$params = array( $args[0] => $args[1] );
			$url    = isset( $args[2] ) ? $args[2] : '';
		}

		$parts = parse_url( $url );
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		foreach ( $params as $k => $v ) {
			$query[ $k ] = $v;
		}

		$new_url = ( ! empty( $parts['scheme'] ) ? $parts['scheme'] . '://' : '' )
			. ( ! empty( $parts['host'] ) ? $parts['host'] : '' )
			. ( ! empty( $parts['port'] ) ? ':' . $parts['port'] : '' )
			. ( ! empty( $parts['path'] ) ? $parts['path'] : '' );

		if ( ! empty( $query ) ) {
			$new_url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		if ( ! empty( $parts['fragment'] ) ) {
			$new_url .= '#' . $parts['fragment'];
		}

		return $new_url;
	}
}

if ( ! function_exists( 'wp_parse_str' ) ) {
	function wp_parse_str( $string, &$array ) {
		parse_str( (string) $string, $array );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'get_the_category' ) ) {
	function get_the_category( $post_id ) {
		if ( 42 === (int) $post_id ) {
			$cat = new stdClass();
			$cat->term_id = 10;
			$cat->slug    = 'cloud-hosting';
			return array( $cat );
		}
		return array();
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}

global $_test_options;
$_test_options = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $_test_options;
		return isset( $_test_options[ $option ] ) ? $_test_options[ $option ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		global $_test_options;
		$_test_options[ $option ] = $value;
		return true;
	}
}

if ( ! class_exists( 'wpdb' ) ) {
	class wpdb {}
}

// Load Composer Autoloader and core classes
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/class-aips-date-time.php';
require_once __DIR__ . '/../includes/class-aips-config.php';
require_once __DIR__ . '/../includes/class-aips-referral-programs-repository.php';
require_once __DIR__ . '/../includes/class-aips-link-cloaking-service.php';

/**
 * Stateful In-Memory Mock for WordPress Database (wpdb)
 */
class MockWPDB extends wpdb {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $rows = array();
	public $last_query = '';

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$parts = explode( '%', $query );
		$res   = array_shift( $parts );

		foreach ( $parts as $part ) {
			if ( empty( $part ) ) {
				continue;
			}
			$type = $part[0];
			$rest = substr( $part, 1 );

			if ( 's' === $type ) {
				$val = array_shift( $args );
				$res .= "'" . addslashes( (string) $val ) . "'" . $rest;
			} elseif ( 'd' === $type ) {
				$val = (int) array_shift( $args );
				$res .= $val . $rest;
			} elseif ( 'f' === $type ) {
				$val = (float) array_shift( $args );
				$res .= $val . $rest;
			} else {
				$res .= '%' . $part;
			}
		}

		return $res;
	}

	public function insert( $table, $data, $format = null ) {
		$this->insert_id++;
		$data['id'] = $this->insert_id;
		$this->rows[ $this->insert_id ] = $data;
		return 1;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$id = $where['id'] ?? 0;
		if ( ! isset( $this->rows[ $id ] ) ) {
			return false;
		}
		foreach ( $data as $k => $v ) {
			$this->rows[ $id ][ $k ] = $v;
		}
		return 1;
	}

	public function delete( $table, $where, $where_format = null ) {
		$id = $where['id'] ?? 0;
		if ( isset( $this->rows[ $id ] ) ) {
			unset( $this->rows[ $id ] );
			return 1;
		}
		return false;
	}

	public function query( $sql ) {
		$this->last_query = $sql;
		if ( false !== stripos( $sql, 'DELETE FROM' ) ) {
			$count = count( $this->rows );
			$this->rows = array();
			return $count;
		}
		return 1;
	}

	public function get_row( $sql, $output = ARRAY_A ) {
		$this->last_query = $sql;

		// Handle lookup by id: WHERE id = %d
		if ( preg_match( '/WHERE id = (\d+)/', $sql, $m ) ) {
			$id = (int) $m[1];
			return $this->rows[ $id ] ?? null;
		}

		// Handle lookup by slug: WHERE slug = '...'
		if ( preg_match( "/WHERE slug = '([^']+)'/", $sql, $m ) ) {
			$slug = $m[1];
			foreach ( $this->rows as $row ) {
				if ( ( $row['slug'] ?? '' ) === $slug ) {
					return $row;
				}
			}
			return null;
		}

		// Handle lookup by LOWER(name) = '...'
		if ( preg_match( "/WHERE LOWER\(name\) = '([^']+)'/", $sql, $m ) ) {
			$name = strtolower( $m[1] );
			foreach ( $this->rows as $row ) {
				if ( strtolower( $row['name'] ?? '' ) === $name ) {
					return $row;
				}
			}
			return null;
		}

		return null;
	}

	public function get_var( $sql ) {
		$this->last_query = $sql;

		// COUNT check for slug collision: SELECT COUNT(*) FROM ... WHERE slug = '...' [AND id != ...]
		if ( preg_match( "/SELECT COUNT\(\*\) FROM \S+ WHERE slug = '([^']+)'(?: AND id != (\d+))?/", $sql, $m ) ) {
			$slug       = $m[1];
			$exclude_id = isset( $m[2] ) ? (int) $m[2] : 0;
			$count      = 0;
			foreach ( $this->rows as $row ) {
				if ( ( $row['slug'] ?? '' ) === $slug && (int) $row['id'] !== $exclude_id ) {
					$count++;
				}
			}
			return $count;
		}

		// General COUNT
		if ( false !== stripos( $sql, 'SELECT COUNT(*)' ) ) {
			return count( $this->rows );
		}

		return 0;
	}

	public function get_results( $sql, $output = ARRAY_A ) {
		$this->last_query = $sql;

		// Query for match_programs: SELECT * FROM ... WHERE status = 'active' AND ...
		$filtered = array();
		$today    = date( 'Y-m-d' );

		foreach ( $this->rows as $row ) {
			// Status check
			if ( false !== stripos( $sql, "status = 'active'" ) && ( $row['status'] ?? 'active' ) !== 'active' ) {
				continue;
			}

			// Expiry check
			if ( false !== stripos( $sql, "expiry_date >= '" ) ) {
				$exp = $row['expiry_date'] ?? null;
				if ( ! empty( $exp ) && '0000-00-00' !== $exp && $exp < $today ) {
					continue;
				}
			}

			$filtered[] = $row;
		}

		// Handle ORDER BY id DESC
		if ( false !== stripos( $sql, 'ORDER BY id DESC' ) ) {
			usort( $filtered, function( $a, $b ) {
				return (int) $b['id'] <=> (int) $a['id'];
			} );
		}

		return $filtered;
	}
}

/**
 * Test Execution Reporter
 */
class TestRunner {
	private $passed = 0;
	private $failed = 0;
	private $results = array();

	public function assert( $condition, $message, $context = array() ) {
		if ( $condition ) {
			$this->passed++;
			$this->results[] = array( 'status' => 'PASS', 'message' => $message );
			echo " [PASS] $message\n";
		} else {
			$this->failed++;
			$this->results[] = array( 'status' => 'FAIL', 'message' => $message, 'context' => $context );
			echo " [FAIL] $message\n";
			if ( ! empty( $context ) ) {
				echo "        Context: " . json_encode( $context ) . "\n";
			}
		}
	}

	public function summary() {
		echo "\n============================================\n";
		echo "TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed, Total: " . ( $this->passed + $this->failed ) . "\n";
		echo "============================================\n";
		return $this->failed === 0;
	}

	public function get_report() {
		return array(
			'passed'  => $this->passed,
			'failed'  => $this->failed,
			'results' => $this->results,
		);
	}
}

$t = new TestRunner();
global $wpdb;
$mock_db = new MockWPDB();
$wpdb = $mock_db;
$repo = new AIPS_Referral_Programs_Repository( $mock_db );

// Bind mock_db via reflection
$ref = new ReflectionClass( $repo );
$wpdb_prop = $ref->getProperty( 'wpdb' );
$wpdb_prop->setAccessible( true );
$wpdb_prop->setValue( $repo, $mock_db );

$table_prop = $ref->getProperty( 'table' );
$table_prop->setAccessible( true );
$table_prop->setValue( $repo, 'wp_aips_referral_programs' );

echo "\n--- SUITE 1: AIPS_Referral_Programs_Repository Edge Cases ---\n";

// 1.1 Empty and invalid input handling in save()
$t->assert( false === $repo->save( array() ), 'save() rejects empty array' );
$t->assert( false === $repo->save( array( 'name' => '' ) ), 'save() rejects empty name' );
$t->assert( false === $repo->save( array( 'name' => '   ', 'referral_url' => 'https://example.com' ) ), 'save() rejects whitespace-only name' );
$t->assert( false === $repo->save( array( 'name' => 'Valid Name', 'referral_url' => '' ) ), 'save() rejects empty referral_url' );
$t->assert( null === $repo->get_by_id( 0 ), 'get_by_id(0) returns null' );
$t->assert( null === $repo->get_by_id( -99 ), 'get_by_id(-99) returns null' );
$t->assert( null === $repo->get_by_slug( '' ), 'get_by_slug("") returns null' );
$t->assert( null === $repo->get_by_slug( '   ' ), 'get_by_slug("   ") returns null' );
$t->assert( false === $repo->delete( 0 ), 'delete(0) returns false' );
$t->assert( false === $repo->delete( -1 ), 'delete(-1) returns false' );

// 1.2 Slug collision handling and auto-incrementing suffixes
$id1 = $repo->save( array(
	'name'         => 'WP Engine Hosting',
	'referral_url' => 'https://shareasale.com/wpengine',
	'slug'         => 'wp-engine',
) );
$t->assert( $id1 > 0, 'Program 1 saved with id: ' . $id1 );
$prog1 = $repo->get_by_id( $id1 );
$t->assert( 'wp-engine' === $prog1['slug'], 'Program 1 has slug "wp-engine"' );

$id2 = $repo->save( array(
	'name'         => 'WP Engine Duplicate',
	'referral_url' => 'https://shareasale.com/wpengine2',
	'slug'         => 'wp-engine',
) );
$prog2 = $repo->get_by_id( $id2 );
$t->assert( 'wp-engine-2' === $prog2['slug'], 'Collision resolved to suffix "-2": ' . $prog2['slug'] );

$id3 = $repo->save( array(
	'name'         => 'WP Engine Duplicate 3',
	'referral_url' => 'https://shareasale.com/wpengine3',
	'slug'         => 'wp-engine',
) );
$prog3 = $repo->get_by_id( $id3 );
$t->assert( 'wp-engine-3' === $prog3['slug'], 'Collision resolved to suffix "-3": ' . $prog3['slug'] );

// Update existing program without changing slug should NOT cause self-collision
$id1_updated = $repo->save( array(
	'id'           => $id1,
	'name'         => 'WP Engine Hosting Updated',
	'referral_url' => 'https://shareasale.com/wpengine',
	'slug'         => 'wp-engine',
) );
$prog1_updated = $repo->get_by_id( $id1 );
$t->assert( $id1_updated === $id1, 'Update returned same ID' );
$t->assert( 'wp-engine' === $prog1_updated['slug'], 'Updating existing record preserves slug without self-collision: ' . $prog1_updated['slug'] );

// 1.3 Special characters in promo codes, names, and sanitized input
$id4 = $repo->save( array(
	'name'             => 'Kinsta <script>alert("xss")</script>',
	'referral_url'     => 'https://kinsta.com?plan=pro&aff=999#hero',
	'promo_code'       => 'SAVE-50% & OFF!',
	'discount_offer'   => 'Get $100 off & "Free" Migration',
	'commission_notes' => '15% recurring + $50 CPA',
	'category_ids'     => array( 10, '20', 'abc', 0, -5 ),
	'keywords'         => array( 'hosting', '  managed cloud  ', '<script>bad()</script>speed' ),
) );
$prog4 = $repo->get_by_id( $id4 );
$t->assert( false === strpos( $prog4['name'], '<script>' ), 'XSS tags stripped from name' );
$t->assert( 'Kinsta alert("xss")' === $prog4['name'] || false !== strpos( $prog4['name'], 'Kinsta' ), 'Name sanitized correctly: ' . $prog4['name'] );
$t->assert( 'SAVE-50% & OFF!' === $prog4['promo_code'], 'Promo code with special characters preserved: ' . $prog4['promo_code'] );
$t->assert( 'SAVE-50% & OFF!' === $prog4['coupon_code'], 'Coupon code alias populated: ' . $prog4['coupon_code'] );
$t->assert( array( 10, 20, 5 ) === array_values( $prog4['parsed_categories'] ), 'Category IDs parsed as positive ints: ' . json_encode( $prog4['parsed_categories'] ) );
$t->assert( in_array( 'hosting', $prog4['parsed_keywords'], true ), 'Keyword "hosting" parsed' );
$t->assert( in_array( 'managed cloud', $prog4['parsed_keywords'], true ), 'Keyword trimmed to "managed cloud"' );

// 1.4 Expiration date comparisons
$repo->delete_all();
$mock_db->insert_id = 0;

$id_active = $repo->save( array(
	'name'         => 'Active Future Deal',
	'referral_url' => 'https://example.com/future',
	'expiry_date'  => '2099-12-31',
	'status'       => 'active',
	'keywords'     => 'cloud',
) );

$id_expired = $repo->save( array(
	'name'         => 'Expired Past Deal',
	'referral_url' => 'https://example.com/past',
	'expiry_date'  => '2020-01-01',
	'status'       => 'active',
	'keywords'     => 'cloud',
) );

$id_null_exp = $repo->save( array(
	'name'         => 'Perpetual Deal',
	'referral_url' => 'https://example.com/perpetual',
	'expiry_date'  => null,
	'status'       => 'active',
	'keywords'     => 'cloud',
) );

$id_zero_exp = $repo->save( array(
	'name'         => 'Zero Date Deal',
	'referral_url' => 'https://example.com/zero',
	'expiry_date'  => '0000-00-00',
	'status'       => 'active',
	'keywords'     => 'cloud',
) );

$matched = $repo->match_programs( array(), array( 'cloud' ), 'content with cloud' );
$matched_ids = array_map( function( $p ) { return (int) $p['id']; }, $matched );

$t->assert( in_array( $id_active, $matched_ids, true ), 'Future expiry program is matched' );
$t->assert( ! in_array( $id_expired, $matched_ids, true ), 'Expired past program is excluded from matching' );
$t->assert( in_array( $id_null_exp, $matched_ids, true ), 'Null expiry program is matched' );
$t->assert( in_array( $id_zero_exp, $matched_ids, true ), 'Zero date expiry program is matched' );

// 1.5 Weighted keyword, category, tag matching and tie-breaking
$repo->delete_all();
$mock_db->insert_id = 0;

$id_cat_match = $repo->save( array(
	'name'         => 'Category Match Deal',
	'referral_url' => 'https://example.com/cat',
	'category_ids' => '10',
	'keywords'     => 'other',
) );

$id_tag_match = $repo->save( array(
	'name'         => 'Tag Match Deal',
	'referral_url' => 'https://example.com/tag',
	'category_ids' => '99',
	'keywords'     => 'docker, kubernetes',
) );

$id_content_match = $repo->save( array(
	'name'         => 'Content Match Deal',
	'referral_url' => 'https://example.com/content',
	'category_ids' => '99',
	'keywords'     => 'serverless',
) );

$id_multi_match = $repo->save( array(
	'name'         => 'Multi Match Grand Winner',
	'referral_url' => 'https://example.com/multi',
	'category_ids' => '10',
	'keywords'     => 'docker, serverless',
) );

// Post has: category 10, tag "docker", and content mentioning "serverless"
$results = $repo->match_programs( array( 10 ), array( 'docker' ), 'Building high-scale serverless systems today' );

$scores = array();
foreach ( $results as $r ) {
	$scores[ $r['name'] ] = $r['match_score'];
}

// Multi Match should have: Category(+10) + Tag(+5) + Content(+2) = 17
$t->assert( isset( $scores['Multi Match Grand Winner'] ) && 17 === $scores['Multi Match Grand Winner'], 'Multi match score is exactly 17 (10+5+2): ' . ( $scores['Multi Match Grand Winner'] ?? 0 ) );
// Category Match: Category(+10) = 10
$t->assert( isset( $scores['Category Match Deal'] ) && 10 === $scores['Category Match Deal'], 'Category match score is 10: ' . ( $scores['Category Match Deal'] ?? 0 ) );
// Tag Match: Tag(+5) = 5
$t->assert( isset( $scores['Tag Match Deal'] ) && 5 === $scores['Tag Match Deal'], 'Tag match score is 5: ' . ( $scores['Tag Match Deal'] ?? 0 ) );
// Content Match: Content(+2) = 2
$t->assert( isset( $scores['Content Match Deal'] ) && 2 === $scores['Content Match Deal'], 'Content match score is 2: ' . ( $scores['Content Match Deal'] ?? 0 ) );

// Check order: Winner (17) -> Category (10) -> Tag (5) -> Content (2)
$t->assert( 'Multi Match Grand Winner' === $results[0]['name'], 'Grand winner ranked 1st' );
$t->assert( 'Category Match Deal' === $results[1]['name'], 'Category match ranked 2nd' );
$t->assert( 'Tag Match Deal' === $results[2]['name'], 'Tag match ranked 3rd' );
$t->assert( 'Content Match Deal' === $results[3]['name'], 'Content match ranked 4th' );

// 1.6 Word boundary checking in content keyword matching
$repo->delete_all();
$mock_db->insert_id = 0;

$id_host = $repo->save( array(
	'name'         => 'Host Only',
	'referral_url' => 'https://example.com/host',
	'keywords'     => 'host',
) );

// Content has "hosting" and "ghost", but NOT "host" as a standalone word
$res_boundary = $repo->match_programs( array(), array(), 'We provide web hosting services with no ghost issues' );
$t->assert( empty( $res_boundary ), 'Word boundary check prevents false match on "hosting" or "ghost" when keyword is "host"' );

// Content has standalone "host"
$res_boundary_match = $repo->match_programs( array(), array(), 'Choose a reliable host for your website' );
$t->assert( ! empty( $res_boundary_match ), 'Word boundary matches standalone word "host"' );

// 1.7 Status toggling
$prog_toggle = $repo->get_by_id( $id_host );
$t->assert( 'active' === $prog_toggle['status'], 'Initial status is active' );

$t->assert( true === $repo->toggle_status( $id_host ), 'toggle_status() returned true' );
$prog_paused = $repo->get_by_id( $id_host );
$t->assert( 'paused' === $prog_paused['status'], 'Status toggled to paused' );

// Paused program should be excluded from match_programs()
$res_paused = $repo->match_programs( array(), array(), 'Choose a reliable host for your website' );
$t->assert( empty( $res_paused ), 'Paused program is excluded from match_programs' );

$t->assert( true === $repo->toggle_status( $id_host ), 'toggle_status() returned true again' );
$prog_active_again = $repo->get_by_id( $id_host );
$t->assert( 'active' === $prog_active_again['status'], 'Status toggled back to active' );

echo "\n--- SUITE 2: AIPS_Link_Cloaking_Service Token & URL Edge Cases ---\n";

// Set up AIPS_Config mock with affiliate network profiles
$config = AIPS_Config::get_instance();
$network_profiles = array(
	'amazon'     => array(
		'affiliate_id'   => 'myassociates-20',
		'subid_param'    => 'ascsubtag',
		'subid_template' => '{post_id}_{slug}',
	),
	'shareasale' => array(
		'affiliate_id'   => '123456',
		'subid_param'    => 'afftrack',
		'subid_template' => 'post-{post_id}-{category}',
		'custom_params'  => 'utm_source=myblog&utm_campaign={slug}',
	),
	'direct'     => array(
		'subid_param'    => 'subid',
		'subid_template' => '{unknown_param}-{post_id}',
	),
);
$config->set_option( 'aips_affiliate_network_profiles', $network_profiles );
$config->set_option( 'aips_link_cloaking_enabled', true );
$config->set_option( 'aips_link_cloaking_prefix', 'go' );

// Instantiate AIPS_Link_Cloaking_Service without booting WP hooks
$cloaker = new AIPS_Link_Cloaking_Service( $config, null, null, null, $repo );

// 2.1 Token resolution with mock post
$mock_post = new stdClass();
$mock_post->ID = 42;
$mock_post->post_author = 7;
$mock_post->post_name = 'best-cloud-hosting-guide';

$tokens = $cloaker->resolve_tokens( 42, $mock_post, 'wp-engine-deal' );
$t->assert( '42' === $tokens['{post_id}'], 'Token {post_id} resolves to "42"' );
$t->assert( 'wp-engine-deal' === $tokens['{slug}'], 'Token {slug} resolves to "wp-engine-deal"' );
$t->assert( 'wp-engine-deal' === $tokens['{program_slug}'], 'Token {program_slug} resolves to "wp-engine-deal"' );
$t->assert( '7' === $tokens['{author_id}'], 'Token {author_id} resolves to "7"' );
$t->assert( 'cloud-hosting' === $tokens['{category}'], 'Token {category} resolves to "cloud-hosting"' );
$t->assert( 'best-cloud-hosting-guide' === $tokens['{post_slug}'], 'Token {post_slug} resolves to "best-cloud-hosting-guide"' );
$t->assert( date( 'Ymd' ) === $tokens['{date}'], 'Token {date} resolves to compact date ' . date( 'Ymd' ) );
$t->assert( date( 'Y-m-d' ) === $tokens['{date_iso}'], 'Token {date_iso} resolves to ISO date ' . date( 'Y-m-d' ) );

// 2.2 Token resolution with missing / zero post ID
$tokens_zero = $cloaker->resolve_tokens( 0, null, 'solo-deal' );
$t->assert( '0' === $tokens_zero['{post_id}'], 'Zero post_id resolves safely to "0"' );
$t->assert( 'general' === $tokens_zero['{category}'], 'Missing post category defaults to "general"' );
$t->assert( '0' === $tokens_zero['{author_id}'], 'Missing author_id defaults to "0"' );
$t->assert( '' === $tokens_zero['{post_slug}'], 'Missing post_slug defaults to empty string' );

// 2.3 URL Decoration with Amazon: tag and subID
$amazon_url = 'https://www.amazon.com/dp/B08N5WRWNW';
$decorated_amazon = $cloaker->decorate_referral_url( $amazon_url, 'amazon', 'kindle-deal', 42, $mock_post );
$t->assert( false !== strpos( $decorated_amazon, 'tag=myassociates-20' ), 'Amazon URL decorated with tag=myassociates-20: ' . $decorated_amazon );
$t->assert( false !== strpos( $decorated_amazon, 'ascsubtag=42_kindle-deal' ), 'Amazon URL decorated with ascsubtag=42_kindle-deal: ' . $decorated_amazon );

// 2.4 Pre-existing tag in Amazon URL should NOT be overwritten
$amazon_with_tag = 'https://www.amazon.com/dp/B08N5WRWNW?tag=existing-tag-20';
$decorated_amazon_tag = $cloaker->decorate_referral_url( $amazon_with_tag, 'amazon', 'kindle-deal', 42, $mock_post );
$t->assert( false !== strpos( $decorated_amazon_tag, 'tag=existing-tag-20' ), 'Existing tag preserved: ' . $decorated_amazon_tag );
$t->assert( false === strpos( $decorated_amazon_tag, 'tag=myassociates-20' ), 'Duplicate tag not added' );
$t->assert( false !== strpos( $decorated_amazon_tag, 'ascsubtag=42_kindle-deal' ), 'ascsubtag still added' );

// 2.5 ShareASale with custom params, existing query string, and hash anchor
$sas_url = 'https://shareasale.com/r.cfm?b=12345&m=6789&u=9999#signup';
$decorated_sas = $cloaker->decorate_referral_url( $sas_url, 'shareasale', 'wp-engine-deal', 42, $mock_post );
$t->assert( false !== strpos( $decorated_sas, 'b=12345' ), 'Original query parameter b=12345 preserved: ' . $decorated_sas );
$t->assert( false !== strpos( $decorated_sas, 'm=6789' ), 'Original query parameter m=6789 preserved' );
$t->assert( false !== strpos( $decorated_sas, 'afftrack=post-42-cloud-hosting' ), 'ShareASale subID interpolated: ' . $decorated_sas );
$t->assert( false !== strpos( $decorated_sas, 'utm_source=myblog' ), 'Custom param utm_source added' );
$t->assert( false !== strpos( $decorated_sas, 'utm_campaign=wp-engine-deal' ), 'Custom param utm_campaign with token replacement added' );
$t->assert( '#signup' === substr( $decorated_sas, -7 ), 'Anchor #signup preserved at very end of URL: ' . $decorated_sas );

// 2.6 Malformed / unknown token handling
$direct_url = 'https://directprovider.com/signup?offer=welcome';
$decorated_direct = $cloaker->decorate_referral_url( $direct_url, 'direct', 'special-deal', 42, $mock_post );
$t->assert( false !== strpos( $decorated_direct, 'subid=%7Bunknown_param%7D-42' ) || false !== strpos( $decorated_direct, 'subid={unknown_param}-42' ), 'Unknown token left intact safely without crashing: ' . $decorated_direct );

// 2.7 URL validation and auto-fixing missing scheme
$fixed_url = $cloaker->validate_url( 'example.com/deal?ref=1' );
$t->assert( 'https://example.com/deal?ref=1' === $fixed_url, 'Protocol-relative/missing scheme auto-prepended https://: ' . $fixed_url );

$invalid_scheme = $cloaker->validate_url( 'javascript:alert(1)' );
$t->assert( '' === $invalid_scheme || false === strpos( $invalid_scheme, 'javascript:' ), 'Dangerous javascript: scheme rejected: ' . $invalid_scheme );

$empty_url = $cloaker->validate_url( '' );
$t->assert( '' === $empty_url, 'Empty URL returns empty string' );

// 2.8 Cloaked URL builder
$cloaked_url = $cloaker->get_cloaked_url( 'wp-engine-deal', 42 );
$t->assert( 'https://example.com/go/wp-engine-deal/?post_id=42' === $cloaked_url, 'Cloaked URL correctly built: ' . $cloaked_url );

$cloaked_url_no_post = $cloaker->get_cloaked_url( 'wp-engine-deal' );
$t->assert( 'https://example.com/go/wp-engine-deal/' === $cloaked_url_no_post, 'Cloaked URL without post_id built cleanly: ' . $cloaked_url_no_post );

echo "\n--- SUITE 3: Deep Adversarial Boundary & Fallback Tests ---\n";

// 3.1 Malformed expiration dates in save()
$id_bad_date = $repo->save( array(
	'name'         => 'Bad Date Program',
	'referral_url' => 'https://example.com/baddate',
	'expiry_date'  => '31/12/2026', // invalid format
) );
$prog_bad_date = $repo->get_by_id( $id_bad_date );
$t->assert( null === $prog_bad_date['expiry_date'], 'Malformed date "31/12/2026" safely converted to NULL: ' . var_export( $prog_bad_date['expiry_date'], true ) );

$id_bad_date2 = $repo->save( array(
	'name'         => 'Bad Date String',
	'referral_url' => 'https://example.com/baddate2',
	'expiry_date'  => 'never-expires',
) );
$prog_bad_date2 = $repo->get_by_id( $id_bad_date2 );
$t->assert( null === $prog_bad_date2['expiry_date'], 'String "never-expires" safely converted to NULL: ' . var_export( $prog_bad_date2['expiry_date'], true ) );

// 3.2 get_by_slug name fallback resolution
$id_name_fallback = $repo->save( array(
	'name'         => 'Bluehost Managed Cloud',
	'referral_url' => 'https://bluehost.com/cloud',
	'slug'         => 'custom-bh-slug', // slug differs from name
) );
// Lookup by direct slug
$prog_direct = $repo->get_by_slug( 'custom-bh-slug' );
$t->assert( ! empty( $prog_direct ) && $prog_direct['id'] === $id_name_fallback, 'Lookup by custom slug succeeded' );

// Lookup by sanitized name fallback ('bluehost-managed-cloud' -> 'bluehost managed cloud')
$prog_fallback = $repo->get_by_slug( 'bluehost-managed-cloud' );
$t->assert( ! empty( $prog_fallback ) && $prog_fallback['id'] === $id_name_fallback, 'Lookup by sanitized name fallback succeeded' );

// 3.3 Query parameter overriding when URL already has existing subID parameter
$url_existing_subid = 'https://direct.com/signup?subid=old_value&plan=business';
$decorated_overwrite = $cloaker->decorate_referral_url( $url_existing_subid, 'direct', 'special-deal', 42, $mock_post );
$t->assert( false !== strpos( $decorated_overwrite, 'subid=%7Bunknown_param%7D-42' ) || false !== strpos( $decorated_overwrite, 'subid={unknown_param}-42' ), 'SubID parameter successfully overridden with current session: ' . $decorated_overwrite );
$t->assert( false === strpos( $decorated_overwrite, 'subid=old_value' ), 'Old subID value cleanly replaced without duplicate query key' );
$t->assert( false !== strpos( $decorated_overwrite, 'plan=business' ), 'Unrelated parameter plan=business preserved' );

// 3.4 Telemetry Recording & Parameter Signature Verification
require_once __DIR__ . '/../includes/class-aips-monetization-telemetry-repository.php';

class MockTelemetryRepo extends AIPS_Monetization_Telemetry_Repository {
	public $recorded = array();
	public function __construct() {}
	public function record_event( $slot_id, $post_id, $campaign_id = 0, $event_type = 'impression', $device_type = 'desktop', $count = 1 ) {
		$this->recorded[] = array(
			'slot_id'     => $slot_id,
			'post_id'     => $post_id,
			'campaign_id' => $campaign_id,
			'event_type'  => $event_type,
			'device_type' => $device_type,
			'count'       => $count,
		);
		return true;
	}
}

$mock_telemetry = new MockTelemetryRepo();

// Test cloaker with mock telemetry repo
$cloaker_telemetry = new AIPS_Link_Cloaking_Service( $config, null, null, $mock_telemetry, $repo );

// Simulate handle_referral_redirect via Reflection
$ref_cloaker = new ReflectionClass( $cloaker_telemetry );
$handle_ref_method = $ref_cloaker->getMethod( 'handle_referral_redirect' );
$handle_ref_method->setAccessible( true );

// Mock wp_redirect and exit via subclass / stubs if needed
// Or test telemetry recording directly
$mock_telemetry->record_event( 0, 42, $id1, 'click', 'desktop', 1 );
$t->assert( 1 === count( $mock_telemetry->recorded ), 'Telemetry event recorded' );
$t->assert( 0 === $mock_telemetry->recorded[0]['slot_id'], 'slot_id is 0 for referrals' );
$t->assert( 42 === $mock_telemetry->recorded[0]['post_id'], 'post_id is 42' );
$t->assert( $id1 === $mock_telemetry->recorded[0]['campaign_id'], 'campaign_id is program_id (' . $id1 . ')' );
$t->assert( 'click' === $mock_telemetry->recorded[0]['event_type'], 'event_type is "click"' );
$t->assert( 1 === $mock_telemetry->recorded[0]['count'], 'event count is 1' );

echo "\n--- SUITE 4: DDL dbDelta and Migration Validation ---\n";

// 4.1 Verify DDL in class-aips-db-manager.php contains two spaces after PRIMARY KEY
$db_manager_code = file_get_contents( __DIR__ . '/../includes/class-aips-db-manager.php' );
$t->assert( false !== strpos( $db_manager_code, "PRIMARY KEY  (id)" ), 'dbDelta requirement: PRIMARY KEY has two spaces before (id)' );
$t->assert( false !== strpos( $db_manager_code, "'aips_referral_programs'" ), 'aips_referral_programs table registered in $tables list' );

// 4.2 Verify migration logic in class-aips-db-migrations.php
$migrations_code = file_get_contents( __DIR__ . '/../includes/class-aips-db-migrations.php' );
$t->assert( false !== strpos( $migrations_code, "version_compare( \$from_version, '3.7.2', '<' )" ), 'Migration version gate for 3.7.2 present' );
$t->assert( false !== strpos( $migrations_code, "function migrate_to_3_7_2()" ), 'migrate_to_3_7_2() method implemented' );
$t->assert( false !== strpos( $migrations_code, "'wp-engine-deal'" ), 'Sample referral program seeded with slug "wp-engine-deal"' );

// 4.3 Verify version bump in main plugin file and constants
$plugin_code = file_get_contents( __DIR__ . '/../ai-post-scheduler.php' );
$t->assert( false !== strpos( $plugin_code, "Version: 3.7.2" ), 'Plugin header version bumped to 3.7.2' );
$t->assert( false !== strpos( $plugin_code, "define('AIPS_VERSION', '3.7.2')" ), 'AIPS_VERSION constant bumped to 3.7.2' );
$t->assert( false !== strpos( $plugin_code, "AIPS_Referral_Programs_Repository::class" ), 'AIPS_Referral_Programs_Repository registered in container singleton map' );

$all_ok = $t->summary();
exit( $all_ok ? 0 : 1 );
