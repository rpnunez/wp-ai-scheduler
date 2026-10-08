<?php
/**
 * Duplicate Group Service
 *
 * Turns the stored post-to-post similarity pairs into small review groups of
 * near-duplicate posts and recommends which post in each group to keep.
 *
 * Groups use complete linkage: a post only joins a group when it is at least
 * as similar as the review threshold to EVERY post already in it. That is what
 * keeps groups small and trustworthy. Connected-component clustering (used by
 * Topic Clusters) lets A~B and B~C pull an unrelated A and C together, which is
 * how a whole library collapses into one giant "duplicate" group.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Duplicate_Group_Service
 */
class AIPS_Duplicate_Group_Service {

	/**
	 * Cron hook for the scheduled duplicate scan.
	 */
	const CRON_HOOK = 'aips_duplicate_scan';

	/**
	 * Option holding pairs the user marked "not a duplicate" (pair key => time).
	 */
	const DISMISSED_OPTION = 'aips_duplicate_dismissed';

	/**
	 * Option holding the last scheduled scan result.
	 */
	const STATUS_OPTION = 'aips_duplicate_scan_status';

	/**
	 * Most dismissed pairs remembered; the oldest are dropped past this.
	 */
	const DISMISSED_LIMIT = 5000;

	/**
	 * @var AIPS_Notifications|null Resolved lazily.
	 */
	private $notifications;

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships;

	/**
	 * @var AIPS_Link_Index_Repository
	 */
	private $link_index;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @param AIPS_Relationships_Repository|null $relationships Similarity pair storage.
	 * @param AIPS_Link_Index_Repository|null    $link_index    Link index (inbound link counts).
	 * @param AIPS_Config|null                   $config        Config.
	 */
	public function __construct(
		?AIPS_Relationships_Repository $relationships = null,
		?AIPS_Link_Index_Repository $link_index = null,
		?AIPS_Config $config = null
	) {
		$this->relationships = $relationships ?: new AIPS_Relationships_Repository();
		$this->link_index    = $link_index ?: new AIPS_Link_Index_Repository();
		$this->config        = $config ?: AIPS_Config::get_instance();
	}

	/**
	 * Strategies for picking the recommended post to keep.
	 *
	 * @return array<string,string> Strategy key => label.
	 */
	public static function get_keep_strategies(): array {
		return array(
			'balanced'    => __('Balanced (links, length and age)', 'ai-post-scheduler'),
			'most_linked' => __('Most inbound links', 'ai-post-scheduler'),
			'longest'     => __('Longest article', 'ai-post-scheduler'),
			'oldest'      => __('Oldest (first published)', 'ai-post-scheduler'),
			'newest'      => __('Newest', 'ai-post-scheduler'),
		);
	}

