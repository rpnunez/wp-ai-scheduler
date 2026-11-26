<?php
/**
 * Claude Code hooks for wp-ai-scheduler (see .claude/settings.json).
 *
 * Written in PHP rather than bash + jq because PHP is the one runtime that is
 * guaranteed on every machine that works on this repo (Windows/XAMPP, WSL,
 * the Docker web container and the cloud VM), while jq is not.
 *
 * Usage: php .claude/hooks/aips-hooks.php <mode>   (hook JSON on stdin)
 *
 * Modes:
 *   guard-bash     PreToolUse  Bash         Enforces the TESTING.md agent test protocol.
 *   post-edit      PostToolUse Write|Edit   php -l, repository boundary, JS UI rules.
 *   session-start  SessionStart             Prints dev-stack status into the context.
 *
 * Every mode fails open: an internal error never blocks Claude.
 */

if (PHP_SAPI !== 'cli') {
	exit(0);
}

$mode  = $argv[1] ?? '';
$raw   = stream_get_contents(STDIN);
$input = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($input)) {
	$input = array();
}

$project_dir = getenv('CLAUDE_PROJECT_DIR');
if (!$project_dir || !is_dir($project_dir)) {
	$project_dir = dirname(__DIR__, 2);
}
$project_dir = rtrim(str_replace('\\', '/', $project_dir), '/');

try {
	switch ($mode) {
		case 'guard-bash':
			aips_hook_guard_bash($input);
			break;
		case 'post-edit':
			aips_hook_post_edit($input, $project_dir);
			break;
		case 'session-start':
			aips_hook_session_start($project_dir);
			break;
	}
} catch (Throwable $e) {
	// Fail open.
}
exit(0);

/**
 * Print a JSON hook response and stop.
 *
 * @param array $payload Hook output.
 */
function aips_hook_emit(array $payload) {
	echo json_encode($payload, JSON_UNESCAPED_SLASHES);
	exit(0);
}

/**
 * PreToolUse(Bash): block commands that TESTING.md forbids for agents.
 *
 * @param array $input Hook input.
 */
function aips_hook_guard_bash(array $input) {
	$cmd = (string) ($input['tool_input']['command'] ?? '');
	if ('' === trim($cmd)) {
		return;
	}

	$deny = array(
		'/\bmake\s+(down|clean|prune)\b/'                                => 'Destroys containers or data volumes. Use `make stop` / `make test-stop` instead (TESTING.md, clean-up rule 3).',
		'/\bdocker(-compose|\s+compose)?\b.*\bdown\b.*(\s-v\b|--volumes)/' => 'Deletes data volumes. Use `make stop` instead (TESTING.md, clean-up rule 3).',
		'/\bdocker\s+volume\s+(rm|prune)\b/'                              => 'Deletes data volumes. Never remove AIPS volumes from an agent session.',
		'/\bdocker\s+(container\s+)?rm\b[^|;&]*\s-[a-zA-Z]*v/'            => 'Removes the container AND its anonymous volumes. Use `docker rm <name>` without -v for stale exited containers.',
		'/\bbash(\.exe)?\s+-lc\b/'                                        => 'Do not wrap test runs in `bash -lc`. Run `make test-session ARGS="..."` directly (TESTING.md rule 1).',
		'#(^|[\s;&|])(bash\s+)?(\./)?scripts/run-docker-test\.sh\b#'       => 'Do not call scripts/run-docker-test.sh directly. Use `make test-session ARGS="tests/Test_X.php"` (TESTING.md rule 1).',
		'/\bdocker(-compose|\s+compose)?\s+exec\b.*\bphpunit\b/'          => 'Do not hand-roll `docker compose exec ... phpunit`. Use `make test-session ARGS="..."` (TESTING.md rule 1).',
		'/\bmake\s+test[\w-]*\b[^|]*\|\s*(grep|head|tail)\b/'             => 'Do not filter test output. Redirect it to a file in your scratchpad and read that (TESTING.md rule 3).',
		'/\bmake\s+test[\w-]*\b.*(&&|;)\s*make\s+test/'                   => 'One invocation per batch: pass every file in a single ARGS instead of chaining runs (TESTING.md rule 2).',
	);

	foreach ($deny as $pattern => $reason) {
		if (preg_match($pattern, $cmd)) {
			aips_hook_emit(array(
				'hookSpecificOutput' => array(
					'hookEventName'            => 'PreToolUse',
					'permissionDecision'       => 'deny',
					'permissionDecisionReason' => 'AIPS guard: ' . $reason,
				),
			));
		}
	}

	// Raw PHPUnit is legitimate in cloud sessions (no Docker stack) and for the
	// host-side live-AI groups, so ask instead of deny everywhere else.
	$is_cloud = 'true' === strtolower((string) getenv('CLAUDE_CODE_REMOTE'));
	if (!$is_cloud && preg_match('#(^|[\s;&|/])phpunit(\s|$)|\bcomposer\s+test\b#', $cmd) && !preg_match('/\bmake\s+test/', $cmd)) {
		aips_hook_emit(array(
			'hookSpecificOutput' => array(
				'hookEventName'            => 'PreToolUse',
				'permissionDecision'       => 'ask',
				'permissionDecisionReason' => 'AIPS guard: local PHPUnit runs go through `make test-session ARGS="..."`. Raw phpunit is only for host-side live-AI groups or cloud sessions.',
			),
		));
	}
}

