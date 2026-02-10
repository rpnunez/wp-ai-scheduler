<?php
/**
 * Generated Posts React Admin Page Controller
 *
 * Handles the React-based "Generated Posts (React)" admin page.
 * This is a proof-of-concept parallel implementation to demonstrate React + WordPress integration.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generated_Posts_React
 *
 * Manages the React-powered Generated Posts admin page.
 */
class AIPS_Generated_Posts_React {
	
	/**
	 * Initialize the controller
	 */
	public function __construct() {
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}
	
	/**
	 * Render the admin page (shell for React app)
	 */
	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Generated Posts (React)', 'ai-post-scheduler'); ?></h1>
			<div id="aips-generated-posts-root"></div>
		</div>
		<?php
	}
	
	/**
	 * Enqueue React app and dependencies
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_assets($hook) {
		// Only load on our page
		if ($hook !== 'ai-post-scheduler_page_aips-generated-posts-react') {
			return;
		}
		
		// Enqueue WordPress components styles
		wp_enqueue_style('wp-components');
		
		// Enqueue the React app
		$asset_file = AIPS_PLUGIN_DIR . 'build/generated-posts.asset.php';
		
		if (!file_exists($asset_file)) {
			// Show error if build file doesn't exist
			add_action('admin_notices', function() {
				?>
				<div class="notice notice-error">
					<p>
						<?php esc_html_e('React app not built. Please run: npm install && npm run build', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<?php
			});
			return;
		}
		
		$asset = include $asset_file;
		
		wp_enqueue_script(
			'aips-generated-posts-react',
			AIPS_PLUGIN_URL . 'build/generated-posts.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		
		wp_enqueue_style(
			'aips-generated-posts-react-style',
			AIPS_PLUGIN_URL . 'build/style-generated-posts.css',
			array('wp-components'),
			$asset['version']
		);
		
		// Pass data to React app
		wp_localize_script('aips-generated-posts-react', 'aipsGeneratedPostsReact', array(
			'restUrl' => rest_url('aips/v1'),
			'nonce' => wp_create_nonce('wp_rest'),
			'adminUrl' => admin_url(),
			'siteUrl' => home_url(),
		));
	}
}
