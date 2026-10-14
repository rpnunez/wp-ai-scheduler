<?php
/**
 * Internal Link Click Tracking Service
 *
 * Counts clicks on internal links inside post content (opt-in).
 *
 * - the_content (priority 99) tags internal links in the main post with
 *   data-aips-src="{post ID}".
 * - A tiny frontend script sends a beacon to POST /wp-json/aips/v1/link-click
 *   when a tagged link is clicked.
 * - The endpoint resolves the link to a published post and adds one to a
 *   daily per-link counter (AIPS_Link_Clicks_Repository). No IP address,
 *   cookie or user ID is stored; IPs are only hashed briefly for rate limiting.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Click_Tracking_Service
 */
class AIPS_Link_Click_Tracking_Service {

	/**
	 * REST namespace and route.
	 */
	const REST_NAMESPACE = 'aips/v1';
	const REST_ROUTE     = '/link-click';

	/**
	 * Attribute added to tracked links.
	 */
	const SOURCE_ATTRIBUTE = 'data-aips-src';

	/**
	 * Clicks accepted per visitor per rate-limit window.
	 */
	const RATE_LIMIT        = 30;
	const RATE_LIMIT_WINDOW = 600;

	/**
	 * Object cache group for the atomic per-visitor rate limit counter.
	 */
	const RATE_LIMIT_CACHE_GROUP = 'aips_link_clicks';

	/**
	 * Transient that throttles pruning of old rows to once a day.
	 */
	const PRUNE_TRANSIENT = 'aips_link_clicks_pruned';

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Link_Clicks_Repository
	 */
	private $clicks;

	/**
	 * @var AIPS_Link_Url_Resolver
	 */
	private $resolver;

	/**
	 * @param AIPS_Config|null                 $config   Config.
	 * @param AIPS_Link_Clicks_Repository|null $clicks   Clicks repository.
	 * @param AIPS_Link_Url_Resolver|null      $resolver URL resolver.
	 */
	public function __construct(?AIPS_Config $config = null, ?AIPS_Link_Clicks_Repository $clicks = null, ?AIPS_Link_Url_Resolver $resolver = null) {
		$this->config   = $config ?: AIPS_Config::get_instance();
		$this->clicks   = $clicks ?: new AIPS_Link_Clicks_Repository();
		$this->resolver = $resolver ?: new AIPS_Link_Url_Resolver();
	}

	/**
	 * Whether click tracking is turned on.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) $this->config->get_option('aips_link_click_tracking_enabled', false);
	}

	/**
	 * Number of days of clicks the reports cover.
	 *
	 * @return int
	 */
	public function get_report_days(): int {
		return (int) apply_filters('aips_link_click_report_days', 30);
	}

	/**
	 * Timestamp of the start of the report window.
	 *
	 * @return int
	 */
	public function get_report_since(): int {
		return $this->day_start(time()) - (($this->get_report_days() - 1) * DAY_IN_SECONDS);
	}

	/**
	 * Register the frontend hooks (called from boot_frontend()).
	 *
	 * @return void
	 */
	public function register_frontend(): void {
		add_action('rest_api_init', array($this, 'register_routes'));

		if (!$this->is_enabled()) {
			return;
		}

		add_filter('the_content', array($this, 'filter_content'), 99);
		add_action('wp_enqueue_scripts', array($this, 'enqueue_tracker'));
	}

	/**
	 * Register POST aips/v1/link-click.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, array(
			'methods'             => 'POST',
			'callback'            => array($this, 'handle_click'),
			// Anonymous visitors click links; the handler validates everything.
			'permission_callback' => '__return_true',
			'args'                => array(
				'source' => array(
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
				'href'   => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'esc_url_raw',
				),
			),
		));
	}

	/**
	 * the_content filter: tag internal links in the main post.
	 *
	 * @param string $content Rendered content.
	 * @return string
	 */
	public function filter_content($content) {
		if (!is_string($content) || $content === '' || stripos($content, '<a') === false) {
			return $content;
		}

		$post = $this->get_trackable_post();
		if (!$post) {
			return $content;
		}

		return $this->tag_links($content, (int) $post->ID);
	}

