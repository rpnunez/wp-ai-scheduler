<?php
/**
 * AI Error Classifier Service
 *
 * Classifies raw AI errors into standardized categories with human-friendly
 * diagnostic labels, severity badges, and prescriptive resolution guidance.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Error_Classifier {

	/**
	 * Recognized error categories.
	 */
	const CATEGORY_RATE_LIMIT     = 'rate_limit';
	const CATEGORY_CONTEXT_LENGTH = 'context_length';
	const CATEGORY_AUTH           = 'auth';
	const CATEGORY_TIMEOUT        = 'timeout';
	const CATEGORY_PROVIDER_DOWN  = 'provider_down';
	const CATEGORY_CONTENT_POLICY = 'content_policy';
	const CATEGORY_JSON_PARSING   = 'json_parsing';
	const CATEGORY_GENERAL        = 'general';

	/**
	 * Placeholder written in place of a redacted secret value.
	 */
	const REDACTED_SECRET = '***[REDACTED_SECRET]***';

	/**
	 * Get definitions and metadata for all supported categories.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_categories() {
		return array(
			self::CATEGORY_RATE_LIMIT     => array(
				'slug'        => self::CATEGORY_RATE_LIMIT,
				'label'       => __('Rate Limit / Quota Exceeded', 'ai-post-scheduler'),
				'short_label' => __('Rate Limit (429)', 'ai-post-scheduler'),
				'badge_class' => 'aips-badge-warning',
				'icon'        => 'dashicons-warning',
				'severity'    => 'warning',
			),
			self::CATEGORY_CONTEXT_LENGTH => array(
				'slug'        => self::CATEGORY_CONTEXT_LENGTH,
				'label'       => __('Context Window Exceeded', 'ai-post-scheduler'),
				'short_label' => __('Context Window', 'ai-post-scheduler'),
				'badge_class' => 'aips-badge-purple',
				'icon'        => 'dashicons-editor-expand',
				'severity'    => 'error',
			),
			self::CATEGORY_AUTH           => array(
				'slug'        => self::CATEGORY_AUTH,
				'label'       => __('Authentication Failed', 'ai-post-scheduler'),
				'short_label' => __('Auth Error (401)', 'ai-post-scheduler'),
				'badge_class' => 'aips-badge-danger',
				'icon'        => 'dashicons-lock',
				'severity'    => 'critical',
			),
			self::CATEGORY_TIMEOUT        => array(
				'slug'        => self::CATEGORY_TIMEOUT,
				'label'       => __('Connection / Gateway Timeout', 'ai-post-scheduler'),
				'short_label' => __('Timeout', 'ai-post-scheduler'),
				'badge_class' => 'aips-badge-info',
				'icon'        => 'dashicons-clock',
				'severity'    => 'warning',
			),
			self::CATEGORY_PROVIDER_DOWN  => array(
				'slug'        => self::CATEGORY_PROVIDER_DOWN,
				'label'       => __('Provider Outage / Server Error', 'ai-post-scheduler'),
				'short_label' => __('Provider Down (5xx)', 'ai-post-scheduler'),
				'badge_class' => 'aips-badge-secondary',
				'icon'        => 'dashicons-cloud',
				'severity'    => 'warning',
			),
			self::CATEGORY_CONTENT_POLICY => array(
				'slug'        => self::CATEGORY_CONTENT_POLICY,
				'label'       => __('Content Policy Violation', 'ai-post-scheduler'),
				'short_label' => __('Policy Filter', 'ai-post-scheduler'),
				'badge_class' => 'aips-badge-danger',
				'icon'        => 'dashicons-shield',
				'severity'    => 'error',
			),
			self::CATEGORY_JSON_PARSING   => array(
				'slug'        => self::CATEGORY_JSON_PARSING,
				'label'       => __('JSON / Format Parsing Error', 'ai-post-scheduler'),
				'short_label' => __('JSON Parse', 'ai-post-scheduler'),
				'badge_class' => 'aips-badge-teal',
				'icon'        => 'dashicons-media-code',
				'severity'    => 'error',
			),
			self::CATEGORY_GENERAL        => array(
				'slug'        => self::CATEGORY_GENERAL,
				'label'       => __('General Error', 'ai-post-scheduler'),
				'short_label' => __('General', 'ai-post-scheduler'),
				'badge_class' => 'aips-badge-light',
				'icon'        => 'dashicons-info-outline',
				'severity'    => 'info',
			),
		);
	}

	/**
	 * Classify raw error details into a structured classification object.
	 *
	 * @param array|string $details Raw error details array or string message.
	 * @return array
	 */
	public static function classify($details) {
		$raw_message = '';
		$http_code   = 0;
		$error_type  = '';
		$context     = array();

		if (is_array($details)) {
			$context = isset($details['context']) && is_array($details['context']) ? $details['context'] : array();

			if (!empty($context['http_code'])) {
				$http_code = (int) $context['http_code'];
			} elseif (!empty($details['http_code'])) {
				$http_code = (int) $details['http_code'];
			}

			if (!empty($context['error_type'])) {
				$error_type = (string) $context['error_type'];
			} elseif (!empty($details['error_type'])) {
				$error_type = (string) $details['error_type'];
			}

			if (!empty($context['error']) && is_string($context['error'])) {
				$raw_message = $context['error'];
			} elseif (!empty($details['message']) && is_string($details['message'])) {
				$raw_message = $details['message'];
			}
		} elseif (is_string($details)) {
			$raw_message = $details;
		}

		$haystack = strtolower($raw_message . ' ' . $error_type);

		// Extract HTTP code from message if not explicitly passed
		if (0 === $http_code) {
			if (preg_match('/\b(400|401|403|404|408|429|500|502|503|504)\b/', $haystack, $m)) {
				$http_code = (int) $m[1];
			}
		}

		$category = self::determine_category($http_code, $haystack);
		$meta     = self::get_category_meta($category);
		$guidance = self::get_resolution_guidance($category, $http_code, $raw_message);

		return array(
			'category'    => $category,
			'label'       => $meta['label'],
			'short_label' => $meta['short_label'],
			'badge_class' => $meta['badge_class'],
			'icon'        => $meta['icon'],
			'severity'    => $meta['severity'],
			'http_code'   => $http_code > 0 ? $http_code : null,
			'root_cause'  => $guidance['root_cause'],
			'action'      => $guidance['action'],
			'action_link' => $guidance['action_link'],
			'link_label'  => $guidance['link_label'],
		);
	}

	/**
	 * Ordered classification rules. The first match wins, so a row belongs to one category.
	 *
	 * @return array<int, array{category:string,http_codes:array<int,int>,needles:array<int,string>}>
	 */
	private static function category_match_rules() {
		return array(
			array(
				'category'   => self::CATEGORY_RATE_LIMIT,
				'http_codes' => array(429),
				'needles'    => array(
					'rate_limit',
					'rate limit',
					'too many requests',
					'insufficient_quota',
					'quota exceeded',
					'exceeded your current quota',
				),
			),
			array(
				'category'   => self::CATEGORY_AUTH,
				'http_codes' => array(401, 403),
				'needles'    => array(
					'invalid_api_key',
					'invalid api key',
					'incorrect api key',
					'authentication_error',
					'permission_denied',
					'unauthorized',
					'forbidden',
				),
			),
			array(
				'category'   => self::CATEGORY_CONTEXT_LENGTH,
				'http_codes' => array(),
				'needles'    => array(
					'context_length_exceeded',
					'context length',
					'maximum context',
					'tokens exceeded',
					'prompt is too long',
					'reduce the length of the messages',
					'context_window_exceeded',
					'token limit',
				),
			),
			array(
				'category'   => self::CATEGORY_TIMEOUT,
				'http_codes' => array(408, 504),
				'needles'    => array(
					'curl error 28',
					'operation timed out',
					'timed out',
					'gateway timeout',
					'request timeout',
				),
			),
			array(
				'category'   => self::CATEGORY_PROVIDER_DOWN,
				'http_codes' => array(500, 502, 503),
				'needles'    => array(
					'internal_server_error',
					'internal server error',
					'bad gateway',
					'service_unavailable',
					'service unavailable',
					'overloaded',
					'server error',
				),
			),
			array(
				'category'   => self::CATEGORY_CONTENT_POLICY,
				'http_codes' => array(),
				'needles'    => array(
					'content_policy',
					'safety_policy',
					'blocked by safety filters',
					'harmful content',
					'moderation',
					'flagged by moderation',
				),
			),
			array(
				'category'   => self::CATEGORY_JSON_PARSING,
				'http_codes' => array(),
				'needles'    => array(
					'json_decode',
					'invalid json',
					'failed to parse json',
					'syntax error in json',
					'schema validation failed',
				),
			),
		);
	}

	/**
	 * Determine category slug based on HTTP status code and message patterns.
	 *
	 * @param int    $http_code Extracted HTTP status code.
	 * @param string $haystack  Lowercase message and error strings.
	 * @return string Category slug.
	 */
	private static function determine_category($http_code, $haystack) {
		foreach (self::category_match_rules() as $rule) {
			if (in_array((int) $http_code, $rule['http_codes'], true)) {
				return $rule['category'];
			}
			foreach ($rule['needles'] as $needle) {
				if (strpos($haystack, $needle) !== false) {
					return $rule['category'];
				}
			}
		}

		return self::CATEGORY_GENERAL;
	}

	/**
	 * Get category metadata by category slug.
	 *
	 * @param string $category Category slug.
	 * @return array
	 */
	public static function get_category_meta($category) {
		$categories = self::get_categories();
		return isset($categories[$category]) ? $categories[$category] : $categories[self::CATEGORY_GENERAL];
	}

	/**
	 * Return prescriptive root-cause explanation and recommended resolution steps.
	 *
	 * @param string $category   Category slug.
	 * @param int    $http_code  Extracted HTTP code.
	 * @param string $raw_msg    Raw error message.
	 * @return array{root_cause: string, action: string, action_link: string, link_label: string}
	 */
	private static function get_resolution_guidance($category, $http_code, $raw_msg) {
		$settings_url    = admin_url('admin.php?page=aips-settings');
		$diagnostics_url = admin_url('admin.php?page=aips-diagnostics&tab=system-health');

		switch ($category) {
			case self::CATEGORY_RATE_LIMIT:
				return array(
					'root_cause'  => __('The AI provider rejected the request due to rate limit throttling or credit exhaustion (HTTP 429).', 'ai-post-scheduler'),
					'action'      => __('Verify available API credits with your provider billing dashboard, or reduce concurrent batch scheduling in WP AI Scheduler settings.', 'ai-post-scheduler'),
					'action_link' => $settings_url,
					'link_label'  => __('Open Settings ↗', 'ai-post-scheduler'),
				);

			case self::CATEGORY_AUTH:
				return array(
					'root_cause'  => __('The AI provider rejected the API key or reported insufficient organization permissions (HTTP 401/403).', 'ai-post-scheduler'),
					'action'      => __('Check that your API key is correctly configured and has active access to the specified model.', 'ai-post-scheduler'),
					'action_link' => $settings_url,
					'link_label'  => __('Check API Key in Settings ↗', 'ai-post-scheduler'),
				);

			case self::CATEGORY_CONTEXT_LENGTH:
				return array(
					'root_cause'  => __('The combined prompt and expected output exceeded the model\'s maximum token context limit.', 'ai-post-scheduler'),
					'action'      => __('Trim lengthy instructions in your template, reduce target word count, or switch the template to a larger-context model (e.g. gpt-4o or claude-3-5-sonnet).', 'ai-post-scheduler'),
					'action_link' => admin_url('admin.php?page=aips-templates'),
					'link_label'  => __('Edit Templates ↗', 'ai-post-scheduler'),
				);

			case self::CATEGORY_TIMEOUT:
				return array(
					'root_cause'  => __('The HTTP connection timed out before the AI provider finished generating a response (HTTP 504 / cURL 28).', 'ai-post-scheduler'),
					'action'      => __('External API latency spike. If persistent, check outbound network firewalls on your host or increase PHP curl timeout filters.', 'ai-post-scheduler'),
					'action_link' => $diagnostics_url,
					'link_label'  => __('Check System Health ↗', 'ai-post-scheduler'),
				);

			case self::CATEGORY_PROVIDER_DOWN:
				return array(
					'root_cause'  => __('The upstream AI provider experienced an internal failure, bad gateway, or high service overload (HTTP 5xx).', 'ai-post-scheduler'),
					'action'      => __('This failure is on the provider side. Check the provider\'s official status page (e.g. status.openai.com) to verify service availability.', 'ai-post-scheduler'),
					'action_link' => 'https://status.openai.com',
					'link_label'  => __('Provider Status Page ↗', 'ai-post-scheduler'),
				);

			case self::CATEGORY_CONTENT_POLICY:
				return array(
					'root_cause'  => __('The prompt or topic was flagged by provider automated safety and moderation filters.', 'ai-post-scheduler'),
					'action'      => __('Inspect the topic name and template prompts. Ensure your content strategy conforms to the AI provider\'s usage policies.', 'ai-post-scheduler'),
					'action_link' => admin_url('admin.php?page=aips-topics'),
					'link_label'  => __('Inspect Topics ↗', 'ai-post-scheduler'),
				);

			case self::CATEGORY_JSON_PARSING:
				return array(
					'root_cause'  => __('The model returned unstructured or truncated output that could not be parsed as valid JSON.', 'ai-post-scheduler'),
					'action'      => __('Ensure the template prompt explicitly requires strict JSON output and provides a schema example, or enable JSON mode in provider options.', 'ai-post-scheduler'),
					'action_link' => admin_url('admin.php?page=aips-templates'),
					'link_label'  => __('Edit Template Prompt ↗', 'ai-post-scheduler'),
				);

			default:
				return array(
					'root_cause'  => __('Unclassified error during AI generation or dispatch.', 'ai-post-scheduler'),
					'action'      => __('Review the full diagnostic payload, raw error message, and associated History Container to identify the failure cause.', 'ai-post-scheduler'),
					'action_link' => '',
					'link_label'  => '',
				);
		}
	}

	/**
	 * Map a category slug to the same signals classify() uses, as SQL LIKE patterns.
	 *
	 * Patterns overlap across categories. Exclusive classification belongs to
	 * classify(); do not SUM these patterns independently.
	 *
	 * @param string $category Category slug.
	 * @return array<int, string> Array of SQL LIKE pattern substrings.
	 */
	public static function get_sql_patterns_for_category($category) {
		foreach (self::category_match_rules() as $rule) {
			if ($rule['category'] !== $category) {
				continue;
			}

			$patterns = array();
			foreach ($rule['http_codes'] as $code) {
				$patterns[] = '%' . $code . '%';
			}
			foreach ($rule['needles'] as $needle) {
				$patterns[] = '%' . $needle . '%';
			}
			return $patterns;
		}

		return array();
	}

	/**
	 * Redact sensitive secrets (e.g. API keys, passwords, auth tokens) from strings.
	 *
	 * JSON objects and arrays are walked recursively so sensitive field names
	 * are redacted at any depth, including provider keys that do not use an
	 * sk- or ant- prefix.
	 *
	 * @param string $text Text to redact.
	 * @return string Sanitized text.
	 */
	public static function redact_secrets($text) {
		if (!is_string($text) || '' === $text) {
			return '';
		}

		return self::redact_secrets_in_string($text, 0);
	}

	/**
	 * Redact a string, decoding JSON payloads so nested fields can be walked.
	 *
	 * @param string $text  Text to redact.
	 * @param int    $depth Current recursion depth.
	 * @return string
	 */
	private static function redact_secrets_in_string($text, $depth) {
		$trimmed = ltrim($text);
		$first   = isset($trimmed[0]) ? $trimmed[0] : '';
		if ($depth < 8 && ('{' === $first || '[' === $first)) {
			$decoded = json_decode($text, true);
			if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
				$encoded = wp_json_encode(self::redact_secrets_in_value($decoded, $depth + 1));
				return is_string($encoded) ? $encoded : self::redact_secret_patterns($text);
			}
		}

		return self::redact_secret_patterns($text);
	}

	/**
	 * Redact secrets inside a decoded JSON value.
	 *
	 * @param mixed $value Decoded JSON value.
	 * @param int   $depth Current recursion depth.
	 * @return mixed
	 */
	private static function redact_secrets_in_value($value, $depth) {
		if ($depth > 8) {
			return is_string($value) ? self::redact_secret_patterns($value) : $value;
		}

		if (!is_array($value)) {
			return is_string($value) ? self::redact_secrets_in_string($value, $depth) : $value;
		}

		$redacted = array();
		foreach ($value as $key => $child) {
			if (is_string($key) && self::is_sensitive_secret_field($key)) {
				$redacted[$key] = self::REDACTED_SECRET;
				continue;
			}
			$redacted[$key] = self::redact_secrets_in_value($child, $depth + 1);
		}

		return $redacted;
	}

	/**
	 * Whether a JSON field name holds a secret.
	 *
	 * @param string $name Field name.
	 * @return bool
	 */
	private static function is_sensitive_secret_field($name) {
		$normalized = strtolower((string) preg_replace('/[^a-z0-9]/', '', $name));
		if ('' === $normalized) {
			return false;
		}

		$exact = array(
			'apikey'         => true,
			'apisecret'      => true,
			'accesstoken'    => true,
			'refreshtoken'   => true,
			'idtoken'        => true,
			'password'       => true,
			'passwd'         => true,
			'pass'           => true,
			'secret'         => true,
			'clientsecret'   => true,
			'privatekey'     => true,
			'authorization'  => true,
			'auth'           => true,
			'credential'     => true,
			'credentials'    => true,
			'token'          => true,
			'accesskey'      => true,
			'secretkey'      => true,
			'bearertoken'    => true,
			'sessiontoken'   => true,
			'authtoken'      => true,
		);
		if (isset($exact[$normalized])) {
			return true;
		}

		$markers = array(
			'apikey',
			'apisecret',
			'accesstoken',
			'refreshtoken',
			'clientsecret',
			'privatekey',
			'secretkey',
			'bearertoken',
		);
		foreach ($markers as $marker) {
			if (false !== strpos($normalized, $marker)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Apply literal secret patterns to a non-JSON string.
	 *
	 * @param string $text Text to redact.
	 * @return string
	 */
	private static function redact_secret_patterns($text) {
		$replacements = array(
			'/sk-[a-zA-Z0-9_\-]{20,}/' => 'sk-***[REDACTED_API_KEY]***',
			'/ant-[a-zA-Z0-9_\-]{20,}/' => 'ant-***[REDACTED_API_KEY]***',
			'/(Bearer\s+)[a-zA-Z0-9_\.\-]{20,}/i' => '$1***[REDACTED_TOKEN]***',
			'/("password"\s*:\s*")[^"]+(")/i' => '$1***[REDACTED_PASSWORD]***$2',
			'/(\bpassword\b\s*=\s*)[^\s&]+/i' => '$1***[REDACTED_PASSWORD]***',
			'/AIza[0-9A-Za-z_\-]{20,}/' => self::REDACTED_SECRET,
			'/gsk_[a-zA-Z0-9]{20,}/' => self::REDACTED_SECRET,
			'/xai-[a-zA-Z0-9]{20,}/' => self::REDACTED_SECRET,
		);

		foreach ($replacements as $pattern => $replacement) {
			$updated = preg_replace($pattern, $replacement, $text);
			if (is_string($updated)) {
				$text = $updated;
			}
		}

		$field = 'api[_\-\s]?key|access[_\-\s]?token|refresh[_\-\s]?token|client[_\-\s]?secret|api[_\-\s]?secret|private[_\-\s]?key|id[_\-\s]?token';
		$updated = preg_replace('/("(?:' . $field . ')"\s*:\s*")[^"]*(")/i', '$1' . self::REDACTED_SECRET . '$2', $text);
		if (is_string($updated)) {
			$text = $updated;
		}
		$updated = preg_replace('/(\b(?:' . $field . ')\b\s*[=:]\s*)(?:"|\')?[^\s&"\']+/i', '$1' . self::REDACTED_SECRET, $text);
		if (is_string($updated)) {
			$text = $updated;
		}

		return $text;
	}

	/**
	 * Generate a clean, sanitized Markdown support report for copying into issues/forums.
	 *
	 * @param array $error_data Formatted error data from repository.
	 * @return string Sanitized Markdown report.
	 */
	public static function generate_support_report(array $error_data) {
		global $wp_version;

		$id              = isset($error_data['id']) ? (int) $error_data['id'] : 0;
		$category_label  = isset($error_data['classification']['label']) ? $error_data['classification']['label'] : __('General', 'ai-post-scheduler');
		$http_code       = isset($error_data['classification']['http_code']) && $error_data['classification']['http_code'] ? (int) $error_data['classification']['http_code'] : 'N/A';
		$component_label = isset($error_data['component_label']) ? $error_data['component_label'] : __('General', 'ai-post-scheduler');
		$date            = isset($error_data['formatted_date']) ? $error_data['formatted_date'] : gmdate('Y-m-d H:i:s');
		$history_id      = isset($error_data['history_id']) ? (int) $error_data['history_id'] : 0;
		$error_message   = isset($error_data['error_message']) ? self::redact_secrets($error_data['error_message']) : '';
		$root_cause      = isset($error_data['classification']['root_cause']) ? $error_data['classification']['root_cause'] : '';
		$suggested_fix   = isset($error_data['classification']['action']) ? $error_data['classification']['action'] : '';

		// Model and provider if available
		$model    = 'N/A';
		$provider = 'Meow Apps AI Engine';
		if (!empty($error_data['options']['model'])) {
			$model = $error_data['options']['model'];
		}
		if (!empty($error_data['options']['provider'])) {
			$provider = $error_data['options']['provider'];
		}

		$report  = "### WP AI Scheduler - Diagnostic Error Report\n\n";
		$report .= "- **Log ID**: #" . $id . "\n";
		$report .= "- **Timestamp**: " . $date . "\n";
		$report .= "- **Category**: " . $category_label . " (HTTP " . $http_code . ")\n";
		$report .= "- **Component**: " . $component_label . "\n";
		$report .= "- **Provider / Model**: " . $provider . " / " . $model . "\n";
		$report .= "- **History Container**: #" . $history_id . "\n";
		$report .= "- **WordPress**: " . ($wp_version ?: 'Unknown') . " | **PHP**: " . PHP_VERSION . " | **Plugin**: " . (defined('AIPS_VERSION') ? AIPS_VERSION : '3.7.20') . "\n\n";

		$report .= "#### Root Cause Diagnosis\n";
		$report .= "> " . $root_cause . "\n\n";

		$report .= "#### Recommended Action\n";
		$report .= "> " . $suggested_fix . "\n\n";

		$report .= "#### Error Message\n";
		$report .= "```text\n" . trim($error_message) . "\n```\n";

		return $report;
	}
}
