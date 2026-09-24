<?php
/**
 * Silo Service
 *
 * A silo is a Topic Cluster whose pillar a person has confirmed. Its member
 * articles should link up to the pillar, and the pillar should link down to
 * its members. This service:
 *
 * - reports each silo's link coverage from the link index;
 * - suggests a pillar for clusters that do not have a confirmed one;
 * - fixes a silo: an auto-link run that adds member → pillar links in the
 *   members' text, following the Bulk Auto-Linking policy (undoable as a run);
 * - adds a render-time "In this guide" list of members to the pillar, so the
 *   pillar's own text is never edited. The list is also placed wherever the
 *   [aips_silo_guide] shortcode is used, and it is counted by the link index.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Silo_Service
 */
class AIPS_Silo_Service {

	/**
	 * Option holding detected clusters (written by AIPS_Similarity_Evaluator).
	 */
	const CLUSTERS_OPTION = 'aips_post_clusters';

	/**
	 * Option bumped whenever the guide list could change (cache key and link index salt).
	 */
	const VERSION_OPTION = 'aips_silo_guide_version';

	/**
	 * Settings.
	 */
	const OPT_ENABLED  = 'aips_silo_guide_enabled';
	const OPT_POSITION = 'aips_silo_guide_position';
	const OPT_MAX      = 'aips_silo_guide_max';
	const OPT_STYLE    = 'aips_silo_guide_style';
	const OPT_HEADING  = 'aips_silo_guide_heading';

	const POSITION_END         = 'end';
	const POSITION_AFTER_FIRST = 'after_first_paragraph';

	const STYLE_AIPS  = 'aips';
	const STYLE_THEME = 'theme';

	/**
	 * Shortcode that places the guide list by hand.
	 */
	const SHORTCODE = 'aips_silo_guide';

	/**
	 * Object cache group for rendered content.
	 */
	const CACHE_GROUP = 'aips_silo_guide';

	/**
	 * Similarity assumed for a member without a stored pillar relationship.
	 */
	const DEFAULT_SIMILARITY = 0.75;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $link_index;

	/**
	 * @var AIPS_Link_Insertion_Engine
	 */
	private $engine;

	/**
	 * @var AIPS_Relationships_Repository|null
	 */
	private $relationships;

	/**
	 * @var array<string, array[]> Guide items per pillar/version for this request.
	 */
	private $guide_items = array();

	/**
	 * @var bool Whether the styled guide CSS was printed on this request.
	 */
	private $style_printed = false;

	/**
	 * @param AIPS_Config|null                   $config        Config.
	 * @param AIPS_Link_Index_Service|null       $link_index    Link index.
	 * @param AIPS_Link_Insertion_Engine|null    $engine        Link engine (URL matching).
	 * @param AIPS_Relationships_Repository|null $relationships Semantic relationships.
	 */
	public function __construct(
		?AIPS_Config $config = null,
		?AIPS_Link_Index_Service $link_index = null,
		?AIPS_Link_Insertion_Engine $engine = null,
		?AIPS_Relationships_Repository $relationships = null
	) {
		$container           = AIPS_Container::get_instance();
		$this->config        = $config ?: AIPS_Config::get_instance();
		$this->link_index    = $link_index ?: ($container->has(AIPS_Link_Index_Service::class) ? $container->make(AIPS_Link_Index_Service::class) : new AIPS_Link_Index_Service());
		$this->engine        = $engine ?: new AIPS_Link_Insertion_Engine();
		$this->relationships = $relationships;
	}

	/**
	 * Hooks needed in every context: keep the link index and the render cache
	 * in step with silo changes. Frontend rendering is registered separately.
	 *
	 * @return void
	 */
	public function register_common_hooks(): void {
		add_filter('aips_link_index_render_html', array($this, 'filter_index_html'), 10, 2);
		add_filter('aips_link_index_hash_salt', array($this, 'filter_index_salt'), 10, 2);

		foreach (array(self::OPT_ENABLED, self::OPT_POSITION, self::OPT_MAX, self::OPT_STYLE, self::OPT_HEADING) as $option) {
			add_action('update_option_' . $option, array($this, 'bump_version'));
		}
		add_action('update_option_' . self::CLUSTERS_OPTION, array($this, 'on_clusters_updated'), 10, 2);
	}

