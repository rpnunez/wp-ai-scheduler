<?php
// Mock WP Environment for verification (simplified)
class AIPS_History_Repository_Mock {
    public function get_stats() {
        return ['total' => 100, 'completed' => 80, 'failed' => 20];
    }

    public function get_history($args = array()) {
        // Logic copied from modified class for verification
        $stats = false;
        if (empty($args['search']) && empty($args['template_id'])) {
             $stats = $this->get_stats();
        }

        if ($stats && empty($args['status'])) {
             return "Used Cached Total: " . $stats['total'];
        } elseif ($stats && !empty($args['status']) && isset($stats[$args['status']])) {
             return "Used Cached Status: " . $stats[$args['status']];
        } else {
             return "Ran SQL Query";
        }
    }
}

$repo = new AIPS_History_Repository_Mock();
echo "Test 1 (Default): " . $repo->get_history() . "\n";
echo "Test 2 (Status=completed): " . $repo->get_history(['status' => 'completed']) . "\n";
echo "Test 3 (Search=test): " . $repo->get_history(['search' => 'test']) . "\n";
?>
