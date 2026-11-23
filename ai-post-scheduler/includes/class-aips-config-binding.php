<?php
/**
 * AIPS Config Binding.
 *
 * Per-site, persistent map from a stable configuration key to the live object
 * ID, so configuration (the DevStackTips setup tool and later ID-driven flows)
 * can address objects by a portable key rather than by their mutable name.
 *
 * A stable key is "<entity_type>:<slug>", where the slug is derived once, by
 * name, when the object is first seen. The map is then authoritative: it is
 * never re-derived for an already-bound key, so renaming an object on the site
 * does not break the binding. New objects (created by seeding) are stamped into
 * the map at creation time. On a fresh install the map is empty and is
 * bootstrapped by name, which is why the same committed keys work on any site.
 *
 * Stored as the option AIPS_Config_Binding::OPTION (autoload off). Read-only
 * against config data (via AIPS_Config_Reader); the only write is the option.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Config_Binding {

	/**
	 * Option holding the stable-key => object-ID map for this site.
	 */
	const OPTION = 'aips_v3_binding';

	/**
	 * Identity-bearing configuration entities.
	 *
	 * type => [ field: the name/slug field keyed on, id: the ID column,
	 *           source: 'table' (read_table) or a taxonomy name (read_terms) ].
	 *
	 * @var array<string,array<string,string>>
	 */
	const BINDABLE = array(
		'campaigns'       => array('field' => 'name',        'id' => 'id',      'source' => 'table'),
		'templates'       => array('field' => 'name',        'id' => 'id',      'source' => 'table'),
		'schedules'       => array('field' => 'title',       'id' => 'id',      'source' => 'table'),
		'authors'         => array('field' => 'name',        'id' => 'id',      'source' => 'table'),
		'voices'          => array('field' => 'name',        'id' => 'id',      'source' => 'table'),
		'structures'      => array('field' => 'name',        'id' => 'id',      'source' => 'table'),
		'prompt_sections' => array('field' => 'section_key', 'id' => 'id',      'source' => 'table'),
		'post_slices'     => array('field' => 'name',        'id' => 'id',      'source' => 'table'),
		'sources'         => array('field' => 'label',       'id' => 'id',      'source' => 'table'),
		'source_groups'   => array('field' => 'slug',        'id' => 'term_id', 'source' => 'aips_source_group'),
		'categories'      => array('field' => 'slug',        'id' => 'term_id', 'source' => 'category'),
	);

	/** @var AIPS_Config_Reader */
	private $reader;

	/**
	 * @param AIPS_Config_Reader|null $reader Config reader; a default is created when omitted.
	 */
	public function __construct(AIPS_Config_Reader $reader = null) {
		$this->reader = $reader ?: new AIPS_Config_Reader();
	}

	/**
	 * Build a stable key from an entity type and a name.
	 *
	 * @param string $entity_type Entity type (must be in BINDABLE).
	 * @param string $name        Name/slug value.
	 * @return string|null Key, or null when the name slugifies to empty.
	 */
	public static function make_key($entity_type, $name) {
		$slug = sanitize_title((string) $name);

		return '' === $slug ? null : $entity_type . ':' . $slug;
	}

	/**
	 * Derive the stable key for a live row of a given entity type.
	 *
	 * @param string       $entity_type Entity type (must be in BINDABLE).
	 * @param array|object $row         Row.
	 * @return string|null
	 */
	public function key_for($entity_type, $row) {
		if (!isset(self::BINDABLE[$entity_type])) {
			return null;
		}

		$row   = (array) $row;
		$field = self::BINDABLE[$entity_type]['field'];
		$name  = isset($row[$field]) ? $row[$field] : '';

		return self::make_key($entity_type, $name);
	}

	/**
	 * Discover key => ID bindings from the live installation.
	 *
	 * @return array{bindings:array<string,int>,collisions:array<string,int[]>,ids_by_type:array<string,int[]>}
	 *   bindings: first ID seen per key; collisions: every ID for keys that
	 *   more than one live object maps to (ambiguous, left for a human);
	 *   ids_by_type: every live ID per entity type (IDs are unique only within
	 *   a type, so staleness must be judged per type).
	 */
	public function discover() {
		$bindings    = array();
		$collisions  = array();
		$ids_by_type = array();

		foreach (self::BINDABLE as $type => $def) {
			$rows = ('table' === $def['source'])
				? $this->reader->read_table($type, ARRAY_A)
				: $this->reader->read_terms($def['source'], ARRAY_A);

			foreach ($rows as $row) {
				$row = (array) $row;
				if (!isset($row[$def['id']])) {
					continue;
				}

				$id                   = (int) $row[$def['id']];
				$ids_by_type[$type][] = $id;

				$key = $this->key_for($type, $row);
				if (null === $key) {
					continue;
				}

				if (isset($bindings[$key])) {
					if (!isset($collisions[$key])) {
						$collisions[$key] = array($bindings[$key]);
					}
					$collisions[$key][] = $id;
					continue;
				}

				$bindings[$key] = $id;
			}
		}

		return array('bindings' => $bindings, 'collisions' => $collisions, 'ids_by_type' => $ids_by_type);
	}

	/**
	 * Entity type encoded in a stable key ("templates:foo" => "templates").
	 *
	 * @param string $key
	 * @return string
	 */
	private function type_of($key) {
		$pos = strpos($key, ':');

		return false === $pos ? '' : substr($key, 0, $pos);
	}

	/**
	 * The stored binding map for this site.
	 *
	 * @return array<string,int>
	 */
	public function load() {
		$value = get_option(self::OPTION, array());

		if (!is_array($value)) {
			return array();
		}

		$map = array();
		foreach ($value as $key => $id) {
			$map[(string) $key] = (int) $id;
		}

		return $map;
	}

	/**
	 * Persist the binding map.
	 *
	 * @param array<string,int> $map
	 * @return void
	 */
	public function save(array $map) {
		update_option(self::OPTION, $map, false);
	}

	/**
	 * Merge newly discovered bindings into the stored map, never overwriting an
	 * existing key (rename survival), and persist the result.
	 *
	 * @return array{added:array<string,int>,collisions:array<string,int[]>,stale:string[],total:int}
	 *   added: keys bound this run; collisions: ambiguous keys skipped;
	 *   stale: stored keys whose ID no longer exists live.
	 */
	public function sync() {
		$stored     = $this->load();
		$discovered = $this->discover();

		$added = array();
		foreach ($discovered['bindings'] as $key => $id) {
			if (!isset($stored[$key])) {
				$stored[$key] = $id;
				$added[$key]  = $id;
			}
		}

		// Staleness is per entity type: IDs are unique only within a type, so a
		// deleted voice's ID must be checked against live voice IDs, not every
		// live ID (a term could share the number).
		$ids_by_type = $discovered['ids_by_type'];
		$stale       = array();
		foreach ($stored as $key => $id) {
			$type     = $this->type_of($key);
			$live_ids = isset($ids_by_type[$type]) ? $ids_by_type[$type] : array();
			if (!in_array($id, $live_ids, true)) {
				$stale[] = $key;
			}
		}

		$this->save($stored);

		return array(
			'added'      => $added,
			'collisions' => $discovered['collisions'],
			'stale'      => $stale,
			'total'      => count($stored),
		);
	}

	/**
	 * Effective bindings without persisting anything: the stored map, plus any
	 * not-yet-bound keys discovered by name this moment. For read-only planning
	 * on a site whose registry has not been written yet.
	 *
	 * @return array{bindings:array<string,int>,collisions:array<string,int[]>}
	 */
	public function preview() {
		$effective  = $this->load();
		$discovered = $this->discover();

		foreach ($discovered['bindings'] as $key => $id) {
			if (!isset($effective[$key])) {
				$effective[$key] = $id;
			}
		}

		return array('bindings' => $effective, 'collisions' => $discovered['collisions']);
	}

	/**
	 * Resolve a stable key to its live object ID.
	 *
	 * @param string $key
	 * @return int|null
	 */
	public function resolve($key) {
		$map = $this->load();

		return isset($map[$key]) ? (int) $map[$key] : null;
	}

	/**
	 * Stamp a single key => ID binding (e.g. for a freshly created object).
	 *
	 * @param string $key
	 * @param int    $id
	 * @return void
	 */
	public function stamp($key, $id) {
		$map       = $this->load();
		$map[$key] = (int) $id;
		$this->save($map);
	}
}
