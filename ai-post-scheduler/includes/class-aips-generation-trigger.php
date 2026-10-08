<?php
/**
 * Generation Trigger Describer
 *
 * Works out what triggered a post generation (schedule, template, campaign,
 * author/topic, ...) and how it was triggered (manually or automatically), so
 * the History modal can show it in the Overview and Timeline tabs.
 *
 * @package AI_Post_Scheduler
 * @since   2.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generation_Trigger
 */
class AIPS_Generation_Trigger {

	const METHOD_MANUAL    = 'manual';
	const METHOD_AUTOMATIC = 'scheduled';

	/**
	 * Whether a creation_method value represents a user-initiated run.
	 *
	 * @param string|null $creation_method Raw creation method.
	 * @return bool
	 */
	public static function is_manual_creation_method($creation_method) {
		$creation_method = strtolower((string) $creation_method);

		if ($creation_method === '') {
			return true;
		}

		foreach (array('manual', 'admin', 'preview', 'bulk_generate_now') as $needle) {
			if (strpos($creation_method, $needle) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Describe how a run was triggered from its creation method alone.
	 *
	 * @param string|null $creation_method Raw creation method.
	 * @return array{method:string,label:string,message:string}
	 */
	public static function describe_method($creation_method) {
		if (self::is_manual_creation_method($creation_method)) {
			return array(
				'method'  => self::METHOD_MANUAL,
				'label'   => __('Manual', 'ai-post-scheduler'),
				'message' => __('Triggered manually by a user', 'ai-post-scheduler'),
			);
		}

		return array(
			'method'  => self::METHOD_AUTOMATIC,
			'label'   => __('Automatic (Scheduled run)', 'ai-post-scheduler'),
			'message' => __('Triggered automatically by a scheduled run', 'ai-post-scheduler'),
		);
	}

	/**
	 * Build the full trigger description for a generation context.
	 *
	 * @param object $context Generation context (template or topic).
	 * @return array{method:array,source:array<string,mixed>,source_message:string}
	 */
	public static function describe($context) {
		$creation_method = method_exists($context, 'get_creation_method') ? $context->get_creation_method() : null;
		$trigger         = method_exists($context, 'get_trigger_context') ? (array) $context->get_trigger_context() : array();
		$source          = array();

		if (!empty($trigger['schedule_id'])) {
			$source['schedule_id'] = (int) $trigger['schedule_id'];
			if (!empty($trigger['schedule_name'])) {
				$source['schedule_name'] = (string) $trigger['schedule_name'];
			}
			if (!empty($trigger['frequency'])) {
				$source['frequency'] = (string) $trigger['frequency'];
			}
		}

		if ($context instanceof AIPS_Template_Context) {
			$template = $context->get_template();
			if ($template) {
				$source['template_id']   = (int) $context->get_id();
				$source['template_name'] = (string) $context->get_name();
				if (!empty($template->campaign_id)) {
					$source['campaign_id']   = (int) $template->campaign_id;
					$campaign_name           = self::lookup_campaign_name((int) $template->campaign_id);
					if ($campaign_name !== '') {
						$source['campaign_name'] = $campaign_name;
					}
				}
			}
			$topic = $context->get_topic();
			if (!empty($topic)) {
				$source['topic'] = (string) $topic;
			}
		} elseif ($context instanceof AIPS_Topic_Context) {
			$author = $context->get_author();
			if ($author && isset($author->id)) {
				$source['author_id'] = (int) $author->id;
				if (!empty($author->name)) {
					$source['author_name'] = (string) $author->name;
				}
			}
			$source['topic_id'] = (int) $context->get_id();
			$topic              = $context->get_topic();
			if (!empty($topic)) {
				$source['topic'] = (string) $topic;
			}
		}

		return array(
			'method'         => self::describe_method($creation_method),
			'source'         => $source,
			'source_message' => self::format_source_message($source),
		);
	}

	/**
	 * Turn a source field map into a single human-readable line.
	 *
	 * @param array<string,mixed> $source Source fields.
	 * @return string
	 */
	public static function format_source_message(array $source) {
		$parts = array();

		if (!empty($source['schedule_id'])) {
			$schedule = !empty($source['schedule_name'])
				? sprintf(__('Schedule "%1$s" (ID %2$d)', 'ai-post-scheduler'), $source['schedule_name'], $source['schedule_id'])
				: sprintf(__('Schedule (ID %d)', 'ai-post-scheduler'), $source['schedule_id']);
			if (!empty($source['frequency'])) {
				$schedule .= ' — ' . $source['frequency'];
			}
			$parts[] = $schedule;
		}

		if (!empty($source['campaign_id'])) {
			$parts[] = !empty($source['campaign_name'])
				? sprintf(__('Campaign "%1$s" (ID %2$d)', 'ai-post-scheduler'), $source['campaign_name'], $source['campaign_id'])
				: sprintf(__('Campaign (ID %d)', 'ai-post-scheduler'), $source['campaign_id']);
		}

		if (!empty($source['template_id'])) {
			$parts[] = !empty($source['template_name'])
				? sprintf(__('Template "%1$s" (ID %2$d)', 'ai-post-scheduler'), $source['template_name'], $source['template_id'])
				: sprintf(__('Template (ID %d)', 'ai-post-scheduler'), $source['template_id']);
		}

		if (!empty($source['author_id'])) {
			$parts[] = !empty($source['author_name'])
				? sprintf(__('Author "%1$s" (ID %2$d)', 'ai-post-scheduler'), $source['author_name'], $source['author_id'])
				: sprintf(__('Author (ID %d)', 'ai-post-scheduler'), $source['author_id']);
		}

		if (!empty($source['topic'])) {
			$parts[] = sprintf(__('Topic "%s"', 'ai-post-scheduler'), $source['topic']);
		}

		return !empty($parts) ? implode(' · ', $parts) : __('No originating schedule, template, or campaign recorded', 'ai-post-scheduler');
	}

	/**
	 * Resolve a campaign name, tolerating a missing repository or campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string
	 */
	private static function lookup_campaign_name($campaign_id) {
		if (!class_exists('AIPS_Campaigns_Repository')) {
			return '';
		}

		try {
			$campaign = AIPS_Campaigns_Repository::instance()->get_campaign_by_id($campaign_id);
		} catch (\Throwable $e) {
			return '';
		}

		return $campaign && !empty($campaign->name) ? (string) $campaign->name : '';
	}
}
