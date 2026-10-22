<?php
/**
 * Stress test fixture collection, counting, and cleanup handler.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Stress_Test_Data_Cleaner
 *
 * Encapsulates the discovery, counting, and permanent deletion of test posts and
 * attachments generated during stress test diagnostic runs.
 */
class AIPS_Stress_Test_Data_Cleaner {

	/**
	 * Meta key flagging posts created by stress test runs.
	 */
	const TEST_POST_META = '_aips_stress_test';

	/**
	 * Meta key flagging attachments uploaded by stress test runs.
	 */
	const TEST_ATTACHMENT_META = '_aips_stress_test_attachment';

	/**
	 * Logger instance.
	 *
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Logger_Interface|null $logger Optional logger.
	 */
	public function __construct(?AIPS_Logger_Interface $logger = null) {
		$container    = AIPS_Container::get_instance();
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
	}

	/**
	 * Count posts and attachments created by stress tests.
	 *
	 * @return array{posts: int, attachments: int}
	 */
	public function count_test_data() {
		return array(
			'posts'       => count($this->get_test_post_ids()),
			'attachments' => count($this->get_test_attachment_ids()),
		);
	}

	/**
	 * IDs of posts created by stress tests.
	 *
	 * @return int[]
	 */
	public function get_test_post_ids() {
		return $this->collect_test_ids('any', self::TEST_POST_META);
	}

	/**
	 * IDs of attachments created by stress tests.
	 *
	 * @return int[]
	 */
	public function get_test_attachment_ids() {
		return $this->collect_test_ids('attachment', self::TEST_ATTACHMENT_META);
	}

	/**
	 * Collect all marked object IDs in batches.
	 *
	 * @param string $post_type Post type to query.
	 * @param string $meta_key  Meta key marker.
	 * @return int[]
	 */
	public function collect_test_ids($post_type, $meta_key) {
		$ids        = array();
		$offset     = 0;
		$batch_size = 200;

		while (true) {
			$batch = get_posts(array(
				'post_type'        => $post_type,
				'post_status'      => 'any',
				'numberposts'      => $batch_size,
				'offset'           => $offset,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'meta_key'         => $meta_key,
				'suppress_filters' => false,
			));

			if (empty($batch)) {
				break;
			}

			$ids     = array_merge($ids, $batch);
			$offset += count($batch);
		}

		return $ids;
	}

	/**
	 * Permanently delete all posts and attachments created by stress tests.
	 *
	 * @return array{posts: int, attachments: int}
	 */
	public function cleanup_test_data() {
		$deleted_posts = 0;
		$deleted_media = 0;

		foreach ($this->get_test_post_ids() as $post_id) {
			// Bypass the trash: these are disposable fixtures.
			if (wp_delete_post($post_id, true)) {
				$deleted_posts++;
			}
		}

		foreach ($this->get_test_attachment_ids() as $attachment_id) {
			if (wp_delete_attachment($attachment_id, true)) {
				$deleted_media++;
			}
		}

		$this->logger->log('Stress test data cleaned up', 'info', array(
			'posts'       => $deleted_posts,
			'attachments' => $deleted_media,
		));

		return array('posts' => $deleted_posts, 'attachments' => $deleted_media);
	}
}
