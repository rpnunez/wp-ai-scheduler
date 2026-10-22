<?php
/**
 * Keyword Link Rules Service
 *
 * "Always link this keyword to that post" rules. Rules are applied when a
 * post is displayed (the_content), not written into post content, so
 * disabling or deleting a rule takes effect immediately and nothing needs
 * undoing. Links are placed with AIPS_Link_Insertion_Engine (body text only;
 * never headings, existing links, code, buttons or shortcodes).
 *
 * The link index records rule links too (see
 * AIPS_Link_Index_Service::index_post()), so the Link Report and orphan
 * detection count them.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Rules_Service
 */
class AIPS_Link_Rules_Service {

	/**
	 * Option holding the rules (id => rule).
	 */
	const RULES_OPTION = 'aips_link_keyword_rules';

	/**
	 * Option bumped on every rule change (cache and index invalidation).
	 */
	const VERSION_OPTION = 'aips_link_keyword_rules_version';

	/**
	 * Object cache group for rendered content.
	 */
	const CACHE_GROUP = 'aips_link_rules';

	/**
	 * Marker value prefix for rule links (data-aips-link="rule-{id}").
	 */
	const MARKER_PREFIX = 'rule-';

	/**
	 * Maximum number of rules.
	 */
	const MAX_RULES = 500;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Link_Insertion_Engine
	 */
	private $engine;

	/**
	 * @param AIPS_Config|null                $config Config.
	 * @param AIPS_Link_Insertion_Engine|null $engine Insertion engine.
	 */
	public function __construct(?AIPS_Config $config = null, ?AIPS_Link_Insertion_Engine $engine = null) {
		$this->config = $config ?: AIPS_Config::get_instance();
		$this->engine = $engine ?: new AIPS_Link_Insertion_Engine();
	}

	/**
	 * Whether rules are applied on the site.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) $this->config->get_option('aips_link_rules_enabled', true);
	}

	/**
	 * Current rules version (changes whenever a rule changes).
	 *
	 * @return int
	 */
	public function get_version(): int {
		return (int) $this->config->get_option(self::VERSION_OPTION, 0);
	}

	/**
	 * All rules, newest first, with target titles for the UI.
	 *
	 * @return array[]
	 */
	public function get_rules(): array {
		$rules = array_values((array) $this->config->get_option(self::RULES_OPTION, array()));

		usort($rules, function ($a, $b) {
			return (int) $b['created_at'] <=> (int) $a['created_at'];
		});

		foreach ($rules as &$rule) {
			$rule['target_title']  = get_the_title((int) $rule['target_id']);
			$rule['target_url']    = (string) get_permalink((int) $rule['target_id']);
			$rule['target_exists'] = get_post_status((int) $rule['target_id']) === 'publish';
		}
		unset($rule);

		return $rules;
	}

	/**
	 * Create or update a rule.
	 *
	 * @param array $data keyword, target_id, max_per_post (1-10), enabled, optional id.
	 * @return array|WP_Error The saved rule.
	 */
	public function save_rule(array $data) {
		$keyword   = isset($data['keyword']) ? trim(preg_replace('/\s+/u', ' ', sanitize_text_field((string) $data['keyword']))) : '';
		$target_id = isset($data['target_id']) ? absint($data['target_id']) : 0;
		$id        = isset($data['id']) ? sanitize_key((string) $data['id']) : '';

		if (mb_strlen($keyword) < 2 || mb_strlen($keyword) > 100) {
			return new WP_Error('aips_link_rule_keyword', __('Keywords must be 2 to 100 characters long.', 'ai-post-scheduler'));
		}

		if (!$target_id || get_post_status($target_id) !== 'publish') {
			return new WP_Error('aips_link_rule_target', __('Choose a published post to link to.', 'ai-post-scheduler'));
		}

		$rules = (array) $this->config->get_option(self::RULES_OPTION, array());

		foreach ($rules as $existing) {
			if ($existing['id'] !== $id && mb_strtolower($existing['keyword']) === mb_strtolower($keyword)) {
				return new WP_Error('aips_link_rule_duplicate', __('A rule for this keyword already exists.', 'ai-post-scheduler'));
			}
		}

		if ($id === '' || !isset($rules[$id])) {
			if (count($rules) >= self::MAX_RULES) {
				return new WP_Error('aips_link_rule_limit', __('You have reached the maximum number of link rules.', 'ai-post-scheduler'));
			}
			$id = substr(md5(uniqid('', true)), 0, 12);
		}

		$rules[$id] = array(
			'id'           => $id,
			'keyword'      => $keyword,
			'target_id'    => $target_id,
			'max_per_post' => isset($data['max_per_post']) ? min(10, max(1, (int) $data['max_per_post'])) : 1,
			'enabled'      => !isset($data['enabled']) || !empty($data['enabled']),
			'created_at'   => isset($rules[$id]['created_at']) ? (int) $rules[$id]['created_at'] : time(),
		);

		$this->store($rules);

		return $rules[$id];
	}

