#!/usr/bin/env php
<?php
/**
 * SQL Batch Runner
 *
 * Runs a file of read-only SQL queries against the database of the WordPress
 * install it lives in and writes the results to an organized folder (one JSON
 * file per query + REPORT.md) and a single zip of that folder. Built so an AI
 * agent (or a person) can get production diagnostics without copy/pasting
 * queries into phpMyAdmin and screenshotting the results.
 *
 * Usage:
 *   php sql-batch.php queries.sql [--wp-config=PATH] [--out=DIR] [--yes] [--no-redact]
 *   php sql-batch.php - <<'SQL'      (read the queries from stdin)
 *
 * Query file format:
 *   -- name: orphaned_history_rows
 *   -- purpose: Why this query is being run (shown in REPORT.md)
 *   SELECT ... FROM {prefix}posts ... ;
 *
 * `{prefix}` is replaced with the install's $table_prefix.
 *
 * Safety (hard-coded, not configurable):
 *  - CLI only. Credentials are read from wp-config.php, never printed.
 *  - Only SELECT / WITH / SHOW / DESCRIBE / EXPLAIN statements run.
 *  - The session is READ ONLY, has a per-query timeout and a row cap.
 *  - Output is never written inside the WordPress directory.
 *  - Secrets (passwords, e-mails, tokens, API keys) are redacted from results
 *    unless --no-redact is passed.
 *
 * Exit codes: 0 all queries ran, 1 some queries failed/were refused, 2 setup error.
 *
 * @package AI_Post_Scheduler
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}

const AIPS_SQLB_ROW_CAP        = 10000;
const AIPS_SQLB_TIMEOUT_SECS   = 60;
const AIPS_SQLB_CELL_MAX_BYTES = 10000;
const AIPS_SQLB_REPORT_ROWS    = 25;
const AIPS_SQLB_REPORT_CELL    = 120;

/* ------------------------------------------------------------------------
 * Arguments
 * --------------------------------------------------------------------- */

/**
 * Parse CLI arguments (argv[0] is skipped).
 *
 * @param array $argv Raw argv.
 * @return array{queries:?string,wp_config:?string,out:?string,yes:bool,redact:bool,help:bool,error:?string}
 */
function aips_sqlb_parse_args(array $argv) {
	$opts = array(
		'queries'   => null,
		'wp_config' => null,
		'out'       => null,
		'yes'       => false,
		'redact'    => true,
		'help'      => false,
		'error'     => null,
	);

	$args = array_slice($argv, 1);
	foreach ($args as $arg) {
		if ($arg === '-h' || $arg === '--help') {
			$opts['help'] = true;
		} elseif ($arg === '--yes') {
			$opts['yes'] = true;
		} elseif ($arg === '--no-redact') {
			$opts['redact'] = false;
		} elseif (strpos($arg, '--wp-config=') === 0) {
			$opts['wp_config'] = substr($arg, 12);
		} elseif (strpos($arg, '--out=') === 0) {
			$opts['out'] = substr($arg, 6);
		} elseif ($arg === '-' || $arg === '' || $arg[0] !== '-') {
			if ($opts['queries'] !== null) {
				$opts['error'] = 'Only one query file may be given.';
			}
			$opts['queries'] = $arg;
		} else {
			$opts['error'] = 'Unknown option: ' . $arg;
		}
	}

	return $opts;
}

/**
 * Print usage text.
 *
 * @return void
 */
function aips_sqlb_usage() {
	echo <<<'TXT'
Usage: php sql-batch.php <queries.sql | -> [options]

  --wp-config=PATH  Use this wp-config.php instead of auto-discovering it.
  --out=DIR         Where to write results (never inside the WordPress dir).
                    Default: ~/sql-results, else the system temp dir.
  --yes             Skip the confirmation prompt for non-local database hosts.
  --no-redact       Do not mask passwords, e-mails, tokens or API keys.
  -h, --help        Show this help.

Query file format (see the header of this script):
  -- name: some_name
  -- purpose: why this is being run
  SELECT ... FROM {prefix}posts ... ;

TXT;
}

/* ------------------------------------------------------------------------
 * wp-config.php discovery and parsing
 * --------------------------------------------------------------------- */

/**
 * Lexically normalize a path (collapse . and ..) WITHOUT resolving symlinks.
 *
 * @param string $path Path.
 * @return string
 */
function aips_sqlb_normalize_path($path) {
	$path  = str_replace('\\', '/', $path);
	$drive = '';
	if (preg_match('#^[A-Za-z]:#', $path)) {
		$drive = substr($path, 0, 2);
		$path  = substr($path, 2);
	}
	$absolute = (isset($path[0]) && $path[0] === '/');
	$out      = array();
	foreach (explode('/', $path) as $part) {
		if ($part === '' || $part === '.') {
			continue;
		}
		if ($part === '..') {
			array_pop($out);
			continue;
		}
		$out[] = $part;
	}
	return $drive . ($absolute ? '/' : '') . implode('/', $out);
}

/**
 * Whether a path is absolute.
 *
 * @param string $path Path.
 * @return bool
 */
function aips_sqlb_is_absolute($path) {
	return isset($path[0]) && ($path[0] === '/' || $path[0] === '\\' || preg_match('#^[A-Za-z]:[\\\\/]#', $path));
}

/**
 * Directories to start the wp-config.php search from.
 *
 * The plugin folder is symlinked into the WordPress tree on production, and
 * __FILE__ / getcwd() resolve symlinks. $argv[0] and $PWD keep the path the
 * script was invoked through, so they are tried first.
 *
 * @param array       $argv       Raw argv.
 * @param string|null $pwd        Logical working directory ($PWD).
 * @param string      $script_dir Fallback: directory of this script.
 * @param string|null $cwd        Physical working directory.
 * @return string[]
 */
function aips_sqlb_start_dirs(array $argv, $pwd, $script_dir, $cwd) {
	$starts = array();
	$script = isset($argv[0]) ? (string) $argv[0] : '';
	$pwd    = ($pwd !== null && $pwd !== '') ? $pwd : $cwd;

	if ($script !== '') {
		$abs      = aips_sqlb_is_absolute($script) ? $script : rtrim((string) $pwd, '/\\') . '/' . $script;
		$starts[] = dirname(aips_sqlb_normalize_path($abs));
	}
	if ($pwd) {
		$starts[] = aips_sqlb_normalize_path($pwd);
	}
	$starts[] = aips_sqlb_normalize_path($script_dir);
	if ($cwd) {
		$starts[] = aips_sqlb_normalize_path($cwd);
	}

	return array_values(array_unique($starts));
}

/**
 * Walk up from each start directory looking for wp-config.php.
 *
 * @param string[] $start_dirs Start directories.
 * @return array{0:?string,1:string[]} Found path (or null) and the directories checked.
 */
