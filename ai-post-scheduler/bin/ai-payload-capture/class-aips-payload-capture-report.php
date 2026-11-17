<?php
/**
 * AI payload capture: report writer.
 *
 * Turns a recorder's captured data into files on disk: one JSON file per
 * scenario (the full, verbatim capture) and a single Markdown report meant to
 * be read top to bottom, or diffed against a previous run after a prompt
 * builder / generator / AI service change.
 *
 * Dev tooling only: loaded by bin/ai-payload-capture.php, never by the plugin.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Payload_Capture_Report {

	/** @var AIPS_Payload_Capture_Recorder */
	private $recorder;

	public function __construct(AIPS_Payload_Capture_Recorder $recorder) {
		$this->recorder = $recorder;
	}

	/**
	 * Write every report file for a run.
	 *
	 * @param string $out_dir   Output directory (already created).
	 * @param array  $scenarios Scenario definitions, keyed by scenario key.
	 * @param array  $summary   Per-scenario outcome, keyed by scenario key.
	 * @param array  $run_meta  Run metadata (run_id, provider_id, offline, ...).
	 * @return void
	 */
	public function write($out_dir, array $scenarios, array $summary, array $run_meta) {
		$manifest = array(
			'run'       => $run_meta,
			'scenarios' => $summary,
		);

		$this->write_file($out_dir . '/manifest.json', $this->json($manifest));

		foreach ($scenarios as $key => $scenario) {
			$capture = $this->recorder->get_scenario_capture($key);
			$this->write_file(
				$out_dir . '/' . $key . '.json',
				$this->json(array(
					'scenario' => $key,
					'label'    => $scenario['label'],
					'outcome'  => $summary[$key] ?? null,
					'capture'  => $capture,
				))
			);
		}

		$this->write_file($out_dir . '/REPORT.md', $this->render_markdown($scenarios, $summary, $run_meta));
	}

	/**
	 * @param string $path    File path.
	 * @param string $content File content, redacted before writing.
	 * @return void
	 */
	private function write_file($path, $content) {
		file_put_contents($path, $this->recorder->redact($content));
	}

	/**
	 * @param mixed $value Value to encode.
	 * @return string
	 */
	private function json($value) {
		return (string) wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	/**
	 * @param array $scenarios Scenario definitions.
	 * @param array $summary   Per-scenario outcomes.
	 * @param array $run_meta  Run metadata.
	 * @return string
	 */
	private function render_markdown(array $scenarios, array $summary, array $run_meta) {
		$lines = array();

		$lines[] = '# AI Payload Capture — run ' . $run_meta['run_id'];
		$lines[] = '';
		$lines[] = 'Generated: ' . $run_meta['generated_at'];
		$lines[] = 'Provider: ' . $run_meta['provider_id'] . ' (' . $run_meta['provider_label'] . ')';
		$lines[] = 'Mode: ' . ($run_meta['offline']
			? 'offline — every AI HTTP request was simulated, nothing left this machine.'
			: ('live text/JSON against the real connector' . ($run_meta['live_images'] ? '; live image generation.' : '; image-generation requests were simulated (pass --live-images for real ones).')));
		$lines[] = '';
		$lines[] = 'API keys and other secrets are redacted everywhere below, including inside request/response bodies.';
		$lines[] = '';
		$lines[] = '## Scenarios';
		$lines[] = '';
		foreach ($scenarios as $key => $scenario) {
			$outcome = $summary[$key] ?? array('status' => 'unknown');
			$lines[] = sprintf('- **%s** — %s (%s)', $key, $scenario['label'], $this->status_label($outcome));
		}
		$lines[] = '';

		foreach ($scenarios as $key => $scenario) {
			$lines = array_merge($lines, $this->render_scenario($key, $scenario, $summary[$key] ?? array('status' => 'unknown')));
		}

		return implode("\n", $lines) . "\n";
	}

	/**
	 * @param array $outcome Scenario outcome.
	 * @return string
	 */
	private function status_label(array $outcome) {
		switch ($outcome['status'] ?? '') {
			case 'ok':
				return 'ok';
			case 'error':
				return 'generation returned an error — captured below';
			case 'fatal':
				return 'FAILED TO RUN: ' . ($outcome['reason'] ?? '');
			case 'skipped':
				return 'skipped: ' . ($outcome['reason'] ?? '');
			default:
				return (string) ($outcome['status'] ?? 'unknown');
		}
	}

	/**
	 * @param string $key      Scenario key.
	 * @param array  $scenario Scenario definition.
	 * @param array  $outcome  Scenario outcome.
	 * @return string[] Markdown lines.
	 */
	private function render_scenario($key, array $scenario, array $outcome) {
		$lines   = array();
		$lines[] = '---';
		$lines[] = '';
		$lines[] = '## ' . $key . ': ' . $scenario['label'];
		$lines[] = '';
		$lines[] = $scenario['description'];
		$lines[] = '';
		$lines[] = '**Outcome:** ' . $this->status_label($outcome);
		$lines[] = '';

		if (($outcome['status'] ?? '') === 'skipped' || ($outcome['status'] ?? '') === 'fatal') {
			return $lines;
		}

		$capture = $this->recorder->get_scenario_capture($key);

		if (empty($capture['calls'])) {
			$lines[] = '_No AI service calls were captured for this scenario._';
			$lines[] = '';
			return $lines;
		}

		foreach ($capture['calls'] as $call) {
			$lines = array_merge($lines, $this->render_call($call));
		}

		if (!empty($capture['unattributed_http'])) {
			$lines[] = '<details><summary>HTTP requests not attributed to an AI service call (' . count($capture['unattributed_http']) . ')</summary>';
			$lines[] = '';
			foreach ($capture['unattributed_http'] as $exchange) {
				$lines[] = '- `' . $exchange['request']['method'] . ' ' . $exchange['request']['url'] . '`'
					. ($exchange['note'] ? ' — ' . $exchange['note'] : '');
			}
			$lines[] = '';
			$lines[] = '</details>';
			$lines[] = '';
		}

		return $lines;
	}

	/**
	 * @param array $call One captured AIPS_AI_Service call.
	 * @return string[] Markdown lines.
	 */
	private function render_call(array $call) {
		$lines   = array();
		$indent  = $call['parent_seq'] ? '  ' : '';
		$heading = sprintf(
			'%s### Call %d — %s %s(%s)',
			$indent,
			$call['seq'],
			$call['component'] !== '' ? '`' . $call['component'] . '`' : '(uncategorized)',
			$call['parent_seq'] ? '· part of call ' . $call['parent_seq'] . ' ' : '',
			$call['method']
		);

		$lines[] = $heading;
		$lines[] = '';
		if ($call['caller'] !== '') {
			$lines[] = $indent . '*Called from `' . $call['caller'] . '`.*';
			$lines[] = '';
		}
		$lines[] = $indent . 'Duration: ' . ($call['duration_ms'] ?? '?') . ' ms';
		$lines[] = '';

		$lines[] = $indent . '**Prompt sent to AIPS_AI_Service** (verbatim):';
		$lines[] = $indent . '```';
		$lines   = array_merge($lines, $this->indent_lines($this->as_text($call['request']['prompt']), $indent));
		$lines[] = $indent . '```';
		$lines[] = '';

		$options = $call['request']['options'];
		if (!empty($options)) {
			$lines[] = $indent . '**Options passed alongside the prompt:**';
			$lines[] = $indent . '```json';
			$lines   = array_merge($lines, $this->indent_lines($this->json($options), $indent));
			$lines[] = $indent . '```';
			$lines[] = '';
		}

		foreach ($call['provider_calls'] as $provider_call) {
			$lines = array_merge($lines, $this->render_provider_call($provider_call, $indent));
		}

		$lines[] = $indent . '**Result returned to the caller:**';
		$lines[] = $indent . '```json';
		$lines   = array_merge($lines, $this->indent_lines($this->json($call['result']), $indent));
		$lines[] = $indent . '```';
		$lines[] = '';

		return $lines;
	}

	/**
	 * @param array  $provider_call One captured provider call.
	 * @param string $indent        Leading indent for nested rendering.
	 * @return string[] Markdown lines.
	 */
	private function render_provider_call(array $provider_call, $indent) {
		$lines   = array();
		$lines[] = $indent . sprintf('**Provider transport call** (attempt %d, `%s`), %d ms:', $provider_call['attempt'], $provider_call['method'], $provider_call['duration_ms'] ?? 0);
		$lines[] = $indent . '```json';
		$lines   = array_merge($lines, $this->indent_lines($this->json($provider_call['params']), $indent));
		$lines[] = $indent . '```';
		$lines[] = '';

		foreach ($provider_call['http'] as $exchange) {
			$lines = array_merge($lines, $this->render_http_exchange($exchange, $indent));
		}

		return $lines;
	}

	/**
	 * @param array  $exchange One captured HTTP request/response pair.
	 * @param string $indent   Leading indent for nested rendering.
	 * @return string[] Markdown lines.
	 */
	private function render_http_exchange(array $exchange, $indent) {
		$lines   = array();
		$summary = sprintf('%s `%s %s`, %d ms%s', $indent, $exchange['request']['method'], $exchange['request']['url'], $exchange['duration_ms'] ?? 0, $exchange['note'] ? ' — ' . $exchange['note'] : '');

		$lines[] = '<details><summary>' . trim(str_replace('`', '&#96;', $summary)) . '</summary>';
		$lines[] = '';

		$lines[] = $indent . 'Request headers: `' . $this->json($exchange['request']['headers']) . '`';
		$lines[] = '';
		if ($exchange['request']['body']) {
			$lines[] = $indent . 'Request body:';
			$lines[] = $indent . '```json';
			$lines   = array_merge($lines, $this->indent_lines($this->json($exchange['request']['body']['json'] ?? $exchange['request']['body']), $indent));
			$lines[] = $indent . '```';
			$lines[] = '';
		}

		$response = $exchange['response'];
		if (isset($response['error_code'])) {
			$lines[] = $indent . 'Response: WP_Error `' . $response['error_code'] . '`: ' . $response['error_message'];
		} else {
			$lines[] = $indent . 'Response: HTTP ' . $response['status'];
			$lines[] = $indent . 'Response headers: `' . $this->json($response['headers']) . '`';
			$lines[] = '';
			if (!empty($response['body'])) {
				$lines[] = $indent . 'Response body:';
				$lines[] = $indent . '```json';
				$lines   = array_merge($lines, $this->indent_lines($this->json($response['body']['json'] ?? $response['body']), $indent));
				$lines[] = $indent . '```';
			}
		}
		$lines[] = '';
		$lines[] = '</details>';
		$lines[] = '';

		return $lines;
	}

	/**
	 * @param mixed $value Prompt/text value.
	 * @return string
	 */
	private function as_text($value) {
		if (is_string($value)) {
			return $value;
		}

		return $this->json($value);
	}

	/**
	 * Indent every line of a block of text for nested rendering.
	 *
	 * @param string $text   Multi-line text.
	 * @param string $indent Indent to prepend to each line.
	 * @return string[]
	 */
	private function indent_lines($text, $indent) {
		if ($indent === '') {
			return explode("\n", $text);
		}

		return array_map(static function ($line) use ($indent) {
			return $indent . $line;
		}, explode("\n", $text));
	}
}
