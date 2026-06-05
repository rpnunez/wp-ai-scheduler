<?php
/**
 * Tests embedded diagnostics template rendering.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Diagnostics_Embedded_Templates extends WP_UnitTestCase {

	private function render_template($template, array $vars = array()) {
		ob_start();
		extract($vars, EXTR_SKIP);
		include AIPS_PLUGIN_DIR . 'templates/admin/' . $template;
		return ob_get_clean();
	}

	public function test_system_status_template_omits_page_shell_when_embedded() {
		$output = $this->render_template(
			'system-status.php',
			array(
				'embedded'       => true,
				'system_info'    => array(
					'wordpress' => array(
						'version' => array(
							'label'  => 'Version',
							'value'  => '6.9',
							'status' => 'ok',
						),
					),
				),
				'export_formats' => array(),
				'import_formats' => array(),
			)
		);

		$this->assertStringNotContainsString('class="wrap aips-wrap"', $output);
		$this->assertStringNotContainsString('class="aips-page-header"', $output);
		$this->assertStringContainsString('class="aips-status-page"', $output);
	}

	public function test_seeder_template_omits_page_shell_when_embedded() {
		$output = $this->render_template(
			'seeder.php',
			array(
				'embedded' => true,
			)
		);

		$this->assertStringNotContainsString('class="wrap aips-wrap"', $output);
		$this->assertStringNotContainsString('class="aips-page-header"', $output);
		$this->assertStringContainsString('id="aips-seeder-form"', $output);
	}

	public function test_operations_insights_template_omits_page_shell_when_embedded() {
		$output = $this->render_template(
			'operations-insights.php',
			array(
				'embedded'            => true,
				'days'                => 14,
				'telemetry_enabled'   => true,
				'history_trend'       => array(),
				'duration_by_flow'    => array(),
				'retry_counts'        => array(),
				'failure_reasons'     => array(),
				'recommended_actions' => array(),
			)
		);

		$this->assertStringNotContainsString('class="wrap aips-admin-wrap"', $output);
		$this->assertStringNotContainsString('<h1>Operations Insights</h1>', $output);
		$this->assertStringContainsString('Export JSON', $output);
	}

	public function test_telemetry_template_omits_page_shell_when_embedded() {
		$output = $this->render_template(
			'telemetry.php',
			array(
				'embedded'       => true,
				'start_date'     => '2026-01-01',
				'end_date'       => '2026-01-31',
				'per_page'       => 25,
				'filter_options' => array(
					'types'            => array(),
					'event_categories' => array(),
					'request_methods'  => array(),
				),
			)
		);

		$this->assertStringNotContainsString('class="wrap aips-wrap"', $output);
		$this->assertStringNotContainsString('class="aips-page-header"', $output);
		$this->assertStringContainsString('id="aips-telemetry-panel"', $output);
	}

	public function test_dev_tools_template_omits_page_shell_when_embedded() {
		$output = $this->render_template(
			'dev-tools.php',
			array(
				'embedded' => true,
			)
		);

		$this->assertStringNotContainsString('class="wrap aips-wrap"', $output);
		$this->assertStringNotContainsString('class="aips-page-header"', $output);
		$this->assertStringContainsString('id="aips-dev-scaffold-form"', $output);
	}
}
