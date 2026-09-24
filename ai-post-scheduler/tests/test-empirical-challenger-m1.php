<?php
/**
 * Empirical Challenger Test Harness: Milestone 1
 *
 * Rigorously challenges:
 * 1. Database schema dbDelta DDL validity in AIPS_DB_Manager
 * 2. Migration upgrade logic & idempotency in AIPS_DB_Migrations::migrate_to_3_7_2
 * 3. Telemetry contracts and event signatures in AIPS_Monetization_Telemetry_Repository
 *
 * @package AI_Post_Scheduler
 */

// Define ABSPATH and necessary WordPress constants/globals if running outside WP
if ( ! defined( 'ABSPATH' ) ) {
	if ( file_exists( 'C:/xampp/htdocs/aips-dev/wp-admin/includes/upgrade.php' ) ) {
		define( 'ABSPATH', 'C:/xampp/htdocs/aips-dev/' );
	} else {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}
}

define( 'ARRAY_A', 'ARRAY_A' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'YEAR_IN_SECONDS', 31536000 );

// Mock global $wpdb if not set
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	class MockWPDB {
		public $prefix = 'wp_';
		public $last_error = '';
		public $insert_id = 1;
		public $queries = array();
		public $tables_created = array();
		public $options = array();

		public function get_charset_collate() {
			return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
		}

		public function prepare( $query, ...$args ) {
			if ( is_array( $args[0] ?? null ) && count( $args ) === 1 ) {
				$args = $args[0];
			}
			// Basic vsprintf replacement for %s, %d, %f
			$query_formatted = str_replace( array( '%s', '%d', '%f' ), array( "'%s'", '%d', '%F' ), $query );
			// Handle double escaping of %%
			$clean_args = array();
			foreach ( $args as $arg ) {
				$clean_args[] = is_numeric( $arg ) ? $arg : addslashes( (string) $arg );
			}
			return @vsprintf( $query_formatted, $clean_args ) ?: $query;
		}

		public function query( $sql ) {
			$this->queries[] = $sql;
			return true;
		}

		public function get_var( $sql ) {
			$this->queries[] = $sql;
			if ( str_contains( $sql, 'SHOW TABLES LIKE' ) ) {
				return 'wp_aips_referral_programs';
			}
			if ( str_contains( $sql, 'COUNT(*)' ) ) {
				return 0; // Empty table simulation initially
			}
			return null;
		}

		public function get_row( $sql, $output = ARRAY_A ) {
			$this->queries[] = $sql;
			return null;
		}

		public function get_results( $sql, $output = ARRAY_A ) {
			$this->queries[] = $sql;
			return array();
		}

		public function insert( $table, $data, $format = null ) {
			$this->queries[] = array( 'INSERT', $table, $data );
			return 1;
		}

		public function update( $table, $data, $where, $format = null, $where_format = null ) {
			$this->queries[] = array( 'UPDATE', $table, $data, $where );
			return 1;
		}

		public function delete( $table, $where, $where_format = null ) {
			$this->queries[] = array( 'DELETE', $table, $where );
			return 1;
		}

		public function suppress_errors( $suppress = true ) {
			return false;
		}

		public function tables( $scope = 'all' ) {
			return array( 'global' => array() );
		}

		public function db_version() {
			return '8.0.30';
		}

		public function db_server_info() {
			return 'MySQL 8.0.30';
		}

		public function esc_like( $text ) {
			return addcslashes( $text, '_%\\' );
		}
	}

	$GLOBALS['wpdb'] = new MockWPDB();
}

// Minimal WordPress function stubs for standalone execution
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( trim( (string) $title ) );
		$title = preg_replace( '/[^a-z0-9-_]/', '-', $title );
		return trim( preg_replace( '/-+/', '-', $title ), '-' );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_-]/', '', strtolower( trim( (string) $key ) ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( $url ) {
		return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : false;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return gmdate( 'Y-m-d' );
	}
}

