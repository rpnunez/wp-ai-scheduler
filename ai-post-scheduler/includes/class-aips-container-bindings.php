<?php
/**
 * Container Bindings Registrations.
 *
 * Registers core services, repositories, and interfaces in the dependency injection container.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

final class AIPS_Container_Bindings {

	/**
	 * Register all container bindings.
	 *
	 * @param AIPS_Container|null $container Container instance (defaults to singleton).
	 * @return void
	 */
	public static function register( $container = null ) {
		if ( null === $container ) {
			$container = AIPS_Container::get_instance();
		}

		// Register AIPS_Config (uses get_instance() instead of instance())
		$container->singleton(AIPS_Config::class, function( $container ) {
			return AIPS_Config::get_instance();
		});

		// Register AIPS_History_Repository
		$container->singleton(AIPS_History_Repository::class, function( $container ) {
			return AIPS_History_Repository::instance();
		});

		$container->singleton(AIPS_History_Repository_Interface::class, function( $container ) {
			return $container->make(AIPS_History_Repository::class);
		});

		// Register AIPS_History_Service
		$container->singleton(AIPS_History_Service::class, function( $container ) {
			return AIPS_History_Service::instance();
		});

		$container->singleton(AIPS_History_Service_Interface::class, function( $container ) {
			return $container->make(AIPS_History_Service::class);
		});

		// Register AIPS_Notifications_Repository
		$container->singleton(AIPS_Notifications_Repository::class, function( $container ) {
			return AIPS_Notifications_Repository::instance();
		});

		$container->singleton(AIPS_Notifications_Repository_Interface::class, function( $container ) {
			return $container->make(AIPS_Notifications_Repository::class);
		});

		$container->singleton(AIPS_Logger::class, function( $container ) {
			return AIPS_Logger::instance();
		});

		$container->singleton(AIPS_Logger_Interface::class, function( $container ) {
			return $container->make(AIPS_Logger::class);
		});

		$container->singleton(AIPS_AI_Provider_Interface::class, function( $container ) {
			return AIPS_AI_Provider_Factory::create();
		});

		$container->singleton(AIPS_AI_Service::class, function( $container ) {
			return new AIPS_AI_Service(
				provider: $container->make(AIPS_AI_Provider_Interface::class)
			);
		});

		$container->singleton(AIPS_AI_Service_Interface::class, function( $container ) {
			return $container->make(AIPS_AI_Service::class);
		});

		$container->singleton(AIPS_Schedule_Repository::class, function( $container ) {
			return AIPS_Schedule_Repository::instance();
		});

		$container->singleton(AIPS_Schedule_Repository_Interface::class, function( $container ) {
			return $container->make(AIPS_Schedule_Repository::class);
		});

		$container->singleton(AIPS_Telemetry_Repository::class, function( $container ) {
			return AIPS_Telemetry_Repository::instance();
		});

		// Register AIPS_DB_Prune_Repository and AIPS_DB_Prune_Service
		$container->singleton(AIPS_DB_Prune_Repository::class, function( $container ) {
			return AIPS_DB_Prune_Repository::instance();
		});

		$container->singleton(AIPS_DB_Prune_Service::class, function( $container ) {
			return AIPS_DB_Prune_Service::instance();
		});

		// Register AIPS_Template_Repository
		$container->singleton(AIPS_Template_Repository::class, function( $container ) {
			return AIPS_Template_Repository::instance();
		});

		// Register AIPS_System_Diagnostics_Service
		$container->singleton(AIPS_System_Diagnostics_Service::class, function( $container ) {
			return new AIPS_System_Diagnostics_Service();
		});

		// Register AIPS_System_Status_Diagnostics_Service
		$container->singleton(AIPS_System_Status_Diagnostics_Service::class, function( $container ) {
			return new AIPS_System_Status_Diagnostics_Service();
		});

		// Register AIPS_Embeddings_Repository
		$container->singleton(AIPS_Embeddings_Repository::class, function( $container ) {
			return new AIPS_Embeddings_Repository();
		});

		// Register AIPS_Relationships_Repository
		$container->singleton(AIPS_Relationships_Repository::class, function( $container ) {
			return new AIPS_Relationships_Repository();
		});

		// Background process registry/manager (start, pause, resume, stop, Heartbeat snapshots)
		$container->singleton(AIPS_Background_Process_Manager::class, function( $container ) {
			return new AIPS_Background_Process_Manager();
		});

		// Register the link index (actual <a href> links in post content)
		$container->singleton(AIPS_Link_Index_Repository::class, function( $container ) {
			return new AIPS_Link_Index_Repository();
		});

		$container->singleton(AIPS_Link_Index_Service::class, function( $container ) {
			return new AIPS_Link_Index_Service(
				$container->make(AIPS_Link_Index_Repository::class)
			);
		});

		$container->singleton(AIPS_Link_Rules_Service::class, function( $container ) {
			return new AIPS_Link_Rules_Service();
		});

		$container->singleton(AIPS_Redirects_Service::class, function( $container ) {
			return new AIPS_Redirects_Service();
		});

		$container->singleton(AIPS_Publish_Linking_Service::class, function( $container ) {
			return new AIPS_Publish_Linking_Service();
		});

		$container->singleton(AIPS_Silo_Service::class, function( $container ) {
			return new AIPS_Silo_Service();
		});

		$container->singleton(AIPS_GSC_Client::class, function( $container ) {
			return new AIPS_GSC_Client();
		});

		$container->singleton(AIPS_GSC_Keywords_Service::class, function( $container ) {
			return new AIPS_GSC_Keywords_Service($container->make(AIPS_GSC_Client::class));
		});

		$container->singleton(AIPS_Link_Clicks_Repository::class, function( $container ) {
			return new AIPS_Link_Clicks_Repository();
		});

		$container->singleton(AIPS_Link_Click_Tracking_Service::class, function( $container ) {
			return new AIPS_Link_Click_Tracking_Service(null, $container->make(AIPS_Link_Clicks_Repository::class));
		});

		$container->singleton(AIPS_Autolink_Run_Service::class, function( $container ) {
			return new AIPS_Autolink_Run_Service(
				$container->make(AIPS_Inbound_Links_Service::class),
				$container->make(AIPS_Link_Index_Service::class)
			);
		});

		$container->singleton(AIPS_Inbound_Links_Service::class, function( $container ) {
			return new AIPS_Inbound_Links_Service(
				null,
				$container->make(AIPS_Link_Index_Service::class),
				$container->make(AIPS_Relationships_Repository::class)
			);
		});

		// Register AIPS_Embeddings_Service
		$container->singleton(AIPS_Embeddings_Service::class, function( $container ) {
			return new AIPS_Embeddings_Service(
				$container->make(AIPS_AI_Service_Interface::class),
				$container->make(AIPS_Logger_Interface::class)
			);
		});

		// Register AIPS_Content_Indexer_Service
		$container->singleton(AIPS_Content_Indexer_Service::class, function( $container ) {
			return new AIPS_Content_Indexer_Service(
				$container->make(AIPS_Embeddings_Repository::class),
				$container->make(AIPS_Relationships_Repository::class),
				$container->make(AIPS_Embeddings_Service::class),
				$container->make(AIPS_History_Service_Interface::class),
				$container->make(AIPS_Logger_Interface::class),
				$container->make(AIPS_Config::class),
				$container->has(AIPS_Author_Topics_Repository::class) ? $container->make(AIPS_Author_Topics_Repository::class) : new AIPS_Author_Topics_Repository()
			);
		});

		// Register AIPS_Related_Posts_Service
		$container->singleton(AIPS_Related_Posts_Service::class, function( $container ) {
			return new AIPS_Related_Posts_Service(
				$container->make(AIPS_Relationships_Repository::class),
				$container->make(AIPS_Embeddings_Repository::class),
				$container->make(AIPS_Embeddings_Service::class),
				$container->make(AIPS_Config::class)
			);
		});

		// Register AIPS_Deduplication_Service
		$container->singleton(AIPS_Deduplication_Service::class, function( $container ) {
			return new AIPS_Deduplication_Service(
				$container->make(AIPS_Embeddings_Repository::class),
				$container->make(AIPS_Relationships_Repository::class),
				$container->make(AIPS_Embeddings_Service::class),
				$container->make(AIPS_Config::class),
				$container->make(AIPS_Logger_Interface::class)
			);
		});

		// Register AIPS_Similarity_Evaluator
		$container->singleton(AIPS_Similarity_Evaluator::class, function( $container ) {
			return new AIPS_Similarity_Evaluator(
				$container->make(AIPS_Config::class)
			);
		});

		// Register AIPS_Post_Insights_Repository
		$container->singleton(AIPS_Post_Insights_Repository::class, function( $container ) {
			return new AIPS_Post_Insights_Repository();
		});

		// Register AIPS_Post_Insights_Controller
		$container->singleton(AIPS_Post_Insights_Controller::class, function( $container ) {
			return new AIPS_Post_Insights_Controller();
		});

		// Repositories used when a post is permanently deleted.
		$container->singleton(AIPS_Internal_Links_Repository::class, function( $container ) {
			return new AIPS_Internal_Links_Repository();
		});

		$container->singleton(AIPS_Author_Topic_Logs_Repository::class, function( $container ) {
			return new AIPS_Author_Topic_Logs_Repository();
		});

		$container->singleton(AIPS_Redirects_Repository::class, function( $container ) {
			return new AIPS_Redirects_Repository();
		});

		$container->singleton(AIPS_Post_Cleanup_Service::class, function( $container ) {
			return new AIPS_Post_Cleanup_Service(
				$container->make(AIPS_Embeddings_Repository::class),
				$container->make(AIPS_Relationships_Repository::class),
				$container->make(AIPS_Internal_Links_Repository::class),
				$container->make(AIPS_Link_Index_Service::class),
				$container->make(AIPS_Link_Click_Tracking_Service::class),
				$container->make(AIPS_History_Repository::class),
				$container->make(AIPS_Redirects_Service::class),
				$container->make(AIPS_Author_Topic_Logs_Repository::class),
				$container->make(AIPS_Logger::class)
			);
		});
	}
}
