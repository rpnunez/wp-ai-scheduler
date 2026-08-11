<?php
/**
 * Generation Claims Repository
 *
 * Provides atomic, expiring claims for generation resources (authors, topics)
 * so that two concurrent workers cannot run the same generation flow at once.
 *
 * Claims are acquired with a SINGLE conditional write (INSERT ... ON DUPLICATE
 * KEY UPDATE guarded by expiry), never a read followed by an update, so the
 * database's unique key is the sole arbiter of who wins a contended claim.
 *
 * Every claim carries an expiry so a fatally terminated worker cannot block a
 * resource forever; recover_expired_claims() reaps any stragglers.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generation_Claims_Repository
 */
class AIPS_Generation_Claims_Repository {

	/**
	 * Claim type: recurring/manual topic generation for one author.
	 */
	const TYPE_AUTHOR_TOPIC_GENERATION = 'author_topic_generation';

	/**
	 * Claim type: an author-level "generate N posts" run.
	 */
	const TYPE_AUTHOR_POST_GENERATION = 'author_post_generation';

	/**
	 * Claim type: generating a post from a single approved topic.
	 */
	const TYPE_TOPIC_POST_GENERATION = 'topic_post_generation';

	/**
	 * Default claim lifetime in seconds.
	 *
	 * @var int
	 */
	const DEFAULT_TTL = 600;

