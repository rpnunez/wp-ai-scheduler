<?php
if (!defined('ABSPATH')) {
	exit;
}

$wizard_url = admin_url('admin.php?page=' . AIPS_Onboarding_Wizard::PAGE_SLUG);
$completed = (bool) get_option('aips_onboarding_completed', false);

$strategy_complete = !empty(get_option('aips_site_niche', ''));
$author_complete = !empty($author) && !empty($author->id);
$template_complete = !empty($template) && !empty($template->id);
$topics_complete = !empty($state['topics_generated']);
$post_complete = !empty($state['post_id']);

$default_template_prompt = "Write a high-quality blog post about {{topic}}.\n\nRequirements:\n- Use clear headings (H2/H3)\n- Include practical, actionable steps\n- Keep it aligned with the site's content strategy\n- End with a short conclusion and next steps";
$default_title_prompt = __('Create a concise, SEO-friendly title for this article.', 'ai-post-scheduler');
?>

<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<div class="aips-page-header">
			<div class="aips-page-header-top" style="align-items: center; justify-content: space-between;">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Onboarding Wizard', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Get AI Post Scheduler ready by setting your site strategy, creating an author and template, and generating your first post.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-btn-group" style="gap: 8px;">
					<a class="aips-btn aips-btn-secondary" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('settings') . '#content-strategy'); ?>">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e('Open Settings', 'ai-post-scheduler'); ?>
					</a>
					<button type="button" class="aips-btn aips-btn-danger" id="aips-onboarding-reset">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e('Restart Wizard', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<?php if (!$ai_engine_active) : ?>
			<div class="notice notice-error" style="margin: 16px 0;">
				<p><?php esc_html_e('AI Engine is not installed or activated. You can complete the setup steps, but topic/post generation requires AI Engine to be active.', 'ai-post-scheduler'); ?></p>
			</div>
		<?php endif; ?>

		<?php if ($completed) : ?>
			<div class="notice notice-success" style="margin: 16px 0;">
				<p><?php esc_html_e('Onboarding is marked as completed. You can restart the wizard if you want to run it again.', 'ai-post-scheduler'); ?></p>
			</div>
		<?php endif; ?>

		<div id="aips-onboarding-notice" style="margin: 16px 0;"></div>

		<div class="aips-content-panel">
			<div class="aips-panel-header">
				<h2><?php esc_html_e('Plugin Concepts (Quick Tour)', 'ai-post-scheduler'); ?></h2>
			</div>
			<div class="aips-panel-body">
				<ul style="margin: 0; padding-left: 18px;">
					<li><strong><?php esc_html_e('Site Content Strategy', 'ai-post-scheduler'); ?></strong> — <?php esc_html_e('Site-wide settings that shape tone, audience, and constraints across all generations.', 'ai-post-scheduler'); ?></li>
					<li><strong><?php esc_html_e('Authors', 'ai-post-scheduler'); ?></strong> — <?php esc_html_e('Reusable personas that define a niche, voice, and cadence for generating topics and posts.', 'ai-post-scheduler'); ?></li>
					<li><strong><?php esc_html_e('Author Topics', 'ai-post-scheduler'); ?></strong> — <?php esc_html_e('AI-generated topic ideas for an author; you can review and use them to create posts.', 'ai-post-scheduler'); ?></li>
					<li><strong><?php esc_html_e('Templates', 'ai-post-scheduler'); ?></strong> — <?php esc_html_e('Prompt blueprints + publishing defaults used to generate posts (with variables like {{topic}}).', 'ai-post-scheduler'); ?></li>
					<li><strong><?php esc_html_e('Generation', 'ai-post-scheduler'); ?></strong> — <?php esc_html_e('Manual or scheduled creation of posts; the wizard helps you generate a first example end-to-end.', 'ai-post-scheduler'); ?></li>
				</ul>
			</div>
		</div>

		<div class="aips-content-panel" style="margin-top: 20px;">
			<div class="aips-panel-header">
				<h2>
					<?php esc_html_e('1) Site Content Strategy', 'ai-post-scheduler'); ?>
					<?php if ($strategy_complete) : ?>
						<span class="aips-badge aips-badge-success" style="margin-left: 10px;"><?php esc_html_e('Done', 'ai-post-scheduler'); ?></span>
					<?php endif; ?>
				</h2>
			</div>
			<div class="aips-panel-body">
				<p class="description"><?php esc_html_e('These settings influence author suggestions, topic generation, and post generation. Fill at least the Site Niche to proceed.', 'ai-post-scheduler'); ?></p>
				<form id="aips-onboarding-strategy-form">
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="aips_site_niche"><?php esc_html_e('Site Niche / Primary Topic', 'ai-post-scheduler'); ?> *</label></th>
								<td><input type="text" class="regular-text" id="aips_site_niche" name="strategy[aips_site_niche]" value="<?php echo esc_attr(get_option('aips_site_niche', '')); ?>" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="aips_site_target_audience"><?php esc_html_e('Target Audience', 'ai-post-scheduler'); ?></label></th>
								<td><input type="text" class="regular-text" id="aips_site_target_audience" name="strategy[aips_site_target_audience]" value="<?php echo esc_attr(get_option('aips_site_target_audience', '')); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="aips_site_content_goals"><?php esc_html_e('Content Goals', 'ai-post-scheduler'); ?></label></th>
								<td><textarea class="large-text" rows="3" id="aips_site_content_goals" name="strategy[aips_site_content_goals]"><?php echo esc_textarea(get_option('aips_site_content_goals', '')); ?></textarea></td>
							</tr>
							<tr>
								<th scope="row"><label for="aips_site_brand_voice"><?php esc_html_e('Brand Voice / Tone', 'ai-post-scheduler'); ?></label></th>
								<td><input type="text" class="regular-text" id="aips_site_brand_voice" name="strategy[aips_site_brand_voice]" value="<?php echo esc_attr(get_option('aips_site_brand_voice', '')); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="aips_site_content_language"><?php esc_html_e('Content Language', 'ai-post-scheduler'); ?></label></th>
								<td>
									<select id="aips_site_content_language" name="strategy[aips_site_content_language]">
										<?php
										$lang_value = get_option('aips_site_content_language', 'en');
										$languages = array(
											'en' => __('English', 'ai-post-scheduler'),
											'es' => __('Spanish', 'ai-post-scheduler'),
											'fr' => __('French', 'ai-post-scheduler'),
											'de' => __('German', 'ai-post-scheduler'),
											'pt' => __('Portuguese', 'ai-post-scheduler'),
											'it' => __('Italian', 'ai-post-scheduler'),
											'nl' => __('Dutch', 'ai-post-scheduler'),
										);
										foreach ($languages as $code => $label) :
											?>
											<option value="<?php echo esc_attr($code); ?>" <?php selected($lang_value, $code); ?>><?php echo esc_html($label); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aips_site_content_guidelines"><?php esc_html_e('Content Guidelines', 'ai-post-scheduler'); ?></label></th>
								<td><textarea class="large-text" rows="3" id="aips_site_content_guidelines" name="strategy[aips_site_content_guidelines]"><?php echo esc_textarea(get_option('aips_site_content_guidelines', '')); ?></textarea></td>
							</tr>
							<tr>
								<th scope="row"><label for="aips_site_excluded_topics"><?php esc_html_e('Excluded Topics (site-wide)', 'ai-post-scheduler'); ?></label></th>
								<td><textarea class="large-text" rows="3" id="aips_site_excluded_topics" name="strategy[aips_site_excluded_topics]"><?php echo esc_textarea(get_option('aips_site_excluded_topics', '')); ?></textarea></td>
							</tr>
						</tbody>
					</table>
					<p>
						<button type="submit" class="aips-btn aips-btn-primary" id="aips-onboarding-save-strategy">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e('Save Content Strategy', 'ai-post-scheduler'); ?>
						</button>
					</p>
				</form>
			</div>
		</div>

		<div class="aips-content-panel" style="margin-top: 20px; opacity: <?php echo $strategy_complete ? '1' : '0.6'; ?>;">
			<div class="aips-panel-header">
				<h2>
					<?php esc_html_e('2) Create an Author', 'ai-post-scheduler'); ?>
					<?php if ($author_complete) : ?>
						<span class="aips-badge aips-badge-success" style="margin-left: 10px;"><?php esc_html_e('Done', 'ai-post-scheduler'); ?></span>
					<?php endif; ?>
				</h2>
			</div>
			<div class="aips-panel-body">
				<?php if ($author_complete) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: author name */
							esc_html__('Onboarding author created: %s', 'ai-post-scheduler'),
							'<strong>' . esc_html($author->name) . '</strong>'
						);
						?>
						<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('authors')); ?>" style="margin-left: 8px;"><?php esc_html_e('Manage Authors', 'ai-post-scheduler'); ?></a>
					</p>
				<?php else : ?>
					<form id="aips-onboarding-author-form">
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="aips_onboarding_author_name"><?php esc_html_e('Author Name', 'ai-post-scheduler'); ?> *</label></th>
									<td><input type="text" class="regular-text" id="aips_onboarding_author_name" name="name" value="<?php echo esc_attr__('Default Author', 'ai-post-scheduler'); ?>" <?php disabled(!$strategy_complete); ?> required></td>
								</tr>
								<tr>
									<th scope="row"><label for="aips_onboarding_author_niche"><?php esc_html_e('Field / Niche', 'ai-post-scheduler'); ?> *</label></th>
									<td><input type="text" class="regular-text" id="aips_onboarding_author_niche" name="field_niche" value="<?php echo esc_attr(get_option('aips_site_niche', '')); ?>" <?php disabled(!$strategy_complete); ?> required></td>
								</tr>
								<tr>
									<th scope="row"><label for="aips_onboarding_author_voice_tone"><?php esc_html_e('Voice Tone', 'ai-post-scheduler'); ?></label></th>
									<td><input type="text" class="regular-text" id="aips_onboarding_author_voice_tone" name="voice_tone" value="<?php echo esc_attr(get_option('aips_site_brand_voice', '')); ?>" <?php disabled(!$strategy_complete); ?>></td>
								</tr>
								<tr>
									<th scope="row"><label for="aips_onboarding_author_target_audience"><?php esc_html_e('Target Audience', 'ai-post-scheduler'); ?></label></th>
									<td><input type="text" class="regular-text" id="aips_onboarding_author_target_audience" name="target_audience" value="<?php echo esc_attr(get_option('aips_site_target_audience', '')); ?>" <?php disabled(!$strategy_complete); ?>></td>
								</tr>
								<tr>
									<th scope="row"><label for="aips_onboarding_author_goals"><?php esc_html_e('Content Goals', 'ai-post-scheduler'); ?></label></th>
									<td><textarea class="large-text" rows="2" id="aips_onboarding_author_goals" name="content_goals" <?php disabled(!$strategy_complete); ?>><?php echo esc_textarea(get_option('aips_site_content_goals', '')); ?></textarea></td>
								</tr>
								<tr>
									<th scope="row"><label for="aips_onboarding_author_language"><?php esc_html_e('Language', 'ai-post-scheduler'); ?></label></th>
									<td><input type="text" class="small-text" id="aips_onboarding_author_language" name="language" value="<?php echo esc_attr(get_option('aips_site_content_language', 'en')); ?>" <?php disabled(!$strategy_complete); ?>></td>
								</tr>
								<tr>
									<th scope="row"><label for="aips_onboarding_author_qty"><?php esc_html_e('Topics to Generate', 'ai-post-scheduler'); ?></label></th>
									<td><input type="number" min="1" class="small-text" id="aips_onboarding_author_qty" name="topic_generation_quantity" value="5" <?php disabled(!$strategy_complete); ?>></td>
								</tr>
							</tbody>
						</table>
						<p>
							<button type="submit" class="aips-btn aips-btn-primary" id="aips-onboarding-create-author" <?php disabled(!$strategy_complete); ?>>
								<span class="dashicons dashicons-admin-users"></span>
								<?php esc_html_e('Create Author', 'ai-post-scheduler'); ?>
							</button>
						</p>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<div class="aips-content-panel" style="margin-top: 20px; opacity: <?php echo $author_complete ? '1' : '0.6'; ?>;">
			<div class="aips-panel-header">
				<h2>
					<?php esc_html_e('3) Create a Template', 'ai-post-scheduler'); ?>
					<?php if ($template_complete) : ?>
						<span class="aips-badge aips-badge-success" style="margin-left: 10px;"><?php esc_html_e('Done', 'ai-post-scheduler'); ?></span>
					<?php endif; ?>
				</h2>
			</div>
			<div class="aips-panel-body">
				<?php if ($template_complete) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: template name */
							esc_html__('Onboarding template created: %s', 'ai-post-scheduler'),
							'<strong>' . esc_html($template->name) . '</strong>'
						);
						?>
						<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('templates')); ?>" style="margin-left: 8px;"><?php esc_html_e('Manage Templates', 'ai-post-scheduler'); ?></a>
					</p>
				<?php else : ?>
					<form id="aips-onboarding-template-form">
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="aips_onboarding_template_name"><?php esc_html_e('Template Name', 'ai-post-scheduler'); ?> *</label></th>
									<td><input type="text" class="regular-text" id="aips_onboarding_template_name" name="name" value="<?php echo esc_attr__('Default Template', 'ai-post-scheduler'); ?>" <?php disabled(!$author_complete); ?> required></td>
								</tr>
								<tr>
									<th scope="row"><label for="aips_onboarding_template_prompt"><?php esc_html_e('Content Prompt', 'ai-post-scheduler'); ?> *</label></th>
									<td>
										<textarea class="large-text" rows="8" id="aips_onboarding_template_prompt" name="prompt_template" <?php disabled(!$author_complete); ?> required><?php echo esc_textarea($default_template_prompt); ?></textarea>
										<p class="description"><?php esc_html_e('Use {{topic}} anywhere in your prompt to insert a selected topic.', 'ai-post-scheduler'); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="aips_onboarding_template_title_prompt"><?php esc_html_e('Title Prompt', 'ai-post-scheduler'); ?></label></th>
									<td><input type="text" class="regular-text" id="aips_onboarding_template_title_prompt" name="title_prompt" value="<?php echo esc_attr($default_title_prompt); ?>" <?php disabled(!$author_complete); ?>></td>
								</tr>
							</tbody>
						</table>
						<p>
							<button type="submit" class="aips-btn aips-btn-primary" id="aips-onboarding-create-template" <?php disabled(!$author_complete); ?>>
								<span class="dashicons dashicons-media-text"></span>
								<?php esc_html_e('Create Template', 'ai-post-scheduler'); ?>
							</button>
						</p>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<div class="aips-content-panel" style="margin-top: 20px; opacity: <?php echo $template_complete ? '1' : '0.6'; ?>;">
			<div class="aips-panel-header">
				<h2>
					<?php esc_html_e('4) Generate Author Topics', 'ai-post-scheduler'); ?>
					<?php if ($topics_complete) : ?>
						<span class="aips-badge aips-badge-success" style="margin-left: 10px;"><?php esc_html_e('Done', 'ai-post-scheduler'); ?></span>
					<?php endif; ?>
				</h2>
			</div>
			<div class="aips-panel-body">
				<?php if ($topics_complete) : ?>
					<p>
						<?php esc_html_e('Topics generated. The wizard will use the first topic to generate a sample post.', 'ai-post-scheduler'); ?>
						<?php if (!empty($state['first_topic'])) : ?>
							<br><strong><?php esc_html_e('First topic:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html($state['first_topic']); ?>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<p class="description"><?php esc_html_e('This will generate topic ideas for your onboarding author.', 'ai-post-scheduler'); ?></p>
					<p>
						<button type="button" class="aips-btn aips-btn-primary" id="aips-onboarding-generate-topics" <?php disabled(!$template_complete || !$ai_engine_active); ?>>
							<span class="dashicons dashicons-lightbulb"></span>
							<?php esc_html_e('Generate Topics', 'ai-post-scheduler'); ?>
						</button>
						<span class="spinner" id="aips-onboarding-topics-spinner" style="float:none;"></span>
					</p>
					<div id="aips-onboarding-topics-preview" style="display:none;"></div>
				<?php endif; ?>
			</div>
		</div>

		<div class="aips-content-panel" style="margin-top: 20px; opacity: <?php echo $topics_complete ? '1' : '0.6'; ?>;">
			<div class="aips-panel-header">
				<h2>
					<?php esc_html_e('5) Generate Your First Post', 'ai-post-scheduler'); ?>
					<?php if ($post_complete) : ?>
						<span class="aips-badge aips-badge-success" style="margin-left: 10px;"><?php esc_html_e('Done', 'ai-post-scheduler'); ?></span>
					<?php endif; ?>
				</h2>
			</div>
			<div class="aips-panel-body">
				<?php if ($post_complete) : ?>
					<p>
						<?php esc_html_e('Your first post is generated!', 'ai-post-scheduler'); ?>
						<a href="<?php echo esc_url(get_edit_post_link((int) $state['post_id'], 'raw')); ?>" style="margin-left: 8px;"><?php esc_html_e('Edit Post', 'ai-post-scheduler'); ?></a>
						<a href="<?php echo esc_url(get_permalink((int) $state['post_id'])); ?>" style="margin-left: 8px;"><?php esc_html_e('View Post', 'ai-post-scheduler'); ?></a>
					</p>
				<?php else : ?>
					<p class="description"><?php esc_html_e('Generate a post using the onboarding template, with the first generated topic as {{topic}}.', 'ai-post-scheduler'); ?></p>
					<p>
						<label for="aips-onboarding-topic" style="display:block; font-weight:600; margin-bottom:6px;"><?php esc_html_e('Topic to Use', 'ai-post-scheduler'); ?></label>
						<input type="text" class="regular-text" id="aips-onboarding-topic" value="<?php echo esc_attr(!empty($state['first_topic']) ? $state['first_topic'] : ''); ?>" <?php disabled(!$topics_complete); ?>>
					</p>
					<p>
						<button type="button" class="aips-btn aips-btn-primary" id="aips-onboarding-generate-post" <?php disabled(!$topics_complete || !$ai_engine_active); ?>>
							<span class="dashicons dashicons-edit"></span>
							<?php esc_html_e('Generate Post', 'ai-post-scheduler'); ?>
						</button>
						<span class="spinner" id="aips-onboarding-post-spinner" style="float:none;"></span>
					</p>
					<div id="aips-onboarding-post-result" style="display:none;"></div>
				<?php endif; ?>
			</div>
		</div>

		<div class="aips-content-panel" style="margin-top: 20px; opacity: <?php echo $post_complete ? '1' : '0.6'; ?>;">
			<div class="aips-panel-header">
				<h2><?php esc_html_e('Finish', 'ai-post-scheduler'); ?></h2>
			</div>
			<div class="aips-panel-body">
				<p class="description"><?php esc_html_e('Mark onboarding as completed. You can still run this wizard later from System Status.', 'ai-post-scheduler'); ?></p>
				<p>
					<button type="button" class="aips-btn aips-btn-primary" id="aips-onboarding-complete" <?php disabled(!$post_complete); ?>>
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e('Finish Onboarding', 'ai-post-scheduler'); ?>
					</button>
					<a class="aips-btn aips-btn-secondary" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('dashboard')); ?>" style="margin-left: 8px;">
						<span class="dashicons dashicons-admin-home"></span>
						<?php esc_html_e('Go to Dashboard', 'ai-post-scheduler'); ?>
					</a>
				</p>
			</div>
		</div>
	</div>
</div>

<script>
window.aipsOnboarding = window.aipsOnboarding || {};
window.aipsOnboarding.pageUrl = <?php echo wp_json_encode($wizard_url); ?>;
</script>

