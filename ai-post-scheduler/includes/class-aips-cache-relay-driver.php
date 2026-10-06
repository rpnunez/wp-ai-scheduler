<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Relay_Driver
 *
 * Specialized cache driver leveraging Relay (\Relay\Relay), an in-memory
 * cache-aside C extension that provides in-process memory caching for Redis,
 * eliminating network round-trips for hot keys across PHP requests.
 *
 * @package AI_Post_Scheduler
 * @since   3.6.7
 */
class AIPS_Cache_Relay_Driver extends AIPS_Cache_Redis_Driver {

	/**
	 * Create and initialize the Relay client instance.
	 *
	 * @return \Relay\Relay
	 */
	protected function create_client_instance() {
		return new \Relay\Relay();
	}

	/**
	 * Establish connection using the Relay client.
	 *
	 * @return bool
	 */
	public function connect(): bool {
		if ( ! class_exists( 'Relay\Relay' ) ) {
			$this->connected = false;
			return false;
		}

		return parent::connect();
	}

	/**
	 * Test server connection and measure round-trip latency.
	 *
	 * @return array{success: bool, latency_ms: float, message: string}
	 */
	public function test_connection(): array {
		if ( ! class_exists( 'Relay\Relay' ) ) {
			return array(
				'success'    => false,
				'latency_ms' => 0.0,
				'message'    => __( 'The Relay PHP extension (\Relay\Relay) is not installed on this server.', 'ai-post-scheduler' ),
			);
		}

		$result = parent::test_connection();
		if ( $result['success'] ) {
			$result['message'] = sprintf(
				/* translators: 1: latency ms, 2: host, 3: port */
				__( 'Connected to Relay in-memory cache at %2$s:%3$d in %1$0.2f ms.', 'ai-post-scheduler' ),
				$result['latency_ms'],
				$this->host,
				$this->port
			);
		}

		return $result;
	}

	/**
	 * Get the driver identifier name.
	 *
	 * @return string
	 */
	public function get_driver_name(): string {
		return 'relay';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_driver_info(): array {
		return array(
			'driver'      => 'relay',
			'label'       => __( 'Relay (Redis In-Memory Client Cache)', 'ai-post-scheduler' ),
			'persistent'  => true,
			'host'        => $this->host,
			'port'        => $this->port,
			'database'    => $this->database,
			'connected'   => $this->connected,
			'limitations' => array(
				__( 'Zero-network local client caching synchronized with Redis.', 'ai-post-scheduler' ),
			),
		);
	}
}
