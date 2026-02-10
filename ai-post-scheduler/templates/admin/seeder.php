<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap aips-redesign">
<div class="aips-page-container">
<!-- Page Header -->
<div class="aips-page-header">
<div class="aips-page-header-top">
<div>
<h1 class="aips-page-title"><?php esc_html_e('Database Seeder', 'ai-post-scheduler'); ?></h1>
<p class="aips-page-description"><?php esc_html_e('Generate test data to quickly populate your database with AI-generated templates, schedules, and content for testing purposes.', 'ai-post-scheduler'); ?></p>
</div>
</div>
</div>

<!-- Content Panel -->
<div class="aips-content-panel">
<div class="aips-panel-body">
<form id="aips-seeder-form">
<div class="aips-form-section">
<h3 class="aips-form-section-title">
<span class="dashicons dashicons-admin-generic"></span>
<?php esc_html_e('Seed Configuration', 'ai-post-scheduler'); ?>
</h3>

<div class="aips-form-row">
<label for="seeder-keywords"><?php esc_html_e('Seeder Keywords', 'ai-post-scheduler'); ?></label>
<textarea id="seeder-keywords" name="keywords" rows="3" class="aips-form-input" placeholder="<?php esc_attr_e('e.g., technology, fitness, vegan recipes', 'ai-post-scheduler'); ?>"></textarea>
<p class="aips-field-description"><?php esc_html_e('Comma-separated keywords to guide the AI generation (optional).', 'ai-post-scheduler'); ?></p>
</div>

<div class="aips-form-grid aips-form-grid-2">
<div class="aips-form-row">
<label for="seeder-voices"><?php esc_html_e('Voices', 'ai-post-scheduler'); ?></label>
<input type="number" id="seeder-voices" name="voices" value="0" min="0" max="50" class="aips-form-input">
<p class="aips-field-description"><?php esc_html_e('Number of personas to create.', 'ai-post-scheduler'); ?></p>
</div>

<div class="aips-form-row">
<label for="seeder-templates"><?php esc_html_e('Templates', 'ai-post-scheduler'); ?></label>
<input type="number" id="seeder-templates" name="templates" value="0" min="0" max="50" class="aips-form-input">
<p class="aips-field-description"><?php esc_html_e('Number of post templates to create.', 'ai-post-scheduler'); ?></p>
</div>

<div class="aips-form-row">
<label for="seeder-schedule"><?php esc_html_e('Scheduled Templates', 'ai-post-scheduler'); ?></label>
<input type="number" id="seeder-schedule" name="schedule" value="0" min="0" max="100" class="aips-form-input">
<p class="aips-field-description"><?php esc_html_e('Recurring schedules (Daily, Weekly, etc.) using existing templates.', 'ai-post-scheduler'); ?></p>
</div>

<div class="aips-form-row">
<label for="seeder-planner"><?php esc_html_e('Planner Entries', 'ai-post-scheduler'); ?></label>
<input type="number" id="seeder-planner" name="planner" value="0" min="0" max="100" class="aips-form-input">
<p class="aips-field-description"><?php esc_html_e('One-time scheduled posts with specific topics (simulating Planner).', 'ai-post-scheduler'); ?></p>
</div>
</div>

<div class="aips-form-actions">
<button type="submit" id="aips-seeder-submit" class="aips-btn aips-btn-primary aips-btn-lg">
<span class="dashicons dashicons-database-add"></span>
<?php esc_html_e('Run Seeder', 'ai-post-scheduler'); ?>
</button>
<span class="spinner" style="float: none; margin-top: 4px;"></span>
</div>
</div>
</form>
</div>
</div>

<!-- Seeder Log -->
<div id="aips-seeder-results" class="aips-content-panel" style="margin-top: 20px; display: none;">
<div class="aips-panel-header">
<h3><?php esc_html_e('Seeder Log', 'ai-post-scheduler'); ?></h3>
</div>
<div class="aips-panel-body">
<div id="aips-seeder-log" style="background: #f0f0f1; padding: 15px; border: 1px solid #c3c4c7; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 13px; line-height: 1.6;"></div>
</div>
</div>
</div>
</div>
