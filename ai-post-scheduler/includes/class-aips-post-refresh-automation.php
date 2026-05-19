<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Automates periodic refresh preparation for existing published content.
 */
class AIPS_Post_Refresh_Automation {

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	public function __construct($logger = null) {
		$this->logger = $logger instanceof AIPS_Logger ? $logger : new AIPS_Logger();
	}

	/**
	 * Run scheduled scan and create review-gated refresh drafts.
	 *
	 * @return int
	 */
	public function process() {
		$config = AIPS_Config::get_instance();
		if (!(bool) $config->get_option('aips_enable_post_refresh_automation')) {
			return 0;
		}

		$max_posts = max(1, absint($config->get_option('aips_post_refresh_max_posts_per_run')));
		$age_days  = max(1, absint($config->get_option('aips_post_refresh_age_days')));

		$query = new WP_Query(array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $max_posts,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'date_query'     => array(
				array(
					'before' => gmdate('Y-m-d H:i:s', time() - (DAY_IN_SECONDS * $age_days)),
				),
			),
			'meta_query'     => array(
				array(
					'key'     => '_aips_refresh_pending_review',
					'compare' => 'NOT EXISTS',
				),
			),
		));

		$created = 0;
		if (!$query->have_posts()) {
			return 0;
		}

		foreach ($query->posts as $post) {
			$draft_id = $this->create_refresh_draft($post, $config);
			if ($draft_id > 0) {
				$created++;
			}
		}

		return $created;
	}

	private function create_refresh_draft($post, $config) {
		$original_content = (string) $post->post_content;
		$updated_content  = $this->build_refreshed_content($original_content, $config);

		$draft_id = wp_insert_post(array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => sprintf(__('Refresh Draft: %s', 'ai-post-scheduler'), $post->post_title),
			'post_content' => $updated_content,
			'post_excerpt' => $post->post_excerpt,
			'post_author'  => (int) $post->post_author,
		));

		if (is_wp_error($draft_id) || !$draft_id) {
			$this->logger->log('Post refresh automation failed to create draft for post ID ' . (int) $post->ID, 'error');
			return 0;
		}

		update_post_meta($draft_id, '_aips_refresh_pending_review', 1);
		update_post_meta($draft_id, '_aips_refresh_source_post_id', (int) $post->ID);
		update_post_meta($post->ID, '_aips_refresh_pending_review', 1);

		$this->logger->log('Post refresh automation created review draft #' . (int) $draft_id . ' for post #' . (int) $post->ID, 'info');

		return (int) $draft_id;
	}

	private function build_refreshed_content($content, $config) {
		$parts = array();

		if ((bool) $config->get_option('aips_post_refresh_reframe_intro_outro')) {
			$parts[] = "<!-- aips-refresh: intro/outro reframe requested -->";
		}

		if ((bool) $config->get_option('aips_post_refresh_update_stats')) {
			$parts[] = "<!-- aips-refresh: update statistics and dated claims requested -->";
		}

		if ((bool) $config->get_option('aips_post_refresh_refresh_links')) {
			$parts[] = "<!-- aips-refresh: validate and refresh outbound/internal links requested -->";
		}

		if ((bool) $config->get_option('aips_post_refresh_insert_faq')) {
			$parts[] = "\n\n<h2>FAQ</h2>\n<ul><li>Pending AI refresh FAQ generation during editorial review.</li></ul>";
		}

		if (empty($parts)) {
			return $content;
		}

		return implode("\n", $parts) . "\n\n" . $content;
	}
}
