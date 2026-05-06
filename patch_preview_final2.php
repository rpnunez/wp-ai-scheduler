<?php
$file = 'ai-post-scheduler/tests/Test_AIPS_Templates_Controller_Preview.php';
$content = file_get_contents($file);

// Fix test_preview_with_voice by mocking the DB get_row to return the voice name
// The AIPS_Voices::get_voice retrieves the voice by ID. Since the mock DB get_row returns a dummy object without "name", we need to mock it.
$pattern = '/\$voice_service->save\(array\(\n\s*\'name\' => \'Test Voice\',\n\s*\'title_prompt\' => \'Use a professional tone\',\n\s*\'content_instructions\' => \'Write in a formal style\',\n\s*\)\);/';
$replacement = <<<REPLACE
\$voice_service->save(array(
			'name' => 'Test Voice',
			'title_prompt' => 'Use a professional tone',
			'content_instructions' => 'Write in a formal style',
		));
		// Mock the DB for get_voice
		\$GLOBALS['wpdb']->get_row_return_val = (object) array(
			'id' => 1,
			'name' => 'Test Voice',
			'title_prompt' => 'Use a professional tone',
			'content_instructions' => 'Write in a formal style'
		);
REPLACE;
$content = preg_replace($pattern, $replacement, $content);

// Also need to clear the mock after the test or at the end
$pattern2 = '/\$voice_service->delete\(\$voice_id\);/';
$replacement2 = <<<REPLACE
\$voice_service->delete(\$voice_id);
		\$GLOBALS['wpdb']->get_row_return_val = null;
REPLACE;
$content = preg_replace($pattern2, $replacement2, $content);

file_put_contents($file, $content);
