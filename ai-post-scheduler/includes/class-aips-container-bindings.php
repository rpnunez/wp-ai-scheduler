<?php
/**
 * Registers core singleton and interface-alias bindings on the DI container.
 *
 * Extracted from AI_Post_Scheduler::register_container_bindings() so the
 * plugin bootstrap file doesn't carry the binding wiring inline.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Container_Bindings {

    /**
     * Register initial container bindings for core singletons.
     *
     * Phase 1 registration as described in the container architecture plan:
     * Registers the most-duplicated singletons to validate the container works
     * correctly before more complex refactors.
     *
     * @param AIPS_Container $container
     * @return void
     */
    public static function register( AIPS_Container $container ) {
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

        $container->singleton(AIPS_AI_Service::class, function( $container ) {
            return AIPS_AI_Service::instance();
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

        // Register AIPS_Template_Repository
        $container->singleton(AIPS_Template_Repository::class, function( $container ) {
            return AIPS_Template_Repository::instance();
        });
    }
}
