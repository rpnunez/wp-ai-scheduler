<?php
/**
 * Search Console Target Keywords Service
 *
 * Pulls the queries each post actually ranks for from Google Search Console
 * (last 28 days, page × query) once a day and stores the top queries per
 * post in post meta. Inbound link suggestions then prefer those real search
 * queries as anchor text (AIPS_Inbound_Links_Service::get_anchor_phrases()).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_GSC_Keywords_Service
 */
class AIPS_GSC_Keywords_Service {

	/**
	 * Daily sync cron hook.
	 */
	const CRON_HOOK = 'aips_gsc_sync';

	/**
	 * Post meta holding a post's top queries.
	 */
	const META_KEY = '_aips_gsc_queries';

	/**
	 * Option holding the last sync result.
	 */
	const STATUS_OPTION = 'aips_gsc_last_sync';

	/**
	 * Rows per Search Analytics request (API maximum).
	 */
	const PAGE_SIZE = 25000;

	/**
	 * Queries kept per post.
	 */
	const QUERIES_PER_POST = 10;

	/**
	 * @var AIPS_GSC_Client
	 */
	private $client;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Link_Url_Resolver
	 */
	private $resolver;

	/**
	 * @param AIPS_GSC_Client|null        $client   Search Console client.
	 * @param AIPS_Config|null            $config   Config.
	 * @param AIPS_Link_Url_Resolver|null $resolver URL resolver.
	 */
	public function __construct(?AIPS_GSC_Client $client = null, ?AIPS_Config $config = null, ?AIPS_Link_Url_Resolver $resolver = null) {
		$this->config   = $config ?: AIPS_Config::get_instance();
		$this->client   = $client ?: new AIPS_GSC_Client($this->config);
		$this->resolver = $resolver ?: new AIPS_Link_Url_Resolver();
	}

	/**
	 * @return AIPS_GSC_Client
	 */
	public function get_client(): AIPS_GSC_Client {
		return $this->client;
	}

	/**
	 * Whether Search Console queries should be used as anchor text.
	 *
	 * @return bool
	 */
	public function is_anchor_enabled(): bool {
		return (bool) $this->config->get_option('aips_gsc_anchor_enabled', true) && $this->client->is_configured();
	}

