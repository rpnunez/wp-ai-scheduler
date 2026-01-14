<?php
// Verification script for confirming atomic update implementation
// Usage: php verification/verify_atomic_lock_exists.php

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

echo "Running Verification...\n";

// Check if update_next_run_atomic exists in repository
if (strpos($repo_content, 'function update_next_run_atomic') !== false) {
    echo "SUCCESS: update_next_run_atomic found in AIPS_Schedule_Repository.\n";
} else {
    echo "ERROR: update_next_run_atomic is MISSING from AIPS_Schedule_Repository.\n";
    $errors[] = "Missing repository method";
}

// Check if process_scheduled_posts uses atomic update
if (strpos($scheduler_content, '$this->repository->update_next_run_atomic') !== false) {
    echo "SUCCESS: AIPS_Scheduler uses update_next_run_atomic.\n";
} else {
    echo "ERROR: AIPS_Scheduler NOT using update_next_run_atomic.\n";
    $errors[] = "Missing scheduler usage";
}

if (empty($errors)) {
    echo "ALL CHECKS PASSED.\n";
} else {
    echo "VERIFICATION FAILED.\n";
}