/**
 * PostToolUse(Write|Edit): lint PHP, enforce the repository boundary, and
 * flag newly added alert()/confirm()/location.reload() in admin JS.
 *
 * @param array  $input       Hook input.
 * @param string $project_dir Repo root.
 */
function aips_hook_post_edit(array $input, $project_dir) {
	$tool  = (string) ($input['tool_name'] ?? '');
	$ti    = $input['tool_input'] ?? array();
	$file  = (string) ($ti['file_path'] ?? ($input['tool_response']['filePath'] ?? ''));
	if ('' === $file || !is_file($file)) {
		return;
	}
	$norm     = str_replace('\\', '/', $file);
	$problems = array();

	if (preg_match('/\.php$/i', $norm)) {
		$out = array();
		$rc  = 0;
		exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $rc);
		if (0 !== $rc) {
			$problems[] = "php -l failed:\n" . trim(implode("\n", $out));
		}

		if (preg_match('#/ai-post-scheduler/includes/.*class-aips-[^/]*-(controller|service)\.php$#', $norm)) {
			$tool_path = $project_dir . '/ai-post-scheduler/tools/check-repository-boundary.php';
			if (is_file($tool_path)) {
				$out = array();
				$rc  = 0;
				exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tool_path) . ' 2>&1', $out, $rc);
				if (0 !== $rc) {
					$problems[] = "Repository boundary violated (no \$wpdb in controllers/services; move SQL to a repository):\n" . trim(implode("\n", $out));
				}
			}
		}
	}

	if (preg_match('#/ai-post-scheduler/assets/js/[^/]+\.js$#', $norm)) {
		if ('Edit' === $tool) {
			$before = (string) ($ti['old_string'] ?? '');
			$after  = (string) ($ti['new_string'] ?? '');
		} else {
			$before = aips_hook_git_head_content($project_dir, $norm);
			$after  = (string) file_get_contents($file);
		}
		$added = array();
		foreach (aips_hook_js_rules() as $label => $pattern) {
			$delta = preg_match_all($pattern, $after) - preg_match_all($pattern, $before);
			if ($delta > 0) {
				$added[] = $label;
			}
		}
		if ($added) {
			$problems[] = 'New ' . implode(', ', $added) . ' added. Use AIPS.Utilities.showToast() for messages, AIPS.Utilities.confirm() for confirmations, and re-fetch + AIPS.Templates.render() instead of reloading (CLAUDE.md JavaScript rules).';
		}
	}

	if ($problems) {
		aips_hook_emit(array(
			'decision' => 'block',
			'reason'   => 'AIPS post-edit check for ' . basename($norm) . ":\n- " . implode("\n- ", $problems),
		));
	}
}

