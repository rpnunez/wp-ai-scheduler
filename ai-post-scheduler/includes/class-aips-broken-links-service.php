<?php
/**
 * Broken Links Service
 *
 * Fixes broken internal links found by the link index: suggests the live
 * post a dead URL most likely meant (matching the URL's slug words and the
 * anchor text against published post titles and slugs), re-points the link
 * to it or removes the link while keeping its text, and undoes either fix.
 *
 * Each change is recorded as before/after snippets (widened until unique)
 * so undo restores exactly what was there, and refuses if the post was
 * edited since.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.5
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Broken_Links_Service
 */
class AIPS_Broken_Links_Service {

	/**
	 * Option holding recent fixes (for undo).
	 */
	const FIXES_OPTION = 'aips_broken_link_fixes';

	/**
	 * Fixes kept for undo.
	 */
	const FIXES_LIMIT = 200;

	/**
	 * Bytes of context kept around each change for undo.
	 */
	const SNIPPET_CONTEXT = 80;

	/**
	 * Replacement candidates returned per broken link.
	 */
	const MAX_SUGGESTIONS = 3;

	/**
	 * Words ignored when matching.
	 *
	 * @var string[]
	 */
	private static $stop_words = array(
		'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'how', 'in', 'is', 'it', 'of',
		'on', 'or', 'the', 'to', 'what', 'why', 'with', 'your', 'you', 'this', 'that', 'page', 'post',
		'html', 'htm', 'php', 'www', 'http', 'https', 'here', 'click', 'more', 'read',
	);

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $link_index;

	/**
	 * @var AIPS_Link_Url_Resolver
	 */
	private $resolver;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @param AIPS_Link_Index_Service|null $link_index Link index service.
	 * @param AIPS_Link_Url_Resolver|null  $resolver   URL resolver.
	 * @param AIPS_Config|null             $config     Config.
	 */
	public function __construct(
		?AIPS_Link_Index_Service $link_index = null,
		?AIPS_Link_Url_Resolver $resolver = null,
		?AIPS_Config $config = null
	) {
		$container        = AIPS_Container::get_instance();
		$this->link_index = $link_index ?: ($container->has(AIPS_Link_Index_Service::class) ? $container->make(AIPS_Link_Index_Service::class) : new AIPS_Link_Index_Service());
		$this->resolver   = $resolver ?: new AIPS_Link_Url_Resolver();
		$this->config     = $config ?: AIPS_Config::get_instance();
	}

	/**
	 * One page of broken links, each with replacement suggestions.
	 *
	 * @param int    $page   1-based page.
	 * @param string $search Optional search.
	 * @return array{rows:array[], total:int, total_pages:int, page:int}
	 */
	public function get_page(int $page = 1, string $search = ''): array {
		$repo     = $this->link_index->get_repository();
		$per_page = 20;
		$total    = $repo->get_broken_links_count($search);
		$rows     = array();

		foreach ($repo->get_broken_links_page($page, $per_page, $search) as $row) {
			$rows[] = array(
				'source_id'    => (int) $row->source_post_id,
				'source_title' => $row->source_title !== '' ? $row->source_title : sprintf('#%d', (int) $row->source_post_id),
				'source_edit'  => (string) get_edit_post_link((int) $row->source_post_id, 'raw'),
				'url'          => (string) $row->target_url,
				'anchor'       => (string) $row->anchor_text,
				'occurrences'  => (int) $row->occurrences,
				'suggestions'  => $this->suggest_replacements((string) $row->target_url, (string) $row->anchor_text, (int) $row->source_post_id),
			);
		}

		return array(
			'rows'        => $rows,
			'total'       => $total,
			'total_pages' => max(1, (int) ceil($total / $per_page)),
			'page'        => max(1, $page),
		);
	}

