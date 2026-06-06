<?php
/**
 * Blueprints unified admin page template.
 *
 * Provides tabbed navigation across Article Structures, Structure Sections,
 * Voices, Post Slices, and Blueprint Presets.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// Determine active tab from query string (default: structures).
$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'structures';
$valid_tabs = array('structures', 'sections', 'voices', 'slices', 'presets');
if (!in_array($active_tab, $valid_tabs, true)) {
	$active_tab = 'structures';
}

$base_url = admin_url('admin.php?page=aips-blueprints');
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container aips-blueprints-page">
		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Blueprints', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description">
						<?php esc_html_e('Manage the building blocks that define how AI-generated posts are structured, styled, and varied.', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<div class="aips-page-actions" id="aips-blueprints-page-actions">
					<?php if ('structures' === $active_tab): ?>
						<button type="button" class="aips-btn aips-btn-primary aips-add-structure-btn">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e('Add New Structure', 'ai-post-scheduler'); ?>
						</button>
					<?php elseif ('sections' === $active_tab): ?>
						<button type="button" class="aips-btn aips-btn-primary aips-add-section-btn">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e('Add Section', 'ai-post-scheduler'); ?>
						</button>
					<?php elseif ('voices' === $active_tab): ?>
						<button type="button" class="aips-btn aips-btn-primary aips-add-voice-btn">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e('Add Voice', 'ai-post-scheduler'); ?>
						</button>
					<?php elseif ('slices' === $active_tab): ?>
						<button type="button" class="aips-btn aips-btn-primary" id="aips-add-post-slice-btn">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e('Add Post Slice', 'ai-post-scheduler'); ?>
						</button>
					<?php elseif ('presets' === $active_tab): ?>
						<button type="button" class="aips-btn aips-btn-primary" id="aips-add-blueprint-preset-btn">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e('Add Preset', 'ai-post-scheduler'); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Tab Navigation -->
		<nav class="nav-tab-wrapper aips-blueprints-tabs">
			<a href="<?php echo esc_url(add_query_arg('tab', 'structures', $base_url)); ?>"
			   class="nav-tab <?php echo 'structures' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<span class="dashicons dashicons-editor-table"></span>
				<?php esc_html_e('Structures', 'ai-post-scheduler'); ?>
			</a>
			<a href="<?php echo esc_url(add_query_arg('tab', 'sections', $base_url)); ?>"
			   class="nav-tab <?php echo 'sections' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<span class="dashicons dashicons-editor-kitchensink"></span>
				<?php esc_html_e('Sections', 'ai-post-scheduler'); ?>
			</a>
			<a href="<?php echo esc_url(add_query_arg('tab', 'voices', $base_url)); ?>"
			   class="nav-tab <?php echo 'voices' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<span class="dashicons dashicons-format-quote"></span>
				<?php esc_html_e('Voices', 'ai-post-scheduler'); ?>
			</a>
			<a href="<?php echo esc_url(add_query_arg('tab', 'slices', $base_url)); ?>"
			   class="nav-tab <?php echo 'slices' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<span class="dashicons dashicons-editor-paragraph"></span>
				<?php esc_html_e('Slices', 'ai-post-scheduler'); ?>
			</a>
			<a href="<?php echo esc_url(add_query_arg('tab', 'presets', $base_url)); ?>"
			   class="nav-tab <?php echo 'presets' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<span class="dashicons dashicons-layout"></span>
				<?php esc_html_e('Presets', 'ai-post-scheduler'); ?>
			</a>
		</nav>

		<!-- Tab Content -->
		<div class="aips-blueprints-tab-content">
			<?php
			switch ($active_tab) {
				case 'structures':
					include AIPS_PLUGIN_DIR . 'templates/admin/blueprints-index.php';
					break;
				case 'sections':
					include AIPS_PLUGIN_DIR . 'templates/admin/blueprints-tab-sections.php';
					break;
				case 'voices':
					// Re-use the existing voices template variables set by render_blueprints_page().
					include AIPS_PLUGIN_DIR . 'templates/admin/blueprints-tab-voices.php';
					break;
				case 'slices':
					include AIPS_PLUGIN_DIR . 'templates/admin/blueprints-tab-slices.php';
					break;
				case 'presets':
					include AIPS_PLUGIN_DIR . 'templates/admin/blueprints-tab-presets.php';
					break;
			}
			?>
		</div>
	</div>
</div>
