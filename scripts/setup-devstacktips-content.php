<?php
/**
 * DevStackTips Content Setup Script
 *
 * Creates a complete content strategy for DevStackTips including:
 * - Plugin settings (Content Strategy + Resilience & Limits + Production Settings)
 * - 7 Categories
 * - 5 Voices (writing styles)
 * - 8 Article Structures
 * - 5 Authors
 * - 4 Post Slices
 * - 2 Source Groups with 6 Sources
 * - 8 Templates
 * - 6 Campaigns
 * - 8 Schedules
 *
 * Plugin Settings Configured:
 * - Default Article Structure, Research Niches, Notifications
 * - Telemetry, Cache System, Topic Similarity Threshold
 * - Log Retention, Default Post Settings, Unsplash Key (placeholder)
 *
 * Note: After running, manually configure:
 * - Notification email address in Settings > Notifications
 * - Unsplash Access Key in Settings > Featured Images (if using Unsplash)
 *
 * Usage:
 *   Run this from WordPress admin via wp-admin/admin.php?page=aips-dev-tools
 *   OR from CLI: wp eval-file scripts/setup-devstacktips-content.php
 *   To rollback: wp eval-file scripts/setup-devstacktips-content.php rollback
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	die('Direct access not permitted.');
}

class AIPS_DevStackTips_Setup {

	private $created_items = array();
	private $errors = array();
	private $rollback_mode = false;

	public function __construct($rollback = false) {
		$this->rollback_mode = $rollback;
	}

	public function run() {
		if ($this->rollback_mode) {
			$this->rollback();
			return;
		}

		echo "<h1>DevStackTips Content Setup</h1>\n";
		echo "<p>Creating complete content production system...</p>\n";

		// Step 1: Create Categories
		$this->create_categories();

		// Step 2: Create Voices
		$this->create_voices();

		// Step 3: Create Article Structures (with sections)
		$this->create_article_structures();

		// Step 4: Configure Plugin Settings (needs category & structure IDs)
		$this->configure_settings();

		// Step 5: Create Authors
		$this->create_authors();

		// Step 6: Create Post Slices
		$this->create_post_slices();

		// Step 7: Create Source Groups and Sources
		$this->create_source_groups();

		// Step 8: Create Templates
		$this->create_templates();

		// Step 9: Create Campaigns
		$this->create_campaigns();

		// Step 10: Create Schedules
		$this->create_schedules();

		// Report Results
		$this->print_summary();
	}

	private function configure_settings() {
		echo "<h2>Step 4: Configuring Plugin Settings</h2>\n";

		// Content Strategy Settings
		echo "<h3>Content Strategy</h3>\n";
		
		$content_strategy = array(
			'aips_site_niche' => 'Full-Stack Software Development, Web Architecture',
			'aips_site_target_audience' => 'Professional Software Engineers, Web Developers (Mid to Senior Level)',
			'aips_site_content_goals' => 'Provide authoritative technical deep-dives, establish industry credibility, drive affiliate revenue via trusted/actionable recommendations, and rank for high-value "how-to" and "best practice" keywords.',
			'aips_site_brand_voice' => 'Authoritative yet approachable, Professional, expert-level',
			'aips_site_content_language' => 'en',
			'aips_site_content_guidelines' => 'Minimum 5-10 paragraphs depending on topic. Use H2/H3 hierarchy. Mandatory: brief hyperlinked a "Sources" section. Break code into small, explained chunks. Security focus. Always emphasize "real-world" patterns.',
			'aips_site_excluded_topics' => 'Politics, religion, celebrity gossip, gambling, adult content, clickbait/listicles with no technical depth ("get rich quick" schemes, unverified rumors/hacks about hardware, non-technical software (e.g., Word/Excel), and general consumer electronics reviews.',
		);

		foreach ($content_strategy as $key => $value) {
			update_option($key, $value);
			echo "✓ Set {$key}\n";
		}

		// Resilience & Limits Settings
		echo "<h3>Resilience & Limits (Production Settings)</h3>\n";
		
		$resilience_settings = array(
			// Retry settings
			'aips_enable_retry' => 1,
			'aips_retry_max_attempts' => 3,
			'aips_retry_initial_delay' => 2, // 2 seconds
			
			// Rate limiting
			'aips_enable_rate_limiting' => 1,
			'aips_rate_limit_requests' => 20, // 20 requests
			'aips_rate_limit_period' => 60,   // per 60 seconds
			
			// Circuit breaker
			'aips_enable_circuit_breaker' => 1,
			'aips_circuit_breaker_threshold' => 5,  // Open after 5 failures
			'aips_circuit_breaker_timeout' => 300,  // 5 minutes timeout
		);

		foreach ($resilience_settings as $key => $value) {
			update_option($key, $value);
			echo "✓ Set {$key} = {$value}\n";
		}

		// Default Article Structure
		echo "<h3>Default Article Structure</h3>\n";
		$structures = $this->get_created_structures_by_name();
		if (isset($structures['Evergreen How-To Guide'])) {
			update_option('aips_default_article_structure_id', $structures['Evergreen How-To Guide']);
			echo "✓ Set default article structure: Evergreen How-To Guide (ID: {$structures['Evergreen How-To Guide']})\n";
		}

		// Notification Settings
		echo "<h3>Notification Preferences</h3>\n";
		update_option('aips_review_notifications_email', '');
		echo "⚠ Email not configured - Please add notification email in Settings > Notifications\n";
		
		$notification_prefs = array(
			'generation_failed' => 'email',
			'quota_alert' => 'email',
			'post_ready_for_review' => 'both',
			'template_generated' => 'db',
			'manual_generation_completed' => 'db',
			'partial_generation_completed' => 'both',
			'author_topics_generated' => 'db',
			'author_topics_failed' => 'email',
			'author_posts_generated' => 'db',
			'author_posts_failed' => 'email',
			'bulk_batch_completed' => 'db',
			'errors' => 'email',
			'daily_digest' => 'email',
			'weekly_digest' => 'email',
			'monthly_digest' => 'email',
		);
		update_option('aips_notification_preferences', $notification_prefs);
		echo "✓ Set notification preferences (email for critical, DB for routine)\n";

		// Research Niches
		echo "<h3>Research & Discovery</h3>\n";
		$research_niches = array(
			'PHP and Backend Development Trends',
			'Application Security and Vulnerability Prevention',
			'Modern Framework Comparisons (Laravel, Symfony, etc.)',
			'DevOps Tools and Automation',
			'AI Tools for Developers',
			'Database Optimization and Best Practices',
			'Software Architecture Patterns',
			'API Design and Integration',
		);
		update_option('aips_research_niches', $research_niches);
		echo "✓ Set research niches (8 DevStackTips topics)\n";
		
		update_option('aips_topic_similarity_threshold', 0.75);
		echo "✓ Set topic similarity threshold: 0.75 (allows more variety)\n";

		// Unsplash Configuration
		echo "<h3>Featured Images</h3>\n";
		update_option('aips_unsplash_access_key', '');
		echo "⚠ Unsplash key not configured - Please add Unsplash Access Key in Settings if using Unsplash images\n";

		// Telemetry
		echo "<h3>Performance Monitoring</h3>\n";
		update_option('aips_enable_telemetry', true);
		echo "✓ Enabled telemetry (slow query & request tracking)\n";

		// Cache System
		echo "<h3>Cache Configuration</h3>\n";
		update_option('aips_enable_cache_system', true);
		update_option('aips_cache_driver', 'db');
		update_option('aips_cache_default_ttl', 3600);
		echo "✓ Enabled cache system (DB driver, 1 hour TTL)\n";

		// Log Retention
		echo "<h3>Log Management</h3>\n";
		update_option('aips_log_retention_days', 60);
		echo "✓ Set log retention: 60 days\n";

		// Default Post Settings
		echo "<h3>Default Post Settings</h3>\n";
		update_option('aips_default_post_status', 'draft');
		echo "✓ Set default post status: draft\n";
		
		$categories = $this->get_created_categories_by_name();
		if (isset($categories['Backend Development'])) {
			update_option('aips_default_category', $categories['Backend Development']);
			echo "✓ Set default category: Backend Development (ID: {$categories['Backend Development']})\n";
		}
		
		update_option('aips_default_post_author', 1);
		echo "✓ Set default post author: 1 (admin)\n";

		echo "✓ All plugin settings configured for production\n";
	}

	private function create_categories() {
		echo "<h2>Step 1: Creating Categories</h2>\n";

		$categories = array(
			array(
				'name' => 'Backend Development',
				'slug' => 'backend-development',
				'description' => 'Server-side programming, APIs, databases, and backend architecture',
			),
			array(
				'name' => 'Security',
				'slug' => 'security',
				'description' => 'Application security, vulnerabilities, and secure coding practices',
			),
			array(
				'name' => 'DevOps & Tools',
				'slug' => 'devops-tools',
				'description' => 'Developer tools, workflows, Git, Docker, and automation',
			),
			array(
				'name' => 'Framework Guides',
				'slug' => 'framework-guides',
				'description' => 'Framework tutorials and comparisons',
			),
			array(
				'name' => 'AI for Developers',
				'slug' => 'ai-for-developers',
				'description' => 'Practical AI tools and workflows for software development',
			),
			array(
				'name' => 'PHP Development',
				'slug' => 'php-development',
				'description' => 'PHP tutorials, patterns, and best practices',
			),
			array(
				'name' => 'Database',
				'slug' => 'database',
				'description' => 'SQL, database design, optimization, and management',
			),
		);

		foreach ($categories as $cat_data) {
			$existing = term_exists($cat_data['slug'], 'category');
			if (!$existing) {
				$result = wp_insert_term($cat_data['name'], 'category', array(
					'slug' => $cat_data['slug'],
					'description' => $cat_data['description'],
				));

				if (!is_wp_error($result)) {
					$this->created_items['categories'][] = array('id' => $result['term_id'], 'name' => $cat_data['name']);
					echo "✓ Created category: {$cat_data['name']}\n";
				} else {
					$this->errors[] = "Failed to create category: {$cat_data['name']} - " . $result->get_error_message();
					echo "✗ Failed: {$cat_data['name']}\n";
				}
			} else {
				$this->created_items['categories'][] = array('id' => $existing['term_id'], 'name' => $cat_data['name']);
				echo "• Category exists: {$cat_data['name']}\n";
			}
		}
	}

	private function create_voices() {
		echo "<h2>Step 3: Creating Voices</h2>\n";

		$voices_repo = new AIPS_Voices_Repository();

		$voices = array(
			array(
				'name' => 'DevStackTips Default',
				'title_prompt' => 'Create a clear, technical, and specific title for this topic. Avoid generic phrases like "ultimate guide" or "everything you need to know". Make it actionable and developer-focused.',
				'content_instructions' => 'You are writing for software developers and technical readers on DevStackTips, a practical developer resource site.

Writing style:
- Be clear and concrete
- Use short paragraphs and informative headings
- Prefer practical examples over abstract explanations
- Be technically confident but not arrogant
- Avoid hype, filler, and exaggerated claims
- Never mention being an AI
- Do not use phrases like "ultimate guide" or "game-changing"

Content approach:
- Focus on what developers need to know
- Include code examples where relevant
- Explain the "why" behind recommendations
- Be honest about tradeoffs and limitations
- Use active voice
- Keep intros brief and get to the point quickly

Avoid:
- Marketing language or sales pitches
- Generic filler sentences
- Inventing statistics or benchmarks
- Making version-specific claims without verification
- Overly broad generalizations',
				'excerpt_instructions' => 'Write a concise 1-2 sentence summary that captures the core value for developers.',
				'is_active' => 1,
			),
			array(
				'name' => 'Senior Backend Mentor',
				'title_prompt' => 'Create a title that emphasizes the architectural or design aspect. Use terms like "patterns", "strategies", "best practices", or "architecture".',
				'content_instructions' => 'You are a senior backend engineer mentoring developers through DevStackTips.

Writing approach:
- Explain not just what to do, but WHY
- Highlight tradeoffs, limitations, and common mistakes
- Emphasize maintainability, reliability, and security
- Share practical wisdom from real-world experience
- Be opinionated but fair

Content structure:
- Start with the problem or context
- Explain the reasoning behind solutions
- Include "when to use" and "when NOT to use" guidance
- Point out anti-patterns and pitfalls
- Focus on long-term consequences of design decisions

Technical depth:
- Assume intermediate to advanced knowledge
- Use precise technical terminology
- Explain edge cases and failure scenarios
- Discuss performance and security implications
- Reference architectural principles when relevant

Avoid:
- Oversimplifying complex topics
- Presenting one approach as universally correct
- Ignoring operational concerns
- Theoretical-only advice without practical application',
				'excerpt_instructions' => 'Highlight the key architectural insight or tradeoff discussed.',
				'is_active' => 1,
			),
			array(
				'name' => 'Hands-On Tutorial Coach',
				'title_prompt' => 'Create a "How to..." title that clearly states what the reader will accomplish. Be specific about the outcome.',
				'content_instructions' => 'You are a patient, practical tutorial instructor helping developers learn by doing.

Teaching style:
- Break complex tasks into clear sequential steps
- Assume readers want to apply this immediately
- Be encouraging without being condescending
- Explain each step\'s purpose
- Anticipate where learners might get stuck

Content structure:
- Start with what the reader will build/learn
- List prerequisites clearly
- Number steps sequentially
- Include validation checkpoints
- Show expected output at each stage
- Provide troubleshooting tips

Code and examples:
- Include complete, working code samples
- Explain what each code block does
- Use comments in code where helpful
- Show before/after comparisons
- Provide copy-paste ready snippets

Tone:
- Friendly and approachable
- Direct and action-oriented
- Patient with beginners
- Practical over theoretical
- Focus on getting it working first, optimization second',
				'excerpt_instructions' => 'Describe what the reader will be able to do after following this tutorial.',
				'is_active' => 1,
			),
			array(
				'name' => 'Neutral Technical Analyst',
				'title_prompt' => 'Create a balanced comparison title using "vs" or "Comparing". Avoid suggesting one option is superior.',
				'content_instructions' => 'You are an objective technical analyst comparing tools, frameworks, and approaches for developers.

Comparison approach:
- Present options fairly and without bias
- Acknowledge that different contexts require different solutions
- Use structured comparison frameworks
- Focus on objective criteria
- Avoid declaring universal winners

Content structure:
- Start with clear comparison criteria
- Present strengths and weaknesses of each option
- Include comparison tables where appropriate
- Discuss ideal use cases for each
- Address migration/switching considerations

Analysis style:
- Evidence-based over opinion-based
- Acknowledge trade-offs explicitly
- Consider multiple perspectives
- Discuss real-world constraints
- Address both technical and practical factors

What to avoid:
- Fanboy enthusiasm or bashing
- Ignoring legitimate use cases
- Outdated information presented as current
- Oversimplifying nuanced differences
- Personal preference disguised as objective analysis',
				'excerpt_instructions' => 'Summarize the key differences and when to choose each option.',
				'is_active' => 1,
			),
			array(
				'name' => 'AI Engineering Editor',
				'title_prompt' => 'Create a title that addresses AI tools or workflows pragmatically. Include "for Developers" or "in Development" to keep focus practical.',
				'content_instructions' => 'You are writing about AI tools and practices for developers, with both enthusiasm and appropriate caution.

Content approach:
- Avoid AI hype and exaggeration
- Focus on practical developer use cases
- Address accuracy, risks, and evaluation methods
- Discuss governance and quality control
- Balance opportunity with realistic limitations

Technical coverage:
- Explain how AI tools fit into developer workflows
- Discuss prompt engineering practically
- Cover evaluation and testing approaches
- Address when NOT to use AI
- Include human review checkpoints

Tone:
- Pragmatic and grounded
- Current but not breathless
- Honest about limitations
- Security and quality conscious
- Focus on sustainable practices

Critical areas to address:
- Accuracy and hallucination risks
- Code review requirements
- Privacy and security implications
- Cost considerations
- When traditional approaches are better
- Responsible AI practices',
				'excerpt_instructions' => 'Emphasize both the benefits and limitations of the AI approach discussed.',
				'is_active' => 1,
			),
		);

		foreach ($voices as $voice_data) {
			$voice_id = $voices_repo->create($voice_data);
			if ($voice_id) {
				$this->created_items['voices'][] = array('id' => $voice_id, 'name' => $voice_data['name']);
				echo "✓ Created voice: {$voice_data['name']}\n";
			} else {
				$this->errors[] = "Failed to create voice: {$voice_data['name']}";
				echo "✗ Failed: {$voice_data['name']}\n";
			}
		}
	}

	private function create_article_structures() {
		echo "<h2>Step 4: Creating Article Structures</h2>\n";

		$structure_repo = new AIPS_Article_Structure_Repository();
		$section_repo = new AIPS_Prompt_Section_Repository();

		// Define reusable sections first
		$sections = array(
			'introduction' => array(
				'name' => 'Introduction',
				'section_key' => 'introduction',
				'description' => 'Opening section that establishes context and relevance',
				'content' => 'Start with a brief introduction explaining why this topic matters to developers. Keep it concise and focused on practical relevance.',
				'is_active' => 1,
			),
			'what_youll_learn' => array(
				'name' => 'What You\'ll Learn',
				'section_key' => 'what_youll_learn',
				'description' => 'Learning objectives section',
				'content' => 'List 3-5 specific things the reader will learn or be able to do after reading this article.',
				'is_active' => 1,
			),
			'prerequisites' => array(
				'name' => 'Prerequisites',
				'section_key' => 'prerequisites',
				'description' => 'Required knowledge and setup',
				'content' => 'List any required knowledge, tools, or setup needed before following this tutorial.',
				'is_active' => 1,
			),
			'core_concepts' => array(
				'name' => 'Core Concepts',
				'section_key' => 'core_concepts',
				'description' => 'Fundamental concepts overview',
				'content' => 'Explain the fundamental concepts readers need to understand. Define key terms and provide context.',
				'is_active' => 1,
			),
			'step_by_step' => array(
				'name' => 'Step-by-Step Instructions',
				'section_key' => 'step_by_step',
				'description' => 'Sequential implementation steps',
				'content' => 'Provide numbered, sequential steps. For each step, explain what to do, why, and what the expected result is.',
				'is_active' => 1,
			),
			'code_examples' => array(
				'name' => 'Code Examples',
				'section_key' => 'code_examples',
				'description' => 'Working code samples',
				'content' => 'Include complete, working code examples with explanations. Use code blocks with proper syntax highlighting.',
				'is_active' => 1,
			),
			'common_mistakes' => array(
				'name' => 'Common Mistakes',
				'section_key' => 'common_mistakes',
				'description' => 'Pitfalls to avoid',
				'content' => 'List 3-5 common mistakes developers make with this approach, and how to avoid them.',
				'is_active' => 1,
			),
			'best_practices' => array(
				'name' => 'Best Practices',
				'section_key' => 'best_practices',
				'description' => 'Recommended approaches',
				'content' => 'Share best practices and recommendations for production use.',
				'is_active' => 1,
			),
			'when_to_use' => array(
				'name' => 'When to Use / When Not to Use',
				'section_key' => 'when_to_use',
				'description' => 'Use case guidance',
				'content' => 'Explain when this approach is ideal and when alternatives should be considered. Be honest about limitations.',
				'is_active' => 1,
			),
			'conclusion' => array(
				'name' => 'Conclusion',
				'section_key' => 'conclusion',
				'description' => 'Summary and next steps',
				'content' => 'Summarize key takeaways and suggest next steps for further learning.',
				'is_active' => 1,
			),
			'faq' => array(
				'name' => 'FAQ',
				'section_key' => 'faq',
				'description' => 'Frequently asked questions',
				'content' => 'Answer 3-5 common questions related to this topic.',
				'is_active' => 1,
			),
			'problem_statement' => array(
				'name' => 'Problem Statement',
				'section_key' => 'problem_statement',
				'description' => 'Define the problem being solved',
				'content' => 'Clearly define the problem or challenge this approach addresses.',
				'is_active' => 1,
			),
			'technical_context' => array(
				'name' => 'Technical Context',
				'section_key' => 'technical_context',
				'description' => 'Background and context',
				'content' => 'Provide technical background and context needed to understand the solution.',
				'is_active' => 1,
			),
			'implementation_strategy' => array(
				'name' => 'Implementation Strategy',
				'section_key' => 'implementation_strategy',
				'description' => 'Overall approach',
				'content' => 'Outline the overall implementation strategy before diving into details.',
				'is_active' => 1,
			),
			'performance_considerations' => array(
				'name' => 'Performance Considerations',
				'section_key' => 'performance_considerations',
				'description' => 'Performance implications',
				'content' => 'Discuss performance implications, optimization opportunities, and scalability concerns.',
				'is_active' => 1,
			),
			'security_considerations' => array(
				'name' => 'Security Considerations',
				'section_key' => 'security_considerations',
				'description' => 'Security implications',
				'content' => 'Address security implications, risks, and secure coding practices.',
				'is_active' => 1,
			),
			'testing_validation' => array(
				'name' => 'Testing / Validation',
				'section_key' => 'testing_validation',
				'description' => 'How to test and validate',
				'content' => 'Explain how to test and validate the implementation.',
				'is_active' => 1,
			),
			'operational_tips' => array(
				'name' => 'Operational Tips',
				'section_key' => 'operational_tips',
				'description' => 'Production operation guidance',
				'content' => 'Provide operational guidance for running this in production (monitoring, logging, debugging).',
				'is_active' => 1,
			),
			'comparison_overview' => array(
				'name' => 'Overview',
				'section_key' => 'comparison_overview',
				'description' => 'Comparison introduction',
				'content' => 'Introduce both options being compared and the criteria for comparison.',
				'is_active' => 1,
			),
			'quick_summary_table' => array(
				'name' => 'Quick Summary Table',
				'section_key' => 'quick_summary_table',
				'description' => 'At-a-glance comparison',
				'content' => 'Create a comparison table with key features side-by-side.',
				'is_active' => 1,
			),
			'option_a' => array(
				'name' => 'What Option A Is',
				'section_key' => 'option_a',
				'description' => 'First option overview',
				'content' => 'Explain what the first option is, its history, and core features.',
				'is_active' => 1,
			),
			'option_b' => array(
				'name' => 'What Option B Is',
				'section_key' => 'option_b',
				'description' => 'Second option overview',
				'content' => 'Explain what the second option is, its history, and core features.',
				'is_active' => 1,
			),
			'developer_experience' => array(
				'name' => 'Developer Experience',
				'section_key' => 'developer_experience',
				'description' => 'DX comparison',
				'content' => 'Compare the developer experience, learning curve, and documentation quality.',
				'is_active' => 1,
			),
			'ecosystem_community' => array(
				'name' => 'Ecosystem / Community',
				'section_key' => 'ecosystem_community',
				'description' => 'Community and ecosystem',
				'content' => 'Compare community size, available packages/plugins, and ecosystem maturity.',
				'is_active' => 1,
			),
			'use_cases' => array(
				'name' => 'Best Use Cases',
				'section_key' => 'use_cases',
				'description' => 'Recommended use cases',
				'content' => 'Describe the ideal use cases for each option.',
				'is_active' => 1,
			),
			'recommendation' => array(
				'name' => 'Final Recommendation',
				'section_key' => 'recommendation',
				'description' => 'Summary and guidance',
				'content' => 'Provide balanced recommendations based on different scenarios and requirements.',
				'is_active' => 1,
			),
		);

		// Create sections
		foreach ($sections as $key => $section_data) {
			// Check if section already exists
			$existing = $section_repo->get_by_key($section_data['section_key']);
			if (!$existing) {
				$section_id = $section_repo->create($section_data);
				if ($section_id) {
					echo "✓ Created section: {$section_data['name']}\n";
				}
			}
		}

		// Now create article structures
		$structures = array(
			array(
				'name' => 'Evergreen How-To Guide',
				'description' => 'For foundational tutorials and step-by-step guides',
				'sections' => array('introduction', 'what_youll_learn', 'prerequisites', 'core_concepts', 'step_by_step', 'code_examples', 'common_mistakes', 'best_practices', 'when_to_use', 'conclusion', 'faq'),
			),
			array(
				'name' => 'Advanced Technical Tutorial',
				'description' => 'For deeper backend, security, and DevOps content',
				'sections' => array('problem_statement', 'technical_context', 'prerequisites', 'implementation_strategy', 'step_by_step', 'code_examples', 'performance_considerations', 'security_considerations', 'testing_validation', 'operational_tips', 'conclusion'),
			),
			array(
				'name' => 'Comparison Article',
				'description' => 'For framework, tool, and approach comparisons',
				'sections' => array('comparison_overview', 'quick_summary_table', 'option_a', 'option_b', 'developer_experience', 'performance_considerations', 'ecosystem_community', 'use_cases', 'recommendation'),
			),
			array(
				'name' => 'Architecture Deep Dive',
				'description' => 'For design patterns and system architecture topics',
				'sections' => array('problem_statement', 'core_concepts', 'technical_context', 'implementation_strategy', 'best_practices', 'common_mistakes', 'use_cases', 'conclusion'),
			),
			array(
				'name' => 'Security Best Practices',
				'description' => 'For security-focused content',
				'sections' => array('problem_statement', 'security_considerations', 'best_practices', 'code_examples', 'testing_validation', 'operational_tips', 'conclusion'),
			),
			array(
				'name' => 'Tool / Workflow Explainer',
				'description' => 'For Git, Composer, Docker, CI/CD, SSH, etc.',
				'sections' => array('introduction', 'core_concepts', 'step_by_step', 'code_examples', 'common_mistakes', 'best_practices', 'conclusion'),
			),
			array(
				'name' => 'AI-for-Devs Article',
				'description' => 'For AI workflow and tooling content',
				'sections' => array('problem_statement', 'best_practices', 'code_examples', 'use_cases', 'security_considerations', 'when_to_use', 'conclusion'),
			),
			array(
				'name' => 'News / Trend Analysis',
				'description' => 'For timely technical analysis',
				'sections' => array('introduction', 'technical_context', 'use_cases', 'recommendation', 'conclusion'),
			),
		);

		foreach ($structures as $structure_data) {
			$structure_json = wp_json_encode(array(
				'sections' => $structure_data['sections'],
				'prompt_template' => '', // Structures handle this automatically
			));

			$structure_id = $structure_repo->create(array(
				'name' => $structure_data['name'],
				'description' => $structure_data['description'],
				'structure_data' => $structure_json,
				'is_active' => 1,
			));

			if ($structure_id) {
				$this->created_items['structures'][] = array('id' => $structure_id, 'name' => $structure_data['name']);
				echo "✓ Created structure: {$structure_data['name']}\n";
			} else {
				$this->errors[] = "Failed to create structure: {$structure_data['name']}";
				echo "✗ Failed: {$structure_data['name']}\n";
			}
		}
	}

	private function create_authors() {
		echo "<h2>Step 5: Creating Authors</h2>\n";

		$authors_repo = new AIPS_Authors_Repository();
		$voices = $this->get_created_voices_by_name();
		$structures = $this->get_created_structures_by_name();
		$categories = $this->get_created_categories_by_name();
		$source_groups = $this->get_created_source_groups_by_name();
		$interval_calc = AIPS_Interval_Calculator::instance();

		$authors = array(
			array(
				'name' => 'Backend Architecture Specialist',
				'field_niche' => 'Backend Development, System Architecture, API Design',
				'keywords' => 'API design, microservices, system architecture, scalability, caching, database optimization',
				'description' => 'Senior backend engineer focused on API design, system architecture, and scalability patterns',
				'details' => 'Specializes in building scalable backend systems. Covers topics like RESTful APIs, GraphQL, microservices architecture, database optimization, and caching strategies. Target audience is intermediate to advanced backend developers.',
				'voice_name' => 'Senior Backend Mentor',
				'structure_name' => 'Advanced Technical Tutorial',
				'voice_tone' => 'Professional, authoritative, mentoring',
				'writing_style' => 'Technical deep-dive with practical examples',
				'target_audience' => 'Intermediate to advanced backend developers',
				'expertise_level' => 'advanced',
				'content_goals' => 'Educate developers on scalable architecture patterns, Build practical skills, Share production-tested solutions',
				'excluded_topics' => 'Basic programming concepts, Frontend frameworks, Mobile development',
				'preferred_content_length' => 'long',
				'category_name' => 'Backend Development',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 5,
				'post_generation_frequency' => 'daily',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active' => 1,
			),
			array(
				'name' => 'Security Expert',
				'field_niche' => 'Application Security, Secure Coding, Vulnerability Prevention',
				'keywords' => 'SQL injection, XSS, CSRF, authentication, encryption, secure coding, OWASP',
				'description' => 'Application security specialist covering vulnerabilities, secure coding, and best practices',
				'details' => 'Focuses on practical application security for developers. Covers common vulnerabilities (OWASP Top 10), secure coding practices, authentication/authorization, encryption, and security testing. Emphasizes prevention over remediation.',
				'voice_name' => 'Senior Backend Mentor',
				'structure_name' => 'Security Best Practices',
				'voice_tone' => 'Professional, serious, educational',
				'writing_style' => 'Security-focused tutorial with code examples',
				'target_audience' => 'All developers who write server-side code',
				'expertise_level' => 'intermediate',
				'content_goals' => 'Prevent security vulnerabilities, Educate on secure coding, Build security awareness',
				'excluded_topics' => 'Network security hardware, Penetration testing tools, Compliance regulations',
				'preferred_content_length' => 'medium',
				'category_name' => 'Security',
				'source_group_name' => 'Security News',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 5,
				'post_generation_frequency' => 'daily',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'include_sources' => 1,
				'is_active' => 1,
			),
			array(
				'name' => 'DevOps Practitioner',
				'field_niche' => 'Developer Tools, DevOps, Automation',
				'keywords' => 'Git, Docker, CI/CD, automation, deployment, developer productivity, workflows',
				'description' => 'Developer tools and workflow specialist focused on Git, Docker, CI/CD, and automation',
				'details' => 'Covers practical developer tools and DevOps workflows. Topics include Git workflows, Docker containerization, CI/CD pipelines, build automation, and developer productivity tools. Focuses on hands-on tutorials.',
				'voice_name' => 'Hands-On Tutorial Coach',
				'structure_name' => 'Tool / Workflow Explainer',
				'voice_tone' => 'Practical, encouraging, step-by-step',
				'writing_style' => 'Tutorial with clear instructions and examples',
				'target_audience' => 'Developers looking to improve their workflows and tooling',
				'expertise_level' => 'beginner_intermediate',
				'content_goals' => 'Teach practical tool usage, Improve developer productivity, Share workflow best practices',
				'excluded_topics' => 'Cloud infrastructure management, Kubernetes orchestration, Enterprise IT',
				'preferred_content_length' => 'medium',
				'category_name' => 'DevOps & Tools',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 5,
				'post_generation_frequency' => 'daily',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active' => 1,
			),
			array(
				'name' => 'Framework Analyst',
				'field_niche' => 'Framework Comparisons, Technology Evaluation',
				'keywords' => 'framework comparison, Laravel vs Symfony, React vs Vue, technology choices, migration',
				'description' => 'Objective framework and tool comparison specialist',
				'details' => 'Provides balanced, unbiased comparisons of frameworks and tools. Covers PHP frameworks, JavaScript frameworks, databases, and other technology choices. Helps developers make informed decisions.',
				'voice_name' => 'Neutral Technical Analyst',
				'structure_name' => 'Comparison Article',
				'voice_tone' => 'Neutral, objective, analytical',
				'writing_style' => 'Comparison with pros/cons and use-case analysis',
				'target_audience' => 'Developers evaluating technology choices',
				'expertise_level' => 'intermediate',
				'content_goals' => 'Help developers make informed choices, Present balanced comparisons, Avoid bias',
				'excluded_topics' => 'Opinionated framework advocacy, Deprecated technologies, Proprietary tools',
				'preferred_content_length' => 'medium',
				'category_name' => 'Framework Guides',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 5,
				'post_generation_frequency' => 'weekly',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active' => 1,
			),
			array(
				'name' => 'AI Engineering Pragmatist',
				'field_niche' => 'AI for Developers, Practical AI Integration',
				'keywords' => 'AI code review, prompt engineering, GitHub Copilot, ChatGPT, LLM integration, AI evaluation',
				'description' => 'Practical AI integration specialist for developer workflows',
				'details' => 'Focuses on practical AI tools for developers. Covers AI-assisted coding, prompt engineering, code review with AI, LLM integration in applications, and honest evaluation of AI capabilities and limitations.',
				'voice_name' => 'AI Engineering Editor',
				'structure_name' => 'AI-for-Devs Article',
				'voice_tone' => 'Pragmatic, honest, forward-thinking',
				'writing_style' => 'Practical guide with real-world examples',
				'target_audience' => 'Developers exploring AI integration',
				'expertise_level' => 'intermediate',
				'content_goals' => 'Demystify AI for developers, Share practical integration patterns, Balance hype with reality',
				'excluded_topics' => 'Deep learning theory, AI research papers, ML model training',
				'preferred_content_length' => 'medium',
				'category_name' => 'AI for Developers',
				'topic_generation_frequency' => 'weekly',
				'topic_generation_quantity' => 5,
				'post_generation_frequency' => 'weekly',
				'max_posts_per_topic' => 1,
				'manual_post_generation_quantity' => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active' => 1,
			),
		);

		$now = AIPS_DateTime::now()->timestamp();

		foreach ($authors as $author_data) {
			$structure_id = isset($structures[$author_data['structure_name']]) ? $structures[$author_data['structure_name']] : null;
			$category_id = isset($author_data['category_name'], $categories[$author_data['category_name']]) ? $categories[$author_data['category_name']] : null;

			// Get source group ID if specified
			$source_group_ids = array();
			if (isset($author_data['source_group_name'], $source_groups[$author_data['source_group_name']])) {
				$source_group_ids[] = $source_groups[$author_data['source_group_name']];
			}

			// Calculate next run times
			$topic_next_run = $interval_calc->calculate_next_run($author_data['topic_generation_frequency'], $now);
			$post_next_run = $interval_calc->calculate_next_run($author_data['post_generation_frequency'], $now);

			$author_id = $authors_repo->create(array(
				'name' => $author_data['name'],
				'field_niche' => $author_data['field_niche'],
				'keywords' => $author_data['keywords'],
				'description' => $author_data['description'],
				'details' => $author_data['details'],
				'article_structure_id' => $structure_id,
				'voice_tone' => $author_data['voice_tone'],
				'writing_style' => $author_data['writing_style'],
				'target_audience' => $author_data['target_audience'],
				'expertise_level' => $author_data['expertise_level'],
				'content_goals' => $author_data['content_goals'],
				'excluded_topics' => $author_data['excluded_topics'],
				'preferred_content_length' => $author_data['preferred_content_length'],
				'language' => 'en',
				'post_status' => 'draft',
				'post_category' => $category_id,
				'post_tags' => '',
				'post_author' => get_current_user_id(),
				'generate_featured_image' => 0,
				'featured_image_source' => 'ai_prompt',
				'topic_generation_frequency' => $author_data['topic_generation_frequency'],
				'topic_generation_quantity' => $author_data['topic_generation_quantity'],
				'topic_generation_next_run' => $topic_next_run,
				'topic_generation_last_run' => 0,
				'topic_generation_is_active' => 1,
				'post_generation_frequency' => $author_data['post_generation_frequency'],
				'post_generation_next_run' => $post_next_run,
				'post_generation_last_run' => 0,
				'post_generation_is_active' => 1,
				'max_posts_per_topic' => $author_data['max_posts_per_topic'],
				'manual_post_generation_quantity' => $author_data['manual_post_generation_quantity'],
				'scheduled_post_generation_quantity' => $author_data['scheduled_post_generation_quantity'],
				'include_sources' => isset($author_data['include_sources']) ? $author_data['include_sources'] : 0,
				'source_group_ids' => wp_json_encode($source_group_ids),
				'is_active' => $author_data['is_active'],
				'created_at' => $now,
				'updated_at' => $now,
			));

			if ($author_id) {
				$this->created_items['authors'][] = array('id' => $author_id, 'name' => $author_data['name']);
				echo "✓ Created author: {$author_data['name']}\n";
			} else {
				$this->errors[] = "Failed to create author: {$author_data['name']}";
				echo "✗ Failed: {$author_data['name']}\n";
			}
		}
	}

	private function create_post_slices() {
		echo "<h2>Step 6: Creating Post Slices</h2>\n";

		$slices_repo = new AIPS_Post_Slices_Repository();
		$now = AIPS_DateTime::now()->timestamp();

		$slices = array(
			array(
				'name' => 'Developer Resources Footer',
				'description' => 'Standard footer with additional resources and next steps',
				'content' => '<h2>Additional Resources</h2>
<ul>
<li>Official documentation for the topics covered</li>
<li>Community forums and discussion groups</li>
<li>Related tutorials and deep-dives</li>
</ul>

<h2>Next Steps</h2>
<p>Now that you understand the basics, consider exploring related topics to deepen your knowledge. Practice is essential - try implementing what you\'ve learned in a small project.</p>',
				'sort_order' => 10,
				'is_active' => 1,
			),
			array(
				'name' => 'Security Disclaimer',
				'description' => 'Security best practices reminder',
				'content' => '<div class="security-note">
<p><strong>Security Note:</strong> Always validate and sanitize user input. Never trust data from external sources. Keep dependencies updated and follow the principle of least privilege.</p>
</div>',
				'sort_order' => 20,
				'is_active' => 1,
			),
			array(
				'name' => 'Code Example Standards',
				'description' => 'Introduction for code-heavy tutorials',
				'content' => '<p><strong>About the Code Examples:</strong> All code examples in this tutorial are tested and functional. However, always review and adapt code to your specific use case. Consider error handling, edge cases, and your application\'s requirements.</p>',
				'sort_order' => 30,
				'is_active' => 1,
			),
			array(
				'name' => 'Version Note',
				'description' => 'Software version disclaimer',
				'content' => '<p><em>Note: Software versions and APIs change over time. While the concepts remain relevant, always check the latest documentation for your specific version.</em></p>',
				'sort_order' => 40,
				'is_active' => 1,
			),
		);

		foreach ($slices as $slice_data) {
			$slice_id = $slices_repo->create(array(
				'name' => $slice_data['name'],
				'description' => $slice_data['description'],
				'content' => $slice_data['content'],
				'sort_order' => $slice_data['sort_order'],
				'is_active' => $slice_data['is_active'],
				'created_at' => $now,
				'updated_at' => $now,
			));

			if ($slice_id) {
				$this->created_items['slices'][] = array('id' => $slice_id, 'name' => $slice_data['name']);
				echo "✓ Created post slice: {$slice_data['name']}\n";
			} else {
				$this->errors[] = "Failed to create post slice: {$slice_data['name']}";
				echo "✗ Failed: {$slice_data['name']}\n";
			}
		}
	}

	private function create_source_groups() {
		echo "<h2>Step 7: Creating Source Groups and Sources</h2>\n";

		$sources_repo = new AIPS_Sources_Repository();

		// Create source groups (taxonomy terms)
		$groups = array(
			array(
				'name' => 'Security News',
				'slug' => 'security-news',
				'description' => 'Security vulnerabilities, patches, and best practices',
			),
			array(
				'name' => 'PHP Ecosystem',
				'slug' => 'php-ecosystem',
				'description' => 'PHP frameworks, libraries, and community updates',
			),
		);

		$group_ids = array();
		foreach ($groups as $group_data) {
			$existing = term_exists($group_data['slug'], 'aips_source_group');
			if (!$existing) {
				$result = wp_insert_term($group_data['name'], 'aips_source_group', array(
					'slug' => $group_data['slug'],
					'description' => $group_data['description'],
				));

				if (!is_wp_error($result)) {
					$group_ids[$group_data['slug']] = $result['term_id'];
					$this->created_items['source_groups'][] = array('id' => $result['term_id'], 'name' => $group_data['name']);
					echo "✓ Created source group: {$group_data['name']}\n";
				}
			} else {
				$group_ids[$group_data['slug']] = $existing['term_id'];
				$this->created_items['source_groups'][] = array('id' => $existing['term_id'], 'name' => $group_data['name']);
				echo "• Source group exists: {$group_data['name']}\n";
			}
		}

		// Create sources
		$sources = array(
			// Security News sources
			array(
				'name' => 'OWASP Top 10',
				'url' => 'https://owasp.org/www-project-top-ten/',
				'source_type' => 'rss',
				'group_slug' => 'security-news',
				'is_active' => 1,
			),
			array(
				'name' => 'Snyk Blog Security',
				'url' => 'https://snyk.io/blog/',
				'source_type' => 'rss',
				'group_slug' => 'security-news',
				'is_active' => 1,
			),
			array(
				'name' => 'Security Week',
				'url' => 'https://www.securityweek.com/feed/',
				'source_type' => 'rss',
				'group_slug' => 'security-news',
				'is_active' => 1,
			),
			// PHP Ecosystem sources
			array(
				'name' => 'PHP.Watch',
				'url' => 'https://php.watch/feed',
				'source_type' => 'rss',
				'group_slug' => 'php-ecosystem',
				'is_active' => 1,
			),
			array(
				'name' => 'Laravel News',
				'url' => 'https://laravel-news.com/feed',
				'source_type' => 'rss',
				'group_slug' => 'php-ecosystem',
				'is_active' => 1,
			),
			array(
				'name' => 'Symfony Blog',
				'url' => 'https://symfony.com/blog/',
				'source_type' => 'rss',
				'group_slug' => 'php-ecosystem',
				'is_active' => 1,
			),
		);

		foreach ($sources as $source_data) {
			if (!isset($group_ids[$source_data['group_slug']])) {
				continue;
			}

			$source_id = $sources_repo->create(array(
				'name' => $source_data['name'],
				'url' => $source_data['url'],
				'source_type' => $source_data['source_type'],
				'source_group_id' => $group_ids[$source_data['group_slug']],
				'is_active' => $source_data['is_active'],
			));

			if ($source_id) {
				$this->created_items['sources'][] = array('id' => $source_id, 'name' => $source_data['name']);
				echo "  ✓ Created source: {$source_data['name']}\n";
			} else {
				$this->errors[] = "Failed to create source: {$source_data['name']}";
				echo "  ✗ Failed: {$source_data['name']}\n";
			}
		}
	}

	private function create_templates() {
		echo "<h2>Step 8: Creating Templates</h2>\n";

		$template_repo = new AIPS_Template_Repository();
		
		// Get voice IDs by name
		$voices = $this->get_created_voices_by_name();
		$structures = $this->get_created_structures_by_name();
		$categories = $this->get_created_categories_by_name();
		$source_groups = $this->get_created_source_groups_by_name();

		$templates = array(
			array(
				'name' => 'Beginner How-To',
				'description' => 'Beginner-friendly tutorials for fundamental concepts',
				'voice_name' => 'Hands-On Tutorial Coach',
				'structure_name' => 'Evergreen How-To Guide',
				'categories' => array('Backend Development', 'PHP Development'),
				'post_quantity' => 3,
				'prompt_template' => 'Write a comprehensive beginner-friendly tutorial about {{topic}}. Focus on helping developers learn by doing.',
				'generate_featured_image' => 0,
			),
			array(
				'name' => 'Intermediate Backend',
				'description' => 'Intermediate backend development patterns and practices',
				'voice_name' => 'DevStackTips Default',
				'structure_name' => 'Advanced Technical Tutorial',
				'categories' => array('Backend Development', 'Database'),
				'post_quantity' => 4,
				'prompt_template' => 'Write an intermediate-level backend development tutorial about {{topic}}. Assume familiarity with programming fundamentals.',
				'generate_featured_image' => 1,
				'featured_image_source' => 'ai_prompt',
				'image_prompt' => 'A clean, modern illustration representing {{topic}} in software development. Abstract geometric shapes, blues and teals, professional developer aesthetic, minimalist design',
			),
			array(
				'name' => 'Security Guide',
				'description' => 'Security best practices and vulnerability prevention',
				'voice_name' => 'Senior Backend Mentor',
				'structure_name' => 'Security Best Practices',
				'categories' => array('Security', 'Backend Development'),
				'post_quantity' => 3,
				'prompt_template' => 'Write a security-focused guide about {{topic}}. Help developers build secure applications by explaining vulnerabilities and secure patterns.',
				'generate_featured_image' => 1,
				'featured_image_source' => 'ai_prompt',
				'image_prompt' => 'A secure lock symbol overlaid on a modern application window or code editor, cybersecurity theme, dark blues and greens, shield iconography, professional and trustworthy aesthetic',
			),
			array(
				'name' => 'Framework Comparison',
				'description' => 'Fair comparisons of frameworks, libraries, and tools',
				'voice_name' => 'Neutral Technical Analyst',
				'structure_name' => 'Comparison Article',
				'categories' => array('Framework Guides'),
				'post_quantity' => 2,
				'prompt_template' => 'Write a balanced, objective comparison of {{topic}}. Present both options fairly without bias.',
				'generate_featured_image' => 0,
			),
			array(
				'name' => 'Developer Tooling',
				'description' => 'Practical guides for developer tools and workflows',
				'voice_name' => 'Hands-On Tutorial Coach',
				'structure_name' => 'Tool / Workflow Explainer',
				'categories' => array('DevOps & Tools'),
				'post_quantity' => 3,
				'prompt_template' => 'Write a practical guide for {{topic}}. Show developers how to use this tool effectively in their daily workflow.',
				'generate_featured_image' => 1,
				'featured_image_source' => 'unsplash',
				'unsplash_keywords' => 'developer tools, programming, code, terminal, workflow',
			),
			array(
				'name' => 'AI for Developers',
				'description' => 'Practical AI guidance for developer workflows',
				'voice_name' => 'AI Engineering Editor',
				'structure_name' => 'AI-for-Devs Article',
				'categories' => array('AI for Developers'),
				'post_quantity' => 2,
				'prompt_template' => 'Write practical guidance about {{topic}}. Focus on real developer use cases and address both benefits and limitations honestly.',
				'generate_featured_image' => 0,
			),
			array(
				'name' => 'Security News',
				'description' => 'Security-focused content informed by current vulnerability reports',
				'voice_name' => 'Senior Backend Mentor',
				'structure_name' => 'Security Best Practices',
				'categories' => array('Security'),
				'post_quantity' => 2,
				'source_group' => 'Security News',
				'include_sources' => 1,
				'prompt_template' => 'Write a security guide about {{topic}}. Reference current security trends and vulnerabilities where relevant.',
				'generate_featured_image' => 1,
				'featured_image_source' => 'ai_prompt',
				'image_prompt' => 'Cybersecurity shield protecting code and data, modern digital security concept, dark theme with accent colors',
			),
			array(
				'name' => 'PHP Framework Deep Dive',
				'description' => 'In-depth PHP framework tutorials informed by ecosystem updates',
				'voice_name' => 'DevStackTips Default',
				'structure_name' => 'Advanced Technical Tutorial',
				'categories' => array('PHP Development', 'Framework Guides'),
				'post_quantity' => 2,
				'source_group' => 'PHP Ecosystem',
				'include_sources' => 1,
				'prompt_template' => 'Write a detailed tutorial about {{topic}}. Incorporate current best practices from the PHP community.',
				'generate_featured_image' => 0,
			),
		);

		foreach ($templates as $template_data) {
			$voice_id = isset($voices[$template_data['voice_name']]) ? $voices[$template_data['voice_name']] : null;
			$structure_id = isset($structures[$template_data['structure_name']]) ? $structures[$template_data['structure_name']] : null;

			// Get category IDs
			$category_ids = array();
			if (isset($template_data['categories'])) {
				foreach ($template_data['categories'] as $cat_name) {
					if (isset($categories[$cat_name])) {
						$category_ids[] = $categories[$cat_name];
					}
				}
			}

			// Get source group ID if specified
			$source_group_ids = array();
			if (isset($template_data['source_group']) && isset($source_groups[$template_data['source_group']])) {
				$source_group_ids[] = $source_groups[$template_data['source_group']];
			}

			$template_id = $template_repo->create(array(
				'name' => $template_data['name'],
				'description' => isset($template_data['description']) ? $template_data['description'] : '',
				'prompt_template' => $template_data['prompt_template'],
				'voice_id' => $voice_id,
				'article_structure_id' => $structure_id,
				'post_quantity' => isset($template_data['post_quantity']) ? $template_data['post_quantity'] : 1,
				'post_status' => 'draft', // Start with drafts for review
				'post_type' => 'post',
				'post_category' => !empty($category_ids) ? $category_ids[0] : 0,
				'post_tags' => '',
				'post_author' => get_current_user_id() ?: (get_users(array('role' => 'administrator', 'number' => 1))[0]->ID ?? 1),
				'generate_featured_image' => isset($template_data['generate_featured_image']) ? $template_data['generate_featured_image'] : 0,
				'featured_image_source' => isset($template_data['featured_image_source']) ? $template_data['featured_image_source'] : 'ai_prompt',
				'image_prompt' => isset($template_data['image_prompt']) ? $template_data['image_prompt'] : '',
				'unsplash_keywords' => isset($template_data['unsplash_keywords']) ? $template_data['unsplash_keywords'] : '',
				'include_sources' => isset($template_data['include_sources']) ? $template_data['include_sources'] : 0,
				'source_group_ids' => wp_json_encode($source_group_ids),
				'is_active' => 1,
			));

			if ($template_id) {
				$this->created_items['templates'][] = array('id' => $template_id, 'name' => $template_data['name']);
				echo "✓ Created template: {$template_data['name']}\n";
			} else {
				$this->errors[] = "Failed to create template: {$template_data['name']}";
				echo "✗ Failed: {$template_data['name']}\n";
			}
		}
	}

	private function create_campaigns() {
		echo "<h2>Step 9: Creating Campaigns</h2>\n";

		$campaigns_repo = new AIPS_Campaigns_Repository();

		$campaigns = array(
			array(
				'name' => 'Developer Foundations',
				'content_goal' => 'Foundational tutorials for core development concepts and tools',
				'topics' => "How to Use Composer in PHP Projects\nUnderstanding Composer Autoloading\nGit Rebase vs Merge Explained\nHow to Resolve Git Merge Conflicts\nUnderstanding REST APIs for Developers\nSQL Joins Explained with Examples\nDocker Basics for PHP Developers\nUsing Environment Variables Safely in Applications\nPHP Namespaces Explained for Beginners\nHow to Debug a 500 Internal Server Error",
			),
			array(
				'name' => 'Backend Engineering',
				'content_goal' => 'Intermediate backend development patterns and practices',
				'topics' => "Repository Pattern in PHP Applications\nImplementing API Rate Limiting Strategies\nBackground Jobs and Queue Workers Explained\nDesigning Idempotent API Endpoints\nStructured Logging Best Practices\nAPI Authentication with Bearer Tokens\nHandling Retries Safely in Distributed Systems\nInput Validation vs Sanitization\nCaching Strategies for Web Applications\nService Layer Pattern in Backend Applications",
			),
			array(
				'name' => 'Security First',
				'content_goal' => 'Security best practices and vulnerability prevention',
				'topics' => "How to Prevent SQL Injection in PHP\nCSRF Protection Explained and Implemented\nXSS Prevention Best Practices\nSecure Password Storage with Hashing\nSecrets Management for Developers\nSecure File Upload Handling\nHTTPS, TLS, and SSL Certificates Explained\nSession Security Best Practices\nProtecting API Keys in Applications\nCommon WordPress Security Mistakes",
			),
			array(
				'name' => 'Framework & Tool Comparisons',
				'content_goal' => 'Fair comparisons of frameworks, libraries, and tools',
				'topics' => "Laravel vs Symfony: Framework Comparison\nMySQL vs PostgreSQL for Web Applications\nPHPUnit vs Pest for PHP Testing\nRedis vs Memcached for Caching\nREST vs GraphQL API Design\nDocker Compose vs Kubernetes for Development\nGit vs SVN Version Control\nJWT vs Session Cookies for Authentication\nNginx vs Apache Web Servers\nMicroservices vs Monolith Architecture",
			),
			array(
				'name' => 'Developer Tooling',
				'content_goal' => 'Practical guides for developer tools and workflows',
				'topics' => "Advanced Git Commands Every Developer Should Know\nComposer Scripts for Automation\nGit Hooks for Code Quality\nUsing Makefiles in Application Projects\nSSH Key Management Best Practices\nGit Interactive Rebase Tutorial\nDebugging Docker Builds\nLinux File Permissions Explained\nBash Scripting for Developers\nCI/CD Pipeline Basics",
			),
			array(
				'name' => 'AI for Developers',
				'content_goal' => 'Practical AI guidance for developer workflows',
				'topics' => "When AI Helps Developers Most\nRisks of AI-Generated Technical Content\nHow to Review AI-Written Code for Accuracy\nPrompt Engineering for Technical Documentation\nAI Agents for Developer Workflows\nWhat Makes AI Content Useful vs Spam\nAI-Assisted Code Review Best Practices\nUsing AI for API Documentation\nEvaluating AI-Generated Code Quality\nWhen NOT to Use AI in Development",
			),
		);

		foreach ($campaigns as $campaign_data) {
			$campaign_id = $campaigns_repo->create_campaign(array(
				'name' => $campaign_data['name'],
				'content_goal' => $campaign_data['content_goal'],
				'campaign_mode' => 'template',
				'is_active' => 1,
				'is_archived' => 0,
			));

			if ($campaign_id) {
				$this->created_items['campaigns'][] = array('id' => $campaign_id, 'name' => $campaign_data['name']);
				echo "✓ Created campaign: {$campaign_data['name']}\n";
				
				// Store topics as campaign notes (you can manually assign these to templates later)
				echo "  Topics to assign:\n";
				$topics_array = explode("\n", $campaign_data['topics']);
				foreach (array_slice($topics_array, 0, 3) as $topic) {
					echo "    - {$topic}\n";
				}
				echo "    ... and " . (count($topics_array) - 3) . " more\n";
			} else {
				$this->errors[] = "Failed to create campaign: {$campaign_data['name']}";
				echo "✗ Failed: {$campaign_data['name']}\n";
			}
		}
	}

	private function create_schedules() {
		echo "<h2>Step 10: Creating Schedules</h2>\n";

		$schedule_repo = new AIPS_Schedule_Repository();
		$templates = $this->get_created_templates_by_name();
		$interval_calc = AIPS_Interval_Calculator::instance();

		// Schedule configuration for 20 posts/week total
		// Distribution: Spread across weekdays with varying frequencies
		$schedules = array(
			array(
				'template_name' => 'Beginner How-To',
				'title' => 'Daily Beginner Tutorials',
				'frequency' => 'daily',
				'start_time' => '09:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Intermediate Backend',
				'title' => 'Backend Development - Twice Daily',
				'frequency' => 'every_12_hours',
				'start_time' => '10:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Security Guide',
				'title' => 'Security Content - Daily',
				'frequency' => 'daily',
				'start_time' => '11:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Framework Comparison',
				'title' => 'Framework Comparisons - Twice Weekly',
				'frequency' => 'weekly',
				'start_time' => '14:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Developer Tooling',
				'title' => 'Developer Tools - Daily',
				'frequency' => 'daily',
				'start_time' => '15:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'AI for Developers',
				'title' => 'AI Content - Twice Weekly',
				'frequency' => 'weekly',
				'start_time' => '16:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'Security News',
				'title' => 'Security News Digest - Twice Weekly',
				'frequency' => 'weekly',
				'start_time' => '13:00',
				'is_active' => 1,
			),
			array(
				'template_name' => 'PHP Framework Deep Dive',
				'title' => 'PHP Ecosystem Updates - Weekly',
				'frequency' => 'weekly',
				'start_time' => '12:00',
				'is_active' => 1,
			),
		);

		foreach ($schedules as $schedule_data) {
			if (!isset($templates[$schedule_data['template_name']])) {
				$this->errors[] = "Template not found for schedule: {$schedule_data['title']}";
				echo "✗ Template not found: {$schedule_data['template_name']}\n";
				continue;
			}

			$template_id = $templates[$schedule_data['template_name']];

			// Calculate next run time using WordPress site timezone
			$start_time = $schedule_data['start_time'];
			
			// Use WordPress site timezone for accurate schedule calculation
			$site_tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
			$today_site = new DateTimeImmutable('today ' . $start_time, $site_tz);
			$today_at_time_ts = $today_site->getTimestamp();
			
			// If that time has passed today, use tomorrow
			if ($today_at_time_ts < AIPS_DateTime::now()->timestamp()) {
				$today_at_time_ts = $today_site->modify('+1 day')->getTimestamp();
			}

			// Use interval calculator to get the proper next run based on frequency
			$next_run = $interval_calc->calculate_next_run($schedule_data['frequency'], $today_at_time_ts);

			$schedule_id = $schedule_repo->create(array(
				'template_id' => $template_id,
				'title' => $schedule_data['title'],
				'frequency' => $schedule_data['frequency'],
				'next_run' => $next_run,
				'last_run' => 0,
				'is_active' => $schedule_data['is_active'],
				'status' => 'active',
				'schedule_type' => 'post_generation',
			));

			if ($schedule_id) {
				$this->created_items['schedules'][] = array('id' => $schedule_id, 'title' => $schedule_data['title']);
				$next_run_formatted = AIPS_DateTime::fromTimestamp($next_run)->format('Y-m-d H:i:s');
				echo "✓ Created schedule: {$schedule_data['title']} (next run: {$next_run_formatted})\n";
			} else {
				$this->errors[] = "Failed to create schedule: {$schedule_data['title']}";
				echo "✗ Failed: {$schedule_data['title']}\n";
			}
		}
	}

	private function get_created_categories_by_name() {
		$map = array();
		if (isset($this->created_items['categories'])) {
			foreach ($this->created_items['categories'] as $category) {
				$map[$category['name']] = $category['id'];
			}
		}
		return $map;
	}

	private function get_created_source_groups_by_name() {
		$map = array();
		if (isset($this->created_items['source_groups'])) {
			foreach ($this->created_items['source_groups'] as $group) {
				$map[$group['name']] = $group['id'];
			}
		}
		return $map;
	}

	private function get_created_templates_by_name() {
		$map = array();
		if (isset($this->created_items['templates'])) {
			foreach ($this->created_items['templates'] as $template) {
				$map[$template['name']] = $template['id'];
			}
		}
		return $map;
	}

	private function get_created_voices_by_name() {
		$map = array();
		if (isset($this->created_items['voices'])) {
			foreach ($this->created_items['voices'] as $voice) {
				$map[$voice['name']] = $voice['id'];
			}
		}
		return $map;
	}

	private function get_created_structures_by_name() {
		$map = array();
		if (isset($this->created_items['structures'])) {
			foreach ($this->created_items['structures'] as $structure) {
				$map[$structure['name']] = $structure['id'];
			}
		}
		return $map;
	}

	private function print_summary() {
		echo "\n<h2>Setup Complete!</h2>\n";
		
		echo "<h3>Configured Settings (16 total):</h3>\n";
		echo "<ul>\n";
		echo "<li><strong>Content Strategy:</strong> Site niche, target audience, content goals, brand voice, guidelines, excluded topics</li>\n";
		echo "<li><strong>Resilience & Limits:</strong> Retry (3 attempts), Rate limiting (20 req/min), Circuit breaker (enabled)</li>\n";
		echo "<li><strong>Default Article Structure:</strong> Evergreen How-To Guide</li>\n";
		echo "<li><strong>Notifications:</strong> Email for critical alerts, DB for routine events, digest rollups enabled</li>\n";
		echo "<li><strong>Research & Discovery:</strong> 8 DevStackTips topics, similarity threshold 0.75</li>\n";
		echo "<li><strong>Performance:</strong> Telemetry enabled, Cache system (DB driver), 60-day log retention</li>\n";
		echo "<li><strong>Default Post Settings:</strong> Draft status, Backend Development category, admin author</li>\n";
		echo "<li><strong>⚠ Manual Configuration Required:</strong> Notification email address, Unsplash Access Key</li>\n";
		echo "</ul>\n";
		
		echo "<h3>Created Items:</h3>\n";
		echo "<ul>\n";
		
		$entity_types = array(
			'categories' => 'Categories',
			'voices' => 'Voices',
			'structures' => 'Article Structures',
			'authors' => 'Authors',
			'slices' => 'Post Slices',
			'source_groups' => 'Source Groups',
			'sources' => 'Sources',
			'templates' => 'Templates',
			'campaigns' => 'Campaigns',
			'schedules' => 'Schedules',
		);

		foreach ($entity_types as $key => $label) {
			if (isset($this->created_items[$key])) {
				$count = count($this->created_items[$key]);
				echo "<li><strong>{$label}:</strong> {$count} created</li>\n";
			}
		}
		
		echo "</ul>\n";

		if (!empty($this->errors)) {
			echo "<h3>Errors:</h3>\n";
			echo "<ul>\n";
			foreach ($this->errors as $error) {
				echo "<li>" . esc_html($error) . "</li>\n";
			}
			echo "</ul>\n";
		}

		echo "\n<h3>Next Steps:</h3>\n";
		echo "<ol>\n";
		echo "<li><strong>⚠ REQUIRED:</strong> Add notification email in Settings > Notifications</li>\n";
		echo "<li><strong>⚠ If using Unsplash:</strong> Add Unsplash Access Key in Settings > Featured Images</li>\n";
		echo "<li>Review all configured settings in Settings page (Content Strategy, Resilience, Notifications, etc.)</li>\n";
		echo "<li>Review created Categories, Templates, Voices, Structures in WordPress admin</li>\n";
		echo "<li>Verify Schedules are configured correctly (20 posts/week target)</li>\n";
		echo "<li>Check Source Groups and Sources for proper RSS feed URLs</li>\n";
		echo "<li>Start with 'Draft' post status to review content quality before auto-publishing</li>\n";
		echo "<li>Monitor History page, Operations Insights, and Telemetry for generation metrics</li>\n";
		echo "<li>Review and approve Author Topics before they generate posts</li>\n";
		echo "</ol>\n";

		echo "\n<h3>Rollback</h3>\n";
		echo "<p>To rollback all changes made by this script, run:</p>\n";
		echo "<pre>wp eval-file scripts/setup-devstacktips-content.php rollback</pre>\n";
		echo "<p>Or use the Dev Tools page and add 'rollback' as an argument.</p>\n";
	}

	/**
	 * Rollback all changes made by this setup script.
	 * 
	 * Deletes all created entities in reverse order to maintain referential integrity.
	 */
	private function rollback() {
		global $wpdb;

		echo "<h1>DevStackTips Content Rollback</h1>\n";
		echo "<p>Rolling back all setup changes...</p>\n";

		$deleted = array(
			'schedules' => 0,
			'campaigns' => 0,
			'templates' => 0,
			'sources' => 0,
			'source_groups' => 0,
			'slices' => 0,
			'authors' => 0,
			'structures' => 0,
			'sections' => 0,
			'voices' => 0,
			'categories' => 0,
			'settings' => 0,
		);

		// Reset settings to defaults first
		echo "<h2>Resetting Plugin Settings</h2>\n";
		
		// Reset Content Strategy settings
		$content_strategy_defaults = array(
			'aips_site_niche' => '',
			'aips_site_target_audience' => '',
			'aips_site_content_goals' => '',
			'aips_site_brand_voice' => '',
			'aips_site_content_language' => 'en',
			'aips_site_content_guidelines' => '',
			'aips_site_excluded_topics' => '',
		);
		
		foreach ($content_strategy_defaults as $key => $default) {
			update_option($key, $default);
			$deleted['settings']++;
		}
		echo "✓ Reset Content Strategy settings to defaults\n";
		
		// Reset Resilience & Limits settings
		$resilience_defaults = array(
			'aips_enable_retry' => 0,
			'aips_retry_max_attempts' => 3,
			'aips_retry_initial_delay' => 1,
			'aips_enable_rate_limiting' => 0,
			'aips_rate_limit_requests' => 10,
			'aips_rate_limit_period' => 60,
			'aips_enable_circuit_breaker' => 0,
			'aips_circuit_breaker_threshold' => 5,
			'aips_circuit_breaker_timeout' => 300,
		);
		
		foreach ($resilience_defaults as $key => $default) {
			update_option($key, $default);
			$deleted['settings']++;
		}
		echo "✓ Reset Resilience & Limits settings to defaults\n";

		// Reset Production settings
		$production_defaults = array(
			'aips_default_article_structure_id' => '',
			'aips_review_notifications_email' => '',
			'aips_notification_preferences' => array(),
			'aips_research_niches' => array(),
			'aips_topic_similarity_threshold' => 0.85,
			'aips_unsplash_access_key' => '',
			'aips_enable_telemetry' => false,
			'aips_enable_cache_system' => false,
			'aips_cache_driver' => 'array',
			'aips_cache_default_ttl' => 3600,
			'aips_log_retention_days' => 30,
			'aips_default_post_status' => 'draft',
			'aips_default_category' => '',
			'aips_default_post_author' => 1,
		);
		foreach ($production_defaults as $key => $value) {
			update_option($key, $value);
			$deleted['settings']++;
		}
		echo "✓ Reset Production settings to defaults\n";

		// Delete in reverse order of creation to maintain referential integrity

		// 1. Delete Schedules
		echo "<h2>Deleting Schedules</h2>\n";
		$schedule_titles = array(
			'Daily Beginner Tutorials',
			'Backend Development - Twice Daily',
			'Security Content - Daily',
			'Framework Comparisons - Twice Weekly',
			'Developer Tools - Daily',
			'AI Content - Twice Weekly',
			'Security News Digest - Twice Weekly',
			'PHP Ecosystem Updates - Weekly',
		);
		foreach ($schedule_titles as $title) {
			$count = $wpdb->delete(
				$wpdb->prefix . 'aips_schedule',
				array('title' => $title),
				array('%s')
			);
			if ($count > 0) {
				$deleted['schedules'] += $count;
				echo "✓ Deleted schedule: {$title}\n";
			}
		}

		// 2. Delete Campaigns
		echo "<h2>Deleting Campaigns</h2>\n";
		$campaign_names = array(
			'Developer Foundations',
			'Backend Engineering',
			'Security First',
			'Framework & Tool Comparisons',
			'Developer Tooling',
			'AI for Developers',
		);
		foreach ($campaign_names as $name) {
			$count = $wpdb->delete(
				$wpdb->prefix . 'aips_campaigns',
				array('name' => $name),
				array('%s')
			);
			if ($count > 0) {
				$deleted['campaigns'] += $count;
				echo "✓ Deleted campaign: {$name}\n";
			}
		}

		// 3. Delete Templates
		echo "<h2>Deleting Templates</h2>\n";
		$template_names = array(
			'Beginner How-To',
			'Intermediate Backend',
			'Security Guide',
			'Framework Comparison',
			'Developer Tooling',
			'AI for Developers',
			'Security News',
			'PHP Framework Deep Dive',
		);
		foreach ($template_names as $name) {
			$count = $wpdb->delete(
				$wpdb->prefix . 'aips_templates',
				array('name' => $name),
				array('%s')
			);
			if ($count > 0) {
				$deleted['templates'] += $count;
				echo "✓ Deleted template: {$name}\n";
			}
		}

		// 4. Delete Sources
		echo "<h2>Deleting Sources</h2>\n";
		$source_names = array(
			'OWASP Top 10',
			'Snyk Blog Security',
			'Security Week',
			'PHP.Watch',
			'Laravel News',
			'Symfony Blog',
		);
		foreach ($source_names as $name) {
			$count = $wpdb->delete(
				$wpdb->prefix . 'aips_sources',
				array('name' => $name),
				array('%s')
			);
			if ($count > 0) {
				$deleted['sources'] += $count;
				echo "✓ Deleted source: {$name}\n";
			}
		}

		// 5. Delete Source Groups (taxonomy terms)
		echo "<h2>Deleting Source Groups</h2>\n";
		$source_group_slugs = array('security-news', 'php-ecosystem');
		foreach ($source_group_slugs as $slug) {
			$term = term_exists($slug, 'aips_source_group');
			if ($term) {
				$result = wp_delete_term($term['term_id'], 'aips_source_group');
				if (!is_wp_error($result)) {
					$deleted['source_groups']++;
					echo "✓ Deleted source group: {$slug}\n";
				}
			}
		}

		// 6. Delete Post Slices
		echo "<h2>Deleting Post Slices</h2>\n";
		$slice_names = array(
			'Developer Resources Footer',
			'Security Disclaimer',
			'Code Example Standards',
			'Version Note',
		);
		foreach ($slice_names as $name) {
			$count = $wpdb->delete(
				$wpdb->prefix . 'aips_post_slices',
				array('name' => $name),
				array('%s')
			);
			if ($count > 0) {
				$deleted['slices'] += $count;
				echo "✓ Deleted post slice: {$name}\n";
			}
		}

		// 7. Delete Authors
		echo "<h2>Deleting Authors</h2>\n";
		$author_names = array(
			'Backend Architecture Specialist',
			'Security Expert',
			'DevOps Practitioner',
			'Framework Analyst',
			'AI Engineering Pragmatist',
		);
		foreach ($author_names as $name) {
			$count = $wpdb->delete(
				$wpdb->prefix . 'aips_authors',
				array('name' => $name),
				array('%s')
			);
			if ($count > 0) {
				$deleted['authors'] += $count;
				echo "✓ Deleted author: {$name}\n";
			}
		}

		// 8. Delete Article Structures
		echo "<h2>Deleting Article Structures</h2>\n";
		$structure_names = array(
			'Evergreen How-To Guide',
			'Advanced Technical Tutorial',
			'Comparison Article',
			'Architecture Deep Dive',
			'Security Best Practices',
			'Tool / Workflow Explainer',
			'AI-for-Devs Article',
			'News / Trend Analysis',
		);
		foreach ($structure_names as $name) {
			$count = $wpdb->delete(
				$wpdb->prefix . 'aips_article_structures',
				array('name' => $name),
				array('%s')
			);
			if ($count > 0) {
				$deleted['structures'] += $count;
				echo "✓ Deleted article structure: {$name}\n";
			}
		}

		// 9. Delete Prompt Sections (delete all sections created by this script)
		echo "<h2>Deleting Prompt Sections</h2>\n";
		$section_names = array(
			'Introduction',
			'What You\'ll Learn',
			'Prerequisites',
			'Core Concepts',
			'Step-by-Step Instructions',
			'Code Examples',
			'Common Mistakes',
			'Best Practices',
			'When to Use / When Not to Use',
			'Conclusion',
			'FAQ',
			'Problem Statement',
			'Technical Context',
			'Implementation Strategy',
			'Performance Considerations',
			'Security Considerations',
			'Testing / Validation',
			'Operational Tips',
			'Overview',
			'Quick Summary Table',
			'What Option A Is',
			'What Option B Is',
			'Developer Experience',
			'Ecosystem / Community',
			'Best Use Cases',
			'Final Recommendation',
		);
		foreach ($section_names as $name) {
			$count = $wpdb->delete(
				$wpdb->prefix . 'aips_prompt_sections',
				array('name' => $name),
				array('%s')
			);
			if ($count > 0) {
				$deleted['sections'] += $count;
				echo "✓ Deleted prompt section: {$name}\n";
			}
		}

		// 10. Delete Voices
		echo "<h2>Deleting Voices</h2>\n";
		$voice_names = array(
			'DevStackTips Default',
			'Senior Backend Mentor',
			'Hands-On Tutorial Coach',
			'Neutral Technical Analyst',
			'AI Engineering Editor',
		);
		foreach ($voice_names as $name) {
			$count = $wpdb->delete(
				$wpdb->prefix . 'aips_voices',
				array('name' => $name),
				array('%s')
			);
			if ($count > 0) {
				$deleted['voices'] += $count;
				echo "✓ Deleted voice: {$name}\n";
			}
		}

		// 11. Delete Categories (WordPress taxonomy terms)
		echo "<h2>Deleting Categories</h2>\n";
		$category_slugs = array(
			'backend-development',
			'security',
			'devops-tools',
			'framework-guides',
			'ai-for-developers',
			'php-development',
			'database',
		);
		foreach ($category_slugs as $slug) {
			$term = term_exists($slug, 'category');
			if ($term) {
				$result = wp_delete_term($term['term_id'], 'category');
				if (!is_wp_error($result)) {
					$deleted['categories']++;
					echo "✓ Deleted category: {$slug}\n";
				}
			}
		}

		// Summary
		echo "\n<h2>Rollback Complete</h2>\n";
		echo "<ul>\n";
		foreach ($deleted as $type => $count) {
			echo "<li><strong>" . ucfirst($type) . ":</strong> {$count} deleted</li>\n";
		}
		echo "</ul>\n";

		$total = array_sum($deleted);
		echo "\n<p><strong>Total items deleted:</strong> {$total}</p>\n";
		echo "<p>You can now re-run the setup script if needed.</p>\n";
	}
}

// Determine mode from CLI argument or query parameter
$rollback_mode = false;
if (PHP_SAPI === 'cli') {
	// CLI mode: check for 'rollback' argument
	global $argv;
	if (isset($argv) && in_array('rollback', $argv, true)) {
		$rollback_mode = true;
	}
} else {
	// Web mode: check for 'rollback' query parameter
	if (isset($_GET['rollback']) && $_GET['rollback'] === '1') {
		$rollback_mode = true;
	}
}

// Run the setup or rollback
$setup = new AIPS_DevStackTips_Setup($rollback_mode);
$setup->run();
