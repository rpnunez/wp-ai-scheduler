<?php
/**
 * DevStackTips Content Setup Script (v3)
 *
 * Configures AIPS for DevStackTips. v3 keeps the live configuration and only
 * changes the fields it manages, so settings and objects tuned in the admin
 * are left alone.
 *
 * What it does:
 * 1. Seed (create-only): categories, voices, prompt sections, article structures,
 *    post slices, source groups and sources, campaigns, authors, templates and
 *    schedules that do not exist yet. Existing objects are never overwritten
 *    by seed data. Settings from the seed are written only when missing.
 * 2. v3 managed fields on existing objects:
 *    - Templates: voice, AI slug + slug prompt, per-template title prompt,
 *      featured AI image (house style, no Unsplash), source groups.
 *    - Schedules: an article structure per schedule.
 *    - Authors: AI slug, featured AI image, category when missing; for the
 *      v2 authors also score-based topic approval and a daily 1-post cadence.
 *    - In-content AI images on tutorial, backend, architecture, refactoring
 *      and testing templates.
 *    - Settings: allowed image sources, image limit, AI assistance guidance.
 * 3. Re-checks the v2 authors' stalled pending topics against the new policy.
 *
 * Usage (WP-CLI only):
 *   Dry run (default, writes nothing, prints every change):
 *     wp eval-file wp-content/plugins/ai-post-scheduler/scripts/setup-devstacktips-content.php
 *   Apply (writes a JSON backup first):
 *     wp eval-file .../setup-devstacktips-content.php apply
 *   Apply and save template posts as drafts during rollout:
 *     wp eval-file .../setup-devstacktips-content.php apply drafts
 *   Switch template posts back to publish after the review week:
 *     wp eval-file .../setup-devstacktips-content.php apply publish
 *   Restore from a backup written by apply:
 *     wp eval-file .../setup-devstacktips-content.php rollback /path/to/backup.json
 *
 * Options:
 *   backup-dir=/path   Where apply writes backups. Default: ~/aips-backups
 *                      (system temp when HOME is not writable). Never inside
 *                      the WordPress directory.
 *
 * Rollback restores every field, setting and image configuration v3 changed
 * and deletes the objects it created. It does not undo topic approvals made by
 * the pending-topic re-check (approved topics can be bulk-rejected in the admin).
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	die('Direct access not permitted.');
}

/**
 * Class AIPS_DevStackTips_Setup
 */
class AIPS_DevStackTips_Setup {

	/**
	 * Strategy profile name, stored in backups.
	 */
	private const STRATEGY_PROFILE = 'devstacktips-production-v3';

	/**
	 * Shared visual style for every generated image.
	 */
	private const HOUSE_STYLE = 'Flat vector technical illustration, dark navy background with blue and teal accents, clean geometric shapes, subtle grid, professional developer aesthetic, no text, no letters, no logos, 16:9';

	/**
	 * Slot (site time) of the first v2 author's daily post run; later authors follow every AUTHOR_SLOT_GAP minutes.
	 */
	private const AUTHOR_FIRST_SLOT = '06:00';

	/**
	 * Minutes between staggered author post runs.
	 */
	private const AUTHOR_SLOT_GAP = 25;

	/**
	 * Pending topics the apply step re-checks per author.
	 */
	private const TOPIC_RECHECK_LIMIT = 200;

	/**
	 * Distribution configuration used by the seed data (campaign and author shares).
	 */
	private const DISTRIBUTION_CONFIG = array(
		'distribution_period' => 'weekly',
		'target_posts' => array(
			'daily' => 6,
			'weekly' => 42,
			'monthly' => 180,
		),
		'campaign_shares' => array(
			'Developer Foundations' => 4,
			'Backend Engineering' => 4,
			'Security First' => 4,
			'Framework & Tool Comparisons' => 4,
			'Developer Tooling' => 4,
			'AI for Developers' => 3,
			'PHP Ecosystem Radar' => 4,
			'Security Intelligence Briefing' => 3,
			'Software Architecture Patterns' => 4,
			'Refactoring & Code Smells' => 4,
			'Test Strategy & Automation' => 4,
		),
		'author_shares' => array(
			'Backend Architecture Specialist' => 6,
			'Security Expert' => 5,
			'DevOps Practitioner' => 5,
			'Framework Analyst' => 4,
			'AI Engineering Pragmatist' => 4,
			'Staff Software Architect' => 6,
			'Test Automation & Quality Specialist' => 6,
			'Code Quality & Refactoring Craftsman' => 6,
		),
	);

	/** @var string 'dry-run', 'apply' or 'rollback'. */
	private $mode = 'dry-run';

	/** @var string '', 'draft' or 'publish': template post status to set. */
	private $template_status = '';

	/** @var string Backup file to restore (rollback mode). */
	private $rollback_file = '';

	/** @var string Directory for backups. */
	private $backup_dir = '';

	/** @var string Backup file written by this apply run. */
	private $backup_file = '';

	/** @var array Seed data keyed by entity type. */
	private $object_data = array();

	/** @var array Name => ID maps of live objects, keyed by entity type. */
	private $ids = array(
		'categories'    => array(),
		'voices'        => array(),
		'structures'    => array(),
		'source_groups' => array(),
		'campaigns'     => array(),
		'templates'     => array(),
		'authors'       => array(),
		'schedules'     => array(),
	);

	/** @var array Planned or applied changes. */
	private $changes = array();

	/** @var array Objects planned or created. */
	private $creations = array();

	/** @var array Error messages. */
	private $errors = array();

	/** @var array Warning messages. */
	private $warnings = array();

	/** @var array Backup payload. */
	private $backup = array();

	/**
	 * @param array $args Positional WP-CLI arguments.
	 */
	public function __construct(array $args = array()) {
		$this->parse_args($args);
	}

	/**
	 * Whether this run writes to the database.
	 *
	 * @return bool
	 */
	private function is_apply() {
		return 'apply' === $this->mode;
	}