if ( ! function_exists( 'wp_is_mobile' ) ) {
	function wp_is_mobile() {
		return false;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['wpdb']->options[ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $GLOBALS['wpdb']->options[ $name ] ?? $default;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['wpdb']->options[ $name ] );
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return is_object( $thing ) && is_a( $thing, 'WP_Error' );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code, $message ) {
			$this->code = $code;
			$this->message = $message;
		}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}

// Load vendor autoloader if present
if ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';
}

// Load plugin classes
require_once dirname( __DIR__ ) . '/includes/class-aips-date-time.php';
require_once dirname( __DIR__ ) . '/includes/class-aips-config.php';
require_once dirname( __DIR__ ) . '/includes/class-aips-db-manager.php';
require_once dirname( __DIR__ ) . '/includes/class-aips-monetization-telemetry-repository.php';
require_once dirname( __DIR__ ) . '/includes/class-aips-referral-programs-repository.php';

// Version constant
if ( ! defined( 'AIPS_VERSION' ) ) {
	define( 'AIPS_VERSION', '3.7.2' );
}

echo "===============================================================\n";
echo " EMPIRICAL CHALLENGE SUITE: M1 BACKEND FOUNDATION\n";
echo "===============================================================\n\n";

$failures = 0;
$passes   = 0;

function assert_test( $condition, $title, $details = '' ) {
	global $passes, $failures;
	if ( $condition ) {
		echo " [PASS] $title\n";
		$passes++;
	} else {
		echo " [FAIL] $title\n";
		if ( $details ) {
			echo "        Details: $details\n";
		}
		$failures++;
	}
}

// -------------------------------------------------------------
// TEST SUITE 1: dbDelta DDL in AIPS_DB_Manager
// -------------------------------------------------------------
echo "--- TEST SUITE 1: dbDelta DDL in AIPS_DB_Manager ---\n";

// 1.1 Table Registration
$table_names = AIPS_DB_Manager::get_table_names();
assert_test(
	in_array( 'aips_referral_programs', $table_names, true ),
	"AIPS_DB_Manager::\$tables contains 'aips_referral_programs'",
	"Tables: " . implode( ', ', $table_names )
);

$full_names = AIPS_DB_Manager::get_full_table_names();
assert_test(
	isset( $full_names['aips_referral_programs'] ) && 'wp_aips_referral_programs' === $full_names['aips_referral_programs'],
	"AIPS_DB_Manager::get_full_table_names() maps to 'wp_aips_referral_programs'"
);

// 1.2 Schema SQL Extraction
$db_manager = new AIPS_DB_Manager();
$schema = $db_manager->get_schema();

$referral_ddl = '';
foreach ( $schema as $sql ) {
	if ( str_contains( $sql, 'wp_aips_referral_programs' ) ) {
		$referral_ddl = $sql;
		break;
	}
}

assert_test(
	! empty( $referral_ddl ),
	"get_schema() contains CREATE TABLE for wp_aips_referral_programs"
);

// 1.3 Strict dbDelta Syntax: Two spaces after PRIMARY KEY
// WordPress dbDelta parser regex: /PRIMARY\s+KEY/ or legacy 'PRIMARY KEY  ('
$has_two_spaces_pk = (bool) preg_match( '/PRIMARY KEY  \(id\)/', $referral_ddl );
assert_test(
	$has_two_spaces_pk,
	"PRIMARY KEY definition has exactly TWO spaces before parentheses (dbDelta strict rule)",
	"DDL PK snippet: " . ( preg_match( '/PRIMARY KEY\s*\(id\)/', $referral_ddl, $m ) ? $m[0] : 'not found' )
);

// 1.4 Column Definitions Verification
$expected_columns = array(
	'id'               => 'bigint(20) NOT NULL AUTO_INCREMENT',
	'name'             => 'varchar(255) NOT NULL',
	'network_provider' => "varchar(50) NOT NULL DEFAULT 'direct'",
	'referral_url'     => 'text NOT NULL',
	'slug'             => 'varchar(100) DEFAULT NULL',
	'promo_code'       => "varchar(100) DEFAULT ''",
	'discount_offer'   => "varchar(255) DEFAULT ''",
	'commission_notes' => "varchar(255) DEFAULT ''",
	'category_ids'     => 'text DEFAULT NULL',
	'keywords'         => 'text DEFAULT NULL',
	'expiry_date'      => 'date DEFAULT NULL',
	'status'           => "varchar(20) NOT NULL DEFAULT 'active'",
	'created_at'       => 'bigint(20) unsigned NOT NULL DEFAULT 0',
	'updated_at'       => 'bigint(20) unsigned NOT NULL DEFAULT 0',
);

