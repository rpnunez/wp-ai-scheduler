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
	<div class="notice notice-error"><p>
		<?php esc_html_e('Invalid author ID.', 'ai-post-scheduler'); ?>
		<a href="<?php echo esc_url($authors_page_url); ?>"><?php esc_html_e('Back to Authors', 'ai-post-scheduler'); ?></a>
	</p></div>
	<?php
	return;
}

$authors_repository = new AIPS_Authors_Repository();
$author = $authors_repository->get_by_id($author_id);

if (!$author) {
	?>
	<div class="notice notice-error"><p>
		<?php esc_html_e('Author not found.', 'ai-post-scheduler'); ?>
		<a href="<?php echo esc_url($authors_page_url); ?>"><?php esc_html_e('Back to Authors', 'ai-post-scheduler'); ?></a>
	</p></div>
	<?php
	return;
}

$topics_repository  = new AIPS_Author_Topics_Repository();
$logs_repository    = new AIPS_Author_Topic_Logs_Repository();
$status_counts      = $topics_repository->get_status_counts($author_id);
$total_topics       = $status_counts['pending'] + $status_counts['approved'] + $status_counts['rejected'] + $status_counts['posts_generated'];
$posts_count        = $logs_repository->count_generated_posts_by_author($author_id);
?>

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
				<span class="aips-stat-value" id="stat-generated-count"><?php echo esc_html($posts_count); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card">
				<span class="aips-stat-value" id="stat-total-count"><?php echo esc_html($total_topics); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Total Topics', 'ai-post-scheduler'); ?></span>
			</div>
		</div>

		<!-- Topics Table -->
		<div id="aips-author-topics-panel">
			<?php
			$author_topics_list_table = new AIPS_Author_Topics_List_Table(array(
				'author_id' => $author_id,
			));
			$author_topics_list_table->prepare_items();
			$author_topics_list_table->display_page();
			?>
		</div>

