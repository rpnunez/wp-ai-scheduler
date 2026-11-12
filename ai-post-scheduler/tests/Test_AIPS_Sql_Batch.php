<?php
/**
 * Tests for the pure functions in bin/sql-batch.php (CLI SQL batch runner).
 *
 * @package AI_Post_Scheduler
 */

require_once dirname(__DIR__) . '/bin/sql-batch.php';

class Test_AIPS_Sql_Batch extends WP_UnitTestCase {

	/* ---- wp-config.php parsing ---------------------------------------- */

	public function test_parse_wp_config_reads_literal_defines_and_prefix() {
		$php = <<<'PHP'
<?php
// define('DB_NAME', 'commented_out');
define( 'DB_NAME', 'my_db' );
define('DB_USER', "dbuser");
define('DB_PASSWORD', 'p\'a\\ss$word');
define('DB_HOST', 'localhost:3307');
define('WP_DEBUG', false);
$table_prefix = 'xyz_';
PHP;
		$parsed = aips_sqlb_parse_wp_config($php);

		$this->assertSame('my_db', $parsed['constants']['DB_NAME']);
		$this->assertSame('dbuser', $parsed['constants']['DB_USER']);
		$this->assertSame("p'a\\ss\$word", $parsed['constants']['DB_PASSWORD']);
		$this->assertSame('localhost:3307', $parsed['constants']['DB_HOST']);
		$this->assertFalse($parsed['constants']['WP_DEBUG']);
		$this->assertSame('xyz_', $parsed['table_prefix']);
	}

	public function test_parse_wp_config_flags_non_literal_values() {
		$php = "<?php\ndefine('DB_NAME', getenv('DB'));\ndefine('DB_USER', \"u{\$x}\");\n";
		$parsed = aips_sqlb_parse_wp_config($php);

		$this->assertArrayNotHasKey('DB_NAME', $parsed['constants']);
		$this->assertArrayNotHasKey('DB_USER', $parsed['constants']);
		$this->assertContains('DB_NAME', $parsed['unsupported']);
		$this->assertContains('DB_USER', $parsed['unsupported']);
	}

	public function test_parse_db_host_variants() {
		$this->assertSame(array('host' => 'localhost', 'port' => null, 'socket' => null), aips_sqlb_parse_db_host('localhost'));
		$this->assertSame(array('host' => 'db', 'port' => 3306, 'socket' => null), aips_sqlb_parse_db_host('db:3306'));
		$this->assertSame(array('host' => 'localhost', 'port' => null, 'socket' => '/var/run/mysqld.sock'), aips_sqlb_parse_db_host('localhost:/var/run/mysqld.sock'));
		$this->assertSame(array('host' => '::1', 'port' => 3307, 'socket' => null), aips_sqlb_parse_db_host('[::1]:3307'));
	}

	public function test_is_local_host() {
		$this->assertTrue(aips_sqlb_is_local_host(aips_sqlb_parse_db_host('localhost')));
		$this->assertTrue(aips_sqlb_is_local_host(aips_sqlb_parse_db_host('127.0.0.1:3306')));
		$this->assertTrue(aips_sqlb_is_local_host(aips_sqlb_parse_db_host('localhost:/tmp/m.sock')));
		$this->assertFalse(aips_sqlb_is_local_host(aips_sqlb_parse_db_host('db:3306')));
		$this->assertFalse(aips_sqlb_is_local_host(aips_sqlb_parse_db_host('db.example.com')));
	}

	/* ---- wp-config.php discovery -------------------------------------- */

	public function test_normalize_path_collapses_dots_without_resolving_symlinks() {
		$this->assertSame('/a/c', aips_sqlb_normalize_path('/a/b/../c/./'));
		$this->assertSame('/a/b', aips_sqlb_normalize_path('/a//b'));
	}

	public function test_start_dirs_prefer_the_invoked_path_over_the_resolved_one() {
		$starts = aips_sqlb_start_dirs(
			array('bin/sql-batch.php'),
			'/home/u/sites/s/public_html/wp-content/plugins/ai-post-scheduler',
			'/home/u/repos/site/ai-post-scheduler/bin',
			'/home/u/repos/site/ai-post-scheduler'
		);

		$this->assertSame('/home/u/sites/s/public_html/wp-content/plugins/ai-post-scheduler/bin', $starts[0]);
		$this->assertContains('/home/u/repos/site/ai-post-scheduler/bin', $starts);
	}

