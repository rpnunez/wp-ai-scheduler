<?php
/**
 * DevStackTips seeding script for WP AI Scheduler.
 *
 * Usage:
 *   wp eval-file scripts/seed-devstacktips-content.php
 */

if (!defined('ABSPATH')) {
	echo "This script must be run via WP-CLI (wp eval-file).\n";
	exit(1);
}

if (!class_exists('AIPS_Prompt_Section_Repository') ||
	!class_exists('AIPS_Article_Structure_Manager') ||
	!class_exists('AIPS_Article_Structure_Repository') ||
	!class_exists('AIPS_Template_Repository') ||
	!class_exists('AIPS_Authors_Repository') ||
	!class_exists('AIPS_Interval_Calculator')) {
	echo "Required WP AI Scheduler classes were not found. Ensure the plugin is active.\n";
	exit(1);
}

$section_repository = new AIPS_Prompt_Section_Repository();
$structure_manager = new AIPS_Article_Structure_Manager();
$structure_repository = new AIPS_Article_Structure_Repository();
$template_repository = new AIPS_Template_Repository();
$authors_repository = new AIPS_Authors_Repository();
$interval_calculator = new AIPS_Interval_Calculator();

$created = array(
	'sections' => 0,
	'structures' => 0,
	'templates' => 0,
	'authors' => 0,
);

$updated = array(
	'sections' => 0,
	'structures' => 0,
	'templates' => 0,
	'authors' => 0,
);

$mission_statement = 'DevStackTips democratizes high-level engineering knowledge with accurate, security-first, production-ready guidance. Every claim must be verifiable, and every tutorial must include reproducible examples backed by official documentation.';

/**
 * Upsert custom prompt sections used by DevStackTips structures.
 */
$custom_sections = array(
	'citation_requirements' => array(
		'name' => 'Citation Requirements',
		'description' => 'Rules for including official references inline and in a sources section.',
		'content' => 'Every technical claim must include a direct hyperlink to official documentation (RFCs, vendor docs, framework docs, or standards). End the article with an H2 heading named "Sources" and list all references as markdown links.',
	),
	'verification_checklist' => array(
		'name' => 'Verification Checklist',
		'description' => 'Enforces factual accuracy and testability.',
		'content' => 'Before finalizing, verify commands, code output expectations, version assumptions, and security caveats. If an assumption is environment-specific, clearly state it.',
	),
	'security_review' => array(
		'name' => 'Security Review',
		'description' => 'Security-first guidance and defensive coding reminders.',
		'content' => 'Include secure defaults, least privilege, input validation, output escaping, and dependency update guidance where applicable. Explicitly call out attack surfaces and mitigations.',
	),
	'formatting_requirements' => array(
		'name' => 'Formatting Requirements',
		'description' => 'Enforces heading hierarchy and emphasis style.',
		'content' => 'Use H2 and H3 headings for hierarchy. Bold key technical terms and concepts at first mention. Keep paragraphs readable and concise while preserving technical depth.',
	),
);

foreach ($custom_sections as $section_key => $section_data) {
	$existing = $section_repository->get_by_key($section_key);
	$payload = array(
		'name' => $section_data['name'],
		'description' => $section_data['description'],
		'section_key' => $section_key,
		'content' => $section_data['content'],
		'is_active' => 1,
	);

	if ($existing) {
		$section_repository->update($existing->id, $payload);
		$updated['sections']++;
	} else {
		$section_repository->create($payload);
		$created['sections']++;
	}
}

/**
 * Upsert article structures.
 */
