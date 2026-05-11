<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registry for the new admin hub pages.
 */
class AIPS_Admin_Hub_Registry {

	/**
	 * Return all visible hub page definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_hubs() {
		$hubs = array(
			'dashboard'     => array(
				'slug'         => 'ai-post-scheduler',
				'menu_title'   => __('Dashboard', 'ai-post-scheduler'),
				'page_title'   => __('Dashboard', 'ai-post-scheduler'),
				'description'  => __('Track generation health, review recent activity, and finish setup without leaving the main plugin workspace.', 'ai-post-scheduler'),
				'render_active_only' => true,
				'legacy_pages' => array(
					'aips-onboarding',
				),
				'tabs'         => array(
					array(
						'key'     => 'overview',
						'label'   => __('Overview', 'ai-post-scheduler'),
						'title'   => __('Dashboard', 'ai-post-scheduler'),
						'description' => __('Monitor content throughput, queue pressure, and recent activity from one place.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Templates', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary',
								'icon'  => 'dashicons-media-document',
								'url'   => AIPS_Admin_Menu_Helper::get_page_url('templates'),
							),
							array(
								'label' => __('Authors', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary',
								'icon'  => 'dashicons-admin-users',
								'url'   => AIPS_Admin_Menu_Helper::get_page_url('authors'),
							),
							array(
								'label' => __('Schedules', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary',
								'icon'  => 'dashicons-calendar-alt',
								'url'   => AIPS_Admin_Menu_Helper::get_page_url('schedule'),
							),
							array(
								'label' => __('Review Queue', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-primary',
								'icon'  => 'dashicons-visibility',
								'url'   => AIPS_Admin_Menu_Helper::get_page_url('generated_posts', array('subtab' => 'aips-pending-review')),
							),
						),
						'context_callback' => array(__CLASS__, 'get_dashboard_overview_context'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/dashboard/overview.php',
					),
					array(
						'key'     => 'onboarding',
						'label'   => __('Onboarding', 'ai-post-scheduler'),
						'title'   => __('Onboarding Wizard', 'ai-post-scheduler'),
						'description' => __('Finish first-run setup, generate a sample author and template, and verify the plugin can create its first post.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Skip Onboarding', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary',
								'icon'  => 'dashicons-dismiss',
								'id'    => 'aips-onboarding-skip',
							),
							array(
								'label' => __('Open Settings', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary',
								'icon'  => 'dashicons-admin-settings',
								'url'   => AIPS_Admin_Menu_Helper::get_page_url('settings', array('subtab' => 'settings-content-strategy')),
							),
							array(
								'label' => __('Restart Wizard', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-danger',
								'icon'  => 'dashicons-update',
								'id'    => 'aips-onboarding-reset',
							),
						),
						'context_callback' => array(__CLASS__, 'get_onboarding_context'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/dashboard/onboarding.php',
					),
				),
			),
			'content_setup' => array(
				'slug'         => 'aips-content-setup',
				'menu_title'   => __('Content Setup', 'ai-post-scheduler'),
				'page_title'   => __('Content Setup', 'ai-post-scheduler'),
				'description'  => __('Configure the reusable building blocks that shape generated posts.', 'ai-post-scheduler'),
				'render_active_only' => true,
				'legacy_pages' => array(
					'aips-templates',
					'aips-voices',
					'aips-structures',
					'aips-sections',
					'aips-post-slices',
				),
				'tabs'         => array(
					array(
						'key'     => 'templates',
						'label'   => __('Templates', 'ai-post-scheduler'),
						'title'   => __('Post Templates', 'ai-post-scheduler'),
						'description' => __('Create and manage AI post generation templates with custom prompts and publishing settings.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Add Template', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-primary aips-add-template-btn',
								'icon'  => 'dashicons-plus-alt',
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/content-setup/templates.php',
					),
					array(
						'key'     => 'voices',
						'label'   => __('Voices', 'ai-post-scheduler'),
						'title'   => __('Voices', 'ai-post-scheduler'),
						'description' => __('Define reusable voice profiles so generated posts stay consistent in tone, structure, and delivery.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Add Voice', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-primary aips-add-voice-btn',
								'icon'  => 'dashicons-plus-alt2',
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/content-setup/voices.php',
					),
					array(
						'key'     => 'structures',
						'label'   => __('Structures', 'ai-post-scheduler'),
						'title'   => __('Article Structures', 'ai-post-scheduler'),
						'description' => __('Define reusable article outlines and section building blocks for generated posts.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Add Structure Section', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary aips-add-section-btn',
								'icon'  => 'dashicons-plus-alt2',
							),
							array(
								'label' => __('Add New Structure', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-primary aips-add-structure-btn',
								'icon'  => 'dashicons-plus-alt2',
							),
						),
						'subtabs' => array(
							array(
								'key'         => 'aips-structures',
								'label'       => __('Article Structures', 'ai-post-scheduler'),
								'title'       => __('Article Structures', 'ai-post-scheduler'),
								'description' => __('Manage the structure presets that control how AI-generated articles are assembled.', 'ai-post-scheduler'),
							),
							array(
								'key'         => 'aips-structure-sections',
								'label'       => __('Structure Sections', 'ai-post-scheduler'),
								'title'       => __('Structure Sections', 'ai-post-scheduler'),
								'description' => __('Maintain the reusable section blocks that can be mixed into article structures.', 'ai-post-scheduler'),
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/content-setup/structures.php',
					),
					array(
						'key'     => 'post_slices',
						'label'   => __('Post Slices', 'ai-post-scheduler'),
						'title'   => __('Post Slices', 'ai-post-scheduler'),
						'description' => __('Create and manage reusable post slices used by generation workflows.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Add Slice', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-primary aips-add-slice-btn',
								'icon'  => 'dashicons-plus-alt',
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/content-setup/post-slices.php',
					),
				),
			),
			'automation'    => array(
				'slug'         => 'aips-automation',
				'menu_title'   => __('Automation', 'ai-post-scheduler'),
				'page_title'   => __('Automation', 'ai-post-scheduler'),
				'description'  => __('Plan what gets generated, when it runs, and how author-specific workflows move from research to scheduled output.', 'ai-post-scheduler'),
				'render_active_only' => true,
				'legacy_pages' => array(
					'aips-authors',
					'aips-author-topics',
					'aips-research',
					'aips-schedule',
					'aips-schedule-calendar',
				),
				'tabs'         => array(
					array(
						'key'     => 'schedule',
						'label'   => __('Schedule', 'ai-post-scheduler'),
						'title'   => __('Schedules', 'ai-post-scheduler'),
						'description' => __('Manage recurring generation schedules across templates and automated workflows.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Add Template Schedule', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-primary aips-add-schedule-btn',
								'icon'  => 'dashicons-plus-alt',
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/automation/schedule.php',
					),
					array(
						'key'     => 'calendar',
						'label'   => __('Calendar', 'ai-post-scheduler'),
						'title'   => __('Schedule Calendar', 'ai-post-scheduler'),
						'description' => __('Review upcoming scheduled output across month, week, and day views.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('List View', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary',
								'icon'  => 'dashicons-list-view',
								'url'   => AIPS_Admin_Menu_Helper::get_page_url('schedule'),
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/automation/calendar.php',
					),
					array(
						'key'     => 'authors',
						'label'   => __('Authors', 'ai-post-scheduler'),
						'title'   => __('Authors', 'ai-post-scheduler'),
						'description' => __('Manage author profiles, topic generation, and author-led post automation in one workspace.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Suggest Authors', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary',
								'icon'  => 'dashicons-lightbulb',
								'id'    => 'aips-suggest-authors-btn',
							),
							array(
								'label' => __('Add Author', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-primary aips-add-author-btn',
								'icon'  => 'dashicons-plus-alt',
							),
						),
						'subtabs' => array(
							array(
								'key'         => 'authors-list',
								'label'       => __('Authors List', 'ai-post-scheduler'),
								'title'       => __('Authors', 'ai-post-scheduler'),
								'description' => __('Review author profiles, quality signals, and generation controls.', 'ai-post-scheduler'),
							),
							array(
								'key'         => 'generation-queue',
								'label'       => __('Generation Queue', 'ai-post-scheduler'),
								'title'       => __('Generation Queue', 'ai-post-scheduler'),
								'description' => __('Inspect approved author topics waiting to become generated posts.', 'ai-post-scheduler'),
							),
							array(
								'key'         => 'author-topics',
								'label'       => __('Author Topics', 'ai-post-scheduler'),
								'title'       => __('Author Topics', 'ai-post-scheduler'),
								'description' => __('Review pending topics, approvals, rejections, generated posts, and topic feedback for a specific author.', 'ai-post-scheduler'),
								'context_callback' => array(__CLASS__, 'get_author_topics_context'),
							),
						),
						'context_callback' => array(__CLASS__, 'get_authors_context'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/automation/authors.php',
					),
					array(
						'key'     => 'research',
						'label'   => __('Research', 'ai-post-scheduler'),
						'title'   => __('Research', 'ai-post-scheduler'),
						'description' => __('Research trends, identify gaps, and plan coverage before generating content.', 'ai-post-scheduler'),
						'subtabs' => array(
							array(
								'key'         => 'trending',
								'label'       => __('Trending Topics', 'ai-post-scheduler'),
								'title'       => __('Trending Topics', 'ai-post-scheduler'),
								'description' => __('Discover and schedule promising topics from current niche research.', 'ai-post-scheduler'),
							),
							array(
								'key'         => 'gap-analysis',
								'label'       => __('Gap Analysis', 'ai-post-scheduler'),
								'title'       => __('Gap Analysis', 'ai-post-scheduler'),
								'description' => __('Find missing coverage areas and generate topic ideas from content gaps.', 'ai-post-scheduler'),
							),
							array(
								'key'         => 'planner',
								'label'       => __('Planner', 'ai-post-scheduler'),
								'title'       => __('Planner', 'ai-post-scheduler'),
								'description' => __('Turn research findings into a structured editorial plan.', 'ai-post-scheduler'),
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/automation/research.php',
					),
				),
			),
			'outputs'       => array(
				'slug'         => 'aips-outputs',
				'menu_title'   => __('Outputs', 'ai-post-scheduler'),
				'page_title'   => __('Outputs', 'ai-post-scheduler'),
				'description'  => __('Review generated content, keep drafts moving, and inspect generation history without hunting through separate menu branches.', 'ai-post-scheduler'),
				'render_active_only' => true,
				'legacy_pages' => array(
					'aips-generated-posts',
					'aips-history',
				),
				'tabs'         => array(
					array(
						'key'     => 'content-queue',
						'label'   => __('Content Queue', 'ai-post-scheduler'),
						'title'   => __('Generated Content', 'ai-post-scheduler'),
						'description' => __('Review completed posts, partial generations, and drafts pending editorial action.', 'ai-post-scheduler'),
						'subtabs' => array(
							array(
								'key'         => 'aips-generated-posts',
								'label'       => __('Generated Posts', 'ai-post-scheduler'),
								'title'       => __('Generated Posts', 'ai-post-scheduler'),
								'description' => __('Browse completed AI-generated posts and inspect their originating sessions.', 'ai-post-scheduler'),
							),
							array(
								'key'         => 'aips-partial-generations',
								'label'       => __('Partial Generations', 'ai-post-scheduler'),
								'title'       => __('Partial Generations', 'ai-post-scheduler'),
								'description' => __('Find posts with incomplete components and recover the missing pieces.', 'ai-post-scheduler'),
							),
							array(
								'key'         => 'aips-pending-review',
								'label'       => __('Pending Review', 'ai-post-scheduler'),
								'title'       => __('Pending Review', 'ai-post-scheduler'),
								'description' => __('Work through draft posts waiting for editorial review and publishing decisions.', 'ai-post-scheduler'),
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/outputs/content-queue.php',
					),
					array(
						'key'     => 'history',
						'label'   => __('History', 'ai-post-scheduler'),
						'title'   => __('History', 'ai-post-scheduler'),
						'description' => __('Inspect generation runs, logs, and failures across templates, schedules, and author workflows.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Export CSV', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary',
								'icon'  => 'dashicons-download',
								'id'    => 'aips-export-history-btn',
							),
							array(
								'label' => __('Operations Insights', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary',
								'icon'  => 'dashicons-chart-bar',
								'url'   => AIPS_Admin_Menu_Helper::get_page_url('operations_insights'),
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/outputs/history.php',
					),
				),
			),
			'site_context'  => array(
				'slug'         => 'aips-site-context',
				'menu_title'   => __('Site Context', 'ai-post-scheduler'),
				'page_title'   => __('Site Context', 'ai-post-scheduler'),
				'description'  => __('Manage the site knowledge the generators rely on: reference sources, taxonomy terms, and internal linking guidance.', 'ai-post-scheduler'),
				'render_active_only' => true,
				'legacy_pages' => array(
					'aips-sources',
					'aips-taxonomy',
					'aips-internal-links',
				),
				'tabs'         => array(
					array(
						'key'     => 'sources',
						'label'   => __('Sources', 'ai-post-scheduler'),
						'title'   => __('Trusted Sources', 'ai-post-scheduler'),
						'description' => __('Manage the external URLs and source groups used to ground topic research and content generation.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Manage Groups', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary',
								'icon'  => 'dashicons-category',
								'id'    => 'aips-manage-source-groups-btn',
							),
							array(
								'label' => __('Add Source', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-primary',
								'icon'  => 'dashicons-plus-alt2',
								'id'    => 'aips-add-source-btn',
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/site-context/sources.php',
					),
					array(
						'key'     => 'taxonomy',
						'label'   => __('Taxonomy', 'ai-post-scheduler'),
						'title'   => __('Taxonomy', 'ai-post-scheduler'),
						'description' => __('Generate and manage AI-assisted categories and tags based on existing site content.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Generate Taxonomy', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-primary aips-generate-taxonomy',
								'icon'  => 'dashicons-update',
								'id'    => 'aips-open-generate-modal',
							),
						),
						'subtabs' => array(
							array(
								'key'         => 'categories',
								'label'       => __('Categories', 'ai-post-scheduler'),
								'title'       => __('Categories', 'ai-post-scheduler'),
								'description' => __('Review and approve generated category suggestions.', 'ai-post-scheduler'),
							),
							array(
								'key'         => 'tags',
								'label'       => __('Tags', 'ai-post-scheduler'),
								'title'       => __('Tags', 'ai-post-scheduler'),
								'description' => __('Review and approve generated tag suggestions.', 'ai-post-scheduler'),
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/site-context/taxonomy.php',
					),
					array(
						'key'     => 'internal-links',
						'label'   => __('Internal Links', 'ai-post-scheduler'),
						'title'   => __('Internal Links', 'ai-post-scheduler'),
						'description' => __('Index content and manage semantic internal link suggestions.', 'ai-post-scheduler'),
						'actions' => array(
							array(
								'label' => __('Index Posts', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-secondary',
								'icon'  => 'dashicons-database-import',
								'id'    => 'aips-start-indexing-btn',
							),
							array(
								'label' => __('Clear Index', 'ai-post-scheduler'),
								'class' => 'aips-btn aips-btn-ghost aips-btn-danger',
								'icon'  => 'dashicons-trash',
								'id'    => 'aips-clear-index-btn',
							),
						),
						'subtabs' => array(
							array(
								'key'         => 'suggestions',
								'label'       => __('Suggestions', 'ai-post-scheduler'),
								'title'       => __('Suggestions', 'ai-post-scheduler'),
								'description' => __('Review pending internal link suggestions and insertion status.', 'ai-post-scheduler'),
							),
							array(
								'key'         => 'generate',
								'label'       => __('Generate for Post', 'ai-post-scheduler'),
								'title'       => __('Generate for Post', 'ai-post-scheduler'),
								'description' => __('Generate or refresh internal link suggestions for a specific post.', 'ai-post-scheduler'),
							),
						),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/site-context/internal-links.php',
					),
				),
			),
			'operations'    => array(
				'slug'         => 'aips-operations',
				'menu_title'   => __('Operations', 'ai-post-scheduler'),
				'page_title'   => __('Operations', 'ai-post-scheduler'),
				'description'  => __('Monitor operational health, troubleshoot generation workflows, and investigate runtime insights from one workspace.', 'ai-post-scheduler'),
				'render_active_only' => true,
				'legacy_pages' => array(
					'aips-operations-insights',
				),
				'tabs'         => array(
					array(
						'key'     => 'insights',
						'label'   => __('Insights', 'ai-post-scheduler'),
						'title'   => __('Operations Insights', 'ai-post-scheduler'),
						'description' => __('Review throughput, queue pressure, and runtime signals to keep automation healthy.', 'ai-post-scheduler'),
						'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/operations/insights.php',
					),
				),
			),
			'settings'      => array(
				'slug'         => 'aips-settings-hub',
				'menu_title'   => __('Settings', 'ai-post-scheduler'),
				'page_title'   => __('Settings', 'ai-post-scheduler'),
				'description'  => __('Keep plugin configuration, system diagnostics, seed data, telemetry, and developer tools in one administrative workspace.', 'ai-post-scheduler'),
				'render_active_only' => true,
				'legacy_pages' => array(
					'aips-settings',
					'aips-status',
					'aips-seeder',
					'aips-telemetry',
					'aips-dev-tools',
					'aips-onboarding',
				),
				'tabs'         => self::get_settings_tabs(),
			),
		);

		return $hubs;
	}

	/**
	 * Return the visible settings hub tabs, honoring feature flags.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_settings_tabs() {
		$tabs = array(
			array(
				'key'     => 'general',
				'label'   => __('General', 'ai-post-scheduler'),
				'title'   => __('Settings', 'ai-post-scheduler'),
				'description' => __('Configure plugin defaults, AI behavior, notifications, resilience, and performance settings.', 'ai-post-scheduler'),
				'subtabs' => array(
					array(
						'key'         => 'settings-general',
						'label'       => __('General', 'ai-post-scheduler'),
						'title'       => __('General Settings', 'ai-post-scheduler'),
						'description' => __('Control the default behavior for AI-generated posts and plugin-wide editorial settings.', 'ai-post-scheduler'),
					),
					array(
						'key'         => 'settings-ai',
						'label'       => __('AI', 'ai-post-scheduler'),
						'title'       => __('AI Settings', 'ai-post-scheduler'),
						'description' => __('Choose the AI model and environment used for content generation workflows.', 'ai-post-scheduler'),
					),
					array(
						'key'         => 'settings-feedback',
						'label'       => __('Feedback', 'ai-post-scheduler'),
						'title'       => __('Feedback Settings', 'ai-post-scheduler'),
						'description' => __('Tune how the plugin evaluates, deduplicates, and learns from generated topic suggestions.', 'ai-post-scheduler'),
					),
					array(
						'key'         => 'settings-notifications',
						'label'       => __('Notifications', 'ai-post-scheduler'),
						'title'       => __('Notification Settings', 'ai-post-scheduler'),
						'description' => __('Set delivery channels and recipients for plugin notifications and summaries.', 'ai-post-scheduler'),
					),
					array(
						'key'         => 'settings-resilience',
						'label'       => __('Resilience & Limits', 'ai-post-scheduler'),
						'title'       => __('Resilience & Limits', 'ai-post-scheduler'),
						'description' => __('Adjust retry, protection, and safety limits that keep the generation system stable.', 'ai-post-scheduler'),
					),
					array(
						'key'         => 'settings-content-strategy',
						'label'       => __('Content Strategy', 'ai-post-scheduler'),
						'title'       => __('Content Strategy', 'ai-post-scheduler'),
						'description' => __('Define the shared content identity that guides topic and post generation.', 'ai-post-scheduler'),
					),
					array(
						'key'         => 'settings-cache',
						'label'       => __('Performance', 'ai-post-scheduler'),
						'title'       => __('Performance Settings', 'ai-post-scheduler'),
						'description' => __('Configure cache and performance-related behavior for admin and generation workloads.', 'ai-post-scheduler'),
					),
					array(
						'key'         => 'settings-api-keys',
						'label'       => __('API Keys', 'ai-post-scheduler'),
						'title'       => __('API Keys', 'ai-post-scheduler'),
						'description' => __('Manage third-party service keys used by the plugin.', 'ai-post-scheduler'),
					),
					array(
						'key'         => 'settings-developers',
						'label'       => __('Developers', 'ai-post-scheduler'),
						'title'       => __('Developer Settings', 'ai-post-scheduler'),
						'description' => __('Access development-only options and debugging controls.', 'ai-post-scheduler'),
					),
				),
				'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/settings/general.php',
			),
			array(
				'key'     => 'system',
				'label'   => __('System Status', 'ai-post-scheduler'),
				'title'   => __('System Status', 'ai-post-scheduler'),
				'description' => __('Monitor system health, diagnostics, queue pressure, and recovery guidance in one place.', 'ai-post-scheduler'),
				'actions' => array(
					array(
						'label' => __('Run Onboarding Wizard', 'ai-post-scheduler'),
						'class' => 'aips-btn aips-btn-primary',
						'icon'  => 'dashicons-welcome-learn-more',
						'url'   => AIPS_Admin_Menu_Helper::get_page_url('onboarding'),
					),
					array(
						'label' => __('Operations Insights', 'ai-post-scheduler'),
						'class' => 'aips-btn aips-btn-secondary',
						'icon'  => 'dashicons-chart-bar',
						'url'   => AIPS_Admin_Menu_Helper::get_page_url('operations_insights'),
					),
				),
				'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/settings/system.php',
			),
			array(
				'key'     => 'utilities',
				'label'   => __('Utilities', 'ai-post-scheduler'),
				'title'   => __('Utilities', 'ai-post-scheduler'),
				'description' => __('Run maintenance and seed-data utilities for local testing and environment setup.', 'ai-post-scheduler'),
				'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/settings/utilities.php',
			),
		);

		if (AIPS_Config::get_instance()->get_option('aips_enable_telemetry')) {
			$tabs[] = array(
				'key'     => 'telemetry',
				'label'   => __('Telemetry', 'ai-post-scheduler'),
				'title'   => __('Telemetry', 'ai-post-scheduler'),
				'description' => __('Inspect request-level telemetry, filter records, and compare trends across plugin activity.', 'ai-post-scheduler'),
				'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/settings/telemetry.php',
			);
		}

		if (AIPS_Config::get_instance()->get_option('aips_developer_mode')) {
			$tabs[] = array(
				'key'     => 'developer',
				'label'   => __('Developer', 'ai-post-scheduler'),
				'title'   => __('Developer Tools', 'ai-post-scheduler'),
				'description' => __('Generate scaffolds and other development-only assets for faster prototyping.', 'ai-post-scheduler'),
				'partial' => AIPS_PLUGIN_DIR . 'templates/admin/hub/tabs/settings/developer.php',
			);
		}

		return $tabs;
	}

	/**
	 * Fetch one hub definition by internal key.
	 *
	 * @param string $hub_key Hub registry key.
	 * @return array<string, mixed>|null
	 */
	public static function get_hub($hub_key) {
		$hubs = self::get_hubs();

		return isset($hubs[ $hub_key ]) ? $hubs[ $hub_key ] : null;
	}

