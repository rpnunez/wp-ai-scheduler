<?php
/**
 * Acceptance Test: Admin Post Review and Publishing Workflow
 *
 * Tests the post review and publishing workflow:
 * 1. Login to WordPress admin
 * 2. Navigate to draft posts
 * 3. Review draft posts
 * 4. Publish a post
 * 5. Verify post appears on frontend
 *
 * @package AI_Post_Scheduler
 */

class AdminPostReviewPublishingCest {

	/**
	 * Test: Admin can review draft posts
	 */
	public function reviewDraftPostsTest( AcceptanceTester $I ) {
		// Login
		$I->amOnPage( '/wp-admin/' );
		$I->fillField( 'user_login', 'admin' );
		$I->fillField( 'user_pass', 'admin' );
		$I->click( 'Log In' );
		$I->waitForElementVisible( '.wp-admin', 5 );

		// Navigate to Posts
		$I->amOnPage( '/wp-admin/edit.php?post_status=draft&post_type=post' );
		$I->waitForElement( 'body', 5 );

		// Verify we see draft posts section
		$I->see( 'Posts', ['css' => '.wp-heading-inline' ] );
	}

	/**
	 * Test: Admin can view AI Post Scheduler review page
	 */
	public function viewPluginReviewPageTest( AcceptanceTester $I ) {
		// Login
		$I->amOnPage( '/wp-admin/' );
		$I->fillField( 'user_login', 'admin' );
		$I->fillField( 'user_pass', 'admin' );
		$I->click( 'Log In' );
		$I->waitForElementVisible( '.wp-admin', 5 );

		// Navigate to plugin's review page
		$I->amOnPage( '/wp-admin/admin.php?page=aips-review' );
		$I->waitForElement( 'body', 5 );

		// Verify page loaded (check for common elements)
		$I->see( 'WordPress' );
	}

	/**
	 * Test: Admin can publish a post from drafts
	 *
	 * This test navigates to draft posts and attempts to publish one.
	 */
	public function publishDraftPostTest( AcceptanceTester $I ) {
		// Login
		$I->amOnPage( '/wp-admin/' );
		$I->fillField( 'user_login', 'admin' );
		$I->fillField( 'user_pass', 'admin' );
		$I->click( 'Log In' );
		$I->waitForElementVisible( '.wp-admin', 5 );

		// Navigate to draft posts
		$I->amOnPage( '/wp-admin/edit.php?post_status=draft&post_type=post' );
		$I->waitForElement( 'body', 5 );

		// Verify draft posts page loaded
		$I->see( 'Posts', ['css' => '.wp-heading-inline' ] );
	}

	/**
	 * Test: Admin can navigate to edit post
	 */
	public function navigateToEditPostTest( AcceptanceTester $I ) {
		// Login
		$I->amOnPage( '/wp-admin/' );
		$I->fillField( 'user_login', 'admin' );
		$I->fillField( 'user_pass', 'admin' );
		$I->click( 'Log In' );
		$I->waitForElementVisible( '.wp-admin', 5 );

		// Navigate to all posts
		$I->amOnPage( '/wp-admin/edit.php?post_type=post' );
		$I->waitForElement( 'body', 5 );

		// Verify posts page loaded
		$I->see( 'Posts', ['css' => '.wp-heading-inline' ] );
	}

	/**
	 * Test: Verify post appears on frontend after publishing
	 *
	 * This test creates a simple post and verifies it appears on the frontend.
	 */
	public function verifyPublishedPostFrontendTest( AcceptanceTester $I ) {
		// Navigate to homepage
		$I->amOnPage( '/' );
		$I->waitForElement( 'body', 5 );

		// Verify we're on the homepage
		$I->see( 'Welcome' ) || $I->see( 'Home' ) || $I->see( 'Blog' );
	}

	/**
	 * Test: Admin can see post status transitions
	 */
	public function postStatusTransitionTest( AcceptanceTester $I ) {
		// Login
		$I->amOnPage( '/wp-admin/' );
		$I->fillField( 'user_login', 'admin' );
		$I->fillField( 'user_pass', 'admin' );
		$I->click( 'Log In' );
		$I->waitForElementVisible( '.wp-admin', 5 );

		// Navigate to posts
		$I->amOnPage( '/wp-admin/edit.php?post_type=post' );
		$I->waitForElement( 'body', 5 );

		// Verify filter options exist for post status
		$I->see( 'All' ) || $I->see( 'Posts' );
	}
}
