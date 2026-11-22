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
			'default'      => 'Lead with the most specific technical term in the title.',
		);

		return $base . ' ' . (isset($rules[$type]) ? $rules[$type] : $rules['default']);
	}

	/**
	 * Global settings v3 always sets.
	 *
	 * @return array Option name => value.
	 */
	public static function settings() {
		return array(
			'aips_allowed_image_sources'  => array('ai_prompt', 'media_library'),
			'aips_max_post_images_limit'  => 3,
			'aips_ai_assistance_guidance' => 'Write for DevStackTips: professional software engineers and web developers (mid to senior level). Voice: authoritative yet approachable, expert-level, practical. Prefer real-world patterns over theory, keep a security-first mindset, use precise technical terms, and avoid hype, clickbait and filler. Keep suggestions concise and specific to PHP, backend, architecture, security, DevOps, testing and AI-for-developers topics.',
		);
	}

	/**
	 * Source groups v3 seeds (created when missing).
	 *
	 * @return array List of ['name','slug','description'].
	 */
	public static function source_groups() {
		return array(
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
	 * Sources v3 seeds (all verified to serve RSS/Atom).
	 *
	 * @return array List of ['name','url','group_slug','is_active'].
	 */
	public static function sources() {
		return array(
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
	 * Raw template config keyed by live template name.
	 *
	 * @return array
	 */
	private static function raw_templates() {
		return array(
			'Beginner How-To' => array(
				'voice'          => 'Hands-On Tutorial Coach',
				'structure'      => 'Evergreen How-To Guide',
				'slug_type'      => 'tutorial',
				'title_prompt'   => 'Write a beginner-friendly how-to title that starts with "How to" and names the exact technology and outcome. Under 65 characters, no clickbait, no year.',
				'image_subject'  => 'a friendly step-by-step learning path with numbered stages leading to a working result',
				'source_group'   => 'php-ecosystem',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Simple step-by-step flow diagram of the setup or process described for {{topic}}, three to five connected stages'),
				),
			),
			'Intermediate Backend' => array(
				'voice'          => 'DevStackTips Default',
				'structure'      => 'Advanced Technical Tutorial',
				'slug_type'      => 'tutorial',
				'title_prompt'   => 'Write a specific title for an intermediate backend tutorial: name the technique and the concrete benefit (performance, reliability, scale). Under 65 characters, no year.',
				'image_subject'  => 'backend services, an API gateway and a database connected by clean data-flow lines',
				'source_group'   => 'backend-architecture',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Request flow diagram for {{topic}}: client, API layer, services and data store as connected boxes with arrows'),
					array('placement' => 'after_section_4', 'prompt' => 'Before and after comparison diagram showing the backend design improvement described for {{topic}}'),
				),
			),
			'Security Guide' => array(
				'voice'          => 'Senior Backend Mentor',
				'structure'      => 'Security Best Practices',
				'slug_type'      => 'security',
				'title_prompt'   => 'Write a security guide title that names the vulnerability or control and the stack, e.g. "Preventing SQL Injection in PHP with Prepared Statements". Under 65 characters, no fear-mongering.',
				'image_subject'  => 'a shield protecting a layered application stack, with a blocked attack path',
				'source_group'   => 'security-news',
				'content_images' => array(),
			),
			'Framework Comparison' => array(
				'voice'          => 'Neutral Technical Analyst',
				'structure'      => 'Comparison Article',
				'slug_type'      => 'comparison',
				'title_prompt'   => 'Write a neutral comparison title in the form "X vs Y: [deciding factor]" that names both options and the main criterion. Under 65 characters, no winner in the title, no year.',
				'image_subject'  => 'two balanced technology stacks side by side on a scale, evenly weighted',
				'source_group'   => 'php-ecosystem',
				'content_images' => array(),
			),
			'Developer Tooling' => array(
				'voice'          => 'Hands-On Tutorial Coach',
				'structure'      => 'Tool / Workflow Explainer',
				'slug_type'      => 'tooling',
				'title_prompt'   => 'Write a practical title that names the tool and the workflow it improves, e.g. "Speed Up PHP Debugging with Xdebug Step Filters". Under 65 characters, no year.',
				'image_subject'  => 'a developer terminal and editor connected to automation gears and a pipeline',
				'source_group'   => 'ai-dev-tooling',
				'content_images' => array(),
			),
			'AI for Developers' => array(
				'voice'          => 'AI Engineering Editor',
				'structure'      => 'AI-for-Devs Article',
				'slug_type'      => 'ai',
				'title_prompt'   => 'Write a grounded title about using AI in a specific developer task; name the task and the technique, avoid hype words like "revolutionary" or "game-changer". Under 65 characters.',
				'image_subject'  => 'a code editor with an assistant panel and a human review checkpoint',
				'source_group'   => 'ai-dev-tooling',
				'content_images' => array(),
			),
			'Security News' => array(
				'voice'          => 'Senior Backend Mentor',
				'structure'      => 'News / Trend Analysis',
				'slug_type'      => 'news',
				'title_prompt'   => 'Write a factual security briefing title that names the affected software or CVE and what developers must do (patch, mitigate, audit). Under 70 characters, no sensationalism.',
				'image_subject'  => 'a security alert radar scanning a network of application nodes',
				'source_group'   => 'security-news',
				'content_images' => array(),
			),
			'PHP Framework Deep Dive' => array(
				'voice'          => 'DevStackTips Default',
				'structure'      => 'Advanced Technical Tutorial',
				'slug_type'      => 'tutorial',
				'title_prompt'   => 'Write a deep-dive title that names the PHP framework, the feature and the practical outcome, e.g. "Laravel Queues: Reliable Retries with Job Middleware". Under 65 characters, no year.',
				'image_subject'  => 'modular PHP framework components fitting together like building blocks',
				'source_group'   => 'php-ecosystem',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Architecture diagram of the framework components involved in {{topic}} and how a request moves through them'),
				),
			),
			'Architecture Pattern Deep Dive' => array(
				'voice'          => 'Staff Software Architect',
				'structure'      => 'Architecture Pattern Deep Dive',
				'slug_type'      => 'architecture',
				'title_prompt'   => 'Write an architecture title that names the pattern and the problem it solves, e.g. "Hexagonal Architecture: Isolating PHP Domain Logic". Under 65 characters, no year.',
				'image_subject'  => 'isometric modular system components with clear boundaries and connections',
				'source_group'   => 'backend-architecture',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Component diagram of the architecture pattern in {{topic}}: modules, boundaries and dependencies as label-free boxes and arrows'),
					array('placement' => 'after_section_4', 'prompt' => 'Sequence-style diagram of one request flowing through the architecture described in {{topic}}'),
				),
			),
			'Refactoring & Code Smells' => array(
				'voice'          => 'Code Craftsman & Refactoring Lead',
				'structure'      => 'Refactoring & Code Smells Case Study',
				'slug_type'      => 'refactoring',
				'title_prompt'   => 'Write a refactoring title that names the code smell and the refactoring move, e.g. "Breaking Up a God Class with Extract Service". Under 65 characters, no year.',
				'image_subject'  => 'tangled lines reorganizing into clean, orderly modular blocks',
				'source_group'   => 'backend-architecture',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Before and after diagram of the code structure in {{topic}}: tangled dependencies on the left, clean separated modules on the right'),
				),
			),
			'Test Strategy & Automation Guide' => array(
				'voice'          => 'Test Automation & QA Lead',
				'structure'      => 'Test Strategy & Automation Guide',
				'slug_type'      => 'testing',
				'title_prompt'   => 'Write a testing title that names the technique and the confidence it gives, e.g. "Contract Tests for PHP APIs Without Flaky Mocks". Under 65 characters, no year.',
				'image_subject'  => 'a test pyramid and a CI pipeline with passing checks',
				'source_group'   => 'backend-architecture',
				'content_images' => array(
					array('placement' => 'after_section_2', 'prompt' => 'Test strategy diagram for {{topic}}: test pyramid layers and where they run in a CI pipeline'),
				),
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
			'Backend Architecture Specialist'      => array('v2_author' => true,  'slug_type' => 'architecture', 'category' => 'Backend Development', 'image_subject' => 'backend services and APIs connected in a clean system diagram'),
			'Security Expert'                      => array('v2_author' => true,  'slug_type' => 'security',     'category' => 'Security',            'source_group' => 'security-news', 'image_subject' => 'a shield protecting application layers from an attack path'),
			'DevOps Practitioner'                  => array('v2_author' => true,  'slug_type' => 'tooling',      'category' => 'DevOps & Tools',      'image_subject' => 'a CI/CD pipeline with containers and automation gears'),
			'Framework Analyst'                    => array('v2_author' => true,  'slug_type' => 'comparison',   'category' => 'Framework Guides',    'image_subject' => 'framework building blocks compared side by side'),
			'AI Engineering Pragmatist'            => array('v2_author' => true,  'slug_type' => 'ai',           'category' => 'AI for Developers',   'image_subject' => 'a code editor with an AI assistant and a human review step'),
			'Staff Software Architect'             => array('v2_author' => true,  'slug_type' => 'architecture', 'category' => 'Backend Development', 'image_subject' => 'isometric modular architecture with clear domain boundaries'),
			'Test Automation & Quality Specialist' => array('v2_author' => true,  'slug_type' => 'testing',      'category' => 'DevOps & Tools',      'image_subject' => 'a test pyramid with passing checks in a pipeline'),
			'Code Quality & Refactoring Craftsman' => array('v2_author' => true,  'slug_type' => 'refactoring',  'category' => 'Backend Development', 'image_subject' => 'messy code blocks being reorganized into clean modules'),
			'Marcus Vance'                         => array('v2_author' => false, 'slug_type' => 'architecture', 'category' => 'Backend Development', 'image_subject' => 'cloud-native distributed services across regions with message queues'),
			'Elena Rostova'                        => array('v2_author' => false, 'slug_type' => 'default',      'category' => 'Web Development',      'image_subject' => 'a modern frontend component tree with performance gauges'),
			'Devon Miller (multi post)'            => array('v2_author' => false, 'slug_type' => 'security',     'category' => 'Security',            'image_subject' => 'a secured CI/CD pipeline with signed artifacts and a shield'),
		);
	}

	/**
	 * Template definitions keyed by stable key, with cross-references resolved
	 * to the stable keys of the voice, structure, source group they point at.
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

			$out[$key] = array(
				'name'             => $name,
				'voice_key'        => AIPS_Config_Binding::make_key('voices', $cfg['voice']),
				'structure_key'    => AIPS_Config_Binding::make_key('structures', $cfg['structure']),
				'slug_type'        => $cfg['slug_type'],
				'title_prompt'     => $cfg['title_prompt'],
				'image_subject'    => $cfg['image_subject'],
				'source_group_key' => empty($cfg['source_group']) ? null : AIPS_Config_Binding::make_key('source_groups', $cfg['source_group']),
				'content_images'   => $cfg['content_images'],
			);
		}

		return $out;
	}

	/**
	 * Author definitions keyed by stable key, with the category and (optional)
	 * source group resolved to stable keys.
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

			$out[$key] = array(
				'name'             => $name,
				'v2_author'        => !empty($cfg['v2_author']),
				'slug_type'        => $cfg['slug_type'],
				'category_key'     => empty($cfg['category']) ? null : AIPS_Config_Binding::make_key('categories', $cfg['category']),
				'image_subject'    => $cfg['image_subject'],
				'source_group_key' => empty($cfg['source_group']) ? null : AIPS_Config_Binding::make_key('source_groups', $cfg['source_group']),
			);
		}

		return $out;
	}
}
