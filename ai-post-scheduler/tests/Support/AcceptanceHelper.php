<?php
/**
 * Acceptance Test Helper
 *
 * Provides utility methods for WordPress acceptance testing.
 *
 * @package AI_Post_Scheduler\Tests
 */

namespace Tests\Support;

class AcceptanceHelper extends \Codeception\Module {

	/**
	 * Login to WordPress admin
	 *
	 * @param string $username WordPress username
	 * @param string $password WordPress password
	 */
	public function loginToWordPress( string $username, string $password ): void {
		$I = $this->getModule( 'WebDriver' );
		$I->amOnPage( '/wp-admin/' );
		$I->fillField( 'user_login', $username );
		$I->fillField( 'user_pass', $password );
		$I->click( 'Log In' );
		$I->waitForElementVisible( '.wp-admin', 5 );
	}

	/**
	 * Navigate to admin page by slug
	 *
	 * @param string $page Admin page slug
	 */
	public function navigateToAdminPage( string $page ): void {
		$I = $this->getModule( 'WebDriver' );
		$I->amOnPage( "/wp-admin/admin.php?page=$page" );
		$I->waitForElement( 'body', 5 );
	}

	/**
	 * Check if user is logged in
	 */
	public function isLoggedIn(): bool {
		$I = $this->getModule( 'WebDriver' );
		try {
			$I->seeElement( '.wp-admin' );
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Verify page title contains text
	 *
	 * @param string $text Text to search for
	 */
	public function seePageTitle( string $text ): void {
		$I = $this->getModule( 'WebDriver' );
		$I->see( $text, ['css' => '.wp-heading-inline, h1' ] );
	}

	/**
	 * Wait for AJAX request to complete
	 *
	 * @param int $timeout Timeout in seconds
	 */
	public function waitForAjax( int $timeout = 10 ): void {
		$I = $this->getModule( 'WebDriver' );
		$I->wait( 1 ); // Initial wait for AJAX to start
		$script = 'return jQuery && jQuery.active == 0';
		$I->waitForJS( $script, $timeout );
	}

	/**
	 * Get text from element
	 *
	 * @param string $selector Element selector
	 * @return string Element text
	 */
	public function getElementText( string $selector ): string {
		$I = $this->getModule( 'WebDriver' );
		return $I->grabTextFrom( $selector );
	}

	/**
	 * Click element by text
	 *
	 * @param string $text Text to find and click
	 */
	public function clickByText( string $text ): void {
		$I = $this->getModule( 'WebDriver' );
		$I->click( "//button[contains(., '$text')] | //a[contains(., '$text')]" );
	}
}