function aips_sqlb_find_wp_config(array $start_dirs) {
	$tried = array();
	foreach ($start_dirs as $start) {
		$dir = $start;
		for ($depth = 0; $depth < 20; $depth++) {
			$candidate = rtrim($dir, '/') . '/wp-config.php';
			$tried[]   = $candidate;
			if (is_file($candidate)) {
				return array($candidate, $tried);
			}
			$parent = dirname($dir);
			if ($parent === $dir || $parent === '.' || $parent === '') {
				break;
			}
			$dir = $parent;
		}
	}
	return array(null, $tried);
}

/**
 * Decode a PHP string literal token. Returns null for strings that interpolate.
 *
 * @param string $literal Literal including quotes.
 * @return string|null
 */
function aips_sqlb_decode_php_string($literal) {
	$quote = $literal[0];
	$body  = substr($literal, 1, -1);
	if ($quote === "'") {
		return preg_replace_callback('/\\\\([\\\\\'])/', function ($m) {
			return $m[1];
		}, $body);
	}
	if (preg_match('/(?<!\\\\)\$|\{\$/', $body)) {
		return null;
	}
	return stripcslashes($body);
}

/**
 * Read DB settings from the source of a wp-config.php without executing it.
 *
 * @param string $php wp-config.php source.
 * @return array{constants:array,table_prefix:?string,unsupported:string[]}
 */
