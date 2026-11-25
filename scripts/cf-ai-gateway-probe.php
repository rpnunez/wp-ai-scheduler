<?php
/**
 * Cloudflare AI Gateway API probe (Slice 0 of the Cloudflare AI Gateway provider plan).
 *
 * Sends a fixed set of small requests to the Cloudflare AI Gateway REST API and
 * records each request/response pair as a sanitized JSON fixture. The fixtures
 * drive the PHPUnit tests of AIPS_Cloudflare_AI_Gateway_Client, and the printed
 * summary settles the API details the docs leave unconfirmed (response shapes,
 * JSON-schema support, embedding/image formats, error envelope).
 *
 * Standalone CLI script: no WordPress needed. Credentials come from environment
 * variables only and are scrubbed from everything written to disk.
 *
 * Usage (PowerShell):
 *   $env:CLOUDFLARE_ACCOUNT_ID = '...'
 *   $env:CLOUDFLARE_API_TOKEN  = '...'
 *   php scripts/cf-ai-gateway-probe.php
 *
 * Optional environment variables:
 *   CF_AIG_GATEWAY_ID     Gateway id (default "default").
 *   CF_PROBE_TEXT_MODELS  Comma-separated chat models
 *                         (default "@cf/meta/llama-3.3-70b-instruct-fp8-fast,google/gemini-3-flash").
 *                         Third-party models need Unified Billing credits or a BYOK key.
 *   CF_PROBE_EMBED_MODEL  Embedding model (default "@cf/baai/bge-m3").
 *   CF_PROBE_IMAGE_MODEL  Image model (default "@cf/black-forest-labs/flux-1-schnell").
 *   CF_PROBE_OUT_DIR      Fixture directory
 *                         (default ai-post-scheduler/tests/fixtures/cloudflare-ai-gateway).
 *   CF_PROBE_ONLY         Comma-separated probe-name prefixes to run (e.g. "chat,embed").
 *
 * Cost: roughly a dozen tiny requests. Third-party chat models are billed at
 * provider list prices; Workers AI models use the account's Workers AI allowance.
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}

$account_id = (string) getenv('CLOUDFLARE_ACCOUNT_ID');
$api_token  = (string) getenv('CLOUDFLARE_API_TOKEN');

if ($account_id === '' || $api_token === '') {
	fwrite(STDERR, "Set CLOUDFLARE_ACCOUNT_ID and CLOUDFLARE_API_TOKEN first.\n");
	exit(2);
}

$gateway_id   = getenv('CF_AIG_GATEWAY_ID') ?: 'default';
$text_models  = array_filter(array_map('trim', explode(',', getenv('CF_PROBE_TEXT_MODELS') ?: '@cf/meta/llama-3.3-70b-instruct-fp8-fast,google/gemini-3-flash')));
$embed_model  = getenv('CF_PROBE_EMBED_MODEL') ?: '@cf/baai/bge-m3';
$image_model  = getenv('CF_PROBE_IMAGE_MODEL') ?: '@cf/black-forest-labs/flux-1-schnell';
$out_dir      = getenv('CF_PROBE_OUT_DIR') ?: dirname(__DIR__) . '/ai-post-scheduler/tests/fixtures/cloudflare-ai-gateway';
$only         = array_filter(array_map('trim', explode(',', (string) getenv('CF_PROBE_ONLY'))));

const CF_API_BASE = 'https://api.cloudflare.com/client/v4';

if (!is_dir($out_dir) && !mkdir($out_dir, 0775, true)) {
	fwrite(STDERR, "Cannot create {$out_dir}\n");
	exit(3);
}

/**
 * Perform one HTTP request. Returns [status, headers(lowercased => value), raw body, error].
 */
function cf_probe_http(string $method, string $url, array $headers, ?string $body): array {
	if (function_exists('curl_init')) {
		$response_headers = array();
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 120,
			CURLOPT_HTTPHEADER     => array_map(static fn($k, $v) => "{$k}: {$v}", array_keys($headers), $headers),
			CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$response_headers) {
				$parts = explode(':', $line, 2);
				if (count($parts) === 2) {
					$response_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
				}
				return strlen($line);
			},
		));
		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}
		$raw    = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$error  = $raw === false ? curl_error($ch) : '';
		curl_close($ch);
		return array($status, $response_headers, $raw === false ? '' : (string) $raw, $error);
	}

	$context = stream_context_create(array(
		'http' => array(
			'method'        => $method,
			'header'        => implode("\r\n", array_map(static fn($k, $v) => "{$k}: {$v}", array_keys($headers), $headers)),
			'content'       => $body ?? '',
			'timeout'       => 120,
			'ignore_errors' => true,
		),
	));
	$raw              = @file_get_contents($url, false, $context);
	$status           = 0;
	$response_headers = array();
	foreach ($http_response_header ?? array() as $line) {
		if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
			$status = (int) $m[1];
			continue;
		}
		$parts = explode(':', $line, 2);
		if (count($parts) === 2) {
			$response_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
		}
	}
	$error = $raw === false ? (error_get_last()['message'] ?? 'request failed') : '';
	return array($status, $response_headers, $raw === false ? '' : (string) $raw, $error);
}

