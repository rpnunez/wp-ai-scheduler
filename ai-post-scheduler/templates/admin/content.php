<?php
/**
 * Content Admin Template
 *
 * Container for the Content admin page with three tab panels:
 *
 * Tab 1: Generated Posts  - @see templates/admin/tab-generated-posts.php
 * Tab 2: Partial Generations - @see templates/admin/tab-partial-generations.php
 * Tab 3: Pending Review      - @see templates/admin/tab-pending-review.php
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var AIPS_Generated_Posts_Controller $controller */
$active_content_tab = !empty($aips_hub_subtab) ? $aips_hub_subtab : (isset($initial_tab) ? $initial_tab : 'aips-generated-posts');
$generated_active   = ('aips-generated-posts' === $active_content_tab);
$partial_active     = ('aips-partial-generations' === $active_content_tab);
$review_active      = ('aips-pending-review' === $active_content_tab);
?>

		<!-- Tab 1: Generated Posts -->
		<div id="aips-generated-posts-tab" class="aips-tab-content<?php echo $generated_active ? ' active' : ''; ?>"<?php echo $generated_active ? '' : ' style="display:none;"'; ?> role="tabpanel" aria-hidden="<?php echo $generated_active ? 'false' : 'true'; ?>">
			<div class="aips-content-panel">
				<?php include AIPS_PLUGIN_DIR . 'templates/admin/tab-generated-posts.php'; ?>
			</div>
		</div>

		<!-- Tab 2: Partial Generations -->
		<div id="aips-partial-generations-tab" class="aips-tab-content<?php echo $partial_active ? ' active' : ''; ?>"<?php echo $partial_active ? '' : ' style="display:none;"'; ?> role="tabpanel" aria-hidden="<?php echo $partial_active ? 'false' : 'true'; ?>">
			<div class="aips-content-panel">
				<?php include AIPS_PLUGIN_DIR . 'templates/admin/tab-partial-generations.php'; ?>
			</div>
		</div>

		<!-- Tab 3: Pending Review -->
		<div id="aips-pending-review-tab" class="aips-tab-content<?php echo $review_active ? ' active' : ''; ?>"<?php echo $review_active ? '' : ' style="display:none;"'; ?> role="tabpanel" aria-hidden="<?php echo $review_active ? 'false' : 'true'; ?>">
			<div class="aips-content-panel">
				<?php include AIPS_PLUGIN_DIR . 'templates/admin/tab-pending-review.php'; ?>
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
