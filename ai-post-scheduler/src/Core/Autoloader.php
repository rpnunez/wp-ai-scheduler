<?php
namespace AIPS\Core;

if (!defined('ABSPATH')) {
	exit;
}

class Autoloader {
	/**
	 * @var array Mapping of legacy class names to their new namespaced targets.
	 */
	private static $aliases = array(
		// Core / Common Subsystem
		'AIPS_Config' => 'AIPS\\Core\\Config',
		'AIPS_Container' => 'AIPS\\Core\\Container',
		'AIPS_DateTime' => 'AIPS\\Core\\DateTime',
		'AIPS_Logger' => 'AIPS\\Core\\Logger',
		'AIPS_Logger_Interface' => 'AIPS\\Core\\LoggerInterface',
		'AIPS_Correlation_Id' => 'AIPS\\Core\\CorrelationID',
		'AIPS_Site_Context' => 'AIPS\\Core\\SiteContext',
		'AIPS_Utilities' => 'AIPS\\Core\\Utilities',
		'AIPS_Error_Handler' => 'AIPS\\Core\\ErrorHandler',

		// Database & Migrations
		'AIPS_DB_Manager' => 'AIPS\\DB\\DBManager',
		'AIPS_DB_Migrations' => 'AIPS\\DB\\DBMigrations',
		'AIPS_Date_Time_DB_Repair' => 'AIPS\\DB\\DateTimeDBRepair',
		'AIPS_Upgrades' => 'AIPS\\DB\\Upgrades',
		'AIPS_Seeder_Service' => 'AIPS\\DB\\SeederService',

		// Routing / AJAX
		'AIPS_Ajax_Registry' => 'AIPS\\Routing\\AJAXRegistry',
		'AIPS_Ajax_Response' => 'AIPS\\Routing\\AJAXResponse',

		// Taxonomy Subsystem
		'AIPS_Taxonomy_Controller' => 'AIPS\\Taxonomy\\TaxonomyController',
		'AIPS_Taxonomy_Repository' => 'AIPS\\Taxonomy\\TaxonomyRepository',

		// Campaign Subsystem
		'AIPS_Campaigns_Controller' => 'AIPS\\Campaigns\\CampaignsController',
		'AIPS_Campaigns_Repository' => 'AIPS\\Campaigns\\CampaignsRepository',

		// AI & Generation
		'AIPS_AI_Service' => 'AIPS\\AI\\AIService',
		'AIPS_AI_Service_Interface' => 'AIPS\\AI\\AIServiceInterface',
		'AIPS_AI_Assistance_Controller' => 'AIPS\\AI\\AIAssistanceController',
		'AIPS_AI_Assistance_Repository' => 'AIPS\\AI\\AIAssistanceRepository',
		'AIPS_AI_Assistance_Service' => 'AIPS\\AI\\AIAssistanceService',
		'AIPS_AI_Edit_Controller' => 'AIPS\\AI\\AIEditController',
		'AIPS_Generator' => 'AIPS\\AI\\Generator',
		'AIPS_Generation_Context_Factory' => 'AIPS\\AI\\GenerationContextFactory',
		'AIPS_Generation_Context_Interface' => 'AIPS\\AI\\GenerationContextInterface',
		'AIPS_Generation_Execution_Runner' => 'AIPS\\AI\\GenerationExecutionRunner',
		'AIPS_Generation_Logger' => 'AIPS\\AI\\GenerationLogger',
		'AIPS_Generation_Result' => 'AIPS\\AI\\GenerationResult',
		'AIPS_Generation_Session' => 'AIPS\\AI\\GenerationSession',
		'AIPS_Bulk_Generator_Service' => 'AIPS\\AI\\BulkGeneratorService',
		'AIPS_Partial_Generation_State_Reconciler' => 'AIPS\\AI\\PartialGenerationStateReconciler',
		'AIPS_Token_Budget' => 'AIPS\\AI\\TokenBudget',
		'AIPS_Topic_Context' => 'AIPS\\AI\\TopicContext',
		'AIPS_Topic_Expansion_Service' => 'AIPS\\AI\\TopicExpansionService',
		'AIPS_Topic_Penalty_Service' => 'AIPS\\AI\\TopicPenaltyService',

		// Post Management
		'AIPS_Post_Creator' => 'AIPS\\Posts\\PostCreator',
		'AIPS_Post_Manager' => 'AIPS\\Posts\\PostManager',
		'AIPS_Post_Review' => 'AIPS\\Posts\\PostReview',
		'AIPS_Post_Review_Repository' => 'AIPS\\Posts\\PostReviewRepository',
		'AIPS_Post_Slices_Controller' => 'AIPS\\Posts\\PostSlicesController',
		'AIPS_Post_Slices_Repository' => 'AIPS\\Posts\\PostSlicesRepository',
		'AIPS_Generated_Posts_Controller' => 'AIPS\\Posts\\GeneratedPostsController',

		// Admin / UI
		'AIPS_Post_History_Ui' => 'AIPS\\Admin\\PostHistoryUI',
		'AIPS_Admin_Assets' => 'AIPS\\Admin\\AdminAssets',
		'AIPS_Admin_Bar' => 'AIPS\\Admin\\AdminBar',
		'AIPS_Admin_Flow_Controller' => 'AIPS\\Admin\\AdminFlowController',
		'AIPS_Admin_Menu_Helper' => 'AIPS\\Admin\\AdminMenuHelper',
		'AIPS_Admin_Menu' => 'AIPS\\Admin\\AdminMenu',
		'AIPS_Dashboard_Controller' => 'AIPS\\Admin\\DashboardController',
		'AIPS_Onboarding_Wizard' => 'AIPS\\Admin\\OnboardingWizard',
		'AIPS_Seeder_Admin' => 'AIPS\\Admin\\SeederAdmin',

		// Scheduler & Jobs
		'AIPS_Unified_Schedule_Service' => 'AIPS\\Scheduler\\ScheduleService',
		'AIPS_Scheduler' => 'AIPS\\Scheduler\\Scheduler',
		'AIPS_Schedule_Controller' => 'AIPS\\Scheduler\\ScheduleController',
		'AIPS_Schedule_Entry' => 'AIPS\\Scheduler\\ScheduleEntry',
		'AIPS_Schedule_Processor' => 'AIPS\\Scheduler\\ScheduleProcessor',
		'AIPS_Schedule_Repository' => 'AIPS\\Scheduler\\ScheduleRepository',
		'AIPS_Schedule_Repository_Interface' => 'AIPS\\Scheduler\\ScheduleRepositoryInterface',
		'AIPS_Schedule_Result_Handler' => 'AIPS\\Scheduler\\ScheduleResultHandler',
		'AIPS_Cron_Generation_Handler' => 'AIPS\\Scheduler\\CronGenerationHandlerInterface',

		// Additional Services
		'AIPS_Voices_Repository' => 'AIPS\\Services\\VoicesRepository',
		'AIPS_Article_Structure_Repository' => 'AIPS\\Services\\ArticleStructureRepository',
		'AIPS_Authors_Repository' => 'AIPS\\Author\\AuthorsRepository',
		'AIPS_Interval_Calculator' => 'AIPS\\Scheduler\\IntervalCalculator',
		'AIPS_Template_Repository' => 'AIPS\\Templates\\TemplateRepository',
		'AIPS_History_Service' => 'AIPS\\History\\HistoryService',

		// Settings
		'AIPS_Settings' => 'AIPS\\Settings\\Settings',
		'AIPS_Settings_UI' => 'AIPS\\Settings\\SettingsUI',
		'AIPS_Settings_Ajax' => 'AIPS\\Settings\\SettingsAJAX',
	);