/**
 * Remove secrets and shrink bulky payloads (base64 images) in a decoded body.
 */
function cf_probe_shrink($value) {
	if (is_array($value)) {
		foreach ($value as $key => $item) {
			$value[$key] = cf_probe_shrink($item);
		}
		return $value;
	}
	if (is_string($value) && strlen($value) > 2000 && preg_match('#^[A-Za-z0-9+/=\s]+$#', $value)) {
		$bytes = base64_decode(substr($value, 0, 64), false);
		$mime  = 'unknown';
		if ($bytes !== false) {
			if (strncmp($bytes, "\x89PNG", 4) === 0) {
				$mime = 'image/png';
			} elseif (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) {
				$mime = 'image/jpeg';
			} elseif (strncmp($bytes, 'RIFF', 4) === 0) {
				$mime = 'image/webp';
			}
		}
		return sprintf('<<base64 omitted: %d chars, detected %s>>', strlen($value), $mime);
	}
	return $value;
}

$results = array();

/**
 * Run one probe and write its fixture.
 */
$probe = static function (string $name, string $method, string $path, ?array $payload, array $extra_headers = array(), ?string $token_override = null) use ($account_id, $api_token, $gateway_id, $out_dir, $only, &$results) {
	if ($only) {
		$match = false;
		foreach ($only as $prefix) {
			if (strpos($name, $prefix) === 0) {
				$match = true;
				break;
			}
		}
		if (!$match) {
			return null;
		}
	}

	$headers = array_merge(array(
		'Authorization'     => 'Bearer ' . ($token_override ?? $api_token),
		'Content-Type'      => 'application/json',
		'cf-aig-gateway-id' => $gateway_id,
		'cf-aig-metadata'   => json_encode(array('aips_probe' => 'slice0', 'aips_probe_name' => $name)),
	), $extra_headers);

	$url   = CF_API_BASE . str_replace('{account_id}', rawurlencode($account_id), $path);
	$body  = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES);
	$start = microtime(true);
	list($status, $response_headers, $raw, $error) = cf_probe_http($method, $url, $headers, $body);
	$ms    = (int) round((microtime(true) - $start) * 1000);

	$decoded = json_decode($raw, true);
	$kept    = array();
	foreach ($response_headers as $key => $value) {
		if (strpos($key, 'cf-aig-') === 0 || in_array($key, array('content-type', 'retry-after'), true)) {
			$kept[$key] = $value;
		}
	}

	$redacted_headers = $headers;
	$redacted_headers['Authorization'] = $token_override === null ? 'Bearer {api_token}' : 'Bearer {invalid_token}';

	$fixture = array(
		'probe'       => $name,
		'recorded_at' => gmdate('c'),
		'duration_ms' => $ms,
		'request'     => array(
			'method'  => $method,
			'path'    => $path,
			'headers' => $redacted_headers,
			'body'    => $payload,
		),
		'response'    => array(
			'status'    => $status,
			'headers'   => $kept,
			'body'      => is_array($decoded) ? cf_probe_shrink($decoded) : $raw,
			'transport_error' => $error,
		),
	);

	$json = json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	// Belt and braces: scrub secrets anywhere they might have been echoed back.
	$json = str_replace(array($api_token, $account_id), array('{api_token}', '{account_id}'), $json);
	file_put_contents($out_dir . '/' . $name . '.json', $json . "\n");

	$results[$name] = array(
		'status' => $status,
		'ms'     => $ms,
		'body'   => is_array($decoded) ? $decoded : null,
		'raw'    => $raw,
		'error'  => $error,
		'headers' => $kept,
	);

	printf("%-40s HTTP %3d  %6d ms\n", $name, $status, $ms);
	return $results[$name];
};

$slug = static fn(string $model): string => trim(preg_replace('#[^a-z0-9]+#', '-', strtolower($model)), '-');

