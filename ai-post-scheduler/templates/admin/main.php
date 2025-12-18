<div class="wrap aips-wrap">
    <h1><?php echo esc_html__('AI Post Scheduler', 'ai-post-scheduler'); ?></h1>

    <div class="nav-tab-wrapper">
        <a href="#templates" class="nav-tab nav-tab-active" data-tab="templates"><?php echo esc_html__('Templates', 'ai-post-scheduler'); ?></a>
        <a href="#planner" class="nav-tab" data-tab="planner"><?php echo esc_html__('Planner', 'ai-post-scheduler'); ?></a>
        <a href="#schedule" class="nav-tab" data-tab="schedule"><?php echo esc_html__('Schedule', 'ai-post-scheduler'); ?></a>
        <a href="#history" class="nav-tab" data-tab="history"><?php echo esc_html__('History', 'ai-post-scheduler'); ?></a>
        <a href="#settings" class="nav-tab" data-tab="settings"><?php echo esc_html__('Settings', 'ai-post-scheduler'); ?></a>
    </div>

    <!-- Templates Tab -->
    <div id="templates-tab" class="aips-tab-content active">
        <!-- Existing Templates Content -->
        <?php include AIPS_PLUGIN_DIR . 'templates/admin/templates.php'; ?>
    </div>

    <!-- Planner Tab -->
    <div id="planner-tab" class="aips-tab-content" style="display:none;">
        <?php include AIPS_PLUGIN_DIR . 'templates/admin/planner.php'; ?>
    </div>

    <!-- Schedule Tab -->
    <div id="schedule-tab" class="aips-tab-content" style="display:none;">
        <!-- Existing Schedule Content -->
         <?php
         $scheduler = new AIPS_Scheduler();
         $schedules = $scheduler->get_all_schedules();
         include AIPS_PLUGIN_DIR . 'templates/admin/schedule.php';
         ?>
    </div>

    <!-- Other tabs would follow similarly -->
</div>
