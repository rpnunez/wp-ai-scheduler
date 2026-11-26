<?php
/**
 * Settings Admin Template (Vertical Sidebar Rail Layout)
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$active_settings_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'settings-general';
if ('engine' === $active_settings_tab || 'settings-engine' === $active_settings_tab || 'ai' === $active_settings_tab) {
	$active_settings_tab = 'settings-ai';
} elseif ('linking' === $active_settings_tab || 'internal-linking' === $active_settings_tab) {
	$active_settings_tab = 'settings-linking';
} elseif ('general' === $active_settings_tab) {
	$active_settings_tab = 'settings-general';
} elseif ('feedback' === $active_settings_tab || 'topics' === $active_settings_tab) {
	$active_settings_tab = 'settings-feedback';
} elseif ('notifications' === $active_settings_tab) {
	$active_settings_tab = 'settings-notifications';
} elseif ('resilience' === $active_settings_tab || 'limits' === $active_settings_tab) {
	$active_settings_tab = 'settings-resilience';
} elseif ('content-strategy' === $active_settings_tab || 'strategy' === $active_settings_tab) {
	$active_settings_tab = 'settings-content-strategy';
} elseif ('cache' === $active_settings_tab || 'performance' === $active_settings_tab) {
	$active_settings_tab = 'settings-cache';
} elseif ('api-keys' === $active_settings_tab || 'api' === $active_settings_tab || 'keys' === $active_settings_tab) {
	$active_settings_tab = 'settings-api-keys';
} elseif ('developers' === $active_settings_tab || 'dev' === $active_settings_tab || 'debug' === $active_settings_tab) {
	$active_settings_tab = 'settings-developers';
}

$settings_rail_items = array();
foreach ( AIPS_Settings_Registry::instance()->get_tabs() as $tab_key => $tab_data ) {
	$settings_rail_items[] = array(
		'key'         => $tab_key,
		'label'       => $tab_data['label'],
		'icon'        => $tab_data['icon'],
		'description' => $tab_data['description'],
		'url'         => admin_url( 'admin.php?page=aips-settings&tab=' . $tab_key ),
		'active'      => ( $active_settings_tab === $tab_key ),
	);
}

$page_context = AIPS_Admin_Page_Context::resolve(
	'aips-settings',
	$active_settings_tab
);
?>
<div class="wrap aips-wrap aips-settings-wrap">
	<div class="aips-page-container">
		<?php AIPS_Admin_UI_Primitives::render_page_header($page_context); ?>

		<!-- Vertical Sidebar Rail Layout -->
		<div class="aips-rail-layout">
			<?php
			AIPS_Admin_UI_Primitives::render_rail(array(
				'id'         => 'aips-settings-tab-nav',
				'aria_label' => __('Settings Navigation', 'ai-post-scheduler'),
				'items'      => $settings_rail_items,
			));
			?>

			<!-- Main Stage Area -->
			<main class="aips-rail-main">
				<div class="aips-content-panel">
					<div class="aips-panel-body">
						<form method="post" action="options.php" id="aips-settings-form" data-aips-async="true">
							<input type="hidden" name="active_tab" id="aips_active_tab" value="<?php echo esc_attr($active_settings_tab); ?>">
							<?php settings_fields('aips_settings'); ?>

							<?php
							$registry = AIPS_Settings_Registry::instance();
							foreach ( $registry->get_tabs() as $tab_key => $tab_data ) :
								$is_active = ( $active_settings_tab === $tab_key );
								$sections  = $registry->get_sections_by_tab( $tab_key );
							?>
								<!-- <?php echo esc_html( $tab_data['label'] ); ?> Tab -->
								<div id="<?php echo esc_attr( $tab_key ); ?>-tab" class="aips-tab-content<?php echo $is_active ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>" <?php echo $is_active ? '' : 'hidden'; ?>>
									<?php if ( ! empty( $tab_data['description'] ) ) : ?>
										<p class="description"><?php echo esc_html( $tab_data['description'] ); ?></p>
									<?php endif; ?>

									<?php
									foreach ( $sections as $section_id => $section_data ) :
										$table_attr = '';
										if ( 'aips_cache_section' === $section_id ) {
											$table_attr = ' id="aips-cache-settings-table"';
										} elseif ( 'aips_database_retention_section' === $section_id ) {
											$table_attr = ' id="aips-database-retention-settings-table"';
										}
									?>
										<div class="aips-settings-section-card" id="<?php echo esc_attr( $section_id ); ?>_card">
											<h3 class="aips-settings-card-title"><?php echo esc_html( $section_data['title'] ); ?></h3>
											<table class="form-table" role="presentation"<?php echo $table_attr; ?>>
												<?php do_settings_fields( 'aips-settings', $section_id ); ?>
											</table>
										</div>
									<?php endforeach; ?>

									<p class="submit">
										<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e( 'Save Settings', 'ai-post-scheduler' ); ?>">
									</p>
								</div>
							<?php endforeach; ?>

						</form>
					</div>
				</div>
			</main>
		</div>

	</div>
</div>

<?php include AIPS_PLUGIN_DIR . 'templates/partials/ai-assistance.php'; ?>
