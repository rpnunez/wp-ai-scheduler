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
$interval_calculator = new AIPS_Interval_Calculator();
$default_research_frequency = 'daily';
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'trending';
$valid_tabs = array('trending', 'planner');
if (!in_array($active_tab, $valid_tabs, true)) {
    $active_tab = 'trending';
}
?>

<div class="wrap aips-research">
    <h1><?php echo esc_html__('Research', 'ai-post-scheduler'); ?></h1>

    <div class="nav-tab-wrapper">
        <a href="#trending" class="nav-tab<?php echo $active_tab === 'trending' ? ' nav-tab-active' : ''; ?>" data-tab="trending"><?php echo esc_html__('Trending Topics', 'ai-post-scheduler'); ?></a>
        <a href="#planner" class="nav-tab<?php echo $active_tab === 'planner' ? ' nav-tab-active' : ''; ?>" data-tab="planner"><?php echo esc_html__('Planner', 'ai-post-scheduler'); ?></a>
    </div>

    <div id="trending-tab" class="aips-tab-content<?php echo $active_tab === 'trending' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'trending' ? '' : 'display:none;'; ?>">
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
                    <?php foreach ($niches as $niche):
                        $niche = (object) $niche;
                    ?>
                        <option value="<?php echo esc_attr($niche->niche); ?>">
                            <?php echo esc_html($niche->niche); ?> (<?php echo esc_html($niche->count); ?>)
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
                            <label for="schedule-start-date"><?php echo esc_html__('Start Date', 'ai-post-scheduler'); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" id="schedule-start-date" name="start_date" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__( 'Frequency', 'ai-post-scheduler' ); ?>
                        </th>
                        <td>
                            <?php AIPS_Template_Helper::render_frequency_dropdown( 'schedule-frequency', 'frequency', $default_research_frequency, __( 'Frequency', 'ai-post-scheduler' ) ); ?>
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

    <div id="planner-tab" class="aips-tab-content<?php echo $active_tab === 'planner' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'planner' ? '' : 'display:none;'; ?>">
        <?php include AIPS_PLUGIN_DIR . 'templates/admin/planner.php'; ?>
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