	/**
	 * Build the duplicate review queue.
	 *
	 * @param float|null  $threshold      Minimum pairwise similarity (defaults to the setting).
	 * @param int|null    $max_group_size Largest group to form (defaults to the setting).
	 * @param int|null    $max_pairs      Most similar pairs to load (defaults to the setting).
	 * @param string|null $strategy       Keep strategy (defaults to the setting).
	 * @return array{groups:array[],stats:array}
	 */
	public function get_review_groups(?float $threshold = null, ?int $max_group_size = null, ?int $max_pairs = null, ?string $strategy = null): array {
		$threshold      = max(0.70, min(0.99, $threshold ?? (float) $this->config->get_option('aips_duplicate_review_threshold', 0.88)));
		$max_group_size = max(2, min(10, $max_group_size ?? (int) $this->config->get_option('aips_duplicate_review_max_group_size', 5)));
		$max_pairs      = max(100, min(5000, $max_pairs ?? (int) $this->config->get_option('aips_duplicate_review_max_pairs', 1000)));
		$strategy       = $strategy ?? (string) $this->config->get_option('aips_duplicate_keep_strategy', 'balanced');
		if (!array_key_exists($strategy, self::get_keep_strategies())) {
			$strategy = 'balanced';
		}

		$rows  = $this->relationships->get_top_duplicate_pairs($threshold, $max_pairs, 'posts');
		$pairs = array();
		foreach ((array) $rows as $row) {
			$pairs[] = array(
				'a'   => (int) $row->source_id,
				'b'   => (int) $row->target_id,
				'sim' => (float) $row->similarity,
			);
		}

		$pairs_loaded = count($pairs);
		$pairs        = $this->remove_excluded_pairs($pairs);
		$excluded     = $pairs_loaded - count($pairs);
		$dismissed    = $this->get_dismissed();
		$visible      = array();
		foreach ($pairs as $pair) {
			if (!isset($dismissed[$this->pair_key($pair['a'], $pair['b'])])) {
				$visible[] = $pair;
			}
		}
		$hidden = count($pairs) - count($visible);
		$pairs  = $visible;

		$protect    = max(0, (int) $this->config->get_option('aips_duplicate_protect_inbound_links', 10));
		$raw_groups = $this->build_groups($pairs, $max_group_size);

		$all_ids = array();
		foreach ($raw_groups as $group) {
			$all_ids = array_merge($all_ids, $group['ids']);
		}
		$link_counts = $this->link_index->get_counts_for_posts($all_ids);

		$groups = array();
		foreach ($raw_groups as $index => $group) {
			$members = array();
			foreach ($group['ids'] as $post_id) {
				$member = $this->describe_member($post_id, isset($link_counts[$post_id]) ? (int) $link_counts[$post_id]['inbound'] : 0);
				if ($member !== null) {
					$members[] = $member;
				}
			}
			if (count($members) < 2) {
				continue;
			}

			$members = $this->recommend_keep($members, $strategy, $protect);

			$groups[] = array(
				'id'                  => 'dup_' . ($index + 1),
				'post_count'          => count($members),
				'min_similarity'      => round($group['min'], 4),
				'avg_similarity'      => round($group['avg'], 4),
				'min_similarity_pct'  => round($group['min'] * 100, 1),
				'avg_similarity_pct'  => round($group['avg'] * 100, 1),
				'recommended_keep_id' => $this->recommended_id($members),
				'posts'               => $members,
			);
		}

		$post_total = 0;
		foreach ($groups as $group) {
			$post_total += $group['post_count'];
		}

		return array(
			'groups' => $groups,
			'stats'  => array(
				'group_count'      => count($groups),
				'post_count'       => $post_total,
				'pairs_considered' => count($pairs),
				'pairs_truncated'  => $pairs_loaded >= $max_pairs,
				'pairs_excluded'   => $excluded,
				'pairs_dismissed'  => $hidden,
				'dismissed_total'  => count($dismissed),
				'protect_links'    => $protect,
				'threshold'        => $threshold,
				'strategy'         => $strategy,
			),
		);
	}

