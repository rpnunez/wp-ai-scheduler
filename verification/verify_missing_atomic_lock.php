<?php
// Verification script for checking missing atomic update method
// Usage: php verification/verify_missing_atomic_lock.php

// Define constants to simulate WP environment partially
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// Simple text-based verification since we can't load WP core
$repo_file = ABSPATH . 'ai-post-scheduler/includes/class-aips-schedule-repository.php';
$scheduler_file = ABSPATH . 'ai-post-scheduler/includes/class-aips-scheduler.php';

$repo_content = file_get_contents($repo_file);
$scheduler_content = file_get_contents($scheduler_file);

$errors = [];

// Check if update_next_run_atomic exists in repository
if (strpos($repo_content, 'function update_next_run_atomic') !== false) {
    echo "WARNING: update_next_run_atomic already exists in AIPS_Schedule_Repository.\n";
} else {
    echo "CONFIRMED: update_next_run_atomic is MISSING from AIPS_Schedule_Repository.\n";
}

// Check if process_scheduled_posts uses atomic update
// Look for usage of update() call in loop
if (preg_match('/\$this->repository->update\(\$schedule->schedule_id, array\(\s*[\'"]next_run[\'"] => \$new_next_run\s*\)\);/', $scheduler_content)) {
    echo "CONFIRMED: AIPS_Scheduler uses non-atomic update() for locking.\n";
} else {
    echo "WARNING: Could not find the specific non-atomic update call pattern in AIPS_Scheduler.\n";
    // Check if it's already using atomic
    if (strpos($scheduler_content, 'update_next_run_atomic') !== false) {
        echo "It appears AIPS_Scheduler is already using update_next_run_atomic.\n";
    }
}

echo "Verification complete.\n";
