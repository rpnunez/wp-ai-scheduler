<?php
/**
 * Secret Encryption
 *
 * Encrypts secrets stored in wp_options (AES-256-CBC keyed from the site's
 * auth salts) so a database dump alone does not reveal them. If the salts
 * change, stored secrets can no longer be read and must be entered again.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Secret_Encryption
 */
class AIPS_Secret_Encryption {

	/**
	 * Prefix marking an encrypted value.
	 */
	const PREFIX = 'aipsenc:';

	/**
	 * Cipher.
	 */
	const CIPHER = 'aes-256-cbc';

	/**
	 * Whether a stored value is already encrypted.
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	public static function is_encrypted($value): bool {
		return is_string($value) && strpos($value, self::PREFIX) === 0;
	}

	/**
	 * Encrypt a secret. Falls back to the plain value when OpenSSL is missing.
	 *
	 * @param string $plain Secret.
	 * @return string
	 */
	public static function encrypt(string $plain): string {
		if ($plain === '' || !function_exists('openssl_encrypt')) {
			return $plain;
		}

		$iv     = random_bytes(16);
		$cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
		if ($cipher === false) {
			return $plain;
		}

		$mac = hash_hmac('sha256', $iv . $cipher, self::key(), true);

		return self::PREFIX . base64_encode($iv . $mac . $cipher);
	}

	/**
	 * Decrypt a stored value. Plain values are returned unchanged.
	 *
	 * @param string $stored Stored value.
	 * @return string Secret, or '' when it cannot be decrypted.
	 */
	public static function decrypt(string $stored): string {
		if (!self::is_encrypted($stored)) {
			return $stored;
		}

		if (!function_exists('openssl_decrypt')) {
			return '';
		}

		$raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
		if ($raw === false || strlen($raw) < 49) {
			return '';
		}

		$iv     = substr($raw, 0, 16);
		$mac    = substr($raw, 16, 32);
		$cipher = substr($raw, 48);

		if (!hash_equals(hash_hmac('sha256', $iv . $cipher, self::key(), true), $mac)) {
			return '';
		}

		$plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);

		return $plain === false ? '' : $plain;
	}

	/**
	 * 32-byte key derived from the site's auth salts.
	 *
	 * @return string
	 */
	private static function key(): string {
		return hash('sha256', 'aips-secret|' . wp_salt('auth'), true);
	}
}