$schema = array(
	'type'       => 'object',
	'properties' => array(
		'title' => array('type' => 'string'),
		'tags'  => array('type' => 'array', 'items' => array('type' => 'string')),
	),
	'required'   => array('title', 'tags'),
	'additionalProperties' => false,
);

// 1. Token checks.
$probe('auth-verify-user-token', 'GET', '/user/tokens/verify', null);
$probe('auth-verify-account-token', 'GET', '/accounts/{account_id}/tokens/verify', null);

// 2. Chat completions per model: basic, conversation, two JSON-schema dialects, json_object.
foreach ($text_models as $model) {
	$s = $slug($model);

	$probe("chat-basic-{$s}", 'POST', '/accounts/{account_id}/ai/v1/chat/completions', array(
		'model'       => $model,
		'messages'    => array(
			array('role' => 'system', 'content' => 'You are terse.'),
			array('role' => 'user', 'content' => 'Reply with exactly: pong'),
		),
		'max_tokens'  => 16,
		'temperature' => 0,
	), array('cf-aig-skip-cache' => 'true'));

	$probe("chat-conversation-{$s}", 'POST', '/accounts/{account_id}/ai/v1/chat/completions', array(
		'model'      => $model,
		'messages'   => array(
			array('role' => 'user', 'content' => 'Remember the word: walnut.'),
			array('role' => 'assistant', 'content' => 'Noted.'),
			array('role' => 'user', 'content' => 'Which word did I ask you to remember? One word.'),
		),
		'max_tokens' => 16,
	), array('cf-aig-skip-cache' => 'true'));

	// OpenAI dialect: json_schema wraps {name, schema, strict}.
	$probe("chat-json-schema-openai-{$s}", 'POST', '/accounts/{account_id}/ai/v1/chat/completions', array(
		'model'           => $model,
		'messages'        => array(array('role' => 'user', 'content' => 'Give a blog title about tea and two tags, as JSON.')),
		'max_tokens'      => 200,
		'response_format' => array(
			'type'        => 'json_schema',
			'json_schema' => array('name' => 'post_idea', 'schema' => $schema, 'strict' => true),
		),
	), array('cf-aig-skip-cache' => 'true'));

	// Workers AI dialect: json_schema is the schema itself.
	$probe("chat-json-schema-workers-{$s}", 'POST', '/accounts/{account_id}/ai/v1/chat/completions', array(
		'model'           => $model,
		'messages'        => array(array('role' => 'user', 'content' => 'Give a blog title about tea and two tags, as JSON.')),
		'max_tokens'      => 200,
		'response_format' => array('type' => 'json_schema', 'json_schema' => $schema),
	), array('cf-aig-skip-cache' => 'true'));

	$probe("chat-json-object-{$s}", 'POST', '/accounts/{account_id}/ai/v1/chat/completions', array(
		'model'           => $model,
		'messages'        => array(array('role' => 'user', 'content' => 'Return JSON with keys title and tags about tea.')),
		'max_tokens'      => 200,
		'response_format' => array('type' => 'json_object'),
	), array('cf-aig-skip-cache' => 'true'));
}

// 3. Cache behaviour: two identical requests with caching allowed.
if ($text_models) {
	$cache_payload = array(
		'model'      => reset($text_models),
		'messages'   => array(array('role' => 'user', 'content' => 'Reply with exactly: cached')),
		'max_tokens' => 8,
	);
	$probe('cache-first', 'POST', '/accounts/{account_id}/ai/v1/chat/completions', $cache_payload, array('cf-aig-cache-ttl' => '300'));
	$probe('cache-second', 'POST', '/accounts/{account_id}/ai/v1/chat/completions', $cache_payload, array('cf-aig-cache-ttl' => '300'));
}

// 4. Embeddings: OpenAI-compatible endpoint, path form, wrapped form.
$embed_texts = array('An orange cloud over the sea.', 'A llama in the Andes.');
$probe('embed-openai-compat', 'POST', '/accounts/{account_id}/ai/v1/embeddings', array('model' => $embed_model, 'input' => $embed_texts));
$probe('embed-run-path', 'POST', '/accounts/{account_id}/ai/run/' . $embed_model, array('text' => $embed_texts));
$probe('embed-run-wrapped', 'POST', '/accounts/{account_id}/ai/run', array('model' => $embed_model, 'input' => array('text' => $embed_texts)));

