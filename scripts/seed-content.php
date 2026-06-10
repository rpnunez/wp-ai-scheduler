<?php
/**
 * Profile-Based Content Seeder Runner
 *
 * Usage:
 *   CLI (recommended): wp eval-file scripts/seed-content.php --profile=dev-test
 *   CLI with fresh start: wp eval-file scripts/seed-content.php --profile=devstacktips --fresh
 *   CLI to rollback a profile: wp eval-file scripts/seed-content.php --profile=devstacktips rollback
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	die('Direct access not permitted.');
}

/**
 * Class AIPS_Content_Seeder
 */
class AIPS_Content_Seeder {
	private $profile_name = 'dev-test';
	private $fresh_mode = false;
	private $rollback_mode = false;

	private $created_items = array();
	private $errors = array();
	private $object_data = array();

	/**
	 * Constructor to parse arguments and load profile configuration
	 *
	 * @param array $args Evaluated arguments from WP-CLI context
	 */
	public function __construct($args = array()) {
		$this->parse_arguments($args);
		$this->load_profile();
	}

	/**
	 * Parse arguments from CLI Context
	 */
	private function parse_arguments($args) {
		global $argv;
		$cli_args = array();

		// $args is passed when running via wp eval-file
		if (isset($args) && is_array($args)) {
			$cli_args = $args;
		} elseif (isset($argv) && is_array($argv)) {
			$cli_args = $argv;
		}

		foreach ($cli_args as $arg) {
			if (strpos($arg, '--profile=') === 0) {
				$this->profile_name = sanitize_key(substr($arg, 10));
			} elseif (strpos($arg, 'profile=') === 0) {
				$this->profile_name = sanitize_key(substr($arg, 8));
			} elseif ($arg === '--fresh' || $arg === 'fresh') {
				$this->fresh_mode = true;
			} elseif ($arg === 'rollback') {
				$this->rollback_mode = true;
			}
		}
	}

	/**
	 * Dynamically load profile array
	 */
	private function load_profile() {
		// Clean and restrict profile name to safe alphanumeric/dashes
		$this->profile_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $this->profile_name);
		if (empty($this->profile_name)) {
			$this->profile_name = 'dev-test';
		}

		$profile_file = dirname(__FILE__) . '/profiles/' . $this->profile_name . '.php';

		if (!file_exists($profile_file)) {
			die("Error: Seeding profile file not found at: " . esc_html($profile_file) . "\n");
		}

		$this->object_data = include $profile_file;

