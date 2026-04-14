<?php
/**
 * Admin Template: Trending Topics Research
 *
 * Interface for discovering and managing trending topics research.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$repository = new AIPS_Trending_Topics_Repository();
$stats = $repository->get_stats();
$niches = $repository->get_niche_list();
$templates = (new AIPS_Template_Repository())->get_all(array('active' => 1));
$interval_calculator = new AIPS_Interval_Calculator();
$default_research_frequency = 'daily';
$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'trending';
$valid_tabs = array('trending', 'planner', 'gap-analysis');
if (!in_array($active_tab, $valid_tabs, true)) {
    $active_tab = 'trending';
}
?>

<div class="wrap aips-wrap">
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php echo esc_html__('Research', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php echo esc_html__('Discover trending topics in your niche using AI-powered research and automatically schedule content creation.', 'ai-post-scheduler'); ?></p>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="aips-tab-nav">
            <a href="#trending" class="aips-tab-link<?php echo $active_tab === 'trending' ? ' active' : ''; ?>" data-tab="trending"><?php echo esc_html__('Trending Topics', 'ai-post-scheduler'); ?></a>
            <a href="#gap-analysis" class="aips-tab-link<?php echo $active_tab === 'gap-analysis' ? ' active' : ''; ?>" data-tab="gap-analysis"><?php echo esc_html__('Gap Analysis', 'ai-post-scheduler'); ?></a>
            <a href="#planner" class="aips-tab-link<?php echo $active_tab === 'planner' ? ' active' : ''; ?>" data-tab="planner"><?php echo esc_html__('Planner', 'ai-post-scheduler'); ?></a>
        </div>

    <div id="trending-tab" class="aips-tab-content<?php echo $active_tab === 'trending' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'trending' ? '' : 'display:none;'; ?>">
        <!-- Research Stats -->
        <div class="aips-topics-stats">
            <div class="aips-stat-card">
                <span class="aips-stat-value"><?php echo esc_html(number_format($stats['total_topics'])); ?></span>
                <span class="aips-stat-label"><?php echo esc_html__('Total Topics', 'ai-post-scheduler'); ?></span>
            </div>
            <div class="aips-stat-card aips-stat-info">
                <span class="aips-stat-value"><?php echo esc_html(number_format($stats['niches_count'])); ?></span>
                <span class="aips-stat-label"><?php echo esc_html__('Niches', 'ai-post-scheduler'); ?></span>
            </div>
            <div class="aips-stat-card aips-stat-success">
                <span class="aips-stat-value"><?php echo esc_html($stats['avg_score']); ?></span>
                <span class="aips-stat-label"><?php echo esc_html__('Avg Score', 'ai-post-scheduler'); ?></span>
            </div>
            <div class="aips-stat-card aips-stat-warning">
                <span class="aips-stat-value"><?php echo esc_html(number_format($stats['recent_research_count'])); ?></span>
                <span class="aips-stat-label"><?php echo esc_html__('Last 7 Days', 'ai-post-scheduler'); ?></span>
            </div>
        </div>
        
        <!-- New Research Panel -->
        <div class="aips-content-panel">
            <div class="aips-panel-header">
                <h2 class="aips-panel-title"><?php esc_html_e('New Research', 'ai-post-scheduler'); ?></h2>
            </div>
            <div class="aips-panel-body">
            <form id="aips-research-form" method="post">
                <?php wp_nonce_field('aips_ajax_nonce', 'aips_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="research-niche"><?php echo esc_html__('Niche/Industry', 'ai-post-scheduler'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="research-niche" name="niche" class="regular-text" required 
                                   placeholder="<?php echo esc_attr__('e.g., Digital Marketing, Health & Wellness, AI Technology', 'ai-post-scheduler'); ?>">
                            <p class="description">
                                <?php echo esc_html__('Enter the niche or industry to research trending topics for.', 'ai-post-scheduler'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="research-count"><?php echo esc_html__('Number of Topics', 'ai-post-scheduler'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="research-count" name="count" min="1" max="50" value="10" class="small-text">
                            <p class="description">
                                <?php echo esc_html__('How many trending topics to discover (1-50).', 'ai-post-scheduler'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="research-keywords"><?php echo esc_html__('Focus Keywords (Optional)', 'ai-post-scheduler'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="research-keywords" name="keywords" class="regular-text" 
                                   placeholder="<?php echo esc_attr__('e.g., SEO, content strategy, automation', 'ai-post-scheduler'); ?>">
                            <p class="description">
                                <?php echo esc_html__('Comma-separated keywords to focus the research (optional).', 'ai-post-scheduler'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div style="margin-top: 8px; display: flex; align-items: center; gap: 12px;">
                    <button type="submit" class="aips-btn aips-btn-primary" id="research-submit">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <?php esc_html_e('Research Trending Topics', 'ai-post-scheduler'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin: 0;"></span>
                </div>
            </form>

            <?php
            // Source-based research section — only shown when source groups exist.
            $research_source_groups = get_terms(array(
                'taxonomy'   => 'aips_source_group',
                'hide_empty' => false,
            ));
            if (!is_wp_error($research_source_groups) && !empty($research_source_groups)):
            ?>
            <hr style="margin: 24px 0;">
            <h3><?php esc_html_e('Research from Trusted Sources', 'ai-post-scheduler'); ?></h3>
            <p class="description" style="margin-bottom: 12px;">
                <?php esc_html_e('Use pre-fetched content from your Trusted Sources to ground AI topic suggestions in real reference material.', 'ai-post-scheduler'); ?>
            </p>
            <form id="aips-research-from-sources-form" method="post">
                <?php wp_nonce_field('aips_ajax_nonce', 'aips_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="source-research-niche"><?php esc_html_e('Niche/Context', 'ai-post-scheduler'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="source-research-niche" name="niche" class="regular-text" required
                                   placeholder="<?php esc_attr_e('e.g., WordPress Development', 'ai-post-scheduler'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Source Groups', 'ai-post-scheduler'); ?></label>
                        </th>
                        <td>
                            <div class="aips-checkbox-group">
                                <?php foreach ($research_source_groups as $rsg): ?>
                                    <label class="aips-checkbox-label" style="display:block; margin-bottom:4px;">
                                        <input type="checkbox" name="source_term_ids[]"
                                               value="<?php echo esc_attr($rsg->term_id); ?>">
                                        <?php echo esc_html($rsg->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php esc_html_e('Select which source groups to include as context.', 'ai-post-scheduler'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="source-research-count"><?php esc_html_e('Number of Topics', 'ai-post-scheduler'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="source-research-count" name="count" min="1" max="50" value="10" class="small-text">
                        </td>
                    </tr>
                </table>
                <div style="margin-top: 8px; display: flex; align-items: center; gap: 12px;">
                    <button type="submit" class="aips-btn aips-btn-secondary" id="source-research-submit">
                        <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
                        <?php esc_html_e('Research from Sources', 'ai-post-scheduler'); ?>
                    </button>
                    <span class="spinner" id="source-research-spinner" style="float: none; margin: 0;"></span>
                </div>
            </form>
            <?php endif; ?>
            
            <div id="research-results" style="display: none; margin-top: 24px;">
                <h3><?php esc_html_e('Research Results', 'ai-post-scheduler'); ?></h3>
                <div id="research-results-content"></div>
            </div>
            </div><!-- .aips-panel-body -->
        </div><!-- .aips-content-panel (research) -->

        <!-- Trending Topics Library Panel -->
        <div class="aips-content-panel">
            <div class="aips-panel-header">
                <h2 class="aips-panel-title"><?php esc_html_e('Trending Topics Library', 'ai-post-scheduler'); ?></h2>
            </div>
            
            <!-- Filters -->
            <div class="aips-filter-bar">
                <div class="aips-filter-left">
                    <select id="filter-niche" class="aips-form-select">
                        <option value=""><?php echo esc_html__('All Niches', 'ai-post-scheduler'); ?></option>
                        <?php foreach ($niches as $niche):
                            $niche = (object) $niche;
                        ?>
                            <option value="<?php echo esc_attr($niche->niche); ?>">
                                <?php echo esc_html($niche->niche); ?> (<?php echo esc_html($niche->count); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="filter-score" class="aips-form-select">
                        <option value="0"><?php echo esc_html__('All Scores', 'ai-post-scheduler'); ?></option>
                        <option value="80"><?php echo esc_html__('Score 80+', 'ai-post-scheduler'); ?></option>
                        <option value="90"><?php echo esc_html__('Score 90+', 'ai-post-scheduler'); ?></option>
                    </select>
                    
                    <label class="aips-filter-label-inline">
                        <input type="checkbox" id="filter-fresh" value="1">
                        <?php echo esc_html__('Fresh Only (Last 7 Days)', 'ai-post-scheduler'); ?>
                    </label>

                    <label class="aips-filter-label-inline">
                        <input type="checkbox" id="filter-include-used" value="1">
                        <?php echo esc_html__('Include used topics', 'ai-post-scheduler'); ?>
                    </label>
                    
                    <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="load-topics">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
                    </button>
                </div>
                <div class="aips-filter-right">
                    <label class="screen-reader-text" for="filter-search"><?php esc_html_e('Search topics...', 'ai-post-scheduler'); ?></label>
                    <input type="search" id="filter-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search topics...', 'ai-post-scheduler'); ?>">
                    <button type="button" id="filter-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="display:none;" aria-label="<?php esc_attr_e('Clear search', 'ai-post-scheduler'); ?>"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
                </div>
            </div>
            
            <!-- Bulk Actions Toolbar -->
            <div class="aips-panel-toolbar">
                <div class="aips-toolbar-left">
                    <button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-btn-danger-solid" id="aips-delete-selected-topics" disabled>
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
                    </button>
                    <button type="button" class="aips-btn aips-btn-sm aips-btn-primary" id="aips-schedule-selected-topics" disabled>
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e('Schedule', 'ai-post-scheduler'); ?>
                    </button>
                    <button type="button" class="aips-btn aips-btn-sm aips-btn-primary" id="aips-generate-selected-topics" disabled>
                        <span class="dashicons dashicons-media-text"></span>
                        <?php esc_html_e('Generate', 'ai-post-scheduler'); ?>
                    </button>
                    <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-reload-topics-btn">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Reload', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>

            <!-- Topics Table -->
            <div class="aips-panel-body no-padding">
                <div id="topics-container">
                    <div class="aips-panel-body">
                        <p class="description"><?php esc_html_e('Click "Filter" to view your research library.', 'ai-post-scheduler'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Table Footer -->
            <div class="tablenav" id="topics-tablenav" style="display: none;">
                <span class="aips-table-footer-count" id="topics-count"></span>
            </div>
        </div><!-- .aips-content-panel (library) -->

        <!-- Schedule Selected Topics Panel -->
        <div id="bulk-schedule-section" class="aips-content-panel" style="display: none;">
            <div class="aips-panel-header">
                <h2 class="aips-panel-title"><?php esc_html_e('Schedule Selected Topics', 'ai-post-scheduler'); ?></h2>
            </div>
            <div class="aips-panel-body">
                <form id="bulk-schedule-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="schedule-template"><?php esc_html_e('Template', 'ai-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <select id="schedule-template" name="template_id" required class="aips-form-select">
                                    <option value=""><?php esc_html_e('Select Template', 'ai-post-scheduler'); ?></option>
                                    <?php foreach ($templates as $template):
                                        $template = (object) $template;
                                    ?>
                                        <option value="<?php echo esc_attr($template->id); ?>">
                                            <?php echo esc_html($template->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="schedule-start-date"><?php esc_html_e('Start Date', 'ai-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <input type="datetime-local" id="schedule-start-date" name="start_date" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Frequency', 'ai-post-scheduler'); ?>
                            </th>
                            <td>
                                <?php AIPS_Template_Helper::render_frequency_dropdown('schedule-frequency', 'frequency', $default_research_frequency, __('Frequency', 'ai-post-scheduler')); ?>
                            </td>
                        </tr>
                    </table>
                    <div style="margin-top: 8px; display: flex; align-items: center; gap: 12px;">
                        <button type="submit" class="aips-btn aips-btn-primary">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php esc_html_e('Schedule Topics', 'ai-post-scheduler'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin: 0;"></span>
                    </div>
                </form>
            </div><!-- .aips-panel-body -->
        </div><!-- #bulk-schedule-section -->
    </div><!-- #trending-tab -->

    <div id="gap-analysis-tab" class="aips-tab-content<?php echo $active_tab === 'gap-analysis' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'gap-analysis' ? '' : 'display:none;'; ?>">
        <div class="aips-content-panel">
            <div class="aips-panel-header">
                <h2 class="aips-panel-title"><?php esc_html_e('Content Gap Analysis', 'ai-post-scheduler'); ?></h2>
            </div>
            <div class="aips-panel-body">
                <p class="description"><?php esc_html_e('Analyze your existing content against your target niche to identify missing sub-topics and opportunities.', 'ai-post-scheduler'); ?></p>

                <div class="aips-gap-analysis-controls">
                    <input type="text" id="gap-niche" class="regular-text" placeholder="<?php esc_attr_e('Enter Target Niche (e.g., Sustainable Gardening)', 'ai-post-scheduler'); ?>">
                    <button type="button" class="aips-btn aips-btn-primary" id="analyze-gaps-btn">
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php esc_html_e('Analyze Site for Gaps', 'ai-post-scheduler'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin-left: 10px;"></span>
                </div>

                <div id="gap-results-container" style="margin-top: 30px; display: none;">
                    <h3><?php esc_html_e('Identified Content Gaps', 'ai-post-scheduler'); ?></h3>
                    <div class="aips-gap-grid">
                        <!-- Gap cards will be injected here -->
                    </div>
                </div>
            </div><!-- .aips-panel-body -->
        </div><!-- .aips-content-panel -->
    </div>

    <div id="planner-tab" class="aips-tab-content<?php echo $active_tab === 'planner' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'planner' ? '' : 'display:none;'; ?>">
        <?php include AIPS_PLUGIN_DIR . 'templates/admin/planner.php'; ?>
    </div>

    <!-- Trending Topic Posts Modal -->
    <div id="aips-trending-topic-posts-modal" class="aips-modal" style="display: none;">
        <div class="aips-modal-content aips-modal-large">
            <button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
            <h2 id="aips-trending-topic-posts-modal-title"><?php esc_html_e('Posts Generated from Topic', 'ai-post-scheduler'); ?></h2>
            <div id="aips-trending-topic-posts-content">
                <p><?php esc_html_e('Loading posts...', 'ai-post-scheduler'); ?></p>
            </div>
        </div>
    </div>

    <!-- Client-side HTML templates (used by assets/js/admin-research.js) -->
    <script type="text/html" id="aips-tmpl-research-results-summary">
        <p><strong>{{saved_count}} {{topics_saved}} "{{niche}}"</strong></p>
        {{top_topics_block_html}}
    </script>

    <script type="text/html" id="aips-tmpl-research-top-topics-block">
        <h4>{{top_topics_label}}</h4>
        <ol>
            {{items_html}}
        </ol>
    </script>

    <script type="text/html" id="aips-tmpl-research-top-topic-item">
        <li>
            <strong>{{topic}}</strong>
            <span class="aips-score-badge aips-score-{{score_class}}">{{score}}</span>
            {{reason_html}}
        </li>
    </script>

    <script type="text/html" id="aips-tmpl-research-top-topic-reason">
        <br><small><em>{{reason}}</em></small>
    </script>

    <script type="text/html" id="aips-tmpl-research-empty-state">
        <div class="aips-panel-body">
            <div class="aips-empty-state">
                <div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
                <h3 class="aips-empty-state-title">{{title}}</h3>
                <p class="aips-empty-state-description">{{description}}</p>
                <button type="button" class="aips-btn aips-btn-sm {{button_class}}" id="{{button_id}}">{{button_label}}</button>
            </div>
        </div>
    </script>

    <script type="text/html" id="aips-tmpl-research-topics-table">
        <table class="aips-table aips-research-table">
            <thead>
                <tr>
                    <th scope="col" style="width:30px;"><input type="checkbox" id="select-all-topics"></th>
                    <th scope="col"><?php esc_html_e('Topic', 'ai-post-scheduler'); ?></th>
                    <th scope="col"><?php esc_html_e('Score', 'ai-post-scheduler'); ?></th>
                    <th scope="col"><?php esc_html_e('Niche', 'ai-post-scheduler'); ?></th>
                    <th scope="col"><?php esc_html_e('Keywords', 'ai-post-scheduler'); ?></th>
                    <th scope="col"><?php esc_html_e('Researched', 'ai-post-scheduler'); ?></th>
                    <th scope="col"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                </tr>
            </thead>
            <tbody>{{rows_html}}</tbody>
        </table>
        {{search_empty_html}}
    </script>

    <script type="text/html" id="aips-tmpl-research-topics-row">
        <tr>
            <td><input type="checkbox" class="topic-checkbox" value="{{id}}"></td>
            <td>
                <strong>{{topic}}</strong>
                {{status_chip_html}}
                {{post_count_badge_html}}
                {{reason_html}}
            </td>
            <td><span class="aips-score-badge aips-score-{{score_class}}">{{score}}</span></td>
            <td>{{niche}}</td>
            <td>
                <div class="aips-keywords-list">
                    {{keywords_html}}
                </div>
            </td>
            <td>{{researched_at}}</td>
            <td>
                <div class="aips-topic-actions">
                    <button class="aips-btn aips-btn-sm aips-btn-danger delete-topic" data-id="{{id}}">
                        <span class="dashicons dashicons-trash"></span> {{delete_label}}
                    </button>
                </div>
            </td>
        </tr>
    </script>

    <script type="text/html" id="aips-tmpl-research-topic-reason">
        <br><small>{{reason}}</small>
    </script>

    <script type="text/html" id="aips-tmpl-research-topic-post-count-badge">
        <br><span class="aips-post-count-badge" data-context="trending-topic" data-topic-id="{{topic_id}}">
            <span class="dashicons dashicons-admin-post" aria-hidden="true"></span>
            {{count}}
        </span>
    </script>

    <script type="text/html" id="aips-tmpl-research-topic-status-chip">
        <span class="aips-topic-status-chip aips-topic-status-{{status}}">{{status_label}}</span>
    </script>

    <script type="text/html" id="aips-tmpl-research-topic-posts-table">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>{{id_label}}</th>
                    <th>{{title_label}}</th>
                    <th>{{generated_label}}</th>
                    <th>{{published_label}}</th>
                    <th>{{actions_label}}</th>
                </tr>
            </thead>
            <tbody>{{rows}}</tbody>
        </table>
    </script>

    <script type="text/html" id="aips-tmpl-research-topic-post-row">
        <tr>
            <td>{{post_id}}</td>
            <td>{{post_title}}</td>
            <td>{{date_generated}}</td>
            <td>{{date_published}}</td>
            <td>{{actions}}</td>
        </tr>
    </script>

    <script type="text/html" id="aips-tmpl-research-keyword-tag">
        <span class="aips-keyword-tag">{{keyword}}</span>
    </script>

    <script type="text/html" id="aips-tmpl-research-topics-search-empty">
        <div id="topics-search-empty" class="aips-empty-state" style="display:none; padding: 40px 20px;">
            <div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
            <h3 class="aips-empty-state-title">{{title}}</h3>
            <p class="aips-empty-state-description">{{description}}</p>
            <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="clear-topics-search">{{clear_label}}</button>
        </div>
    </script>

    <script type="text/html" id="aips-tmpl-research-gap-empty">
        <p><?php esc_html_e('No gaps found.', 'ai-post-scheduler'); ?></p>
    </script>

    <script type="text/html" id="aips-tmpl-research-gap-card">
        <div class="aips-gap-card priority-{{priority_class}}">
            <span class="aips-gap-badge {{priority_class}}">{{priority}} <?php esc_html_e('Priority', 'ai-post-scheduler'); ?></span>
            <h4>{{missing_topic}}</h4>
            <p class="aips-gap-reason">{{reason}}</p>
            <p class="aips-gap-intent"><?php esc_html_e('Intent:', 'ai-post-scheduler'); ?> {{search_intent}}</p>
            <div class="aips-gap-actions">
                <button class="aips-btn aips-btn-sm aips-btn-secondary generate-gap-ideas" data-topic="{{missing_topic}}">{{generate_ideas_label}}</button>
            </div>
        </div>
    </script>
    </div><!-- .aips-page-container -->
</div><!-- .wrap -->