	/**
	 * Add data-aips-src to internal links in HTML.
	 *
	 * @param string $html      HTML.
	 * @param int    $source_id Post the HTML belongs to.
	 * @return string
	 */
	public function tag_links(string $html, int $source_id): string {
		$resolver  = $this->resolver;
		$attribute = self::SOURCE_ATTRIBUTE;

		return (string) preg_replace_callback(
			'/<a\s[^>]*>/i',
			function ($match) use ($resolver, $attribute, $source_id) {
				$tag = $match[0];
				if (stripos($tag, $attribute) !== false) {
					return $tag;
				}
				if (!preg_match('/(?<![\w-])href\s*=\s*(["\'])(.*?)\1/is', $tag, $href)) {
					return $tag;
				}
				$url = $resolver->normalize(html_entity_decode($href[2], ENT_QUOTES, 'UTF-8'));
				if ($url === '' || !$resolver->is_internal($url)) {
					return $tag;
				}
				return substr($tag, 0, 2) . ' ' . $attribute . '="' . (int) $source_id . '"' . substr($tag, 2);
			},
			$html
		);
	}

	/**
	 * Enqueue the click beacon on tracked single posts.
	 *
	 * @return void
	 */
	public function enqueue_tracker(): void {
		if (!$this->get_trackable_post(false)) {
			return;
		}

		wp_enqueue_script(
			'aips-link-tracker',
			AIPS_PLUGIN_URL . 'assets/js/link-click-tracker.js',
			array(),
			AIPS_VERSION,
			array('in_footer' => true, 'strategy' => 'defer')
		);

		wp_localize_script('aips-link-tracker', 'aipsLinkTracker', array(
			'endpoint' => esc_url_raw(rest_url(self::REST_NAMESPACE . self::REST_ROUTE)),
		));
	}

	/**
	 * REST handler: count one click. Always answers 204 so the endpoint does
	 * not reveal which clicks were counted.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_click(WP_REST_Request $request): WP_REST_Response {
		$this->record_click((int) $request->get_param('source'), (string) $request->get_param('href'), (string) $request->get_header('user_agent'));

		return new WP_REST_Response(null, 204);
	}

	/**
	 * Validate and count a click.
	 *
	 * @param int    $source_id  Post the link was clicked in.
	 * @param string $href       Link href.
	 * @param string $user_agent Visitor user agent.
	 * @return bool Whether the click was counted.
	 */
	public function record_click(int $source_id, string $href, string $user_agent = ''): bool {
		if (!$this->is_enabled() || $source_id <= 0 || $href === '' || $this->is_bot($user_agent)) {
			return false;
		}

		$source = get_post($source_id);
		if (!$source instanceof WP_Post || $source->post_status !== 'publish' || !$this->link_index()->is_post_in_scope($source)) {
			return false;
		}

		$url = $this->resolver->normalize($href);
		if ($url === '' || !$this->resolver->is_internal($url)) {
			return false;
		}

		$target_id = $this->resolver->resolve_post_id($url);
		if ($target_id <= 0 || $target_id === $source_id || get_post_status($target_id) !== 'publish') {
			return false;
		}

		// Once a post is indexed, only count links it really contains.
		if (get_post_meta($source_id, AIPS_Link_Index_Service::HASH_META_KEY, true)
			&& !AIPS_Container::get_instance()->make(AIPS_Link_Index_Repository::class)->source_links_to($source_id, $target_id)) {
			return false;
		}

		if (!$this->within_rate_limit()) {
			return false;
		}

		$this->maybe_prune();

		return $this->clicks->record($source_id, $target_id, $this->day_start(time()));
	}

	/**
	 * Clicks into each post over the report window.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return array<int,int>
	 */
	public function get_inbound_clicks(array $post_ids): array {
		return $this->clicks->get_inbound_clicks_for_posts($post_ids, $this->get_report_since());
	}

	/**
	 * Clicks into a post by source post over the report window.
	 *
	 * @param int $post_id Target post.
	 * @return array<int,int>
	 */
	public function get_clicks_by_source(int $post_id): array {
		return $this->clicks->get_clicks_by_source($post_id, $this->get_report_since());
	}

	/**
	 * Clicks on a post's outbound links by destination over the report window.
	 *
	 * @param int $post_id Source post.
	 * @return array<int,int>
	 */
	public function get_clicks_by_target(int $post_id): array {
		return $this->clicks->get_clicks_by_target($post_id, $this->get_report_since());
	}

	/**
	 * Total clicks over the report window.
	 *
	 * @return int
	 */
	public function get_total(): int {
		return $this->clicks->get_total($this->get_report_since());
	}