	/**
	 * Group similar pairs with complete linkage.
	 *
	 * Pairs are merged strongest first. Two groups merge only when every post in
	 * one is paired with every post in the other, so each group's weakest link is
	 * at least as strong as the input threshold. A missing pair means "not
	 * similar enough (or not stored)", which blocks the merge.
	 *
	 * @param array[] $pairs          Each: array('a' => int, 'b' => int, 'sim' => float).
	 * @param int     $max_group_size Largest group to form.
	 * @return array[] Each: array('ids' => int[], 'min' => float, 'avg' => float), strongest first.
	 */
	public function build_groups(array $pairs, int $max_group_size = 5): array {
		$max_group_size = max(2, $max_group_size);

		$sim = array();
		foreach ($pairs as $pair) {
			$a = (int) $pair['a'];
			$b = (int) $pair['b'];
			if ($a <= 0 || $b <= 0 || $a === $b) {
				continue;
			}
			$key = $a < $b ? $a . ':' . $b : $b . ':' . $a;
			if (!isset($sim[$key]) || $pair['sim'] > $sim[$key]) {
				$sim[$key] = (float) $pair['sim'];
			}
		}
		arsort($sim);

		$group_of = array(); // post id => group index
		$groups   = array(); // group index => int[]

		foreach ($sim as $key => $value) {
			list($a, $b) = array_map('intval', explode(':', $key));

			foreach (array($a, $b) as $id) {
				if (!isset($group_of[$id])) {
					$group_of[$id] = count($groups);
					$groups[]      = array($id);
				}
			}

			$ga = $group_of[$a];
			$gb = $group_of[$b];
			if ($ga === $gb) {
				continue;
			}
			if (count($groups[$ga]) + count($groups[$gb]) > $max_group_size) {
				continue;
			}
			if (!$this->all_pairs_present($groups[$ga], $groups[$gb], $sim)) {
				continue;
			}

			foreach ($groups[$gb] as $id) {
				$group_of[$id] = $ga;
			}
			$groups[$ga] = array_merge($groups[$ga], $groups[$gb]);
			$groups[$gb] = array();
		}

		$result = array();
		foreach ($groups as $ids) {
			if (count($ids) < 2) {
				continue;
			}
			sort($ids);

			$values = array();
			for ($i = 0; $i < count($ids); $i++) {
				for ($j = $i + 1; $j < count($ids); $j++) {
					$values[] = $sim[$ids[$i] . ':' . $ids[$j]];
				}
			}

			$result[] = array(
				'ids' => $ids,
				'min' => min($values),
				'avg' => array_sum($values) / count($values),
			);
		}

		usort($result, function ($x, $y) {
			return $y['avg'] <=> $x['avg'];
		});

		return $result;
	}

	/**
	 * Score members and flag the one to keep.
	 *
	 * @param array[] $members  Members from describe_member().
	 * @param string  $strategy Keep strategy key.
	 * @param int     $protect  Inbound-link count at which a post is protected (0 = off). A
	 *                          protected post outranks unprotected ones and is flagged 'protected'.
	 * @return array[] Members with 'score', 'is_recommended', 'protected' and 'reasons', best first.
	 */
	public function recommend_keep(array $members, string $strategy = 'balanced', int $protect = 0): array {
		if (empty($members)) {
			return $members;
		}

		$links = array_column($members, 'inbound_links');
		$words = array_column($members, 'words');
		$times = array_column($members, 'timestamp');

		$max_links = max($links);
		$max_words = max($words);
		$min_time  = min($times);
		$max_time  = max($times);

		foreach ($members as $i => $member) {
			$link_norm = $max_links > 0 ? $member['inbound_links'] / $max_links : 0.0;
			$word_norm = $max_words > 0 ? $member['words'] / $max_words : 0.0;
			$age_norm  = $max_time > $min_time ? ($max_time - $member['timestamp']) / ($max_time - $min_time) : 0.5;
			$new_norm  = 1.0 - $age_norm;

			switch ($strategy) {
				case 'most_linked':
					$primary = $link_norm;
					break;
				case 'longest':
					$primary = $word_norm;
					break;
				case 'oldest':
					$primary = $age_norm;
					break;
				case 'newest':
					$primary = $new_norm;
					break;
				default:
					$primary = 0.0;
			}

			$balanced = (0.45 * $link_norm) + (0.30 * $word_norm) + (0.25 * $age_norm);

			// The chosen strategy decides; the balanced score breaks ties.
			$members[$i]['score'] = $strategy === 'balanced'
				? round($balanced, 4)
				: round($primary + ($balanced / 100), 4);

			$reasons = array();
			if ($max_links > 0 && $member['inbound_links'] === $max_links && count(array_unique($links)) > 1) {
				/* translators: %d: number of inbound internal links */
				$reasons[] = sprintf(_n('%d inbound link (most)', '%d inbound links (most)', $max_links, 'ai-post-scheduler'), $max_links);
			}
			if ($member['words'] === $max_words && count(array_unique($words)) > 1) {
				/* translators: %s: formatted word count */
				$reasons[] = sprintf(__('Longest (%s words)', 'ai-post-scheduler'), number_format_i18n($max_words));
			}
			if ($member['timestamp'] === $min_time && $max_time > $min_time) {
				$reasons[] = __('Oldest', 'ai-post-scheduler');
			}
			$is_protected = $protect > 0 && $member['inbound_links'] >= $protect;
			if ($is_protected) {
				// Always outranks an unprotected post, whatever the strategy scores.
				$members[$i]['score'] += 10;
				/* translators: %d: number of inbound internal links */
				$reasons[] = sprintf(__('Protected: %d inbound links', 'ai-post-scheduler'), $member['inbound_links']);
			}
			$members[$i]['protected'] = $is_protected;
			$members[$i]['reasons']   = $reasons;
		}

		usort($members, function ($x, $y) {
			return $y['score'] <=> $x['score'] ?: $x['id'] <=> $y['id'];
		});

		foreach ($members as $i => $member) {
			$members[$i]['is_recommended'] = ($i === 0);
		}

		return $members;
	}