	public function test_find_wp_config_walks_up_from_a_nested_directory() {
		$root = sys_get_temp_dir() . '/aips-sqlb-' . uniqid();
		mkdir($root . '/wp-content/plugins/p/bin', 0777, true);
		file_put_contents($root . '/wp-config.php', '<?php');

		list($found, $tried) = aips_sqlb_find_wp_config(array($root . '/wp-content/plugins/p/bin'));

		$this->assertSame($root . '/wp-config.php', $found);
		$this->assertNotEmpty($tried);

		unlink($root . '/wp-config.php');
		list($missing) = aips_sqlb_find_wp_config(array($root . '/wp-content/plugins/p/bin'));
		$this->assertNotSame($root . '/wp-config.php', $missing);
		$this->rrmdir($root);
	}

	/* ---- query splitting ---------------------------------------------- */

	public function test_split_queries_reads_names_and_purposes() {
		$text = "-- name: first\n-- purpose: why\nSELECT 1;\n\n-- name: second\nSELECT 2";
		$q    = aips_sqlb_split_queries($text);

		$this->assertCount(2, $q);
		$this->assertSame('first', $q[0]['name']);
		$this->assertSame('why', $q[0]['purpose']);
		$this->assertSame('SELECT 1', $q[0]['sql']);
		$this->assertSame('second', $q[1]['name']);
		$this->assertNull($q[1]['purpose']);
		$this->assertSame('SELECT 2', $q[1]['sql']);
	}

	public function test_split_queries_ignores_semicolons_and_comment_markers_inside_strings() {
		$q = aips_sqlb_split_queries("SELECT 'a;b -- not a comment', \"c;d\", `e;f` FROM t; SELECT 2;");

		$this->assertCount(2, $q);
		$this->assertSame("SELECT 'a;b -- not a comment', \"c;d\", `e;f` FROM t", $q[0]['sql']);
	}

	public function test_split_queries_handles_escaped_and_doubled_quotes() {
		$q = aips_sqlb_split_queries("SELECT 'it\\'s; ok', 'it''s; ok'; SELECT 2;");

		$this->assertCount(2, $q);
		$this->assertSame("SELECT 'it\\'s; ok', 'it''s; ok'", $q[0]['sql']);
	}

	public function test_split_queries_strips_comments_including_conditional_ones() {
		$q = aips_sqlb_split_queries("SELECT 1 /*!50000 , DROP_ME */ -- trailing\n, 2 # hash\n;");

		$this->assertCount(1, $q);
		$this->assertStringNotContainsString('DROP_ME', $q[0]['sql']);
		$this->assertStringNotContainsString('trailing', $q[0]['sql']);
		$this->assertStringNotContainsString('hash', $q[0]['sql']);
	}

	public function test_split_queries_flags_unterminated_strings() {
		$q = aips_sqlb_split_queries("SELECT 'oops;\nSELECT 2;");

		$this->assertCount(1, $q);
		$this->assertNotEmpty($q[0]['error']);
	}

	public function test_split_queries_skips_empty_statements_and_bom() {
		$q = aips_sqlb_split_queries("\xEF\xBB\xBF;;\n-- just a comment\n;SELECT 1;");

		$this->assertCount(1, $q);
		$this->assertSame('SELECT 1', $q[0]['sql']);
	}

	public function test_query_slug_is_safe_and_ordered() {
		$this->assertSame('03_stuck_jobs', aips_sqlb_query_slug('stuck jobs', 3));
		$this->assertSame('01_query', aips_sqlb_query_slug('../../', 1));
	}

	/* ---- read-only classifier ----------------------------------------- */

	/**
	 * @dataProvider allowed_sql_provider
	 */
	public function test_classify_allows_read_only_statements($sql) {
		list($ok, $reason) = aips_sqlb_classify($sql);
		$this->assertTrue($ok, $reason);
	}

