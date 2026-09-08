<?php
/**
 * Admin Page Context & Route Metadata Model
 *
 * Provides a canonical registry of all 8 admin hubs, tabs, and child views.
 * Resolves current route state to generate contextual headers, breadcrumbs,
 * summary chips, and actions across the WordPress admin.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Page_Context
 */
class AIPS_Admin_Page_Context {

	/**
	 * Canonical Hub Identifiers.
	 */
	public const HUB_DASHBOARD   = 'dashboard';
	public const HUB_AUTOMATIONS = 'automations';
	public const HUB_STUDIO      = 'studio';
	public const HUB_RESEARCH    = 'research';
	public const HUB_CONTENT     = 'content';
	public const HUB_HISTORY     = 'history';
	public const HUB_SETTINGS    = 'settings';
	public const HUB_DIAGNOSTICS = 'diagnostics';

	/**
	 * @var string
	 */
	public $hub_key = '';

	/**
	 * @var string
	 */
	public $hub_label = '';

	/**
	 * @var string
	 */
	public $hub_url = '';

	/**
	 * @var string
	 */
	public $hub_icon = '';

	/**
	 * @var string
	 */
	public $section_key = '';

	/**
	 * @var string
	 */
	public $section_label = '';

	/**
	 * @var string
	 */
	public $section_url = '';

	/**
	 * @var string
	 */
	public $section_icon = '';

	/**
	 * @var string
	 */
	public $view_key = '';

	/**
	 * @var string
	 */
	public $view_label = '';

	/**
	 * @var string
	 */
	public $page_title = '';

	/**
	 * @var string
	 */
	public $context_title = '';

	/**
	 * @var string
	 */
	public $subtitle = '';

	/**
	 * @var array<int, array{label:string, url?:string, icon?:string}>
	 */
	public $breadcrumbs = array();

	/**
	 * @var array<int, array{label:string, value?:string|int, type?:string, icon?:string}>
	 */
	public $summary_items = array();

	/**
	 * @var array<int, array<string, mixed>>
	 */
	public $actions = array();