function aips_sqlb_parse_wp_config($php) {
	$tokens = array();
	foreach (token_get_all($php) as $tok) {
		if (is_array($tok) && in_array($tok[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			continue;
		}
		$tokens[] = $tok;
	}

	$value_of = function ($tok, &$ok) {
		$ok = true;
		if (is_array($tok)) {
			if ($tok[0] === T_CONSTANT_ENCAPSED_STRING) {
				$v = aips_sqlb_decode_php_string($tok[1]);
				if ($v === null) {
					$ok = false;
				}
				return $v;
			}
			if ($tok[0] === T_LNUMBER) {
				return (int) $tok[1];
			}
			if ($tok[0] === T_STRING) {
				$lower = strtolower($tok[1]);
				if ($lower === 'true') {
					return true;
				}
				if ($lower === 'false') {
					return false;
				}
			}
		}
		$ok = false;
		return null;
	};

	$constants   = array();
	$unsupported = array();
	$prefix      = null;
	$count       = count($tokens);

	for ($i = 0; $i < $count; $i++) {
		$tok = $tokens[$i];

		if (
			is_array($tok) && $tok[0] === T_STRING && strtolower($tok[1]) === 'define'
			&& isset($tokens[$i + 4])
			&& $tokens[$i + 1] === '('
			&& is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING
			&& $tokens[$i + 3] === ','
		) {
			$name = aips_sqlb_decode_php_string($tokens[$i + 2][1]);
			$ok   = false;
			$val  = $value_of($tokens[$i + 4], $ok);
			$next = isset($tokens[$i + 5]) ? $tokens[$i + 5] : null;
			if ($ok && ($next === ')' || $next === ',')) {
				$constants[$name] = $val;
			} else {
				$unsupported[] = $name;
			}
			continue;
		}

		if (
			is_array($tok) && $tok[0] === T_VARIABLE && $tok[1] === '$table_prefix'
			&& isset($tokens[$i + 2]) && $tokens[$i + 1] === '='
		) {
			$ok  = false;
			$val = $value_of($tokens[$i + 2], $ok);
			if ($ok && is_string($val)) {
				$prefix = $val;
			} else {
				$unsupported[] = '$table_prefix';
			}
		}
	}

	return array(
		'constants'    => $constants,
		'table_prefix' => $prefix,
		'unsupported'  => $unsupported,
	);
}

/**
 * Split a WordPress DB_HOST value into host, port and socket.
 *
 * @param string $db_host DB_HOST.
 * @return array{host:string,port:?int,socket:?string}
 */
function aips_sqlb_parse_db_host($db_host) {
	$host   = $db_host;
	$port   = null;
	$socket = null;

	if (preg_match('/^\[(.+)\](?::(\d+))?$/', $db_host, $m)) {
		$host = $m[1];
		$port = isset($m[2]) ? (int) $m[2] : null;
	} elseif (strpos($db_host, ':') !== false && substr_count($db_host, ':') === 1) {
		list($host, $rest) = explode(':', $db_host, 2);
		if ($rest !== '' && $rest[0] === '/') {
			$socket = $rest;
		} elseif (ctype_digit($rest)) {
			$port = (int) $rest;
		}
	}

	if ($host === '') {
		$host = 'localhost';
	}

	return array('host' => $host, 'port' => $port, 'socket' => $socket);
}

/**
 * Whether the DB host is the machine the script runs on.
 *
 * @param array $parsed Result of aips_sqlb_parse_db_host().
 * @return bool
 */
function aips_sqlb_is_local_host(array $parsed) {
	return $parsed['socket'] !== null || in_array(strtolower($parsed['host']), array('localhost', '127.0.0.1', '::1'), true);
}

/* ------------------------------------------------------------------------
 * Query file parsing
 * --------------------------------------------------------------------- */

/**
 * Split query-file text into statements.
 *
 * Quote- and comment-aware. Comments are removed from the returned SQL (so a
 * comment can never smuggle executable text, e.g. MySQL's version-conditional
 * comments),
 * but `-- name:` and `-- purpose:` comments before a statement are captured.
 *
 * @param string $text Query file contents.
 * @return array<int,array{sql:string,name:?string,purpose:?string,error:?string}>
 */
function aips_sqlb_split_queries($text) {
	$text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
	$text = str_replace(array("\r\n", "\r"), "\n", $text);
	$len  = strlen($text);

	$queries   = array();
	$buf       = '';
	$name      = null;
	$purpose   = null;
	$has_token = false;
	$error     = null;
	$i         = 0;

	$flush = function () use (&$queries, &$buf, &$name, &$purpose, &$has_token, &$error) {
		$sql = trim($buf);
		if ($sql !== '') {
			$queries[] = array('sql' => $sql, 'name' => $name, 'purpose' => $purpose, 'error' => $error);
		}
		$buf       = '';
		$name      = null;
		$purpose   = null;
		$has_token = false;
		$error     = null;
	};

	while ($i < $len) {
		$c = $text[$i];
		$n = ($i + 1 < $len) ? $text[$i + 1] : '';

		// Line comments: "-- " (dash dash + whitespace/EOL) and "#".
		if (($c === '-' && $n === '-' && ($i + 2 >= $len || ctype_space($text[$i + 2]))) || $c === '#') {
			$end = strpos($text, "\n", $i);
			if ($end === false) {
				$end = $len;
			}
			if (!$has_token) {
				$comment = substr($text, $i, $end - $i);
				if (preg_match('/^(?:--|#)\s*(name|purpose)\s*:\s*(.*?)\s*$/i', $comment, $m)) {
					if (strtolower($m[1]) === 'name') {
						$name = $m[2];
					} else {
						$purpose = $m[2];
					}
				}
			}
			$buf .= ' ';
			$i    = $end;
			continue;
		}

		// Block comments (including /*! ... */ and /*+ ... */) are dropped.
		if ($c === '/' && $n === '*') {
			$end  = strpos($text, '*/', $i + 2);
			$buf .= ' ';
			$i    = ($end === false) ? $len : $end + 2;
			continue;
		}

		// Quoted strings and identifiers.
		if ($c === "'" || $c === '"' || $c === '`') {
			$j     = $i + 1;
			$closed = false;
			while ($j < $len) {
				$d = $text[$j];
				if ($d === '\\' && $c !== '`') {
					$j += 2;
					continue;
				}
				if ($d === $c) {
					if ($j + 1 < $len && $text[$j + 1] === $c) {
						$j += 2;
						continue;
					}
					$closed = true;
					break;
				}
				$j++;
			}
			if (!$closed) {
				$error = 'Unterminated quoted string or identifier.';
				$j     = $len - 1;
			}
			$buf      .= substr($text, $i, $j - $i + 1);
			$has_token = true;
			$i         = $j + 1;
			continue;
		}

		if ($c === ';') {
			$flush();
			$i++;
			continue;
		}

		$buf .= $c;
		if (!ctype_space($c)) {
			$has_token = true;
		}
		$i++;
	}
	$flush();

	return $queries;
}

/**
 * Replace every quoted string / identifier with an empty literal.
 *
 * @param string $sql Comment-free SQL.
 * @return string
 */
function aips_sqlb_mask_literals($sql) {
	$masked = preg_replace('/\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"\\\\]|\\\\.|"")*"|`(?:[^`]|``)*`/s', "''", $sql);
	return $masked === null ? $sql : $masked;
}

/**
 * Decide whether a statement may run. Fails closed.
 *
 * @param string $sql Comment-free SQL as returned by aips_sqlb_split_queries().
 * @return array{0:bool,1:string} Allowed flag and the reason when refused.
 */
function aips_sqlb_classify($sql) {
	$masked = aips_sqlb_mask_literals($sql);
	$head   = ltrim($masked, " \t\n(");

	if (!preg_match('/^([A-Za-z]+)/', $head, $m)) {
		return array(false, 'Empty or unrecognised statement.');
	}
	$first = strtoupper($m[1]);

	if (!in_array($first, array('SELECT', 'WITH', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'), true)) {
		return array(false, 'Only read-only statements (SELECT, WITH, SHOW, DESCRIBE, EXPLAIN) are allowed; got ' . $first . '.');
	}

	if (strpos($masked, ';') !== false) {
		return array(false, 'Multiple statements are not allowed.');
	}

	if (in_array($first, array('SHOW', 'DESCRIBE', 'DESC'), true)) {
		return array(true, '');
	}

	if (preg_match('/\b(?:INSERT|REPLACE)\b(?!\s*\()/i', $masked, $m)
		|| preg_match('/\b(UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE|GRANT|REVOKE|RENAME|CALL|LOCK|UNLOCK|HANDLER|LOAD|PREPARE|EXECUTE|SET|INTO)\b/i', $masked, $m)) {
		return array(false, 'Forbidden keyword in a read-only query: ' . strtoupper($m[0]) . '.');
	}
	if (preg_match('/\b(LOAD_FILE|SLEEP|BENCHMARK|GET_LOCK|RELEASE_LOCK|RELEASE_ALL_LOCKS|NAME_CONST)\s*\(/i', $masked, $m)) {
		return array(false, 'Forbidden function in a read-only query: ' . strtoupper($m[1]) . '().');
	}

	return array(true, '');
}

/**
 * Turn a query name into a safe file-name fragment.
 *
 * @param string|null $name     Name from the query file.
 * @param int         $position 1-based position.
 * @return string
 */
function aips_sqlb_query_slug($name, $position) {
	$slug = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $name), '_');
	if ($slug === '') {
		$slug = 'query';
	}
	return sprintf('%02d_%s', $position, substr($slug, 0, 60));
}

/* ------------------------------------------------------------------------
 * Redaction
 * --------------------------------------------------------------------- */

/**
 * Whether a column name is itself sensitive.
 *
 * @param string $column Column name.
 * @return bool
 */
function aips_sqlb_is_sensitive_column($column) {
	return (bool) preg_match('/(?:^|_)(?:pass|passwd|password|pwd|secret|token|api_?key|apikey|nonce|salt|activation_key|email|credentials?)(?:$|_)/i', $column);
}

/**
 * Whether an option/meta key names a secret.
 *
 * @param string $key option_name / meta_key.
 * @return bool
 */
function aips_sqlb_is_sensitive_key($key) {
	return (bool) preg_match('/pass|secret|token|api[_-]?key|apikey|salt|credential|private_key|nonce/i', $key);
}

/**
 * Whether a value is left alone by redaction (empty or numeric).
 *
 * @param string $value Cell value.
 * @return bool
 */
function aips_sqlb_keep_value($value) {
	return $value === '' || is_numeric($value);
}

/**
 * Whether any quoted string literal in the SQL names a secret
 * (e.g. WHERE option_name = 'aips_api_secret').
 *
 * @param string $sql Comment-free SQL.
 * @return bool
 */
function aips_sqlb_sql_has_sensitive_literal($sql) {
	if (!preg_match_all('/\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"\\\\]|\\\\.|"")*"/s', $sql, $m)) {
		return false;
	}
	foreach ($m[0] as $literal) {
		if (aips_sqlb_is_sensitive_key($literal)) {
			return true;
		}
	}
	return false;
}

/**
 * Result columns whose non-numeric values must always be masked.
 *
 * Uses the underlying column name (orgname) so `SELECT user_pass AS h` is still
 * caught, and masks option_value / meta_value / computed columns when the query
 * itself filters on a sensitive option or meta key.
 *
 * @param string[] $names  De-duplicated result column names.
 * @param array    $fields mysqli field objects, same order as $names.
 * @param string   $sql    The query.
 * @return string[]
 */
function aips_sqlb_force_columns(array $names, array $fields, $sql) {
	$literal = aips_sqlb_sql_has_sensitive_literal($sql);
	$force   = array();
	foreach ($names as $i => $name) {
		$org = isset($fields[$i]) ? (string) $fields[$i]->orgname : '';
		if (aips_sqlb_is_sensitive_column($org)
			|| ($literal && ($org === '' || $org === 'option_value' || $org === 'meta_value'))) {
			$force[] = (string) $name;
		}
	}
	return $force;
}

/**
 * Mask secret-looking substrings inside one value.
 *
 * @param string $value Cell value.
 * @param string $mask  Replacement text.
 * @param int    $n     Incremented by the number of replacements.
 * @return string|null Redacted value, or null if a regex failed (caller must mask the whole cell).
 */
function aips_sqlb_redact_value($value, $mask, &$n) {
	$new = preg_replace('/AIza[0-9A-Za-z_\-]{30,}/', $mask, $value, -1, $c);
	if ($new === null) {
		return null;
	}
	$n  += $c;
	$new = preg_replace('/\bsk-[A-Za-z0-9_\-]{20,}/', $mask, $new, -1, $c);
	if ($new === null) {
		return null;
	}
	$n  += $c;
	$new = preg_replace('/\b(Bearer)\s+[A-Za-z0-9._\-]{20,}/i', '$1 ' . $mask, $new, -1, $c);
	if ($new === null) {
		return null;
	}
	$n  += $c;
	$new = preg_replace_callback(
		'/("[^"\\\\]*(?:api[_-]?key|apikey|secret|token|password|passwd)[^"\\\\]*"\s*:\s*)"([^"\\\\]*)"/i',
		function ($m) use ($mask, &$n) {
			if (aips_sqlb_keep_value($m[2])) {
				return $m[0];
			}
			$n++;
			return $m[1] . '"' . $mask . '"';
		},
		$new
	);
	if ($new === null) {
		return null;
	}
	return preg_replace_callback(
		'/(s:\d+:"[^"]*(?:api[_-]?key|apikey|secret|password|passwd|token)[^"]*";s:\d+:")([^"]*)(")/i',
		function ($m) use ($mask, &$n) {
			if (aips_sqlb_keep_value($m[2])) {
				return $m[0];
			}
			$n++;
			return $m[1] . $mask . $m[3];
		},
		$new
	);
}

/**
 * Redact one result row.
 *
 * Numeric and empty values are never masked (so e.g. a `total_tokens` count
 * stays readable). Anything that looks like an API key is masked wherever it
 * appears, including inside serialized/JSON option values. If a pattern cannot
 * be evaluated (regex limits) the whole cell is masked.
 *
 * @param array    $row        Associative row.
 * @param int      $count      Incremented by the number of masked values.
 * @param string[] $force_cols Columns to mask regardless of their name (see aips_sqlb_force_columns()).
 * @return array
 */
function aips_sqlb_redact_row(array $row, &$count, array $force_cols = array()) {
	$mask = '[REDACTED]';
	$ctx  = null;
	foreach (array('option_name', 'meta_key') as $k) {
		if (isset($row[$k]) && is_string($row[$k])) {
			$ctx = $row[$k];
		}
	}

	foreach ($row as $col => $val) {
		if (!is_string($val) || $val === '') {
			continue;
		}

		$forced = in_array((string) $col, $force_cols, true) || aips_sqlb_is_sensitive_column($col);
		if ($forced && !aips_sqlb_keep_value($val)) {
			$row[$col] = $mask;
			$count++;
			continue;
		}
		if (($col === 'option_value' || $col === 'meta_value') && $ctx !== null && aips_sqlb_is_sensitive_key($ctx) && !aips_sqlb_keep_value($val)) {
			$row[$col] = $mask;
			$count++;
			continue;
		}

		$n   = 0;
		$new = aips_sqlb_redact_value($val, $mask, $n);
		if ($new === null) {
			$row[$col] = $mask;
			$count++;
		} elseif ($n > 0) {
			$row[$col] = $new;
			$count    += $n;
		}
	}

	return $row;
}

/**
 * Make a cell JSON-safe: mask binary data, cap very long strings.
 *
 * @param mixed $value Cell value.
 * @return mixed
 */
function aips_sqlb_clean_cell($value) {
	if (!is_string($value)) {
		return $value;
	}
	if (!preg_match('//u', $value)) {
		return '[binary ' . strlen($value) . ' bytes]';
	}
	if (strlen($value) > AIPS_SQLB_CELL_MAX_BYTES) {
		$cut = AIPS_SQLB_CELL_MAX_BYTES;
		while ($cut > 0 && !preg_match('//u', substr($value, 0, $cut))) {
			$cut--;
		}
		return substr($value, 0, $cut) . '…[truncated, ' . strlen($value) . ' bytes total]';
	}
	return $value;
}

/* ------------------------------------------------------------------------
 * Environment fingerprint
 * --------------------------------------------------------------------- */

/**
 * Best-effort branch + commit of the git checkout containing a directory.
 *
 * @param string $start Directory inside the checkout.
 * @return array{branch:?string,commit:?string}
 */
function aips_sqlb_git_info($start) {
	$info = array('branch' => null, 'commit' => null);
	$dir  = realpath($start);
	if ($dir === false) {
		return $info;
	}

	$gitdir = null;
	for ($i = 0; $i < 8; $i++) {
		$git = $dir . '/.git';
		if (is_dir($git)) {
			$gitdir = $git;
			break;
		}
		if (is_file($git) && preg_match('/^gitdir:\s*(.+)$/m', (string) @file_get_contents($git), $m)) {
			$gitdir = trim($m[1]);
			break;
		}
		$parent = dirname($dir);
		if ($parent === $dir) {
			break;
		}
		$dir = $parent;
	}
	if ($gitdir === null || !is_file($gitdir . '/HEAD')) {
		return $info;
	}

	$head = trim((string) @file_get_contents($gitdir . '/HEAD'));
	if (preg_match('#^ref:\s*(.+)$#', $head, $m)) {
		$ref            = trim($m[1]);
		$info['branch'] = preg_replace('#^refs/heads/#', '', $ref);
		$roots          = array($gitdir);
		if (is_file($gitdir . '/commondir')) {
			$roots[] = rtrim($gitdir . '/' . trim((string) @file_get_contents($gitdir . '/commondir')), '/');
		}
		foreach ($roots as $root) {
			if (is_file($root . '/' . $ref)) {
				$info['commit'] = substr(trim((string) @file_get_contents($root . '/' . $ref)), 0, 8);
				break;
			}
			$packed = is_file($root . '/packed-refs') ? (string) @file_get_contents($root . '/packed-refs') : '';
			if ($packed !== '' && preg_match('/^([0-9a-f]{40}) ' . preg_quote($ref, '/') . '$/m', $packed, $pm)) {
				$info['commit'] = substr($pm[1], 0, 8);
				break;
			}
		}
	} elseif (preg_match('/^[0-9a-f]{40}$/', $head)) {
		$info['commit'] = substr($head, 0, 8);
	}

	return $info;
}

/* ------------------------------------------------------------------------
 * Output
 * --------------------------------------------------------------------- */

/**
 * Resolve a path that may not exist yet: realpath() of its deepest existing
 * ancestor plus the missing remainder.
 *
 * @param string $path Path.
 * @return string|null
 */
function aips_sqlb_resolve_path($path) {
	$rest = array();
	$cur  = $path;
	while ($cur !== '' && realpath($cur) === false) {
		$parent = dirname($cur);
		if ($parent === $cur) {
			break;
		}
		array_unshift($rest, basename($cur));
		$cur = $parent;
	}
	$real = realpath($cur);
	if ($real === false) {
		return null;
	}
	$real = rtrim(str_replace('\\', '/', $real), '/');
	return $rest ? $real . '/' . implode('/', $rest) : $real;
}

/**
 * Whether $path (which need not exist) is inside, or equal to, $root.
 *
 * @param string $path Path.
 * @param string $root Existing directory.
 * @return bool
 */
function aips_sqlb_is_inside($path, $root) {
	$p = aips_sqlb_resolve_path($path);
	$r = realpath($root);
	if ($p === null || $r === false) {
		return false;
	}
	$p = rtrim($p, '/') . '/';
	$r = rtrim(str_replace('\\', '/', $r), '/') . '/';
	return strpos($p, $r) === 0;
}

/**
 * Directories whose contents may be served over HTTP: the wp-config.php
 * directory plus any directory above the start paths that holds wp-settings.php
 * (the WordPress root, which differs when wp-config.php sits one level above it).
 *
 * @param string   $config_path wp-config.php path.
 * @param string[] $start_dirs  Directories the search started from.
 * @return string[]
 */
function aips_sqlb_web_roots($config_path, array $start_dirs) {
	$roots = array(dirname($config_path));
	foreach ($start_dirs as $start) {
		$dir = $start;
		for ($i = 0; $i < 20; $i++) {
			if (is_file($dir . '/wp-settings.php')) {
				$roots[] = $dir;
				break;
			}
			$parent = dirname($dir);
			if ($parent === $dir) {
				break;
			}
			$dir = $parent;
		}
	}
	return array_values(array_unique($roots));
}

/**
 * The logical working directory ($PWD), but only when it really is the current
 * directory; an inherited, stale $PWD (cron, sudo, docker exec) is ignored.
 *
 * @param string|null $pwd $PWD from the environment.
 * @param string|null $cwd getcwd().
 * @return string|null
 */
function aips_sqlb_logical_cwd($pwd, $cwd) {
	if ($pwd && $cwd && realpath($pwd) !== false && realpath($pwd) === realpath($cwd)) {
		return $pwd;
	}
	return null;
}

/**
 * Default base directory for results.
 *
 * @return string
 */
function aips_sqlb_default_out_dir() {
	$home = getenv('HOME');
	if ($home && is_dir($home) && is_writable($home)) {
		return rtrim($home, '/\\') . '/sql-results';
	}
	return rtrim(sys_get_temp_dir(), '/\\') . '/aips-sql-batch';
}

/**
 * Escape a value for a markdown table cell.
 *
 * @param mixed $value Cell value.
 * @return string
 */
function aips_sqlb_md_cell($value) {
	if ($value === null) {
		$s = 'NULL';
	} elseif (is_bool($value)) {
		$s = $value ? 'true' : 'false';
	} else {
		$s = (string) $value;
	}
	$s = str_replace(array("\r\n", "\n", "\r", "\t"), ' ', $s);
	if (strlen($s) > AIPS_SQLB_REPORT_CELL) {
		$cut = AIPS_SQLB_REPORT_CELL;
		while ($cut > 0 && !preg_match('//u', substr($s, 0, $cut))) {
			$cut--;
		}
		$s = substr($s, 0, $cut) . '…';
	}
	return str_replace('|', '\\|', $s);
}

/**
 * Build REPORT.md.
 *
 * @param array $run Run metadata (see aips_sqlb_main()).
 * @param array $results Per-query results.
 * @return string
 */
function aips_sqlb_build_report(array $run, array $results) {
	$ok = $err = $skipped = 0;
	foreach ($results as $r) {
		if ($r['status'] === 'ok') {
			$ok++;
		} elseif ($r['status'] === 'error') {
			$err++;
		} else {
			$skipped++;
		}
	}

	$md  = "# SQL batch report\n\n";
	$md .= '- **Run:** ' . $run['run_id'] . "\n";
	$md .= '- **Started:** ' . $run['started'] . ' (' . $run['duration_s'] . "s)\n";
	$md .= '- **Database:** `' . $run['db_name'] . '` on `' . $run['db_host'] . '` (' . $run['db_server'] . ")\n";
	$md .= '- **Table prefix:** `' . $run['prefix'] . "`\n";
	$md .= '- **Site URL:** ' . ($run['site_url'] ?: 'unknown') . "\n";
	$md .= '- **WordPress:** ' . ($run['wp_version'] ?: 'unknown') . "\n";
	$md .= '- **Plugin:** ' . ($run['plugin_version'] ?: 'unknown') . ($run['git'] ? ' (' . $run['git'] . ')' : '') . "\n";
	$md .= '- **Queries:** ' . count($results) . ' total, ' . $ok . ' ok, ' . $err . ' failed, ' . $skipped . " refused\n";
	$md .= '- **Limits:** read-only session, ' . ($run['timeout_applied'] ? AIPS_SQLB_TIMEOUT_SECS . 's per-query timeout' : 'no server-side timeout available') . ', ' . AIPS_SQLB_ROW_CAP . ' rows max per query, cells over ' . AIPS_SQLB_CELL_MAX_BYTES . " bytes truncated\n";
	$md .= '- **Redaction:** ' . ($run['redact'] ? $run['redacted'] . ' value(s) replaced with `[REDACTED]` (passwords, e-mails, tokens, API keys)' : 'DISABLED (--no-redact)') . "\n";
	$md .= "- **Full results:** one JSON file per query in `queries/` (the tables below show only the first " . AIPS_SQLB_REPORT_ROWS . " rows).\n\n";

	$md .= "## Summary\n\n| # | Query | Status | Rows | Time | File |\n|---|---|---|---|---|---|\n";
	foreach ($results as $r) {
		$rows = $r['status'] === 'ok' ? $r['row_count'] . ($r['truncated'] ? '+ (capped)' : '') : '-';
		$md  .= '| ' . $r['index'] . ' | ' . aips_sqlb_md_cell($r['name']) . ' | ' . $r['status'] . ' | ' . $rows . ' | ' . $r['duration_ms'] . ' ms | `queries/' . $r['file'] . "` |\n";
	}
	$md .= "\n";

	foreach ($results as $r) {
		$md .= '## ' . $r['index'] . ' — ' . $r['name'] . "\n\n";
		if ($r['purpose']) {
			$md .= '**Purpose:** ' . $r['purpose'] . "\n\n";
		}
		$md .= "```sql\n" . $r['sql'] . "\n```\n\n";

		if ($r['status'] !== 'ok') {
			$md .= '**' . strtoupper($r['status']) . ':** ' . $r['error'] . "\n\n";
			continue;
		}

		$md .= '**' . $r['row_count'] . ' row(s)**' . ($r['truncated'] ? ' (capped at ' . AIPS_SQLB_ROW_CAP . ', more exist)' : '') . ' in ' . $r['duration_ms'] . " ms\n\n";
		if ($r['row_count'] === 0 || !$r['columns']) {
			continue;
		}

		$md .= '| ' . implode(' | ', array_map('aips_sqlb_md_cell', $r['columns'])) . " |\n";
		$md .= '|' . str_repeat('---|', count($r['columns'])) . "\n";
		foreach (array_slice($r['rows'], 0, AIPS_SQLB_REPORT_ROWS) as $row) {
			$md .= '| ' . implode(' | ', array_map('aips_sqlb_md_cell', array_values($row))) . " |\n";
		}
		if ($r['row_count'] > AIPS_SQLB_REPORT_ROWS) {
			$md .= "\n_… " . ($r['row_count'] - AIPS_SQLB_REPORT_ROWS) . ' more row(s) in `queries/' . $r['file'] . "`_\n";
		}
		$md .= "\n";
	}

	return $md;
}

/**
 * Files of a results folder keyed by their path inside the archive
 * (<run folder>/REPORT.md, <run folder>/queries/...).
 *
 * @param string $dir Results folder.
 * @return array<string,string> Archive entry name => file path.
 */
function aips_sqlb_archive_entries($dir) {
	$base    = basename($dir);
	$entries = array();
	$files   = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
	foreach ($files as $file) {
		if ($file->isFile()) {
			$entries[$base . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1))] = $file->getPathname();
		}
	}
	return $entries;
}