	/**
	 * Frontend hooks: the guide list on pillars and its shortcode.
	 *
	 * @return void
	 */
	public function register_frontend_hooks(): void {
		add_filter('the_content', array($this, 'filter_content'), 9);
		add_shortcode(self::SHORTCODE, array($this, 'shortcode'));
	}

	// -----------------------------------------------------------------------
	// Overview
	// -----------------------------------------------------------------------

	/**
	 * Silos (confirmed pillars) and clusters that still need a pillar.
	 *
	 * @return array{silos:array[], candidates:array[], has_clusters:bool}
	 */
	public function get_overview(): array {
		$silos      = array();
		$candidates = array();

		foreach ($this->get_clusters() as $cluster) {
			if (!empty($cluster['pillar_confirmed']) && get_post_status((int) $cluster['pillar_id']) === 'publish') {
				$silos[] = $this->build_silo($cluster);
			} else {
				$candidates[] = $this->build_candidate($cluster);
			}
		}

		usort($silos, function ($a, $b) {
			return $a['health'] <=> $b['health'];
		});

		return array(
			'silos'        => $silos,
			'candidates'   => $candidates,
			'has_clusters' => !empty($silos) || !empty($candidates),
		);
	}

	/**
	 * Confirm (or change) a cluster's pillar.
	 *
	 * @param string $cluster_id Cluster key.
	 * @param int    $post_id    New pillar.
	 * @return true|WP_Error
	 */
	public function confirm_pillar(string $cluster_id, int $post_id) {
		$clusters = $this->get_clusters();
		if (!isset($clusters[$cluster_id])) {
			return new WP_Error('aips_silo_cluster_not_found', __('This cluster no longer exists. Refresh the clusters and try again.', 'ai-post-scheduler'));
		}
		if (!in_array($post_id, $this->member_ids($clusters[$cluster_id]), true) || get_post_status($post_id) !== 'publish') {
			return new WP_Error('aips_silo_invalid_pillar', __('The pillar must be a published post in this cluster.', 'ai-post-scheduler'));
		}

		$previous = (int) $clusters[$cluster_id]['pillar_id'];
		$evaluator = AIPS_Container::get_instance()->has(AIPS_Similarity_Evaluator::class)
			? AIPS_Container::get_instance()->make(AIPS_Similarity_Evaluator::class)
			: new AIPS_Similarity_Evaluator();

		if (!$evaluator->set_pillar_post($cluster_id, $post_id)) {
			return new WP_Error('aips_silo_save_failed', __('The pillar could not be saved.', 'ai-post-scheduler'));
		}

		// Saving the clusters fires on_clusters_updated(), which re-indexes
		// the old and new pillars; do it here too in case hooks are not loaded.
		if (!has_action('update_option_' . self::CLUSTERS_OPTION, array($this, 'on_clusters_updated'))) {
			$this->bump_version();
			$this->reindex(array_unique(array($previous, $post_id)));
		}

		return true;
	}

