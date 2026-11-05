<?php
/**
 * Affiliate Links Retroactive Scan Background Process
 *
 * Scans published WordPress posts in batches to discover monetization opportunities
 * based on active affiliate mappings, staging pending suggestions for admin review.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Affiliate_Scan_Process extends AIPS_Managed_Background_Process {

	const KEY = 'affiliate_links_scan';

	/**
	 * @var AIPS_Affiliate_Links_Repository
	 */
	private $repo;

	/**
	 * @var AIPS_Affiliate_Link_Suggestions_Repository
	 */
	private $suggestions_repo;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	public function __construct(
		?AIPS_Background_Process_Repository $repository = null,
		?AIPS_Affiliate_Links_Repository $repo = null,
		?AIPS_Affiliate_Link_Suggestions_Repository $suggestions_repo = null
	) {
		global $wpdb;
		parent::__construct($repository);
		$this->repo             = $repo ?: new AIPS_Affiliate_Links_Repository();
		$this->suggestions_repo = $suggestions_repo ?: new AIPS_Affiliate_Link_Suggestions_Repository();
		$this->wpdb             = $wpdb;
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Retroactive Affiliate Scan', 'ai-post-scheduler');
	}

	public function get_description(): string {
		return __('Scans existing published posts and queues high-relevance affiliate link suggestions for review.', 'ai-post-scheduler');
	}

	public function uses_ai(): bool {
		return false;
	}

	protected function get_batch_size(): int {
		return 15;
	}

	protected function get_delay(): int {
		return 4;
	}

	protected function count_remaining(): int {
		return (int) $this->wpdb->get_var(
			"SELECT COUNT(ID) FROM {$this->wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'"
		);
	}

	protected function process_slice(int $cursor, int $limit, array $options): array {
		$posts = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT ID, post_title, post_content, post_date
				 FROM {$this->wpdb->posts}
				 WHERE ID > %d
				   AND post_status = 'publish'
				   AND post_type = 'post'
				 ORDER BY ID ASC
				 LIMIT %d",
				$cursor,
				$limit
			)
		);

		if (empty($posts)) {
			return array(
				'processed' => 0,
				'failed'    => 0,
				'cursor'    => $cursor,
				'done'      => true,
				'message'   => __('Retroactive scan complete.', 'ai-post-scheduler'),
			);
		}

		$processed   = 0;
		$new_cursor  = $cursor;
		$all_offers  = $this->repo->get_all(true);

		if (empty($all_offers)) {
			return array(
				'processed' => 0,
				'failed'    => 0,
				'cursor'    => $cursor,
				'done'      => true,
				'message'   => __('No enabled affiliate link mappings found to match against.', 'ai-post-scheduler'),
			);
		}

		foreach ($posts as $post) {
			$post_id    = (int) $post->ID;
			$new_cursor = $post_id;
			$content    = (string) $post->post_content;
			$title      = (string) $post->post_title;

			// Get post tags & category
			$tag_terms = get_the_tags($post_id);
			$tag_names = (!empty($tag_terms) && !is_wp_error($tag_terms)) ? wp_list_pluck($tag_terms, 'name') : array();
			$cat_terms = get_the_category($post_id);
			$cat_ids   = (!empty($cat_terms) && !is_wp_error($cat_terms)) ? wp_list_pluck($cat_terms, 'term_id') : array();

			// Match offers
			$matched = $this->repo->find_matching_offers($title . ' ' . wp_strip_all_tags($content), implode(',', $cat_ids), $tag_names);

			foreach ($matched as $offer) {
				// Skip if post already contains this URL
				if (strpos($content, $offer->affiliate_url) !== false || strpos($content, esc_url($offer->affiliate_url)) !== false) {
					continue;
				}

				// Skip if suggestion already staged
				if ($this->suggestions_repo->exists_for_post_and_link($post_id, (int) $offer->id)) {
					continue;
				}

				// Build before and after snippet
				$cta_html = !empty($offer->cta_html)
					? str_replace('{{affiliate_url}}', esc_url($offer->affiliate_url), $offer->cta_html)
					: sprintf('<p>Recommended resource: <a href="%s" rel="nofollow sponsored" target="_blank">%s</a></p>', esc_url($offer->affiliate_url), esc_html($offer->label ?: $offer->tag));

				$before_snippet = '';
				$after_snippet  = $cta_html;

				// Try to find a paragraph to insert after
				if (preg_match('/<p>.*?<\/p>/i', $content, $m)) {
					$before_snippet = $m[0];
					$after_snippet  = $m[0] . "\n" . $cta_html;
				}

				$this->suggestions_repo->insert(array(
					'post_id'           => $post_id,
					'affiliate_link_id' => (int) $offer->id,
					'anchor_text'       => $offer->label ?: $offer->tag,
					'status'            => 'pending',
					'match_type'        => 'cta_block',
					'before_snippet'    => $before_snippet,
					'after_snippet'     => $after_snippet,
				));

				$processed++;
				break; // Max 1 suggestion per post per scan
			}
		}

		$done = count($posts) < $limit;

		return array(
			'processed' => $processed,
			'failed'    => 0,
			'cursor'    => $new_cursor,
			'done'      => $done,
			'message'   => sprintf(__('Scanned %d posts; found %d new suggestions.', 'ai-post-scheduler'), count($posts), $processed),
		);
	}
}
