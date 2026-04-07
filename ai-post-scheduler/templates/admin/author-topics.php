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
$total_topics       = $status_counts['pending'] + $status_counts['approved'] + $status_counts['rejected'] + $status_counts['posts_generated'];
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
			<div class="aips-stat-card">
				<span class="aips-stat-value" id="stat-total-count"><?php echo esc_html($total_topics); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Total Topics', 'ai-post-scheduler'); ?></span>
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
				<button class="aips-tab-link" data-tab="posts_generated">
					<?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?>
					<span class="aips-tab-count" id="posts-generated-count"><?php echo esc_html($status_counts['posts_generated']); ?></span>
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
					<button type="button" id="aips-topic-search-clear" class="aips-btn aips-btn-sm aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
				</div>
			</div>

			<!-- Topics Content -->
			<div class="aips-panel-body no-padding">
				<div id="aips-topics-loading" class="aips-topics-loading">
					<div class="aips-topics-loading-inner">
						<div class="aips-topics-loading-icon-wrapper">
							<span class="dashicons dashicons-update aips-spin" aria-hidden="true"></span>
						</div>
						<p class="aips-topics-loading-text"><?php esc_html_e('Loading...', 'ai-post-scheduler'); ?></p>
						<ul class="aips-topics-loading-list" id="aips-topics-loading-list"></ul>
					</div>
				</div>
				<div id="aips-topics-content" style="display: none;"></div>
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
				<label id="feedback_reason_category_label" for="feedback_reason_category"><?php esc_html_e('Feedback Category', 'ai-post-scheduler'); ?></label>
				<select id="feedback_reason_category" name="reason_category">
					<option value="other"><?php esc_html_e('Other', 'ai-post-scheduler'); ?></option>
				</select>
				<p id="feedback_reason_category_description" class="description"><?php esc_html_e('Select a structured reason to improve future topic quality.', 'ai-post-scheduler'); ?></p>
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

<?php /* ------------------------------------------------------------------ */
/* HTML templates used by AIPS.Templates.renderRaw() in authors.js          */
/* (unescaped HTML; required for tokens like {{rows}} and {{actions}}).     */ ?>

<!-- Topics List Templates -->
<script type="text/html" id="aips-tmpl-topics-table">
<table class="aips-table aips-topics-table">
	<thead>
		<tr>
			<th class="check-column"><input type="checkbox" class="aips-select-all-topics"></th>
			<th class="column-topic">{{topicDetails}}</th>
			<th class="column-generated">{{generatedAtLabel}}</th>
			<th class="column-actions">{{actionsLabel}}</th>
		</tr>
	</thead>
	<tbody>
		{{rows}}
	</tbody>
</table>
</script>

<script type="text/html" id="aips-tmpl-topic-row">
<tr data-topic-id="{{id}}">
	<th class="check-column"><input type="checkbox" class="aips-topic-checkbox" value="{{id}}"></th>
	<td class="topic-title-cell column-topic">
		<div class="aips-topic-row">
			{{expandBtn}}
			<span class="topic-title">{{topicTitle}}</span>
			<span class="aips-topic-similarity-slot" data-topic-id="{{id}}"></span>
			{{postCountBadge}}
			{{duplicateBadge}}
			{{feedbackBadge}}
			<input type="text" class="topic-title-edit" style="display:none;" value="{{topicTitle}}">
		</div>
		{{detailContent}}
	</td>
	<td class="column-generated">{{generatedAt}}</td>
	<td class="topic-actions column-actions">
		{{actions}}
	</td>
</tr>
</script>

<script type="text/html" id="aips-tmpl-topic-detail-section">
<div class="aips-topic-detail-content" id="aips-topic-details-{{id}}" style="display:none;">
	{{content}}
</div>
</script>

<script type="text/html" id="aips-tmpl-topic-detail-item">
<div class="aips-detail-section"><strong>{{label}}:</strong> {{value}}</div>
</script>

<script type="text/html" id="aips-tmpl-topic-detail-feedback">
<div class="aips-detail-section aips-detail-feedback">
	<strong>{{label}}:</strong> <span class="aips-feedback-badge aips-feedback-badge-{{action}}">{{actionLabel}}</span>
	{{categoryBadge}} {{reason}} {{date}}
