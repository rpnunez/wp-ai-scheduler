#!/usr/bin/env php
<?php
/**
 * AI Payload Capture
 *
 * Runs the plugin's real generation pipeline (AIPS_Generator + the real prompt
 * builders + the real AIPS_AI_Service / AI provider / connector) against a set
 * of fixed scenarios, and records — verbatim — every prompt and option sent to
 * AIPS_AI_Service, every canonical parameter handed to the provider, and (by
 * default, unless --offline) the exact HTTP request/response exchanged with
 * the configured Gemini connector. Nothing here reimplements or guesses at a
 * prompt; it watches the real code build and send one.
 *
 * Built for: "I have not been keeping up with what the Post Components Prompt
 * Builder prompts are" and "changes to generation/post content don't
 * accidentally break the rest of generation" — run it before and after a
 * change to a prompt builder, generator, or AI service and diff the reports.
 *
 * Usage (inside the dev stack's web container, where the Gemini connector is
 * already configured from .env's GOOGLE_API_KEY):
 *
 *   docker compose exec web php wp-content/plugins/ai-post-scheduler/bin/ai-payload-capture.php
 *   = bash scripts/ai-payload-capture.sh          (from the repo root)
 *   = make ai-payload-capture                      (preferred)
 *
 * Options:
 *   --scenario=KEY     Run only this scenario. Repeatable. Default: all.
 *   --list              List available scenarios (with descriptions) and exit.
 *   --offline           Simulate every AI HTTP call; no network, no API key needed.
 *                       Captures the exact request shape with a canned response.
 *   --live-images       Also send real image-generation requests (costs real
 *                       money/quota). Default: image generation is simulated;
 *                       everything else (text, JSON, embeddings) is live.
 *   --keep-posts        Do not delete the draft posts/history/attachments this
 *                       run creates. Default: cleaned up at the end of the run.
 *   --out=DIR           Output directory. Default: .artifacts/ai-payload-capture/<run-id>/
 *   --wp-root=PATH      WordPress root. Default: autodetect, else /var/www/html.
 *   -h, --help          Show this help.
 *
 * Output: one <scenario>.json per scenario (every call, byte for byte) plus a
 * single REPORT.md you can read top to bottom or diff against a previous run.
 * API keys and other secrets are redacted from every file.
 *
 * Safety: CLI only, runs against this dev stack's own database (never
 * production), and only ever touches posts/history/attachments/structures it
 * creates itself (tagged and cleaned up at the end of the run).
 *
 * @package AI_Post_Scheduler
 */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This script must be run from the command line.\n");
	exit(2);
}

/* ------------------------------------------------------------------------
 * CLI arguments
 * --------------------------------------------------------------------- */

function aips_capture_parse_args(array $argv) {
	$opts = array(
		'scenarios'   => array(),
		'list'        => false,
		'offline'     => false,
		'live_images' => false,
		'keep_posts'  => false,
		'out'         => null,
		'wp_root'     => null,
		'help'        => false,
		'error'       => null,
	);

	foreach (array_slice($argv, 1) as $arg) {
		if ($arg === '-h' || $arg === '--help') {
			$opts['help'] = true;
		} elseif ($arg === '--list') {
			$opts['list'] = true;
		} elseif ($arg === '--offline') {
			$opts['offline'] = true;
		} elseif ($arg === '--live-images') {
			$opts['live_images'] = true;
		} elseif ($arg === '--keep-posts') {
			$opts['keep_posts'] = true;
		} elseif (strpos($arg, '--scenario=') === 0) {
			$opts['scenarios'][] = substr($arg, 11);
		} elseif (strpos($arg, '--out=') === 0) {
			$opts['out'] = substr($arg, 6);
		} elseif (strpos($arg, '--wp-root=') === 0) {
			$opts['wp_root'] = substr($arg, 10);
		} else {
			$opts['error'] = 'Unknown option: ' . $arg;
		}
	}

	return $opts;
}

