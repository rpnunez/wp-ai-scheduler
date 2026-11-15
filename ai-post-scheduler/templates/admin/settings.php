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

							<!-- General Tab -->
							<div id="settings-general-tab" class="aips-tab-content<?php echo 'settings-general' === $active_settings_tab ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo 'settings-general' === $active_settings_tab ? 'false' : 'true'; ?>" <?php echo 'settings-general' === $active_settings_tab ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e('Configure default settings for AI-generated posts.', 'ai-post-scheduler'); ?></p>
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('General Defaults', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_general_section'); ?>
									</table>
								</div>
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Post Content Images', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_post_images_section'); ?>
									</table>
								</div>
								<p class="submit">
									<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- AI Engine & Embeddings Tab -->
							<div id="settings-ai-tab" class="aips-tab-content<?php echo 'settings-ai' === $active_settings_tab ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo 'settings-ai' === $active_settings_tab ? 'false' : 'true'; ?>" <?php echo 'settings-ai' === $active_settings_tab ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e('Configure AI models, token budgets, vector embeddings, continuous sync, related posts, and semantic duplicate detection.', 'ai-post-scheduler'); ?></p>

								<!-- Card 1: AI Provider -->
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Content Generation AI Provider', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_ai_provider_section'); ?>
									</table>
								</div>

								<!-- Card 2: Tokens -->
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Token Budgets & Prompt Optimization', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_ai_tokens_section'); ?>
									</table>
								</div>

								<!-- Card 3: Embeddings Engine -->
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Vector Embeddings Engine & Model', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_ai_embeddings_section'); ?>
									</table>
								</div>

								<!-- Card 4: Indexing Scope & Sync -->
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Indexing Scope, Continuous Sync & Rate Limits', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_ai_scope_section'); ?>
									</table>
								</div>

								<!-- Card 5: Related Posts -->
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Frontend Related Posts Engine', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_ai_related_posts_section'); ?>
									</table>
								</div>

								<!-- Card 6: Semantic Deduplication -->
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Semantic Duplicate Detection & Gatekeeper Guard', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_ai_deduplication_section'); ?>
									</table>
								</div>

								<p class="submit">
									<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- AI Assistance Tab -->
							<div id="settings-ai-assistance-tab" class="aips-tab-content<?php echo 'settings-ai-assistance' === $active_settings_tab ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo 'settings-ai-assistance' === $active_settings_tab ? 'false' : 'true'; ?>" <?php echo 'settings-ai-assistance' === $active_settings_tab ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e('Set the site-wide policy for guided AI field suggestions.', 'ai-post-scheduler'); ?></p>
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('AI Assistance Policy', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_ai_assistance_section'); ?>
									</table>
								</div>
								<p class="submit">
									<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Internal Linking Tab -->
							<div id="settings-linking-tab" class="aips-tab-content<?php echo 'settings-linking' === $active_settings_tab ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo 'settings-linking' === $active_settings_tab ? 'false' : 'true'; ?>" <?php echo 'settings-linking' === $active_settings_tab ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e('Configure the link index behind the Link Report and orphan detection, and the guardrails for automatic internal linking.', 'ai-post-scheduler'); ?></p>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Link Index', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_link_index_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Keyword Link Rules', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_link_rules_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Link Click Tracking', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_link_clicks_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Topic Silos', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_silo_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Internal Link Automation', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_ai_autolink_section'); ?>
									</table>
								</div>

								<p class="submit">
									<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Feedback Tab -->
							<div id="settings-feedback-tab" class="aips-tab-content<?php echo 'settings-feedback' === $active_settings_tab ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo 'settings-feedback' === $active_settings_tab ? 'false' : 'true'; ?>" <?php echo 'settings-feedback' === $active_settings_tab ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e('Configure how the plugin evaluates and deduplicates generated topic suggestions.', 'ai-post-scheduler'); ?></p>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Global Author Topic Policy & Semantic Gate', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_authors_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Topic Similarity & Feedback Scoring', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_feedback_section'); ?>
									</table>
								</div>

								<p class="submit">
									<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Notifications Tab -->
							<div id="settings-notifications-tab" class="aips-tab-content<?php echo 'settings-notifications' === $active_settings_tab ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo 'settings-notifications' === $active_settings_tab ? 'false' : 'true'; ?>" <?php echo 'settings-notifications' === $active_settings_tab ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e('Configure the notification email address and delivery channels for all plugin notifications.', 'ai-post-scheduler'); ?></p>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Email Routing & Delivery', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_notifications_delivery_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('System Health & Resilience Alerts', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_notifications_system_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Content & Editorial Workflow Alerts', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_notifications_content_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Automation & Operational Maintenance', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_notifications_automation_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Summarized Notification Digest', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_notifications_summarized_digest_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Scheduled Activity Digests & Reports', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_notifications_digests_section'); ?>
									</table>
								</div>

								<p class="submit">
									<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Resilience & Limits Tab -->
							<div id="settings-resilience-tab" class="aips-tab-content<?php echo 'settings-resilience' === $active_settings_tab ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo 'settings-resilience' === $active_settings_tab ? 'false' : 'true'; ?>" <?php echo 'settings-resilience' === $active_settings_tab ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e('Configure advanced resilience options to protect the application from failing and being blocked when external services return errors.', 'ai-post-scheduler'); ?></p>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Retry & Backoff Policy', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_resilience_retry_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('API Rate Limiting', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_resilience_rate_limit_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Batch Pacing & Resume Cooldown', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_resilience_batch_section'); ?>
									</table>
								</div>

								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Circuit Breaker Protection', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_resilience_circuit_breaker_section'); ?>
									</table>
								</div>

								<p class="submit">
									<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Content Strategy Tab -->
							<div id="settings-content-strategy-tab" class="aips-tab-content<?php echo 'settings-content-strategy' === $active_settings_tab ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo 'settings-content-strategy' === $active_settings_tab ? 'false' : 'true'; ?>" <?php echo 'settings-content-strategy' === $active_settings_tab ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e('Define the overall content identity of your website. These settings are shared across Author Suggestions, topic generation, and post generation to ensure consistent, on-brand output.', 'ai-post-scheduler'); ?></p>
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Brand Persona & Strategy Guidelines', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_content_strategy_section'); ?>
									</table>
								</div>
								<p class="submit">
									<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Performance Tab -->
							<div id="settings-cache-tab" class="aips-tab-content<?php echo 'settings-cache' === $active_settings_tab ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo 'settings-cache' === $active_settings_tab ? 'false' : 'true'; ?>" <?php echo 'settings-cache' === $active_settings_tab ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e('Configure performance-related options for the plugin, including the internal cache layer used to speed up database reads, template processing, and scheduled operations.', 'ai-post-scheduler'); ?></p>
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Cache System & Storage Engine', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation" id="aips-cache-settings-table">
										<?php do_settings_fields('aips-settings', 'aips_cache_section'); ?>
									</table>
								</div>
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Database & Data Retention', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation" id="aips-database-retention-settings-table">
										<?php do_settings_fields('aips-settings', 'aips_database_retention_section'); ?>
									</table>
								</div>
								<p class="submit">
									<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- API Keys Tab -->
							<div id="settings-api-keys-tab" class="aips-tab-content<?php echo 'settings-api-keys' === $active_settings_tab ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo 'settings-api-keys' === $active_settings_tab ? 'false' : 'true'; ?>" <?php echo 'settings-api-keys' === $active_settings_tab ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e('Enter API keys for third-party services used by the plugin.', 'ai-post-scheduler'); ?></p>
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Third-Party Service Credentials', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_api_keys_section'); ?>
									</table>
								</div>
								<p class="submit">
									<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Developers Tab -->
							<div id="settings-developers-tab" class="aips-tab-content<?php echo 'settings-developers' === $active_settings_tab ? ' active' : ''; ?>" role="tabpanel" aria-hidden="<?php echo 'settings-developers' === $active_settings_tab ? 'false' : 'true'; ?>" <?php echo 'settings-developers' === $active_settings_tab ? '' : 'hidden'; ?>>
								<p class="description"><?php esc_html_e('Options for debugging and plugin development. Not recommended for production use.', 'ai-post-scheduler'); ?></p>
								<div class="aips-settings-section-card">
									<h3 class="aips-settings-card-title"><?php esc_html_e('Diagnostics & Debugging Tools', 'ai-post-scheduler'); ?></h3>
									<table class="form-table" role="presentation">
										<?php do_settings_fields('aips-settings', 'aips_developers_section'); ?>
									</table>
								</div>
								<p class="submit">
									<input type="submit" class="button button-primary aips-btn aips-btn-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

						</form>
					</div>
				</div>
			</main>
		</div>

	</div>
</div>

<?php include AIPS_PLUGIN_DIR . 'templates/partials/ai-assistance.php'; ?>
