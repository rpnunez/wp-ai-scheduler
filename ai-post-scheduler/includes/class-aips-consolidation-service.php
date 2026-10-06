<?php
/**
 * Consolidation Service
 *
 * Consolidates two overlapping posts (a Cannibalization Shield pair): the
 * post that is kept stays published; the other one is retired. Retiring
 * creates a redirect from the old URL to the kept post, re-points internal
 * links to it, moves the retired post to draft and sends a notification.
 * An optional AI-merged draft of both posts can be saved as a revision of
 * the kept post (to review first) or written into it straight away.
 *
 * Every consolidation is recorded and can be undone.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Consolidation_Service
 */
class AIPS_Consolidation_Service {

	/**
	 * Option holding consolidation records, keyed by ID.
	 */
	const HISTORY_OPTION = 'aips_consolidations';

	/**
	 * Records kept.
	 */
	const HISTORY_LIMIT = 100;

	/**
	 * Post meta on the kept post: content before a rewrite, keyed by consolidation ID.
	 */
	const BACKUP_META = '_aips_consolidation_backup';

	/**
	 * Post meta on the retired post: ID of the post it was consolidated into.
	 */
	const RETIRED_META = '_aips_consolidated_into';

	/**
	 * What to do with the AI-merged content.
	 */
	const CONTENT_NONE     = 'none';
	const CONTENT_REVISION = 'revision';
	const CONTENT_REWRITE  = 'rewrite';

	/**
	 * @var AIPS_Redirects_Service
	 */
	private $redirects;

	/**
	 * @var AIPS_Broken_Links_Service
	 */
	private $link_fixer;

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $link_index;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_AI_Service|null Resolved lazily; only the merge needs it.
	 */
	private $ai_service;

	/**
	 * @var AIPS_Notifications|null Resolved lazily.
	 */
	private $notifications;

	/**
	 * @param AIPS_Redirects_Service|null    $redirects     Redirects service.
	 * @param AIPS_Broken_Links_Service|null $link_fixer    Link re-pointing (shared with the broken-link fixer).
	 * @param AIPS_Link_Index_Service|null   $link_index    Link index.
	 * @param AIPS_Config|null               $config        Config.
	 * @param AIPS_AI_Service|null           $ai_service    AI service.
	 * @param AIPS_Notifications|null        $notifications Notifications.
	 */
	public function __construct(
		?AIPS_Redirects_Service $redirects = null,
		?AIPS_Broken_Links_Service $link_fixer = null,
		?AIPS_Link_Index_Service $link_index = null,
		?AIPS_Config $config = null,
		$ai_service = null,
		$notifications = null
	) {
		$container          = AIPS_Container::get_instance();
		$this->redirects    = $redirects ?: ($container->has(AIPS_Redirects_Service::class) ? $container->make(AIPS_Redirects_Service::class) : new AIPS_Redirects_Service());
		$this->link_index   = $link_index ?: ($container->has(AIPS_Link_Index_Service::class) ? $container->make(AIPS_Link_Index_Service::class) : new AIPS_Link_Index_Service());
		$this->link_fixer   = $link_fixer ?: new AIPS_Broken_Links_Service($this->link_index);
		$this->config       = $config ?: AIPS_Config::get_instance();
		$this->ai_service   = $ai_service;
		$this->notifications = $notifications;
	}

	/**
	 * What consolidating a pair would do, for the confirmation dialog.
	 *
	 * @param int $keep_id   Post to keep.
	 * @param int $retire_id Post to retire.
	 * @return array|WP_Error
	 */
	public function preview(int $keep_id, int $retire_id) {
		$pair = $this->validate_pair($keep_id, $retire_id);
		if (is_wp_error($pair)) {
			return $pair;
		}
		list($keep, $retire) = $pair;

		return array(
			'keep'          => $this->describe($keep),
			'retire'        => $this->describe($retire),
			'inbound_posts' => count($this->get_inbound_links($retire_id)),
			'provider'      => $this->redirects->get_active_provider()->get_label(),
			'ai_available'  => $this->get_ai_service()->is_available(),
		);
	}

