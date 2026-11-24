<?php
/**
 * DevStackTips configuration setup (ID-driven).
 *
 * Reads the live installation through AIPS_Config_Reader, resolves each managed
 * object to its live ID through the AIPS_Config_Binding stable-key registry, and
 * compares it against the DevStackTips desired configuration
 * (AIPS_DevStackTips_Definitions). It never matches objects by their mutable
 * name at apply time and never carries a hardcoded ID.
 *
 * This is the successor to scripts/setup-devstacktips-content.php and is being
 * built in slices. Implemented so far:
 *   plan   Dry run: print the changes that apply would make (default; writes nothing).
 *   apply  Write changes strictly by ID through repositories, with JSON backup.
 *          Seeds missing v3 source groups and sources, stamping their bindings.
 *
 * Planned next: rollback, topic re-check, and bare install seed parity.
 *
 * Usage (CLI only):
 *   wp eval-file wp-content/plugins/ai-post-scheduler/scripts/devstacktips-setup.php
 *   wp eval-file wp-content/plugins/ai-post-scheduler/scripts/devstacktips-setup.php plan
 *   wp eval-file wp-content/plugins/ai-post-scheduler/scripts/devstacktips-setup.php apply
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/class-aips-devstacktips-definitions.php';

class AIPS_DevStackTips_Config_Setup {

	/**
	 * Minimum topic score the v2 authors auto-approve at.
	 */
	const V2_AUTHOR_MIN_SCORE = 85;

	/**
	 * Profile tag stamped into backup files (guards rollback against other sites/tools).
	 */
	const BACKUP_PROFILE = 'devstacktips-id-driven';

	/** @var string 'plan' or 'apply'. */
	private $mode = 'plan';

	/** @var AIPS_Config_Reader */
	private $reader;

	/** @var AIPS_Config_Binding */
	private $binding;

	/** @var array<string,int> Effective stable-key => ID bindings for this run. */
	private $bindings = array();

	/** @var array<string,int[]> Ambiguous keys (one key, several live objects). */
	private $collisions = array();

	/** @var string[] Warnings collected during planning. */
	private $warnings = array();

	/** @var string[] Errors collected during apply. */
	private $errors = array();

	/** @var int Fields/objects written during apply. */
	private $applied = 0;

	/** @var string Directory backups are written to (apply mode). */
	private $backup_dir = '';

	/** @var string Backup file written by this apply run. */
	private $backup_file = '';

	/** @var array Backup payload. */
	private $backup = array();

	/**
	 * @param array $args Positional WP-CLI arguments.
	 */
	public function __construct(array $args = array()) {
		foreach ($args as $arg) {
			$arg = (string) $arg;
			if (in_array($arg, array('plan', 'apply', 'rollback'), true)) {
				$this->mode = $arg;
			} elseif (0 === strpos($arg, 'backup-dir=')) {
				$this->backup_dir = substr($arg, strlen('backup-dir='));
			}
		}

		if ('' === $this->backup_dir) {
			$home = getenv('HOME');
			$this->backup_dir = ($home && is_dir($home) && is_writable($home))
				? rtrim($home, '/\\') . DIRECTORY_SEPARATOR . 'aips-backups'
				: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aips-backups';
		}

		$this->reader  = new AIPS_Config_Reader();
		$this->binding = new AIPS_Config_Binding($this->reader);
	}

	/**
	 * Run the selected mode.
	 *
	 * @return bool
	 */
	public function run() {
		if ('plan' === $this->mode) {
			$this->print_plan($this->plan());
			return true;
		}

		if ('apply' === $this->mode) {
			return $this->apply();
		}

		$this->line("Mode '{$this->mode}' is not implemented yet in this build.");
		return false;
	}

	/**
	 * Apply the plan: seed missing v3 objects, stamp bindings, update managed
	 * objects by ID through the repositories, and write a JSON backup before
	 * each write. Absent definitions with no seed logic are reported.
	 *
	 * @return bool True when the run finished without errors.
	 */
	public function apply() {
		$this->line('=========================================');
		$this->line('DevStackTips setup (ID-driven) — APPLY');
		$this->line('=========================================');

		if (!$this->start_backup()) {
			return false;
		}

		// Persist the stable-key bindings so this and later runs resolve by the
		// registry rather than by name.
		$sync             = $this->binding->sync();
		$this->collisions = isset($sync['collisions']) ? $sync['collisions'] : array();
		if (!empty($this->collisions)) {
			foreach ($this->collisions as $key => $ids) {
				$this->warn(sprintf('Ambiguous key %s maps to IDs %s; left unbound.', $key, implode(', ', $ids)));
			}
		}

		// Seed missing objects in dependency order, stamping their stable keys.
		$this->seed_objects();

		$plan = $this->plan();

		foreach ($plan as $entry) {
			if ('absent' === $entry['action']) {
				$this->warn(sprintf('%s %s: %s (not changed)', $entry['entity'], $entry['name'], implode('; ', $entry['warnings'])));
				continue;
			}

			foreach ($entry['warnings'] as $warning) {
				$this->warn(sprintf('%s %s: %s', $entry['entity'], $entry['name'], $warning));
			}

			if ('update' !== $entry['action']) {
				continue;
			}

			$this->apply_entry($entry);
		}

		$this->write_backup();
		$this->print_apply_summary();

		return empty($this->errors);
	}

	/**
	 * Seed missing objects in dependency order, stamping their stable keys.
	 *
	 * Order: categories -> voices -> sections -> structures -> post_slices
	 *   -> source_groups -> sources -> campaigns -> templates -> authors
	 *   -> schedules -> settings.
	 *
	 * @return void
	 */
	private function seed_objects() {
		$this->seed_categories();
		$this->seed_voices();
		$this->seed_sections();
		$this->seed_structures();
		$this->seed_post_slices();
		$this->seed_source_groups_and_sources();
		$this->seed_campaigns();
		$this->seed_templates();
		$this->seed_authors();
		$this->seed_schedules();
		$this->seed_settings_if_missing();
	}

	/**
	 * Seed missing WordPress categories.
	 *
	 * @return void
	 */
	private function seed_categories() {
		foreach (AIPS_DevStackTips_Definitions::categories() as $cat) {
			$key = AIPS_Config_Binding::make_key('categories', $cat['slug']);
			$id  = $this->binding->resolve($key);

			if (null === $id) {
				$term = get_term_by('slug', $cat['slug'], 'category');
				if ($term && !is_wp_error($term)) {
					$id = (int) $term->term_id;
					$this->binding->stamp($key, $id);
				}
			}

			if (null === $id) {
				$result = wp_insert_term($cat['name'], 'category', array(
					'slug'        => $cat['slug'],
					'description' => $cat['description'],
				));

				if (is_wp_error($result)) {
					$existing_term_id = $result->get_error_data();
					if ($existing_term_id) {
						$id = (int) $existing_term_id;
						$this->binding->stamp($key, $id);
					} else {
						$this->error('Failed to create category ' . $cat['name'] . ': ' . $result->get_error_message());
					}
					continue;
				}

				$id = (int) $result['term_id'];
				$this->binding->stamp($key, $id);
				$this->record_created('category', $id, $key, $cat['name']);
				$this->applied++;
				$this->line(sprintf('[CREATE] category %s (#%d)', $cat['name'], $id));
			}
		}
	}

	/**
	 * Seed missing writing voices.
	 *
	 * @return void
	 */
	private function seed_voices() {
		$repo     = new AIPS_Voices_Repository();
		$existing = array();
		foreach ($repo->get_all() as $row) {
			$existing[(string) $row->name] = (int) $row->id;
		}

		foreach (AIPS_DevStackTips_Definitions::voices() as $voice) {
			$key = AIPS_Config_Binding::make_key('voices', $voice['name']);
			$id  = $this->binding->resolve($key);

			if (null === $id && isset($this->collisions[$key])) {
				continue;
			}

			if (null === $id && isset($existing[$voice['name']])) {
				$id = $existing[$voice['name']];
				$this->binding->stamp($key, $id);
			}

			if (null === $id) {
				$new_id = $repo->create($voice);
				if (!$new_id) {
					$this->error('Failed to create voice: ' . $voice['name']);
					continue;
				}

				$id = (int) $new_id;
				$this->binding->stamp($key, $id);
				$this->record_created('voice', $id, $key, $voice['name']);
				$this->applied++;
				$this->line(sprintf('[CREATE] voice %s (#%d)', $voice['name'], $id));
			}
		}
	}

	/**
	 * Seed missing prompt sections.
	 *
	 * @return void
	 */
	private function seed_sections() {
		$repo = new AIPS_Prompt_Section_Repository();

		foreach (AIPS_DevStackTips_Definitions::sections() as $section) {
			$key = AIPS_Config_Binding::make_key('prompt_sections', $section['section_key']);
			$id  = $this->binding->resolve($key);

			if (null === $id) {
				$existing = $repo->get_by_key($section['section_key']);
				if ($existing) {
					$id = (int) $existing->id;
					$this->binding->stamp($key, $id);
				}
			}

			if (null === $id) {
				$new_id = $repo->create($section);
				if (!$new_id) {
					$this->error('Failed to create prompt section: ' . $section['name']);
					continue;
				}

				$id = (int) $new_id;
				$this->binding->stamp($key, $id);
				$this->record_created('prompt_section', $id, $key, $section['name']);
				$this->applied++;
				$this->line(sprintf('[CREATE] prompt_section %s (#%d)', $section['name'], $id));
			}
		}
	}

	/**
	 * Seed missing article structures.
	 *
	 * @return void
	 */
	private function seed_structures() {
		$repo     = new AIPS_Article_Structure_Repository();
		$existing = array();
		foreach ($repo->get_all() as $row) {
			$existing[(string) $row->name] = (int) $row->id;
		}

		foreach (AIPS_DevStackTips_Definitions::structures() as $structure) {
			$key = AIPS_Config_Binding::make_key('structures', $structure['name']);
			$id  = $this->binding->resolve($key);

			if (null === $id && isset($this->collisions[$key])) {
				continue;
			}

			if (null === $id && isset($existing[$structure['name']])) {
				$id = $existing[$structure['name']];
				$this->binding->stamp($key, $id);
			}

			if (null === $id) {
				$new_id = $repo->create(array(
					'name'           => $structure['name'],
					'description'    => $structure['description'],
					'structure_data' => wp_json_encode(array(
						'sections'        => $structure['sections'],
						'prompt_template' => '',
					)),
					'is_active'      => 1,
				));

				if (!$new_id) {
					$this->error('Failed to create article structure: ' . $structure['name']);
					continue;
				}

				$id = (int) $new_id;
				$this->binding->stamp($key, $id);
				$this->record_created('structure', $id, $key, $structure['name']);
				$this->applied++;
				$this->line(sprintf('[CREATE] structure %s (#%d)', $structure['name'], $id));
			}
		}
	}

	/**
	 * Seed missing post slices.
	 *
	 * @return void
	 */
	private function seed_post_slices() {
		$repo     = new AIPS_Post_Slices_Repository();
		$existing = array();
		foreach ($repo->get_all(false) as $row) {
			$existing[(string) $row->name] = (int) $row->id;
		}

		$now = AIPS_DateTime::now()->timestamp();

		foreach (AIPS_DevStackTips_Definitions::post_slices() as $slice) {
			$key = AIPS_Config_Binding::make_key('post_slices', $slice['name']);
			$id  = $this->binding->resolve($key);

			if (null === $id && isset($this->collisions[$key])) {
				continue;
			}

			if (null === $id && isset($existing[$slice['name']])) {
				$id = $existing[$slice['name']];
				$this->binding->stamp($key, $id);
			}

			if (null === $id) {
				$new_id = $repo->create(array(
					'name'        => $slice['name'],
					'description' => $slice['description'],
					'sort_order'  => $slice['sort_order'],
					'is_active'   => $slice['is_active'],
					'created_at'  => $now,
					'updated_at'  => $now,
				));

				if (!$new_id) {
					$this->error('Failed to create post slice: ' . $slice['name']);
					continue;
				}

				$id = (int) $new_id;
				$this->binding->stamp($key, $id);
				$this->record_created('post_slice', $id, $key, $slice['name']);
				$this->applied++;
				$this->line(sprintf('[CREATE] post_slice %s (#%d)', $slice['name'], $id));
			}
		}
	}

	/**
	 * Seed missing source groups and sources, stamping their bindings.
	 *
	 * @return void
	 */
	private function seed_source_groups_and_sources() {
		if (!taxonomy_exists('aips_source_group')) {
			register_taxonomy('aips_source_group', array(), array('public' => false));
		}

		foreach (AIPS_DevStackTips_Definitions::source_groups() as $group) {
			$key = AIPS_Config_Binding::make_key('source_groups', $group['slug']);
			$id  = $this->binding->resolve($key);

			if (null === $id) {
				$term = get_term_by('slug', $group['slug'], 'aips_source_group');
				if ($term && !is_wp_error($term)) {
					$id = (int) $term->term_id;
					$this->binding->stamp($key, $id);
				}
			}

			if (null === $id) {
				$result = wp_insert_term($group['name'], 'aips_source_group', array(
					'slug'        => $group['slug'],
					'description' => $group['description'],
				));

				if (is_wp_error($result)) {
					$existing_term_id = $result->get_error_data();
					if ($existing_term_id) {
						$id = (int) $existing_term_id;
						$this->binding->stamp($key, $id);
					} else {
						$this->error('Failed to create source group ' . $group['name'] . ': ' . $result->get_error_message());
						continue;
					}
				} else {
					$id = (int) $result['term_id'];
					$this->binding->stamp($key, $id);
					$this->record_created('source_group', $id, $key, $group['name']);
					$this->applied++;
					$this->line(sprintf('[CREATE] source_group %s (#%d)', $group['name'], $id));
				}
			}
		}

		$sources_repo = new AIPS_Sources_Repository();

		foreach (AIPS_DevStackTips_Definitions::sources() as $source) {
			$key = AIPS_Config_Binding::make_key('sources', $source['name']);
			$id  = $this->binding->resolve($key);

			if (null === $id && $sources_repo->url_exists($source['url'])) {
				foreach ($sources_repo->get_all() as $row) {
					if (strtolower($row->url) === strtolower($source['url'])) {
						$id = (int) $row->id;
						$this->binding->stamp($key, $id);
						break;
					}
				}
			}

			if (null === $id) {
				$group_key = AIPS_Config_Binding::make_key('source_groups', $source['group_slug']);
				$group_id  = $this->binding->resolve($group_key);

				if (null === $group_id) {
					$this->warn(sprintf('Skipped source %s: source group %s is missing.', $source['name'], $source['group_slug']));
					continue;
				}

				$new_id = $sources_repo->create(array(
					'url'       => $source['url'],
					'label'     => $source['name'],
					'is_active' => $source['is_active'],
				));

				if (!$new_id) {
					$this->error('Failed to create source: ' . $source['name']);
					continue;
				}

				$id = (int) $new_id;
				$sources_repo->set_source_terms($id, array((int) $group_id));
				$sources_repo->set_fetch_schedule($id, 'daily');
				$this->binding->stamp($key, $id);
				$this->record_created('source', $id, $key, $source['name']);
				$this->applied++;
				$this->line(sprintf('[CREATE] source %s (#%d)', $source['name'], $id));
			}
		}
	}

	/**
	 * Seed missing campaigns.
	 *
	 * @return void
	 */
	private function seed_campaigns() {
		$repo     = new AIPS_Campaigns_Repository();
		$existing = array();
		foreach ($repo->get_all() as $row) {
			$existing[(string) $row->name] = (int) $row->id;
		}

		foreach (AIPS_DevStackTips_Definitions::campaigns() as $campaign) {
			$key = AIPS_Config_Binding::make_key('campaigns', $campaign['name']);
			$id  = $this->binding->resolve($key);

			if (null === $id && isset($this->collisions[$key])) {
				continue;
			}

			if (null === $id && isset($existing[$campaign['name']])) {
				$id = $existing[$campaign['name']];
				$this->binding->stamp($key, $id);
			}

			if (null === $id) {
				$new_id = $repo->create_campaign(array(
					'name'          => $campaign['name'],
					'content_goal'  => $campaign['content_goal'],
					'campaign_mode' => 'template',
					'is_active'     => 1,
					'is_archived'   => 0,
				));

				if (!$new_id || is_wp_error($new_id)) {
					$this->error('Failed to create campaign: ' . $campaign['name']);
					continue;
				}

				$id = (int) $new_id;
				$this->binding->stamp($key, $id);
				$this->record_created('campaign', $id, $key, $campaign['name']);
				$this->applied++;
				$this->line(sprintf('[CREATE] campaign %s (#%d)', $campaign['name'], $id));
			}
		}
	}

	/**
	 * Seed missing templates.
	 *
	 * @return void
	 */
	private function seed_templates() {
		$repo        = new AIPS_Template_Repository();
		$post_author = $this->resolve_post_author();
		$existing    = array();
		foreach ($repo->get_all() as $row) {
			$existing[(string) $row->name] = (int) $row->id;
		}

		foreach (AIPS_DevStackTips_Definitions::templates() as $key => $def) {
			$id = $this->binding->resolve($key);

			if (null === $id && isset($this->collisions[$key])) {
				continue;
			}

			if (null === $id && isset($existing[$def['name']])) {
				$id = $existing[$def['name']];
				$this->binding->stamp($key, $id);
			}

			if (null === $id) {
				$campaign_id = $this->binding->resolve($def['campaign_key']);
				if (!$campaign_id) {
					$this->error(sprintf('Campaign not found for template %s (%s)', $def['name'], $def['campaign_key']));
					continue;
				}

				$voice_id      = $this->binding->resolve($def['voice_key']);
				$post_category = 0;
				if (!empty($def['category_keys'][0])) {
					$cat_id = $this->binding->resolve($def['category_keys'][0]);
					if ($cat_id) {
						$post_category = (int) $cat_id;
					}
				}

				$group_ids = array();
				if (!empty($def['source_group_key'])) {
					$group_id = $this->binding->resolve($def['source_group_key']);
					if ($group_id) {
						$group_ids[] = (int) $group_id;
					}
				}

				$include_sources = (!empty($def['include_sources']) && !empty($group_ids)) ? 1 : 0;

				$new_id = $repo->create(array(
					'name'                    => $def['name'],
					'prompt_template'         => $def['prompt_template'],
					'title_prompt'            => $def['title_prompt'],
					'voice_id'                => $voice_id,
					'post_quantity'           => $def['post_quantity'],
					'post_status'             => $def['post_status'],
					'post_type'               => 'post',
					'post_category'           => $post_category,
					'post_tags'               => $def['post_tags'],
					'post_author'             => $post_author,
					'generate_featured_image' => $def['generate_featured_image'],
					'featured_image_source'   => $def['featured_image_source'],
					'image_prompt'            => AIPS_DevStackTips_Definitions::featured_image_prompt($def['image_subject']),
					'generate_slug'           => 1,
					'slug_prompt'             => AIPS_DevStackTips_Definitions::slug_prompt($def['slug_type']),
					'include_sources'         => $include_sources,
					'source_group_ids'        => wp_json_encode($group_ids),
					'campaign_id'             => $campaign_id,
					'is_active'               => 1,
				));

				if (!$new_id) {
					$this->error('Failed to create template: ' . $def['name']);
					continue;
				}

				$id = (int) $new_id;
				$this->binding->stamp($key, $id);
				$this->record_created('template', $id, $key, $def['name']);
				$this->applied++;
				$this->line(sprintf('[CREATE] template %s (#%d)', $def['name'], $id));
			}
		}
	}

	/**
	 * Seed missing v2 authors.
	 *
	 * @return void
	 */
	private function seed_authors() {
		$repo        = new AIPS_Authors_Repository();
		$interval    = AIPS_Interval_Calculator::instance();
		$now         = AIPS_DateTime::now()->timestamp();
		$post_author = $this->resolve_post_author();
		$existing    = array();
		foreach ($repo->get_all() as $row) {
			$existing[(string) $row->name] = (int) $row->id;
		}

		$index = 0;
		foreach (AIPS_DevStackTips_Definitions::authors() as $key => $def) {
			if (empty($def['v2_author'])) {
				continue;
			}

			$index++;
			$id = $this->binding->resolve($key);

			if (null === $id && isset($this->collisions[$key])) {
				continue;
			}

			if (null === $id && isset($existing[$def['name']])) {
				$id = $existing[$def['name']];
				$this->binding->stamp($key, $id);
			}

			if (null === $id) {
				$group_ids = array();
				if (!empty($def['source_group_key'])) {
					$group_id = $this->binding->resolve($def['source_group_key']);
					if ($group_id) {
						$group_ids[] = (int) $group_id;
					}
				}

				$stagger = $index * 600;
				$new_id  = $repo->create(array(
					'name'                               => $def['name'],
					'field_niche'                        => $def['field_niche'],
					'keywords'                           => $def['keywords'],
					'description'                        => $def['description'],
					'details'                            => $def['details'],
					'article_structure_id'               => $this->binding->resolve($def['structure_key']),
					'voice_tone'                         => $def['voice_tone'],
					'writing_style'                      => $def['writing_style'],
					'target_audience'                    => $def['target_audience'],
					'expertise_level'                    => $def['expertise_level'],
					'content_goals'                      => $def['content_goals'],
					'excluded_topics'                    => $def['excluded_topics'],
					'preferred_content_length'           => $def['preferred_content_length'],
					'language'                           => 'en',
					'post_status'                        => 'draft',
					'post_category'                      => $this->binding->resolve($def['category_key']),
					'post_author'                        => $post_author,
					'generate_slug'                      => 1,
					'slug_prompt'                        => AIPS_DevStackTips_Definitions::slug_prompt($def['slug_type']),
					'generate_featured_image'            => 1,
					'featured_image_source'              => 'ai_prompt',
					'image_prompt'                       => AIPS_DevStackTips_Definitions::featured_image_prompt($def['image_subject']),
					'topic_auto_approval_mode'           => 'score',
					'topic_auto_approval_min_score'      => self::V2_AUTHOR_MIN_SCORE,
					'topic_auto_approval_fallback'       => 'pending',
					'topic_generation_frequency'         => $def['topic_generation_frequency'],
					'topic_generation_quantity'          => $def['topic_generation_quantity'],
					'topic_generation_next_run'          => $interval->calculate_next_run($def['topic_generation_frequency'], $now + $stagger),
					'topic_generation_last_run'          => 0,
					'topic_generation_is_active'         => 1,
					'post_generation_frequency'          => $def['post_generation_frequency'],
					'post_generation_next_run'           => $interval->calculate_next_run($def['post_generation_frequency'], $now + $stagger + 300),
					'post_generation_last_run'           => 0,
					'post_generation_is_active'          => 1,
					'max_posts_per_topic'                => $def['max_posts_per_topic'],
					'manual_post_generation_quantity'    => $def['manual_post_generation_quantity'],
					'scheduled_post_generation_quantity' => $def['scheduled_post_generation_quantity'],
					'include_sources'                    => !empty($group_ids) ? 1 : 0,
					'source_group_ids'                   => wp_json_encode($group_ids),
					'is_active'                          => $def['is_active'],
					'created_at'                         => $now,
					'updated_at'                         => $now,
				));

				if (!$new_id) {
					$this->error('Failed to create author: ' . $def['name']);
					continue;
				}

				$id = (int) $new_id;
				$this->binding->stamp($key, $id);
				$this->record_created('author', $id, $key, $def['name']);
				$this->applied++;
				$this->line(sprintf('[CREATE] author %s (#%d)', $def['name'], $id));
			}
		}
	}

	/**
	 * Seed missing schedules.
	 *
	 * @return void
	 */
	private function seed_schedules() {
		$repo     = new AIPS_Schedule_Repository();
		$existing = array();
		foreach ($repo->get_all(false) as $row) {
			$existing[(string) $row->title] = (int) $row->id;
		}

		foreach (AIPS_DevStackTips_Definitions::schedules() as $key => $def) {
			$id = $this->binding->resolve($key);

			if (null === $id && isset($this->collisions[$key])) {
				continue;
			}

			if (null === $id && isset($existing[$def['title']])) {
				$id = $existing[$def['title']];
				$this->binding->stamp($key, $id);
			}

			if (null === $id) {
				$template_id = $this->binding->resolve($def['template_key']);
				if (!$template_id) {
					$this->error(sprintf('Template not found for schedule %s (%s)', $def['title'], $def['template_key']));
					continue;
				}

				$structure_id = !empty($def['structure_key']) ? $this->binding->resolve($def['structure_key']) : null;
				$next_run     = (null !== $def['weekday'])
					? $this->next_weekday_timestamp((int) $def['weekday'], $def['start_time'])
					: $this->next_daily_timestamp($def['start_time']);

				$new_id = $repo->create(array(
					'template_id'          => $template_id,
					'article_structure_id' => $structure_id,
					'title'                => $def['title'],
					'frequency'            => $def['frequency'],
					'next_run'             => $next_run,
					'last_run'             => 0,
					'is_active'            => $def['is_active'],
					'status'               => !empty($def['is_active']) ? 'active' : 'inactive',
					'schedule_type'        => 'post_generation',
				));

				if (!$new_id) {
					$this->error('Failed to create schedule: ' . $def['title']);
					continue;
				}

				$id = (int) $new_id;
				$this->binding->stamp($key, $id);
				$this->record_created('schedule', $id, $key, $def['title']);
				$this->applied++;
				$this->line(sprintf('[CREATE] schedule %s (#%d)', $def['title'], $id));
			}
		}
	}

	/**
	 * Seed settings that are not stored yet (create-missing only).
	 *
	 * @return void
	 */
	private function seed_settings_if_missing() {
		$missing = new stdClass();

		foreach (AIPS_DevStackTips_Definitions::seed_settings() as $entry) {
			$name = $entry['option_name'];
			if (get_option($name, $missing) !== $missing) {
				continue;
			}

			$value = $this->resolve_seed_setting_value($entry);
			if (null === $value) {
				continue;
			}

			$this->backup_option($name);
			$this->write_backup();
			update_option($name, $value);
			$this->applied++;
			$this->line(sprintf('[CREATE] setting %s', $name));
		}
	}

	/**
	 * Resolve a seed setting value (static, structure key, or category key).
	 *
	 * @param array $entry
	 * @return mixed|null
	 */
	private function resolve_seed_setting_value(array $entry) {
		if (array_key_exists('value', $entry)) {
			return $entry['value'];
		}
		if (isset($entry['structure_key'])) {
			return $this->binding->resolve($entry['structure_key']);
		}
		if (isset($entry['category_key'])) {
			return $this->binding->resolve($entry['category_key']);
		}

		return null;
	}

	/**
	 * Resolve a seed setting value during read-only planning.
	 *
	 * @param array $entry
	 * @return mixed|null
	 */
	private function resolve_seed_setting_preview(array $entry) {
		if (array_key_exists('value', $entry)) {
			return $entry['value'];
		}
		if (isset($entry['structure_key'])) {
			return $this->resolve($entry['structure_key']);
		}
		if (isset($entry['category_key'])) {
			return $this->resolve($entry['category_key']);
		}

		return null;
	}

	/**
	 * Resolve the default WordPress post author ID.
	 *
	 * @return int
	 */
	private function resolve_post_author() {
		$user_id = (int) get_current_user_id();

		return $user_id > 0 ? $user_id : (int) AIPS_Config::get_instance()->get_option('aips_default_post_author');
	}

	/**
	 * Next occurrence of a weekday and time in the site timezone.
	 *
	 * @param int    $iso_weekday 1 (Monday) to 7 (Sunday).
	 * @param string $time_hhmm   "HH:MM".
	 * @return int Unix timestamp.
	 */
	public function next_weekday_timestamp($iso_weekday, $time_hhmm) {
		$timezone              = wp_timezone();
		$now                   = new DateTimeImmutable('now', $timezone);
		list($hour, $minute)   = array_map('intval', explode(':', $time_hhmm));
		$candidate             = $now->setTime($hour, $minute);
		$days                  = ($iso_weekday - (int) $now->format('N') + 7) % 7;
		$candidate             = $candidate->modify('+' . $days . ' days');

		if ($candidate <= $now) {
			$candidate = $candidate->modify('+7 days');
		}

		return $candidate->getTimestamp();
	}

	/**
	 * Next occurrence of a daily time (site timezone).
	 *
	 * @param string $time_hhmm      "HH:MM".
	 * @param int    $offset_minutes Minutes added to the slot.
	 * @return int Unix timestamp.
	 */
	public function next_daily_timestamp($time_hhmm, $offset_minutes = 0) {
		$timezone              = wp_timezone();
		$now                   = new DateTimeImmutable('now', $timezone);
		list($hour, $minute)   = array_map('intval', explode(':', $time_hhmm));
		$candidate             = $now->setTime($hour, $minute)->modify('+' . (int) $offset_minutes . ' minutes');

		if ($candidate <= $now) {
			$candidate = $candidate->modify('+1 day');
		}

		return $candidate->getTimestamp();
	}

	/**
	 * Write one plan entry's changes, backing up the old values first.
	 *
	 * @param array $entry
	 * @return void
	 */
	private function apply_entry(array $entry) {
		if ('setting' === $entry['entity']) {
			$name = $entry['key'];
			$this->backup_option($name);
			$this->write_backup();
			update_option($name, $entry['changes'][$name]['new']);
			$this->applied++;
			$this->line(sprintf('[SET] %s', $name));
			return;
		}

		$fields = array();
		foreach ($entry['changes'] as $field => $change) {
			$this->backup_field($entry['entity'], (int) $entry['id'], $field, $change['old']);
			$fields[$field] = $change['new'];
		}
		$this->write_backup();

		if (false === $this->repository_update($entry['entity'], (int) $entry['id'], $fields)) {
			$this->error(sprintf('Failed to update %s %s (#%d)', $entry['entity'], $entry['name'], (int) $entry['id']));
			return;
		}

		$this->applied++;
		$this->line(sprintf('[SET] %s %s (#%d): %s', $entry['entity'], $entry['name'], (int) $entry['id'], implode(', ', array_keys($fields))));
	}

	/**
	 * Update a managed row through its repository.
	 *
	 * @param string $entity 'template', 'author', or 'schedule'.
	 * @param int    $id
	 * @param array  $fields
	 * @return mixed False on failure.
	 */
	private function repository_update($entity, $id, array $fields) {
		switch ($entity) {
			case 'template':
				return (new AIPS_Template_Repository())->update($id, $fields);
			case 'author':
				return (new AIPS_Authors_Repository())->update($id, $fields);
			case 'schedule':
				return (new AIPS_Schedule_Repository())->update($id, $fields);
		}

		return false;
	}

	/**
	 * Resolve the effective bindings and build the plan (pure read; no writes).
	 *
	 * @return array List of plan entries.
	 */
	public function plan() {
		$preview          = $this->binding->preview();
		$this->bindings   = $preview['bindings'];
		$this->collisions = $preview['collisions'];

		return $this->build_plan();
	}

	/**
	 * Build the structured plan (pure read; no writes).
	 *
	 * @return array List of plan entries.
	 */
	public function build_plan() {
		$entries = array();

		foreach (AIPS_DevStackTips_Definitions::categories() as $cat) {
			$entries[] = $this->plan_category($cat);
		}

		foreach (AIPS_DevStackTips_Definitions::voices() as $voice) {
			$entries[] = $this->plan_voice($voice);
		}

		foreach (AIPS_DevStackTips_Definitions::sections() as $section) {
			$entries[] = $this->plan_section($section);
		}

		foreach (AIPS_DevStackTips_Definitions::structures() as $structure) {
			$entries[] = $this->plan_structure($structure);
		}

		foreach (AIPS_DevStackTips_Definitions::post_slices() as $slice) {
			$entries[] = $this->plan_post_slice($slice);
		}

		foreach (AIPS_DevStackTips_Definitions::source_groups() as $group) {
			$entries[] = $this->plan_source_group($group);
		}

		foreach (AIPS_DevStackTips_Definitions::sources() as $source) {
			$entries[] = $this->plan_source($source);
		}

		foreach (AIPS_DevStackTips_Definitions::campaigns() as $campaign) {
			$entries[] = $this->plan_campaign($campaign);
		}

		foreach (AIPS_DevStackTips_Definitions::templates() as $key => $def) {
			$entries[] = $this->plan_template($key, $def);
		}

		foreach (AIPS_DevStackTips_Definitions::authors() as $key => $def) {
			$entries[] = $this->plan_author($key, $def);
		}

		foreach (AIPS_DevStackTips_Definitions::schedules() as $key => $def) {
			$entries[] = $this->plan_schedule($key, $def);
		}

		foreach (AIPS_DevStackTips_Definitions::seed_settings() as $entry) {
			$entries[] = $this->plan_seed_setting($entry);
		}

		foreach (AIPS_DevStackTips_Definitions::settings() as $name => $value) {
			$entries[] = $this->plan_setting($name, $value);
		}

		return $entries;
	}

	/**
	 * Plan one category.
	 *
	 * @param array $cat ['name', 'slug', 'description']
	 * @return array Plan entry.
	 */
	private function plan_category(array $cat) {
		$key = AIPS_Config_Binding::make_key('categories', $cat['slug']);
		$id  = $this->resolve($key);

		if (null === $id) {
			$term = get_term_by('slug', $cat['slug'], 'category');
			if ($term && !is_wp_error($term)) {
				$id = (int) $term->term_id;
			}
		}

		if (null !== $id) {
			return $this->noop_entry('category', $key, $cat['name'], $id);
		}

		if (isset($this->collisions[$key])) {
			return $this->absent_entry('category', $key, $cat['name']);
		}

		return array(
			'entity'   => 'category',
			'key'      => $key,
			'name'     => $cat['name'],
			'id'       => null,
			'action'   => 'create',
			'changes'  => array(
				'slug'        => array('old' => null, 'new' => $cat['slug']),
				'description' => array('old' => null, 'new' => $cat['description']),
			),
			'warnings' => array(),
		);
	}

	/**
	 * Plan one voice.
	 *
	 * @param array $voice
	 * @return array Plan entry.
	 */
	private function plan_voice(array $voice) {
		$key = AIPS_Config_Binding::make_key('voices', $voice['name']);
		$id  = $this->resolve($key);

		if (null !== $id) {
			return $this->noop_entry('voice', $key, $voice['name'], $id);
		}

		if (isset($this->collisions[$key])) {
			return $this->absent_entry('voice', $key, $voice['name']);
		}

		return array(
			'entity'   => 'voice',
			'key'      => $key,
			'name'     => $voice['name'],
			'id'       => null,
			'action'   => 'create',
			'changes'  => array(
				'title_prompt' => array('old' => null, 'new' => $voice['title_prompt']),
			),
			'warnings' => array(),
		);
	}

	/**
	 * Plan one prompt section.
	 *
	 * @param array $section
	 * @return array Plan entry.
	 */
	private function plan_section(array $section) {
		$key = AIPS_Config_Binding::make_key('prompt_sections', $section['section_key']);
		$id  = $this->resolve($key);

		if (null === $id) {
			$existing = (new AIPS_Prompt_Section_Repository())->get_by_key($section['section_key']);
			if ($existing) {
				$id = (int) $existing->id;
			}
		}

		if (null !== $id) {
			return $this->noop_entry('prompt_section', $key, $section['name'], $id);
		}

		if (isset($this->collisions[$key])) {
			return $this->absent_entry('prompt_section', $key, $section['name']);
		}

		return array(
			'entity'   => 'prompt_section',
			'key'      => $key,
			'name'     => $section['name'],
			'id'       => null,
			'action'   => 'create',
			'changes'  => array(
				'section_key' => array('old' => null, 'new' => $section['section_key']),
			),
			'warnings' => array(),
		);
	}

	/**
	 * Plan one article structure.
	 *
	 * @param array $structure
	 * @return array Plan entry.
	 */
	private function plan_structure(array $structure) {
		$key = AIPS_Config_Binding::make_key('structures', $structure['name']);
		$id  = $this->resolve($key);

		if (null !== $id) {
			return $this->noop_entry('structure', $key, $structure['name'], $id);
		}

		if (isset($this->collisions[$key])) {
			return $this->absent_entry('structure', $key, $structure['name']);
		}

		return array(
			'entity'   => 'structure',
			'key'      => $key,
			'name'     => $structure['name'],
			'id'       => null,
			'action'   => 'create',
			'changes'  => array(
				'sections' => array('old' => null, 'new' => $structure['sections']),
			),
			'warnings' => array(),
		);
	}

	/**
	 * Plan one post slice.
	 *
	 * @param array $slice
	 * @return array Plan entry.
	 */
	private function plan_post_slice(array $slice) {
		$key = AIPS_Config_Binding::make_key('post_slices', $slice['name']);
		$id  = $this->resolve($key);

		if (null !== $id) {
			return $this->noop_entry('post_slice', $key, $slice['name'], $id);
		}

		if (isset($this->collisions[$key])) {
			return $this->absent_entry('post_slice', $key, $slice['name']);
		}

		return array(
			'entity'   => 'post_slice',
			'key'      => $key,
			'name'     => $slice['name'],
			'id'       => null,
			'action'   => 'create',
			'changes'  => array(
				'sort_order' => array('old' => null, 'new' => $slice['sort_order']),
			),
			'warnings' => array(),
		);
	}

	/**
	 * Plan one campaign.
	 *
	 * @param array $campaign
	 * @return array Plan entry.
	 */
	private function plan_campaign(array $campaign) {
		$key = AIPS_Config_Binding::make_key('campaigns', $campaign['name']);
		$id  = $this->resolve($key);

		if (null !== $id) {
			return $this->noop_entry('campaign', $key, $campaign['name'], $id);
		}

		if (isset($this->collisions[$key])) {
			return $this->absent_entry('campaign', $key, $campaign['name']);
		}

		return array(
			'entity'   => 'campaign',
			'key'      => $key,
			'name'     => $campaign['name'],
			'id'       => null,
			'action'   => 'create',
			'changes'  => array(
				'content_goal' => array('old' => null, 'new' => $campaign['content_goal']),
			),
			'warnings' => array(),
		);
	}

	/**
	 * Build a noop plan entry.
	 *
	 * @param string $entity
	 * @param string $key
	 * @param string $name
	 * @param int    $id
	 * @return array
	 */
	private function noop_entry($entity, $key, $name, $id) {
		return array(
			'entity'   => $entity,
			'key'      => $key,
			'name'     => $name,
			'id'       => (int) $id,
			'action'   => 'noop',
			'changes'  => array(),
			'warnings' => array(),
		);
	}

	/**
	 * Plan one source group.
	 *
	 * @param array $group ['name', 'slug', 'description']
	 * @return array Plan entry.
	 */
	private function plan_source_group(array $group) {
		$key = AIPS_Config_Binding::make_key('source_groups', $group['slug']);
		$id  = $this->resolve($key);

		if (null === $id && taxonomy_exists('aips_source_group')) {
			$term = get_term_by('slug', $group['slug'], 'aips_source_group');
			if ($term && !is_wp_error($term)) {
				$id = (int) $term->term_id;
			}
		}

		if (null !== $id) {
			return array(
				'entity'   => 'source_group',
				'key'      => $key,
				'name'     => $group['name'],
				'id'       => $id,
				'action'   => 'noop',
				'changes'  => array(),
				'warnings' => array(),
			);
		}

		return array(
			'entity'   => 'source_group',
			'key'      => $key,
			'name'     => $group['name'],
			'id'       => null,
			'action'   => 'create',
			'changes'  => array(
				'slug'        => array('old' => null, 'new' => $group['slug']),
				'description' => array('old' => null, 'new' => $group['description']),
			),
			'warnings' => array(),
		);
	}

	/**
	 * Plan one source.
	 *
	 * @param array $source ['name', 'url', 'group_slug', 'is_active']
	 * @return array Plan entry.
	 */
	private function plan_source(array $source) {
		$key = AIPS_Config_Binding::make_key('sources', $source['name']);
		$id  = $this->resolve($key);

		if (null === $id) {
			$repo = new AIPS_Sources_Repository();
			if ($repo->url_exists($source['url'])) {
				foreach ($repo->get_all() as $row) {
					if (strtolower($row->url) === strtolower($source['url'])) {
						$id = (int) $row->id;
						break;
					}
				}
			}
		}

		if (null !== $id) {
			return array(
				'entity'   => 'source',
				'key'      => $key,
				'name'     => $source['name'],
				'id'       => $id,
				'action'   => 'noop',
				'changes'  => array(),
				'warnings' => array(),
			);
		}

		return array(
			'entity'   => 'source',
			'key'      => $key,
			'name'     => $source['name'],
			'id'       => null,
			'action'   => 'create',
			'changes'  => array(
				'url'        => array('old' => null, 'new' => $source['url']),
				'group_slug' => array('old' => null, 'new' => $source['group_slug']),
				'is_active'  => array('old' => null, 'new' => $source['is_active']),
			),
			'warnings' => array(),
		);
	}

	/**
	 * Plan one template's managed fields.
	 *
	 * @param string $key Stable key.
	 * @param array  $def Definition.
	 * @return array Plan entry.
	 */
	private function plan_template($key, array $def) {
		$id = $this->resolve($key);
		if (null === $id) {
			if (isset($this->collisions[$key])) {
				return $this->absent_entry('template', $key, $def['name']);
			}

			return array(
				'entity'   => 'template',
				'key'      => $key,
				'name'     => $def['name'],
				'id'       => null,
				'action'   => 'create',
				'changes'  => array(
					'campaign_key'            => array('old' => null, 'new' => $def['campaign_key']),
					'voice_key'               => array('old' => null, 'new' => $def['voice_key']),
					'structure_key'           => array('old' => null, 'new' => $def['structure_key']),
					'post_quantity'           => array('old' => null, 'new' => $def['post_quantity']),
					'post_status'             => array('old' => null, 'new' => $def['post_status']),
					'generate_featured_image' => array('old' => null, 'new' => $def['generate_featured_image']),
					'include_sources'         => array('old' => null, 'new' => $def['include_sources']),
				),
				'warnings' => array(),
			);
		}

		$live = (new AIPS_Template_Repository())->get_by_id($id);
		if (!$live) {
			return $this->absent_entry('template', $key, $def['name'], true);
		}

		$warnings = array();
		$desired  = array(
			'generate_slug'           => 1,
			'slug_prompt'             => AIPS_DevStackTips_Definitions::slug_prompt($def['slug_type']),
			'title_prompt'            => $def['title_prompt'],
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
		);

		$voice_id = $this->resolve($def['voice_key']);
		if (null !== $voice_id) {
			$desired['voice_id'] = $voice_id;
		} else {
			$warnings[] = "voice not found ({$def['voice_key']})";
		}

		// Keep a hand-written featured image prompt; fill only an empty one.
		if ('' === trim((string) $live->image_prompt)) {
			$desired['image_prompt'] = AIPS_DevStackTips_Definitions::featured_image_prompt($def['image_subject']);
		}

		if (!empty($def['source_group_key'])) {
			$group_id = $this->resolve($def['source_group_key']);
			if (null !== $group_id) {
				$desired['include_sources']  = 1;
				$desired['source_group_ids'] = wp_json_encode(array($group_id));
			} else {
				$warnings[] = "source group not present yet ({$def['source_group_key']})";
			}
		}

		return $this->diff_entry('template', $key, $def['name'], $id, $live, $desired, $warnings);
	}

	/**
	 * Plan one author's managed fields.
	 *
	 * @param string $key Stable key.
	 * @param array  $def Definition.
	 * @return array Plan entry.
	 */
	private function plan_author($key, array $def) {
		$id = $this->resolve($key);
		if (null === $id) {
			if (empty($def['v2_author']) || isset($this->collisions[$key])) {
				return $this->absent_entry('author', $key, $def['name']);
			}

			return array(
				'entity'   => 'author',
				'key'      => $key,
				'name'     => $def['name'],
				'id'       => null,
				'action'   => 'create',
				'changes'  => array(
					'field_niche'               => array('old' => null, 'new' => $def['field_niche']),
					'structure_key'             => array('old' => null, 'new' => $def['structure_key']),
					'category_key'              => array('old' => null, 'new' => $def['category_key']),
					'post_generation_frequency' => array('old' => null, 'new' => $def['post_generation_frequency']),
				),
				'warnings' => array(),
			);
		}

		$live = (new AIPS_Authors_Repository())->get_by_id($id);
		if (!$live) {
			return $this->absent_entry('author', $key, $def['name'], true);
		}

		$warnings = array();
		$desired  = array(
			'generate_slug'           => 1,
			'slug_prompt'             => AIPS_DevStackTips_Definitions::slug_prompt($def['slug_type']),
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
		);

		if ('' === trim((string) $live->image_prompt)) {
			$desired['image_prompt'] = AIPS_DevStackTips_Definitions::featured_image_prompt($def['image_subject']);
		}

		if (empty($live->post_category) && !empty($def['category_key'])) {
			$category_id = $this->resolve($def['category_key']);
			if (null !== $category_id) {
				$desired['post_category'] = $category_id;
			} else {
				$warnings[] = "category not found ({$def['category_key']})";
			}
		}

		if (!empty($def['source_group_key'])) {
			$group_id = $this->resolve($def['source_group_key']);
			if (null !== $group_id) {
				$desired['include_sources']  = 1;
				$desired['source_group_ids'] = wp_json_encode(array($group_id));
			}
		}

		if (!empty($def['v2_author'])) {
			$desired['topic_auto_approval_mode']          = 'score';
			$desired['topic_auto_approval_min_score']     = self::V2_AUTHOR_MIN_SCORE;
			$desired['topic_auto_approval_fallback']      = 'pending';
			$desired['topic_generation_quantity']         = 8;
			$desired['scheduled_post_generation_quantity'] = 1;
			$desired['post_generation_frequency']         = 'daily';
		}

		return $this->diff_entry('author', $key, $def['name'], $id, $live, $desired, $warnings);
	}

	/**
	 * Plan one schedule.
	 *
	 * @param string $key Stable key.
	 * @param array  $def Definition.
	 * @return array Plan entry.
	 */
	private function plan_schedule($key, array $def) {
		$id = $this->resolve($key);
		if (null === $id) {
			if (isset($this->collisions[$key])) {
				return $this->absent_entry('schedule', $key, $def['title']);
			}

			return array(
				'entity'   => 'schedule',
				'key'      => $key,
				'name'     => $def['title'],
				'id'       => null,
				'action'   => 'create',
				'changes'  => array(
					'template_key' => array('old' => null, 'new' => $def['template_key']),
					'frequency'    => array('old' => null, 'new' => $def['frequency']),
					'start_time'   => array('old' => null, 'new' => $def['start_time']),
					'weekday'      => array('old' => null, 'new' => $def['weekday']),
					'is_active'    => array('old' => null, 'new' => $def['is_active']),
				),
				'warnings' => array(),
			);
		}

		$live = (new AIPS_Schedule_Repository())->get_by_id($id);
		if (!$live) {
			return $this->absent_entry('schedule', $key, $def['title'], true);
		}

		$warnings = array();
		$desired  = array();
		if (!empty($def['structure_key'])) {
			$structure_id = $this->resolve($def['structure_key']);
			if (null !== $structure_id) {
				$desired['article_structure_id'] = $structure_id;
			} else {
				$warnings[] = "structure not found ({$def['structure_key']})";
			}
		}

		return $this->diff_entry('schedule', $key, $def['title'], $id, $live, $desired, $warnings);
	}

	/**
	 * Plan one create-missing seed setting.
	 *
	 * @param array $entry
	 * @return array Plan entry.
	 */
	private function plan_seed_setting(array $entry) {
		$name    = $entry['option_name'];
		$missing = new stdClass();
		$current = get_option($name, $missing);

		if ($current !== $missing) {
			return $this->noop_entry('setting', $name, $name, 0);
		}

		$value = $this->resolve_seed_setting_preview($entry);

		return array(
			'entity'   => 'setting',
			'key'      => $name,
			'name'     => $name,
			'id'       => 0,
			'action'   => 'create',
			'changes'  => array($name => array('old' => null, 'new' => $value)),
			'warnings' => array(),
		);
	}

	/**
	 * Plan one global setting.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Desired value.
	 * @return array Plan entry.
	 */
	private function plan_setting($name, $value) {
		$current = get_option($name, null);
		$changed = !$this->same($current, $value);

		return array(
			'entity'   => 'setting',
			'key'      => $name,
			'name'     => $name,
			'id'       => 0,
			'action'   => $changed ? 'update' : 'noop',
			'changes'  => $changed ? array($name => array('old' => $current, 'new' => $value)) : array(),
			'warnings' => array(),
		);
	}

	/**
	 * Diff desired fields against a live row into a plan entry.
	 *
	 * @param string $entity
	 * @param string $key
	 * @param string $name
	 * @param int    $id
	 * @param object $live
	 * @param array  $desired
	 * @param array  $warnings
	 * @return array
	 */
	private function diff_entry($entity, $key, $name, $id, $live, array $desired, array $warnings) {
		$changes = array();
		foreach ($desired as $field => $value) {
			$old = isset($live->$field) ? $live->$field : null;
			if (!$this->same($old, $value)) {
				$changes[$field] = array('old' => $old, 'new' => $value);
			}
		}

		return array(
			'entity'   => $entity,
			'key'      => $key,
			'name'     => $name,
			'id'       => (int) $id,
			'action'   => empty($changes) ? 'noop' : 'update',
			'changes'  => $changes,
			'warnings' => $warnings,
		);
	}

	/**
	 * A plan entry for a definition with no live object bound.
	 *
	 * @param string $entity
	 * @param string $key
	 * @param string $name
	 * @param bool   $load_failed Whether a bound ID failed to load.
	 * @return array
	 */
	private function absent_entry($entity, $key, $name, $load_failed = false) {
		$note = $load_failed ? 'bound ID could not be loaded' : 'no live object bound to this key';
		if (isset($this->collisions[$key])) {
			$note = 'ambiguous: several live objects map to this key (' . implode(', ', $this->collisions[$key]) . ')';
		}

		return array(
			'entity'   => $entity,
			'key'      => $key,
			'name'     => $name,
			'id'       => null,
			'action'   => 'absent',
			'changes'  => array(),
			'warnings' => array($note),
		);
	}

	/**
	 * Resolve a stable key to an ID using this run's effective bindings.
	 *
	 * @param string|null $key
	 * @return int|null
	 */
	private function resolve($key) {
		if (null === $key || !isset($this->bindings[$key])) {
			return null;
		}

		return (int) $this->bindings[$key];
	}

	/**
	 * Print the plan.
	 *
	 * @param array $plan
	 * @return void
	 */
	private function print_plan(array $plan) {
		$this->line('=========================================');
		$this->line('DevStackTips setup (ID-driven) — PLAN (dry run, nothing written)');
		$this->line('=========================================');

		$counts = array('update' => 0, 'noop' => 0, 'absent' => 0, 'create' => 0);

		foreach ($plan as $entry) {
			$counts[$entry['action']] = isset($counts[$entry['action']]) ? $counts[$entry['action']] + 1 : 1;

			if ('noop' === $entry['action']) {
				$this->line(sprintf('[OK]     %s %s: already up to date', $entry['entity'], $entry['name']));
				continue;
			}

			if ('absent' === $entry['action']) {
				$this->line(sprintf('[ABSENT] %s %s: %s', $entry['entity'], $entry['name'], implode('; ', $entry['warnings'])));
				continue;
			}

			if ('create' === $entry['action']) {
				$this->line(sprintf('[CREATE] %s %s:', $entry['entity'], $entry['name']));
				foreach ($entry['changes'] as $field => $change) {
					$this->line(sprintf('           %s: %s', $field, $this->short($change['new'])));
				}
				continue;
			}

			$this->line(sprintf('[PLAN]   %s %s (#%d):', $entry['entity'], $entry['name'], (int) $entry['id']));
			foreach ($entry['changes'] as $field => $change) {
				$this->line(sprintf('           %s: %s -> %s', $field, $this->short($change['old']), $this->short($change['new'])));
			}
			foreach ($entry['warnings'] as $warning) {
				$this->line('           ! ' . $warning);
			}
		}

		if (!empty($this->collisions)) {
			$this->line('');
			$this->line('Ambiguous keys (resolve by renaming or binding manually):');
			foreach ($this->collisions as $key => $ids) {
				$this->line(sprintf('  %s -> IDs %s', $key, implode(', ', $ids)));
			}
		}

		$this->line('');
		$this->line(sprintf('Summary: %d to create, %d to update, %d already correct, %d absent, %d ambiguous key(s).',
			$counts['create'], $counts['update'], $counts['noop'], $counts['absent'], count($this->collisions)));
		$this->line('Nothing was written. Run with apply to execute changes.');
	}

	/* ------------------------------------------------------------------ */
	/* Comparison + output helpers (shared behaviour with the v2 script).  */
	/* ------------------------------------------------------------------ */

	/**
	 * Normalize a value for comparison (null/'' equal, JSON arrays by content, numbers as strings).
	 *
	 * @param mixed $value
	 * @return string
	 */
	private function normalize($value) {
		if (null === $value || false === $value) {
			return '';
		}
		if (true === $value) {
			return '1';
		}
		if (is_string($value)) {
			$trimmed = trim($value);
			if ('' !== $trimmed && ('[' === $trimmed[0] || '{' === $trimmed[0])) {
				$decoded = json_decode($trimmed, true);
				if (is_array($decoded)) {
					$value = $decoded;
				}
			}
		}
		if (is_array($value)) {
			$value = array_map(
				function ($item) {
					return is_scalar($item) ? (string) $item : $item;
				},
				$value
			);
			return (string) wp_json_encode($value);
		}
		if (is_float($value) || is_int($value) || is_numeric($value)) {
			return (string) (0 + $value);
		}

		return (string) $value;
	}

	/**
	 * Whether two values are equal after normalization.
	 *
	 * @param mixed $a
	 * @param mixed $b
	 * @return bool
	 */
	private function same($a, $b) {
		return $this->normalize($a) === $this->normalize($b);
	}

	/**
	 * Short printable form of a value.
	 *
	 * @param mixed $value
	 * @return string
	 */
	private function short($value) {
		if (null === $value) {
			return '(null)';
		}
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}
		if (is_array($value) || is_object($value)) {
			$value = wp_json_encode($value);
		}
		$value = str_replace(array("\r", "\n"), ' ', (string) $value);
		if ('' === $value) {
			return '(empty)';
		}

		return strlen($value) > 90 ? substr($value, 0, 87) . '...' : $value;
	}

	/**
	 * Print one line.
	 *
	 * @param string $text
	 * @return void
	 */
	private function line($text = '') {
		echo $text . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	}

	/**
	 * Record an error.
	 *
	 * @param string $message
	 * @return void
	 */
	private function error($message) {
		$this->errors[] = $message;
		$this->line('[ERR] ' . $message);
	}

	/**
	 * Record a warning.
	 *
	 * @param string $message
	 * @return void
	 */
	private function warn($message) {
		$this->warnings[] = $message;
		$this->line('[WARN] ' . $message);
	}

	/* ------------------------------------------------------------------ */
	/* Backup (apply mode)                                                 */
	/* ------------------------------------------------------------------ */

	/**
	 * Create the backup file before the first write.
	 *
	 * @return bool
	 */
	private function start_backup() {
		if (!wp_mkdir_p($this->backup_dir) || !is_writable($this->backup_dir)) {
			$this->error('Backup directory is not writable: ' . $this->backup_dir . ' (pass backup-dir=/path).');
			return false;
		}

		if ($this->is_inside_wordpress($this->backup_dir)) {
			$this->error('Refusing to write backups inside the WordPress directory: ' . $this->backup_dir . ' (pass backup-dir=/path outside it).');
			return false;
		}

		$this->backup = array(
			'profile'     => self::BACKUP_PROFILE,
			'site_url'    => home_url(),
			'created_utc' => gmdate('c'),
			'rows'        => array(),
			'options'     => array(),
			'created'     => array(),
		);

		// Random suffix so two runs in the same second do not share a file.
		$this->backup_file = rtrim($this->backup_dir, '/\\') . DIRECTORY_SEPARATOR
			. 'devstacktips-id-backup-' . gmdate('Ymd-His') . '-' . wp_generate_password(6, false, false) . '.json';

		if (!$this->write_backup()) {
			$this->error('Could not write backup file: ' . $this->backup_file);
			return false;
		}

		$this->line('Backup file: ' . $this->backup_file);

		return true;
	}

	/**
	 * Write the backup payload to disk.
	 *
	 * @return bool
	 */
	private function write_backup() {
		if ('' === $this->backup_file) {
			return false;
		}

		$json = wp_json_encode($this->backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (false === $json) {
			return false;
		}

		// Atomic write: a partial write must never replace the last good backup.
		$payload = $json . "\n";
		$tmp     = $this->backup_file . '.tmp';
		if (strlen($payload) !== (int) file_put_contents($tmp, $payload)) {
			@unlink($tmp);
			return false;
		}
		if (!rename($tmp, $this->backup_file)) {
			@unlink($tmp);
			return false;
		}

		return true;
	}

	/**
	 * Record a newly created object in the backup payload.
	 *
	 * @param string $entity Entity type ('source_group', 'source').
	 * @param int    $id     Object ID.
	 * @param string $key    Stable key.
	 * @param string $name   Object name/label.
	 * @return void
	 */
	private function record_created($entity, $id, $key, $name) {
		if (!isset($this->backup['created'])) {
			$this->backup['created'] = array();
		}

		$this->backup['created'][] = array(
			'entity' => $entity,
			'id'     => (int) $id,
			'key'    => $key,
			'name'   => $name,
		);

		$this->write_backup();
	}

	/**
	 * Remember a field's value before the first change.
	 *
	 * @param string $entity
	 * @param int    $id
	 * @param string $field
	 * @param mixed  $old
	 * @return void
	 */
	private function backup_field($entity, $id, $field, $old) {
		$key = (string) (int) $id;
		if (!isset($this->backup['rows'][$entity][$key]) || !array_key_exists($field, $this->backup['rows'][$entity][$key])) {
			$this->backup['rows'][$entity][$key][$field] = $old;
		}
	}

	/**
	 * Remember an option's value before the first change.
	 *
	 * @param string $name
	 * @return void
	 */
	private function backup_option($name) {
		if (isset($this->backup['options'][$name])) {
			return;
		}

		$missing = new stdClass();
		$value   = get_option($name, $missing);

		$this->backup['options'][$name] = array(
			'existed' => ($value !== $missing),
			'value'   => ($value !== $missing) ? $value : null,
		);
	}

	/**
	 * Whether a directory is inside the WordPress install.
	 *
	 * @param string $dir
	 * @return bool
	 */
	private function is_inside_wordpress($dir) {
		$real_dir  = realpath($dir);
		$real_root = realpath(ABSPATH);

		if (!$real_dir || !$real_root) {
			return false;
		}

		$real_dir  = rtrim(str_replace('\\', '/', $real_dir), '/') . '/';
		$real_root = rtrim(str_replace('\\', '/', $real_root), '/') . '/';

		return 0 === strpos($real_dir, $real_root);
	}

	/**
	 * Print the apply summary.
	 *
	 * @return void
	 */
	private function print_apply_summary() {
		$this->line('');
		$this->line('=========================================');
		$this->line(sprintf('APPLIED: %d change(s), %d warning(s), %d error(s).', $this->applied, count($this->warnings), count($this->errors)));
		$this->line('Backup: ' . $this->backup_file);
		$this->line('=========================================');
	}
}

if (!defined('AIPS_DEVSTACKTIPS_CONFIG_SETUP_NO_RUN')) {
	if (!defined('WP_CLI') || !WP_CLI) {
		wp_die(esc_html__('The DevStackTips setup script can only be run from WP-CLI.', 'ai-post-scheduler'));
	}

	$aips_dst_args  = isset($args) && is_array($args) ? $args : array();
	$aips_dst_setup = new AIPS_DevStackTips_Config_Setup($aips_dst_args);

	if (!$aips_dst_setup->run()) {
		WP_CLI::error('DevStackTips setup did not complete.');
	}
}