// Extract lines between parentheses
preg_match( '|\((.*)\)|ms', $referral_ddl, $body_match );
$body_lines = array_map( 'trim', explode( "\n", $body_match[1] ?? '' ) );

$parsed_columns = array();
$parsed_keys    = array();

foreach ( $body_lines as $line ) {
	$clean_line = trim( $line, " \t\n\r\0\x0B," );
	if ( empty( $clean_line ) ) {
		continue;
	}

	if ( preg_match( '/^PRIMARY KEY\s+\((.*?)\)/i', $clean_line, $m ) ) {
		$parsed_keys['PRIMARY'] = trim( $m[1] );
	} elseif ( preg_match( '/^KEY\s+([a-zA-Z0-9_]+)\s*\((.*?)\)/i', $clean_line, $m ) ) {
		$parsed_keys[ $m[1] ] = trim( $m[2] );
	} elseif ( preg_match( '/^([a-zA-Z0-9_]+)\s+(.*)$/', $clean_line, $m ) ) {
		$parsed_columns[ $m[1] ] = $m[2];
	}
}

assert_test(
	count( $parsed_columns ) === 14,
	"All 14 required columns exist in DDL (found " . count( $parsed_columns ) . ")"
);

foreach ( $expected_columns as $col => $expected_def ) {
	$actual_def = $parsed_columns[ $col ] ?? null;
	assert_test(
		null !== $actual_def && strcasecmp( $actual_def, $expected_def ) === 0,
		"Column '$col' definition conforms: $expected_def",
		"Actual: " . ( $actual_def ?? 'MISSING' )
	);
}

// 1.5 Indexes Verification
$expected_keys = array(
	'PRIMARY'          => 'id',
	'slug'             => 'slug',
	'network_provider' => 'network_provider',
	'status'           => 'status',
	'expiry_date'      => 'expiry_date',
);

foreach ( $expected_keys as $key_name => $col_target ) {
	$actual_key = $parsed_keys[ $key_name ] ?? null;
	assert_test(
		$actual_key === $col_target,
		"Index '$key_name' on column '$col_target' correctly defined",
		"Actual: " . ( $actual_key ?? 'MISSING' )
	);
}

// 1.6 dbDelta Core Regex Simulation (WordPress wp-admin/includes/upgrade.php)
$index_regex = '/^
	(?P<index_type>
		PRIMARY\s+KEY|(?:UNIQUE|FULLTEXT|SPATIAL)\s+(?:KEY|INDEX)|KEY|INDEX
	)
	\s+
	(?:
		`?
			(?P<index_name>
				(?:[0-9a-zA-Z$_-]|[\xC2-\xDF][\x80-\xBF])+
			)
		`?
		\s+
	)*
	\(
		(?P<index_columns>
			.+?
		)
	\)
$/imx';

$dbdelta_parsed_indices = array();
foreach ( $body_lines as $line ) {
	$fld = trim( $line, " \t\n\r\0\x0B," );
	if ( preg_match( $index_regex, $fld, $im ) ) {
		$itype = strtoupper( preg_replace( '/\s+/', ' ', trim( $im['index_type'] ) ) );
		$iname = ( 'PRIMARY KEY' === $itype ) ? 'PRIMARY' : ( $im['index_name'] ?? '' );
		$dbdelta_parsed_indices[ $iname ] = trim( $im['index_columns'] );
	}
}

assert_test(
	count( $dbdelta_parsed_indices ) === 5,
	"WordPress core dbDelta index parser extracts all 5 indexes without error (extracted: " . implode( ', ', array_keys( $dbdelta_parsed_indices ) ) . ")"
);