/**
 * Patterns for the CLAUDE.md JavaScript rules.
 *
 * @return array<string,string>
 */
function aips_hook_js_rules() {
	return array(
		'alert()'           => '/(?<![\w.$])alert\s*\(/',
		'confirm()'         => '/(?<![\w.$])confirm\s*\(|window\.confirm\s*\(/',
		'location.reload()' => '/location\.reload\s*\(/',
	);
}

/**
 * File content at HEAD, or '' for a new/untracked file.
 *
 * @param string $project_dir Repo root.
 * @param string $file        Absolute path (forward slashes).
 * @return string
 */
function aips_hook_git_head_content($project_dir, $file) {
	$rel = ltrim(substr($file, strlen($project_dir)), '/');
	if (0 !== stripos($file, $project_dir . '/')) {
		return '';
	}
	$out = array();
	$rc  = 0;
	exec('git -C ' . escapeshellarg($project_dir) . ' show ' . escapeshellarg('HEAD:' . $rel) . ' 2>&1', $out, $rc);
	return 0 === $rc ? implode("\n", $out) : '';
}

/**
 * SessionStart: tell Claude which dev stack this checkout owns and its state.
 *
 * @param string $project_dir Repo root.
 */
function aips_hook_session_start($project_dir) {
	$lines = array();

	if ('true' === strtolower((string) getenv('CLAUDE_CODE_REMOTE'))) {
		$lines[] = 'AIPS: cloud session. No Docker stack: run PHPUnit with `cd ai-post-scheduler && composer test` (see TESTING.md).';
		aips_hook_emit(array(
			'hookSpecificOutput' => array(
				'hookEventName'     => 'SessionStart',
				'additionalContext' => implode("\n", $lines),
			),
		));
	}

	$env      = aips_hook_read_env($project_dir . '/.env');
	$instance = $env['AIPS_INSTANCE_ID'] ?? '';
	if (!$env) {
		$lines[] = 'AIPS: this checkout has no .env yet (never provisioned). `make test-session` creates its own instance on first run; run `./start-dev.sh` only for a browsable site.';
	} else {
		$port    = $env['WP_PORT'] ?? '8080';
		$lines[] = sprintf(
			'AIPS dev stack: instance %s, WordPress http://localhost:%s (admin: http://localhost:%s/wp-admin), MySQL port %s.',
			'' !== $instance ? $instance : '(none)',
			$port,
			$port,
			$env['MYSQL_PORT'] ?? '3307'
		);
	}

	$names = array_filter(array($env['AIPS_WEB_CONTAINER'] ?? '', $env['AIPS_DB_CONTAINER'] ?? ''));
	if ($names) {
		$out = array();
		$rc  = 0;
		$filters = '';
		foreach ($names as $n) {
			$filters .= ' --filter ' . escapeshellarg('name=^' . $n . '$');
		}
		exec('docker ps -a' . $filters . ' --format ' . escapeshellarg('{{.Names}}: {{.Status}}') . ' 2>&1', $out, $rc);
		if (0 === $rc) {
			$lines[] = $out ? 'Containers: ' . implode('; ', $out) : 'Containers: not created yet.';
		} else {
			$lines[] = 'Docker is not reachable right now (is Docker Desktop running?).';
		}
	}

	if ($lines) {
		aips_hook_emit(array(
			'hookSpecificOutput' => array(
				'hookEventName'     => 'SessionStart',
				'additionalContext' => implode("\n", $lines),
			),
		));
	}
}

/**
 * Minimal .env parser (KEY=value, no eval).
 *
 * @param string $path File path.
 * @return array<string,string>
 */
function aips_hook_read_env($path) {
	if (!is_file($path)) {
		return array();
	}
	$vars = array();
	foreach ((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', (string) $line, $m)) {
			$vars[$m[1]] = trim(trim($m[2]), "\"'");
		}
	}
	return $vars;
}