/**
 * Build <dir>.tar.gz (used when ZipArchive is unavailable).
 *
 * @param string $dir Results folder.
 * @return string|null Archive path or null on failure.
 */
function aips_sqlb_archive_tar($dir) {
	if (!class_exists('PharData')) {
		return null;
	}
	$tar_path = $dir . '.tar';
	try {
		@unlink($tar_path);
		@unlink($tar_path . '.gz');
		$tar = new PharData($tar_path);
		foreach (aips_sqlb_archive_entries($dir) as $name => $path) {
			$tar->addFile($path, $name);
		}
		$tar->compress(Phar::GZ);
		unset($tar);
		@unlink($tar_path);
		@chmod($tar_path . '.gz', 0600);
		return $tar_path . '.gz';
	} catch (Exception $e) {
		return null;
	}
}

/**
 * Zip (or tar.gz as a fallback) a results folder.
 *
 * @param string $dir Results folder.
 * @return string|null Archive path, or null if no archiver worked.
 */
function aips_sqlb_archive($dir) {
	if (class_exists('ZipArchive')) {
		$zip_path = $dir . '.zip';
		$zip      = new ZipArchive();
		if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
			foreach (aips_sqlb_archive_entries($dir) as $name => $path) {
				$zip->addFile($path, $name);
			}
			if ($zip->close()) {
				@chmod($zip_path, 0600);
				return $zip_path;
			}
		}
	}

	return aips_sqlb_archive_tar($dir);
}

