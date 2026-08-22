<?php
/**
 * Content Admin Template
 *
 * Unified Content Hub for managing all AI-generated content (Published articles,
 * scheduled queue, pending review drafts, and incomplete/partial generations).
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var AIPS_Generated_Posts_Controller $controller */
?>

<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Content Hub', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Monitor, review, and manage all AI-generated posts across your scheduled queues, review pipelines, and published library.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>

		<!-- Main Unified Content View -->
		<div id="aips-generated-posts-container" class="aips-content-hub-wrap">
			<?php include AIPS_PLUGIN_DIR . 'templates/admin/tab-generated-posts.php'; ?>
		</div>
	</div>
</div>

<?php
// Include the Post Preview modal partial
include AIPS_PLUGIN_DIR . 'templates/partials/post-preview-modal.php';

// Include the View Session modal partial
include AIPS_PLUGIN_DIR . 'templates/partials/view-session-modal.php';

// Include the AI Edit modal partial
include AIPS_PLUGIN_DIR . 'templates/partials/ai-edit-modal.php';
?>
