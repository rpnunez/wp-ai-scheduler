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
	 * @param array $params Parameters for the tool
	 * @return mixed|WP_Error Result or error
	 */
	public function execute_tool($tool_name, $params = array()) {
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
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';
		
		$where = array();
		
		if ($params['older_than_days'] > 0) {
			$date = date('Y-m-d H:i:s', strtotime("-{$params['older_than_days']} days"));
			$where[] = $wpdb->prepare("created_at < %s", $date);
		}
		
		if ($params['status'] !== 'all') {
			$where[] = $wpdb->prepare("status = %s", $params['status']);
		}
		
		$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
		
		$count = $wpdb->get_var("SELECT COUNT(*) FROM $table $where_clause");
		$deleted = $wpdb->query("DELETE FROM $table $where_clause");
		
		// Clear cache
		delete_transient('aips_history_stats');
		
		return array(
			'success' => true,
			'deleted' => $deleted,
			'message' => "Deleted $deleted history records"
		);
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
			
			// Create topic context
			require_once AIPS_PLUGIN_DIR . 'includes/class-aips-topic-context.php';
			$context = new AIPS_Topic_Context($author_topic);
			
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
}

// If called directly via HTTP
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$bridge = new AIPS_MCP_Bridge();
	$bridge->handle_request();
}