	/**
	 * Resolve a visible hub definition from an admin page slug.
	 *
	 * @param string $slug Page slug.
	 * @return array<string, mixed>|null
	 */
	public static function get_hub_by_slug($slug) {
		foreach (self::get_hubs() as $hub) {
			if ($hub['slug'] === $slug) {
				return $hub;
			}
		}

		return null;
	}

	/**
	 * Map any visible or hidden plugin page slug to its visible hub slug.
	 *
	 * @param string $page_slug Current admin page slug.
	 * @return string
	 */
	public static function get_visible_slug_for_page($page_slug) {
		if ('ai-post-scheduler' === $page_slug) {
			return 'ai-post-scheduler';
		}

		foreach (self::get_hubs() as $hub) {
			if ($hub['slug'] === $page_slug) {
				return $hub['slug'];
			}

			if (in_array($page_slug, $hub['legacy_pages'], true)) {
				return $hub['slug'];
			}
		}

		return '';
	}

	/**
	 * Build context for the dashboard overview tab.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_dashboard_overview_context($hub = array(), $tab = array(), $subtab = array()) {
		$controller = new AIPS_Dashboard_Controller();
		$data       = $controller->get_view_data();

		return array(
			'metrics' => array(
				array(
					'label' => __('Posts Generated', 'ai-post-scheduler'),
					'value' => isset($data['total_generated']) ? number_format_i18n((int) $data['total_generated']) : '0',
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('generated_posts'),
				),
				array(
					'label' => __('Pending Review', 'ai-post-scheduler'),
					'value' => isset($data['pending_reviews']) ? number_format_i18n((int) $data['pending_reviews']) : '0',
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('generated_posts', array('subtab' => 'aips-pending-review')),
				),
				array(
					'label' => __('Topics in Queue', 'ai-post-scheduler'),
					'value' => isset($data['topics_in_queue']) ? number_format_i18n((int) $data['topics_in_queue']) : '0',
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('authors', array('subtab' => 'generation-queue')),
				),
				array(
					'label' => __('Partial Generations', 'ai-post-scheduler'),
					'value' => isset($data['partial_generations']) ? number_format_i18n((int) $data['partial_generations']) : '0',
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('generated_posts', array('subtab' => 'aips-partial-generations')),
				),
			),
		);
	}

	/**
	 * Build context for the onboarding tab.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_onboarding_context($hub = array(), $tab = array(), $subtab = array()) {
		$wizard            = new AIPS_Onboarding_Wizard();
		$data              = $wizard->get_view_data();
		$state             = !empty($data['state']) && is_array($data['state']) ? $data['state'] : array();
		$aips_config       = AIPS_Config::get_instance();
		$strategy_complete = !empty($aips_config->get_option('aips_site_niche'));
		$author_complete   = !empty($data['author']) && !empty($data['author']->id);
		$template_complete = !empty($data['template']) && !empty($data['template']->id);
		$topics_complete   = !empty($state['topics_generated']);
		$post_complete     = !empty($state['post_id']);
		$completed_steps   = 0;

		foreach (array($strategy_complete, $author_complete, $template_complete, $topics_complete, $post_complete) as $step_complete) {
			if ($step_complete) {
				$completed_steps++;
			}
		}

		return array(
			'metrics' => array(
				array(
					'label' => __('Completed Steps', 'ai-post-scheduler'),
					'value' => sprintf(__('%1$d / %2$d', 'ai-post-scheduler'), $completed_steps, 5),
				),
				array(
					'label' => __('AI Engine', 'ai-post-scheduler'),
					'value' => !empty($data['ai_engine_active']) ? __('Ready', 'ai-post-scheduler') : __('Missing', 'ai-post-scheduler'),
				),
				array(
					'label' => __('Topics Generated', 'ai-post-scheduler'),
					'value' => !empty($state['topics_generated']) ? __('Yes', 'ai-post-scheduler') : __('No', 'ai-post-scheduler'),
				),
				array(
					'label' => __('First Post', 'ai-post-scheduler'),
					'value' => !empty($state['post_id']) ? __('Created', 'ai-post-scheduler') : __('Not Yet', 'ai-post-scheduler'),
				),
			),
		);
	}

	/**
	 * Build shared Authors tab context.
	 *
	 * @param array<string, mixed> $hub    Hub definition.
	 * @param array<string, mixed> $tab    Tab definition.
	 * @param array<string, mixed> $subtab Active subtab definition.
	 * @return array<string, mixed>
	 */
	public static function get_authors_context($hub, $tab, $subtab) {
		if (!empty($subtab['key']) && 'author-topics' === $subtab['key']) {
			return self::get_author_topics_context();
		}

		return array();
	}

