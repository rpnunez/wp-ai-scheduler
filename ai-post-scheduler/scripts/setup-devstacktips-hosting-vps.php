<?php
/**
 * Add a focused Hosting & VPS content series to DevStackTips.
 *
 * Run from WordPress CLI after setup-devstacktips-content.php:
 *   wp eval-file wp-content/plugins/ai-post-scheduler/scripts/setup-devstacktips-hosting-vps.php
 *
 * This script creates or updates only the specifically named templates and
 * schedules below. Affiliate links are managed separately in Monetization;
 * template affiliate injection remains disabled until repository persistence
 * for that flag is implemented and the intended mappings are configured.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit('Direct access not permitted.');
}

if (!class_exists('AIPS_Template_Repository') || !class_exists('AIPS_Schedule_Repository') || !class_exists('AIPS_Article_Structure_Repository')) {
	exit('AI Post Scheduler repositories are unavailable.');
}

class AIPS_DevStackTips_Hosting_VPS_Setup {
	/**
	 * Content series configuration. Prompts prioritize useful, balanced guidance
	 * and do not make unsupported claims about current prices or plan features.
	 *
	 * @return array
	 */
	private function get_items() {
		return array(
			array(
				'name' => 'Hosting & VPS: Choosing Your First VPS',
				'structure_name' => 'Evergreen How-To Guide',
				'tags' => 'hosting,vps,beginner,server,hosting-buying-guide',
				'weekday' => 1,
				'title_prompt' => 'Create a clear, search-focused title for a beginner deciding whether to move from shared hosting to a VPS. Be specific and avoid clickbait.',
				'prompt' => 'Write a practical, technically accurate guide for a developer or site owner choosing their first VPS. Explain what a VPS is, when shared hosting is still enough, the tradeoffs between managed and unmanaged hosting, Linux experience, resource sizing, backups, support, and likely total cost categories. Give readers a decision checklist and a small first-week setup plan. Mention Namecheap as one provider readers may compare, but do not claim it is best or invent current prices, plan names, benchmarks, or features. Encourage checking current provider documentation and terms. Explain that affiliate relationships may exist where links are shown. Use accessible explanations, concrete examples, and sensible caveats. Structure with an introduction, descriptive H2 sections, a comparison table where useful, checklist, conclusion, and a short FAQ. Do not fabricate tests or personal experience.',
				'image_prompt' => 'Editorial technology illustration, a small developer laptop displaying a simple server dashboard beside a clean abstract virtual server rack, dark navy and teal palette, warm accent light, polished realistic 3D, no text, no logos, 16:9 landscape.',
			),
			array(
				'name' => 'Hosting & VPS: Shared Hosting vs WordPress Hosting',
				'structure_name' => 'Comparison Article',
				'tags' => 'wordpress,shared-hosting,wordpress-hosting,hosting-comparison,beginner',
				'weekday' => 2,
				'title_prompt' => 'Create a balanced, search-focused title comparing shared hosting with WordPress hosting for a site owner choosing where to launch or move a WordPress site.',
				'prompt' => 'Write a balanced decision guide comparing general shared hosting and WordPress-focused hosting for people building or managing a WordPress website. Explain that provider definitions and included services vary, then compare typical management scope, WordPress-specific tools, performance considerations, backups, updates, support, flexibility, migration, security responsibilities, and total cost. Distinguish general category patterns from verified provider-specific claims. Include reader profiles for a new blog, a growing business site, and a technically capable DIY owner, plus questions to ask before buying or migrating. Namecheap may be included as one provider readers can compare, but do not invent current plan names, prices, limits, or features; direct readers to verify current terms. Disclose affiliate relationships wherever applicable. Use a comparison table, clear recommendation framework, practical checklist, conclusion, and FAQ. Avoid claiming hands-on testing or personal experience.',
				'image_prompt' => 'Editorial comparison illustration of two WordPress website hosting setups: a simple shared hosting environment and a managed WordPress hosting dashboard, clear balanced side-by-side composition, modern navy and teal palette with warm orange accent, no text, no logos, 16:9 landscape.',
			),
			array(
				'name' => 'Hosting & VPS: VPS Security Hardening Checklist',
				'structure_name' => 'Security Best Practices',
				'tags' => 'vps,security,linux,hardening,server-admin',
				'weekday' => 3,
				'title_prompt' => 'Create a trustworthy, actionable title for a VPS security hardening checklist. Avoid absolute security claims.',
				'prompt' => 'Write a prioritized VPS security hardening guide for small site owners and developers. Cover account and SSH hygiene, least privilege, updates, firewall rules, service exposure, TLS, secrets, backups and restore tests, monitoring, logging, and incident preparation. Explain what each measure reduces and what it does not protect against. Distinguish general practices from distribution-specific commands, include safe validation steps, and warn before lockout-prone firewall or SSH changes. Include an initial 30-minute checklist and ongoing monthly routine. Do not present any server as completely secure. Provider choice is secondary; Namecheap can be mentioned as one provider to evaluate, without unsupported feature or price claims. Include a clear affiliate disclosure if links appear. Use concise sections, a checklist, realistic examples, and FAQ. Do not fabricate security audits or personal experience.',
				'image_prompt' => 'Professional editorial image of a compact virtual server protected by layered translucent security shields, terminal glow, subtle network lines, dark graphite and emerald palette, realistic 3D, no text or logos, 16:9 landscape.',
			),
			array(
				'name' => 'Hosting & VPS: VPS Backups and Disaster Recovery',
				'structure_name' => 'Evergreen How-To Guide',
				'tags' => 'vps,backup,disaster-recovery,hosting,wordpress',
				'weekday' => 4,
				'title_prompt' => 'Generate a specific search-friendly title about VPS backup and recovery planning for small websites.',
				'prompt' => 'Write a practical backup and disaster recovery guide for a small website running on a VPS. Explain database and file backups, off-server copies, encryption, retention, recovery point and recovery time objectives in plain language, restore testing, DNS considerations, credentials, and a simple recovery runbook. Compare provider snapshots with independent backups and explain why neither should be assumed sufficient on its own. Include a sample schedule readers can adapt, a restore drill, and common failure modes. Avoid promising that any provider guarantees recovery; tell readers to verify current policies and limits. Namecheap may be included as one provider to compare, with no invented current features or prices. State affiliate disclosure where relevant. Organize with clear H2s, a checklist, example plan, and FAQ.',
				'image_prompt' => 'Editorial technology illustration showing a website database and files copied securely from a virtual server to two separate backup locations, clear arrows and recovery symbol, calm blue and mint palette, no text, no logos, 16:9 landscape.',
			),
			array(
				'name' => 'Hosting & VPS: Managed vs Unmanaged VPS',
				'structure_name' => 'Comparison Article',
				'tags' => 'vps,managed-hosting,hosting-comparison,beginner,devops',
				'weekday' => 5,
				'title_prompt' => 'Create a balanced comparison title for managed and unmanaged VPS hosting that helps readers choose by skill and responsibility.',
				'prompt' => 'Write a balanced decision guide comparing managed and unmanaged VPS hosting for freelancers, small businesses, and developers. Define which operational tasks may be handled by the customer or provider and emphasize that the exact boundary varies by plan and contract. Compare control, learning curve, maintenance, incident response, support scope, flexibility, and total cost of ownership. Give three reader profiles and a recommendation framework rather than a universal winner. Include questions to ask sales/support before purchase and a short checklist for migrating later. Namecheap may be listed among providers to compare, but do not state current plan inclusions, prices, or superiority without verified sources. Encourage readers to verify terms directly. Disclose affiliate links where present. Use a comparison table, practical examples, conclusion, and FAQ; avoid invented first-hand experience.',
				'image_prompt' => 'Split editorial illustration: on one side a managed cloud server with a helpful support operator, on the other a developer configuring a VPS terminal, balanced composition, modern navy and cyan with warm orange accents, no text, no logos, 16:9 landscape.',
			),
		);
	}

	/** Create or update only this series and its schedules. */
	public function run() {
		$template_repo = new AIPS_Template_Repository();
		$schedule_repo = new AIPS_Schedule_Repository();
		$structure_repo = new AIPS_Article_Structure_Repository();
		$author_id = (int) get_current_user_id();
		if (!$author_id) {
			$author_id = (int) AIPS_Config::get_instance()->get_option('aips_default_post_author');
		}
		if (!$author_id || !get_user_by('id', $author_id)) {
			WP_CLI::error('No valid post author is available. Set a default post author before running this script.');
		}

		$existing_templates = array();
		foreach ($template_repo->get_all() as $template) {
			if (isset($existing_templates[(string) $template->name])) {
				WP_CLI::error(sprintf('Multiple templates are named "%s". Resolve the duplicate before running this script.', $template->name));
			}
			$existing_templates[(string) $template->name] = (int) $template->id;
		}
		$existing_schedules = array();
		foreach ($schedule_repo->get_all(false) as $schedule) {
			if (isset($existing_schedules[(string) $schedule->title])) {
				WP_CLI::error(sprintf('Multiple schedules are named "%s". Resolve the duplicate before running this script.', $schedule->title));
			}
			$existing_schedules[(string) $schedule->title] = (int) $schedule->id;
		}
		$structures_by_name = array();
		foreach ($structure_repo->get_all(true) as $structure) {
			if (isset($structures_by_name[(string) $structure->name])) {
				WP_CLI::error(sprintf('Multiple active article structures are named "%s".', $structure->name));
			}
			$structures_by_name[(string) $structure->name] = (int) $structure->id;
		}

		$weekday_frequencies = array(
			1 => 'every_monday',
			2 => 'every_tuesday',
			3 => 'every_wednesday',
			4 => 'every_thursday',
			5 => 'every_friday',
		);
		$image_template_names = array(
			'Hosting & VPS: Choosing Your First VPS',
			'Hosting & VPS: Shared Hosting vs WordPress Hosting',
		);

		foreach ($this->get_items() as $item) {
			if (!isset($weekday_frequencies[$item['weekday']])) {
				WP_CLI::error(sprintf('Unsupported schedule weekday configured for "%s".', $item['name']));
			}
			$generate_featured_image = in_array($item['name'], $image_template_names, true);
			if (empty($item['structure_name']) || !isset($structures_by_name[$item['structure_name']])) {
				WP_CLI::error(sprintf('Required active article structure "%s" was not found. Run the DevStackTips setup script first.', isset($item['structure_name']) ? $item['structure_name'] : '(not configured)'));
			}
			$structure_id = $structures_by_name[$item['structure_name']];
			$template_data = array(
				'name' => $item['name'],
				'prompt_template' => $item['prompt'],
				'title_prompt' => $item['title_prompt'],
				'voice_id' => null,
				'post_quantity' => 1,
				'image_prompt' => $item['image_prompt'],
				'generate_featured_image' => $generate_featured_image ? 1 : 0,
				'featured_image_source' => 'ai_prompt',
				'post_status' => 'draft',
				'post_type' => 'post',
				'post_tags' => $item['tags'],
				'post_author' => $author_id,
				'include_sources' => 0,
				'is_active' => 1,
			);

			if (isset($existing_templates[$item['name']])) {
				$template_id = $existing_templates[$item['name']];
				if (!$template_repo->update($template_id, $template_data)) {
					WP_CLI::error(sprintf('Could not update template "%s".', $item['name']));
				}
				echo "[UPDATED] Template: {$item['name']}\n";
			} else {
				$template_id = $template_repo->create($template_data);
				if (!$template_id) {
					echo "[ERROR] Could not create template: {$item['name']}\n";
					continue;
				}
				$existing_templates[$item['name']] = (int) $template_id;
				echo "[CREATED] Template: {$item['name']}\n";
			}

			$schedule_title = 'Hosting & VPS: ' . $item['name'] . ' (weekly)';
			$next_run = $this->next_weekday_at_ten($item['weekday']);
			$schedule_data = array(
				'template_id' => (int) $template_id,
				'title' => $schedule_title,
				'frequency' => $weekday_frequencies[$item['weekday']],
				'topic' => $item['name'],
				'article_structure_id' => $structure_id,
				'next_run' => $next_run,
				'is_active' => 1,
				'status' => 'active',
				'schedule_type' => 'post_generation',
			);
			if (isset($existing_schedules[$schedule_title])) {
				if (!$schedule_repo->update($existing_schedules[$schedule_title], $schedule_data)) {
					WP_CLI::error(sprintf('Could not update schedule "%s".', $schedule_title));
				}
				echo "[UPDATED] Schedule: {$schedule_title}\n";
			} else {
				$schedule_data['last_run'] = 0;
				$schedule_id = $schedule_repo->create($schedule_data);
				if ($schedule_id) {
					$existing_schedules[$schedule_title] = (int) $schedule_id;
					echo "[CREATED] Schedule: {$schedule_title}\n";
				} else {
					echo "[ERROR] Could not create schedule: {$schedule_title}\n";
				}
			}
		}

		echo "\nAffiliate mappings were not created. Configure tag mappings in Automations > Monetization, then enable template insertion after repository persistence is fixed.\n";
	}

	/** Return the next occurrence of a weekday at 10:00 in the site timezone. */
	private function next_weekday_at_ten($iso_weekday) {
		$now = new DateTimeImmutable('now', wp_timezone());
		$target = $now->setTime(10, 0, 0);
		$days = ((int) $iso_weekday - (int) $target->format('N') + 7) % 7;
		if (0 === $days && $target <= $now) {
			$days = 7;
		}
		return $target->modify('+' . $days . ' days')->getTimestamp();
	}
}

(new AIPS_DevStackTips_Hosting_VPS_Setup())->run();