	/**
	 * Live posts a broken URL most likely meant.
	 *
	 * @param string $url       Broken URL.
	 * @param string $anchor    Anchor text of the link.
	 * @param int    $source_id Post containing the link (never suggested).
	 * @return array[] Up to MAX_SUGGESTIONS of {id, title, url, score}.
	 */
	public function suggest_replacements(string $url, string $anchor, int $source_id = 0): array {
		$path       = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
		$segments   = $path === '' ? array() : explode('/', $path);
		$slug_words = $this->words(str_replace(array('-', '_'), ' ', urldecode((string) end($segments))));
		$words      = array_values(array_unique(array_merge($slug_words, $this->words($anchor))));

		if (empty($words)) {
			return array();
		}

		$candidates = array();
		foreach (array_slice($words, 0, 5) as $word) {
			$ids = get_posts(array(
				's'                      => $word,
				'post_type'              => $this->link_index->get_post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => 20,
				'fields'                 => 'ids',
				'post__not_in'           => $source_id ? array($source_id) : array(),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			));
			foreach ((array) $ids as $id) {
				$candidates[(int) $id] = true;
			}
		}

		$scored = array();
		foreach (array_keys($candidates) as $id) {
			$post = get_post($id);
			if (!$post) {
				continue;
			}

			$candidate_words = array_unique(array_merge(
				$this->words((string) $post->post_title),
				$this->words(str_replace('-', ' ', (string) $post->post_name))
			));
			$shared = count(array_intersect($words, $candidate_words));
			if ($shared === 0) {
				continue;
			}

			$slug_hits = count(array_intersect($slug_words, $candidate_words));
			$union     = count(array_unique(array_merge($words, $candidate_words)));
			$score     = ($shared / max(1, $union)) + (empty($slug_words) ? 0 : 0.5 * $slug_hits / count($slug_words));

			$scored[] = array(
				'id'    => (int) $id,
				'title' => get_the_title($id),
				'url'   => (string) get_permalink($id),
				'score' => (int) round(min(1.0, $score) * 100),
			);
		}

		usort($scored, function ($a, $b) {
			return $b['score'] <=> $a['score'];
		});

		return array_slice($scored, 0, self::MAX_SUGGESTIONS);
	}

	/**
	 * Point every link to $old_url in a post at another post.
	 *
	 * @param int    $source_id Post containing the broken link.
	 * @param string $old_url   Broken URL (as stored in the link index).
	 * @param int    $target_id Replacement post.
	 * @return array{fix_id:string, changed:int}|WP_Error
	 */
	public function repoint(int $source_id, string $old_url, int $target_id) {
		$target_url = $target_id ? (string) get_permalink($target_id) : '';
		if ($target_url === '' || get_post_status($target_id) !== 'publish') {
			return new WP_Error('aips_broken_invalid_target', __('Choose a published post to link to.', 'ai-post-scheduler'));
		}

		return $this->rewrite($source_id, $old_url, 'repoint', function ($tag, $attributes_raw, $inner) use ($target_url) {
			$new_attributes = preg_replace(
				'/((?<![\w-])href\s*=\s*)(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+)/i',
				'$1"' . esc_url($target_url) . '"',
				$attributes_raw,
				1
			);
			return '<a' . $new_attributes . '>' . $inner . '</a>';
		}, array('target_id' => $target_id, 'new_url' => $target_url));
	}

	/**
	 * Remove every link to $old_url in a post, keeping the linked text.
	 *
	 * @param int    $source_id Post containing the broken link.
	 * @param string $old_url   Broken URL.
	 * @return array{fix_id:string, changed:int}|WP_Error
	 */
	public function unlink(int $source_id, string $old_url) {
		return $this->rewrite($source_id, $old_url, 'unlink', function ($tag, $attributes_raw, $inner) {
			return $inner;
		}, array());
	}

