<?php
/**
 * Author Topics Admin Template
 *
 * Displays all AI-generated topics for a specific author with a full-page
 * management interface allowing approve, reject, edit, delete, and generation.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$author_id = isset($_GET['author_id']) ? absint($_GET['author_id']) : 0;
$authors_page_url = AIPS_Admin_Menu_Helper::get_page_url('authors');

if (!$author_id) {
	?>
	<div class="wrap aips-wrap">
		<div class="aips-page-container">
			<div class="notice notice-error">
				<p>
					<?php esc_html_e('Invalid author ID.', 'ai-post-scheduler'); ?>
					<a href="<?php echo esc_url($authors_page_url); ?>"><?php esc_html_e('Back to Authors', 'ai-post-scheduler'); ?></a>
				</p>
			</div>
		</div>
	</div>
	<?php
	return;
}

$authors_repository = new AIPS_Authors_Repository();
$author = $authors_repository->get_by_id($author_id);
$author_page_url = add_query_arg( array( 'page' => 'aips-authors', 'author_id' => $author_id ), admin_url( 'admin.php' ) );

if (!$author) {
	?>
	<div class="wrap aips-wrap">
		<div class="aips-page-container">
			<div class="notice notice-error">
				<p>
					<?php esc_html_e('Author not found.', 'ai-post-scheduler'); ?>
					<a href="<?php echo esc_url($authors_page_url); ?>"><?php esc_html_e('Back to Authors', 'ai-post-scheduler'); ?></a>
				</p>
			</div>
		</div>
	</div>
	<?php
	return;
}

$topics_repository  = new AIPS_Author_Topics_Repository();
$logs_repository    = new AIPS_Author_Topic_Logs_Repository();
$status_counts      = $topics_repository->get_status_counts($author_id);
$total_topics       = $status_counts['pending'] + $status_counts['approved'] + $status_counts['rejected'];
$posts_count        = $logs_repository->count_generated_posts_by_author($author_id);
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<!-- Breadcrumb -->
		<nav class="aips-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'ai-post-scheduler'); ?>">
			<a href="<?php echo esc_url($authors_page_url); ?>"><?php esc_html_e('Authors', 'ai-post-scheduler'); ?></a>
			<span class="aips-breadcrumb-sep" aria-hidden="true">&rsaquo;</span>
			<span><?php echo esc_html($author->name); ?></span>
		</nav>

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title">
						<?php
						/* translators: %s: author name */
						printf(esc_html__('Topics: %s', 'ai-post-scheduler'), esc_html($author->name));
						?>
					</h1>
					<p class="aips-page-description">
						<?php echo esc_html($author->field_niche); ?>
					</p>
				</div>
				<div class="aips-page-actions">
					<a href="<?php echo esc_url($authors_page_url); ?>" class="aips-btn aips-btn-secondary">
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e('Edit Author', 'ai-post-scheduler'); ?>
					</a>
					<button class="aips-btn aips-btn-primary aips-generate-topics-now" data-id="<?php echo esc_attr($author->id); ?>">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e('Generate Topics', 'ai-post-scheduler'); ?>
					</button>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'aips-generated-posts', 'author_id' => absint( $author->id ) ), admin_url( 'admin.php' ) ) ); ?>" class="aips-btn aips-btn-secondary">
						<span class="dashicons dashicons-admin-post"></span>
						<?php esc_html_e('View Generated Posts', 'ai-post-scheduler'); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- Author Stats -->
		<div class="aips-author-topics-stats">
			<div class="aips-stat-card">
				<span class="aips-stat-value" id="stat-total-count"><?php echo esc_html($total_topics); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Total Topics', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-pending">
				<span class="aips-stat-value" id="stat-pending-count"><?php echo esc_html($status_counts['pending']); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-approved">
				<span class="aips-stat-value" id="stat-approved-count"><?php echo esc_html($status_counts['approved']); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Approved', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-rejected">
				<span class="aips-stat-value" id="stat-rejected-count"><?php echo esc_html($status_counts['rejected']); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Rejected', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-generated">
				<span class="aips-stat-value"><?php echo esc_html($posts_count); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></span>
			</div>
		</div>

		<!-- Topics Panel -->
		<div class="aips-content-panel" id="aips-author-topics-panel">
			<!-- Tabs -->
			<div class="aips-topics-tabs aips-page-tabs">
				<button class="aips-tab-link active" data-tab="pending">
					<?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?>
					<span class="aips-tab-count" id="pending-count"><?php echo esc_html($status_counts['pending']); ?></span>
				</button>
				<button class="aips-tab-link" data-tab="approved">
					<?php esc_html_e('Approved', 'ai-post-scheduler'); ?>
					<span class="aips-tab-count" id="approved-count"><?php echo esc_html($status_counts['approved']); ?></span>
				</button>
				<button class="aips-tab-link" data-tab="rejected">
					<?php esc_html_e('Rejected', 'ai-post-scheduler'); ?>
					<span class="aips-tab-count" id="rejected-count"><?php echo esc_html($status_counts['rejected']); ?></span>
				</button>
				<button class="aips-tab-link" data-tab="feedback">
					<?php esc_html_e('Feedback', 'ai-post-scheduler'); ?>
				</button>
			</div>

			<!-- Filter Bar -->
			<div class="aips-filter-bar">
				<div class="aips-filter-left aips-btn-group aips-btn-group-inline">
					<select class="aips-bulk-action-select aips-form-select" style="width: auto;">
						<option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
						<option value="approve"><?php esc_html_e('Approve', 'ai-post-scheduler'); ?></option>
						<option value="reject"><?php esc_html_e('Reject', 'ai-post-scheduler'); ?></option>
						<option value="delete"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></option>
					</select>
					<button class="aips-btn aips-btn-sm aips-btn-secondary aips-bulk-action-execute"><?php esc_html_e('Execute', 'ai-post-scheduler'); ?></button>
				</div>
				<div class="aips-filter-right">
					<label class="screen-reader-text" for="aips-topic-search"><?php esc_html_e('Search Topics:', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-topic-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search topics...', 'ai-post-scheduler'); ?>">
					<button type="button" id="aips-topic-search-clear" class="aips-btn aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
				</div>
			</div>

			<div id="aips-topic-search-no-results" class="aips-empty-state" style="display: none; padding: 60px 20px;">
				<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
				<h3 class="aips-empty-state-title"><?php esc_html_e('No Topics Found', 'ai-post-scheduler'); ?></h3>
				<p class="aips-empty-state-description"><?php esc_html_e('No topics match your search criteria. Try a different search term.', 'ai-post-scheduler'); ?></p>
				<div class="aips-empty-state-actions">
					<button type="button" class="aips-btn aips-btn-primary" id="aips-clear-topic-search-btn">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>

			<!-- Topics Content -->
			<div class="aips-panel-body no-padding">
				<div id="aips-topics-content">
					<p><?php esc_html_e('Loading topics...', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>
		<!-- Table footer -->
		<div class="tablenav">
			<span class="aips-table-footer-count" id="aips-topics-result-count">
				<?php
				printf(
					esc_html(
						_n(
							'%s topic',
							'%s topics',
							$status_counts['pending'],
							'ai-post-scheduler'
						)
					),
					number_format_i18n( $status_counts['pending'] )
				);
				?>
			</span>
		</div>
	</div>
</div>

<!-- Topic Logs Modal -->
<div id="aips-topic-logs-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content aips-modal-large">
		<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		<h2 id="aips-topic-logs-modal-title"><?php esc_html_e('Topic History Log', 'ai-post-scheduler'); ?></h2>
		<div id="aips-topic-logs-content">
			<p><?php esc_html_e('Loading logs...', 'ai-post-scheduler'); ?></p>
		</div>
	</div>
</div>

<!-- Topic Posts Modal -->
<div id="aips-topic-posts-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content aips-modal-large">
		<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		<h2 id="aips-topic-posts-modal-title"><?php esc_html_e('Posts Generated from Topic', 'ai-post-scheduler'); ?></h2>
		<div id="aips-topic-posts-content">
			<p><?php esc_html_e('Loading posts...', 'ai-post-scheduler'); ?></p>
		</div>
	</div>
</div>

<!-- Feedback Modal -->
<div id="aips-feedback-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content">
		<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		<h2 id="aips-feedback-modal-title"><?php esc_html_e('Provide Feedback', 'ai-post-scheduler'); ?></h2>
		<form id="aips-feedback-form">
			<input type="hidden" id="feedback_topic_id" name="topic_id" value="">
			<input type="hidden" id="feedback_action" name="action_type" value="">

			<div class="form-group">
				<label for="feedback_reason_category"><?php esc_html_e('Feedback Category', 'ai-post-scheduler'); ?></label>
				<select id="feedback_reason_category" name="reason_category">
					<option value="other"><?php esc_html_e('Other', 'ai-post-scheduler'); ?></option>
					<option value="duplicate"><?php esc_html_e('Duplicate', 'ai-post-scheduler'); ?></option>
					<option value="tone"><?php esc_html_e('Tone', 'ai-post-scheduler'); ?></option>
					<option value="irrelevant"><?php esc_html_e('Irrelevant', 'ai-post-scheduler'); ?></option>
					<option value="policy"><?php esc_html_e('Policy', 'ai-post-scheduler'); ?></option>
				</select>
				<p class="description"><?php esc_html_e('Select a structured reason to improve future topic quality.', 'ai-post-scheduler'); ?></p>
			</div>

			<div class="form-group">
				<label for="feedback_reason"><?php esc_html_e('Reason (optional)', 'ai-post-scheduler'); ?></label>
				<textarea id="feedback_reason" name="reason" rows="4" placeholder="<?php esc_attr_e('Why are you approving/rejecting this topic?', 'ai-post-scheduler'); ?>"></textarea>
				<p class="description"><?php esc_html_e('Your feedback helps improve future topic generation', 'ai-post-scheduler'); ?></p>
			</div>

			<div class="form-actions">
				<button type="submit" class="button button-primary" id="feedback-submit-btn"><?php esc_html_e('Submit', 'ai-post-scheduler'); ?></button>
				<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			</div>
		</form>
	</div>
</div>