// 5. Image generation: path form and wrapped form.
$probe('image-run-path', 'POST', '/accounts/{account_id}/ai/run/' . $image_model, array('prompt' => 'A watercolor teapot on a wooden table', 'steps' => 4), array('cf-aig-skip-cache' => 'true'));
$probe('image-run-wrapped', 'POST', '/accounts/{account_id}/ai/run', array('model' => $image_model, 'input' => array('prompt' => 'A watercolor teapot on a wooden table', 'steps' => 4)), array('cf-aig-skip-cache' => 'true'));

// 6. Error envelopes.
$probe('error-invalid-token', 'POST', '/accounts/{account_id}/ai/v1/chat/completions', array(
	'model'    => reset($text_models) ?: '@cf/meta/llama-3.3-70b-instruct-fp8-fast',
	'messages' => array(array('role' => 'user', 'content' => 'hi')),
), array(), 'invalid-token-for-probe');
$probe('error-unknown-model', 'POST', '/accounts/{account_id}/ai/v1/chat/completions', array(
	'model'    => 'openai/aips-model-that-does-not-exist',
	'messages' => array(array('role' => 'user', 'content' => 'hi')),
));
$probe('error-unknown-workers-model', 'POST', '/accounts/{account_id}/ai/v1/chat/completions', array(
	'model'    => '@cf/aips/model-that-does-not-exist',
	'messages' => array(array('role' => 'user', 'content' => 'hi')),
));
$probe('error-bad-input', 'POST', '/accounts/{account_id}/ai/v1/chat/completions', array(
	'model'    => reset($text_models) ?: '@cf/meta/llama-3.3-70b-instruct-fp8-fast',
	'messages' => array(),
));

// ---------------------------------------------------------------------------
// Summary: the facts Slice 0 has to settle.
// ---------------------------------------------------------------------------
$keys_of = static fn($body): string => is_array($body) ? implode(', ', array_keys($body)) : '(not JSON)';

$lines   = array();
$lines[] = '# Cloudflare AI Gateway probe summary';
$lines[] = '';
$lines[] = 'Recorded ' . gmdate('Y-m-d H:i') . ' UTC, gateway `' . $gateway_id . '`.';
$lines[] = '';
$lines[] = '| Probe | HTTP | Top-level keys | Notes |';
$lines[] = '|---|---|---|---|';

foreach ($results as $name => $r) {
	$notes = array();
	$body  = $r['body'];

	if (isset($body['choices'][0]['message']['content'])) {
		$content = $body['choices'][0]['message']['content'];
		$notes[] = 'content: `' . substr(str_replace(array("\n", '|'), array(' ', '/'), is_string($content) ? $content : json_encode($content)), 0, 80) . '`';
		if (strpos($name, 'chat-json') === 0) {
			$parsed  = is_string($content) ? json_decode($content, true) : $content;
			$notes[] = is_array($parsed) && isset($parsed['title'], $parsed['tags']) ? 'schema satisfied' : 'schema NOT satisfied';
		}
	}
	if (isset($body['usage'])) {
		$notes[] = 'usage: ' . implode(', ', array_keys((array) $body['usage']));
	}
	$vector = $body['data'][0]['embedding'] ?? $body['result']['data'][0] ?? $body['data'][0] ?? null;
	if (strpos($name, 'embed') === 0 && is_array($vector)) {
		$notes[] = 'dims: ' . count($vector);
	}
	if (isset($body['result']) && is_array($body['result'])) {
		$notes[] = 'result keys: ' . implode(', ', array_keys($body['result']));
	}
	if (isset($body['errors']) && is_array($body['errors']) && $body['errors']) {
		$notes[] = 'errors: ' . json_encode($body['errors']);
	}
	if (isset($body['error'])) {
		$notes[] = 'error: ' . json_encode($body['error']);
	}
	foreach ($r['headers'] as $key => $value) {
		if (strpos($key, 'cf-aig-') === 0) {
			$notes[] = "{$key}: {$value}";
		}
	}
	if ($r['error'] !== '') {
		$notes[] = 'transport: ' . $r['error'];
	}

	$note_text = str_replace('|', '/', implode('; ', $notes));
	$note_text = str_replace(array($api_token, $account_id), array('{api_token}', '{account_id}'), $note_text);
	$lines[]   = sprintf('| %s | %d | %s | %s |', $name, $r['status'], $keys_of($body), $note_text);
}

$summary = implode("\n", $lines) . "\n";
file_put_contents($out_dir . '/SUMMARY.md', $summary);

echo "\n" . $summary;
echo "\nFixtures written to {$out_dir}\n";
echo "Check them before committing: they should contain no account id or token.\n";
