<?php
/**
 * Prompt Profiles Admin Page Template
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 *
 * @var array $profiles Array of prompt profile objects or arrays.
 * @var array $core_fallbacks Array of default fallback prompts.
 */

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
					<h1 class="aips-page-title"><?php esc_html_e('Prompt Profiles', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description">
						<?php esc_html_e('Customize prompt templates and instructions across all generation stages with dynamic contextual placeholders and live preview.', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<div class="aips-page-actions">
					<button type="button" class="aips-btn aips-btn-primary aips-add-profile-btn" id="aips-add-profile-btn">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e('Create Prompt Profile', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Main Content Panel -->
		<div class="aips-content-panel">
			<div class="aips-prompt-profiles-container">
				<?php if (!empty($profiles)) : ?>
					<!-- Filter Bar -->
					<div class="aips-filter-bar">
						<div class="aips-filter-left">
							<div class="aips-filter-group">
								<button type="button" class="aips-filter-btn is-active" data-filter="all"><?php esc_html_e('All Profiles', 'ai-post-scheduler'); ?></button>
								<button type="button" class="aips-filter-btn" data-filter="builtin"><?php esc_html_e('System Presets', 'ai-post-scheduler'); ?></button>
								<button type="button" class="aips-filter-btn" data-filter="custom"><?php esc_html_e('Custom', 'ai-post-scheduler'); ?></button>
							</div>
						</div>
						<div class="aips-filter-right">
							<label class="screen-reader-text" for="aips-profile-search"><?php esc_html_e('Search Profiles:', 'ai-post-scheduler'); ?></label>
							<input type="search" id="aips-profile-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search profiles...', 'ai-post-scheduler'); ?>">
							<button type="button" id="aips-profile-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
						</div>
					</div>

					<!-- Profiles Table -->
					<div class="aips-panel-body no-padding">
						<table class="aips-table aips-prompt-profiles-list" id="aips-prompt-profiles-table">
							<thead>
								<tr>
									<th class="column-name"><?php esc_html_e('Profile Name', 'ai-post-scheduler'); ?></th>
									<th class="column-description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
									<th class="column-type"><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
									<th class="column-default"><?php esc_html_e('Default', 'ai-post-scheduler'); ?></th>
									<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($profiles as $profile) : 
									$prof = (object) $profile;
									$is_builtin = !empty($prof->is_builtin);
									$is_default = !empty($prof->is_default);
								?>
									<tr data-profile-id="<?php echo esc_attr($prof->id); ?>" data-is-builtin="<?php echo $is_builtin ? '1' : '0'; ?>" data-is-default="<?php echo $is_default ? '1' : '0'; ?>">
										<td class="column-name">
											<div class="aips-table-primary">
												<strong><?php echo esc_html($prof->name); ?></strong>
												<?php if (!empty($prof->slug)) : ?>
													<span class="aips-table-subtext"><code><?php echo esc_html($prof->slug); ?></code></span>
												<?php endif; ?>
											</div>
										</td>
										<td class="column-description">
											<div class="aips-table-meta">
												<?php echo esc_html($prof->description ?: __('No description provided.', 'ai-post-scheduler')); ?>
											</div>
										</td>
										<td class="column-type">
											<?php if ($is_builtin) : ?>
												<span class="aips-badge aips-badge-info" title="<?php esc_attr_e('Pre-seeded archetype with specialized prompts.', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-admin-settings"></span>
													<?php esc_html_e('System Preset', 'ai-post-scheduler'); ?>
												</span>
											<?php else : ?>
												<span class="aips-badge aips-badge-neutral">
													<span class="dashicons dashicons-admin-generic"></span>
													<?php esc_html_e('Custom', 'ai-post-scheduler'); ?>
												</span>
											<?php endif; ?>
										</td>
										<td class="column-default">
											<?php if ($is_default) : ?>
												<span class="aips-badge aips-badge-success" title="<?php esc_attr_e('Default profile used whenever templates or authors do not specify one.', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-star-filled"></span>
													<?php esc_html_e('Global Default', 'ai-post-scheduler'); ?>
												</span>
											<?php else : ?>
												<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-set-default-btn" data-id="<?php echo esc_attr($prof->id); ?>" title="<?php esc_attr_e('Set as Global Default', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-star-empty"></span>
													<?php esc_html_e('Set Default', 'ai-post-scheduler'); ?>
												</button>
											<?php endif; ?>
										</td>
										<td class="column-actions">
											<div class="aips-action-buttons">
												<button type="button" class="aips-btn aips-btn-sm aips-edit-profile-btn" data-id="<?php echo esc_attr($prof->id); ?>" title="<?php esc_attr_e('Edit Stages & Prompts', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-edit"></span>
													<span class="screen-reader-text"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></span>
												</button>
												<button type="button" class="aips-btn aips-btn-sm aips-clone-profile-btn" data-id="<?php echo esc_attr($prof->id); ?>" title="<?php esc_attr_e('Clone Profile', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-admin-page"></span>
													<span class="screen-reader-text"><?php esc_html_e('Clone', 'ai-post-scheduler'); ?></span>
												</button>
												<?php if (!$is_builtin) : ?>
													<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-delete-profile-btn" data-id="<?php echo esc_attr($prof->id); ?>" title="<?php esc_attr_e('Delete Profile', 'ai-post-scheduler'); ?>">
														<span class="dashicons dashicons-trash"></span>
														<span class="screen-reader-text"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></span>
													</button>
												<?php endif; ?>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<!-- Table Footer Count -->
					<div class="tablenav">
						<span class="aips-table-footer-count">
							<?php printf(esc_html(_n('%d profile', '%d profiles', count($profiles), 'ai-post-scheduler')), count($profiles)); ?>
						</span>
					</div>

					<!-- No Search Results -->
					<div id="aips-profile-search-no-results" class="aips-empty-state" style="display: none;">
						<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Profiles Found', 'ai-post-scheduler'); ?></h3>
						<p class="aips-empty-state-description"><?php esc_html_e('No prompt profiles match your search criteria.', 'ai-post-scheduler'); ?></p>
						<div class="aips-empty-state-actions">
							<button type="button" class="aips-btn aips-btn-primary" id="aips-clear-search-btn">
								<span class="dashicons dashicons-dismiss"></span>
								<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
							</button>
						</div>
					</div>
				<?php else : ?>
					<!-- Empty State -->
					<div class="aips-empty-state">
						<div class="dashicons dashicons-editor-quote aips-empty-state-icon" aria-hidden="true"></div>
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Prompt Profiles Found', 'ai-post-scheduler'); ?></h3>
						<p class="aips-empty-state-description"><?php esc_html_e('Create your first prompt profile to customize how the AI writes titles, content, excerpts, and images.', 'ai-post-scheduler'); ?></p>
						<div class="aips-empty-state-actions">
							<button type="button" class="aips-btn aips-btn-primary aips-btn-lg aips-add-profile-btn">
								<span class="dashicons dashicons-plus-alt2"></span>
								<?php esc_html_e('Create Prompt Profile', 'ai-post-scheduler'); ?>
							</button>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Modal: Add / Edit Prompt Profile -->
	<div id="aips-prompt-profile-modal" class="aips-modal" style="display: none;" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="aips-prompt-profile-modal-title">
		<div class="aips-modal-content aips-modal-xl">
			<div class="aips-modal-header">
				<div class="aips-modal-header-info">
					<h2 id="aips-prompt-profile-modal-title"><?php esc_html_e('Edit Prompt Profile', 'ai-post-scheduler'); ?></h2>
					<span id="aips-modal-profile-badge" class="aips-badge aips-badge-neutral" style="display: none;"></span>
				</div>
				<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
			</div>

			<div class="aips-modal-body no-padding">
				<form id="aips-prompt-profile-form">
					<input type="hidden" name="id" id="aips_profile_id" value="">
					<input type="hidden" name="is_default" id="aips_profile_is_default" value="0">

					<!-- Top Profile Meta Section -->
					<div class="aips-modal-section aips-profile-meta-grid">
						<div class="aips-form-group">
							<label for="aips_profile_name" class="aips-form-label">
								<?php esc_html_e('Profile Name', 'ai-post-scheduler'); ?> <span class="required">*</span>
							</label>
							<input type="text" id="aips_profile_name" name="name" class="aips-form-input" required placeholder="<?php esc_attr_e('e.g. SEO Longform Specialist', 'ai-post-scheduler'); ?>">
						</div>
						<div class="aips-form-group">
							<label for="aips_profile_slug" class="aips-form-label">
								<?php esc_html_e('Slug', 'ai-post-scheduler'); ?>
							</label>
							<input type="text" id="aips_profile_slug" name="slug" class="aips-form-input" placeholder="<?php esc_attr_e('auto-generated-slug', 'ai-post-scheduler'); ?>">
						</div>
						<div class="aips-form-group full-width">
							<label for="aips_profile_description" class="aips-form-label">
								<?php esc_html_e('Description / Purpose', 'ai-post-scheduler'); ?>
							</label>
							<textarea id="aips_profile_description" name="description" rows="2" class="aips-form-textarea" placeholder="<?php esc_attr_e('Explain what tone or style this prompt profile creates...', 'ai-post-scheduler'); ?>"></textarea>
						</div>
					</div>

					<!-- Stage Editor Workspace -->
					<div class="aips-prompt-stage-workspace">
						<!-- Stage Tabs Sidebar -->
						<div class="aips-prompt-stage-nav">
							<div class="aips-stage-nav-title"><?php esc_html_e('Generation Stages', 'ai-post-scheduler'); ?></div>
							<ul class="aips-stage-tabs-list">
								<li class="is-active" data-stage="title_prompt">
									<span class="dashicons dashicons-heading"></span>
									<span class="aips-tab-label"><?php esc_html_e('Post Title', 'ai-post-scheduler'); ?></span>
									<span class="aips-stage-indicator" data-stage="title_prompt"></span>
								</li>
								<li data-stage="title_followup_prompt">
									<span class="dashicons dashicons-redo"></span>
									<span class="aips-tab-label"><?php esc_html_e('Follow-up Title', 'ai-post-scheduler'); ?></span>
									<span class="aips-stage-indicator" data-stage="title_followup_prompt"></span>
								</li>
								<li data-stage="content_prompt">
									<span class="dashicons dashicons-editor-alignleft"></span>
									<span class="aips-tab-label"><?php esc_html_e('Content Shell', 'ai-post-scheduler'); ?></span>
									<span class="aips-stage-indicator" data-stage="content_prompt"></span>
								</li>
								<li data-stage="excerpt_prompt">
									<span class="dashicons dashicons-editor-quote"></span>
									<span class="aips-tab-label"><?php esc_html_e('Post Excerpt', 'ai-post-scheduler'); ?></span>
									<span class="aips-stage-indicator" data-stage="excerpt_prompt"></span>
								</li>
								<li data-stage="excerpt_followup_prompt">
									<span class="dashicons dashicons-redo"></span>
									<span class="aips-tab-label"><?php esc_html_e('Follow-up Excerpt', 'ai-post-scheduler'); ?></span>
									<span class="aips-stage-indicator" data-stage="excerpt_followup_prompt"></span>
								</li>
								<li data-stage="featured_image_prompt">
									<span class="dashicons dashicons-format-image"></span>
									<span class="aips-tab-label"><?php esc_html_e('Featured Image', 'ai-post-scheduler'); ?></span>
									<span class="aips-stage-indicator" data-stage="featured_image_prompt"></span>
								</li>
								<li data-stage="topic_ideas_prompt">
									<span class="dashicons dashicons-lightbulb"></span>
									<span class="aips-tab-label"><?php esc_html_e('Author Topic Ideas', 'ai-post-scheduler'); ?></span>
									<span class="aips-stage-indicator" data-stage="topic_ideas_prompt"></span>
								</li>
								<li data-stage="metadata_prompt">
									<span class="dashicons dashicons-search"></span>
									<span class="aips-tab-label"><?php esc_html_e('SEO & Metadata', 'ai-post-scheduler'); ?></span>
									<span class="aips-stage-indicator" data-stage="metadata_prompt"></span>
								</li>
								<li data-stage="taxonomy_prompt">
									<span class="dashicons dashicons-tag"></span>
									<span class="aips-tab-label"><?php esc_html_e('Taxonomy Classification', 'ai-post-scheduler'); ?></span>
									<span class="aips-stage-indicator" data-stage="taxonomy_prompt"></span>
								</li>
							</ul>
						</div>

						<!-- Stage Content Pane -->
						<div class="aips-prompt-stage-content">
							<!-- Container for stage panels -->
							<div class="aips-stage-panels-wrapper">
								<!-- Stage: Post Title -->
								<div class="aips-stage-panel is-active" data-stage-panel="title_prompt">
									<div class="aips-stage-header">
										<div>
											<h3 class="aips-stage-heading"><?php esc_html_e('Post Title Prompt', 'ai-post-scheduler'); ?></h3>
											<p class="aips-stage-subtext"><?php esc_html_e('Generates the standalone blog post headline from the topic context and voice.', 'ai-post-scheduler'); ?></p>
										</div>
										<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-reset-stage-btn" data-stage="title_prompt">
											<span class="dashicons dashicons-undo"></span>
											<?php esc_html_e('Reset to Default', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div class="aips-stage-chips-bar">
										<span class="aips-chips-label"><?php esc_html_e('Insert tag:', 'ai-post-scheduler'); ?></span>
										<div class="aips-chips-group">
											<button type="button" class="aips-placeholder-chip" data-tag="{{topic}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{topic}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{voice_instructions}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{voice_instructions}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{diversity_blocks}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{diversity_blocks}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{article_data}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{article_data}}</button>
										</div>
									</div>
									<div class="aips-form-group">
										<textarea id="stage_title_prompt" name="title_prompt" class="aips-form-textarea aips-stage-textarea" rows="7" placeholder="<?php esc_attr_e('(Leave blank to inherit core codebase default)', 'ai-post-scheduler'); ?>"></textarea>
									</div>
								</div>

								<!-- Stage: Follow-up Title -->
								<div class="aips-stage-panel" data-stage-panel="title_followup_prompt">
									<div class="aips-stage-header">
										<div>
											<h3 class="aips-stage-heading"><?php esc_html_e('Follow-up Title Prompt', 'ai-post-scheduler'); ?></h3>
											<p class="aips-stage-subtext"><?php esc_html_e('Generates a refined post title after the post content body has been produced.', 'ai-post-scheduler'); ?></p>
										</div>
										<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-reset-stage-btn" data-stage="title_followup_prompt">
											<span class="dashicons dashicons-undo"></span>
											<?php esc_html_e('Reset to Default', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div class="aips-stage-chips-bar">
										<span class="aips-chips-label"><?php esc_html_e('Insert tag:', 'ai-post-scheduler'); ?></span>
										<div class="aips-chips-group">
											<button type="button" class="aips-placeholder-chip" data-tag="{{article_data}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{article_data}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{topic}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{topic}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{voice_instructions}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{voice_instructions}}</button>
										</div>
									</div>
									<div class="aips-form-group">
										<textarea id="stage_title_followup_prompt" name="title_followup_prompt" class="aips-form-textarea aips-stage-textarea" rows="7" placeholder="<?php esc_attr_e('(Leave blank to inherit core codebase default)', 'ai-post-scheduler'); ?>"></textarea>
									</div>
								</div>

								<!-- Stage: Content Shell -->
								<div class="aips-stage-panel" data-stage-panel="content_prompt">
									<div class="aips-stage-header">
										<div>
											<h3 class="aips-stage-heading"><?php esc_html_e('Content Shell & Wrapper Prompt', 'ai-post-scheduler'); ?></h3>
											<p class="aips-stage-subtext"><?php esc_html_e('Guides overall article construction, length, structure, formatting, tone, and link injection.', 'ai-post-scheduler'); ?></p>
										</div>
										<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-reset-stage-btn" data-stage="content_prompt">
											<span class="dashicons dashicons-undo"></span>
											<?php esc_html_e('Reset to Default', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div class="aips-stage-chips-bar">
										<span class="aips-chips-label"><?php esc_html_e('Insert tag:', 'ai-post-scheduler'); ?></span>
										<div class="aips-chips-group">
											<button type="button" class="aips-placeholder-chip" data-tag="{{topic}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{topic}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{voice_instructions}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{voice_instructions}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{user_instructions}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{user_instructions}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{sections}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{sections}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{word_count_min}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{word_count_min}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{word_count_max}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{word_count_max}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{internal_links}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{internal_links}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{reference_source}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{reference_source}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{diversity_blocks}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{diversity_blocks}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{custom_fields}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{custom_fields}}</button>
										</div>
									</div>
									<div class="aips-form-group">
										<textarea id="stage_content_prompt" name="content_prompt" class="aips-form-textarea aips-stage-textarea" rows="9" placeholder="<?php esc_attr_e('(Leave blank to inherit core codebase default)', 'ai-post-scheduler'); ?>"></textarea>
									</div>
								</div>

								<!-- Stage: Post Excerpt -->
								<div class="aips-stage-panel" data-stage-panel="excerpt_prompt">
									<div class="aips-stage-header">
										<div>
											<h3 class="aips-stage-heading"><?php esc_html_e('Post Excerpt Prompt', 'ai-post-scheduler'); ?></h3>
											<p class="aips-stage-subtext"><?php esc_html_e('Generates a summary excerpt from the topic headline.', 'ai-post-scheduler'); ?></p>
										</div>
										<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-reset-stage-btn" data-stage="excerpt_prompt">
											<span class="dashicons dashicons-undo"></span>
											<?php esc_html_e('Reset to Default', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div class="aips-stage-chips-bar">
										<span class="aips-chips-label"><?php esc_html_e('Insert tag:', 'ai-post-scheduler'); ?></span>
										<div class="aips-chips-group">
											<button type="button" class="aips-placeholder-chip" data-tag="{{topic}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{topic}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{voice_instructions}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{voice_instructions}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{article_data}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{article_data}}</button>
										</div>
									</div>
									<div class="aips-form-group">
										<textarea id="stage_excerpt_prompt" name="excerpt_prompt" class="aips-form-textarea aips-stage-textarea" rows="7" placeholder="<?php esc_attr_e('(Leave blank to inherit core codebase default)', 'ai-post-scheduler'); ?>"></textarea>
									</div>
								</div>

								<!-- Stage: Follow-up Excerpt -->
								<div class="aips-stage-panel" data-stage-panel="excerpt_followup_prompt">
									<div class="aips-stage-header">
										<div>
											<h3 class="aips-stage-heading"><?php esc_html_e('Follow-up Excerpt Prompt', 'ai-post-scheduler'); ?></h3>
											<p class="aips-stage-subtext"><?php esc_html_e('Summarizes the generated post content into a concise meta excerpt.', 'ai-post-scheduler'); ?></p>
										</div>
										<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-reset-stage-btn" data-stage="excerpt_followup_prompt">
											<span class="dashicons dashicons-undo"></span>
											<?php esc_html_e('Reset to Default', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div class="aips-stage-chips-bar">
										<span class="aips-chips-label"><?php esc_html_e('Insert tag:', 'ai-post-scheduler'); ?></span>
										<div class="aips-chips-group">
											<button type="button" class="aips-placeholder-chip" data-tag="{{article_data}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{article_data}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{topic}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{topic}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{voice_instructions}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{voice_instructions}}</button>
										</div>
									</div>
									<div class="aips-form-group">
										<textarea id="stage_excerpt_followup_prompt" name="excerpt_followup_prompt" class="aips-form-textarea aips-stage-textarea" rows="7" placeholder="<?php esc_attr_e('(Leave blank to inherit core codebase default)', 'ai-post-scheduler'); ?>"></textarea>
									</div>
								</div>

								<!-- Stage: Featured Image -->
								<div class="aips-stage-panel" data-stage-panel="featured_image_prompt">
									<div class="aips-stage-header">
										<div>
											<h3 class="aips-stage-heading"><?php esc_html_e('Featured Image Prompt', 'ai-post-scheduler'); ?></h3>
											<p class="aips-stage-subtext"><?php esc_html_e('Guides AI image generation prompts from article content and theme styling.', 'ai-post-scheduler'); ?></p>
										</div>
										<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-reset-stage-btn" data-stage="featured_image_prompt">
											<span class="dashicons dashicons-undo"></span>
											<?php esc_html_e('Reset to Default', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div class="aips-stage-chips-bar">
										<span class="aips-chips-label"><?php esc_html_e('Insert tag:', 'ai-post-scheduler'); ?></span>
										<div class="aips-chips-group">
											<button type="button" class="aips-placeholder-chip" data-tag="{{article_data}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{article_data}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{topic}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{topic}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{style_instructions}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{style_instructions}}</button>
										</div>
									</div>
									<div class="aips-form-group">
										<textarea id="stage_featured_image_prompt" name="featured_image_prompt" class="aips-form-textarea aips-stage-textarea" rows="7" placeholder="<?php esc_attr_e('(Leave blank to inherit core codebase default)', 'ai-post-scheduler'); ?>"></textarea>
									</div>
								</div>

								<!-- Stage: Topic Discovery -->
								<div class="aips-stage-panel" data-stage-panel="topic_ideas_prompt">
									<div class="aips-stage-header">
										<div>
											<h3 class="aips-stage-heading"><?php esc_html_e('Author Topic Ideas Prompt', 'ai-post-scheduler'); ?></h3>
											<p class="aips-stage-subtext"><?php esc_html_e('Instructs the AI to ideate high-performing topic angles aligned with author niche and previous feedback.', 'ai-post-scheduler'); ?></p>
										</div>
										<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-reset-stage-btn" data-stage="topic_ideas_prompt">
											<span class="dashicons dashicons-undo"></span>
											<?php esc_html_e('Reset to Default', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div class="aips-stage-chips-bar">
										<span class="aips-chips-label"><?php esc_html_e('Insert tag:', 'ai-post-scheduler'); ?></span>
										<div class="aips-chips-group">
											<button type="button" class="aips-placeholder-chip" data-tag="{{niche}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{niche}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{author_name}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{author_name}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{existing_topics}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{existing_topics}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{feedback_history}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{feedback_history}}</button>
										</div>
									</div>
									<div class="aips-form-group">
										<textarea id="stage_topic_ideas_prompt" name="topic_ideas_prompt" class="aips-form-textarea aips-stage-textarea" rows="7" placeholder="<?php esc_attr_e('(Leave blank to inherit core codebase default)', 'ai-post-scheduler'); ?>"></textarea>
									</div>
								</div>

								<!-- Stage: SEO Metadata -->
								<div class="aips-stage-panel" data-stage-panel="metadata_prompt">
									<div class="aips-stage-header">
										<div>
											<h3 class="aips-stage-heading"><?php esc_html_e('SEO & Metadata JSON Prompt', 'ai-post-scheduler'); ?></h3>
											<p class="aips-stage-subtext"><?php esc_html_e('Extracts meta descriptions, focus keywords, and structured schema in JSON format.', 'ai-post-scheduler'); ?></p>
										</div>
										<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-reset-stage-btn" data-stage="metadata_prompt">
											<span class="dashicons dashicons-undo"></span>
											<?php esc_html_e('Reset to Default', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div class="aips-stage-chips-bar">
										<span class="aips-chips-label"><?php esc_html_e('Insert tag:', 'ai-post-scheduler'); ?></span>
										<div class="aips-chips-group">
											<button type="button" class="aips-placeholder-chip" data-tag="{{article_data}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{article_data}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{topic}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{topic}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{target_keywords}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{target_keywords}}</button>
										</div>
									</div>
									<div class="aips-form-group">
										<textarea id="stage_metadata_prompt" name="metadata_prompt" class="aips-form-textarea aips-stage-textarea" rows="7" placeholder="<?php esc_attr_e('(Leave blank to inherit core codebase default)', 'ai-post-scheduler'); ?>"></textarea>
									</div>
								</div>

								<!-- Stage: Taxonomy -->
								<div class="aips-stage-panel" data-stage-panel="taxonomy_prompt">
									<div class="aips-stage-header">
										<div>
											<h3 class="aips-stage-heading"><?php esc_html_e('Taxonomy Classification Prompt', 'ai-post-scheduler'); ?></h3>
											<p class="aips-stage-subtext"><?php esc_html_e('Matches post content against your existing WordPress categories and tags.', 'ai-post-scheduler'); ?></p>
										</div>
										<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-reset-stage-btn" data-stage="taxonomy_prompt">
											<span class="dashicons dashicons-undo"></span>
											<?php esc_html_e('Reset to Default', 'ai-post-scheduler'); ?>
										</button>
									</div>
									<div class="aips-stage-chips-bar">
										<span class="aips-chips-label"><?php esc_html_e('Insert tag:', 'ai-post-scheduler'); ?></span>
										<div class="aips-chips-group">
											<button type="button" class="aips-placeholder-chip" data-tag="{{article_data}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{article_data}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{topic}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{topic}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{available_categories}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{available_categories}}</button>
											<button type="button" class="aips-placeholder-chip" data-tag="{{available_tags}}" title="<?php esc_attr_e('Click to insert tag at cursor', 'ai-post-scheduler'); ?>">{{available_tags}}</button>
										</div>
									</div>
									<div class="aips-form-group">
										<textarea id="stage_taxonomy_prompt" name="taxonomy_prompt" class="aips-form-textarea aips-stage-textarea" rows="7" placeholder="<?php esc_attr_e('(Leave blank to inherit core codebase default)', 'ai-post-scheduler'); ?>"></textarea>
									</div>
								</div>
							</div>

							<!-- Live Preview Sandbox Accordion -->
							<div class="aips-prompt-preview-sandbox">
								<div class="aips-sandbox-header" id="aips-toggle-sandbox">
									<div class="aips-sandbox-title">
										<span class="dashicons dashicons-visibility"></span>
										<strong><?php esc_html_e('Live Prompt Assembly Sandbox', 'ai-post-scheduler'); ?></strong>
										<span class="aips-sandbox-subtitle"><?php esc_html_e('(Test placeholder interpolation without token costs)', 'ai-post-scheduler'); ?></span>
									</div>
									<span class="dashicons dashicons-arrow-down-alt2 aips-sandbox-chevron"></span>
								</div>
								<div class="aips-sandbox-body" id="aips-sandbox-body" style="display: none;">
									<div class="aips-sandbox-inputs-grid">
										<div class="aips-form-group">
											<label for="aips_preview_sample_topic" class="aips-form-label"><?php esc_html_e('Sample Topic', 'ai-post-scheduler'); ?></label>
											<input type="text" id="aips_preview_sample_topic" class="aips-form-input" value="10 Proven Strategies for Sustainable Organic Gardening">
										</div>
										<div class="aips-form-group">
											<label for="aips_preview_sample_voice" class="aips-form-label"><?php esc_html_e('Sample Voice / Tone', 'ai-post-scheduler'); ?></label>
											<input type="text" id="aips_preview_sample_voice" class="aips-form-input" value="Write in an authoritative yet encouraging conversational style.">
										</div>
									</div>
									<div class="aips-sandbox-result">
										<div class="aips-result-header">
											<span class="aips-result-label"><?php esc_html_e('Compiled Prompt Output for Active Stage:', 'ai-post-scheduler'); ?></span>
											<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost" id="aips-refresh-preview-btn">
												<span class="dashicons dashicons-update"></span>
												<?php esc_html_e('Refresh Preview', 'ai-post-scheduler'); ?>
											</button>
										</div>
										<pre id="aips-preview-output-box" class="aips-prompt-code-output"><?php esc_html_e('Click refresh or type above to assemble preview...', 'ai-post-scheduler'); ?></pre>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Modal Footer -->
					<div class="aips-modal-footer">
						<div class="aips-footer-left">
							<span id="aips-unsaved-changes-indicator" class="aips-unsaved-text" style="display: none;">
								<span class="dashicons dashicons-warning"></span>
								<?php esc_html_e('Unsaved changes', 'ai-post-scheduler'); ?>
							</span>
						</div>
						<div class="aips-footer-right">
							<button type="button" class="aips-btn aips-btn-secondary aips-modal-close">
								<?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
							</button>
							<button type="submit" class="aips-btn aips-btn-primary" id="aips-save-profile-btn">
								<span class="dashicons dashicons-saved"></span>
								<?php esc_html_e('Save Profile', 'ai-post-scheduler'); ?>
							</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