	/**
	 * Register the autoloader.
	 */
	public static function register() {
		// Register namespaced and aliased autoloader
		spl_autoload_register(array(__CLASS__, 'load'), true, true);
	}

	/**
	 * Convert class name to base name (lowercase with hyphens)
	 *
	 * @param string $class_name The class name to convert
	 * @return string The base name (e.g., "aips-history-repository")
	 */
	public static function convert_class_name_to_base($class_name) {
		return strtolower(str_replace('_', '-', $class_name));
	}

	/**
	 * Convert class name to class file name
	 *
	 * @param string $class_name The class name to convert
	 * @return string The converted file name (e.g., "class-aips-history-repository.php")
	 */
	public static function convert_class_name_to_filename($class_name) {
		$base_name = self::convert_class_name_to_base($class_name);
		return 'class-' . $base_name . '.php';
	}

	/**
	 * @var array Lowercased cache of class aliases for case-insensitive lookup.
	 */
	private static $aliases_lower = null;

	/**
	 * @var array Flipped cache of class aliases for bidirectional resolution.
	 */
	private static $aliases_flipped = null;

	/**
	 * Load class files.
	 *
	 * @param string $class_name
	 * @return bool
	 */
	public static function load($class_name) {
		// 1. Handle namespaced classes starting with AIPS\
		if (strpos($class_name, 'AIPS\\') === 0) {
			$relative_class = substr($class_name, 5);
			$file = AIPS_PLUGIN_DIR . 'src/' . str_replace('\\', '/', $relative_class) . '.php';
			if (file_exists($file)) {
				require_once $file;
				return true;
			}

			// Bidirectional aliasing: if new file doesn't exist yet, try to load legacy class from includes/
			if (self::$aliases_flipped === null) {
				self::$aliases_flipped = array_flip(self::$aliases);
			}
			if (isset(self::$aliases_flipped[$class_name])) {
				$legacy_name = self::$aliases_flipped[$class_name];
				if (!class_exists($legacy_name) && !interface_exists($legacy_name) && !trait_exists($legacy_name)) {
					self::load($legacy_name);
				}
				if (class_exists($legacy_name) || interface_exists($legacy_name) || trait_exists($legacy_name)) {
					class_alias($legacy_name, $class_name);
					return true;
				}
			}
		}

		// 2. Handle mapped legacy AIPS_ prefix classes via dynamic class_alias()
		if (self::$aliases_lower === null) {
			self::$aliases_lower = array_change_key_case(self::$aliases, CASE_LOWER);
		}

		$lookup = strtolower($class_name);
		if (isset(self::$aliases_lower[$lookup])) {
			$target = self::$aliases_lower[$lookup];
			if (!class_exists($target) && !interface_exists($target) && !trait_exists($target)) {
				self::load($target);
			}
			if (class_exists($target) || interface_exists($target) || trait_exists($target)) {
				class_alias($target, $class_name);
				return true;
			}
		}

		// 3. Fallback compatibility: load AIPS_Autoloader class name itself
		if (strcasecmp($class_name, 'AIPS_Autoloader') === 0) {
			class_alias(__CLASS__, 'AIPS_Autoloader');
			return true;
		}

		// 4. Fallback loader for un-refactored AIPS_ prefix classes from includes/
		if (strpos($class_name, 'AIPS_') === 0) {
			$base_name = self::convert_class_name_to_base($class_name);
			$class_file = 'class-' . $base_name . '.php';
			$interface_file = 'interface-' . $base_name . '.php';
			$trait_file = 'trait-' . $base_name . '.php';

			$paths = array(
				AIPS_PLUGIN_DIR . 'includes/',
				AIPS_PLUGIN_DIR . 'includes/diagnostics/',
				AIPS_PLUGIN_DIR . 'includes/job/',
			);

			foreach ($paths as $path) {
				if (file_exists($path . $class_file)) {
					require_once $path . $class_file;
					return true;
				}
				if (file_exists($path . $interface_file)) {
					require_once $path . $interface_file;
					return true;
				}
				if (file_exists($path . $trait_file)) {
					require_once $path . $trait_file;
					return true;
				}
			}
		}

		return false;
	}
}
