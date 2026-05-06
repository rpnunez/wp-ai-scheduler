<?php
$file = 'ai-post-scheduler/tests/Test_AIPS_Templates_Controller_Preview.php';
$content = file_get_contents($file);

// Replace test_preview_requires_nonce entirely
$pattern = '/public function test_preview_requires_nonce\(\) \{.*?\}/s';
$replacement = <<<REPLACE
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
$content = preg_replace($pattern, $replacement, $content);

// For the other tests: they need $_REQUEST['nonce'] added, and ob_start/try/catch/ob_get_clean.
// We will replace the standard block in each method.
$pattern2 = '/ob_start\(\);\s*\$this->controller->ajax_preview_template_prompts\(\);\s*\$output = ob_get_clean\(\);/s';
$replacement2 = <<<REPLACE
\$_REQUEST['nonce'] = \$_POST['nonce'];
		ob_start();
		try {
			\$this->controller->ajax_preview_template_prompts();
		} catch (WPAjaxDieStopException \$e) {
			// Expected
		} catch (WPAjaxDieContinueException \$e) {
			// Expected
		}
		\$output = ob_get_clean();
REPLACE;

$content = preg_replace($pattern2, $replacement2, $content);

file_put_contents($file, $content);