	/**
	 * AI-merged article body from both posts. Nothing is saved.
	 *
	 * @param int    $keep_id      Post to keep.
	 * @param int    $retire_id    Post to retire.
	 * @param string $instructions Optional extra instructions.
	 * @return string|WP_Error Sanitized HTML.
	 */
	public function generate_merge(int $keep_id, int $retire_id, string $instructions = '') {
		$pair = $this->validate_pair($keep_id, $retire_id);
		if (is_wp_error($pair)) {
			return $pair;
		}
		list($keep, $retire) = $pair;

		$builder = new AIPS_Prompt_Builder_Consolidation();
		$prompt  = $builder->build($keep, $retire, (string) get_permalink($retire), $instructions);

		$response = $this->get_ai_service()->generate_text($prompt, array('temperature' => 0.4, 'max_tokens' => 8000));
		if (is_wp_error($response)) {
			return $response;
		}

		$html = $this->clean_ai_html((string) $response);
		if (trim(wp_strip_all_tags($html)) === '') {
			return new WP_Error('aips_consolidation_empty_merge', __('The AI returned an empty article. Try again.', 'ai-post-scheduler'));
		}

		return $html;
	}

	/**
	 * Consolidate a pair.
	 *
	 * @param int   $keep_id   Post to keep.
	 * @param int   $retire_id Post to retire.
	 * @param array $args {
	 *     @type string $content_mode 'none', 'revision' or 'rewrite'.
	 *     @type string $content      Merged HTML (required unless content_mode is 'none').
	 *     @type int    $status_code  Redirect type (301 default).
	 * }
	 * @return array|WP_Error The stored record (see get_history()).
	 */
	public function consolidate(int $keep_id, int $retire_id, array $args = array()) {
		$pair = $this->validate_pair($keep_id, $retire_id);
		if (is_wp_error($pair)) {
			return $pair;
		}
		list($keep, $retire) = $pair;

		$mode    = isset($args['content_mode']) ? (string) $args['content_mode'] : self::CONTENT_NONE;
		$content = isset($args['content']) ? wp_kses_post((string) $args['content']) : '';
		if (!in_array($mode, array(self::CONTENT_NONE, self::CONTENT_REVISION, self::CONTENT_REWRITE), true)) {
			$mode = self::CONTENT_NONE;
		}
		if ($mode !== self::CONTENT_NONE && trim(wp_strip_all_tags($content)) === '') {
			return new WP_Error('aips_consolidation_no_content', __('Generate the merged draft first, or choose not to use one.', 'ai-post-scheduler'));
		}

		// Read the old URL while the post is still published: a draft's permalink is ?p=ID.
		$retire_url = (string) get_permalink($retire);
		$id         = wp_generate_uuid4();

		// 1. Redirect first: it is the step most likely to be refused (an
		//    existing redirect for the URL), and nothing else has changed yet.
		$redirect = $this->redirects->create(array(
			'source'         => $retire_url,
			'target_post_id' => $keep_id,
			'status_code'    => isset($args['status_code']) ? (int) $args['status_code'] : 301,
			'origin'         => AIPS_Redirects_Service::ORIGIN_CONSOLIDATION,
			'origin_ref'     => $retire_id,
		));
		if (is_wp_error($redirect)) {
			return $redirect;
		}

		// 2. Merged content.
		$revision_id = 0;
		if ($mode === self::CONTENT_REVISION) {
			$revision_id = $this->save_as_revision($keep, $content);
			if (is_wp_error($revision_id)) {
				$this->redirects->delete((int) $redirect->id);
				return $revision_id;
			}
		} elseif ($mode === self::CONTENT_REWRITE) {
			$backups        = (array) get_post_meta($keep_id, self::BACKUP_META, true);
			$backups[$id]   = (string) $keep->post_content;
			update_post_meta($keep_id, self::BACKUP_META, wp_slash($backups));

			$saved = wp_update_post(array('ID' => $keep_id, 'post_content' => wp_slash($content)), true);
			if (is_wp_error($saved)) {
				$this->redirects->delete((int) $redirect->id);
				return $saved;
			}
		}

		// 3. Re-point internal links from the retired post to the kept one.
		list($fixes, $links_changed, $link_failures) = $this->repoint_links($retire_id, $keep_id);

		// 4. Retire the post.
		$previous_status = $retire->post_status;
		wp_update_post(array('ID' => $retire_id, 'post_status' => 'draft'));
		update_post_meta($retire_id, self::RETIRED_META, $keep_id);

		$providers = $this->redirects->get_providers();
		$record    = array(
			'id'               => $id,
			'keep_id'          => $keep_id,
			'retire_id'        => $retire_id,
			'keep_title'       => get_the_title($keep),
			'retire_title'     => get_the_title($retire),
			'retire_url'       => $retire_url,
			'previous_status'  => $previous_status,
			'redirect_id'      => (int) $redirect->id,
			'redirect_provider'=> isset($providers[$redirect->provider]) ? $providers[$redirect->provider]->get_label() : (string) $redirect->provider,
			'content_mode'     => $mode,
			'revision_id'      => is_int($revision_id) ? $revision_id : 0,
			'merged_hash'      => $mode === self::CONTENT_REWRITE ? md5($this->stored_content($keep_id)) : '',
			'fixes'            => $fixes,
			'links_repointed'  => $links_changed,
			'link_failures'    => $link_failures,
			'user_id'          => get_current_user_id(),
			'time'             => time(),
			'undone'           => false,
		);

		$history      = (array) $this->config->get_option(self::HISTORY_OPTION, array());
		$history[$id] = $record;
		$this->config->set_option(self::HISTORY_OPTION, array_slice($history, -self::HISTORY_LIMIT, null, true), false);

		$this->get_notifications()->post_consolidated(array(
			'consolidation_id'  => $id,
			'keep_id'           => $keep_id,
			'retire_id'         => $retire_id,
			'keep_title'        => $record['keep_title'],
			'retire_title'      => $record['retire_title'],
			'retire_url'        => $retire_url,
			'links_repointed'   => $links_changed,
			'redirect_provider' => $record['redirect_provider'],
			'content_mode'      => $mode,
		));

		/**
		 * Fires after two posts were consolidated.
		 *
		 * @param array $record Consolidation record.
		 */
		do_action('aips_posts_consolidated', $record);

		return $record;
	}

