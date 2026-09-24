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
		$this->assertStringContainsString('aria-current="page"', $output);
		$this->assertStringContainsString('aria-hidden="true"', $output);
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
	 * Test card rendering with header, actions, and body callback.
	 */
	public function test_render_card() {
		ob_start();
		AIPS_Admin_UI_Primitives::render_card(array(
			'id'          => 'test-card-id',
			'title'       => 'Test Card Panel',
			'icon'        => 'dashicons-info',
			'description' => 'Card subtitle explanation',
			'actions'     => array(
				array(
					'label'      => 'Edit',
					'aria_label' => 'Edit this card',
					'data_attrs' => array('target' => 'modal'),
				),
			),
		), function() {
			echo '<p class="test-body-content">Inside card body</p>';
		});
		$output = ob_get_clean();

		$this->assertStringContainsString('test-card-id', $output);
		$this->assertStringContainsString('Test Card Panel', $output);
		$this->assertStringContainsString('dashicons-info', $output);
		$this->assertStringContainsString('aria-label="Edit this card"', $output);
		$this->assertStringContainsString('data-target="modal"', $output);
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
		$this->assertStringContainsString('aips-empty-state-icon-dashicon', $output);
		$this->assertStringContainsString('aria-hidden="true"', $output);
	}

	/**
	 * Test error fallback rendering.
	 */
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
		$this->assertStringContainsString('aips-error-fallback', $output);
	}

	/**
	 * Test missing partial safety handling.
	 */
	public function test_missing_partial_safety() {
		ob_start();
		AIPS_Admin_UI_Primitives::include_partial('non-existent-partial.php');
		$output = ob_get_clean();

		$this->assertStringContainsString('aips-error-fallback', $output);
	}

	/**
	 * Test that raw HTML strings passed into card body and footer are sanitized with wp_kses_post.
	 */
	public function test_render_card_sanitizes_raw_html_body_and_footer_strings() {
		ob_start();
		AIPS_Admin_UI_Primitives::render_card(array(
			'id'     => 'test-xss-card',
			'title'  => 'Security Test Card',
			'body'   => '<p>Safe paragraph</p><script>alert("xss-body")</script><img src="x" onerror="alert(1)">',
			'footer' => '<span>Safe Footer</span><script>alert("xss-footer")</script>',
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('<p>Safe paragraph</p>', $output);
		$this->assertStringContainsString('<span>Safe Footer</span>', $output);
		$this->assertStringNotContainsString('<script>alert("xss-body")</script>', $output);
		$this->assertStringNotContainsString('<script>alert("xss-footer")</script>', $output);
		$this->assertStringNotContainsString('onerror=', $output);
	}

	/**
	 * Test that raw HTML string passed into hub shell content is sanitized with wp_kses_post.
	 */
	public function test_render_hub_shell_sanitizes_raw_html_content_string() {
		ob_start();
		AIPS_Admin_UI_Primitives::render_hub_shell(array(
			'content' => '<div class="hub-main-safe">Safe Content</div><script>alert("xss-shell")</script>',
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('<div class="hub-main-safe">Safe Content</div>', $output);
		$this->assertStringNotContainsString('<script>alert("xss-shell")</script>', $output);
	}

	/**
	 * Test breadcrumbs primitive rendering.
	 */
	public function test_render_breadcrumbs() {
		ob_start();
		AIPS_Admin_UI_Primitives::render_breadcrumbs(array(
			array('label' => 'Automations', 'url' => 'admin.php?page=aips-automations'),
			array('label' => 'Schedules', 'url' => 'admin.php?page=aips-automations&tab=schedules'),
			array('label' => 'Edit Rule'),
		));
		$output = ob_get_clean();

		$this->assertStringContainsString('aips-title-breadcrumb-trail', $output);
		$this->assertStringContainsString('aips-title-breadcrumb-link', $output);
		$this->assertStringContainsString('aips-title-breadcrumb-current', $output);
		$this->assertStringContainsString('aips-page-context-separator', $output);
		$this->assertStringContainsString('Automations', $output);
		$this->assertStringContainsString('Schedules', $output);
		$this->assertStringContainsString('Edit Rule', $output);
	}

	/**
	 * Test page header rendering with contextual title, breadcrumbs, and summary chips.
	 */
	public function test_render_page_header_with_context_and_summary_strip() {
		$ctx = AIPS_Admin_Page_Context::resolve('aips-automations', 'schedules', null, array(
			'summary_items' => array(
				array('label' => 'Active Pipelines', 'value' => 7, 'type' => 'success'),
			),
		));

		ob_start();
		AIPS_Admin_UI_Primitives::render_page_header($ctx);
		$output = ob_get_clean();

		$this->assertStringContainsString('aips-title-breadcrumb-trail', $output);
		$this->assertStringContainsString('aips-title-breadcrumb-link', $output);
		$this->assertStringContainsString('aips-title-breadcrumb-current', $output);
		$this->assertStringContainsString('Automations', $output);
		$this->assertStringContainsString('Schedules', $output);
		$this->assertStringContainsString('aips-page-summary-strip', $output);
		$this->assertStringContainsString('aips-summary-chip-success', $output);
		$this->assertStringContainsString('Active Pipelines', $output);
		$this->assertStringContainsString('7', $output);
	}
}
