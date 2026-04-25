<?php
/**
 * History Operation Type Constants
 *
 * Provides a centralised taxonomy of operation type strings used for
 * the hierarchical history system. Top-level "parent" containers use
 * these values as their creation_method / type identifier.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Operation_Type
 *
 * Constants and helpers for history operation type taxonomy.
 */
class AIPS_History_Operation_Type {

	// -------------------------------------------------------------------------
	// Batch / run-level parent types
	// -------------------------------------------------------------------------

	/** A single scheduled-template execution run. */
	const SCHEDULE_RUN = 'schedule_lifecycle';

	/** A cron-driven batch of author-topic generation. */
	const TOPIC_GENERATION_BATCH = 'topic_generation_batch';

	/** A cron-driven batch of author post generation. */
	const POST_GENERATION_BATCH = 'post_generation_batch';

	/** A manual bulk-generate request from the admin UI (queue). */
	const BULK_GENERATE_FROM_QUEUE = 'bulk_generate_from_queue';

	/** A manual bulk-generate request from the author-topics admin UI. */
	const BULK_GENERATE_TOPICS = 'bulk_generate_topics';

	// -------------------------------------------------------------------------
	// Child / item-level types (for reference, not used as parent keys)
	// -------------------------------------------------------------------------

	/** Per-post generation within a schedule run or batch. */
	const POST_GENERATION = 'topic_post_generation';

	/** Per-author topic generation within a batch. */
	const AUTHOR_TOPIC_GENERATION = 'author_topic_generation';

	/** A single immediate generate-now from a topic. */
	const TOPIC_APPROVAL_GENERATE = 'topic_approval';

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Return a human-readable label for a given operation type string.
	 *
	 * @param string $type Operation type string (e.g. SCHEDULE_RUN constant value).
	 * @return string Translated label, or a formatted fallback for unknown types.
	 */
	public static function get_label($type) {
		$labels = array(
			self::SCHEDULE_RUN            => __('Schedule Run', 'ai-post-scheduler'),
			self::TOPIC_GENERATION_BATCH  => __('Topic Generation Batch', 'ai-post-scheduler'),
			self::POST_GENERATION_BATCH   => __('Post Generation Batch', 'ai-post-scheduler'),
			self::BULK_GENERATE_FROM_QUEUE => __('Bulk Generate (Queue)', 'ai-post-scheduler'),
			self::BULK_GENERATE_TOPICS    => __('Bulk Generate (Topics)', 'ai-post-scheduler'),
			self::POST_GENERATION         => __('Post Generation', 'ai-post-scheduler'),
			self::AUTHOR_TOPIC_GENERATION => __('Topic Generation', 'ai-post-scheduler'),
			self::TOPIC_APPROVAL_GENERATE => __('Topic Approval', 'ai-post-scheduler'),
			// Legacy aliases
			'bulk_generation'             => __('Bulk Generate (Queue)', 'ai-post-scheduler'),
			'bulk_generate'               => __('Bulk Generate (Topics)', 'ai-post-scheduler'),
		);

		if (isset($labels[$type])) {
			return $labels[$type];
		}

		// Humanise unknown types: replace underscores with spaces, title-case.
		if (!empty($type)) {
			return ucwords(str_replace('_', ' ', $type));
		}

		return __('Unknown Operation', 'ai-post-scheduler');
	}

	/**
	 * Return all known parent/batch operation types with their labels.
	 *
	 * Useful for populating filter dropdowns.
	 *
	 * @return array<string,string> Associative array of type => label.
	 */
	public static function get_all_types() {
		return array(
			self::SCHEDULE_RUN            => self::get_label(self::SCHEDULE_RUN),
			self::TOPIC_GENERATION_BATCH  => self::get_label(self::TOPIC_GENERATION_BATCH),
			self::POST_GENERATION_BATCH   => self::get_label(self::POST_GENERATION_BATCH),
			self::BULK_GENERATE_FROM_QUEUE => self::get_label(self::BULK_GENERATE_FROM_QUEUE),
			self::BULK_GENERATE_TOPICS    => self::get_label(self::BULK_GENERATE_TOPICS),
		);
	}

	/**
	 * Return all operation type strings that denote a top-level "parent" container.
	 *
	 * @return string[]
	 */
	public static function get_parent_types() {
		return array(
			self::SCHEDULE_RUN,
			self::TOPIC_GENERATION_BATCH,
			self::POST_GENERATION_BATCH,
			self::BULK_GENERATE_FROM_QUEUE,
			self::BULK_GENERATE_TOPICS,
		);
	}
}
