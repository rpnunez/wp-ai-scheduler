<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get authors - only instantiate repository when needed
$authors_repository = null;
$topics_repository = null;
$logs_repository = null;
$structures_repository = null;
$authors = array();
$article_structures = array();

if (isset($_GET['page']) && $_GET['page'] === 'aips-authors') {
    $authors_repository = new AIPS_Authors_Repository();
    $authors = $authors_repository->get_all();

    if (!empty($authors)) {
        $topics_repository = new AIPS_Author_Topics_Repository();
        $logs_repository = new AIPS_Author_Topic_Logs_Repository();
    }

    // Load article structures for the dropdown
    $structures_repository = new AIPS_Article_Structure_Repository();
    $article_structures = $structures_repository->get_all(true); // Get active structures only
}
?>
<div class="wrap aips-wrap">
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('Authors', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Manage AI author profiles, generate topics, and create authentic content from different perspectives.', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="aips-page-actions">
                    <button class="aips-btn aips-btn-primary aips-add-author-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Add Author', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Add tabs for Authors List and Generation Queue -->
        <div class="aips-authors-tabs" style="margin-bottom: 20px;">
            <button class="aips-authors-tab-link active" data-tab="authors-list"><?php esc_html_e('Authors List', 'ai-post-scheduler'); ?></button>
            <button class="aips-authors-tab-link" data-tab="generation-queue"><?php esc_html_e('Generation Queue', 'ai-post-scheduler'); ?></button>
        </div>

        <!-- Authors List Tab Content -->
        <div id="authors-list-tab" class="aips-authors-tab-content active">
            <?php if (!empty($authors)): ?>
            <div class="aips-content-panel">
                <!-- Filter Bar -->
                <div class="aips-filter-bar">
                    <label class="screen-reader-text" for="aips-author-search"><?php esc_html_e('Search Authors:', 'ai-post-scheduler'); ?></label>
                    <input type="search" id="aips-author-search" class="aips-form-input" style="max-width: 300px;" placeholder="<?php esc_attr_e('Search authors...', 'ai-post-scheduler'); ?>">
                    <button type="button" id="aips-author-search-clear" class="aips-btn aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
                </div>

                <!-- Authors Table -->
                <div class="aips-panel-body no-padding">
                    <table class="aips-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Field/Niche', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Topics', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($authors as $author):
                                $status_counts = $topics_repository->get_status_counts($author->id);
                                $total_topics = $status_counts['pending'] + $status_counts['approved'] + $status_counts['rejected'];
                                $posts = $logs_repository->get_generated_posts_by_author($author->id);
                                $posts_count = count($posts);
                            ?>
                                <tr data-author-id="<?php echo esc_attr($author->id); ?>">
                                    <td>
                                        <div class="cell-primary"><?php echo esc_html($author->name); ?></div>
                                    </td>
                                    <td>
                                        <?php echo esc_html($author->field_niche); ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <div>
                                                <strong style="font-size: 14px;"><?php echo esc_html($total_topics); ?></strong>
                                                <span class="cell-meta"><?php esc_html_e('total', 'ai-post-scheduler'); ?></span>
                                            </div>
                                            <div class="cell-meta" style="font-size: 11px;">
                                                <span style="color: #d63638;"><?php echo esc_html($status_counts['pending']); ?> pending</span> |
                                                <span style="color: #00a32a;"><?php echo esc_html($status_counts['approved']); ?> approved</span> |
                                                <span style="color: #999;"><?php echo esc_html($status_counts['rejected']); ?> rejected</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="font-size: 14px;"><?php echo esc_html($posts_count); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($author->is_active): ?>
                                        <span class="aips-badge aips-badge-success">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php esc_html_e('Active', 'ai-post-scheduler'); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="aips-badge aips-badge-neutral">
                                            <span class="dashicons dashicons-minus"></span>
                                            <?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="cell-actions">
                                            <button class="aips-btn aips-btn-sm aips-btn-secondary aips-view-author" data-id="<?php echo esc_attr($author->id); ?>">
                                                <span class="dashicons dashicons-visibility"></span>
                                                <?php esc_html_e('View Topics', 'ai-post-scheduler'); ?>
                                            </button>
                                            <button class="aips-btn aips-btn-sm aips-btn-ghost aips-edit-author" data-id="<?php echo esc_attr($author->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                            <button class="aips-btn aips-btn-sm aips-btn-ghost aips-generate-topics-now" data-id="<?php echo esc_attr($author->id); ?>" title="<?php esc_attr_e('Generate Topics', 'ai-post-scheduler'); ?>">
                                                <span class="dashicons dashicons-update"></span>
                                            </button>
                                            <button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-author" data-id="<?php echo esc_attr($author->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- No Search Results State -->
                    <div id="aips-author-search-no-results" class="aips-empty-state" style="display: none; padding: 60px 20px;">
                        <div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
                        <h3 class="aips-empty-state-title"><?php esc_html_e('No Authors Found', 'ai-post-scheduler'); ?></h3>
                        <p class="aips-empty-state-description"><?php esc_html_e('No authors match your search criteria. Try a different search term.', 'ai-post-scheduler'); ?></p>
                        <div class="aips-empty-state-actions">
                            <button type="button" class="aips-btn aips-btn-primary aips-clear-author-search-btn">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="aips-content-panel">
                <div class="aips-panel-body">
                    <div class="aips-empty-state">
                        <div class="dashicons dashicons-admin-users aips-empty-state-icon" aria-hidden="true"></div>
                        <h3 class="aips-empty-state-title"><?php esc_html_e('No Authors Yet', 'ai-post-scheduler'); ?></h3>
                        <p class="aips-empty-state-description"><?php esc_html_e('Create your first author to start generating topically diverse blog posts.', 'ai-post-scheduler'); ?></p>
                        <div class="aips-empty-state-actions">
                            <button class="aips-btn aips-btn-primary aips-add-author-btn">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php esc_html_e('Add Author', 'ai-post-scheduler'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Generation Queue Tab Content -->
        <div id="generation-queue-tab" class="aips-authors-tab-content" style="display: none;">
            <div class="aips-content-panel">
                <div class="aips-panel-body">
                    <p class="description" style="margin-bottom: 20px;">
                        <?php esc_html_e('This queue shows all approved topics across all authors, ready for post generation. Topics are processed in the order they were approved.', 'ai-post-scheduler'); ?>
                    </p>
                    
                    <!-- Bulk Actions -->
                    <div class="aips-bulk-actions" style="margin-bottom: 15px;">
                        <select id="aips-queue-bulk-action-select" class="aips-form-select aips-queue-bulk-action-select">
                            <option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
                    <option value="generate_now"><?php esc_html_e('Generate Now', 'ai-post-scheduler'); ?></option>
                </select>
                <button class="button aips-queue-bulk-action-execute"><?php esc_html_e('Execute', 'ai-post-scheduler'); ?></button>
            </div>

            <!-- Queue Topics List -->
            <div id="aips-queue-topics-list">
                <p><?php esc_html_e('Loading queue...', 'ai-post-scheduler'); ?></p>
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

<!-- Author Edit/Create Modal -->
<div id="aips-author-modal" class="aips-modal" style="display: none;">
    <div class="aips-modal-content">
        <button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
        <h2 id="aips-author-modal-title"><?php esc_html_e('Add New Author', 'ai-post-scheduler'); ?></h2>
        <form id="aips-author-form">
            <input type="hidden" id="author_id" name="author_id" value="">

            <div class="form-group">
                <label for="author_name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?> *</label>
                <input type="text" id="author_name" name="name" required>
            </div>

            <div class="form-group">
                <label for="author_field_niche"><?php esc_html_e('Field/Niche', 'ai-post-scheduler'); ?> *</label>
                <input type="text" id="author_field_niche" name="field_niche" placeholder="<?php esc_attr_e('e.g., PHP Programming', 'ai-post-scheduler'); ?>" required>
                <p class="description"><?php esc_html_e('The main topic or field this author covers', 'ai-post-scheduler'); ?></p>
            </div>

            <div class="form-group">
                <label for="author_keywords"><?php esc_html_e('Keywords', 'ai-post-scheduler'); ?></label>
                <input type="text" id="author_keywords" name="keywords" placeholder="<?php esc_attr_e('e.g., Laravel, Symfony, Composer, PSR', 'ai-post-scheduler'); ?>">
                <p class="description"><?php esc_html_e('Comma-separated keywords to focus on when generating topics', 'ai-post-scheduler'); ?></p>
            </div>

            <div class="form-group">
                <label for="author_details"><?php esc_html_e('Details', 'ai-post-scheduler'); ?></label>
                <textarea id="author_details" name="details" rows="4" placeholder="<?php esc_attr_e('Additional context or instructions for topic generation...', 'ai-post-scheduler'); ?>"></textarea>
                <p class="description"><?php esc_html_e('Additional context that will be included when generating topics', 'ai-post-scheduler'); ?></p>
            </div>

            <div class="form-group">
                <label for="author_description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></label>
                <textarea id="author_description" name="description" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label for="article_structure_id"><?php esc_html_e('Article Structure', 'ai-post-scheduler'); ?></label>
                <select id="article_structure_id" name="article_structure_id">
                    <option value=""><?php esc_html_e('None (use default)', 'ai-post-scheduler'); ?></option>
                    <?php foreach ($article_structures as $structure): ?>
                        <option value="<?php echo esc_attr($structure->id); ?>">
                            <?php echo esc_html($structure->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Optional: Select a specific article structure for posts generated from this author', 'ai-post-scheduler'); ?></p>
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

<!-- Topics View Modal -->
<div id="aips-topics-modal" class="aips-modal" style="display: none;">
    <div class="aips-modal-content aips-modal-large">
        <button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
        <h2 id="aips-topics-modal-title"><?php esc_html_e('Author Topics', 'ai-post-scheduler'); ?></h2>

        		<div class="aips-topics-tabs">
                    <button class="aips-tab-link active" data-tab="pending"><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?> (<span id="pending-count">0</span>)</button>
                    <button class="aips-tab-link" data-tab="approved"><?php esc_html_e('Approved', 'ai-post-scheduler'); ?> (<span id="approved-count">0</span>)</button>
                    <button class="aips-tab-link" data-tab="rejected"><?php esc_html_e('Rejected', 'ai-post-scheduler'); ?> (<span id="rejected-count">0</span>)</button>
                    <button class="aips-tab-link" data-tab="feedback"><?php esc_html_e('Feedback', 'ai-post-scheduler'); ?></button>
                </div>
        
                <div class="aips-topics-list-container">
                    <div class="aips-bulk-actions">
                        <select class="aips-bulk-action-select">
                            <option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
                            <option value="approve"><?php esc_html_e('Approve', 'ai-post-scheduler'); ?></option>
                            <option value="reject"><?php esc_html_e('Reject', 'ai-post-scheduler'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></option>
                        </select>
                        <button class="button aips-bulk-action-execute"><?php esc_html_e('Execute', 'ai-post-scheduler'); ?></button>
                    </div>
        
                    <div id="aips-topics-content">
                        <p><?php esc_html_e('Loading topics...', 'ai-post-scheduler'); ?></p>
                    </div>
        
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