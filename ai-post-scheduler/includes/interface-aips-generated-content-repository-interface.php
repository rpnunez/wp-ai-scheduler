<?php
/**
 * Generated Content Repository Interface
 *
 * Interface contract for querying and aggregating generated posts content,
 * draft posts pending review, partial generations, and content KPIs.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Generated_Content_Repository_Interface
 */
interface AIPS_Generated_Content_Repository_Interface {

	/**
	 * Get summary KPI statistics for generated content.
	 *
	 * Returns counts for total content, published, scheduled, pending review drafts,
	 * incomplete generations needing attention, and average generation duration.
	 *
	 * @return array {
	 *     @type int   $total_content        Total content records.
	 *     @type int   $total_published      Published posts count.
	 *     @type int   $total_scheduled      Scheduled future posts count.
	 *     @type int   $total_pending_review Draft posts pending review.
	 *     @type int   $total_incomplete     Incomplete/failed generation count.
	 *     @type float $avg_duration_seconds Average generation time in seconds.
	 * }
	 */
	public function get_content_kpis();

	/**
	 * Get paginated unified content records across all states.
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type int    $per_page    Number of items per page. Default 20.
	 *     @type int    $page        Current page number. Default 1.
	 *     @type string $search      Search query for title. Default empty.
	 *     @type int    $author_id   Filter by author ID. Default 0.
	 *     @type int    $template_id Filter by template ID. Default 0.
	 *     @type int    $campaign_id Filter by campaign ID. Default 0.
	 *     @type string $status      Filter by state ('all', 'publish', 'future', 'draft', 'incomplete'). Default empty.
	 *     @type string $post_type   Filter by post type slug. Default empty.
	 *     @type string $orderby     Column to sort by. Default 'created_at'.
	 *     @type string $order       Sort direction ('ASC' or 'DESC'). Default 'DESC'.
	 * }
	 * @return array {
	 *     @type array $items        Array of content items with post details and metadata.
	 *     @type int   $total        Total matching items count.
	 *     @type int   $pages        Total number of pages.
	 *     @type int   $current_page Current active page number.
	 * }
	 */
	public function get_unified_content(array $args = array());
}
