<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Analytics_Service {

    private $history_repo;
    private $template_repo;

    public function __construct() {
        $this->history_repo = new AIPS_History_Repository();
        $this->template_repo = new AIPS_Template_Repository();
    }

    public function get_template_performance() {
        $templates = $this->template_repo->get_all();
        $performance = array();

        foreach ($templates as $template) {
            $stats = $this->history_repo->get_detailed_template_stats($template->id);

            // Only include templates with at least one generation
            if (!$stats || $stats->total == 0) {
                continue;
            }

            $total = (int)$stats->total;
            $completed = (int)$stats->completed;
            $failed = (int)$stats->failed;
            $rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

            $performance[] = array(
                'id' => $template->id,
                'name' => $template->name,
                'total' => $total,
                'completed' => $completed,
                'failed' => $failed,
                'success_rate' => $rate
            );
        }

        // Sort by success rate descending
        usort($performance, function($a, $b) {
            return $b['success_rate'] <=> $a['success_rate'];
        });

        return $performance;
    }

    public function get_suggestions($stats) {
        $suggestions = array();

        if ($stats['failed'] > 0) {
            $suggestions[] = array(
                'type' => 'warning',
                'message' => sprintf(__('You have %d failed generations. Check logs to diagnose.', 'ai-post-scheduler'), $stats['failed']),
                'action' => 'logs'
            );
        }

        // Mock suggestion
        $suggestions[] = array(
            'type' => 'info',
            'message' => __('Trending research suggests posting at 2 PM boosts engagement.', 'ai-post-scheduler'),
            'action' => 'research'
        );

        return $suggestions;
    }
}
