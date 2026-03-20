<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Upgrades {

    /**
     * WP-Cron hook names owned by this plugin.
     */
    const CRON_HOOKS = array(
        'aips_generate_scheduled_posts',
        'aips_generate_author_topics',
        'aips_generate_author_posts',
        'aips_scheduled_research',
        'aips_send_review_notifications',
        'aips_cleanup_export_files',
    );

    private $logger;
    
    public function __construct() {
        $this->logger = new AIPS_Logger();
    }
    
    public static function check_and_run() {
        $current_version = get_option('aips_db_version', '0');
        
        if (version_compare($current_version, AIPS_VERSION, '<')) {
            $instance = new self();
            $instance->run_upgrade($current_version);
        }
    }
    
    private function run_upgrade($from_version) {
        // Use dbDelta to update schema - it handles adding new tables and columns automatically
        // This is the WordPress standard approach for database schema updates
        AIPS_DB_Manager::install_tables();

        // Migrate legacy WP-Cron events to Action Scheduler (idempotent, runs once).
        $this->migrate_wp_cron_to_action_scheduler();

        update_option('aips_db_version', AIPS_VERSION);
        $this->logger->log('Database upgraded from version ' . $from_version . ' to ' . AIPS_VERSION, 'info');
    }

    /**
     * Migrate legacy WP-Cron scheduled events for this plugin to Action Scheduler.
     *
     * This method is idempotent: it records a flag option the first time it runs
     * and will not run again. Call it from run_upgrade() so it executes as part
     * of the per-version upgrade routine.
     *
     * @return void
     */
    public function migrate_wp_cron_to_action_scheduler() {
        // Guard: only run when Action Scheduler is available.
        if (!function_exists('as_schedule_single_action') && !function_exists('as_schedule_recurring_action')) {
            $this->logger->log('Action Scheduler not available, skipping WP-Cron migration.', 'info');
            return;
        }

        // Idempotency guard: skip if already migrated.
        if (get_option('aips_migrated_to_action_scheduler')) {
            return;
        }

        $cron = _get_cron_array();
        if (empty($cron) || !is_array($cron)) {
            update_option('aips_migrated_to_action_scheduler', '1');
            return;
        }

        $migrated_hooks = array();

        foreach ($cron as $timestamp => $events) {
            if (!is_array($events)) {
                continue;
            }

            foreach ($events as $hook => $schedules) {
                if (!in_array($hook, self::CRON_HOOKS, true)) {
                    continue;
                }

                if (!is_array($schedules)) {
                    continue;
                }

                foreach ($schedules as $schedule_data) {
                    $args = isset($schedule_data['args']) ? $schedule_data['args'] : array();

                    // Ensure timestamp is in the future; if not, schedule immediately.
                    $int_timestamp = (int) $timestamp;
                    $run_at        = $int_timestamp > time() ? $int_timestamp : time();

                    if (function_exists('as_schedule_single_action')) {
                        as_schedule_single_action($run_at, $hook, $args, 'aips');
                    }
                }

                $migrated_hooks[] = $hook;
            }
        }

        // Remove migrated WP-Cron hooks to avoid double execution.
        foreach (array_unique($migrated_hooks) as $hook) {
            wp_clear_scheduled_hook($hook);
            $this->logger->log('Migrated WP-Cron hook to Action Scheduler: ' . $hook, 'info');
        }

        update_option('aips_migrated_to_action_scheduler', '1');
        $this->logger->log('WP-Cron to Action Scheduler migration complete.', 'info');
    }
}

