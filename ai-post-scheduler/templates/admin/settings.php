<?php
/**
 * Settings Admin Template
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$settings_rail_items = array(
	array(
		'key'         => 'settings-general',
		'label'       => __('General', 'ai-post-scheduler'),
		'icon'        => 'dashicons-admin-generic',
		'description' => __('Defaults & post settings', 'ai-post-scheduler'),
		'active'      => true,
	),
	array(
		'key'         => 'settings-ai',
		'label'       => __('AI Engine', 'ai-post-scheduler'),
		'icon'        => 'dashicons-rest-api',
		'description' => __('Models & AI connection', 'ai-post-scheduler'),
	),
	array(
		'key'         => 'settings-feedback',
		'label'       => __('Feedback', 'ai-post-scheduler'),
		'icon'        => 'dashicons-thumbs-up',
		'description' => __('Deduplication & scoring', 'ai-post-scheduler'),
	),
	array(
		'key'         => 'settings-notifications',
		'label'       => __('Notifications', 'ai-post-scheduler'),
		'icon'        => 'dashicons-email-alt',
		'description' => __('Email & alert channels', 'ai-post-scheduler'),
	),
	array(
		'key'         => 'settings-resilience',
		'label'       => __('Resilience & Limits', 'ai-post-scheduler'),
		'icon'        => 'dashicons-shield',
		'description' => __('Failover & circuit breaker', 'ai-post-scheduler'),
	),
	array(
		'key'         => 'settings-content-strategy',
		'label'       => __('Content Strategy', 'ai-post-scheduler'),
		'icon'        => 'dashicons-art',
		'description' => __('Brand voice & persona', 'ai-post-scheduler'),
	),
	array(
		'key'         => 'settings-cache',
		'label'       => __('Performance', 'ai-post-scheduler'),
		'icon'        => 'dashicons-performance',
		'description' => __('Caching layer & driver', 'ai-post-scheduler'),
	),
	array(
		'key'         => 'settings-api-keys',
		'label'       => __('API Keys', 'ai-post-scheduler'),
		'icon'        => 'dashicons-admin-network',
		'description' => __('Third-party credentials', 'ai-post-scheduler'),
	),
	array(
		'key'         => 'settings-developers',
		'label'       => __('Developers', 'ai-post-scheduler'),
		'icon'        => 'dashicons-editor-code',
		'description' => __('Debug & dev tools', 'ai-post-scheduler'),
	),
);
$active_settings_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'settings-general';
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
							<?php settings_fields('aips_settings'); ?>

							<!-- General Tab -->
							<div id="settings-general-tab" class="aips-tab-content active">
								<p class="description"><?php esc_html_e('Configure default settings for AI-generated posts.', 'ai-post-scheduler'); ?></p>
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_general_section'); ?>
								</table>
								<p class="submit">
									<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- AI Tab -->
							<div id="settings-ai-tab" class="aips-tab-content" style="display:none;">
								<p class="description"><?php esc_html_e('Configure the AI Engine model and environment used for content generation.', 'ai-post-scheduler'); ?></p>
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_ai_section'); ?>
								</table>
								<p class="submit">
									<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Feedback Tab -->
							<div id="settings-feedback-tab" class="aips-tab-content" style="display:none;">
								<p class="description"><?php esc_html_e('Configure how the plugin evaluates and deduplicates generated topic suggestions.', 'ai-post-scheduler'); ?></p>
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_feedback_section'); ?>
								</table>
								<p class="submit">
									<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Notifications Tab -->
							<div id="settings-notifications-tab" class="aips-tab-content" style="display:none;">
								<p class="description"><?php esc_html_e('Configure the notification email address and delivery channels for all plugin notifications.', 'ai-post-scheduler'); ?></p>
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_notifications_section'); ?>
								</table>
								<p class="submit">
									<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Resilience & Limits Tab -->
							<div id="settings-resilience-tab" class="aips-tab-content" style="display:none;">
								<p class="description"><?php esc_html_e('Configure advanced resilience options to protect the application from failing and being blocked when external services return errors.', 'ai-post-scheduler'); ?></p>
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_resilience_section'); ?>
								</table>
								<p class="submit">
									<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Content Strategy Tab -->
							<div id="settings-content-strategy-tab" class="aips-tab-content" style="display:none;">
								<p class="description"><?php esc_html_e('Define the overall content identity of your website. These settings are shared across Author Suggestions, topic generation, and post generation to ensure consistent, on-brand output.', 'ai-post-scheduler'); ?></p>
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_content_strategy_section'); ?>
								</table>
								<p class="submit">
									<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Performance Tab -->
							<div id="settings-cache-tab" class="aips-tab-content" style="display:none;">
								<p class="description"><?php esc_html_e('Configure performance-related options for the plugin, including the internal cache layer used to speed up database reads, template processing, and scheduled operations.', 'ai-post-scheduler'); ?></p>

								<h3><?php esc_html_e('Cache System', 'ai-post-scheduler'); ?></h3>
								<table class="form-table" role="presentation" id="aips-cache-settings-table">
									<?php do_settings_fields('aips-settings', 'aips_cache_section'); ?>
								</table>
								<p class="submit">
									<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- API Keys Tab -->
							<div id="settings-api-keys-tab" class="aips-tab-content" style="display:none;">
								<p class="description"><?php esc_html_e('Enter API keys for third-party services used by the plugin.', 'ai-post-scheduler'); ?></p>
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_api_keys_section'); ?>
								</table>
								<p class="submit">
									<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

							<!-- Developers Tab -->
							<div id="settings-developers-tab" class="aips-tab-content" style="display:none;">
								<p class="description"><?php esc_html_e('Options for debugging and plugin development. Not recommended for production use.', 'ai-post-scheduler'); ?></p>
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_developers_section'); ?>
								</table>
								<p class="submit">
									<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
								</p>
							</div>

						</form>
					</div>
				</div>
			</main>
		</div>

	</div>
</div>
