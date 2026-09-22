<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Settings', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Configure plugin settings, check system status, and manage AI Engine connection.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>

		<!-- Plugin Settings Form -->
		<div class="aips-content-panel">
			<div class="aips-panel-header">
				<h2><?php esc_html_e('Plugin Configuration', 'ai-post-scheduler'); ?></h2>
			</div>
			<div class="aips-panel-body">

				<!-- Tab Navigation -->
				<div class="aips-tab-nav" id="aips-settings-tab-nav">
					<button type="button" class="aips-tab-link active" data-tab="settings-general"><?php esc_html_e('General', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-ai"><?php esc_html_e('AI', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-authors"><?php esc_html_e('Authors', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-feedback"><?php esc_html_e('Feedback', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-notifications"><?php esc_html_e('Notifications', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-resilience"><?php esc_html_e('Resilience &amp; Limits', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-content-strategy"><?php esc_html_e('Content Strategy', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-cache"><?php esc_html_e('Performance', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-api-keys"><?php esc_html_e('API Keys', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-developers"><?php esc_html_e('Developers', 'ai-post-scheduler'); ?></button>
				</div>

				<form method="post" action="options.php" id="aips-settings-form">
					<?php settings_fields('aips_settings'); ?>

					<!-- General Tab -->
					<div id="settings-general-tab" class="aips-tab-content">
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
						<!-- Card 1: AI Provider -->
						<div class="aips-settings-section-card">
							<div class="aips-settings-section-header">
								<h3 class="aips-settings-section-title"><?php esc_html_e('Content Generation AI Provider', 'ai-post-scheduler'); ?></h3>
								<p class="description"><?php esc_html_e('Configure the primary AI provider and model used to generate post content and metadata.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-settings-section-body">
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_ai_provider_section'); ?>
								</table>
							</div>
						</div>

						<!-- Card 2: Token Budgets & Optimization -->
						<div class="aips-settings-section-card">
							<div class="aips-settings-section-header">
								<h3 class="aips-settings-section-title"><?php esc_html_e('Token Budgets & Prompt Optimization', 'ai-post-scheduler'); ?></h3>
								<p class="description"><?php esc_html_e('Tune token limits per content section and manage multi-turn conversational generation.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-settings-section-body">
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_ai_tokens_section'); ?>
								</table>
							</div>
						</div>

						<!-- Card 3: Vector Embeddings Engine & Model -->
						<div class="aips-settings-section-card">
							<div class="aips-settings-section-header">
								<h3 class="aips-settings-section-title"><?php esc_html_e('Vector Embeddings Engine & Model', 'ai-post-scheduler'); ?></h3>
								<p class="description"><?php esc_html_e('Configure the vector embedding provider, model, dimensions, and Meow environment used for semantic search, relationships, and indexing.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-settings-section-body">
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_ai_embeddings_section'); ?>
								</table>
							</div>
						</div>

						<!-- Card 4: Indexing Scope, Continuous Sync & Rate Limits -->
						<div class="aips-settings-section-card">
							<div class="aips-settings-section-header">
								<h3 class="aips-settings-section-title"><?php esc_html_e('Indexing Scope, Continuous Sync & Rate Limits', 'ai-post-scheduler'); ?></h3>
								<p class="description"><?php esc_html_e('Define which post types and publication ranges are vectorized, configure real-time sync, and enforce sliding-window API quotas.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-settings-section-body">
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_ai_scope_section'); ?>
								</table>
							</div>
						</div>

						<!-- Card 5: Frontend Related Posts Engine -->
						<div class="aips-settings-section-card">
							<div class="aips-settings-section-header">
								<h3 class="aips-settings-section-title"><?php esc_html_e('Frontend Related Posts Engine', 'ai-post-scheduler'); ?></h3>
								<p class="description"><?php esc_html_e('Configure automated semantic recommendations, layout styling, and post injection.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-settings-section-body">
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_ai_related_posts_section'); ?>
								</table>
							</div>
						</div>

						<!-- Card 6: Semantic Duplicate Detection & Gatekeeper Guard -->
						<div class="aips-settings-section-card">
							<div class="aips-settings-section-header">
								<h3 class="aips-settings-section-title"><?php esc_html_e('Semantic Duplicate Detection & Gatekeeper Guard', 'ai-post-scheduler'); ?></h3>
								<p class="description"><?php esc_html_e('Prevent cannibalization and duplicate topics during schedule execution and indexing.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-settings-section-body">
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_ai_deduplication_section'); ?>
								</table>
							</div>
						</div>

						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Authors Tab -->
					<div id="settings-authors-tab" class="aips-tab-content" style="display:none;">
						<p class="description"><?php esc_html_e('Configure default topic generation, auto-approval thresholds, and semantic duplicate filters for authors.', 'ai-post-scheduler'); ?></p>
						<div class="aips-settings-section-card">
							<div class="aips-settings-section-header">
								<h3 class="aips-settings-section-title"><?php esc_html_e('Global Author Topics Auto-Approval &amp; Semantic Gate', 'ai-post-scheduler'); ?></h3>
								<p class="description"><?php esc_html_e('Set the baseline semantic gate for new author topics. Authors set to "Inherit Global Policy" will use these thresholds.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-settings-section-body">
								<table class="form-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_authors_section'); ?>
								</table>
							</div>
						</div>
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

	</div>
</div>
