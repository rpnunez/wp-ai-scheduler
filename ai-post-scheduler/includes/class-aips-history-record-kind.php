<?php
/**
 * History Record Kind
 *
 * Every aips_history row is one of three kinds. The kind is data (the
 * `record_kind` column), set once when the row is written, so no query has to
 * guess from creation_method names which rows are real post generations.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.17
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Record_Kind
 *
 * - generation: a run that produces or regenerates a post and owns its components.
 * - job:        operational background work (content indexing, embeddings, bulk slices,
 *               source fetches...). Not a generation, but worth reporting on its own.
 * - event:      an audit row (lifecycle containers, topic-generation wrappers, review
 *               actions, notifications).
 *
 * Callers that know what they are writing pass `record_kind` explicitly (the generator
 * tags every run as a generation whatever its creation_method). Everything else is
 * classified from its creation_method by for_creation_method(); anything unknown is an
 * event, so a new container type can never leak into the run statistics or run lists.
 */
class AIPS_History_Record_Kind {

	const GENERATION = 'generation';
	const JOB        = 'job';
	const EVENT      = 'event';

	/**
	 * All valid kinds.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array(self::GENERATION, self::JOB, self::EVENT);
	}

	/**
	 * Whether a value is a valid kind.
	 *
	 * @param mixed $kind Candidate value.
	 * @return bool
	 */
	public static function is_valid($kind) {
		return is_string($kind) && in_array($kind, self::all(), true);
	}