<!-- Topic Logs Modal -->
<div id="aips-topic-logs-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title"><?php esc_html_e('Topic History Log', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body aips-modal-content-body">
			<p><?php esc_html_e('Loading logs...', 'ai-post-scheduler'); ?></p>
		</div>
	</div>
</div>

<!-- Topic Posts Modal -->
<div id="aips-topic-posts-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title"><?php esc_html_e('Posts Generated from Topic', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body aips-modal-content-body">
			<p><?php esc_html_e('Loading posts...', 'ai-post-scheduler'); ?></p>
		</div>
	</div>
</div>

<!-- Feedback Modal -->
<div id="aips-feedback-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title"><?php esc_html_e('Provide Feedback', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<form id="aips-feedback-form">
			<div class="aips-modal-body">
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
			</div>
			<div class="aips-modal-footer form-actions">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
				<button type="submit" class="aips-btn aips-btn-primary" id="feedback-submit-btn"><?php esc_html_e('Submit', 'ai-post-scheduler'); ?></button>
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
			<th class="check-column"><input type="checkbox" class="aips-select-all-topics" aria-label="<?php esc_attr_e('Select all topics', 'ai-post-scheduler'); ?>"></th>
			<th class="column-topic">{{topicDetails}}</th>
			<th class="column-date">{{generatedAtLabel}}</th>
			{{secondaryDateHeader}}
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
	<th class="check-column"><input type="checkbox" class="aips-topic-checkbox" value="{{id}}" aria-label="<?php esc_attr_e('Select topic', 'ai-post-scheduler'); ?>"></th>
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
	<td class="column-date"><div class="cell-meta">{{generatedAt}}</div></td>
	{{secondaryDateCell}}
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
	<div class="aips-row-action-group">
		<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-topic" data-id="{{id}}" title="{{editTitle}}">{{editLabel}}</button>
		<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="aips-author-topic-row-actions-{{id}}" title="{{moreActionsTitle}}">
			<span class="screen-reader-text">{{moreActionsLabel}}</span>
		</button>
	</div>
	<div id="aips-author-topic-row-actions-{{id}}" class="aips-row-action-menu" hidden>
		<button type="button" class="aips-row-action-item aips-approve-topic" data-id="{{id}}">
			<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
			<span>{{approveLabel}}</span>
		</button>
		<button type="button" class="aips-row-action-item aips-reject-topic" data-id="{{id}}">
			<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			<span>{{rejectLabel}}</span>
		</button>
	</div>
</div>
</script>

<script type="text/html" id="aips-tmpl-topic-actions-approved">
<div class="cell-actions">
	<div class="aips-row-action-group">
		<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-generate-post-now" data-id="{{id}}">{{generateLabel}}</button>
		<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="aips-author-topic-row-actions-{{id}}" title="{{moreActionsTitle}}">
			<span class="screen-reader-text">{{moreActionsLabel}}</span>
		</button>
	</div>
	<div id="aips-author-topic-row-actions-{{id}}" class="aips-row-action-menu" hidden>
		<button type="button" class="aips-row-action-item aips-edit-topic" data-id="{{id}}">
			<span class="dashicons dashicons-edit" aria-hidden="true"></span>
			<span>{{editLabel}}</span>
		</button>
	</div>
</div>
</script>

<script type="text/html" id="aips-tmpl-topic-actions-rejected">
<div class="cell-actions">
	<div class="aips-row-action-group">
		<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-topic" data-id="{{id}}">{{editLabel}}</button>
	</div>
</div>
</script>

<!-- Feedback Tab Templates -->
<script type="text/html" id="aips-tmpl-feedback-table">
<table class="aips-table aips-feedback-table">
	<thead>
		<tr>
			<th class="check-column"><input type="checkbox" class="aips-select-all-feedback" aria-label="<?php esc_attr_e('Select all feedback', 'ai-post-scheduler'); ?>"></th>
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
	<th class="check-column"><input type="checkbox" class="aips-feedback-checkbox" value="{{id}}" aria-label="<?php esc_attr_e('Select feedback', 'ai-post-scheduler'); ?>"></th>
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
<script type="text/html" id="aips-tmpl-topic-posts-list">
<div class="aips-topic-posts-list">
	{{items}}
</div>
</script>

<script type="text/html" id="aips-tmpl-topic-post-item">
<article class="aips-topic-post-item" data-post-id="{{postId}}">
	<div class="aips-topic-post-main">
		<h1 class="aips-topic-post-title">{{postTitle}}</h1>
		<p class="aips-topic-post-excerpt">{{postExcerpt}}</p>
		<div class="aips-topic-post-meta">
			<span class="aips-topic-post-meta-item"><strong>{{generatedLabel}}:</strong> {{dateGenerated}}</span>
			<span class="aips-topic-post-meta-item"><strong>{{publishedLabel}}:</strong> {{datePublished}}</span>
		</div>
		<div class="aips-topic-post-actions">
			{{actions}}
		</div>
	</div>
	<div class="aips-topic-post-media">
		{{featuredImageMarkup}}
	</div>
</article>
</script>

<script type="text/html" id="aips-tmpl-topic-post-image">
<img src="{{imageUrl}}" alt="{{imageAlt}}" class="aips-topic-post-image">
</script>

<script type="text/html" id="aips-tmpl-topic-post-image-placeholder">
<div class="aips-topic-post-image-placeholder">{{placeholderText}}</div>
</script>

<script type="text/html" id="aips-tmpl-topic-post-action-link">
<a href="{{url}}" class="aips-btn aips-btn-sm aips-btn-secondary" target="_blank" rel="noopener noreferrer">{{label}}</a>
</script>

<script type="text/html" id="aips-tmpl-topic-post-action-publish">
<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-publish-topic-post" data-post-id="{{postId}}">{{label}}</button>
</script>