	/**
	 * Fix a silo: add member → pillar links in the members' text through an
	 * auto-link run limited to the members that do not link to the pillar yet.
	 * Links are inserted when Bulk Auto-Linking allows it; the rest go to the
	 * review queue. The run can be undone from the Link Report.
	 *
	 * @param string $cluster_id Cluster key.
	 * @return array|WP_Error Public run state plus 'missing' (members without an up link).
	 */
	public function fix_silo(string $cluster_id) {
		$clusters = $this->get_clusters();
		if (!isset($clusters[$cluster_id]) || empty($clusters[$cluster_id]['pillar_confirmed'])) {
			return new WP_Error('aips_silo_not_found', __('Confirm this cluster\'s pillar first.', 'ai-post-scheduler'));
		}

		$cluster   = $clusters[$cluster_id];
		$pillar_id = (int) $cluster['pillar_id'];
		if (get_post_status($pillar_id) !== 'publish') {
			return new WP_Error('aips_silo_pillar_unpublished', __('The pillar is not published.', 'ai-post-scheduler'));
		}

		$similarity = $this->pillar_similarities($pillar_id);
		$index_repo = $this->link_index->get_repository();
		$sources    = array();

		foreach ($this->member_ids($cluster) as $member_id) {
			if ($member_id === $pillar_id || get_post_status($member_id) !== 'publish' || $index_repo->source_links_to($member_id, $pillar_id)) {
				continue;
			}
			$sources[$member_id] = isset($similarity[$member_id]) ? $similarity[$member_id] : self::DEFAULT_SIMILARITY;
		}

		if (empty($sources)) {
			return new WP_Error('aips_silo_complete', __('Every article in this silo already links to the pillar.', 'ai-post-scheduler'));
		}

		$runs  = AIPS_Container::get_instance()->has(AIPS_Autolink_Run_Service::class)
			? AIPS_Container::get_instance()->make(AIPS_Autolink_Run_Service::class)
			: new AIPS_Autolink_Run_Service();
		$apply = (new AIPS_Autolink_Policy($this->config))->is_enabled();

		$run = $runs->run_now(
			array($pillar_id),
			$apply,
			get_current_user_id(),
			AIPS_Autolink_Run_Service::SCOPE_SILO,
			array(
				'post_id'           => $pillar_id,
				'post_title'        => get_the_title($pillar_id),
				'cluster_id'        => $cluster_id,
				'only_sources'      => $sources,
				// Linking every member to its pillar is the point of a silo.
				'ignore_target_cap' => true,
			)
		);

		$run['missing'] = count($sources);

		return $run;
	}

	// -----------------------------------------------------------------------
	// "In this guide" list
	// -----------------------------------------------------------------------

	/**
	 * Whether the guide list is on.
	 *
	 * @return bool
	 */
	public function is_guide_enabled(): bool {
		return (bool) $this->config->get_option(self::OPT_ENABLED, true);
	}

	/**
	 * Current guide version (cache key and link index salt).
	 *
	 * @return int
	 */
	public function get_version(): int {
		return (int) $this->config->get_option(self::VERSION_OPTION, 1);
	}

	/**
	 * Bump the guide version (settings or clusters changed).
	 *
	 * @return void
	 */
	public function bump_version(): void {
		$this->guide_items = array();
		$this->config->set_option(self::VERSION_OPTION, $this->get_version() + 1);
	}

	/**
	 * Members shown in a pillar's guide list: published members the pillar's
	 * text does not already link to, closest to the pillar first, capped.
	 *
	 * @param int $pillar_id Pillar post ID.
	 * @return array[] Items with id, title, url.
	 */
	public function get_guide_items(int $pillar_id): array {
		$cache_key = $pillar_id . '|' . $this->get_version() . '|' . $this->get_guide_max();
		if (isset($this->guide_items[$cache_key])) {
			return $this->guide_items[$cache_key];
		}

		$items   = array();
		$cluster = $this->get_cluster_for_pillar($pillar_id);
		$pillar  = get_post($pillar_id);

		if ($cluster && $pillar instanceof WP_Post) {
			$content    = (string) $pillar->post_content;
			$similarity = $this->pillar_similarities($pillar_id);
			$topic      = $this->topic_scores($cluster);
			$candidates = array();

			foreach ($this->member_ids($cluster) as $member_id) {
				if ($member_id === $pillar_id || get_post_status($member_id) !== 'publish') {
					continue;
				}
				$url = (string) get_permalink($member_id);
				if ($url === '' || $this->engine->has_link_to($content, $url)) {
					continue;
				}
				$candidates[] = array(
					'id'    => $member_id,
					'title' => get_the_title($member_id),
					'url'   => $url,
					'score' => isset($similarity[$member_id]) ? $similarity[$member_id] : (isset($topic[$member_id]) ? $topic[$member_id] : 0.0),
				);
			}

			usort($candidates, function ($a, $b) {
				return $b['score'] <=> $a['score'];
			});

			$items = array_slice($candidates, 0, $this->get_guide_max());
		}

		/**
		 * Filters the members listed in a pillar's "In this guide" list.
		 *
		 * @param array[] $items     Items (id, title, url, score).
		 * @param int     $pillar_id Pillar post ID.
		 */
		$items = (array) apply_filters('aips_silo_guide_items', $items, $pillar_id);

		$this->guide_items[$cache_key] = $items;

		return $items;
	}