$structures = array(
	'DST General Content Structure' => array(
		'description' => 'Approachable but authoritative structure for development pulse, updates, and quick how-to content.',
		'sections' => array('introduction', 'prerequisites', 'steps', 'examples', 'tips', 'formatting_requirements', 'citation_requirements', 'verification_checklist', 'conclusion'),
		'prompt_template' => "Write a development and programming article about {{topic}} for DevStackTips.\n\n{{section:introduction}}\n\nUse at least 5 substantial paragraphs. Keep the tone approachable but authoritative for working developers.\n\n{{section:prerequisites}}\n\n{{section:steps}}\n\n{{section:examples}}\n\nRequirements for this article:\n- Include at least two code samples.\n- Explain each code sample step by step.\n- Focus on current updates, quick tutorials, or practical how-to snippets.\n\n{{section:tips}}\n\n{{section:formatting_requirements}}\n\n{{section:citation_requirements}}\n\n{{section:verification_checklist}}\n\n{{section:conclusion}}",
	),
	'DST Software Guides Structure' => array(
		'description' => 'Expert-level long-form structure for architecture, frameworks, and computer science masterclasses.',
		'sections' => array('introduction', 'prerequisites', 'examples', 'steps', 'tips', 'resources', 'formatting_requirements', 'citation_requirements', 'verification_checklist', 'conclusion'),
		'prompt_template' => "Write a long-form, expert-level DevStackTips guide about {{topic}}.\n\n{{section:introduction}}\n\nMust include:\n- At least 10 substantial paragraphs.\n- An H2 Table of Contents near the top.\n- Deep technical explanations suitable for experienced engineers.\n\nFor algorithm-heavy topics, provide logic-focused snippets and complexity discussion.\nFor practical implementation topics (for example, CRUD workflows), provide exhaustive end-to-end examples.\n\n{{section:prerequisites}}\n\n{{section:examples}}\n\n{{section:steps}}\n\n{{section:tips}}\n\n{{section:resources}}\n\n{{section:formatting_requirements}}\n\n{{section:citation_requirements}}\n\n{{section:verification_checklist}}\n\n{{section:conclusion}}",
	),
	'DST Security Structure' => array(
		'description' => 'Security-centric structure emphasizing vulnerable vs secure implementations and remediation.',
		'sections' => array('introduction', 'examples', 'steps', 'security_review', 'troubleshooting', 'formatting_requirements', 'citation_requirements', 'verification_checklist', 'conclusion'),
		'prompt_template' => "Write a security-focused DevStackTips article about {{topic}}.\n\n{{section:introduction}}\n\nUse at least 5 substantial paragraphs and maintain a defensive engineering perspective throughout.\n\nCore requirements:\n- Show \"The Wrong Way\" first with vulnerable code.\n- Show \"The Secure Way\" with patched code immediately after.\n- Analyze why the first approach is vulnerable and how the patch mitigates risk.\n- Focus on risks such as XSS, SQL Injection, CSRF, MITM, insecure secrets handling, and broken authorization where relevant.\n\n{{section:examples}}\n\n{{section:steps}}\n\n{{section:security_review}}\n\n{{section:troubleshooting}}\n\n{{section:formatting_requirements}}\n\n{{section:citation_requirements}}\n\n{{section:verification_checklist}}\n\n{{section:conclusion}}",
	),
);

$structure_ids = array();

foreach ($structures as $name => $config) {
	$existing = $structure_repository->get_by_name($name);

	if ($existing) {
		$result = $structure_manager->update_structure(
			$existing->id,
			$name,
			$config['sections'],
			$config['prompt_template'],
			$config['description'],
			false,
			true
		);

		if (!is_wp_error($result)) {
			$updated['structures']++;
			$structure_ids[$name] = (int) $existing->id;
		}
	} else {
		$new_id = $structure_manager->create_structure(
			$name,
			$config['sections'],
			$config['prompt_template'],
			$config['description'],
			false,
			true
		);

		if (!is_wp_error($new_id) && $new_id) {
			$created['structures']++;
			$structure_ids[$name] = (int) $new_id;
		}
	}
}

/**
 * Upsert templates.
 */
$templates_by_name = array();
foreach ($template_repository->get_all(false) as $tpl) {
	$templates_by_name[$tpl->name] = $tpl;
}

