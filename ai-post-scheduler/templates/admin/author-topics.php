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
$authors_page_url = admin_url('admin.php?page=aips-authors');

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
						<span class="dashicons dashicons-arrow-left-alt"></span>
						<?php esc_html_e('Back to Authors', 'ai-post-scheduler'); ?>
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
				<span class="aips-stat-value"><?php echo esc_html($total_topics); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Total Topics', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-pending">
				<span class="aips-stat-value"><?php echo esc_html($status_counts['pending']); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-approved">
				<span class="aips-stat-value"><?php echo esc_html($status_counts['approved']); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Approved', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-rejected">
				<span class="aips-stat-value"><?php echo esc_html($status_counts['rejected']); ?></span>
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

			<!-- Bulk Actions (top) -->
			<div class="aips-panel-body" style="padding-bottom: 0;">
				<div class="aips-bulk-actions">
					<select class="aips-bulk-action-select">
						<option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
						<option value="approve"><?php esc_html_e('Approve', 'ai-post-scheduler'); ?></option>
						<option value="reject"><?php esc_html_e('Reject', 'ai-post-scheduler'); ?></option>
						<option value="delete"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></option>
					</select>
					<button class="button aips-bulk-action-execute"><?php esc_html_e('Execute', 'ai-post-scheduler'); ?></button>
				</div>
			</div>

			<!-- Topics Content -->
			<div class="aips-panel-body no-padding">
				<div id="aips-topics-content" style="padding: 0 20px 20px;">
					<p><?php esc_html_e('Loading topics...', 'ai-post-scheduler'); ?></p>
				</div>

				<!-- Bulk Actions (bottom) -->
				<div style="padding: 0 20px 20px;">
					<div class="aips-bulk-actions">
						<select class="aips-bulk-action-select">
							<option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
							<option value="approve"><?php esc_html_e('Approve', 'ai-post-scheduler'); ?></option>
							<option value="reject"><?php esc_html_e('Reject', 'ai-post-scheduler'); ?></option>
							<option value="delete"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></option>
						</select>
						<button class="button aips-bulk-action-execute"><?php esc_html_e('Execute', 'ai-post-scheduler'); ?></button>
					</div>
				</div>
			</div>
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
