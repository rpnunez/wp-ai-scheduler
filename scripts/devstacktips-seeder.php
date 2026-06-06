#!/usr/bin/env php
<?php
/**
 * DevStackTips Production Configuration Seeder
 *
 * Creates all WordPress categories, plugin Voices, Structure Sections,
 * Article Structures, standalone Templates, and Campaigns (with owned
 * templates + schedules) defined in:
 *   docs/features/templates/DEVSTACKTIPS_PRODUCTION_CONFIGURATION_GUIDE.md
 *
 * Usage (run from WordPress root):
 *   php wp-content/plugins/ai-post-scheduler/../../scripts/devstacktips-seeder.php
 *
 * Or with an explicit WordPress root:
 *   WP_ROOT=/var/www/html php scripts/devstacktips-seeder.php
 *
 * The script is idempotent: existing items (matched by name or section key)
 * are skipped, not duplicated.
 */

// ─── Bootstrap ───────────────────────────────────────────────────────────────

define('AIPS_SEEDER_CLI', true);

$wp_root = getenv('WP_ROOT') ?: '';

if (empty($wp_root)) {
    // Walk up from this script's location to find wp-load.php
    $dir = realpath(__DIR__);
    for ($i = 0; $i < 8; $i++) {
        if (file_exists($dir . '/wp-load.php')) {
            $wp_root = $dir;
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
}

if (empty($wp_root) || !file_exists($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "ERROR: Could not locate wp-load.php.\n");
    fwrite(STDERR, "Set the WP_ROOT environment variable to your WordPress installation path.\n");
    fwrite(STDERR, "Example: WP_ROOT=/var/www/html php scripts/devstacktips-seeder.php\n");
    exit(1);
}

$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';

require_once $wp_root . '/wp-load.php';

if (!defined('ABSPATH')) {
    fwrite(STDERR, "ERROR: WordPress failed to load.\n");
    exit(1);
}

if (!defined('AIPS_VERSION')) {
    fwrite(STDERR, "ERROR: AI Post Scheduler plugin is not active. Activate the plugin first.\n");
    exit(1);
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function seeder_log(string $msg, string $colour = 'default'): void {
    $codes = ['green' => '32', 'yellow' => '33', 'red' => '31', 'cyan' => '36', 'bold' => '1', 'default' => '0'];
    $code  = $codes[$colour] ?? '0';
    echo "\033[{$code}m{$msg}\033[0m\n";
}

function seeder_skip(string $label): void  { seeder_log("  ↩ skip   {$label}", 'yellow'); }
function seeder_ok(string $label): void    { seeder_log("  ✓ create {$label}", 'green'); }
function seeder_err(string $label): void   { seeder_log("  ✗ error  {$label}", 'red'); }
function seeder_head(string $label): void  { seeder_log("\n── {$label} ──", 'bold'); }

// ─── Data definitions ─────────────────────────────────────────────────────────

$categories = [
    ['name' => 'Foundations',        'slug' => 'foundations',        'description' => 'Entry-level tutorials and how-to guides for core developer skills.'],
    ['name' => 'Backend Engineering','slug' => 'backend-engineering', 'description' => 'Intermediate-to-advanced backend implementation patterns.'],
    ['name' => 'Security',           'slug' => 'security',           'description' => 'Practical security hardening, threat models, and secure coding.'],
    ['name' => 'Architecture & Scale','slug' => 'architecture-scale','description' => 'System design, scalability patterns, and reliability engineering.'],
    ['name' => 'Comparisons',        'slug' => 'comparisons',        'description' => 'Framework, tool, and technology decision guides.'],
    ['name' => 'Developer Tooling',  'slug' => 'developer-tooling',  'description' => 'Git, Docker, CI/CD, and local development workflows.'],
    ['name' => 'AI for Developers',  'slug' => 'ai-for-developers',  'description' => 'Practical AI usage, review loops, and governance for engineers.'],
    ['name' => 'Industry Trends',    'slug' => 'industry-trends',    'description' => 'Timely ecosystem analysis and developer-relevant commentary.'],
];

$voices = [
    [
        'name'                 => 'DevStackTips Default',
        'title_prompt'         => "Create a concise, technically precise title for {{topic}}.\nPrioritize clarity and practical intent over hype.\nAvoid clickbait words and avoid generic \"ultimate\" phrasing.",
        'content_instructions' => "Write for working software engineers.\nBe practical, concrete, and concise.\nUse clear headings and short paragraphs.\nInclude real implementation detail, tradeoffs, and pitfalls.\nPrefer examples over abstract definitions.\nDo not use hype, fluff, or AI self-references.",
        'excerpt_instructions' => 'Summarize in 1-2 sentences with practical value and clear scope.',
        'is_active'            => 1,
    ],
    [
        'name'                 => 'Senior Backend Mentor',
        'title_prompt'         => 'Generate a title that signals depth and engineering tradeoffs for {{topic}}.',
        'content_instructions' => "Teach through reasoning, not just instructions.\nExplain why decisions are made and what can go wrong.\nHighlight maintainability, reliability, and operational impact.\nInclude design tradeoffs and failure modes.",
        'excerpt_instructions' => 'State the core decision/tradeoff and who should care.',
        'is_active'            => 1,
    ],
    [
        'name'                 => 'Hands-On Tutorial Coach',
        'title_prompt'         => 'Generate a tutorial-style title for {{topic}} with clear outcome language.',
        'content_instructions' => "Teach in sequence from prerequisites to verification.\nUse numbered steps and concrete examples.\nAssume reader will implement immediately.\nInclude command/code snippets where relevant.",
        'excerpt_instructions' => 'Describe what the reader will build/do and expected result.',
        'is_active'            => 1,
    ],
    [
        'name'                 => 'Neutral Technical Analyst',
        'title_prompt'         => 'Create a neutral, analytical title for {{topic}} with no hype or bias.',
        'content_instructions' => "Compare options fairly.\nUse explicit criteria and structured sections.\nAvoid universal winners; choose by context.\nInclude migration/operational constraints.",
        'excerpt_instructions' => 'Summarize comparison criteria and best-fit audience for each option.',
        'is_active'            => 1,
    ],
    [
        'name'                 => 'AI Engineering Editor',
        'title_prompt'         => 'Generate a current, practical title for {{topic}} focused on real engineering outcomes.',
        'content_instructions' => "Prioritize practical AI workflows over hype.\nAddress accuracy risks, review loops, and governance.\nDiscuss where AI helps and where manual review is mandatory.\nUse concrete developer use cases.",
        'excerpt_instructions' => 'Summarize the practical workflow and risk controls in one tight paragraph.',
        'is_active'            => 1,
    ],
];

$sections = [
    ['name' => 'Why This Matters',         'section_key' => 'why_this_matters',         'description' => 'Word target: 80–120',  'content' => 'Explain why {{topic}} matters for real developer outcomes. Mention one concrete production impact.'],
    ['name' => 'Learning Objectives',      'section_key' => 'learning_objectives',      'description' => 'Word target: 60–90',   'content' => 'List what the reader will be able to do after this article.'],
    ['name' => 'Prerequisites',            'section_key' => 'prerequisites',            'description' => 'Word target: 80–120',  'content' => 'State required knowledge, environment, and tools before implementation.'],
    ['name' => 'Key Concepts',             'section_key' => 'key_concepts',             'description' => 'Word target: 140–220', 'content' => 'Define the essential concepts for {{topic}} with concise, technical explanations.'],
    ['name' => 'Step-by-Step Instructions','section_key' => 'step_by_step',             'description' => 'Word target: 350–700', 'content' => 'Provide ordered implementation steps. Each step should have action + expected result.'],
    ['name' => 'Code Example',             'section_key' => 'code_example',             'description' => 'Word target: 180–320', 'content' => 'Provide a practical code sample for {{topic}} and explain the important lines.'],
    ['name' => 'Config Example',           'section_key' => 'config_example',           'description' => 'Word target: 120–240', 'content' => 'Provide a production-like configuration example and explain each critical setting.'],
    ['name' => 'Common Mistakes',          'section_key' => 'common_mistakes',          'description' => 'Word target: 120–200', 'content' => 'List common implementation errors and how to avoid each one.'],
    ['name' => 'Validation Check',         'section_key' => 'validation_check',         'description' => 'Word target: 100–170', 'content' => 'Give a quick checklist/commands to verify the implementation is correct.'],
    ['name' => 'Next Steps',               'section_key' => 'next_steps',               'description' => 'Word target: 80–130',  'content' => 'Suggest practical next improvements after completing this implementation.'],
    ['name' => 'Problem Statement',        'section_key' => 'problem_statement',        'description' => 'Word target: 100–160', 'content' => 'Define the exact problem {{topic}} solves and constraints that matter.'],
    ['name' => 'Technical Context',        'section_key' => 'technical_context',        'description' => 'Word target: 120–220', 'content' => 'Describe the architecture/runtime context needed to understand this solution.'],
    ['name' => 'Implementation Plan',      'section_key' => 'implementation_plan',      'description' => 'Word target: 120–180', 'content' => 'Present a phased implementation plan with clear milestones.'],
    ['name' => 'Performance Considerations','section_key' => 'performance_considerations','description' => 'Word target: 120–220','content' => 'Explain latency/throughput/resource impacts and practical tuning levers.'],
    ['name' => 'Security Considerations',  'section_key' => 'security_considerations',  'description' => 'Word target: 120–220', 'content' => 'Explain relevant threats and required secure implementation controls.'],
    ['name' => 'Testing & Validation',     'section_key' => 'testing_validation',       'description' => 'Word target: 120–220', 'content' => 'Provide test strategy (unit/integration/manual) for verifying behavior and regressions.'],
    ['name' => 'Operational Runbook',      'section_key' => 'operational_runbook',      'description' => 'Word target: 140–240', 'content' => 'Provide day-2 operations guidance: monitoring, alerting, rollback, incident handling.'],
    ['name' => 'Decision Criteria',        'section_key' => 'decision_criteria',        'description' => 'Word target: 120–200', 'content' => 'Define objective criteria used to compare options for {{topic}}.'],
    ['name' => 'Pros/Cons Matrix',         'section_key' => 'pros_cons_matrix',         'description' => 'Word target: 140–240', 'content' => 'Present strengths/weaknesses in a structured matrix or equivalent bullet format.'],
    ['name' => 'Recommendation by Scenario','section_key' => 'recommendation_by_scenario','description' => 'Word target: 120–220','content' => 'Recommend best option by scenario (team size, scale, constraints).'],
    ['name' => 'Threat Model',             'section_key' => 'threat_model',             'description' => 'Word target: 120–200', 'content' => 'Identify realistic attack vectors and assets at risk for {{topic}}.'],
    ['name' => 'Security Checklist',       'section_key' => 'security_checklist',       'description' => 'Word target: 100–180', 'content' => 'Provide a concise checklist to validate secure implementation before shipping.'],
];

// Build a prompt_template string from an ordered list of section keys.
function build_prompt_template(array $section_keys): string {
    return implode("\n\n", array_map(fn($k) => "{{section:{$k}}}", $section_keys));
}

$article_structures = [
    [
        'name'        => 'Evergreen How-To Guide',
        'description' => 'Tutorials and foundational topics.',
        'sections'    => ['why_this_matters','learning_objectives','prerequisites','key_concepts','step_by_step','code_example','common_mistakes','validation_check','next_steps'],
        'is_active'   => 1,
    ],
    [
        'name'        => 'Advanced Technical Tutorial',
        'description' => 'Deeper backend, security, and DevOps implementation guides.',
        'sections'    => ['problem_statement','technical_context','prerequisites','implementation_plan','step_by_step','config_example','performance_considerations','security_considerations','testing_validation','operational_runbook'],
        'is_active'   => 1,
    ],
    [
        'name'        => 'Comparison Article',
        'description' => '"X vs Y" decision content.',
        'sections'    => ['problem_statement','key_concepts','decision_criteria','pros_cons_matrix','performance_considerations','recommendation_by_scenario'],
        'is_active'   => 1,
    ],
    [
        'name'        => 'Architecture Deep Dive',
        'description' => 'Systems design, scaling, and reliability.',
        'sections'    => ['problem_statement','technical_context','implementation_plan','step_by_step','performance_considerations','security_considerations','testing_validation','operational_runbook'],
        'is_active'   => 1,
    ],
    [
        'name'        => 'Security Best Practices',
        'description' => 'Security-focused implementation guides.',
        'sections'    => ['threat_model','why_this_matters','security_considerations','config_example','code_example','testing_validation','operational_runbook','security_checklist'],
        'is_active'   => 1,
    ],
    [
        'name'        => 'Tool / Workflow Explainer',
        'description' => 'Git, Composer, Docker, CI, SSH, and related workflow guides.',
        'sections'    => ['why_this_matters','prerequisites','key_concepts','step_by_step','code_example','common_mistakes','next_steps'],
        'is_active'   => 1,
    ],
    [
        'name'        => 'AI-for-Devs Article',
        'description' => 'AI workflow and process guides for engineers.',
        'sections'    => ['problem_statement','key_concepts','decision_criteria','step_by_step','security_considerations','testing_validation','recommendation_by_scenario'],
        'is_active'   => 1,
    ],
    [
        'name'        => 'News / Trend Analysis',
        'description' => 'Timely ecosystem analysis.',
        'sections'    => ['problem_statement','key_concepts','decision_criteria','performance_considerations','security_considerations','recommendation_by_scenario','next_steps'],
        'is_active'   => 1,
    ],
];

// Templates: voice_name and structure_name are resolved to IDs after creation.
// category_slug is resolved to a WP term ID.
$templates = [
    [
        'name'             => 'Dev Foundations: Beginner How-To',
        'prompt_template'  => "Write an implementation-first tutorial about {{topic}} for software developers.\nUse prerequisites, steps, validation checks, and common mistakes.\nInclude at least one practical command/code example.",
        'voice_name'       => 'Hands-On Tutorial Coach',
        'structure_name'   => 'Evergreen How-To Guide',
        'category_slug'    => 'foundations',
        'post_status'      => 'draft',
        'post_type'        => 'post',
        'post_quantity'    => 1,
        'is_active'        => 1,
    ],
    [
        'name'             => 'Backend Engineering: Intermediate Tutorial',
        'prompt_template'  => "Write a production-oriented backend engineering tutorial on {{topic}}.\nCover implementation strategy, tradeoffs, performance, and validation.",
        'voice_name'       => 'DevStackTips Default',
        'structure_name'   => 'Advanced Technical Tutorial',
        'category_slug'    => 'backend-engineering',
        'post_status'      => 'draft',
        'post_type'        => 'post',
        'post_quantity'    => 1,
        'is_active'        => 1,
    ],
    [
        'name'             => 'Security First Guide',
        'prompt_template'  => "Write a security-first guide for {{topic}}.\nInclude threat model, secure patterns, testing, and operational monitoring.",
        'voice_name'       => 'Senior Backend Mentor',
        'structure_name'   => 'Security Best Practices',
        'category_slug'    => 'security',
        'post_status'      => 'draft',
        'post_type'        => 'post',
        'post_quantity'    => 1,
        'is_active'        => 1,
    ],
    [
        'name'             => 'Architecture Deep Dive',
        'prompt_template'  => "Write an architecture deep dive on {{topic}}.\nExplain component design, request/data flow, reliability controls, and tradeoffs.",
        'voice_name'       => 'Senior Backend Mentor',
        'structure_name'   => 'Architecture Deep Dive',
        'category_slug'    => 'architecture-scale',
        'post_status'      => 'draft',
        'post_type'        => 'post',
        'post_quantity'    => 1,
        'is_active'        => 1,
    ],
    [
        'name'             => 'Framework Comparison',
        'prompt_template'  => "Write a balanced comparison article for {{topic}}.\nUse explicit decision criteria and scenario-based recommendations.",
        'voice_name'       => 'Neutral Technical Analyst',
        'structure_name'   => 'Comparison Article',
        'category_slug'    => 'comparisons',
        'post_status'      => 'draft',
        'post_type'        => 'post',
        'post_quantity'    => 1,
        'is_active'        => 1,
    ],
    [
        'name'             => 'Developer Tooling Explainer',
        'prompt_template'  => "Write a practical tooling workflow guide for {{topic}}.\nInclude core commands, sequence, common failures, and debugging tips.",
        'voice_name'       => 'Hands-On Tutorial Coach',
        'structure_name'   => 'Tool / Workflow Explainer',
        'category_slug'    => 'developer-tooling',
        'post_status'      => 'draft',
        'post_type'        => 'post',
        'post_quantity'    => 1,
        'is_active'        => 1,
    ],
    [
        'name'             => 'AI for Developers',
        'prompt_template'  => "Write a practical AI-for-developers article on {{topic}}.\nCover where AI helps, where it fails, and required human review controls.",
        'voice_name'       => 'AI Engineering Editor',
        'structure_name'   => 'AI-for-Devs Article',
        'category_slug'    => 'ai-for-developers',
        'post_status'      => 'draft',
        'post_type'        => 'post',
        'post_quantity'    => 1,
        'is_active'        => 1,
    ],
    [
        'name'             => 'Trends / Timely Analysis',
        'prompt_template'  => "Analyze {{topic}} with a neutral technical lens.\nFocus on implications for developers and practical next actions.\nAvoid press-release style writing.",
        'voice_name'       => 'Neutral Technical Analyst',
        'structure_name'   => 'News / Trend Analysis',
        'category_slug'    => 'industry-trends',
        'post_status'      => 'draft',
        'post_type'        => 'post',
        'post_quantity'    => 1,
        'is_active'        => 1,
    ],
];

// Campaigns: template_name resolved after template creation.
// day_preferences: 1=Mon … 7=Sun (comma-separated).
// frequency: 'daily' with day_preferences used to limit to specific weekdays.
$campaigns = [
    [
        'name'            => 'Dev Foundations',
        'content_goal'    => 'Evergreen entry-level developer traffic covering Git, Composer, REST, SQL, Docker, and PHP fundamentals.',
        'campaign_mode'   => 'template',
        'template_name'   => 'Dev Foundations: Beginner How-To',
        'frequency'       => 'daily',
        'day_preferences' => '1,2,3,4,5,6',   // Mon–Sat
        'start_hour'      => '08:00',
        'is_active'       => 1,
    ],
    [
        'name'            => 'Backend Engineering',
        'content_goal'    => 'Intermediate backend implementation quality covering DI, queues, caching, auth, idempotency, and logging.',
        'campaign_mode'   => 'template',
        'template_name'   => 'Backend Engineering: Intermediate Tutorial',
        'frequency'       => 'daily',
        'day_preferences' => '1,2,3,4,5',     // Mon–Fri
        'start_hour'      => '11:00',
        'is_active'       => 1,
    ],
    [
        'name'            => 'Security First',
        'content_goal'    => 'Security trust and practical hardening covering SQLi, XSS, CSRF, secrets, TLS, and file uploads.',
        'campaign_mode'   => 'template',
        'template_name'   => 'Security First Guide',
        'frequency'       => 'daily',
        'day_preferences' => '1,2,3,4',        // Mon–Thu
        'start_hour'      => '14:00',
        'is_active'       => 1,
    ],
    [
        'name'            => 'Architecture & Scale',
        'content_goal'    => 'Senior-level systems design content covering scaling, retries, circuit breakers, and service boundaries.',
        'campaign_mode'   => 'template',
        'template_name'   => 'Architecture Deep Dive',
        'frequency'       => 'daily',
        'day_preferences' => '1,3,5',          // Mon/Wed/Fri
        'start_hour'      => '17:00',
        'is_active'       => 1,
    ],
    [
        'name'            => 'Framework Comparisons',
        'content_goal'    => 'Decision-intent comparison traffic covering Laravel vs Symfony, Redis vs Memcached, REST vs GraphQL, and more.',
        'campaign_mode'   => 'template',
        'template_name'   => 'Framework Comparison',
        'frequency'       => 'daily',
        'day_preferences' => '2,4,6',          // Tue/Thu/Sat
        'start_hour'      => '10:00',
        'is_active'       => 1,
    ],
    [
        'name'            => 'Developer Tooling',
        'content_goal'    => 'Practical tooling and workflow efficiency covering Git, Composer, Docker, CI, SSH, and Makefiles.',
        'campaign_mode'   => 'template',
        'template_name'   => 'Developer Tooling Explainer',
        'frequency'       => 'daily',
        'day_preferences' => '2,4,6',          // Tue/Thu/Sat
        'start_hour'      => '13:00',
        'is_active'       => 1,
    ],
    [
        'name'            => 'AI for Developers',
        'content_goal'    => 'Practical AI engineering workflows covering prompt quality, review loops, evals, and AI governance.',
        'campaign_mode'   => 'author',
        'template_name'   => 'AI for Developers',
        'frequency'       => 'daily',
        'day_preferences' => '2,4,7',          // Tue/Thu/Sun
        'start_hour'      => '15:00',
        'is_active'       => 1,
    ],
    [
        'name'            => 'Trends / Timely Analysis',
        'content_goal'    => 'Timely but curated technical commentary on ecosystem changes, releases, and developer-relevant shifts.',
        'campaign_mode'   => 'author',
        'template_name'   => 'Trends / Timely Analysis',
        'frequency'       => 'daily',
        'day_preferences' => '1,4,6,7',        // Mon/Thu/Sat/Sun
        'start_hour'      => '19:00',
        'is_active'       => 1,
    ],
];

// ─── Step 0: Categories ───────────────────────────────────────────────────────

seeder_head('WordPress Categories');
$category_id_map = []; // slug => term_id

foreach ($categories as $cat) {
    $existing = get_term_by('slug', $cat['slug'], 'category');
    if ($existing) {
        seeder_skip("Category: {$cat['name']} (already exists, ID {$existing->term_id})");
        $category_id_map[$cat['slug']] = (int) $existing->term_id;
        continue;
    }

    $result = wp_insert_category([
        'cat_name'             => $cat['name'],
        'category_nicename'    => $cat['slug'],
        'category_description' => $cat['description'],
    ]);

    if (is_wp_error($result) || !$result) {
        $msg = is_wp_error($result) ? $result->get_error_message() : 'unknown error';
        seeder_err("Category: {$cat['name']} — {$msg}");
    } else {
        seeder_ok("Category: {$cat['name']} (ID {$result})");
        $category_id_map[$cat['slug']] = (int) $result;
    }
}

// ─── Step 1: Voices ───────────────────────────────────────────────────────────

seeder_head('Voices');
$voice_repo   = new AIPS_Voices_Repository();
$voice_id_map = []; // name => id

foreach ($voices as $voice) {
    $existing_voices = $voice_repo->get_all();
    $found = null;
    foreach ($existing_voices as $v) {
        if ($v->name === $voice['name']) { $found = $v; break; }
    }

    if ($found) {
        seeder_skip("Voice: {$voice['name']} (ID {$found->id})");
        $voice_id_map[$voice['name']] = (int) $found->id;
        continue;
    }

    $id = $voice_repo->create($voice);
    if ($id) {
        seeder_ok("Voice: {$voice['name']} (ID {$id})");
        $voice_id_map[$voice['name']] = (int) $id;
    } else {
        seeder_err("Voice: {$voice['name']}");
    }
}

// ─── Step 2: Structure Sections ───────────────────────────────────────────────

seeder_head('Structure Sections');
$section_repo = new AIPS_Prompt_Section_Repository();

foreach ($sections as $section) {
    $existing = $section_repo->get_by_key($section['section_key']);
    if ($existing) {
        seeder_skip("Section: {$section['name']} [{$section['section_key']}] (ID {$existing->id})");
        continue;
    }

    $id = $section_repo->create(array_merge($section, ['is_active' => 1]));
    if ($id) {
        seeder_ok("Section: {$section['name']} [{$section['section_key']}] (ID {$id})");
    } else {
        seeder_err("Section: {$section['name']} [{$section['section_key']}]");
    }
}

// ─── Step 3: Article Structures ───────────────────────────────────────────────

seeder_head('Article Structures');
$structure_repo     = new AIPS_Article_Structure_Repository();
$structure_id_map   = []; // name => id

foreach ($article_structures as $structure) {
    $existing_structures = $structure_repo->get_all();
    $found = null;
    foreach ($existing_structures as $s) {
        if ($s->name === $structure['name']) { $found = $s; break; }
    }

    if ($found) {
        seeder_skip("Structure: {$structure['name']} (ID {$found->id})");
        $structure_id_map[$structure['name']] = (int) $found->id;
        continue;
    }

    $structure_data = wp_json_encode([
        'sections'        => $structure['sections'],
        'prompt_template' => build_prompt_template($structure['sections']),
    ]);

    $id = $structure_repo->create([
        'name'           => $structure['name'],
        'description'    => $structure['description'],
        'structure_data' => $structure_data,
        'is_active'      => $structure['is_active'],
    ]);

    if ($id) {
        seeder_ok("Structure: {$structure['name']} (ID {$id})");
        $structure_id_map[$structure['name']] = (int) $id;
    } else {
        seeder_err("Structure: {$structure['name']}");
    }
}

// ─── Step 4: Standalone Templates ────────────────────────────────────────────

seeder_head('Standalone Templates');
$template_repo = new AIPS_Template_Repository();
$template_id_map = []; // name => id

foreach ($templates as $tpl) {
    $existing_templates = $template_repo->get_all();
    $found = null;
    foreach ($existing_templates as $t) {
        if ($t->name === $tpl['name'] && empty($t->campaign_id)) { $found = $t; break; }
    }

    if ($found) {
        seeder_skip("Template: {$tpl['name']} (ID {$found->id})");
        $template_id_map[$tpl['name']] = (int) $found->id;
        continue;
    }

    $voice_id     = $voice_id_map[$tpl['voice_name']] ?? null;
    $category_id  = $category_id_map[$tpl['category_slug']] ?? 0;

    if (!$voice_id) {
        seeder_err("Template: {$tpl['name']} — voice '{$tpl['voice_name']}' not found, skipping.");
        continue;
    }

    $id = $template_repo->create([
        'name'            => $tpl['name'],
        'prompt_template' => $tpl['prompt_template'],
        'voice_id'        => $voice_id,
        'post_quantity'   => $tpl['post_quantity'],
        'post_status'     => $tpl['post_status'],
        'post_type'       => $tpl['post_type'],
        'post_category'   => $category_id,
        'is_active'       => $tpl['is_active'],
    ]);

    if ($id) {
        seeder_ok("Template: {$tpl['name']} (ID {$id}, category_id {$category_id})");
        $template_id_map[$tpl['name']] = (int) $id;
    } else {
        seeder_err("Template: {$tpl['name']}");
    }
}

// ─── Step 5: Campaigns ───────────────────────────────────────────────────────

seeder_head('Campaigns');
$campaigns_repo = new AIPS_Campaigns_Repository();

foreach ($campaigns as $campaign) {
    // Skip if campaign with this name already exists.
    $existing_campaigns = $campaigns_repo->get_campaigns();
    $found = null;
    foreach ($existing_campaigns as $c) {
        if ($c->name === $campaign['name']) { $found = $c; break; }
    }

    if ($found) {
        seeder_skip("Campaign: {$campaign['name']} (ID {$found->id})");
        continue;
    }

    // Resolve template
    $source_template_id = $template_id_map[$campaign['template_name']] ?? null;
    if (!$source_template_id) {
        seeder_err("Campaign: {$campaign['name']} — template '{$campaign['template_name']}' not found, skipping.");
        continue;
    }

    $source_template = $template_repo->get_by_id($source_template_id);
    if (!$source_template) {
        seeder_err("Campaign: {$campaign['name']} — could not load template ID {$source_template_id}, skipping.");
        continue;
    }

    // Build start_time as "today at the configured hour" in site timezone.
    $site_tz    = wp_timezone();
    $today      = new DateTime('now', $site_tz);
    [$h, $m]    = explode(':', $campaign['start_hour']);
    $today->setTime((int) $h, (int) $m, 0);
    $start_time = $today->format('Y-m-d\TH:i');   // datetime-local format

    // Resolve article_structure_id from the source template's voice/structure
    // (structures not directly on template row, so pass null and let campaign own it).
    $structure_id = null;

    // Create campaign row.
    $campaign_id = $campaigns_repo->create_campaign([
        'name'          => $campaign['name'],
        'content_goal'  => $campaign['content_goal'],
        'campaign_mode' => $campaign['campaign_mode'],
        'is_active'     => $campaign['is_active'],
        'is_archived'   => 0,
    ]);

    if (!$campaign_id) {
        seeder_err("Campaign: {$campaign['name']} — could not create campaign row.");
        continue;
    }

    // Create campaign-owned template (copy source template settings).
    $tpl_data = get_object_vars($source_template);
    unset($tpl_data['id'], $tpl_data['created_at'], $tpl_data['updated_at']);
    $tpl_data['campaign_id'] = $campaign_id;
    $tpl_data['is_active']   = 1;

    $campaign_template_id = $template_repo->create($tpl_data);
    if (!$campaign_template_id) {
        seeder_err("Campaign: {$campaign['name']} — could not create campaign template.");
        continue;
    }

    // Create schedule.
    $scheduler   = new AIPS_Scheduler();
    $schedule_id = $scheduler->save_schedule([
        'template_id'     => $campaign_template_id,
        'campaign_id'     => $campaign_id,
        'title'           => $campaign['name'],
        'frequency'       => $campaign['frequency'],
        'start_time'      => $start_time,
        'day_preferences' => $campaign['day_preferences'],
        'is_active'       => $campaign['is_active'],
        'topic'           => $campaign['content_goal'],
        'campaign_mode'   => $campaign['campaign_mode'],
    ]);

    if (is_wp_error($schedule_id) || !$schedule_id) {
        $msg = is_wp_error($schedule_id) ? $schedule_id->get_error_message() : 'unknown error';
        seeder_err("Campaign: {$campaign['name']} — schedule failed: {$msg}");
        continue;
    }

    seeder_ok("Campaign: {$campaign['name']} (campaign {$campaign_id} / template {$campaign_template_id} / schedule {$schedule_id})");
}

// ─── Summary ─────────────────────────────────────────────────────────────────

seeder_log("\n── Done ──────────────────────────────────────────────────────────────", 'bold');
seeder_log('Review any ↩ skip and ✗ error lines above.', 'cyan');
seeder_log('All generated posts will be saved as Draft until manually reviewed and published.', 'cyan');
