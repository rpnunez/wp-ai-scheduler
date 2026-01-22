<?php
namespace AIPS\Controllers;

if (!defined('ABSPATH')) {
	exit;
}

class SettingsPage {
	public function render_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/settings.php';
	}
}
