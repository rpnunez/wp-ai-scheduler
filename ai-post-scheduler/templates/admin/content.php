<?php
/**
 * Content Admin Template
 *
 * Container for the Content admin page with four tab panels:
 *
 * Tab 1: Generated Posts     - @see templates/admin/tab-generated-posts.php
 * Tab 2: Partial Generations - @see templates/admin/tab-partial-generations.php
 * Tab 3: Pending Review      - @see templates/admin/tab-pending-review.php
 * Tab 4: Content Indexer     - @see templates/admin/content-indexer.php
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var AIPS_Generated_Posts_Controller $controller */

$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'aips-generated-posts';
$valid_tabs = array('aips-generated-posts', 'aips-partial-generations', 'aips-pending-review', 'aips-content-indexer');
if ('content-indexer' === $active_tab || 'indexer' === $active_tab) {
	$active_tab = 'aips-content-indexer';
} elseif ('partial-generations' === $active_tab || 'partial' === $active_tab) {
	$active_tab = 'aips-partial-generations';
} elseif ('pending-review' === $active_tab || 'pending' === $active_tab) {
	$active_tab = 'aips-pending-review';
} elseif (!in_array($active_tab, $valid_tabs, true)) {
	$active_tab = 'aips-generated-posts';
}

$rail_items = array(
	array(
		'key'         => 'aips-generated-posts',
		'label'       => __('Generated Posts', 'ai-post-scheduler'),
		'icon'        => 'dashicons-admin-post',
		'description' => __('Published & drafted articles', 'ai-post-scheduler'),
		'active'      => ($active_tab === 'aips-generated-posts'),
	),
	array(
		'key'         => 'aips-partial-generations',
		'label'       => __('Partial Generations', 'ai-post-scheduler'),
		'icon'        => 'dashicons-warning',
		'description' => __('Incomplete runs & recovery', 'ai-post-scheduler'),
		'active'      => ($active_tab === 'aips-partial-generations'),
	),
	array(
		'key'         => 'aips-pending-review',
		'label'       => __('Pending Review', 'ai-post-scheduler'),
		'icon'        => 'dashicons-visibility',
		'description' => __('Drafts awaiting human review', 'ai-post-scheduler'),
		'active'      => ($active_tab === 'aips-pending-review'),
	),
	array(
		'key'         => 'aips-content-indexer',
		'label'       => __('Content Indexer', 'ai-post-scheduler'),
		'icon'        => 'dashicons-database',
		'description' => __('Vectors & semantic embeddings', 'ai-post-scheduler'),
		'active'      => ($active_tab === 'aips-content-indexer'),
	),
);
$summary_items = array();
if ('aips-generated-posts' === $active_tab && isset($history['total'])) {
	$summary_items[] = array('label' => __('Generated Posts', 'ai-post-scheduler'), 'value' => (int) $history['total'], 'type' => 'neutral', 'icon' => 'dashicons-admin-post');
} elseif ('aips-partial-generations' === $active_tab && isset($partial_generations['total'])) {
	$p_cnt = (int) $partial_generations['total'];
	$summary_items[] = array('label' => __('Incomplete Runs', 'ai-post-scheduler'), 'value' => $p_cnt, 'type' => $p_cnt > 0 ? 'warning' : 'success', 'icon' => 'dashicons-warning');
} elseif ('aips-pending-review' === $active_tab && isset($draft_posts['total'])) {
	$d_cnt = (int) $draft_posts['total'];
	$summary_items[] = array('label' => __('Pending Approval', 'ai-post-scheduler'), 'value' => $d_cnt, 'type' => $d_cnt > 0 ? 'warning' : 'neutral', 'icon' => 'dashicons-visibility');
}

$page_context = AIPS_Admin_Page_Context::resolve(
	'aips-generated-posts',
	$active_tab,
	null,
	array('summary_items' => $summary_items)
);
?>

<div class="wrap aips-wrap aips-content-wrap">
	<div class="aips-page-container">
		<!-- Page Header -->
		<?php AIPS_Admin_UI_Primitives::render_page_header($page_context); ?>

		<!-- Vertical Sidebar Rail Layout -->
		<div class="aips-rail-layout">
			<?php
			AIPS_Admin_UI_Primitives::render_rail(array(
				'aria_label' => __('Content Navigation', 'ai-post-scheduler'),
				'items'      => $rail_items,
			));
			?>

			<main class="aips-rail-main">
				<!-- Tab 1: Generated Posts -->
				<div id="aips-generated-posts-tab" class="aips-tab-content<?php echo $active_tab === 'aips-generated-posts' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'aips-generated-posts' ? '' : 'display:none;'; ?>" role="tabpanel" aria-hidden="<?php echo $active_tab === 'aips-generated-posts' ? 'false' : 'true'; ?>">
					<div class="aips-content-panel">
						<?php include AIPS_PLUGIN_DIR . 'templates/admin/tab-generated-posts.php'; ?>
					</div>
				</div>

				<!-- Tab 2: Partial Generations -->
				<div id="aips-partial-generations-tab" class="aips-tab-content<?php echo $active_tab === 'aips-partial-generations' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'aips-partial-generations' ? '' : 'display:none;'; ?>" role="tabpanel" aria-hidden="<?php echo $active_tab === 'aips-partial-generations' ? 'false' : 'true'; ?>">
					<div class="aips-content-panel">
						<?php include AIPS_PLUGIN_DIR . 'templates/admin/tab-partial-generations.php'; ?>
					</div>
				</div>

				<!-- Tab 3: Pending Review -->
				<div id="aips-pending-review-tab" class="aips-tab-content<?php echo $active_tab === 'aips-pending-review' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'aips-pending-review' ? '' : 'display:none;'; ?>" role="tabpanel" aria-hidden="<?php echo $active_tab === 'aips-pending-review' ? 'false' : 'true'; ?>">
					<div class="aips-content-panel">
						<?php include AIPS_PLUGIN_DIR . 'templates/admin/tab-pending-review.php'; ?>
					</div>
				</div>

				<!-- Tab 4: Content Indexer -->
				<div id="aips-content-indexer-tab" class="aips-tab-content<?php echo $active_tab === 'aips-content-indexer' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'aips-content-indexer' ? '' : 'display:none;'; ?>" role="tabpanel" aria-hidden="<?php echo $active_tab === 'aips-content-indexer' ? 'false' : 'true'; ?>">
					<?php
					$indexer_controller = new AIPS_Content_Indexer_Controller();
					$indexer_controller->render_page();
					?>
				</div>
			</main>
		</div><!-- /.aips-rail-layout -->
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