	/**
	 * Get the canonical hubs registry with localized labels, icons, default tabs, and sections.
	 *
	 * @return array<string, array{slug:string, label:string, icon:string, description:string, default_tab:string, sections:array<string, array{label:string, icon?:string, description?:string}>, child_pages?:array<string, string>}>
	 */
	public static function get_canonical_hubs() {
		return array(
			self::HUB_DASHBOARD => array(
				'slug'        => 'ai-post-scheduler',
				'label'       => __('Dashboard', 'ai-post-scheduler'),
				'icon'        => 'dashicons-chart-pie',
				'description' => __('Observe your AI content generation pipelines, success rates, and upcoming schedules.', 'ai-post-scheduler'),
				'default_tab' => '',
				'sections'    => array(),
				'child_pages' => array(),
			),
			self::HUB_AUTOMATIONS => array(
				'slug'        => 'aips-automations',
				'label'       => __('Automations', 'ai-post-scheduler'),
				'icon'        => 'dashicons-rest-api',
				'description' => __('Orchestrate generation schedules, goal-based campaigns, authors, data sources, monetization, and SEO linking.', 'ai-post-scheduler'),
				'default_tab' => 'schedules',
				'sections'    => array(
					'schedules' => array(
						'label'       => __('Schedules', 'ai-post-scheduler'),
						'icon'        => 'dashicons-clock',
						'description' => __('Recurring generation pipelines and blueprint workflows.', 'ai-post-scheduler'),
					),
					'campaigns' => array(
						'label'       => __('Campaigns', 'ai-post-scheduler'),
						'icon'        => 'dashicons-calendar-alt',
						'description' => __('Goal-oriented post campaigns and targeted publishing batches.', 'ai-post-scheduler'),
					),
					'authors' => array(
						'label'       => __('Authors', 'ai-post-scheduler'),
						'icon'        => 'dashicons-admin-users',
						'description' => __('Content author personas and writing style profiles.', 'ai-post-scheduler'),
					),
					'author-topics' => array(
						'label'       => __("Author's Topics", 'ai-post-scheduler'),
						'icon'        => 'dashicons-list-view',
						'description' => __('AI-generated topic suggestions and approval queues.', 'ai-post-scheduler'),
					),
					'sources' => array(
						'label'       => __('Sources', 'ai-post-scheduler'),
						'icon'        => 'dashicons-rss',
						'description' => __('External RSS feeds and curated data ingestion sources.', 'ai-post-scheduler'),
					),
					'source-data' => array(
						'label'       => __('Source Data', 'ai-post-scheduler'),
						'icon'        => 'dashicons-database',
						'description' => __('Ingested content items and feed payload browser.', 'ai-post-scheduler'),
					),
					'monetization' => array(
						'label'       => __('Monetization', 'ai-post-scheduler'),
						'icon'        => 'dashicons-money-alt',
						'description' => __('Affiliate links, disclosure rules, and call-to-action injections.', 'ai-post-scheduler'),
					),
					'internal-links' => array(
						'label'       => __('Internal Links', 'ai-post-scheduler'),
						'icon'        => 'dashicons-admin-links',
						'description' => __('Automated internal cross-linking rules and anchor targets.', 'ai-post-scheduler'),
					),
					'taxonomy' => array(
						'label'       => __('Taxonomy', 'ai-post-scheduler'),
						'icon'        => 'dashicons-tag',
						'description' => __('Automated category and tag assignment rules.', 'ai-post-scheduler'),
					),
				),
				'child_pages' => array(
					'aips-schedule'             => 'schedules',
					'aips-campaigns'            => 'campaigns',
					'aips-campaign-wizard'      => 'campaigns',
					'aips-campaign-detail'      => 'campaigns',
					'aips-authors'              => 'authors',
					'aips-author-topics'        => 'author-topics',
					'aips-sources'              => 'sources',
					'aips-source-data'          => 'source-data',
					'aips-affiliate-links'      => 'monetization',
					'aips-internal-links'       => 'internal-links',
					'aips-taxonomy'             => 'taxonomy',
				),
			),
			self::HUB_STUDIO => array(
				'slug'        => 'aips-studio',
				'label'       => __('Studio', 'ai-post-scheduler'),
				'icon'        => 'dashicons-art',
				'description' => __('Craft templates, define brand voices, build article structures, and configure modular post slices.', 'ai-post-scheduler'),
				'default_tab' => '',
				'sections'    => array(
					'templates' => array(
						'label'       => __('Templates', 'ai-post-scheduler'),
						'icon'        => 'dashicons-media-document',
						'description' => __('Create and configure AI post generation templates and prompt strategies.', 'ai-post-scheduler'),
					),
					'voices' => array(
						'label'       => __('Voices', 'ai-post-scheduler'),
						'icon'        => 'dashicons-megaphone',
						'description' => __('Define brand personality, tone of voice, stylistic rules, and custom excerpt guidelines.', 'ai-post-scheduler'),
					),
					'structures' => array(
						'label'       => __('Article Structures', 'ai-post-scheduler'),
						'icon'        => 'dashicons-editor-ol',
						'description' => __('Build reusable article frameworks, required section outlines, and heading constraints.', 'ai-post-scheduler'),
					),
					'post-slices' => array(
						'label'       => __('Post Slices', 'ai-post-scheduler'),
						'icon'        => 'dashicons-grid-view',
						'description' => __('Manage modular content blocks, dynamic CTAs, and automated slice insertions.', 'ai-post-scheduler'),
					),
				),
				'child_pages' => array(
					'aips-templates'   => 'templates',
					'aips-voices'      => 'voices',
					'aips-structures'  => 'structures',
					'aips-post-slices' => 'post-slices',
				),
			),
			self::HUB_RESEARCH => array(
				'slug'        => 'aips-research',
				'label'       => __('Research', 'ai-post-scheduler'),
				'icon'        => 'dashicons-search',
				'description' => __('Discover trending topics in your niche using AI-powered research, perform content gap audits, and plan keyword strategy.', 'ai-post-scheduler'),
				'default_tab' => 'trending',
				'sections'    => array(
					'trending' => array(
						'label'       => __('Trending Topics', 'ai-post-scheduler'),
						'icon'        => 'dashicons-chart-line',
						'description' => __('AI niche trends and real-time topic discovery.', 'ai-post-scheduler'),
					),
					'gap-analysis' => array(
						'label'       => __('Content Auditor', 'ai-post-scheduler'),
						'icon'        => 'dashicons-analytics',
						'description' => __('Content gap discovery and search engine optimization audit.', 'ai-post-scheduler'),
					),
					'planner' => array(
						'label'       => __('Keyword Planner', 'ai-post-scheduler'),
						'icon'        => 'dashicons-calendar-alt',
						'description' => __('Topic calendar, keyword research, and cluster grouping.', 'ai-post-scheduler'),
					),
				),
				'child_pages' => array(),
			),
			self::HUB_CONTENT => array(
				'slug'        => 'aips-generated-posts',
				'label'       => __('Content', 'ai-post-scheduler'),
				'icon'        => 'dashicons-admin-post',
				'description' => __('Browse generated posts, review human-in-the-loop drafts, resume partial generations, and manage semantic embeddings.', 'ai-post-scheduler'),
				'default_tab' => 'aips-generated-posts',
				'sections'    => array(
					'aips-generated-posts' => array(
						'label'       => __('Generated Posts', 'ai-post-scheduler'),
						'icon'        => 'dashicons-admin-post',
						'description' => __('Published, scheduled, and drafted AI articles.', 'ai-post-scheduler'),
					),
					'aips-partial-generations' => array(
						'label'       => __('Partial Generations', 'ai-post-scheduler'),
						'icon'        => 'dashicons-warning',
						'description' => __('Incomplete generation runs and recovery workspace.', 'ai-post-scheduler'),
					),
					'aips-pending-review' => array(
						'label'       => __('Pending Review', 'ai-post-scheduler'),
						'icon'        => 'dashicons-visibility',
						'description' => __('Draft articles awaiting human approval before publishing.', 'ai-post-scheduler'),
					),
					'aips-content-indexer' => array(
						'label'       => __('Content Indexer', 'ai-post-scheduler'),
						'icon'        => 'dashicons-database',
						'description' => __('Vector embeddings index and semantic post search.', 'ai-post-scheduler'),
					),
				),
				'child_pages' => array(
					'aips-content-indexer' => 'aips-content-indexer',
				),
			),
			self::HUB_HISTORY => array(
				'slug'        => 'aips-history',
				'label'       => __('History', 'ai-post-scheduler'),
				'icon'        => 'dashicons-backup',
				'description' => __('View generation history containers and inspect every logged step, AI call, and error for each run.', 'ai-post-scheduler'),
				'default_tab' => '',
				'sections'    => array(),
				'child_pages' => array(),
			),
			self::HUB_SETTINGS => array(
				'slug'        => 'aips-settings',
				'label'       => __('Settings', 'ai-post-scheduler'),
				'icon'        => 'dashicons-admin-generic',
				'description' => __('Configure global defaults, AI provider connection, retry limits, notifications, and performance caching.', 'ai-post-scheduler'),
				'default_tab' => 'settings-general',
				'sections'    => array(
					'settings-general' => array(
						'label'       => __('General', 'ai-post-scheduler'),
						'icon'        => 'dashicons-admin-generic',
						'description' => __('Defaults & post settings', 'ai-post-scheduler'),
					),
					'settings-ai' => array(
						'label'       => __('AI Engine', 'ai-post-scheduler'),
						'icon'        => 'dashicons-rest-api',
						'description' => __('Models & AI provider connection', 'ai-post-scheduler'),
					),
					'settings-feedback' => array(
						'label'       => __('Feedback', 'ai-post-scheduler'),
						'icon'        => 'dashicons-thumbs-up',
						'description' => __('Deduplication, quality scoring, and learning', 'ai-post-scheduler'),
					),
					'settings-notifications' => array(
						'label'       => __('Notifications', 'ai-post-scheduler'),
						'icon'        => 'dashicons-email-alt',
						'description' => __('Email & alert channels', 'ai-post-scheduler'),
					),
					'settings-resilience' => array(
						'label'       => __('Resilience & Limits', 'ai-post-scheduler'),
						'icon'        => 'dashicons-shield',
						'description' => __('Failover, backoff, and circuit breaker', 'ai-post-scheduler'),
					),
					'settings-content-strategy' => array(
						'label'       => __('Content Strategy', 'ai-post-scheduler'),
						'icon'        => 'dashicons-art',
						'description' => __('Brand voice & persona defaults', 'ai-post-scheduler'),
					),
					'settings-cache' => array(
						'label'       => __('Performance', 'ai-post-scheduler'),
						'icon'        => 'dashicons-performance',
						'description' => __('Caching layer & object cache driver', 'ai-post-scheduler'),
					),
					'settings-api-keys' => array(
						'label'       => __('API Keys', 'ai-post-scheduler'),
						'icon'        => 'dashicons-admin-network',
						'description' => __('API credentials and external tokens', 'ai-post-scheduler'),
					),
				),
				'child_pages' => array(),
			),
			self::HUB_DIAGNOSTICS => array(
				'slug'        => 'aips-diagnostics',
				'label'       => __('Diagnostics', 'ai-post-scheduler'),
				'icon'        => 'dashicons-admin-tools',
				'description' => __('Review system health, generation operations, telemetry, seeding utilities, and developer tools from one place.', 'ai-post-scheduler'),
				'default_tab' => 'system-info',
				'sections'    => array(
					'system-info' => array(
						'label'       => __('System Info', 'ai-post-scheduler'),
						'icon'        => 'dashicons-info',
						'description' => __('Environment & server specifications', 'ai-post-scheduler'),
					),
					'health' => array(
						'label'       => __('System Health & Tools', 'ai-post-scheduler'),
						'icon'        => 'dashicons-admin-tools',
						'description' => __('Maintenance, recovery & cache rebuild', 'ai-post-scheduler'),
					),
					'operations' => array(
						'label'       => __('Operational Status', 'ai-post-scheduler'),
						'icon'        => 'dashicons-dashboard',
						'description' => __('Scheduler, queue & pipeline metrics', 'ai-post-scheduler'),
					),
					'insights' => array(
						'label'       => __('Operations Insights', 'ai-post-scheduler'),
						'icon'        => 'dashicons-chart-area',
						'description' => __('Detailed cron job and scheduler execution trends', 'ai-post-scheduler'),
					),
					'telemetry' => array(
						'label'       => __('Telemetry', 'ai-post-scheduler'),
						'icon'        => 'dashicons-performance',
						'description' => __('Performance metrics & queries', 'ai-post-scheduler'),
					),
					'cache-monitor' => array(
						'label'       => __('Cache Monitor', 'ai-post-scheduler'),
						'icon'        => 'dashicons-database',
						'description' => __('Object cache & transient stats', 'ai-post-scheduler'),
					),
					'dev-tools' => array(
						'label'       => __('Dev Tools', 'ai-post-scheduler'),
						'icon'        => 'dashicons-hammer',
						'description' => __('Seeder, test generation & cache debuggers', 'ai-post-scheduler'),
					),
					'stress-test' => array(
						'label'       => __('Stress Test', 'ai-post-scheduler'),
						'icon'        => 'dashicons-superhero',
						'description' => __('High-volume batch load testing', 'ai-post-scheduler'),
					),
				),
				'child_pages' => array(
					'aips-operations-insights' => 'insights',
					'aips-status'              => 'health',
					'aips-telemetry'           => 'telemetry',
					'aips-cache-monitor'       => 'cache-monitor',
					'aips-dev-tools'           => 'dev-tools',
					'aips-stress-test'         => 'stress-test',
				),
			),
		);
	}