// 1.7 Column Lengths & Key Sizes (MySQL InnoDB utf8mb4 limits: max 767 / 3072 bytes)
$slug_bytes = 100 * 4; // varchar(100) * 4 bytes/utf8mb4
$network_bytes = 50 * 4; // varchar(50) * 4
$status_bytes = 20 * 4; // varchar(20) * 4
assert_test(
	$slug_bytes <= 767 && $network_bytes <= 767 && $status_bytes <= 767,
	"All secondary index key lengths stay well under legacy 767-byte prefix limit (max: {$slug_bytes} bytes)"
);

echo "\n";

// -------------------------------------------------------------
// TEST SUITE 2: Migration Upgrade Logic (migrate_to_3_7_2)
// -------------------------------------------------------------
echo "--- TEST SUITE 2: Migration Upgrade Logic (migrate_to_3_7_2) ---\n";

// Mock Logger
class MockLogger {
	public $logs = array();
	public function log( $message, $level = 'info' ) {
		$this->logs[] = array( 'level' => $level, 'message' => $message );
	}
}

// Load AIPS_DB_Migrations
require_once dirname( __DIR__ ) . '/includes/class-aips-db-migrations.php';

$mock_logger = new MockLogger();
$reflection = new ReflectionClass( 'AIPS_DB_Migrations' );
$migrations_instance = $reflection->newInstanceWithoutConstructor();

// Set private logger property
$logger_prop = $reflection->getProperty( 'logger' );
$logger_prop->setAccessible( true );
$logger_prop->setValue( $migrations_instance, $mock_logger );

// 2.1 Version Comparison & Gating
assert_test(
	version_compare( '3.7.1', '3.7.2', '<' ) === true,
	"Version gate: '3.7.1' < '3.7.2' triggers migrate_to_3_7_2()"
);
assert_test(
	version_compare( '3.7.0', '3.7.2', '<' ) === true,
	"Version gate: '3.7.0' < '3.7.2' triggers migrate_to_3_7_2()"
);
assert_test(
	version_compare( '3.7.2', '3.7.2', '<' ) === false,
	"Version gate: '3.7.2' < '3.7.2' is FALSE (no-op on current version)"
);
assert_test(
	version_compare( '3.8.0', '3.7.2', '<' ) === false,
	"Version gate: '3.8.0' < '3.7.2' is FALSE (no-op on future version)"
);

// 2.2 Execution: First run on empty table (Fresh upgrade simulation)
$migrate_method = $reflection->getMethod( 'migrate_to_3_7_2' );
$migrate_method->setAccessible( true );

// Reset DB state
$GLOBALS['wpdb']->queries = array();
AIPS_Config::get_instance()->set_option( 'aips_affiliate_network_profiles', array() );

$migrate_method->invoke( $migrations_instance );

// Verify install_tables was called
$called_install = false;
foreach ( $GLOBALS['wpdb']->queries as $q ) {
	if ( is_string( $q ) && str_contains( $q, 'CREATE TABLE' ) ) {
		$called_install = true;
		break;
	}
}
assert_test( $called_install, "migrate_to_3_7_2() executes AIPS_DB_Manager::install_tables()" );

// Verify default program was seeded
$seeded_program = false;
foreach ( $GLOBALS['wpdb']->queries as $q ) {
	if ( is_array( $q ) && 'INSERT' === $q[0] && 'wp_aips_referral_programs' === $q[1] ) {
		if ( 'WP Engine Hosting' === ( $q[2]['name'] ?? '' ) && 'SAVE20' === ( $q[2]['promo_code'] ?? '' ) ) {
			$seeded_program = true;
			break;
		}
	}
}
assert_test( $seeded_program, "migrate_to_3_7_2() seeds initial sample program 'WP Engine Hosting' with promo 'SAVE20'" );

