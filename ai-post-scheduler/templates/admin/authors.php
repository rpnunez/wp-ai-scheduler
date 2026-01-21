<?php
if (!defined('ABSPATH')) {
	exit;
}

// Get authors
$authors_repository = new AIPS_Authors_Repository();
$authors = $authors_repository->get_all();
?>
<div class="wrap aips-wrap">
	<h1>
		<?php esc_html_e('Authors', 'ai-post-scheduler'); ?>
		<button class="page-title-action aips-add-author-btn"><?php esc_html_e('Add New Author', 'ai-post-scheduler'); ?></button>
	</h1>
	
	<div class="aips-authors-container">
		<div class="aips-authors-list">
			<?php if (!empty($authors)): ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
						<th class="column-field"><?php esc_html_e('Field/Niche', 'ai-post-scheduler'); ?></th>
						<th class="column-topics"><?php esc_html_e('Topics', 'ai-post-scheduler'); ?></th>
						<th class="column-posts"><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></th>
						<th class="column-active"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></th>
						<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$topics_repository = new AIPS_Author_Topics_Repository();
					$logs_repository = new AIPS_Author_Topic_Logs_Repository();

					foreach ($authors as $author):
						$status_counts = $topics_repository->get_status_counts($author->id);
						$total_topics = $status_counts['pending'] + $status_counts['approved'] + $status_counts['rejected'];
						$posts = $logs_repository->get_generated_posts_by_author($author->id);
						$posts_count = count($posts);
					?>
					<tr data-author-id="<?php echo esc_attr($author->id); ?>">
						<td class="column-name">
							<strong><?php echo esc_html($author->name); ?></strong>
						</td>
						<td class="column-field">
							<?php echo esc_html($author->field_niche); ?>
						</td>
						<td class="column-topics">
							<div style="font-size: 0.9em;">
								<strong><?php echo esc_html($total_topics); ?></strong> total<br>
								<span style="color: #d63638;"><?php echo esc_html($status_counts['pending']); ?> pending</span> | 
								<span style="color: #00a32a;"><?php echo esc_html($status_counts['approved']); ?> approved</span> | 
								<span style="color: #999;"><?php echo esc_html($status_counts['rejected']); ?> rejected</span>
							</div>
						</td>
						<td class="column-posts">
							<strong><?php echo esc_html($posts_count); ?></strong>
						</td>
						<td class="column-active">
							<span class="aips-status aips-status-<?php echo $author->is_active ? 'active' : 'inactive'; ?>">
								<?php echo $author->is_active ? esc_html__('Yes', 'ai-post-scheduler') : esc_html__('No', 'ai-post-scheduler'); ?>
							</span>
						</td>
						<td class="column-actions">
							<button class="button aips-view-author" data-id="<?php echo esc_attr($author->id); ?>">
								<?php esc_html_e('View Topics', 'ai-post-scheduler'); ?>
							</button>
							<button class="button aips-edit-author" data-id="<?php echo esc_attr($author->id); ?>">
								<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
							</button>
							<button class="button aips-generate-topics-now" data-id="<?php echo esc_attr($author->id); ?>">
								<?php esc_html_e('Generate Topics Now', 'ai-post-scheduler'); ?>
							</button>
							<button class="button button-link-delete aips-delete-author" data-id="<?php echo esc_attr($author->id); ?>">
								<?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php else: ?>
			<div class="aips-empty-state">
				<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
				<h3><?php esc_html_e('No Authors Yet', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('Create your first author to start generating topically diverse blog posts.', 'ai-post-scheduler'); ?></p>
				<button class="button button-primary aips-add-author-btn"><?php esc_html_e('Add New Author', 'ai-post-scheduler'); ?></button>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Author Edit/Create Modal (placeholder for future implementation) -->
<div id="aips-author-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content">
		<span class="aips-modal-close">&times;</span>
		<h2 id="aips-author-modal-title"><?php esc_html_e('Add New Author', 'ai-post-scheduler'); ?></h2>
		<form id="aips-author-form">
			<input type="hidden" id="author_id" name="author_id" value="">
			
			<div class="form-group">
				<label for="author_name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?> *</label>
				<input type="text" id="author_name" name="name" required>
			</div>
			
			<div class="form-group">
				<label for="author_field_niche"><?php esc_html_e('Field/Niche', 'ai-post-scheduler'); ?> *</label>
				<input type="text" id="author_field_niche" name="field_niche" placeholder="e.g., PHP Programming" required>
				<p class="description"><?php esc_html_e('The main topic or field this author covers (e.g., "PHP Programming", "Web Development", etc.)', 'ai-post-scheduler'); ?></p>
			</div>
			
			<div class="form-group">
				<label for="author_description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></label>
				<textarea id="author_description" name="description" rows="3"></textarea>
			</div>
			
			<div class="form-group">
				<label for="topic_generation_quantity"><?php esc_html_e('Number of Topics to Generate', 'ai-post-scheduler'); ?></label>
				<input type="number" id="topic_generation_quantity" name="topic_generation_quantity" value="5" min="1" max="20">
			</div>
			
			<div class="form-group">
				<label for="topic_generation_frequency"><?php esc_html_e('Topic Generation Frequency', 'ai-post-scheduler'); ?></label>
				<select id="topic_generation_frequency" name="topic_generation_frequency">
					<option value="daily"><?php esc_html_e('Daily', 'ai-post-scheduler'); ?></option>
					<option value="weekly" selected><?php esc_html_e('Weekly', 'ai-post-scheduler'); ?></option>
					<option value="biweekly"><?php esc_html_e('Bi-weekly', 'ai-post-scheduler'); ?></option>
					<option value="monthly"><?php esc_html_e('Monthly', 'ai-post-scheduler'); ?></option>
				</select>
			</div>
			
			<div class="form-group">
				<label for="post_generation_frequency"><?php esc_html_e('Post Generation Frequency', 'ai-post-scheduler'); ?></label>
				<select id="post_generation_frequency" name="post_generation_frequency">
					<option value="hourly"><?php esc_html_e('Hourly', 'ai-post-scheduler'); ?></option>
					<option value="daily" selected><?php esc_html_e('Daily', 'ai-post-scheduler'); ?></option>
					<option value="weekly"><?php esc_html_e('Weekly', 'ai-post-scheduler'); ?></option>
				</select>
			</div>
			
			<div class="form-group">
				<label>
					<input type="checkbox" id="is_active" name="is_active" checked>
					<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
				</label>
			</div>
			
			<div class="form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e('Save Author', 'ai-post-scheduler'); ?></button>
				<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			</div>
		</form>
	</div>
</div>

<!-- Topics View Modal (placeholder for viewing author topics) -->
<div id="aips-topics-modal" class="aips-modal" style="display: none;">
	<div class="aips-modal-content aips-modal-large">
		<span class="aips-modal-close">&times;</span>
		<h2 id="aips-topics-modal-title"><?php esc_html_e('Author Topics', 'ai-post-scheduler'); ?></h2>
		
		<div class="aips-topics-tabs">
			<button class="aips-tab-link active" data-tab="pending"><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?> (<span id="pending-count">0</span>)</button>
			<button class="aips-tab-link" data-tab="approved"><?php esc_html_e('Approved', 'ai-post-scheduler'); ?> (<span id="approved-count">0</span>)</button>
			<button class="aips-tab-link" data-tab="rejected"><?php esc_html_e('Rejected', 'ai-post-scheduler'); ?> (<span id="rejected-count">0</span>)</button>
		</div>
		
		<div id="aips-topics-content">
			<p><?php esc_html_e('Loading topics...', 'ai-post-scheduler'); ?></p>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Add Author Button
	$('.aips-add-author-btn').on('click', function() {
		$('#aips-author-modal-title').text('<?php esc_html_e('Add New Author', 'ai-post-scheduler'); ?>');
		$('#aips-author-form')[0].reset();
		$('#author_id').val('');
		$('#aips-author-modal').fadeIn();
	});
	
	// Edit Author Button
	$('.aips-edit-author').on('click', function() {
		var authorId = $(this).data('id');
		// TODO: Load author data via AJAX and populate form
		$('#aips-author-modal-title').text('<?php esc_html_e('Edit Author', 'ai-post-scheduler'); ?>');
		$('#author_id').val(authorId);
		$('#aips-author-modal').fadeIn();
	});
	
	// View Topics Button
	$('.aips-view-author').on('click', function() {
		var authorId = $(this).data('id');
		// TODO: Load topics via AJAX
		$('#aips-topics-modal').fadeIn();
	});
	
	// Generate Topics Now Button
	$('.aips-generate-topics-now').on('click', function() {
		var authorId = $(this).data('id');
		if (confirm('<?php esc_html_e('Generate topics for this author now?', 'ai-post-scheduler'); ?>')) {
			// TODO: Trigger AJAX request to generate topics
			alert('<?php esc_html_e('Topic generation feature will be implemented in next iteration', 'ai-post-scheduler'); ?>');
		}
	});
	
	// Delete Author Button
	$('.aips-delete-author').on('click', function() {
		var authorId = $(this).data('id');
		if (confirm('<?php esc_html_e('Are you sure you want to delete this author? This will also delete all associated topics and logs.', 'ai-post-scheduler'); ?>')) {
			// TODO: Trigger AJAX request to delete author
			alert('<?php esc_html_e('Delete feature will be implemented in next iteration', 'ai-post-scheduler'); ?>');
		}
	});
	
	// Close Modal
	$('.aips-modal-close').on('click', function() {
		$(this).closest('.aips-modal').fadeOut();
	});
	
	// Submit Author Form
	$('#aips-author-form').on('submit', function(e) {
		e.preventDefault();
		// TODO: Submit form via AJAX
		alert('<?php esc_html_e('Save feature will be implemented in next iteration', 'ai-post-scheduler'); ?>');
	});
});
</script>