	public function allowed_sql_provider() {
		return array(
			'select'                  => array('SELECT * FROM wp_posts WHERE post_status = \'draft\''),
			'parenthesised select'    => array('(SELECT 1) UNION (SELECT 2)'),
			'cte'                     => array('WITH x AS (SELECT 1 AS n) SELECT n FROM x'),
			'show create table'       => array('SHOW CREATE TABLE wp_posts'),
			'show tables'             => array('SHOW TABLES LIKE \'wp_aips%\''),
			'describe'                => array('DESCRIBE wp_posts'),
			'explain'                 => array('EXPLAIN SELECT * FROM wp_posts'),
			'replace function'        => array('SELECT REPLACE(post_title, \'a\', \'b\') FROM wp_posts'),
			'column named update_time'=> array('SELECT update_time, created_at FROM information_schema.tables'),
			'dangerous words in str'  => array('SELECT * FROM t WHERE x = \'DROP TABLE; DELETE FROM y\''),
			'backticked keyword'      => array('SELECT `update`, `delete` FROM t'),
		);
	}

	/**
	 * @dataProvider refused_sql_provider
	 */
	public function test_classify_refuses_everything_else($sql) {
		list($ok) = aips_sqlb_classify($sql);
		$this->assertFalse($ok, $sql);
	}

	public function refused_sql_provider() {
		return array(
			'update'              => array('UPDATE wp_posts SET post_status = \'draft\''),
			'delete'              => array('DELETE FROM wp_posts'),
			'drop'                => array('DROP TABLE wp_posts'),
			'insert'              => array('INSERT INTO wp_options VALUES (1)'),
			'replace statement'   => array('REPLACE INTO wp_options VALUES (1)'),
			'truncate'            => array('TRUNCATE wp_posts'),
			'set'                 => array('SET GLOBAL read_only = 0'),
			'call'                => array('CALL do_something()'),
			'select into outfile' => array('SELECT * FROM wp_posts INTO OUTFILE \'/tmp/x\''),
			'select into dumpfile'=> array('SELECT 1 INTO DUMPFILE \'/tmp/x\''),
			'for update'          => array('SELECT * FROM wp_posts FOR UPDATE'),
			'lock in share mode'  => array('SELECT * FROM wp_posts LOCK IN SHARE MODE'),
			'load_file'           => array('SELECT LOAD_FILE(\'/etc/passwd\')'),
			'sleep'               => array('SELECT SLEEP(100)'),
			'benchmark'           => array('SELECT BENCHMARK(100000000, MD5(1))'),
			'stacked statements'  => array('SELECT 1; DROP TABLE wp_posts'),
			'cte delete'          => array('WITH x AS (SELECT 1) DELETE FROM wp_posts'),
			'empty'               => array(''),
			'use database'        => array('USE mysql'),
			'grant'               => array('GRANT ALL ON *.* TO x'),
		);
	}

	/* ---- redaction ----------------------------------------------------- */

	public function test_redact_row_masks_sensitive_columns() {
		$count = 0;
		$row   = aips_sqlb_redact_row(array(
			'ID'         => 1,
			'user_login' => 'admin',
			'user_pass'  => '$P$Babc',
			'user_email' => 'me@example.com',
		), $count);

		$this->assertSame('admin', $row['user_login']);
		$this->assertSame('[REDACTED]', $row['user_pass']);
		$this->assertSame('[REDACTED]', $row['user_email']);
		$this->assertSame(2, $count);
	}

	public function test_redact_row_masks_option_values_by_option_name() {
		$count = 0;
		$row   = aips_sqlb_redact_row(array('option_name' => 'aips_google_api_key', 'option_value' => 'abc123xyz'), $count);
		$this->assertSame('[REDACTED]', $row['option_value']);

		$row = aips_sqlb_redact_row(array('option_name' => 'blogname', 'option_value' => 'My Blog'), $count);
		$this->assertSame('My Blog', $row['option_value']);
	}

	public function test_redact_row_keeps_numeric_and_empty_values() {
		$count = 0;
		$row   = aips_sqlb_redact_row(array(
			'total_tokens' => '1500',
			'api_token'    => '',
			'option_name'  => 'aips_max_tokens',
			'option_value' => '2048',
		), $count);

		$this->assertSame('1500', $row['total_tokens']);
		$this->assertSame('', $row['api_token']);
		$this->assertSame('2048', $row['option_value']);
		$this->assertSame(0, $count);
	}

