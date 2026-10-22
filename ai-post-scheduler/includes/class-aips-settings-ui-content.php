<?php
/**
 * Settings UI Presenter: Content Strategy & AI Assistance
 *
 * Handles rendering for Site Content Strategy (niche, audience, goals, structure,
 * brand voice, language, guidelines, excluded topics) and AI Assistance.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Settings_UI_Content
 */
class AIPS_Settings_UI_Content {

	public function ai_assistance_section_callback() {
		echo '<p>' . esc_html__('Configure consistent, admin-only AI suggestions. Current field values and declared sibling fields are sent to your AI provider to produce a suggestion.', 'ai-post-scheduler') . '</p>';
	}

	public function ai_assistance_enabled_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option('aips_ai_assistance_enabled');
		?>
		<label>
			<input type="checkbox" name="aips_ai_assistance_enabled" value="1" <?php checked($value); ?>>
			<?php esc_html_e('Enable AI Assistance site-wide', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	public function ai_assistance_contexts_field_callback() {
		$enabled = AIPS_Settings::get_enabled_ai_assistance_contexts();
		$labels  = array(
			'authors'          => __('Authors', 'ai-post-scheduler'),
			'templates'        => __('Templates', 'ai-post-scheduler'),
			'sources'          => __('Sources', 'ai-post-scheduler'),
			'content_strategy' => __('Site Content Strategy', 'ai-post-scheduler'),
		);
		?>
		<input type="hidden" name="aips_ai_assistance_contexts[]" value="">
		<?php foreach ($labels as $context => $label) : ?>
			<label style="display:block">
				<input type="checkbox" name="aips_ai_assistance_contexts[]" value="<?php echo esc_attr($context); ?>" <?php checked(in_array($context, $enabled, true)); ?>>
				<?php echo esc_html($label); ?>
			</label>
		<?php endforeach; ?>
		<?php
	}

	public function ai_assistance_response_length_field_callback() {
		$value   = AIPS_Settings::sanitize_ai_assistance_response_length(AIPS_Config::get_instance()->get_option('aips_ai_assistance_response_length'));
		$options = array(
			'concise'  => __('Concise', 'ai-post-scheduler'),
			'standard' => __('Standard', 'ai-post-scheduler'),
			'detailed' => __('Detailed', 'ai-post-scheduler'),
		);
		?>
		<select name="aips_ai_assistance_response_length">
			<?php foreach ($options as $key => $label) : ?>
				<option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($label); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function ai_assistance_guidance_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_ai_assistance_guidance');
		?>
		<textarea class="large-text" rows="3" name="aips_ai_assistance_guidance"><?php echo esc_textarea($value); ?></textarea>
		<p class="description"><?php esc_html_e('Optional instructions that supplement the Site Content Strategy.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	public function ai_assistance_overwrite_mode_field_callback() {
		$value = AIPS_Settings::sanitize_ai_assistance_overwrite_mode(AIPS_Config::get_instance()->get_option('aips_ai_assistance_overwrite_mode'));
		?>
		<label>
			<input type="radio" name="aips_ai_assistance_overwrite_mode" value="replace" <?php checked($value, 'replace'); ?>>
			<?php esc_html_e('Replace automatically', 'ai-post-scheduler'); ?>
		</label>
		<br>
		<label>
			<input type="radio" name="aips_ai_assistance_overwrite_mode" value="preview" <?php checked($value, 'preview'); ?>>
			<?php esc_html_e('Show preview and apply choice', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	public function ai_assistance_retention_days_field_callback() {
		$value   = AIPS_Settings::get_ai_assistance_retention_days();
		$options = array(
			0   => __('Do not retain', 'ai-post-scheduler'),
			7   => __('7 days', 'ai-post-scheduler'),
			30  => __('30 days', 'ai-post-scheduler'),
			90  => __('90 days', 'ai-post-scheduler'),
			365 => __('1 year', 'ai-post-scheduler'),
		);
		?>
		<select name="aips_ai_assistance_retention_days">
			<?php foreach ($options as $days => $label) : ?>
				<option value="<?php echo esc_attr($days); ?>" <?php selected($value, $days); ?>><?php echo esc_html($label); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render the description for the site content strategy settings section.
	 *
	 * @return void
	 */
	public function content_strategy_section_callback() {
		echo '<p>' . esc_html__('Define the overall content identity of your website. These settings are shared across Author Suggestions, topic generation, and post generation to ensure consistent, on-brand output.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render the Site Niche / Primary Topic field.
	 *
	 * @return void
	 */
	public function site_niche_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_site_niche');
		?>
		<input type="text" id="aips_site_niche" name="aips_site_niche" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g., Personal Finance, WordPress Development, Fitness', 'ai-post-scheduler'); ?>">
		<p class="description"><?php esc_html_e('The main topic or industry your website covers. Used as context for Author Suggestions and AI generation.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the Target Audience field.
	 *
	 * @return void
	 */
	public function site_target_audience_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_site_target_audience');
		?>
		<input type="text" id="aips_site_target_audience" name="aips_site_target_audience" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g., Beginner developers, Small business owners, Parents', 'ai-post-scheduler'); ?>">
		<p class="description"><?php esc_html_e('Who your content is written for. Helps the AI tailor the language and depth of generated topics and posts.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the Content Goals field.
	 *
	 * @return void
	 */
	public function site_content_goals_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_site_content_goals');
		?>
		<textarea id="aips_site_content_goals" name="aips_site_content_goals" class="large-text" rows="3" placeholder="<?php esc_attr_e('e.g., Educate readers, Drive product sign-ups, Build a community, Rank on search engines', 'ai-post-scheduler'); ?>"><?php echo esc_textarea($value); ?></textarea>
		<p class="description"><?php esc_html_e('What you want your content to achieve. Informs the angle and call-to-action emphasis in generated content.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the Default Article Structure field.
	 *
	 * @return void
	 */
	public function site_default_article_structure_field_callback() {
		$value      = absint(AIPS_Config::get_instance()->get_option('aips_default_article_structure_id'));
		$repository = new AIPS_Article_Structure_Repository();
		$structures = $repository->get_all(false);
		?>
		<select name="aips_default_article_structure_id">
			<option value="0"><?php esc_html_e('Select an article structure', 'ai-post-scheduler'); ?></option>
			<?php foreach ($structures as $structure) : ?>
				<?php
				$structure_id = isset($structure->id) ? absint($structure->id) : 0;
				$is_active    = true;

				if (isset($structure->is_active)) {
					$is_active = (bool) $structure->is_active;
				}

				$label = $structure->name;
				if (!$is_active) {
					$label .= ' ' . __('(Inactive)', 'ai-post-scheduler');
				}

				$disabled = (!$is_active && $value !== $structure_id) ? ' disabled="disabled"' : '';
				?>
				<option value="<?php echo esc_attr($structure_id); ?>" <?php selected($value, $structure_id); ?><?php echo $disabled; ?>><?php echo esc_html($label); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e('Used as the fallback structure whenever a schedule or generation flow does not specify one explicitly.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the Brand Voice / Tone field.
	 *
	 * @return void
	 */
	public function site_brand_voice_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_site_brand_voice');
		?>
		<input type="text" id="aips_site_brand_voice" name="aips_site_brand_voice" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g., Friendly and approachable, Authoritative, Conversational', 'ai-post-scheduler'); ?>">
		<p class="description"><?php esc_html_e('The overall voice and tone of your brand. Applied as a default across all authors unless overridden per-author.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the Content Language field.
	 *
	 * @return void
	 */
	public function site_content_language_field_callback() {
		$value     = AIPS_Config::get_instance()->get_option('aips_site_content_language');
		$languages = array(
			'en' => __('English', 'ai-post-scheduler'),
			'es' => __('Spanish', 'ai-post-scheduler'),
			'fr' => __('French', 'ai-post-scheduler'),
			'de' => __('German', 'ai-post-scheduler'),
			'it' => __('Italian', 'ai-post-scheduler'),
			'pt' => __('Portuguese', 'ai-post-scheduler'),
			'nl' => __('Dutch', 'ai-post-scheduler'),
			'pl' => __('Polish', 'ai-post-scheduler'),
			'ru' => __('Russian', 'ai-post-scheduler'),
			'ja' => __('Japanese', 'ai-post-scheduler'),
			'ko' => __('Korean', 'ai-post-scheduler'),
			'zh' => __('Chinese (Simplified)', 'ai-post-scheduler'),
			'ar' => __('Arabic', 'ai-post-scheduler'),
			'hi' => __('Hindi', 'ai-post-scheduler'),
			'tr' => __('Turkish', 'ai-post-scheduler'),
			'sv' => __('Swedish', 'ai-post-scheduler'),
			'da' => __('Danish', 'ai-post-scheduler'),
			'fi' => __('Finnish', 'ai-post-scheduler'),
			'nb' => __('Norwegian', 'ai-post-scheduler'),
		);
		?>
		<select name="aips_site_content_language">
			<?php foreach ($languages as $code => $label) : ?>
				<option value="<?php echo esc_attr($code); ?>" <?php selected($value, $code); ?>><?php echo esc_html($label); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e('The primary language for all AI-generated content. Individual authors can override this.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the Content Guidelines field.
	 *
	 * @return void
	 */
	public function site_content_guidelines_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_site_content_guidelines');
		?>
		<textarea id="aips_site_content_guidelines" name="aips_site_content_guidelines" class="large-text" rows="4" placeholder="<?php esc_attr_e('e.g., Always include at least one actionable tip per post. Avoid profanity. Cite sources where possible.', 'ai-post-scheduler'); ?>"><?php echo esc_textarea($value); ?></textarea>
		<p class="description"><?php esc_html_e('General rules and guidelines for all generated content. Included in every generation prompt as hard constraints.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the Excluded Topics (site-wide) field.
	 *
	 * @return void
	 */
	public function site_excluded_topics_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_site_excluded_topics');
		?>
		<textarea id="aips_site_excluded_topics" name="aips_site_excluded_topics" class="large-text" rows="3" placeholder="<?php esc_attr_e('e.g., competitor brand names, controversial political topics, adult content', 'ai-post-scheduler'); ?>"><?php echo esc_textarea($value); ?></textarea>
		<p class="description"><?php esc_html_e('Topics or subjects that should never appear in any generated post or topic suggestion. Applied globally.', 'ai-post-scheduler'); ?></p>
		<?php
	}
}
