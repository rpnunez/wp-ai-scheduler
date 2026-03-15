<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Class AIPS_Container
 *
 * A lightweight service container (registry) for the AI Post Scheduler plugin.
 *
 * Stores and retrieves shared instances of controllers, handlers, and services
 * so that each class is instantiated only once per request. Admin-menu render
 * callbacks retrieve their handler from the container rather than creating a
 * second instance (which would re-register hooks).
 *
 * Usage:
 *   // Register once (e.g. in AI_Post_Scheduler::init()):
 *   AIPS_Container::get_instance()->register( 'schedule_controller', $instance );
 *
 *   // Retrieve anywhere:
 *   $controller = AIPS_Container::get_instance()->get( 'schedule_controller' );
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Container {

	/**
	 * Singleton instance.
	 *
	 * @var AIPS_Container|null
	 */
	private static $instance = null;

	/**
	 * Registered service instances keyed by identifier.
	 *
	 * @var array<string, object>
	 */
	private $bindings = array();

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	/**
	 * Return the singleton container instance.
	 *
	 * @return AIPS_Container
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register a service instance under a given identifier.
	 *
	 * Overwrites any previously registered instance for the same $id.
	 *
	 * @param string $id       Unique identifier for the service.
	 * @param object $instance The service instance to store.
	 * @return void
	 * @throws InvalidArgumentException If $instance is not an object.
	 */
	public function register( $id, $instance ) {
		if ( ! is_object( $instance ) ) {
			throw new InvalidArgumentException(
				sprintf( 'AIPS_Container::register() expects an object for "%s", %s given.', $id, gettype( $instance ) )
			);
		}
		$this->bindings[ $id ] = $instance;
	}

	/**
	 * Retrieve a registered service instance.
	 *
	 * @param string $id Identifier used when the service was registered.
	 * @return object The registered instance.
	 * @throws RuntimeException If no service is registered for $id.
	 */
	public function get( $id ) {
		if ( ! isset( $this->bindings[ $id ] ) ) {
			throw new RuntimeException(
				sprintf( 'AIPS_Container: no service registered for "%s".', $id )
			);
		}
		return $this->bindings[ $id ];
	}

	/**
	 * Check whether a service is registered.
	 *
	 * @param string $id Identifier to check.
	 * @return bool True if registered, false otherwise.
	 */
	public function has( $id ) {
		return isset( $this->bindings[ $id ] );
	}

	/**
	 * Reset the container (primarily for unit tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$instance = null;
	}
}