	public function test_redact_row_masks_keys_embedded_in_serialized_and_json_values() {
		$count = 0;
		$key   = 'AIza' . str_repeat('x', 35);
		$row   = aips_sqlb_redact_row(array(
			'option_name'  => 'aips_settings',
			'option_value' => 'a:2:{s:7:"api_key";s:39:"' . $key . '";s:5:"limit";i:5;}',
			'extra'        => '{"client_secret":"s3cr3tvalue","max_tokens":"100"}',
			'note'         => 'Authorization: Bearer abcdefghijklmnopqrstuvwxyz0123',
		), $count);

		$this->assertStringNotContainsString($key, $row['option_value']);
		$this->assertStringContainsString('i:5;', $row['option_value']);
		$this->assertStringNotContainsString('s3cr3tvalue', $row['extra']);
		$this->assertStringContainsString('"max_tokens":"100"', $row['extra']);
		$this->assertStringNotContainsString('abcdefghijklmnop', $row['note']);
		$this->assertGreaterThanOrEqual(3, $count);
	}

	public function test_clean_cell_masks_binary_and_truncates_long_strings() {
		$this->assertSame('[binary 3 bytes]', aips_sqlb_clean_cell("\xff\xfe\xfd"));
		$this->assertSame(5, aips_sqlb_clean_cell(5));
		$this->assertNull(aips_sqlb_clean_cell(null));

		$long = str_repeat('é', AIPS_SQLB_CELL_MAX_BYTES);
		$cut  = aips_sqlb_clean_cell($long);
		$this->assertStringContainsString('truncated', $cut);
		$this->assertSame(1, preg_match('//u', $cut));
	}

	/* ---- output helpers ------------------------------------------------ */

	public function test_is_inside_detects_nested_paths_only() {
		$root = sys_get_temp_dir() . '/aips-sqlb-in-' . uniqid();
		mkdir($root . '/a/b', 0777, true);
		mkdir($root . '-sibling', 0777, true);

		$this->assertTrue(aips_sqlb_is_inside($root . '/a/b', $root));
		$this->assertTrue(aips_sqlb_is_inside($root, $root));
		$this->assertFalse(aips_sqlb_is_inside($root . '-sibling', $root));

		$this->rrmdir($root);
		$this->rrmdir($root . '-sibling');
	}

	public function test_md_cell_escapes_pipes_and_newlines() {
		$this->assertSame('a\\|b c', aips_sqlb_md_cell("a|b\nc"));
		$this->assertSame('NULL', aips_sqlb_md_cell(null));
		$this->assertSame('true', aips_sqlb_md_cell(true));
	}

	public function test_archive_creates_a_zip_containing_the_run_folder() {
		if (!class_exists('ZipArchive')) {
			$this->markTestSkipped('ZipArchive is not available.');
		}
		$root = sys_get_temp_dir() . '/aips-sqlb-zip-' . uniqid();
		$dir  = $root . '/sql-batch-test';
		mkdir($dir . '/queries', 0777, true);
		file_put_contents($dir . '/REPORT.md', '# r');
		file_put_contents($dir . '/queries/01_a.json', '{}');

		$archive = aips_sqlb_archive($dir);

		$this->assertSame($dir . '.zip', $archive);
		$zip = new ZipArchive();
		$this->assertTrue($zip->open($archive));
		$this->assertNotFalse($zip->locateName('sql-batch-test/REPORT.md'));
		$this->assertNotFalse($zip->locateName('sql-batch-test/queries/01_a.json'));
		$zip->close();

		$this->rrmdir($root);
	}

	public function test_parse_args() {
		$o = aips_sqlb_parse_args(array('sql-batch.php', 'q.sql', '--yes', '--no-redact', '--out=/x', '--wp-config=/y/wp-config.php'));
		$this->assertSame('q.sql', $o['queries']);
		$this->assertTrue($o['yes']);
		$this->assertFalse($o['redact']);
		$this->assertSame('/x', $o['out']);
		$this->assertSame('/y/wp-config.php', $o['wp_config']);
		$this->assertNull($o['error']);

		$this->assertSame('-', aips_sqlb_parse_args(array('sql-batch.php', '-'))['queries']);
		$this->assertNotNull(aips_sqlb_parse_args(array('sql-batch.php', 'q.sql', '--bogus'))['error']);
		$this->assertNotNull(aips_sqlb_parse_args(array('sql-batch.php', 'a.sql', 'b.sql'))['error']);
	}