	/**
	 * Enable or disable a rule.
	 *
	 * @param string $id      Rule ID.
	 * @param bool   $enabled New state.
	 * @return bool
	 */
	public function set_enabled(string $id, bool $enabled): bool {
		$rules = (array) $this->config->get_option(self::RULES_OPTION, array());
		if (!isset($rules[$id])) {
			return false;
		}

		$rules[$id]['enabled'] = $enabled;
		$this->store($rules);

		return true;
	}

	/**
	 * Delete a rule.
	 *
	 * @param string $id Rule ID.
	 * @return bool
	 */
	public function delete_rule(string $id): bool {
		$rules = (array) $this->config->get_option(self::RULES_OPTION, array());
		if (!isset($rules[$id])) {
			return false;
		}

		unset($rules[$id]);
		$this->store($rules);

		return true;
	}

	/**
	 * Apply enabled rules to a post's HTML.
	 *
	 * @param string $html    Post content (raw or rendered).
	 * @param int    $post_id Post the content belongs to (never linked to itself).
	 * @return array{html:string, applied:array[]} applied: rule_id, target_id, text.
	 */
	public function apply_to_html(string $html, int $post_id): array {
		$applied = array();
		if ($html === '' || !$this->is_enabled()) {
			return array('html' => $html, 'applied' => $applied);
		}

		$rules = array_filter((array) $this->config->get_option(self::RULES_OPTION, array()), function ($rule) use ($post_id) {
			return !empty($rule['enabled']) && (int) $rule['target_id'] !== $post_id;
		});

		if (empty($rules)) {
			return array('html' => $html, 'applied' => $applied);
		}

		// Longer keywords first so "internal linking strategy" wins over "internal linking".
		uasort($rules, function ($a, $b) {
			return mb_strlen($b['keyword']) <=> mb_strlen($a['keyword']);
		});

		$post_cap = min(20, max(1, (int) $this->config->get_option('aips_link_rules_max_per_post', 3)));

		foreach ($rules as $rule) {
			if (count($applied) >= $post_cap) {
				break;
			}

			$target_id  = (int) $rule['target_id'];
			$target_url = (string) get_permalink($target_id);
			if ($target_url === '' || get_post_status($target_id) !== 'publish' || $this->engine->has_link_to($html, $target_url)) {
				continue;
			}

			for ($i = 0; $i < (int) $rule['max_per_post'] && count($applied) < $post_cap; $i++) {
				$occurrences = $this->engine->find_phrase_occurrences($html, array($rule['keyword']), array('limit' => 1));
				if (empty($occurrences)) {
					break;
				}

				$inserted = $this->engine->insert($html, $occurrences[0], $target_url, array('marker' => self::MARKER_PREFIX . $rule['id']));
				if (is_wp_error($inserted)) {
					break;
				}

				$html      = $inserted['content'];
				$applied[] = array(
					'rule_id'   => $rule['id'],
					'target_id' => $target_id,
					'text'      => $occurrences[0]['text'],
				);
			}
		}

		return array('html' => $html, 'applied' => $applied);
	}

	/**
	 * the_content filter: add rule links to the main post on single views.
	 *
	 * @param string $content Rendered content.
	 * @return string
	 */
	public function filter_content($content) {
		if (!is_string($content) || $content === '' || is_admin() || !is_singular() || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		$post = get_post();
		if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
			return $content;
		}

		$link_index = AIPS_Container::get_instance()->make(AIPS_Link_Index_Service::class);
		if (!$link_index->is_post_in_scope($post)) {
			return $content;
		}

		$key    = md5($post->ID . '|' . $this->get_version() . '|' . $content);
		$cached = wp_cache_get($key, self::CACHE_GROUP);
		if (is_string($cached)) {
			return $cached;
		}

		$result = $this->apply_to_html($content, (int) $post->ID);
		wp_cache_set($key, $result['html'], self::CACHE_GROUP, HOUR_IN_SECONDS);

		return $result['html'];
	}

	/**
	 * Persist rules and bump the version.
	 *
	 * @param array $rules Rules keyed by ID.
	 * @return void
	 */
	private function store(array $rules): void {
		$this->config->set_option(self::RULES_OPTION, $rules, false);
		$this->config->set_option(self::VERSION_OPTION, $this->get_version() + 1);
	}
}
