<?php
/**
 * AIPS Setting Definition — strongly typed value object for plugin configuration settings.
 *
 * Encapsulates option metadata, control types, defaults, validation, and sanitization
 * rules for declarative settings registration.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Setting_Definition {

	/**
	 * Supported core data types.
	 */
	const TYPE_BOOL   = 'bool';
	const TYPE_INT    = 'int';
	const TYPE_FLOAT  = 'float';
	const TYPE_STRING = 'string';
	const TYPE_ENUM   = 'enum';
	const TYPE_ARRAY  = 'array';
	const TYPE_JSON   = 'json';

	/**
	 * Supported control types.
	 */
	const CONTROL_TOGGLE      = 'toggle';
	const CONTROL_TEXT        = 'text';
	const CONTROL_NUMBER      = 'number';
	const CONTROL_SELECT      = 'select';
	const CONTROL_MULTISELECT = 'multiselect';
	const CONTROL_TEXTAREA    = 'textarea';
	const CONTROL_SECRET      = 'secret';
	const CONTROL_SLIDER      = 'slider';
	const CONTROL_ACTION      = 'action';

	/**
	 * Option key stored in WordPress options table.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Data type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Default value.
	 *
	 * @var mixed
	 */
	private $default;

	/**
	 * Settings tab identifier (e.g. 'settings-general').
	 *
	 * @var string
	 */
	private $tab;

	/**
	 * Settings section identifier (e.g. 'aips_general_section').
	 *
	 * @var string
	 */
	private $section;

	/**
	 * Localized human-readable label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Localized help text / description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * UI control type.
	 *
	 * @var string
	 */
	private $control_type;

	/**
	 * Control options (choices, min/max limits, step, unit, placeholder).
	 *
	 * @var array
	 */
	private $control_options;

	/**
	 * Sanitization callable or standard sanitizer name.
	 *
	 * @var callable|string|null
	 */
	private $sanitize_callback;

	/**
	 * Optional validation callable.
	 *
	 * @var callable|null
	 */
	private $validate_callback;

	/**
	 * Visibility dependencies.
	 *
	 * @var array
	 */
	private $dependencies;

	/**
	 * Constructor.
	 *
	 * @param array $args Setting definition arguments.
	 */
	public function __construct( array $args ) {
		$this->key               = isset( $args['key'] ) ? sanitize_key( $args['key'] ) : '';
		$this->type              = isset( $args['type'] ) ? (string) $args['type'] : self::TYPE_STRING;
		$this->default           = array_key_exists( 'default', $args ) ? $args['default'] : null;
		$this->tab               = isset( $args['tab'] ) ? sanitize_key( $args['tab'] ) : '';
		$this->section           = isset( $args['section'] ) ? sanitize_key( $args['section'] ) : '';
		$this->label             = isset( $args['label'] ) ? (string) $args['label'] : '';
		$this->description       = isset( $args['description'] ) ? (string) $args['description'] : '';
		$this->control_type      = isset( $args['control_type'] ) ? (string) $args['control_type'] : self::CONTROL_TEXT;
		$this->control_options   = isset( $args['control_options'] ) && is_array( $args['control_options'] ) ? $args['control_options'] : array();
		$this->sanitize_callback = isset( $args['sanitize_callback'] ) ? $args['sanitize_callback'] : null;
		$this->validate_callback = isset( $args['validate_callback'] ) && is_callable( $args['validate_callback'] ) ? $args['validate_callback'] : null;
		$this->dependencies      = isset( $args['dependencies'] ) && is_array( $args['dependencies'] ) ? $args['dependencies'] : array();
	}

	/**
	 * Create an instance from an arguments array.
	 *
	 * @param array $args Arguments array.
	 * @return self
	 */
	public static function from_array( array $args ) {
		return new self( $args );
	}

	/**
	 * Get option key.
	 *
	 * @return string
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * Get data type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Get default value.
	 *
	 * @return mixed
	 */
	public function get_default() {
		return $this->default;
	}

	/**
	 * Get tab identifier.
	 *
	 * @return string
	 */
	public function get_tab() {
		return $this->tab;
	}

	/**
	 * Get section identifier.
	 *
	 * @return string
	 */
	public function get_section() {
		return $this->section;
	}

	/**
	 * Get localized label.
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * Get localized description.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get control type.
	 *
	 * @return string
	 */
	public function get_control_type() {
		return $this->control_type;
	}

	/**
	 * Get control options.
	 *
	 * @return array
	 */
	public function get_control_options() {
		return $this->control_options;
	}

	/**
	 * Get a specific control option.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default value if not set.
	 * @return mixed
	 */
	public function get_control_option( $name, $default = null ) {
		return array_key_exists( $name, $this->control_options ) ? $this->control_options[ $name ] : $default;
	}

	/**
	 * Get sanitization callback.
	 *
	 * @return callable|string|null
	 */
	public function get_sanitize_callback() {
		return $this->sanitize_callback;
	}

	/**
	 * Get validation callback.
	 *
	 * @return callable|null
	 */
	public function get_validate_callback() {
		return $this->validate_callback;
	}

	/**
	 * Get visibility dependencies.
	 *
	 * @return array
	 */
	public function get_dependencies() {
		return $this->dependencies;
	}

	/**
	 * Sanitize a given value according to the definition's callback or default type handler.
	 *
	 * @param mixed $value Raw input value.
	 * @return mixed Sanitized value.
	 */
	public function sanitize( $value ) {
		if ( ! empty( $this->sanitize_callback ) ) {
			if ( is_callable( $this->sanitize_callback ) ) {
				return call_user_func( $this->sanitize_callback, $value );
			}

			if ( is_string( $this->sanitize_callback ) && function_exists( $this->sanitize_callback ) ) {
				return call_user_func( $this->sanitize_callback, $value );
			}
		}

		// Fallback to type-driven sanitization.
		return $this->coerce_type( $value );
	}

	/**
	 * Validate a given value.
	 *
	 * @param mixed $value Value to validate.
	 * @return bool|WP_Error True if valid, false or WP_Error otherwise.
	 */
	public function validate( $value ) {
		if ( null !== $this->validate_callback ) {
			return call_user_func( $this->validate_callback, $value );
		}

		return true;
	}

	/**
	 * Coerce a value into the definition's declared type.
	 *
	 * @param mixed $value Raw input value.
	 * @return mixed Coerced value.
	 */
	public function coerce_type( $value ) {
		switch ( $this->type ) {
			case self::TYPE_BOOL:
				if ( is_bool( $value ) ) {
					return $value;
				}
				if ( is_string( $value ) ) {
					return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				}
				return (bool) $value;

			case self::TYPE_INT:
				return (int) $value;

			case self::TYPE_FLOAT:
				return (float) $value;

			case self::TYPE_ARRAY:
				if ( is_array( $value ) ) {
					return $value;
				}
				if ( empty( $value ) ) {
					return array();
				}
				if ( is_string( $value ) ) {
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) {
						return $decoded;
					}
					return array_map( 'trim', explode( ',', $value ) );
				}
				return (array) $value;

			case self::TYPE_JSON:
				if ( is_string( $value ) ) {
					return $value;
				}
				return wp_json_encode( $value );

			case self::TYPE_ENUM:
			case self::TYPE_STRING:
			default:
				if ( is_scalar( $value ) ) {
					return sanitize_text_field( (string) $value );
				}
				return '';
		}
	}
}