	/* ---- redaction: aliases, literals, fail-closed ----------------------- */

	public function test_sql_has_sensitive_literal() {
		$this->assertTrue(aips_sqlb_sql_has_sensitive_literal("SELECT option_value FROM wp_options WHERE option_name = 'aips_api_secret'"));
		$this->assertTrue(aips_sqlb_sql_has_sensitive_literal("SELECT meta_value FROM wp_usermeta WHERE meta_key = 'session_tokens'"));
		$this->assertFalse(aips_sqlb_sql_has_sensitive_literal("SELECT option_value FROM wp_options WHERE option_name = 'blogname'"));
		// Identifiers are not literals.
		$this->assertFalse(aips_sqlb_sql_has_sensitive_literal('SELECT user_pass FROM wp_users'));
	}

	public function test_force_columns_use_the_original_column_name_and_sensitive_literals() {
		$f = function ($orgname) {
			return (object) array('orgname' => $orgname);
		};

		// `SELECT user_pass AS h, user_login AS who`
		$force = aips_sqlb_force_columns(array('h', 'who'), array($f('user_pass'), $f('user_login')), 'SELECT user_pass AS h, user_login AS who FROM wp_users');
		$this->assertSame(array('h'), $force);

		// Filtering on a secret option masks the value and computed columns, not the name.
		$sql   = "SELECT option_name, option_value AS v, LEFT(option_value, 5) AS l FROM wp_options WHERE option_name = 'aips_api_key'";
		$force = aips_sqlb_force_columns(array('option_name', 'v', 'l'), array($f('option_name'), $f('option_value'), $f('')), $sql);
		$this->assertSame(array('v', 'l'), $force);
	}

	public function test_redact_row_masks_forced_columns_but_keeps_numbers() {
		$count = 0;
		$row   = aips_sqlb_redact_row(array('h' => '$P$Bhash', 'n' => '42', 'other' => 'visible'), $count, array('h', 'n'));

		$this->assertSame('[REDACTED]', $row['h']);
		$this->assertSame('42', $row['n']);
		$this->assertSame('visible', $row['other']);
		$this->assertSame(1, $count);
	}

	public function test_redact_row_fails_closed_when_a_pattern_cannot_be_evaluated() {
		$old_jit   = ini_set('pcre.jit', '0');
		$old_limit = ini_set('pcre.backtrack_limit', '10');

		$count = 0;
		$row   = aips_sqlb_redact_row(array('note' => '"' . str_repeat('api_key', 2000) . '" :'), $count);

		ini_set('pcre.jit', $old_jit);
		ini_set('pcre.backtrack_limit', $old_limit);

		$this->assertSame('[REDACTED]', $row['note']);
		$this->assertSame(1, $count);
	}

	/* ---- paths ----------------------------------------------------------- */

	public function test_logical_cwd_ignores_a_stale_pwd() {
		$cwd = getcwd();
		$this->assertSame($cwd, aips_sqlb_logical_cwd($cwd, $cwd));
		$this->assertNull(aips_sqlb_logical_cwd(sys_get_temp_dir(), $cwd === realpath(sys_get_temp_dir()) ? dirname($cwd) : $cwd));
		$this->assertNull(aips_sqlb_logical_cwd('/does/not/exist', $cwd));
		$this->assertNull(aips_sqlb_logical_cwd(null, $cwd));
	}

	public function test_is_inside_handles_paths_that_do_not_exist_yet() {
		$root = sys_get_temp_dir() . '/aips-sqlb-new-' . uniqid();
		mkdir($root, 0777, true);

		$this->assertTrue(aips_sqlb_is_inside($root . '/not/created/yet', $root));
		$this->assertFalse(aips_sqlb_is_inside($root . '-other/not/created', $root));

		$this->rrmdir($root);
	}

