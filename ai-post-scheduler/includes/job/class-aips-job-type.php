<?php
/**
 * AIPS Job Type — specification value object for a registered job definition.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Job_Type {

	/** @var string */
	private $slug;

	/** @var string */
	private $label;

	/** @var string */
	private $hook;

	/** @var string */
	private $group;

	/** @var int */
	private $max_retries;

	/** @var int */
	private $retry_backoff_sec;

	/** @var int */
	private $batch_size;

	/** @var string|callable */
	private $handler;

	/**
	 * @param string $slug Job type identifier.
	 * @param array  $definition Definition array from registry.
	 */
	public function __construct( string $slug, array $definition = array() ) {
		$this->slug              = $slug;
		$this->label             = isset( $definition['label'] ) ? (string) $definition['label'] : $slug;
		$this->hook              = isset( $definition['hook'] ) ? (string) $definition['hook'] : 'aips_job_tick';
		$this->group             = isset( $definition['group'] ) ? (string) $definition['group'] : 'aips-default';
		$this->max_retries       = isset( $definition['max_retries'] ) ? (int) $definition['max_retries'] : 3;
		$this->retry_backoff_sec = isset( $definition['retry_backoff_sec'] ) ? (int) $definition['retry_backoff_sec'] : 60;
		$this->batch_size        = isset( $definition['batch_size'] ) ? (int) $definition['batch_size'] : 10;
		$this->handler           = isset( $definition['handler'] ) ? $definition['handler'] : '';
	}

	public function get_slug(): string {
		return $this->slug;
	}

	public function get_label(): string {
		return $this->label;
	}

	public function get_hook(): string {
		return $this->hook;
	}

	public function get_group(): string {
		return $this->group;
	}

	public function get_max_retries(): int {
		return $this->max_retries;
	}

	public function get_retry_backoff_sec(): int {
		return $this->retry_backoff_sec;
	}

	public function get_batch_size(): int {
		return $this->batch_size;
	}

	/**
	 * @return string|callable Handler class name or callable.
	 */
	public function get_handler() {
		return $this->handler;
	}

	public function to_array(): array {
		return array(
			'slug'              => $this->slug,
			'label'             => $this->label,
			'hook'              => $this->hook,
			'group'             => $this->group,
			'max_retries'       => $this->max_retries,
			'retry_backoff_sec' => $this->retry_backoff_sec,
			'batch_size'        => $this->batch_size,
			'handler'           => is_string( $this->handler ) ? $this->handler : 'callable',
		);
	}
}
