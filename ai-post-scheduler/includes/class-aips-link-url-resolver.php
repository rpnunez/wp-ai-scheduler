<?php
/**
 * Link URL Resolver
 *
 * Classifies hrefs found in post content as internal or external and
 * resolves internal URLs to post IDs, with caching. Handles relative and
 * protocol-relative URLs, http/https and www differences, ?p= / ?page_id=
 * query links, attachment URLs and links to posts whose slug has changed
 * (_wp_old_slug).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Url_Resolver
 */
class AIPS_Link_Url_Resolver {

	/**
	 * Link type for URLs on this site (matches AIPS_Link_Index_Repository).
	 */
	const TYPE_INTERNAL = 'internal';

	/**
	 * Link type for URLs on other sites (matches AIPS_Link_Index_Repository).
	 */
	const TYPE_EXTERNAL = 'external';

	/**
	 * Object cache group for resolved URLs.
	 */
	const CACHE_GROUP = 'aips_link_resolve';

	/**
	 * Lifetime of cached resolutions, in seconds.
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Per-request resolution cache: normalized URL => post ID.
	 *
	 * @var array<string, int>
	 */
	private $resolved = array();

	/**
	 * Lower-cased site hosts without a leading "www.".
	 *
	 * @var string[]|null
	 */
	private $site_hosts = null;

	/**
	 * Resolve an href from post content into a link index row.
	 *
	 * @param string $href Decoded href from AIPS_Link_Extractor.
	 * @return array|null Array with target_url, link_type ('internal'|'external')
	 *                    and target_post_id, or null when the href is unusable.
	 */
	public function resolve_link(string $href): ?array {
		$url = $this->normalize($href);
		if ($url === '') {
			return null;
		}

		if (!$this->is_internal($url)) {
			return array(
				'target_url'     => $url,
				'link_type'      => self::TYPE_EXTERNAL,
				'target_post_id' => 0,
			);
		}

		return array(
			'target_url'     => $url,
			'link_type'      => self::TYPE_INTERNAL,
			'target_post_id' => $this->resolve_post_id($url),
		);
	}

	/**
	 * Make an href absolute and drop its fragment.
	 *
	 * Relative hrefs resolve against home_url(). Returns '' for hrefs that are
	 * not http(s) URLs once resolved.
	 *
	 * @param string $href Raw href.
	 * @return string
	 */
	public function normalize(string $href): string {
		$href = trim($href);
		if ($href === '') {
			return '';
		}

		$hash = strpos($href, '#');
		if ($hash !== false) {
			$href = substr($href, 0, $hash);
		}
		if ($href === '') {
			return '';
		}

		$home = home_url('/');

		if (strpos($href, '//') === 0) {
			$href = (string) wp_parse_url($home, PHP_URL_SCHEME) . ':' . $href;
		} elseif (!preg_match('#^[a-z][a-z0-9+.\-]*:#i', $href)) {
			$href = $this->resolve_relative($href, $home);
		}

		$scheme = strtolower((string) wp_parse_url($href, PHP_URL_SCHEME));
		if (!in_array($scheme, array('http', 'https'), true) || !wp_parse_url($href, PHP_URL_HOST)) {
			return '';
		}

		/**
		 * Filter a normalized link URL before it is classified and resolved.
		 *
		 * Useful for multilingual setups that map language domains or
		 * prefixes onto the primary site URL.
		 *
		 * @param string $href Absolute URL without fragment.
		 */
		return (string) apply_filters('aips_link_resolver_url', $href);
	}

	/**
	 * Whether an absolute URL points at this site.
	 *
	 * Hosts compare case-insensitively and ignore a leading "www.".
	 *
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	public function is_internal(string $url): bool {
		$host = wp_parse_url($url, PHP_URL_HOST);
		if (!$host) {
			return false;
		}

		return in_array($this->canonical_host($host), $this->get_site_hosts(), true);
	}

	/**
	 * Resolve an internal URL to a post ID (0 when nothing matches).
	 *
	 * @param string $url Normalized absolute URL.
	 * @return int
	 */
	public function resolve_post_id(string $url): int {
		if (isset($this->resolved[$url])) {
			return $this->resolved[$url];
		}

		$cache_key = md5((string) get_option('permalink_structure') . '|' . $url);
		$cached    = wp_cache_get($cache_key, self::CACHE_GROUP);

		if (is_array($cached) && isset($cached['id'])) {
			$post_id = (int) $cached['id'];
		} else {
			$post_id = $this->lookup_post_id($url);
			wp_cache_set($cache_key, array('id' => $post_id), self::CACHE_GROUP, self::CACHE_TTL);
		}

		$this->resolved[$url] = $post_id;

		return $post_id;
	}

