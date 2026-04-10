<?php
/**
 * Taxonomy Admin Template
 *
 * Displays interface for generating and managing AI-generated taxonomy (categories and tags)
 * based on existing posts.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.3
 */

if (!defined('ABSPATH')) {
	exit;
}

$repository = new AIPS_Taxonomy_Repository();
$status_counts = $repository->get_status_counts();
$total_items = $status_counts['categories']['pending'] + $status_counts['categories']['approved'] + $status_counts['categories']['rejected'] +
	$status_counts['tags']['pending'] + $status_counts['tags']['approved'] + $status_counts['tags']['rejected'];
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Taxonomy', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description">
						<?php esc_html_e('Generate and manage AI-powered categories and tags based on your existing posts', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<div class="aips-page-actions">
					<button class="aips-btn aips-btn-primary aips-generate-taxonomy" id="aips-open-generate-modal">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e('Generate Taxonomy', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Taxonomy Stats -->
		<div class="aips-author-topics-stats">
			<div class="aips-stat-card aips-stat-pending">
				<span class="aips-stat-value" id="stat-pending-count"><?php echo esc_html($status_counts['categories']['pending'] + $status_counts['tags']['pending']); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-approved">
				<span class="aips-stat-value" id="stat-approved-count"><?php echo esc_html($status_counts['categories']['approved'] + $status_counts['tags']['approved']); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Approved', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-rejected">
				<span class="aips-stat-value" id="stat-rejected-count"><?php echo esc_html($status_counts['categories']['rejected'] + $status_counts['tags']['rejected']); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Rejected', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card">
				<span class="aips-stat-value" id="stat-total-count"><?php echo esc_html($total_items); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Total Items', 'ai-post-scheduler'); ?></span>
			</div>
		</div>

		<!-- Taxonomy Panel -->
		<div class="aips-content-panel" id="aips-taxonomy-panel">
			<!-- Tabs -->
			<div class="aips-topics-tabs aips-page-tabs">
				<button class="aips-tab-link active" data-tab="categories">
					<?php esc_html_e('Categories', 'ai-post-scheduler'); ?>
					<span class="aips-tab-count" id="categories-count"><?php echo esc_html($status_counts['categories']['pending'] + $status_counts['categories']['approved'] + $status_counts['categories']['rejected']); ?></span>
				</button>
				<button class="aips-tab-link" data-tab="tags">
					<?php esc_html_e('Tags', 'ai-post-scheduler'); ?>
					<span class="aips-tab-count" id="tags-count"><?php echo esc_html($status_counts['tags']['pending'] + $status_counts['tags']['approved'] + $status_counts['tags']['rejected']); ?></span>
				</button>
			</div>

			<!-- Filter Bar -->
			<div class="aips-filter-bar">
				<div class="aips-filter-left aips-btn-group aips-btn-group-inline">
					<select class="aips-bulk-action-select aips-form-select" style="width: auto;">
						<option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
						<option value="approve"><?php esc_html_e('Approve', 'ai-post-scheduler'); ?></option>
						<option value="reject"><?php esc_html_e('Reject', 'ai-post-scheduler'); ?></option>
						<option value="generate_terms"><?php esc_html_e('Generate Terms', 'ai-post-scheduler'); ?></option>
						<option value="delete"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></option>
					</select>
					<button class="aips-btn aips-btn-sm aips-btn-secondary aips-bulk-action-execute"><?php esc_html_e('Execute', 'ai-post-scheduler'); ?></button>
				</div>
				<div class="aips-filter-right">
					<label class="screen-reader-text" for="aips-taxonomy-search"><?php esc_html_e('Search Taxonomy:', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-taxonomy-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search...', 'ai-post-scheduler'); ?>">
					<button type="button" id="aips-taxonomy-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
				</div>
			</div>

			<!-- Taxonomy Content -->
			<div class="aips-panel-body no-padding">
				<div id="aips-taxonomy-loading" class="aips-topics-loading">
					<div class="aips-topics-loading-inner">
						<div class="aips-topics-loading-icon-wrapper">
							<span class="dashicons dashicons-update aips-spin" aria-hidden="true"></span>
						</div>
						<p class="aips-topics-loading-text"><?php esc_html_e('Loading...', 'ai-post-scheduler'); ?></p>
					</div>
				</div>
				<div id="aips-taxonomy-content" style="display: none;"></div>
			</div>
		</div>
		<!-- Table footer -->
		<div class="tablenav">
			<span class="aips-table-footer-count" id="aips-taxonomy-result-count">
				<?php
				printf(
					esc_html(
						_n(
							'%s item',
							'%s items',
							$total_items,
							'ai-post-scheduler'
						)
					),
					number_format_i18n( $total_items )
				);
				?>
			</span>
		</div>
	</div>
</div>

<!-- Generate Taxonomy Modal -->
<div id="aips-generate-taxonomy-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content aips-modal-large">
		<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		<h2><?php esc_html_e('Generate Taxonomy', 'ai-post-scheduler'); ?></h2>
		<form id="aips-generate-taxonomy-form">
			<div class="form-group">
				<label for="taxonomy_type"><?php esc_html_e('Taxonomy Type', 'ai-post-scheduler'); ?></label>
				<select id="taxonomy_type" name="taxonomy_type" class="aips-form-select" required>
					<option value=""><?php esc_html_e('Select Type', 'ai-post-scheduler'); ?></option>
					<option value="category"><?php esc_html_e('Categories', 'ai-post-scheduler'); ?></option>
					<option value="post_tag"><?php esc_html_e('Tags', 'ai-post-scheduler'); ?></option>
				</select>
			</div>

			<div class="form-group">
				<label for="generation_prompt"><?php esc_html_e('Generation Prompt (optional)', 'ai-post-scheduler'); ?></label>
				<textarea id="generation_prompt" name="generation_prompt" rows="3" class="aips-form-input" placeholder="<?php esc_attr_e('Additional instructions for AI generation...', 'ai-post-scheduler'); ?>"></textarea>
				<p class="description"><?php esc_html_e('Optional: Provide additional context or instructions for the AI.', 'ai-post-scheduler'); ?></p>
			</div>

			<div class="form-group">
				<label for="base_posts"><?php esc_html_e('Base Posts', 'ai-post-scheduler'); ?></label>
				<input type="text" id="base_posts" name="base_posts" class="aips-form-input" placeholder="<?php esc_attr_e('Search and select posts...', 'ai-post-scheduler'); ?>">
				<p class="description"><?php esc_html_e('Search for posts to base the taxonomy generation on.', 'ai-post-scheduler'); ?></p>
				<div id="base-post-search-results" style="margin-top: 10px;"></div>
				<div id="selected-posts-container" style="margin-top: 10px;"></div>
			</div>

			<div class="form-actions">
				<button type="submit" class="button button-primary" id="generate-taxonomy-submit-btn"><?php esc_html_e('Generate', 'ai-post-scheduler'); ?></button>
				<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			</div>
		</form>
	</div>
</div>

<?php /* ------------------------------------------------------------------ */
/* HTML templates used by AIPS.Templates.renderRaw() in taxonomy.js        */
/* (unescaped HTML; required for tokens like {{rows}} and {{actions}}).   */ ?>

<!-- Taxonomy List Templates -->
<script type="text/html" id="aips-tmpl-taxonomy-table">
<table class="aips-table aips-taxonomy-table">
	<thead>
		<tr>
			<th class="check-column"><input type="checkbox" class="aips-select-all-taxonomy" aria-label="{{selectAllLabel}}"></th>
			<th class="column-name">{{nameLabel}}</th>
			<th class="column-status">{{statusLabel}}</th>
			<th class="column-generated">{{generatedAtLabel}}</th>
			<th class="column-actions">{{actionsLabel}}</th>
		</tr>
	</thead>
	<tbody>
		{{rows}}
	</tbody>
</table>
</script>

<script type="text/html" id="aips-tmpl-taxonomy-row">
<tr data-taxonomy-id="{{id}}" data-taxonomy-type="{{taxonomy_type}}">
	<th class="check-column"><input type="checkbox" class="aips-taxonomy-checkbox" value="{{id}}"></th>
	<td class="column-name">
		<span class="taxonomy-name">{{name}}</span>
	</td>
	<td class="column-status">
		<span class="aips-status aips-status-{{status}}">{{status_label}}</span>
	</td>
	<td class="column-generated">{{generated_at}}</td>
	<td class="taxonomy-actions column-actions">
		{{actions}}
	</td>
</tr>
</script>

<script type="text/html" id="aips-tmpl-taxonomy-actions-pending">
<div class="cell-actions">
	<button class="aips-btn aips-btn-sm aips-btn-secondary aips-approve-taxonomy" data-id="{{id}}">{{approveLabel}}</button>
	<button class="aips-btn aips-btn-sm aips-btn-secondary aips-reject-taxonomy" data-id="{{id}}">{{rejectLabel}}</button>
</div>
</script>

<script type="text/html" id="aips-tmpl-taxonomy-actions-approved">
<div class="cell-actions">
	{{createControl}}
	<button class="aips-btn aips-btn-sm aips-btn-ghost aips-btn-icon aips-delete-taxonomy" data-id="{{id}}" aria-label="{{deleteLabel}}">
		<span class="dashicons dashicons-trash" aria-hidden="true"></span>
		<span class="screen-reader-text">{{deleteLabel}}</span>
	</button>
</div>
</script>

<script type="text/html" id="aips-tmpl-taxonomy-actions-rejected">
<div class="cell-actions">
	<button class="aips-btn aips-btn-sm aips-btn-ghost aips-delete-taxonomy" data-id="{{id}}">{{deleteLabel}}</button>
</div>
</script>

<script type="text/html" id="aips-tmpl-selected-post">
<div class="aips-selected-post" data-post-id="{{id}}">
	<span>{{title}}</span>
	<button type="button" class="aips-remove-post" data-post-id="{{id}}">&times;</button>
</div>
</script>