function aips_capture_usage() {
	// Print the usage block from this file's own header comment.
	$source = file_get_contents(__FILE__);
	if (preg_match('/\* Usage.*?\n(?:.*\n)*?\*\/\n/s', $source, $m)) {
		echo preg_replace('/^\s*\* ?/m', '', $m[0]);
		return;
	}
	echo "Usage: php bin/ai-payload-capture.php [options]\n";
}

$cli_opts = aips_capture_parse_args($argv);

if ($cli_opts['error']) {
	fwrite(STDERR, $cli_opts['error'] . "\n\n");
	aips_capture_usage();
	exit(2);
}

if ($cli_opts['help']) {
	aips_capture_usage();
	exit(0);
}

/* ------------------------------------------------------------------------
 * WordPress bootstrap
 * --------------------------------------------------------------------- */

/**
 * Find the WordPress root by walking up from this script's real location.
 *
 * @return string|null
 */
function aips_capture_autodetect_wp_root() {
	$dir = dirname(__DIR__); // ai-post-scheduler/
	for ($i = 0; $i < 6; $i++) {
		$dir = dirname($dir);
		if (is_file($dir . '/wp-load.php')) {
			return $dir;
		}
	}
	return null;
}

$wp_root = $cli_opts['wp_root']
	?: getenv('WP_CORE_DIR')
	?: aips_capture_autodetect_wp_root()
	?: '/var/www/html';
$wp_root = rtrim($wp_root, '/');

if (!is_file($wp_root . '/wp-load.php')) {
	fwrite(STDERR, "Could not find wp-load.php under: {$wp_root}\n");
	fwrite(STDERR, "Pass --wp-root=PATH, or run this inside the dev stack's web container.\n");
	exit(2);
}

$_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI']    = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require $wp_root . '/wp-load.php';

if (!class_exists('AIPS_Generator') || !class_exists('AIPS_AI_Service')) {
	fwrite(STDERR, "The ai-post-scheduler plugin is not active on this WordPress install ({$wp_root}).\n");
	exit(2);
}

require __DIR__ . '/ai-payload-capture/class-aips-payload-capture-recorder.php';
require __DIR__ . '/ai-payload-capture/class-aips-payload-capture-provider.php';
require __DIR__ . '/ai-payload-capture/class-aips-payload-capture-ai-service.php';
require __DIR__ . '/ai-payload-capture/class-aips-payload-capture-history-service.php';
require __DIR__ . '/ai-payload-capture/scenarios.php';

$scenarios = aips_capture_scenarios();

if ($cli_opts['list']) {
	echo "Available scenarios:\n\n";
	foreach ($scenarios as $key => $scenario) {
		echo "  {$key}\n";
		echo "    {$scenario['label']}\n";
		echo '    ' . wordwrap($scenario['description'], 96, "\n    ") . "\n\n";
	}
	exit(0);
}

if (!empty($cli_opts['scenarios'])) {
	$unknown = array_diff($cli_opts['scenarios'], array_keys($scenarios));
	if (!empty($unknown)) {
		fwrite(STDERR, 'Unknown scenario(s): ' . implode(', ', $unknown) . "\n");
		fwrite(STDERR, 'Known scenarios: ' . implode(', ', array_keys($scenarios)) . "\n");
		exit(2);
	}
	$scenarios = array_intersect_key($scenarios, array_flip($cli_opts['scenarios']));
}

/* ------------------------------------------------------------------------
 * Credentials: read the connector's API key from .env when it is not
 * already configured on this WordPress install (e.g. docker-entrypoint.sh
 * has not run, or this script runs outside the dev container).
 * --------------------------------------------------------------------- */

/**
 * Parse KEY=VALUE lines from a .env-style file. No eval, no shell expansion.
 *
 * @param string $path File path.
 * @return array<string,string>
 */
function aips_capture_parse_dotenv($path) {
	$values = array();
	if (!is_file($path) || !is_readable($path)) {
		return $values;
	}

	foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
		$line = trim($line);
		if ($line === '' || $line[0] === '#' || !preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) {
			continue;
		}
		$value = trim($m[2]);
		if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
			$value = substr($value, 1, -1);
		}
		$values[$m[1]] = $value;
	}

	return $values;
}