	/**
	 * Check if a page slug is registered as a child page of a given hub.
	 *
	 * @param string $page    Page slug to test.
	 * @param string $hub_key Target hub key (e.g. self::HUB_AUTOMATIONS).
	 * @return bool
	 */
	public static function is_child_page($page, $hub_key) {
		if (empty($page)) {
			return false;
		}

		$hubs = self::get_canonical_hubs();
		if (!isset($hubs[$hub_key])) {
			return false;
		}

		$child_pages = isset($hubs[$hub_key]['child_pages']) ? $hubs[$hub_key]['child_pages'] : array();
		return array_key_exists($page, $child_pages);
	}

	/**
	 * Resolve current route context into an AIPS_Admin_Page_Context instance.
	 *
	 * @param string|null $page_slug  Optional page slug override.
	 * @param string|null $tab_key    Optional tab/section override.
	 * @param string|null $view_key   Optional leaf view override (e.g. 'wizard', 'detail').
	 * @param array       $overrides  Optional property overrides (actions, summary_items, etc.).
	 * @return AIPS_Admin_Page_Context
	 */
	public static function resolve($page_slug = null, $tab_key = null, $view_key = null, $overrides = array()) {
		$context = new self();

		$current_page = null !== $page_slug ? sanitize_key($page_slug) : (isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'ai-post-scheduler');
		$current_tab  = null !== $tab_key ? sanitize_key($tab_key) : (isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : (isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : ''));
		$current_view = null !== $view_key ? sanitize_key($view_key) : (isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '');

		// Handle specific leaf view query params
		if (empty($current_view)) {
			if ('aips-campaign-wizard' === $current_page || (isset($_GET['action']) && 'wizard' === $_GET['action'])) {
				$current_view = 'wizard';
			} elseif ('aips-campaign-detail' === $current_page || (isset($_GET['campaign_id']) && absint($_GET['campaign_id']) > 0)) {
				$current_view = 'detail';
			} elseif ('aips-author-topics' === $current_page || (isset($_GET['author_id']) && absint($_GET['author_id']) > 0)) {
				$current_view = 'author-topics';
			} elseif ('aips-source-data' === $current_page || (isset($_GET['source_id']) && absint($_GET['source_id']) > 0)) {
				$current_view = 'source-data';
			}
		}

		$hubs = self::get_canonical_hubs();
		$matched_hub_key = self::HUB_DASHBOARD;
		$matched_hub_def = $hubs[self::HUB_DASHBOARD];

		// Find matching hub by slug or child_pages mapping
		foreach ($hubs as $h_key => $h_def) {
			if ($h_def['slug'] === $current_page) {
				$matched_hub_key = $h_key;
				$matched_hub_def = $h_def;
				break;
			}
			if (isset($h_def['child_pages']) && array_key_exists($current_page, $h_def['child_pages'])) {
				$matched_hub_key = $h_key;
				$matched_hub_def = $h_def;
				if (empty($current_tab)) {
					$current_tab = $h_def['child_pages'][$current_page];
				}
				break;
			}
		}

		$context->hub_key   = $matched_hub_key;
		$context->hub_label = $matched_hub_def['label'];
		$context->hub_url   = admin_url('admin.php?page=' . $matched_hub_def['slug']);
		$context->hub_icon  = $matched_hub_def['icon'];
		$context->subtitle  = $matched_hub_def['description'];

		// Resolve active section
		$sections = isset($matched_hub_def['sections']) ? $matched_hub_def['sections'] : array();
		if (!empty($current_tab) && isset($sections[$current_tab])) {
			$context->section_key   = $current_tab;
			$context->section_label = $sections[$current_tab]['label'];
			$context->section_url   = add_query_arg(array('page' => $matched_hub_def['slug'], 'tab' => $current_tab), admin_url('admin.php'));
			$context->section_icon  = isset($sections[$current_tab]['icon']) ? $sections[$current_tab]['icon'] : '';
			if (!empty($sections[$current_tab]['description'])) {
				$context->subtitle = $sections[$current_tab]['description'];
			}
		} elseif (!empty($matched_hub_def['default_tab']) && isset($sections[$matched_hub_def['default_tab']])) {
			$def_key                = $matched_hub_def['default_tab'];
			$context->section_key   = $def_key;
			$context->section_label = $sections[$def_key]['label'];
			$context->section_url   = add_query_arg(array('page' => $matched_hub_def['slug'], 'tab' => $def_key), admin_url('admin.php'));
			$context->section_icon  = isset($sections[$def_key]['icon']) ? $sections[$def_key]['icon'] : '';
		}

		// Resolve leaf view
		if (!empty($current_view)) {
			$context->view_key = $current_view;
			switch ($current_view) {
				case 'wizard':
					$context->view_label = __('Campaign Wizard', 'ai-post-scheduler');
					$context->subtitle   = __('Step-by-step guided campaign creation and publishing setup.', 'ai-post-scheduler');
					break;
				case 'detail':
					$context->view_label = __('Campaign Details', 'ai-post-scheduler');
					$context->subtitle   = __('Monitor campaign progress, generated posts, and generation targets.', 'ai-post-scheduler');
					break;
				case 'author-topics':
					$context->view_label = __("Author's Topic Queue", 'ai-post-scheduler');
					$context->subtitle   = __('Manage AI-suggested topics, approval statuses, and scheduled runs.', 'ai-post-scheduler');
					break;
				case 'source-data':
					$context->view_label = __('Source Feed Data', 'ai-post-scheduler');
					$context->subtitle   = __('Inspect ingested articles, raw payloads, and scraping statuses.', 'ai-post-scheduler');
					break;
				default:
					$context->view_label = ucwords(str_replace(array('-', '_'), ' ', $current_view));
					break;
			}
		}

		// Construct Page Title & Context Title
		$context->page_title = $context->hub_label;
		if (!empty($context->view_label)) {
			$context->context_title = $context->view_label;
		} elseif (!empty($context->section_label) && $context->section_key !== $matched_hub_def['default_tab']) {
			$context->context_title = $context->section_label;
		}

		// Build Breadcrumbs Trail
		$context->breadcrumbs = self::build_breadcrumbs($context);

		// Apply overrides
		if (!empty($overrides) && is_array($overrides)) {
			foreach ($overrides as $k => $v) {
				if (property_exists($context, $k)) {
					$context->$k = $v;
				}
			}
		}

		return $context;
	}

