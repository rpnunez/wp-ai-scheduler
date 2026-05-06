<?php
$file = 'ai-post-scheduler/tests/Test_Generated_Posts_Controller.php';
$content = file_get_contents($file);

$search = <<<SEARCH
		// Retrieve and verify
		\$history_item = \$this->history_repository->get_by_id(\$history_id);
SEARCH;

$replace = <<<REPLACE
		// Mock DB for get_by_id in limited environment
		\$GLOBALS['wpdb']->get_results_return_val = array(
			(object) array('history_type_id' => AIPS_History_Type::AI_REQUEST),
			(object) array('history_type_id' => AIPS_History_Type::AI_RESPONSE)
		);

		// Retrieve and verify
		\$history_item = \$this->history_repository->get_by_id(\$history_id);
REPLACE;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
