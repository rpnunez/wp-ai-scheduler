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

    /**
     * Get performance metrics for all templates.
     *
     * Uses aggregated stats to prevent N+1 queries.
     *
     * @return array
     */
    public function get_template_performance() {
        $templates = $this->template_repo->get_all();
        $all_stats = $this->history_repo->get_all_template_stats_aggregated();
        $performance = array();

        foreach ($templates as $template) {
            if (!isset($all_stats[$template->id])) {
                continue;
            }

            $stats = $all_stats[$template->id];

            $performance[] = array(
                'id' => $template->id,
                'name' => $template->name,
                'total' => $stats['total'],
                'completed' => $stats['completed'],
                'failed' => $stats['failed'],
                'processing' => $stats['processing'],
                'success_rate' => $stats['success_rate']
            );
        }

        // Sort by success rate descending
        usort($performance, function($a, $b) {
            return $b['success_rate'] <=> $a['success_rate'];
        });

        return $performance;
    }

    public function get_suggestions($stats) {
        $suggestions_service = new AIPS_Suggestions_Service();
        return $suggestions_service->get_suggestions($stats);
    }
}