/**
 * Placeholder value used only to satisfy a connector's local "is this
 * configured" check under --offline. It is never sent anywhere: --offline
 * intercepts every AI HTTP request before it reaches the network, so this
 * never needs to look like a real key, and (being process-env-only) it never
 * outlives this run.
 */
const AIPS_CAPTURE_OFFLINE_PLACEHOLDER_KEY = 'aips-payload-capture-offline-placeholder-key';

/**
 * Make sure every locally-authenticated, API-key AI connector that has a
 * value available (container env, or the repo's .env) is actually wired up
 * on this WordPress install — the same thing docker-entrypoint.sh does for a
 * fresh container, done here so the script also works when that has not run.
 *
 * Under --offline, a connector with no real key anywhere still needs to look
 * "configured" to AIPS_AI_Service's own availability guard, or execution
 * never reaches the request-building code --offline exists to capture. A
 * process-local env var (never persisted, never defined as a constant, never
 * written to options) is enough for that and guarantees nothing fake outlives
 * this run or leaks into a real request — --offline's own HTTP interception
 * is what keeps it from ever being sent.
 *
 * @param bool $offline Whether this run is fully simulated.
 * @return void
 */
function aips_capture_ensure_connector_credentials($offline) {
	if (!function_exists('wp_get_connectors')) {
		return;
	}

	$dotenv = null; // Lazily parsed only if a connector actually needs it.

	foreach (wp_get_connectors() as $connector) {
		if (!is_array($connector) || ($connector['type'] ?? '') !== 'ai_provider') {
			continue;
		}

		$auth = isset($connector['authentication']) && is_array($connector['authentication']) ? $connector['authentication'] : array();
		if (($auth['method'] ?? '') !== 'api_key') {
			continue;
		}

		$env_name      = (string) ($auth['env_var_name'] ?? '');
		$constant_name = (string) ($auth['constant_name'] ?? '');
		$setting_name  = (string) ($auth['setting_name'] ?? '');

		if ($env_name === '') {
			continue;
		}

		$value = getenv($env_name);

		if ($value === false || $value === '') {
			if ($dotenv === null) {
				$dotenv = array();
				// Repo root is 3 levels above this script when run from a
				// checkout (bin/ -> ai-post-scheduler -> repo root), and also
				// try the current working directory for a standalone run.
				foreach (array(dirname(__DIR__, 2) . '/.env', getcwd() . '/.env') as $candidate) {
					$dotenv = $dotenv + aips_capture_parse_dotenv($candidate);
				}
			}

			$value = $dotenv[$env_name] ?? '';
		}

		$placeholder = (bool) preg_match('/^(your[_-]|xxx|changeme|placeholder)/i', $value);

		if ($value === '' || $placeholder) {
			if ($offline) {
				// Process-local only: never defined as a constant, never
				// written to an option, so nothing outlives this run.
				putenv($env_name . '=' . AIPS_CAPTURE_OFFLINE_PLACEHOLDER_KEY);
			}

			continue;
		}

		// Make the value visible to getenv() for the rest of this process
		// (the AI Client may check the env var directly) and configure the
		// two persistent paths core also accepts, without overwriting a
		// value an administrator already set on purpose.
		putenv($env_name . '=' . $value);

		if ($constant_name !== '' && !defined($constant_name)) {
			define($constant_name, $value);
		}

		if ($setting_name !== '' && get_option($setting_name, '') === '') {
			update_option($setting_name, $value);
		}
	}
}

aips_capture_ensure_connector_credentials($cli_opts['offline']);

/* ------------------------------------------------------------------------
 * Wire up the capture layer
 * --------------------------------------------------------------------- */

$recorder = new AIPS_Payload_Capture_Recorder($cli_opts['offline'], !$cli_opts['live_images']);
$recorder->add_secret((string) getenv('GOOGLE_API_KEY'), 'GOOGLE_API_KEY');
$recorder->add_secret((string) getenv('OPENAI_API_KEY'), 'OPENAI_API_KEY');
$recorder->add_secret((string) getenv('ANTHROPIC_API_KEY'), 'ANTHROPIC_API_KEY');
$recorder->install();

