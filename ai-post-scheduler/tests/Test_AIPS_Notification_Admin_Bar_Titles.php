<?php
/**
 * Focused tests for admin-bar notification titles.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Notification_Admin_Bar_Titles extends WP_UnitTestCase {

	/**
	 * @var AIPS_Notifications
	 */
	private $notifications;

	/**
	 * @var AIPS_Test_Admin_Bar_Title_Repository
	 */
	private $repository;

	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->repository = new AIPS_Test_Admin_Bar_Title_Repository();
		$this->notifications = new AIPS_Notifications( $this->repository );
	}

	public function tearDown(): void {
		$this->repository->reset();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_post_ready_for_review_uses_short_action_title() {
		$post_id = wp_insert_post( array(
			'post_title'  => 'Review Me',
			'post_status' => 'draft',
			'post_type'   => 'post',
		) );

		$this->notifications->post_ready_for_review( array(
			'post_id'       => $post_id,
			'dedupe_key'    => 'admin_bar_title_ready_' . $post_id,
			'dedupe_window' => 0,
		) );

		$notification = $this->repository->get_unread( 1 )[0];
		$this->assertSame( 'Post ready for review', $notification->title );
		$this->assertStringContainsString( 'Review Me', $notification->message );
		$this->assertStringNotContainsString( 'Review Me', $notification->title );

		wp_delete_post( $post_id, true );
	}

	public function test_manual_generation_completed_uses_short_action_title() {
		$post_id = wp_insert_post( array(
			'post_title'  => 'Generated Example',
			'post_status' => 'draft',
			'post_type'   => 'post',
		) );

		$this->notifications->manual_generation_completed( array(
			'post_id'       => $post_id,
			'dedupe_key'    => 'admin_bar_title_manual_' . $post_id,
			'dedupe_window' => 0,
		) );

		$notification = $this->repository->get_unread( 1 )[0];
		$this->assertSame( 'Manual generation completed', $notification->title );
		$this->assertStringContainsString( 'Generated Example', $notification->message );
		$this->assertStringNotContainsString( 'Generated Example', $notification->title );

		wp_delete_post( $post_id, true );
	}

	public function test_partial_generation_completed_uses_short_action_title() {
		$post_id = wp_insert_post( array(
			'post_title'  => 'Partial Example',
			'post_status' => 'draft',
			'post_type'   => 'post',
		) );

		$this->notifications->partial_generation_completed( array(
			'post_id'            => $post_id,
			'missing_components' => array( 'post_content' ),
			'dedupe_key'         => 'admin_bar_title_partial_' . $post_id,
			'dedupe_window'      => 0,
		) );

		$notification = $this->repository->get_unread( 1 )[0];
		$this->assertSame( 'Partial generation completed', $notification->title );
		$this->assertStringContainsString( 'Partial Example', $notification->message );
		$this->assertStringNotContainsString( 'Partial Example', $notification->title );

		wp_delete_post( $post_id, true );
	}
}

class AIPS_Test_Admin_Bar_Title_Repository implements AIPS_Notifications_Repository_Interface {

	/**
	 * @var array<int, object>
	 */
	private $notifications = array();

	/**
	 * @var int
	 */
	private $next_id = 1;

	public function reset() {
		$this->notifications = array();
		$this->next_id       = 1;
	}

	public function create( $type, $message, $url = '' ) {
		return $this->create_notification(
			array(
				'type'    => $type,
				'message' => $message,
				'url'     => $url,
			)
		);
	}

	public function create_notification( array $data ) {
		$defaults = array(
			'type'       => '',
			'title'      => '',
			'message'    => '',
			'url'        => '',
			'level'      => 'info',
			'dedupe_key' => '',
		);

		$data = wp_parse_args( $data, $defaults );
		$id   = $this->next_id++;

		$this->notifications[] = (object) array(
			'id'         => $id,
			'type'       => $data['type'],
			'title'      => $data['title'],
			'message'    => $data['message'],
			'url'        => $data['url'],
			'level'      => $data['level'],
			'dedupe_key' => $data['dedupe_key'],
			'is_read'    => 0,
		);

		return $id;
	}

	public function get_unread( $limit = 20, $user_id = 0 ) {
		return array_slice( array_reverse( $this->notifications ), 0, absint( $limit ) );
	}

	public function count_unread( $user_id = 0 ) {
		return count( $this->notifications );
	}

	public function mark_as_read( $id, $user_id = 0 ) {
		return true;
	}

	public function mark_all_as_read( $user_id = 0 ) {
		return true;
	}

	public function was_recently_sent( $dedupe_key, $window_seconds = 3600 ) {
		return false;
	}

	public function get_type_counts_for_window( $seconds, array $types = array() ) {
		return array();
	}
}
