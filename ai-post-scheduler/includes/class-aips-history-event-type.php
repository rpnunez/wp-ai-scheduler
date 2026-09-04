<?php
/**
 * History Event Type catalog.
 *
 * Canonical vocabulary for history event names. Every event a producer emits
 * should resolve to one canonical name registered here. Historical names that
 * described the same event ("naming drift") are registered as aliases so that:
 *
 *   - New writers emit only the canonical name.
 *   - Readers can expand a canonical name back to the full set of historical
 *     names, so rows written before the contract existed still surface.
 *
 * The catalog also records the expected subject type for each event and, where
 * useful, the statuses an event is allowed to carry.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Event_Type
 *
 * Registry of canonical event types plus alias resolution helpers.
 */
final class AIPS_History_Event_Type {

	// Topic review lifecycle.
	const TOPIC_APPROVED = 'topic_approved';
	const TOPIC_REJECTED = 'topic_rejected';

	// Taxonomy review lifecycle.
	const TAXONOMY_APPROVED = 'taxonomy_approved';
	const TAXONOMY_REJECTED = 'taxonomy_rejected';

	// Post lifecycle.
	const POST_PUBLISHED   = 'post_published';
	const POST_DRAFT       = 'post_draft';
	const POST_REGENERATED = 'post_regenerated';
	const POST_DELETED     = 'post_deleted';

	// Author-driven generation.
	const AUTHOR_POST_GENERATION  = 'author_post_generation';
	const AUTHOR_TOPIC_GENERATION = 'author_topic_generation';

	// Schedule lifecycle.
	const SCHEDULE_CREATED           = 'schedule_created';
	const SCHEDULE_UPDATED           = 'schedule_updated';
	const SCHEDULE_ENABLED           = 'schedule_enabled';
	const SCHEDULE_DISABLED          = 'schedule_disabled';
	const SCHEDULE_EXECUTED          = 'schedule_executed';
	const SCHEDULE_FAILED            = 'schedule_failed';
	const MANUAL_SCHEDULE_STARTED    = 'manual_schedule_started';
	const MANUAL_SCHEDULE_COMPLETED  = 'manual_schedule_completed';
	const MANUAL_SCHEDULE_FAILED     = 'manual_schedule_failed';
	const SCHEDULE_TERMINATED        = 'schedule_terminated';
	const MANUAL_SCHEDULE_TERMINATED = 'manual_schedule_terminated';
	const BATCH_SLICE_TERMINATED     = 'batch_slice_terminated';
	const RETRY_SCHEDULE_FAILED      = 'retry_schedule_failed';
	const BULK_SLICING_NOTICE        = 'bulk_slicing_notice';
	const TEMPLATE_SLICING_NOTICE    = 'template_slicing_notice';

	// Batch / job execution.
	const BATCH_SLICE_COMPLETED        = 'batch_slice_completed';
	const BATCH_SLICE_FAILED           = 'batch_slice_failed';
	const BATCH_QUEUE_DISPATCHED       = 'batch_queue_dispatched';
	const BATCH_QUEUE_DISPATCH_FAILED  = 'batch_queue_dispatch_failed';
	const JOB_DISPATCH_FAILED          = 'job_dispatch_failed';
	const GENERATION_EXCEPTION         = 'generation_exception';

	// Embeddings.
	const EMBEDDINGS_BATCH_START    = 'embeddings_batch_start';
	const EMBEDDINGS_BATCH_COMPLETE = 'embeddings_batch_complete';
	const EMBEDDINGS_BATCH_EMPTY    = 'embeddings_batch_empty';
	const EMBEDDING_COMPUTED        = 'embedding_computed';
	const EMBEDDING_SKIPPED         = 'embedding_skipped';
	const EMBEDDING_FAILED          = 'embedding_failed';

	// Notification.
	const NOTIFICATION_EMAIL_SENT = 'notification_email_sent';

	// Campaign lifecycle.
	const CAMPAIGN_CREATED = 'campaign_created';
	const CAMPAIGN_UPDATED = 'campaign_updated';