		if (!is_array($this->object_data)) {
			die("Error: Profile file " . esc_html($this->profile_name) . " did not return a valid configuration array.\n");
		}
	}

	/**
	 * Run seeder setup or rollback
	 */
	public function run() {
		if ($this->rollback_mode) {
			$this->rollback();
			return;
		}

		if ($this->fresh_mode) {
			echo "<h1>Fresh Start: Rolling back existing profile data first...</h1>\n";
			$this->rollback();
			echo "<hr>\n";
		}

		$profile_title = isset($this->object_data['strategy_profile']) ? $this->object_data['strategy_profile'] : $this->profile_name;
		echo "<h1>Content Setup</h1>\n";
		echo "<p>Applying strategy profile: <code>" . esc_html($profile_title) . "</code></p>\n";

		// Step 1: Create Categories
		$this->create_categories();

		// Step 2: Create Voices
		$this->create_voices();

		// Step 3: Create Article Structures (with sections)
		$this->create_article_structures();

		// Step 4: Configure Plugin Settings (needs category & structure IDs)
		$this->configure_settings();

		// Step 5: Create Authors
		$this->create_authors();

		// Step 6: Create Post Slices
		$this->create_post_slices();

		// Step 7: Create Source Groups and Sources
		$this->create_source_groups();

		// Step 8: Create Campaigns
		$this->create_campaigns();

		// Step 9: Create Templates (campaign-bound)
		$this->create_templates();

		// Step 10: Create Schedules
		$this->create_schedules();

		// Report Results
		$this->print_summary();
	}

	private function get_target_posts_by_period() {
		$config = isset($this->object_data['distribution_config']) ? $this->object_data['distribution_config'] : array();
		return isset($config['target_posts']) ? $config['target_posts'] : array();
	}

	private function get_target_posts_for_period($period) {
		$targets = $this->get_target_posts_by_period();
		return isset($targets[$period]) ? (int) $targets[$period] : 0;
	}

	private function get_primary_distribution_period() {
		$config = isset($this->object_data['distribution_config']) ? $this->object_data['distribution_config'] : array();
		return isset($config['distribution_period']) ? (string) $config['distribution_period'] : 'weekly';
	}

	private function get_primary_target_post_count() {
		return $this->get_target_posts_for_period($this->get_primary_distribution_period());
	}

	private function distribute_target_counts($weights, $target_total) {
		$allocations = array();
		$remainders = array();
		$total_weight = array_sum($weights);
		$assigned = 0;

		foreach ($weights as $key => $weight) {
			if ($total_weight <= 0) {
				$allocations[$key] = 0;
				$remainders[$key] = 0;
				continue;
			}

			$raw_allocation = ($target_total * (float) $weight) / (float) $total_weight;
			$base_allocation = (int) floor($raw_allocation);

			$allocations[$key] = $base_allocation;
			$remainders[$key] = $raw_allocation - $base_allocation;
			$assigned += $base_allocation;
		}

		$remaining = (int) $target_total - $assigned;
		arsort($remainders, SORT_NUMERIC);

		foreach (array_keys($remainders) as $key) {
			if ($remaining <= 0) {
				break;
			}

			$allocations[$key]++;
			$remaining--;
		}

		return $allocations;
	}

	private function get_campaign_post_targets() {
		$config = isset($this->object_data['distribution_config']) ? $this->object_data['distribution_config'] : array();
		$shares = isset($config['campaign_shares']) ? $config['campaign_shares'] : array();

		return $this->distribute_target_counts($shares, $this->get_primary_target_post_count());
	}

	private function get_author_post_targets() {
		$config = isset($this->object_data['distribution_config']) ? $this->object_data['distribution_config'] : array();
		$shares = isset($config['author_shares']) ? $config['author_shares'] : array();

		return $this->distribute_target_counts($shares, $this->get_primary_target_post_count());
	}

	private function format_summary_value($value) {
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		if (is_array($value)) {
			return wp_json_encode($value);
		}

		$display_value = (string) $value;
		return ($display_value === '') ? '(empty)' : $display_value;
	}

	private function values_match($expected, $actual) {
		if (is_scalar($expected) && is_scalar($actual)) {
			return (string) $expected === (string) $actual;
		}
		return maybe_serialize($expected) === maybe_serialize($actual);
	}

	private function report_status($is_ok, $message) {
		echo ($is_ok ? '[OK] ' : '[WARN] ') . $message . "\n";
	}

	private function update_option_and_return($option_name, $value) {
		update_option($option_name, $value);
		return get_option($option_name);
	}

	private function resolve_setting_entry_value($entry, $structures = array(), $categories = array()) {
		if (array_key_exists('value', $entry)) {
			return $entry['value'];
		}

		if (isset($entry['structure_name'])) {
			return isset($structures[$entry['structure_name']]) ? $structures[$entry['structure_name']] : null;
		}

		if (isset($entry['category_name'])) {
			return isset($categories[$entry['category_name']]) ? $categories[$entry['category_name']] : null;
		}

		return null;
	}

	private function get_setting_entry_label($entry) {
		return isset($entry['label']) ? $entry['label'] : $entry['option_name'];
	}

	private function get_flattened_setting_entries() {
		$entries = array();
		if (empty($this->object_data['settings'])) {
			return $entries;
		}

		foreach ($this->object_data['settings'] as $section) {
			if (empty($section['options']) || !is_array($section['options'])) {
				continue;
			}

			foreach ($section['options'] as $entry) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	private function get_resolved_configured_settings($structures = array(), $categories = array()) {
		$configured_settings = array();

		foreach ($this->get_flattened_setting_entries() as $entry) {
			$value = $this->resolve_setting_entry_value($entry, $structures, $categories);
			if ($value === null) {
				continue;
			}

			$configured_settings[$entry['option_name']] = $value;
		}

		return $configured_settings;
	}

	private function configure_settings() {
		echo "<h2>Step 4: Configuring Plugin Settings</h2>\n";
		if (empty($this->object_data['settings'])) {
			echo "[INFO] No plugin settings configured in this profile.\n";
			return;
		}

		$settings = $this->object_data['settings'];
		$structures = $this->get_created_structures_by_name();
		$categories = $this->get_created_categories_by_name();

		foreach ($settings as $section) {
			$title = isset($section['title']) ? $section['title'] : 'Settings';
			echo "<h3>" . esc_html($title) . "</h3>\n";

			if (empty($section['options']) || !is_array($section['options'])) {
				continue;
			}

			foreach ($section['options'] as $entry) {
				$expected_value = $this->resolve_setting_entry_value($entry, $structures, $categories);
				$label = $this->get_setting_entry_label($entry);
				$warning_message = isset($entry['warning_message']) ? $entry['warning_message'] : '';

				if ($expected_value === null) {
					$this->report_status(false, 'Skipped ' . $label . ' because the configured source value could not be resolved');
					continue;
				}

				$current_value = $this->update_option_and_return($entry['option_name'], $expected_value);
				$is_saved = $this->values_match($expected_value, $current_value);

				$this->report_status(
					$is_saved,
					'Set ' . $label . ' = <code>' . esc_html($this->format_summary_value($current_value)) . '</code>'
				);

				if (!$is_saved) {
					$this->report_status(
						false,
						'Expected <code>' . esc_html($this->format_summary_value($expected_value)) . '</code> but found <code>' . esc_html($this->format_summary_value($current_value)) . '</code>'
					);
				}

				if ($warning_message !== '') {
					$this->report_status(false, esc_html($warning_message));
				}
			}
		}

		$this->report_status(true, 'All plugin settings configured for production');
	}

	private function create_categories() {
		echo "<h2>Step 1: Creating Categories</h2>\n";
		if (empty($this->object_data['categories'])) {
			echo "[INFO] No categories to seed.\n";
			return;
		}

		foreach ($this->object_data['categories'] as $cat_data) {
			$existing = term_exists($cat_data['slug'], 'category');
			if (!$existing) {
				$result = wp_insert_term($cat_data['name'], 'category', array(
					'slug' => $cat_data['slug'],
					'description' => $cat_data['description'],
				));

				if (!is_wp_error($result)) {
					$this->created_items['categories'][] = array('id' => $result['term_id'], 'name' => $cat_data['name']);
					echo "[OK] Created category: {$cat_data['name']}\n";
				} else {
					$this->errors[] = "Failed to create category: {$cat_data['name']} - " . $result->get_error_message();
					echo "[ERR] Failed: {$cat_data['name']}\n";
				}
			} else {
				$term_id = is_array($existing) ? (int) $existing['term_id'] : (int) $existing;
				$this->created_items['categories'][] = array('id' => $term_id, 'name' => $cat_data['name']);
				echo "[INFO] Category exists: {$cat_data['name']}\n";
			}
		}
	}

	private function create_voices() {
		echo "<h2>Step 2: Creating Voices</h2>\n";
		if (empty($this->object_data['voices'])) {
			echo "[INFO] No voices to seed.\n";
			return;
		}

		$voices_repo = new AIPS_Voices_Repository();
		$voices = $this->object_data['voices'];
		$existing_voices = array();

		foreach ($voices_repo->get_all() as $existing_voice) {
			if (!empty($existing_voice->name) && !empty($existing_voice->id)) {
				$existing_voices[(string) $existing_voice->name] = (int) $existing_voice->id;
			}
		}

		foreach ($voices as $voice_data) {
			if (isset($existing_voices[$voice_data['name']])) {
				$voice_id = $existing_voices[$voice_data['name']];
				$voices_repo->update($voice_id, $voice_data);
				$this->created_items['voices'][] = array('id' => $voice_id, 'name' => $voice_data['name']);
				echo "[INFO] Voice exists, updated: {$voice_data['name']}\n";
				continue;
			}

			$voice_id = $voices_repo->create($voice_data);
			if ($voice_id) {
				$this->created_items['voices'][] = array('id' => $voice_id, 'name' => $voice_data['name']);
				echo "[OK] Created voice: {$voice_data['name']}\n";
			} else {
				$this->errors[] = "Failed to create voice: {$voice_data['name']}";
				echo "[ERR] Failed: {$voice_data['name']}\n";
			}
		}
	}

	private function create_article_structures() {
		echo "<h2>Step 3: Creating Article Structures</h2>\n";
		if (empty($this->object_data['structures'])) {
			echo "[INFO] No article structures to seed.\n";
			return;
		}

		$structure_repo = new AIPS_Article_Structure_Repository();
		$section_repo = new AIPS_Prompt_Section_Repository();

		if (!empty($this->object_data['sections'])) {
			foreach ($this->object_data['sections'] as $section_data) {
				$existing = $section_repo->get_by_key($section_data['section_key']);
				if (!$existing) {
					$section_id = $section_repo->create($section_data);
					if ($section_id) {
						echo "[OK] Created section: {$section_data['name']}\n";
					}
				}
			}
		}

		$existing_structures = array();
		foreach ($structure_repo->get_all() as $existing_structure) {
			if (!empty($existing_structure->name) && !empty($existing_structure->id)) {
				$existing_structures[(string) $existing_structure->name] = (int) $existing_structure->id;
			}
		}

		foreach ($this->object_data['structures'] as $structure_data) {
			$structure_json = wp_json_encode(array(
				'sections' => $structure_data['sections'],
				'prompt_template' => '',
			));

			$structure_payload = array(
				'name' => $structure_data['name'],
				'description' => $structure_data['description'],
				'structure_data' => $structure_json,
				'is_active' => 1,
			);

			if (isset($existing_structures[$structure_data['name']])) {
				$structure_id = $existing_structures[$structure_data['name']];
				$structure_repo->update($structure_id, $structure_payload);
				$this->created_items['structures'][] = array('id' => $structure_id, 'name' => $structure_data['name']);
				if ($structure_data['name'] === 'Evergreen How-To Guide') {
					update_option('aips_default_article_structure_id', (int) $structure_id);
				}
				echo "[INFO] Structure exists, updated: {$structure_data['name']}\n";
				continue;
			}

			$structure_id = $structure_repo->create($structure_payload);
			if ($structure_id) {
				$this->created_items['structures'][] = array('id' => $structure_id, 'name' => $structure_data['name']);
				if ($structure_data['name'] === 'Evergreen How-To Guide') {
					update_option('aips_default_article_structure_id', (int) $structure_id);
				}
				echo "[OK] Created structure: {$structure_data['name']}\n";
			} else {
				$this->errors[] = "Failed to create structure: {$structure_data['name']}";
				echo "[ERR] Failed: {$structure_data['name']}\n";
			}
		}
	}

	private function create_authors() {
		echo "<h2>Step 5: Creating Authors</h2>\n";
		if (empty($this->object_data['authors'])) {
			echo "[INFO] No authors to seed.\n";
			return;
		}

		$authors_repo = new AIPS_Authors_Repository();
		$structures = $this->get_created_structures_by_name();
		$categories = $this->get_created_categories_by_name();
		$source_groups = $this->get_created_source_groups_by_name();
		$interval_calc = AIPS_Interval_Calculator::instance();
		$now = AIPS_DateTime::now()->timestamp();
		$existing_authors = array();

		foreach ($authors_repo->get_all() as $existing_author) {
			if (!empty($existing_author->name) && !empty($existing_author->id)) {
				$existing_authors[(string) $existing_author->name] = (int) $existing_author->id;
			}
		}

		$authors = $this->object_data['authors'];
		$post_targets = $this->get_author_post_targets();

		foreach ($authors as $author_data) {
			$weekly_target = isset($post_targets[$author_data['name']]) ? (int) $post_targets[$author_data['name']] : 1;
			$scheduled_quantity = max(1, $weekly_target);
			$topic_quantity = max($scheduled_quantity, 5);

			$structure_id = isset($structures[$author_data['structure_name']]) ? $structures[$author_data['structure_name']] : null;
			$post_category = isset($categories[$author_data['category_name']]) ? $categories[$author_data['category_name']] : 0;
			$source_group_ids = array();
			if (isset($author_data['source_group_name'], $source_groups[$author_data['source_group_name']])) {
				$source_group_ids[] = $source_groups[$author_data['source_group_name']];
			}

			$topic_next_run = $interval_calc->calculate_next_run($author_data['topic_generation_frequency'], $now);
			$post_next_run = $interval_calc->calculate_next_run($author_data['post_generation_frequency'], $now);

			$author_payload = array(
				'name' => $author_data['name'],
				'field_niche' => $author_data['field_niche'],
				'keywords' => $author_data['keywords'],
				'description' => $author_data['description'],
				'details' => $author_data['details'],
				'article_structure_id' => $structure_id,
				'voice_tone' => $author_data['voice_tone'],
				'writing_style' => $author_data['writing_style'],
				'target_audience' => $author_data['target_audience'],
				'expertise_level' => $author_data['expertise_level'],
				'content_goals' => $author_data['content_goals'],
				'excluded_topics' => $author_data['excluded_topics'],
				'preferred_content_length' => $author_data['preferred_content_length'],
				'language' => 'en',
				'post_status' => 'draft',
				'post_category' => $post_category,
				'post_author' => (get_current_user_id() > 0) ? get_current_user_id() : (int) AIPS_Config::get_instance()->get_option('aips_default_post_author'),
				'featured_image_source' => 'ai_prompt',
				'topic_generation_frequency' => $author_data['topic_generation_frequency'],
				'topic_generation_quantity' => $topic_quantity,
				'topic_generation_next_run' => $topic_next_run,
				'topic_generation_last_run' => 0,
				'topic_generation_is_active' => 1,
				'post_generation_frequency' => $author_data['post_generation_frequency'],
				'post_generation_next_run' => $post_next_run,
				'post_generation_last_run' => 0,
				'post_generation_is_active' => 1,
				'max_posts_per_topic' => $author_data['max_posts_per_topic'],
				'manual_post_generation_quantity' => $author_data['manual_post_generation_quantity'],
				'scheduled_post_generation_quantity' => $scheduled_quantity,
				'include_sources' => isset($author_data['include_sources']) ? $author_data['include_sources'] : 0,
				'source_group_ids' => wp_json_encode($source_group_ids),
				'is_active' => $author_data['is_active'],
				'created_at' => $now,
				'updated_at' => $now,
			);

			if (isset($existing_authors[$author_data['name']])) {
				$author_id = $existing_authors[$author_data['name']];
				unset($author_payload['created_at']);
				$updated = $authors_repo->update($author_id, $author_payload);

				if ($updated !== false) {
					$this->created_items['authors'][] = array('id' => $author_id, 'name' => $author_data['name']);
					echo "[INFO] Author exists, updated: {$author_data['name']}\n";
					continue;
				}

				$this->errors[] = "Failed to update author: {$author_data['name']}";
				echo "[ERR] Failed to update author: {$author_data['name']}\n";
				continue;
			}

			$author_id = $authors_repo->create($author_payload);

			if ($author_id) {
				$this->created_items['authors'][] = array('id' => $author_id, 'name' => $author_data['name']);
				echo "[OK] Created author: {$author_data['name']}\n";
			} else {
				$this->errors[] = "Failed to create author: {$author_data['name']}";
				echo "[ERR] Failed: {$author_data['name']}\n";
			}
		}
	}

	private function create_post_slices() {
		echo "<h2>Step 6: Creating Post Slices</h2>\n";
		if (empty($this->object_data['post_slices'])) {
			echo "[INFO] No post slices to seed.\n";
			return;
		}

		$slices_repo = new AIPS_Post_Slices_Repository();
		$now = AIPS_DateTime::now()->timestamp();
		global $wpdb;

		foreach ($this->object_data['post_slices'] as $slice_data) {
			$slice_payload = array(
				'name' => $slice_data['name'],
				'description' => $slice_data['description'],
				'sort_order' => $slice_data['sort_order'],
				'is_active' => $slice_data['is_active'],
				'created_at' => $now,
				'updated_at' => $now,
			);

			$existing_id = $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}aips_post_slices WHERE name = %s LIMIT 1",
				$slice_data['name']
			));

			if ($existing_id) {
				unset($slice_payload['created_at']);
				$slices_repo->update($existing_id, $slice_payload);
				$this->created_items['slices'][] = array('id' => $existing_id, 'name' => $slice_data['name']);
				echo "[INFO] Post slice exists, updated: {$slice_data['name']}\n";
				continue;
			}

			$slice_id = $slices_repo->create($slice_payload);

			if ($slice_id) {
				$this->created_items['slices'][] = array('id' => $slice_id, 'name' => $slice_data['name']);
				echo "[OK] Created post slice: {$slice_data['name']}\n";
			} else {
				$this->errors[] = "Failed to create post slice: {$slice_data['name']}";
				echo "[ERR] Failed: {$slice_data['name']}\n";
			}
		}
	}

	private function create_source_groups() {
		echo "<h2>Step 7: Creating Source Groups and Sources</h2>\n";
		if (empty($this->object_data['source_groups']) && empty($this->object_data['sources'])) {
			echo "[INFO] No sources to seed.\n";
			return;
		}

		$sources_repo = new AIPS_Sources_Repository();
		$group_ids = array();

		if (!empty($this->object_data['source_groups'])) {
			foreach ($this->object_data['source_groups'] as $group_data) {
				$existing = term_exists($group_data['slug'], 'aips_source_group');
				if (!$existing) {
					$result = wp_insert_term($group_data['name'], 'aips_source_group', array(
						'slug' => $group_data['slug'],
						'description' => $group_data['description'],
					));

					if (!is_wp_error($result)) {
						$group_ids[$group_data['slug']] = $result['term_id'];
						$this->created_items['source_groups'][] = array('id' => $result['term_id'], 'name' => $group_data['name']);
						echo "[OK] Created source group: {$group_data['name']}\n";
					}
				} else {
					$term_id = is_array($existing) ? (int) $existing['term_id'] : (int) $existing;
					$group_ids[$group_data['slug']] = $term_id;
					$this->created_items['source_groups'][] = array('id' => $term_id, 'name' => $group_data['name']);
					echo "[INFO] Source group exists: {$group_data['name']}\n";
				}
			}
		}

		if (!empty($this->object_data['sources'])) {
			foreach ($this->object_data['sources'] as $source_data) {
				if (!isset($group_ids[$source_data['group_slug']])) {
					continue;
				}

				if ($sources_repo->url_exists($source_data['url'])) {
					echo "  [INFO] Source exists (URL): {$source_data['name']}\n";
					continue;
				}

				$source_id = $sources_repo->create(array(
					'url' => $source_data['url'],
					'label' => $source_data['name'],
					'is_active' => $source_data['is_active'],
				));

				if ($source_id) {
					$sources_repo->set_source_terms($source_id, array((int) $group_ids[$source_data['group_slug']]));
					$this->created_items['sources'][] = array('id' => $source_id, 'name' => $source_data['name']);
					echo "[OK] Created source: {$source_data['name']}\n";
				} else {
					$this->errors[] = "Failed to create source: {$source_data['name']}";
					echo "[ERR] Failed: {$source_data['name']}\n";
				}
			}
		}
	}

	private function create_campaigns() {
		echo "<h2>Step 8: Creating Campaigns</h2>\n";
		if (empty($this->object_data['campaigns'])) {
			echo "[INFO] No campaigns to seed.\n";
			return;
		}

		$campaigns_repo = new AIPS_Campaigns_Repository();
		$existing_campaigns = array();

		foreach ($campaigns_repo->get_campaigns(null, null) as $existing_campaign) {
			if (!empty($existing_campaign->name) && !empty($existing_campaign->id)) {
				$existing_campaigns[(string) $existing_campaign->name] = (int) $existing_campaign->id;
			}
		}

		$campaigns = $this->object_data['campaigns'];
		$post_targets = $this->get_campaign_post_targets();
		$primary_period = $this->get_primary_distribution_period();

		foreach ($campaigns as $campaign_data) {
			$campaign_id = 0;
			$weekly_target = isset($post_targets[$campaign_data['name']]) ? (int) $post_targets[$campaign_data['name']] : 1;

			$campaign_payload = array(
				'name' => $campaign_data['name'],
				'content_goal' => $campaign_data['content_goal'],
				'campaign_mode' => 'template',
				'is_active' => 1,
				'is_archived' => 0,
				'target_posts_per_' . $primary_period => $weekly_target,
			);

			if (isset($existing_campaigns[$campaign_data['name']])) {
				$campaign_id = (int) $existing_campaigns[$campaign_data['name']];
				$updated = $campaigns_repo->update_campaign($campaign_id, $campaign_payload);

				if (is_wp_error($updated)) {
					$this->errors[] = "Failed to update campaign: {$campaign_data['name']}";
					echo "[ERR] Failed to update campaign: {$campaign_data['name']}\n";
					continue;
				}

				$this->created_items['campaigns'][] = array('id' => $campaign_id, 'name' => $campaign_data['name']);
				echo "[INFO] Campaign exists, updated: {$campaign_data['name']}\n";
			} else {
				$campaign_id = $campaigns_repo->create_campaign($campaign_payload);
			}

			if ($campaign_id) {
				if (!isset($existing_campaigns[$campaign_data['name']])) {
					$this->created_items['campaigns'][] = array('id' => $campaign_id, 'name' => $campaign_data['name']);
					echo "[OK] Created campaign: {$campaign_data['name']}\n";
				}

				echo "  Topics to assign:\n";
				$topics_array = explode("\n", $campaign_data['topics']);
				foreach (array_slice($topics_array, 0, 3) as $topic) {
					echo "    - {$topic}\n";
				}
				if (count($topics_array) > 3) {
					echo "    ... and " . (count($topics_array) - 3) . " more\n";
				}
			} else {
				$this->errors[] = "Failed to create campaign: {$campaign_data['name']}";
				echo "[ERR] Failed: {$campaign_data['name']}\n";
			}
		}
	}

	private function create_templates() {
		echo "<h2>Step 9: Creating Templates</h2>\n";
		if (empty($this->object_data['templates'])) {
			echo "[INFO] No templates to seed.\n";
			return;
		}

		$template_repo = new AIPS_Template_Repository();
		$voices = $this->get_created_voices_by_name();
		$structures = $this->get_created_structures_by_name();
		$categories = $this->get_created_categories_by_name();
		$source_groups = $this->get_created_source_groups_by_name();
		$campaigns = $this->get_created_campaigns_by_name();
		$default_post_author = (int) AIPS_Config::get_instance()->get_option('aips_default_post_author');
		$current_user_id = (int) get_current_user_id();
		$post_author = $current_user_id > 0 ? $current_user_id : $default_post_author;
		$existing_templates = array();

		foreach ($template_repo->get_all() as $existing_template) {
			if (!empty($existing_template->name) && !empty($existing_template->id)) {
				$existing_templates[(string) $existing_template->name] = (int) $existing_template->id;
			}
		}

		$templates = $this->object_data['templates'];
		$post_targets = $this->get_campaign_post_targets();
		$primary_period = $this->get_primary_distribution_period();

		foreach ($templates as $template_data) {
			$voice_id = isset($voices[$template_data['voice_name']]) ? $voices[$template_data['voice_name']] : null;
			$structure_id = isset($structures[$template_data['structure_name']]) ? $structures[$template_data['structure_name']] : null;
			$campaign_id = isset($template_data['campaign_name'], $campaigns[$template_data['campaign_name']]) ? $campaigns[$template_data['campaign_name']] : 0;

			if (empty($campaign_id)) {
				$this->errors[] = "Campaign not found for template: {$template_data['name']} ({$template_data['campaign_name']})";
				echo "[ERR] Campaign not found for template: {$template_data['name']}\n";
				continue;
			}

			$category_ids = array();
			if (isset($template_data['categories'])) {
				foreach ($template_data['categories'] as $cat_name) {
					if (isset($categories[$cat_name])) {
						$category_ids[] = $categories[$cat_name];
					}
				}
			}

			$source_group_ids = array();
			if (isset($template_data['source_group']) && isset($source_groups[$template_data['source_group']])) {
				$source_group_ids[] = $source_groups[$template_data['source_group']];
			}

			$weekly_target = isset($post_targets[$template_data['campaign_name']]) ? (int) $post_targets[$template_data['campaign_name']] : 1;
			$post_quantity = max(1, $weekly_target);

			$template_payload = array(
				'name' => $template_data['name'],
				'prompt_template' => $template_data['prompt_template'],
				'title_prompt' => 'Generate a concise, specific, SEO-friendly technical post title for this topic.',
				'voice_id' => $voice_id,
				'article_structure_id' => $structure_id,
				'post_quantity' => $post_quantity,
				'post_status' => 'draft',
				'post_type' => 'post',
				'post_category' => !empty($category_ids) ? $category_ids[0] : 0,
				'post_tags' => isset($template_data['post_tags']) ? $template_data['post_tags'] : '',
				'post_author' => $post_author,
				'generate_featured_image' => isset($template_data['generate_featured_image']) ? $template_data['generate_featured_image'] : 0,
				'featured_image_source' => isset($template_data['featured_image_source']) ? $template_data['featured_image_source'] : 'ai_prompt',
				'image_prompt' => isset($template_data['image_prompt']) ? $template_data['image_prompt'] : '',
				'featured_image_unsplash_keywords' => isset($template_data['unsplash_keywords']) ? $template_data['unsplash_keywords'] : '',
				'include_sources' => isset($template_data['include_sources']) ? (int) $template_data['include_sources'] : 0,
				'source_group_ids' => wp_json_encode($source_group_ids),
				'campaign_id' => $campaign_id,
				'is_active' => 1,
				'target_posts_per_' . $primary_period => $post_quantity,
			);

			if (isset($existing_templates[$template_data['name']])) {
				$template_id = $existing_templates[$template_data['name']];
				$template_repo->update($template_id, $template_payload);
				$this->created_items['templates'][] = array('id' => $template_id, 'name' => $template_data['name']);
				echo "[INFO] Template exists, updated: {$template_data['name']}\n";
				continue;
			}

			$template_id = $template_repo->create($template_payload);
			if ($template_id) {
				$this->created_items['templates'][] = array('id' => $template_id, 'name' => $template_data['name']);
				echo "[OK] Created template: {$template_data['name']}\n";
			} else {
				$this->errors[] = "Failed to create template: {$template_data['name']}";
				echo "[ERR] Failed: {$template_data['name']}\n";
			}
		}
	}

	private function create_schedules() {
		echo "<h2>Step 10: Creating Schedules</h2>\n";
		if (empty($this->object_data['schedules'])) {
			echo "[INFO] No schedules to seed.\n";
			return;
		}

		$schedule_repo = new AIPS_Schedule_Repository();
		$templates = $this->get_created_templates_by_name();
		$primary_period = $this->get_primary_distribution_period();
		global $wpdb;

		foreach ($this->object_data['schedules'] as $schedule_data) {
			if (!isset($templates[$schedule_data['template_name']])) {
				$this->errors[] = "Template not found for schedule: {$schedule_data['title']}";
				echo "[ERR] Template not found: {$schedule_data['template_name']}\n";
				continue;
			}

			$template_id = $templates[$schedule_data['template_name']];
			$next_run = $this->get_next_weekday_timestamp(
				isset($schedule_data['weekday']) ? (int) $schedule_data['weekday'] : 1,
				$schedule_data['start_time']
			);

			// Resolve template post quantity target
			$post_qty = 1;
			if (isset($this->object_data['templates'])) {
				foreach ($this->object_data['templates'] as $tpl) {
					if ($tpl['name'] === $schedule_data['template_name']) {
						$post_qty = isset($tpl['post_quantity']) ? (int) $tpl['post_quantity'] : 1;
						break;
					}
				}
			}

			$schedule_payload = array(
				'template_id' => $template_id,
				'title' => $schedule_data['title'],
				'frequency' => $schedule_data['frequency'],
				'next_run' => $next_run,
				'last_run' => 0,
				'is_active' => $schedule_data['is_active'],
				'status' => 'active',
				'schedule_type' => 'post_generation',
				'target_posts_per_' . $primary_period => $post_qty,
			);

			$existing_id = $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}aips_schedule WHERE title = %s LIMIT 1",
				$schedule_data['title']
			));

			if ($existing_id) {
				$schedule_repo->update($existing_id, $schedule_payload);
				$this->created_items['schedules'][] = array('id' => $existing_id, 'title' => $schedule_data['title']);
				$next_run_formatted = AIPS_DateTime::fromTimestamp($next_run)->format('Y-m-d H:i:s');
				echo "[INFO] Schedule exists, updated: {$schedule_data['title']} (next run: {$next_run_formatted})\n";
				continue;
			}

			$schedule_id = $schedule_repo->create($schedule_payload);

			if ($schedule_id) {
				$this->created_items['schedules'][] = array('id' => $schedule_id, 'title' => $schedule_data['title']);
				$next_run_formatted = AIPS_DateTime::fromTimestamp($next_run)->format('Y-m-d H:i:s');
				echo "[OK] Created schedule: {$schedule_data['title']} (next run: {$next_run_formatted})\n";
			} else {
				$this->errors[] = "Failed to create schedule: {$schedule_data['title']}";
				echo "[ERR] Failed: {$schedule_data['title']}\n";
			}
		}
	}

	private function get_next_weekday_timestamp($iso_weekday, $time_hhmm) {
		$iso_weekday = max(1, min(7, (int) $iso_weekday));
		$time_hhmm = preg_match('/^\d{2}:\d{2}$/', (string) $time_hhmm) ? (string) $time_hhmm : '09:00';

		$site_tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
		$now = new DateTimeImmutable('now', $site_tz);

		$days_ahead = $iso_weekday - (int) $now->format('N');
		if ($days_ahead < 0) {
			$days_ahead += 7;
		}

		$candidate = $now->modify('+' . $days_ahead . ' days')->setTime((int) substr($time_hhmm, 0, 2), (int) substr($time_hhmm, 3, 2));

		if ($candidate->getTimestamp() <= $now->getTimestamp()) {
			$candidate = $candidate->modify('+7 days');
		}

		return $candidate->getTimestamp();
	}

	private function get_created_categories_by_name() {
		$map = array();
		if (isset($this->created_items['categories'])) {
			foreach ($this->created_items['categories'] as $category) {
				$map[$category['name']] = $category['id'];
			}
		}
		return $map;
	}

	private function get_created_source_groups_by_name() {
		$map = array();
		if (isset($this->created_items['source_groups'])) {
			foreach ($this->created_items['source_groups'] as $group) {
				$map[$group['name']] = $group['id'];
			}
		}
		return $map;
	}

	private function get_created_templates_by_name() {
		$map = array();
		if (isset($this->created_items['templates'])) {
			foreach ($this->created_items['templates'] as $template) {
				$map[$template['name']] = $template['id'];
			}
		}
		return $map;
	}

	private function get_created_voices_by_name() {
		$map = array();
		if (isset($this->created_items['voices'])) {
			foreach ($this->created_items['voices'] as $voice) {
				$map[$voice['name']] = $voice['id'];
			}
		}
		return $map;
	}

	private function get_created_structures_by_name() {
		$map = array();
		if (isset($this->created_items['structures'])) {
			foreach ($this->created_items['structures'] as $structure) {
				$map[$structure['name']] = $structure['id'];
			}
		}
		return $map;
	}

	private function get_created_campaigns_by_name() {
		$map = array();
		if (isset($this->created_items['campaigns'])) {
			foreach ($this->created_items['campaigns'] as $campaign) {
				$map[$campaign['name']] = $campaign['id'];
			}
		}
		return $map;
	}

	private function get_configured_setting_keys() {
		$keys = array();

		foreach ($this->get_flattened_setting_entries() as $entry) {
			$keys[] = $entry['option_name'];
		}

		$keys[] = 'aips_cache_driver';
		$keys[] = 'aips_cache_default_ttl';

		return array_values(array_unique($keys));
	}

	private function print_summary() {
		echo "\n<h2>Setup Complete!</h2>\n";

		$structures = $this->get_created_structures_by_name();
		$categories = $this->get_created_categories_by_name();
		$primary_period = $this->get_primary_distribution_period();
		$target_posts = $this->get_target_posts_by_period();
		$template_targets = array();
		$campaign_targets = array();
		$author_targets = array();

		if (isset($this->object_data['templates'])) {
			foreach ($this->object_data['templates'] as $template) {
				$template_targets[$template['name']] = isset($template['post_quantity']) ? (int) $template['post_quantity'] : 0;
			}
		}

		if (isset($this->object_data['campaigns'])) {
			foreach ($this->object_data['campaigns'] as $campaign) {
				$key = 'target_posts_per_' . $primary_period;
				$campaign_targets[$campaign['name']] = isset($campaign[$key]) ? (int) $campaign[$key] : 0;
			}
		}

		if (isset($this->object_data['authors'])) {
			foreach ($this->object_data['authors'] as $author) {
				$author_targets[$author['name']] = isset($author['scheduled_post_generation_quantity']) ? (int) $author['scheduled_post_generation_quantity'] : 0;
			}
		}

		$configured_settings = $this->get_resolved_configured_settings($structures, $categories);

		echo "<h3>Configured Settings (" . count($configured_settings) . " total):</h3>\n";
		echo "<ul>\n";
		foreach ($configured_settings as $key => $value) {
			echo "<li><strong>" . esc_html($key) . ":</strong> <code>" . esc_html($this->format_summary_value($value)) . "</code></li>\n";
		}
		echo "</ul>\n";

		echo "<h3>Target Throughput:</h3>\n";
		echo "<ul>\n";
		foreach ($target_posts as $period => $count) {
			echo "<li><strong>" . esc_html(ucfirst($period)) . " target:</strong> <code>" . esc_html((string) $count) . " posts</code></li>\n";
		}
		echo "<li><strong>Primary distribution period:</strong> <code>" . esc_html($primary_period) . "</code></li>\n";
		echo "</ul>\n";

		echo "<h3>Campaign Allocation (" . esc_html($primary_period) . "):</h3>\n";
		echo "<ul>\n";
		foreach ($campaign_targets as $name => $count) {
			echo "<li><strong>" . esc_html($name) . ":</strong> <code>" . esc_html((string) $count) . " posts per " . esc_html($primary_period) . "</code></li>\n";
		}
		echo "</ul>\n";

		echo "<h3>Template Allocation (" . esc_html($primary_period) . "):</h3>\n";
		echo "<ul>\n";
		foreach ($template_targets as $name => $count) {
			echo "<li><strong>" . esc_html($name) . ":</strong> <code>" . esc_html((string) $count) . " posts per run</code></li>\n";
		}
		echo "</ul>\n";

		echo "<h3>Author Allocation (" . esc_html($primary_period) . "):</h3>\n";
		echo "<ul>\n";
		foreach ($author_targets as $name => $count) {
			echo "<li><strong>" . esc_html($name) . ":</strong> <code>" . esc_html((string) $count) . " scheduled posts per " . esc_html($primary_period) . "</code></li>\n";
		}
		echo "</ul>\n";

		echo "<h3>Created Items:</h3>\n";
		echo "<ul>\n";

		$entity_types = array(
			'categories' => 'Categories',
			'voices' => 'Voices',
			'structures' => 'Article Structures',
			'authors' => 'Authors',
			'slices' => 'Post Slices',
			'source_groups' => 'Source Groups',
			'sources' => 'Sources',
			'templates' => 'Templates',
			'campaigns' => 'Campaigns',
			'schedules' => 'Schedules',
		);

		foreach ($entity_types as $key => $label) {
			if (isset($this->created_items[$key])) {
				$count = count($this->created_items[$key]);
				echo "<li><strong>{$label}:</strong> {$count} created/updated</li>\n";
			}
		}

		echo "</ul>\n";

		if (!empty($this->errors)) {
			echo "<h3>Errors:</h3>\n";
			echo "<ul>\n";
			foreach ($this->errors as $error) {
				echo "<li>" . esc_html($error) . "</li>\n";
			}
			echo "</ul>\n";
		}

		echo "\n<h3>Next Steps:</h3>\n";
		echo "<ol>\n";
		echo "<li>Review all configured settings in settings pages.</li>\n";
		echo "<li>Review created Categories, Templates, Voices, Structures, Campaigns, and Schedules in admin panel.</li>\n";
		echo "</ol>\n";

		echo "\n<h3>Rollback</h3>\n";
		echo "<p>To rollback all changes made by this script profile, run:</p>\n";
		echo "<pre>wp eval-file scripts/seed-content.php --profile=" . esc_attr($this->profile_name) . " rollback</pre>\n";
	}

	/**
	 * Rollback all changes made by this script profile.
	 */
	private function rollback() {
		global $wpdb;

		$profile_title = isset($this->object_data['strategy_profile']) ? $this->object_data['strategy_profile'] : $this->profile_name;
		echo "<h1>Rolling back profile: " . esc_html($profile_title) . "</h1>\n";

		$deleted = array(
			'schedules' => 0,
			'campaigns' => 0,
			'templates' => 0,
			'sources' => 0,
			'source_groups' => 0,
			'slices' => 0,
			'authors' => 0,
			'structures' => 0,
			'sections' => 0,
			'voices' => 0,
			'categories' => 0,
			'settings' => 0,
		);

		// Reset settings to defaults first
		echo "<h2>Resetting Plugin Settings</h2>\n";
		$defaults = AIPS_Config::get_instance()->get_default_options();
		foreach ($this->get_configured_setting_keys() as $key) {
			if (array_key_exists($key, $defaults)) {
				update_option($key, $defaults[$key]);
				$deleted['settings']++;
			}
		}
		echo "[OK] Reset configured settings to plugin defaults\n";

		// 1. Delete Schedules
		if (!empty($this->object_data['schedules'])) {
			echo "<h2>Deleting Schedules</h2>\n";
			$schedule_titles = wp_list_pluck($this->object_data['schedules'], 'title');
			foreach ($schedule_titles as $title) {
				$count = $wpdb->delete(
					$wpdb->prefix . 'aips_schedule',
					array('title' => $title),
					array('%s')
				);
				if ($count > 0) {
					$deleted['schedules'] += $count;
					echo "[OK] Deleted schedule: {$title}\n";
				}
			}
		}

		// 2. Delete Campaigns
		if (!empty($this->object_data['campaigns'])) {
			echo "<h2>Deleting Campaigns</h2>\n";
			$campaign_names = wp_list_pluck($this->object_data['campaigns'], 'name');
			foreach ($campaign_names as $name) {
				$count = $wpdb->delete(
					$wpdb->prefix . 'aips_campaigns',
					array('name' => $name),
					array('%s')
				);
				if ($count > 0) {
					$deleted['campaigns'] += $count;
					echo "[OK] Deleted campaign: {$name}\n";
				}
			}
		}

		// 3. Delete Templates
		if (!empty($this->object_data['templates'])) {
			echo "<h2>Deleting Templates</h2>\n";
			$template_names = wp_list_pluck($this->object_data['templates'], 'name');
			foreach ($template_names as $name) {
				$count = $wpdb->delete(
					$wpdb->prefix . 'aips_templates',
					array('name' => $name),
					array('%s')
				);
				if ($count > 0) {
					$deleted['templates'] += $count;
					echo "[OK] Deleted template: {$name}\n";
				}
			}
		}

		// 4. Delete Sources
		if (!empty($this->object_data['sources'])) {
			echo "<h2>Deleting Sources</h2>\n";
			$source_names = wp_list_pluck($this->object_data['sources'], 'name');
			$sources_repo = new AIPS_Sources_Repository();
			$sources_by_label = array();
			foreach ($sources_repo->get_all() as $existing_source) {
				if (!empty($existing_source->id) && !empty($existing_source->label)) {
					if (!isset($sources_by_label[(string) $existing_source->label])) {
						$sources_by_label[(string) $existing_source->label] = array();
					}
					$sources_by_label[(string) $existing_source->label][] = (int) $existing_source->id;
				}
			}

			foreach ($source_names as $name) {
				$source_ids = isset($sources_by_label[$name]) ? $sources_by_label[$name] : array();
				foreach ($source_ids as $source_id) {
					$sources_repo->delete_source_terms((int) $source_id);
				}

				$count = $wpdb->delete(
					$wpdb->prefix . 'aips_sources',
					array('label' => $name),
					array('%s')
				);
				if ($count > 0) {
					$deleted['sources'] += $count;
					echo "[OK] Deleted source: {$name}\n";
				}
			}
		}

		// 5. Delete Source Groups
		if (!empty($this->object_data['source_groups'])) {
			echo "<h2>Deleting Source Groups</h2>\n";
			$source_group_slugs = wp_list_pluck($this->object_data['source_groups'], 'slug');
			foreach ($source_group_slugs as $slug) {
				$term = term_exists($slug, 'aips_source_group');
				if ($term) {
					$term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
					$result = wp_delete_term($term_id, 'aips_source_group');
					if (!is_wp_error($result)) {
						$deleted['source_groups']++;
						echo "[OK] Deleted source group: {$slug}\n";
					} else {
						$error_message = $result->get_error_message();
						$this->errors[] = "Failed to delete source group: {$slug} ({$error_message})";
						echo "[ERR] Failed to delete source group: {$slug} ({$error_message})\n";
					}
				}
			}
		}

		// 6. Delete Post Slices
		if (!empty($this->object_data['post_slices'])) {
			echo "<h2>Deleting Post Slices</h2>\n";
			$slice_names = wp_list_pluck($this->object_data['post_slices'], 'name');
			foreach ($slice_names as $name) {
				$count = $wpdb->delete(
					$wpdb->prefix . 'aips_post_slices',
					array('name' => $name),
					array('%s')
				);
				if ($count > 0) {
					$deleted['slices'] += $count;
					echo "[OK] Deleted post slice: {$name}\n";
				}
			}
		}

		// 7. Delete Authors
		if (!empty($this->object_data['authors'])) {
			echo "<h2>Deleting Authors</h2>\n";
			$author_names = wp_list_pluck($this->object_data['authors'], 'name');
			foreach ($author_names as $name) {
				$count = $wpdb->delete(
					$wpdb->prefix . 'aips_authors',
					array('name' => $name),
					array('%s')
				);
				if ($count > 0) {
					$deleted['authors'] += $count;
					echo "[OK] Deleted author: {$name}\n";
				}
			}
		}

		// 8. Delete Article Structures
		if (!empty($this->object_data['structures'])) {
			echo "<h2>Deleting Article Structures</h2>\n";
			$structure_names = wp_list_pluck($this->object_data['structures'], 'name');
			foreach ($structure_names as $name) {
				$count = $wpdb->delete(
					$wpdb->prefix . 'aips_article_structures',
					array('name' => $name),
					array('%s')
				);
				if ($count > 0) {
					$deleted['structures'] += $count;
					echo "[OK] Deleted article structure: {$name}\n";
				}
			}
		}

		// 9. Delete Prompt Sections
		if (!empty($this->object_data['sections'])) {
			echo "<h2>Deleting Prompt Sections</h2>\n";
			$section_names = wp_list_pluck($this->object_data['sections'], 'name');
			foreach ($section_names as $name) {
				$count = $wpdb->delete(
					$wpdb->prefix . 'aips_prompt_sections',
					array('name' => $name),
					array('%s')
				);
				if ($count > 0) {
					$deleted['sections'] += $count;
					echo "[OK] Deleted prompt section: {$name}\n";
				}
			}
		}

		// 10. Delete Voices
		if (!empty($this->object_data['voices'])) {
			echo "<h2>Deleting Voices</h2>\n";
			$voice_names = wp_list_pluck($this->object_data['voices'], 'name');
			foreach ($voice_names as $name) {
				$count = $wpdb->delete(
					$wpdb->prefix . 'aips_voices',
					array('name' => $name),
					array('%s')
				);
				if ($count > 0) {
					$deleted['voices'] += $count;
					echo "[OK] Deleted voice: {$name}\n";
				}
			}
		}

		// 11. Delete Categories
		if (!empty($this->object_data['categories'])) {
			echo "<h2>Deleting Categories</h2>\n";
			$category_slugs = wp_list_pluck($this->object_data['categories'], 'slug');
			foreach ($category_slugs as $slug) {
				$term = term_exists($slug, 'category');
				if ($term) {
					$term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
					$result = wp_delete_term($term_id, 'category');
					if (!is_wp_error($result)) {
						$deleted['categories']++;
						echo "[OK] Deleted category: {$slug}\n";
					} else {
						$error_message = $result->get_error_message();
						$this->errors[] = "Failed to delete category: {$slug} ({$error_message})";
						echo "[ERR] Failed to delete category: {$slug} ({$error_message})\n";
					}
				}
			}
		}

		// Summary
		echo "\n<h2>Rollback Complete</h2>\n";
		echo "<ul>\n";
		foreach ($deleted as $type => $count) {
			echo "<li><strong>" . esc_html(ucfirst($type)) . ":</strong> {$count} deleted</li>\n";
		}
		echo "</ul>\n";

		$total = array_sum($deleted);
		echo "\n<p><strong>Total items deleted/reset:</strong> {$total}</p>\n";
		echo "<p>You can now re-run the setup script if needed.</p>\n";
	}
}

// Run the seeder
$seeder = new AIPS_Content_Seeder(isset($args) ? $args : array());
$seeder->run();