	public function test_web_roots_include_the_wordpress_root_when_wp_config_is_above_it() {
		$base = sys_get_temp_dir() . '/aips-sqlb-roots-' . uniqid();
		mkdir($base . '/public_html/wp-content/plugins/p/bin', 0777, true);
		file_put_contents($base . '/wp-config.php', '<?php');
		file_put_contents($base . '/public_html/wp-settings.php', '<?php');

		$roots = aips_sqlb_web_roots($base . '/wp-config.php', array($base . '/public_html/wp-content/plugins/p/bin'));

		$this->assertContains($base, $roots);
		$this->assertContains($base . '/public_html', $roots);

		$this->rrmdir($base);
	}

	/* ---- archive --------------------------------------------------------- */

	public function test_tar_fallback_keeps_the_run_folder_prefix() {
		if (!class_exists('PharData') || !function_exists('gzopen')) {
			$this->markTestSkipped('PharData/zlib is not available.');
		}
		$root = sys_get_temp_dir() . '/aips-sqlb-tar-' . uniqid();
		$dir  = $root . '/sql-batch-test';
		mkdir($dir . '/queries', 0777, true);
		file_put_contents($dir . '/REPORT.md', '# r');
		file_put_contents($dir . '/queries/01_a.json', '{}');

		$archive = aips_sqlb_archive_tar($dir);

		$this->assertSame($dir . '.tar.gz', $archive);
		$phar = new PharData($archive);
		$this->assertTrue(isset($phar['sql-batch-test/REPORT.md']));
		$this->assertTrue(isset($phar['sql-batch-test/queries/01_a.json']));
		unset($phar);

		$this->rrmdir($root);
	}

	/* ---- report ---------------------------------------------------------- */

	public function test_build_report_lists_every_status_and_a_row_preview() {
		$base = array(
			'purpose' => null, 'truncated' => false, 'duration_ms' => 3, 'columns' => array(), 'rows' => array(), 'row_count' => 0, 'error' => null, 'redacted' => 0,
		);
		$results = array(
			array_merge($base, array('index' => '01', 'name' => 'good', 'status' => 'ok', 'sql' => 'SELECT 1', 'file' => '01_good.json', 'purpose' => 'Check things', 'columns' => array('a', 'b'), 'rows' => array(array('a' => 1, 'b' => 'x|y')), 'row_count' => 1)),
			array_merge($base, array('index' => '02', 'name' => 'bad', 'status' => 'error', 'sql' => 'SELECT nope', 'file' => '02_bad.json', 'error' => 'Unknown column (1054)')),
			array_merge($base, array('index' => '03', 'name' => 'nope', 'status' => 'refused', 'sql' => 'DROP TABLE t', 'file' => '03_nope.json', 'error' => 'Only read-only statements')),
			array_merge($base, array('index' => '04', 'name' => 'big', 'status' => 'ok', 'sql' => 'SELECT 2', 'file' => '04_big.json', 'truncated' => true, 'row_count' => 10000, 'columns' => array('a'), 'rows' => array(array('a' => 1)))),
		);
		$run = array(
			'run_id' => 'sql-batch-x', 'started' => 'now', 'duration_s' => 0.1, 'db_name' => 'db', 'db_host' => 'h', 'db_server' => '10.6', 'prefix' => 'wp_',
			'site_url' => 'https://example.test', 'wp_version' => '6.9', 'plugin_version' => '1.2.3', 'git' => 'main @ abc', 'timeout_applied' => true, 'redact' => true, 'redacted' => 2,
		);

		$md = aips_sqlb_build_report($run, $results);

		$this->assertStringContainsString('4 total, 2 ok, 1 failed, 1 refused', $md);
		$this->assertStringContainsString('**Purpose:** Check things', $md);
		$this->assertStringContainsString('| a | b |', $md);
		$this->assertStringContainsString('x\\|y', $md);
		$this->assertStringContainsString('**ERROR:** Unknown column (1054)', $md);
		$this->assertStringContainsString('**REFUSED:** Only read-only statements', $md);
		$this->assertStringContainsString('capped at ' . AIPS_SQLB_ROW_CAP, $md);
		$this->assertStringContainsString('2 value(s) replaced', $md);
	}

	/* ---- main(): real database ------------------------------------------ */

