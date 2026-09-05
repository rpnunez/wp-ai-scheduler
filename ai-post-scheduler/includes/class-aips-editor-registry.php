<?php
/**
 * Editor Registry
 *
 * Manages registered page/post builder adapters and resolves active editor
 * instances per post.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Editor_Registry
 */
class AIPS_Editor_Registry {

	/**
	 * Registered adapters.
	 *
	 * @var array<string, AIPS_Editor_Adapter_Interface>
	 */
	protected $adapters = array();

	/**
	 * Default fallback adapter ID.
	 *
	 * @var string
	 */
	protected $default_adapter_id = 'gutenberg';

	/**
	 * Initialize registry and register built-in adapters.
	 */
	public function __construct() {
		// Register built-in adapters
		$this->register(new AIPS_Gutenberg_Editor_Adapter());
		$this->register(new AIPS_Elementor_Editor_Adapter());

		/**
		 * Hook to allow third-party add-ons or custom page builder extensions.
		 *
		 * @param AIPS_Editor_Registry $this Registry instance.
		 */
		do_action('aips_register_editor_adapters', $this);
	}

	/**
	 * Register an editor adapter.
	 *
	 * @param AIPS_Editor_Adapter_Interface $adapter Adapter instance.
	 * @return void
	 */
	public function register(AIPS_Editor_Adapter_Interface $adapter) {
		$this->adapters[$adapter->get_id()] = $adapter;
	}

	/**
	 * Retrieve adapter by ID.
	 *
	 * @param string $id Adapter ID.
	 * @return AIPS_Editor_Adapter_Interface|null
	 */
	public function get($id) {
		return $this->adapters[$id] ?? null;
	}

	/**
	 * Retrieve all registered adapters.
	 *
	 * @return array<string, AIPS_Editor_Adapter_Interface>
	 */
	public function get_all() {
		return $this->adapters;
	}

	/**
	 * Resolve the active editor adapter for a specific post.
	 *
	 * Iterates over registered adapters in priority order; returns the first
	 * non-default adapter that reports active, or falls back to Gutenberg.
	 *
	 * @param int $post_id Post ID.
	 * @return AIPS_Editor_Adapter_Interface
	 */
	public function get_active_adapter_for_post($post_id) {
		$post_id = absint($post_id);

		// Check non-default builders first (e.g. Elementor, Divi)
		foreach ($this->adapters as $id => $adapter) {
			if ($id === $this->default_adapter_id) {
				continue;
			}
			if ($adapter->is_active_for_post($post_id)) {
				return $adapter;
			}
		}

		// Fallback to default adapter (Gutenberg)
		return $this->get($this->default_adapter_id) ?: new AIPS_Gutenberg_Editor_Adapter();
	}
}
