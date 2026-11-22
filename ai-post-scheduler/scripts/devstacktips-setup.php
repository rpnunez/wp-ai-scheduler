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
 *
 * Planned next: apply (write by ID, with backup), seed (create missing), rollback.
 *
 * Usage (CLI only):
 *   wp eval-file wp-content/plugins/ai-post-scheduler/scripts/devstacktips-setup.php
 *   wp eval-file wp-content/plugins/ai-post-scheduler/scripts/devstacktips-setup.php plan
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

	/** @var string 'plan' (more modes added in later slices). */
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
	 * Apply the plan: update managed objects by ID through the repositories,
	 * writing a JSON backup before the first change. Nothing is created here
	 * (seeding lands in a later slice); 'absent' definitions are reported.
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
		$sync = $this->binding->sync();
		if (!empty($sync['collisions'])) {
			foreach ($sync['collisions'] as $key => $ids) {
				$this->warn(sprintf('Ambiguous key %s maps to IDs %s; left unbound.', $key, implode(', ', $ids)));
			}
		}

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
	 * @param string $entity 'template' or 'author'.
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

		foreach (AIPS_DevStackTips_Definitions::templates() as $key => $def) {
			$entries[] = $this->plan_template($key, $def);
		}

		foreach (AIPS_DevStackTips_Definitions::authors() as $key => $def) {
			$entries[] = $this->plan_author($key, $def);
		}

		foreach (AIPS_DevStackTips_Definitions::settings() as $name => $value) {
			$entries[] = $this->plan_setting($name, $value);
		}

		return $entries;
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
			return $this->absent_entry('template', $key, $def['name']);
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
			return $this->absent_entry('author', $key, $def['name']);
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

		$counts = array('update' => 0, 'noop' => 0, 'absent' => 0);

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
		$this->line(sprintf('Summary: %d to update, %d already correct, %d absent, %d ambiguous key(s).',
			$counts['update'], $counts['noop'], $counts['absent'], count($this->collisions)));
		$this->line('Nothing was written. Apply mode lands in a later slice.');
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
		);

		$this->backup_file = rtrim($this->backup_dir, '/\\') . DIRECTORY_SEPARATOR . 'devstacktips-id-backup-' . gmdate('Ymd-His') . '.json';

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