	/**
	 * Most-clicked links over the report window.
	 *
	 * @param int $limit Maximum rows.
	 * @return array[] source_id, source_title, target_id, target_title, clicks.
	 */
	public function get_top_links(int $limit = 10): array {
		$links = array();
		foreach ($this->clicks->get_top_links($this->get_report_since(), $limit) as $row) {
			$links[] = array(
				'source_id'    => (int) $row->source_post_id,
				'source_title' => get_the_title((int) $row->source_post_id),
				'source_edit'  => (string) get_edit_post_link((int) $row->source_post_id, 'raw'),
				'target_id'    => (int) $row->target_post_id,
				'target_title' => get_the_title((int) $row->target_post_id),
				'target_edit'  => (string) get_edit_post_link((int) $row->target_post_id, 'raw'),
				'clicks'       => (int) $row->clicks,
			);
		}
		return $links;
	}

	/**
	 * Forget clicks involving a deleted post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_before_delete_post($post_id): void {
		$post_id = (int) $post_id;
		if ($post_id > 0 && !wp_is_post_revision($post_id)) {
			$this->clicks->delete_for_post($post_id);
		}
	}

	/**
	 * The current main-query post, when its links should be tracked.
	 *
	 * @param bool $require_loop Also require the_content to run in the main loop.
	 * @return WP_Post|null
	 */
	private function get_trackable_post(bool $require_loop = true): ?WP_Post {
		if (is_admin() || !is_singular() || is_preview() || (is_user_logged_in() && current_user_can('edit_posts'))) {
			return null;
		}

		if ($require_loop && (!in_the_loop() || !is_main_query())) {
			return null;
		}

		$post = $require_loop ? get_post() : get_queried_object();
		if (!$post instanceof WP_Post || $post->post_status !== 'publish' || !$this->link_index()->is_post_in_scope($post)) {
			return null;
		}

		return $post;
	}

	/**
	 * Crude bot filter (real browsers send a user agent without these words).
	 *
	 * @param string $user_agent User agent.
	 * @return bool
	 */
	private function is_bot(string $user_agent): bool {
		if ($user_agent === '') {
			return true;
		}

		$is_bot = (bool) preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|facebookexternalhit|curl|wget|python|java\//i', $user_agent);

		/**
		 * Filters whether a click comes from a bot and should not be counted.
		 *
		 * @param bool   $is_bot     Whether the user agent looks like a bot.
		 * @param string $user_agent The user agent.
		 */
		return (bool) apply_filters('aips_link_click_is_bot', $is_bot, $user_agent);
	}

	/**
	 * Per-visitor rate limit (hashed IP, short-lived transient).
	 *
	 * @return bool False when this visitor sent too many clicks recently.
	 */
	private function within_rate_limit(): bool {
		$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
		if ($ip === '') {
			return true;
		}

		$key = 'aips_lc_' . substr(hash_hmac('sha256', $ip, wp_salt('nonce')), 0, 32);

		// A persistent object cache (Redis, Memcached, ...) increments
		// atomically, so concurrent clicks from the same visitor can't slip
		// past the cap the way a plain transient read-then-write would.
		if (wp_using_ext_object_cache()) {
			wp_cache_add($key, 0, self::RATE_LIMIT_CACHE_GROUP, self::RATE_LIMIT_WINDOW);
			$count = wp_cache_incr($key, 1, self::RATE_LIMIT_CACHE_GROUP);
			return $count !== false && $count <= self::RATE_LIMIT;
		}

		// No persistent object cache: a rough throttle backed by the options
		// table. Good enough here — click counts are advisory reporting, not
		// a security boundary — but not a hard cap under heavy concurrency.
		$count = (int) get_transient($key);
		if ($count >= self::RATE_LIMIT) {
			return false;
		}

		set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
		return true;
	}

	/**
	 * Remove rows older than the retention period, at most once a day.
	 *
	 * @return void
	 */
	private function maybe_prune(): void {
		if (get_transient(self::PRUNE_TRANSIENT)) {
			return;
		}

		set_transient(self::PRUNE_TRANSIENT, 1, DAY_IN_SECONDS);

		$days = max(30, (int) $this->config->get_option('aips_link_click_retention_days', 365));
		$this->clicks->prune($this->day_start(time()) - ($days * DAY_IN_SECONDS));
	}

	/**
	 * UTC midnight for a timestamp.
	 *
	 * @param int $timestamp Timestamp.
	 * @return int
	 */
	private function day_start(int $timestamp): int {
		return $timestamp - ($timestamp % DAY_IN_SECONDS);
	}

	/**
	 * @return AIPS_Link_Index_Service
	 */
	private function link_index(): AIPS_Link_Index_Service {
		return AIPS_Container::get_instance()->make(AIPS_Link_Index_Service::class);
	}
}
