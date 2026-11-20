<?php
/**
 * AI payload capture: scenario fixtures.
 *
 * Each scenario exercises a different combination of prompt builders through
 * the real AIPS_Generator, using in-memory fixture objects the same way the
 * plugin's own PHPUnit suite does (see tests/Test_AIPS_Generator_Conversational.php)
 * rather than persisted Template/Voice/Author rows. Nothing here is specific to
 * any one AI backend: the fixtures only shape prompts, the active provider does
 * the rest.
 *
 * Dev tooling only: loaded by bin/ai-payload-capture.php, never by the plugin.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Build the ordered list of capture scenarios.
 *
 * Each entry:
 *   label         Human-readable name shown in the report.
 *   description   What this scenario is meant to reveal.
 *   method        'generate_post' or 'generate_preview' (AIPS_Generator method to call).
 *   options       Site options to set for the duration of the run (restored after).
 *   build         Callable(): array{context: AIPS_Generation_Context, cleanup?: callable}
 *                 May throw to skip the scenario (recorded, not fatal to the run).
 *
 * @return array<string,array>
 */
function aips_capture_scenarios() {
	return array(

		'template_basic' => array(
			'label'       => 'Template-based post (content, title, excerpt, slug)',
			'description' => 'A plain template with no voice override, no article structure, and no featured image — the baseline path through AIPS_Prompt_Builder_Post_Content, _Post_Title, _Post_Excerpt and _Post_Slug.',
			'method'      => 'generate_post',
			// Force conversational generation off even if the site's own admin
			// settings have it enabled, so this scenario reliably exercises the
			// separate title/excerpt/slug prompt paths it is here to show
			// rather than silently collapsing into the combined metadata turn.
			'options'     => array(
				'aips_conversational_generation'    => 0,
				'aips_conversational_metadata_turn' => 0,
			),
			'build'       => function () {
				$template = (object) array(
					'id'                      => 900001,
					'name'                    => 'Capture: Basic Template',
					'prompt_template'         => "Write a helpful, factual blog post about {{topic}} for a general audience.\nInclude at least two sections with H2 headings and a short practical takeaway at the end.",
					'title_prompt'            => 'Write a single engaging, SEO-friendly title for a post about {{topic}}. Return only the title text.',
					'slug_prompt'             => '',
					'generate_slug'           => 1,
					'image_prompt'            => '',
					'generate_featured_image' => 0,
					'featured_image_source'   => 'ai_prompt',
					'post_status'             => 'draft',
					'post_type'               => 'post',
					'post_category'           => '',
					'post_tags'               => '',
					'post_author'             => 1,
					'article_structure_id'    => null,
					'campaign_id'             => 0,
					'include_sources'         => 0,
					'source_group_ids'        => '',
					'affiliate_links_enabled' => 0,
					'post_images'             => array(),
				);

				return array(
					'context' => new AIPS_Template_Context($template, null, 'Composting for small urban gardens', 'manual'),
				);
			},
		),

		'template_voice_structure' => array(
			'label'       => 'Template + voice + article structure + AI-prompt featured image',
			'description' => 'A voice override (AIPS_Prompt_Builder_Post_Content/_Title/_Excerpt voice branch), a real AIPS_Article_Structure_Manager structure (AIPS_Prompt_Builder_Article_Structure_Section), and an AI-generated featured image (AIPS_Prompt_Builder_Post_Featured_Image + AIPS_Image_Service). Creates two prompt_sections rows and one article_structures row for the duration of the run, then deletes them.',
			'method'      => 'generate_post',
			// Force conversational generation off even if the site's own admin
			// settings have it enabled, so this scenario reliably exercises the
			// separate title/excerpt/slug prompt paths it is here to show
			// rather than silently collapsing into the combined metadata turn.
			'options'     => array(
				'aips_conversational_generation'    => 0,
				'aips_conversational_metadata_turn' => 0,
			),
			'build'       => function () {
				$section_repo = new AIPS_Prompt_Section_Repository();
				$manager      = new AIPS_Article_Structure_Manager();

				$intro_id = $section_repo->create(array(
					'name'        => 'Capture Intro',
					'section_key' => 'aips_capture_intro',
					'content'     => 'Open with a short, concrete hook about {{topic}} — a specific scenario the reader will recognize.',
					'is_active'   => 1,
				));
				$body_id = $section_repo->create(array(
					'name'        => 'Capture Body',
					'section_key' => 'aips_capture_body',
					'content'     => 'Explain the core idea in 2-3 short paragraphs, referencing {{topic}} directly and ending with one actionable tip.',
					'is_active'   => 1,
				));

				if (!$intro_id || !$body_id) {
					throw new RuntimeException('Could not create prompt_sections fixture rows.');
				}

				$structure_id = $manager->create_structure(
					'AIPS Capture Test Structure',
					array('aips_capture_intro', 'aips_capture_body'),
					"{{section:aips_capture_intro}}\n\n{{section:aips_capture_body}}",
					'Temporary structure created by bin/ai-payload-capture.php'
				);

				if (is_wp_error($structure_id)) {
					$section_repo->delete($intro_id);
					$section_repo->delete($body_id);
					throw new RuntimeException('Could not create article_structures fixture row: ' . $structure_id->get_error_message());
				}

				$voice = (object) array(
					'id'                    => 900101,
					'name'                  => 'Capture Voice: Encouraging Mentor',
					'title_prompt'          => 'Write a single punchy, curiosity-driven title about {{topic}}, in the voice of an encouraging mentor. Return only the title text.',
					'content_instructions'  => 'Write in a warm, encouraging voice, as if mentoring a beginner. Prefer short sentences and plain language.',
					'excerpt_instructions'  => 'Summarize the article in exactly two warm, encouraging sentences.',
					'slug_prompt'           => '',
				);

				$template = (object) array(
					'id'                      => 900102,
					'name'                    => 'Capture: Voice + Structure Template',
					'prompt_template'         => 'This text is replaced by the article structure; it is kept here only as a legacy fallback.',
					'title_prompt'            => 'Write a single engaging title for a post about {{topic}}. Return only the title text.',
					'slug_prompt'             => '',
					'generate_slug'           => 1,
					'image_prompt'            => 'A bright, inviting editorial photograph illustrating {{topic}}, suitable as a blog post header image.',
					'generate_featured_image' => 1,
					'featured_image_source'   => 'ai_prompt',
					'post_status'             => 'draft',
					'post_type'               => 'post',
					'post_category'           => '',
					'post_tags'               => '',
					'post_author'             => 1,
					'article_structure_id'    => $structure_id,
					'campaign_id'             => 0,
					'include_sources'         => 0,
					'source_group_ids'        => '',
					'affiliate_links_enabled' => 0,
					'post_images'             => array(),
				);

				return array(
					'context' => new AIPS_Template_Context($template, $voice, 'Companion planting basics', 'manual'),
					'cleanup' => function () use ($manager, $section_repo, $intro_id, $body_id, $structure_id) {
						$manager->delete_structure($structure_id);
						$section_repo->delete($intro_id);
						$section_repo->delete($body_id);
					},
				);
			},
		),

		'topic_based' => array(
			'label'       => 'Author/topic post (no template)',
			'description' => 'An AIPS_Topic_Context (author + topic pair, the Author Topics pipeline) rather than a template — a different get_content_prompt()/get_title_prompt() implementation.',
			'method'      => 'generate_post',
			// Force conversational generation off even if the site's own admin
			// settings have it enabled, so this scenario reliably exercises the
			// separate title/excerpt/slug prompt paths it is here to show
			// rather than silently collapsing into the combined metadata turn.
			'options'     => array(
				'aips_conversational_generation'    => 0,
				'aips_conversational_metadata_turn' => 0,
			),
			'build'       => function () {
				$author = (object) array(
					'id'                      => 900201,
					'name'                    => 'Capture Author',
					'field_niche'             => 'Home Automation',
					'voice_tone'              => 'confident and practical',
					'writing_style'           => 'short paragraphs, numbered steps where helpful',
					'slug_prompt'             => '',
					'generate_slug'           => 0,
					'image_prompt'            => '',
					'generate_featured_image' => 0,
					'featured_image_source'   => 'ai_prompt',
					'post_status'             => 'draft',
					'post_type'               => 'post',
					'post_category'           => '',
					'post_tags'               => '',
					'post_author'             => 1,
					'article_structure_id'    => null,
					'include_sources'         => 0,
					'source_group_ids'        => '',
					'affiliate_links_enabled' => 0,
					'post_images'             => array(),
				);

				$topic = (object) array(
					'id'          => 900202,
					'topic_title' => 'Setting up a smart thermostat schedule',
				);

				return array(
					'context' => new AIPS_Topic_Context($author, $topic, '', 'manual'),
				);
			},
		),

		'conversational_metadata_turn' => array(
			'label'       => 'Conversational generation + combined metadata turn',
			'description' => 'aips_conversational_generation + aips_conversational_metadata_turn both on: the title/excerpt/slug/image-prompt/AI-variable follow-up prompts omit the article body (it is replayed as conversation history instead) and are collapsed into one structured JSON call against a schema built by AIPS_Prompt_Builder_Post_Metadata.',
			'method'      => 'generate_post',
			'options'     => array(
				'aips_conversational_generation'    => 1,
				'aips_conversational_metadata_turn' => 1,
			),
			'build'       => function () {
				$template = (object) array(
					'id'                      => 900301,
					'name'                    => 'Capture: Conversational Metadata Turn',
					'prompt_template'         => "Write a concise, practical blog post about {{topic}}.\nKeep it under 400 words and include one H2 heading.",
					'title_prompt'            => 'Write a single engaging title for a post about {{topic}}. Return only the title text.',
					'slug_prompt'             => '',
					'generate_slug'           => 1,
					'image_prompt'            => 'A clean, modern illustration representing {{topic}}.',
					'generate_featured_image' => 1,
					'featured_image_source'   => 'ai_prompt',
					'post_status'             => 'draft',
					'post_type'               => 'post',
					'post_category'           => '',
					'post_tags'               => '',
					'post_author'             => 1,
					'article_structure_id'    => null,
					'campaign_id'             => 0,
					'include_sources'         => 0,
					'source_group_ids'        => '',
					'affiliate_links_enabled' => 0,
					'post_images'             => array(),
				);

				return array(
					'context' => new AIPS_Template_Context($template, null, 'Reducing standby power use at home', 'manual'),
				);
			},
		),

		'post_content_images' => array(
			'label'       => 'In-body content images',
			'description' => 'A template with two configured content images — the "Image Placement Instructions" block AIPS_Prompt_Builder_Post_Content injects into the content prompt, and the per-image prompt AIPS_Generator::resolve_post_image() builds afterward.',
			'method'      => 'generate_post',
			// Force conversational generation off even if the site's own admin
			// settings have it enabled, so this scenario reliably exercises the
			// separate title/excerpt/slug prompt paths it is here to show
			// rather than silently collapsing into the combined metadata turn.
			'options'     => array(
				'aips_conversational_generation'    => 0,
				'aips_conversational_metadata_turn' => 0,
			),
			'build'       => function () {
				$template = (object) array(
					'id'                      => 900401,
					'name'                    => 'Capture: Content Images Template',
					'prompt_template'         => "Write a step-by-step blog post about {{topic}} with at least two distinct sections.",
					'title_prompt'            => 'Write a single engaging title for a post about {{topic}}. Return only the title text.',
					'slug_prompt'             => '',
					'generate_slug'           => 0,
					'image_prompt'            => '',
					'generate_featured_image' => 0,
					'featured_image_source'   => 'ai_prompt',
					'post_status'             => 'draft',
					'post_type'               => 'post',
					'post_category'           => '',
					'post_tags'               => '',
					'post_author'             => 1,
					'article_structure_id'    => null,
					'campaign_id'             => 0,
					'include_sources'         => 0,
					'source_group_ids'        => '',
					'affiliate_links_enabled' => 0,
					'post_images'             => array(
						(object) array(
							'id'           => 1,
							'image_order'  => 1,
							'source'       => 'ai_prompt',
							'placement'    => 'auto',
							'image_prompt' => 'A simple labeled diagram illustrating the first step of {{topic}}.',
							'caption'      => '',
						),
						(object) array(
							'id'           => 2,
							'image_order'  => 2,
							'source'       => 'ai_prompt',
							'placement'    => 'auto',
							'image_prompt' => 'A photograph showing the finished result of {{topic}}.',
							'caption'      => '',
						),
					),
				);

				return array(
					'context' => new AIPS_Template_Context($template, null, 'Weatherproofing a wooden garden bench', 'manual'),
				);
			},
		),

	);
}