	/**
	 * Subject type constants (mirrors AIPS_History_Subject::TYPE_*).
	 */
	const SUBJECT_POST          = 'post';
	const SUBJECT_TOPIC         = 'topic';
	const SUBJECT_TAXONOMY_ITEM = 'taxonomy_item';
	const SUBJECT_AUTHOR        = 'author';
	const SUBJECT_SCHEDULE      = 'schedule';
	const SUBJECT_TEMPLATE      = 'template';
	const SUBJECT_CAMPAIGN      = 'campaign';
	const SUBJECT_BATCH         = 'batch';
	const SUBJECT_JOB           = 'job';
	const SUBJECT_EMBEDDING     = 'embedding';
	const SUBJECT_NOTIFICATION  = 'notification';
	const SUBJECT_NONE          = 'none';

	/**
	 * The catalog, lazily built once per request.
	 *
	 * @var array<string, array{aliases: string[], subject: string, statuses: string[]}>|null
	 */
	private static $catalog = null;

	/**
	 * Reverse index: alias (or canonical) => canonical.
	 *
	 * @var array<string, string>|null
	 */
	private static $alias_index = null;

	/**
	 * Build the catalog definition.
	 *
	 * @return array<string, array{aliases: string[], subject: string, statuses: string[]}>
	 */
	private static function definitions() {
		if (self::$catalog !== null) {
			return self::$catalog;
		}

		self::$catalog = array(
			// Topic review. Historical container types used the noun forms
			// ("topic_approval"); the event names used the verb forms.
			self::TOPIC_APPROVED => array(
				'aliases'  => array('topic_approval'),
				'subject'  => self::SUBJECT_TOPIC,
				'statuses' => array(AIPS_History_Event_Status::SUCCESS),
			),
			self::TOPIC_REJECTED => array(
				'aliases'  => array('topic_rejection'),
				'subject'  => self::SUBJECT_TOPIC,
				'statuses' => array(AIPS_History_Event_Status::FAILED),
			),

			// Taxonomy review.
			self::TAXONOMY_APPROVED => array(
				'aliases'  => array('taxonomy_approval'),
				'subject'  => self::SUBJECT_TAXONOMY_ITEM,
				'statuses' => array(AIPS_History_Event_Status::SUCCESS),
			),
			self::TAXONOMY_REJECTED => array(
				'aliases'  => array('taxonomy_rejection'),
				'subject'  => self::SUBJECT_TAXONOMY_ITEM,
				'statuses' => array(AIPS_History_Event_Status::FAILED),
			),

			// Post lifecycle.
			self::POST_PUBLISHED => array(
				'aliases'  => array(),
				'subject'  => self::SUBJECT_POST,
				'statuses' => array(AIPS_History_Event_Status::SUCCESS, AIPS_History_Event_Status::FAILED),
			),
			self::POST_DRAFT => array(
				'aliases'  => array(),
				'subject'  => self::SUBJECT_POST,
				'statuses' => array(AIPS_History_Event_Status::SUCCESS),
			),
			self::POST_REGENERATED => array(
				'aliases'  => array(),
				'subject'  => self::SUBJECT_POST,
				'statuses' => array(AIPS_History_Event_Status::SUCCESS, AIPS_History_Event_Status::FAILED),
			),
			self::POST_DELETED => array(
				'aliases'  => array(),
				'subject'  => self::SUBJECT_POST,
				'statuses' => array(AIPS_History_Event_Status::SUCCESS, AIPS_History_Event_Status::FAILED),
			),

			// Author generation. Legacy "topic_post_generation" is the historical
			// name for author-post generation events.
			self::AUTHOR_POST_GENERATION => array(
				'aliases'  => array('topic_post_generation'),
				'subject'  => self::SUBJECT_AUTHOR,
				'statuses' => array(AIPS_History_Event_Status::SUCCESS, AIPS_History_Event_Status::FAILED, AIPS_History_Event_Status::PARTIAL),
			),
			self::AUTHOR_TOPIC_GENERATION => array(
				'aliases'  => array(),
				'subject'  => self::SUBJECT_AUTHOR,
				'statuses' => array(AIPS_History_Event_Status::SUCCESS, AIPS_History_Event_Status::FAILED),
			),

			// Schedule lifecycle.
			self::SCHEDULE_CREATED          => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::SCHEDULE_UPDATED          => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::SCHEDULE_ENABLED          => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::SCHEDULE_DISABLED         => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::SCHEDULE_EXECUTED         => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::SCHEDULE_FAILED           => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::FAILED)),
			self::MANUAL_SCHEDULE_STARTED   => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::SUCCESS, AIPS_History_Event_Status::RUNNING)),
			self::MANUAL_SCHEDULE_COMPLETED => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::MANUAL_SCHEDULE_FAILED    => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::FAILED)),
			self::SCHEDULE_TERMINATED       => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::TERMINATED)),
			self::MANUAL_SCHEDULE_TERMINATED => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::TERMINATED)),
			self::BATCH_SLICE_TERMINATED    => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::TERMINATED)),
			self::RETRY_SCHEDULE_FAILED     => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::FAILED)),
			self::BULK_SLICING_NOTICE       => array('aliases' => array(), 'subject' => self::SUBJECT_SCHEDULE, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::TEMPLATE_SLICING_NOTICE   => array('aliases' => array(), 'subject' => self::SUBJECT_TEMPLATE, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),

			// Batch / job execution.
			self::BATCH_SLICE_COMPLETED       => array('aliases' => array(), 'subject' => self::SUBJECT_BATCH, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::BATCH_SLICE_FAILED          => array('aliases' => array(), 'subject' => self::SUBJECT_BATCH, 'statuses' => array(AIPS_History_Event_Status::FAILED)),
			self::BATCH_QUEUE_DISPATCHED      => array('aliases' => array(), 'subject' => self::SUBJECT_BATCH, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::BATCH_QUEUE_DISPATCH_FAILED => array('aliases' => array(), 'subject' => self::SUBJECT_BATCH, 'statuses' => array(AIPS_History_Event_Status::FAILED)),
			self::JOB_DISPATCH_FAILED         => array('aliases' => array(), 'subject' => self::SUBJECT_JOB, 'statuses' => array(AIPS_History_Event_Status::FAILED)),
			self::GENERATION_EXCEPTION        => array('aliases' => array(), 'subject' => self::SUBJECT_POST, 'statuses' => array(AIPS_History_Event_Status::FAILED)),

			// Embeddings.
			self::EMBEDDINGS_BATCH_START    => array('aliases' => array(), 'subject' => self::SUBJECT_EMBEDDING, 'statuses' => array(AIPS_History_Event_Status::RUNNING)),
			self::EMBEDDINGS_BATCH_COMPLETE => array('aliases' => array(), 'subject' => self::SUBJECT_EMBEDDING, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::EMBEDDINGS_BATCH_EMPTY    => array('aliases' => array(), 'subject' => self::SUBJECT_EMBEDDING, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::EMBEDDING_COMPUTED        => array('aliases' => array(), 'subject' => self::SUBJECT_EMBEDDING, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::EMBEDDING_SKIPPED         => array('aliases' => array(), 'subject' => self::SUBJECT_EMBEDDING, 'statuses' => array(AIPS_History_Event_Status::SKIPPED)),
			self::EMBEDDING_FAILED          => array('aliases' => array(), 'subject' => self::SUBJECT_EMBEDDING, 'statuses' => array(AIPS_History_Event_Status::FAILED)),

			// Notification.
			self::NOTIFICATION_EMAIL_SENT => array('aliases' => array(), 'subject' => self::SUBJECT_NOTIFICATION, 'statuses' => array(AIPS_History_Event_Status::SUCCESS, AIPS_History_Event_Status::FAILED)),

			// Campaign lifecycle.
			self::CAMPAIGN_CREATED => array('aliases' => array(), 'subject' => self::SUBJECT_CAMPAIGN, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
			self::CAMPAIGN_UPDATED => array('aliases' => array(), 'subject' => self::SUBJECT_CAMPAIGN, 'statuses' => array(AIPS_History_Event_Status::SUCCESS)),
		);

		return self::$catalog;
	}

	/**
	 * Build (and cache) the alias => canonical reverse index.
	 *
	 * @return array<string, string>
	 */
	private static function alias_index() {
		if (self::$alias_index !== null) {
			return self::$alias_index;
		}

		$index = array();
		foreach (self::definitions() as $canonical => $definition) {
			$index[$canonical] = $canonical;
			foreach ($definition['aliases'] as $alias) {
				$index[$alias] = $canonical;
			}
		}

		self::$alias_index = $index;
		return $index;
	}

	/**
	 * Return every canonical event-type name.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array_keys(self::definitions());
	}

	/**
	 * Determine whether a value is registered (canonical or alias).
	 *
	 * @param string $type Event type value.
	 * @return bool
	 */
	public static function is_registered($type) {
		$index = self::alias_index();
		return isset($index[(string) $type]);
	}

	/**
	 * Determine whether a value is a canonical event-type name.
	 *
	 * @param string $type Event type value.
	 * @return bool
	 */
	public static function is_canonical($type) {
		return array_key_exists((string) $type, self::definitions());
	}

	/**
	 * Resolve any registered name (canonical or alias) to its canonical value.
	 *
	 * Unregistered values are returned unchanged so that no data is silently
	 * lost; callers that need strictness can pair this with is_registered().
	 *
	 * @param string $type Raw event-type value.
	 * @return string Canonical value when known, otherwise the input.
	 */
	public static function canonicalize($type) {
		$type  = (string) $type;
		$index = self::alias_index();

		return isset($index[$type]) ? $index[$type] : $type;
	}

	/**
	 * Return the full set of names (canonical + registered aliases) for an event.
	 *
	 * Used by read paths to match both new canonical rows and legacy rows. The
	 * input may itself be an alias. Unregistered inputs return a single-element
	 * array containing the input, so callers always receive a usable set.
	 *
	 * @param string $type Canonical or alias event-type value.
	 * @return string[] Unique list of names to match.
	 */
	public static function names_for($type) {
		$canonical  = self::canonicalize($type);
		$definitions = self::definitions();

		if (!isset($definitions[$canonical])) {
			return array((string) $type);
		}

		return array_values(array_unique(array_merge(
			array($canonical),
			$definitions[$canonical]['aliases']
		)));
	}

	/**
	 * Expand a set of requested event types to all names to match on read.
	 *
	 * @param string[] $types Requested canonical or alias names.
	 * @return string[] Unique, flattened set of names.
	 */
	public static function expand($types) {
		$expanded = array();
		foreach ((array) $types as $type) {
			foreach (self::names_for($type) as $name) {
				$expanded[] = $name;
			}
		}

		return array_values(array_unique($expanded));
	}

	/**
	 * Return the expected subject type for an event, or SUBJECT_NONE if unknown.
	 *
	 * @param string $type Canonical or alias event-type value.
	 * @return string
	 */
	public static function subject_type_for($type) {
		$canonical   = self::canonicalize($type);
		$definitions = self::definitions();

		return isset($definitions[$canonical]) ? $definitions[$canonical]['subject'] : self::SUBJECT_NONE;
	}

	/**
	 * Return the allowed statuses for an event, or an empty array if unconstrained.
	 *
	 * @param string $type Canonical or alias event-type value.
	 * @return string[]
	 */
	public static function allowed_statuses_for($type) {
		$canonical   = self::canonicalize($type);
		$definitions = self::definitions();

		return isset($definitions[$canonical]) ? $definitions[$canonical]['statuses'] : array();
	}
}
