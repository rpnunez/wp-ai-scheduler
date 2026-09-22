<?php
/**
 * Service Provider
 *
 * Centralizes dependency injection container binding registrations for the plugin.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.5
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Service_Provider
 */
class AIPS_Service_Provider {

	/**
	 * Register core bindings into the container.
	 *
	 * @param AIPS_Container $container The container instance.
	 * @return void
	 */
	public static function register(AIPS_Container $container) {
		// Configuration
		$container->singleton(AIPS_Config::class, function() {
			return AIPS_Config::get_instance();
		});

		// Core Utilities & Logging
		$container->singleton(AIPS_Logger::class, function() {
			return new AIPS_Logger();
		});
		$container->singleton(AIPS_Logger_Interface::class, AIPS_Logger::class);

		// AI Provider & AI Service
		$container->singleton(AIPS_AI_Provider_Interface::class, function() {
			return AIPS_AI_Provider_Factory::create();
		});

		$container->singleton(AIPS_AI_Service::class, function($c) {
			return new AIPS_AI_Service(
				$c->make(AIPS_Logger_Interface::class),
				$c->make(AIPS_Config::class),
				null,
				$c->make(AIPS_AI_Provider_Interface::class)
			);
		});
		$container->singleton(AIPS_AI_Service_Interface::class, AIPS_AI_Service::class);

		// Repositories
		$container->singleton(AIPS_History_Repository::class, function() {
			return new AIPS_History_Repository();
		});
		$container->singleton(AIPS_History_Repository_Interface::class, AIPS_History_Repository::class);

		$container->singleton(AIPS_Notifications_Repository::class, function() {
			return new AIPS_Notifications_Repository();
		});
		$container->singleton(AIPS_Notifications_Repository_Interface::class, AIPS_Notifications_Repository::class);

		$container->singleton(AIPS_Schedule_Repository::class, function() {
			return new AIPS_Schedule_Repository();
		});
		$container->singleton(AIPS_Schedule_Repository_Interface::class, AIPS_Schedule_Repository::class);

		$container->singleton(AIPS_Telemetry_Repository::class, function() {
			return new AIPS_Telemetry_Repository();
		});

		$container->singleton(AIPS_Template_Repository::class, function() {
			return new AIPS_Template_Repository();
		});

		$container->singleton(AIPS_Authors_Repository::class, function() {
			return new AIPS_Authors_Repository();
		});

		$container->singleton(AIPS_Author_Topics_Repository::class, function() {
			return new AIPS_Author_Topics_Repository();
		});

		$container->singleton(AIPS_Author_Topic_Logs_Repository::class, function() {
			return new AIPS_Author_Topic_Logs_Repository();
		});

		$container->singleton(AIPS_Post_Slices_Repository::class, function() {
			return new AIPS_Post_Slices_Repository();
		});

		$container->singleton(AIPS_Feedback_Repository::class, function() {
			return new AIPS_Feedback_Repository();
		});

		$container->singleton(AIPS_Sources_Data_Repository::class, function() {
			return new AIPS_Sources_Data_Repository();
		});

		// Services
		$container->singleton(AIPS_History_Service::class, function($c) {
			return new AIPS_History_Service(
				$c->make(AIPS_History_Repository_Interface::class),
				$c->make(AIPS_Config::class)
			);
		});
		$container->singleton(AIPS_History_Service_Interface::class, AIPS_History_Service::class);

		$container->singleton(AIPS_System_Diagnostics_Service::class, function() {
			return new AIPS_System_Diagnostics_Service();
		});

		$container->singleton(AIPS_System_Status_Diagnostics_Service::class, function() {
			return new AIPS_System_Status_Diagnostics_Service();
		});

		$container->singleton(AIPS_Interval_Calculator::class, function() {
			return new AIPS_Interval_Calculator();
		});

		$container->singleton(AIPS_Job_Scheduler::class, function() {
			return new AIPS_Job_Scheduler();
		});

		// Embeddings & Content Indexing Bindings (for container resolution without modifying embedding files)
		$container->singleton(AIPS_Embeddings_Repository::class, function() {
			return new AIPS_Embeddings_Repository();
		});

		$container->singleton(AIPS_Relationships_Repository::class, function() {
			return new AIPS_Relationships_Repository();
		});

		$container->singleton(AIPS_Embeddings_Service::class, function($c) {
			return new AIPS_Embeddings_Service(
				$c->make(AIPS_AI_Service_Interface::class),
				$c->make(AIPS_Logger_Interface::class)
			);
		});

		$container->singleton(AIPS_Content_Indexer_Service::class, function($c) {
			return new AIPS_Content_Indexer_Service(
				$c->make(AIPS_Embeddings_Repository::class),
				$c->make(AIPS_Relationships_Repository::class),
				$c->make(AIPS_Embeddings_Service::class),
				$c->make(AIPS_History_Service_Interface::class),
				$c->make(AIPS_Logger_Interface::class),
				$c->make(AIPS_Config::class),
				$c->make(AIPS_Author_Topics_Repository::class)
			);
		});

		$container->singleton(AIPS_Related_Posts_Service::class, function($c) {
			return new AIPS_Related_Posts_Service(
				$c->make(AIPS_Relationships_Repository::class),
				$c->make(AIPS_Embeddings_Repository::class),
				$c->make(AIPS_Embeddings_Service::class),
				$c->make(AIPS_Config::class)
			);
		});

		$container->singleton(AIPS_Deduplication_Service::class, function($c) {
			return new AIPS_Deduplication_Service(
				$c->make(AIPS_Embeddings_Repository::class),
				$c->make(AIPS_Relationships_Repository::class),
				$c->make(AIPS_Embeddings_Service::class),
				null,
				$c->make(AIPS_Logger_Interface::class)
			);
		});
	}
}
