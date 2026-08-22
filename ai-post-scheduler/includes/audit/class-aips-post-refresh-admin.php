<?php
/**
 * Post Refresher Editor Integration
 *
 * Adds the "Content Refresher" meta box to the post editor so an admin can mark
 * a post Immutable (never auto-refreshed) and read the append-only audit log of
 * every refresher check performed on that post.
 *
 * Also keeps refresher staging drafts out of the main admin post list so they do
 * not look like ordinary drafts.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Refresh_Admin {

	/**
	 * Nonce action/name used by the meta box.
	 */
	const NONCE_ACTION = 'aips_post_refresh_meta_box';
	const NONCE_NAME   = 'aips_post_refresh_nonce';

	/**
	 * Query args and nonce action used by the review row actions.
	 */
	const ACTION_ARG   = 'aips_refresh_action';
	const REVISION_ARG = 'aips_revision_id';
	const NOTICE_ARG   = 'aips_refresh_notice';
	const ACTION_NONCE = 'aips_refresh_review_';

	/**
	 * @var AIPS_Post_Audit_Service
	 */
	private $audit_service;

	/**
	 * Constructor. Registers the editor hooks.
	 *
	 * @param AIPS_Post_Audit_Service|null $audit_service Optional audit service.
	 */
	public function __construct(?AIPS_Post_Audit_Service $audit_service = null) {
		if ($audit_service === null) {
			$container = AIPS_Container::get_instance();
			$audit_service = $container->has(AIPS_Post_Audit_Service::class)
				? $container->make(AIPS_Post_Audit_Service::class)
				: new AIPS_Post_Audit_Service();
		}

		$this->audit_service = $audit_service;

		add_action('add_meta_boxes', array($this, 'register_meta_box'));
		add_action('save_post', array($this, 'save_meta_box'), 10, 2);
		add_action('pre_get_posts', array($this, 'hide_staging_revisions_from_list'));
		add_filter('display_post_states', array($this, 'add_post_states'), 10, 2);

		// Review surface for staged revisions.
		add_filter('post_row_actions', array($this, 'add_revision_row_actions'), 10, 2);
		add_filter('page_row_actions', array($this, 'add_revision_row_actions'), 10, 2);
		add_action('admin_init', array($this, 'handle_revision_action'));
		add_action('admin_notices', array($this, 'render_action_notice'));

		// Release the live post when its staging draft is removed another way.
		add_action('before_delete_post', array($this, 'on_revision_removed'));
		add_action('wp_trash_post', array($this, 'on_revision_removed'));
	}

	/**
	 * Register the Content Refresher meta box on eligible post types.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		$post_types = $this->audit_service->get_config()['post_types'];

		foreach ($post_types as $post_type) {
			add_meta_box(
				'aips-post-refresher',
				__('AI Content Refresher', 'ai-post-scheduler'),
				array($this, 'render_meta_box'),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the meta box contents.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box($post): void {
		$immutable = $this->audit_service->is_immutable((int) $post->ID);
		$log       = array_reverse($this->audit_service->get_audit_log((int) $post->ID));
		$count     = (int) get_post_meta($post->ID, AIPS_Post_Audit_Service::META_AUDIT_COUNT, true);
		$last      = get_post_meta($post->ID, AIPS_Post_Audit_Service::META_LAST_AUDITED_AT, true);
		$refreshed = get_post_meta($post->ID, AIPS_Post_Audit_Service::META_LAST_REFRESHED_AT, true);

		wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
		?>
		<p>
			<label>
				<input type="checkbox" name="aips_refresh_immutable" value="1" <?php checked($immutable, true); ?>>
				<strong><?php esc_html_e('Immutable', 'ai-post-scheduler'); ?></strong>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e('When checked, the Autonomous Post Refresher will never propose or apply an update to this post.', 'ai-post-scheduler'); ?>
		</p>

		<hr>

		<p>
			<strong><?php esc_html_e('Refresher checks:', 'ai-post-scheduler'); ?></strong>
			<?php echo esc_html((string) $count); ?>
		</p>
		<?php if (!empty($last)) : ?>
			<p>
				<strong><?php esc_html_e('Last checked:', 'ai-post-scheduler'); ?></strong>
				<?php echo esc_html($last); ?>
			</p>
		<?php endif; ?>
		<?php if (!empty($refreshed)) : ?>
			<p>
				<strong><?php esc_html_e('Last refreshed:', 'ai-post-scheduler'); ?></strong>
				<?php echo esc_html($refreshed); ?>
			</p>
		<?php endif; ?>

		<?php if (empty($log)) : ?>
			<p class="description"><?php esc_html_e('No refresher activity recorded for this post yet.', 'ai-post-scheduler'); ?></p>
		<?php else : ?>
			<ul style="max-height:220px;overflow-y:auto;margin:0;">
				<?php foreach ($log as $entry) : ?>
					<li style="border-bottom:1px solid #e0e0e0;padding:6px 0;">
						<code><?php echo esc_html(isset($entry['result']) ? (string) $entry['result'] : ''); ?></code><br>
						<small><?php echo esc_html(isset($entry['checked_at']) ? (string) $entry['checked_at'] : ''); ?></small>
						<?php if (!empty($entry['message'])) : ?>
							<br><span><?php echo esc_html((string) $entry['message']); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
	}

	/**
	 * Persist the Immutable checkbox.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta_box($post_id, $post): void {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return;
		}

		// Only act when our meta box was actually rendered in the submitted form.
		if (!isset($_POST[self::NONCE_NAME])) {
			return;
		}

		$nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]));
		if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$immutable = isset($_POST['aips_refresh_immutable']) && $_POST['aips_refresh_immutable'] === '1';

		$this->audit_service->set_immutable((int) $post_id, $immutable);
	}

	/**
	 * Exclude refresher staging drafts from the main admin post list.
	 *
	 * Staging revisions are real draft posts, so without this they appear
	 * alongside genuine drafts and invite accidental publishing.
	 *
	 * @param WP_Query $query Current query.
	 * @return void
	 */
	public function hide_staging_revisions_from_list($query): void {
		if (!is_admin() || !$query->is_main_query()) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || $screen->base !== 'edit') {
			return;
		}

		/**
		 * Filter whether refresher staging drafts are hidden from the post list.
		 *
		 * @param bool     $hide  Whether to hide staging drafts.
		 * @param WP_Query $query Current query.
		 */
		if (!apply_filters('aips_hide_staging_revisions_in_admin_list', true, $query)) {
			return;
		}

		$meta_query = $query->get('meta_query');
		if (!is_array($meta_query)) {
			$meta_query = array();
		}

		$meta_query[] = array(
			'key'     => AIPS_Post_Audit_Service::META_IS_STAGING,
			'compare' => 'NOT EXISTS',
		);

		$query->set('meta_query', $meta_query);
	}

	/**
	 * Label protected and staged posts in the admin post list.
	 *
	 * @param string[] $states Existing post states.
	 * @param WP_Post  $post   Post being listed.
	 * @return string[]
	 */
	public function add_post_states($states, $post) {
		if (!is_array($states)) {
			$states = array();
		}

		if (get_post_meta($post->ID, AIPS_Post_Audit_Service::META_IS_STAGING, true) === '1') {
			$states['aips_staging_revision'] = __('AI Staging Revision', 'ai-post-scheduler');
		}

		// Label from the explicitly stored value, never the effective default:
		// with "Protect Posts By Default" on, is_immutable() is true for every
		// post and would badge the entire list.
		$stored_immutable = get_post_meta($post->ID, AIPS_Post_Audit_Service::META_IMMUTABLE, true);

		if ($stored_immutable === '1') {
			$states['aips_immutable'] = __('Immutable', 'ai-post-scheduler');
		} elseif ($stored_immutable === '0' && $this->audit_service->get_config()['default_immutable']) {
			// Only meaningful while the site default protects everything: this
			// marks the posts an admin has deliberately opted back in.
			$states['aips_refreshable'] = __('Refreshable', 'ai-post-scheduler');
		}

		if (get_post_meta($post->ID, AIPS_Post_Audit_Service::META_HAS_PENDING, true) === '1') {
			$states['aips_pending_revision'] = __('Refresh Pending', 'ai-post-scheduler');
		}

		return $states;
	}

	// -------------------------------------------------------------------------
	// Review surface
	// -------------------------------------------------------------------------

	/**
	 * Add Review / Approve / Discard row actions to a post with a staged revision.
	 *
	 * Staging drafts are hidden from the post list, so without these the staged
	 * content has no reachable review path in the admin at all.
	 *
	 * @param string[] $actions Existing row actions.
	 * @param WP_Post  $post    Post being listed.
	 * @return string[]
	 */
	public function add_revision_row_actions($actions, $post) {
		if (!is_array($actions)) {
			$actions = array();
		}

		if (get_post_meta($post->ID, AIPS_Post_Audit_Service::META_HAS_PENDING, true) !== '1') {
			return $actions;
		}

		$revision_id = (int) get_post_meta($post->ID, AIPS_Post_Audit_Service::META_PENDING_ID, true);

		if ($revision_id <= 0 || !get_post($revision_id)) {
			return $actions;
		}

		if (!current_user_can('edit_post', $post->ID)) {
			return $actions;
		}

		$review_url = get_edit_post_link($revision_id);

		if ($review_url) {
			$actions['aips_review_refresh'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url($review_url),
				esc_html__('Review AI Refresh', 'ai-post-scheduler')
			);
		}

		$actions['aips_approve_refresh'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url($this->build_action_url('approve', $revision_id)),
			esc_html__('Approve AI Refresh', 'ai-post-scheduler')
		);

		$actions['aips_discard_refresh'] = sprintf(
			'<a href="%s" class="submitdelete">%s</a>',
			esc_url($this->build_action_url('reject', $revision_id)),
			esc_html__('Discard AI Refresh', 'ai-post-scheduler')
		);

		return $actions;
	}

	/**
	 * Build a nonce-protected row-action URL that returns to the current list.
	 *
	 * @param string $action      Either 'approve' or 'reject'.
	 * @param int    $revision_id Staging revision ID.
	 * @return string
	 */
	private function build_action_url(string $action, int $revision_id): string {
		$base = remove_query_arg(array(self::ACTION_ARG, self::REVISION_ARG, '_wpnonce', self::NOTICE_ARG));

		$url = add_query_arg(
			array(
				self::ACTION_ARG   => $action,
				self::REVISION_ARG => $revision_id,
			),
			$base
		);

		return wp_nonce_url($url, self::ACTION_NONCE . $revision_id);
	}

	/**
	 * Handle an approve/discard row action.
	 *
	 * Capability is checked against the live target post, and the service
	 * re-validates that the ID really is a staging revision.
	 *
	 * @return void
	 */
	public function handle_revision_action(): void {
		if (!isset($_GET[self::ACTION_ARG], $_GET[self::REVISION_ARG])) {
			return;
		}

		$action = sanitize_key(wp_unslash($_GET[self::ACTION_ARG]));

		if (!in_array($action, array('approve', 'reject'), true)) {
			return;
		}

		$revision_id = absint(wp_unslash($_GET[self::REVISION_ARG]));

		if ($revision_id <= 0) {
			return;
		}

		check_admin_referer(self::ACTION_NONCE . $revision_id);

		$target_id = (int) get_post_meta($revision_id, AIPS_Post_Audit_Service::META_TARGET_POST_ID, true);

		if ($target_id <= 0 || !current_user_can('edit_post', $target_id)) {
			wp_die(esc_html__('You are not allowed to review this refresh.', 'ai-post-scheduler'), '', array('response' => 403));
		}

		$result = ($action === 'approve')
			? $this->audit_service->approve_revision($revision_id)
			: $this->audit_service->reject_revision($revision_id);

		if (is_wp_error($result)) {
			$notice = 'error';
		} else {
			$notice = ($action === 'approve') ? 'approved' : 'discarded';
		}

		$redirect = add_query_arg(
			self::NOTICE_ARG,
			$notice,
			remove_query_arg(array(self::ACTION_ARG, self::REVISION_ARG, '_wpnonce'))
		);

		wp_safe_redirect($redirect);
		exit;
	}

	/**
	 * Render the result notice after a row action.
	 *
	 * @return void
	 */
	public function render_action_notice(): void {
		if (!isset($_GET[self::NOTICE_ARG])) {
			return;
		}

		$notice = sanitize_key(wp_unslash($_GET[self::NOTICE_ARG]));

		$messages = array(
			'approved'  => array('success', __('AI refresh applied to the live post.', 'ai-post-scheduler')),
			'discarded' => array('success', __('AI refresh discarded.', 'ai-post-scheduler')),
			'error'     => array('error', __('That AI refresh could not be applied. It may have already been reviewed.', 'ai-post-scheduler')),
		);

		if (!isset($messages[$notice])) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr($messages[$notice][0]),
			esc_html($messages[$notice][1])
		);
	}

	/**
	 * Release a live post when its staging draft is trashed or deleted.
	 *
	 * @param int $post_id Post being removed.
	 * @return void
	 */
	public function on_revision_removed($post_id): void {
		$this->audit_service->release_target_of_revision((int) $post_id);
	}

}