</div>
</script>

<script type="text/html" id="aips-tmpl-topic-detail-duplicate">
<div class="aips-detail-section aips-detail-duplicate">
	<strong>{{label}}:</strong> <em>{{match}}</em>
</div>
</script>

<script type="text/html" id="aips-tmpl-topic-actions-pending">
<div class="cell-actions">
	<button class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-topic" data-id="{{id}}">{{editLabel}}</button>
</div>
<div class="cell-actions" style="margin-top: 6px;">
	<button class="aips-btn aips-btn-sm aips-btn-secondary aips-approve-topic" data-id="{{id}}">{{approveLabel}}</button>
	<button class="aips-btn aips-btn-sm aips-btn-secondary aips-reject-topic" data-id="{{id}}">{{rejectLabel}}</button>
</div>
</script>

<script type="text/html" id="aips-tmpl-topic-actions-approved">
<div class="cell-actions">
	<button class="aips-btn aips-btn-sm aips-btn-secondary aips-generate-post-now" data-id="{{id}}">{{generateLabel}}</button>
	<button class="aips-btn aips-btn-sm aips-btn-ghost aips-edit-topic" data-id="{{id}}">{{editLabel}}</button>
</div>
</script>

<script type="text/html" id="aips-tmpl-topic-actions-rejected">
<div class="cell-actions">
	<button class="aips-btn aips-btn-sm aips-btn-ghost aips-edit-topic" data-id="{{id}}">{{editLabel}}</button>
</div>
</script>

<!-- Feedback Tab Templates -->
<script type="text/html" id="aips-tmpl-feedback-table">
<table class="aips-table aips-feedback-table">
	<thead>
		<tr>
			<th class="check-column"><input type="checkbox" class="aips-select-all-feedback"></th>
			<th class="column-topic">{{topicLabel}}</th>
			<th class="column-action">{{actionLabel}}</th>
			<th class="column-reason">{{reasonLabel}}</th>
			<th class="column-user">{{userLabel}}</th>
			<th class="column-date">{{dateLabel}}</th>
		</tr>
	</thead>
	<tbody>
		{{rows}}
	</tbody>
</table>
</script>

<script type="text/html" id="aips-tmpl-feedback-row">
<tr>
	<th class="check-column"><input type="checkbox" class="aips-feedback-checkbox" value="{{id}}"></th>
	<td>{{topicTitle}}</td>
	<td><span class="aips-status aips-status-{{action}}">{{action}}</span></td>
	<td>{{reason}}</td>
	<td>{{userName}}</td>
	<td>{{date}}</td>
</tr>
</script>

<!-- Topic Logs Modal Templates -->
<script type="text/html" id="aips-tmpl-topic-logs-table">
<table class="wp-list-table widefat fixed striped">
	<thead>
		<tr>
			<th>{{actionLabel}}</th>
			<th>{{userLabel}}</th>
			<th>{{dateLabel}}</th>
			<th>{{detailsLabel}}</th>
		</tr>
	</thead>
	<tbody>
		{{rows}}
	</tbody>
</table>
</script>

<script type="text/html" id="aips-tmpl-topic-log-row">
<tr>
	<td><span class="aips-status aips-status-{{action}}">{{action}}</span></td>
	<td>{{userName}}</td>
	<td>{{date}}</td>
	<td>{{notes}}</td>
</tr>
</script>

<!-- Topic Posts Modal Templates -->
<script type="text/html" id="aips-tmpl-topic-posts-table">
<table class="wp-list-table widefat fixed striped">
	<thead>
		<tr>
			<th>{{idLabel}}</th>
			<th>{{titleLabel}}</th>
			<th>{{generatedLabel}}</th>
			<th>{{publishedLabel}}</th>
			<th>{{actionsLabel}}</th>
		</tr>
	</thead>
	<tbody>
		{{rows}}
	</tbody>
</table>
</script>

<script type="text/html" id="aips-tmpl-topic-post-row">
<tr>
	<td>{{postId}}</td>
	<td>{{postTitle}}</td>
	<td>{{dateGenerated}}</td>
	<td>{{datePublished}}</td>
	<td>{{actions}}</td>
</tr>
</script>


