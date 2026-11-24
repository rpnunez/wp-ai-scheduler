<?php
/**
 * DevStackTips configuration definitions.
 *
 * The single source of truth for the DevStackTips v3 desired configuration and
 * seed data, keyed by the stable configuration keys that AIPS_Config_Binding
 * produces (see make_key). The ID-driven setup tool (scripts/devstacktips-setup.php)
 * reads these definitions and resolves each key to a live object ID through the
 * binding registry, so nothing here depends on per-site auto-increment IDs.
 *
 * Pure data + pure helpers: no database access, no WordPress runtime state.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_DevStackTips_Definitions {

	/**
	 * Shared house style for every generated image prompt.
	 */
	const HOUSE_STYLE = 'Flat vector technical illustration, dark navy background with blue and teal accents, clean geometric shapes, subtle grid, professional developer aesthetic, no text, no letters, no logos, 16:9';

	/**
	 * House style string.
	 *
	 * @return string
	 */
	public static function house_style() {
		return self::HOUSE_STYLE;
	}

	/**
	 * Featured-image prompt for a subject, in the house style.
	 *
	 * @param string $subject
	 * @return string
	 */
	public static function featured_image_prompt($subject) {
		return self::HOUSE_STYLE . '. Concept illustration for a technical article about {{topic}}: ' . $subject . '.';
	}

	/**
	 * In-content image prompt for a subject, in the house style.
	 *
	 * @param string $prompt Subject-specific prompt fragment.
	 * @return string
	 */
	public static function content_image_prompt($prompt) {
		return self::HOUSE_STYLE . '. ' . $prompt;
	}

	/**
	 * Slug prompt for a content type.
	 *
	 * @param string $type
	 * @return string
	 */
	public static function slug_prompt($type) {
		$base = 'Return only a URL slug: lowercase, words separated by hyphens, 3 to 6 words, primary keyword first, no stop words (a, the, and, of, to, for, with), no dates or years, no version numbers unless they define the topic, no punctuation.';

		$rules = array(
			'tutorial'     => 'Start with the main technology or task, e.g. "php-enum-validation-guide".',
			'comparison'   => 'Use the pattern "x-vs-y" followed by at most two context words, e.g. "laravel-vs-symfony-apis".',
			'security'     => 'Lead with the vulnerability or control, e.g. "sql-injection-prevention-php".',
			'news'         => 'Lead with the affected product or CVE identifier and the issue, e.g. "cve-2026-1234-openssl-patch". Keep it evergreen: no "latest" or "new".',
			'architecture' => 'Lead with the pattern or design concept, e.g. "hexagonal-architecture-php-services".',
			'refactoring'  => 'Lead with the code smell or refactoring move, e.g. "refactor-god-class-php".',
			'testing'      => 'Lead with the testing technique, e.g. "phpunit-test-doubles-guide".',
			'tooling'      => 'Lead with the tool name and the task, e.g. "docker-compose-php-dev-setup".',
			'ai'           => 'Lead with the AI technique or tool and the developer task, e.g. "llm-code-review-workflow".',
			'hosting'      => 'Lead with the hosting or server topic, e.g. "configure-apache-virtual-hosts" or "shared-vs-vps-hosting".',
			'default'      => 'Lead with the most specific technical term in the title.',
		);

		return $base . ' ' . (isset($rules[$type]) ? $rules[$type] : $rules['default']);
	}

	/**
	 * Global settings v3 always enforces.
	 *
	 * @return array<string,mixed> Option name => value.
	 */
	public static function settings() {
		return array(
			'aips_allowed_image_sources'  => array('ai_prompt', 'media_library'),
			'aips_max_post_images_limit'  => 3,
			'aips_ai_assistance_guidance' => 'Write for DevStackTips: professional software engineers and web developers (mid to senior level). Voice: authoritative yet approachable, expert-level, practical. Prefer real-world patterns over theory, keep a security-first mindset, use precise technical terms, and avoid hype, clickbait and filler. Keep suggestions concise and specific to PHP, backend, architecture, security, DevOps, testing and AI-for-developers topics.',
		);
	}

	/**
	 * Seed settings created only when missing (never overwrites existing values).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function seed_settings() {
		return array(
			array(
				'option_name' => 'aips_site_niche',
				'value'       => 'Full-Stack Software Development, Web Architecture',
			),
			array(
				'option_name' => 'aips_site_target_audience',
				'value'       => 'Professional Software Engineers, Web Developers (Mid to Senior Level)',
			),
			array(
				'option_name' => 'aips_site_content_goals',
				'value'       => 'Provide authoritative technical deep-dives, establish industry credibility, drive affiliate revenue via trusted/actionable recommendations, and rank for high-value "how-to" and "best practice" keywords.',
			),
			array(
				'option_name' => 'aips_site_brand_voice',
				'value'       => 'Authoritative yet approachable, Professional, expert-level',
			),
			array(
				'option_name' => 'aips_site_content_language',
				'value'       => 'en',
			),
			array(
				'option_name' => 'aips_site_content_guidelines',
				'value'       => 'Minimum 5-10 paragraphs depending on topic. Use H2/H3 hierarchy. Mandatory: brief hyperlinked a "Sources" section. Break code into small, explained chunks. Security focus. Always emphasize "real-world" patterns.',
			),
			array(
				'option_name' => 'aips_site_excluded_topics',
				'value'       => 'Politics, religion, celebrity gossip, gambling, adult content, clickbait/listicles with no technical depth ("get rich quick" schemes, unverified rumors/hacks about hardware, non-technical software (e.g., Word/Excel), and general consumer electronics reviews.',
			),
			array(
				'option_name' => 'aips_enable_retry',
				'value'       => 1,
			),
			array(
				'option_name' => 'aips_retry_max_attempts',
				'value'       => 3,
			),
			array(
				'option_name' => 'aips_retry_initial_delay',
				'value'       => 2,
			),
			array(
				'option_name' => 'aips_enable_rate_limiting',
				'value'       => 1,
			),
			array(
				'option_name' => 'aips_rate_limit_requests',
				'value'       => 20,
			),
			array(
				'option_name' => 'aips_rate_limit_period',
				'value'       => 60,
			),
			array(
				'option_name' => 'aips_enable_circuit_breaker',
				'value'       => 1,
			),
			array(
				'option_name' => 'aips_circuit_breaker_threshold',
				'value'       => 5,
			),
			array(
				'option_name' => 'aips_circuit_breaker_timeout',
				'value'       => 300,
			),
			array(
				'option_name'   => 'aips_default_article_structure_id',
				'structure_key' => AIPS_Config_Binding::make_key('structures', 'Evergreen How-To Guide'),
			),
			array(
				'option_name' => 'aips_review_notifications_email',
				'value'       => '',
			),
			array(
				'option_name' => 'aips_notification_preferences',
				'value'       => array(
					'generation_failed'            => 'email',
					'quota_alert'                  => 'email',
					'post_ready_for_review'        => 'both',
					'template_generated'           => 'db',
					'manual_generation_completed'  => 'db',
					'partial_generation_completed' => 'both',
					'author_topics_generated'      => 'db',
					'author_topics_failed'         => 'email',
					'author_posts_generated'       => 'db',
					'author_posts_failed'          => 'email',
					'bulk_batch_completed'         => 'db',
					'errors'                       => 'email',
					'daily_digest'                 => 'email',
					'weekly_digest'                => 'email',
					'monthly_digest'               => 'email',
				),
			),
			array(
				'option_name' => 'aips_research_niches',
				'value'       => array(
					'PHP and Backend Development Trends',
					'Application Security and Vulnerability Prevention',
					'Modern Framework Comparisons (Laravel, Symfony, etc.)',
					'DevOps Tools and Automation',
					'AI Tools for Developers',
					'Database Optimization and Best Practices',
					'Software Architecture Patterns',
					'API Design and Integration',
				),
			),
			array(
				'option_name' => 'aips_topic_similarity_threshold',
				'value'       => 0.75,
			),
			array(
				'option_name' => 'aips_unsplash_access_key',
				'value'       => '',
			),
			array(
				'option_name' => 'aips_enable_telemetry',
				'value'       => true,
			),
			array(
				'option_name' => 'aips_enable_cache_system',
				'value'       => false,
			),
			array(
				'option_name' => 'aips_log_retention_days',
				'value'       => 60,
			),
			array(
				'option_name' => 'aips_default_post_status',
				'value'       => 'draft',
			),
			array(
				'option_name'  => 'aips_default_category',
				'category_key' => AIPS_Config_Binding::make_key('categories', 'backend-development'),
			),
			array(
				'option_name' => 'aips_default_post_author',
				'value'       => 1,
			),
		);
	}

	/**
	 * WordPress category seeds (created when missing).
	 *
	 * @return array List of ['name','slug','description'].
	 */
	public static function categories() {
		return array(
			array(
				'name'        => 'Backend Development',
				'slug'        => 'backend-development',
				'description' => 'Server-side programming, APIs, databases, and backend architecture',
			),
			array(
				'name'        => 'Security',
				'slug'        => 'security',
				'description' => 'Application security, vulnerabilities, and secure coding practices',
			),
			array(
				'name'        => 'DevOps & Tools',
				'slug'        => 'devops-tools',
				'description' => 'Developer tools, workflows, Git, Docker, and automation',
			),
			array(
				'name'        => 'Framework Guides',
				'slug'        => 'framework-guides',
				'description' => 'Framework tutorials and comparisons',
			),
			array(
				'name'        => 'AI for Developers',
				'slug'        => 'ai-for-developers',
				'description' => 'Practical AI tools and workflows for software development',
			),
			array(
				'name'        => 'PHP Development',
				'slug'        => 'php-development',
				'description' => 'PHP tutorials, patterns, and best practices',
			),
			array(
				'name'        => 'Database',
				'slug'        => 'database',
				'description' => 'SQL, database design, optimization, and management',
			),
		);
	}

	/**
	 * Writing voice seeds (created when missing).
	 *
	 * @return array List of voice data arrays with content_instructions normalized to string.
	 */
	public static function voices() {
		$voices = array(
			array(
				'name'                 => 'DevStackTips Default',
				'title_prompt'         => 'Create a clear, technical, and specific title for this topic. Avoid generic phrases like "ultimate guide" or "everything you need to know". Make it actionable and developer-focused.',
				'content_instructions' => array(
					'You are writing for software developers and technical readers on DevStackTips, a practical developer resource site.',
					'Writing style:',
					'- Be clear and concrete',
					'- Use short paragraphs and informative headings',
					'- Prefer practical examples over abstract explanations',
					'- Be technically confident but not arrogant',
					'- Avoid hype, filler, and exaggerated claims',
					'- Never mention being an AI',
					'- Do not use phrases like "ultimate guide" or "game-changing"',
					'Content approach:',
					'- Focus on what developers need to know',
					'- Include code examples where relevant',
					'- Explain the "why" behind recommendations',
					'- Be honest about tradeoffs and limitations',
					'- Use active voice',
					'- Keep intros brief and get to the point quickly',
					'Avoid:',
					'- Marketing language or sales pitches',
					'- Generic filler sentences',
					'- Inventing statistics or benchmarks',
					'- Making version-specific claims without verification',
					'- Overly broad generalizations',
				),
				'excerpt_instructions' => 'Write a concise 1-2 sentence summary that captures the core value for developers.',
				'is_active'            => 1,
			),
			array(
				'name'                 => 'Senior Backend Mentor',
				'title_prompt'         => 'Create a title that emphasizes the architectural or design aspect. Use terms like "patterns", "strategies", "best practices", or "architecture".',
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
				'is_active'            => 1,
			),
			array(
				'name'                 => 'Hands-On Tutorial Coach',
				'title_prompt'         => 'Create a "How to..." title that clearly states what the reader will accomplish. Be specific about the outcome.',
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
				'is_active'            => 1,
			),
			array(
				'name'                 => 'Neutral Technical Analyst',
				'title_prompt'         => 'Create a balanced comparison title using "vs" or "Comparing". Avoid suggesting one option is superior.',
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
				'is_active'            => 1,
			),
			array(
				'name'                 => 'AI Engineering Editor',
				'title_prompt'         => 'Create a title that addresses AI tools or workflows pragmatically. Include "for Developers" or "in Development" to keep focus practical.',
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
				'is_active'            => 1,
			),
			array(
				'name'                 => 'Staff Software Architect',
				'title_prompt'         => 'Create a title emphasizing system architecture, design patterns, or domain modeling (e.g., "Designing a Resilient Event-Driven System", "Domain-Driven Design in Practice"). Make it authoritative, clear, and technically profound.',
				'content_instructions' => 'You are a staff software architect providing strategic architectural guidance on DevStackTips.

Writing approach:
- Focus on system boundaries, high cohesion, low coupling, and modularity
- Deeply analyze architectural trade-offs (e.g., consistency vs availability, simplicity vs flexibility)
- Provide clear structural diagrams (ASCII or text decomposition)
- Balance immediate pragmatic delivery with long-term evolvability
- Address failure modes, scalability limits, and resilience patterns

Technical depth:
- Target senior software engineers and technical leaders
- Use precise architectural terminology (bounded contexts, aggregates, ports and adapters, saga pattern)
- Compare alternative patterns before justifying the recommended approach
- Include concrete, idiomatic code examples implementing the pattern cleanly

Avoid:
- Abstract architectural hand-waving without code implementation
- Dogmatic purity that ignores real-world operational realities
- Superficial design pattern overviews without trade-off analysis',
				'excerpt_instructions' => 'Summarize the foundational architectural principle and high-impact structural trade-off.',
				'is_active'            => 1,
			),
			array(
				'name'                 => 'Test Automation & QA Lead',
				'title_prompt'         => 'Create a title emphasizing automated testing strategies, TDD cycles, or quality engineering (e.g., "Mastering TDD with Mocking Strategies", "Building Robust Integration Tests for Distributed APIs").',
				'content_instructions' => 'You are a quality engineering and test automation lead guiding developers on DevStackTips.

Teaching style:
- Advocate rigorous, test-first thinking and disciplined TDD cycles
- Demonstrate clear Red-Green-Refactor steps
- Emphasize test isolation, speed, determinism, and maintainability
- Teach proper test double usage (distinguish mocks, stubs, fakes, and spies)
- Focus on eliminating flaky tests and building mutation-resistant suites

Content structure:
- Start with the testing scenario and requirements
- Show how to write the failing test assertion first
- Provide the minimal implementation to pass
- Refactor the code while keeping tests green
- Cover edge cases, mutation testing, and CI pipeline automation

Tone:
- Methodical, encouraging, and quality-driven
- Practical with concrete test assertions and frameworks
- Focused on confidence in continuous delivery',
				'excerpt_instructions' => 'Highlight the primary testing methodology, TDD cycle, or quality assurance pattern discussed.',
				'is_active'            => 1,
			),
			array(
				'name'                 => 'Code Craftsman & Refactoring Lead',
				'title_prompt'         => 'Create a title emphasizing safe code refactoring, code smell elimination, or technical debt management (e.g., "Refactoring God Classes into Clean Services", "Eliminating Code Smells with Strangler Patterns").',
				'content_instructions' => 'You are a seasoned code craftsman specializing in legacy modernization and refactoring on DevStackTips.

Refactoring approach:
- Treat refactoring as disciplined, incremental behavior-preserving transformations
- Always establish characterization tests and safety nets BEFORE changing code
- Explicitly identify code smells (God Class, Feature Envy, Primitive Obsession, Hidden Coupling)
- Demonstrate step-by-step micro-refactorings with clear before/after diffs
- Emphasize clean code, readability, maintainability, and SOLID principles

Analysis style:
- Ground recommendations in real-world messy codebases, not toy examples
- Show how to pay down technical debt incrementally without risky total rewrites
- Benchmark or assess clean code against performance implications
- Emphasize automated static analysis and linting rules

Tone:
- Pragmatic, empathetic to legacy constraints, disciplined, and craftsmanship-oriented',
				'excerpt_instructions' => 'Highlight the specific code smell eliminated and the core refactoring step applied.',
				'is_active'            => 1,
			),
		);

		foreach ($voices as &$voice) {
			if (isset($voice['content_instructions']) && is_array($voice['content_instructions'])) {
				$voice['content_instructions'] = implode("\n", $voice['content_instructions']);
			}
		}
		unset($voice);

		return $voices;
	}

	/**
	 * Prompt section seeds keyed by section_key.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function sections() {
		return array(
			'introduction' => array(
				'name'        => 'Introduction',
				'section_key' => 'introduction',
				'description' => 'Opening section that establishes context and relevance',
				'content'     => 'Start with a brief introduction explaining why this topic matters to developers. Keep it concise and focused on practical relevance.',
				'is_active'   => 1,
			),
			'what_youll_learn' => array(
				'name'        => 'What You\'ll Learn',
				'section_key' => 'what_youll_learn',
				'description' => 'Learning objectives section',
				'content'     => 'List 3-5 specific things the reader will learn or be able to do after reading this article.',
				'is_active'   => 1,
			),
			'prerequisites' => array(
				'name'        => 'Prerequisites',
				'section_key' => 'prerequisites',
				'description' => 'Required knowledge and setup',
				'content'     => 'List any required knowledge, tools, or setup needed before following this tutorial.',
				'is_active'   => 1,
			),
			'core_concepts' => array(
				'name'        => 'Core Concepts',
				'section_key' => 'core_concepts',
				'description' => 'Fundamental concepts overview',
				'content'     => 'Explain the fundamental concepts readers need to understand. Define key terms and provide context.',
				'is_active'   => 1,
			),
			'step_by_step' => array(
				'name'        => 'Step-by-Step Instructions',
				'section_key' => 'step_by_step',
				'description' => 'Sequential implementation steps',
				'content'     => 'Provide numbered, sequential steps. For each step, explain what to do, why, and what the expected result is.',
				'is_active'   => 1,
			),
			'code_examples' => array(
				'name'        => 'Code Examples',
				'section_key' => 'code_examples',
				'description' => 'Working code samples',
				'content'     => 'Include complete, working code examples with explanations. Use code blocks with proper syntax highlighting.',
				'is_active'   => 1,
			),
			'common_mistakes' => array(
				'name'        => 'Common Mistakes',
				'section_key' => 'common_mistakes',
				'description' => 'Pitfalls to avoid',
				'content'     => 'List 3-5 common mistakes developers make with this approach, and how to avoid them.',
				'is_active'   => 1,
			),
			'best_practices' => array(
				'name'        => 'Best Practices',
				'section_key' => 'best_practices',
				'description' => 'Recommended approaches',
				'content'     => 'Share best practices and recommendations for production use.',
				'is_active'   => 1,
			),
			'when_to_use' => array(
				'name'        => 'When to Use / When Not to Use',
				'section_key' => 'when_to_use',
				'description' => 'Use case guidance',
				'content'     => 'Explain when this approach is ideal and when alternatives should be considered. Be honest about limitations.',
				'is_active'   => 1,
			),
			'conclusion' => array(
				'name'        => 'Conclusion',
				'section_key' => 'conclusion',
				'description' => 'Summary and next steps',
				'content'     => 'Summarize key takeaways and suggest next steps for further learning.',
				'is_active'   => 1,
			),
			'faq' => array(
				'name'        => 'FAQ',
				'section_key' => 'faq',
				'description' => 'Frequently asked questions',
				'content'     => 'Answer 3-5 common questions related to this topic.',
				'is_active'   => 1,
			),
			'problem_statement' => array(
				'name'        => 'Problem Statement',
				'section_key' => 'problem_statement',
				'description' => 'Define the problem being solved',
				'content'     => 'Clearly define the problem or challenge this approach addresses.',
				'is_active'   => 1,
			),
			'technical_context' => array(
				'name'        => 'Technical Context',
				'section_key' => 'technical_context',
				'description' => 'Background and context',
				'content'     => 'Provide technical background and context needed to understand the solution.',
				'is_active'   => 1,
			),
			'implementation_strategy' => array(
				'name'        => 'Implementation Strategy',
				'section_key' => 'implementation_strategy',
				'description' => 'Overall approach',
				'content'     => 'Outline the overall implementation strategy before diving into details.',
				'is_active'   => 1,
			),
			'performance_considerations' => array(
				'name'        => 'Performance Considerations',
				'section_key' => 'performance_considerations',
				'description' => 'Performance implications',
				'content'     => 'Discuss performance implications, optimization opportunities, and scalability concerns.',
				'is_active'   => 1,
			),
			'security_considerations' => array(
				'name'        => 'Security Considerations',
				'section_key' => 'security_considerations',
				'description' => 'Security implications',
				'content'     => 'Address security implications, risks, and secure coding practices.',
				'is_active'   => 1,
			),
			'testing_validation' => array(
				'name'        => 'Testing / Validation',
				'section_key' => 'testing_validation',
				'description' => 'How to test and validate',
				'content'     => 'Explain how to test and validate the implementation.',
				'is_active'   => 1,
			),
			'operational_tips' => array(
				'name'        => 'Operational Tips',
				'section_key' => 'operational_tips',
				'description' => 'Production operation guidance',
				'content'     => 'Provide operational guidance for running this in production (monitoring, logging, debugging).',
				'is_active'   => 1,
			),
			'comparison_overview' => array(
				'name'        => 'Overview',
				'section_key' => 'comparison_overview',
				'description' => 'Comparison introduction',
				'content'     => 'Introduce both options being compared and the criteria for comparison.',
				'is_active'   => 1,
			),
			'quick_summary_table' => array(
				'name'        => 'Quick Summary Table',
				'section_key' => 'quick_summary_table',
				'description' => 'At-a-glance comparison',
				'content'     => 'Create a comparison table with key features side-by-side.',
				'is_active'   => 1,
			),
			'option_a' => array(
				'name'        => 'What Option A Is',
				'section_key' => 'option_a',
				'description' => 'First option overview',
				'content'     => 'Explain what the first option is, its history, and core features.',
				'is_active'   => 1,
			),
			'option_b' => array(
				'name'        => 'What Option B Is',
				'section_key' => 'option_b',
				'description' => 'Second option overview',
				'content'     => 'Explain what the second option is, its history, and core features.',
				'is_active'   => 1,
			),
			'developer_experience' => array(
				'name'        => 'Developer Experience',
				'section_key' => 'developer_experience',
				'description' => 'DX comparison',
				'content'     => 'Compare the developer experience, learning curve, and documentation quality.',
				'is_active'   => 1,
			),
			'ecosystem_community' => array(
				'name'        => 'Ecosystem / Community',
				'section_key' => 'ecosystem_community',
				'description' => 'Community and ecosystem',
				'content'     => 'Compare community size, available packages/plugins, and ecosystem maturity.',
				'is_active'   => 1,
			),
			'use_cases' => array(
				'name'        => 'Best Use Cases',
				'section_key' => 'use_cases',
				'description' => 'Recommended use cases',
				'content'     => 'Describe the ideal use cases for each option.',
				'is_active'   => 1,
			),
			'recommendation' => array(
				'name'        => 'Final Recommendation',
				'section_key' => 'recommendation',
				'description' => 'Summary and guidance',
				'content'     => 'Provide balanced recommendations based on different scenarios and requirements.',
				'is_active'   => 1,
			),
			'architectural_tradeoffs' => array(
				'name'        => 'Architectural Trade-offs & Alternatives',
				'section_key' => 'architectural_tradeoffs',
				'description' => 'Analysis of architectural trade-offs, complexity vs maintainability, and alternative designs',
				'content'     => 'Deeply analyze the architectural trade-offs of this pattern (e.g. coupling vs cohesion, latency vs consistency, simplicity vs flexibility). Discuss alternative patterns and why this specific solution was chosen.',
				'is_active'   => 1,
			),
			'system_diagram_concept' => array(
				'name'        => 'System & Component Architecture',
				'section_key' => 'system_diagram_concept',
				'description' => 'Visual or structural breakdown of components, boundaries, and data flow',
				'content'     => 'Provide a clear textual/ASCII diagram or structured decomposition showing how components, modules, or services interact, detailing boundaries and data flows.',
				'is_active'   => 1,
			),
			'smelly_code_analysis' => array(
				'name'        => 'Code Smell & Anti-Pattern Analysis',
				'section_key' => 'smelly_code_analysis',
				'description' => 'Inspection of problematic legacy code and identified design flaws',
				'content'     => 'Show the original problematic or smelly code. Pinpoint exact anti-patterns (e.g. God Class, Feature Envy, Primitive Obsession, Hidden Coupling) and explain the business/technical risks of leaving it unchanged.',
				'is_active'   => 1,
			),
			'safety_nets_tests' => array(
				'name'        => 'Safety Nets & Characterization Tests',
				'section_key' => 'safety_nets_tests',
				'description' => 'Establishing test coverage and regression baselines before refactoring',
				'content'     => 'Explain how to wrap the existing code with characterization tests and regression safety nets before making any changes, ensuring behavior preservation.',
				'is_active'   => 1,
			),
			'step_by_step_refactoring' => array(
				'name'        => 'Step-by-Step Refactoring Sequence',
				'section_key' => 'step_by_step_refactoring',
				'description' => 'Disciplined micro-steps to transform the code cleanly',
				'content'     => 'Walk through the refactoring process in incremental, test-backed micro-steps. Show before/after diffs for each transformation step.',
				'is_active'   => 1,
			),
			'test_scenario_setup' => array(
				'name'        => 'Test Scenarios & Fixture Design',
				'section_key' => 'test_scenario_setup',
				'description' => 'Defining testing scenarios, state fixtures, and test doubles',
				'content'     => 'Outline the exact test scenarios, state fixtures, and mock/stub boundaries needed to test this behavior thoroughly without fragile dependencies.',
				'is_active'   => 1,
			),
			'red_green_refactor' => array(
				'name'        => 'Red-Green-Refactor Cycle',
				'section_key' => 'red_green_refactor',
				'description' => 'TDD cycle walkthrough with failing test, minimal implementation, and clean refactor',
				'content'     => 'Demonstrate the TDD cycle: write the failing assertion (Red), write the simplest code to pass (Green), and refactor for elegance and performance (Refactor).',
				'is_active'   => 1,
			),
			'mutation_edge_cases' => array(
				'name'        => 'Edge Cases & Mutation Resistance',
				'section_key' => 'mutation_edge_cases',
				'description' => 'Testing boundaries, failure modes, and mutation test verification',
				'content'     => 'Identify critical edge cases, null/unexpected inputs, failure modes, and how to verify test suite quality using mutation testing concepts.',
				'is_active'   => 1,
			),
		);
	}

	/**
	 * Article structure seeds (created when missing).
	 *
	 * @return array List of ['name','description','sections'].
	 */
	public static function structures() {
		return array(
			array(
				'name'        => 'Evergreen How-To Guide',
				'description' => 'For foundational tutorials and step-by-step guides',
				'sections'    => array('introduction', 'what_youll_learn', 'prerequisites', 'core_concepts', 'step_by_step', 'code_examples', 'common_mistakes', 'best_practices', 'when_to_use', 'conclusion', 'faq'),
			),
			array(
				'name'        => 'Advanced Technical Tutorial',
				'description' => 'For deeper backend, security, and DevOps content',
				'sections'    => array('problem_statement', 'technical_context', 'prerequisites', 'implementation_strategy', 'step_by_step', 'code_examples', 'performance_considerations', 'security_considerations', 'testing_validation', 'operational_tips', 'conclusion'),
			),
			array(
				'name'        => 'Comparison Article',
				'description' => 'For framework, tool, and approach comparisons',
				'sections'    => array('comparison_overview', 'quick_summary_table', 'option_a', 'option_b', 'developer_experience', 'performance_considerations', 'ecosystem_community', 'use_cases', 'recommendation'),
			),
			array(
				'name'        => 'Architecture Deep Dive',
				'description' => 'For design patterns and system architecture topics',
				'sections'    => array('problem_statement', 'core_concepts', 'technical_context', 'implementation_strategy', 'best_practices', 'common_mistakes', 'use_cases', 'conclusion'),
			),
			array(
				'name'        => 'Security Best Practices',
				'description' => 'For security-focused content',
				'sections'    => array('problem_statement', 'security_considerations', 'best_practices', 'code_examples', 'testing_validation', 'operational_tips', 'conclusion'),
			),
			array(
				'name'        => 'Tool / Workflow Explainer',
				'description' => 'For Git, Composer, Docker, CI/CD, SSH, etc.',
				'sections'    => array('introduction', 'core_concepts', 'step_by_step', 'code_examples', 'common_mistakes', 'best_practices', 'conclusion'),
			),
			array(
				'name'        => 'AI-for-Devs Article',
				'description' => 'For AI workflow and tooling content',
				'sections'    => array('problem_statement', 'best_practices', 'code_examples', 'use_cases', 'security_considerations', 'when_to_use', 'conclusion'),
			),
			array(
				'name'        => 'News / Trend Analysis',
				'description' => 'For timely technical analysis',
				'sections'    => array('introduction', 'technical_context', 'use_cases', 'recommendation', 'conclusion'),
			),
			array(
				'name'        => 'Architecture Pattern Deep Dive',
				'description' => 'For system architecture, design patterns, domain modeling, and modularity',
				'sections'    => array('problem_statement', 'system_diagram_concept', 'architectural_tradeoffs', 'implementation_strategy', 'step_by_step', 'code_examples', 'best_practices', 'common_mistakes', 'conclusion'),
			),
			array(
				'name'        => 'Refactoring & Code Smells Case Study',
				'description' => 'For legacy code modernization, code smell elimination, and safe refactoring workflows',
				'sections'    => array('problem_statement', 'smelly_code_analysis', 'safety_nets_tests', 'step_by_step_refactoring', 'code_examples', 'best_practices', 'operational_tips', 'conclusion'),
			),
			array(
				'name'        => 'Test Strategy & Automation Guide',
				'description' => 'For TDD, integration testing, test doubles, and quality automation pipelines',
				'sections'    => array('problem_statement', 'test_scenario_setup', 'red_green_refactor', 'code_examples', 'mutation_edge_cases', 'best_practices', 'testing_validation', 'conclusion'),
			),
		);
	}

	/**
	 * Post slice seeds (created when missing).
	 *
	 * @return array List of ['name','description','sort_order','is_active'].
	 */
	public static function post_slices() {
		return array(
			array(
				'name'        => 'Developer Resources Footer',
				'description' => 'Add an additional resources list and next-steps guidance for readers who want to keep learning and apply the topic in a small project.',
				'sort_order'  => 10,
				'is_active'   => 1,
			),
			array(
				'name'        => 'Security Disclaimer',
				'description' => 'Insert a short security reminder that emphasizes validating and sanitizing input, distrust of external data, and least-privilege practices.',
				'sort_order'  => 20,
				'is_active'   => 1,
			),
			array(
				'name'        => 'Code Example Standards',
				'description' => 'Prepend a note that code samples should be reviewed and adapted for error handling, edge cases, and project-specific constraints.',
				'sort_order'  => 30,
				'is_active'   => 1,
			),
			array(
				'name'        => 'Version Note',
				'description' => 'Append a version disclaimer reminding readers to check current upstream documentation because APIs and tooling evolve over time.',
				'sort_order'  => 40,
				'is_active'   => 1,
			),
		);
	}

	/**
	 * Source group seeds (v2 + v3, created when missing).
	 *
	 * @return array List of ['name','slug','description'].
	 */
	public static function source_groups() {
		return array(
			array(
				'name'        => 'Security News',
				'slug'        => 'security-news',
				'description' => 'Security vulnerabilities, patches, and best practices',
			),
			array(
				'name'        => 'PHP Ecosystem',
				'slug'        => 'php-ecosystem',
				'description' => 'PHP frameworks, libraries, and community updates',
			),
			array(
				'name'        => 'Backend & Architecture',
				'slug'        => 'backend-architecture',
				'description' => 'Software architecture, backend engineering, testing and refactoring practice',
			),
			array(
				'name'        => 'AI & Dev Tooling',
				'slug'        => 'ai-dev-tooling',
				'description' => 'AI for developers, developer tools, editors, containers and workflows',
			),
		);
	}

	/**
	 * Source seeds (v2 + v3, created when missing).
	 *
	 * @return array List of ['name','url','group_slug','is_active'].
	 */
	public static function sources() {
		return array(
			array('name' => 'OWASP Top 10', 'url' => 'https://owasp.org/www-project-top-ten/', 'group_slug' => 'security-news', 'is_active' => 1),
			array('name' => 'Snyk Blog Security', 'url' => 'https://snyk.io/blog/', 'group_slug' => 'security-news', 'is_active' => 1),
			array('name' => 'Security Week', 'url' => 'https://www.securityweek.com/feed/', 'group_slug' => 'security-news', 'is_active' => 1),
			array('name' => 'Laravel News', 'url' => 'https://laravel-news.com/feed', 'group_slug' => 'php-ecosystem', 'is_active' => 1),
			array('name' => 'Symfony Blog', 'url' => 'https://symfony.com/blog/', 'group_slug' => 'php-ecosystem', 'is_active' => 1),
			array('name' => 'Martin Fowler', 'url' => 'https://martinfowler.com/feed.atom', 'group_slug' => 'backend-architecture', 'is_active' => 1),
			array('name' => 'The Pragmatic Engineer', 'url' => 'https://blog.pragmaticengineer.com/rss/', 'group_slug' => 'backend-architecture', 'is_active' => 1),
			array('name' => 'InfoQ', 'url' => 'https://feed.infoq.com/', 'group_slug' => 'backend-architecture', 'is_active' => 1),
			array('name' => 'stitcher.io (PHP)', 'url' => 'https://stitcher.io/rss', 'group_slug' => 'backend-architecture', 'is_active' => 1),
			array('name' => 'GitHub Blog', 'url' => 'https://github.blog/feed/', 'group_slug' => 'ai-dev-tooling', 'is_active' => 1),
			array('name' => 'Simon Willison', 'url' => 'https://simonwillison.net/atom/everything/', 'group_slug' => 'ai-dev-tooling', 'is_active' => 1),
			array('name' => 'PhpStorm Blog', 'url' => 'https://blog.jetbrains.com/phpstorm/feed/', 'group_slug' => 'ai-dev-tooling', 'is_active' => 1),
			array('name' => 'Docker Blog', 'url' => 'https://www.docker.com/blog/feed/', 'group_slug' => 'ai-dev-tooling', 'is_active' => 1),
		);
	}

	/**
	 * Campaign seeds (created when missing).
	 *
	 * @return array List of ['name','content_goal'].
	 */
	public static function campaigns() {
		return array(
			array(
				'name'         => 'Developer Foundations',
				'content_goal' => 'Foundational tutorials for core development concepts and tools',
			),
			array(
				'name'         => 'Backend Engineering',
				'content_goal' => 'Intermediate backend development patterns and practices',
			),
			array(
				'name'         => 'Security First',
				'content_goal' => 'Security best practices and vulnerability prevention',
			),
			array(
				'name'         => 'Framework & Tool Comparisons',
				'content_goal' => 'Fair comparisons of frameworks, libraries, and tools',
			),
			array(
				'name'         => 'Developer Tooling',
				'content_goal' => 'Practical guides for developer tools and workflows',
			),
			array(
				'name'         => 'AI for Developers',
				'content_goal' => 'Practical AI guidance for developer workflows',
			),
			array(
				'name'         => 'PHP Ecosystem Radar',
				'content_goal' => 'Track PHP ecosystem updates and translate them into implementation-ready guidance',
			),
			array(
				'name'         => 'Security Intelligence Briefing',
				'content_goal' => 'Deliver actionable security briefings tied to current vulnerabilities and prevention patterns',
			),
			array(
				'name'         => 'Software Architecture Patterns',
				'content_goal' => 'Deep-dive architectural patterns, domain modeling, and modular system design',
			),
			array(
				'name'         => 'Refactoring & Code Smells',
				'content_goal' => 'Step-by-step legacy code refactoring, anti-pattern remediation, and clean code techniques',
			),
			array(
				'name'         => 'Test Strategy & Automation',
				'content_goal' => 'Practical test-driven development, resilient test doubles, and CI/CD quality engineering',
			),
			array(
				'name'         => 'Hosting & Infrastructure',
				'content_goal' => 'Help developers and site owners run reliable, secure web hosting on the LAMP stack across shared and VPS environments.',
			),
		);
	}

	/**
	 * Raw template config keyed by live template name.
	 *
	 * @return array
	 */
	private static function raw_templates() {
		return array(
			'Beginner How-To' => array(
				'description'             => 'Beginner-friendly tutorials for fundamental concepts',
				'campaign'                => 'Developer Foundations',
				'voice'                   => 'Hands-On Tutorial Coach',
				'structure'               => 'Evergreen How-To Guide',
				'categories'              => array('Backend Development', 'PHP Development'),
				'post_tags'               => 'php,backend,developer-fundamentals,tutorial',
				'prompt_template'         => 'Write a comprehensive beginner-friendly tutorial about {{topic}}. Focus on helping developers learn by doing.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 1,
				'slug_type'               => 'tutorial',
				'title_prompt'            => 'Write a beginner-friendly how-to title that starts with "How to" and names the exact technology and outcome. Under 65 characters, no clickbait, no year.',
				'image_subject'           => 'a friendly step-by-step learning path with numbered stages leading to a working result',
				'source_group'            => 'php-ecosystem',
				'content_images'          => array(
					array('placement' => 'after_section_2', 'prompt' => 'Simple step-by-step flow diagram of the setup or process described for {{topic}}, three to five connected stages'),
				),
			),
			'Intermediate Backend' => array(
				'description'             => 'Intermediate backend development patterns and practices',
				'campaign'                => 'Backend Engineering',
				'voice'                   => 'DevStackTips Default',
				'structure'               => 'Advanced Technical Tutorial',
				'categories'              => array('Backend Development', 'Database'),
				'post_tags'               => 'backend,architecture,api,scalability',
				'prompt_template'         => 'Write an intermediate-level backend development tutorial about {{topic}}. Assume familiarity with programming fundamentals.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 1,
				'slug_type'               => 'tutorial',
				'title_prompt'            => 'Write a specific title for an intermediate backend tutorial: name the technique and the concrete benefit (performance, reliability, scale). Under 65 characters, no year.',
				'image_subject'           => 'backend services, an API gateway and a database connected by clean data-flow lines',
				'source_group'            => 'backend-architecture',
				'content_images'          => array(
					array('placement' => 'after_section_2', 'prompt' => 'Request flow diagram for {{topic}}: client, API layer, services and data store as connected boxes with arrows'),
					array('placement' => 'after_section_4', 'prompt' => 'Before and after comparison diagram showing the backend design improvement described for {{topic}}'),
				),
			),
			'Security Guide' => array(
				'description'             => 'Security best practices and vulnerability prevention',
				'campaign'                => 'Security First',
				'voice'                   => 'Senior Backend Mentor',
				'structure'               => 'Security Best Practices',
				'categories'              => array('Security', 'Backend Development'),
				'post_tags'               => 'security,owasp,secure-coding,vulnerabilities',
				'prompt_template'         => 'Write a security-focused guide about {{topic}}. Help developers build secure applications by explaining vulnerabilities and secure patterns.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 1,
				'slug_type'               => 'security',
				'title_prompt'            => 'Write a security guide title that names the vulnerability or control and the stack, e.g. "Preventing SQL Injection in PHP with Prepared Statements". Under 65 characters, no fear-mongering.',
				'image_subject'           => 'a shield protecting a layered application stack, with a blocked attack path',
				'source_group'            => 'security-news',
				'content_images'          => array(),
			),
			'Framework Comparison' => array(
				'description'             => 'Fair comparisons of frameworks, libraries, and tools',
				'campaign'                => 'Framework & Tool Comparisons',
				'voice'                   => 'Neutral Technical Analyst',
				'structure'               => 'Comparison Article',
				'categories'              => array('Framework Guides'),
				'post_tags'               => 'comparison,frameworks,tradeoffs,decision-making',
				'prompt_template'         => 'Write a balanced, objective comparison of {{topic}}. Present both options fairly without bias.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 1,
				'slug_type'               => 'comparison',
				'title_prompt'            => 'Write a neutral comparison title in the form "X vs Y: [deciding factor]" that names both options and the main criterion. Under 65 characters, no winner in the title, no year.',
				'image_subject'           => 'two balanced technology stacks side by side on a scale, evenly weighted',
				'source_group'            => 'php-ecosystem',
				'content_images'          => array(),
			),
			'Developer Tooling' => array(
				'description'             => 'Practical guides for developer tools and workflows',
				'campaign'                => 'Developer Tooling',
				'voice'                   => 'Hands-On Tutorial Coach',
				'structure'               => 'Tool / Workflow Explainer',
				'categories'              => array('DevOps & Tools'),
				'post_tags'               => 'devops,developer-tools,automation,workflow',
				'prompt_template'         => 'Write a practical guide for {{topic}}. Show developers how to use this tool effectively in their daily workflow.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 1,
				'slug_type'               => 'tooling',
				'title_prompt'            => 'Write a practical title that names the tool and the workflow it improves, e.g. "Speed Up PHP Debugging with Xdebug Step Filters". Under 65 characters, no year.',
				'image_subject'           => 'a developer terminal and editor connected to automation gears and a pipeline',
				'source_group'            => 'ai-dev-tooling',
				'content_images'          => array(),
			),
			'AI for Developers' => array(
				'description'             => 'Practical AI guidance for developer workflows',
				'campaign'                => 'AI for Developers',
				'voice'                   => 'AI Engineering Editor',
				'structure'               => 'AI-for-Devs Article',
				'categories'              => array('AI for Developers'),
				'post_tags'               => 'ai,developer-productivity,prompt-engineering,quality-control',
				'prompt_template'         => 'Write practical guidance about {{topic}}. Focus on real developer use cases and address both benefits and limitations honestly.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 1,
				'slug_type'               => 'ai',
				'title_prompt'            => 'Write a grounded title about using AI in a specific developer task; name the task and the technique, avoid hype words like "revolutionary" or "game-changer". Under 65 characters.',
				'image_subject'           => 'a code editor with an assistant panel and a human review checkpoint',
				'source_group'            => 'ai-dev-tooling',
				'content_images'          => array(),
			),
			'Security News' => array(
				'description'             => 'Security-focused content informed by current vulnerability reports',
				'campaign'                => 'Security Intelligence Briefing',
				'voice'                   => 'Senior Backend Mentor',
				'structure'               => 'News / Trend Analysis',
				'categories'              => array('Security'),
				'post_tags'               => 'security-news,cve,incident-response,threat-intelligence',
				'prompt_template'         => 'Write a security guide about {{topic}}. Reference current security trends and vulnerabilities where relevant.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 1,
				'slug_type'               => 'news',
				'title_prompt'            => 'Write a factual security briefing title that names the affected software or CVE and what developers must do (patch, mitigate, audit). Under 70 characters, no sensationalism.',
				'image_subject'           => 'a security alert radar scanning a network of application nodes',
				'source_group'            => 'security-news',
				'content_images'          => array(),
			),
			'PHP Framework Deep Dive' => array(
				'description'             => 'In-depth PHP framework tutorials informed by ecosystem updates',
				'campaign'                => 'PHP Ecosystem Radar',
				'voice'                   => 'DevStackTips Default',
				'structure'               => 'Advanced Technical Tutorial',
				'categories'              => array('PHP Development', 'Framework Guides'),
				'post_tags'               => 'php,laravel,symfony,ecosystem-updates',
				'prompt_template'         => 'Write a detailed tutorial about {{topic}}. Incorporate current best practices from the PHP community.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 1,
				'slug_type'               => 'tutorial',
				'title_prompt'            => 'Write a deep-dive title that names the PHP framework, the feature and the practical outcome, e.g. "Laravel Queues: Reliable Retries with Job Middleware". Under 65 characters, no year.',
				'image_subject'           => 'modular PHP framework components fitting together like building blocks',
				'source_group'            => 'php-ecosystem',
				'content_images'          => array(
					array('placement' => 'after_section_2', 'prompt' => 'Architecture diagram of the framework components involved in {{topic}} and how a request moves through them'),
				),
			),
			'Architecture Pattern Deep Dive' => array(
				'description'             => 'Architectural pattern deep-dives with system diagrams and trade-off analysis',
				'campaign'                => 'Software Architecture Patterns',
				'voice'                   => 'Staff Software Architect',
				'structure'               => 'Architecture Pattern Deep Dive',
				'categories'              => array('Backend Development'),
				'post_tags'               => 'software-architecture,design-patterns,ddd,clean-architecture,system-design',
				'prompt_template'         => 'Write an in-depth architectural guide about {{topic}}. Focus on structural design, trade-offs, modularity, and production-tested patterns.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 1,
				'slug_type'               => 'architecture',
				'title_prompt'            => 'Write an architecture title that names the pattern and the problem it solves, e.g. "Hexagonal Architecture: Isolating PHP Domain Logic". Under 65 characters, no year.',
				'image_subject'           => 'isometric modular system components with clear boundaries and connections',
				'source_group'            => 'backend-architecture',
				'content_images'          => array(
					array('placement' => 'after_section_2', 'prompt' => 'Component diagram of the architecture pattern in {{topic}}: modules, boundaries and dependencies as label-free boxes and arrows'),
					array('placement' => 'after_section_4', 'prompt' => 'Sequence-style diagram of one request flowing through the architecture described in {{topic}}'),
				),
			),
			'Refactoring & Code Smells' => array(
				'description'             => 'Practical code smell elimination and safe step-by-step legacy refactoring',
				'campaign'                => 'Refactoring & Code Smells',
				'voice'                   => 'Code Craftsman & Refactoring Lead',
				'structure'               => 'Refactoring & Code Smells Case Study',
				'categories'              => array('Backend Development'),
				'post_tags'               => 'refactoring,code-smells,clean-code,technical-debt,legacy-code',
				'prompt_template'         => 'Write a practical refactoring guide about {{topic}}. Demonstrate how to transform problematic code into clean, maintainable architecture with safety-net tests.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 1,
				'slug_type'               => 'refactoring',
				'title_prompt'            => 'Write a refactoring title that names the code smell and the refactoring move, e.g. "Breaking Up a God Class with Extract Service". Under 65 characters, no year.',
				'image_subject'           => 'tangled lines reorganizing into clean, orderly modular blocks',
				'source_group'            => 'backend-architecture',
				'content_images'          => array(
					array('placement' => 'after_section_2', 'prompt' => 'Before and after diagram of the code structure in {{topic}}: tangled dependencies on the left, clean separated modules on the right'),
				),
			),
			'Test Strategy & Automation Guide' => array(
				'description'             => 'Test-driven development, automated testing patterns, and quality engineering',
				'campaign'                => 'Test Strategy & Automation',
				'voice'                   => 'Test Automation & QA Lead',
				'structure'               => 'Test Strategy & Automation Guide',
				'categories'              => array('DevOps & Tools', 'Backend Development'),
				'post_tags'               => 'tdd,automated-testing,unit-testing,integration-testing,qa,code-quality',
				'prompt_template'         => 'Write a rigorous, practical automated testing guide about {{topic}}. Walk through testing cycles, fixtures, mocking strategies, and robust test assertions.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 1,
				'slug_type'               => 'testing',
				'title_prompt'            => 'Write a testing title that names the technique and the confidence it gives, e.g. "Contract Tests for PHP APIs Without Flaky Mocks". Under 65 characters, no year.',
				'image_subject'           => 'a test pyramid and a CI pipeline with passing checks',
				'source_group'            => 'backend-architecture',
				'content_images'          => array(
					array('placement' => 'after_section_2', 'prompt' => 'Test strategy diagram for {{topic}}: test pyramid layers and where they run in a CI pipeline'),
				),
			),
			'Hosting & Server Management' => array(
				'description'             => 'Practical LAMP, shared hosting, and VPS server management guides and comparisons',
				'campaign'                => 'Hosting & Infrastructure',
				'voice'                   => 'Senior Backend Mentor',
				'structure'               => 'Evergreen How-To Guide',
				'categories'              => array('DevOps & Tools'),
				'post_tags'               => 'hosting,web-hosting,vps,lamp,server-management',
				'prompt_template'         => 'Write a practical, authoritative article about {{topic}} for developers and site owners who manage their own web hosting. Focus on the LAMP stack (Linux, Apache, MySQL, PHP) across both shared hosting and VPS environments. Depending on the topic, either give clear step-by-step how-to guidance for common hosting and server tasks and problems, or compare hosting services, plans, or approaches objectively on performance, reliability, control, and cost. Use concrete commands, configuration snippets, and real-world examples; keep a security-first mindset; and avoid marketing hype and unsupported claims.',
				'post_status'             => 'draft',
				'post_quantity'           => 1,
				'generate_featured_image' => 1,
				'featured_image_source'   => 'ai_prompt',
				'include_sources'         => 0,
				'slug_type'               => 'hosting',
				'title_prompt'            => 'Write a practical web hosting or server management title that names the LAMP, Apache, shared hosting, or VPS task or comparison clearly. Under 65 characters, no hype, no year.',
				'image_subject'           => 'a server rack connected to LAMP stack layers — Linux, Apache, MySQL and PHP — and a globe representing web hosting and VPS infrastructure',
				'source_group'            => null,
				'content_images'          => array(),
			),
		);
	}

	/**
	 * Raw author config keyed by live author name.
	 *
	 * @return array
	 */
	private static function raw_authors() {
		return array(
			'Backend Architecture Specialist' => array(
				'v2_author'                          => true,
				'slug_type'                          => 'architecture',
				'category'                           => 'Backend Development',
				'image_subject'                      => 'backend services and APIs connected in a clean system diagram',
				'field_niche'                        => 'Backend Development, System Architecture, API Design',
				'keywords'                           => 'API design, microservices, system architecture, scalability, caching, database optimization',
				'description'                        => 'Senior backend engineer focused on API design, system architecture, and scalability patterns',
				'details'                            => 'Specializes in building scalable backend systems. Covers topics like RESTful APIs, GraphQL, microservices architecture, database optimization, and caching strategies. Target audience is intermediate to advanced backend developers.',
				'structure'                          => 'Advanced Technical Tutorial',
				'voice_tone'                         => 'Professional, authoritative, mentoring',
				'writing_style'                      => 'Technical deep-dive with practical examples',
				'target_audience'                    => 'Intermediate to advanced backend developers',
				'expertise_level'                    => 'advanced',
				'content_goals'                      => 'Educate developers on scalable architecture patterns, Build practical skills, Share production-tested solutions',
				'excluded_topics'                    => 'Basic programming concepts, Frontend frameworks, Mobile development',
				'preferred_content_length'           => 'long',
				'topic_generation_frequency'         => 'weekly',
				'topic_generation_quantity'          => 8,
				'post_generation_frequency'          => 'daily',
				'max_posts_per_topic'                => 1,
				'manual_post_generation_quantity'    => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active'                          => 1,
			),
			'Security Expert' => array(
				'v2_author'                          => true,
				'slug_type'                          => 'security',
				'category'                           => 'Security',
				'source_group'                       => 'security-news',
				'image_subject'                      => 'a shield protecting application layers from an attack path',
				'field_niche'                        => 'Application Security, Secure Coding, Vulnerability Prevention',
				'keywords'                           => 'SQL injection, XSS, CSRF, authentication, encryption, secure coding, OWASP',
				'description'                        => 'Application security specialist covering vulnerabilities, secure coding, and best practices',
				'details'                            => 'Focuses on practical application security for developers. Covers common vulnerabilities (OWASP Top 10), secure coding practices, authentication/authorization, encryption, and security testing. Emphasizes prevention over remediation.',
				'structure'                          => 'Security Best Practices',
				'voice_tone'                         => 'Professional, serious, educational',
				'writing_style'                      => 'Security-focused tutorial with code examples',
				'target_audience'                    => 'All developers who write server-side code',
				'expertise_level'                    => 'intermediate',
				'content_goals'                      => 'Prevent security vulnerabilities, Educate on secure coding, Build security awareness',
				'excluded_topics'                    => 'Network security hardware, Penetration testing tools, Compliance regulations',
				'preferred_content_length'           => 'medium',
				'topic_generation_frequency'         => 'weekly',
				'topic_generation_quantity'          => 8,
				'post_generation_frequency'          => 'daily',
				'max_posts_per_topic'                => 1,
				'manual_post_generation_quantity'    => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active'                          => 1,
			),
			'DevOps Practitioner' => array(
				'v2_author'                          => true,
				'slug_type'                          => 'tooling',
				'category'                           => 'DevOps & Tools',
				'image_subject'                      => 'a CI/CD pipeline with containers and automation gears',
				'field_niche'                        => 'Developer Tools, DevOps, Automation',
				'keywords'                           => 'Git, Docker, CI/CD, automation, deployment, developer productivity, workflows',
				'description'                        => 'Developer tools and workflow specialist focused on Git, Docker, CI/CD, and automation',
				'details'                            => 'Covers practical developer tools and DevOps workflows. Topics include Git workflows, Docker containerization, CI/CD pipelines, build automation, and developer productivity tools. Focuses on hands-on tutorials.',
				'structure'                          => 'Tool / Workflow Explainer',
				'voice_tone'                         => 'Practical, encouraging, step-by-step',
				'writing_style'                      => 'Tutorial with clear instructions and examples',
				'target_audience'                    => 'Developers looking to improve their workflows and tooling',
				'expertise_level'                    => 'beginner_intermediate',
				'content_goals'                      => 'Teach practical tool usage, Improve developer productivity, Share workflow best practices',
				'excluded_topics'                    => 'Cloud infrastructure management, Kubernetes orchestration, Enterprise IT',
				'preferred_content_length'           => 'medium',
				'topic_generation_frequency'         => 'weekly',
				'topic_generation_quantity'          => 8,
				'post_generation_frequency'          => 'daily',
				'max_posts_per_topic'                => 1,
				'manual_post_generation_quantity'    => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active'                          => 1,
			),
			'Framework Analyst' => array(
				'v2_author'                          => true,
				'slug_type'                          => 'comparison',
				'category'                           => 'Framework Guides',
				'image_subject'                      => 'framework building blocks compared side by side',
				'field_niche'                        => 'Framework Comparisons, Technology Evaluation',
				'keywords'                           => 'framework comparison, Laravel vs Symfony, React vs Vue, technology choices, migration',
				'description'                        => 'Objective framework and tool comparison specialist',
				'details'                            => 'Provides balanced, unbiased comparisons of frameworks and tools. Covers PHP frameworks, JavaScript frameworks, databases, and other technology choices. Helps developers make informed decisions.',
				'structure'                          => 'Comparison Article',
				'voice_tone'                         => 'Neutral, objective, analytical',
				'writing_style'                      => 'Comparison with pros/cons and use-case analysis',
				'target_audience'                    => 'Developers evaluating technology choices',
				'expertise_level'                    => 'intermediate',
				'content_goals'                      => 'Help developers make informed choices, Present balanced comparisons, Avoid bias',
				'excluded_topics'                    => 'Opinionated framework advocacy, Deprecated technologies, Proprietary tools',
				'preferred_content_length'           => 'medium',
				'topic_generation_frequency'         => 'weekly',
				'topic_generation_quantity'          => 8,
				'post_generation_frequency'          => 'daily',
				'max_posts_per_topic'                => 1,
				'manual_post_generation_quantity'    => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active'                          => 1,
			),
			'AI Engineering Pragmatist' => array(
				'v2_author'                          => true,
				'slug_type'                          => 'ai',
				'category'                           => 'AI for Developers',
				'image_subject'                      => 'a code editor with an AI assistant and a human review step',
				'field_niche'                        => 'AI for Developers, Practical AI Integration',
				'keywords'                           => 'AI code review, prompt engineering, GitHub Copilot, ChatGPT, LLM integration, AI evaluation',
				'description'                        => 'Practical AI integration specialist for developer workflows',
				'details'                            => 'Focuses on practical AI tools for developers. Covers AI-assisted coding, prompt engineering, code review with AI, LLM integration in applications, and honest evaluation of AI capabilities and limitations.',
				'structure'                          => 'AI-for-Devs Article',
				'voice_tone'                         => 'Pragmatic, honest, forward-thinking',
				'writing_style'                      => 'Practical guide with real-world examples',
				'target_audience'                    => 'Developers exploring AI integration',
				'expertise_level'                    => 'intermediate',
				'content_goals'                      => 'Demystify AI for developers, Share practical integration patterns, Balance hype with reality',
				'excluded_topics'                    => 'Deep learning theory, AI research papers, ML model training',
				'preferred_content_length'           => 'medium',
				'topic_generation_frequency'         => 'weekly',
				'topic_generation_quantity'          => 8,
				'post_generation_frequency'          => 'daily',
				'max_posts_per_topic'                => 1,
				'manual_post_generation_quantity'    => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active'                          => 1,
			),
			'Staff Software Architect' => array(
				'v2_author'                          => true,
				'slug_type'                          => 'architecture',
				'category'                           => 'Backend Development',
				'image_subject'                      => 'isometric modular architecture with clear domain boundaries',
				'field_niche'                        => 'System Architecture, Domain-Driven Design, Design Patterns, Modularity',
				'keywords'                           => 'clean architecture, domain-driven design, design patterns, microservices vs monolith, event-driven architecture, modularity, system design',
				'description'                        => 'Staff software architect specializing in scalable system design, domain modeling, and enterprise design patterns',
				'details'                            => 'Focuses on large-scale software architecture, domain-driven design (DDD), hexagonal/clean architecture, event-driven patterns, and strategic technical decision making. Guides developers on balancing immediate pragmatism with long-term architectural health.',
				'structure'                          => 'Architecture Pattern Deep Dive',
				'voice_tone'                         => 'Authoritative, strategic, pragmatic, architectural',
				'writing_style'                      => 'Architectural deep-dive with system trade-offs and structural analysis',
				'target_audience'                    => 'Senior engineers, tech leads, and aspiring software architects',
				'expertise_level'                    => 'advanced',
				'content_goals'                      => 'Elevate system design skills, Demystify architectural patterns, Master domain modeling and modularity',
				'excluded_topics'                    => 'Basic syntax tutorials, Superficial tool installations, Marketing buzzwords',
				'preferred_content_length'           => 'long',
				'topic_generation_frequency'         => 'weekly',
				'topic_generation_quantity'          => 8,
				'post_generation_frequency'          => 'daily',
				'max_posts_per_topic'                => 1,
				'manual_post_generation_quantity'    => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active'                          => 1,
			),
			'Test Automation & Quality Specialist' => array(
				'v2_author'                          => true,
				'slug_type'                          => 'testing',
				'category'                           => 'DevOps & Tools',
				'image_subject'                      => 'a test pyramid with passing checks in a pipeline',
				'field_niche'                        => 'Test-Driven Development, Automation Pipelines, Quality Engineering',
				'keywords'                           => 'TDD, unit testing, integration testing, end-to-end testing, mocking, mutation testing, test fixtures, CI/CD quality gates',
				'description'                        => 'Quality engineering and test automation specialist advocating test-driven design and bulletproof test suites',
				'details'                            => 'Covers practical testing strategies across the testing pyramid. Specializes in TDD workflows, robust test doubles/mocking, property-based and mutation testing, eliminating flaky tests, and embedding automated quality gates in CI/CD pipelines.',
				'structure'                          => 'Test Strategy & Automation Guide',
				'voice_tone'                         => 'Rigorous, methodical, test-first, encouraging',
				'writing_style'                      => 'Step-by-step testing walkthrough with red-green-refactor cycles and concrete assertions',
				'target_audience'                    => 'Software engineers looking to master testing and build resilient automated test suites',
				'expertise_level'                    => 'intermediate',
				'content_goals'                      => 'Promote test-driven development, Eliminate software defects, Teach robust test automation patterns',
				'excluded_topics'                    => 'Manual clicking QA checklists, Theoretical quality models without code, Deprecated testing frameworks',
				'preferred_content_length'           => 'medium',
				'topic_generation_frequency'         => 'weekly',
				'topic_generation_quantity'          => 8,
				'post_generation_frequency'          => 'daily',
				'max_posts_per_topic'                => 1,
				'manual_post_generation_quantity'    => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active'                          => 1,
			),
			'Code Quality & Refactoring Craftsman' => array(
				'v2_author'                          => true,
				'slug_type'                          => 'refactoring',
				'category'                           => 'Backend Development',
				'image_subject'                      => 'messy code blocks being reorganized into clean modules',
				'field_niche'                        => 'Legacy Code Refactoring, Code Smells, Static Analysis, Technical Debt',
				'keywords'                           => 'refactoring, code smells, legacy code, static analysis, technical debt, clean code, code review, SOLID principles',
				'description'                        => 'Code craftsman dedicated to transforming tangled legacy systems into clean, maintainable, and well-tested codebases',
				'details'                            => 'Specializes in safe, incremental refactoring techniques for mission-critical codebases. Covers code smell detection, characterization testing, strangler fig transformations, automated static analysis tooling, and maintaining clean code standards in growing teams.',
				'structure'                          => 'Refactoring & Code Smells Case Study',
				'voice_tone'                         => 'Pragmatic, disciplined, detail-oriented, empathetic to legacy constraints',
				'writing_style'                      => 'Case-study style refactoring tutorial with clear before/after transformations',
				'target_audience'                    => 'Intermediate and senior software developers managing evolving or legacy codebases',
				'expertise_level'                    => 'intermediate',
				'content_goals'                      => 'Demystify safe refactoring, Reduce technical debt, Share actionable code quality techniques',
				'excluded_topics'                    => 'Total rewrites without business rationale, Pure dogmatic clean code debates, Unactionable theory',
				'preferred_content_length'           => 'medium',
				'topic_generation_frequency'         => 'weekly',
				'topic_generation_quantity'          => 8,
				'post_generation_frequency'          => 'daily',
				'max_posts_per_topic'                => 1,
				'manual_post_generation_quantity'    => 1,
				'scheduled_post_generation_quantity' => 1,
				'is_active'                          => 1,
			),
			'Marcus Vance' => array(
				'v2_author'     => false,
				'slug_type'     => 'architecture',
				'category'      => 'Backend Development',
				'image_subject' => 'cloud-native distributed services across regions with message queues',
			),
			'Elena Rostova' => array(
				'v2_author'     => false,
				'slug_type'     => 'default',
				'category'      => 'Web Development',
				'image_subject' => 'a modern frontend component tree with performance gauges',
			),
			'Devon Miller (multi post)' => array(
				'v2_author'     => false,
				'slug_type'     => 'security',
				'category'      => 'Security',
				'image_subject' => 'a secured CI/CD pipeline with signed artifacts and a shield',
			),
		);
	}

	/**
	 * Template definitions keyed by stable key, with cross-references resolved
	 * to stable keys.
	 *
	 * @return array<string,array>
	 */
	public static function templates() {
		$out = array();

		foreach (self::raw_templates() as $name => $cfg) {
			$key = AIPS_Config_Binding::make_key('templates', $name);
			if (null === $key) {
				continue;
			}

			$category_keys = array();
			foreach ((array) $cfg['categories'] as $category_name) {
				$cat_key = AIPS_Config_Binding::make_key('categories', $category_name);
				if (null !== $cat_key) {
					$category_keys[] = $cat_key;
				}
			}

			$out[$key] = array(
				'name'                    => $name,
				'description'             => $cfg['description'],
				'campaign_key'            => AIPS_Config_Binding::make_key('campaigns', $cfg['campaign']),
				'voice_key'               => AIPS_Config_Binding::make_key('voices', $cfg['voice']),
				'structure_key'           => AIPS_Config_Binding::make_key('structures', $cfg['structure']),
				'category_keys'           => $category_keys,
				'post_tags'               => $cfg['post_tags'],
				'prompt_template'         => $cfg['prompt_template'],
				'post_status'             => $cfg['post_status'],
				'post_quantity'           => (int) $cfg['post_quantity'],
				'generate_featured_image' => (int) $cfg['generate_featured_image'],
				'featured_image_source'   => $cfg['featured_image_source'],
				'include_sources'         => (int) $cfg['include_sources'],
				'slug_type'               => $cfg['slug_type'],
				'title_prompt'            => $cfg['title_prompt'],
				'image_subject'           => $cfg['image_subject'],
				'source_group_key'        => empty($cfg['source_group']) ? null : AIPS_Config_Binding::make_key('source_groups', $cfg['source_group']),
				'content_images'          => $cfg['content_images'],
			);
		}

		return $out;
	}

	/**
	 * Author definitions keyed by stable key, with cross-references resolved
	 * to stable keys.
	 *
	 * @return array<string,array>
	 */
	public static function authors() {
		$out = array();

		foreach (self::raw_authors() as $name => $cfg) {
			$key = AIPS_Config_Binding::make_key('authors', $name);
			if (null === $key) {
				continue;
			}

			$entry = array(
				'name'             => $name,
				'v2_author'        => !empty($cfg['v2_author']),
				'slug_type'        => $cfg['slug_type'],
				'category_key'     => empty($cfg['category']) ? null : AIPS_Config_Binding::make_key('categories', $cfg['category']),
				'image_subject'    => $cfg['image_subject'],
				'source_group_key' => empty($cfg['source_group']) ? null : AIPS_Config_Binding::make_key('source_groups', $cfg['source_group']),
			);

			if (!empty($cfg['v2_author'])) {
				$entry['field_niche']                        = $cfg['field_niche'];
				$entry['keywords']                           = $cfg['keywords'];
				$entry['description']                        = $cfg['description'];
				$entry['details']                            = $cfg['details'];
				$entry['structure_key']                      = AIPS_Config_Binding::make_key('structures', $cfg['structure']);
				$entry['voice_tone']                         = $cfg['voice_tone'];
				$entry['writing_style']                      = $cfg['writing_style'];
				$entry['target_audience']                    = $cfg['target_audience'];
				$entry['expertise_level']                    = $cfg['expertise_level'];
				$entry['content_goals']                      = $cfg['content_goals'];
				$entry['excluded_topics']                    = $cfg['excluded_topics'];
				$entry['preferred_content_length']           = $cfg['preferred_content_length'];
				$entry['topic_generation_frequency']         = $cfg['topic_generation_frequency'];
				$entry['topic_generation_quantity']          = (int) $cfg['topic_generation_quantity'];
				$entry['post_generation_frequency']          = $cfg['post_generation_frequency'];
				$entry['max_posts_per_topic']                = (int) $cfg['max_posts_per_topic'];
				$entry['manual_post_generation_quantity']    = (int) $cfg['manual_post_generation_quantity'];
				$entry['scheduled_post_generation_quantity'] = (int) $cfg['scheduled_post_generation_quantity'];
				$entry['is_active']                          = (int) $cfg['is_active'];
			}

			$out[$key] = $entry;
		}

		return $out;
	}

	/**
	 * Schedule definitions keyed by stable key.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function schedules() {
		$raw = array(
			array(
				'template_name' => 'Beginner How-To',
				'title'         => 'Core Monday - Developer Foundations',
				'frequency'     => 'every_monday',
				'weekday'       => 1,
				'start_time'    => '09:00',
				'is_active'     => 1,
			),
			array(
				'template_name' => 'Intermediate Backend',
				'title'         => 'Core Tuesday - Backend Engineering',
				'frequency'     => 'every_tuesday',
				'weekday'       => 2,
				'start_time'    => '09:00',
				'is_active'     => 1,
			),
			array(
				'template_name' => 'Security Guide',
				'title'         => 'Core Wednesday - Security First',
				'frequency'     => 'every_wednesday',
				'weekday'       => 3,
				'start_time'    => '09:00',
				'is_active'     => 1,
			),
			array(
				'template_name' => 'Framework Comparison',
				'title'         => 'Core Thursday - Framework Comparisons',
				'frequency'     => 'every_thursday',
				'weekday'       => 4,
				'start_time'    => '09:00',
				'is_active'     => 1,
			),
			array(
				'template_name' => 'Developer Tooling',
				'title'         => 'Core Friday - Developer Tooling',
				'frequency'     => 'every_friday',
				'weekday'       => 5,
				'start_time'    => '09:00',
				'is_active'     => 1,
			),
			array(
				'template_name' => 'AI for Developers',
				'title'         => 'Flex Tuesday PM - AI Workflow Insights',
				'frequency'     => 'every_tuesday',
				'weekday'       => 2,
				'start_time'    => '14:00',
				'is_active'     => 1,
			),
			array(
				'template_name' => 'Security News',
				'title'         => 'Flex Thursday PM - Security Intelligence Briefing',
				'frequency'     => 'every_thursday',
				'weekday'       => 4,
				'start_time'    => '14:00',
				'is_active'     => 1,
			),
			array(
				'template_name' => 'PHP Framework Deep Dive',
				'title'         => 'Flex Saturday - PHP Ecosystem Radar',
				'frequency'     => 'every_saturday',
				'weekday'       => 6,
				'start_time'    => '10:00',
				'is_active'     => 1,
			),
			array(
				'template_name' => 'Architecture Pattern Deep Dive',
				'title'         => 'Core Monday PM - Software Architecture Patterns',
				'frequency'     => 'every_monday',
				'weekday'       => 1,
				'start_time'    => '14:00',
				'is_active'     => 1,
			),
			array(
				'template_name' => 'Test Strategy & Automation Guide',
				'title'         => 'Core Wednesday PM - Test Strategy & Automation',
				'frequency'     => 'every_wednesday',
				'weekday'       => 3,
				'start_time'    => '14:00',
				'is_active'     => 1,
			),
			array(
				'template_name' => 'Refactoring & Code Smells',
				'title'         => 'Core Friday PM - Refactoring & Code Smells',
				'frequency'     => 'every_friday',
				'weekday'       => 5,
				'start_time'    => '14:00',
				'is_active'     => 1,
			),
			array(
				'template_name' => 'Hosting & Server Management',
				'title'         => 'Hosting & Server Management - Daily 2PM',
				'frequency'     => 'daily',
				'weekday'       => null,
				'start_time'    => '14:00',
				'is_active'     => 1,
			),
		);

		$templates = self::templates();
		$out       = array();

		foreach ($raw as $row) {
			$key = AIPS_Config_Binding::make_key('schedules', $row['title']);
			if (null === $key) {
				continue;
			}

			$template_key  = AIPS_Config_Binding::make_key('templates', $row['template_name']);
			$structure_key = ($template_key && isset($templates[$template_key])) ? $templates[$template_key]['structure_key'] : null;

			$out[$key] = array(
				'title'         => $row['title'],
				'template_key'  => $template_key,
				'structure_key' => $structure_key,
				'frequency'     => $row['frequency'],
				'weekday'       => $row['weekday'],
				'start_time'    => $row['start_time'],
				'is_active'     => (int) $row['is_active'],
			);
		}

		return $out;
	}
}