/**
 * Connect with the settings every run needs: native int/float types, charset,
 * a per-query timeout and a READ ONLY session + transaction.
 *
 * @param array       $host            Result of aips_sqlb_parse_db_host().
 * @param array       $const           wp-config.php constants.
 * @param string|null $error           Set to a message on failure.
 * @param bool        $timeout_applied Set to whether a server-side timeout was applied.
 * @return mysqli|null
 */
function aips_sqlb_connect(array $host, array $const, &$error, &$timeout_applied) {
	$error = null;
	$db    = mysqli_init();
	$db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
	$db->options(MYSQLI_OPT_READ_TIMEOUT, AIPS_SQLB_TIMEOUT_SECS * 2);
	$db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
	$connected = @$db->real_connect(
		$host['host'],
		(string) $const['DB_USER'],
		(string) $const['DB_PASSWORD'],
		(string) $const['DB_NAME'],
		$host['port'] !== null ? $host['port'] : 3306,
		$host['socket']
	);
	if (!$connected) {
		$error = 'Database connection failed: ' . $db->connect_error . ' (' . $db->connect_errno . ')';
		return null;
	}
	$charset = isset($const['DB_CHARSET']) && $const['DB_CHARSET'] ? (string) $const['DB_CHARSET'] : 'utf8mb4';
	if (!@$db->set_charset($charset)) {
		@$db->set_charset('utf8mb4');
	}

	$timeout_applied = @$db->query('SET SESSION MAX_EXECUTION_TIME=' . (AIPS_SQLB_TIMEOUT_SECS * 1000)) !== false
		|| @$db->query('SET SESSION max_statement_time=' . AIPS_SQLB_TIMEOUT_SECS) !== false;
	if (@$db->query('SET SESSION TRANSACTION READ ONLY') === false || @$db->query('START TRANSACTION READ ONLY') === false) {
		$error = 'Could not start a read-only transaction: ' . $db->error . ' Refusing to run without it.';
		$db->close();
		return null;
	}
	return $db;
}