	/**
	 * Undo a consolidation: republish the retired post, remove the redirect,
	 * restore the re-pointed links and (for a rewrite) the kept post's content.
	 * Steps that can no longer be undone safely are skipped and reported.
	 *
	 * @param string $id Consolidation ID.
	 * @return array{warnings:string[]}|WP_Error
	 */
	public function undo(string $id) {
		$history = (array) $this->config->get_option(self::HISTORY_OPTION, array());
		if (!isset($history[$id]) || !empty($history[$id]['undone'])) {
			return new WP_Error('aips_consolidation_not_found', __('This consolidation can no longer be undone.', 'ai-post-scheduler'));
		}

		$record   = $history[$id];
		$keep_id  = (int) $record['keep_id'];
		$warnings = array();

		// Kept post: restoring the pre-merge backup also restores its own
		// links (they were changed after the merge), so its link fixes are
		// skipped when the backup is used.
		$restored_keep = false;
		if ($record['content_mode'] === self::CONTENT_REWRITE) {
			$backups = (array) get_post_meta($keep_id, self::BACKUP_META, true);
			if (!isset($backups[$id])) {
				$warnings[] = __('The kept post\'s original content was not found, so it was left as is.', 'ai-post-scheduler');
			} elseif (md5($this->stored_content($keep_id)) !== $record['merged_hash']) {
				$warnings[] = __('The kept post was edited after the merge, so its content was left as is. Use its revisions to restore the old version.', 'ai-post-scheduler');
			} else {
				wp_update_post(array('ID' => $keep_id, 'post_content' => wp_slash($backups[$id])));
				$restored_keep = true;
			}
			if (isset($backups[$id])) {
				unset($backups[$id]);
				update_post_meta($keep_id, self::BACKUP_META, wp_slash($backups));
			}
		} elseif ($record['content_mode'] === self::CONTENT_REVISION && !empty($record['revision_id'])) {
			$revision_id = (int) $record['revision_id'];
			if (wp_get_post_revision($revision_id)) {
				wp_delete_post_revision($revision_id);
			}
		}

		$link_conflicts = 0;
		foreach (array_reverse((array) $record['fixes']) as $fix) {
			if ($restored_keep && (int) $fix['source_id'] === $keep_id) {
				continue;
			}
			if (is_wp_error($this->link_fixer->undo((string) $fix['id']))) {
				$link_conflicts++;
			}
		}
		if ($link_conflicts > 0) {
			$warnings[] = sprintf(
				/* translators: %d: number of links */
				_n('%d link could not be restored because its post was edited afterwards; it still points to the kept post.', '%d links could not be restored because their posts were edited afterwards; they still point to the kept post.', $link_conflicts, 'ai-post-scheduler'),
				$link_conflicts
			);
		}

		if (is_wp_error($this->redirects->delete((int) $record['redirect_id']))) {
			$warnings[] = __('The redirect had already been removed.', 'ai-post-scheduler');
		}

		$retire = get_post((int) $record['retire_id']);
		if (!$retire) {
			$warnings[] = __('The retired post no longer exists.', 'ai-post-scheduler');
		} elseif ($retire->post_status !== 'draft') {
			$warnings[] = __('The retired post is no longer a draft, so its status was left as is.', 'ai-post-scheduler');
		} else {
			wp_update_post(array('ID' => (int) $record['retire_id'], 'post_status' => $record['previous_status']));
			delete_post_meta((int) $record['retire_id'], self::RETIRED_META);
		}

		$history[$id]['undone'] = true;
		$this->config->set_option(self::HISTORY_OPTION, $history, false);

		/**
		 * Fires after a consolidation was undone.
		 *
		 * @param array    $record   Consolidation record.
		 * @param string[] $warnings Steps that were skipped.
		 */
		do_action('aips_posts_consolidation_undone', $record, $warnings);

		return array('warnings' => $warnings);
	}

