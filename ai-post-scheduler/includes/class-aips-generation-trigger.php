<?php
/**
 * Generation Trigger Describer
 *
 * Works out what triggered a history-producing run (schedule, template,
 * campaign, author/topic, content indexing, ...) and how it was triggered
 * (manually or automatically), and records that as the first two entries of
 * a history container so the History modal can show it in the Overview and
 * Timeline tabs.
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
	const METHOD_UNKNOWN   = 'unknown';

	/**
	 * creation_method values that are started by a user action.
	 *
	 * @return string[]
	 */
	private static function manual_methods() {
		return array(
			'manual', 'manual_generation', 'manual_regeneration', 'manual_ui', 'admin', 'preview',
			'post_review_action', 'campaign_lifecycle', 'template_lifecycle', 'author_lifecycle', 'source_lifecycle',
			'bulk_delete', 'bulk_delete_feedback',
			'bulk_generate', 'bulk_generate_now', 'bulk_generation', 'bulk_regenerate',
			'regenerate', 'retry',
		);
	}

	/**
	 * creation_method values that are started by cron / the plugin itself.
	 *
	 * @return string[]
	 */
	private static function automatic_methods() {
		return array(
			'scheduled', 'cron', 'bulk_batch_slice', 'template_schedule', 'schedule_execution', 'batch_job', 'planner_post',
			'trending_topic_post', 'author_topic_post', 'author_topic_gen', 'author_post_gen',
			'author_topic_generation', 'author_post_generation', 'author_embeddings',
		);
	}

	/**
	 * Classify a creation_method as manual, automatic or unknown.
	 *
	 * Unrecognised values are never guessed: they come back as "unknown".
	 *
	 * @param string|null $creation_method Raw creation method.
	 * @return string One of the METHOD_* constants.
	 */
	public static function classify_creation_method($creation_method) {
		$creation_method = strtolower(trim((string) $creation_method));

		if ($creation_method === '') {
			return self::METHOD_UNKNOWN;
		}
		if (in_array($creation_method, self::manual_methods(), true)) {
			return self::METHOD_MANUAL;
		}
		if (in_array($creation_method, self::automatic_methods(), true)) {
			return self::METHOD_AUTOMATIC;
		}

		return self::METHOD_UNKNOWN;
	}

	/**
	 * Whether a creation_method represents a user-initiated run.
	 *
	 * @param string|null $creation_method Raw creation method.
	 * @return bool
	 */
	public static function is_manual_creation_method($creation_method) {
		return self::classify_creation_method($creation_method) === self::METHOD_MANUAL;
	}

	/**
	 * Pick manual/scheduled from the current request, for code that can run
	 * either from a user action or from cron (e.g. content indexing).
	 *
	 * @return string 'scheduled' under cron, 'manual' for a logged-in user, otherwise 'unknown'.
	 */
	public static function detect_creation_method() {
		if (function_exists('wp_doing_cron') && wp_doing_cron()) {
			return self::METHOD_AUTOMATIC;
		}
		if (function_exists('get_current_user_id') && get_current_user_id() > 0) {
			return self::METHOD_MANUAL;
		}

		return self::METHOD_UNKNOWN;
	}

	/**
	 * Describe how a run was triggered from its creation method alone.
	 *
	 * @param string|null $creation_method Raw creation method.
	 * @return array{method:string,label:string,message:string}
	 */
	public static function describe_method($creation_method) {
		$method = self::classify_creation_method($creation_method);

		if ($method === self::METHOD_MANUAL) {
			return array(
				'method'  => self::METHOD_MANUAL,
				'label'   => __('Manual', 'ai-post-scheduler'),
				'message' => __('Triggered manually by a user', 'ai-post-scheduler'),
			);
		}

		if ($method === self::METHOD_AUTOMATIC) {
			return array(
				'method'  => self::METHOD_AUTOMATIC,
				'label'   => __('Automatic (Scheduled run)', 'ai-post-scheduler'),
				'message' => __('Triggered automatically by a scheduled run', 'ai-post-scheduler'),
			);
		}

		return array(
			'method'  => self::METHOD_UNKNOWN,
			'label'   => __('Unknown', 'ai-post-scheduler'),
			'message' => __('Could not determine whether this run was manual or automatic', 'ai-post-scheduler'),
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
		// Contexts built without an explicit method are user-initiated by definition
		// (every automated caller sets one), matching the generator's own default.
		if ($creation_method === null || $creation_method === '') {
			$creation_method = self::METHOD_MANUAL;
		}

		$trigger = method_exists($context, 'get_trigger_context') ? (array) $context->get_trigger_context() : array();
		$source  = array();

		foreach (array('event', 'detail', 'schedule_id', 'schedule_name', 'frequency') as $key) {
			if (!empty($trigger[$key])) {
				$source[$key] = $key === 'schedule_id' ? (int) $trigger[$key] : (string) $trigger[$key];
			}
		}

		if ($context instanceof AIPS_Template_Context) {
			$template = $context->get_template();
			if ($template) {
				$source['template_id']   = (int) $context->get_id();
				$source['template_name'] = (string) $context->get_name();
				if (!empty($template->campaign_id)) {
					$source['campaign_id'] = (int) $template->campaign_id;
					$campaign_name         = self::lookup_campaign_name((int) $template->campaign_id);
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
	 * Write the "trigger source" and "trigger method" entries to a history
	 * container. Call this immediately after creating the container so they
	 * are the first two entries on its Timeline.
	 *
	 * @param object|null         $history         History container (AIPS_History_Container).
	 * @param array<string,mixed> $source          Source fields (see format_source_message()).
	 * @param string|null         $creation_method Raw creation method / METHOD_* value.
	 * @return void
	 */
	public static function record($history, array $source, $creation_method) {
		if (!$history || !method_exists($history, 'record')) {
			return;
		}

		$method = self::describe_method($creation_method);

		// Manual runs: say who started them.
		if ($method['method'] === self::METHOD_MANUAL && empty($source['user']) && function_exists('wp_get_current_user')) {
			$user = wp_get_current_user();
			if ($user && !empty($user->user_login)) {
				$source['user'] = (string) $user->user_login;
			}
		}

		$text = self::format_source_message($source);
		if ($text === '') {
			$text = __('No originating schedule, template, or campaign recorded', 'ai-post-scheduler');
		}

		$history->record(
			'trigger_source',
			sprintf(
				/* translators: %s: description of what started the run */
				__('Started from: %s', 'ai-post-scheduler'),
				$text
			),
			$source,
			null,
			array('component' => 'trigger')
		);

		$history->record(
			'trigger_method',
			$method['message'],
			array(
				'method'          => $method['method'],
				'method_label'    => $method['label'],
				'creation_method' => (string) $creation_method,
			),
			null,
			array('component' => 'trigger')
		);
	}

	/**
	 * Turn a source field map into a single human-readable line.
	 *
	 * @param array<string,mixed> $source Source fields.
	 * @return string Empty string when there is nothing to show.
	 */
	public static function format_source_message(array $source) {
		$parts = array();

		if (!empty($source['event'])) {
			$parts[] = (string) $source['event'];
		}

		if (!empty($source['detail'])) {
			$parts[] = (string) $source['detail'];
		}

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

		if (!empty($source['post_id'])) {
			$parts[] = sprintf(__('Post (ID %d)', 'ai-post-scheduler'), $source['post_id']);
		}

		if (!empty($source['topic'])) {
			$parts[] = sprintf(__('Topic "%s"', 'ai-post-scheduler'), $source['topic']);
		} elseif (!empty($source['topic_id'])) {
			$parts[] = sprintf(__('Topic (ID %d)', 'ai-post-scheduler'), $source['topic_id']);
		}

		if (!empty($source['user'])) {
			$parts[] = sprintf(__('By %s', 'ai-post-scheduler'), $source['user']);
		}

		return implode(' · ', $parts);
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