/**
 * Run one allowed query and fill $r with its status, columns and rows.
 *
 * @param mysqli $db       Connection.
 * @param array  $r        Query record (modified).
 * @param bool   $redact   Whether to redact secrets.
 * @param int    $redacted Running total of redacted values.
 * @return bool True when the connection was closed (row cap reached) and must be replaced.
 */
function aips_sqlb_execute_query(mysqli $db, array &$r, $redact, &$redacted) {
	$t0  = microtime(true);
	$res = @$db->query($r['sql'], MYSQLI_USE_RESULT);

	if ($res === false) {
		$r['status']      = 'error';
		$r['error']       = $db->error . ' (' . $db->errno . ')';
		$r['duration_ms'] = (int) round((microtime(true) - $t0) * 1000);
		return false;
	}

	$capped = false;
	if ($res instanceof mysqli_result) {
		$fields = $res->fetch_fields();
		$names  = array();
		$seen   = array();
		foreach ($fields as $field) {
			$n = $field->name;
			if (isset($seen[$n])) {
				do {
					$seen[$n]++;
					$candidate = $field->name . '_' . $seen[$n];
				} while (isset($seen[$candidate]));
				$n = $candidate;
			}
			$seen[$n] = 1;
			$names[]  = $n;
		}
		$r['columns'] = $names;
		$force        = $redact ? aips_sqlb_force_columns($names, $fields, $r['sql']) : array();

		$read = 0;
		while (($row = $res->fetch_row()) !== null) {
			if ($read >= AIPS_SQLB_ROW_CAP) {
				$r['truncated'] = true;
				$capped         = true;
				break;
			}
			// Clean (binary mask + truncate) first so redaction only scans bounded strings.
			$assoc = array_map('aips_sqlb_clean_cell', array_combine($names, $row));
			if ($redact) {
				$cnt            = 0;
				$assoc          = aips_sqlb_redact_row($assoc, $cnt, $force);
				$r['redacted'] += $cnt;
				$redacted      += $cnt;
			}
			$r['rows'][] = $assoc;
			$read++;
		}
		if ($capped) {
			// free() would read and discard every remaining row; drop the connection instead.
			$db->close();
		} else {
			$res->free();
		}
		$r['row_count'] = $read;
	}

	$r['status']      = 'ok';
	$r['duration_ms'] = (int) round((microtime(true) - $t0) * 1000);
	return $capped;
}