<style>
.aips-modal {
	position: fixed;
	z-index: 9999;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0,0,0,0.5);
}

.aips-modal-content {
	background-color: #fff;
	margin: 5% auto;
	padding: 20px;
	border: 1px solid #888;
	width: 80%;
	max-width: 600px;
	border-radius: 4px;
	position: relative;
}

.aips-modal-content.aips-modal-large {
	max-width: 900px;
}

.aips-modal-close {
	color: #aaa;
	float: right;
	font-size: 28px;
	font-weight: bold;
	cursor: pointer;
	line-height: 20px;
}

.aips-modal-close:hover,
.aips-modal-close:focus {
	color: #000;
}

.form-group {
	margin-bottom: 20px;
}

.form-group label {
	display: block;
	margin-bottom: 5px;
	font-weight: 600;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
	width: 100%;
	padding: 8px;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.form-group .description {
	margin-top: 5px;
	font-size: 12px;
	color: #666;
}

.form-actions {
	margin-top: 20px;
	text-align: right;
}

.aips-topics-tabs {
	margin-bottom: 20px;
	border-bottom: 1px solid #ddd;
}

.aips-tab-link {
	background: none;
	border: none;
	padding: 10px 20px;
	cursor: pointer;
	border-bottom: 2px solid transparent;
	margin-right: 5px;
}

.aips-tab-link.active {
	border-bottom-color: #2271b1;
	font-weight: 600;
}

.aips-empty-state {
	text-align: center;
	padding: 60px 20px;
	color: #666;
}

.aips-empty-state .dashicons {
	font-size: 80px;
	width: 80px;
	height: 80px;
	color: #ccc;
}

.aips-empty-state h3 {
	margin-top: 20px;
	font-size: 18px;
}

.aips-status {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
}

.aips-status-active {
	background-color: #d7ffd9;
	color: #00a32a;
}

.aips-status-inactive {
	background-color: #f0f0f1;
	color: #666;
}
</style>