	/**
	 * Keep the daily sync scheduled while Search Console is configured.
	 *
	 * @return void
	 */
	public function ensure_schedule(): void {
		$next = wp_next_scheduled(self::CRON_HOOK);

		if ($this->client->is_configured()) {
			if (!$next) {
				wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'daily', self::CRON_HOOK);
			}
		} elseif ($next) {
			wp_clear_scheduled_hook(self::CRON_HOOK);
		}
	}

	/**
	 * Last sync result.
	 *
	 * @return array{time:int, posts:int, queries:int, rows:int, error:string}|null
	 */
	public function get_status(): ?array {
		$status = $this->config->get_option(self::STATUS_OPTION, null);
		return is_array($status) ? $status : null;
	}

	/**
	 * Fetch page × query data and store each post's top queries.
	 *
	 * @return array{time:int, posts:int, queries:int, rows:int, error:string}|WP_Error
	 */
	public function sync() {
		if (!$this->client->is_configured()) {
			return $this->fail(new WP_Error('aips_gsc_not_configured', __('Search Console is not connected. Add a service account key and property under Settings → API Keys.', 'ai-post-scheduler')));
		}

		if (function_exists('set_time_limit')) {
			@set_time_limit(300); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// Search Console data lags ~2-3 days; use the last 28 complete days.
		$end   = gmdate('Y-m-d', time() - 3 * DAY_IN_SECONDS);
		$start = gmdate('Y-m-d', time() - 30 * DAY_IN_SECONDS);

		/**
		 * Filters the maximum number of Search Console rows read per sync.
		 *
		 * @param int $max_rows Default 100000 (four API pages).
		 */
		$max_rows = max(self::PAGE_SIZE, (int) apply_filters('aips_gsc_max_rows', 100000));

		$by_post   = array();
		$page_ids  = array();
		$row_count = 0;

		for ($start_row = 0; $start_row < $max_rows; $start_row += self::PAGE_SIZE) {
			$rows = $this->client->search_analytics(array(
				'startDate'  => $start,
				'endDate'    => $end,
				'dimensions' => array('page', 'query'),
				'type'       => 'web',
				'rowLimit'   => self::PAGE_SIZE,
				'startRow'   => $start_row,
			));

			if (is_wp_error($rows)) {
				return $this->fail($rows);
			}

			foreach ($rows as $row) {
				$row_count++;
				$this->collect_row($row, $by_post, $page_ids);
			}

			if (count($rows) < self::PAGE_SIZE) {
				break;
			}
		}

		$query_count = 0;
		foreach ($by_post as $post_id => $queries) {
			$top = $this->top_queries($queries);
			update_post_meta($post_id, self::META_KEY, $top);
			$query_count += count($top);
		}

		// Drop stale data for posts that no longer have qualifying queries.
		$stale = get_posts(array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'meta_key'       => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		));
		foreach ((array) $stale as $post_id) {
			if (!isset($by_post[(int) $post_id])) {
				delete_post_meta((int) $post_id, self::META_KEY);
			}
		}

		$status = array(
			'time'    => time(),
			'posts'   => count($by_post),
			'queries' => $query_count,
			'rows'    => $row_count,
			'error'   => '',
		);
		$this->config->set_option(self::STATUS_OPTION, $status, false);

		return $status;
	}

	/**
	 * A post's stored top queries.
	 *
	 * @param int $post_id Post ID.
	 * @return array[] query, clicks, impressions, position.
	 */
	public function get_queries(int $post_id): array {
		$queries = get_post_meta($post_id, self::META_KEY, true);
		return is_array($queries) ? $queries : array();
	}

	/**
	 * Query strings to try as anchor text for a post (best first).
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public function get_anchor_queries(int $post_id): array {
		if (!$this->is_anchor_enabled()) {
			return array();
		}

		return array_values(array_filter(array_map(function ($row) {
			return isset($row['query']) ? (string) $row['query'] : '';
		}, $this->get_queries($post_id)), 'strlen'));
	}

	/**
	 * Add one API row to the per-post aggregation.
	 *
	 * @param array $row      API row (keys: [page, query]).
	 * @param array $by_post  post ID => query => stats (by reference).
	 * @param array $page_ids page URL => post ID cache (by reference).
	 * @return void
	 */
	private function collect_row(array $row, array &$by_post, array &$page_ids): void {
		if (empty($row['keys'][0]) || empty($row['keys'][1])) {
			return;
		}

		$page  = (string) $row['keys'][0];
		$query = trim(preg_replace('/\s+/u', ' ', (string) $row['keys'][1]));

		$words = count(preg_split('/\s+/u', $query));
		if ($words < 2 || $words > 6 || mb_strlen($query) > 80) {
			return;
		}

		if (!array_key_exists($page, $page_ids)) {
			$url             = $this->resolver->normalize($page);
			$post_id         = ($url !== '' && $this->resolver->is_internal($url)) ? $this->resolver->resolve_post_id($url) : 0;
			$page_ids[$page] = ($post_id > 0 && get_post_status($post_id) === 'publish') ? $post_id : 0;
		}

		$post_id = $page_ids[$page];
		if ($post_id <= 0) {
			return;
		}

		$key = mb_strtolower($query);
		if (!isset($by_post[$post_id][$key])) {
			$by_post[$post_id][$key] = array('query' => $query, 'clicks' => 0, 'impressions' => 0, 'position_sum' => 0.0);
		}

		$impressions = (int) ($row['impressions'] ?? 0);

		$by_post[$post_id][$key]['clicks']       += (int) ($row['clicks'] ?? 0);
		$by_post[$post_id][$key]['impressions']  += $impressions;
		$by_post[$post_id][$key]['position_sum'] += (float) ($row['position'] ?? 0) * max(1, $impressions);
	}

	/**
	 * Top queries for a post: most clicks, then most impressions.
	 *
	 * @param array $queries query => stats.
	 * @return array[]
	 */
	private function top_queries(array $queries): array {
		usort($queries, function ($a, $b) {
			return array($b['clicks'], $b['impressions']) <=> array($a['clicks'], $a['impressions']);
		});

		$top = array();
		foreach (array_slice($queries, 0, self::QUERIES_PER_POST) as $stats) {
			$top[] = array(
				'query'       => $stats['query'],
				'clicks'      => $stats['clicks'],
				'impressions' => $stats['impressions'],
				'position'    => round($stats['position_sum'] / max(1, $stats['impressions']), 1),
			);
		}
		return $top;
	}

	/**
	 * Record a failed sync and return the error.
	 *
	 * @param WP_Error $error Error.
	 * @return WP_Error
	 */
	private function fail(WP_Error $error): WP_Error {
		$previous = $this->get_status() ?: array('posts' => 0, 'queries' => 0, 'rows' => 0);

		$this->config->set_option(self::STATUS_OPTION, array(
			'time'    => time(),
			'posts'   => (int) $previous['posts'],
			'queries' => (int) $previous['queries'],
			'rows'    => 0,
			'error'   => $error->get_error_message(),
		), false);

		return $error;
	}
}
