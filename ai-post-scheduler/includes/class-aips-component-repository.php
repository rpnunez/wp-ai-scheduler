<?php
/**
 * Component Repository
 *
 * Database abstraction layer for article structure components.
 * Manages reusable structural components (Pros/Cons, FAQ Accordions,
 * Rating Boxes, How-To Steps, Comparison Tables, Callout Boxes).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Component_Repository
 *
 * Encapsulates CRUD and cache invalidation for the aips_components table.
 */
class AIPS_Component_Repository {
	use AIPS_Cacheable_Repository;

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @var string The table name (with prefix).
	 */
	private $table_name;

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * Initialize repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_components';
	}

	/**
	 * Get all components with optional filtering.
	 *
	 * @param bool $active_only Return only active components. Default false.
	 * @return array Array of component objects.
	 */
	public function get_all($active_only = false) {
		$active_only = (bool) $active_only;
		return $this->cache_read(
			'components.get_all',
			array('active_only' => $active_only),
			function() use ($active_only) {
				$where = $active_only ? 'WHERE is_active = 1' : '';
				return $this->wpdb->get_results("SELECT * FROM {$this->table_name} {$where} ORDER BY name ASC");
			}
		);
	}

	/**
	 * Get component by ID.
	 *
	 * @param int $id Component ID.
	 * @return object|null Component object or null.
	 */
	public function get_by_id($id) {
		$id = absint($id);
		if ($id <= 0) {
			return null;
		}

		return $this->cache_read(
			'components.get_by_id',
			array('component_id' => $id),
			function() use ($id) {
				return $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE id = %d",
					$id
				));
			}
		);
	}

	/**
	 * Get component by unique component_key.
	 *
	 * @param string $key Component key (e.g. 'pros_cons', 'faq_accordion').
	 * @return object|null Component object or null.
	 */
	public function get_by_key($key) {
		$key = sanitize_key($key);
		if (empty($key)) {
			return null;
		}

		return $this->cache_read(
			'components.get_by_key',
			array('component_key' => $key),
			function() use ($key) {
				return $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE component_key = %s",
					$key
				));
			}
		);
	}

	/**
	 * Alias for get_by_key() to support slug-based lookups.
	 *
	 * @param string $slug Component slug/key.
	 * @return object|null Component object or null.
	 */
	public function get_by_slug($slug) {
		return $this->get_by_key($slug);
	}

	/**
	 * Create a new component.
	 *
	 * @param array $data Component fields.
	 * @return int|false Inserted ID or false.
	 */
	public function create($data) {
		$key = !empty($data['component_key']) ? $data['component_key'] : (!empty($data['slug']) ? $data['slug'] : '');
		if (empty($key) || empty($data['name'])) {
			return false;
		}

		$now = AIPS_DateTime::now()->timestamp();
		$prompt_tag = isset($data['prompt_tag']) ? $data['prompt_tag'] : (!empty($data['tag']) ? $data['tag'] : '');
		$prompt_instructions = isset($data['prompt_instructions']) ? $data['prompt_instructions'] : (!empty($data['prompt_blueprint']) ? $data['prompt_blueprint'] : '');

		$insert_data = array(
			'component_key'       => sanitize_key($key),
			'name'                => sanitize_text_field($data['name']),
			'description'         => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
			'prompt_tag'          => sanitize_text_field($prompt_tag),
			'prompt_instructions' => wp_kses_post($prompt_instructions),
			'gutenberg_template'  => isset($data['gutenberg_template']) ? wp_kses_post($data['gutenberg_template']) : '',
			'shortcode_template'  => isset($data['shortcode_template']) ? wp_kses_post($data['shortcode_template']) : '',
			'schema_template'     => isset($data['schema_template']) ? wp_kses_post($data['schema_template']) : '',
			'is_system'           => !empty($data['is_system']) ? 1 : 0,
			'is_active'           => !isset($data['is_active']) || !empty($data['is_active']) ? 1 : 0,
			'created_at'          => $now,
			'updated_at'          => $now,
		);

		$result = $this->wpdb->insert(
			$this->table_name,
			$insert_data,
			array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
		);

		$insert_id = $result ? (int) $this->wpdb->insert_id : 0;
		if ($result) {
			$this->invalidate_cache_domain('component', array(), 'component_created');
		}

		return $result ? $insert_id : false;
	}

	/**
	 * Update an existing component.
	 *
	 * @param int   $id   Component ID.
	 * @param array $data Component fields to update.
	 * @return bool True on success, false on failure.
	 */
	public function update($id, $data) {
		$id = absint($id);
		if ($id <= 0) {
			return false;
		}

		$update_data = array();
		$format = array();

		if (isset($data['name'])) {
			$update_data['name'] = sanitize_text_field($data['name']);
			$format[] = '%s';
		}
		if (isset($data['description'])) {
			$update_data['description'] = sanitize_textarea_field($data['description']);
			$format[] = '%s';
		}
		if (isset($data['prompt_tag'])) {
			$update_data['prompt_tag'] = sanitize_text_field($data['prompt_tag']);
			$format[] = '%s';
		}
		if (isset($data['prompt_instructions'])) {
			$update_data['prompt_instructions'] = wp_kses_post($data['prompt_instructions']);
			$format[] = '%s';
		}
		if (isset($data['gutenberg_template'])) {
			$update_data['gutenberg_template'] = wp_kses_post($data['gutenberg_template']);
			$format[] = '%s';
		}
		if (isset($data['shortcode_template'])) {
			$update_data['shortcode_template'] = wp_kses_post($data['shortcode_template']);
			$format[] = '%s';
		}
		if (isset($data['schema_template'])) {
			$update_data['schema_template'] = wp_kses_post($data['schema_template']);
			$format[] = '%s';
		}
		if (isset($data['is_active'])) {
			$update_data['is_active'] = !empty($data['is_active']) ? 1 : 0;
			$format[] = '%d';
		}

		if (empty($update_data)) {
			return false;
		}

		$update_data['updated_at'] = AIPS_DateTime::now()->timestamp();
		$format[] = '%d';

		$result = $this->wpdb->update(
			$this->table_name,
			$update_data,
			array('id' => $id),
			$format,
			array('%d')
		) !== false;

		if ($result) {
			$this->invalidate_cache_domain('component', array('component_id' => $id), 'component_updated');
		}

		return $result;
	}

	/**
	 * Delete a component. Blocks deleting system components.
	 *
	 * @param int $id Component ID.
	 * @return bool True on success, false on failure or if system component.
	 */
	public function delete($id) {
		$id = absint($id);
		if ($id <= 0) {
			return false;
		}

		$component = $this->get_by_id($id);
		if (!$component || !empty($component->is_system)) {
			return false;
		}

		$result = $this->wpdb->delete($this->table_name, array('id' => $id), array('%d')) !== false;
		if ($result) {
			$this->invalidate_cache_domain('component', array('component_id' => $id), 'component_deleted');
		}

		return $result;
	}

	/**
	 * Get definitions for default system components.
	 *
	 * @return array Default components array.
	 */
	public static function get_default_definitions() {
		return array(
			array(
				'component_key'       => 'step_list',
				'name'                => 'Step-by-Step Instructions',
				'description'         => 'Numbered procedural steps with title and guidance',
				'prompt_tag'          => '[STEP_LIST]...[STEP title="..."]...[/STEP][/STEP_LIST]',
				'prompt_instructions' => 'Format actionable instructions as [STEP_LIST] with individual [STEP title="Step Title"]Instructions text[/STEP] tags. Include 4-8 logical steps.',
				'gutenberg_template'  => '<div class="wp-block-group aips-step-list-block"><ol class="aips-step-list">{{items}}</ol></div>',
				'shortcode_template'  => '[aips_step_list]{{content}}[/aips_step_list]',
				'schema_template'     => '{"@type":"HowToStep","name":"{{title}}","text":"{{text}}"}',
				'is_system'           => 1,
				'is_active'           => 1,
			),
			array(
				'component_key'       => 'pros_cons',
				'name'                => 'Pros & Cons Grid',
				'description'         => 'Dual-column advantages and disadvantages matrix',
				'prompt_tag'          => '[PROS]...[/PROS][CONS]...[/CONS]',
				'prompt_instructions' => 'List 3-5 key advantages inside [PROS]...[/PROS] with each item on a new line beginning with "+ ", and 2-4 drawbacks inside [CONS]...[/CONS] with each item on a new line beginning with "- ".',
				'gutenberg_template'  => '<div class="wp-block-columns aips-pros-cons-grid"><div class="wp-block-column aips-pros-column"><h4>Pros</h4><ul>{{pros_items}}</ul></div><div class="wp-block-column aips-cons-column"><h4>Cons</h4><ul>{{cons_items}}</ul></div></div>',
				'shortcode_template'  => '[aips_pros_cons pros="{{pros}}" cons="{{cons}}"]',
				'schema_template'     => '{"positiveNotes":"{{pros}}","negativeNotes":"{{cons}}"}',
				'is_system'           => 1,
				'is_active'           => 1,
			),
			array(
				'component_key'       => 'rating_box',
				'name'                => 'Scorecard & Rating Box',
				'description'         => 'Overall numerical score, score breakdown, and final verdict card',
				'prompt_tag'          => '[RATING value="4.5" max="5" verdict="..."]...[/RATING]',
				'prompt_instructions' => 'Include an overall rating block using [RATING value="X.X" max="5" verdict="One-sentence final takeaway"]Detailed verdict paragraph[/RATING].',
				'gutenberg_template'  => '<div class="wp-block-group aips-rating-box"><div class="aips-rating-score"><span class="aips-rating-val">{{value}}</span><span class="aips-rating-max">/{{max}}</span></div><div class="aips-rating-verdict"><strong>Verdict:</strong> {{verdict}}</div><div class="aips-rating-summary">{{content}}</div></div>',
				'shortcode_template'  => '[aips_rating value="{{value}}" max="{{max}}" verdict="{{verdict}}"]{{content}}[/aips_rating]',
				'schema_template'     => '{"@type":"Rating","ratingValue":"{{value}}","bestRating":"{{max}}"}',
				'is_system'           => 1,
				'is_active'           => 1,
			),
			array(
				'component_key'       => 'faq_accordion',
				'name'                => 'FAQ Accordion',
				'description'         => 'Collapsible Question & Answer pairs with FAQ schema',
				'prompt_tag'          => '[FAQ q="..." a="..."]',
				'prompt_instructions' => 'Provide 4-6 frequently asked questions and clear, concise answers using [FAQ q="Question text?" a="Answer text."] for each.',
				'gutenberg_template'  => '<details class="wp-block-details aips-faq-item"><summary><strong>{{q}}</strong></summary><p>{{a}}</p></details>',
				'shortcode_template'  => '[aips_faq question="{{q}}"]{{a}}[/aips_faq]',
				'schema_template'     => '{"@type":"Question","name":"{{q}}","acceptedAnswer":{"@type":"Answer","text":"{{a}}"}}',
				'is_system'           => 1,
				'is_active'           => 1,
			),
			array(
				'component_key'       => 'comparison_table',
				'name'                => 'Comparison Table',
				'description'         => 'Side-by-side criteria and feature matrix',
				'prompt_tag'          => '[TABLE]...[/TABLE]',
				'prompt_instructions' => 'Present side-by-side comparison in markdown table format enclosed inside [TABLE]...[/TABLE].',
				'gutenberg_template'  => '<figure class="wp-block-table aips-comparison-table"><table>{{content}}</table></figure>',
				'shortcode_template'  => '[aips_table]{{content}}[/aips_table]',
				'schema_template'     => '{"@type":"Table"}',
				'is_system'           => 1,
				'is_active'           => 1,
			),
			array(
				'component_key'       => 'callout_box',
				'name'                => 'Callout / Alert Banner',
				'description'         => 'Key takeaway, tip, or warning highlight banner',
				'prompt_tag'          => '[CALLOUT type="tip|warning|info"]...[/CALLOUT]',
				'prompt_instructions' => 'Highlight key takeaways or vital warnings inside [CALLOUT type="tip"]Important highlight[/CALLOUT].',
				'gutenberg_template'  => '<div class="aips-callout aips-callout-{{type}}">{{content}}</div>',
				'shortcode_template'  => '[aips_callout type="{{type}}"]{{content}}[/aips_callout]',
				'schema_template'     => '{"@type":"CreativeWork"}',
				'is_system'           => 1,
				'is_active'           => 1,
			),
		);
	}

	/**
	 * Restore or seed default components idempotently.
	 *
	 * @return int Number of restored/created components.
	 */
	public function restore_defaults() {
		$defaults = self::get_default_definitions();
		$restored = 0;

		foreach ($defaults as $comp) {
			$existing = $this->get_by_key($comp['component_key']);
			if (!$existing) {
				if ($this->create($comp)) {
					$restored++;
				}
			} else {
				// Re-align system fields if existing
				$this->update($existing->id, array(
					'prompt_tag'          => $comp['prompt_tag'],
					'prompt_instructions' => $comp['prompt_instructions'],
					'gutenberg_template'  => $comp['gutenberg_template'],
					'shortcode_template'  => $comp['shortcode_template'],
					'schema_template'     => $comp['schema_template'],
					'is_active'           => 1,
				));
			}
		}

		$this->invalidate_cache_domain('component', array(), 'components_restored');
		return $restored;
	}

	/**
	 * Cache group name.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_components';
	}

	/**
	 * Cache policies.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'components.get_all' => array(
				'tier'        => 'long',
				'tags'        => array('components'),
				'description' => 'Cache components list.',
			),
			'components.get_by_id' => array(
				'tier'        => 'long',
				'tags'        => array('components', 'component:{component_id}'),
				'cache_null'  => false,
				'description' => 'Cache single component by ID.',
			),
			'components.get_by_key' => array(
				'tier'        => 'long',
				'tags'        => array('components', 'component:{component_key}'),
				'cache_null'  => false,
				'description' => 'Cache single component by key.',
			),
		);
	}
}
