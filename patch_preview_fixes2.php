<?php
$file = 'ai-post-scheduler/tests/Test_AIPS_Templates_Controller_Preview.php';
$content = file_get_contents($file);

// test_preview_with_voice fails because "Test Voice" doesn't match "". Why is voice empty?
// Wait, AIPS_Voices::save creates a voice, but maybe our mock db doesn't implement getting rows correctly for get_voice?
// In bootstrap.php mock DB, $GLOBALS['wpdb']->insert_id increments, but get_row returns object with id = 1.
// Let's modify test_preview_with_voice to just assert the expected behavior considering mock DB limitations, or mock get_row.

// Let's look at test_preview_requires_nonce. It needs an assertion. Let's capture the exception type.
$replace_nonce_test = <<<REPLACE
	public function test_preview_requires_nonce() {
		\$_POST['prompt_template'] = 'Test content prompt';
		\$_POST['nonce'] = 'invalid_nonce';
		\$_REQUEST['nonce'] = \$_POST['nonce'];

		\$thrown = false;
		ob_start();
		try {
			\$this->controller->ajax_preview_template_prompts();
		} catch (WPAjaxDieStopException \$e) {
			\$thrown = true;
		} catch (WPAjaxDieContinueException \$e) {
			\$thrown = true;
		}
		ob_end_clean();
		\$this->assertTrue(\$thrown, 'Exception should be thrown for invalid nonce');
	}
REPLACE;

$content = preg_replace('/public function test_preview_requires_nonce\(\) \{.*?\}/s', $replace_nonce_test, $content);

file_put_contents($file, $content);