$real_provider = AIPS_AI_Provider_Factory::create();

if (!$recorder->is_offline() && !$real_provider->is_available()) {
	fwrite(STDERR, "No AI provider is available: " . $real_provider->get_unavailable_reason() . "\n");
	fwrite(STDERR, "Set GOOGLE_API_KEY in .env (or the connector of your choice), or pass --offline to simulate requests instead.\n");
	$recorder->uninstall();
	exit(2);
}

$wrapped_provider = new AIPS_Payload_Capture_Provider($real_provider, $recorder);
$ai_service        = new AIPS_Payload_Capture_AI_Service($recorder, $wrapped_provider);
$history_repository = new AIPS_History_Repository();

// AIPS_Generator writes a history row before any content is generated, so a
// run that errors out or throws before creating a post still leaves one
// behind. Wrapping the real history service tracks every row a scenario
// creates (by ID, not by post_id) so cleanup can remove it either way.
$container             = AIPS_Container::get_instance();
$real_history_service   = $container->has(AIPS_History_Service_Interface::class)
	? $container->make(AIPS_History_Service_Interface::class)
	: new AIPS_History_Service();
$history_service        = new AIPS_Payload_Capture_History_Service($real_history_service);

/* ------------------------------------------------------------------------
 * Run every scenario
 * --------------------------------------------------------------------- */

$run_started_at = AIPS_DateTime::now();
$run_id         = $run_started_at->format('Ymd-His');
$out_dir        = $cli_opts['out'] ?: (dirname(__DIR__) . '/.artifacts/ai-payload-capture/' . $run_id);
if (!is_dir($out_dir) && !mkdir($out_dir, 0775, true) && !is_dir($out_dir)) {
	fwrite(STDERR, "Could not create output directory: {$out_dir}\n");
	exit(2);
}

echo "AI Payload Capture — run {$run_id}\n";
echo 'Provider: ' . $real_provider->get_id() . ' (' . $real_provider->get_label() . ")\n";
echo 'Mode: ' . ($cli_opts['offline'] ? 'offline (fully simulated)' : ('live text/JSON; ' . ($cli_opts['live_images'] ? 'live images' : 'simulated images'))) . "\n";
echo "Scenarios: " . implode(', ', array_keys($scenarios)) . "\n\n";

/**
 * Attachment IDs tied to a post via its featured image and generated content
 * images, for cleanup.
 *
 * @param int $post_id Post ID.
 * @return int[]
 */
function aips_capture_attachment_ids_for_post($post_id) {
	$ids = array();

	$thumbnail_id = get_post_thumbnail_id($post_id);
	if ($thumbnail_id) {
		$ids[] = (int) $thumbnail_id;
	}

	if (class_exists('AIPS_Post_Images_Generated_Repository')) {
		foreach ((new AIPS_Post_Images_Generated_Repository())->get_by_post_id($post_id) as $row) {
			if (!empty($row->attachment_id)) {
				$ids[] = (int) $row->attachment_id;
			}
		}
	}

	return array_values(array_unique(array_filter($ids)));
}

$summary = array();