$templates = array(
	'DevStackTips - General Content' => array(
		'description' => 'Template for development pulse updates, server configuration guides, and quick practical tutorials.',
		'title_prompt' => 'Create a concise, search-friendly title about {{topic}} for developers. Keep it specific, actionable, and under 70 characters when possible.',
		'prompt_template' => "You are writing for DevStackTips. Mission: {$mission_statement}\n\nProduce approachable-but-authoritative content for this topic: {{topic}}.\n\nRequirements:\n1) At least 5 paragraphs.\n2) At least two code samples with step-by-step explanations.\n3) Use H2/H3 headings and bold key technical terms on first mention.\n4) Include direct hyperlinks to official documentation inside the article body.\n5) End with an H2 heading titled Sources and list official references.\n6) Ensure all claims are verifiable and avoid speculation.\n\nContent focus: ecosystem updates (for example WordPress/Joomla releases), web server configuration (Apache/LiteSpeed), and practical best practices.",
		'post_tags' => 'devstacktips,general-content,how-to,web-development',
	),
	'DevStackTips - Software Guides' => array(
		'description' => 'Template for deep-dive evergreen manuals and technical reference content.',
		'title_prompt' => 'Generate a definitive guide title for {{topic}} that sounds authoritative and evergreen for experienced engineers.',
		'prompt_template' => "You are writing an expert-level DevStackTips masterclass. Mission: {$mission_statement}\n\nTopic: {{topic}}\n\nRequirements:\n1) At least 10 paragraphs.\n2) Include a Table of Contents near the top using H2/H3 hierarchy.\n3) Provide extensive code coverage and deep implementation details.\n4) For algorithms, prioritize logic snippets, complexity, and trade-offs.\n5) For practical tasks, provide exhaustive end-to-end examples.\n6) Include direct official documentation hyperlinks inline.\n7) End with an H2 Sources section containing all references.\n8) Bold key technical terms at first mention and keep all claims verifiable.",
		'post_tags' => 'devstacktips,software-guides,architecture,reference',
	),
	'DevStackTips - Security' => array(
		'description' => 'Template for secure coding analysis, vulnerability write-ups, and hardening guides.',
		'title_prompt' => 'Generate a security-focused title for {{topic}} that highlights risk and mitigation in practical terms.',
		'prompt_template' => "You are writing for the DevStackTips Defensive team. Mission: {$mission_statement}\n\nTopic: {{topic}}\n\nRequirements:\n1) At least 5 paragraphs.\n2) Show The Wrong Way (vulnerable code) and The Secure Way (patched code).\n3) Provide critical code analysis of vulnerability root cause and mitigation steps.\n4) Cover secure coding standards relevant to sensitive operations.\n5) Use H2/H3 headings and bold key security terms.\n6) Include direct links to official security documentation/standards inline.\n7) End with an H2 Sources section listing all references.\n8) Ensure every technical claim is verifiable.",
		'post_tags' => 'devstacktips,security,xss,sqli,csrf,secure-coding',
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
		$updated['templates']++;
	} else {
		$template_repository->create($payload);
		$created['templates']++;
	}
}

/**
 * Upsert authors aligned to the three content teams.
 */
$authors_by_name = array();
foreach ($authors_repository->get_all(false) as $author) {
	$authors_by_name[$author->name] = $author;
}

