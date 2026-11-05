<?php
/**
 * Affiliate URL Replace Background Process
 *
 * Mass-replaces old or expired affiliate URLs in existing published posts
 * with updated destination URLs in background slices.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Affiliate_Url_Replace_Process extends AIPS_Managed_Background_Process {

	const KEY = 'affiliate_url_replace';

	/**
	 * @var AIPS_Affiliate_Links_Repository
	 */
	private $repo;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Transient options passed during start() before record creation.
	 *
	 * @var array
	 */
	private $current_options = array();

	public function __construct(
		?AIPS_Background_Process_Repository $repository = null,
		?AIPS_Affiliate_Links_Repository $repo = null
	) {
		global $wpdb;
		parent::__construct($repository);
		$this->repo = $repo ?: new AIPS_Affiliate_Links_Repository();
		$this->wpdb = $wpdb;
	}

	public function start(array $options = array()) {
		$this->current_options = $options;
		try {
			return parent::start($options);
		} finally {
			$this->current_options = array();
		}
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Affiliate URL Replacement', 'ai-post-scheduler');
	}

	public function get_description(): string {
		return __('Mass-updates posts containing expired or modified affiliate URLs to the new destination URL.', 'ai-post-scheduler');
	}

	public function uses_ai(): bool {
		return false;
	}

	protected function get_batch_size(): int {
		return 20;
	}

	protected function get_delay(): int {
		return 3;
	}

	protected function count_remaining(): int {
		$options = !empty($this->current_options) ? $this->current_options : $this->repository->get_active_options(self::KEY);
		$old_url = isset($options['old_url']) ? esc_url_raw($options['old_url']) : '';

		if (empty($old_url)) {
			return 0;
		}

		return $this->repo->count_posts_with_url($old_url);
	}

	protected function process_slice(int $cursor, int $limit, array $options): array {
		$old_url = isset($options['old_url']) ? esc_url_raw($options['old_url']) : '';
		$new_url = isset($options['new_url']) ? esc_url_raw($options['new_url']) : '';

		if (empty($old_url) || empty($new_url)) {
			return array(
				'processed' => 0,
				'failed'    => 0,
				'cursor'    => $cursor,
				'done'      => true,
				'message'   => __('Missing old or new URL in process parameters.', 'ai-post-scheduler'),
			);
		}

		// Query batch of posts with ID > cursor containing old_url
		$like = '%' . $this->wpdb->esc_like($old_url) . '%';
		$posts = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT ID, post_content FROM {$this->wpdb->posts}
				 WHERE ID > %d
				   AND post_status IN ('publish', 'draft', 'future')
				   AND post_content LIKE %s
				 ORDER BY ID ASC
				 LIMIT %d",
				$cursor,
				$like,
				$limit
			)
		);

		if (empty($posts)) {
			return array(
				'processed' => 0,
				'failed'    => 0,
				'cursor'    => $cursor,
				'done'      => true,
				'message'   => __('All matching posts have been updated.', 'ai-post-scheduler'),
			);
		}

		$processed = 0;
		$failed    = 0;
		$new_cursor = $cursor;
		$escaped_old = preg_quote($old_url, '/');
		$link_index_table = $this->wpdb->prefix . 'aips_link_index';
		$has_link_index   = ($this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $link_index_table)) === $link_index_table);

		foreach ($posts as $post) {
			$post_id    = (int) $post->ID;
			$new_cursor = $post_id;
			$content    = (string) $post->post_content;

			// Replace <a ... href="old_url" ...> with new_url while keeping other attributes
			$updated_content = preg_replace_callback(
				'/<a\s+([^>]*?)href=(["\'])(' . $escaped_old . ')(["\'])([^>]*?)>/i',
				function($m) use ($new_url) {
					$before = $m[1];
					$quote  = $m[2];
					$after  = $m[5];
					return '<a ' . trim($before . ' ' . $after) . ' href=' . $quote . esc_url($new_url) . $quote . '>';
				},
				$content
			);

			// Also replace markdown links [anchor](old_url) or raw URL instances
			$url_variants = array_unique(array(
				$old_url,
				esc_url($old_url),
				htmlspecialchars($old_url, ENT_QUOTES, 'UTF-8'),
			));
			$updated_content = str_replace($url_variants, $new_url, $updated_content);

			if ($updated_content !== $content) {
				$res = wp_update_post(array(
					'ID'           => $post_id,
					'post_content' => $updated_content,
				));

				if (is_wp_error($res) || ! $res) {
					$failed++;
					continue;
				}

				// Synchronize aips_link_index if table exists
				if ($has_link_index) {
					$this->wpdb->update(
						$link_index_table,
						array(
							'target_url' => $new_url,
							'url_hash'   => md5($new_url),
						),
						array(
							'source_post_id' => $post_id,
							'target_url'     => $old_url,
						),
						array('%s', '%s'),
						array('%d', '%s')
					);
				}

				$processed++;
			}
		}

		$done = count($posts) < $limit;

		return array(
			'processed' => $processed,
			'failed'    => $failed,
			'cursor'    => $new_cursor,
			'done'      => $done,
			'message'   => sprintf(
				__('Updated %d posts in this batch (%s → %s).', 'ai-post-scheduler'),
				$processed,
				$old_url,
				$new_url
			),
		);
	}
}
