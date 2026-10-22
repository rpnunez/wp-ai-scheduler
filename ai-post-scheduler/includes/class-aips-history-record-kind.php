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
	 * creation_method values that are background jobs.
	 *
	 * Methods that match AIPS_History_Repository::is_chain_method() (content_index,
	 * embedding, batch, internal_link, bulk) are jobs as well, unless they are named as
	 * a generation run or an event.
	 *
	 * @return string[]
	 */
	public static function job_creation_methods() {
		$methods = array(
			'content_indexing', 'content_index_operation', 'author_embeddings', 'author_suggestion',
			'author_topic_generation', 'bulk_batch_slice', 'source_fetch', 'research_run', 'gsc_sync',
			'link_index_run',
		);

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
	 * The event-only list plus the author-topic wrappers that surround a generation
	 * run without being one.
	 *
	 * @return string[]
	 */
	public static function event_creation_methods() {
		return array_merge(
			AIPS_History_Repository::event_only_creation_methods(),
			array('manual_generation', 'manual_regeneration')
		);
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
