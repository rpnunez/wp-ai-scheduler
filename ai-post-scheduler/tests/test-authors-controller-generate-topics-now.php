<?php
/**
 * Tests for AIPS_Authors_Controller::ajax_generate_topics_now()
 *
 * Verifies that the manual generate-topics AJAX response includes the
 * generated topic count and the deep link to the Author Topics page so the
 * Authors screen can show a success modal instead of reloading immediately.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Authors_Controller_Generate_Topics_Now_Test extends WP_UnitTestCase {

	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Capture JSON output produced by a controller AJAX method.
	 *
	 * @param callable $callable Controller method to invoke.
	 * @return array Decoded response array.
	 */
	private function capture_ajax(callable $callable) {
		ob_start();
		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected after wp_send_json_*.
		}

		return json_decode(ob_get_clean(), true);
	}

	/**
	 * Replace a private property on the controller with a test double.
	 *
	 * @param object $object        Object instance.
	 * @param string $property_name Property name.
	 * @param mixed  $value         Replacement value.
	 */
	private function set_private_property($object, $property_name, $value) {
		$reflection = new ReflectionProperty($object, $property_name);
		$reflection->setAccessible(true);
		$reflection->setValue($object, $value);
	}

	public function test_generate_topics_now_returns_count_and_author_topics_url() {
		wp_set_current_user($this->admin_user_id);

		$controller = new AIPS_Authors_Controller();
		$generated_topics = array(
			(object) array('id' => 11, 'topic_title' => 'Topic One'),
			(object) array('id' => 12, 'topic_title' => 'Topic Two'),
			(object) array('id' => 13, 'topic_title' => 'Topic Three'),
		);
		$notifications = new class() {
			public $calls = array();

			public function author_topics_generated($author_name, $count, $author_id) {
				$this->calls[] = array(
					'author_name' => $author_name,
					'count'       => $count,
					'author_id'   => $author_id,
				);
			}
		};

		$this->set_private_property(
			$controller,
			'topics_scheduler',
			new class($generated_topics) {
				private $result;

				public function __construct($result) {
					$this->result = $result;
				}

				public function generate_now($author_id) {
					return $this->result;
				}
			}
		);
		$this->set_private_property(
			$controller,
			'repository',
			new class() {
				public function get_by_id($author_id) {
					return (object) array(
						'id'   => $author_id,
						'name' => 'Test Author',
					);
				}
			}
		);
		$this->set_private_property($controller, 'notifications', $notifications);

		$_POST = array(
			'nonce'     => wp_create_nonce('aips_ajax_nonce'),
			'author_id' => 42,
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_generate_topics_now'));
		$expected_url = AIPS_Admin_Menu_Helper::get_page_url('author_topics', array('author_id' => 42));

		$this->assertTrue($response['success']);
		$this->assertSame('3 topics generated', $response['data']['message']);
		$this->assertSame(3, $response['data']['topics_count']);
		$this->assertSame(42, $response['data']['author_id']);
		$this->assertSame($expected_url, $response['data']['author_topics_url']);
		$this->assertSame($generated_topics, $response['data']['topics']);
		$this->assertCount(1, $notifications->calls);
		$this->assertSame(
			array(
				'author_name' => 'Test Author',
				'count'       => 3,
				'author_id'   => 42,
			),
			$notifications->calls[0]
		);
	}
}