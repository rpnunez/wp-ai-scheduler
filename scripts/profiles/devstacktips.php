<?php
/**
 * DevStackTips Production Seeding Profile
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

return array(
	'strategy_profile' => 'devstacktips-production-v1',

	'distribution_config' => array(
		'distribution_period' => 'weekly',
		'target_posts' => array(
			'daily' => 6,
			'weekly' => 30,
			'monthly' => 120,
		),
		'campaign_shares' => array(
			'Developer Foundations' => 4,
			'Backend Engineering' => 4,
			'Security First' => 4,
			'Framework & Tool Comparisons' => 4,
			'Developer Tooling' => 4,
			'AI for Developers' => 3,
			'PHP Ecosystem Radar' => 4,
			'Security Intelligence Briefing' => 3,
		),
		'author_shares' => array(
			'Backend Architecture Specialist' => 8,
			'Security Expert' => 7,
			'DevOps Practitioner' => 6,
			'Framework Analyst' => 5,
			'AI Engineering Pragmatist' => 4,
		),
	),

	'settings' => array(
		'content_strategy' => array(
			'title' => 'Content Strategy',
			'options' => array(
				array(
					'option_name' => 'aips_site_niche',
					'value' => 'Full-Stack Software Development, Web Architecture',
				),
				array(
					'option_name' => 'aips_site_target_audience',
					'value' => 'Professional Software Engineers, Web Developers (Mid to Senior Level)',
				),
				array(
					'option_name' => 'aips_site_content_goals',
					'value' => 'Provide authoritative technical deep-dives, establish industry credibility, drive affiliate revenue via trusted/actionable recommendations, and rank for high-value "how-to" and "best practice" keywords.',
				),
				array(
					'option_name' => 'aips_site_brand_voice',
					'value' => 'Authoritative yet approachable, Professional, expert-level',
				),
				array(
					'option_name' => 'aips_site_content_language',
					'value' => 'en',
				),
				array(
					'option_name' => 'aips_site_content_guidelines',
					'value' => 'Minimum 5-10 paragraphs depending on topic. Use H2/H3 hierarchy. Mandatory: brief hyperlinked a "Sources" section. Break code into small, explained chunks. Security focus. Always emphasize "real-world" patterns.',
				),
				array(
					'option_name' => 'aips_site_excluded_topics',
					'value' => 'Politics, religion, celebrity gossip, gambling, adult content, clickbait/listicles with no technical depth ("get rich quick" schemes, unverified rumors/hacks about hardware, non-technical software (e.g., Word/Excel), and general consumer electronics reviews.',
				),
			),
		),
		'resilience_settings' => array(
			'title' => 'Resilience & Limits (Production Settings)',
			'options' => array(
				array(
					'option_name' => 'aips_enable_retry',
					'value' => 1,
				),
				array(
					'option_name' => 'aips_retry_max_attempts',
					'value' => 3,
				),
				array(
					'option_name' => 'aips_retry_initial_delay',
					'value' => 2,
				),
				array(
					'option_name' => 'aips_enable_rate_limiting',
					'value' => 1,
				),
				array(
					'option_name' => 'aips_rate_limit_requests',
					'value' => 20,
				),
				array(
					'option_name' => 'aips_rate_limit_period',
					'value' => 60,
				),
				array(
					'option_name' => 'aips_enable_circuit_breaker',
					'value' => 1,
				),
				array(
					'option_name' => 'aips_circuit_breaker_threshold',
					'value' => 5,
				),
				array(
					'option_name' => 'aips_circuit_breaker_timeout',
					'value' => 300,
				),
			),
		),
		'default_article_structure' => array(
			'title' => 'Default Article Structure',
			'options' => array(
				array(
					'option_name' => 'aips_default_article_structure_id',
					'structure_name' => 'Evergreen How-To Guide',
					'label' => 'default article structure: Evergreen How-To Guide',
				),
			),
		),
		'notification_preferences' => array(
			'title' => 'Notification Preferences',
			'options' => array(
				array(
					'option_name' => 'aips_review_notifications_email',
					'value' => '',
					'warning_message' => 'Please add notification email in Settings > Notifications',
				),
				array(
					'option_name' => 'aips_notification_preferences',
					'value' => array(
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
					),
				),
			),
		),
		'research_and_discovery' => array(
			'title' => 'Research & Discovery',
			'options' => array(
				array(
					'option_name' => 'aips_research_niches',
					'value' => array(
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
					'value' => 0.75,
				),
			),
		),
		'featured_images' => array(
			'title' => 'Featured Images',
			'options' => array(
				array(
					'option_name' => 'aips_unsplash_access_key',
					'value' => '',
					'warning_message' => 'Please add Unsplash Access Key in Settings if using Unsplash images',
				),
			),
		),
		'performance_monitoring' => array(
			'title' => 'Performance Monitoring',
			'options' => array(
				array(
					'option_name' => 'aips_enable_telemetry',
					'value' => true,
				),
			),
		),
		'cache_configuration' => array(
			'title' => 'Cache Configuration',
			'options' => array(
				array(
					'option_name' => 'aips_enable_cache_system',
					'value' => false,
				),
			),
		),
		'log_management' => array(
			'title' => 'Log Management',
			'options' => array(
				array(
					'option_name' => 'aips_log_retention_days',
					'value' => 60,
				),
			),
		),
		'default_post_settings' => array(
			'title' => 'Default Post Settings',
			'options' => array(
				array(
					'option_name' => 'aips_default_post_status',
					'value' => 'draft',
				),
				array(
					'option_name' => 'aips_default_category',
					'category_name' => 'Backend Development',
					'label' => 'default category: Backend Development',
				),
				array(
					'option_name' => 'aips_default_post_author',
					'value' => 1,
				),
			),
		),
	),

	'categories' => array(
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
	),

	'voices' => array(
		array(
			'name' => 'DevStackTips Default',
			'title_prompt' => 'Create a clear, technical, and specific title for this topic. Avoid generic phrases like "ultimate guide" or "everything you need to know". Make it actionable and developer-focused.',
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
			'is_active' => 1,
		),
		array(
			'name' => 'Senior Backend Mentor',
			'title_prompt' => 'Create a title that emphasizes the architectural or design aspect. Use terms like "patterns", "strategies", "best practices", or "architecture".',
			'content_instructions' => "You are a senior backend engineer mentoring developers through DevStackTips.

Writing approach:
- Explain not just what to do, but WHY
- Highlight tradeoffs, limitations, and common mistakes
- Emphasize maintainability, reliability, and security
- Share practical wisdom from real-world experience
- Be opinionated but fair

Content structure:
- Start with the problem or context
- Explain the reasoning behind solutions
- Include \"when to use\" and \"when NOT to use\" guidance
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
- Theoretical-only advice without practical application",
			'excerpt_instructions' => 'Highlight the key architectural insight or tradeoff discussed.',
			'is_active' => 1,
		),
		array(
			'name' => 'Hands-On Tutorial Coach',
			'title_prompt' => 'Create a "How to..." title that clearly states what the reader will accomplish. Be specific about the outcome.',
			'content_instructions' => "You are a patient, practical tutorial instructor helping developers learn by doing.

Teaching style:
- Break complex tasks into clear sequential steps
- Assume readers want to apply this immediately
- Be encouraging without being condescending
- Explain each step's purpose
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
- Focus on getting it working first, optimization second",
			'excerpt_instructions' => 'Describe what the reader will be able to do after following this tutorial.',
			'is_active' => 1,
		),
		array(
			'name' => 'Neutral Technical Analyst',
			'title_prompt' => 'Create a balanced comparison title using "vs" or "Comparing". Avoid suggesting one option is superior.',
			'content_instructions' => "You are an objective technical analyst comparing tools, frameworks, and approaches for developers.

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
- Personal preference disguised as objective analysis",
			'excerpt_instructions' => 'Summarize the key differences and when to choose each option.',
			'is_active' => 1,
		),
		array(
			'name' => 'AI Engineering Editor',
			'title_prompt' => 'Create a title that addresses AI tools or workflows pragmatically. Include "for Developers" or "in Development" to keep focus practical.',
			'content_instructions' => "You are writing about AI tools and practices for developers, with both enthusiasm and appropriate caution.

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
- Responsible AI practices",
			'excerpt_instructions' => 'Emphasize both the benefits and limitations of the AI approach discussed.',
			'is_active' => 1,
		),
	),

	'sections' => array(
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
	),

	'structures' => array(
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
	),

	'authors' => array(
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
			'post_generation_frequency' => 'weekly',
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
			'post_generation_frequency' => 'weekly',
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
			'post_generation_frequency' => 'weekly',
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
	),

	'post_slices' => array(
		array(
			'name' => 'Developer Resources Footer',
			'description' => 'Add an additional resources list and next-steps guidance for readers who want to keep learning and apply the topic in a small project.',
			'sort_order' => 10,
			'is_active' => 1,
		),
		array(
			'name' => 'Security Disclaimer',
			'description' => 'Insert a short security reminder that emphasizes validating and sanitizing input, distrust of external data, and least-privilege practices.',
			'sort_order' => 20,
			'is_active' => 1,
		),
		array(
			'name' => 'Code Example Standards',
			'description' => 'Prepend a note that code samples should be reviewed and adapted for error handling, edge cases, and project-specific constraints.',
			'sort_order' => 30,
			'is_active' => 1,
		),
		array(
			'name' => 'Version Note',
			'description' => 'Append a version disclaimer reminding readers to check current upstream documentation because APIs and tooling evolve over time.',
			'sort_order' => 40,
			'is_active' => 1,
		),
	),

	'source_groups' => array(
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
	),

	'sources' => array(
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
	),

	'campaigns' => array(
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
		array(
			'name' => 'PHP Ecosystem Radar',
			'content_goal' => 'Track PHP ecosystem updates and translate them into implementation-ready guidance',
			'topics' => "What Changed in PHP 8.4 for Backend Teams\nLaravel Release Notes: What Matters in Production\nSymfony Upgrade Guide: Breaking Changes to Watch\nComposer Dependency Strategy for Long-Lived Projects\nFramework Migration Checklists for Teams\nPHP Package Vetting for Security and Stability\nInterpreting RFCs for Engineering Planning\nHow to Adopt New PHP Features Safely\nRuntime Upgrades with Minimal Risk\nDeprecation Management in PHP Applications",
		),
		array(
			'name' => 'Security Intelligence Briefing',
			'content_goal' => 'Deliver actionable security briefings tied to current vulnerabilities and prevention patterns',
			'topics' => "CVE Triage Workflow for Engineering Teams\nPatch Prioritization Under Time Constraints\nHow to Communicate Security Risk to Stakeholders\nDependency Vulnerability Response Playbook\nFrom Advisory to Action: Turning Alerts into Fixes\nThreat Modeling for Existing Applications\nSecure Defaults for New Services\nIncident Readiness Checklist for Web Apps\nHow to Validate Security Fixes in CI\nPost-Incident Lessons Learned Template",
		),
	),

	'templates' => array(
		array(
			'name' => 'Beginner How-To',
			'description' => 'Beginner-friendly tutorials for fundamental concepts',
			'campaign_name' => 'Developer Foundations',
			'voice_name' => 'Hands-On Tutorial Coach',
			'structure_name' => 'Evergreen How-To Guide',
			'categories' => array('Backend Development', 'PHP Development'),
			'post_tags' => 'php,backend,developer-fundamentals,tutorial',
			'prompt_template' => 'Write a comprehensive beginner-friendly tutorial about {{topic}}. Focus on helping developers learn by doing.',
			'generate_featured_image' => 0,
			'featured_image_source' => 'ai_prompt',
			'include_sources' => 0,
		),
		array(
			'name' => 'Intermediate Backend',
			'description' => 'Intermediate backend development patterns and practices',
			'campaign_name' => 'Backend Engineering',
			'voice_name' => 'DevStackTips Default',
			'structure_name' => 'Advanced Technical Tutorial',
			'categories' => array('Backend Development', 'Database'),
			'post_tags' => 'backend,architecture,api,scalability',
			'prompt_template' => 'Write an intermediate-level backend development tutorial about {{topic}}. Assume familiarity with programming fundamentals.',
			'generate_featured_image' => 1,
			'featured_image_source' => 'ai_prompt',
			'image_prompt' => 'A clean, modern illustration representing {{topic}} in software development. Abstract geometric shapes, blues and teals, professional developer aesthetic, minimalist design',
			'include_sources' => 0,
		),
		array(
			'name' => 'Security Guide',
			'description' => 'Security best practices and vulnerability prevention',
			'campaign_name' => 'Security First',
			'voice_name' => 'Senior Backend Mentor',
			'structure_name' => 'Security Best Practices',
			'categories' => array('Security', 'Backend Development'),
			'post_tags' => 'security,owasp,secure-coding,vulnerabilities',
			'prompt_template' => 'Write a security-focused guide about {{topic}}. Help developers build secure applications by explaining vulnerabilities and secure patterns.',
			'generate_featured_image' => 1,
			'featured_image_source' => 'ai_prompt',
			'image_prompt' => 'A secure lock symbol overlaid on a modern application window or code editor, cybersecurity theme, dark blues and greens, shield iconography, professional and trustworthy aesthetic',
			'include_sources' => 0,
		),
		array(
			'name' => 'Framework Comparison',
			'description' => 'Fair comparisons of frameworks, libraries, and tools',
			'campaign_name' => 'Framework & Tool Comparisons',
			'voice_name' => 'Neutral Technical Analyst',
			'structure_name' => 'Comparison Article',
			'categories' => array('Framework Guides'),
			'post_tags' => 'comparison,frameworks,tradeoffs,decision-making',
			'prompt_template' => 'Write a balanced, objective comparison of {{topic}}. Present both options fairly without bias.',
			'generate_featured_image' => 0,
			'featured_image_source' => 'ai_prompt',
			'include_sources' => 0,
		),
		array(
			'name' => 'Developer Tooling',
			'description' => 'Practical guides for developer tools and workflows',
			'campaign_name' => 'Developer Tooling',
			'voice_name' => 'Hands-On Tutorial Coach',
			'structure_name' => 'Tool / Workflow Explainer',
			'categories' => array('DevOps & Tools'),
			'post_tags' => 'devops,developer-tools,automation,workflow',
			'prompt_template' => 'Write a practical guide for {{topic}}. Show developers how to use this tool effectively in their daily workflow.',
			'generate_featured_image' => 1,
			'featured_image_source' => 'unsplash',
			'unsplash_keywords' => 'developer tools, programming, code, terminal, workflow',
			'include_sources' => 0,
		),
		array(
			'name' => 'AI for Developers',
			'description' => 'Practical AI guidance for developer workflows',
			'campaign_name' => 'AI for Developers',
			'voice_name' => 'AI Engineering Editor',
			'structure_name' => 'AI-for-Devs Article',
			'categories' => array('AI for Developers'),
			'post_tags' => 'ai,developer-productivity,prompt-engineering,quality-control',
			'prompt_template' => 'Write practical guidance about {{topic}}. Focus on real developer use cases and address both benefits and limitations honestly.',
			'generate_featured_image' => 0,
			'featured_image_source' => 'ai_prompt',
			'include_sources' => 0,
		),
		array(
			'name' => 'Security News',
			'description' => 'Security-focused content informed by current vulnerability reports',
			'campaign_name' => 'Security Intelligence Briefing',
			'voice_name' => 'Senior Backend Mentor',
			'structure_name' => 'Security Best Practices',
			'categories' => array('Security'),
			'post_tags' => 'security-news,cve,incident-response,threat-intelligence',
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
			'campaign_name' => 'PHP Ecosystem Radar',
			'voice_name' => 'DevStackTips Default',
			'structure_name' => 'Advanced Technical Tutorial',
			'categories' => array('PHP Development', 'Framework Guides'),
			'post_tags' => 'php,laravel,symfony,ecosystem-updates',
			'source_group' => 'PHP Ecosystem',
			'include_sources' => 1,
			'prompt_template' => 'Write a detailed tutorial about {{topic}}. Incorporate current best practices from the PHP community.',
			'generate_featured_image' => 0,
			'featured_image_source' => 'ai_prompt',
		),
	),

	'schedules' => array(
		array(
			'template_name' => 'Beginner How-To',
			'title' => 'Core Monday - Developer Foundations',
			'frequency' => 'weekly',
			'weekday' => 1,
			'start_time' => '09:00',
			'is_active' => 1,
		),
		array(
			'template_name' => 'Intermediate Backend',
			'title' => 'Core Tuesday - Backend Engineering',
			'frequency' => 'weekly',
			'weekday' => 2,
			'start_time' => '09:00',
			'is_active' => 1,
		),
		array(
			'template_name' => 'Security Guide',
			'title' => 'Core Wednesday - Security First',
			'frequency' => 'weekly',
			'weekday' => 3,
			'start_time' => '09:00',
			'is_active' => 1,
		),
		array(
			'template_name' => 'Framework Comparison',
			'title' => 'Core Thursday - Framework Comparisons',
			'frequency' => 'weekly',
			'weekday' => 4,
			'start_time' => '09:00',
			'is_active' => 1,
		),
		array(
			'template_name' => 'Developer Tooling',
			'title' => 'Core Friday - Developer Tooling',
			'frequency' => 'weekly',
			'weekday' => 5,
			'start_time' => '09:00',
			'is_active' => 1,
		),
		array(
			'template_name' => 'AI for Developers',
			'title' => 'Flex Tuesday PM - AI Workflow Insights',
			'frequency' => 'weekly',
			'weekday' => 2,
			'start_time' => '14:00',
			'is_active' => 1,
		),
		array(
			'template_name' => 'Security News',
			'title' => 'Flex Thursday PM - Security Intelligence Briefing',
			'frequency' => 'weekly',
			'weekday' => 4,
			'start_time' => '14:00',
			'is_active' => 1,
		),
		array(
			'template_name' => 'PHP Framework Deep Dive',
			'title' => 'Flex Saturday - PHP Ecosystem Radar',
			'frequency' => 'weekly',
			'weekday' => 6,
			'start_time' => '10:00',
			'is_active' => 1,
		),
	),
);
