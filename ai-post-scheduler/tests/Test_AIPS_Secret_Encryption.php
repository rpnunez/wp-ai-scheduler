<?php
/**
 * Tests for AIPS_Secret_Encryption.
 *
 * @package AI_Post_Scheduler
 */

/**
 * Class Test_AIPS_Secret_Encryption
 */
class Test_AIPS_Secret_Encryption extends WP_UnitTestCase {

	/**
	 * Test is_encrypted method with various inputs.
	 */
	public function test_is_encrypted(): void {
		$this->assertFalse( AIPS_Secret_Encryption::is_encrypted( '' ) );
		$this->assertFalse( AIPS_Secret_Encryption::is_encrypted( 'plain-secret-key' ) );
		$this->assertFalse( AIPS_Secret_Encryption::is_encrypted( 12345 ) );
		$this->assertFalse( AIPS_Secret_Encryption::is_encrypted( null ) );
		$this->assertFalse( AIPS_Secret_Encryption::is_encrypted( array( 'foo' => 'bar' ) ) );
		$this->assertTrue( AIPS_Secret_Encryption::is_encrypted( 'aipsenc:someencryptedpayload' ) );
	}

	/**
	 * Test encrypt empty string returns empty string.
	 */
	public function test_encrypt_empty_string(): void {
		$this->assertSame( '', AIPS_Secret_Encryption::encrypt( '' ) );
	}

	/**
	 * Test encryption produces properly prefixed ciphertext and is recognized as encrypted.
	 */
	public function test_encrypt_produces_prefixed_string(): void {
		$plain     = 'my-super-secret-api-token-12345';
		$encrypted = AIPS_Secret_Encryption::encrypt( $plain );

		$this->assertNotSame( $plain, $encrypted );
		$this->assertStringStartsWith( AIPS_Secret_Encryption::PREFIX, $encrypted );
		$this->assertTrue( AIPS_Secret_Encryption::is_encrypted( $encrypted ) );
	}

	/**
	 * Test encrypt-decrypt roundtrip preserves exact string.
	 */
	public function test_encrypt_decrypt_roundtrip(): void {
		$test_cases = array(
			'simple'      => 'hello-world',
			'whitespace'  => "  secret with\nnewlines\tand spaces  ",
			'json'        => '{"client_id":"123","client_secret":"abc-def","private_key":"-----BEGIN PRIVATE KEY-----\\nMIIE..."}',
			'unicode'     => '🔐 Clé secrète 日本語 測試 🚀',
			'special_chr' => '`~!@#$%^&*()_+-=[]{}|;:",.<>?/\\',
		);

		foreach ( $test_cases as $label => $plain ) {
			$encrypted = AIPS_Secret_Encryption::encrypt( $plain );
			$this->assertNotSame( $plain, $encrypted, "Ciphertext should not match plain for {$label}" );
			$decrypted = AIPS_Secret_Encryption::decrypt( $encrypted );
			$this->assertSame( $plain, $decrypted, "Decrypted text should match original for {$label}" );
		}
	}

	/**
	 * Test decrypt returns unencrypted plain string unchanged.
	 */
	public function test_decrypt_unencrypted_string_returns_unchanged(): void {
		$plain = 'plain-text-not-encrypted';
		$this->assertSame( $plain, AIPS_Secret_Encryption::decrypt( $plain ) );
		$this->assertSame( '', AIPS_Secret_Encryption::decrypt( '' ) );
	}

	/**
	 * Test decrypt returns empty string on malformed or truncated payload.
	 */
	public function test_decrypt_malformed_payload(): void {
		// Non-base64 payload after prefix.
		$invalid_base64 = AIPS_Secret_Encryption::PREFIX . '!!!not-base-64!!!';
		$this->assertSame( '', AIPS_Secret_Encryption::decrypt( $invalid_base64 ) );

		// Base64 payload shorter than 49 bytes (16 bytes IV + 32 bytes MAC + at least 1 byte ciphertext).
		$too_short = AIPS_Secret_Encryption::PREFIX . base64_encode( 'short_payload' );
		$this->assertSame( '', AIPS_Secret_Encryption::decrypt( $too_short ) );
	}

	/**
	 * Test decrypt returns empty string when ciphertext or MAC has been tampered with.
	 */
	public function test_decrypt_tampered_payload(): void {
		$plain     = 'confidential-data';
		$encrypted = AIPS_Secret_Encryption::encrypt( $plain );

		$raw = base64_decode( substr( $encrypted, strlen( AIPS_Secret_Encryption::PREFIX ) ) );
		$this->assertNotFalse( $raw );

		// Tamper with the MAC portion (bytes 16 to 47).
		$tampered_mac       = $raw;
		$tampered_mac[20]   = chr( ord( $tampered_mac[20] ) ^ 0xFF );
		$tampered_mac_str   = AIPS_Secret_Encryption::PREFIX . base64_encode( $tampered_mac );
		$this->assertSame( '', AIPS_Secret_Encryption::decrypt( $tampered_mac_str ) );

		// Tamper with the ciphertext portion (bytes 48 onwards).
		$tampered_cipher     = $raw;
		$tampered_cipher[50] = chr( ord( $tampered_cipher[50] ) ^ 0xFF );
		$tampered_cipher_str = AIPS_Secret_Encryption::PREFIX . base64_encode( $tampered_cipher );
		$this->assertSame( '', AIPS_Secret_Encryption::decrypt( $tampered_cipher_str ) );
	}
}