	/**
	 * Build breadcrumbs array for the resolved context.
	 *
	 * @param AIPS_Admin_Page_Context $ctx Resolved context.
	 * @return array<int, array{label:string, url?:string, icon?:string}>
	 */
	public static function build_breadcrumbs($ctx) {
		$crumbs = array();

		// Root hub crumb
		$has_deeper = !empty($ctx->section_key) || !empty($ctx->view_key);
		$crumbs[] = array(
			'label' => $ctx->hub_label,
			'url'   => $has_deeper ? $ctx->hub_url : '',
			'icon'  => $ctx->hub_icon,
		);

		// Section crumb
		if (!empty($ctx->section_label)) {
			$has_view = !empty($ctx->view_key);
			$crumbs[] = array(
				'label' => $ctx->section_label,
				'url'   => $has_view ? $ctx->section_url : '',
				'icon'  => $ctx->section_icon,
			);
		}

		// Leaf view crumb
		if (!empty($ctx->view_label)) {
			$crumbs[] = array(
				'label' => $ctx->view_label,
				'url'   => '',
			);
		}

		return $crumbs;
	}

	/**
	 * Export normalized arguments array for AIPS_Admin_UI_Primitives::render_page_header().
	 *
	 * @return array<string, mixed>
	 */
	public function to_header_args() {
		return array(
			'title'         => $this->page_title,
			'context_title' => $this->context_title,
			'icon'          => $this->hub_icon,
			'description'   => $this->subtitle,
			'breadcrumbs'   => $this->breadcrumbs,
			'summary_items' => $this->summary_items,
			'actions'       => $this->actions,
		);
	}
}