	/**
	 * Parse positional arguments.
	 *
	 * @param array $args
	 * @return void
	 */
	private function parse_args(array $args) {
		foreach ($args as $index => $arg) {
			$arg = (string) $arg;

			if ('apply' === $arg) {
				$this->mode = 'apply';
			} elseif ('rollback' === $arg) {
				$this->mode = 'rollback';
				$this->rollback_file = isset($args[$index + 1]) ? (string) $args[$index + 1] : '';
			} elseif ('drafts' === $arg) {
				$this->template_status = 'draft';
			} elseif ('publish' === $arg) {
				$this->template_status = 'publish';
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
	}

	/**
	 * Run the selected mode.
	 *
	 * @return bool True when the run finished without errors.
	 */
	public function run() {
		if ('rollback' === $this->mode) {
			return $this->rollback();
		}

		$this->line('=========================================');
		$this->line('DevStackTips Content Setup (' . self::STRATEGY_PROFILE . ')');
		$this->line('Mode: ' . ($this->is_apply() ? 'APPLY (writes changes)' : 'DRY RUN (no changes are written)'));
		$this->line('=========================================');

		$this->object_data = $this->build_seeding_object_data();

		if ($this->is_apply() && !$this->start_backup()) {
			return false;
		}

		$this->load_live_ids();

		$this->seed_categories();
		$this->seed_voices();
		$this->seed_structures();
		$this->seed_post_slices();
		$this->seed_source_groups_and_sources();
		$this->seed_campaigns();
		$this->seed_authors();
		$this->seed_templates();
		$this->seed_schedules();
		$this->seed_settings_if_missing();

		$this->apply_template_fields();
		$this->apply_schedule_fields();
		$this->apply_author_fields();
		$this->apply_post_images();
		$this->apply_v3_settings();
		$this->recheck_pending_topics();

		if ($this->is_apply()) {
			$this->write_backup();
		}

		$this->print_summary();

		return empty($this->errors);
	}

	/* ------------------------------------------------------------------ */
	/* Output helpers                                                      */
	/* ------------------------------------------------------------------ */

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
	 * Print a section heading.
	 *
	 * @param string $title
	 * @return void
	 */
	private function heading($title) {
		$this->line('');
		$this->line('--- ' . $title . ' ---');
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

	/* ------------------------------------------------------------------ */
	/* Comparison helpers                                                  */
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
		if (is_float($value) || is_int($value)) {
			return (string) (0 + $value);
		}
		if (is_numeric($value)) {
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

	/* ------------------------------------------------------------------ */
	/* Backup                                                              */
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
			'profile'     => self::STRATEGY_PROFILE,
			'site_url'    => home_url(),
			'created_utc' => gmdate('c'),
			'rows'        => array(),
			'options'     => array(),
			'post_images' => array(),
			'created'     => array(),
		);

		$this->backup_file = rtrim($this->backup_dir, '/\\') . DIRECTORY_SEPARATOR . 'devstacktips-v3-backup-' . gmdate('Ymd-His') . '.json';

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

		return false !== $json && false !== file_put_contents($this->backup_file, $json . "\n");
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
		$value = get_option($name, $missing);

		$this->backup['options'][$name] = array(
			'existed' => ($value !== $missing),
			'value'   => ($value !== $missing) ? $value : null,
		);
	}

	/**
	 * Record a created object (for rollback).
	 *
	 * @param string $entity
	 * @param int    $id
	 * @param string $name
	 * @return void
	 */
	private function record_created($entity, $id, $name) {
		$this->creations[] = array('entity' => $entity, 'id' => (int) $id, 'name' => $name);

		if ($this->is_apply()) {
			$this->backup['created'][] = array('entity' => $entity, 'id' => (int) $id, 'name' => $name);
			$this->write_backup();
		}
	}

	/**
	 * Record a planned creation in dry-run mode.
	 *
	 * @param string $entity
	 * @param string $name
	 * @return void
	 */
	private function plan_create($entity, $name) {
		$this->creations[] = array('entity' => $entity, 'id' => 0, 'name' => $name);
		$this->line("[PLAN] Would create {$entity}: {$name}");
	}

	/**
	 * Whether a directory is inside the WordPress install.
	 *
	 * @param string $dir
	 * @return bool
	 */
	private function is_inside_wordpress($dir) {
		$real_dir = realpath($dir);
		$real_root = realpath(ABSPATH);

		if (!$real_dir || !$real_root) {
			return false;
		}

		$real_dir = rtrim(str_replace('\\', '/', $real_dir), '/') . '/';
		$real_root = rtrim(str_replace('\\', '/', $real_root), '/') . '/';

		return 0 === strpos($real_dir, $real_root);
	}

	/* ------------------------------------------------------------------ */
	/* Live lookups                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Build name => ID maps from the live database.
	 *
	 * @return void
	 */
	private function load_live_ids() {
		foreach ($this->object_data['categories'] as $category) {
			$term = get_term_by('slug', $category['slug'], 'category');
			if ($term && !is_wp_error($term)) {
				$this->ids['categories'][$category['name']] = (int) $term->term_id;
			}
		}

		foreach ((new AIPS_Voices_Repository())->get_all() as $row) {
			$this->ids['voices'][(string) $row->name] = (int) $row->id;
		}

		foreach ((new AIPS_Article_Structure_Repository())->get_all() as $row) {
			$this->ids['structures'][(string) $row->name] = (int) $row->id;
		}

		foreach ($this->get_all_source_group_seeds() as $group) {
			$term = get_term_by('slug', $group['slug'], 'aips_source_group');
			if ($term && !is_wp_error($term)) {
				$this->ids['source_groups'][$group['slug']] = (int) $term->term_id;
			}
		}

		foreach ((new AIPS_Campaigns_Repository())->get_campaigns(null, null) as $row) {
			$this->ids['campaigns'][(string) $row->name] = (int) $row->id;
		}

		foreach ((new AIPS_Template_Repository())->get_all() as $row) {
			$this->ids['templates'][(string) $row->name] = (int) $row->id;
		}

		foreach ((new AIPS_Authors_Repository())->get_all() as $row) {
			$this->ids['authors'][(string) $row->name] = (int) $row->id;
		}

		foreach ((new AIPS_Schedule_Repository())->get_all(false) as $row) {
			if (!empty($row->title)) {
				$this->ids['schedules'][(string) $row->title] = (int) $row->id;
			}
		}
	}

	/**
	 * Category term ID by seed name, or by live category name.
	 *
	 * @param string $name
	 * @return int
	 */
	private function category_id($name) {
		if (isset($this->ids['categories'][$name])) {
			return (int) $this->ids['categories'][$name];
		}

		$id = (int) get_cat_ID($name);
		if ($id > 0) {
			$this->ids['categories'][$name] = $id;
		}

		return $id;
	}

	/* ------------------------------------------------------------------ */
	/* Seed: create-only                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Create missing categories.
	 *
	 * @return void
	 */
	private function seed_categories() {
		$this->heading('Seed: categories (create missing only)');

		foreach ($this->object_data['categories'] as $category) {
			if (isset($this->ids['categories'][$category['name']])) {
				continue;
			}

			if (!$this->is_apply()) {
				$this->plan_create('category', $category['name']);
				continue;
			}

			$result = wp_insert_term($category['name'], 'category', array(
				'slug'        => $category['slug'],
				'description' => $category['description'],
			));

			if (is_wp_error($result)) {
				$this->error('Failed to create category ' . $category['name'] . ': ' . $result->get_error_message());
				continue;
			}

			$this->ids['categories'][$category['name']] = (int) $result['term_id'];
			$this->record_created('category', (int) $result['term_id'], $category['name']);
			$this->line('[OK] Created category: ' . $category['name']);
		}
	}

	/**
	 * Create missing voices.
	 *
	 * @return void
	 */
	private function seed_voices() {
		$this->heading('Seed: voices (create missing only)');
		$repo = new AIPS_Voices_Repository();

		foreach ($this->object_data['voices'] as $voice) {
			if (isset($this->ids['voices'][$voice['name']])) {
				continue;
			}

			if (isset($voice['content_instructions']) && is_array($voice['content_instructions'])) {
				$voice['content_instructions'] = implode("\n", $voice['content_instructions']);
			}

			if (!$this->is_apply()) {
				$this->plan_create('voice', $voice['name']);
				continue;
			}

			$id = $repo->create($voice);
			if (!$id) {
				$this->error('Failed to create voice: ' . $voice['name']);
				continue;
			}

			$this->ids['voices'][$voice['name']] = (int) $id;
			$this->record_created('voice', (int) $id, $voice['name']);
			$this->line('[OK] Created voice: ' . $voice['name']);
		}
	}

	/**
	 * Create missing prompt sections and article structures.
	 *
	 * @return void
	 */
	private function seed_structures() {
		$this->heading('Seed: prompt sections and article structures (create missing only)');
		$structure_repo = new AIPS_Article_Structure_Repository();
		$section_repo = new AIPS_Prompt_Section_Repository();

		foreach ($this->object_data['sections'] as $section) {
			if ($section_repo->get_by_key($section['section_key'])) {
				continue;
			}

			if (!$this->is_apply()) {
				$this->plan_create('prompt section', $section['name']);
				continue;
			}

			$id = $section_repo->create($section);
			if ($id) {
				$this->record_created('prompt_section', (int) $id, $section['name']);
				$this->line('[OK] Created prompt section: ' . $section['name']);
			} else {
				$this->error('Failed to create prompt section: ' . $section['name']);
			}
		}

		foreach ($this->object_data['structures'] as $structure) {
			if (isset($this->ids['structures'][$structure['name']])) {
				continue;
			}

			if (!$this->is_apply()) {
				$this->plan_create('article structure', $structure['name']);
				continue;
			}

			$id = $structure_repo->create(array(
				'name'           => $structure['name'],
				'description'    => $structure['description'],
				'structure_data' => wp_json_encode(array(
					'sections'        => $structure['sections'],
					'prompt_template' => '',
				)),
				'is_active'      => 1,
			));

			if (!$id) {
				$this->error('Failed to create article structure: ' . $structure['name']);
				continue;
			}

			$this->ids['structures'][$structure['name']] = (int) $id;
			$this->record_created('structure', (int) $id, $structure['name']);
			$this->line('[OK] Created article structure: ' . $structure['name']);
		}
	}

	/**
	 * Create missing post slices.
	 *
	 * @return void
	 */
	private function seed_post_slices() {
		$this->heading('Seed: post slices (create missing only)');
		$repo = new AIPS_Post_Slices_Repository();
		$existing = array();

		foreach ($repo->get_all(false) as $row) {
			$existing[(string) $row->name] = (int) $row->id;
		}

		$now = AIPS_DateTime::now()->timestamp();

		foreach ($this->object_data['post_slices'] as $slice) {
			if (isset($existing[$slice['name']])) {
				continue;
			}

			if (!$this->is_apply()) {
				$this->plan_create('post slice', $slice['name']);
				continue;
			}

			$id = $repo->create(array(
				'name'        => $slice['name'],
				'description' => $slice['description'],
				'sort_order'  => $slice['sort_order'],
				'is_active'   => $slice['is_active'],
				'created_at'  => $now,
				'updated_at'  => $now,
			));

			if ($id) {
				$this->record_created('post_slice', (int) $id, $slice['name']);
				$this->line('[OK] Created post slice: ' . $slice['name']);
			} else {
				$this->error('Failed to create post slice: ' . $slice['name']);
			}
		}
	}

	/**
	 * Create missing source groups (v2 + v3) and their sources.
	 *
	 * @return void
	 */
	private function seed_source_groups_and_sources() {
		$this->heading('Seed: source groups and sources (create missing only)');
		$sources_repo = new AIPS_Sources_Repository();

		foreach ($this->get_all_source_group_seeds() as $group) {
			if (isset($this->ids['source_groups'][$group['slug']])) {
				continue;
			}

			if (!$this->is_apply()) {
				$this->plan_create('source group', $group['name']);
				continue;
			}

			$result = wp_insert_term($group['name'], 'aips_source_group', array(
				'slug'        => $group['slug'],
				'description' => $group['description'],
			));

			if (is_wp_error($result)) {
				$this->error('Failed to create source group ' . $group['name'] . ': ' . $result->get_error_message());
				continue;
			}

			$this->ids['source_groups'][$group['slug']] = (int) $result['term_id'];
			$this->record_created('source_group', (int) $result['term_id'], $group['name']);
			$this->line('[OK] Created source group: ' . $group['name']);
		}

		foreach ($this->get_all_source_seeds() as $source) {
			if ($sources_repo->url_exists($source['url'])) {
				continue;
			}

			if (!$this->is_apply()) {
				$this->plan_create('source', $source['name'] . ' (' . $source['url'] . ')');
				continue;
			}

			if (!isset($this->ids['source_groups'][$source['group_slug']])) {
				$this->warn('Skipped source ' . $source['name'] . ': source group ' . $source['group_slug'] . ' is missing.');
				continue;
			}

			$id = $sources_repo->create(array(
				'url'       => $source['url'],
				'label'     => $source['name'],
				'is_active' => $source['is_active'],
			));

			if (!$id) {
				$this->error('Failed to create source: ' . $source['name']);
				continue;
			}

			$sources_repo->set_source_terms($id, array((int) $this->ids['source_groups'][$source['group_slug']]));
			$sources_repo->set_fetch_schedule($id, 'daily');
			$this->record_created('source', (int) $id, $source['name']);
			$this->line('[OK] Created source: ' . $source['name']);
		}
	}

	/**
	 * Create missing campaigns.
	 *
	 * @return void
	 */
	private function seed_campaigns() {
		$this->heading('Seed: campaigns (create missing only)');
		$repo = new AIPS_Campaigns_Repository();

		foreach ($this->object_data['campaigns'] as $campaign) {
			if (isset($this->ids['campaigns'][$campaign['name']])) {
				continue;
			}

			if (!$this->is_apply()) {
				$this->plan_create('campaign', $campaign['name']);
				continue;
			}

			$id = $repo->create_campaign(array(
				'name'          => $campaign['name'],
				'content_goal'  => $campaign['content_goal'],
				'campaign_mode' => 'template',
				'is_active'     => 1,
				'is_archived'   => 0,
			));

			if (!$id || is_wp_error($id)) {
				$this->error('Failed to create campaign: ' . $campaign['name']);
				continue;
			}

			$this->ids['campaigns'][$campaign['name']] = (int) $id;
			$this->record_created('campaign', (int) $id, $campaign['name']);
			$this->line('[OK] Created campaign: ' . $campaign['name']);
		}
	}

	/**
	 * Create missing authors from the v2 seed.
	 *
	 * @return void
	 */
	private function seed_authors() {
		$this->heading('Seed: authors (create missing only)');
		$repo = new AIPS_Authors_Repository();
		$interval = AIPS_Interval_Calculator::instance();
		$now = AIPS_DateTime::now()->timestamp();
		$post_author = $this->resolve_post_author();
		$index = 0;

		foreach ($this->object_data['authors'] as $author) {
			$index++;
			if (isset($this->ids['authors'][$author['name']])) {
				continue;
			}

			if (!$this->is_apply()) {
				$this->plan_create('author', $author['name']);
				continue;
			}

			$group_ids = array();
			if (!empty($author['source_group_name'])) {
				foreach ($this->get_all_source_group_seeds() as $group) {
					if ($group['name'] === $author['source_group_name'] && isset($this->ids['source_groups'][$group['slug']])) {
						$group_ids[] = (int) $this->ids['source_groups'][$group['slug']];
					}
				}
			}

			$stagger = $index * 600;
			$id = $repo->create(array(
				'name'                               => $author['name'],
				'field_niche'                        => $author['field_niche'],
				'keywords'                           => $author['keywords'],
				'description'                        => $author['description'],
				'details'                            => $author['details'],
				'article_structure_id'               => isset($this->ids['structures'][$author['structure_name']]) ? $this->ids['structures'][$author['structure_name']] : null,
				'voice_tone'                         => $author['voice_tone'],
				'writing_style'                      => $author['writing_style'],
				'target_audience'                    => $author['target_audience'],
				'expertise_level'                    => $author['expertise_level'],
				'content_goals'                      => $author['content_goals'],
				'excluded_topics'                    => $author['excluded_topics'],
				'preferred_content_length'           => $author['preferred_content_length'],
				'language'                           => 'en',
				'post_status'                        => 'draft',
				'post_category'                      => $this->category_id($author['category_name']),
				'post_author'                        => $post_author,
				'featured_image_source'              => 'ai_prompt',
				'topic_generation_frequency'         => $author['topic_generation_frequency'],
				'topic_generation_quantity'          => $author['topic_generation_quantity'],
				'topic_generation_next_run'          => $interval->calculate_next_run($author['topic_generation_frequency'], $now + $stagger),
				'topic_generation_last_run'          => 0,
				'topic_generation_is_active'         => 1,
				'post_generation_frequency'          => $author['post_generation_frequency'],
				'post_generation_next_run'           => $interval->calculate_next_run($author['post_generation_frequency'], $now + $stagger + 300),
				'post_generation_last_run'           => 0,
				'post_generation_is_active'          => 1,
				'max_posts_per_topic'                => $author['max_posts_per_topic'],
				'manual_post_generation_quantity'    => $author['manual_post_generation_quantity'],
				'scheduled_post_generation_quantity' => $author['scheduled_post_generation_quantity'],
				'include_sources'                    => !empty($group_ids) ? 1 : 0,
				'source_group_ids'                   => wp_json_encode($group_ids),
				'is_active'                          => $author['is_active'],
				'created_at'                         => $now,
				'updated_at'                         => $now,
			));

			if (!$id) {
				$this->error('Failed to create author: ' . $author['name']);
				continue;
			}

			$this->ids['authors'][$author['name']] = (int) $id;
			$this->record_created('author', (int) $id, $author['name']);
			$this->line('[OK] Created author: ' . $author['name']);
		}
	}

	/**
	 * Create missing templates from the v2 seed (v3 fields are applied afterwards).
	 *
	 * @return void
	 */
	private function seed_templates() {
		$this->heading('Seed: templates (create missing only)');
		$repo = new AIPS_Template_Repository();
		$post_author = $this->resolve_post_author();

		foreach ($this->object_data['templates'] as $template) {
			if (isset($this->ids['templates'][$template['name']])) {
				continue;
			}

			if (!$this->is_apply()) {
				$this->plan_create('template', $template['name']);
				continue;
			}

			$campaign_id = isset($this->ids['campaigns'][$template['campaign_name']]) ? $this->ids['campaigns'][$template['campaign_name']] : 0;
			if (!$campaign_id) {
				$this->error('Campaign not found for template ' . $template['name'] . ' (' . $template['campaign_name'] . ')');
				continue;
			}

			$category_ids = array();
			foreach ((array) $template['categories'] as $category_name) {
				$category_id = $this->category_id($category_name);
				if ($category_id) {
					$category_ids[] = $category_id;
				}
			}

			$id = $repo->create(array(
				'name'                    => $template['name'],
				'prompt_template'         => $template['prompt_template'],
				'title_prompt'            => 'Generate a concise, specific, SEO-friendly technical post title for this topic.',
				'voice_id'                => isset($this->ids['voices'][$template['voice_name']]) ? $this->ids['voices'][$template['voice_name']] : null,
				'post_quantity'           => isset($template['post_quantity']) ? $template['post_quantity'] : 1,
				'post_status'             => 'draft',
				'post_type'               => 'post',
				'post_category'           => !empty($category_ids) ? $category_ids[0] : 0,
				'post_tags'               => isset($template['post_tags']) ? $template['post_tags'] : '',
				'post_author'             => $post_author,
				'generate_featured_image' => 0,
				'featured_image_source'   => 'ai_prompt',
				'image_prompt'            => isset($template['image_prompt']) ? $template['image_prompt'] : '',
				'include_sources'         => 0,
				'source_group_ids'        => wp_json_encode(array()),
				'campaign_id'             => $campaign_id,
				'is_active'               => 1,
			));

			if (!$id) {
				$this->error('Failed to create template: ' . $template['name']);
				continue;
			}

			$this->ids['templates'][$template['name']] = (int) $id;
			$this->record_created('template', (int) $id, $template['name']);
			$this->line('[OK] Created template: ' . $template['name']);
		}
	}

	/**
	 * Create missing schedules from the v2 seed.
	 *
	 * @return void
	 */
	private function seed_schedules() {
		$this->heading('Seed: schedules (create missing only)');
		$repo = new AIPS_Schedule_Repository();

		foreach ($this->object_data['schedules'] as $schedule) {
			if (isset($this->ids['schedules'][$schedule['title']])) {
				continue;
			}

			if (!$this->is_apply()) {
				$this->plan_create('schedule', $schedule['title']);
				continue;
			}

			if (!isset($this->ids['templates'][$schedule['template_name']])) {
				$this->error('Template not found for schedule ' . $schedule['title']);
				continue;
			}

			$id = $repo->create(array(
				'template_id'   => $this->ids['templates'][$schedule['template_name']],
				'title'         => $schedule['title'],
				'frequency'     => $schedule['frequency'],
				'next_run'      => $this->next_weekday_timestamp((int) $schedule['weekday'], $schedule['start_time']),
				'last_run'      => 0,
				'is_active'     => $schedule['is_active'],
				'status'        => !empty($schedule['is_active']) ? 'active' : 'inactive',
				'schedule_type' => 'post_generation',
			));

			if (!$id) {
				$this->error('Failed to create schedule: ' . $schedule['title']);
				continue;
			}

			$this->ids['schedules'][$schedule['title']] = (int) $id;
			$this->record_created('schedule', (int) $id, $schedule['title']);
			$this->line('[OK] Created schedule: ' . $schedule['title']);
		}
	}

	/**
	 * Write v2 seed settings that are not stored yet. Stored values are never changed here.
	 *
	 * @return void
	 */
	private function seed_settings_if_missing() {
		$this->heading('Seed: settings (only options that are not stored yet)');
		$missing = new stdClass();

		foreach ($this->object_data['settings'] as $section) {
			if (empty($section['options']) || !is_array($section['options'])) {
				continue;
			}

			foreach ($section['options'] as $entry) {
				$name = $entry['option_name'];
				if (get_option($name, $missing) !== $missing) {
					continue;
				}

				$value = $this->resolve_seed_setting_value($entry);
				if (null === $value) {
					continue;
				}

				$this->set_option($name, $value, 'seed');
			}
		}
	}

	/**
	 * Resolve a v2 settings entry (static value, structure or category reference).
	 *
	 * @param array $entry
	 * @return mixed|null
	 */
	private function resolve_seed_setting_value(array $entry) {
		if (array_key_exists('value', $entry)) {
			return $entry['value'];
		}
		if (isset($entry['structure_name'])) {
			return isset($this->ids['structures'][$entry['structure_name']]) ? $this->ids['structures'][$entry['structure_name']] : null;
		}
		if (isset($entry['category_name'])) {
			$id = $this->category_id($entry['category_name']);
			return $id ? $id : null;
		}

		return null;
	}

	/* ------------------------------------------------------------------ */
	/* v3 managed fields                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Compare desired fields with a live row and write the differences.
	 *
	 * @param string   $entity  'template', 'schedule' or 'author'.
	 * @param int      $id      Row ID.
	 * @param string   $label   Display name.
	 * @param object   $live    Live row.
	 * @param array    $desired Field => value.
	 * @return void
	 */
	private function apply_fields($entity, $id, $label, $live, array $desired) {
		$diff = array();

		foreach ($desired as $field => $value) {
			$old = isset($live->$field) ? $live->$field : null;
			if (!$this->same($old, $value)) {
				$diff[$field] = array('old' => $old, 'new' => $value);
			}
		}

		if (empty($diff)) {
			$this->line("[OK] {$label}: already up to date");
			return;
		}

		foreach ($diff as $field => $values) {
			$this->changes[] = array('entity' => $entity, 'id' => (int) $id, 'name' => $label, 'field' => $field);
			$this->line(sprintf('[%s] %s: %s: %s -> %s', $this->is_apply() ? 'SET' : 'PLAN', $label, $field, $this->short($values['old']), $this->short($values['new'])));
		}

		if (!$this->is_apply()) {
			return;
		}

		$update = array();
		foreach ($diff as $field => $values) {
			$this->backup_field($entity, $id, $field, $values['old']);
			$update[$field] = $values['new'];
		}
		$this->write_backup();

		if (false === $this->repository_update($entity, $id, $update)) {
			$this->error("Failed to update {$entity} {$label}");
		}
	}

	/**
	 * Update a row through its repository.
	 *
	 * @param string $entity
	 * @param int    $id
	 * @param array  $data
	 * @return mixed False on failure.
	 */
	private function repository_update($entity, $id, array $data) {
		switch ($entity) {
			case 'template':
				return (new AIPS_Template_Repository())->update($id, $data);
			case 'schedule':
				return (new AIPS_Schedule_Repository())->update($id, $data);
			case 'author':
				return (new AIPS_Authors_Repository())->update($id, $data);
		}

		return false;
	}

	/**
	 * Fetch a live row by ID.
	 *
	 * @param string $entity
	 * @param int    $id
	 * @return object|null
	 */
	private function get_live_row($entity, $id) {
		switch ($entity) {
			case 'template':
				return (new AIPS_Template_Repository())->get_by_id($id);
			case 'schedule':
				return (new AIPS_Schedule_Repository())->get_by_id($id);
			case 'author':
				return (new AIPS_Authors_Repository())->get_by_id($id);
		}

		return null;
	}

	/**
	 * Apply v3 template fields.
	 *
	 * @return void
	 */
	private function apply_template_fields() {
		$this->heading('v3: templates (voice, slug, title prompt, featured image, sources)');

		foreach ($this->get_v3_template_config() as $name => $config) {
			if (empty($this->ids['templates'][$name])) {
				$this->line("[SKIP] Template not found: {$name}" . ($this->is_apply() ? '' : ' (would be created by the seed step)'));
				continue;
			}

			$id = (int) $this->ids['templates'][$name];
			$live = $this->get_live_row('template', $id);
			if (!$live) {
				$this->warn("Template {$name} (#{$id}) could not be loaded.");
				continue;
			}

			$desired = array(
				'generate_slug'           => 1,
				'slug_prompt'             => $this->get_slug_prompt($config['slug_type']),
				'title_prompt'            => $config['title_prompt'],
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
			);

			if (isset($this->ids['voices'][$config['voice']])) {
				$desired['voice_id'] = (int) $this->ids['voices'][$config['voice']];
			} else {
				$this->warn("Voice not found for template {$name}: {$config['voice']}");
			}

			// Keep a hand-written featured image prompt; fill only an empty one.
			if ('' === trim((string) $live->image_prompt)) {
				$desired['image_prompt'] = $this->featured_image_prompt($config['image_subject']);
			}

			if (!empty($config['source_group'])) {
				if (isset($this->ids['source_groups'][$config['source_group']])) {
					$desired['include_sources'] = 1;
					$desired['source_group_ids'] = wp_json_encode(array((int) $this->ids['source_groups'][$config['source_group']]));
				} else {
					$this->line("[SKIP] {$name}: source group {$config['source_group']} not created yet" . ($this->is_apply() ? '' : ' (created on apply)'));
				}
			}

			if ('' !== $this->template_status) {
				$desired['post_status'] = $this->template_status;
			}

			$this->apply_fields('template', $id, 'Template ' . $name, $live, $desired);
		}
	}

	/**
	 * Apply v3 schedule fields (one article structure per schedule).
	 *
	 * @return void
	 */
	private function apply_schedule_fields() {
		$this->heading('v3: schedules (article structure per schedule)');
		$structure_by_template = array();

		foreach ($this->get_v3_template_config() as $name => $config) {
			$structure_by_template[$name] = $config['structure'];
		}

		foreach ($this->object_data['schedules'] as $schedule) {
			$title = $schedule['title'];
			if (empty($this->ids['schedules'][$title])) {
				$this->line("[SKIP] Schedule not found: {$title}");
				continue;
			}

			$structure_name = isset($structure_by_template[$schedule['template_name']]) ? $structure_by_template[$schedule['template_name']] : '';
			if (!$structure_name || empty($this->ids['structures'][$structure_name])) {
				$this->warn("Article structure not found for schedule {$title}: {$structure_name}");
				continue;
			}

			$id = (int) $this->ids['schedules'][$title];
			$live = $this->get_live_row('schedule', $id);
			if (!$live) {
				$this->warn("Schedule {$title} (#{$id}) could not be loaded.");
				continue;
			}

			$this->apply_fields('schedule', $id, 'Schedule ' . $title, $live, array(
				'article_structure_id' => (int) $this->ids['structures'][$structure_name],
			));
		}
	}

	/**
	 * Apply v3 author fields.
	 *
	 * @return void
	 */
	private function apply_author_fields() {
		$this->heading('v3: authors (slug, featured image, category, approval, cadence)');
		$slot = 0;

		foreach ($this->get_v3_author_config() as $name => $config) {
			if (empty($this->ids['authors'][$name])) {
				$this->line("[SKIP] Author not found: {$name}");
				continue;
			}

			$id = (int) $this->ids['authors'][$name];
			$live = $this->get_live_row('author', $id);
			if (!$live) {
				$this->warn("Author {$name} (#{$id}) could not be loaded.");
				continue;
			}

			$desired = array(
				'generate_slug'           => 1,
				'slug_prompt'             => $this->get_slug_prompt($config['slug_type']),
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
			);

			if ('' === trim((string) $live->image_prompt)) {
				$desired['image_prompt'] = $this->featured_image_prompt($config['image_subject']);
			}

			if (empty($live->post_category) && !empty($config['category'])) {
				$category_id = $this->category_id($config['category']);
				if ($category_id) {
					$desired['post_category'] = $category_id;
				} else {
					$this->warn("Category not found for author {$name}: {$config['category']}");
				}
			}

			if (!empty($config['source_group']) && isset($this->ids['source_groups'][$config['source_group']])) {
				$desired['include_sources'] = 1;
				$desired['source_group_ids'] = wp_json_encode(array((int) $this->ids['source_groups'][$config['source_group']]));
			}

			if (!empty($config['v2_author'])) {
				$desired['topic_auto_approval_mode'] = 'score';
				$desired['topic_auto_approval_min_score'] = 85;
				$desired['topic_auto_approval_fallback'] = 'pending';
				$desired['topic_generation_quantity'] = 8;
				$desired['scheduled_post_generation_quantity'] = 1;
				$desired['post_generation_frequency'] = 'daily';

				// Only move the next run when the cadence actually changes, so a
				// re-run does not keep shifting the schedule.
				if (!$this->same(isset($live->post_generation_frequency) ? $live->post_generation_frequency : '', 'daily')) {
					$desired['post_generation_next_run'] = $this->next_daily_timestamp(self::AUTHOR_FIRST_SLOT, $slot * self::AUTHOR_SLOT_GAP);
				}

				$slot++;
			}

			$this->apply_fields('author', $id, 'Author ' . $name, $live, $desired);
		}
	}

	/**
	 * Apply in-content image configurations to templates.
	 *
	 * @return void
	 */
	private function apply_post_images() {
		$this->heading('v3: in-content AI images (templates)');
		$repo = new AIPS_Post_Images_Repository();

		foreach ($this->get_v3_template_config() as $name => $config) {
			if (empty($config['content_images'])) {
				continue;
			}

			if (empty($this->ids['templates'][$name])) {
				$this->line("[SKIP] Template not found: {$name}");
				continue;
			}

			$id = (int) $this->ids['templates'][$name];
			$live_rows = (array) $repo->get_content_images_for_entity('template', $id);

			$desired = array();
			foreach (array_values($config['content_images']) as $index => $image) {
				$desired[] = array(
					'id'           => isset($live_rows[$index]->id) ? (int) $live_rows[$index]->id : 0,
					'image_order'  => $index + 1,
					'source'       => 'ai_prompt',
					'image_prompt' => self::HOUSE_STYLE . '. ' . $image['prompt'],
					'placement'    => $image['placement'],
					'caption'      => '',
				);
			}

			$live_compare = array();
			foreach ($live_rows as $row) {
				$live_compare[] = array((string) $row->source, (string) $row->image_prompt, (string) $row->placement);
			}
			$desired_compare = array();
			foreach ($desired as $row) {
				$desired_compare[] = array($row['source'], $row['image_prompt'], $row['placement']);
			}

			if ($live_compare === $desired_compare) {
				$this->line("[OK] Template {$name}: in-content images already up to date");
				continue;
			}

			$this->changes[] = array('entity' => 'post_images', 'id' => $id, 'name' => 'Template ' . $name, 'field' => 'content_images');
			$this->line(sprintf('[%s] Template %s: in-content images: %d -> %d (%s)', $this->is_apply() ? 'SET' : 'PLAN', $name, count($live_rows), count($desired), implode(', ', wp_list_pluck($desired, 'placement'))));

			if (!$this->is_apply()) {
				continue;
			}

			$key = 'template:' . $id;
			if (!isset($this->backup['post_images'][$key])) {
				$this->backup['post_images'][$key] = array_map(
					function ($row) {
						return array(
							'image_order'       => (int) $row->image_order,
							'source'            => (string) $row->source,
							'image_prompt'      => (string) $row->image_prompt,
							'unsplash_keywords' => (string) $row->unsplash_keywords,
							'media_ids'         => (string) $row->media_ids,
							'placement'         => (string) $row->placement,
							'caption'           => (string) $row->caption,
						);
					},
					$live_rows
				);
				$this->write_backup();
			}

			$repo->sync_for_entity('template', $id, $desired, 'content');
		}
	}

	/**
	 * Apply v3 settings.
	 *
	 * @return void
	 */
	private function apply_v3_settings() {
		$this->heading('v3: settings');

		foreach ($this->get_v3_settings() as $name => $value) {
			$current = get_option($name, null);
			if ($this->same($current, $value)) {
				$this->line("[OK] {$name}: already up to date");
				continue;
			}

			$this->set_option($name, $value, 'v3');
		}
	}

	/**
	 * Write (or plan) an option change.
	 *
	 * @param string $name
	 * @param mixed  $value
	 * @param string $reason 'seed' or 'v3'.
	 * @return void
	 */
	private function set_option($name, $value, $reason) {
		$current = get_option($name, null);
		$this->changes[] = array('entity' => 'option', 'id' => 0, 'name' => $name, 'field' => $reason);
		$this->line(sprintf('[%s] %s: %s -> %s', $this->is_apply() ? 'SET' : 'PLAN', $name, $this->short($current), $this->short($value)));

		if (!$this->is_apply()) {
			return;
		}

		$this->backup_option($name);
		$this->write_backup();
		update_option($name, $value);
	}

	/**
	 * Re-check the v2 authors' pending topics against their new approval policy.
	 *
	 * @return void
	 */
	private function recheck_pending_topics() {
		$this->heading('v3: pending topic re-check (v2 authors)');
		$topics_repo = new AIPS_Author_Topics_Repository();
		$generator = null;

		foreach ($this->get_v3_author_config() as $name => $config) {
			if (empty($config['v2_author']) || empty($this->ids['authors'][$name])) {
				continue;
			}

			$author_id = (int) $this->ids['authors'][$name];

			if (!$this->is_apply()) {
				$eligible = 0;
				$qualifying = 0;
				foreach ($topics_repo->get_pending_for_approval_recheck($author_id, 500) as $row) {
					$meta = !empty($row->metadata) ? json_decode($row->metadata, true) : array();
					$meta = is_array($meta) ? $meta : array();
					if (!empty($meta['auto_approval_rule']) && (!isset($meta['auto_approval_reason']) || 'relevance_unavailable' !== $meta['auto_approval_reason'])) {
						continue;
					}
					$eligible++;
					if ((int) $row->score >= 85) {
						$qualifying++;
					}
				}
				$this->line("[PLAN] Author {$name}: {$eligible} pending topic(s) eligible for re-check, about {$qualifying} at score 85 or above would be approved");
				continue;
			}

			$author = (new AIPS_Authors_Repository())->get_by_id($author_id);
			if (!$author) {
				continue;
			}

			if (null === $generator) {
				$generator = new AIPS_Author_Topics_Generator();
			}

			$counts = $generator->recheck_pending_auto_approval($author, self::TOPIC_RECHECK_LIMIT);
			$this->line(sprintf('[SET] Author %s: re-checked %d pending topic(s): %d approved, %d rejected', $name, $counts['checked'], $counts['approved'], $counts['rejected']));
		}
	}

	/* ------------------------------------------------------------------ */
	/* Rollback                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Restore everything recorded in a backup file.
	 *
	 * @return bool
	 */
	private function rollback() {
		$this->line('=========================================');
		$this->line('DevStackTips Content Setup: ROLLBACK');
		$this->line('=========================================');

		if ('' === $this->rollback_file || !is_readable($this->rollback_file)) {
			$this->error('Pass a readable backup file: rollback /path/to/devstacktips-v3-backup-*.json');
			return false;
		}

		$backup = json_decode((string) file_get_contents($this->rollback_file), true);
		if (!is_array($backup) || empty($backup['profile']) || self::STRATEGY_PROFILE !== $backup['profile']) {
			$this->error('Not a ' . self::STRATEGY_PROFILE . ' backup: ' . $this->rollback_file);
			return false;
		}

		if (!empty($backup['site_url']) && home_url() !== $backup['site_url']) {
			$this->error('Backup was made on ' . $backup['site_url'] . ', not ' . home_url() . '. Refusing to restore.');
			return false;
		}

		$this->heading('Restoring fields');
		foreach ((array) $backup['rows'] as $entity => $rows) {
			foreach ((array) $rows as $id => $fields) {
				$data = array();
				foreach ((array) $fields as $field => $old) {
					// Repository update() skips null values; 0/'' clear the column instead.
					if (null === $old) {
						$old = ('_id' === substr($field, -3) || 'post_category' === $field) ? 0 : '';
					}
					$data[$field] = $old;
				}

				if (false === $this->repository_update($entity, (int) $id, $data)) {
					$this->error("Failed to restore {$entity} #{$id}");
					continue;
				}
				$this->line("[OK] Restored {$entity} #{$id}: " . implode(', ', array_keys($data)));
			}
		}

		$this->heading('Restoring in-content image configurations');
		$images_repo = new AIPS_Post_Images_Repository();
		foreach ((array) $backup['post_images'] as $key => $rows) {
			list($type, $id) = array_pad(explode(':', (string) $key, 2), 2, 0);
			$images_repo->sync_for_entity($type, (int) $id, (array) $rows, 'content');
			$this->line("[OK] Restored images for {$type} #{$id} (" . count((array) $rows) . ' row(s))');
		}

		$this->heading('Restoring settings');
		foreach ((array) $backup['options'] as $name => $state) {
			if (!empty($state['existed'])) {
				update_option($name, $state['value']);
				$this->line("[OK] Restored {$name}");
			} else {
				delete_option($name);
				$this->line("[OK] Removed {$name} (it did not exist before)");
			}
		}

		$this->heading('Deleting created objects');
		foreach (array_reverse((array) $backup['created']) as $item) {
			$this->delete_created($item['entity'], (int) $item['id'], (string) $item['name']);
		}

		$this->line('');
		$this->line('Topic approvals made by the pending-topic re-check are not undone; bulk-reject them in Authors > Topics if needed.');
		$this->line(empty($this->errors) ? 'Rollback complete.' : 'Rollback finished with ' . count($this->errors) . ' error(s).');

		return empty($this->errors);
	}

	/**
	 * Delete one object created by an apply run.
	 *
	 * @param string $entity
	 * @param int    $id
	 * @param string $name
	 * @return void
	 */
	private function delete_created($entity, $id, $name) {
		$result = false;

		switch ($entity) {
			case 'category':
				$result = wp_delete_term($id, 'category');
				break;
			case 'source_group':
				$result = wp_delete_term($id, 'aips_source_group');
				break;
			case 'source':
				$sources = new AIPS_Sources_Repository();
				$sources->delete_source_terms($id);
				$result = $sources->delete($id);
				break;
			case 'voice':
				$result = (new AIPS_Voices_Repository())->delete($id);
				break;
			case 'prompt_section':
				$result = (new AIPS_Prompt_Section_Repository())->delete($id);
				break;
			case 'structure':
				$result = (new AIPS_Article_Structure_Repository())->delete($id);
				break;
			case 'post_slice':
				$result = (new AIPS_Post_Slices_Repository())->delete($id);
				break;
			case 'campaign':
				$result = (new AIPS_Campaigns_Repository())->delete_campaign($id);
				break;
			case 'author':
				$result = (new AIPS_Authors_Repository())->delete($id);
				break;
			case 'template':
				$result = (new AIPS_Template_Repository())->delete($id);
				break;
			case 'schedule':
				$result = (new AIPS_Schedule_Repository())->delete($id);
				break;
		}

		if (false === $result || is_wp_error($result)) {
			$this->error("Failed to delete {$entity} #{$id} ({$name})");
			return;
		}

		$this->line("[OK] Deleted {$entity} #{$id} ({$name})");
	}

	/* ------------------------------------------------------------------ */
	/* Summary and small helpers                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Print the run summary.
	 *
	 * @return void
	 */
	private function print_summary() {
		$this->line('');
		$this->line('=========================================');
		$this->line($this->is_apply() ? 'SUMMARY (applied)' : 'SUMMARY (dry run, nothing written)');
		$this->line('=========================================');

		$by_entity = array();
		foreach ($this->changes as $change) {
			$by_entity[$change['entity']] = isset($by_entity[$change['entity']]) ? $by_entity[$change['entity']] + 1 : 1;
		}

		$this->line('Objects ' . ($this->is_apply() ? 'created' : 'to create') . ': ' . count($this->creations));
		foreach ($by_entity as $entity => $count) {
			$this->line("Field changes ({$entity}): {$count}");
		}
		$this->line('Warnings: ' . count($this->warnings));
		$this->line('Errors: ' . count($this->errors));

		if ($this->is_apply()) {
			$this->line('');
			$this->line('Backup: ' . $this->backup_file);
			$this->line('Undo with: wp eval-file <this script> rollback ' . $this->backup_file);
		} else {
			$this->line('');
			$this->line('Run again with "apply" to write these changes (add "drafts" to save template posts as drafts during rollout).');
		}

		$this->line('');
		$this->line('Not changed by this script (manual follow-ups):');
		$this->line('- Unsplash stays unused (no API key). Allowed image sources are AI prompt and media library.');
		$this->line('- The indexer queue pause (aips_indexer_queue_paused) is left as is.');
		$this->line('- Affiliate links, related posts and telemetry are left as configured.');
	}

	/**
	 * Post author for created objects.
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
	private function next_weekday_timestamp($iso_weekday, $time_hhmm) {
		$timezone = wp_timezone();
		$now = new DateTimeImmutable('now', $timezone);
		list($hour, $minute) = array_map('intval', explode(':', $time_hhmm));
		$candidate = $now->setTime($hour, $minute);
		$days = ($iso_weekday - (int) $now->format('N') + 7) % 7;
		$candidate = $candidate->modify('+' . $days . ' days');

		if ($candidate <= $now) {
			$candidate = $candidate->modify('+7 days');
		}

		return $candidate->getTimestamp();
	}

	/**
	 * Next occurrence of a daily time (site timezone), shifted by a number of minutes.
	 *
	 * @param string $time_hhmm      "HH:MM".
	 * @param int    $offset_minutes Minutes added to the slot.
	 * @return int Unix timestamp.
	 */
	private function next_daily_timestamp($time_hhmm, $offset_minutes = 0) {
		$timezone = wp_timezone();
		$now = new DateTimeImmutable('now', $timezone);
		list($hour, $minute) = array_map('intval', explode(':', $time_hhmm));
		$candidate = $now->setTime($hour, $minute)->modify('+' . (int) $offset_minutes . ' minutes');

		if ($candidate <= $now) {
			$candidate = $candidate->modify('+1 day');
		}

		return $candidate->getTimestamp();
	}

	/**
	 * Featured image prompt in the house style.
	 *
	 * @param string $subject
	 * @return string
	 */
	private function featured_image_prompt($subject) {
		return self::HOUSE_STYLE . '. Concept illustration for a technical article about {{topic}}: ' . $subject . '.';
	}

	/**
	 * Seed data for every entity type.
	 *
	 * @return array
	 */
	private function build_seeding_object_data() {
		return array(
			'settings'      => $this->get_settings_seed_data(),
			'categories'    => $this->get_category_seed_data(),
			'voices'        => $this->get_voice_seed_data(),
			'sections'      => $this->get_section_seed_data(),
			'structures'    => $this->get_structure_seed_data(),
			'authors'       => $this->get_author_seed_data(),
			'post_slices'   => $this->get_post_slice_seed_data(),
			'campaigns'     => $this->get_campaign_seed_data(),
			'templates'     => $this->get_template_seed_data(),
			'schedules'     => $this->get_schedule_seed_data(),
		);
	}

	/**
	 * v2 source groups plus the groups added in v3.
	 *
	 * @return array
	 */
	private function get_all_source_group_seeds() {
		return array_merge($this->get_source_group_seed_data(), $this->get_v3_source_group_seed_data());
	}

	/**
	 * v2 sources plus the sources added in v3.
	 *
	 * @return array
	 */
	private function get_all_source_seeds() {
		return array_merge($this->get_source_seed_data(), $this->get_v3_source_seed_data());
	}

	/* ------------------------------------------------------------------ */
	/* v2 distribution helpers (used by the seed data below)               */
	/* ------------------------------------------------------------------ */

	/**
	 * Returns the distribution configuration constant as an array.
	 *
	 * @return array The full distribution configuration array.
	 */
	private function get_distribution_config() {
		return self::DISTRIBUTION_CONFIG;
	}

	/**
	 * Returns the target post counts for all defined distribution periods.
	 *
	 * @return array Associative array of period names to post counts (e.g. ['daily' => 6, 'weekly' => 30]).
	 */
	private function get_target_posts_by_period() {
		$config = $this->get_distribution_config();

		return isset($config['target_posts']) ? $config['target_posts'] : array();
	}

	/**
	 * Returns the target post count for a specific distribution period.
	 *
	 * @param string $period The period name (e.g. 'daily', 'weekly', 'monthly').
	 * @return int The configured post target for that period, or 0 if not defined.
	 */
	private function get_target_posts_for_period($period) {
		$targets = $this->get_target_posts_by_period();

		return isset($targets[$period]) ? (int) $targets[$period] : 0;
	}

	/**
	 * Returns the primary distribution period configured for this content strategy.
	 *
	 * @return string The primary period name (e.g. 'weekly').
	 */
	private function get_primary_distribution_period() {
		$config = $this->get_distribution_config();

		return isset($config['distribution_period']) ? (string) $config['distribution_period'] : 'weekly';
	}

	/**
	 * Returns the target post count for the primary distribution period.
	 *
	 * @return int The post count target for the primary period.
	 */
	private function get_primary_target_post_count() {
		return $this->get_target_posts_for_period($this->get_primary_distribution_period());
	}

	/**
	 * Distributes target post counts across campaigns or authors based on defined shares.
	 * 
	 * @param array $weights Associative array of keys (campaign or author names) and their corresponding share weights.
	 * @param int $target_total The total number of posts to distribute across the keys.
	 * 
	 * @return array Associative array of keys and the calculated number of posts to assign to each key based on their share weights.
	 */
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

	/**
	 * Calculates the target post counts for each campaign based on the defined campaign shares and the primary target post count for the distribution period. This method retrieves the campaign shares from the distribution configuration and uses the distribute_target_counts method to allocate the primary target post count across the campaigns according to their respective shares, resulting in a clear distribution of how many posts should be generated for each campaign within the content strategy for DevStackTips.
	 * 
	 * @return array Associative array of campaign names and the calculated number of posts to assign to each campaign based on their share weights.
	 */
	private function get_campaign_post_targets() {
		$config = $this->get_distribution_config();
		$shares = isset($config['campaign_shares']) ? $config['campaign_shares'] : array();

		return $this->distribute_target_counts($shares, $this->get_primary_target_post_count());
	}

	/**
	 * Calculates the target post counts for each author based on their defined shares and the primary target post count.
	 *
	 * @return array Associative array of author names to their calculated post counts.
	 */
	private function get_author_post_targets() {
		$config = $this->get_distribution_config();
		$shares = isset($config['author_shares']) ? $config['author_shares'] : array();

		return $this->distribute_target_counts($shares, $this->get_primary_target_post_count());
	}

	/* ------------------------------------------------------------------ */
	/* v2 seed data (create-only for objects that do not exist yet)        */
	/* ------------------------------------------------------------------ */

	/**
	 * Returns the seed data array for all plugin settings, organized into labeled sections.
	 *
	 * Each section contains a 'title' and an 'options' array of setting entries. An entry may
	 * specify a static 'value', a 'structure_name' reference, or a 'category_name' reference.
	 *
	 * @return array Associative array of section keys to section data arrays.
	 */
	private function get_settings_seed_data() {
		return array(
			'content_strategy' => array(
				'title' => 'Content Strategy',
				'options' => array(
					array(
						'option_name' => 'aips_site_niche',
						'value' => 'Full-Stack Software Development, Web Architecture',
					),
					array(
						'option_name' => 'aips_site_target_audience',
						'value' => 'Professional Software Engineers, Web Developers (Mid to Senior Level)',
					),
					array(
						'option_name' => 'aips_site_content_goals',
						'value' => 'Provide authoritative technical deep-dives, establish industry credibility, drive affiliate revenue via trusted/actionable recommendations, and rank for high-value "how-to" and "best practice" keywords.',
					),
					array(
						'option_name' => 'aips_site_brand_voice',
						'value' => 'Authoritative yet approachable, Professional, expert-level',
					),
					array(
						'option_name' => 'aips_site_content_language',
						'value' => 'en',
					),
					array(
						'option_name' => 'aips_site_content_guidelines',
						'value' => 'Minimum 5-10 paragraphs depending on topic. Use H2/H3 hierarchy. Mandatory: brief hyperlinked a "Sources" section. Break code into small, explained chunks. Security focus. Always emphasize "real-world" patterns.',
					),
					array(
						'option_name' => 'aips_site_excluded_topics',
						'value' => 'Politics, religion, celebrity gossip, gambling, adult content, clickbait/listicles with no technical depth ("get rich quick" schemes, unverified rumors/hacks about hardware, non-technical software (e.g., Word/Excel), and general consumer electronics reviews.',
					),
				),
			),
			'resilience_settings' => array(
				'title' => 'Resilience & Limits (Production Settings)',
				'options' => array(
					array(
						'option_name' => 'aips_enable_retry',
						'value' => 1,
					),
					array(
						'option_name' => 'aips_retry_max_attempts',
						'value' => 3,
					),
					array(
						'option_name' => 'aips_retry_initial_delay',
						'value' => 2,
					),
					array(
						'option_name' => 'aips_enable_rate_limiting',
						'value' => 1,
					),
					array(
						'option_name' => 'aips_rate_limit_requests',
						'value' => 20,
					),
					array(
						'option_name' => 'aips_rate_limit_period',
						'value' => 60,
					),
					array(
						'option_name' => 'aips_enable_circuit_breaker',
						'value' => 1,
					),
					array(
						'option_name' => 'aips_circuit_breaker_threshold',
						'value' => 5,
					),
					array(
						'option_name' => 'aips_circuit_breaker_timeout',
						'value' => 300,
					),
				),
			),
			'default_article_structure' => array(
				'title' => 'Default Article Structure',
				'options' => array(
					array(
						'option_name' => 'aips_default_article_structure_id',
						'structure_name' => 'Evergreen How-To Guide',
						'label' => 'default article structure: Evergreen How-To Guide',
					),
				),
			),
			'notification_preferences' => array(
				'title' => 'Notification Preferences',
				'options' => array(
					array(
						'option_name' => 'aips_review_notifications_email',
						'value' => '',
						'warning_message' => 'Please add notification email in Settings > Notifications',
					),
					array(
						'option_name' => 'aips_notification_preferences',
						'value' => array(
							'generation_failed' => 'email',
							'quota_alert' => 'email',
							'post_ready_for_review' => 'both',
							'template_generated' => 'db',
							'manual_generation_completed' => 'db',
							'partial_generation_completed' => 'both',
							'author_topics_generated' => 'db',
							'author_topics_failed' => 'email',
							'author_posts_generated' => 'db',
							'author_posts_failed' => 'email',
							'bulk_batch_completed' => 'db',
							'errors' => 'email',
							'daily_digest' => 'email',
							'weekly_digest' => 'email',
							'monthly_digest' => 'email',
						),
					),
				),
			),
			'research_and_discovery' => array(
				'title' => 'Research & Discovery',
				'options' => array(
					array(
						'option_name' => 'aips_research_niches',
						'value' => array(
							'PHP and Backend Development Trends',
							'Application Security and Vulnerability Prevention',
							'Modern Framework Comparisons (Laravel, Symfony, etc.)',
							'DevOps Tools and Automation',
							'AI Tools for Developers',
							'Database Optimization and Best Practices',
							'Software Architecture Patterns',
							'API Design and Integration',
						),
					),
					array(
						'option_name' => 'aips_topic_similarity_threshold',
						'value' => 0.75,
					),
				),
			),
			'featured_images' => array(
				'title' => 'Featured Images',
				'options' => array(
					array(
						'option_name' => 'aips_unsplash_access_key',
						'value' => '',
						'warning_message' => 'Please add Unsplash Access Key in Settings if using Unsplash images',
					),
				),
			),
			'performance_monitoring' => array(
				'title' => 'Performance Monitoring',
				'options' => array(
					array(
						'option_name' => 'aips_enable_telemetry',
						'value' => true,
					),
				),
			),
			'cache_configuration' => array(
				'title' => 'Cache Configuration',
				'options' => array(
					array(
						'option_name' => 'aips_enable_cache_system',
						'value' => false,
					),
				),
			),
			'log_management' => array(
				'title' => 'Log Management',
				'options' => array(
					array(
						'option_name' => 'aips_log_retention_days',
						'value' => 60,
					),
				),
			),
			'default_post_settings' => array(
				'title' => 'Default Post Settings',
				'options' => array(
					array(
						'option_name' => 'aips_default_post_status',
						'value' => 'draft',
					),
					array(
						'option_name' => 'aips_default_category',
						'category_name' => 'Backend Development',
						'label' => 'default category: Backend Development',
					),
					array(
						'option_name' => 'aips_default_post_author',
						'value' => 1,
					),
				),
			),
		);
	}

	/**
	 * Returns the seed data array for all WordPress categories to create.
	 *
	 * @return array List of category data arrays, each with 'name', 'slug', and 'description' keys.
	 */
	private function get_category_seed_data() {
		return array(
			array(
				'name' => 'Backend Development',
				'slug' => 'backend-development',
				'description' => 'Server-side programming, APIs, databases, and backend architecture',
			),
			array(
				'name' => 'Security',
				'slug' => 'security',
				'description' => 'Application security, vulnerabilities, and secure coding practices',
			),
			array(
				'name' => 'DevOps & Tools',
				'slug' => 'devops-tools',
				'description' => 'Developer tools, workflows, Git, Docker, and automation',
			),
			array(
				'name' => 'Framework Guides',
				'slug' => 'framework-guides',
				'description' => 'Framework tutorials and comparisons',
			),
			array(
				'name' => 'AI for Developers',
				'slug' => 'ai-for-developers',
				'description' => 'Practical AI tools and workflows for software development',
			),
			array(
				'name' => 'PHP Development',
				'slug' => 'php-development',
				'description' => 'PHP tutorials, patterns, and best practices',
			),
			array(
				'name' => 'Database',
				'slug' => 'database',
				'description' => 'SQL, database design, optimization, and management',
			),
		);
	}

	/**
	 * Returns the seed data array for all writing voices to create.
	 *
	 * @return array List of voice data arrays with 'name', 'title_prompt', 'content_instructions', 'excerpt_instructions', and 'is_active' keys.
	 */
	private function get_voice_seed_data() {
		return array(
			array(
				'name' => 'DevStackTips Default',
				'title_prompt' => 'Create a clear, technical, and specific title for this topic. Avoid generic phrases like "ultimate guide" or "everything you need to know". Make it actionable and developer-focused.',
				'content_instructions' => ['You are writing for software developers and technical readers on DevStackTips, a practical developer resource site.',
					'Writing style:',
					'- Be clear and concrete',
					'- Use short paragraphs and informative headings',
					'- Prefer practical examples over abstract explanations',
					'- Be technically confident but not arrogant',
					'- Avoid hype, filler, and exaggerated claims',
					'- Never mention being an AI',
					'- Do not use phrases like "ultimate guide" or "game-changing"',
					'Content approach:',
					'- Focus on what developers need to know',
					'- Include code examples where relevant',
					'- Explain the "why" behind recommendations',
					'- Be honest about tradeoffs and limitations',
					'- Use active voice',
					'- Keep intros brief and get to the point quickly',

					'Avoid:',
					'- Marketing language or sales pitches',
					'- Generic filler sentences',
					'- Inventing statistics or benchmarks',
					'- Making version-specific claims without verification',
					'- Overly broad generalizations',
				],
				'excerpt_instructions' => 'Write a concise 1-2 sentence summary that captures the core value for developers.',
				'is_active' => 1,
			),
			array(
				'name' => 'Senior Backend Mentor',
				'title_prompt' => 'Create a title that emphasizes the architectural or design aspect. Use terms like "patterns", "strategies", "best practices", or "architecture".',
				'content_instructions' => 'You are a senior backend engineer mentoring developers through DevStackTips.

				Writing approach:
				- Explain not just what to do, but WHY
				- Highlight tradeoffs, limitations, and common mistakes
				- Emphasize maintainability, reliability, and security
				- Share practical wisdom from real-world experience
				- Be opinionated but fair

				Content structure:
				- Start with the problem or context
				- Explain the reasoning behind solutions
				- Include "when to use" and "when NOT to use" guidance
				- Point out anti-patterns and pitfalls
				- Focus on long-term consequences of design decisions

				Technical depth:
				- Assume intermediate to advanced knowledge
				- Use precise technical terminology
				- Explain edge cases and failure scenarios
				- Discuss performance and security implications
				- Reference architectural principles when relevant

				Avoid:
				- Oversimplifying complex topics
				- Presenting one approach as universally correct
				- Ignoring operational concerns
				- Theoretical-only advice without practical application',
				'excerpt_instructions' => 'Highlight the key architectural insight or tradeoff discussed.',
				'is_active' => 1,
			),
			array(
				'name' => 'Hands-On Tutorial Coach',
				'title_prompt' => 'Create a "How to..." title that clearly states what the reader will accomplish. Be specific about the outcome.',
				'content_instructions' => 'You are a patient, practical tutorial instructor helping developers learn by doing.

				Teaching style:
				- Break complex tasks into clear sequential steps
				- Assume readers want to apply this immediately
				- Be encouraging without being condescending
				- Explain each step\'s purpose
				- Anticipate where learners might get stuck

				Content structure:
				- Start with what the reader will build/learn
				- List prerequisites clearly
				- Number steps sequentially
				- Include validation checkpoints
				- Show expected output at each stage
				- Provide troubleshooting tips

				Code and examples:
				- Include complete, working code samples
				- Explain what each code block does
				- Use comments in code where helpful
				- Show before/after comparisons
				- Provide copy-paste ready snippets

				Tone:
				- Friendly and approachable
				- Direct and action-oriented
				- Patient with beginners
				- Practical over theoretical
				- Focus on getting it working first, optimization second',
				'excerpt_instructions' => 'Describe what the reader will be able to do after following this tutorial.',
				'is_active' => 1,
			),
			array(
				'name' => 'Neutral Technical Analyst',
				'title_prompt' => 'Create a balanced comparison title using "vs" or "Comparing". Avoid suggesting one option is superior.',
				'content_instructions' => 'You are an objective technical analyst comparing tools, frameworks, and approaches for developers.

					Comparison approach:
					- Present options fairly and without bias
					- Acknowledge that different contexts require different solutions
					- Use structured comparison frameworks
					- Focus on objective criteria
					- Avoid declaring universal winners

					Content structure:
					- Start with clear comparison criteria
					- Present strengths and weaknesses of each option
					- Include comparison tables where appropriate
					- Discuss ideal use cases for each
					- Address migration/switching considerations

					Analysis style:
					- Evidence-based over opinion-based
					- Acknowledge trade-offs explicitly
					- Consider multiple perspectives
					- Discuss real-world constraints
					- Address both technical and practical factors

					What to avoid:
					- Fanboy enthusiasm or bashing
					- Ignoring legitimate use cases
					- Outdated information presented as current
					- Oversimplifying nuanced differences
					- Personal preference disguised as objective analysis',
				'excerpt_instructions' => 'Summarize the key differences and when to choose each option.',
				'is_active' => 1,
			),
			array(
				'name' => 'AI Engineering Editor',
				'title_prompt' => 'Create a title that addresses AI tools or workflows pragmatically. Include "for Developers" or "in Development" to keep focus practical.',
				'content_instructions' => 'You are writing about AI tools and practices for developers, with both enthusiasm and appropriate caution.

				Content approach:
				- Avoid AI hype and exaggeration
				- Focus on practical developer use cases
				- Address accuracy, risks, and evaluation methods
				- Discuss governance and quality control
				- Balance opportunity with realistic limitations

				Technical coverage:
				- Explain how AI tools fit into developer workflows
				- Discuss prompt engineering practically
				- Cover evaluation and testing approaches
				- Address when NOT to use AI
				- Include human review checkpoints

				Tone:
				- Pragmatic and grounded
				- Current but not breathless
				- Honest about limitations
				- Security and quality conscious
				- Focus on sustainable practices

				Critical areas to address:
				- Accuracy and hallucination risks
				- Code review requirements
				- Privacy and security implications
				- Cost considerations
				- When traditional approaches are better
				- Responsible AI practices',
				'excerpt_instructions' => 'Emphasize both the benefits and limitations of the AI approach discussed.',
				'is_active' => 1,
			),
			array(
				'name' => 'Staff Software Architect',
				'title_prompt' => 'Create a title emphasizing system architecture, design patterns, or domain modeling (e.g., "Designing a Resilient Event-Driven System", "Domain-Driven Design in Practice"). Make it authoritative, clear, and technically profound.',
				'content_instructions' => 'You are a staff software architect providing strategic architectural guidance on DevStackTips.

Writing approach:
- Focus on system boundaries, high cohesion, low coupling, and modularity
- Deeply analyze architectural trade-offs (e.g., consistency vs availability, simplicity vs flexibility)
- Provide clear structural diagrams (ASCII or text decomposition)
- Balance immediate pragmatic delivery with long-term evolvability
- Address failure modes, scalability limits, and resilience patterns

Technical depth:
- Target senior software engineers and technical leaders
- Use precise architectural terminology (bounded contexts, aggregates, ports and adapters, saga pattern)
- Compare alternative patterns before justifying the recommended approach
- Include concrete, idiomatic code examples implementing the pattern cleanly

Avoid:
- Abstract architectural hand-waving without code implementation
- Dogmatic purity that ignores real-world operational realities
- Superficial design pattern overviews without trade-off analysis',
				'excerpt_instructions' => 'Summarize the foundational architectural principle and high-impact structural trade-off.',
				'is_active' => 1,
			),
			array(
				'name' => 'Test Automation & QA Lead',
				'title_prompt' => 'Create a title emphasizing automated testing strategies, TDD cycles, or quality engineering (e.g., "Mastering TDD with Mocking Strategies", "Building Robust Integration Tests for Distributed APIs").',
				'content_instructions' => 'You are a quality engineering and test automation lead guiding developers on DevStackTips.

Teaching style:
- Advocate rigorous, test-first thinking and disciplined TDD cycles
- Demonstrate clear Red-Green-Refactor steps
- Emphasize test isolation, speed, determinism, and maintainability
- Teach proper test double usage (distinguish mocks, stubs, fakes, and spies)
- Focus on eliminating flaky tests and building mutation-resistant suites

Content structure:
- Start with the testing scenario and requirements
- Show how to write the failing test assertion first
- Provide the minimal implementation to pass
- Refactor the code while keeping tests green
- Cover edge cases, mutation testing, and CI pipeline automation

Tone:
- Methodical, encouraging, and quality-driven
- Practical with concrete test assertions and frameworks
- Focused on confidence in continuous delivery',
				'excerpt_instructions' => 'Highlight the primary testing methodology, TDD cycle, or quality assurance pattern discussed.',
				'is_active' => 1,
			),
			array(
				'name' => 'Code Craftsman & Refactoring Lead',
				'title_prompt' => 'Create a title emphasizing safe code refactoring, code smell elimination, or technical debt management (e.g., "Refactoring God Classes into Clean Services", "Eliminating Code Smells with Strangler Patterns").',
				'content_instructions' => 'You are a seasoned code craftsman specializing in legacy modernization and refactoring on DevStackTips.

Refactoring approach:
- Treat refactoring as disciplined, incremental behavior-preserving transformations
- Always establish characterization tests and safety nets BEFORE changing code
- Explicitly identify code smells (God Class, Feature Envy, Primitive Obsession, Hidden Coupling)
- Demonstrate step-by-step micro-refactorings with clear before/after diffs
- Emphasize clean code, readability, maintainability, and SOLID principles

Analysis style:
- Ground recommendations in real-world messy codebases, not toy examples
- Show how to pay down technical debt incrementally without risky total rewrites
- Benchmark or assess clean code against performance implications
- Emphasize automated static analysis and linting rules

Tone:
- Pragmatic, empathetic to legacy constraints, disciplined, and craftsmanship-oriented',
				'excerpt_instructions' => 'Highlight the specific code smell eliminated and the core refactoring step applied.',
				'is_active' => 1,
			),
		);
	}

	/**
	 * Returns the seed data array for all prompt sections to create.
	 *
	 * @return array Associative array keyed by section_key, each value containing 'name', 'section_key', 'description', 'content', and 'is_active' keys.
	 */
	private function get_section_seed_data() {
		return array(
			'introduction' => array(
				'name' => 'Introduction',
				'section_key' => 'introduction',
				'description' => 'Opening section that establishes context and relevance',
				'content' => 'Start with a brief introduction explaining why this topic matters to developers. Keep it concise and focused on practical relevance.',
				'is_active' => 1,
			),
			'what_youll_learn' => array(
				'name' => 'What You\'ll Learn',
				'section_key' => 'what_youll_learn',
				'description' => 'Learning objectives section',
				'content' => 'List 3-5 specific things the reader will learn or be able to do after reading this article.',
				'is_active' => 1,
			),
			'prerequisites' => array(
				'name' => 'Prerequisites',
				'section_key' => 'prerequisites',
				'description' => 'Required knowledge and setup',
				'content' => 'List any required knowledge, tools, or setup needed before following this tutorial.',
				'is_active' => 1,
			),
			'core_concepts' => array(
				'name' => 'Core Concepts',
				'section_key' => 'core_concepts',
				'description' => 'Fundamental concepts overview',
				'content' => 'Explain the fundamental concepts readers need to understand. Define key terms and provide context.',
				'is_active' => 1,
			),
			'step_by_step' => array(
				'name' => 'Step-by-Step Instructions',
				'section_key' => 'step_by_step',
				'description' => 'Sequential implementation steps',
				'content' => 'Provide numbered, sequential steps. For each step, explain what to do, why, and what the expected result is.',
				'is_active' => 1,
			),
			'code_examples' => array(
				'name' => 'Code Examples',
				'section_key' => 'code_examples',
				'description' => 'Working code samples',
				'content' => 'Include complete, working code examples with explanations. Use code blocks with proper syntax highlighting.',
				'is_active' => 1,
			),
			'common_mistakes' => array(
				'name' => 'Common Mistakes',
				'section_key' => 'common_mistakes',
				'description' => 'Pitfalls to avoid',
				'content' => 'List 3-5 common mistakes developers make with this approach, and how to avoid them.',
				'is_active' => 1,
			),
			'best_practices' => array(
				'name' => 'Best Practices',
				'section_key' => 'best_practices',
				'description' => 'Recommended approaches',
				'content' => 'Share best practices and recommendations for production use.',
				'is_active' => 1,
			),
			'when_to_use' => array(
				'name' => 'When to Use / When Not to Use',
				'section_key' => 'when_to_use',
				'description' => 'Use case guidance',
				'content' => 'Explain when this approach is ideal and when alternatives should be considered. Be honest about limitations.',
				'is_active' => 1,
			),
			'conclusion' => array(
				'name' => 'Conclusion',
				'section_key' => 'conclusion',
				'description' => 'Summary and next steps',
				'content' => 'Summarize key takeaways and suggest next steps for further learning.',
				'is_active' => 1,
			),
			'faq' => array(
				'name' => 'FAQ',
				'section_key' => 'faq',
				'description' => 'Frequently asked questions',
				'content' => 'Answer 3-5 common questions related to this topic.',
				'is_active' => 1,
			),
			'problem_statement' => array(
				'name' => 'Problem Statement',
				'section_key' => 'problem_statement',
				'description' => 'Define the problem being solved',
				'content' => 'Clearly define the problem or challenge this approach addresses.',
				'is_active' => 1,
			),
			'technical_context' => array(
				'name' => 'Technical Context',
				'section_key' => 'technical_context',
				'description' => 'Background and context',
				'content' => 'Provide technical background and context needed to understand the solution.',
				'is_active' => 1,
			),
			'implementation_strategy' => array(
				'name' => 'Implementation Strategy',
				'section_key' => 'implementation_strategy',
				'description' => 'Overall approach',
				'content' => 'Outline the overall implementation strategy before diving into details.',
				'is_active' => 1,
			),
			'performance_considerations' => array(
				'name' => 'Performance Considerations',
				'section_key' => 'performance_considerations',
				'description' => 'Performance implications',
				'content' => 'Discuss performance implications, optimization opportunities, and scalability concerns.',
				'is_active' => 1,
			),
			'security_considerations' => array(
				'name' => 'Security Considerations',
				'section_key' => 'security_considerations',
				'description' => 'Security implications',
				'content' => 'Address security implications, risks, and secure coding practices.',
				'is_active' => 1,
			),
			'testing_validation' => array(
				'name' => 'Testing / Validation',
				'section_key' => 'testing_validation',
				'description' => 'How to test and validate',
				'content' => 'Explain how to test and validate the implementation.',
				'is_active' => 1,
			),
			'operational_tips' => array(
				'name' => 'Operational Tips',
				'section_key' => 'operational_tips',
				'description' => 'Production operation guidance',
				'content' => 'Provide operational guidance for running this in production (monitoring, logging, debugging).',
				'is_active' => 1,
			),
			'comparison_overview' => array(
				'name' => 'Overview',
				'section_key' => 'comparison_overview',
				'description' => 'Comparison introduction',
				'content' => 'Introduce both options being compared and the criteria for comparison.',
				'is_active' => 1,
			),
			'quick_summary_table' => array(
				'name' => 'Quick Summary Table',
				'section_key' => 'quick_summary_table',
				'description' => 'At-a-glance comparison',
				'content' => 'Create a comparison table with key features side-by-side.',
				'is_active' => 1,
			),
			'option_a' => array(
				'name' => 'What Option A Is',
				'section_key' => 'option_a',
				'description' => 'First option overview',
				'content' => 'Explain what the first option is, its history, and core features.',
				'is_active' => 1,
			),
			'option_b' => array(
				'name' => 'What Option B Is',
				'section_key' => 'option_b',
				'description' => 'Second option overview',
				'content' => 'Explain what the second option is, its history, and core features.',
				'is_active' => 1,
			),
			'developer_experience' => array(
				'name' => 'Developer Experience',
				'section_key' => 'developer_experience',
				'description' => 'DX comparison',
				'content' => 'Compare the developer experience, learning curve, and documentation quality.',
				'is_active' => 1,
			),
			'ecosystem_community' => array(
				'name' => 'Ecosystem / Community',
				'section_key' => 'ecosystem_community',
				'description' => 'Community and ecosystem',
				'content' => 'Compare community size, available packages/plugins, and ecosystem maturity.',
				'is_active' => 1,
			),
			'use_cases' => array(
				'name' => 'Best Use Cases',
				'section_key' => 'use_cases',
				'description' => 'Recommended use cases',
				'content' => 'Describe the ideal use cases for each option.',
				'is_active' => 1,
			),
			'recommendation' => array(
				'name' => 'Final Recommendation',
				'section_key' => 'recommendation',
				'description' => 'Summary and guidance',
				'content' => 'Provide balanced recommendations based on different scenarios and requirements.',
				'is_active' => 1,
			),
			'architectural_tradeoffs' => array(
				'name' => 'Architectural Trade-offs & Alternatives',
				'section_key' => 'architectural_tradeoffs',
				'description' => 'Analysis of architectural trade-offs, complexity vs maintainability, and alternative designs',
				'content' => 'Deeply analyze the architectural trade-offs of this pattern (e.g. coupling vs cohesion, latency vs consistency, simplicity vs flexibility). Discuss alternative patterns and why this specific solution was chosen.',
				'is_active' => 1,
			),
			'system_diagram_concept' => array(
				'name' => 'System & Component Architecture',
				'section_key' => 'system_diagram_concept',
				'description' => 'Visual or structural breakdown of components, boundaries, and data flow',
				'content' => 'Provide a clear textual/ASCII diagram or structured decomposition showing how components, modules, or services interact, detailing boundaries and data flows.',
				'is_active' => 1,
			),
			'smelly_code_analysis' => array(
				'name' => 'Code Smell & Anti-Pattern Analysis',
				'section_key' => 'smelly_code_analysis',
				'description' => 'Inspection of problematic legacy code and identified design flaws',
				'content' => 'Show the original problematic or smelly code. Pinpoint exact anti-patterns (e.g. God Class, Feature Envy, Primitive Obsession, Hidden Coupling) and explain the business/technical risks of leaving it unchanged.',
				'is_active' => 1,
			),
			'safety_nets_tests' => array(
				'name' => 'Safety Nets & Characterization Tests',
				'section_key' => 'safety_nets_tests',
				'description' => 'Establishing test coverage and regression baselines before refactoring',
				'content' => 'Explain how to wrap the existing code with characterization tests and regression safety nets before making any changes, ensuring behavior preservation.',
				'is_active' => 1,
			),
			'step_by_step_refactoring' => array(
				'name' => 'Step-by-Step Refactoring Sequence',
				'section_key' => 'step_by_step_refactoring',
				'description' => 'Disciplined micro-steps to transform the code cleanly',
				'content' => 'Walk through the refactoring process in incremental, test-backed micro-steps. Show before/after diffs for each transformation step.',
				'is_active' => 1,
			),
			'test_scenario_setup' => array(
				'name' => 'Test Scenarios & Fixture Design',
				'section_key' => 'test_scenario_setup',
				'description' => 'Defining testing scenarios, state fixtures, and test doubles',
				'content' => 'Outline the exact test scenarios, state fixtures, and mock/stub boundaries needed to test this behavior thoroughly without fragile dependencies.',
				'is_active' => 1,
			),
			'red_green_refactor' => array(
				'name' => 'Red-Green-Refactor Cycle',
				'section_key' => 'red_green_refactor',
				'description' => 'TDD cycle walkthrough with failing test, minimal implementation, and clean refactor',
				'content' => 'Demonstrate the TDD cycle: write the failing assertion (Red), write the simplest code to pass (Green), and refactor for elegance and performance (Refactor).',
				'is_active' => 1,
			),
			'mutation_edge_cases' => array(
				'name' => 'Edge Cases & Mutation Resistance',
				'section_key' => 'mutation_edge_cases',
				'description' => 'Testing boundaries, failure modes, and mutation test verification',
				'content' => 'Identify critical edge cases, null/unexpected inputs, failure modes, and how to verify test suite quality using mutation testing concepts.',
				'is_active' => 1,
			),
		);
	}

	/**
	 * Returns the seed data array for all article structures to create.
	 *
	 * @return array List of structure data arrays, each with 'name', 'description', and 'sections' (ordered array of section_key strings) keys.
	 */
	private function get_structure_seed_data() {
		return array(
			array(
				'name' => 'Evergreen How-To Guide',
				'description' => 'For foundational tutorials and step-by-step guides',
				'sections' => array('introduction', 'what_youll_learn', 'prerequisites', 'core_concepts', 'step_by_step', 'code_examples', 'common_mistakes', 'best_practices', 'when_to_use', 'conclusion', 'faq'),
			),
			array(
				'name' => 'Advanced Technical Tutorial',
				'description' => 'For deeper backend, security, and DevOps content',
				'sections' => array('problem_statement', 'technical_context', 'prerequisites', 'implementation_strategy', 'step_by_step', 'code_examples', 'performance_considerations', 'security_considerations', 'testing_validation', 'operational_tips', 'conclusion'),
			),
			array(
				'name' => 'Comparison Article',
				'description' => 'For framework, tool, and approach comparisons',
				'sections' => array('comparison_overview', 'quick_summary_table', 'option_a', 'option_b', 'developer_experience', 'performance_considerations', 'ecosystem_community', 'use_cases', 'recommendation'),
			),
			array(
				'name' => 'Architecture Deep Dive',
				'description' => 'For design patterns and system architecture topics',
				'sections' => array('problem_statement', 'core_concepts', 'technical_context', 'implementation_strategy', 'best_practices', 'common_mistakes', 'use_cases', 'conclusion'),
			),
			array(
				'name' => 'Security Best Practices',
				'description' => 'For security-focused content',
				'sections' => array('problem_statement', 'security_considerations', 'best_practices', 'code_examples', 'testing_validation', 'operational_tips', 'conclusion'),
			),
			array(
				'name' => 'Tool / Workflow Explainer',
				'description' => 'For Git, Composer, Docker, CI/CD, SSH, etc.',
				'sections' => array('introduction', 'core_concepts', 'step_by_step', 'code_examples', 'common_mistakes', 'best_practices', 'conclusion'),
			),
			array(
				'name' => 'AI-for-Devs Article',
				'description' => 'For AI workflow and tooling content',
				'sections' => array('problem_statement', 'best_practices', 'code_examples', 'use_cases', 'security_considerations', 'when_to_use', 'conclusion'),
			),
			array(
				'name' => 'News / Trend Analysis',
				'description' => 'For timely technical analysis',
				'sections' => array('introduction', 'technical_context', 'use_cases', 'recommendation', 'conclusion'),
			),
			array(
				'name' => 'Architecture Pattern Deep Dive',
				'description' => 'For system architecture, design patterns, domain modeling, and modularity',
				'sections' => array('problem_statement', 'system_diagram_concept', 'architectural_tradeoffs', 'implementation_strategy', 'step_by_step', 'code_examples', 'best_practices', 'common_mistakes', 'conclusion'),
			),
			array(
				'name' => 'Refactoring & Code Smells Case Study',
				'description' => 'For legacy code modernization, code smell elimination, and safe refactoring workflows',
				'sections' => array('problem_statement', 'smelly_code_analysis', 'safety_nets_tests', 'step_by_step_refactoring', 'code_examples', 'best_practices', 'operational_tips', 'conclusion'),
			),
			array(
				'name' => 'Test Strategy & Automation Guide',
				'description' => 'For TDD, integration testing, test doubles, and quality automation pipelines',
				'sections' => array('problem_statement', 'test_scenario_setup', 'red_green_refactor', 'code_examples', 'mutation_edge_cases', 'best_practices', 'testing_validation', 'conclusion'),
			),
		);
	}

	/**
	 * Returns the seed data array for all authors to create, with scheduled post quantities derived from distribution targets.
	 *
	 * @return array List of author data arrays containing profile, generation frequency, and quantity fields.
	 */
	private function get_author_seed_data() {
		$authors = array(
			array(
				'name' => 'Backend Architecture Specialist',
				'field_niche' => 'Backend Development, System Architecture, API Design',
				'keywords' => 'API design, microservices, system architecture, scalability, caching, database optimization',
				'description' => 'Senior backend engineer focused on API design, system architecture, and scalability patterns',
				'details' => 'Specializes in building scalable backend systems. Covers topics like RESTful APIs, GraphQL, microservices architecture, database optimization, and caching strategies. Target audience is intermediate to advanced backend developers.',
				'voice_name' => 'Senior Backend Mentor',
				'structure_name' => 'Advanced Technical Tutorial',
				'voice_tone' => 'Professional, authoritative, mentoring',
				'writing_style' => 'Technical deep-dive with practical examples',
				'target_audience' => 'Intermediate to advanced backend developers',
				'expertise_level' => 'advanced',
				'content_goals' => 'Educate developers on scalable architecture patterns, Build practical skills, Share production-tested solutions',
				'excluded_topics' => 'Basic programming concepts, Frontend frameworks, Mobile development',
				'preferred_content_length' => 'long',
				'category_name' => 'Backend Development',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 5,
				'post_generation_frequency' => 'weekly',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active' => 1,
			),
			array(
				'name' => 'Security Expert',
				'field_niche' => 'Application Security, Secure Coding, Vulnerability Prevention',
				'keywords' => 'SQL injection, XSS, CSRF, authentication, encryption, secure coding, OWASP',
				'description' => 'Application security specialist covering vulnerabilities, secure coding, and best practices',
				'details' => 'Focuses on practical application security for developers. Covers common vulnerabilities (OWASP Top 10), secure coding practices, authentication/authorization, encryption, and security testing. Emphasizes prevention over remediation.',
				'voice_name' => 'Senior Backend Mentor',
				'structure_name' => 'Security Best Practices',
				'voice_tone' => 'Professional, serious, educational',
				'writing_style' => 'Security-focused tutorial with code examples',
				'target_audience' => 'All developers who write server-side code',
				'expertise_level' => 'intermediate',
				'content_goals' => 'Prevent security vulnerabilities, Educate on secure coding, Build security awareness',
				'excluded_topics' => 'Network security hardware, Penetration testing tools, Compliance regulations',
				'preferred_content_length' => 'medium',
				'category_name' => 'Security',
				'source_group_name' => 'Security News',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 5,
				'post_generation_frequency' => 'weekly',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'include_sources' => 1,
				'is_active' => 1,
			),
			array(
				'name' => 'DevOps Practitioner',
				'field_niche' => 'Developer Tools, DevOps, Automation',
				'keywords' => 'Git, Docker, CI/CD, automation, deployment, developer productivity, workflows',
				'description' => 'Developer tools and workflow specialist focused on Git, Docker, CI/CD, and automation',
				'details' => 'Covers practical developer tools and DevOps workflows. Topics include Git workflows, Docker containerization, CI/CD pipelines, build automation, and developer productivity tools. Focuses on hands-on tutorials.',
				'voice_name' => 'Hands-On Tutorial Coach',
				'structure_name' => 'Tool / Workflow Explainer',
				'voice_tone' => 'Practical, encouraging, step-by-step',
				'writing_style' => 'Tutorial with clear instructions and examples',
				'target_audience' => 'Developers looking to improve their workflows and tooling',
				'expertise_level' => 'beginner_intermediate',
				'content_goals' => 'Teach practical tool usage, Improve developer productivity, Share workflow best practices',
				'excluded_topics' => 'Cloud infrastructure management, Kubernetes orchestration, Enterprise IT',
				'preferred_content_length' => 'medium',
				'category_name' => 'DevOps & Tools',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 5,
				'post_generation_frequency' => 'weekly',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active' => 1,
			),
			array(
				'name' => 'Framework Analyst',
				'field_niche' => 'Framework Comparisons, Technology Evaluation',
				'keywords' => 'framework comparison, Laravel vs Symfony, React vs Vue, technology choices, migration',
				'description' => 'Objective framework and tool comparison specialist',
				'details' => 'Provides balanced, unbiased comparisons of frameworks and tools. Covers PHP frameworks, JavaScript frameworks, databases, and other technology choices. Helps developers make informed decisions.',
				'voice_name' => 'Neutral Technical Analyst',
				'structure_name' => 'Comparison Article',
				'voice_tone' => 'Neutral, objective, analytical',
				'writing_style' => 'Comparison with pros/cons and use-case analysis',
				'target_audience' => 'Developers evaluating technology choices',
				'expertise_level' => 'intermediate',
				'content_goals' => 'Help developers make informed choices, Present balanced comparisons, Avoid bias',
				'excluded_topics' => 'Opinionated framework advocacy, Deprecated technologies, Proprietary tools',
				'preferred_content_length' => 'medium',
				'category_name' => 'Framework Guides',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 5,
				'post_generation_frequency' => 'weekly',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active' => 1,
			),
			array(
				'name' => 'AI Engineering Pragmatist',
				'field_niche' => 'AI for Developers, Practical AI Integration',
				'keywords' => 'AI code review, prompt engineering, GitHub Copilot, ChatGPT, LLM integration, AI evaluation',
				'description' => 'Practical AI integration specialist for developer workflows',
				'details' => 'Focuses on practical AI tools for developers. Covers AI-assisted coding, prompt engineering, code review with AI, LLM integration in applications, and honest evaluation of AI capabilities and limitations.',
				'voice_name' => 'AI Engineering Editor',
				'structure_name' => 'AI-for-Devs Article',
				'voice_tone' => 'Pragmatic, honest, forward-thinking',
				'writing_style' => 'Practical guide with real-world examples',
				'target_audience' => 'Developers exploring AI integration',
				'expertise_level' => 'intermediate',
				'content_goals' => 'Demystify AI for developers, Share practical integration patterns, Balance hype with reality',
				'excluded_topics' => 'Deep learning theory, AI research papers, ML model training',
				'preferred_content_length' => 'medium',
				'category_name' => 'AI for Developers',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 5,
				'post_generation_frequency' => 'weekly',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active' => 1,
			),
			array(
				'name' => 'Staff Software Architect',
				'field_niche' => 'System Architecture, Domain-Driven Design, Design Patterns, Modularity',
				'keywords' => 'clean architecture, domain-driven design, design patterns, microservices vs monolith, event-driven architecture, modularity, system design',
				'description' => 'Staff software architect specializing in scalable system design, domain modeling, and enterprise design patterns',
				'details' => 'Focuses on large-scale software architecture, domain-driven design (DDD), hexagonal/clean architecture, event-driven patterns, and strategic technical decision making. Guides developers on balancing immediate pragmatism with long-term architectural health.',
				'voice_name' => 'Staff Software Architect',
				'structure_name' => 'Architecture Pattern Deep Dive',
				'voice_tone' => 'Authoritative, strategic, pragmatic, architectural',
				'writing_style' => 'Architectural deep-dive with system trade-offs and structural analysis',
				'target_audience' => 'Senior engineers, tech leads, and aspiring software architects',
				'expertise_level' => 'advanced',
				'content_goals' => 'Elevate system design skills, Demystify architectural patterns, Master domain modeling and modularity',
				'excluded_topics' => 'Basic syntax tutorials, Superficial tool installations, Marketing buzzwords',
				'preferred_content_length' => 'long',
				'category_name' => 'Backend Development',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 6,
				'post_generation_frequency' => 'weekly',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active' => 1,
			),
			array(
				'name' => 'Test Automation & Quality Specialist',
				'field_niche' => 'Test-Driven Development, Automation Pipelines, Quality Engineering',
				'keywords' => 'TDD, unit testing, integration testing, end-to-end testing, mocking, mutation testing, test fixtures, CI/CD quality gates',
				'description' => 'Quality engineering and test automation specialist advocating test-driven design and bulletproof test suites',
				'details' => 'Covers practical testing strategies across the testing pyramid. Specializes in TDD workflows, robust test doubles/mocking, property-based and mutation testing, eliminating flaky tests, and embedding automated quality gates in CI/CD pipelines.',
				'voice_name' => 'Test Automation & QA Lead',
				'structure_name' => 'Test Strategy & Automation Guide',
				'voice_tone' => 'Rigorous, methodical, test-first, encouraging',
				'writing_style' => 'Step-by-step testing walkthrough with red-green-refactor cycles and concrete assertions',
				'target_audience' => 'Software engineers looking to master testing and build resilient automated test suites',
				'expertise_level' => 'intermediate',
				'content_goals' => 'Promote test-driven development, Eliminate software defects, Teach robust test automation patterns',
				'excluded_topics' => 'Manual clicking QA checklists, Theoretical quality models without code, Deprecated testing frameworks',
				'preferred_content_length' => 'medium',
				'category_name' => 'DevOps & Tools',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 6,
				'post_generation_frequency' => 'weekly',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active' => 1,
			),
			array(
				'name' => 'Code Quality & Refactoring Craftsman',
				'field_niche' => 'Legacy Code Refactoring, Code Smells, Static Analysis, Technical Debt',
				'keywords' => 'refactoring, code smells, legacy code, static analysis, technical debt, clean code, code review, SOLID principles',
				'description' => 'Code craftsman dedicated to transforming tangled legacy systems into clean, maintainable, and well-tested codebases',
				'details' => 'Specializes in safe, incremental refactoring techniques for mission-critical codebases. Covers code smell detection, characterization testing, strangler fig transformations, automated static analysis tooling, and maintaining clean code standards in growing teams.',
				'voice_name' => 'Code Craftsman & Refactoring Lead',
				'structure_name' => 'Refactoring & Code Smells Case Study',
				'voice_tone' => 'Pragmatic, disciplined, detail-oriented, empathetic to legacy constraints',
				'writing_style' => 'Case-study style refactoring tutorial with clear before/after transformations',
				'target_audience' => 'Intermediate and senior software developers managing evolving or legacy codebases',
				'expertise_level' => 'intermediate',
				'content_goals' => 'Demystify safe refactoring, Reduce technical debt, Share actionable code quality techniques',
				'excluded_topics' => 'Total rewrites without business rationale, Pure dogmatic clean code debates, Unactionable theory',
				'preferred_content_length' => 'medium',
				'category_name' => 'Backend Development',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 6,
				'post_generation_frequency' => 'weekly',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active' => 1,
			),
		);
		$post_targets = $this->get_author_post_targets();

		foreach ($authors as &$author) {
			$weekly_target = isset($post_targets[$author['name']]) ? (int) $post_targets[$author['name']] : 1;
			$author['scheduled_post_generation_quantity'] = max(1, $weekly_target);
			$author['topic_generation_quantity'] = max($author['scheduled_post_generation_quantity'], 5);
		}

		unset($author);

		return $authors;
	}

	/**
	 * Returns the seed data array for all post slices to create.
	 *
	 * @return array List of post slice data arrays with 'name', 'description', 'sort_order', and 'is_active' keys.
	 */
	private function get_post_slice_seed_data() {
		return array(
			array(
				'name' => 'Developer Resources Footer',
				'description' => 'Add an additional resources list and next-steps guidance for readers who want to keep learning and apply the topic in a small project.',
				'sort_order' => 10,
				'is_active' => 1,
			),
			array(
				'name' => 'Security Disclaimer',
				'description' => 'Insert a short security reminder that emphasizes validating and sanitizing input, distrust of external data, and least-privilege practices.',
				'sort_order' => 20,
				'is_active' => 1,
			),
			array(
				'name' => 'Code Example Standards',
				'description' => 'Prepend a note that code samples should be reviewed and adapted for error handling, edge cases, and project-specific constraints.',
				'sort_order' => 30,
				'is_active' => 1,
			),
			array(
				'name' => 'Version Note',
				'description' => 'Append a version disclaimer reminding readers to check current upstream documentation because APIs and tooling evolve over time.',
				'sort_order' => 40,
				'is_active' => 1,
			),
		);
	}

	/**
	 * Returns the seed data array for all source groups to create.
	 *
	 * @return array List of source group data arrays with 'name', 'slug', and 'description' keys.
	 */
	private function get_source_group_seed_data() {
		return array(
			array(
				'name' => 'Security News',
				'slug' => 'security-news',
				'description' => 'Security vulnerabilities, patches, and best practices',
			),
			array(
				'name' => 'PHP Ecosystem',
				'slug' => 'php-ecosystem',
				'description' => 'PHP frameworks, libraries, and community updates',
			),
		);
	}

	/**
	 * Returns the seed data array for all individual sources to create.
	 *
	 * @return array List of source data arrays with 'name', 'url', 'source_type', 'group_slug', and 'is_active' keys.
	 */
	private function get_source_seed_data() {
		return array(
			array(
				'name' => 'OWASP Top 10',
				'url' => 'https://owasp.org/www-project-top-ten/',
				'source_type' => 'rss',
				'group_slug' => 'security-news',
				'is_active' => 1,
			),
			array(
				'name' => 'Snyk Blog Security',
				'url' => 'https://snyk.io/blog/',
				'source_type' => 'rss',
				'group_slug' => 'security-news',
				'is_active' => 1,
			),
			array(
				'name' => 'Security Week',
				'url' => 'https://www.securityweek.com/feed/',
				'source_type' => 'rss',
				'group_slug' => 'security-news',
				'is_active' => 1,
			),
			array(
				'name' => 'Laravel News',
				'url' => 'https://laravel-news.com/feed',
				'source_type' => 'rss',
				'group_slug' => 'php-ecosystem',
				'is_active' => 1,
			),
			array(
				'name' => 'Symfony Blog',
				'url' => 'https://symfony.com/blog/',
				'source_type' => 'rss',
				'group_slug' => 'php-ecosystem',
				'is_active' => 1,
			),
		);
	}

	/**
	 * Returns the seed data array for all campaigns to create, with per-period post targets derived from distribution shares.
	 *
	 * @return array List of campaign data arrays with 'name', 'content_goal', 'topics', and computed target post count keys.
	 */
	private function get_campaign_seed_data() {
		$campaigns = array(
			array(
				'name' => 'Developer Foundations',
				'content_goal' => 'Foundational tutorials for core development concepts and tools',
				'topics' => "How to Use Composer in PHP Projects\nUnderstanding Composer Autoloading\nGit Rebase vs Merge Explained\nHow to Resolve Git Merge Conflicts\nUnderstanding REST APIs for Developers\nSQL Joins Explained with Examples\nDocker Basics for PHP Developers\nUsing Environment Variables Safely in Applications\nPHP Namespaces Explained for Beginners\nHow to Debug a 500 Internal Server Error",
			),
			array(
				'name' => 'Backend Engineering',
				'content_goal' => 'Intermediate backend development patterns and practices',
				'topics' => "Repository Pattern in PHP Applications\nImplementing API Rate Limiting Strategies\nBackground Jobs and Queue Workers Explained\nDesigning Idempotent API Endpoints\nStructured Logging Best Practices\nAPI Authentication with Bearer Tokens\nHandling Retries Safely in Distributed Systems\nInput Validation vs Sanitization\nCaching Strategies for Web Applications\nService Layer Pattern in Backend Applications",
			),
			array(
				'name' => 'Security First',
				'content_goal' => 'Security best practices and vulnerability prevention',
				'topics' => "How to Prevent SQL Injection in PHP\nCSRF Protection Explained and Implemented\nXSS Prevention Best Practices\nSecure Password Storage with Hashing\nSecrets Management for Developers\nSecure File Upload Handling\nHTTPS, TLS, and SSL Certificates Explained\nSession Security Best Practices\nProtecting API Keys in Applications\nCommon WordPress Security Mistakes",
			),
			array(
				'name' => 'Framework & Tool Comparisons',
				'content_goal' => 'Fair comparisons of frameworks, libraries, and tools',
				'topics' => "Laravel vs Symfony: Framework Comparison\nMySQL vs PostgreSQL for Web Applications\nPHPUnit vs Pest for PHP Testing\nRedis vs Memcached for Caching\nREST vs GraphQL API Design\nDocker Compose vs Kubernetes for Development\nGit vs SVN Version Control\nJWT vs Session Cookies for Authentication\nNginx vs Apache Web Servers\nMicroservices vs Monolith Architecture",
			),
			array(
				'name' => 'Developer Tooling',
				'content_goal' => 'Practical guides for developer tools and workflows',
				'topics' => "Advanced Git Commands Every Developer Should Know\nComposer Scripts for Automation\nGit Hooks for Code Quality\nUsing Makefiles in Application Projects\nSSH Key Management Best Practices\nGit Interactive Rebase Tutorial\nDebugging Docker Builds\nLinux File Permissions Explained\nBash Scripting for Developers\nCI/CD Pipeline Basics",
			),
			array(
				'name' => 'AI for Developers',
				'content_goal' => 'Practical AI guidance for developer workflows',
				'topics' => "When AI Helps Developers Most\nRisks of AI-Generated Technical Content\nHow to Review AI-Written Code for Accuracy\nPrompt Engineering for Technical Documentation\nAI Agents for Developer Workflows\nWhat Makes AI Content Useful vs Spam\nAI-Assisted Code Review Best Practices\nUsing AI for API Documentation\nEvaluating AI-Generated Code Quality\nWhen NOT to Use AI in Development",
			),
			array(
				'name' => 'PHP Ecosystem Radar',
				'content_goal' => 'Track PHP ecosystem updates and translate them into implementation-ready guidance',
				'topics' => "What Changed in PHP 8.4 for Backend Teams\nLaravel Release Notes: What Matters in Production\nSymfony Upgrade Guide: Breaking Changes to Watch\nComposer Dependency Strategy for Long-Lived Projects\nFramework Migration Checklists for Teams\nPHP Package Vetting for Security and Stability\nInterpreting RFCs for Engineering Planning\nHow to Adopt New PHP Features Safely\nRuntime Upgrades with Minimal Risk\nDeprecation Management in PHP Applications",
			),
			array(
				'name' => 'Security Intelligence Briefing',
				'content_goal' => 'Deliver actionable security briefings tied to current vulnerabilities and prevention patterns',
				'topics' => "CVE Triage Workflow for Engineering Teams\nPatch Prioritization Under Time Constraints\nHow to Communicate Security Risk to Stakeholders\nDependency Vulnerability Response Playbook\nFrom Advisory to Action: Turning Alerts into Fixes\nThreat Modeling for Existing Applications\nSecure Defaults for New Services\nIncident Readiness Checklist for Web Apps\nHow to Validate Security Fixes in CI\nPost-Incident Lessons Learned Template",
			),
			array(
				'name' => 'Software Architecture Patterns',
				'content_goal' => 'Deep-dive architectural patterns, domain modeling, and modular system design',
				'topics' => "Implementing Domain-Driven Design Bounded Contexts\nHexagonal Architecture (Ports and Adapters) in Modern Backend Systems\nCQRS and Event Sourcing Architectural Trade-offs\nEvent-Driven Architecture vs Synchronous REST\nDesigning Resilient Saga Patterns for Distributed Transactions\nOutbox Pattern for Reliable Message Publishing\nModular Monolith vs Microservices: Strategic Decision Matrix\nApplying SOLID Principles in Enterprise Web Applications\nImplementing the Strategy and Factory Patterns for Extensible Code\nDesigning Clean Application Layers: Domain, Application, and Infrastructure",
			),
			array(
				'name' => 'Refactoring & Code Smells',
				'content_goal' => 'Step-by-step legacy code refactoring, anti-pattern remediation, and clean code techniques',
				'topics' => "Refactoring God Classes into Cohesive Domain Services\nEliminating Primitive Obsession with Value Objects\nSafe Refactoring of Nested Conditionals using Guard Clauses\nBreaking Hidden Coupling with Dependency Inversion\nUsing the Strangler Fig Pattern to Modernize Legacy Systems\nExtracting Service Objects from Bloated Controllers\nCharacterization Testing: Writing Safety Nets for Untested Legacy Code\nRefactoring Switch Statements using Polymorphism and Strategy Patterns\nManaging and Paying Down Technical Debt in Agile Teams\nAutomating Code Quality Gates with Static Analysis Tools",
			),
			array(
				'name' => 'Test Strategy & Automation',
				'content_goal' => 'Practical test-driven development, resilient test doubles, and CI/CD quality engineering',
				'topics' => "Mastering the Red-Green-Refactor Cycle in Practice\nTest Doubles Demystified: Mocks vs Stubs vs Fakes vs Spies\nWriting Fast and Deterministic Integration Tests with In-Memory Databases\nMutation Testing: Validating the Quality of Your Test Suite\nContract Testing for Microservices with Pact\nDesigning Clean Test Fixtures and Object Mothers\nTesting Asynchronous Queue Workers and Event Listeners\nEnd-to-End Testing Strategies without Flaky Tests\nEmbedding Automated Quality Gates in CI Pipelines\nProperty-Based Testing for Edge Case Discovery",
			),
		);
		$post_targets = $this->get_campaign_post_targets();

		foreach ($campaigns as &$campaign) {
			$campaign['target_posts_per_' . $this->get_primary_distribution_period()] = isset($post_targets[$campaign['name']]) ? (int) $post_targets[$campaign['name']] : 0;
		}
		unset($campaign);

		return $campaigns;
	}

	/**
	 * Returns the seed data array for all templates to create, with post quantities derived from campaign distribution targets.
	 *
	 * @return array List of template data arrays with name, campaign, voice, structure, category, and generation settings keys.
	 */
	private function get_template_seed_data() {
		$templates = array(
			array(
				'name' => 'Beginner How-To',
				'description' => 'Beginner-friendly tutorials for fundamental concepts',
				'campaign_name' => 'Developer Foundations',
				'voice_name' => 'Hands-On Tutorial Coach',
				'structure_name' => 'Evergreen How-To Guide',
				'categories' => array('Backend Development', 'PHP Development'),
				'post_tags' => 'php,backend,developer-fundamentals,tutorial',
				'prompt_template' => 'Write a comprehensive beginner-friendly tutorial about {{topic}}. Focus on helping developers learn by doing.',
				'generate_featured_image' => 0,
				'featured_image_source' => 'ai_prompt',
				'include_sources' => 0,
			),
			array(
				'name' => 'Intermediate Backend',
				'description' => 'Intermediate backend development patterns and practices',
				'campaign_name' => 'Backend Engineering',
				'voice_name' => 'DevStackTips Default',
				'structure_name' => 'Advanced Technical Tutorial',
				'categories' => array('Backend Development', 'Database'),
				'post_tags' => 'backend,architecture,api,scalability',
				'prompt_template' => 'Write an intermediate-level backend development tutorial about {{topic}}. Assume familiarity with programming fundamentals.',
				'generate_featured_image' => 1,
				'featured_image_source' => 'ai_prompt',
				'image_prompt' => 'A clean, modern illustration representing {{topic}} in software development. Abstract geometric shapes, blues and teals, professional developer aesthetic, minimalist design',
				'include_sources' => 0,
			),
			array(
				'name' => 'Security Guide',
				'description' => 'Security best practices and vulnerability prevention',
				'campaign_name' => 'Security First',
				'voice_name' => 'Senior Backend Mentor',
				'structure_name' => 'Security Best Practices',
				'categories' => array('Security', 'Backend Development'),
				'post_tags' => 'security,owasp,secure-coding,vulnerabilities',
				'prompt_template' => 'Write a security-focused guide about {{topic}}. Help developers build secure applications by explaining vulnerabilities and secure patterns.',
				'generate_featured_image' => 1,
				'featured_image_source' => 'ai_prompt',
				'image_prompt' => 'A secure lock symbol overlaid on a modern application window or code editor, cybersecurity theme, dark blues and greens, shield iconography, professional and trustworthy aesthetic',
				'include_sources' => 0,
			),
			array(
				'name' => 'Framework Comparison',
				'description' => 'Fair comparisons of frameworks, libraries, and tools',
				'campaign_name' => 'Framework & Tool Comparisons',
				'voice_name' => 'Neutral Technical Analyst',
				'structure_name' => 'Comparison Article',
				'categories' => array('Framework Guides'),
				'post_tags' => 'comparison,frameworks,tradeoffs,decision-making',
				'prompt_template' => 'Write a balanced, objective comparison of {{topic}}. Present both options fairly without bias.',
				'generate_featured_image' => 0,
				'featured_image_source' => 'ai_prompt',
				'include_sources' => 0,
			),
			array(
				'name' => 'Developer Tooling',
				'description' => 'Practical guides for developer tools and workflows',
				'campaign_name' => 'Developer Tooling',
				'voice_name' => 'Hands-On Tutorial Coach',
				'structure_name' => 'Tool / Workflow Explainer',
				'categories' => array('DevOps & Tools'),
				'post_tags' => 'devops,developer-tools,automation,workflow',
				'prompt_template' => 'Write a practical guide for {{topic}}. Show developers how to use this tool effectively in their daily workflow.',
				'generate_featured_image' => 1,
				'featured_image_source' => 'unsplash',
				'unsplash_keywords' => 'developer tools, programming, code, terminal, workflow',
				'include_sources' => 0,
			),
			array(
				'name' => 'AI for Developers',
				'description' => 'Practical AI guidance for developer workflows',
				'campaign_name' => 'AI for Developers',
				'voice_name' => 'AI Engineering Editor',
				'structure_name' => 'AI-for-Devs Article',
				'categories' => array('AI for Developers'),
				'post_tags' => 'ai,developer-productivity,prompt-engineering,quality-control',
				'prompt_template' => 'Write practical guidance about {{topic}}. Focus on real developer use cases and address both benefits and limitations honestly.',
				'generate_featured_image' => 0,
				'featured_image_source' => 'ai_prompt',
				'include_sources' => 0,
			),
			array(
				'name' => 'Security News',
				'description' => 'Security-focused content informed by current vulnerability reports',
				'campaign_name' => 'Security Intelligence Briefing',
				'voice_name' => 'Senior Backend Mentor',
				'structure_name' => 'Security Best Practices',
				'categories' => array('Security'),
				'post_tags' => 'security-news,cve,incident-response,threat-intelligence',
				'source_group' => 'Security News',
				'include_sources' => 1,
				'prompt_template' => 'Write a security guide about {{topic}}. Reference current security trends and vulnerabilities where relevant.',
				'generate_featured_image' => 1,
				'featured_image_source' => 'ai_prompt',
				'image_prompt' => 'Cybersecurity shield protecting code and data, modern digital security concept, dark theme with accent colors',
			),
			array(
				'name' => 'PHP Framework Deep Dive',
				'description' => 'In-depth PHP framework tutorials informed by ecosystem updates',
				'campaign_name' => 'PHP Ecosystem Radar',
				'voice_name' => 'DevStackTips Default',
				'structure_name' => 'Advanced Technical Tutorial',
				'categories' => array('PHP Development', 'Framework Guides'),
				'post_tags' => 'php,laravel,symfony,ecosystem-updates',
				'source_group' => 'PHP Ecosystem',
				'include_sources' => 1,
				'prompt_template' => 'Write a detailed tutorial about {{topic}}. Incorporate current best practices from the PHP community.',
				'generate_featured_image' => 0,
				'featured_image_source' => 'ai_prompt',
			),
			array(
				'name' => 'Architecture Pattern Deep Dive',
				'description' => 'Architectural pattern deep-dives with system diagrams and trade-off analysis',
				'campaign_name' => 'Software Architecture Patterns',
				'voice_name' => 'Staff Software Architect',
				'structure_name' => 'Architecture Pattern Deep Dive',
				'categories' => array('Backend Development'),
				'post_tags' => 'software-architecture,design-patterns,ddd,clean-architecture,system-design',
				'prompt_template' => 'Write an in-depth architectural guide about {{topic}}. Focus on structural design, trade-offs, modularity, and production-tested patterns.',
				'generate_featured_image' => 1,
				'featured_image_source' => 'ai_prompt',
				'image_prompt' => 'A clean, modern technical blueprint and architectural system diagram, isometric modular components, sleek dark mode theme with neon blue and cyan accents, high-end developer engineering aesthetic',
				'include_sources' => 0,
			),
			array(
				'name' => 'Refactoring & Code Smells',
				'description' => 'Practical code smell elimination and safe step-by-step legacy refactoring',
				'campaign_name' => 'Refactoring & Code Smells',
				'voice_name' => 'Code Craftsman & Refactoring Lead',
				'structure_name' => 'Refactoring & Code Smells Case Study',
				'categories' => array('Backend Development'),
				'post_tags' => 'refactoring,code-smells,clean-code,technical-debt,legacy-code',
				'prompt_template' => 'Write a practical refactoring guide about {{topic}}. Demonstrate how to transform problematic code into clean, maintainable architecture with safety-net tests.',
				'generate_featured_image' => 1,
				'featured_image_source' => 'ai_prompt',
				'image_prompt' => 'An elegant visual metaphor for code transformation and refactoring, tangled complex glowing lines organizing into clean orderly modular geometric code blocks, dark theme with emerald green and teal highlights',
				'include_sources' => 0,
			),
			array(
				'name' => 'Test Strategy & Automation Guide',
				'description' => 'Test-driven development, automated testing patterns, and quality engineering',
				'campaign_name' => 'Test Strategy & Automation',
				'voice_name' => 'Test Automation & QA Lead',
				'structure_name' => 'Test Strategy & Automation Guide',
				'categories' => array('DevOps & Tools', 'Backend Development'),
				'post_tags' => 'tdd,automated-testing,unit-testing,integration-testing,qa,code-quality',
				'prompt_template' => 'Write a rigorous, practical automated testing guide about {{topic}}. Walk through testing cycles, fixtures, mocking strategies, and robust test assertions.',
				'generate_featured_image' => 1,
				'featured_image_source' => 'ai_prompt',
				'image_prompt' => 'A sleek developer dashboard showing automated test suites passing with glowing green checkmarks, code coverage metrics, terminal test runner output, dark background with vibrant green and turquoise accents',
				'include_sources' => 0,
			),
		);
		$post_targets = $this->get_campaign_post_targets();

		foreach ($templates as &$template) {
			$weekly_target = isset($post_targets[$template['campaign_name']]) ? (int) $post_targets[$template['campaign_name']] : 1;
			$template['post_quantity'] = max(1, $weekly_target);
			$template['target_posts_per_' . $this->get_primary_distribution_period()] = $template['post_quantity'];
		}
		unset($template);

		return $templates;
	}

	/**
	 * Returns the seed data array for all schedules to create, with per-period post targets derived from their linked templates.
	 *
	 * @return array List of schedule data arrays with 'template_name', 'title', 'frequency', 'weekday', 'start_time', 'is_active', and computed target post count keys.
	 */
	private function get_schedule_seed_data() {
		$schedules = array(
			array(
				'template_name' => 'Beginner How-To',
				'title' => 'Core Monday - Developer Foundations',
				'frequency' => 'every_monday',
				'weekday' => 1,
				'start_time' => '09:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Intermediate Backend',
				'title' => 'Core Tuesday - Backend Engineering',
				'frequency' => 'every_tuesday',
				'weekday' => 2,
				'start_time' => '09:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Security Guide',
				'title' => 'Core Wednesday - Security First',
				'frequency' => 'every_wednesday',
				'weekday' => 3,
				'start_time' => '09:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Framework Comparison',
				'title' => 'Core Thursday - Framework Comparisons',
				'frequency' => 'every_thursday',
				'weekday' => 4,
				'start_time' => '09:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Developer Tooling',
				'title' => 'Core Friday - Developer Tooling',
				'frequency' => 'every_friday',
				'weekday' => 5,
				'start_time' => '09:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'AI for Developers',
				'title' => 'Flex Tuesday PM - AI Workflow Insights',
				'frequency' => 'every_tuesday',
				'weekday' => 2,
				'start_time' => '14:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Security News',
				'title' => 'Flex Thursday PM - Security Intelligence Briefing',
				'frequency' => 'every_thursday',
				'weekday' => 4,
				'start_time' => '14:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'PHP Framework Deep Dive',
				'title' => 'Flex Saturday - PHP Ecosystem Radar',
				'frequency' => 'every_saturday',
				'weekday' => 6,
				'start_time' => '10:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Architecture Pattern Deep Dive',
				'title' => 'Core Monday PM - Software Architecture Patterns',
				'frequency' => 'every_monday',
				'weekday' => 1,
				'start_time' => '14:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Test Strategy & Automation Guide',
				'title' => 'Core Wednesday PM - Test Strategy & Automation',
				'frequency' => 'every_wednesday',
				'weekday' => 3,
				'start_time' => '14:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Refactoring & Code Smells',
				'title' => 'Core Friday PM - Refactoring & Code Smells',
				'frequency' => 'every_friday',
				'weekday' => 5,
				'start_time' => '14:00',
				'is_active' => 1,
			),
		);
		$template_targets = array();

		foreach ($this->get_template_seed_data() as $template) {
			$template_targets[$template['name']] = isset($template['post_quantity']) ? (int) $template['post_quantity'] : 1;
		}

		foreach ($schedules as &$schedule) {
			$schedule['target_posts_per_' . $this->get_primary_distribution_period()] = isset($template_targets[$schedule['template_name']]) ? $template_targets[$schedule['template_name']] : 1;
		}
		unset($schedule);

		return $schedules;
	}

	/* ------------------------------------------------------------------ */
	/* v3 configuration                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Source groups added in v3.
	 *
	 * @return array
	 */
	private function get_v3_source_group_seed_data() {
		return array(
			array(
				'name'        => 'Backend & Architecture',
				'slug'        => 'backend-architecture',
				'description' => 'Software architecture, backend engineering, testing and refactoring practice',
			),
			array(
				'name'        => 'AI & Dev Tooling',
				'slug'        => 'ai-dev-tooling',
				'description' => 'AI for developers, developer tools, editors, containers and workflows',
			),
		);
	}

	/**
	 * Sources added in v3 (all verified to serve RSS/Atom).
	 *
	 * @return array
	 */
	private function get_v3_source_seed_data() {
		return array(
			array('name' => 'Martin Fowler', 'url' => 'https://martinfowler.com/feed.atom', 'group_slug' => 'backend-architecture', 'is_active' => 1),
			array('name' => 'The Pragmatic Engineer', 'url' => 'https://blog.pragmaticengineer.com/rss/', 'group_slug' => 'backend-architecture', 'is_active' => 1),
			array('name' => 'InfoQ', 'url' => 'https://feed.infoq.com/', 'group_slug' => 'backend-architecture', 'is_active' => 1),
			array('name' => 'stitcher.io (PHP)', 'url' => 'https://stitcher.io/rss', 'group_slug' => 'backend-architecture', 'is_active' => 1),
			array('name' => 'GitHub Blog', 'url' => 'https://github.blog/feed/', 'group_slug' => 'ai-dev-tooling', 'is_active' => 1),
			array('name' => 'Simon Willison', 'url' => 'https://simonwillison.net/atom/everything/', 'group_slug' => 'ai-dev-tooling', 'is_active' => 1),
			array('name' => 'PhpStorm Blog', 'url' => 'https://blog.jetbrains.com/phpstorm/feed/', 'group_slug' => 'ai-dev-tooling', 'is_active' => 1),
			array('name' => 'Docker Blog', 'url' => 'https://www.docker.com/blog/feed/', 'group_slug' => 'ai-dev-tooling', 'is_active' => 1),
		);
	}

	/**
	 * Slug prompt for a content type.
	 *
	 * @param string $type
	 * @return string
	 */
	private function get_slug_prompt($type) {
		$base = 'Return only a URL slug: lowercase, words separated by hyphens, 3 to 6 words, primary keyword first, no stop words (a, the, and, of, to, for, with), no dates or years, no version numbers unless they define the topic, no punctuation.';

		$rules = array(
			'tutorial'     => 'Start with the main technology or task, e.g. "php-enum-validation-guide".',
			'comparison'   => 'Use the pattern "x-vs-y" followed by at most two context words, e.g. "laravel-vs-symfony-apis".',
			'security'     => 'Lead with the vulnerability or control, e.g. "sql-injection-prevention-php".',
			'news'         => 'Lead with the affected product or CVE identifier and the issue, e.g. "cve-2026-1234-openssl-patch". Keep it evergreen: no "latest" or "new".',
			'architecture' => 'Lead with the pattern or design concept, e.g. "hexagonal-architecture-php-services".',
			'refactoring'  => 'Lead with the code smell or refactoring move, e.g. "refactor-god-class-php".',
			'testing'      => 'Lead with the testing technique, e.g. "phpunit-test-doubles-guide".',
			'tooling'      => 'Lead with the tool name and the task, e.g. "docker-compose-php-dev-setup".',
			'ai'           => 'Lead with the AI technique or tool and the developer task, e.g. "llm-code-review-workflow".',
			'default'      => 'Lead with the most specific technical term in the title.',
		);

		return $base . ' ' . (isset($rules[$type]) ? $rules[$type] : $rules['default']);
	}

	/**
	 * v3 template configuration, keyed by live template name.
	 *
	 * Keys: voice, structure (schedule article structure), slug_type, title_prompt,
	 * image_subject (featured image, used only when the template has no image prompt),
	 * source_group (slug), content_images (in-content AI images).
	 *
	 * @return array
	 */
	private function get_v3_template_config() {
		return array(
			'Beginner How-To' => array(
				'voice'          => 'Hands-On Tutorial Coach',
				'structure'      => 'Evergreen How-To Guide',
				'slug_type'      => 'tutorial',
				'title_prompt'   => 'Write a beginner-friendly how-to title that starts with "How to" and names the exact technology and outcome. Under 65 characters, no clickbait, no year.',
				'image_subject'  => 'a friendly step-by-step learning path with numbered stages leading to a working result',
				'source_group'   => 'php-ecosystem',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Simple step-by-step flow diagram of the setup or process described for {{topic}}, three to five connected stages'),
				),
			),
			'Intermediate Backend' => array(
				'voice'          => 'DevStackTips Default',
				'structure'      => 'Advanced Technical Tutorial',
				'slug_type'      => 'tutorial',
				'title_prompt'   => 'Write a specific title for an intermediate backend tutorial: name the technique and the concrete benefit (performance, reliability, scale). Under 65 characters, no year.',
				'image_subject'  => 'backend services, an API gateway and a database connected by clean data-flow lines',
				'source_group'   => 'backend-architecture',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Request flow diagram for {{topic}}: client, API layer, services and data store as connected boxes with arrows'),
					array('placement' => 'after_section_4', 'prompt' => 'Before and after comparison diagram showing the backend design improvement described for {{topic}}'),
				),
			),
			'Security Guide' => array(
				'voice'          => 'Senior Backend Mentor',
				'structure'      => 'Security Best Practices',
				'slug_type'      => 'security',
				'title_prompt'   => 'Write a security guide title that names the vulnerability or control and the stack, e.g. "Preventing SQL Injection in PHP with Prepared Statements". Under 65 characters, no fear-mongering.',
				'image_subject'  => 'a shield protecting a layered application stack, with a blocked attack path',
				'source_group'   => 'security-news',
				'content_images' => array(),
			),
			'Framework Comparison' => array(
				'voice'          => 'Neutral Technical Analyst',
				'structure'      => 'Comparison Article',
				'slug_type'      => 'comparison',
				'title_prompt'   => 'Write a neutral comparison title in the form "X vs Y: <deciding factor>" that names both options and the main criterion. Under 65 characters, no winner in the title, no year.',
				'image_subject'  => 'two balanced technology stacks side by side on a scale, evenly weighted',
				'source_group'   => 'php-ecosystem',
				'content_images' => array(),
			),
			'Developer Tooling' => array(
				'voice'          => 'Hands-On Tutorial Coach',
				'structure'      => 'Tool / Workflow Explainer',
				'slug_type'      => 'tooling',
				'title_prompt'   => 'Write a practical title that names the tool and the workflow it improves, e.g. "Speed Up PHP Debugging with Xdebug Step Filters". Under 65 characters, no year.',
				'image_subject'  => 'a developer terminal and editor connected to automation gears and a pipeline',
				'source_group'   => 'ai-dev-tooling',
				'content_images' => array(),
			),
			'AI for Developers' => array(
				'voice'          => 'AI Engineering Editor',
				'structure'      => 'AI-for-Devs Article',
				'slug_type'      => 'ai',
				'title_prompt'   => 'Write a grounded title about using AI in a specific developer task; name the task and the technique, avoid hype words like "revolutionary" or "game-changer". Under 65 characters.',
				'image_subject'  => 'a code editor with an assistant panel and a human review checkpoint',
				'source_group'   => 'ai-dev-tooling',
				'content_images' => array(),
			),
			'Security News' => array(
				'voice'          => 'Senior Backend Mentor',
				'structure'      => 'News / Trend Analysis',
				'slug_type'      => 'news',
				'title_prompt'   => 'Write a factual security briefing title that names the affected software or CVE and what developers must do (patch, mitigate, audit). Under 70 characters, no sensationalism.',
				'image_subject'  => 'a security alert radar scanning a network of application nodes',
				'source_group'   => 'security-news',
				'content_images' => array(),
			),
			'PHP Framework Deep Dive' => array(
				'voice'          => 'DevStackTips Default',
				'structure'      => 'Advanced Technical Tutorial',
				'slug_type'      => 'tutorial',
				'title_prompt'   => 'Write a deep-dive title that names the PHP framework, the feature and the practical outcome, e.g. "Laravel Queues: Reliable Retries with Job Middleware". Under 65 characters, no year.',
				'image_subject'  => 'modular PHP framework components fitting together like building blocks',
				'source_group'   => 'php-ecosystem',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Architecture diagram of the framework components involved in {{topic}} and how a request moves through them'),
				),
			),
			'Architecture Pattern Deep Dive' => array(
				'voice'          => 'Staff Software Architect',
				'structure'      => 'Architecture Pattern Deep Dive',
				'slug_type'      => 'architecture',
				'title_prompt'   => 'Write an architecture title that names the pattern and the problem it solves, e.g. "Hexagonal Architecture: Isolating PHP Domain Logic". Under 65 characters, no year.',
				'image_subject'  => 'isometric modular system components with clear boundaries and connections',
				'source_group'   => 'backend-architecture',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Component diagram of the architecture pattern in {{topic}}: modules, boundaries and dependencies as labeled-free boxes and arrows'),
					array('placement' => 'after_section_4', 'prompt' => 'Sequence-style diagram of one request flowing through the architecture described in {{topic}}'),
				),
			),
			'Refactoring & Code Smells' => array(
				'voice'          => 'Code Craftsman & Refactoring Lead',
				'structure'      => 'Refactoring & Code Smells Case Study',
				'slug_type'      => 'refactoring',
				'title_prompt'   => 'Write a refactoring title that names the code smell and the refactoring move, e.g. "Breaking Up a God Class with Extract Service". Under 65 characters, no year.',
				'image_subject'  => 'tangled lines reorganizing into clean, orderly modular blocks',
				'source_group'   => 'backend-architecture',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Before and after diagram of the code structure in {{topic}}: tangled dependencies on the left, clean separated modules on the right'),
				),
			),
			'Test Strategy & Automation Guide' => array(
				'voice'          => 'Test Automation & QA Lead',
				'structure'      => 'Test Strategy & Automation Guide',
				'slug_type'      => 'testing',
				'title_prompt'   => 'Write a testing title that names the technique and the confidence it gives, e.g. "Contract Tests for PHP APIs Without Flaky Mocks". Under 65 characters, no year.',
				'image_subject'  => 'a test pyramid and a CI pipeline with passing checks',
				'source_group'   => 'backend-architecture',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Test strategy diagram for {{topic}}: test pyramid layers and where they run in a CI pipeline'),
				),
			),
		);
	}

	/**
	 * v3 author configuration, keyed by live author name.
	 *
	 * v2_author: also switch to score-based approval (85+) and a daily 1-post cadence.
	 * category: set only when the author has none.
	 *
	 * @return array
	 */
	private function get_v3_author_config() {
		return array(
			'Backend Architecture Specialist' => array('v2_author' => true, 'slug_type' => 'architecture', 'category' => 'Backend Development', 'image_subject' => 'backend services and APIs connected in a clean system diagram'),
			'Security Expert' => array('v2_author' => true, 'slug_type' => 'security', 'category' => 'Security', 'source_group' => 'security-news', 'image_subject' => 'a shield protecting application layers from an attack path'),
			'DevOps Practitioner' => array('v2_author' => true, 'slug_type' => 'tooling', 'category' => 'DevOps & Tools', 'image_subject' => 'a CI/CD pipeline with containers and automation gears'),
			'Framework Analyst' => array('v2_author' => true, 'slug_type' => 'comparison', 'category' => 'Framework Guides', 'image_subject' => 'framework building blocks compared side by side'),
			'AI Engineering Pragmatist' => array('v2_author' => true, 'slug_type' => 'ai', 'category' => 'AI for Developers', 'image_subject' => 'a code editor with an AI assistant and a human review step'),
			'Staff Software Architect' => array('v2_author' => true, 'slug_type' => 'architecture', 'category' => 'Backend Development', 'image_subject' => 'isometric modular architecture with clear domain boundaries'),
			'Test Automation & Quality Specialist' => array('v2_author' => true, 'slug_type' => 'testing', 'category' => 'DevOps & Tools', 'image_subject' => 'a test pyramid with passing checks in a pipeline'),
			'Code Quality & Refactoring Craftsman' => array('v2_author' => true, 'slug_type' => 'refactoring', 'category' => 'Backend Development', 'image_subject' => 'messy code blocks being reorganized into clean modules'),
			'Marcus Vance' => array('v2_author' => false, 'slug_type' => 'architecture', 'category' => 'Backend Development', 'image_subject' => 'cloud-native distributed services across regions with message queues'),
			'Elena Rostova' => array('v2_author' => false, 'slug_type' => 'default', 'category' => 'Web Development', 'image_subject' => 'a modern frontend component tree with performance gauges'),
			'Devon Miller (multi post)' => array('v2_author' => false, 'slug_type' => 'security', 'category' => 'Security', 'image_subject' => 'a secured CI/CD pipeline with signed artifacts and a shield'),
		);
	}

	/**
	 * Settings v3 always sets.
	 *
	 * @return array Option name => value.
	 */
	private function get_v3_settings() {
		return array(
			'aips_allowed_image_sources'   => array('ai_prompt', 'media_library'),
			'aips_max_post_images_limit'   => 3,
			'aips_ai_assistance_guidance'  => 'Write for DevStackTips: professional software engineers and web developers (mid to senior level). Voice: authoritative yet approachable, expert-level, practical. Prefer real-world patterns over theory, keep a security-first mindset, use precise technical terms, and avoid hype, clickbait and filler. Keep suggestions concise and specific to PHP, backend, architecture, security, DevOps, testing and AI-for-developers topics.',
		);
	}
}

if (!defined('AIPS_DEVSTACKTIPS_SETUP_NO_RUN')) {
	if (!defined('WP_CLI') || !WP_CLI) {
		wp_die(esc_html__('The DevStackTips setup script can only be run from WP-CLI.', 'ai-post-scheduler'));
	}

	$aips_devstacktips_args = (isset($args) && is_array($args)) ? $args : array();
	$aips_devstacktips_setup = new AIPS_DevStackTips_Setup($aips_devstacktips_args);

	if (!$aips_devstacktips_setup->run()) {
		WP_CLI::error('DevStackTips setup finished with errors (see [ERR] lines above).');
	}
}
