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

	/**
	 * @param array $args Positional WP-CLI arguments.
	 */
	public function __construct(array $args = array()) {
		foreach ($args as $arg) {
			$arg = (string) $arg;
			if (in_array($arg, array('plan', 'apply', 'rollback'), true)) {
				$this->mode = $arg;
			}
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
		if ('plan' !== $this->mode) {
			$this->line("Mode '{$this->mode}' is not implemented yet in this build.");
			return false;
		}

		$this->print_plan($this->plan());

		return true;
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
