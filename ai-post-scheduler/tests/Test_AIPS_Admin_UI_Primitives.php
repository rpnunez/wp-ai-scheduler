<?php
/**
 * Unit Tests for AIPS_Admin_UI_Primitives
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_UI_Primitives extends WP_UnitTestCase {

	/**
	 * Test that get_partials_dir returns a valid path string.
	 */
	public function test_get_partials_dir() {
		$dir = AIPS_Admin_UI_Primitives::get_partials_dir();
		$this->assertNotEmpty($dir);
		$this->assertStringContainsString('partials', $dir);
	}

	/**
	 * Test status badge rendering for various badge types.
	 */
	public function test_render_status_badge() {
		// Neutral badge
		$html = AIPS_Admin_UI_Primitives::render_status_badge(array(
			'label' => 'Neutral Label',
			'type'  => 'neutral',
		));
		$this->assertStringContainsString('aips-badge-neutral', $html);
		$this->assertStringContainsString('Neutral Label', $html);

		// Success badge with icon
		$html = AIPS_Admin_UI_Primitives::render_status_badge(array(
			'label' => 'Published',
			'type'  => 'success',
			'icon'  => 'dashicons-yes',
		));
		$this->assertStringContainsString('aips-badge-success', $html);
		$this->assertStringContainsString('dashicons-yes', $html);
		$this->assertStringContainsString('Published', $html);

		// Warning badge
		$html = AIPS_Admin_UI_Primitives::render_status_badge(array(
			'label' => 'Warning State',
			'type'  => 'warning',
		));
		$this->assertStringContainsString('aips-badge-warning', $html);

		// Danger / Error badge
		$html = AIPS_Admin_UI_Primitives::render_status_badge(array(
			'label' => 'Error State',
			'type'  => 'danger',
		));
		$this->assertStringContainsString('aips-badge-danger', $html);
	}

	/**
	 * Test page header rendering with title, icon, and action buttons.
	 */
	public function test_render_page_header() {
		ob_start();
		AIPS_Admin_UI_Primitives::render_page_header(array(
			'title'       => 'Test Header Title',
			'icon'        => 'dashicons-admin-post',
			'description' => 'Test header description subtitle.',
			'actions'     => array(
				array(
					'type'  => 'button',
					'label' => 'Create New',
					'class' => 'aips-btn aips-btn-primary',
					'id'    => 'test-create-btn',
				),
			),
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('Test Header Title', $output);
		$this->assertStringContainsString('dashicons-admin-post', $output);
		$this->assertStringContainsString('Test header description subtitle.', $output);
		$this->assertStringContainsString('Create New', $output);
		$this->assertStringContainsString('test-create-btn', $output);
	}

	/**
	 * Test rail sidebar rendering.
	 */
	public function test_render_rail() {
		ob_start();
		AIPS_Admin_UI_Primitives::render_rail(array(
			'aria_label' => 'Test Navigation',
			'items'      => array(
				array(
					'key'         => 'tab-one',
					'label'       => 'First Tab',
					'icon'        => 'dashicons-schedule',
					'description' => 'Tab description',
					'active'      => true,
					'badge'       => '5',
				),
				array(
					'key'         => 'tab-two',
					'label'       => 'Second Tab',
					'icon'        => 'dashicons-admin-generic',
					'active'      => false,
				),
			),
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('First Tab', $output);
		$this->assertStringContainsString('active', $output);
		$this->assertStringContainsString('Second Tab', $output);
		$this->assertStringContainsString('dashicons-schedule', $output);
		$this->assertStringContainsString('Tab description', $output);
	}

	/**
	 * Test action toolbar rendering.
	 */
	public function test_render_action_toolbar() {
		ob_start();
		AIPS_Admin_UI_Primitives::render_action_toolbar(array(
			'search' => array(
				'id'          => 'custom-search-input',
				'placeholder' => 'Filter items...',
				'value'       => 'query',
			),
			'actions' => array(
				array(
					'type'  => 'button',
					'label' => 'Bulk Action',
					'class' => 'aips-btn aips-btn-secondary',
				),
			),
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('custom-search-input', $output);
		$this->assertStringContainsString('Filter items...', $output);
		$this->assertStringContainsString('Bulk Action', $output);
	}

	/**
	 * Test card rendering with header and body callback.
	 */
	public function test_render_card() {
		ob_start();
		AIPS_Admin_UI_Primitives::render_card(array(
			'id'          => 'test-card-id',
			'title'       => 'Test Card Panel',
			'icon'        => 'dashicons-info',
			'description' => 'Card subtitle explanation',
		), function() {
			echo '<p class="test-body-content">Inside card body</p>';
		});
		$output = ob_get_clean();

		$this->assertStringContainsString('test-card-id', $output);
		$this->assertStringContainsString('Test Card Panel', $output);
		$this->assertStringContainsString('dashicons-info', $output);
		$this->assertStringContainsString('Inside card body', $output);
	}

	/**
	 * Test empty state rendering.
	 */
	public function test_render_empty_state() {
		ob_start();
		AIPS_Admin_UI_Primitives::render_empty_state(array(
			'icon'      => 'dashicons-database',
			'title'     => 'No Records Available',
			'message'   => 'Get started by clicking create below.',
			'cta_label' => 'Add First Record',
			'cta_url'   => 'admin.php?page=aips-studio',
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('No Records Available', $output);
		$this->assertStringContainsString('Get started by clicking create below.', $output);
		$this->assertStringContainsString('Add First Record', $output);
		$this->assertStringContainsString('admin.php?page=aips-studio', $output);
	}

	/**
	 * Test error fallback rendering.
	 */

	/**
	 * Test dense table rendering with progressive disclosure primitives.
	 */
	public function test_render_table() {
		ob_start();
		AIPS_Admin_UI_Primitives::render_table(array(
			'id'              => 'test-data-table',
			'aria_label'      => 'Test Data Table',
			'columns'         => array(
				'title'   => 'Title',
				'status'  => 'Status',
				'actions' => 'Actions',
			),
			'bulk_actions'    => array(
				'delete' => 'Delete Selected',
			),
			'rows'            => array(
				array(
					'id'             => 'item-1',
					'checkbox_value' => '1',
					'cells'          => array(
						'title'  => 'First Item Title',
						'status' => 'Active',
					),
					'actions'        => array(
						'primary'  => array(
							'label' => 'Edit',
							'icon'  => 'dashicons-edit',
							'url'   => '#edit-1',
						),
						'overflow' => array(
							array(
								'label' => 'Preview',
								'icon'  => 'dashicons-visibility',
							),
						),
					),
					'details'        => '<p>Expanded details content for item 1</p>',
				),
			),
			'footer_count'    => '1 item total',
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('test-data-table', $output);
		$this->assertStringContainsString('aips-table-bulk-toolbar', $output);
		$this->assertStringContainsString('aips-select-all-cb', $output);
		$this->assertStringContainsString('aips-row-action-overflow-toggle', $output);
		$this->assertStringContainsString('aips-row-action-menu', $output);
		$this->assertStringContainsString('aips-row-expand-toggle', $output);
		$this->assertStringContainsString('aips-row-details', $output);
		$this->assertStringContainsString('Expanded details content for item 1', $output);
		$this->assertStringContainsString('1 item total', $output);
	}

	public function test_render_error_fallback() {
		ob_start();
		AIPS_Admin_UI_Primitives::render_error_fallback(array(
			'title'       => 'Component Failed',
			'message'     => 'Database query timed out.',
			'retry_url'   => 'admin.php?page=aips-diagnostics',
			'retry_label' => 'Try Again',
			'details'     => 'Exception at line 42',
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('Component Failed', $output);
		$this->assertStringContainsString('Database query timed out.', $output);
		$this->assertStringContainsString('Try Again', $output);
		$this->assertStringContainsString('Exception at line 42', $output);
	}
}
