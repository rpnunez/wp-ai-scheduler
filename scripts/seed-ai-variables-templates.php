<?php
/**
 * AI Variables Templates seeding script for WP AI Scheduler.
 *
 * Creates 3 demonstration Templates that use AI Variables in both the Title
 * Prompt and the Content Prompt. AI Variables are custom {{VariableName}}
 * placeholders (not in the built-in system variable list) that are resolved
 * dynamically by the AI Engine before the main prompt is sent.
 *
 * System variables (not AI variables): {{date}}, {{year}}, {{month}},
 * {{day}}, {{time}}, {{site_name}}, {{site_description}},
 * {{random_number}}, {{topic}}, {{title}}.
 *
 * Any other {{VariableName}} found in a template is treated as an AI Variable.
 *
 * Usage:
 *   wp eval-file scripts/seed-ai-variables-templates.php
 */

if (!defined('ABSPATH')) {
	echo "This script must be run via WP-CLI (wp eval-file).\n";
	exit(1);
}

if (!class_exists('AIPS_Template_Repository')) {
	echo "Required WP AI Scheduler class AIPS_Template_Repository was not found. Ensure the plugin is active.\n";
	exit(1);
}

$template_repository = new AIPS_Template_Repository();

$created = 0;
$updated = 0;

// Pre-load existing templates keyed by name for upsert logic.
$templates_by_name = array();
foreach ($template_repository->get_all(false) as $tpl) {
	$templates_by_name[$tpl->name] = $tpl;
}

/**
 * Template definitions.
 *
 * Each template uses AI Variables (custom {{VariableName}} placeholders) in
 * both the title_prompt and prompt_template fields so the AI Variables
 * feature can be tested end-to-end.
 *
 * AI Variables used:
 *   Template 1 – Product Comparison: {{ProductA}}, {{ProductB}}, {{ComparisonCriteria}}
 *   Template 2 – Expert Tutorial:    {{ExpertRole}}, {{SkillLevel}}, {{TechStack}}, {{UseCase}}
 *   Template 3 – Trend Analysis:     {{TrendName}}, {{Industry}}, {{KeyInsight}}, {{FutureImpact}}
 */
$templates = array(
	'AI Variables Demo - Product Comparison' => array(
		'description' => 'Demonstrates AI Variables by generating a side-by-side comparison article. The AI resolves {{ProductA}}, {{ProductB}}, and {{ComparisonCriteria}} before writing the post.',
		'title_prompt' => 'Write a concise, search-friendly comparison title for {{topic}} that pits {{ProductA}} against {{ProductB}} and hints at the {{ComparisonCriteria}} angle. Keep it under 70 characters.',
		'prompt_template' => "Write a detailed comparison article about {{topic}}.\n\nThe article compares {{ProductA}} and {{ProductB}} focusing on {{ComparisonCriteria}}.\n\nStructure:\n1. Introduction – why {{ComparisonCriteria}} matters when choosing between {{ProductA}} and {{ProductB}}.\n2. Overview of {{ProductA}} – strengths, weaknesses, and best use-cases.\n3. Overview of {{ProductB}} – strengths, weaknesses, and best use-cases.\n4. Head-to-head analysis across key {{ComparisonCriteria}} dimensions.\n5. Recommendation – which tool wins for which audience.\n6. Conclusion.\n\nRequirements:\n- At least 6 paragraphs.\n- Use H2 headings for each section.\n- Bold key differences at first mention.\n- Keep the tone neutral, evidence-based, and developer-friendly.",
		'post_tags' => 'ai-variables-demo,comparison,product-review',
	),
	'AI Variables Demo - Expert Tutorial' => array(
		'description' => 'Demonstrates AI Variables in a persona-driven tutorial. The AI resolves {{ExpertRole}}, {{SkillLevel}}, {{TechStack}}, and {{UseCase}} to tailor the content before writing.',
		'title_prompt' => 'Create a how-to tutorial title for {{topic}} written from the perspective of a {{ExpertRole}}. Target the {{SkillLevel}} audience and reference {{TechStack}} in the title.',
		'prompt_template' => "You are a {{ExpertRole}} writing a tutorial for a {{SkillLevel}} audience.\n\nWrite a step-by-step tutorial about {{topic}} using {{TechStack}}.\n\nThe tutorial should guide the reader through a realistic {{UseCase}} scenario.\n\nStructure:\n1. Introduction – context and what the reader will achieve.\n2. Prerequisites – tools, versions, and assumed knowledge for a {{SkillLevel}} developer.\n3. Step-by-step implementation using {{TechStack}}.\n4. Working code example for the {{UseCase}}.\n5. Common pitfalls and how to avoid them.\n6. Next steps and further resources.\n\nRequirements:\n- At least 6 paragraphs.\n- At least two complete code samples with inline comments.\n- Use H2 for major sections and H3 for sub-steps.\n- Tone should match a {{ExpertRole}} explaining to a {{SkillLevel}} peer.",
		'post_tags' => 'ai-variables-demo,tutorial,how-to',
	),
	'AI Variables Demo - Trend Analysis' => array(
		'description' => 'Demonstrates AI Variables in a trend-analysis article. The AI resolves {{TrendName}}, {{Industry}}, {{KeyInsight}}, and {{FutureImpact}} before drafting the post.',
		'title_prompt' => 'Write a forward-looking trend title about {{topic}} that spotlights the {{TrendName}} shift in {{Industry}} and hints at the {{FutureImpact}}. Keep it under 80 characters.',
		'prompt_template' => "Write an in-depth trend analysis article about {{topic}} for professionals in the {{Industry}} sector.\n\nFocus on the {{TrendName}} movement and unpack the following key insight: {{KeyInsight}}.\n\nStructure:\n1. Executive summary – what {{TrendName}} is and why it matters in {{Industry}} right now.\n2. Background – how {{Industry}} arrived at this inflection point.\n3. Deep-dive into {{KeyInsight}} with supporting data and real-world examples.\n4. Opportunities and risks created by {{TrendName}} for teams and organizations in {{Industry}}.\n5. Future outlook – {{FutureImpact}} and what practitioners should do today.\n6. Conclusion with actionable takeaways.\n\nRequirements:\n- At least 7 substantial paragraphs.\n- Use H2 headings for each section.\n- Bold key terminology and trend-related concepts at first mention.\n- Keep the tone analytical, authoritative, and jargon-aware (explain terms for a mixed audience).",
		'post_tags' => 'ai-variables-demo,trend-analysis,industry-insights',
	),
);

foreach ($templates as $name => $template_data) {
	$payload = array(
		'name' => $name,
		'description' => $template_data['description'],
		'prompt_template' => $template_data['prompt_template'],
		'title_prompt' => $template_data['title_prompt'],
		'voice_id' => null,
		'post_quantity' => 1,
		'image_prompt' => '',
		'generate_featured_image' => 0,
		'featured_image_source' => 'ai_prompt',
		'featured_image_unsplash_keywords' => '',
		'featured_image_media_ids' => '',
		'post_status' => 'draft',
		'post_category' => 0,
		'post_tags' => $template_data['post_tags'],
		'post_author' => get_current_user_id(),
		'is_active' => 1,
	);

	if (isset($templates_by_name[$name])) {
		$template_repository->update((int) $templates_by_name[$name]->id, $payload);
		$updated++;
	} else {
		$template_repository->create($payload);
		$created++;
	}
}

$summary = sprintf(
	"AI Variables Templates seed completed. Created: %d template(s). Updated: %d template(s).",
	$created,
	$updated
);

if (class_exists('WP_CLI')) {
	WP_CLI::success($summary);
} else {
	echo $summary . "\n";
}