	/**
	 * ID of the recommended member.
	 *
	 * @param array[] $members Members after recommend_keep().
	 * @return int
	 */
	private function recommended_id(array $members): int {
		foreach ($members as $member) {
			if (!empty($member['is_recommended'])) {
				return (int) $member['id'];
			}
		}
		return 0;
	}

	/**
	 * Whether every cross pair between two groups has a stored similarity.
	 *
	 * @param int[]              $left  Group members.
	 * @param int[]              $right Group members.
	 * @param array<string,float> $sim  Pair similarities keyed 'low:high'.
	 * @return bool
	 */
	private function all_pairs_present(array $left, array $right, array $sim): bool {
		foreach ($left as $x) {
			foreach ($right as $y) {
				$key = $x < $y ? $x . ':' . $y : $y . ':' . $x;
				if (!isset($sim[$key])) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Remember that the posts in a group are not duplicates of each other.
	 *
	 * @param int[] $post_ids Posts in the group.
	 * @return int Number of pairs remembered.
	 */
	public function dismiss_group(array $post_ids): int {
		$post_ids = array_values(array_unique(array_filter(array_map('absint', $post_ids))));
		sort($post_ids);

		$dismissed = $this->get_dismissed();
		$added     = 0;
		for ($i = 0; $i < count($post_ids); $i++) {
			for ($j = $i + 1; $j < count($post_ids); $j++) {
				$key = $this->pair_key($post_ids[$i], $post_ids[$j]);
				if (!isset($dismissed[$key])) {
					$added++;
				}
				$dismissed[$key] = time();
			}
		}

		if ($added > 0) {
			$this->config->set_option(self::DISMISSED_OPTION, array_slice($dismissed, -self::DISMISSED_LIMIT, null, true), false);
		}

		return $added;
	}

	/**
	 * Forget every "not a duplicate" decision.
	 *
	 * @return void
	 */
	public function reset_dismissed(): void {
		$this->config->set_option(self::DISMISSED_OPTION, array(), false);
	}

	/**
	 * Scan for duplicate groups and notify when new ones appear. Runs from cron.
	 *
	 * @return array{time:int, group_count:int, post_count:int, new_groups:int}
	 */
	public function run_scheduled_scan(): array {
		$result     = $this->get_review_groups();
		$signatures = array();
		foreach ($result['groups'] as $group) {
			$ids = wp_list_pluck($group['posts'], 'id');
			sort($ids);
			$signatures[] = implode('-', $ids);
		}

		$previous = $this->config->get_option(self::STATUS_OPTION, array());
		$known    = isset($previous['signatures']) ? (array) $previous['signatures'] : array();
		$new      = array_diff($signatures, $known);

		$status = array(
			'time'        => time(),
			'group_count' => $result['stats']['group_count'],
			'post_count'  => $result['stats']['post_count'],
			'new_groups'  => count($new),
			'signatures'  => $signatures,
		);
		$this->config->set_option(self::STATUS_OPTION, $status, false);

		if (!empty($new)) {
			$this->get_notifications()->duplicate_groups_found(array(
				'new_groups'  => count($new),
				'group_count' => $status['group_count'],
				'post_count'  => $status['post_count'],
			));
		}

		unset($status['signatures']);
		return $status;
	}

	/**
	 * Keep the cron event in line with Settings > Engine > Scheduled Duplicate Scan.
	 *
	 * @return void
	 */
	public function ensure_schedule(): void {
		$wanted  = (string) $this->config->get_option('aips_duplicate_scan_schedule', 'off');
		$wanted  = in_array($wanted, array('daily', 'weekly'), true) ? $wanted : '';
		$current = wp_get_scheduled_event(self::CRON_HOOK);

		if ($current && $current->schedule !== $wanted) {
			wp_clear_scheduled_hook(self::CRON_HOOK);
			$current = false;
		}

		if ($wanted !== '' && !$current) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, $wanted, self::CRON_HOOK);
		}
	}

	/**
	 * Pair key shared by dismissals and group building.
	 *
	 * @param int $a Post ID.
	 * @param int $b Post ID.
	 * @return string
	 */
	private function pair_key(int $a, int $b): string {
		return $a < $b ? $a . ':' . $b : $b . ':' . $a;
	}

	/**
	 * @return array<string,int> Dismissed pair key => time.
	 */
	private function get_dismissed(): array {
		return (array) $this->config->get_option(self::DISMISSED_OPTION, array());
	}

	/**
	 * Drop pairs where either post is excluded in Settings > Engine.
	 *
	 * @param array[] $pairs Pairs from the relationships table.
	 * @return array[]
	 */
	private function remove_excluded_pairs(array $pairs): array {
		$types      = array_map('strval', (array) $this->config->get_option('aips_duplicate_excluded_post_types', array()));
		$categories = array_filter(array_map('intval', (array) $this->config->get_option('aips_duplicate_excluded_categories', array())));
		$by_id      = array_filter(array_map('intval', preg_split('/[^0-9]+/', (string) $this->config->get_option('aips_duplicate_excluded_post_ids', ''))));
		$locked     = array_fill_keys($by_id, true);

		if ($this->config->get_option('aips_duplicate_exclude_pillars', true)) {
			foreach ((array) $this->config->get_option('aips_post_clusters', array()) as $cluster) {
				if (!empty($cluster['pillar_confirmed']) && !empty($cluster['pillar_id'])) {
					$locked[(int) $cluster['pillar_id']] = true;
				}
			}
		}

		if (empty($types) && empty($categories) && empty($locked)) {
			return $pairs;
		}

		$verdict = array();
		$is_out  = function (int $id) use (&$verdict, $types, $categories, $locked): bool {
			if (!isset($verdict[$id])) {
				$post         = isset($locked[$id]) ? null : get_post($id);
				$verdict[$id] = isset($locked[$id])
					|| !$post
					|| in_array($post->post_type, $types, true)
					|| (!empty($categories) && has_term($categories, 'category', $post));
			}
			return $verdict[$id];
		};

		return array_values(array_filter($pairs, function ($pair) use ($is_out) {
			return !$is_out($pair['a']) && !$is_out($pair['b']);
		}));
	}

	/**
	 * @return AIPS_Notifications
	 */
	private function get_notifications() {
		if (!$this->notifications) {
			$this->notifications = new AIPS_Notifications();
		}
		return $this->notifications;
	}

	/**
	 * Display fields for one group member.
	 *
	 * @param int $post_id       Post ID.
	 * @param int $inbound_links Inbound internal links from the link index.
	 * @return array|null Null when the post is gone or not published.
	 */
	private function describe_member(int $post_id, int $inbound_links): ?array {
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'publish') {
			return null;
		}

		return array(
			'id'            => (int) $post->ID,
			'title'         => get_the_title($post),
			'post_type'     => $post->post_type,
			'url'           => (string) get_permalink($post),
			'edit_url'      => (string) get_edit_post_link($post->ID, ''),
			'post_date'     => (string) get_the_date('', $post),
			'timestamp'     => (int) get_post_time('U', true, $post),
			'words'         => str_word_count(wp_strip_all_tags((string) $post->post_content)),
			'inbound_links' => $inbound_links,
		);
	}
}