/**
 * Write one query's full result as JSON.
 *
 * @param string $run_dir Results folder.
 * @param array  $r       Query record.
 * @return void
 */
function aips_sqlb_write_query_json($run_dir, array $r) {
	$flags   = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
	$payload = array(
		'name'        => $r['name'],
		'purpose'     => $r['purpose'],
		'sql'         => $r['sql'],
		'status'      => $r['status'],
		'error'       => $r['error'],
		'row_count'   => $r['row_count'],
		'truncated'   => $r['truncated'],
		'row_cap'     => AIPS_SQLB_ROW_CAP,
		'duration_ms' => $r['duration_ms'],
		'redacted'    => $r['redacted'],
		'columns'     => $r['columns'],
		'rows'        => $r['rows'],
	);
	$file = $run_dir . '/queries/' . $r['file'];
	file_put_contents($file, json_encode($payload, $flags) . "\n");
	@chmod($file, 0600);
}

/* ------------------------------------------------------------------------
 * Main
 * --------------------------------------------------------------------- */

/**
 * Print an error to stderr.
 *
 * @param string $message Message.
 * @return void
 */
function aips_sqlb_fail($message) {
	fwrite(STDERR, "Error: " . $message . "\n");
}

/**
 * Ask for confirmation on a terminal.
 *
 * @param string $prompt Prompt text.
 * @return bool|null True/false for an answer, null when no terminal is available.
 */
function aips_sqlb_confirm($prompt) {
	$in = null;
	if (function_exists('stream_isatty') && stream_isatty(STDIN)) {
		$in = STDIN;
	} elseif (@is_readable('/dev/tty')) {
		$in = @fopen('/dev/tty', 'r');
	}
	if (!$in) {
		return null;
	}
	fwrite(STDERR, $prompt . ' [y/N] ');
	$answer = fgets($in);
	return $answer !== false && strtolower(trim($answer)) === 'y';
}

/**
 * Entry point.
 *
 * @param array $argv Raw argv.
 * @return int Exit code.
 */
