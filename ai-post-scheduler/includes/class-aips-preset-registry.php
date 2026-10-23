<?php
/**
 * Presets Registry
 *
 * Provides pre-configured starter blueprints (niches) for AI Post Scheduler,
 * bundling Voices, Structures, Prompt Sections, Authors, Starter Topics,
 * Templates, and Schedules.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Preset_Registry {

	/**
	 * Retrieve all available starter presets.
	 *
	 * @return array<string, array>
	 */
	public static function get_presets(): array {
		return array(
			'news_updates' => array(
				'id'          => 'news_updates',
				'name'        => __('News & Industry Updates', 'ai-post-scheduler'),
				'tagline'     => __('Timely, factual coverage of industry news, announcements, and trends.', 'ai-post-scheduler'),
				'description' => __('Ideal for news portals, tech blogs, and trade publications. Sets up an objective journalist persona, structured news article template, and daily reporting cadence.', 'ai-post-scheduler'),
				'icon'        => 'dashicons-megaphone',
				'strategy'    => array(
					'niche'           => 'Industry News & Market Updates',
					'target_audience' => 'Professionals, practitioners, and industry observers',
					'content_goals'   => 'Provide fast, accurate, and objective reporting on key developments and their practical significance.',
				),
				'voice'       => array(
					'name'                 => 'Preset: Journalistic Wire',
					'title_prompt'         => 'Write a concise, factual news headline without sensationalism or clickbait.',
					'content_instructions' => 'Tone: objective, authoritative, concise, and timely. Lead with the core development, summarize verifiable facts, explain industry context, and conclude with forward-looking analysis.',
					'excerpt_instructions' => 'A two-sentence neutral summary of the primary event and its key implication.',
				),
				'sections'    => array(
					array(
						'section_key' => 'preset_news_lead',
						'name'        => 'Lead & Announcement',
						'description' => 'Opening paragraph delivering the core facts: who, what, when, and immediate significance.',
						'content'     => 'State the primary news event clearly in the opening sentences. Cover who is involved, what was announced, and why it is important right now.',
					),
					array(
						'section_key' => 'preset_news_context',
						'name'        => 'Background & Context',
						'description' => 'What led up to this development and relevant industry history.',
						'content'     => 'Provide essential background context. Explain prior benchmarks, recent trends, and the market backdrop leading up to this event.',
					),
					array(
						'section_key' => 'preset_news_impact',
						'name'        => 'Industry & Consumer Impact',
						'description' => 'Detailed analysis of consequences for consumers and competitors.',
						'content'     => 'Analyze who is most affected by this news. Break down the practical consequences, advantages, disadvantages, and potential ripple effects.',
					),
					array(
						'section_key' => 'preset_news_outlook',
						'name'        => 'Outlook & What to Watch',
						'description' => 'Upcoming dates, pending decisions, and next steps.',
						'content'     => 'Conclude with forward-looking guidance: key dates to watch, expected next steps, and what readers should monitor moving forward.',
					),
				),
				'structure'   => array(
					'name'            => 'Preset: News & Industry Brief',
					'description'     => 'Editorial format designed for news bulletins, announcements, and market reports.',
					'section_keys'    => array('preset_news_lead', 'preset_news_context', 'preset_news_impact', 'preset_news_outlook'),
					'prompt_template' => "Write an objective, journalistic news article on: {{topic}}.\n\n{{section:preset_news_lead}}\n\n{{section:preset_news_context}}\n\n{{section:preset_news_impact}}\n\n{{section:preset_news_outlook}}",
				),
				'author'      => array(
					'name'                       => 'Alex Rivera',
					'field_niche'                => 'Industry News & Market Analysis',
					'description'                => 'Senior correspondent tracking industry shifts, regulations, and technological breakthroughs.',
					'keywords'                   => 'breaking news, market analysis, regulation, industry trends',
					'voice_tone'                 => 'Authoritative, Objective, Concise',
					'writing_style'              => 'Journalistic inverted pyramid, verifiable facts',
					'target_audience'            => 'Industry executives, engineers, and observers',
					'expertise_level'            => 'Expert',
					'topic_generation_frequency' => 'weekly',
					'topic_generation_quantity'  => 5,
					'post_generation_frequency'  => 'daily',
					'starter_topics'             => array(
						'Major Regulatory Updates Reshaping the Industry This Quarter',
						'Top Disruptions and Emerging Competitors to Watch This Year',
						'Key Takeaways and Growth Drivers from the Latest Industry Benchmark',
					),
				),
				'template'    => array(
					'name'            => 'Preset: Industry News Report',
					'prompt_template' => "Write a timely, authoritative news report covering: {{topic}}.\n\nRequirements:\n- Maintain objective journalistic tone without cheerleading\n- Use clear H2/H3 subheadings\n- Cite observable impacts and stakeholder perspectives\n- Keep paragraphs under 3-4 sentences for scannability",
					'title_prompt'    => 'Create a crisp, informative news headline for: {{topic}}',
					'post_status'     => 'draft',
					'post_type'       => 'post',
				),
				'schedule'    => array(
					'title'         => 'Daily News Briefing Schedule',
					'frequency'     => 'daily',
					'schedule_type' => 'post_generation',
					'is_active'     => 0,
				),
			),

			'how_to_guides' => array(
				'id'          => 'how_to_guides',
				'name'        => __('Comprehensive How-To Guides', 'ai-post-scheduler'),
				'tagline'     => __('Actionable, step-by-step problem solvers that teach readers practical skills.', 'ai-post-scheduler'),
				'description' => __('Ideal for documentation sites, software blogs, and tutorial hubs. Preconfigures an instructor voice, tutorial structure, and actionable templates.', 'ai-post-scheduler'),
				'icon'        => 'dashicons-welcome-learn-more',
				'strategy'    => array(
					'niche'           => 'Practical Tutorials & Workflows',
					'target_audience' => 'Beginners to intermediate builders seeking actionable solutions',
					'content_goals'   => 'Empower readers to complete specific workflows with zero frustration through clear, numbered steps.',
				),
				'voice'       => array(
					'name'                 => 'Preset: Practical Mentor',
					'title_prompt'         => 'Create a clear, benefit-driven title starting with "How to" or "Step-by-Step Guide to".',
					'content_instructions' => 'Tone: clear, supportive, instructional, structured. Use second person ("you"), bullet lists, and pro-tips.',
					'excerpt_instructions' => 'A clear summary of what readers will learn and build by following the tutorial.',
				),
				'sections'    => array(
					array(
						'section_key' => 'preset_howto_prereq',
						'name'        => 'Prerequisites & Setup',
						'description' => 'What readers need before starting (tools, permissions, estimated time).',
						'content'     => 'List all prerequisites required to complete this tutorial: software, access keys, baseline knowledge, and time needed.',
					),
					array(
						'section_key' => 'preset_howto_steps',
						'name'        => 'Step-by-Step Walkthrough',
						'description' => 'Sequential, numbered actions with practical instructions.',
						'content'     => 'Break down the process into numbered steps. For each step, clearly state what action to take and the expected outcome.',
					),
					array(
						'section_key' => 'preset_howto_troubleshoot',
						'name'        => 'Troubleshooting & Common Pitfalls',
						'description' => 'Frequent mistakes and exact troubleshooting steps.',
						'content'     => 'Highlight the most common mistakes users make during this process and provide exact troubleshooting solutions.',
					),
					array(
						'section_key' => 'preset_howto_summary',
						'name'        => 'Summary & Next Steps',
						'description' => 'Verification checklist and recommended next steps.',
						'content'     => 'Summarize the accomplished goal, provide a quick verification checklist, and suggest logical next tutorials.',
					),
				),
				'structure'   => array(
					'name'            => 'Preset: Step-by-Step Tutorial',
					'description'     => 'Instructional structure with prerequisites, walkthrough, and troubleshooting.',
					'section_keys'    => array('preset_howto_prereq', 'preset_howto_steps', 'preset_howto_troubleshoot', 'preset_howto_summary'),
					'prompt_template' => "Write a practical step-by-step tutorial on: {{topic}}.\n\n{{section:preset_howto_prereq}}\n\n{{section:preset_howto_steps}}\n\n{{section:preset_howto_troubleshoot}}\n\n{{section:preset_howto_summary}}",
				),
				'author'      => array(
					'name'                       => 'Jordan Lee',
					'field_niche'                => 'Practical Tutorials & Workflows',
					'description'                => 'Hands-on engineer dedicated to writing clear, frustration-free tutorials and developer guides.',
					'keywords'                   => 'how-to, tutorial, step-by-step, troubleshooting, setup guide',
					'voice_tone'                 => 'Supportive, Clear, Pragmatic',
					'writing_style'              => 'Instructional, numbered steps, practical code snippets',
					'target_audience'            => 'Builders, developers, and practitioners',
					'expertise_level'            => 'Intermediate',
					'topic_generation_frequency' => 'weekly',
					'topic_generation_quantity'  => 5,
					'post_generation_frequency'  => 'weekly',
					'starter_topics'             => array(
						'How to Set Up Your First Production Workflow from Scratch',
						'5 Steps to Troubleshoot and Fix Common Configuration Errors',
						'A Complete Guide to Optimizing Performance and Speed',
					),
				),
				'template'    => array(
					'name'            => 'Preset: Actionable How-To Guide',
					'prompt_template' => "Write a comprehensive how-to tutorial teaching: {{topic}}.\n\nRequirements:\n- Numbered sequential instructions\n- Clear prerequisites and outcome verification\n- Practical troubleshooting tips for common hurdles",
					'title_prompt'    => 'Write an enticing "How to" title for: {{topic}}',
					'post_status'     => 'draft',
					'post_type'       => 'post',
				),
				'schedule'    => array(
					'title'         => 'Weekly Tutorial Publication',
					'frequency'     => 'weekly',
					'schedule_type' => 'post_generation',
					'is_active'     => 0,
				),
			),

			'product_reviews' => array(
				'id'          => 'product_reviews',
				'name'        => __('Product Reviews & Comparisons', 'ai-post-scheduler'),
				'tagline'     => __('Objective product reviews, pros/cons breakdowns, and buyer recommendations.', 'ai-post-scheduler'),
				'description' => __('Perfect for affiliate sites and review blogs. Configures a balanced reviewer voice, testing criteria, pros/cons breakdown, and clear purchase verdicts.', 'ai-post-scheduler'),
				'icon'        => 'dashicons-star-filled',
				'strategy'    => array(
					'niche'           => 'Product Reviews & Buyer Guides',
					'target_audience' => 'Buyers researching options before making purchase decisions',
					'content_goals'   => 'Provide impartial, rigorous reviews with transparent testing insights and clear buyer guidance.',
				),
				'voice'       => array(
					'name'                 => 'Preset: Candid Evaluator',
					'title_prompt'         => 'Create a review headline with the product name and verdict hint (e.g., "Product Review: Is It Worth It?").',
					'content_instructions' => 'Tone: balanced, candid, analytical, helpful. Highlight real advantages alongside genuine drawbacks and value comparisons.',
					'excerpt_instructions' => 'Quick score summary and clear buyer recommendation.',
				),
				'sections'    => array(
					array(
						'section_key' => 'preset_review_overview',
						'name'        => 'Product Overview & Specifications',
						'description' => 'Category, target user, key specifications, and pricing tier.',
						'content'     => 'Introduce the product, its intended market, core specifications, and pricing tier.',
					),
					array(
						'section_key' => 'preset_review_testing',
						'name'        => 'Hands-On Performance Testing',
						'description' => 'Real-world testing observations, performance, and usability.',
						'content'     => 'Detail hands-on testing results across key criteria: usability, reliability, speed, and real-world value.',
					),
					array(
						'section_key' => 'preset_review_proscons',
						'name'        => 'Pros & Cons Breakdown',
						'description' => 'Bulleted strengths versus notable weaknesses.',
						'content'     => 'Present a balanced bulleted list of strengths versus trade-offs and weaknesses.',
					),
					array(
						'section_key' => 'preset_review_verdict',
						'name'        => 'Final Verdict & Buying Advice',
						'description' => 'Definitive recommendation: who should buy and who should pass.',
						'content'     => 'Deliver a definitive final verdict: score, ideal customer profile, and who should consider alternatives.',
					),
				),
				'structure'   => array(
					'name'            => 'Preset: Balanced Product Review',
					'description'     => 'Structured product evaluation layout with specifications, pros/cons, and verdict.',
					'section_keys'    => array('preset_review_overview', 'preset_review_testing', 'preset_review_proscons', 'preset_review_verdict'),
					'prompt_template' => "Write an honest, thorough product review evaluating: {{topic}}.\n\n{{section:preset_review_overview}}\n\n{{section:preset_review_testing}}\n\n{{section:preset_review_proscons}}\n\n{{section:preset_review_verdict}}",
				),
				'author'      => array(
					'name'                       => 'Sam Morgan',
					'field_niche'                => 'Product Testing & Consumer Tech',
					'description'                => 'Product evaluator putting tools through rigorous trials to help buyers choose wisely.',
					'keywords'                   => 'product review, comparison, buyer guide, pros and cons, verdict',
					'voice_tone'                 => 'Objective, Candid, Critical, Helpful',
					'writing_style'              => 'Evaluative, metric-driven, transparent comparisons',
					'target_audience'            => 'Shoppers and comparison seekers',
					'expertise_level'            => 'Expert',
					'topic_generation_frequency' => 'weekly',
					'topic_generation_quantity'  => 5,
					'post_generation_frequency'  => 'weekly',
					'starter_topics'             => array(
						'Hands-on Review: Is the Latest Release Worth the Upgrade?',
						'Top 5 Alternatives Compared Head-to-Head for Value',
						'Complete Buyer Guide: What to Check Before Purchasing',
					),
				),
				'template'    => array(
					'name'            => 'Preset: In-Depth Product Review',
					'prompt_template' => "Write an exhaustive, balanced product review evaluating: {{topic}}.\n\nRequirements:\n- Include honest pros and cons\n- Detail testing conditions and usability findings\n- Deliver a definitive purchase recommendation for specific user types",
					'title_prompt'    => 'Write an engaging review title for: {{topic}}',
					'post_status'     => 'draft',
					'post_type'       => 'post',
				),
				'schedule'    => array(
					'title'         => 'Weekly Review Roundup',
					'frequency'     => 'weekly',
					'schedule_type' => 'post_generation',
					'is_active'     => 0,
				),
			),

			'thought_leadership' => array(
				'id'          => 'thought_leadership',
				'name'        => __('Thought Leadership & Deep Dives', 'ai-post-scheduler'),
				'tagline'     => __('Strategic viewpoints, visionary perspectives, and long-form analytical essays.', 'ai-post-scheduler'),
				'description' => __('Ideal for executives, agencies, and consulting firms. Prepares an authoritative leadership voice, analytical structure, and long-form essay templates.', 'ai-post-scheduler'),
				'icon'        => 'dashicons-lightbulb',
				'strategy'    => array(
					'niche'           => 'Strategic Leadership & Industry Evolution',
					'target_audience' => 'Founders, C-level executives, and innovative decision-makers',
					'content_goals'   => 'Challenge standard assumptions, offer novel mental models, and articulate long-term visions.',
				),
				'voice'       => array(
					'name'                 => 'Preset: Visionary Strategist',
					'title_prompt'         => 'Create a bold, thought-provoking title that challenges an industry orthodoxy.',
					'content_instructions' => 'Tone: visionary, persuasive, intellectually rigorous, authoritative. Connect macro trends to strategic takeaways.',
					'excerpt_instructions' => 'An intellectual hook capturing the counter-intuitive central thesis.',
				),
				'sections'    => array(
					array(
						'section_key' => 'preset_thought_thesis',
						'name'        => 'The Big Idea & Core Tension',
						'description' => 'The emerging shift or hidden problem most organizations overlook.',
						'content'     => 'Articulate the macro shift or underappreciated industry tension occurring right now. Hook the reader with a non-obvious observation.',
					),
					array(
						'section_key' => 'preset_thought_critique',
						'name'        => 'Why Conventional Playbooks Fail',
						'description' => 'Critique of legacy approaches and current consensus.',
						'content'     => 'Critique traditional thinking. Explain why the existing consensus is outdated and why yesterday’s solutions no longer produce results.',
					),
					array(
						'section_key' => 'preset_thought_framework',
						'name'        => 'Strategic Framework for the Future',
						'description' => 'A structured model or paradigm for navigating the new environment.',
						'content'     => 'Introduce a practical mental model or strategic framework that forward-thinking leaders can apply.',
					),
					array(
						'section_key' => 'preset_thought_action',
						'name'        => 'Executive Next Steps',
						'description' => 'High-leverage action items for leadership teams.',
						'content'     => 'Provide 3 high-impact strategic priorities that leaders should execute immediately to thrive in this new landscape.',
					),
				),
				'structure'   => array(
					'name'            => 'Preset: Strategic Thought Piece',
					'description'     => 'Analytical essay layout for deep dives, opinion pieces, and vision articles.',
					'section_keys'    => array('preset_thought_thesis', 'preset_thought_critique', 'preset_thought_framework', 'preset_thought_action'),
					'prompt_template' => "Write an authoritative thought leadership essay analyzing: {{topic}}.\n\n{{section:preset_thought_thesis}}\n\n{{section:preset_thought_critique}}\n\n{{section:preset_thought_framework}}\n\n{{section:preset_thought_action}}",
				),
				'author'      => array(
					'name'                       => 'Elena Vance',
					'field_niche'                => 'Executive Strategy & Innovation',
					'description'                => 'Strategic advisor examining technological paradigm shifts and long-term organizational agility.',
					'keywords'                   => 'thought leadership, business strategy, future of work, executive vision',
					'voice_tone'                 => 'Visionary, Analytical, Bold, Persuasive',
					'writing_style'              => 'Long-form analytical essay, conceptual models',
					'target_audience'            => 'Executives, founders, and strategists',
					'expertise_level'            => 'Expert',
					'topic_generation_frequency' => 'biweekly',
					'topic_generation_quantity'  => 3,
					'post_generation_frequency'  => 'biweekly',
					'starter_topics'             => array(
						'The Structural Industry Shift Most Organizations Are Ignoring',
						'Why the Traditional Playbook Is Broken and What Replaces It',
						'Building for the Next Decade: Core Principles of Modern Agility',
					),
				),
				'template'    => array(
					'name'            => 'Preset: Strategic Deep Dive',
					'prompt_template' => "Write an insightful thought leadership article on: {{topic}}.\n\nRequirements:\n- Present a defensible, non-obvious central thesis\n- Critique common misconceptions with real-world examples\n- Propose actionable strategic guidance for decision-makers",
					'title_prompt'    => 'Write an intellectual, compelling headline for: {{topic}}',
					'post_status'     => 'draft',
					'post_type'       => 'post',
				),
				'schedule'    => array(
					'title'         => 'Bi-Weekly Strategic Insight',
					'frequency'     => 'biweekly',
					'schedule_type' => 'post_generation',
					'is_active'     => 0,
				),
			),
		);
	}

	/**
	 * Retrieve a specific preset definition.
	 *
	 * @param string $preset_id Preset identifier.
	 * @return array|null Preset array or null if not found.
	 */
	public static function get_preset(string $preset_id): ?array {
		$presets = self::get_presets();
		return isset($presets[$preset_id]) ? $presets[$preset_id] : null;
	}

	/**
	 * Check if a preset exists.
	 *
	 * @param string $preset_id Preset identifier.
	 * @return bool True if registered.
	 */
	public static function preset_exists(string $preset_id): bool {
		$presets = self::get_presets();
		return isset($presets[$preset_id]);
	}
}