	/**
	 * Build context for the Author Topics subtab.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_author_topics_context($hub = array(), $tab = array(), $subtab = array()) {
		$author_id = isset($_GET['author_id']) ? absint(wp_unslash($_GET['author_id'])) : 0;
		if (!$author_id) {
			return array(
				'title'       => __('Author Topics', 'ai-post-scheduler'),
				'description' => __('Select an author to review pending topics, approvals, generated posts, and feedback.', 'ai-post-scheduler'),
			);
		}

		$authors_repository = new AIPS_Authors_Repository();
		$topics_repository  = new AIPS_Author_Topics_Repository();
		$logs_repository    = new AIPS_Author_Topic_Logs_Repository();
		$author             = $authors_repository->get_by_id($author_id);

		if (!$author) {
			return array(
				'title'       => __('Author Topics', 'ai-post-scheduler'),
				'description' => __('The requested author could not be found.', 'ai-post-scheduler'),
			);
		}

		$status_counts = $topics_repository->get_status_counts($author_id);
		$posts_count   = $logs_repository->count_generated_posts_by_author($author_id);
		$total_topics  = (int) $status_counts['pending'] + (int) $status_counts['approved'] + (int) $status_counts['rejected'] + (int) $status_counts['posts_generated'];

		return array(
			'eyebrow'     => __('Automation', 'ai-post-scheduler'),
			'title'       => $author->name,
			'description' => $author->field_niche,
			'breadcrumbs' => array(
				array(
					'label' => __('Authors', 'ai-post-scheduler'),
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('authors'),
				),
				array(
					'label' => $author->name,
				),
			),
			'actions' => array(
				array(
					'label' => __('Edit Author', 'ai-post-scheduler'),
					'class' => 'aips-btn aips-btn-secondary',
					'icon'  => 'dashicons-edit',
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('authors', array('author_id' => $author_id)),
				),
				array(
					'label'      => __('Generate Topics', 'ai-post-scheduler'),
					'class'      => 'aips-btn aips-btn-primary aips-generate-topics-now',
					'icon'       => 'dashicons-update',
					'attributes' => array(
						'data-id' => (string) $author_id,
					),
				),
				array(
					'label' => __('View Generated Posts', 'ai-post-scheduler'),
					'class' => 'aips-btn aips-btn-secondary',
					'icon'  => 'dashicons-admin-post',
					'url'   => AIPS_Admin_Menu_Helper::get_page_url('generated_posts', array('author_id' => $author_id)),
				),
			),
			'metrics' => array(
				array(
					'label' => __('Pending Review', 'ai-post-scheduler'),
					'value' => number_format_i18n((int) $status_counts['pending']),
					'tone'  => 'danger',
					'id'    => 'stat-pending-count',
				),
				array(
					'label' => __('Approved', 'ai-post-scheduler'),
					'value' => number_format_i18n((int) $status_counts['approved']),
					'tone'  => 'success',
					'id'    => 'stat-approved-count',
				),
				array(
					'label' => __('Rejected', 'ai-post-scheduler'),
					'value' => number_format_i18n((int) $status_counts['rejected']),
					'id'    => 'stat-rejected-count',
				),
				array(
					'label' => __('Posts Generated', 'ai-post-scheduler'),
					'value' => number_format_i18n((int) $posts_count),
					'tone'  => 'primary',
					'id'    => 'stat-posts-generated-count',
				),
				array(
					'label' => __('Total Topics', 'ai-post-scheduler'),
					'value' => number_format_i18n($total_topics),
					'id'    => 'stat-total-count',
				),
			),
		);
	}
}