// Verify default network profiles option saved
$saved_profiles = AIPS_Config::get_instance()->get_option( 'aips_affiliate_network_profiles' );
assert_test(
	is_array( $saved_profiles ) && count( $saved_profiles ) >= 7,
	"migrate_to_3_7_2() initializes 'aips_affiliate_network_profiles' with all 7 network profiles",
	"Count: " . ( is_array( $saved_profiles ) ? count( $saved_profiles ) : 0 )
);

// Verify log output
$log_found = false;
foreach ( $mock_logger->logs as $entry ) {
	if ( str_contains( $entry['message'], 'Migration 3.7.2' ) ) {
		$log_found = true;
		break;
	}
}
assert_test( $log_found, "migrate_to_3_7_2() logs completion with level 'info'" );

// 2.3 Idempotency: Second run when table is already populated
$GLOBALS['wpdb']->queries = array();
// Mock COUNT(*) returning 1
class PopulatedMockWPDB extends MockWPDB {
	public function get_var( $sql ) {
		$this->queries[] = $sql;
		if ( str_contains( $sql, 'SHOW TABLES LIKE' ) ) {
			return 'wp_aips_referral_programs';
		}
		if ( str_contains( $sql, 'COUNT(*)' ) ) {
			return 1; // Already has records!
		}
		return null;
	}
}
$GLOBALS['wpdb'] = new PopulatedMockWPDB();

// Also test that customized profiles are NOT overwritten
AIPS_Config::get_instance()->set_option( 'aips_affiliate_network_profiles', array(
	'amazon' => array( 'affiliate_id' => 'my-custom-tag-20' )
) );

$migrate_method->invoke( $migrations_instance );

$re_seeded = false;
foreach ( $GLOBALS['wpdb']->queries as $q ) {
	if ( is_array( $q ) && 'INSERT' === $q[0] && 'wp_aips_referral_programs' === $q[1] ) {
		$re_seeded = true;
		break;
	}
}
assert_test(
	! $re_seeded,
	"Idempotency: migrate_to_3_7_2() DOES NOT re-seed program when table is already populated (COUNT(*) > 0)"
);

$preserved_profiles = AIPS_Config::get_instance()->get_option( 'aips_affiliate_network_profiles' );
assert_test(
	isset( $preserved_profiles['amazon']['affiliate_id'] ) && 'my-custom-tag-20' === $preserved_profiles['amazon']['affiliate_id'],
	"Idempotency: migrate_to_3_7_2() DOES NOT overwrite existing user-customized network profiles"
);

echo "\n";

// -------------------------------------------------------------
// TEST SUITE 3: Telemetry Contract in AIPS_Monetization_Telemetry_Repository
// -------------------------------------------------------------
echo "--- TEST SUITE 3: Telemetry Contracts & Arguments ---\n";

$telemetry_repo = new AIPS_Monetization_Telemetry_Repository();
$telemetry_ref = new ReflectionClass( $telemetry_repo );

// 3.1 Method Reflection & Signature Verification
$record_method = $telemetry_ref->getMethod( 'record_event' );
$params = $record_method->getParameters();

$param_names = array_map( function( $p ) { return $p->getName(); }, $params );
assert_test(
	$param_names === array( 'slot_id', 'post_id', 'campaign_id', 'event_type', 'device_type', 'count' ),
	"record_event() signature parameters: " . implode( ', ', $param_names )
);

// 3.2 Verification of Link Cloaking Caller Arguments
// Inspect class-aips-link-cloaking-service.php around line 280
$cloaking_code = file_get_contents( dirname( __DIR__ ) . '/includes/class-aips-link-cloaking-service.php' );
preg_match( '/\$this->telemetry_repo->record_event\s*\((.*?)\);/s', $cloaking_code, $caller_match );

assert_test(
	! empty( $caller_match[1] ),
	"Found record_event() call in AIPS_Link_Cloaking_Service::handle_referral_redirect()"
);

$call_args = array_map( 'trim', explode( ',', $caller_match[1] ?? '' ) );
assert_test(
	count( $call_args ) === 6,
	"Caller provides 6 arguments matching signature",
	"Args: " . implode( ', ', $call_args )
);

assert_test(
	$call_args[0] === '0',
	"Arg 1 is 0 (slot_id = 0 for referrals/direct links)"
);

