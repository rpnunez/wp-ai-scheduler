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

$research_controller = new AIPS_Research_Controller();
$stats = $research_controller->get_research_stats();
$repository = new AIPS_Trending_Topics_Repository();
$niches = $repository->get_niche_list();
$templates = (new AIPS_Template_Repository())->get_all(array('active' => 1));
?>

<div class="wrap aips-research">
    <h1><?php echo esc_html__('Trending Topics Research', 'ai-post-scheduler'); ?></h1>
    
    <p class="description">
        <?php echo esc_html__('Use AI to discover trending topics in your niche and automatically schedule content creation. This feature helps you stay current with what your audience is searching for.', 'ai-post-scheduler'); ?>
    </p>
    
    <!-- Research Stats -->
    <div class="aips-stats-cards">
        <div class="aips-stat-card">
            <h3><?php echo esc_html(number_format($stats['total_topics'])); ?></h3>
            <p><?php echo esc_html__('Total Topics', 'ai-post-scheduler'); ?></p>
        </div>
        <div class="aips-stat-card">
            <h3><?php echo esc_html(number_format($stats['niches_count'])); ?></h3>
            <p><?php echo esc_html__('Niches', 'ai-post-scheduler'); ?></p>
        </div>
        <div class="aips-stat-card">
            <h3><?php echo esc_html($stats['avg_score']); ?></h3>
            <p><?php echo esc_html__('Avg Score', 'ai-post-scheduler'); ?></p>
        </div>
        <div class="aips-stat-card">
            <h3><?php echo esc_html(number_format($stats['recent_research_count'])); ?></h3>
            <p><?php echo esc_html__('Last 7 Days', 'ai-post-scheduler'); ?></p>
        </div>
    </div>
    
    <!-- Research Form -->
    <div class="aips-card">
        <h2><?php echo esc_html__('New Research', 'ai-post-scheduler'); ?></h2>
        
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
            
            <p class="submit">
                <button type="submit" class="button button-primary" id="research-submit">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <?php echo esc_html__('Research Trending Topics', 'ai-post-scheduler'); ?>
                </button>
                <span class="spinner" style="float: none; margin-left: 10px;"></span>
            </p>
        </form>
        
        <div id="research-results" style="display: none; margin-top: 30px;">
            <h3><?php echo esc_html__('Research Results', 'ai-post-scheduler'); ?></h3>
            <div id="research-results-content"></div>
        </div>
    </div>
    
    <!-- Existing Research -->
    <div class="aips-card">
        <h2><?php echo esc_html__('Trending Topics Library', 'ai-post-scheduler'); ?></h2>
        
        <!-- Filters -->
        <div class="aips-filters">
            <select id="filter-niche" class="aips-filter-select">
                <option value=""><?php echo esc_html__('All Niches', 'ai-post-scheduler'); ?></option>
                <?php foreach ($niches as $niche): ?>
                    <option value="<?php echo esc_attr($niche['niche']); ?>">
                        <?php echo esc_html($niche['niche']); ?> (<?php echo esc_html($niche['count']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select id="filter-score" class="aips-filter-select">
                <option value="0"><?php echo esc_html__('All Scores', 'ai-post-scheduler'); ?></option>
                <option value="80"><?php echo esc_html__('Score 80+', 'ai-post-scheduler'); ?></option>
                <option value="90"><?php echo esc_html__('Score 90+', 'ai-post-scheduler'); ?></option>
            </select>
            
            <label>
                <input type="checkbox" id="filter-fresh" value="1">
                <?php echo esc_html__('Fresh Only (Last 7 Days)', 'ai-post-scheduler'); ?>
            </label>
            
            <button type="button" class="button" id="load-topics">
                <?php echo esc_html__('Load Topics', 'ai-post-scheduler'); ?>
            </button>
        </div>
        
        <!-- Topics Table -->
        <div id="topics-container">
            <p class="description"><?php echo esc_html__('Click "Load Topics" to view your research library.', 'ai-post-scheduler'); ?></p>
        </div>
        
        <!-- Bulk Schedule -->
        <div id="bulk-schedule-section" style="display: none; margin-top: 30px;">
            <h3><?php echo esc_html__('Schedule Selected Topics', 'ai-post-scheduler'); ?></h3>
            
            <form id="bulk-schedule-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="schedule-template"><?php echo esc_html__('Template', 'ai-post-scheduler'); ?></label>
                        </th>
                        <td>
                            <select id="schedule-template" name="template_id" required>
                                <option value=""><?php echo esc_html__('Select Template', 'ai-post-scheduler'); ?></option>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo esc_attr($template['id']); ?>">
                                        <?php echo esc_html($template['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="schedule-start-date"><?php echo esc_html__('Start Date', 'ai-post-scheduler'); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" id="schedule-start-date" name="start_date" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="schedule-frequency"><?php echo esc_html__('Frequency', 'ai-post-scheduler'); ?></label>
                        </th>
                        <td>
                            <select id="schedule-frequency" name="frequency">
                                <option value="hourly"><?php echo esc_html__('Hourly', 'ai-post-scheduler'); ?></option>
                                <option value="every_6_hours"><?php echo esc_html__('Every 6 Hours', 'ai-post-scheduler'); ?></option>
                                <option value="every_12_hours"><?php echo esc_html__('Every 12 Hours', 'ai-post-scheduler'); ?></option>
                                <option value="daily" selected><?php echo esc_html__('Daily', 'ai-post-scheduler'); ?></option>
                                <option value="weekly"><?php echo esc_html__('Weekly', 'ai-post-scheduler'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo esc_html__('Schedule Topics', 'ai-post-scheduler'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin-left: 10px;"></span>
                </p>
            </form>
        </div>
    </div>
</div>

<style>
.aips-research {
    max-width: 1200px;
}

.aips-stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0 30px;
}

.aips-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.aips-stat-card h3 {
    margin: 0 0 10px;
    font-size: 32px;
    color: #2271b1;
}

.aips-stat-card p {
    margin: 0;
    color: #646970;
    font-size: 14px;
}

.aips-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.aips-card h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.aips-filters {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.aips-filter-select {
    min-width: 200px;
}

.aips-topics-table {
    width: 100%;
    border-collapse: collapse;
}

.aips-topics-table th,
.aips-topics-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.aips-topics-table th {
    background: #f6f7f7;
    font-weight: 600;
}

.aips-topics-table tbody tr:hover {
    background: #f9f9f9;
}

.aips-score-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 12px;
}

.aips-score-high {
    background: #00a32a;
    color: #fff;
}

.aips-score-medium {
    background: #ffb900;
    color: #000;
}

.aips-score-low {
    background: #999;
    color: #fff;
}

.aips-keywords-list {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.aips-keyword-tag {
    display: inline-block;
    padding: 3px 8px;
    background: #f0f0f1;
    border-radius: 3px;
    font-size: 11px;
}

.aips-topic-actions {
    display: flex;
    gap: 5px;
}

.button.is-loading .spinner {
    visibility: visible;
    float: none;
}
</style>

<script>
jQuery(document).ready(function($) {
    let selectedTopics = [];
    
    // Research form submission
    $('#aips-research-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submit = $('#research-submit');
        const $spinner = $form.find('.spinner');
        
        const niche = $('#research-niche').val();
        const count = $('#research-count').val();
        const keywordsStr = $('#research-keywords').val();
        const keywords = keywordsStr ? keywordsStr.split(',').map(k => k.trim()) : [];
        
        $submit.prop('disabled', true).addClass('is-loading');
        $spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aips_research_topics',
                nonce: $('#aips_nonce').val(),
                niche: niche,
                count: count,
                keywords: keywords
            },
            success: function(response) {
                if (response.success) {
                    displayResearchResults(response.data);
                    $('#load-topics').trigger('click'); // Refresh topics list
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('An error occurred during research.', 'ai-post-scheduler')); ?>');
            },
            complete: function() {
                $submit.prop('disabled', false).removeClass('is-loading');
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Display research results
    function displayResearchResults(data) {
        const $container = $('#research-results-content');
        let html = '<p><strong>' + data.saved_count + ' <?php echo esc_js(__('topics saved for', 'ai-post-scheduler')); ?> "' + data.niche + '"</strong></p>';
        
        if (data.top_topics && data.top_topics.length > 0) {
            html += '<h4><?php echo esc_js(__('Top 5 Topics:', 'ai-post-scheduler')); ?></h4><ol>';
            data.top_topics.forEach(function(topic) {
                const scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
                html += '<li><strong>' + topic.topic + '</strong> ';
                html += '<span class="aips-score-badge aips-score-' + scoreClass + '">' + topic.score + '</span>';
                if (topic.reason) {
                    html += '<br><small><em>' + topic.reason + '</em></small>';
                }
                html += '</li>';
            });
            html += '</ol>';
        }
        
        $container.html(html);
        $('#research-results').slideDown();
    }
    
    // Load topics
    $('#load-topics').on('click', function() {
        const niche = $('#filter-niche').val();
        const minScore = $('#filter-score').val();
        const freshOnly = $('#filter-fresh').is(':checked');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aips_get_trending_topics',
                nonce: $('#aips_nonce').val(),
                niche: niche,
                min_score: minScore,
                fresh_only: freshOnly,
                limit: 50
            },
            success: function(response) {
                if (response.success) {
                    displayTopicsTable(response.data.topics);
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
    // Display topics table
    function displayTopicsTable(topics) {
        if (!topics || topics.length === 0) {
            $('#topics-container').html('<p><?php echo esc_js(__('No topics found matching your filters.', 'ai-post-scheduler')); ?></p>');
            return;
        }
        
        let html = '<table class="aips-topics-table">';
        html += '<thead><tr>';
        html += '<th><input type="checkbox" id="select-all-topics"></th>';
        html += '<th><?php echo esc_js(__('Topic', 'ai-post-scheduler')); ?></th>';
        html += '<th><?php echo esc_js(__('Score', 'ai-post-scheduler')); ?></th>';
        html += '<th><?php echo esc_js(__('Niche', 'ai-post-scheduler')); ?></th>';
        html += '<th><?php echo esc_js(__('Keywords', 'ai-post-scheduler')); ?></th>';
        html += '<th><?php echo esc_js(__('Researched', 'ai-post-scheduler')); ?></th>';
        html += '<th><?php echo esc_js(__('Actions', 'ai-post-scheduler')); ?></th>';
        html += '</tr></thead><tbody>';
        
        topics.forEach(function(topic) {
            const scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
            const keywords = Array.isArray(topic.keywords) ? topic.keywords : [];
            
            html += '<tr>';
            html += '<td><input type="checkbox" class="topic-checkbox" value="' + topic.id + '"></td>';
            html += '<td><strong>' + topic.topic + '</strong>';
            if (topic.reason) {
                html += '<br><small>' + topic.reason + '</small>';
            }
            html += '</td>';
            html += '<td><span class="aips-score-badge aips-score-' + scoreClass + '">' + topic.score + '</span></td>';
            html += '<td>' + topic.niche + '</td>';
            html += '<td><div class="aips-keywords-list">';
            keywords.forEach(function(kw) {
                html += '<span class="aips-keyword-tag">' + kw + '</span>';
            });
            html += '</div></td>';
            html += '<td>' + new Date(topic.researched_at).toLocaleDateString() + '</td>';
            html += '<td><div class="aips-topic-actions">';
            html += '<button class="button button-small delete-topic" data-id="' + topic.id + '"><?php echo esc_js(__('Delete', 'ai-post-scheduler')); ?></button>';
            html += '</div></td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        $('#topics-container').html(html);
        
        // Show bulk schedule section
        $('#bulk-schedule-section').show();
    }
    
    // Select all topics
    $(document).on('change', '#select-all-topics', function() {
        $('.topic-checkbox').prop('checked', $(this).is(':checked'));
        updateSelectedTopics();
    });
    
    // Individual checkbox change
    $(document).on('change', '.topic-checkbox', function() {
        updateSelectedTopics();
    });
    
    // Update selected topics
    function updateSelectedTopics() {
        selectedTopics = $('.topic-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
    }
    
    // Delete topic
    $(document).on('click', '.delete-topic', function() {
        if (!confirm('<?php echo esc_js(__('Delete this topic?', 'ai-post-scheduler')); ?>')) {
            return;
        }
        
        const topicId = $(this).data('id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aips_delete_trending_topic',
                nonce: $('#aips_nonce').val(),
                topic_id: topicId
            },
            success: function(response) {
                if (response.success) {
                    $('#load-topics').trigger('click');
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
    // Bulk schedule
    $('#bulk-schedule-form').on('submit', function(e) {
        e.preventDefault();
        
        if (selectedTopics.length === 0) {
            alert('<?php echo esc_js(__('Please select at least one topic to schedule.', 'ai-post-scheduler')); ?>');
            return;
        }
        
        const $form = $(this);
        const $submit = $form.find('button[type="submit"]');
        const $spinner = $form.find('.spinner');
        
        $submit.prop('disabled', true).addClass('is-loading');
        $spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aips_schedule_trending_topics',
                nonce: $('#aips_nonce').val(),
                topic_ids: selectedTopics,
                template_id: $('#schedule-template').val(),
                start_date: $('#schedule-start-date').val(),
                frequency: $('#schedule-frequency').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    selectedTopics = [];
                    $('.topic-checkbox').prop('checked', false);
                    $('#select-all-topics').prop('checked', false);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('An error occurred during scheduling.', 'ai-post-scheduler')); ?>');
            },
            complete: function() {
                $submit.prop('disabled', false).removeClass('is-loading');
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Auto-load topics on page load
    $('#load-topics').trigger('click');
});
</script>
