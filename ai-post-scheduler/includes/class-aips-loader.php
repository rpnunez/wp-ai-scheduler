<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Loader
 *
 * Handles the loading of plugin dependencies.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Loader {

    /**
     * Load all plugin dependencies.
     *
     * @return void
     */
    public function load_dependencies() {
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-logger.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-config.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-db-manager.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-upgrades.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-settings.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-voices.php';

        // Repository layer
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-history-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-schedule-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-article-structure-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-prompt-section-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-trending-topics-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-activity-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-authors-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topics-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topic-logs-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-feedback-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-post-review-repository.php';

        // Services
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-embeddings-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-topic-expansion-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-topic-penalty-service.php';

        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-templates.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-templates-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-processor.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-prompt-builder.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-article-structure-manager.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-type-selector.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-structures-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-prompt-sections-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-interval-calculator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-helper.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-resilience-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-ai-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-image-service.php';

        // Generation Context architecture
        require_once AIPS_PLUGIN_DIR . 'includes/interface-aips-generation-context.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-context.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-topic-context.php';

        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-generation-session.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-research-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-post-creator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-generator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-scheduler.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-schedule-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-activity-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-research-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-planner.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-history.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-post-review.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-post-review-notifications.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-system-status.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-dev-tools.php';

        // Data Management Feature
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-export.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-import.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-export-mysql.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-import-mysql.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-export-json.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-import-json.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management.php';
        // Authors Feature
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topics-generator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topics-scheduler.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-post-generator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-authors-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topics-controller.php';

        // Seeder Feature
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-seeder-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-seeder-admin.php';
    }
}