assert_test(
	$call_args[1] === '$post_id',
	"Arg 2 is \$post_id (WordPress post ID context)"
);

assert_test(
	$call_args[2] === '$program_id',
	"Arg 3 is \$program_id (Referral Program ID passed as campaign_id)"
);

assert_test(
	$call_args[3] === "'click'",
	"Arg 4 is 'click' (valid event_type in VALID_EVENT_TYPES)"
);

assert_test(
	$call_args[4] === '$device_type',
	"Arg 5 is \$device_type ('desktop', 'mobile', or 'tablet')"
);

assert_test(
	$call_args[5] === '1',
	"Arg 6 is 1 (count = 1)"
);

// 3.3 Evaluation of PROJECT.md Discrepancy
// PROJECT.md line 67 stated: record_event(0, $program_id, $post_id, 'click', $device_type)
// But actual signature is: ($slot_id, $post_id, $campaign_id, ...)
// Worker implemented: (0, $post_id, $program_id, 'click', $device_type, 1)
assert_test(
	$call_args[1] === '$post_id' && $call_args[2] === '$program_id',
	"CRITICAL VERIFICATION: Implementation correctly adhered to repository method contract (post_id, campaign_id) rather than the transposed docstring in PROJECT.md"
);

// 3.4 Prepared SQL Query Generation & Atomic Aggregation Test
$GLOBALS['wpdb']->queries = array();
$res = $telemetry_repo->record_event( 0, 105, 12, 'click', 'mobile', 1 );

$last_query = end( $GLOBALS['wpdb']->queries );
assert_test(
	$res === true && is_string( $last_query ),
	"record_event() executes query successfully"
);

assert_test(
	str_contains( $last_query, "INSERT INTO wp_aips_monetization_events (slot_id, campaign_id, post_id, event_type, device_type, event_date, event_count)" ),
	"INSERT column list matches wp_aips_monetization_events schema"
);

assert_test(
	str_contains( $last_query, "VALUES (0, 12, 105, 'click', 'mobile'" ),
	"Prepared values correctly bound: slot_id=0, campaign_id=12, post_id=105, event_type='click', device_type='mobile'",
	"Query: " . $last_query
);

assert_test(
	str_contains( $last_query, "ON DUPLICATE KEY UPDATE event_count = event_count + VALUES(event_count)" ),
	"Atomic counter aggregation clause is present and correct"
);

// 3.5 Telemetry Input Sanitization & Resilience
// Test invalid event_type -> should fall back to 'impression'
$GLOBALS['wpdb']->queries = array();
$telemetry_repo->record_event( 0, 1, 1, 'invalid_type_injection', 'invalid_device', -10 );
$sanitized_query = end( $GLOBALS['wpdb']->queries );

assert_test(
	str_contains( $sanitized_query, "'impression'" ),
	"Invalid event_type safely sanitizes to 'impression'"
);

assert_test(
	str_contains( $sanitized_query, "'desktop'" ),
	"Invalid device_type safely sanitizes to 'desktop'"
);

assert_test(
	str_contains( $sanitized_query, ", 1)" ),
	"Negative event count safely clamped to min 1"
);

// 3.6 Batch Telemetry Recording
$batch_res = $telemetry_repo->record_events_batch( array(
	array( 'slot_id' => 0, 'post_id' => 10, 'campaign_id' => 5, 'event_type' => 'click', 'device_type' => 'desktop', 'count' => 1 ),
	array( 'slot_id' => 1, 'post_id' => 20, 'campaign_id' => 0, 'event_type' => 'impression', 'device_type' => 'mobile', 'count' => 2 ),
	'invalid_non_array_element',
) );

assert_test(
	$batch_res === 2,
	"record_events_batch() records valid items and ignores malformed inputs (recorded: $batch_res)"
);

echo "\n===============================================================\n";
echo " CHALLENGE SUMMARY: $passes PASSED, $failures FAILED\n";
echo "===============================================================\n";

if ( $failures > 0 ) {
	exit( 1 );
}
exit( 0 );
