<?php
if (!defined('ABSPATH')) {
	exit;
}

$current_user_id = get_current_user_id();
$authors = get_users(array(
	'fields' => array('ID', 'display_name'),
	'orderby' => 'display_name',
));
?>

<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<div class="aips-page-header">
			<div class="aips-page-header-top" style="align-items:center; justify-content:space-between;">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Campaign Wizard', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Build a campaign template, publishing defaults, review policy, and schedule in one flow.', 'ai-post-scheduler'); ?></p>
				</div>
				<a class="aips-btn aips-btn-secondary" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule')); ?>">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php esc_html_e('Schedules', 'ai-post-scheduler'); ?>
				</a>
			</div>
		</div>

		<div id="aips-campaign-wizard-notice" style="margin:16px 0;"></div>

		<form id="aips-campaign-wizard-form" class="aips-content-panel">
			<div class="aips-panel-body">
				<div class="aips-wizard-steps" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:18px;">
					<?php
					$steps = array(
						'goal' => __('Goal', 'ai-post-scheduler'),
						'template' => __('Template', 'ai-post-scheduler'),
						'defaults' => __('Defaults', 'ai-post-scheduler'),
						'schedule' => __('Schedule', 'ai-post-scheduler'),
						'review' => __('Review', 'ai-post-scheduler'),
						'confirm' => __('Confirm', 'ai-post-scheduler'),
					);
					foreach ($steps as $step_key => $label) :
						?>
						<button type="button" class="aips-btn aips-btn-secondary aips-wizard-step-tab" data-step="<?php echo esc_attr($step_key); ?>"><?php echo esc_html($label); ?></button>
					<?php endforeach; ?>
				</div>

				<section class="aips-wizard-step" data-step="goal">
					<h2><?php esc_html_e('1) Content Goal & Post Type', 'ai-post-scheduler'); ?></h2>
					<table class="form-table" role="presentation"><tbody>
						<tr>
							<th scope="row"><label for="aips_campaign_name"><?php esc_html_e('Campaign Name', 'ai-post-scheduler'); ?> *</label></th>
							<td><input type="text" class="regular-text" id="aips_campaign_name" name="campaign_name" value="<?php echo esc_attr($draft['campaign_name'] ?? ''); ?>" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_content_goal"><?php esc_html_e('Content Goal', 'ai-post-scheduler'); ?> *</label></th>
							<td><textarea class="large-text" rows="4" id="aips_content_goal" name="content_goal" required><?php echo esc_textarea($draft['content_goal'] ?? $aips_config->get_option('aips_site_content_goals')); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_post_type"><?php esc_html_e('Post Type', 'ai-post-scheduler'); ?></label></th>
							<td>
								<select id="aips_post_type" name="post_type">
									<?php foreach ($post_types as $post_type => $post_type_obj) : ?>
										<option value="<?php echo esc_attr($post_type); ?>" <?php selected($draft['post_type'] ?? 'post', $post_type); ?>><?php echo esc_html($post_type_obj->labels->singular_name); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</tbody></table>
				</section>

				<section class="aips-wizard-step" data-step="template" style="display:none;">
					<h2><?php esc_html_e('2) Prompt Template', 'ai-post-scheduler'); ?></h2>
					<table class="form-table" role="presentation"><tbody>
						<tr>
							<th scope="row"><?php esc_html_e('Template Source', 'ai-post-scheduler'); ?></th>
							<td>
								<label><input type="radio" name="template_mode" value="custom" <?php checked($draft['template_mode'] ?? 'custom', 'custom'); ?>> <?php esc_html_e('Create/customize template', 'ai-post-scheduler'); ?></label>
								<br>
								<label><input type="radio" name="template_mode" value="existing" <?php checked($draft['template_mode'] ?? '', 'existing'); ?>> <?php esc_html_e('Use existing template', 'ai-post-scheduler'); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_template_id"><?php esc_html_e('Existing Template', 'ai-post-scheduler'); ?></label></th>
							<td>
								<select id="aips_template_id" name="template_id">
									<option value="0"><?php esc_html_e('Select a template', 'ai-post-scheduler'); ?></option>
									<?php foreach ($templates as $template) : ?>
										<option value="<?php echo esc_attr($template->id); ?>" <?php selected($draft['template_id'] ?? 0, $template->id); ?>><?php echo esc_html($template->name); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_prompt_template"><?php esc_html_e('Prompt Template', 'ai-post-scheduler'); ?> *</label></th>
							<td><textarea class="large-text code" rows="9" id="aips_prompt_template" name="prompt_template"><?php echo esc_textarea($draft['prompt_template'] ?? "Write a high-quality article about {{topic}}.\n\nUse clear headings and practical examples."); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_title_prompt"><?php esc_html_e('Title Prompt', 'ai-post-scheduler'); ?></label></th>
							<td><input type="text" class="large-text" id="aips_title_prompt" name="title_prompt" value="<?php echo esc_attr($draft['title_prompt'] ?? __('Create a concise SEO-friendly title.', 'ai-post-scheduler')); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_author_id"><?php esc_html_e('Author Persona (Optional)', 'ai-post-scheduler'); ?></label></th>
							<td>
								<select id="aips_author_id" name="author_id">
									<option value="0"><?php esc_html_e('None - Use template-based generation', 'ai-post-scheduler'); ?></option>
									<?php if (!empty($authors)) : ?>
										<?php foreach ($authors as $author) : ?>
											<option value="<?php echo esc_attr($author->id); ?>" <?php selected($draft['author_id'] ?? 0, $author->id); ?>><?php echo esc_html($author->name); ?></option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
								<p class="description"><?php esc_html_e('Link this campaign to an author persona to generate topics through their lens and writing style.', 'ai-post-scheduler'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_campaign_mode"><?php esc_html_e('Campaign Mode', 'ai-post-scheduler'); ?></label></th>
							<td>
								<select id="aips_campaign_mode" name="campaign_mode">
									<option value="template" <?php selected($draft['campaign_mode'] ?? 'template', 'template'); ?>><?php esc_html_e('Template-based', 'ai-post-scheduler'); ?></option>
									<option value="author" <?php selected($draft['campaign_mode'] ?? '', 'author'); ?>><?php esc_html_e('Author-based (use author persona workflow)', 'ai-post-scheduler'); ?></option>
								</select>
								<p class="description"><?php esc_html_e('Choose how content is generated: directly from template or through author topic approval workflow.', 'ai-post-scheduler'); ?></p>
							</td>
						</tr>
					</tbody></table>
				</section>

				<section class="aips-wizard-step" data-step="defaults" style="display:none;">
					<h2><?php esc_html_e('3) Voice, Structure & Taxonomy Defaults', 'ai-post-scheduler'); ?></h2>
					<table class="form-table" role="presentation"><tbody>
						<tr>
							<th scope="row"><label for="aips_voice_id"><?php esc_html_e('Voice', 'ai-post-scheduler'); ?></label></th>
							<td><select id="aips_voice_id" name="voice_id"><option value="0"><?php esc_html_e('Default voice', 'ai-post-scheduler'); ?></option><?php foreach ($voices as $voice) : ?><option value="<?php echo esc_attr($voice->id); ?>" <?php selected($draft['voice_id'] ?? 0, $voice->id); ?>><?php echo esc_html($voice->name); ?></option><?php endforeach; ?></select></td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_article_structure_id"><?php esc_html_e('Article Structure', 'ai-post-scheduler'); ?></label></th>
							<td><select id="aips_article_structure_id" name="article_structure_id"><option value="0"><?php esc_html_e('Default structure', 'ai-post-scheduler'); ?></option><?php foreach ($structures as $structure) : ?><option value="<?php echo esc_attr($structure->id); ?>" <?php selected($draft['article_structure_id'] ?? $aips_config->get_option('aips_default_article_structure_id'), $structure->id); ?>><?php echo esc_html($structure->name); ?></option><?php endforeach; ?></select></td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_post_category"><?php esc_html_e('Category', 'ai-post-scheduler'); ?></label></th>
							<td><select id="aips_post_category" name="post_category"><option value="0"><?php esc_html_e('Default category', 'ai-post-scheduler'); ?></option><?php foreach ($categories as $category) : ?><option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($draft['post_category'] ?? $aips_config->get_option('aips_default_category'), $category->term_id); ?>><?php echo esc_html($category->name); ?></option><?php endforeach; ?></select></td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_post_tags"><?php esc_html_e('Tags', 'ai-post-scheduler'); ?></label></th>
							<td><input type="text" class="regular-text" id="aips_post_tags" name="post_tags" value="<?php echo esc_attr($draft['post_tags'] ?? ''); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_post_author"><?php esc_html_e('Author', 'ai-post-scheduler'); ?></label></th>
							<td><select id="aips_post_author" name="post_author"><?php foreach ($authors as $author) : ?><option value="<?php echo esc_attr($author->ID); ?>" <?php selected($draft['post_author'] ?? $current_user_id, $author->ID); ?>><?php echo esc_html($author->display_name); ?></option><?php endforeach; ?></select></td>
						</tr>
					</tbody></table>

					<h3 style="margin-top: 24px;"><?php esc_html_e('Multi-Post-Type Rules (Optional)', 'ai-post-scheduler'); ?></h3>
					<p class="description"><?php esc_html_e('Generate multiple post types per cycle with different quantities and prompt variations. Leave empty to use the single post type from Step 1.', 'ai-post-scheduler'); ?></p>

					<div id="aips-post-type-rules-container" style="margin-top: 12px;">
						<?php
						$saved_rules = isset($draft['post_type_rules']) ? json_decode($draft['post_type_rules'], true) : array();
						if (!empty($saved_rules) && is_array($saved_rules)) :
							foreach ($saved_rules as $index => $rule) :
								?>
								<div class="aips-post-type-rule" data-rule-index="<?php echo esc_attr($index); ?>" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 12px; margin-bottom: 10px;">
									<div style="display: grid; grid-template-columns: 1fr 1fr 80px 50px; gap: 10px; align-items: start;">
										<div>
											<label><?php esc_html_e('Post Type', 'ai-post-scheduler'); ?></label>
											<select name="post_type_rules[<?php echo esc_attr($index); ?>][post_type]" class="regular-text">
												<?php foreach ($post_types as $post_type => $post_type_obj) : ?>
													<option value="<?php echo esc_attr($post_type); ?>" <?php selected($rule['post_type'] ?? 'post', $post_type); ?>><?php echo esc_html($post_type_obj->labels->singular_name); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<div>
											<label><?php esc_html_e('Prompt Override (Optional)', 'ai-post-scheduler'); ?></label>
											<input type="text" name="post_type_rules[<?php echo esc_attr($index); ?>][prompt_override]" class="regular-text" value="<?php echo esc_attr($rule['prompt_override'] ?? ''); ?>" placeholder="<?php esc_attr_e('Leave empty to use main template', 'ai-post-scheduler'); ?>">
										</div>
										<div>
											<label><?php esc_html_e('Quantity', 'ai-post-scheduler'); ?></label>
											<input type="number" name="post_type_rules[<?php echo esc_attr($index); ?>][quantity]" min="1" max="100" value="<?php echo esc_attr($rule['quantity'] ?? 1); ?>" style="width: 100%;">
										</div>
										<div style="padding-top: 20px;">
											<button type="button" class="button button-small aips-remove-post-type-rule" title="<?php esc_attr_e('Remove', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-no-alt" style="margin-top: 2px;"></span>
											</button>
										</div>
									</div>
								</div>
								<?php
							endforeach;
						endif;
						?>
					</div>
					<button type="button" id="aips-add-post-type-rule" class="button" style="margin-top: 10px;">
						<span class="dashicons dashicons-plus-alt2" style="margin-top: 2px;"></span> <?php esc_html_e('Add Post Type Rule', 'ai-post-scheduler'); ?>
					</button>
				</section>

				<section class="aips-wizard-step" data-step="schedule" style="display:none;">
					<h2><?php esc_html_e('4) Publish Cadence & Schedule', 'ai-post-scheduler'); ?></h2>
					<table class="form-table" role="presentation"><tbody>
						<tr>
							<th scope="row"><label for="aips_frequency"><?php esc_html_e('Cadence', 'ai-post-scheduler'); ?></label></th>
							<td><select id="aips_frequency" name="frequency"><?php foreach ($frequencies as $key => $meta) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($draft['frequency'] ?? 'daily', $key); ?>><?php echo esc_html($meta['display'] ?? $key); ?></option><?php endforeach; ?></select></td>
						</tr>
						<tr>
							<th scope="row"><label for="aips_start_time"><?php esc_html_e('First Run', 'ai-post-scheduler'); ?></label></th>
							<td><input type="datetime-local" id="aips_start_time" name="start_time" value="<?php echo esc_attr($draft['start_time'] ?? current_time('Y-m-d\TH:i')); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
							<td><label><input type="checkbox" name="is_active" value="1" <?php checked($draft['is_active'] ?? 1, 1); ?>> <?php esc_html_e('Activate schedule after creation', 'ai-post-scheduler'); ?></label></td>
						</tr>
					</tbody></table>
				</section>

				<section class="aips-wizard-step" data-step="review" style="display:none;">
					<h2><?php esc_html_e('5) Review Policy', 'ai-post-scheduler'); ?></h2>
					<p><label><input type="radio" name="review_policy" value="draft" <?php checked($draft['review_policy'] ?? 'draft', 'draft'); ?>> <?php esc_html_e('Save generated posts as drafts', 'ai-post-scheduler'); ?></label></p>
					<p><label><input type="radio" name="review_policy" value="approval" <?php checked($draft['review_policy'] ?? '', 'approval'); ?>> <?php esc_html_e('Send generated posts to pending approval', 'ai-post-scheduler'); ?></label></p>
					<p><label><input type="radio" name="review_policy" value="auto_publish" <?php checked($draft['review_policy'] ?? '', 'auto_publish'); ?>> <?php esc_html_e('Auto-publish generated posts', 'ai-post-scheduler'); ?></label></p>
				</section>

				<section class="aips-wizard-step" data-step="confirm" style="display:none;">
					<h2><?php esc_html_e('6) Review & Confirm', 'ai-post-scheduler'); ?></h2>
					<div id="aips-campaign-summary" class="aips-content-panel" style="margin-top:12px;">
						<div class="aips-panel-body">
							<dl style="display:grid; grid-template-columns:180px 1fr; gap:10px 16px; margin:0;"></dl>
						</div>
					</div>
				</section>

				<p class="submit" style="display:flex; gap:8px;">
					<button type="button" class="aips-btn aips-btn-secondary" id="aips-wizard-prev"><?php esc_html_e('Back', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-btn aips-btn-primary" id="aips-wizard-next"><?php esc_html_e('Save & Continue', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-btn aips-btn-primary" id="aips-wizard-finalize" style="display:none;"><?php esc_html_e('Create Campaign', 'ai-post-scheduler'); ?></button>
					<span class="spinner" id="aips-campaign-spinner"></span>
				</p>
			</div>
		</form>
	</div>
</div>

<script type="application/json" id="aips-campaign-summary-json"><?php echo wp_json_encode($default_summary); ?></script>
