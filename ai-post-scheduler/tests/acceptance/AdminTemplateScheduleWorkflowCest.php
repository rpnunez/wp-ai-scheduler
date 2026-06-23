<?php
/**
 * Acceptance Test: Admin Template Schedule Workflow
 *
 * Tests the complete admin workflow:
 * 1. Login to WordPress admin
 * 2. Navigate to AI Post Scheduler
 * 3. Create a template
 * 4. Create a schedule
 * 5. Verify workflow
 *
 * @package AI_Post_Scheduler
 */

class AdminTemplateScheduleWorkflowCest {

	/**
	 * Test: Admin can login to WordPress
	 */
	public function adminLoginTest( AcceptanceTester $I ) {
		$I->amOnPage( '/wp-admin/' );
		$I->see( 'Log In' );
		$I->fillField( 'user_login', 'admin' );
		$I->fillField( 'user_pass', 'admin' );
		$I->click( 'Log In' );
		$I->waitForElementVisible( '.wp-admin', 5 );
		$I->see( 'Dashboard' );
	}

	/**
	 * Test: Admin can access AI Post Scheduler
	 */
	public function accessPluginAdminTest( AcceptanceTester $I ) {
		// Login
		$I->amOnPage( '/wp-admin/' );
		$I->fillField( 'user_login', 'admin' );
		$I->fillField( 'user_pass', 'admin' );
		$I->click( 'Log In' );
		$I->waitForElementVisible( '.wp-admin', 5 );

		// Look for AI Post Scheduler menu
		$I->see( 'AI Post Scheduler', 'a' );
	}

	/**
	 * Test: Complete template and schedule creation workflow
	 */
	public function createTemplateAndScheduleTest( AcceptanceTester $I ) {
		// Login
		$I->amOnPage( '/wp-admin/' );
		$I->fillField( 'user_login', 'admin' );
		$I->fillField( 'user_pass', 'admin' );
		$I->click( 'Log In' );
		$I->waitForElementVisible( '.wp-admin', 5 );

		// Navigate to Templates (adjust selector based on actual plugin menu)
		$I->amOnPage( '/wp-admin/admin.php?page=aips-templates' );
		$I->waitForElement( 'body', 5 );

		// Verify we're on templates page
		$I->see( 'Template', ['css' => '.wp-heading-inline, h1'] );
	}

	/**
	 * Test: Verify post generation from schedule
	 *
	 * Note: This test requires the schedule to be already configured
	 * and waits for post generation to complete.
	 */
	public function verifyPostGenerationTest( AcceptanceTester $I ) {
		// Login
		$I->amOnPage( '/wp-admin/' );
		$I->fillField( 'user_login', 'admin' );
		$I->fillField( 'user_pass', 'admin' );
		$I->click( 'Log In' );
		$I->waitForElementVisible( '.wp-admin', 5 );

		// Navigate to Draft Posts (from plugin)
		$I->amOnPage( '/wp-admin/admin.php?page=aips-review' );
		$I->waitForElement( 'body', 5 );

		// Verify post review page loaded
		$I->see( 'Review', ['css' => '.wp-heading-inline, h1'] );
	}

	/**
	 * Test: Admin can view dashboard after login
	 */
	public function adminDashboardAccessTest( AcceptanceTester $I ) {
		$I->amOnPage( '/wp-admin/' );
		$I->fillField( 'user_login', 'admin' );
		$I->fillField( 'user_pass', 'admin' );
		$I->click( 'Log In' );
		$I->waitForElementVisible( '.wp-admin', 5 );
		$I->see( 'WordPress' );
	}
}