	private function wp_config_for_test_db($dir) {
		foreach (array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST') as $c) {
			if (!defined($c)) {
				$this->markTestSkipped($c . ' is not defined; the WordPress test database is required.');
			}
		}
		$php  = "<?php\n";
		foreach (array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST') as $c) {
			$php .= 'define(' . var_export($c, true) . ', ' . var_export(constant($c), true) . ");\n";
		}
		$php .= '$table_prefix = ' . var_export($GLOBALS['wpdb']->prefix, true) . ";\n";
		file_put_contents($dir . '/wp-config.php', $php);
		return $dir . '/wp-config.php';
	}

	public function test_main_runs_a_batch_writes_results_and_survives_the_row_cap() {
		if (!class_exists('mysqli')) {
			$this->markTestSkipped('mysqli is not available.');
		}
		$root = sys_get_temp_dir() . '/aips-sqlb-main-' . uniqid();
		mkdir($root . '/site', 0777, true);
		$config  = $this->wp_config_for_test_db($root . '/site');
		$queries = $root . '/queries.sql';
		file_put_contents($queries, <<<'SQL'
-- name: hash_alias
SELECT user_pass AS h FROM {prefix}users LIMIT 1;
-- name: capped
SELECT 1 AS n FROM information_schema.columns a, information_schema.columns b LIMIT 10050;
-- name: after_cap
SELECT option_value AS v FROM {prefix}options WHERE option_name = 'siteurl';
-- name: bad_column
SELECT nope FROM {prefix}users;
-- name: write_attempt
UPDATE {prefix}users SET user_pass = 'x';
SQL
		);

		ob_start();
		$rc     = aips_sqlb_main(array('sql-batch.php', $queries, '--wp-config=' . $config, '--out=' . $root . '/out', '--yes'));
		$output = ob_get_clean();

		$this->assertSame(1, $rc, $output);
		$this->assertSame(1, preg_match('/^RESULTS_DIR=(.+)$/m', $output, $m), $output);
		$dir = trim($m[1]);

		$this->assertFileExists($dir . '/REPORT.md');
		foreach (array('01_hash_alias', '02_capped', '03_after_cap', '04_bad_column', '05_write_attempt') as $f) {
			$this->assertFileExists($dir . '/queries/' . $f . '.json');
		}

		$read = function ($f) use ($dir) {
			return json_decode(file_get_contents($dir . '/queries/' . $f . '.json'), true);
		};
		$hash = $read('01_hash_alias');
		$this->assertSame('ok', $hash['status']);
		$this->assertSame('[REDACTED]', $hash['rows'][0]['h']);

		$capped = $read('02_capped');
		$this->assertSame(AIPS_SQLB_ROW_CAP, $capped['row_count']);
		$this->assertTrue($capped['truncated']);
		$this->assertCount(AIPS_SQLB_ROW_CAP, $capped['rows']);

		$after = $read('03_after_cap');
		$this->assertSame('ok', $after['status'], 'The query after a capped one must still run (reconnect).');
		$this->assertCount(1, $after['rows']);

		$this->assertSame('error', $read('04_bad_column')['status']);
		$this->assertSame('refused', $read('05_write_attempt')['status']);

		$this->assertStringContainsString('1 refused', file_get_contents($dir . '/REPORT.md'));
		$this->assertFileExists($dir . '.zip');

		$this->rrmdir($root);
	}

	public function test_main_refuses_to_write_results_inside_the_wordpress_directory_and_creates_nothing() {
		$root = sys_get_temp_dir() . '/aips-sqlb-guard-' . uniqid();
		mkdir($root . '/site', 0777, true);
		$config  = $this->wp_config_for_test_db($root . '/site');
		$queries = $root . '/queries.sql';
		file_put_contents($queries, 'SELECT 1;');

		ob_start();
		$rc = aips_sqlb_main(array('sql-batch.php', $queries, '--wp-config=' . $config, '--out=' . $root . '/site/results', '--yes'));
		ob_end_clean();

		$this->assertSame(2, $rc);
		$this->assertDirectoryDoesNotExist($root . '/site/results');

		$this->rrmdir($root);
	}

	/* ---- helpers -------------------------------------------------------- */

	private function rrmdir($dir) {
		if (!is_dir($dir)) {
			return;
		}
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
			$f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
		}
		rmdir($dir);
	}
}
