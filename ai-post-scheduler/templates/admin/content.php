<?php
/**
 * Content Admin Template
 *
 * Container for the consolidated Content admin page. Local tabs render
 * generated posts, drafts/review, and partial generations; history tabs link
 * to the hidden legacy history route for backward compatibility.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var AIPS_Generated_Posts_Controller $controller */
$active_content_tab = 'generated_posts';
?>

<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Content', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('View and manage all AI-generated posts including published articles and drafts pending review.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>

		<!-- Tabs navigation -->
		<?php include AIPS_PLUGIN_DIR . 'templates/admin/content-tabs.php'; ?>

		<!-- Tab 1: Generated Posts -->
		<div id="aips-generated-posts-tab" class="aips-tab-content active" role="tabpanel" aria-hidden="false">
			<div class="aips-content-panel">
				<?php include AIPS_PLUGIN_DIR . 'templates/admin/tab-generated-posts.php'; ?>
			</div>
		</div>

		<!-- Tab 2: Partial Generations -->
		<div id="aips-partial-generations-tab" class="aips-tab-content" style="display:none;" role="tabpanel" aria-hidden="true">
			<div class="aips-content-panel">
				<?php include AIPS_PLUGIN_DIR . 'templates/admin/tab-partial-generations.php'; ?>
			</div>
		</div>

		<!-- Tab 3: Pending Review -->
		<div id="aips-pending-review-tab" class="aips-tab-content" style="display:none;" role="tabpanel" aria-hidden="true">
			<div class="aips-content-panel">
				<?php include AIPS_PLUGIN_DIR . 'templates/admin/tab-pending-review.php'; ?>
			</div>
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
