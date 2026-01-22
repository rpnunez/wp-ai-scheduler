<?php
namespace AIPS\Controllers;

if (!defined('ABSPATH')) {
	exit;
}

class SeederPage {
	public function render_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/seeder.php';
	}
}
