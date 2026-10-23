<?php
/**
 * Screen Meta Box Helper Class
 *
 * Provides standardized WordPress Core Meta Box integration (add_meta_box, do_meta_boxes,
 * and postbox.js), enabling collapsible, reorderable 2-column layouts with native user-meta
 * persistence and Screen Options visibility controls across AIPS editor and dashboard pages.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Screen_Meta_Box_Helper
 */
class AIPS_Screen_Meta_Box_Helper {

	/**
	 * Registered screens utilizing meta boxes.
	 *
	 * @var array<string, bool>
	 */
	private static $registered_screens = array();

	/**
	 * Initialize a screen for native meta box handling and enqueue required core scripts.
	 *
	 * @param string $screen_id Screen hook or identifier.
	 * @return void
	 */
	public static function init_screen($screen_id) {
		if (empty($screen_id)) {
			return;
		}

		self::$registered_screens[$screen_id] = true;

		// Enqueue WordPress core meta box dependencies.
		if (function_exists('wp_enqueue_script')) {
			wp_enqueue_script('postbox');
		}

		// Enable column layout screen option (1 or 2 columns) if available.
		if (function_exists('add_screen_option')) {
			add_screen_option('layout_columns', array(
				'max'     => 2,
				'default' => 2,
			));
		}

		// Inject postbox toggle initialization script into admin footer.
		if (function_exists('add_action')) {
			add_action('admin_footer', function() use ($screen_id) {
				?>
				<script type="text/javascript">
					jQuery(document).ready(function($) {
						if (typeof postboxes !== 'undefined' && typeof postboxes.add_postbox_toggles === 'function') {
							postboxes.add_postbox_toggles('<?php echo esc_js($screen_id); ?>');
						}
					});
				</script>
				<?php
			}, 20);
		}
	}

	/**
	 * Register a meta box on a specific screen.
	 *
	 * @param string               $id            HTML ID of the meta box.
	 * @param string               $title         Display title for the box header.
	 * @param callable             $callback      Render callback function.
	 * @param string               $screen_id     Screen ID where box appears.
	 * @param string               $context       Box context ('normal', 'advanced', or 'side').
	 * @param string               $priority      Box priority ('high', 'core', 'default', 'low').
	 * @param array<string, mixed> $callback_args Optional arguments passed to the callback.
	 * @return void
	 */
	public static function register_box($id, $title, $callback, $screen_id, $context = 'normal', $priority = 'default', $callback_args = null) {
		if (!function_exists('add_meta_box')) {
			return;
		}

		add_meta_box(
			$id,
			$title,
			$callback,
			$screen_id,
			$context,
			$priority,
			$callback_args
		);
	}

	/**
	 * Render the standard 2-column WordPress #poststuff layout for registered meta boxes.
	 *
	 * @param string $screen_id Screen identifier.
	 * @param mixed  $data      Optional data passed into meta box render callbacks.
	 * @param int    $columns   Number of columns (default 2).
	 * @return void
	 */
	public static function render_layout($screen_id, $data = null, $columns = 2) {
		if (!function_exists('do_meta_boxes')) {
			return;
		}

		// Nonces required by WordPress core for drag-and-drop ordering and collapse toggles.
		if (function_exists('wp_nonce_field')) {
			wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
			wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
		}

		if ((null === $columns || 2 === (int) $columns) && function_exists('get_user_option')) {
			$user_columns = get_user_option('screen_layout_' . $screen_id);
			if (!empty($user_columns)) {
				$columns = (int) $user_columns;
			}
		}

		$columns_class = 'columns-' . absint($columns);
		?>
		<div id="poststuff" class="aips-poststuff">
			<div id="post-body" class="metabox-holder <?php echo esc_attr($columns_class); ?>">
				<?php if (2 === (int) $columns) : ?>
					<div id="postbox-container-1" class="postbox-container aips-postbox-side">
						<?php do_meta_boxes($screen_id, 'side', $data); ?>
					</div>
				<?php endif; ?>

				<div id="postbox-container-2" class="postbox-container aips-postbox-main">
					<?php
					do_meta_boxes($screen_id, 'normal', $data);
					do_meta_boxes($screen_id, 'advanced', $data);
					if (1 === (int) $columns) {
						do_meta_boxes($screen_id, 'side', $data);
					}
					?>
				</div>
			</div>
			<br class="clear">
		</div>
		<?php
	}

	/**
	 * Helper to render an isolated meta box directly (without registering to screen).
	 *
	 * @param string               $id       Box HTML ID.
	 * @param string               $title    Header title.
	 * @param string|callable      $content  HTML string or render callback.
	 * @param array<string, mixed> $actions  Optional header action buttons.
	 * @param array<string, mixed> $badge    Optional status badge.
	 * @return void
	 */
	public static function render_isolated_box($id, $title, $content, $actions = array(), $badge = array()) {
		AIPS_Admin_UI_Primitives::render_card(array(
			'id'          => $id,
			'title'       => $title,
			'actions'     => $actions,
			'badge'       => $badge,
			'collapsible' => true,
			'body'        => is_string($content) ? $content : '',
		), is_callable($content) ? $content : null);
	}
}
