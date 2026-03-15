<?php
/**
 * History Container Type Constants
 *
 * Defines constants for different types of history containers in the unified
 * history system. These constants categorise the high-level operation that
 * created the container and are used for filtering and display on the History
 * admin page.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Container_Type
 *
 * Provides type constants for history containers and helper methods for
 * label resolution and string-to-constant mapping.
 */
class AIPS_History_Container_Type {

	/**
	 * Post generation — AI content generated from a template or author topic.
	 */
	const POST_GENERATION = 1;

	/**
	 * Author — author persona lifecycle events (create, update, delete).
	 */
	const AUTHOR = 2;

	/**
	 * Author Topic — topic queue operations (generate, approve, reject, bulk actions).
	 */
	const AUTHOR_TOPIC = 3;

	/**
	 * Research — trending topics research and scheduling.
	 */
	const RESEARCH = 4;

	/**
	 * Gap Analysis — content gap analysis runs.
	 */
	const GAP_ANALYSIS = 5;

	/**
	 * Schedule — schedule lifecycle events (run, create, update).
	 */
	const SCHEDULE = 6;

	/**
	 * Post Review — post review workflow actions.
	 */
	const POST_REVIEW = 7;

	/**
	 * Planner — content planner topic generation.
	 */
	const PLANNER = 8;

	/**
	 * Template Audit — template quality audit runs.
	 */
	const TEMPLATE_AUDIT = 9;

	/**
	 * Notification — notification dispatch events.
	 */
	const NOTIFICATION = 10;

	/**
	 * Get the human-readable label for a container type constant.
	 *
	 * @param int $type Container type constant.
	 * @return string Human-readable label.
	 */
	public static function get_label($type) {
		$labels = array(
			self::POST_GENERATION => __('Post Generation', 'ai-post-scheduler'),
			self::AUTHOR          => __('Author', 'ai-post-scheduler'),
			self::AUTHOR_TOPIC    => __('Author Topic', 'ai-post-scheduler'),
			self::RESEARCH        => __('Research', 'ai-post-scheduler'),
			self::GAP_ANALYSIS    => __('Gap Analysis', 'ai-post-scheduler'),
			self::SCHEDULE        => __('Schedule', 'ai-post-scheduler'),
			self::POST_REVIEW     => __('Post Review', 'ai-post-scheduler'),
			self::PLANNER         => __('Planner', 'ai-post-scheduler'),
			self::TEMPLATE_AUDIT  => __('Template Audit', 'ai-post-scheduler'),
			self::NOTIFICATION    => __('Notification', 'ai-post-scheduler'),
		);

		return isset($labels[$type]) ? $labels[$type] : __('Unknown', 'ai-post-scheduler');
	}

	/**
	 * Get all container types as an array of constant => label pairs.
	 *
	 * @return array
	 */
	public static function get_all_types() {
		return array(
			self::POST_GENERATION => self::get_label(self::POST_GENERATION),
			self::AUTHOR          => self::get_label(self::AUTHOR),
			self::AUTHOR_TOPIC    => self::get_label(self::AUTHOR_TOPIC),
			self::RESEARCH        => self::get_label(self::RESEARCH),
			self::GAP_ANALYSIS    => self::get_label(self::GAP_ANALYSIS),
			self::SCHEDULE        => self::get_label(self::SCHEDULE),
			self::POST_REVIEW     => self::get_label(self::POST_REVIEW),
			self::PLANNER         => self::get_label(self::PLANNER),
			self::TEMPLATE_AUDIT  => self::get_label(self::TEMPLATE_AUDIT),
			self::NOTIFICATION    => self::get_label(self::NOTIFICATION),
		);
	}

	/**
	 * Resolve a container type integer constant from a string type identifier.
	 *
	 * These string identifiers are the values passed to
	 * `AIPS_History_Service::create()` throughout the codebase.
	 *
	 * @param string $type_string String type identifier (e.g. 'post_generation').
	 * @return int Container type constant.
	 */
	public static function resolve_from_string($type_string) {
		$map = array(
			// Post generation
			'post_generation'           => self::POST_GENERATION,
			// Author lifecycle
			'author_lifecycle'          => self::AUTHOR,
			// Author topic operations
			'author_topic_generation'   => self::AUTHOR_TOPIC,
			'topic_lifecycle'           => self::AUTHOR_TOPIC,
			'topic_approval'            => self::AUTHOR_TOPIC,
			'topic_rejection'           => self::AUTHOR_TOPIC,
			'manual_generation'         => self::AUTHOR_TOPIC,
			'bulk_generation'           => self::AUTHOR_TOPIC,
			'bulk_generate'             => self::AUTHOR_TOPIC,
			'manual_regeneration'       => self::AUTHOR_TOPIC,
			'bulk_delete'               => self::AUTHOR_TOPIC,
			'bulk_delete_feedback'      => self::AUTHOR_TOPIC,
			// Trending topics research
			'trending_topics_research'  => self::RESEARCH,
			'trending_topics_scheduling' => self::RESEARCH,
			// Content gap analysis
			'content_gap_analysis'      => self::GAP_ANALYSIS,
			// Schedule lifecycle
			'schedule_lifecycle'        => self::SCHEDULE,
			// Post review workflow
			'post_review_action'        => self::POST_REVIEW,
			'post_review_bulk_delete'   => self::POST_REVIEW,
			// Planner
			'planner_topics_generation' => self::PLANNER,
			// Template audit
			'template_audit'            => self::TEMPLATE_AUDIT,
			// Notifications
			'notification_sent'         => self::NOTIFICATION,
		);

		return isset($map[$type_string]) ? $map[$type_string] : self::POST_GENERATION;
	}
}