$authors = array(
	'DST General Content Team' => array(
		'field_niche' => 'Development Pulse, CMS updates, server configuration, and practical best practices',
		'article_structure_name' => 'DST General Content Structure',
		'description' => 'Covers software updates, web server setup, and practical development best practices in an approachable but authoritative tone.',
		'keywords' => 'WordPress,Joomla,Apache,LiteSpeed,release notes,configuration,best practices',
		'details' => 'Focuses on actionable tutorials and current ecosystem changes. Must include at least two code samples with clear step-by-step explanations.',
		'topic_generation_prompt' => 'Generate practical, current, and useful programming topics for DevStackTips General Content. Include release updates, server tuning how-tos, and concise implementation guides relevant to working developers.',
		'topic_generation_frequency' => 'weekly',
		'topic_generation_quantity' => 8,
		'post_generation_frequency' => 'daily',
		'post_tags' => 'devstacktips,general-content,quick-guide',
	),
	'DST Software Guides Team' => array(
		'field_niche' => 'Software architecture, frameworks, and computer science deep-dives',
		'article_structure_name' => 'DST Software Guides Structure',
		'description' => 'Produces long-form evergreen manuals and definitive references for advanced engineering topics.',
		'keywords' => 'software architecture,react,laravel,computer science,algorithms,data structures,system design',
		'details' => 'Targets expert-level readers and emphasizes depth, trade-off analysis, and comprehensive code coverage.',
		'topic_generation_prompt' => 'Generate high-value deep-dive guide topics for DevStackTips Software Guides. Prioritize evergreen architecture, framework internals, and computer science concepts with practical implementation value.',
		'topic_generation_frequency' => 'weekly',
		'topic_generation_quantity' => 6,
		'post_generation_frequency' => 'weekly',
		'post_tags' => 'devstacktips,software-guides,masterclass',
	),
	'DST Security Team' => array(
		'field_niche' => 'Application security hardening and secure coding practices',
		'article_structure_name' => 'DST Security Structure',
		'description' => 'Focuses on defensive engineering, exploit analysis, and secure implementation patterns.',
		'keywords' => 'xss,sql injection,csrf,mitm,secure coding,owasp,hardening',
		'details' => 'Every article compares vulnerable vs secure implementations with remediation-focused code analysis.',
		'topic_generation_prompt' => 'Generate security-centric programming topics for DevStackTips. Focus on common vulnerabilities, secure patches, hardening checklists, and attack-surface reduction strategies for production systems.',
		'topic_generation_frequency' => 'weekly',
		'topic_generation_quantity' => 7,
		'post_generation_frequency' => 'weekly',
		'post_tags' => 'devstacktips,security,secure-coding',
	),
);

foreach ($authors as $name => $author_data) {
	$structure_id = isset($structure_ids[$author_data['article_structure_name']]) ? $structure_ids[$author_data['article_structure_name']] : null;
	$topic_next_run = $interval_calculator->calculate_next_run($author_data['topic_generation_frequency']);
	$post_next_run = $interval_calculator->calculate_next_run($author_data['post_generation_frequency']);

	$payload = array(
		'name' => $name,
		'field_niche' => $author_data['field_niche'],
		'description' => $author_data['description'],
		'keywords' => $author_data['keywords'],
		'details' => $author_data['details'],
		'article_structure_id' => $structure_id,
		'topic_generation_prompt' => $author_data['topic_generation_prompt'],
		'topic_generation_frequency' => $author_data['topic_generation_frequency'],
		'topic_generation_quantity' => $author_data['topic_generation_quantity'],
		'topic_generation_next_run' => $topic_next_run,
		'post_generation_frequency' => $author_data['post_generation_frequency'],
		'post_generation_next_run' => $post_next_run,
		'post_status' => 'draft',
		'post_category' => null,
		'post_tags' => $author_data['post_tags'],
		'post_author' => get_current_user_id(),
		'generate_featured_image' => 0,
		'featured_image_source' => 'ai_prompt',
		'voice_tone' => 'Authoritative, precise, and developer-first',
		'writing_style' => 'Technical tutorial with practical examples',
		'is_active' => 1,
	);

	if (isset($authors_by_name[$name])) {
		$existing_author = $authors_by_name[$name];

		if (!empty($existing_author->topic_generation_next_run)) {
			$payload['topic_generation_next_run'] = $existing_author->topic_generation_next_run;
		}

		if (!empty($existing_author->post_generation_next_run)) {
			$payload['post_generation_next_run'] = $existing_author->post_generation_next_run;
		}

		$authors_repository->update((int) $existing_author->id, $payload);
		$updated['authors']++;
	} else {
		$authors_repository->create($payload);
		$created['authors']++;
	}
}

$summary = sprintf(
	"DevStackTips seed completed. Created: sections=%d, structures=%d, templates=%d, authors=%d. Updated: sections=%d, structures=%d, templates=%d, authors=%d.",
	$created['sections'],
	$created['structures'],
	$created['templates'],
	$created['authors'],
	$updated['sections'],
	$updated['structures'],
	$updated['templates'],
	$updated['authors']
);

if (class_exists('WP_CLI')) {
	WP_CLI::success($summary);
} else {
	echo $summary . "\n";
}
