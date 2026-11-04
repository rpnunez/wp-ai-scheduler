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
	 * Determine category slug based on HTTP status code and message patterns.
	 *
	 * @param int    $http_code Extracted HTTP status code.
	 * @param string $haystack  Lowercase message and error strings.
	 * @return string Category slug.
	 */
	private static function determine_category($http_code, $haystack) {
		// 1. Rate Limit & Quota (429)
		if (429 === $http_code
			|| strpos($haystack, 'rate_limit') !== false
			|| strpos($haystack, 'rate limit') !== false
			|| strpos($haystack, 'too many requests') !== false
			|| strpos($haystack, 'insufficient_quota') !== false
			|| strpos($haystack, 'quota exceeded') !== false
			|| strpos($haystack, 'exceeded your current quota') !== false
		) {
			return self::CATEGORY_RATE_LIMIT;
		}

		// 2. Authentication & Permissions (401, 403)
		if (401 === $http_code || 403 === $http_code
			|| strpos($haystack, 'invalid_api_key') !== false
			|| strpos($haystack, 'invalid api key') !== false
			|| strpos($haystack, 'incorrect api key') !== false
			|| strpos($haystack, 'authentication_error') !== false
			|| strpos($haystack, 'permission_denied') !== false
			|| strpos($haystack, 'unauthorized') !== false
			|| strpos($haystack, 'forbidden') !== false
		) {
			return self::CATEGORY_AUTH;
		}

		// 3. Context Length / Token Limit Exceeded
		if (strpos($haystack, 'context_length_exceeded') !== false
			|| strpos($haystack, 'context length') !== false
			|| strpos($haystack, 'maximum context') !== false
			|| strpos($haystack, 'tokens exceeded') !== false
			|| strpos($haystack, 'prompt is too long') !== false
			|| strpos($haystack, 'reduce the length of the messages') !== false
			|| strpos($haystack, 'context_window_exceeded') !== false
			|| strpos($haystack, 'token limit') !== false
		) {
			return self::CATEGORY_CONTEXT_LENGTH;
		}

		// 4. Connection / Gateway Timeout (408, 504, curl 28)
		if (408 === $http_code || 504 === $http_code
			|| strpos($haystack, 'curl error 28') !== false
			|| strpos($haystack, 'operation timed out') !== false
			|| strpos($haystack, 'timed out') !== false
			|| strpos($haystack, 'gateway timeout') !== false
			|| strpos($haystack, 'request timeout') !== false
		) {
			return self::CATEGORY_TIMEOUT;
		}

		// 5. Provider Outage / Server Failure (500, 502, 503)
		if (500 === $http_code || 502 === $http_code || 503 === $http_code
			|| strpos($haystack, 'internal_server_error') !== false
			|| strpos($haystack, 'internal server error') !== false
			|| strpos($haystack, 'bad gateway') !== false
			|| strpos($haystack, 'service_unavailable') !== false
			|| strpos($haystack, 'service unavailable') !== false
			|| strpos($haystack, 'overloaded') !== false
			|| strpos($haystack, 'server error') !== false
		) {
			return self::CATEGORY_PROVIDER_DOWN;
		}

		// 6. Content Policy Violation / Safety Filter
		if (strpos($haystack, 'content_policy') !== false
			|| strpos($haystack, 'safety_policy') !== false
			|| strpos($haystack, 'blocked by safety filters') !== false
			|| strpos($haystack, 'harmful content') !== false
			|| strpos($haystack, 'moderation') !== false
			|| strpos($haystack, 'flagged by moderation') !== false
		) {
			return self::CATEGORY_CONTENT_POLICY;
		}

		// 7. JSON / Parsing Errors
		if (strpos($haystack, 'json_decode') !== false
			|| strpos($haystack, 'invalid json') !== false
			|| strpos($haystack, 'failed to parse json') !== false
			|| strpos($haystack, 'syntax error in json') !== false
			|| strpos($haystack, 'schema validation failed') !== false
		) {
			return self::CATEGORY_JSON_PARSING;
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
	 * Map a category slug to SQL LIKE patterns for repository queries.
	 *
	 * @param string $category Category slug.
	 * @return array<int, string> Array of SQL LIKE pattern substrings.
	 */
	public static function get_sql_patterns_for_category($category) {
		switch ($category) {
			case self::CATEGORY_RATE_LIMIT:
				return array('%429%', '%rate_limit%', '%rate limit%', '%too many requests%', '%insufficient_quota%', '%quota exceeded%');
			case self::CATEGORY_AUTH:
				return array('%401%', '%403%', '%invalid_api_key%', '%invalid api key%', '%unauthorized%', '%permission_denied%', '%authentication_error%');
			case self::CATEGORY_CONTEXT_LENGTH:
				return array('%context_length%', '%context length%', '%maximum context%', '%tokens exceeded%', '%prompt is too long%');
			case self::CATEGORY_TIMEOUT:
				return array('%504%', '%408%', '%curl error 28%', '%operation timed out%', '%timed out%', '%gateway timeout%');
			case self::CATEGORY_PROVIDER_DOWN:
				return array('%500%', '%502%', '%503%', '%internal_server_error%', '%bad gateway%', '%service unavailable%', '%service_unavailable%', '%overloaded%');
			case self::CATEGORY_CONTENT_POLICY:
				return array('%content_policy%', '%safety_policy%', '%blocked by safety filters%', '%moderation%');
			case self::CATEGORY_JSON_PARSING:
				return array('%json_decode%', '%invalid json%', '%failed to parse json%');
			default:
				return array();
		}
	}

	/**
	 * Redact sensitive secrets (e.g. API keys, passwords, auth tokens) from strings.
	 *
	 * @param string $text Text to redact.
	 * @return string Sanitized text.
	 */
	public static function redact_secrets($text) {
		if (!is_string($text) || empty($text)) {
			return '';
		}

		// Redact OpenAI / standard bearer keys: sk-...
		$text = preg_replace('/sk-[a-zA-Z0-9_\-]{20,}/', 'sk-***[REDACTED_API_KEY]***', $text);

		// Redact Anthropic keys: ant-...
		$text = preg_replace('/ant-[a-zA-Z0-9_\-]{20,}/', 'ant-***[REDACTED_API_KEY]***', $text);

		// Redact generic Authorization / Bearer tokens
		$text = preg_replace('/(Bearer\s+)[a-zA-Z0-9_\.\-]{20,}/i', '$1***[REDACTED_TOKEN]***', $text);

		// Redact database passwords or password parameters
		$text = preg_replace('/("password"\s*:\s*")[^"]+(")/i', '$1***[REDACTED_PASSWORD]***$2', $text);
		$text = preg_replace('/(\bpassword\b\s*=\s*)[^\s&]+/i', '$1***[REDACTED_PASSWORD]***', $text);

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
