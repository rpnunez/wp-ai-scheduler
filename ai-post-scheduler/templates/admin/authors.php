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
        // Bulk-fetch feedback stats and policy flags to avoid N+1 queries.
        $feedback_repository = new AIPS_Feedback_Repository();
        $author_ids = array_map(function($a) { return $a->id; }, $authors);
        $all_feedback_stats = $feedback_repository->get_statistics_bulk($author_ids);
    }

    // Load article structures for the dropdown
    $structures_repository = new AIPS_Article_Structure_Repository();
    $article_structures = $structures_repository->get_all(true); // Get active structures only
}

// Site-wide content settings used to pre-fill the Author Suggestions modal
$site_ctx = AIPS_Site_Context::get();
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
                    <button class="aips-btn aips-btn-secondary" id="aips-suggest-authors-btn">
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php esc_html_e('Suggest Authors', 'ai-post-scheduler'); ?>
                    </button>
                    <button class="aips-btn aips-btn-primary aips-add-author-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Add Author', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Add tabs for Authors List and Generation Queue -->
        <div class="aips-tab-nav">
            <a href="#authors-list" class="aips-tab-link active" data-tab="authors-list"><?php esc_html_e('Authors List', 'ai-post-scheduler'); ?></a>
            <a href="#generation-queue" class="aips-tab-link" data-tab="generation-queue"><?php esc_html_e('Generation Queue', 'ai-post-scheduler'); ?></a>
        </div>

        <!-- Authors List Tab Content -->
        <div id="authors-list-tab" class="aips-tab-content active" role="tabpanel" aria-hidden="false">
            <?php if (!empty($authors)): ?>
            <div class="aips-content-panel">
                <!-- Filter Bar -->
                <div class="aips-filter-bar">
                    <div class="aips-filter-right">
                        <label class="screen-reader-text" for="aips-author-search"><?php esc_html_e('Search Authors:', 'ai-post-scheduler'); ?></label>
                        <input type="search" id="aips-author-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search authors...', 'ai-post-scheduler'); ?>">
                        <button type="button" id="aips-author-search-clear" class="aips-btn aips-btn-secondary" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
                    </div>
                </div>

                <!-- Authors Table -->
                <div class="aips-panel-body no-padding">
                    <div class="aips-panel-toolbar">
                        <div class="aips-toolbar-left aips-btn-group aips-btn-group-inline">
                            <select id="aips-authors-bulk-action-select" class="aips-form-select" style="width: auto;">
                                <option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
                                <option value="generate_topics"><?php esc_html_e('Generate Topics', 'ai-post-scheduler'); ?></option>
                                <option value="delete"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></option>
                            </select>
                            <button type="button" id="aips-authors-bulk-apply" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e('Apply', 'ai-post-scheduler'); ?></button>
                        </div>
                    </div>

                    <table class="aips-table aips-authors-table">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 30px;">
                                    <label class="screen-reader-text" for="aips-authors-select-all"><?php esc_html_e('Select all authors', 'ai-post-scheduler'); ?></label>
                                    <input id="aips-authors-select-all" type="checkbox">
                                </th>
                                <th class="column-quality">
                                    <span class="screen-reader-text"><?php esc_html_e('Quality', 'ai-post-scheduler'); ?></span>
                                </th>
                                <th><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Topics', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Posts', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($authors as $author):
                                $status_counts = $topics_repository->get_status_counts($author->id);
                                $total_topics = $status_counts['pending'] + $status_counts['approved'] + $status_counts['rejected'];
                                $posts_count = $logs_repository->count_generated_posts_by_author($author->id);
                                // Read policy flags directly from already-loaded author details (no extra DB query).
                                $author_details = !empty($author->details) ? json_decode($author->details, true) : array();
                                $policy_flags = (is_array($author_details) && isset($author_details['policy_flags']) && is_array($author_details['policy_flags'])) ? $author_details['policy_flags'] : array();
                                $policy_flags_count = count($policy_flags);
                                // Quality indicator data — use pre-fetched bulk stats.
                                $feedback_stats = isset($all_feedback_stats[$author->id]) ? $all_feedback_stats[$author->id] : array('total' => 0, 'approved' => 0, 'rejected' => 0);
                                $feedback_total = (int) $feedback_stats['total'];
                                $feedback_approved = (int) $feedback_stats['approved'];
                                $approval_rate = $feedback_total > 0 ? round(($feedback_approved / $feedback_total) * 100) : null;
                                $approval_component = $approval_rate !== null ? (int) $approval_rate : 50;
                                $policy_penalty = min(60, $policy_flags_count * 20);
                                $quality_score = max(0, min(100, $approval_component - $policy_penalty));
                                // Determine quality state: Green = healthy, Yellow = warning, Red = critical
                                if ($policy_flags_count >= 3) {
                                    $quality_state = 'critical';
                                } elseif ($policy_flags_count >= 1 || ($approval_rate !== null && $approval_rate < 50)) {
                                    $quality_state = 'warning';
                                } else {
                                    $quality_state = 'healthy';
                                }
                                if ($quality_state === 'critical') {
                                    $indicator_icon = 'dashicons-dismiss';
                                    $indicator_label = __('Critical', 'ai-post-scheduler');
                                    $quality_description = __('Critical quality risk. This author has repeated policy issues and/or weak approval performance. Review and adjust prompts before generating more content.', 'ai-post-scheduler');
                                } elseif ($quality_state === 'warning') {
                                    $indicator_icon = 'dashicons-warning';
                                    $indicator_label = __('Warning', 'ai-post-scheduler');
                                    $quality_description = __('Quality needs attention. Some policy issues or low approvals are reducing reliability. Tune author settings and review recent topics.', 'ai-post-scheduler');
                                } else {
                                    $indicator_icon = 'dashicons-heart';
                                    $indicator_label = __('Healthy', 'ai-post-scheduler');
                                    $quality_description = __('Quality is stable. Approval performance is healthy and policy issues are low.', 'ai-post-scheduler');
                                }

                                $quality_score_text = sprintf(
                                    __('Score: %d/100', 'ai-post-scheduler'),
                                    (int) $quality_score
                                );
                                $quality_meta_text = sprintf(
                                    __('Approval: %1$s | Policy flags: %2$d | Feedback samples: %3$d', 'ai-post-scheduler'),
                                    $approval_rate !== null ? sprintf(__('%d%%', 'ai-post-scheduler'), $approval_rate) : __('No feedback yet', 'ai-post-scheduler'),
                                    (int) $policy_flags_count,
                                    (int) $feedback_total
                                );
                            ?>
                                <tr data-author-id="<?php echo esc_attr($author->id); ?>" data-field-niche="<?php echo esc_attr($author->field_niche); ?>">
                                    <td>
                                        <label class="screen-reader-text" for="aips-author-select-<?php echo esc_attr($author->id); ?>"><?php esc_html_e('Select author', 'ai-post-scheduler'); ?></label>
                                        <input id="aips-author-select-<?php echo esc_attr($author->id); ?>" type="checkbox" class="aips-author-checkbox" value="<?php echo esc_attr($author->id); ?>">
                                    </td>
                                    <td class="column-quality">
                                        <span
                                            class="aips-quality-indicator aips-quality-<?php echo esc_attr($quality_state); ?>"
                                            tabindex="0"
                                            aria-label="<?php echo esc_attr($indicator_label); ?>"
                                            aria-describedby="aips-quality-tooltip-<?php echo esc_attr($author->id); ?>"
                                        >
                                            <span class="dashicons <?php echo esc_attr($indicator_icon); ?>"></span>
                                            <span id="aips-quality-tooltip-<?php echo esc_attr($author->id); ?>" class="aips-quality-tooltip" role="tooltip">
                                                <span class="aips-quality-tooltip-title"><?php echo esc_html($indicator_label); ?></span>
                                                <span class="aips-quality-tooltip-score"><?php echo esc_html($quality_score_text); ?></span>
                                                <span class="aips-quality-tooltip-description"><?php echo esc_html($quality_description); ?></span>
                                                <span class="aips-quality-tooltip-meta"><?php echo esc_html($quality_meta_text); ?></span>
                                            </span>
                                            <span class="screen-reader-text">
                                                <?php
                                                printf(
                                                    esc_html__('%1$s quality score %2$d out of 100', 'ai-post-scheduler'),
                                                    esc_html($indicator_label),
                                                    (int) $quality_score
                                                );
                                                ?>
                                            </span>
                                        </span>
                                    </td>
                                    <td class="column-name">
                                        <div class="cell-primary"><?php echo esc_html($author->name); ?></div>
                                        <div class="cell-meta"><?php echo esc_html($author->field_niche); ?></div>
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
                                        <?php if ($policy_flags_count >= 3): ?>
                                        <div style="margin-top: 6px;">
                                            <span class="aips-badge aips-badge-warning">
                                                <span class="dashicons dashicons-warning"></span>
                                                <?php
                                                printf(
                                                    esc_html__('Policy flags: %d', 'ai-post-scheduler'),
                                                    (int) $policy_flags_count
                                                );
                                                ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 6px; align-items: flex-start;">
                                            <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'aips-author-topics', 'author_id' => absint( $author->id ) ), admin_url( 'admin.php' ) ) ); ?>" class="aips-btn aips-btn-sm aips-btn-secondary">
                                                <span class="dashicons dashicons-visibility"></span>
                                                <?php echo esc_html(sprintf(_n('%d Topic', '%d Topics', $total_topics, 'ai-post-scheduler'), $total_topics)); ?>
                                            </a>
                                            <div class="cell-meta" style="font-size: 11px;">
                                                <span style="color: #d63638;"><?php echo esc_html($status_counts['pending']); ?> pending</span> |
                                                <span style="color: #00a32a;"><?php echo esc_html($status_counts['approved']); ?> approved</span> |
                                                <span style="color: #999;"><?php echo esc_html($status_counts['rejected']); ?> rejected</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'aips-generated-posts', 'author_id' => absint( $author->id ) ), admin_url( 'admin.php' ) ) ); ?>" class="aips-btn aips-btn-sm aips-btn-secondary">
                                            <span class="dashicons dashicons-admin-post"></span>
                                            <?php echo esc_html(sprintf(_n('%d Post', '%d Posts', $posts_count, 'ai-post-scheduler'), $posts_count)); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="cell-actions">
                                            <button class="aips-btn aips-btn-sm aips-btn-primary aips-generate-topics-now" data-id="<?php echo esc_attr($author->id); ?>" title="<?php esc_attr_e('Generate Topics', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Generate Topics', 'ai-post-scheduler'); ?>">
                                                <span class="dashicons dashicons-update"></span>
                                                <?php esc_html_e('Generate Topics', 'ai-post-scheduler'); ?>
                                            </button>
                                            <button class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-author" data-id="<?php echo esc_attr($author->id); ?>" title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                                <?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
                                            </button>
                                            <button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-author" data-id="<?php echo esc_attr($author->id); ?>" title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                                <?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Table footer -->
                    <div class="tablenav">
                        <span class="aips-table-footer-count">
                            <?php
                            $authors_count = count( $authors );
                            printf(
                                esc_html(
                                    _n(
                                        '%s author',
                                        '%s authors',
                                        $authors_count,
                                        'ai-post-scheduler'
                                    )
                                ),
                                number_format_i18n( $authors_count )
                            );
                            ?>
                        </span>
                    </div>

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
        <div id="generation-queue-tab" class="aips-tab-content" style="display: none;" role="tabpanel" aria-hidden="true">
            <div class="aips-content-panel">
                <div class="aips-filter-bar">
                    <div class="aips-filter-left">
                        <select id="aips-queue-author-filter" class="aips-form-select">
                            <option value=""><?php esc_html_e('All Authors', 'ai-post-scheduler'); ?></option>
                        </select>
                        <select id="aips-queue-field-filter" class="aips-form-select">
                            <option value=""><?php esc_html_e('All Fields/Niches', 'ai-post-scheduler'); ?></option>
                        </select>
                        <button type="button" id="aips-queue-filter-submit" class="aips-btn aips-btn-sm aips-btn-secondary">
                            <span class="dashicons dashicons-filter"></span>
                            <?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                    <div class="aips-filter-right">
                        <label class="screen-reader-text" for="aips-queue-search"><?php esc_html_e('Search Queue Topics:', 'ai-post-scheduler'); ?></label>
                        <input type="search" id="aips-queue-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search queue topics...', 'ai-post-scheduler'); ?>">
                        <button type="button" id="aips-queue-search-clear" class="aips-btn aips-btn-sm aips-btn-secondary" style="display: none;">
                            <?php esc_html_e('Clear', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                </div>

                <div class="aips-panel-toolbar">
                    <div class="aips-toolbar-left aips-btn-group aips-btn-group-inline">
                        <select id="aips-queue-bulk-action-select" class="aips-form-select aips-queue-bulk-action-select" style="width: auto;">
                            <option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
                            <option value="generate_now"><?php esc_html_e('Generate Now', 'ai-post-scheduler'); ?></option>
                        </select>
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-queue-bulk-action-execute">
                            <?php esc_html_e('Apply', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                    <div class="aips-toolbar-right">
                        <button type="button" id="aips-queue-reload-btn" class="aips-btn aips-btn-sm aips-btn-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Reload', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                </div>

                <div class="aips-panel-body no-padding">
                    <div id="aips-queue-topics-list">
                        <div class="aips-panel-body">
                            <p class="description" style="margin-bottom: 0;">
                                <?php esc_html_e('This queue shows all approved topics across all authors, ready for post generation. Topics are prioritized by score (highest first), then by approval date.', 'ai-post-scheduler'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="tablenav" id="aips-queue-tablenav" style="display: none;">
                    <span class="aips-table-footer-count" id="aips-queue-table-footer-count"></span>
                    <div class="aips-history-pagination-links" id="aips-queue-pagination-links"></div>
                </div>
            </div>
        </div>
    </div><!-- .aips-page-container -->
</div><!-- .wrap.aips-wrap -->

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
                <label for="voice_tone"><?php esc_html_e('Tone', 'ai-post-scheduler'); ?></label>
                <input type="text" id="voice_tone" name="voice_tone" placeholder="<?php esc_attr_e('e.g., Professional, Witty, Academic', 'ai-post-scheduler'); ?>">
                <p class="description"><?php esc_html_e('Specify the tone of voice for the generated content', 'ai-post-scheduler'); ?></p>
            </div>

            <div class="form-group">
                <label for="writing_style"><?php esc_html_e('Writing Style', 'ai-post-scheduler'); ?></label>
                <input type="text" id="writing_style" name="writing_style" placeholder="<?php esc_attr_e('e.g., Tutorial, Opinion Piece, Case Study', 'ai-post-scheduler'); ?>">
                <p class="description"><?php esc_html_e('Specify the writing style for the generated content', 'ai-post-scheduler'); ?></p>
            </div>

            <div class="form-group">
                <label for="author_target_audience"><?php esc_html_e('Target Audience', 'ai-post-scheduler'); ?></label>
                <input type="text" id="author_target_audience" name="target_audience" placeholder="<?php esc_attr_e('e.g., Beginner developers, Small business owners', 'ai-post-scheduler'); ?>">
                <p class="description"><?php esc_html_e('Who this author writes for. Helps the AI tailor topic ideas and content depth.', 'ai-post-scheduler'); ?></p>
            </div>

            <div class="form-group">
                <label for="author_expertise_level"><?php esc_html_e('Expertise Level', 'ai-post-scheduler'); ?></label>
                <select id="author_expertise_level" name="expertise_level">
                    <option value=""><?php esc_html_e('— Not specified —', 'ai-post-scheduler'); ?></option>
                    <option value="beginner"><?php esc_html_e('Beginner', 'ai-post-scheduler'); ?></option>
                    <option value="intermediate"><?php esc_html_e('Intermediate', 'ai-post-scheduler'); ?></option>
                    <option value="expert"><?php esc_html_e('Expert', 'ai-post-scheduler'); ?></option>
                    <option value="thought_leader"><?php esc_html_e('Thought Leader', 'ai-post-scheduler'); ?></option>
                </select>
                <p class="description"><?php esc_html_e("The author's level of expertise. Influences the depth and complexity of generated content.", 'ai-post-scheduler'); ?></p>
            </div>

            <div class="form-group">
                <label for="author_content_goals"><?php esc_html_e('Content Goals', 'ai-post-scheduler'); ?></label>
                <textarea id="author_content_goals" name="content_goals" rows="2" placeholder="<?php esc_attr_e('e.g., Educate readers, Drive conversions, Build community', 'ai-post-scheduler'); ?>"></textarea>
                <p class="description"><?php esc_html_e('What the content from this author should achieve. Used to steer topic generation.', 'ai-post-scheduler'); ?></p>
            </div>

            <div class="form-group">
                <label for="author_excluded_topics"><?php esc_html_e('Excluded Topics', 'ai-post-scheduler'); ?></label>
                <textarea id="author_excluded_topics" name="excluded_topics" rows="2" placeholder="<?php esc_attr_e('e.g., competitor products, controversial politics', 'ai-post-scheduler'); ?>"></textarea>
                <p class="description"><?php esc_html_e('Topics this author should never write about. Combined with site-wide exclusions.', 'ai-post-scheduler'); ?></p>
            </div>

            <div class="form-group">
                <label for="author_preferred_content_length"><?php esc_html_e('Preferred Content Length', 'ai-post-scheduler'); ?></label>
                <select id="author_preferred_content_length" name="preferred_content_length">
                    <option value=""><?php esc_html_e('— Not specified —', 'ai-post-scheduler'); ?></option>
                    <option value="short"><?php esc_html_e('Short (under 800 words)', 'ai-post-scheduler'); ?></option>
                    <option value="medium"><?php esc_html_e('Medium (800–1,500 words)', 'ai-post-scheduler'); ?></option>
                    <option value="long"><?php esc_html_e('Long (1,500+ words)', 'ai-post-scheduler'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Guides the AI on how long each generated post should be.', 'ai-post-scheduler'); ?></p>
            </div>

            <div class="form-group">
                <label for="author_language"><?php esc_html_e('Content Language', 'ai-post-scheduler'); ?></label>
                <select id="author_language" name="language">
                    <option value="en"><?php esc_html_e('English (default)', 'ai-post-scheduler'); ?></option>
                    <option value="es"><?php esc_html_e('Spanish', 'ai-post-scheduler'); ?></option>
                    <option value="fr"><?php esc_html_e('French', 'ai-post-scheduler'); ?></option>
                    <option value="de"><?php esc_html_e('German', 'ai-post-scheduler'); ?></option>
                    <option value="it"><?php esc_html_e('Italian', 'ai-post-scheduler'); ?></option>
                    <option value="pt"><?php esc_html_e('Portuguese', 'ai-post-scheduler'); ?></option>
                    <option value="nl"><?php esc_html_e('Dutch', 'ai-post-scheduler'); ?></option>
                    <option value="pl"><?php esc_html_e('Polish', 'ai-post-scheduler'); ?></option>
                    <option value="ru"><?php esc_html_e('Russian', 'ai-post-scheduler'); ?></option>
                    <option value="ja"><?php esc_html_e('Japanese', 'ai-post-scheduler'); ?></option>
                    <option value="ko"><?php esc_html_e('Korean', 'ai-post-scheduler'); ?></option>
                    <option value="zh"><?php esc_html_e('Chinese (Simplified)', 'ai-post-scheduler'); ?></option>
                    <option value="ar"><?php esc_html_e('Arabic', 'ai-post-scheduler'); ?></option>
                    <option value="hi"><?php esc_html_e('Hindi', 'ai-post-scheduler'); ?></option>
                    <option value="tr"><?php esc_html_e('Turkish', 'ai-post-scheduler'); ?></option>
                    <option value="sv"><?php esc_html_e('Swedish', 'ai-post-scheduler'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Language for content generated by this author. Overrides the site-wide language setting.', 'ai-post-scheduler'); ?></p>
            </div>

            <div class="form-group">
                <label for="author_max_posts_per_topic"><?php esc_html_e('Max Posts per Topic', 'ai-post-scheduler'); ?></label>
                <input type="number" id="author_max_posts_per_topic" name="max_posts_per_topic" value="1" min="1" max="10">
                <p class="description"><?php esc_html_e('Maximum number of posts that can be generated from a single approved topic.', 'ai-post-scheduler'); ?></p>
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

<!-- Author Suggestions Modal -->
<div id="aips-suggest-authors-modal" class="aips-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="aips-suggest-authors-modal-title">
    <div class="aips-modal-content aips-modal-large">
        <button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
        <div class="aips-modal-header">
            <h2 id="aips-suggest-authors-modal-title"><?php esc_html_e('Suggest Authors with AI', 'ai-post-scheduler'); ?></h2>
            <p class="description">
                <?php esc_html_e('Describe your site and goals. The AI will suggest author profiles tailored to your content strategy.', 'ai-post-scheduler'); ?>
                <?php if (!empty($site_ctx['niche'])) : ?>
                    <?php esc_html_e('Fields below are pre-filled from your Site Content Strategy settings.', 'ai-post-scheduler'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings#content-strategy')); ?>"><?php esc_html_e('Edit settings', 'ai-post-scheduler'); ?></a>
                <?php endif; ?>
            </p>
        </div>
        <div class="aips-modal-body">
            <form id="aips-suggest-authors-form">
                <div class="form-group">
                    <label for="aips-suggest-site-niche"><?php esc_html_e('Site Niche / Primary Topic', 'ai-post-scheduler'); ?> *</label>
                    <input type="text" id="aips-suggest-site-niche" name="site_niche" required
                        value="<?php echo esc_attr($site_ctx['niche']); ?>"
                        placeholder="<?php esc_attr_e('e.g., WordPress development, personal finance, fitness', 'ai-post-scheduler'); ?>">
                    <p class="description"><?php esc_html_e('The main topic or industry your site covers.', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="form-group">
                    <label for="aips-suggest-target-audience"><?php esc_html_e('Target Audience', 'ai-post-scheduler'); ?></label>
                    <input type="text" id="aips-suggest-target-audience" name="target_audience"
                        value="<?php echo esc_attr($site_ctx['target_audience']); ?>"
                        placeholder="<?php esc_attr_e('e.g., beginner developers, busy professionals, parents', 'ai-post-scheduler'); ?>">
                    <p class="description"><?php esc_html_e('Who are you writing for?', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="form-group">
                    <label for="aips-suggest-content-goals"><?php esc_html_e('Content Goals', 'ai-post-scheduler'); ?></label>
                    <textarea id="aips-suggest-content-goals" name="content_goals" rows="3"
                        placeholder="<?php esc_attr_e('e.g., educate readers, drive product sign-ups, build a community', 'ai-post-scheduler'); ?>"><?php echo esc_textarea($site_ctx['content_goals']); ?></textarea>
                    <p class="description"><?php esc_html_e('What do you want your content to achieve?', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="form-group">
                    <label for="aips-suggest-count"><?php esc_html_e('Number of Suggestions', 'ai-post-scheduler'); ?></label>
                    <select id="aips-suggest-count" name="count">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3" selected>3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" id="aips-suggest-authors-submit" class="aips-btn aips-btn-primary">
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php esc_html_e('Generate Suggestions', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </form>

            <div id="aips-suggest-authors-results" style="display: none; margin-top: 24px;">
                <hr>
                <h3><?php esc_html_e('Suggested Authors', 'ai-post-scheduler'); ?></h3>
                <p class="description"><?php esc_html_e('Review the suggestions below. Click "Import Author" to add a suggestion as a new author profile.', 'ai-post-scheduler'); ?></p>
                <div id="aips-suggest-authors-cards"></div>
            </div>
        </div>
    </div>
</div>


<?php /* ------------------------------------------------------------------ */
/* HTML templates used by AIPS.Templates.render() in authors.js             */
/* ------------------------------------------------------------------ */ ?>

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

<!-- Generation Queue Tab Templates -->
<script type="text/html" id="aips-tmpl-queue-table">
<table class="aips-table aips-queue-table">
    <thead>
        <tr>
            <th scope="col" style="width: 30px;"><input type="checkbox" class="aips-queue-select-all"></th>
            <th scope="col">{{titleLabel}}</th>
            <th scope="col">{{authorLabel}}</th>
            <th scope="col">{{fieldLabel}}</th>
            <th scope="col">{{dateLabel}}</th>
        </tr>
    </thead>
    <tbody>
        {{rows}}
    </tbody>
</table>
</script>

<script type="text/html" id="aips-tmpl-queue-row">
<tr>
    <td><input type="checkbox" class="aips-queue-topic-checkbox" value="{{id}}"></td>
    <td><span class="cell-primary">{{title}}</span></td>
    <td>{{author}}</td>
    <td>{{field}}</td>
    <td><div class="cell-meta">{{date}}</div></td>
</tr>
</script>

<!-- Pagination Template -->
<script type="text/html" id="aips-tmpl-queue-pagination">
<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-queue-page-link" data-page="{{prevPage}}" {{prevDisabled}}><span class="dashicons dashicons-arrow-left-alt2"></span></button>
<span class="aips-history-page-numbers">
    {{pages}}
</span>
<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-queue-page-link" data-page="{{nextPage}}" {{nextDisabled}}><span class="dashicons dashicons-arrow-right-alt2"></span></button>
</script>
<?php /* ------------------------------------------------------------------ */
/* HTML template used by AIPS.Templates.renderRaw() in authors.js          */
/* The {{token}} placeholders are replaced at run-time with data from the   */
/* AI-generated suggestion object; values are escaped by the template       */
/* engine so keep the markup as-is.                                         */
/* ------------------------------------------------------------------ */ ?>
<script type="text/html" id="aips-tmpl-suggestion-card">
<div class="aips-suggestion-card">
    <div class="aips-suggestion-card-header">
        <div class="aips-suggestion-card-identity">
            <h4 class="aips-suggestion-card-name">{{name}}</h4>
            <span class="aips-badge aips-badge-neutral">{{field_niche}}</span>
        </div>
        <button type="button"
                class="aips-btn aips-btn-sm aips-btn-primary aips-import-suggested-author"
                data-index="{{index}}"
                aria-label="{{importAriaLabel}}">
            <span class="dashicons dashicons-download" aria-hidden="true"></span>
            {{importLabel}}
        </button>
    </div>
    <p class="aips-suggestion-card-description">{{description}}</p>
    <div class="aips-suggestion-card-meta">{{meta}}</div>
</div>
</script>

<?php /* Template for a single meta row inside the suggestion card */ ?>
<script type="text/html" id="aips-tmpl-suggestion-meta-row">
<span class="aips-suggestion-meta-row"><strong>{{label}}:</strong> {{value}}</span>
</script>