	/**
	 * Guide list HTML for a pillar ('' when it has nothing to list).
	 *
	 * @param int  $pillar_id  Pillar post ID.
	 * @param bool $with_style Print the styled list's CSS (once per page).
	 * @return string
	 */
	public function render_guide(int $pillar_id, bool $with_style = true): string {
		$items = $this->get_guide_items($pillar_id);
		if (empty($items)) {
			return '';
		}

		$styled  = $this->get_guide_style() === self::STYLE_AIPS;
		$heading = trim((string) $this->config->get_option(self::OPT_HEADING, ''));
		if ($heading === '') {
			$heading = __('In this guide', 'ai-post-scheduler');
		}

		$html  = '<nav class="aips-silo-guide' . ($styled ? ' aips-silo-guide--styled' : '') . '" aria-label="' . esc_attr($heading) . '">';
		$html .= '<h2 class="aips-silo-guide__title">' . esc_html($heading) . '</h2><ul class="aips-silo-guide__list">';
		foreach ($items as $item) {
			$html .= '<li><a href="' . esc_url($item['url']) . '" data-aips-link="silo">' . esc_html($item['title']) . '</a></li>';
		}
		$html .= '</ul></nav>';

		if ($styled && $with_style && !$this->style_printed && !is_admin()) {
			$this->style_printed = true;
			$html = $this->inline_style() . $html;
		}

		/**
		 * Filters the "In this guide" list HTML.
		 *
		 * @param string  $html      List HTML.
		 * @param array[] $items     Items.
		 * @param int     $pillar_id Pillar post ID.
		 */
		return (string) apply_filters('aips_silo_guide_html', $html, $items, $pillar_id);
	}

	/**
	 * Add the guide list to HTML (end, or after the first paragraph).
	 *
	 * @param string $html       Post HTML.
	 * @param int    $pillar_id  Pillar post ID.
	 * @param bool   $with_style Print the styled list's CSS.
	 * @return string
	 */
	public function add_guide_to_html(string $html, int $pillar_id, bool $with_style = true): string {
		$guide = $this->render_guide($pillar_id, $with_style);
		if ($guide === '') {
			return $html;
		}

		if ($this->get_guide_position() === self::POSITION_AFTER_FIRST) {
			$pos = stripos($html, '</p>');
			if ($pos !== false) {
				$pos += 4;
				return substr($html, 0, $pos) . "\n" . $guide . "\n" . substr($html, $pos);
			}
		}

		return rtrim($html) . "\n" . $guide;
	}

