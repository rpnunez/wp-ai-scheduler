<?php
if (!defined('ABSPATH')) { exit; }

/** Immutable effective policy for generated-post feedback. */
final class AIPS_Post_Feedback_Policy {
	private $enabled;
	private $values;
	private $scope;

	public function __construct($enabled, array $values = array(), array $scope = array()) {
		$this->enabled = (bool) $enabled;
		$this->values  = $values;
		$this->scope   = $scope;
	}

	public static function disabled() { return new self(false); }
	public function is_enabled() { return $this->enabled; }
	public function get($key, $default = null) { return array_key_exists($key, $this->values) ? $this->values[$key] : $default; }
	public function get_scope() { return $this->scope; }
	public function to_array() { return array('enabled' => $this->enabled, 'weights' => $this->values, 'scope' => $this->scope); }
}