	/**
	 * Undo a fix.
	 *
	 * @param string $fix_id Fix ID.
	 * @return true|WP_Error
	 */
	public function undo(string $fix_id) {
		$fixes = (array) $this->config->get_option(self::FIXES_OPTION, array());
		if (!isset($fixes[$fix_id]) || !empty($fixes[$fix_id]['undone'])) {
			return new WP_Error('aips_broken_fix_not_found', __('This fix can no longer be undone.', 'ai-post-scheduler'));
		}

		$fix  = $fixes[$fix_id];
		$post = get_post((int) $fix['source_id']);
		if (!$post) {
			return new WP_Error('aips_broken_missing_post', __('The post no longer exists.', 'ai-post-scheduler'));
		}

		$engine  = new AIPS_Link_Insertion_Engine();
		$content = (string) $post->post_content;

		// Undo in reverse order of application.
		foreach (array_reverse($fix['changes']) as $change) {
			$result = $engine->revert($content, $change['before'], $change['after']);
			if ($result['status'] !== AIPS_Link_Insertion_Engine::REVERT_OK) {
				return new WP_Error('aips_broken_undo_conflict', __('The post was edited after this fix, so it cannot be undone automatically. Edit the link in the post instead.', 'ai-post-scheduler'));
			}
			$content = $result['content'];
		}

		$saved = wp_update_post(array('ID' => (int) $fix['source_id'], 'post_content' => wp_slash($content)), true);
		if (is_wp_error($saved)) {
			return $saved;
		}

		$fixes[$fix_id]['undone'] = true;
		$this->config->set_option(self::FIXES_OPTION, $fixes, false);

		return true;
	}