	/**
	 * Canonical registry of creation_method metadata across the plugin.
	 *
	 * Centralizes display labels, default record_kind classification, trigger
	 * origin ('manual' vs 'scheduled'), and lifecycle/event-only flags so new
	 * creation methods only need to be registered in one place.
	 *
	 * Keys per entry:
	 * - label:      string|null  Translated human-readable label (null if internal/fallback).
	 * - kind:       string|null  Explicit default kind ('generation', 'job', 'event', or null for chain/contextual).
	 * - trigger:    string       'manual', 'scheduled', or 'unknown'.
	 * - lifecycle:  bool         True for lifecycle/execution containers excluded from 3.7.12 stale-run cleanup.
	 * - event_only: bool         True for event-only containers that never own a post's generated components.
	 *
	 * @return array<string,array{label:?string,kind:?string,trigger:string,lifecycle:bool,event_only:bool}>
	 */
	public static function creation_method_registry() {
		return array(
			// Generation runs.
			'manual'                  => array('label' => __('Manual', 'ai-post-scheduler'),                         'kind' => self::GENERATION, 'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'scheduled'               => array('label' => __('Scheduled', 'ai-post-scheduler'),                      'kind' => self::GENERATION, 'trigger' => 'scheduled', 'lifecycle' => false, 'event_only' => false),
			'regenerate'              => array('label' => __('Regeneration', 'ai-post-scheduler'),                   'kind' => self::GENERATION, 'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'retry'                   => array('label' => __('Retry', 'ai-post-scheduler'),                          'kind' => self::GENERATION, 'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'bulk_generate'           => array('label' => __('Bulk Generation', 'ai-post-scheduler'),                'kind' => self::GENERATION, 'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'post_generation'         => array('label' => __('Post Generation', 'ai-post-scheduler'),                'kind' => self::GENERATION, 'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => false),
			'author_post_gen'         => array('label' => __('Author Posts', 'ai-post-scheduler'),                   'kind' => self::GENERATION, 'trigger' => 'scheduled', 'lifecycle' => false, 'event_only' => false),

			// Background jobs.
			'content_indexing'        => array('label' => __('Content Indexing', 'ai-post-scheduler'),               'kind' => self::JOB,        'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => false),
			'content_index_operation' => array('label' => null,                                                      'kind' => self::JOB,        'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => false),
			'author_embeddings'       => array('label' => __('Author Embeddings', 'ai-post-scheduler'),              'kind' => self::JOB,        'trigger' => 'scheduled', 'lifecycle' => false, 'event_only' => false),
			'author_suggestion'       => array('label' => null,                                                      'kind' => self::JOB,        'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => false),
			'author_topic_generation' => array('label' => __('Author Topics', 'ai-post-scheduler'),                  'kind' => self::JOB,        'trigger' => 'scheduled', 'lifecycle' => false, 'event_only' => false),
			'bulk_batch_slice'        => array('label' => null,                                                      'kind' => self::JOB,        'trigger' => 'scheduled', 'lifecycle' => false, 'event_only' => false),
			'source_fetch'            => array('label' => __('Source Fetch', 'ai-post-scheduler'),                   'kind' => self::JOB,        'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => false),
			'research_run'            => array('label' => __('Research Run', 'ai-post-scheduler'),                   'kind' => self::JOB,        'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => false),
			'gsc_sync'                => array('label' => __('Search Console Sync', 'ai-post-scheduler'),            'kind' => self::JOB,        'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => false),
			'link_index_run'          => array('label' => null,                                                      'kind' => self::JOB,        'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => false),

			// Lifecycle & execution event containers.
			'schedule_lifecycle'      => array('label' => __('Schedule Change', 'ai-post-scheduler'),                'kind' => self::EVENT,      'trigger' => 'unknown',   'lifecycle' => true,  'event_only' => true),
			'schedule_execution'      => array('label' => __('Scheduled Execution', 'ai-post-scheduler'),            'kind' => self::EVENT,      'trigger' => 'unknown',   'lifecycle' => true,  'event_only' => true),
			'template_lifecycle'      => array('label' => __('Template Change', 'ai-post-scheduler'),                'kind' => self::EVENT,      'trigger' => 'manual',    'lifecycle' => true,  'event_only' => true),
			'campaign_lifecycle'      => array('label' => __('Campaign Change', 'ai-post-scheduler'),                'kind' => self::EVENT,      'trigger' => 'manual',    'lifecycle' => true,  'event_only' => true),
			'author_lifecycle'        => array('label' => __('Author Change', 'ai-post-scheduler'),                  'kind' => self::EVENT,      'trigger' => 'manual',    'lifecycle' => true,  'event_only' => true),
			'source_lifecycle'        => array('label' => __('Source Change', 'ai-post-scheduler'),                  'kind' => self::EVENT,      'trigger' => 'manual',    'lifecycle' => true,  'event_only' => true),
			'post_review_action'      => array('label' => __('Post Review', 'ai-post-scheduler'),                    'kind' => self::EVENT,      'trigger' => 'manual',    'lifecycle' => true,  'event_only' => true),

			// Non-lifecycle event-only containers.
			'notification_sent'       => array('label' => __('Notification', 'ai-post-scheduler'),                   'kind' => self::EVENT,      'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => true),
			'topic_post_generation'   => array('label' => __('Topic Post Generation', 'ai-post-scheduler'),          'kind' => self::EVENT,      'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => true),
			'bulk_delete'             => array('label' => __('Bulk Delete', 'ai-post-scheduler'),                    'kind' => self::EVENT,      'trigger' => 'manual',    'lifecycle' => false, 'event_only' => true),
			'bulk_delete_feedback'    => array('label' => __('Bulk Delete Feedback', 'ai-post-scheduler'),           'kind' => self::EVENT,      'trigger' => 'manual',    'lifecycle' => false, 'event_only' => true),
			'bulk_schedule'           => array('label' => __('Bulk Schedule', 'ai-post-scheduler'),                  'kind' => self::EVENT,      'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => true),
			'settings_connection_test'=> array('label' => __('AI Connection Test', 'ai-post-scheduler'),             'kind' => self::EVENT,      'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => true),
			'stress_test'             => array('label' => __('Stress Test', 'ai-post-scheduler'),                    'kind' => self::EVENT,      'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => true),
			'taxonomy_generation'     => array('label' => __('Taxonomy Generation', 'ai-post-scheduler'),            'kind' => self::EVENT,      'trigger' => 'unknown',   'lifecycle' => false, 'event_only' => true),

			// Wrapper event containers.
			'manual_generation'       => array('label' => __('Manual Generation', 'ai-post-scheduler'),              'kind' => self::EVENT,      'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'manual_regeneration'     => array('label' => __('Manual Regeneration', 'ai-post-scheduler'),            'kind' => self::EVENT,      'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),

			// Additional labeled or trigger-classified methods (tagged explicitly at write time or matched by chain rules).
			'template_schedule'       => array('label' => __('Template Schedule', 'ai-post-scheduler'),              'kind' => null,             'trigger' => 'scheduled', 'lifecycle' => false, 'event_only' => false),
			'author_topic_gen'        => array('label' => __('Author Topics', 'ai-post-scheduler'),                  'kind' => null,             'trigger' => 'scheduled', 'lifecycle' => false, 'event_only' => false),
			'author_post_generation'  => array('label' => __('Author Posts', 'ai-post-scheduler'),                   'kind' => null,             'trigger' => 'scheduled', 'lifecycle' => false, 'event_only' => false),
			'bulk_generate_now'       => array('label' => __('Bulk Generation', 'ai-post-scheduler'),                'kind' => null,             'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'bulk_generation'         => array('label' => __('Bulk Generation', 'ai-post-scheduler'),                'kind' => null,             'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'bulk_regenerate'         => array('label' => __('Bulk Regeneration', 'ai-post-scheduler'),              'kind' => null,             'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'planner_post'            => array('label' => __('Planner Bulk Generation', 'ai-post-scheduler'),        'kind' => null,             'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'trending_topic_post'     => array('label' => __('Trending Topics Bulk Generation', 'ai-post-scheduler'),'kind' => null,             'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'author_topic_post'       => array('label' => __('Author Topics Bulk Generation', 'ai-post-scheduler'),  'kind' => null,             'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'manual_ui'               => array('label' => null,                                                      'kind' => null,             'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'admin'                   => array('label' => null,                                                      'kind' => null,             'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'preview'                 => array('label' => null,                                                      'kind' => null,             'trigger' => 'manual',    'lifecycle' => false, 'event_only' => false),
			'cron'                    => array('label' => null,                                                      'kind' => null,             'trigger' => 'scheduled', 'lifecycle' => false, 'event_only' => false),
			'batch_job'               => array('label' => null,                                                      'kind' => null,             'trigger' => 'scheduled', 'lifecycle' => false, 'event_only' => false),
		);
	}

	/**
	 * Map of creation_method => translated human-readable label.
	 *
	 * @return array<string,string>
	 */
	public static function creation_method_labels() {
		$labels = array();
		foreach (self::creation_method_registry() as $method => $meta) {
			if (!empty($meta['label'])) {
				$labels[$method] = $meta['label'];
			}
		}
		return $labels;
	}

	/**
	 * creation_method values classified as post-generation runs by default.
	 *
	 * @return string[]
	 */
	public static function generation_creation_methods() {
		$methods = array();
		foreach (self::creation_method_registry() as $method => $meta) {
			if ($meta['kind'] === self::GENERATION) {
				$methods[] = $method;
			}
		}
		return $methods;
	}

	/**
	 * creation_method values used for lifecycle/execution containers.
	 *
	 * @return string[]
	 */
	public static function lifecycle_creation_methods() {
		$methods = array();
		foreach (self::creation_method_registry() as $method => $meta) {
			if (!empty($meta['lifecycle'])) {
				$methods[] = $method;
			}
		}
		return $methods;
	}

	/**
	 * creation_method values that are event-only (never own a generated post's components).
	 *
	 * @return string[]
	 */
	public static function event_only_creation_methods() {
		$methods = array();
		foreach (self::creation_method_registry() as $method => $meta) {
			if (!empty($meta['event_only'])) {
				$methods[] = $method;
			}
		}
		return $methods;
	}

	/**
	 * creation_method values started by a user action.
	 *
	 * @return string[]
	 */
	public static function manual_creation_methods() {
		$methods = array();
		foreach (self::creation_method_registry() as $method => $meta) {
			if ($meta['trigger'] === 'manual') {
				$methods[] = $method;
			}
		}
		return $methods;
	}

	/**
	 * creation_method values started automatically by cron / background workflows.
	 *
	 * @return string[]
	 */
	public static function automatic_creation_methods() {
		$methods = array();
		foreach (self::creation_method_registry() as $method => $meta) {
			if ($meta['trigger'] === 'scheduled') {
				$methods[] = $method;
			}
		}
		return $methods;
	}

	/**
	 * creation_method values that are background jobs.
	 *
	 * Methods that match AIPS_History_Repository::is_chain_method() (content_index,
	 * embedding, batch, internal_link, bulk) are jobs as well, unless they are named as
	 * a generation run or an event.
	 *
	 * @return string[]
	 */
	public static function job_creation_methods() {
		$methods = array();
		foreach (self::creation_method_registry() as $method => $meta) {
			if ($meta['kind'] === self::JOB) {
				$methods[] = $method;
			}
		}

		/**
		 * Filters the creation_method values classified as background jobs.
		 *
		 * @param string[] $methods creation_method values.
		 */
		$methods = apply_filters('aips_history_job_creation_methods', $methods);

		return array_values(array_unique(array_filter(array_map('strval', (array) $methods))));
	}

	/**
	 * creation_method values that are events.
	 *
	 * The event-only list plus the manual generation wrappers that surround a
	 * generation run without being one.
	 *
	 * @return string[]
	 */
	public static function event_creation_methods() {
		$methods = array();
		foreach (self::creation_method_registry() as $method => $meta) {
			if ($meta['kind'] === self::EVENT) {
				$methods[] = $method;
			}
		}
		return $methods;
	}

	/**
	 * Classify a row from its creation_method.
	 *
	 * An empty creation_method is a legacy row: it is a generation when it is linked to
	 * a post, template, topic or author, and an empty shell (an event) otherwise.
	 *
	 * @param string|null $creation_method Raw creation_method.
	 * @param array       $row             Optional row fields (template_id, topic_id, post_id, author_id).
	 * @return string One of the kind constants.
	 */
	public static function for_creation_method($creation_method, array $row = array()) {
		$method = trim((string) $creation_method);

		if ($method === '') {
			foreach (array('template_id', 'topic_id', 'post_id', 'author_id') as $key) {
				if (!empty($row[$key])) {
					return self::GENERATION;
				}
			}

			return self::EVENT;
		}

		if (in_array($method, AIPS_History_Repository::generation_run_creation_methods(), true)) {
			return self::GENERATION;
		}

		if (in_array($method, self::event_creation_methods(), true)) {
			return self::EVENT;
		}

		if (in_array($method, self::job_creation_methods(), true) || AIPS_History_Repository::is_chain_method($method)) {
			return self::JOB;
		}

		return self::EVENT;
	}

	/**
	 * The kind to store for a row about to be inserted: the explicit `record_kind`
	 * when valid, otherwise the classification of its creation_method.
	 *
	 * @param array $data Insert data.
	 * @return string One of the kind constants.
	 */
	public static function resolve(array $data) {
		if (isset($data['record_kind']) && self::is_valid($data['record_kind'])) {
			return $data['record_kind'];
		}

		return self::for_creation_method(isset($data['creation_method']) ? $data['creation_method'] : '', $data);
	}
}