foreach ($scenarios as $key => $scenario) {
	echo "=== {$key}: {$scenario['label']} ===\n";

	$previous_options = array();
	foreach ($scenario['options'] as $option_name => $option_value) {
		$sentinel                       = get_option($option_name, '__aips_capture_missing__');
		$previous_options[$option_name] = array('existed' => ($sentinel !== '__aips_capture_missing__'), 'value' => $sentinel);
		update_option($option_name, $option_value);
	}
	if (!empty($scenario['options'])) {
		AIPS_Config::get_instance()->flush_option_cache();
	}

	$built       = null;
	$build_error = null;

	try {
		$built = $scenario['build']();
	} catch (Throwable $e) {
		$build_error = $e;
	}

	$outcome    = array('scenario' => $key, 'label' => $scenario['label']);
	$post_id    = null;
	$run_error  = null;

	if ($build_error) {
		$outcome['status'] = 'skipped';
		$outcome['reason'] = $build_error->getMessage();
		echo '  SKIPPED (fixture setup failed): ' . $build_error->getMessage() . "\n\n";
	} else {
		$recorder->begin_scenario($key);
		$history_service->reset_tracking();

		try {
			$generator = new AIPS_Generator(null, $ai_service, null, null, null, null, $history_service);
			$result    = ($scenario['method'] === 'generate_preview')
				? $generator->generate_preview($built['context'])
				: $generator->generate_post($built['context']);

			if ($scenario['method'] === 'generate_preview') {
				$outcome['status'] = is_wp_error($result) ? 'error' : 'ok';
				$outcome['result'] = is_wp_error($result)
					? array('error_code' => $result->get_error_code(), 'error_message' => $result->get_error_message())
					: array('title' => $result['title'] ?? '', 'content_length' => isset($result['content']) ? strlen((string) $result['content']) : 0);
			} elseif (is_wp_error($result)) {
				$outcome['status'] = 'error';
				$outcome['result'] = array('error_code' => $result->get_error_code(), 'error_message' => $result->get_error_message());
			} else {
				$post_id            = (int) $result;
				$outcome['status']  = 'ok';
				$outcome['post_id'] = $post_id;
				$outcome['result']  = array('post_id' => $post_id, 'title' => get_the_title($post_id));
			}
		} catch (Throwable $e) {
			$run_error          = $e;
			$outcome['status']  = 'fatal';
			$outcome['reason']  = $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
		}

		$recorder->end_scenario();

		if ($run_error) {
			echo '  FATAL: ' . $outcome['reason'] . "\n";
		} elseif ($outcome['status'] === 'error') {
			echo '  Generation returned an error (captured): ' . $outcome['result']['error_message'] . "\n";
		} else {
			echo '  OK' . ($post_id ? " (post #{$post_id})" : '') . "\n";
		}

		$capture                  = $recorder->get_scenario_capture($key);
		$outcome['service_calls'] = count($capture['calls']);
		echo '  Service calls captured: ' . count($capture['calls']) . "\n\n";

		if (!$cli_opts['keep_posts']) {
			if ($post_id) {
				foreach (aips_capture_attachment_ids_for_post($post_id) as $attachment_id) {
					wp_delete_attachment($attachment_id, true);
				}
				wp_delete_post($post_id, true);
			}

			// Delete every history row this scenario created, by ID — not by
			// post_id, which generation never reaches on a failed/thrown run.
			$history_repository->delete_bulk($history_service->get_created_ids());
		}

		if (isset($built['cleanup']) && is_callable($built['cleanup'])) {
			try {
				$built['cleanup']();
			} catch (Throwable $e) {
				echo '  (cleanup warning: ' . $e->getMessage() . ")\n";
			}
		}
	}

	foreach ($previous_options as $option_name => $previous) {
		if ($previous['existed']) {
			update_option($option_name, $previous['value']);
		} else {
			delete_option($option_name);
		}
	}
	if (!empty($scenario['options'])) {
		AIPS_Config::get_instance()->flush_option_cache();
	}

	$summary[$key] = $outcome;
}

$recorder->uninstall();

/* ------------------------------------------------------------------------
 * Write reports
 * --------------------------------------------------------------------- */

require __DIR__ . '/ai-payload-capture/class-aips-payload-capture-report.php';

$report = new AIPS_Payload_Capture_Report($recorder);
$report->write(
	$out_dir,
	$scenarios,
	$summary,
	array(
		'run_id'       => $run_id,
		'provider_id'  => $real_provider->get_id(),
		'provider_label' => $real_provider->get_label(),
		'offline'      => $cli_opts['offline'],
		'live_images'  => $cli_opts['live_images'],
		'generated_at' => $run_started_at->toIso8601(),
	)
);

echo "Done. Report: {$out_dir}/REPORT.md\n";

$had_fatal = false;
foreach ($summary as $outcome) {
	if ($outcome['status'] === 'fatal') {
		$had_fatal = true;
	}
}

exit($had_fatal ? 1 : 0);
