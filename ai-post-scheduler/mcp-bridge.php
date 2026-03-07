<?php
/**
 * MCP Bridge - Model Context Protocol Server for AI Post Scheduler
 * 
 * This file provides a JSON-RPC style API bridge that exposes plugin functionality
 * to MCP-compatible AI tools and Copilot.
 * 
 * Usage:
 * - Can be called via HTTP POST to expose tools
 * - Can be included in custom MCP server implementations
 * - Provides secure access to plugin internals with authentication
 * 
 * @package AI_Post_Scheduler
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	// If called directly (not via WordPress), bootstrap WordPress
	$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
	if (file_exists($wp_load_path)) {
		require_once $wp_load_path;
	} else {
		header('HTTP/1.1 500 Internal Server Error');
		echo json_encode(array(
			'error' => array(
				'code' => -32000,
				'message' => 'WordPress environment not available'
			)
		));
		exit;
	}
}

/**
 * MCP Bridge Server Class
 * 
 * Implements a JSON-RPC 2.0 style protocol for exposing plugin tools
 */
class AIPS_MCP_Bridge {
	
	/**
	 * @var string Version of the MCP bridge
	 */
	const VERSION = '1.0.0';
	
	/**
	 * @var array List of available tools
	 */
	private $tools = array();
	
	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = new AIPS_Logger();
		$this->register_tools();
	}
	
	/**
	 * Register all available MCP tools
	 */
	private function register_tools() {
		$this->tools = array(
			'clear_cache' => array(
				'description' => 'Clear all plugin caches (transients)',
				'parameters' => array(
					'cache_type' => array(
						'type' => 'string',
						'description' => 'Type of cache to clear: all, history_stats, schedule_stats, or specific transient name',
						'required' => false,
						'default' => 'all'
					)
				),
				'handler' => array($this, 'tool_clear_cache')
			),
			'check_database' => array(
				'description' => 'Check database health and verify all tables/columns exist',
				'parameters' => array(),
				'handler' => array($this, 'tool_check_database')
			),
			'repair_database' => array(
				'description' => 'Repair database tables using WordPress dbDelta',
				'parameters' => array(),
				'handler' => array($this, 'tool_repair_database')
			),
			'check_upgrades' => array(
				'description' => 'Check if database upgrades are needed and optionally run them',
				'parameters' => array(
					'run' => array(
						'type' => 'boolean',
						'description' => 'If true, run upgrades. If false, just check.',
						'required' => false,
						'default' => false
					)
				),
				'handler' => array($this, 'tool_check_upgrades')
			),
			'system_status' => array(
				'description' => 'Get comprehensive system status including environment, plugin, database, filesystem, cron, and logs',
				'parameters' => array(
					'section' => array(
						'type' => 'string',
						'description' => 'Specific section: all, environment, plugin, database, filesystem, cron, logs',
						'required' => false,
						'default' => 'all'
					)
				),
				'handler' => array($this, 'tool_system_status')
			),
			'clear_history' => array(
				'description' => 'Clear generation history records',
				'parameters' => array(
					'older_than_days' => array(
						'type' => 'integer',
						'description' => 'Clear records older than N days. If 0, clear all.',
						'required' => false,
						'default' => 0
					),
					'status' => array(
						'type' => 'string',
						'description' => 'Clear only records with specific status: all, completed, failed, pending',
						'required' => false,
						'default' => 'all'
					)
				),
				'handler' => array($this, 'tool_clear_history')
			),
			'export_data' => array(
				'description' => 'Export plugin data in JSON or MySQL format',
				'parameters' => array(
					'format' => array(
						'type' => 'string',
						'description' => 'Export format: json or mysql',
						'required' => true
					),
					'tables' => array(
						'type' => 'array',
						'description' => 'List of tables to export (empty for all)',
						'required' => false,
						'default' => array()
					)
				),
				'handler' => array($this, 'tool_export_data')
			),
			'get_cron_status' => array(
				'description' => 'Get status of all scheduled cron jobs',
				'parameters' => array(),
				'handler' => array($this, 'tool_get_cron_status')
			),
			'trigger_cron' => array(
				'description' => 'Manually trigger a specific cron job',
				'parameters' => array(
					'hook' => array(
						'type' => 'string',
						'description' => 'Cron hook name: aips_generate_scheduled_posts, aips_generate_author_topics, aips_generate_author_posts, aips_scheduled_research, aips_send_review_notifications, aips_cleanup_export_files',
						'required' => true
					)
				),
				'handler' => array($this, 'tool_trigger_cron')
			),
			'list_tools' => array(
				'description' => 'List all available MCP tools with their descriptions and parameters',
				'parameters' => array(),
				'handler' => array($this, 'tool_list_tools')
			),
				'get_plugin_info' => array(
				'description' => 'Get plugin version, settings, and configuration',
				'parameters' => array(),
				'handler' => array($this, 'tool_get_plugin_info')
			),
			'generate_post' => array(
				'description' => 'Generate a single post now with AI',
				'parameters' => array(
					'template_id' => array(
						'type' => 'integer',
						'description' => 'Template ID to use for generation',
						'required' => false
					),
					'author_topic_id' => array(
						'type' => 'integer',
						'description' => 'Author topic ID for topic-based generation',
						'required' => false
					),
					'schedule_id' => array(
						'type' => 'integer',
						'description' => 'Schedule ID to use schedule configuration',
						'required' => false
					),
					'overrides' => array(
						'type' => 'object',
						'description' => 'Optional overrides for post creation',
						'required' => false,
						'properties' => array(
							'title' => 'string',
							'category_ids' => 'array',
							'tag_ids' => 'array',
							'post_status' => 'string',
							'post_author' => 'integer'
						)
					)
				),
				'handler' => array($this, 'tool_generate_post')
			),
			'list_templates' => array(
				'description' => 'Get all available templates',
				'parameters' => array(
					'active_only' => array(
						'type' => 'boolean',
						'description' => 'Return only active templates',
						'required' => false,
						'default' => false
					),
					'search' => array(
						'type' => 'string',
						'description' => 'Search term to filter templates by name',
						'required' => false
					)
				),
				'handler' => array($this, 'tool_list_templates')
			),
			'get_generation_history' => array(
				'description' => 'Retrieve past post generations with filters',
				'parameters' => array(
					'per_page' => array(
						'type' => 'integer',
						'description' => 'Number of items per page (1-100)',
						'required' => false,
						'default' => 20
					),
					'page' => array(
						'type' => 'integer',
						'description' => 'Page number',
						'required' => false,
						'default' => 1
					),
					'status' => array(
						'type' => 'string',
						'description' => 'Filter by status: completed, failed, pending',
						'required' => false
					),
					'template_id' => array(
						'type' => 'integer',
						'description' => 'Filter by template ID',
						'required' => false
					),
					'search' => array(
						'type' => 'string',
						'description' => 'Search term for post title',
						'required' => false
					)
				),
				'handler' => array($this, 'tool_get_generation_history')
			),
			'get_history' => array(
				'description' => 'Get detailed history record by history ID or post ID',
				'parameters' => array(
					'history_id' => array(
						'type' => 'integer',
						'description' => 'History record ID',
						'required' => false
					),
					'post_id' => array(
						'type' => 'integer',
						'description' => 'WordPress post ID to find history for',
						'required' => false
					),
					'include_logs' => array(
						'type' => 'boolean',
						'description' => 'Include detailed log entries',
						'required' => false,
						'default' => true
					)
				),
				'handler' => array($this, 'tool_get_history')
			),
			'list_authors' => array(
				'description' => 'Get all authors with optional filtering',
				'parameters' => array(
					'active_only' => array(
						'type' => 'boolean',
						'description' => 'Return only active authors',
						'required' => false,
						'default' => false
					)
				),
				'handler' => array($this, 'tool_list_authors')
			),
			'get_author' => array(
				'description' => 'Get author details by ID',
				'parameters' => array(
					'author_id' => array(
						'type' => 'integer',
						'description' => 'Author ID',
						'required' => true
					)
				),
				'handler' => array($this, 'tool_get_author')
			),
			'list_author_topics' => array(
				'description' => 'Get topics for an author with filtering',
				'parameters' => array(
					'author_id' => array(
						'type' => 'integer',
						'description' => 'Author ID',
						'required' => true
					),
					'status' => array(
						'type' => 'string',
						'description' => 'Filter by status: pending, approved, rejected',
						'required' => false
					),
					'limit' => array(
						'type' => 'integer',
						'description' => 'Maximum number of topics to return',
						'required' => false,
						'default' => 50
					)
				),
				'handler' => array($this, 'tool_list_author_topics')
			),
			'get_author_topic' => array(
				'description' => 'Get specific topic details by ID',
				'parameters' => array(
					'topic_id' => array(
						'type' => 'integer',
						'description' => 'Topic ID',
						'required' => true
					)
				),
				'handler' => array($this, 'tool_get_author_topic')
			),
			'regenerate_post_component' => array(
				'description' => 'Regenerate individual post component (title, excerpt, content, or featured_image)',
				'parameters' => array(
					'post_id' => array(
						'type' => 'integer',
						'description' => 'WordPress post ID',
						'required' => true
					),
					'history_id' => array(
						'type' => 'integer',
						'description' => 'History record ID for context',
						'required' => true
					),
					'component' => array(
						'type' => 'string',
						'description' => 'Component to regenerate',
						'required' => true,
						'enum' => array('title', 'excerpt', 'content', 'featured_image')
					),
					'save' => array(
						'type' => 'boolean',
						'description' => 'Automatically save to post (false returns preview only)',
						'required' => false,
						'default' => false
					)
				),
				'handler' => array($this, 'tool_regenerate_post_component')
			),
			'get_generation_stats' => array(
				'description' => 'Get generation statistics (success rates, performance metrics)',
				'parameters' => array(
					'period' => array(
						'type' => 'string',
						'description' => 'Time period for stats: all, today, week, month',
						'required' => false,
						'default' => 'all'
					),
					'template_id' => array(
						'type' => 'integer',
						'description' => 'Filter by template ID',
						'required' => false
					)
				),
				'handler' => array($this, 'tool_get_generation_stats')
			),
			'get_post_metadata' => array(
				'description' => 'Get AI generation metadata for a specific post',
				'parameters' => array(
					'post_id' => array(
						'type' => 'integer',
						'description' => 'WordPress post ID',
						'required' => true
					)
				),
				'handler' => array($this, 'tool_get_post_metadata')
			),
			'get_ai_models' => array(
				'description' => 'List available AI models from AI Engine',
				'parameters' => array(),
				'handler' => array($this, 'tool_get_ai_models')
			),
			'test_ai_connection' => array(
				'description' => 'Test AI Engine connection with a simple query',
				'parameters' => array(
					'test_prompt' => array(
						'type' => 'string',
						'description' => 'Optional test prompt',
						'required' => false,
						'default' => 'Say "Hello" if you can read this.'
					)
				),
				'handler' => array($this, 'tool_test_ai_connection')
			),
			'get_plugin_settings' => array(
				'description' => 'Get plugin configuration settings',
				'parameters' => array(
					'category' => array(
						'type' => 'string',
						'description' => 'Settings category: ai, resilience, logging, all',
						'required' => false,
						'default' => 'all'
					)
				),
				'handler' => array($this, 'tool_get_plugin_settings')
			)
		);
	}
	
	/**
	 * Handle incoming JSON-RPC request
	 * 
	 * @return void
	 */
	public function handle_request() {
		// Verify user has admin capabilities
		if (!current_user_can('manage_options')) {
			$this->send_error(-32001, 'Insufficient permissions. Admin access required.');
			return;
		}
		
		// Get request body
		$input = file_get_contents('php://input');
		$request = json_decode($input, true);
		
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->send_error(-32700, 'Parse error: Invalid JSON');
			return;
		}
		
		// Validate JSON-RPC structure
		if (!isset($request['method'])) {
			$this->send_error(-32600, 'Invalid Request: missing method');
			return;
		}
		
		$method = $request['method'];
		$params = isset($request['params']) ? $request['params'] : array();
		$id = isset($request['id']) ? $request['id'] : null;
		
		// Execute tool
		$result = $this->execute_tool($method, $params);
		
		if (is_wp_error($result)) {
			$this->send_error(-32000, $result->get_error_message(), $id);
		} else {
			$this->send_success($result, $id);
		}
	}
	
	/**
	 * Execute a tool by name
	 * 
	 * @param string $tool_name Tool to execute
	 * @param array  $params Parameters for the tool
	 * @param bool   $bypass_cap_check Whether to bypass capability checks (for trusted contexts like WP-CLI/tests)
	 * @return mixed|WP_Error Result or error
	 */
	public function execute_tool($tool_name, $params = array(), $bypass_cap_check = false) {
		// Enforce capability check by default to prevent unauthorized tool execution
		if (!$bypass_cap_check && function_exists('current_user_can') && !current_user_can('manage_options')) {
			return new WP_Error('aips_mcp_insufficient_permissions', 'Insufficient permissions. Admin access required.');
		}
		
		if (!isset($this->tools[$tool_name])) {
			return new WP_Error('tool_not_found', 'Tool not found: ' . $tool_name);
		}
		
		$tool = $this->tools[$tool_name];
		
		// Validate and apply default parameters
		$validated_params = $this->validate_params($tool['parameters'], $params);
		if (is_wp_error($validated_params)) {
			return $validated_params;
		}
		
		// Call tool handler
		try {
			$this->logger->log("MCP Bridge: Executing tool '$tool_name' with params: " . json_encode($validated_params));
			$result = call_user_func($tool['handler'], $validated_params);
			$this->logger->log("MCP Bridge: Tool '$tool_name' completed successfully");
			return $result;
		} catch (Exception $e) {
			$this->logger->log("MCP Bridge: Tool '$tool_name' failed: " . $e->getMessage(), 'error');
			return new WP_Error('tool_execution_error', 'Tool execution failed: ' . $e->getMessage());
		}
	}
	
	/**
	 * Validate parameters against tool definition
	 * 
	 * @param array $definition Parameter definitions
	 * @param array $params User-provided parameters
	 * @return array|WP_Error Validated parameters or error
	 */
	private function validate_params($definition, $params) {
		$validated = array();
		
		foreach ($definition as $param_name => $param_def) {
			if (isset($params[$param_name])) {
				$validated[$param_name] = $params[$param_name];
			} elseif (isset($param_def['required']) && $param_def['required']) {
				return new WP_Error('missing_parameter', "Required parameter missing: $param_name");
			} elseif (isset($param_def['default'])) {
				$validated[$param_name] = $param_def['default'];
			}
		}
		
		return $validated;
	}
	
	/**
	 * Send JSON-RPC success response
	 * 
	 * @param mixed $result Result data
	 * @param mixed $id Request ID
	 */
	private function send_success($result, $id = null) {
		header('Content-Type: application/json');
		echo json_encode(array(
			'jsonrpc' => '2.0',
			'result' => $result,
			'id' => $id
		));
		exit;
	}
	
	/**
	 * Send JSON-RPC error response
	 * 
	 * @param int $code Error code
	 * @param string $message Error message
	 * @param mixed $id Request ID
	 */
	private function send_error($code, $message, $id = null) {
		header('Content-Type: application/json');
		http_response_code(400);
		echo json_encode(array(
			'jsonrpc' => '2.0',
			'error' => array(
				'code' => $code,
				'message' => $message
			),
			'id' => $id
		));
		exit;
	}
	
	// ===== Tool Handlers =====
	
	/**
	 * Tool: Clear cache
	 */
	private function tool_clear_cache($params) {
		$cache_type = $params['cache_type'];
		$cleared = array();
		
		if ($cache_type === 'all' || $cache_type === 'history_stats') {
			delete_transient('aips_history_stats');
			$cleared[] = 'aips_history_stats';
		}
		
		if ($cache_type === 'all' || $cache_type === 'schedule_stats') {
			delete_transient('aips_pending_schedule_stats');
			$cleared[] = 'aips_pending_schedule_stats';
		}
		
		if ($cache_type === 'all') {
			// Clear circuit breaker and rate limiter caches
			delete_transient('aips_circuit_breaker_state');
			delete_transient('aips_rate_limiter_requests');
			$cleared[] = 'aips_circuit_breaker_state';
			$cleared[] = 'aips_rate_limiter_requests';
			
			// Clear schedule-specific caches
			global $wpdb;
			$schedule_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}aips_schedule");
			foreach ($schedule_ids as $schedule_id) {
				delete_transient("aips_sched_cnt_{$schedule_id}");
				$cleared[] = "aips_sched_cnt_{$schedule_id}";
			}
		}
		
		// If specific transient name provided
		if (!in_array($cache_type, array('all', 'history_stats', 'schedule_stats'))) {
			delete_transient($cache_type);
			$cleared[] = $cache_type;
		}
		
		return array(
			'success' => true,
			'cleared' => $cleared,
			'count' => count($cleared)
		);
	}
	
	/**
	 * Tool: Check database
	 */
	private function tool_check_database($params) {
		$system_status = new AIPS_System_Status();
		$db_info = $system_status->get_system_info();
		
		return array(
			'success' => true,
			'database' => $db_info['database']
		);
	}
	
	/**
	 * Tool: Repair database
	 */
	private function tool_repair_database($params) {
		// Run dbDelta to repair/create tables
		AIPS_DB_Manager::install_tables();
		
		return array(
			'success' => true,
			'message' => 'Database tables repaired/installed successfully'
		);
	}
	
	/**
	 * Tool: Check upgrades
	 */
	private function tool_check_upgrades($params) {
		$current_version = get_option('aips_db_version', '0');
		$needs_upgrade = version_compare($current_version, AIPS_VERSION, '<');
		
		if ($params['run'] && $needs_upgrade) {
			AIPS_Upgrades::check_and_run();
			$current_version = get_option('aips_db_version');
		}
		
		return array(
			'success' => true,
			'current_version' => $current_version,
			'plugin_version' => AIPS_VERSION,
			'needs_upgrade' => version_compare($current_version, AIPS_VERSION, '<'),
			'upgraded' => $params['run'] && $needs_upgrade
		);
	}
	
	/**
	 * Tool: System status
	 */
	private function tool_system_status($params) {
		$system_status = new AIPS_System_Status();
		$info = $system_status->get_system_info();
		
		if ($params['section'] !== 'all') {
			if (isset($info[$params['section']])) {
				return array(
					'success' => true,
					'section' => $params['section'],
					'data' => $info[$params['section']]
				);
			} else {
				return new WP_Error('invalid_section', 'Invalid section: ' . $params['section']);
			}
		}
		
		return array(
			'success' => true,
			'system_info' => $info
		);
	}
	
	/**
	 * Tool: Clear history
	 */
	private function tool_clear_history($params) {
		$repository = new AIPS_History_Repository();
		
		$result = $repository->clear_history(array(
			'status' => $params['status'],
			'older_than_days' => $params['older_than_days'],
		));
		
		return $result;
	}
	
	/**
	 * Tool: Export data
	 */
	private function tool_export_data($params) {
		if ($params['format'] === 'json') {
			$exporter = new AIPS_Data_Management_Export_JSON();
		} elseif ($params['format'] === 'mysql') {
			$exporter = new AIPS_Data_Management_Export_MySQL();
		} else {
			return new WP_Error('invalid_format', 'Invalid export format. Use json or mysql.');
		}
		
		$tables = !empty($params['tables']) ? $params['tables'] : AIPS_DB_Manager::get_table_names();
		
		$result = $exporter->export($tables);
		
		if (is_wp_error($result)) {
			return $result;
		}
		
		return array(
			'success' => true,
			'format' => $params['format'],
			'file' => $result['file'],
			'url' => $result['url'],
			'size' => $result['size'],
			'tables' => $tables
		);
	}
	
	/**
	 * Tool: Get cron status
	 */
	private function tool_get_cron_status($params) {
		$crons = array(
			'aips_generate_scheduled_posts',
			'aips_generate_author_topics',
			'aips_generate_author_posts',
			'aips_scheduled_research',
			'aips_send_review_notifications',
			'aips_cleanup_export_files'
		);
		
		$status = array();
		foreach ($crons as $hook) {
			$next_run = wp_next_scheduled($hook);
			$status[$hook] = array(
				'scheduled' => $next_run !== false,
				'next_run' => $next_run ? date('Y-m-d H:i:s', $next_run) : null,
				'next_run_timestamp' => $next_run
			);
		}
		
		return array(
			'success' => true,
			'crons' => $status
		);
	}
	
	/**
	 * Tool: Trigger cron
	 */
	private function tool_trigger_cron($params) {
		$hook = $params['hook'];
		
		// Validate hook
		$valid_hooks = array(
			'aips_generate_scheduled_posts',
			'aips_generate_author_topics',
			'aips_generate_author_posts',
			'aips_scheduled_research',
			'aips_send_review_notifications',
			'aips_cleanup_export_files'
		);
		
		if (!in_array($hook, $valid_hooks)) {
			return new WP_Error('invalid_hook', 'Invalid cron hook: ' . $hook);
		}
		
		// Trigger the action
		do_action($hook);
		
		return array(
			'success' => true,
			'hook' => $hook,
			'message' => "Cron hook '$hook' triggered successfully"
		);
	}
	
	/**
	 * Tool: List tools
	 */
	private function tool_list_tools($params) {
		$tools_list = array();
		
		foreach ($this->tools as $name => $tool) {
			$tools_list[$name] = array(
				'name' => $name,
				'description' => $tool['description'],
				'parameters' => $tool['parameters']
			);
		}
		
		return array(
			'success' => true,
			'version' => self::VERSION,
			'tools' => $tools_list
		);
	}
	
	/**
	 * Tool: Get plugin info
	 */
	private function tool_get_plugin_info($params) {
		return array(
			'success' => true,
			'plugin' => array(
				'name' => 'AI Post Scheduler',
				'version' => AIPS_VERSION,
				'db_version' => get_option('aips_db_version'),
				'php_version' => phpversion(),
				'wp_version' => get_bloginfo('version'),
				'ai_engine_active' => class_exists('Meow_MWAI_Core'),
				'settings' => array(
					'default_post_status' => get_option('aips_default_post_status'),
					'default_category' => get_option('aips_default_category'),
					'enable_logging' => get_option('aips_enable_logging'),
					'retry_max_attempts' => get_option('aips_retry_max_attempts'),
					'ai_model' => get_option('aips_ai_model'),
					'developer_mode' => get_option('aips_developer_mode')
				)
			)
		);
	}
	
	/**
	 * Tool: Generate post
	 */
	private function tool_generate_post($params) {
		// Validate that at least one source is provided
		if (empty($params['template_id']) && empty($params['author_topic_id']) && empty($params['schedule_id'])) {
			return new WP_Error('missing_source', 'Must provide template_id, author_topic_id, or schedule_id');
		}
		
		$generator = new AIPS_Generator();
		
		// Check if AI is available
		if (!$generator->is_available()) {
			return new WP_Error('ai_unavailable', 'AI Engine is not available. Please check your configuration.');
		}
		
		// Determine generation context
		$context = null;
		$template = null;
		$topic = null;
		
		if (!empty($params['schedule_id'])) {
			// Use schedule configuration
			$schedule_repo = new AIPS_Schedule_Repository();
			$schedule = $schedule_repo->get_by_id($params['schedule_id']);
			
			if (!$schedule) {
				return new WP_Error('schedule_not_found', 'Schedule not found');
			}
			
			$template_repo = new AIPS_Template_Repository();
			$template = $template_repo->get_by_id($schedule->template_id);
			
			if (!$template) {
				return new WP_Error('template_not_found', 'Template associated with schedule not found');
			}
			
			$topic = $schedule->topic;
			
		} elseif (!empty($params['author_topic_id'])) {
			// Use author topic
			$topics_repo = new AIPS_Author_Topics_Repository();
			$author_topic = $topics_repo->get_by_id($params['author_topic_id']);
			
			if (!$author_topic) {
				return new WP_Error('topic_not_found', 'Author topic not found');
			}
			
			$authors_repo = new AIPS_Authors_Repository();
			$author = $authors_repo->get_by_id($author_topic->author_id);
			if (!$author) {
				return new WP_Error('author_not_found', 'Author for topic not found');
			}
			
			// Create topic context (loaded via Composer + compatibility layer)
			$context = new AIPS_Topic_Context($author, $author_topic);
			
		} elseif (!empty($params['template_id'])) {
			// Use template directly
			$template_repo = new AIPS_Template_Repository();
			$template = $template_repo->get_by_id($params['template_id']);
			
			if (!$template) {
				return new WP_Error('template_not_found', 'Template not found');
			}
		}
		
		// Apply overrides if provided
		if (!empty($params['overrides'])) {
			$overrides = $params['overrides'];
			
			// If we have a template, apply overrides to it
			if ($template) {
				if (isset($overrides['post_status'])) {
					$template->post_status = $overrides['post_status'];
				}
				if (isset($overrides['post_author'])) {
					$template->post_author = $overrides['post_author'];
				}
				if (isset($overrides['category_ids']) && is_array($overrides['category_ids']) && !empty($overrides['category_ids'])) {
					$template->post_category = $overrides['category_ids'][0]; // WordPress typically uses single category
				}
				if (isset($overrides['tag_ids']) && is_array($overrides['tag_ids'])) {
					$template->post_tags = implode(',', $overrides['tag_ids']);
				}
			}
			
			// TODO: Apply overrides to context if needed
		}
		
		// Generate the post
		if ($context) {
			$post_id = $generator->generate_post($context);
		} else {
			// Get voice if template has one
			$voice = null;
			if (!empty($template->voice_id)) {
				$voices_repo = new AIPS_Voices_Repository();
				$voice = $voices_repo->get_by_id($template->voice_id);
			}
			
			$post_id = $generator->generate_post($template, $voice, $topic);
		}
		
		if (is_wp_error($post_id)) {
			return $post_id;
		}
		
		// Get the post details
		$post = get_post($post_id);
		
		// Get history ID for this generation
		$history_repo = new AIPS_History_Repository();
		$history = $history_repo->get_by_post_id($post_id);
		
		// Apply title override if provided (after generation)
		if (!empty($params['overrides']['title'])) {
			wp_update_post(array(
				'ID' => $post_id,
				'post_title' => $params['overrides']['title']
			));
		}
		
		return array(
			'success' => true,
			'post_id' => $post_id,
			'history_id' => $history ? $history->id : null,
			'post' => array(
				'id' => $post_id,
				'title' => $post->post_title,
				'status' => $post->post_status,
				'url' => get_permalink($post_id),
				'edit_url' => get_edit_post_link($post_id, 'raw')
			)
		);
	}
	
	/**
	 * Tool: List templates
	 */
	private function tool_list_templates($params) {
		$template_repo = new AIPS_Template_Repository();
		
		// Get templates with optional filtering
		if (!empty($params['search'])) {
			$templates = $template_repo->search($params['search']);
		} else {
			$templates = $template_repo->get_all($params['active_only']);
		}
		
		// Format templates for response
		$formatted_templates = array();
		foreach ($templates as $template) {
			$formatted_templates[] = array(
				'id' => $template->id,
				'name' => $template->name,
				'is_active' => (bool) $template->is_active,
				'prompt_template' => $template->prompt_template,
				'title_prompt' => $template->title_prompt,
				'excerpt_prompt' => $template->excerpt_prompt,
				'post_status' => $template->post_status,
				'post_category' => $template->post_category,
				'post_author' => $template->post_author,
				'voice_id' => $template->voice_id,
				'article_structure_id' => $template->article_structure_id,
				'created_at' => $template->created_at
			);
		}
		
		return array(
			'success' => true,
			'templates' => $formatted_templates,
			'count' => count($formatted_templates)
		);
	}
	
	/**
	 * Tool: Get generation history
	 */
	private function tool_get_generation_history($params) {
		$history_repo = new AIPS_History_Repository();
		
		// Build query args
		$args = array(
			'per_page' => max(1, min(100, $params['per_page'])), // Clamp between 1-100
			'page' => max(1, $params['page']),
			'fields' => 'list' // Use list view for better performance
		);
		
		if (!empty($params['status'])) {
			$args['status'] = $params['status'];
		}
		
		if (!empty($params['template_id'])) {
			$args['template_id'] = $params['template_id'];
		}
		
		if (!empty($params['search'])) {
			$args['search'] = $params['search'];
		}
		
		// Get history
		$result = $history_repo->get_history($args);
		
		// Format history items
		$formatted_items = array();
		foreach ($result['items'] as $item) {
			$formatted_items[] = array(
				'id' => $item->id,
				'uuid' => $item->uuid,
				'post_id' => $item->post_id,
				'template_id' => $item->template_id,
				'template_name' => $item->template_name,
				'status' => $item->status,
				'generated_title' => $item->generated_title,
				'error_message' => $item->error_message,
				'created_at' => $item->created_at,
				'completed_at' => $item->completed_at,
				'post_url' => $item->post_id ? get_permalink($item->post_id) : null,
				'edit_url' => $item->post_id ? get_edit_post_link($item->post_id, 'raw') : null
			);
		}
		
		return array(
			'success' => true,
			'items' => $formatted_items,
			'pagination' => array(
				'total' => $result['total'],
				'pages' => $result['pages'],
				'current_page' => $result['current_page'],
				'per_page' => $args['per_page']
			)
		);
	}
	
	/**
	 * Tool: Get history
	 */
	private function tool_get_history($params) {
		// Must provide either history_id or post_id
		if (empty($params['history_id']) && empty($params['post_id'])) {
			return new WP_Error('missing_parameter', 'Must provide history_id or post_id');
		}
		
		$history_repo = new AIPS_History_Repository();
		$include_logs = isset($params['include_logs']) ? $params['include_logs'] : true;
		
		// Get history record
		if (!empty($params['history_id'])) {
			$history = $history_repo->get_by_id($params['history_id']);
		} else {
			$history = $history_repo->get_by_post_id($params['post_id']);
		}
		
		if (!$history) {
			return new WP_Error('history_not_found', 'History record not found');
		}
		
		// Format the response
		$result = array(
			'success' => true,
			'history' => array(
				'id' => $history->id,
				'uuid' => $history->uuid,
				'post_id' => $history->post_id,
				'template_id' => $history->template_id,
				'author_id' => isset($history->author_id) ? $history->author_id : null,
				'topic_id' => isset($history->topic_id) ? $history->topic_id : null,
				'status' => $history->status,
				'generated_title' => $history->generated_title,
				'generated_content' => isset($history->generated_content) ? $history->generated_content : null,
				'error_message' => $history->error_message,
				'creation_method' => isset($history->creation_method) ? $history->creation_method : null,
				'created_at' => $history->created_at,
				'completed_at' => $history->completed_at,
				'post_url' => $history->post_id ? get_permalink($history->post_id) : null,
				'edit_url' => $history->post_id ? get_edit_post_link($history->post_id, 'raw') : null
			)
		);
		
		// Add logs if requested and available
		if ($include_logs && isset($history->log)) {
			$formatted_logs = array();
			foreach ($history->log as $log) {
				$formatted_logs[] = array(
					'id' => $log->id,
					'log_type' => $log->log_type,
					'history_type_id' => isset($log->history_type_id) ? $log->history_type_id : null,
					'details' => json_decode($log->details, true),
					'timestamp' => $log->timestamp
				);
			}
			$result['history']['logs'] = $formatted_logs;
			$result['history']['log_count'] = count($formatted_logs);
		}
		
		return $result;
	}
	
	/**
	 * Tool: List authors
	 */
	private function tool_list_authors($params) {
		$authors_repo = new AIPS_Authors_Repository();
		$active_only = isset($params['active_only']) ? $params['active_only'] : false;
		
		$authors = $authors_repo->get_all($active_only);
		
		// Format authors for response
		$formatted_authors = array();
		foreach ($authors as $author) {
			$formatted_authors[] = array(
				'id' => $author->id,
				'name' => $author->name,
				'bio' => isset($author->bio) ? $author->bio : '',
				'expertise' => isset($author->expertise) ? $author->expertise : '',
				'tone' => isset($author->tone) ? $author->tone : '',
				'is_active' => (bool) $author->is_active,
				'created_at' => $author->created_at
			);
		}
		
		return array(
			'success' => true,
			'authors' => $formatted_authors,
			'count' => count($formatted_authors)
		);
	}
	
	/**
	 * Tool: Get author
	 */
	private function tool_get_author($params) {
		if (empty($params['author_id'])) {
			return new WP_Error('missing_parameter', 'author_id is required');
		}
		
		$authors_repo = new AIPS_Authors_Repository();
		$author = $authors_repo->get_by_id($params['author_id']);
		
		if (!$author) {
			return new WP_Error('author_not_found', 'Author not found');
		}
		
		return array(
			'success' => true,
			'author' => array(
				'id' => $author->id,
				'name' => $author->name,
				'bio' => isset($author->bio) ? $author->bio : '',
				'expertise' => isset($author->expertise) ? $author->expertise : '',
				'tone' => isset($author->tone) ? $author->tone : '',
				'is_active' => (bool) $author->is_active,
				'created_at' => $author->created_at,
				'updated_at' => isset($author->updated_at) ? $author->updated_at : null
			)
		);
	}
	
	/**
	 * Tool: List author topics
	 */
	private function tool_list_author_topics($params) {
		if (empty($params['author_id'])) {
			return new WP_Error('missing_parameter', 'author_id is required');
		}
		
		$topics_repo = new AIPS_Author_Topics_Repository();
		$status = isset($params['status']) ? $params['status'] : null;
		$limit = isset($params['limit']) ? max(1, min(500, $params['limit'])) : 50;
		
		// Get topics for author
		$topics = $topics_repo->get_by_author($params['author_id'], $status);
		
		// Limit results
		if (count($topics) > $limit) {
			$topics = array_slice($topics, 0, $limit);
		}
		
		// Format topics for response
		$formatted_topics = array();
		foreach ($topics as $topic) {
			$formatted_topics[] = array(
				'id' => $topic->id,
				'author_id' => $topic->author_id,
				'topic_title' => $topic->topic_title,
				'topic_prompt' => isset($topic->topic_prompt) ? $topic->topic_prompt : '',
				'status' => $topic->status,
				'score' => isset($topic->score) ? (int) $topic->score : null,
				'keywords' => isset($topic->keywords) ? $topic->keywords : '',
				'metadata' => isset($topic->metadata) ? $topic->metadata : '',
				'generated_at' => $topic->generated_at,
				'approved_at' => isset($topic->approved_at) ? $topic->approved_at : null
			);
		}
		
		return array(
			'success' => true,
			'topics' => $formatted_topics,
			'count' => count($formatted_topics),
			'total_available' => count($topics_repo->get_by_author($params['author_id'], $status))
		);
	}
	
	/**
	 * Tool: Get author topic
	 */
	private function tool_get_author_topic($params) {
		if (empty($params['topic_id'])) {
			return new WP_Error('missing_parameter', 'topic_id is required');
		}
		
		$topics_repo = new AIPS_Author_Topics_Repository();
		$topic = $topics_repo->get_by_id($params['topic_id']);
		
		if (!$topic) {
			return new WP_Error('topic_not_found', 'Topic not found');
		}
		
		return array(
			'success' => true,
			'topic' => array(
				'id' => $topic->id,
				'author_id' => $topic->author_id,
				'topic_title' => $topic->topic_title,
				'topic_prompt' => isset($topic->topic_prompt) ? $topic->topic_prompt : '',
				'status' => $topic->status,
				'score' => isset($topic->score) ? (int) $topic->score : null,
				'keywords' => isset($topic->keywords) ? $topic->keywords : '',
				'metadata' => isset($topic->metadata) ? $topic->metadata : '',
				'generated_at' => $topic->generated_at,
				'approved_at' => isset($topic->approved_at) ? $topic->approved_at : null,
				'feedback' => isset($topic->feedback) ? $topic->feedback : null
			)
		);
	}
	
	/**
	 * Tool: Regenerate post component
	 */
	private function tool_regenerate_post_component($params) {
		// Validate required parameters
		if (empty($params['post_id'])) {
			return new WP_Error('missing_parameter', 'post_id is required');
		}
		if (empty($params['history_id'])) {
			return new WP_Error('missing_parameter', 'history_id is required');
		}
		if (empty($params['component'])) {
			return new WP_Error('missing_parameter', 'component is required');
		}
		
		// Validate component type
		$valid_components = array('title', 'excerpt', 'content', 'featured_image');
		if (!in_array($params['component'], $valid_components)) {
			return new WP_Error('invalid_component', 'Invalid component. Must be one of: ' . implode(', ', $valid_components));
		}
		
		// Check if post exists
		$post = get_post($params['post_id']);
		if (!$post) {
			return new WP_Error('post_not_found', 'Post not found');
		}
		
		// Initialize regeneration service
		$regen_service = new AIPS_Component_Regeneration_Service();
		
		// Get generation context
		$context = $regen_service->get_generation_context($params['history_id']);
		if (is_wp_error($context)) {
			return $context;
		}
		
		// Verify context matches post
		if (isset($context['post_id']) && $context['post_id'] != $params['post_id']) {
			return new WP_Error('context_mismatch', 'History record does not belong to this post');
		}
		
		// Add post_id to context
		$context['post_id'] = $params['post_id'];
		
		// Regenerate the component
		$component = $params['component'];
		$result = null;
		
		try {
			switch ($component) {
				case 'title':
					$result = $regen_service->regenerate_title($context);
					break;
				case 'excerpt':
					$result = $regen_service->regenerate_excerpt($context);
					break;
				case 'content':
					$result = $regen_service->regenerate_content($context);
					break;
				case 'featured_image':
					$result = $regen_service->regenerate_featured_image($context);
					break;
			}
		} catch (Exception $e) {
			return new WP_Error('regeneration_failed', 'Component regeneration failed: ' . $e->getMessage());
		}
		
		if (is_wp_error($result)) {
			return $result;
		}
		
		// Prepare response
		$response = array(
			'success' => true,
			'component' => $component,
			'post_id' => $params['post_id'],
			'history_id' => $params['history_id'],
			'saved' => false
		);
		
		// Save to post if requested
		if (isset($params['save']) && $params['save'] === true) {
			$update_data = array('ID' => $params['post_id']);
			
			switch ($component) {
				case 'title':
					$update_data['post_title'] = $result;
					$response['new_value'] = $result;
					break;
				case 'excerpt':
					$update_data['post_excerpt'] = $result;
					$response['new_value'] = $result;
					break;
				case 'content':
					$update_data['post_content'] = $result;
					$response['new_value'] = $result;
					break;
				case 'featured_image':
					if (isset($result['attachment_id'])) {
						set_post_thumbnail($params['post_id'], $result['attachment_id']);
						$response['new_value'] = array(
							'attachment_id' => $result['attachment_id'],
							'url' => isset($result['url']) ? $result['url'] : wp_get_attachment_url($result['attachment_id'])
						);
					}
					break;
			}
			
			if ($component !== 'featured_image') {
				$updated = wp_update_post($update_data, true);
				if (is_wp_error($updated)) {
					return $updated;
				}
			}
			
			$response['saved'] = true;
			$response['message'] = ucfirst($component) . ' regenerated and saved successfully';
		} else {
			// Return preview only
			$response['new_value'] = $result;
			$response['message'] = ucfirst($component) . ' regenerated (preview only, not saved)';
		}
		
		return $response;
	}
	
	/**
	 * Tool: Get generation stats
	 */
	private function tool_get_generation_stats($params) {
		$history_repo = new AIPS_History_Repository();
		$period = isset($params['period']) ? $params['period'] : 'all';
		$template_id = isset($params['template_id']) ? $params['template_id'] : null;
		
		// Get overall stats
		$stats = $history_repo->get_stats();
		
		// Add period filtering if needed
		if ($period !== 'all') {
			$date_filter = '';
			switch ($period) {
				case 'today':
					$date_filter = date('Y-m-d 00:00:00');
					break;
				case 'week':
					$date_filter = date('Y-m-d 00:00:00', strtotime('-7 days'));
					break;
				case 'month':
					$date_filter = date('Y-m-d 00:00:00', strtotime('-30 days'));
					break;
			}
			
			if ($date_filter) {
				global $wpdb;
				$table_name = $wpdb->prefix . 'aips_history';
				
				$where = "created_at >= %s";
				$query_args = array($date_filter);
				
				if ($template_id) {
					$where .= " AND template_id = %d";
					$query_args[] = $template_id;
				}
				
				$results = $wpdb->get_row($wpdb->prepare("
					SELECT
						COUNT(*) as total,
						SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
						SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
						SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing
					FROM {$table_name}
					WHERE {$where}
				", $query_args));
				
				$stats = array(
					'total' => (int) $results->total,
					'completed' => (int) $results->completed,
					'failed' => (int) $results->failed,
					'processing' => (int) $results->processing,
				);
				
				$stats['success_rate'] = $stats['total'] > 0 
					? round(($stats['completed'] / $stats['total']) * 100, 1) 
					: 0;
			}
		} elseif ($template_id) {
			// Filter by template for all time
			$template_count = $history_repo->get_template_stats($template_id);
			$stats['template_id'] = $template_id;
			$stats['template_completed'] = $template_count;
		}
		
		// Add template breakdown
		$stats['by_template'] = $history_repo->get_all_template_stats();
		
		// Add period info
		$stats['period'] = $period;
		
		return array(
			'success' => true,
			'stats' => $stats
		);
	}
	
	/**
	 * Tool: Get post metadata
	 */
	private function tool_get_post_metadata($params) {
		if (empty($params['post_id'])) {
			return new WP_Error('missing_parameter', 'post_id is required');
		}
		
		$post_id = $params['post_id'];
		
		// Check if post exists
		$post = get_post($post_id);
		if (!$post) {
			return new WP_Error('post_not_found', 'Post not found');
		}
		
		// Get history for this post
		$history_repo = new AIPS_History_Repository();
		$history = $history_repo->get_by_post_id($post_id);
		
		if (!$history) {
			return new WP_Error('history_not_found', 'No generation history found for this post');
		}
		
		// Get AI-specific metadata from post meta
		$ai_model = get_post_meta($post_id, '_aips_ai_model', true);
		$ai_prompt = get_post_meta($post_id, '_aips_prompt', true);
		$generation_time = get_post_meta($post_id, '_aips_generation_time', true);
		$tokens_used = get_post_meta($post_id, '_aips_tokens_used', true);
		
		// Build response
		$metadata = array(
			'post_id' => $post_id,
			'post_title' => $post->post_title,
			'post_status' => $post->post_status,
			'post_date' => $post->post_date,
			'history_id' => $history->id,
			'template_id' => $history->template_id,
			'template_name' => isset($history->template_name) ? $history->template_name : null,
			'author_id' => isset($history->author_id) ? $history->author_id : null,
			'topic_id' => isset($history->topic_id) ? $history->topic_id : null,
			'creation_method' => isset($history->creation_method) ? $history->creation_method : null,
			'generated_at' => $history->created_at,
			'completed_at' => $history->completed_at,
			'status' => $history->status,
			'ai_model' => $ai_model ?: null,
			'tokens_used' => $tokens_used ? (int) $tokens_used : null,
			'generation_time' => $generation_time ? (float) $generation_time : null,
			'has_prompt' => !empty($ai_prompt),
			'post_url' => get_permalink($post_id),
			'edit_url' => get_edit_post_link($post_id, 'raw')
		);
		
		return array(
			'success' => true,
			'metadata' => $metadata
		);
	}
	
	/**
	 * Tool: Get AI models
	 */
	private function tool_get_ai_models($params) {
		// Check if AI Engine is available
		$ai_service = new AIPS_AI_Service();
		
		if (!$ai_service->is_available()) {
			return new WP_Error('ai_unavailable', 'AI Engine plugin is not available or not configured');
		}
		
		// Get the current configured model
		$current_model = get_option('aips_ai_model', '');
		
		// Try to get available models from AI Engine
		global $mwai;
		$available_models = array();
		
		if ($mwai) {
			// AI Engine doesn't expose a direct API for listing models
			// We'll provide the commonly available models and indicate which is configured
			$common_models = array(
				'gpt-4' => array('name' => 'GPT-4', 'provider' => 'OpenAI', 'type' => 'chat'),
				'gpt-4-turbo' => array('name' => 'GPT-4 Turbo', 'provider' => 'OpenAI', 'type' => 'chat'),
				'gpt-4o' => array('name' => 'GPT-4o', 'provider' => 'OpenAI', 'type' => 'chat'),
				'gpt-3.5-turbo' => array('name' => 'GPT-3.5 Turbo', 'provider' => 'OpenAI', 'type' => 'chat'),
				'claude-3-opus' => array('name' => 'Claude 3 Opus', 'provider' => 'Anthropic', 'type' => 'chat'),
				'claude-3-sonnet' => array('name' => 'Claude 3 Sonnet', 'provider' => 'Anthropic', 'type' => 'chat'),
				'claude-3-haiku' => array('name' => 'Claude 3 Haiku', 'provider' => 'Anthropic', 'type' => 'chat'),
			);
			
			foreach ($common_models as $model_id => $model_info) {
				$available_models[] = array(
					'id' => $model_id,
					'name' => $model_info['name'],
					'provider' => $model_info['provider'],
					'type' => $model_info['type'],
					'is_current' => ($model_id === $current_model)
				);
			}
		}
		
		return array(
			'success' => true,
			'current_model' => $current_model ?: null,
			'models' => $available_models,
			'note' => 'Model availability depends on AI Engine configuration. List shows common models.'
		);
	}
	
	/**
	 * Tool: Test AI connection
	 */
	private function tool_test_ai_connection($params) {
		$test_prompt = isset($params['test_prompt']) ? $params['test_prompt'] : 'Say "Hello" if you can read this.';
		
		// Check if AI Engine is available
		$ai_service = new AIPS_AI_Service();
		
		if (!$ai_service->is_available()) {
			return array(
				'success' => false,
				'connected' => false,
				'error' => 'AI Engine plugin is not available or not installed',
				'message' => 'Please install and activate the AI Engine plugin'
			);
		}
		
		// Try a simple test query
		$start_time = microtime(true);
		
		try {
			$result = $ai_service->generate_text($test_prompt, array(
				'max_tokens' => 50,
				'temperature' => 0.7
			));
			
			$elapsed_time = round((microtime(true) - $start_time) * 1000, 2);
			
			if (is_wp_error($result)) {
				return array(
					'success' => false,
					'connected' => false,
					'error' => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
					'response_time_ms' => $elapsed_time
				);
			}
			
			return array(
				'success' => true,
				'connected' => true,
				'test_prompt' => $test_prompt,
				'response' => substr($result, 0, 200), // Limit response length
				'response_time_ms' => $elapsed_time,
				'model' => get_option('aips_ai_model', 'default'),
				'message' => 'AI Engine connection successful'
			);
			
		} catch (Exception $e) {
			$elapsed_time = round((microtime(true) - $start_time) * 1000, 2);
			
			return array(
				'success' => false,
				'connected' => false,
				'error' => $e->getMessage(),
				'response_time_ms' => $elapsed_time
			);
		}
	}
	
	/**
	 * Tool: Get plugin settings
	 */
	private function tool_get_plugin_settings($params) {
		$category = isset($params['category']) ? $params['category'] : 'all';
		$config = AIPS_Config::get_instance();
		
		$settings = array();
		
		// AI Settings
		if ($category === 'all' || $category === 'ai') {
			$settings['ai'] = array(
				'model' => get_option('aips_ai_model', ''),
				'max_tokens' => (int) get_option('aips_max_tokens', 2000),
				'temperature' => (float) get_option('aips_temperature', 0.7),
				'default_post_status' => get_option('aips_default_post_status', 'draft'),
				'default_post_author' => (int) get_option('aips_default_post_author', 1)
			);
		}
		
		// Resilience Settings
		if ($category === 'all' || $category === 'resilience') {
			$settings['resilience'] = array(
				'enable_retry' => (bool) get_option('aips_enable_retry', true),
				'retry_max_attempts' => (int) get_option('aips_retry_max_attempts', 3),
				'retry_initial_delay' => (int) get_option('aips_retry_initial_delay', 1),
				'enable_rate_limiting' => (bool) get_option('aips_enable_rate_limiting', false),
				'rate_limit_requests' => (int) get_option('aips_rate_limit_requests', 10),
				'rate_limit_period' => (int) get_option('aips_rate_limit_period', 60),
				'enable_circuit_breaker' => (bool) get_option('aips_enable_circuit_breaker', false),
				'circuit_breaker_threshold' => (int) get_option('aips_circuit_breaker_threshold', 5),
				'circuit_breaker_timeout' => (int) get_option('aips_circuit_breaker_timeout', 300)
			);
		}
		
		// Logging Settings
		if ($category === 'all' || $category === 'logging') {
			$settings['logging'] = array(
				'enable_logging' => (bool) get_option('aips_enable_logging', true),
				'log_retention_days' => (int) get_option('aips_log_retention_days', 30)
			);
		}
		
		// Export Thresholds
		if ($category === 'all') {
			$settings['thresholds'] = array(
				'generated_posts_log_threshold_tmpfile' => (int) get_option('generated_posts_log_threshold_tmpfile', 200),
				'generated_posts_log_threshold_client' => (int) get_option('generated_posts_log_threshold_client', 20),
				'history_export_max_records' => (int) get_option('history_export_max_records', 10000)
			);
		}
		
		return array(
			'success' => true,
			'category' => $category,
			'settings' => $settings
		);
	}
}

// If called directly via HTTP
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$bridge = new AIPS_MCP_Bridge();
	$bridge->handle_request();
}
