<?php
/**
 * Internal Links Admin Template
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap">
    <div class="aips-page-container">
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('Internal Links', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Index post embeddings and generate semantic internal links for stronger discoverability and SEO.', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="aips-page-actions">
                    <button type="button" class="aips-btn aips-btn-primary" id="aips-index-all-posts">
                        <span class="dashicons dashicons-database-add"></span>
                        <?php esc_html_e('Index All Posts', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="aips-author-topics-stats" id="aips-index-summary-banner">
            <div class="aips-stat-card">
                <span class="aips-stat-value" id="aips-summary-total"><?php echo esc_html((string) $summary['total_published']); ?></span>
                <span class="aips-stat-label"><?php esc_html_e('Published Posts', 'ai-post-scheduler'); ?></span>
            </div>
            <div class="aips-stat-card aips-stat-approved">
                <span class="aips-stat-value" id="aips-summary-indexed"><?php echo esc_html((string) $summary['indexed']); ?></span>
                <span class="aips-stat-label"><?php esc_html_e('Indexed', 'ai-post-scheduler'); ?></span>
            </div>
            <div class="aips-stat-card aips-stat-pending">
                <span class="aips-stat-value" id="aips-summary-pending"><?php echo esc_html((string) $summary['pending']); ?></span>
                <span class="aips-stat-label"><?php esc_html_e('Pending', 'ai-post-scheduler'); ?></span>
            </div>
            <div class="aips-stat-card aips-stat-rejected">
                <span class="aips-stat-value" id="aips-summary-error"><?php echo esc_html((string) $summary['error']); ?></span>
                <span class="aips-stat-label"><?php esc_html_e('Errored', 'ai-post-scheduler'); ?></span>
            </div>
        </div>

        <div class="aips-content-panel">
            <div class="aips-topics-tabs aips-page-tabs">
                <button class="aips-tab-link active" data-tab="index-posts"><?php esc_html_e('Index Posts', 'ai-post-scheduler'); ?></button>
                <button class="aips-tab-link" data-tab="generate-links"><?php esc_html_e('Generate Internal Links', 'ai-post-scheduler'); ?></button>
            </div>

            <div class="aips-tab-content active" id="aips-index-posts-tab">
                <div class="aips-filter-bar">
                    <div class="aips-filter-left">
                        <label class="screen-reader-text" for="aips-index-search"><?php esc_html_e('Search posts', 'ai-post-scheduler'); ?></label>
                        <input type="search" id="aips-index-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search post title...', 'ai-post-scheduler'); ?>">
                        <label class="screen-reader-text" for="aips-index-status-filter"><?php esc_html_e('Status filter', 'ai-post-scheduler'); ?></label>
                        <select id="aips-index-status-filter" class="aips-form-select">
                            <option value="all"><?php esc_html_e('All Statuses', 'ai-post-scheduler'); ?></option>
                            <option value="indexed"><?php esc_html_e('Indexed', 'ai-post-scheduler'); ?></option>
                            <option value="pending"><?php esc_html_e('Pending', 'ai-post-scheduler'); ?></option>
                            <option value="error"><?php esc_html_e('Error', 'ai-post-scheduler'); ?></option>
                        </select>
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-index-filter-apply">
                            <span class="dashicons dashicons-filter"></span>
                            <?php esc_html_e('Apply', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                </div>

                <div class="aips-panel-body no-padding">
                    <table class="aips-table aips-internal-links-index-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Post Title', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Post Type', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Index Status', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Indexed At', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="aips-index-table-body">
                            <tr><td colspan="5"><?php esc_html_e('Loading...', 'ai-post-scheduler'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="tablenav">
                    <div class="aips-btn-group" id="aips-index-pagination"></div>
                </div>
            </div>

            <div class="aips-tab-content" id="aips-generate-links-tab" style="display:none;">
                <div class="aips-filter-bar">
                    <div class="aips-filter-left" style="width:100%;">
                        <label class="screen-reader-text" for="aips-link-source-search"><?php esc_html_e('Search source post', 'ai-post-scheduler'); ?></label>
                        <input type="search" id="aips-link-source-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search source post...', 'ai-post-scheduler'); ?>">
                        <div id="aips-link-source-autocomplete" class="aips-content-panel" style="display:none;position:absolute;z-index:10;max-height:220px;overflow:auto;"></div>
                        <div id="aips-selected-source-preview" class="description" style="margin-top:8px;"></div>
                    </div>
                </div>

                <div class="aips-filter-bar">
                    <div class="aips-filter-left">
                        <label for="aips-max-links"><?php esc_html_e('Max Links', 'ai-post-scheduler'); ?></label>
                        <input type="number" id="aips-max-links" class="aips-form-input" min="1" max="20" value="<?php echo esc_attr((string) $top_n_default); ?>" style="width:90px;">
                        <label for="aips-min-similarity"><?php esc_html_e('Min Similarity', 'ai-post-scheduler'); ?></label>
                        <input type="range" id="aips-min-similarity" min="0.1" max="1" step="0.01" value="<?php echo esc_attr((string) $min_score_default); ?>">
                        <span id="aips-min-similarity-value"><?php echo esc_html(number_format_i18n($min_score_default, 2)); ?></span>
                    </div>
                    <div class="aips-filter-right">
                        <button type="button" class="aips-btn aips-btn-secondary" id="aips-find-related-posts">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e('Find Related Posts', 'ai-post-scheduler'); ?>
                        </button>
                        <button type="button" class="aips-btn aips-btn-primary" id="aips-preview-links" disabled>
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e('Preview Links', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                </div>

                <div class="aips-panel-body no-padding">
                    <table class="aips-table" id="aips-related-posts-table" style="display:none;">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox" id="aips-select-all-related"></th>
                                <th><?php esc_html_e('Post Title', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Similarity Score', 'ai-post-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="aips-related-posts-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="aips-internal-links-preview-modal" class="aips-modal" style="display:none;">
    <div class="aips-modal-content aips-modal-large">
        <button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
        <div class="aips-modal-header">
            <h2><?php esc_html_e('Internal Links Preview', 'ai-post-scheduler'); ?></h2>
        </div>
        <div class="aips-modal-body" style="max-height:60vh;overflow:auto;">
            <div id="aips-preview-links-html"></div>
        </div>
        <div class="aips-modal-footer">
            <button type="button" class="aips-btn aips-btn-primary" id="aips-apply-links-save"><?php esc_html_e('Apply & Save', 'ai-post-scheduler'); ?></button>
            <button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
        </div>
    </div>
</div>