function aips_sqlb_main(array $argv) {
	$started_at = microtime(true);
	$opts       = aips_sqlb_parse_args($argv);

	if ($opts['help']) {
		aips_sqlb_usage();
		return 0;
	}
	if ($opts['error'] !== null || $opts['queries'] === null) {
		aips_sqlb_fail($opts['error'] ?: 'No query file given.');
		aips_sqlb_usage();
		return 2;
	}

	// --- wp-config.php ---------------------------------------------------
	$cwd    = getcwd() ?: null;
	$starts = aips_sqlb_start_dirs($argv, aips_sqlb_logical_cwd(getenv('PWD') ?: null, $cwd), __DIR__, $cwd);
	if ($opts['wp_config'] !== null) {
		$config_path = is_file($opts['wp_config']) ? $opts['wp_config'] : null;
		$tried       = array($opts['wp_config']);
	} else {
		list($config_path, $tried) = aips_sqlb_find_wp_config($starts);
	}
	if ($config_path === null) {
		aips_sqlb_fail("wp-config.php not found. Looked in:\n  " . implode("\n  ", array_unique($tried)) . "\nPass --wp-config=/path/to/wp-config.php.");
		return 2;
	}

	$parsed = aips_sqlb_parse_wp_config((string) file_get_contents($config_path));
	$const  = $parsed['constants'];
	foreach (array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST') as $required) {
		if (!array_key_exists($required, $const)) {
			$why = in_array($required, $parsed['unsupported'], true) ? 'is not a plain string literal (e.g. it reads an environment variable)' : 'was not found';
			aips_sqlb_fail($required . ' ' . $why . ' in ' . $config_path . '.');
			return 2;
		}
	}
	$prefix = $parsed['table_prefix'] !== null ? $parsed['table_prefix'] : 'wp_';
	if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
		aips_sqlb_fail('Unsupported characters in $table_prefix.');
		return 2;
	}
	$web_root  = dirname($config_path);
	$web_roots = aips_sqlb_web_roots($config_path, $starts);

	// --- queries ---------------------------------------------------------
	$raw = ($opts['queries'] === '-') ? stream_get_contents(STDIN) : @file_get_contents($opts['queries']);
	if ($raw === false || trim($raw) === '') {
		aips_sqlb_fail('Could not read any queries from ' . ($opts['queries'] === '-' ? 'stdin' : $opts['queries']) . '.');
		return 2;
	}
	$statements = aips_sqlb_split_queries(str_replace('{prefix}', $prefix, $raw));
	if (!$statements) {
		aips_sqlb_fail('No SQL statements found.');
		return 2;
	}

	$results = array();
	foreach ($statements as $idx => $st) {
		$position = $idx + 1;
		$name     = ($st['name'] !== null && $st['name'] !== '') ? $st['name'] : sprintf('query_%02d', $position);
		list($allowed, $reason) = $st['error'] ? array(false, $st['error']) : aips_sqlb_classify($st['sql']);
		$results[] = array(
			'index'       => sprintf('%02d', $position),
			'name'        => $name,
			'purpose'     => $st['purpose'],
			'sql'         => $st['sql'],
			'file'        => aips_sqlb_query_slug($name, $position) . '.json',
			'status'      => $allowed ? 'pending' : 'refused',
			'error'       => $allowed ? null : $reason,
			'row_count'   => 0,
			'truncated'   => false,
			'duration_ms' => 0,
			'columns'     => array(),
			'rows'        => array(),
			'redacted'    => 0,
		);
	}

	// --- target banner + confirmation -----------------------------------
	$host = aips_sqlb_parse_db_host((string) $const['DB_HOST']);
	$is_local = aips_sqlb_is_local_host($host);
	$target   = $const['DB_USER'] . '@' . $const['DB_HOST'] . ' / ' . $const['DB_NAME'] . ' (prefix ' . $prefix . ')';
	echo "wp-config: $config_path\n";
	echo "Target:    $target\n";
	echo 'Queries:   ' . count($results) . ' (' . count(array_filter($results, function ($r) {
		return $r['status'] === 'refused';
	})) . " refused up front)\n";

	if (!$is_local && !$opts['yes']) {
		$answer = aips_sqlb_confirm('Database host is not local. Run against ' . $target . '?');
		if ($answer === null) {
			aips_sqlb_fail('Non-local database host and no terminal to confirm on. Re-run with --yes to proceed.');
			return 2;
		}
		if (!$answer) {
			echo "Aborted.\n";
			return 2;
		}
	}

	// --- output folder ---------------------------------------------------
	$out_base = $opts['out'] !== null ? $opts['out'] : aips_sqlb_default_out_dir();
	foreach ($web_roots as $root) {
		if (aips_sqlb_is_inside($out_base, $root)) {
			aips_sqlb_fail('Refusing to write results inside the WordPress directory (' . $root . "): they would be web-accessible.\nUse --out=/some/path/outside/it.");
			return 2;
		}
	}
	if (!is_dir($out_base) && !@mkdir($out_base, 0700, true) && !is_dir($out_base)) {
		aips_sqlb_fail('Cannot create output directory: ' . $out_base);
		return 2;
	}
	$run_id  = 'sql-batch-' . date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 4) . '-' . substr(preg_replace('/[^A-Za-z0-9]+/', '', (string) $const['DB_NAME']), 0, 20);
	$run_dir = rtrim(str_replace('\\', '/', (string) realpath($out_base)), '/') . '/' . $run_id;
	if (file_exists($run_dir) || (!@mkdir($run_dir . '/queries', 0700, true) && !is_dir($run_dir . '/queries'))) {
		aips_sqlb_fail('Cannot create ' . $run_dir);
		return 2;
	}

	// --- connect ---------------------------------------------------------
	mysqli_report(MYSQLI_REPORT_OFF);
	$timeout_applied = false;
	$db              = aips_sqlb_connect($host, $const, $connect_error, $timeout_applied);
	if ($db === null) {
		aips_sqlb_fail($connect_error);
		return 2;
	}

	// --- environment fingerprint ----------------------------------------
	$site_url = null;
	$res      = @$db->query("SELECT option_value FROM `{$prefix}options` WHERE option_name = 'siteurl' LIMIT 1");
	if ($res instanceof mysqli_result) {
		$row      = $res->fetch_row();
		$site_url = $row ? (string) $row[0] : null;
		$res->free();
	}
	$wp_version = null;
	$version_file = $web_root . '/wp-includes/version.php';
	if (is_file($version_file) && preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string) file_get_contents($version_file, false, null, 0, 4096), $m)) {
		$wp_version = $m[1];
	}
	$plugin_version = null;
	$plugin_file    = dirname(__DIR__) . '/ai-post-scheduler.php';
	if (is_file($plugin_file) && preg_match('/^\s*\*?\s*Version:\s*(\S+)/mi', (string) file_get_contents($plugin_file, false, null, 0, 8192), $m)) {
		$plugin_version = $m[1];
	}
	$git_info = aips_sqlb_git_info(__DIR__);
	$git      = $git_info['commit'] ? trim(($git_info['branch'] ?: 'detached') . ' @ ' . $git_info['commit']) : null;
	$db_server = $db->server_info;

	// --- run -------------------------------------------------------------
	$total     = count($results);
	$redacted  = 0;
	$had_issue = false;

	foreach ($results as &$r) {
		$label = sprintf('[%s/%02d] %s ... ', $r['index'], $total, $r['name']);
		if ($r['status'] === 'refused') {
			echo $label . "REFUSED: {$r['error']}\n";
			$had_issue = true;
		} elseif ($db === null) {
			$r['status'] = 'error';
			$r['error']  = 'No database connection (re-connecting after a row-capped query failed).';
			echo $label . "ERROR: {$r['error']}\n";
			$had_issue = true;
		} else {
			$reconnect = aips_sqlb_execute_query($db, $r, $opts['redact'], $redacted);
			if ($r['status'] === 'ok') {
				echo $label . "ok, {$r['row_count']} row(s)" . ($r['truncated'] ? ' (capped)' : '') . ", {$r['duration_ms']} ms\n";
			} else {
				echo $label . "ERROR: {$r['error']}\n";
				$had_issue = true;
			}
			if ($reconnect) {
				$db = aips_sqlb_connect($host, $const, $connect_error, $timeout_applied);
			}
		}

		// Persist immediately and keep only the report preview in memory.
		aips_sqlb_write_query_json($run_dir, $r);
		$r['rows'] = array_slice($r['rows'], 0, AIPS_SQLB_REPORT_ROWS);
	}
	unset($r);

	if ($db !== null) {
		@$db->query('ROLLBACK');
		$db->close();
	}

	$run = array(
		'run_id'          => $run_id,
		'started'         => date('Y-m-d H:i:s T', (int) $started_at),
		'duration_s'      => round(microtime(true) - $started_at, 1),
		'db_name'         => $const['DB_NAME'],
		'db_host'         => $const['DB_HOST'],
		'db_server'       => $db_server,
		'prefix'          => $prefix,
		'site_url'        => $site_url,
		'wp_version'      => $wp_version,
		'plugin_version'  => $plugin_version,
		'git'             => $git,
		'timeout_applied' => $timeout_applied,
		'redact'          => $opts['redact'],
		'redacted'        => $redacted,
	);
	file_put_contents($run_dir . '/REPORT.md', aips_sqlb_build_report($run, $results));
	@chmod($run_dir . '/REPORT.md', 0600);

	$archive = aips_sqlb_archive($run_dir);

	echo "\n";
	echo 'RESULTS_DIR=' . $run_dir . "\n";
	if ($archive !== null) {
		echo 'RESULTS_ZIP=' . $archive . ' (' . number_format(filesize($archive)) . " bytes)\n";
	} else {
		echo "RESULTS_ZIP=(no zip or tar support available; copy the folder instead)\n";
	}
	echo 'Read first: ' . $run_dir . "/REPORT.md\n";

	return $had_issue ? 1 : 0;
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
	exit(aips_sqlb_main($argv));
}
