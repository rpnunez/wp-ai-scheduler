<?php
namespace AIPS\Controllers;

if (!defined('ABSPATH')) {
	exit;
}

class AdminRouter {
	public function render_dashboard_page() {
		$controller = new Dashboard();
		$controller->render_page();
	}

	public function render_activity_page() {
		$controller = new Activity(false);
		$controller->render_page();
	}

	public function render_schedule_page() {
		$controller = new Schedule(null, false);
		$controller->render_page();
	}

	public function render_templates_page() {
		$controller = new Templates();
		$controller->render_page();
	}

	public function render_authors_page() {
		$controller = new Authors(false);
		$controller->render_page();
	}

	public function render_voices_page() {
		$controller = new Voices(false);
		$controller->render_page();
	}

	public function render_research_page() {
		$controller = new Research(false);
		$controller->render_page();
	}

	public function render_structures_page() {
		$controller = new Structures(null, false);
		$controller->render_page();
	}

	public function render_prompt_sections_page() {
		$controller = new PromptSections(null, false);
		$controller->render_page();
	}

	public function render_history_page() {
		$controller = new History(false);
		$controller->render_page();
	}

	public function render_settings_page() {
		$controller = new SettingsPage();
		$controller->render_page();
	}

	public function render_seeder_page() {
		$controller = new SeederPage();
		$controller->render_page();
	}

	public function render_status_page() {
		$controller = new SystemStatus();
		$controller->render_page();
	}

	public function render_dev_tools_page() {
		$dev_tools = new \AIPS_Dev_Tools();
		$dev_tools->render_page();
	}
}
