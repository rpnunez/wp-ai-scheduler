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

		if ($this->audit_service->is_immutable((int) $post->ID)) {
			$states['aips_immutable'] = __('Immutable', 'ai-post-scheduler');
		}

		if (get_post_meta($post->ID, AIPS_Post_Audit_Service::META_HAS_PENDING, true) === '1') {
			$states['aips_pending_revision'] = __('Refresh Pending', 'ai-post-scheduler');
		}

		return $states;
	}
}
