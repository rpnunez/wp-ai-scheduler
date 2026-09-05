<?php
/**
 * AJAX Controller Base
 *
 * Abstract base class for all plugin AJAX controllers.
 * Provides declarative action registration and standardized request guarding.
 *
 * @package AI_Post_Scheduler
 * @since   2.9.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Abstract Class AIPS_Ajax_Controller_Base
 */
abstract class AIPS_Ajax_Controller_Base {
	use AIPS_Ajax_Guard;

	/**
	 * Map of AJAX action names to handler method names on this controller.
	 *
	 * Format: array('aips_action_name' => 'ajax_handler_method')
	 * Or list: array('aips_action_name') where method name matches action name.
	 *
	 * @var array<string|int, string>
	 */
	protected array $actions = array();

	/**
	 * Constructor.
	 *
	 * Automatically registers all actions declared in $this->actions.
	 */
	public function __construct() {
		$this->register_actions();
	}

	/**
	 * Register declared AJAX actions with WordPress wp_ajax_* hooks.
	 *
	 * @return void
	 */
	protected function register_actions(): void {
		foreach ($this->actions as $action => $method) {
			$action_name = is_int($action) ? (string) $method : (string) $action;
			$method_name = (string) $method;

			if (is_callable(array($this, $method_name))) {
				add_action('wp_ajax_' . $action_name, array($this, $method_name));
			}
		}
	}

	/**
	 * Unregister declared AJAX actions from WordPress wp_ajax_* hooks.
	 *
	 * Useful for testing teardown or dynamic controller resets.
	 *
	 * @return void
	 */
	public function unregister_actions(): void {
		foreach ($this->actions as $action => $method) {
			$action_name = is_int($action) ? (string) $method : (string) $action;
			$method_name = (string) $method;

			remove_action('wp_ajax_' . $action_name, array($this, $method_name));
		}
	}

	/**
	 * Get the list of registered action names for this controller.
	 *
	 * @return array<string>
	 */
	public function get_actions(): array {
		$action_names = array();
		foreach ($this->actions as $action => $method) {
			$action_names[] = is_int($action) ? $method : $action;
		}
		return $action_names;
	}
}
