<?php
/**
 * Content enhancement security boundary tests.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Content_Enhancement_Security extends WP_UnitTestCase {

	private $admin_user_id;
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();
		$this->admin_user_id      = $this->factory->user->create(array('role' => 'administrator'));
		$this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));
		update_option('aips_content_enhancement_provider_domains', array('custom' => array('example.com')), false);
		update_option('aips_content_enhancement_allowed_post_statuses', array('draft', 'future'), false);
		update_option('aips_default_post_status', 'draft', false);
	}

	public function tearDown(): void {
		delete_option('aips_content_enhancements');
		delete_option('aips_content_enhancement_provider_domains');
		$_POST = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	public function test_repository_sanitizes_missing_slug_and_escaped_fields() {
		$repository = new AIPS_Content_Enhancement_Repository();
		$record = $repository->save(array(
			'name' => '<b>Example Tool</b>',
			'provider' => 'custom',
			'endpoint_url' => 'javascript:alert(1)',
			'cta_label' => '<script>Bad</script> CTA',
			'disclosure_text' => '<em>Partner</em> disclosure',
			'is_active' => true,
		));

		$this->assertSame('example-tool', $record['slug']);
		$this->assertSame('Bad CTA', $record['cta_label']);
		$this->assertSame('Partner disclosure', $record['disclosure_text']);
		$this->assertSame('', $record['endpoint_url']);
	}

	public function test_referral_link_builder_blocks_unsafe_and_adds_utms() {
		$builder = new AIPS_Referral_Link_Builder();
		$this->assertSame('', $builder->build(array('referral_url' => 'javascript:alert(1)')));
		$this->assertSame('', $builder->build(array('referral_url' => 'notaurl')));

		$url = $builder->build(array(
			'referral_url' => 'https://example.com/signup?ref=abc',
			'utm_campaign' => 'Partner Launch',
			'utm_source' => 'AI Scheduler',
			'utm_medium' => 'Post CTA',
		));

		$this->assertStringStartsWith('https://example.com/signup?ref=abc', $url);
		$this->assertStringContainsString('utm_campaign=partnerlaunch', $url);
		$this->assertStringContainsString('utm_source=aischeduler', $url);
		$this->assertStringContainsString('utm_medium=postcta', $url);
	}

	public function test_renderer_blocks_domains_and_escapes_cta_disclosure() {
		$renderer = new AIPS_Content_Enhancement_Renderer(new AIPS_Content_Enhancement_Array_Repository(array(
			'slug' => 'safe-tool',
			'name' => 'Safe Tool',
			'provider' => 'custom',
			'type' => 'embed',
			'endpoint_url' => 'https://example.com/embed',
			'referral_url' => 'https://example.com/signup',
			'cta_label' => '<Click>',
			'disclosure_text' => '<Partner>',
			'is_active' => true,
		)));

		$html = $renderer->render_by_slug('safe-tool');
		$this->assertStringContainsString('&lt;Click&gt;', $html);
		$this->assertStringContainsString('&lt;Partner&gt;', $html);
		$this->assertStringContainsString('rel="sponsored nofollow noopener noreferrer"', $html);
		$this->assertStringContainsString('target="_blank"', $html);
		$this->assertStringNotContainsString('<script', $html);

		$blocked = $renderer->render(array(
			'slug' => 'blocked',
			'name' => 'Blocked',
			'provider' => 'custom',
			'type' => 'embed',
			'endpoint_url' => 'https://evil.example.net/embed',
			'is_active' => true,
		));
		$this->assertStringContainsString('provider is blocked', $blocked);
	}

	public function test_inserter_only_replaces_approved_placeholders_and_ignores_raw_ai_markup() {
		$repository = new AIPS_Content_Enhancement_Array_Repository(array('slug' => 'safe-tool', 'is_active' => true));
		$inserter = new AIPS_Content_Enhancement_Inserter($repository);
		$content = '<script>alert(1)</script><iframe src="https://evil.test"></iframe>{{aips_enhancement:safe-tool}}{{aips_enhancement:missing}}';

		$result = $inserter->replace_placeholders($content);
		$this->assertStringContainsString('<script>alert(1)</script><iframe src="https://evil.test"></iframe>', $result);
		$this->assertStringContainsString('[aips_ce_tool slug="safe-tool"]', $result);
		$this->assertStringContainsString('{{aips_enhancement:missing}}', $result);
	}

	public function test_controller_nonce_capability_invalid_payload_and_success() {
		$controller = new AIPS_Content_Enhancements_Controller(new AIPS_Content_Enhancement_Repository());

		wp_set_current_user($this->admin_user_id);
		$_POST = array('nonce' => 'bad', 'name' => 'Tool');
		$response = $this->capture_ajax_response(array($controller, 'ajax_save'));
		$this->assertFalse($response['success']);

		wp_set_current_user($this->subscriber_user_id);
		$_POST = array('nonce' => wp_create_nonce('aips_ajax_nonce'), 'name' => 'Tool');
		$response = $this->capture_ajax_response(array($controller, 'ajax_save'));
		$this->assertFalse($response['success']);

		wp_set_current_user($this->admin_user_id);
		$_POST = array('nonce' => wp_create_nonce('aips_ajax_nonce'), 'name' => '');
		$response = $this->capture_ajax_response(array($controller, 'ajax_save'));
		$this->assertFalse($response['success']);

		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'name' => 'CRUD Tool',
			'provider' => 'custom',
			'type' => 'embed',
			'endpoint_url' => 'https://example.com/embed',
			'referral_url' => 'https://example.com/signup',
			'is_active' => '1',
		);
		$response = $this->capture_ajax_response(array($controller, 'ajax_save'));
		$this->assertTrue($response['success']);
		$this->assertSame('crud-tool', $response['data']['enhancement']['slug']);
	}

	private function capture_ajax_response($callback) {
		ob_start();
		try {
			$callback();
		} catch (WPAjaxDieContinueException $e) {
		} catch (WPAjaxDieStopException $e) {
		}
		$output = ob_get_clean();
		return json_decode($output, true);
	}
}

class AIPS_Content_Enhancement_Array_Repository extends AIPS_Content_Enhancement_Repository {
	private $record;

	public function __construct(array $record) {
		$this->record = $record;
	}

	public function find_by_slug(string $slug): ?array {
		return isset($this->record['slug']) && $this->record['slug'] === $slug ? $this->record : null;
	}
}