	/**
	 * Uncached resolution chain.
	 *
	 * @param string $url Normalized absolute URL.
	 * @return int
	 */
	private function lookup_post_id(string $url): int {
		$query = (string) wp_parse_url($url, PHP_URL_QUERY);
		if ($query !== '') {
			parse_str($query, $vars);
			foreach (array('p', 'page_id', 'attachment_id') as $var) {
				if (!empty($vars[$var]) && get_post(absint($vars[$var]))) {
					return absint($vars[$var]);
				}
			}
		}

		$candidates = array($url, $this->with_site_scheme_and_host($url));
		foreach (array_unique($candidates) as $candidate) {
			// url_to_postid() echoes ?p=N style IDs without checking the post exists.
			$post_id = (int) url_to_postid($candidate);
			if ($post_id > 0 && get_post($post_id)) {
				return $post_id;
			}
		}

		$upload_dir = wp_get_upload_dir();
		if (!empty($upload_dir['baseurl']) && strpos($this->strip_scheme($url), $this->strip_scheme($upload_dir['baseurl'])) === 0) {
			$attachment_id = (int) attachment_url_to_postid($url);
			if ($attachment_id > 0) {
				return $attachment_id;
			}
		}

		return $this->lookup_old_slug($url);
	}

	/**
	 * Find a post whose previous slug matches the URL's last path segment.
	 *
	 * @param string $url Normalized absolute URL.
	 * @return int
	 */
	private function lookup_old_slug(string $url): int {
		$path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
		if ($path === '') {
			return 0;
		}

		$segments = explode('/', $path);
		$slug     = sanitize_title(urldecode(end($segments)));
		if ($slug === '') {
			return 0;
		}

		$ids = get_posts(array(
			'post_type'              => 'any',
			'post_status'            => 'publish',
			'meta_key'               => '_wp_old_slug',
			'meta_value'             => $slug,
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		));

		return !empty($ids) ? (int) $ids[0] : 0;
	}

	/**
	 * Resolve a relative href against a base URL.
	 *
	 * @param string $href Relative href (path, "./", "../" or query).
	 * @param string $base Base URL ending in "/".
	 * @return string
	 */
	private function resolve_relative(string $href, string $base): string {
		$scheme = (string) wp_parse_url($base, PHP_URL_SCHEME);
		$host   = (string) wp_parse_url($base, PHP_URL_HOST);
		$port   = wp_parse_url($base, PHP_URL_PORT);
		$origin = $scheme . '://' . $host . ($port ? ':' . $port : '');

		$query = '';
		$qpos  = strpos($href, '?');
		if ($qpos !== false) {
			$query = substr($href, $qpos);
			$href  = substr($href, 0, $qpos);
		}

		if ($href !== '' && $href[0] === '/') {
			$path = $href;
		} else {
			$base_path = (string) wp_parse_url($base, PHP_URL_PATH);
			$base_path = substr($base_path, 0, (int) strrpos($base_path, '/') + 1);
			$path      = $base_path . $href;
		}

		$segments = array();
		foreach (explode('/', $path) as $segment) {
			if ($segment === '..') {
				array_pop($segments);
			} elseif ($segment !== '.') {
				$segments[] = $segment;
			}
		}

		$path = implode('/', $segments);
		if ($path === '' || $path[0] !== '/') {
			$path = '/' . $path;
		}

		return $origin . $path . $query;
	}

	/**
	 * Rewrite a URL onto the site's own scheme and host so url_to_postid()
	 * accepts www/non-www and http/https variants.
	 *
	 * @param string $url Absolute URL.
	 * @return string
	 */
	private function with_site_scheme_and_host(string $url): string {
		$home   = home_url('/');
		$scheme = (string) wp_parse_url($home, PHP_URL_SCHEME);
		$host   = (string) wp_parse_url($home, PHP_URL_HOST);
		$port   = wp_parse_url($home, PHP_URL_PORT);

		return (string) preg_replace(
			'#^https?://[^/?]+#i',
			$scheme . '://' . $host . ($port ? ':' . $port : ''),
			$url
		);
	}

	/**
	 * Strip the scheme and a leading "www." from a URL for prefix comparison.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function strip_scheme(string $url): string {
		return (string) preg_replace('#^(https?:)?//(www\.)?#i', '', strtolower($url));
	}

	/**
	 * Lower-case a host and drop a leading "www.".
	 *
	 * @param string $host Host name.
	 * @return string
	 */
	private function canonical_host(string $host): string {
		$host = strtolower($host);
		return (strpos($host, 'www.') === 0) ? substr($host, 4) : $host;
	}

	/**
	 * Hosts that count as this site.
	 *
	 * @return string[]
	 */
	private function get_site_hosts(): array {
		if ($this->site_hosts === null) {
			$hosts = array(
				(string) wp_parse_url(home_url(), PHP_URL_HOST),
				(string) wp_parse_url(site_url(), PHP_URL_HOST),
			);

			/**
			 * Filter the hosts treated as internal when classifying links.
			 *
			 * @param string[] $hosts Host names (e.g. additional language domains).
			 */
			$hosts = (array) apply_filters('aips_link_resolver_internal_hosts', $hosts);

			$this->site_hosts = array_values(array_unique(array_filter(array_map(array($this, 'canonical_host'), array_map('strval', $hosts)))));
		}

		return $this->site_hosts;
	}
}