	/**
	 * Recent consolidations, newest first.
	 *
	 * @param int $limit Maximum records.
	 * @return array[]
	 */
	public function get_history(int $limit = 20): array {
		$out = array();

		foreach (array_reverse((array) $this->config->get_option(self::HISTORY_OPTION, array()), true) as $record) {
			$out[] = array(
				'id'              => (string) $record['id'],
				'keep_id'         => (int) $record['keep_id'],
				'keep_title'      => (string) $record['keep_title'],
				'keep_url'        => (string) get_permalink((int) $record['keep_id']),
				'retire_id'       => (int) $record['retire_id'],
				'retire_title'    => (string) $record['retire_title'],
				'retire_url'      => (string) $record['retire_url'],
				'retire_edit'     => (string) get_edit_post_link((int) $record['retire_id'], 'raw'),
				'content_mode'    => (string) $record['content_mode'],
				'revision_url'    => !empty($record['revision_id']) ? admin_url('revision.php?revision=' . (int) $record['revision_id']) : '',
				'links_repointed' => (int) $record['links_repointed'],
				'time'            => (int) $record['time'],
				'undone'          => !empty($record['undone']),
			);
			if (count($out) >= $limit) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Both posts exist, differ, are published and editable.
	 *
	 * @param int $keep_id   Post to keep.
	 * @param int $retire_id Post to retire.
	 * @return WP_Post[]|WP_Error [keep, retire].
	 */
	private function validate_pair(int $keep_id, int $retire_id) {
		$keep   = $keep_id ? get_post($keep_id) : null;
		$retire = $retire_id ? get_post($retire_id) : null;

		if (!$keep || !$retire || $keep_id === $retire_id) {
			return new WP_Error('aips_consolidation_invalid_pair', __('Choose two different posts.', 'ai-post-scheduler'));
		}
		if ($keep->post_status !== 'publish' || $retire->post_status !== 'publish') {
			return new WP_Error('aips_consolidation_not_published', __('Both posts must be published to consolidate them.', 'ai-post-scheduler'));
		}
		if (!current_user_can('edit_post', $keep_id) || !current_user_can('edit_post', $retire_id)) {
			return new WP_Error('aips_consolidation_forbidden', __('You cannot edit both posts.', 'ai-post-scheduler'));
		}

		return array($keep, $retire);
	}

	/**
	 * Post summary for the dialog.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	private function describe(WP_Post $post): array {
		return array(
			'id'    => (int) $post->ID,
			'title' => get_the_title($post),
			'url'   => (string) get_permalink($post),
			'date'  => get_the_date('', $post),
			'words' => str_word_count(wp_strip_all_tags((string) $post->post_content)),
		);
	}

	/**
	 * Internal links to a post from the link index, as source ID => URLs.
	 *
	 * @param int $post_id Target post.
	 * @return array<int, string[]>
	 */
	private function get_inbound_links(int $post_id): array {
		$out = array();

		foreach ($this->link_index->get_repository()->get_inbound($post_id) as $row) {
			$out[(int) $row->source_post_id][] = (string) $row->target_url;
		}

		foreach ($out as $source_id => $urls) {
			$out[$source_id] = array_values(array_unique($urls));
		}

		return $out;
	}

	/**
	 * Re-point every indexed link to the retired post. Links in the kept post
	 * itself are unlinked instead (they would become self-links).
	 *
	 * @param int $retire_id Retired post.
	 * @param int $keep_id   Kept post.
	 * @return array{0:array[], 1:int, 2:int} Fixes (id, source_id), links changed, failures.
	 */
	private function repoint_links(int $retire_id, int $keep_id): array {
		$fixes    = array();
		$changed  = 0;
		$failures = 0;

		foreach ($this->get_inbound_links($retire_id) as $source_id => $urls) {
			foreach ($urls as $url) {
				$result = $source_id === $keep_id
					? $this->link_fixer->unlink($source_id, $url)
					: $this->link_fixer->repoint($source_id, $url, $keep_id);

				if (is_wp_error($result)) {
					// A stale index row (link already gone) is not a failure.
					if ($result->get_error_code() !== 'aips_broken_not_found') {
						$failures++;
					}
					continue;
				}

				$fixes[]  = array('id' => $result['fix_id'], 'source_id' => $source_id);
				$changed  += (int) $result['changed'];
			}
		}

		return array($fixes, $changed, $failures);
	}

	/**
	 * Store merged content as a revision of the kept post, leaving the live
	 * post untouched. The editor restores it from the revisions screen.
	 *
	 * @param WP_Post $keep    Kept post.
	 * @param string  $content Merged HTML.
	 * @return int|WP_Error Revision ID.
	 */
	private function save_as_revision(WP_Post $keep, string $content) {
		if (!wp_revisions_enabled($keep)) {
			return new WP_Error('aips_consolidation_no_revisions', __('Revisions are turned off for this post, so the merged draft cannot be saved as one. Rewrite the post instead.', 'ai-post-scheduler'));
		}

		$revision_id = _wp_put_post_revision(array(
			'ID'           => $keep->ID,
			'post_title'   => $keep->post_title,
			'post_excerpt' => $keep->post_excerpt,
			'post_content' => $content, // _wp_put_post_revision() slashes the data itself.
		));

		if (is_wp_error($revision_id) || !$revision_id) {
			return new WP_Error('aips_consolidation_revision_failed', __('The merged draft could not be saved as a revision.', 'ai-post-scheduler'));
		}

		return (int) $revision_id;
	}

	/**
	 * Strip code fences, a leading title and anything unsafe from AI output.
	 *
	 * @param string $html Raw AI response.
	 * @return string
	 */
	private function clean_ai_html(string $html): string {
		$html = trim($html);
		$html = (string) preg_replace('/^```(?:html)?\s*|\s*```$/i', '', $html);
		$html = (string) preg_replace('/^\s*<h1\b[^>]*>.*?<\/h1>\s*/is', '', $html);
		$html = (string) preg_replace('/<(script|style|iframe)\b[^>]*>.*?<\/\1\s*>/is', '', $html);

		return trim(wp_kses_post($html));
	}

	/**
	 * Current post content straight from the database.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function stored_content(int $post_id): string {
		clean_post_cache($post_id);
		$post = get_post($post_id);

		return $post ? (string) $post->post_content : '';
	}

	/**
	 * @return AIPS_AI_Service
	 */
	private function get_ai_service() {
		if (!$this->ai_service) {
			$container        = AIPS_Container::get_instance();
			$this->ai_service = $container->has(AIPS_AI_Service::class) ? $container->make(AIPS_AI_Service::class) : new AIPS_AI_Service();
		}

		return $this->ai_service;
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
}
