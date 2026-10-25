<?php
/**
 * Settings UI Presenter: Authors & Topics Policy
 *
 * Handles rendering and sanitization for author topic auto-approval policies,
 * dual-boundary semantic gates, and feedback/deduplication settings.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Settings_UI_Authors
 */
class AIPS_Settings_UI_Authors {

	/**
	 * Render description for authors settings section.
	 *
	 * @return void
	 */
	public function authors_section_callback() {
		echo '<p>' . esc_html__('Configure default auto-approval policies and dual-boundary semantic gates for author topics.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render description for feedback settings section.
	 *
	 * @return void
	 */
	public function feedback_section_callback() {
		echo '<p>' . esc_html__('Configure how the plugin evaluates and deduplicates generated topic suggestions.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render the topic similarity threshold field.
	 *
	 * @return void
	 */
	public function topic_similarity_threshold_field_callback() {
		$raw   = AIPS_Config::get_instance()->get_option('aips_topic_similarity_threshold');
		$value = is_numeric($raw) ? min(1.0, max(0.1, (float) $raw)) : 0.8;
		?>
		<input
			type="number"
			name="aips_topic_similarity_threshold"
			value="<?php echo esc_attr($value); ?>"
			min="0.1"
			max="1.0"
			step="0.01"
			class="small-text"
		>
		<p class="description">
			<?php esc_html_e('Minimum similarity score (0.1–1.0) used to flag new topics as potential duplicates during generation. A higher value requires topics to be more similar before being flagged. Default: 0.8.', 'ai-post-scheduler'); ?>
		</p>
		<?php
	}

	/**
	 * Render Global Author Topic Auto-Approval Mode.
	 *
	 * @return void
	 */
	public function author_topic_auto_approval_mode_field_callback() {
		$value = AIPS_Author_Topic_Approval_Policy::normalize_mode(AIPS_Config::get_instance()->get_option('aips_author_topic_auto_approval_mode', 'manual'));
		?>
		<select name="aips_author_topic_auto_approval_mode" id="aips_author_topic_auto_approval_mode">
			<option value="manual" <?php selected($value, 'manual'); ?>><?php esc_html_e('Manual Review (Hold in Pending)', 'ai-post-scheduler'); ?></option>
			<option value="all" <?php selected($value, 'all'); ?>><?php esc_html_e('Auto-Approve All (Unfiltered)', 'ai-post-scheduler'); ?></option>
			<option value="score" <?php selected($value, 'score'); ?>><?php esc_html_e('Quality Score Threshold', 'ai-post-scheduler'); ?></option>
			<option value="similarity" <?php selected($value, 'similarity'); ?>><?php esc_html_e('Dual-Boundary Semantic Gate (Vector Similarity)', 'ai-post-scheduler'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Applies only to authors whose Topic Auto-Approval Policy is set to "Use Global Settings". Changing this affects every one of those authors. Authors with their own policy are not affected.', 'ai-post-scheduler'); ?></p>
		<?php
		$embeddings = AIPS_Container::get_instance()->has(AIPS_Embeddings_Service::class)
			? AIPS_Container::get_instance()->make(AIPS_Embeddings_Service::class)
			: null;
		if (!$embeddings || !$embeddings->is_enabled()) :
			?>
			<p class="description aips-embeddings-warning" id="aips-auto-approval-embeddings-warning" style="color: #b32d2e;">
				<?php esc_html_e('Embeddings are currently disabled. The semantic gate cannot measure niche relevance until embeddings are enabled.', 'ai-post-scheduler'); ?>
			</p>
			<?php
		endif;
	}

	/**
	 * Render Global Author Topic Minimum Quality Score.
	 *
	 * @return void
	 */
	public function author_topic_auto_approval_min_score_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_author_topic_auto_approval_min_score', 70);
		?>
		<input type="number" min="1" max="100" step="1" name="aips_author_topic_auto_approval_min_score" id="aips_author_topic_auto_approval_min_score" value="<?php echo esc_attr((string) $value); ?>" class="small-text">
		<p class="description"><?php esc_html_e('Quality Score Threshold mode: topics whose AI quality score is at least this value (1-100) are auto-approved.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render Global Author Topic Minimum Niche Relevance %.
	 *
	 * @return void
	 */
	public function author_topic_auto_approval_min_relevance_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_author_topic_auto_approval_min_relevance', 65);
		?>
		<input type="number" min="1" max="100" step="1" name="aips_author_topic_auto_approval_min_relevance" id="aips_author_topic_auto_approval_min_relevance" value="<?php echo esc_attr((string) $value); ?>" class="small-text"> %
		<p class="description"><?php esc_html_e('Semantic Gate mode: minimum cosine relevance percentage against the author persona and focus niche. Topics below this threshold fail approval.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render Global Author Topic Maximum Duplicate Ceiling.
	 *
	 * @return void
	 */
	public function author_topic_auto_approval_max_similarity_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option('aips_author_topic_auto_approval_max_similarity', 0.85);
		?>
		<input type="number" min="0.50" max="0.99" step="0.01" name="aips_author_topic_auto_approval_max_similarity" id="aips_author_topic_auto_approval_max_similarity" value="<?php echo esc_attr((string) $value); ?>" class="small-text">
		<p class="description"><?php esc_html_e('Semantic Gate mode: maximum cosine similarity allowed against existing topics and published articles. Topics at or above this score are flagged as duplicates.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render Global Author Topic Sub-Threshold Fallback Handling.
	 *
	 * @return void
	 */
	public function author_topic_auto_approval_fallback_field_callback() {
		$value = AIPS_Author_Topic_Approval_Policy::normalize_fallback(AIPS_Config::get_instance()->get_option('aips_author_topic_auto_approval_fallback', 'smart_split'));
		?>
		<select name="aips_author_topic_auto_approval_fallback" id="aips_author_topic_auto_approval_fallback">
			<option value="smart_split" <?php selected($value, 'smart_split'); ?>><?php esc_html_e('Smart Split (Reject duplicates, hold the rest in Pending)', 'ai-post-scheduler'); ?></option>
			<option value="pending" <?php selected($value, 'pending'); ?>><?php esc_html_e('Leave All in Pending (Queue for Review)', 'ai-post-scheduler'); ?></option>
			<option value="rejected" <?php selected($value, 'rejected'); ?>><?php esc_html_e('Immediate Rejection (Move to Rejected tab)', 'ai-post-scheduler'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Determines what happens to topics that do not qualify for auto-approval.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Sanitize similarity threshold (0.1 - 1.0).
	 *
	 * @param mixed $value
	 * @return float
	 */
	public function sanitize_similarity_threshold($value) {
		if (!is_numeric($value)) {
			return 0.8;
		}
		$float = (float) $value;
		return min(1.0, max(0.1, $float));
	}

	/**
	 * Sanitize author topic auto approval mode.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_author_topic_auto_approval_mode($value) {
		return AIPS_Author_Topic_Approval_Policy::normalize_mode($value);
	}

	/**
	 * Sanitize author topic auto approval fallback.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_author_topic_auto_approval_fallback($value) {
		return AIPS_Author_Topic_Approval_Policy::normalize_fallback($value);
	}

	/**
	 * Sanitize an auto-approval threshold expressed as an integer 1-100.
	 *
	 * @param mixed $value
	 * @return int
	 */
	public function sanitize_author_topic_auto_approval_percent($value) {
		return max(1, min(100, absint($value)));
	}

	/**
	 * Sanitize the maximum duplicate similarity (0.50-0.99).
	 *
	 * @param mixed $value
	 * @return float
	 */
	public function sanitize_author_topic_auto_approval_max_similarity($value) {
		return max(0.50, min(0.99, (float) $value));
	}
}