	/**
	 * @var string Table name with prefix.
	 */
	private $table_name;

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_generation_claims';
	}

	/**
	 * Resolve the configured claim TTL, filterable per claim type.
	 *
	 * @param string $claim_type Claim type.
	 * @param int    $ttl        Requested TTL, or 0 to use the default.
	 * @return int Positive TTL in seconds.
	 */
	private function resolve_ttl(string $claim_type, int $ttl): int {
		$ttl = $ttl > 0 ? $ttl : self::DEFAULT_TTL;

		/**
		 * Filters the lifetime (in seconds) of a generation claim.
		 *
		 * @since 3.2.0
		 *
		 * @param int    $ttl        Claim lifetime in seconds.
		 * @param string $claim_type Claim type.
		 */
		$ttl = (int) apply_filters('aips_generation_claim_ttl', $ttl, $claim_type);

		return max(1, $ttl);
	}

	/**
	 * Attempt to acquire a claim on a resource using a single atomic write.
	 *
	 * @param string $claim_type     Claim type (one of the TYPE_* constants).
	 * @param int    $resource_id    Resource ID (author ID, topic ID, ...).
	 * @param int    $ttl            Optional lifetime in seconds. 0 = default.
	 * @param string $correlation_id Optional correlation ID for tracing.
	 * @return string|false Claim token on success, false if already claimed.
	 */
	public function claim(string $claim_type, int $resource_id, int $ttl = 0, string $correlation_id = '') {
		if ('' === $claim_type || $resource_id <= 0) {
			return false;
		}

		if (!$this->table_exists()) {
			// Fail open when the claims table is not present (e.g. partial test
			// environments) so generation is never permanently blocked by an
			// absent schema. Contention protection resumes once the table exists.
			return $this->generate_token();
		}

		$ttl        = $this->resolve_ttl($claim_type, $ttl);
		$now        = AIPS_DateTime::now()->timestamp();
		$expires_at = $now + $ttl;
		$token      = $this->generate_token();

		if ('' === $correlation_id) {
			$correlation_id = (string) AIPS_Correlation_ID::get();
		}

		// Single conditional write. The unique key (claim_type, resource_id)
		// makes the INSERT the sole arbiter. When a stale (expired) row exists
		// the ON DUPLICATE branch overwrites it — but ONLY if it has expired,
		// so a live claim is never stolen.
		$sql = $this->wpdb->prepare(
			"INSERT INTO {$this->table_name}
				(claim_type, resource_id, claim_token, correlation_id, claimed_at, expires_at)
			VALUES (%s, %d, %s, %s, %d, %d)
			ON DUPLICATE KEY UPDATE
				claim_token     = IF(expires_at <= %d, VALUES(claim_token), claim_token),
				correlation_id  = IF(expires_at <= %d, VALUES(correlation_id), correlation_id),
				claimed_at      = IF(expires_at <= %d, VALUES(claimed_at), claimed_at),
				expires_at      = IF(expires_at <= %d, VALUES(expires_at), expires_at)",
			$claim_type,
			$resource_id,
			$token,
			$correlation_id,
			$now,
			$expires_at,
			$now,
			$now,
			$now,
			$now
		);

		$suppress = $this->wpdb->suppress_errors(true);
		$this->wpdb->query($sql);
		$affected = (int) $this->wpdb->rows_affected;
		$this->wpdb->suppress_errors($suppress);

		// rows_affected: 1 = fresh insert (acquired); 2 = expired row reclaimed
		// (acquired); 0 = a live claim already exists (denied).
		return ($affected >= 1) ? $token : false;
	}

	/**
	 * Acquire a topic-generation claim for an author.
	 *
	 * @param int    $author_id      Author ID.
	 * @param int    $ttl            Optional TTL in seconds.
	 * @param string $correlation_id Optional correlation ID.
	 * @return string|false Claim token or false.
	 */
	public function claim_author_topic_generation(int $author_id, int $ttl = 0, string $correlation_id = '') {
		return $this->claim(self::TYPE_AUTHOR_TOPIC_GENERATION, $author_id, $ttl, $correlation_id);
	}

	/**
	 * Acquire an author-run post-generation claim.
	 *
	 * @param int    $author_id      Author ID.
	 * @param int    $ttl            Optional TTL in seconds.
	 * @param string $correlation_id Optional correlation ID.
	 * @return string|false Claim token or false.
	 */
	public function claim_author_post_generation(int $author_id, int $ttl = 0, string $correlation_id = '') {
		return $this->claim(self::TYPE_AUTHOR_POST_GENERATION, $author_id, $ttl, $correlation_id);
	}

	/**
	 * Acquire a per-topic post-generation claim.
	 *
	 * @param int    $topic_id       Topic ID.
	 * @param int    $ttl            Optional TTL in seconds.
	 * @param string $correlation_id Optional correlation ID.
	 * @return string|false Claim token or false.
	 */
	public function claim_topic_post_generation(int $topic_id, int $ttl = 0, string $correlation_id = '') {
		return $this->claim(self::TYPE_TOPIC_POST_GENERATION, $topic_id, $ttl, $correlation_id);
	}

	/**
	 * Extend the expiry of a held claim.
	 *
	 * @param string $claim_type  Claim type.
	 * @param int    $resource_id Resource ID.
	 * @param string $token       Claim token returned by claim().
	 * @param int    $ttl         Optional new lifetime in seconds. 0 = default.
	 * @return bool True if the claim was refreshed.
	 */
	public function refresh_claim(string $claim_type, int $resource_id, string $token, int $ttl = 0): bool {
		if ('' === $claim_type || $resource_id <= 0 || '' === $token || !$this->table_exists()) {
			return false;
		}

		$ttl        = $this->resolve_ttl($claim_type, $ttl);
		$expires_at = AIPS_DateTime::now()->timestamp() + $ttl;

		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table_name}
				SET expires_at = %d
				WHERE claim_type = %s AND resource_id = %d AND claim_token = %s",
				$expires_at,
				$claim_type,
				$resource_id,
				$token
			)
		);

		return $result > 0;
	}

	/**
	 * Release a held claim. Only the token holder can release it.
	 *
	 * @param string $claim_type  Claim type.
	 * @param int    $resource_id Resource ID.
	 * @param string $token       Claim token returned by claim().
	 * @return bool True if a row was released.
	 */
	public function release_claim(string $claim_type, int $resource_id, string $token): bool {
		if ('' === $claim_type || $resource_id <= 0 || '' === $token || !$this->table_exists()) {
			return false;
		}

		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name}
				WHERE claim_type = %s AND resource_id = %d AND claim_token = %s",
				$claim_type,
				$resource_id,
				$token
			)
		);

		return $result > 0;
	}

	/**
	 * Delete all expired claims.
	 *
	 * @param int|null $now Optional reference timestamp; defaults to now.
	 * @return int Number of expired claims removed.
	 */
	public function recover_expired_claims($now = null): int {
		if (!$this->table_exists()) {
			return 0;
		}

		$now = (null === $now) ? AIPS_DateTime::now()->timestamp() : (int) $now;

		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE expires_at <= %d",
				$now
			)
		);

		return (int) $result;
	}

	/**
	 * Check whether a live (unexpired) claim exists for a resource.
	 *
	 * @param string $claim_type  Claim type.
	 * @param int    $resource_id Resource ID.
	 * @return bool
	 */
	public function is_claimed(string $claim_type, int $resource_id): bool {
		if ('' === $claim_type || $resource_id <= 0 || !$this->table_exists()) {
			return false;
		}

		$now = AIPS_DateTime::now()->timestamp();

		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name}
				WHERE claim_type = %s AND resource_id = %d AND expires_at > %d",
				$claim_type,
				$resource_id,
				$now
			)
		);

		return $count > 0;
	}

	/**
	 * Generate a random claim token.
	 *
	 * @return string
	 */
	private function generate_token(): string {
		if (function_exists('wp_generate_uuid4')) {
			return wp_generate_uuid4();
		}

		return md5(uniqid((string) wp_rand(), true));
	}

	/**
	 * Whether the claims table exists. Cached per request.
	 *
	 * @return bool
	 */
	private function table_exists(): bool {
		static $exists = null;
		if (null !== $exists) {
			return $exists;
		}

		$found  = $this->wpdb->get_var(
			$this->wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name)
		);
		$exists = ($found === $this->table_name);

		return $exists;
	}
}
