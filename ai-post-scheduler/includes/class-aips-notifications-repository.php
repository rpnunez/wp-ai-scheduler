<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

if (!trait_exists('AIPS_Repository_Tables')) {
	require_once __DIR__ . '/trait-aips-repository-tables.php';
}

/**
 * Class AIPS_Notifications_Repository
 *
 * Handles database operations for system-wide admin notifications.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */
class AIPS_Notifications_Repository implements AIPS_Notifications_Repository_Interface {
	use AIPS_Cacheable_Repository;
	use AIPS_Repository_Tables;

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @var wpdb WordPress database object.
	 */
	private $wpdb;

	/**
	 * @var string Full table name.
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		// table() is the AIPS_Repository_Tables trait method; $this->table is the
		// cached table-name property (PHP keeps method/property names separate).
		$this->table = $this->table('aips_notifications');
	}

	/**
	 * Create a new notification.
	 *
	 * @param string $type    Notification type (e.g. 'author_topics_generated').
	 * @param string $message Human-readable message.
	 * @param string $url     Optional URL for the action link.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function create($type, $message, $url = '') {
		return $this->create_notification(array(
			'type'    => $type,
			'message' => $message,
			'url'     => $url,
		));
	}

	/**
	 * Create a rich notification record.
	 *
	 * @param array $data Notification record fields.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function create_notification(array $data) {
		$defaults = array(
			'type'       => '',
			'title'      => '',
			'message'    => '',
			'url'        => '',
			'level'      => 'info',
			'meta'       => null,
			'dedupe_key' => '',
			'is_read'    => 0,
			'read_at'    => 0,
			'created_at' => AIPS_DateTime::now()->timestamp(),
		);

		$data = wp_parse_args($data, $defaults);

		$meta_json = null;
		if (null !== $data['meta']) {
			$meta_json = is_string($data['meta']) ? $data['meta'] : wp_json_encode($data['meta']);
		}

		$result = $this->wpdb->insert(
			$this->table,
			array(
				'type'       => sanitize_text_field($data['type']),
				'title'      => sanitize_text_field($data['title']),
				'message'    => sanitize_textarea_field($data['message']),
				'url'        => esc_url_raw($data['url']),
				'level'      => sanitize_key($data['level']),
				'meta'       => $meta_json,
				'dedupe_key' => sanitize_text_field($data['dedupe_key']),
				'is_read'    => absint($data['is_read']) ? 1 : 0,
				'read_at'    => !empty($data['read_at']) ? absint($data['read_at']) : 0,
				'created_at' => !empty($data['created_at']) ? absint($data['created_at']) : AIPS_DateTime::now()->timestamp(),
			),
			array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
		);

		if ($result === false) {
			return false;
		}

		$this->invalidate_notifications_cache('notification_created');

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Check whether a dedupe key was sent recently.
	 *
	 * @param string $dedupe_key     Dedupe key.
	 * @param int    $window_seconds Time window in seconds.
	 * @return bool
	 */
	public function was_recently_sent($dedupe_key, $window_seconds = 3600) {
		$dedupe_key = sanitize_text_field($dedupe_key);
		$window_seconds = absint($window_seconds);
		$cutoff_timestamp = AIPS_DateTime::now()->timestamp() - $window_seconds;

		if ('' === $dedupe_key || $window_seconds < 1) {
			return false;
		}

		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE dedupe_key = %s AND created_at >= %d",
				$dedupe_key,
				$cutoff_timestamp
			)
		);

		return ((int) $count) > 0;
	}

	/**
	 * Get all unread notifications.
	 *
	 * @param int $limit Maximum number to return. Default 20.
	 * @return array Array of notification objects.
	 */
	public function get_unread($limit = 20) {
		$limit = absint($limit);
		if ($limit < 1) {
			$limit = 20;
		}

		return $this->cache_read(
			'notifications.get_unread',
			array(
				'limit' => $limit,
			),
			function() use ( $limit ) {
				return $this->wpdb->get_results(
					$this->wpdb->prepare(
						"SELECT id, type, title, message, url, level, is_read, read_at, created_at FROM {$this->table} WHERE is_read = 0 ORDER BY created_at DESC LIMIT %d",
						$limit
					)
				);
			}
		);
	}

	/**
	 * Count unread notifications.
	 *
	 * @return int
	 */
	public function count_unread() {
		return $this->cache_read(
			'notifications.count_unread',
			array(),
			function() {
				return (int) $this->wpdb->get_var(
					"SELECT COUNT(*) FROM {$this->table} WHERE is_read = 0"
				);
			}
		);
	}

	/**
	 * Mark a single notification as read.
	 *
	 * @param int $id Notification ID.
	 * @return bool True on success.
	 */
	public function mark_as_read($id) {
		$result = $this->wpdb->update(
			$this->table,
			array(
				'is_read' => 1,
				'read_at' => AIPS_DateTime::now()->timestamp(),
			),
			array('id' => absint($id)),
			array('%d', '%d'),
			array('%d')
		);

		if ($result !== false) {
			$this->invalidate_notifications_cache('notification_marked_read');
		}

		return $result !== false;
	}

	/**
	 * Mark all notifications as read.
	 *
	 * @return bool True on success.
	 */
	public function mark_all_as_read() {
		$result = $this->wpdb->update(
			$this->table,
			array(
				'is_read' => 1,
				'read_at' => AIPS_DateTime::now()->timestamp(),
			),
			array('is_read' => 0),
			array('%d', '%d'),
			array('%d')
		);

		if ($result !== false) {
			$this->invalidate_notifications_cache('notifications_marked_all_read');
		}

		return $result !== false;
	}

	/**
	 * Delete old read notifications (older than a given number of days).
	 *
	 * @param int $days Number of days. Default 30.
	 * @return int Number of rows deleted.
	 */
	public function cleanup_old($days = 30) {
		$days = absint($days);
		if ($days < 1) {
			$days = 30;
		}
		$cutoff_timestamp = AIPS_DateTime::now()->timestamp() - ($days * DAY_IN_SECONDS);

		$deleted = (int) $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE is_read = 1 AND created_at < %d",
				$cutoff_timestamp
			)
		);

		if ($deleted > 0) {
			$this->invalidate_notifications_cache('notifications_cleaned_up');
		}

		return $deleted;
	}

	/**
	 * Return notification counts grouped by type over a recent time window.
	 *
	 * @param int   $seconds Time window in seconds.
	 * @param array $types   Optional list of type slugs to include.
	 * @return array<string, int>
	 */
	public function get_type_counts_for_window($seconds, array $types = array()) {
		$seconds = absint($seconds);
		$cutoff_timestamp = AIPS_DateTime::now()->timestamp() - $seconds;

		if ($seconds < 1) {
			return array();
		}

		$sql = "SELECT type, COUNT(*) AS count FROM {$this->table} WHERE created_at >= %d";
		$params = array($cutoff_timestamp);

		if (!empty($types)) {
			$types = array_values(array_filter(array_map('sanitize_key', $types)));
			if (!empty($types)) {
				$placeholders = implode(',', array_fill(0, count($types), '%s'));
				$sql .= " AND type IN ({$placeholders})";
				$params = array_merge($params, $types);
			}
		}

		$sql .= ' GROUP BY type';

		$rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
		$counts = array();

		if (empty($rows)) {
			return $counts;
		}

		foreach ($rows as $row) {
			$counts[sanitize_key($row->type)] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Return the repository cache group for notification reads.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_notifications';
	}

	/**
	 * Return the explicit repository cache policies for notification reads.
	 *
	 * The admin bell-badge reads (get_unread, count_unread) run on every admin page
	 * load and are cached under the broad `notifications` tag that every write
	 * invalidates. The was_recently_sent() dedupe gate and the time-window throttle
	 * read (get_type_counts_for_window) are left uncached, since they gate writes
	 * and use rolling "now"-relative windows.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'notifications.get_unread' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'notifications' ),
				'description' => 'Cache unread notification list reads for the admin bell.',
			),
			'notifications.count_unread' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array( 'notifications' ),
				'description' => 'Cache the unread notification count for the admin bell badge.',
			),
		);
	}

	/**
	 * Invalidate notification read caches after a write.
	 *
	 * Bumps the broad `notifications` tag present on every cached read.
	 *
	 * @param string $reason Invalidation reason.
	 * @return void
	 */
	private function invalidate_notifications_cache($reason) {
		$this->invalidate_cache_tags(array( 'notifications' ), (string) $reason);
	}
}