	/**
	 * the_content: the guide list on a silo pillar's single view.
	 *
	 * @param string $content Rendered content.
	 * @return string
	 */
	public function filter_content($content) {
		if (!is_string($content) || $content === '' || is_admin() || !is_singular() || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		$post = get_post();
		if (!$post instanceof WP_Post || $post->post_status !== 'publish' || !$this->is_guide_enabled()) {
			return $content;
		}

		// Placed by hand: the shortcode renders the list instead.
		if (has_shortcode((string) $post->post_content, self::SHORTCODE) || !$this->get_cluster_for_pillar((int) $post->ID)) {
			return $content;
		}

		$key    = md5($post->ID . '|' . $this->get_version() . '|' . $content);
		$cached = wp_cache_get($key, self::CACHE_GROUP);
		if (is_string($cached)) {
			return $cached;
		}

		$html = $this->add_guide_to_html($content, (int) $post->ID);
		wp_cache_set($key, $html, self::CACHE_GROUP, HOUR_IN_SECONDS);

		return $html;
	}

	/**
	 * [aips_silo_guide] shortcode: the guide list where the shortcode is.
	 *
	 * @param array|string $atts Attributes: 'id' (pillar; default current post).
	 * @return string
	 */
	public function shortcode($atts = array()): string {
		$atts      = shortcode_atts(array('id' => 0), (array) $atts, self::SHORTCODE);
		$pillar_id = absint($atts['id']) ?: (int) get_the_ID();

		if (!$pillar_id || !$this->is_guide_enabled() || !$this->get_cluster_for_pillar($pillar_id)) {
			return '';
		}

		return $this->render_guide($pillar_id);
	}

	/**
	 * aips_link_index_render_html: count the guide list's links for pillars.
	 *
	 * @param string $html    HTML being indexed.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public function filter_index_html($html, $post_id) {
		$post_id = (int) $post_id;
		if (!$this->is_guide_enabled() || !$this->get_cluster_for_pillar($post_id)) {
			return $html;
		}

		return $this->add_guide_to_html((string) $html, $post_id, false);
	}

	/**
	 * aips_link_index_hash_salt: re-index pillars when the guide changes.
	 *
	 * @param string $salt    Current salt.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public function filter_index_salt($salt, $post_id) {
		if (!$this->get_cluster_for_pillar((int) $post_id)) {
			return $salt;
		}

		return $salt . '|silo:' . $this->get_version() . ':' . ($this->is_guide_enabled() ? 'on' : 'off');
	}

	/**
	 * Clusters were re-detected or a pillar changed: refresh the guide and
	 * re-index confirmed pillars (their guide lists may have changed).
	 *
	 * @param mixed $old_value Previous clusters.
	 * @param mixed $new_value New clusters.
	 * @return void
	 */
	public function on_clusters_updated($old_value, $new_value): void {
		$this->bump_version();

		$pillars = array();
		foreach (array((array) $old_value, (array) $new_value) as $clusters) {
			foreach ($clusters as $cluster) {
				if (!empty($cluster['pillar_confirmed']) && !empty($cluster['pillar_id'])) {
					$pillars[] = (int) $cluster['pillar_id'];
				}
			}
		}

		$this->reindex(array_unique($pillars));
	}

	// -----------------------------------------------------------------------
	// Internals
	// -----------------------------------------------------------------------

	/**
	 * Saved clusters, keyed by cluster ID.
	 *
	 * @return array<string, array>
	 */
	private function get_clusters(): array {
		$clusters = $this->config->get_option(self::CLUSTERS_OPTION, array());
		return is_array($clusters) ? $clusters : array();
	}

	/**
	 * The confirmed cluster whose pillar is $post_id, or null.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private function get_cluster_for_pillar(int $post_id): ?array {
		if ($post_id <= 0) {
			return null;
		}

		foreach ($this->get_clusters() as $cluster) {
			if (!empty($cluster['pillar_confirmed']) && (int) $cluster['pillar_id'] === $post_id) {
				return $cluster;
			}
		}

		return null;
	}

	/**
	 * @param array $cluster Cluster.
	 * @return int[]
	 */
	private function member_ids(array $cluster): array {
		return array_values(array_unique(array_map('intval', isset($cluster['member_ids']) ? (array) $cluster['member_ids'] : array())));
	}

	/**
	 * Member ID => closeness to the cluster topic (from cluster detection).
	 *
	 * @param array $cluster Cluster.
	 * @return array<int, float>
	 */
	private function topic_scores(array $cluster): array {
		$out = array();
		foreach (isset($cluster['posts']) ? (array) $cluster['posts'] : array() as $post) {
			if (isset($post['id'], $post['topic_score'])) {
				$out[(int) $post['id']] = (float) $post['topic_score'];
			}
		}
		return $out;
	}

	/**
	 * Member ID => similarity to the pillar (stored pillar/related relationships).
	 *
	 * @param int $pillar_id Pillar post ID.
	 * @return array<int, float>
	 */
	private function pillar_similarities(int $pillar_id): array {
		if (!$this->relationships) {
			$container           = AIPS_Container::get_instance();
			$this->relationships = $container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository();
		}

		$out = array();
		foreach ($this->relationships->get_related('post', $pillar_id, 500, 0.0, 'pillar_spoke') as $row) {
			if ((!isset($row->target_type) || $row->target_type === 'post') && !isset($out[(int) $row->target_id])) {
				$out[(int) $row->target_id] = (float) $row->similarity;
			}
		}

		return $out;
	}

	/**
	 * Link coverage for a confirmed silo.
	 *
	 * @param array $cluster Cluster.
	 * @return array
	 */
	private function build_silo(array $cluster): array {
		$pillar_id  = (int) $cluster['pillar_id'];
		$content    = (string) get_post_field('post_content', $pillar_id);
		$index_repo = $this->link_index->get_repository();
		$guide_ids  = $this->is_guide_enabled() ? wp_list_pluck($this->get_guide_items($pillar_id), 'id') : array();
		$members    = array();
		$up = $in_text = $in_guide = 0;

		foreach ($this->member_ids($cluster) as $member_id) {
			if ($member_id === $pillar_id || get_post_status($member_id) !== 'publish') {
				continue;
			}

			$links_up = $index_repo->source_links_to($member_id, $pillar_id);
			$down     = 'none';
			if ($this->engine->has_link_to($content, (string) get_permalink($member_id))) {
				$down = 'text';
				$in_text++;
			} elseif (in_array($member_id, $guide_ids, true)) {
				$down = 'guide';
				$in_guide++;
			}
			if ($links_up) {
				$up++;
			}

			$members[] = array(
				'id'       => $member_id,
				'title'    => get_the_title($member_id),
				'url'      => (string) get_permalink($member_id),
				'edit_url' => (string) get_edit_post_link($member_id, 'raw'),
				'links_up' => $links_up,
				'down'     => $down,
			);
		}

		$total  = count($members);
		$health = $total > 0 ? (int) round(100 * ($up + $in_text + $in_guide) / (2 * $total)) : 100;

		return array(
			'cluster_id'   => (string) $cluster['id'],
			'name'         => (string) $cluster['name'],
			'pillar_id'    => $pillar_id,
			'pillar_title' => get_the_title($pillar_id),
			'pillar_url'   => (string) get_permalink($pillar_id),
			'pillar_edit'  => (string) get_edit_post_link($pillar_id, 'raw'),
			'total'        => $total,
			'links_up'     => $up,
			'down_text'    => $in_text,
			'down_guide'   => $in_guide,
			'down_none'    => $total - $in_text - $in_guide,
			'health'       => $health,
			'members'      => $members,
			'choices'      => $this->choices($cluster),
		);
	}

	/**
	 * A cluster without a confirmed pillar, with a suggested one.
	 *
	 * Suggestion score: inbound internal links (40%), length (30%) and
	 * closeness to the cluster's topic (30%), each relative to the cluster.
	 *
	 * @param array $cluster Cluster.
	 * @return array
	 */
	private function build_candidate(array $cluster): array {
		$ids    = array_values(array_filter($this->member_ids($cluster), function ($id) {
			return get_post_status($id) === 'publish';
		}));
		$counts = $this->link_index->get_repository()->get_counts_for_posts($ids);
		$topic  = $this->topic_scores($cluster);
		$rows   = array();

		foreach ($ids as $id) {
			$rows[$id] = array(
				'inbound' => isset($counts[$id]) ? (int) $counts[$id]['inbound'] : 0,
				'words'   => str_word_count(wp_strip_all_tags((string) get_post_field('post_content', $id))),
				'topic'   => isset($topic[$id]) ? $topic[$id] : 0.0,
			);
		}

		$max_inbound = max(1, $rows ? max(wp_list_pluck($rows, 'inbound')) : 1);
		$max_words   = max(1, $rows ? max(wp_list_pluck($rows, 'words')) : 1);
		$best        = 0;
		$best_score  = -1.0;

		foreach ($rows as $id => $row) {
			$score = 0.4 * ($row['inbound'] / $max_inbound) + 0.3 * ($row['words'] / $max_words) + 0.3 * $row['topic'];
			if ($score > $best_score) {
				$best_score = $score;
				$best       = $id;
			}
		}

		return array(
			'cluster_id' => (string) $cluster['id'],
			'name'       => (string) $cluster['name'],
			'total'      => count($ids),
			'suggested'  => $best ? array(
				'id'      => $best,
				'title'   => get_the_title($best),
				'url'     => (string) get_permalink($best),
				'inbound' => $rows[$best]['inbound'],
				'words'   => $rows[$best]['words'],
				'topic'   => (int) round($rows[$best]['topic'] * 100),
			) : null,
			'choices'    => $this->choices($cluster),
		);
	}

	/**
	 * Members as pillar choices (id, title).
	 *
	 * @param array $cluster Cluster.
	 * @return array[]
	 */
	private function choices(array $cluster): array {
		$out = array();
		foreach ($this->member_ids($cluster) as $id) {
			if (get_post_status($id) === 'publish') {
				$out[] = array('id' => $id, 'title' => get_the_title($id));
			}
		}
		return $out;
	}

	/**
	 * Re-index posts in the link index (after their guide list changed).
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return void
	 */
	private function reindex(array $post_ids): void {
		foreach ($post_ids as $post_id) {
			if ((int) $post_id > 0 && $this->link_index->is_enabled()) {
				$this->link_index->index_post((int) $post_id, true);
			}
		}
	}

	/**
	 * @return int
	 */
	private function get_guide_max(): int {
		return min(50, max(1, (int) $this->config->get_option(self::OPT_MAX, 10)));
	}

	/**
	 * @return string
	 */
	private function get_guide_position(): string {
		$position = (string) $this->config->get_option(self::OPT_POSITION, self::POSITION_END);
		return $position === self::POSITION_AFTER_FIRST ? self::POSITION_AFTER_FIRST : self::POSITION_END;
	}

	/**
	 * @return string
	 */
	private function get_guide_style(): string {
		return (string) $this->config->get_option(self::OPT_STYLE, self::STYLE_AIPS) === self::STYLE_THEME ? self::STYLE_THEME : self::STYLE_AIPS;
	}

	/**
	 * Minimal CSS for the styled list; inherits the theme's colours and fonts.
	 *
	 * @return string
	 */
	private function inline_style(): string {
		return '<style id="aips-silo-guide-css">'
			. '.aips-silo-guide--styled{margin:2em 0;padding:1.25em 1.5em;border:1px solid rgba(0,0,0,.12);border-left:4px solid currentColor;border-radius:6px;background:rgba(0,0,0,.03)}'
			. '.aips-silo-guide--styled .aips-silo-guide__title{margin:0 0 .6em;font-size:1.1em}'
			. '.aips-silo-guide--styled .aips-silo-guide__list{margin:0;padding-left:1.2em}'
			. '.aips-silo-guide--styled .aips-silo-guide__list li{margin:.3em 0}'
			. '</style>';
	}
}
