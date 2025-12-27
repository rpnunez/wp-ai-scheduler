<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Suggestions_Service {

    public function get_suggestions($stats) {
        $suggestions = array();

        // Suggestion 1: Check for high failure rate
        if ($stats['failed'] > 0) {
            $suggestions[] = array(
                'type' => 'warning',
                'message' => sprintf(__('You have %d failed generations. Check logs to diagnose.', 'ai-post-scheduler'), $stats['failed']),
                'action' => 'logs'
            );
        }

        // Suggestion 2: Check success rate
        if ($stats['total'] > 10 && $stats['success_rate'] < 50) {
            $suggestions[] = array(
                'type' => 'error',
                'message' => __('Global success rate is below 50%. Consider reviewing your prompt templates or AI settings.', 'ai-post-scheduler'),
                'action' => 'templates'
            );
        }

        // Suggestion 3: Check for pending/processing bottleneck
        if ($stats['processing'] > 5) {
             $suggestions[] = array(
                'type' => 'info',
                'message' => __('Multiple posts are currently processing. Please wait for them to complete.', 'ai-post-scheduler'),
                'action' => 'metrics'
            );
        }

        // Suggestion 4: Mock Trending Research
        $suggestions[] = array(
            'type' => 'info',
            'message' => __('Trending research suggests posting at 2 PM boosts engagement.', 'ai-post-scheduler'),
            'action' => 'research'
        );

        return $suggestions;
    }
}
