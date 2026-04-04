<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<!-- Template Wizard Modal -->
<div id="aips-template-modal" class="aips-modal aips-wizard-modal" style="display: none;" data-wizard-steps="3">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 id="aips-modal-title"><?php esc_html_e('Add New Template', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>

		<!-- Wizard Progress Indicator -->
		<div class="aips-wizard-progress">
			<div class="aips-wizard-step" data-step="1">
				<div class="aips-step-number">1</div>
				<div class="aips-step-label"><?php esc_html_e('Basic Info', 'ai-post-scheduler'); ?></div>
			</div>
			<div class="aips-wizard-step" data-step="2">
				<div class="aips-step-number">2</div>
				<div class="aips-step-label"><?php esc_html_e('Generation Settings', 'ai-post-scheduler'); ?></div>
			</div>
			<div class="aips-wizard-step" data-step="3">
				<div class="aips-step-number">3</div>
				<div class="aips-step-label"><?php esc_html_e('Review & Publish', 'ai-post-scheduler'); ?></div>
			</div>
		</div>

		<div class="aips-modal-body">
			<form id="aips-template-form">
				<input type="hidden" name="template_id" id="template_id" value="">

				<!-- Step 1: Basic Info + Content Prompt -->
				<div class="aips-wizard-step-content" data-step="1">
					<h3>
						<?php esc_html_e('Basic Information & Content', 'ai-post-scheduler'); ?>
						<span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Give your template a unique name and define what content the AI should generate.', 'ai-post-scheduler'); ?>"></span>
					</h3>
					<p class="description"><?php esc_html_e('Define your template name and the content prompt that guides AI to generate your posts.', 'ai-post-scheduler'); ?></p>

					<div class="aips-form-row">
						<label for="template_name">
							<?php esc_html_e('Template Name', 'ai-post-scheduler'); ?> <span class="required">*</span>
							<span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Enter a unique, descriptive name for this template. This helps you identify it when creating schedules.', 'ai-post-scheduler'); ?>"></span>
						</label>
						<input type="text" id="template_name" name="name" required class="regular-text" placeholder="<?php esc_attr_e('e.g., Tech News Blog Post', 'ai-post-scheduler'); ?>">
						<p class="description"><?php esc_html_e('A descriptive name for your template', 'ai-post-scheduler'); ?></p>
					</div>

					<div class="aips-form-row">
						<label for="template_description">
							<?php esc_html_e('Template Description', 'ai-post-scheduler'); ?>
							<span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Optional notes about what this template is used for, target audience, or any special instructions.', 'ai-post-scheduler'); ?>"></span>
						</label>
						<textarea id="template_description" name="description" rows="3" class="large-text" placeholder="<?php esc_attr_e('Optional: Describe what this template is used for...', 'ai-post-scheduler'); ?>"></textarea>
						<p class="description"><?php esc_html_e('Optional. Helps you remember the purpose of this template.', 'ai-post-scheduler'); ?></p>
					</div>

					<div class="aips-form-row">
						<label for="prompt_template">
							<?php esc_html_e('Content Prompt', 'ai-post-scheduler'); ?> <span class="required">*</span>
							<span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Required. Detailed instructions for the AI about what content to generate. Be specific about topic, style, length, and target audience.', 'ai-post-scheduler'); ?>"></span>
						</label>
						<textarea id="prompt_template" name="prompt_template" rows="8" required class="large-text" placeholder="<?php esc_attr_e('Write a detailed blog post about...', 'ai-post-scheduler'); ?>"></textarea>
						<p class="description">
							<?php esc_html_e('Available variables: {{date}}, {{year}}, {{month}}, {{day}}, {{time}}, {{site_name}}, {{site_description}}, {{random_number}}', 'ai-post-scheduler'); ?>
						</p>
					</div>
				</div>

				<!-- Step 2: Generation Settings (Title & Excerpt, Voice, Quantity, Sources, Featured Image) -->
				<div class="aips-wizard-step-content" data-step="2" style="display: none;">
					<h3>
						<?php esc_html_e('Generation Settings', 'ai-post-scheduler'); ?>
						<span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Configure how AI generates titles, excerpts, and other post components.', 'ai-post-scheduler'); ?>"></span>
					</h3>
					<p class="description"><?php esc_html_e('Configure the AI generation settings for your posts, including title, excerpt, voice, and media options.', 'ai-post-scheduler'); ?></p>

					<div class="aips-form-row">
						<label for="title_prompt">
							<?php esc_html_e('Title Prompt', 'ai-post-scheduler'); ?>
							<span class="aips-help-tooltip dashicons dashicons-editor-help" data-tooltip="<?php esc_attr_e('Optional. Instruct the AI how to generate titles. Leave empty to auto-generate based on content. Supports AI variables like {{Framework1}}.', 'ai-post-scheduler'); ?>"></span>
						</label>
						<input type="text" id="title_prompt" name="title_prompt" class="regular-text aips-ai-var-input" placeholder="<?php esc_attr_e('Leave empty to auto-generate from content prompt', 'ai-post-scheduler'); ?>">
						<p class="description">
							<?php esc_html_e('Supports AI Variables: Use custom variables like {{PHPFramework1Name}} that AI will dynamically resolve based on your content. Example: "PHP Framework Comparison: {{Framework1}} vs. {{Framework2}}"', 'ai-post-scheduler'); ?>
						</p>
					</div>

					<!-- AI Variables Panel -->
					<div class="aips-form-row aips-ai-variables-panel" style="display: none;">
						<div class="aips-ai-variables-header">
							<span class="dashicons dashicons-admin-generic"></span>
							<strong><?php esc_html_e('AI Variables Detected', 'ai-post-scheduler'); ?></strong>
							<span class="aips-ai-variables-hint"><?php esc_html_e('(Click to copy)', 'ai-post-scheduler'); ?></span>
						</div>
						<div class="aips-ai-variables-list" id="aips-ai-variables-list">
							<!-- AI Variables will be rendered here by JavaScript -->
						</div>
						<div class="aips-ai-variables-info">
							<p class="description">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e('These variables will be dynamically resolved by AI based on your generated content. Each post generation may produce different values.', 'ai-post-scheduler'); ?>
							</p>
						</div>
					</div>

					<div class="aips-form-row aips-ai-variables-instructions">
						<details class="aips-collapsible">
							<summary>
								<span class="dashicons dashicons-editor-help"></span>
								<?php esc_html_e('How to use AI Variables', 'ai-post-scheduler'); ?>
							</summary>
							<div class="aips-collapsible-content">
								<p><?php esc_html_e('AI Variables allow you to create dynamic, context-aware titles. The AI will automatically fill in values based on the content it generates.', 'ai-post-scheduler'); ?></p>
								<h4><?php esc_html_e('Examples:', 'ai-post-scheduler'); ?></h4>
								<ul>
									<li><code>{{Framework1}} vs {{Framework2}}</code> → <em>"Laravel vs Symfony"</em></li>
									<li><code>Top {{Number}} {{Topic}} Tips</code> → <em>"Top 10 SEO Tips"</em></li>
									<li><code>{{ProductName}} Review: Is it worth it?</code> → <em>"iPhone 15 Pro Review: Is it worth it?"</em></li>
								</ul>
								<h4><?php esc_html_e('Tips:', 'ai-post-scheduler'); ?></h4>
								<ul>
									<li><?php esc_html_e('Use descriptive variable names (e.g., {{PHPFramework}} instead of {{X}})', 'ai-post-scheduler'); ?></li>
									<li><?php esc_html_e('AI Variables work best with comparison or list-based content prompts', 'ai-post-scheduler'); ?></li>
									<li><?php esc_html_e('System variables like {{date}}, {{site_name}} are NOT AI Variables', 'ai-post-scheduler'); ?></li>
								</ul>
							</div>
						</details>
					</div>

					<div class="aips-form-row">
						<label for="voice_id"><?php esc_html_e('Voice', 'ai-post-scheduler'); ?></label>
						<div class="aips-voice-selector">
							<input type="text" id="voice_search" class="regular-text" placeholder="<?php esc_attr_e('Search voices...', 'ai-post-scheduler'); ?>" style="margin-bottom: 8px;">
							<select id="voice_id" name="voice_id" class="regular-text">
								<option value="0"><?php esc_html_e('No Voice (Use Default)', 'ai-post-scheduler'); ?></option>
							</select>
							<p class="description"><?php esc_html_e('Optional. A voice provides pre-configured title and content instructions.', 'ai-post-scheduler'); ?></p>
						</div>
					</div>

					<div class="aips-form-row">
						<label for="post_quantity"><?php esc_html_e('Number of Posts to Generate', 'ai-post-scheduler'); ?></label>
						<input type="number" id="post_quantity" name="post_quantity" min="1" max="20" value="1" class="small-text">
						<p class="description"><?php esc_html_e('Generate 1-20 posts when running this template. Useful for batch generation.', 'ai-post-scheduler'); ?></p>
					</div>

					<?php
					$template_source_groups = get_terms(array(
						'taxonomy'   => 'aips_source_group',
						'hide_empty' => false,
					));
					if (is_wp_error($template_source_groups)) {
						$template_source_groups = array();
					}
					?>
					<div class="aips-form-row">
						<label class="aips-checkbox-label">
							<input type="checkbox" id="include_sources" name="include_sources" value="1">
							<?php esc_html_e('Include Sources?', 'ai-post-scheduler'); ?>
						</label>
						<p class="description"><?php esc_html_e('When enabled, active sources from the selected Source Groups will be injected into the content generation prompt.', 'ai-post-scheduler'); ?></p>
					</div>

					<div id="template-source-groups-selector" style="display:none; margin-top:8px;">
						<div class="aips-form-row">
							<label><?php esc_html_e('Source Groups', 'ai-post-scheduler'); ?></label>
							<?php if (!empty($template_source_groups)): ?>
								<div class="aips-checkbox-group">
									<?php foreach ($template_source_groups as $sg): ?>
										<label class="aips-checkbox-label" style="display:block; margin-bottom:4px;">
											<input type="checkbox"
												name="source_group_ids[]"
												class="aips-template-source-group-cb"
												value="<?php echo esc_attr($sg->term_id); ?>">
											<?php echo esc_html($sg->name); ?>
										</label>
									<?php endforeach; ?>
								</div>
								<p class="description"><?php esc_html_e('Select one or more Source Groups whose active sources will be included in the prompt.', 'ai-post-scheduler'); ?></p>
							<?php else: ?>
								<p class="description">
									<?php esc_html_e('No Source Groups found. Create groups on the', 'ai-post-scheduler'); ?>
									<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('aips-sources')); ?>" target="_blank"><?php esc_html_e('Trusted Sources page', 'ai-post-scheduler'); ?></a>.
								</p>
							<?php endif; ?>
						</div>
					</div>

					<div class="aips-form-row">
						<label class="aips-checkbox-label">
							<input type="checkbox" id="generate_featured_image" name="generate_featured_image" value="1">
							<?php esc_html_e('Generate Featured Image?', 'ai-post-scheduler'); ?>
						</label>
						<p class="description"><?php esc_html_e('If checked, a featured image will be attached to the generated post.', 'ai-post-scheduler'); ?></p>
					</div>

					<div class="aips-featured-image-settings" style="display: none;">
						<div class="aips-form-row">
							<label for="featured_image_source"><?php esc_html_e('Featured Image Source', 'ai-post-scheduler'); ?></label>
							<select id="featured_image_source" name="featured_image_source">
								<option value="ai_prompt"><?php esc_html_e('Generate with AI Prompt', 'ai-post-scheduler'); ?></option>
								<option value="unsplash"><?php esc_html_e('Unsplash (keywords)', 'ai-post-scheduler'); ?></option>
								<option value="media_library"><?php esc_html_e('Media Library (select images)', 'ai-post-scheduler'); ?></option>
							</select>
							<p class="description"><?php esc_html_e('Choose how the featured image should be sourced for this template.', 'ai-post-scheduler'); ?></p>
						</div>

						<div class="aips-form-row aips-image-source aips-image-source-ai">
							<label for="image_prompt"><?php esc_html_e('Image Prompt', 'ai-post-scheduler'); ?></label>
							<textarea id="image_prompt" name="image_prompt" rows="3" class="large-text" placeholder="<?php esc_attr_e('Describe the image you want generated...', 'ai-post-scheduler'); ?>"></textarea>
							<p class="description"><?php esc_html_e('Used when generating the image with AI.', 'ai-post-scheduler'); ?></p>
						</div>

						<div class="aips-form-row aips-image-source aips-image-source-unsplash" style="display: none;">
							<label for="featured_image_unsplash_keywords"><?php esc_html_e('Unsplash Keywords', 'ai-post-scheduler'); ?></label>
							<input type="text" id="featured_image_unsplash_keywords" name="featured_image_unsplash_keywords" class="regular-text" placeholder="<?php esc_attr_e('e.g. sunrise, mountains, drone view', 'ai-post-scheduler'); ?>">
							<p class="description"><?php esc_html_e('Unsplash will return a random image that matches these keywords.', 'ai-post-scheduler'); ?></p>
						</div>

						<div class="aips-form-row aips-image-source aips-image-source-media" style="display: none;">
							<label><?php esc_html_e('Media Library Images', 'ai-post-scheduler'); ?></label>
							<div class="aips-media-library-picker">
								<input type="hidden" id="featured_image_media_ids" name="featured_image_media_ids" value="">
								<button type="button" class="button" id="featured_image_media_select"><?php esc_html_e('Select Images', 'ai-post-scheduler'); ?></button>
								<button type="button" class="button-link" id="featured_image_media_clear"><?php esc_html_e('Clear Selection', 'ai-post-scheduler'); ?></button>
								<div id="featured_image_media_preview" class="description" style="margin-top: 6px;"><?php esc_html_e('No images selected.', 'ai-post-scheduler'); ?></div>
							</div>
							<p class="description"><?php esc_html_e('One image will be chosen at random from the selected media library items.', 'ai-post-scheduler'); ?></p>
						</div>
					</div>
				</div>


				<!-- Step 3: Review & Post Settings -->
				<div class="aips-wizard-step-content" data-step="3" style="display: none;">
					<h3><?php esc_html_e('Review & Post Settings', 'ai-post-scheduler'); ?></h3>
					<p class="description"><?php esc_html_e('Review your template configuration and set post publishing options.', 'ai-post-scheduler'); ?></p>

					<!-- Summary Display -->
					<div class="aips-template-summary">
						<h4><?php esc_html_e('Template Summary', 'ai-post-scheduler'); ?></h4>
						<div class="aips-summary-grid">
							<div class="aips-summary-item">
								<strong><?php esc_html_e('Template Name:', 'ai-post-scheduler'); ?></strong>
								<span id="summary_name">-</span>
							</div>
							<div class="aips-summary-item">
								<strong><?php esc_html_e('Description:', 'ai-post-scheduler'); ?></strong>
								<span id="summary_description">-</span>
							</div>
							<div class="aips-summary-item">
								<strong><?php esc_html_e('Title Prompt:', 'ai-post-scheduler'); ?></strong>
								<span id="summary_title_prompt"><?php esc_html_e('Auto-generate from content', 'ai-post-scheduler'); ?></span>
							</div>
							<div class="aips-summary-item">
								<strong><?php esc_html_e('Content Prompt:', 'ai-post-scheduler'); ?></strong>
								<span id="summary_content_prompt">-</span>
							</div>
							<div class="aips-summary-item">
								<strong><?php esc_html_e('Voice:', 'ai-post-scheduler'); ?></strong>
								<span id="summary_voice"><?php esc_html_e('None', 'ai-post-scheduler'); ?></span>
							</div>
							<div class="aips-summary-item">
								<strong><?php esc_html_e('Post Quantity:', 'ai-post-scheduler'); ?></strong>
								<span id="summary_quantity">1</span>
							</div>
							<div class="aips-summary-item">
								<strong><?php esc_html_e('Featured Image:', 'ai-post-scheduler'); ?></strong>
								<span id="summary_featured_image"><?php esc_html_e('No', 'ai-post-scheduler'); ?></span>
							</div>
						</div>
					</div>

					<!-- Post Settings -->
					<h4 style="margin-top: 20px;"><?php esc_html_e('Post Settings', 'ai-post-scheduler'); ?></h4>

					<div class="aips-form-columns">
						<div class="aips-form-row">
							<label for="post_status"><?php esc_html_e('Post Status', 'ai-post-scheduler'); ?></label>
							<select id="post_status" name="post_status">
								<option value="draft"><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
								<option value="pending"><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></option>
								<option value="publish"><?php esc_html_e('Published', 'ai-post-scheduler'); ?></option>
							</select>
						</div>

						<div class="aips-form-row">
							<label for="post_category"><?php esc_html_e('Category', 'ai-post-scheduler'); ?></label>
							<select id="post_category" name="post_category">
								<option value="0"><?php esc_html_e('Select Category', 'ai-post-scheduler'); ?></option>
								<?php foreach ($categories as $cat): ?>
								<option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="aips-form-row">
						<label for="post_tags"><?php esc_html_e('Tags', 'ai-post-scheduler'); ?></label>
						<input type="text" id="post_tags" name="post_tags" class="regular-text" placeholder="<?php esc_attr_e('tag1, tag2, tag3', 'ai-post-scheduler'); ?>">
						<p class="description"><?php esc_html_e('Comma-separated list of tags', 'ai-post-scheduler'); ?></p>
					</div>

					<div class="aips-form-row">
						<label for="post_author"><?php esc_html_e('Author', 'ai-post-scheduler'); ?></label>
						<select id="post_author" name="post_author">
							<?php foreach ($users as $user): ?>
							<option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user->ID, get_current_user_id()); ?>>
								<?php echo esc_html($user->display_name); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="aips-form-row">
						<label class="aips-checkbox-label">
							<input type="checkbox" id="is_active" name="is_active" value="1" checked>
							<?php esc_html_e('Template is active', 'ai-post-scheduler'); ?>
						</label>
					</div>
				</div>

				<!-- Step 6: Post-Save Next Steps (shown after successful save) -->
				<div class="aips-wizard-step-content aips-post-save-step" data-step="6" style="display: none;">
					<div style="text-align: center; padding: 30px 20px;">
						<span class="dashicons dashicons-yes-alt" style="font-size: 64px; color: #46b450; width: 64px; height: 64px;"></span>
						<h3 style="margin-top: 16px; font-size: 20px;" id="aips-save-success-title"><?php esc_html_e('Template Saved Successfully!', 'ai-post-scheduler'); ?></h3>
						<p class="description" style="font-size: 14px; margin-bottom: 24px;"><?php esc_html_e('Your template is ready. What would you like to do next?', 'ai-post-scheduler'); ?></p>

						<div class="aips-next-steps-grid" style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; max-width: 600px; margin: 0 auto;">
							<a href="#" id="aips-quick-schedule-btn" class="aips-btn aips-btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; font-size: 14px; text-decoration: none;">
								<span class="dashicons dashicons-calendar-alt"></span>
								<?php esc_html_e('Schedule This Template', 'ai-post-scheduler'); ?>
							</a>
							<button type="button" id="aips-quick-run-now-btn" class="aips-btn aips-btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; font-size: 14px;">
								<span class="dashicons dashicons-controls-play"></span>
								<?php esc_html_e('Run Now', 'ai-post-scheduler'); ?>
							</button>
							<button type="button" id="aips-post-save-done-btn" class="aips-btn aips-btn-ghost" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; font-size: 14px;">
								<span class="dashicons dashicons-dismiss"></span>
								<?php esc_html_e('Done', 'ai-post-scheduler'); ?>
							</button>
						</div>
					</div>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer aips-wizard-footer">
			<div class="aips-footer-left">
				<button type="button" class="button aips-wizard-back" style="display: none;">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
					<?php esc_html_e('Back', 'ai-post-scheduler'); ?>
				</button>
			</div>
			<div class="aips-footer-center">
				<button type="button" class="button aips-save-draft-template" title="<?php esc_attr_e('Save current progress as inactive template', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-cloud-saved"></span>
					<?php esc_html_e('Save Draft', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="button aips-test-template" title="<?php esc_attr_e('Generate a sample post using current settings', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-controls-play"></span>
					<?php esc_html_e('Test Generation', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="button aips-preview-prompts" title="<?php esc_attr_e('Preview the prompts that will be sent to AI', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e('Preview Prompts', 'ai-post-scheduler'); ?>
				</button>
			</div>
			<div class="aips-footer-right">
				<button type="button" class="button aips-modal-close">
					<?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="button button-primary aips-wizard-next">
					<?php esc_html_e('Next', 'ai-post-scheduler'); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
				<button type="button" class="button button-secondary aips-save-template aips-wizard-save-btn">
					<?php esc_html_e('Save Template', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>

		<!-- Preview Drawer -->
		<div class="aips-preview-drawer" id="aips-preview-drawer">
			<div class="aips-preview-drawer-toggle">
				<button type="button" class="aips-preview-drawer-handle" aria-label="<?php esc_attr_e('Toggle preview drawer', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-arrow-down-alt2"></span>
					<span class="aips-preview-drawer-label"><?php esc_html_e('Prompt Preview', 'ai-post-scheduler'); ?></span>
				</button>
			</div>
			<div class="aips-preview-drawer-content" style="display: none;">
				<div class="aips-preview-loading" style="display: none;">
					<span class="spinner is-active"></span>
					<span><?php esc_html_e('Generating preview...', 'ai-post-scheduler'); ?></span>
				</div>
				<div class="aips-preview-error" style="display: none;"></div>
				<div class="aips-preview-sections" style="display: none;">
					<div class="aips-preview-metadata">
						<div class="aips-preview-meta-item" id="aips-preview-voice" style="display: none;">
							<strong><?php esc_html_e('Voice:', 'ai-post-scheduler'); ?></strong>
							<span class="aips-preview-voice-name"></span>
						</div>
						<div class="aips-preview-meta-item" id="aips-preview-structure" style="display: none;">
							<strong><?php esc_html_e('Article Structure:', 'ai-post-scheduler'); ?></strong>
							<span class="aips-preview-structure-name"></span>
						</div>
						<div class="aips-preview-meta-item">
							<strong><?php esc_html_e('Sample Topic:', 'ai-post-scheduler'); ?></strong>
							<span class="aips-preview-sample-topic"></span>
						</div>
					</div>

					<div class="aips-preview-section">
						<h4><?php esc_html_e('Content Prompt', 'ai-post-scheduler'); ?></h4>
						<div class="aips-preview-prompt-text" id="aips-preview-content-prompt"></div>
					</div>

					<div class="aips-preview-section">
						<h4><?php esc_html_e('Title Prompt', 'ai-post-scheduler'); ?></h4>
						<div class="aips-preview-prompt-text" id="aips-preview-title-prompt"></div>
					</div>

					<div class="aips-preview-section">
						<h4><?php esc_html_e('Excerpt Prompt', 'ai-post-scheduler'); ?></h4>
						<div class="aips-preview-prompt-text" id="aips-preview-excerpt-prompt"></div>
					</div>

					<div class="aips-preview-section" id="aips-preview-image-section" style="display: none;">
						<h4><?php esc_html_e('Image Prompt', 'ai-post-scheduler'); ?></h4>
						<div class="aips-preview-prompt-text" id="aips-preview-image-prompt"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