	/**
	 * Recent fixes, newest first, for the UI.
	 *
	 * @param int $limit Maximum fixes.
	 * @return array[]
	 */
	public function get_recent_fixes(int $limit = 20): array {
		$fixes = array_reverse((array) $this->config->get_option(self::FIXES_OPTION, array()), true);
		$out   = array();

		foreach ($fixes as $id => $fix) {
			$out[] = array(
				'id'           => (string) $id,
				'action'       => (string) $fix['action'],
				'source_title' => get_the_title((int) $fix['source_id']),
				'source_edit'  => (string) get_edit_post_link((int) $fix['source_id'], 'raw'),
				'old_url'      => (string) $fix['old_url'],
				'new_url'      => isset($fix['new_url']) ? (string) $fix['new_url'] : '',
				'changed'      => count($fix['changes']),
				'undone'       => !empty($fix['undone']),
				'time'         => (int) $fix['time'],
			);
			if (count($out) >= $limit) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Rewrite every anchor in a post whose href resolves to $old_url.
	 *
	 * @param int      $source_id Post ID.
	 * @param string   $old_url   Broken URL.
	 * @param string   $action    'repoint' or 'unlink'.
	 * @param callable $replace   fn(string $tag, string $attributes_raw, string $inner): string.
	 * @param array    $meta      Extra data stored with the fix.
	 * @return array{fix_id:string, changed:int}|WP_Error
	 */
	private function rewrite(int $source_id, string $old_url, string $action, callable $replace, array $meta) {
		$post = get_post($source_id);
		if (!$post) {
			return new WP_Error('aips_broken_missing_post', __('The post no longer exists.', 'ai-post-scheduler'));
		}

		$locked_by = function_exists('wp_check_post_lock') ? wp_check_post_lock($source_id) : false;
		if ($locked_by) {
			return new WP_Error('aips_broken_post_locked', __('Someone is editing this post right now. Try again when they are done.', 'ai-post-scheduler'));
		}

		$needle  = $this->comparable($old_url);
		$content = (string) $post->post_content;
		$pattern = '/<a\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>(.*?)<\/a\s*>/is';

		if ($needle === '' || !preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
			return new WP_Error('aips_broken_not_found', __('That link is no longer in the post. Run a link scan to refresh the report.', 'ai-post-scheduler'));
		}

		$changes = array();

		// Rewrite from the end so earlier offsets stay valid.
		foreach (array_reverse($matches) as $match) {
			list($tag, $offset)   = $match[0];
			$attributes_raw       = $match[1][0];
			$inner                = $match[2][0];

			if (!preg_match('/(?<![\w-])href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i', $attributes_raw, $href_match)) {
				continue;
			}
			$href = html_entity_decode($href_match[1] !== '' ? $href_match[1] : (isset($href_match[2]) && $href_match[2] !== '' ? $href_match[2] : (isset($href_match[3]) ? $href_match[3] : '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if ($this->comparable($href) !== $needle) {
				continue;
			}

			$new_tag     = call_user_func($replace, $tag, $attributes_raw, $inner);
			$new_content = substr($content, 0, $offset) . $new_tag . substr($content, $offset + strlen($tag));

			$changes[] = $this->snippets($content, $new_content, $offset, strlen($tag), strlen($new_tag));
			$content   = $new_content;
		}

		if (empty($changes)) {
			return new WP_Error('aips_broken_not_found', __('That link is no longer in the post. Run a link scan to refresh the report.', 'ai-post-scheduler'));
		}

		$saved = wp_update_post(array('ID' => $source_id, 'post_content' => wp_slash($content)), true);
		if (is_wp_error($saved)) {
			return $saved;
		}

		$fix_id = wp_generate_uuid4();
		$fixes  = (array) $this->config->get_option(self::FIXES_OPTION, array());
		$fixes[$fix_id] = array_merge(
			array(
				'action'    => $action,
				'source_id' => $source_id,
				'old_url'   => $old_url,
				'changes'   => $changes,
				'time'      => time(),
				'undone'    => false,
			),
			$meta
		);
		$this->config->set_option(self::FIXES_OPTION, array_slice($fixes, -self::FIXES_LIMIT, null, true), false);

		return array(
			'fix_id'  => $fix_id,
			'changed' => count($changes),
		);
	}

	/**
	 * Before/after snippets around one change, widened until each is unique.
	 *
	 * @param string $before_html Content before the change.
	 * @param string $after_html  Content after the change.
	 * @param int    $offset      Change offset.
	 * @param int    $old_length  Replaced length.
	 * @param int    $new_length  Replacement length.
	 * @return array{before:string, after:string}
	 */
	private function snippets(string $before_html, string $after_html, int $offset, int $old_length, int $new_length): array {
		$context = self::SNIPPET_CONTEXT;

		do {
			$start  = max(0, $offset - $context);
			$tail   = min($context, strlen($before_html) - ($offset + $old_length));
			$before = substr($before_html, $start, ($offset - $start) + $old_length + $tail);
			$after  = substr($after_html, $start, ($offset - $start) + $new_length + $tail);
			$unique = substr_count($before_html, $before) === 1 && substr_count($after_html, $after) === 1;
			$whole  = $start === 0 && ($offset + $old_length + $tail) >= strlen($before_html);
			$context *= 2;
		} while (!$unique && !$whole && $context <= 4000);

		return array('before' => $before, 'after' => $after);
	}

	/**
	 * URL key that ignores scheme, "www." and a trailing slash, so one fix
	 * covers every variant of the same dead URL.
	 *
	 * @param string $url Raw or normalized URL.
	 * @return string Empty when the URL is not an http(s) URL.
	 */
	private function comparable(string $url): string {
		$normalized = $this->resolver->normalize($url);
		if ($normalized === '') {
			return '';
		}

		return untrailingslashit((string) preg_replace('#^https?://(www\.)?#i', '', strtolower($normalized)));
	}

	/**
	 * Lower-case meaningful words (3+ letters, no stop words).
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private function words(string $text): array {
		$text  = mb_strtolower(wp_strip_all_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
		$parts = preg_split('/[^\p{L}\p{N}]+/u', $text);
		$words = array();

		foreach ((array) $parts as $word) {
			if (mb_strlen($word) >= 3 && !is_numeric($word) && !in_array($word, self::$stop_words, true)) {
				$words[] = $word;
			}
		}

		return array_values(array_unique($words));
	}
}
