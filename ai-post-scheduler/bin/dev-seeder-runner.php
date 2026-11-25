<?php
/**
 * Developer Data Seeder Runner for WP AI Scheduler
 *
 * Executed via WP-CLI inside the container:
 * wp eval-file /var/www/html/wp-content/plugins/ai-post-scheduler/bin/dev-seeder-runner.php -- [options]
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

// Ensure required repositories and classes are loaded
if (!class_exists('AIPS_Template_Repository')) {
	require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-repository.php';
}
if (!class_exists('AIPS_History_Record_Kind')) {
	require_once AIPS_PLUGIN_DIR . 'includes/class-aips-history-record-kind.php';
}
if (!class_exists('AIPS_History_Repository')) {
	require_once AIPS_PLUGIN_DIR . 'includes/class-aips-history-repository.php';
}
if (!class_exists('AIPS_Authors_Repository')) {
	require_once AIPS_PLUGIN_DIR . 'includes/class-aips-authors-repository.php';
}
if (!class_exists('AIPS_Author_Topics_Repository')) {
	require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topics-repository.php';
}
if (!class_exists('AIPS_Author_Topic_Logs_Repository')) {
	require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topic-logs-repository.php';
}
if (!class_exists('AIPS_Post_Images_Repository')) {
	require_once AIPS_PLUGIN_DIR . 'includes/class-aips-post-images-repository.php';
}

class AIPS_Dev_Data_Seeder_Runner {

	private $fixtures_dir;
	private $clean_mode = false;
	private $preset     = 'ui-processing';
	private $includes   = array('templates', 'pending', 'published', 'authors');
	private $multiplier = 1;

	public function __construct() {
		$this->fixtures_dir = __DIR__ . '/fixtures/';
	}

	public function run($args = array()) {
		$this->parse_args($args);

		WP_CLI::line('----------------------------------------------------');
		WP_CLI::line('WP AI Scheduler — Dev Data Seeder');
		WP_CLI::line("Preset:    {$this->preset}");
		WP_CLI::line('Clean:     ' . ($this->clean_mode ? 'Yes (purging prior seeded data)' : 'No (appending)'));
		WP_CLI::line('----------------------------------------------------');

		if ($this->clean_mode) {
			$this->clean_existing_data();
		}

		$template_map = array();
		if (in_array('templates', $this->includes, true)) {
			$template_map = $this->seed_templates();
		}

		if (in_array('pending', $this->includes, true)) {
			$this->seed_pending_posts($template_map);
		}

		if (in_array('published', $this->includes, true)) {
			$this->seed_published_posts($template_map);
		}

		if (in_array('authors', $this->includes, true)) {
			$this->seed_authors_and_topics();
		}

		WP_CLI::success('Database seeding completed successfully! No AI API calls were made.');
	}

	private function parse_args($args) {
		foreach ($args as $raw_arg) {
			$arg = ltrim($raw_arg, '-');
			if ($arg === 'clean') {
				$this->clean_mode = true;
			} elseif (strpos($arg, 'preset=') === 0) {
				$this->preset = substr($arg, 7);
			} elseif (strpos($arg, 'include=') === 0) {
				$val = substr($arg, 8);
				$this->includes = array_map('trim', explode(',', $val));
			} elseif (strpos($arg, 'count=') === 0) {
				$this->multiplier = max(1, absint(substr($arg, 6)));
			}
		}

		if ($this->preset === 'stress') {
			$this->multiplier = 4; // 12 x 4 = 48 draft posts + published
		}
	}

	private function clean_existing_data() {
		global $wpdb;
		WP_CLI::line('Cleaning previous dev-seeded data...');

		// 1. Delete seeded posts (drafts and published)
		$post_ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_aips_dev_seed'");
		$deleted_posts = 0;
		if (!empty($post_ids)) {
			foreach ($post_ids as $pid) {
				wp_delete_post($pid, true);
				$deleted_posts++;
			}
		}

		// 2. Delete seeded history entries
		$deleted_history = (int) $wpdb->query("DELETE FROM {$wpdb->prefix}aips_history WHERE creation_method = 'dev_seed'");

		// 3. Delete seeded templates
		$deleted_templates = (int) $wpdb->query("DELETE FROM {$wpdb->prefix}aips_templates WHERE name LIKE '%[Dev Seed]%'");

		// 4. Delete seeded authors & their topics
		$seeded_author_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}aips_authors WHERE description LIKE '%[Dev Seed]%'");
		$deleted_authors = 0;
		$deleted_topics  = 0;
		if (!empty($seeded_author_ids)) {
			$ids_in = implode(',', array_map('absint', $seeded_author_ids));
			$topic_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}aips_author_topics WHERE author_id IN ($ids_in)");
			if (!empty($topic_ids)) {
				(new AIPS_Author_Topic_Logs_Repository())->delete_by_topic_ids($topic_ids);
			}
			$image_repo = new AIPS_Post_Images_Repository();
			foreach ($seeded_author_ids as $seeded_author_id) {
				$image_repo->delete_for_entity('author', $seeded_author_id);
			}
			$deleted_topics  = (int) $wpdb->query("DELETE FROM {$wpdb->prefix}aips_author_topics WHERE author_id IN ($ids_in)");
			$deleted_authors = (int) $wpdb->query("DELETE FROM {$wpdb->prefix}aips_authors WHERE id IN ($ids_in)");
		}

		WP_CLI::line("Purged {$deleted_posts} posts, {$deleted_history} history entries, {$deleted_templates} templates, {$deleted_authors} authors, {$deleted_topics} topics.");
	}

	private function seed_templates() {
		$file = $this->fixtures_dir . 'templates.json';
		if (!file_exists($file)) {
			WP_CLI::warning('templates.json not found, skipping templates.');
			return array();
		}

		$items = json_decode(file_get_contents($file), true);
		if (!is_array($items)) {
			return array();
		}

		$repo = new AIPS_Template_Repository();
		$map  = array();

		foreach ($items as $item) {
			$template_data = array(
				'name'                    => sanitize_text_field($item['name'] . ' [Dev Seed]'),
				'prompt_template'         => wp_kses_post($item['prompt_template']),
				'title_prompt'            => sanitize_text_field($item['title_prompt']),
				'post_quantity'           => 1,
				'generate_featured_image' => 0,
				'featured_image_source'   => 'ai_prompt',
				'post_status'             => sanitize_text_field($item['post_status']),
				'post_type'               => isset($item['post_type']) ? sanitize_key($item['post_type']) : 'post',
				'is_active'               => 1,
			);

			$id = $repo->create($template_data);
			if ($id) {
				$map[$item['id_alias']] = $id;
			}
		}

		WP_CLI::line('Seeded ' . count($map) . ' templates.');
		return $map;
	}

	private function seed_pending_posts($template_map) {
		$file = $this->fixtures_dir . 'pending-posts.json';
		if (!file_exists($file)) {
			WP_CLI::warning('pending-posts.json not found, skipping pending posts.');
			return;
		}

		$items = json_decode(file_get_contents($file), true);
		if (!is_array($items)) {
			return;
		}

		$history_repo = new AIPS_History_Repository();
		$created      = 0;
		$now          = AIPS_DateTime::now()->timestamp();

		for ($i = 0; $i < $this->multiplier; $i++) {
			foreach ($items as $idx => $item) {
				$suffix = ($i > 0) ? ' (Batch #' . ($i + 1) . ')' : '';
				$title  = $item['title'] . $suffix;

				$post_id = wp_insert_post(array(
					'post_title'   => $title,
					'post_content' => $item['content'],
					'post_excerpt' => $item['excerpt'],
					'post_status'  => 'draft',
					'post_type'    => 'post',
					'post_author'  => 1,
				));

				if (!$post_id || is_wp_error($post_id)) {
					continue;
				}

				update_post_meta($post_id, '_aips_dev_seed', 1);
				update_post_meta($post_id, '_aips_generated', 1);

				$template_id = isset($item['template_alias'], $template_map[$item['template_alias']])
					? $template_map[$item['template_alias']]
					: (reset($template_map) ?: null);

				$history_id = $history_repo->create(array(
					'template_id'       => $template_id,
					'creation_method'   => 'dev_seed',
					'record_kind'       => 'generation',
					'status'            => 'completed',
					'generated_title'   => $title,
					'generated_content' => $item['content'],
					'post_id'           => $post_id,
					'post_type'         => 'post',
					'created_at'        => $now - (rand(60, 7200) + ($idx * 300)),
					'completed_at'      => $now - rand(10, 50),
				));
				if ($history_id) {
					$history_repo->update($history_id, array('completed_at' => $now - rand(10, 50)));
				}

				$created++;
			}
		}

		WP_CLI::line("Seeded {$created} Pending Review draft posts (with history containers).");
	}

	private function seed_published_posts($template_map) {
		$file = $this->fixtures_dir . 'generated-posts.json';
		if (!file_exists($file)) {
			return;
		}

		$items = json_decode(file_get_contents($file), true);
		if (!is_array($items)) {
			return;
		}

		$history_repo = new AIPS_History_Repository();
		$created      = 0;
		$now          = AIPS_DateTime::now()->timestamp();

		foreach ($items as $idx => $item) {
			$post_id = wp_insert_post(array(
				'post_title'   => $item['title'],
				'post_content' => $item['content'],
				'post_excerpt' => $item['excerpt'],
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_author'  => 1,
			));

			if (!$post_id || is_wp_error($post_id)) {
				continue;
			}

			update_post_meta($post_id, '_aips_dev_seed', 1);
			update_post_meta($post_id, '_aips_generated', 1);

			$template_id = isset($item['template_alias'], $template_map[$item['template_alias']])
				? $template_map[$item['template_alias']]
				: (reset($template_map) ?: null);

			$history_id = $history_repo->create(array(
				'template_id'       => $template_id,
				'creation_method'   => 'dev_seed',
				'record_kind'       => 'generation',
				'status'            => 'completed',
				'generated_title'   => $item['title'],
				'generated_content' => $item['content'],
				'post_id'           => $post_id,
				'post_type'         => 'post',
				'created_at'        => $now - (86400 + ($idx * 600)),
				'completed_at'      => $now - 86300,
			));
			if ($history_id) {
				$history_repo->update($history_id, array('completed_at' => $now - 86300));
			}

			$created++;
		}

		WP_CLI::line("Seeded {$created} Published Generated posts.");
	}

	private function seed_authors_and_topics() {
		$file = $this->fixtures_dir . 'authors-topics.json';
		if (!file_exists($file)) {
			return;
		}

		$items = json_decode(file_get_contents($file), true);
		if (!is_array($items)) {
			return;
		}

		$author_repo   = new AIPS_Authors_Repository();
		$topic_repo    = new AIPS_Author_Topics_Repository();
		$authors_count = 0;
		$topics_count  = 0;
		$now           = AIPS_DateTime::now()->timestamp();

		foreach ($items as $author_data) {
			$author_id = $author_repo->create(array(
				'name'          => sanitize_text_field($author_data['name']),
				'field_niche'   => sanitize_text_field($author_data['niche']),
				'description'   => sanitize_textarea_field('[Dev Seed] ' . $author_data['niche']),
				'voice_tone'    => sanitize_text_field($author_data['voice_tone']),
				'writing_style' => sanitize_text_field($author_data['writing_style']),
				'is_active'     => 1,
				'topic_generation_is_active' => 0,
				'post_generation_is_active'  => 0,
			));

			if (!$author_id) {
				continue;
			}
			$authors_count++;

			if (!empty($author_data['topics']) && is_array($author_data['topics'])) {
				foreach ($author_data['topics'] as $topic_item) {
					$topic_repo->create(array(
						'author_id'    => $author_id,
						'topic_title'  => sanitize_text_field($topic_item['topic']),
						'status'       => sanitize_text_field($topic_item['status']),
						'generated_at' => $now - rand(300, 3600),
					));
					$topics_count++;
				}
			}
		}

		WP_CLI::line("Seeded {$authors_count} authors and {$topics_count} topics.");
	}
}

// Instantiate and run with command line arguments passed after '--'
$runner = new AIPS_Dev_Data_Seeder_Runner();
$runner->run(isset($args) ? $args : array());
