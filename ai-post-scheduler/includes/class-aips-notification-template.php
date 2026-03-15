<?php
/**
 * Notification Template Value Object
 *
 * Represents a single named notification template used by AIPS_Notifications
 * when sending email or DB notifications. Subject and body strings may contain
 * `{{token}}` placeholders that are replaced at render time via str_replace.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Notification_Template
 *
 * Immutable value object that holds the content for a single notification type.
 * Tokens inside the subject and body strings follow the `{{token_name}}` convention
 * and are substituted by calling render_subject() / render_body() with a `$vars` map.
 *
 * Example registration:
 *
 *   $templates->register( new AIPS_Notification_Template(
 *       'my_event',
 *       '[{{site_name}}] My Event Occurred',
 *       '<p>Hello, {{user_name}}!</p>',
 *       'My Event'
 *   ) );
 *
 * Example rendering:
 *
 *   $tpl  = $templates->get('my_event');
 *   $body = $tpl->render_body( [ '{{site_name}}' => 'My Blog', '{{user_name}}' => 'Jane' ] );
 */
class AIPS_Notification_Template {

	/**
	 * Unique slug that identifies this template.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Email subject line pattern.  May contain `{{token}}` placeholders.
	 *
	 * @var string
	 */
	private $subject;

	/**
	 * HTML email body pattern.  May contain `{{token}}` placeholders.
	 *
	 * @var string
	 */
	private $body;

	/**
	 * Human-readable header title shown inside the email banner.
	 *
	 * @var string
	 */
	private $header_title;

	/**
	 * CSS background colour for the email header banner (e.g. '#2271b1').
	 *
	 * @var string
	 */
	private $header_color;

	/**
	 * Constructor.
	 *
	 * @param string $type         Unique slug for this notification type.
	 * @param string $subject      Subject line pattern (may include {{tokens}}).
	 * @param string $body         Full HTML body pattern (may include {{tokens}}).
	 * @param string $header_title Optional text for the email header banner.
	 * @param string $header_color Optional CSS colour for the header banner. Default '#2271b1'.
	 */
	public function __construct($type, $subject, $body, $header_title = '', $header_color = '#2271b1') {
		$this->type         = (string) $type;
		$this->subject      = (string) $subject;
		$this->body         = (string) $body;
		$this->header_title = (string) $header_title;
		$this->header_color = (string) $header_color;
	}

	/**
	 * Return the notification type slug.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Return the header title.
	 *
	 * @return string
	 */
	public function get_header_title() {
		return $this->header_title;
	}

	/**
	 * Return the header colour.
	 *
	 * @return string
	 */
	public function get_header_color() {
		return $this->header_color;
	}

	/**
	 * Render the email subject with tokens replaced.
	 *
	 * @param array $vars Associative array of `'{{token}}' => 'value'` pairs.
	 * @return string
	 */
	public function render_subject(array $vars = array()) {
		return $this->replace_tokens($this->subject, $vars);
	}

	/**
	 * Render the email body with tokens replaced.
	 *
	 * @param array $vars Associative array of `'{{token}}' => 'value'` pairs.
	 * @return string
	 */
	public function render_body(array $vars = array()) {
		return $this->replace_tokens($this->body, $vars);
	}

	/**
	 * Replace `{{token}}` placeholders in a string.
	 *
	 * Keys in `$vars` that do not already start with `{{` are wrapped
	 * automatically so callers can pass either `'{{name}}'` or just `'name'`.
	 *
	 * @param string $content Source string.
	 * @param array  $vars    Replacement map.
	 * @return string
	 */
	private function replace_tokens($content, array $vars) {
		$search  = array();
		$replace = array();

		foreach ($vars as $token => $value) {
			// Normalise the token so both '{{name}}' and 'name' are accepted.
			if (strpos($token, '{{') !== 0) {
				$token = '{{' . $token . '}}';
			}
			$search[]  = $token;
			$replace[] = (string) $value;
		}

		if (empty($search)) {
			return $content;
		}

		return str_replace($search, $replace, $content);
	}
}
