<?php
/**
 * Local Development & Test Seeding Profile
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

return array(
	'strategy_profile' => 'dev-test-v1',

	'distribution_config' => array(
		'distribution_period' => 'weekly',
		'target_posts' => array(
			'daily' => 2,
			'weekly' => 2,
			'monthly' => 8,
		),
		'campaign_shares' => array(
			'Developer Foundations' => 1,
			'Framework & Tool Comparisons' => 1,
		),
		'author_shares' => array(
			'Backend Architecture Specialist' => 1,
		),
	),

	'settings' => array(
		'content_strategy' => array(
			'title' => 'Content Strategy',
			'options' => array(
				array(
					'option_name' => 'aips_site_niche',
					'value' => 'Local Testing Niche',
				),
				array(
					'option_name' => 'aips_site_target_audience',
					'value' => 'Local Automated Test Suite',
				),
				array(
					'option_name' => 'aips_site_content_goals',
					'value' => 'Verify functionality of the AI Post Scheduler plugin in a sandbox.',
				),
				array(
					'option_name' => 'aips_site_brand_voice',
					'value' => 'Objective and programmatic',
				),
				array(
					'option_name' => 'aips_site_content_language',
					'value' => 'en',
				),
				array(
					'option_name' => 'aips_site_content_guidelines',
					'value' => 'Short 2-3 paragraph posts. Minimum overhead.',
				),
				array(
					'option_name' => 'aips_site_excluded_topics',
					'value' => 'None for testing.',
				),
			),
		),
		'resilience_settings' => array(
			'title' => 'Resilience & Limits (Test Settings)',
			'options' => array(
				array(
					'option_name' => 'aips_enable_retry',
					'value' => 1,
				),
				array(
					'option_name' => 'aips_retry_max_attempts',
					'value' => 2,
				),
				array(
					'option_name' => 'aips_retry_initial_delay',
					'value' => 1,
				),
				array(
					'option_name' => 'aips_enable_rate_limiting',
					'value' => 0,
				),
				array(
					'option_name' => 'aips_enable_circuit_breaker',
					'value' => 0,
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
					'value' => 'test@example.com',
				),
				array(
					'option_name' => 'aips_notification_preferences',
					'value' => array(
						'generation_failed' => 'db',
						'quota_alert' => 'db',
						'post_ready_for_review' => 'db',
						'template_generated' => 'db',
						'manual_generation_completed' => 'db',
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
						'PHP Testing',
						'Software Quality Assurance',
					),
				),
				array(
					'option_name' => 'aips_topic_similarity_threshold',
					'value' => 0.9,
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
					'value' => 7,
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
	),

	'voices' => array(
		array(
			'name' => 'Hands-On Tutorial Coach',
			'title_prompt' => 'Create a "How to..." title that clearly states what the reader will accomplish.',
			'content_instructions' => "You are a patient, practical tutorial instructor helping developers learn by doing.
- Break complex tasks into clear sequential steps.
- Provide copy-paste ready snippets.",
			'excerpt_instructions' => 'Describe what the reader will be able to do after following this tutorial.',
			'is_active' => 1,
		),
		array(
			'name' => 'Neutral Technical Analyst',
			'title_prompt' => 'Create a balanced comparison title using "vs".',
			'content_instructions' => "You are an objective technical analyst comparing tools.
- Present options fairly and without bias.
- Acknowledge tradeoffs.",
			'excerpt_instructions' => 'Summarize the key differences and when to choose each option.',
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
		'step_by_step' => array(
			'name' => 'Step-by-Step Instructions',
			'section_key' => 'step_by_step',
			'description' => 'Sequential implementation steps',
			'content' => 'Provide numbered, sequential steps.',
			'is_active' => 1,
		),
		'code_examples' => array(
			'name' => 'Code Examples',
			'section_key' => 'code_examples',
			'description' => 'Working code samples',
			'content' => 'Include complete, working code examples with explanations.',
			'is_active' => 1,
		),
		'conclusion' => array(
			'name' => 'Conclusion',
			'section_key' => 'conclusion',
			'description' => 'Summary and next steps',
			'content' => 'Summarize key takeaways.',
			'is_active' => 1,
		),
		'comparison_overview' => array(
			'name' => 'Overview',
			'section_key' => 'comparison_overview',
			'description' => 'Comparison introduction',
			'content' => 'Introduce both options being compared.',
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
			'content' => 'Provide balanced recommendations.',
			'is_active' => 1,
		),
	),

	'structures' => array(
		array(
			'name' => 'Evergreen How-To Guide',
			'description' => 'For foundational tutorials and step-by-step guides',
			'sections' => array('introduction', 'what_youll_learn', 'step_by_step', 'code_examples', 'conclusion'),
		),
		array(
			'name' => 'Comparison Article',
			'description' => 'For framework, tool, and approach comparisons',
			'sections' => array('comparison_overview', 'use_cases', 'recommendation'),
		),
	),

	'authors' => array(
		array(
			'name' => 'Backend Architecture Specialist',
			'field_niche' => 'Backend Development, System Architecture',
			'keywords' => 'API design, system architecture, caching',
			'description' => 'Senior backend engineer focused on API design',
			'details' => 'Specializes in building scalable backend systems.',
			'voice_name' => 'Neutral Technical Analyst',
			'structure_name' => 'Comparison Article',
			'voice_tone' => 'Professional, authoritative',
			'writing_style' => 'Technical deep-dive',
			'target_audience' => 'Intermediate developers',
			'expertise_level' => 'intermediate',
			'content_goals' => 'Educate developers',
			'excluded_topics' => 'Basic programming concepts',
			'preferred_content_length' => 'medium',
			'category_name' => 'Backend Development',
			'topic_generation_frequency' => 'weekly',
			'topic_generation_quantity' => 2,
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
			'description' => 'Add an additional resources list and next-steps guidance.',
			'sort_order' => 10,
			'is_active' => 1,
		),
	),

	'source_groups' => array(),
	'sources' => array(),

	'campaigns' => array(
		array(
			'name' => 'Developer Foundations',
			'content_goal' => 'Foundational tutorials for core development concepts',
			'topics' => "How to Use Composer in PHP Projects\nGit Rebase vs Merge Explained",
		),
		array(
			'name' => 'Framework & Tool Comparisons',
			'content_goal' => 'Fair comparisons of frameworks',
			'topics' => "Laravel vs Symfony: Framework Comparison",
		),
	),

	'templates' => array(
		array(
			'name' => 'Beginner How-To',
			'description' => 'Beginner-friendly tutorials for fundamental concepts',
			'campaign_name' => 'Developer Foundations',
			'voice_name' => 'Hands-On Tutorial Coach',
			'structure_name' => 'Evergreen How-To Guide',
			'categories' => array('Backend Development'),
			'post_tags' => 'php,tutorial',
			'prompt_template' => 'Write a beginner-friendly tutorial about {{topic}}.',
			'generate_featured_image' => 0,
			'featured_image_source' => 'ai_prompt',
			'include_sources' => 0,
		),
		array(
			'name' => 'Framework Comparison',
			'description' => 'Fair comparisons of frameworks',
			'campaign_name' => 'Framework & Tool Comparisons',
			'voice_name' => 'Neutral Technical Analyst',
			'structure_name' => 'Comparison Article',
			'categories' => array('Backend Development'),
			'post_tags' => 'comparison,frameworks',
			'prompt_template' => 'Write a balanced comparison of {{topic}}.',
			'generate_featured_image' => 0,
			'featured_image_source' => 'ai_prompt',
			'include_sources' => 0,
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
			'template_name' => 'Framework Comparison',
			'title' => 'Core Thursday - Framework Comparisons',
			'frequency' => 'weekly',
			'weekday' => 4,
			'start_time' => '09:00',
			'is_active' => 1,
		),
	),
);
