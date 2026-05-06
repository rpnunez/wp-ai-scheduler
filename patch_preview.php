<?php
$file = 'ai-post-scheduler/tests/Test_AIPS_Templates_Controller_Preview.php';
$content = file_get_contents($file);

// Add $_REQUEST['nonce'] to test_preview_requires_nonce
$content = preg_replace('/(\$_POST\[\'nonce\'\] = \'invalid_nonce\';)/', "$1\n\t\t\$_REQUEST['nonce'] = \$_POST['nonce'];", $content);

// Apply try/catch blocks
$pattern = '/ob_start\(\);\s*\$this->controller->ajax_preview_template_prompts\(\);\s*\$output = ob_get_clean\(\);/';
$replacement = <<<REPLACE
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

$content = preg_replace($pattern, $replacement, $content);

file_put_contents($file, $content);
