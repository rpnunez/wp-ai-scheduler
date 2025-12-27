<div class="wrap aips-wrap">
    <h1><?php echo esc_html__('AI Post Scheduler', 'ai-post-scheduler'); ?></h1>

    <?php $active_tab = isset($active_tab) ? $active_tab : 'templates'; ?>
    <div class="nav-tab-wrapper">
        <a href="#dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>" data-tab="dashboard"><?php echo esc_html__('Dashboard', 'ai-post-scheduler'); ?></a>
        <a href="#templates" class="nav-tab <?php echo $active_tab === 'templates' ? 'nav-tab-active' : ''; ?>" data-tab="templates"><?php echo esc_html__('Templates', 'ai-post-scheduler'); ?></a>
        <a href="#planner" class="nav-tab <?php echo $active_tab === 'planner' ? 'nav-tab-active' : ''; ?>" data-tab="planner"><?php echo esc_html__('Planner', 'ai-post-scheduler'); ?></a>
        <a href="#schedule" class="nav-tab <?php echo $active_tab === 'schedule' ? 'nav-tab-active' : ''; ?>" data-tab="schedule"><?php echo esc_html__('Schedule', 'ai-post-scheduler'); ?></a>
        <a href="#history" class="nav-tab <?php echo $active_tab === 'history' ? 'nav-tab-active' : ''; ?>" data-tab="history"><?php echo esc_html__('History', 'ai-post-scheduler'); ?></a>
        <a href="#settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>" data-tab="settings"><?php echo esc_html__('Settings', 'ai-post-scheduler'); ?></a>
    </div>

    <!-- Dashboard Tab -->
    <div id="dashboard-tab" class="aips-tab-content <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'dashboard' ? '' : 'display:none;'; ?>">
        <?php
        // Ensure we have data even if loaded via main wrapper
        if ( ! isset( $stats ) && class_exists( 'AIPS_Dashboard' ) ) {
            $dashboard_service = new AIPS_Dashboard();
            $dashboard_data    = $dashboard_service->get_dashboard_data();

            if ( is_array( $dashboard_data ) && array_key_exists( 'stats', $dashboard_data ) && ! isset( $stats ) ) {
                $stats = $dashboard_data['stats'];
            }
        }
        include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
        ?>
    </div>

    <!-- Templates Tab -->
    <div id="templates-tab" class="aips-tab-content <?php echo $active_tab === 'templates' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'templates' ? '' : 'display:none;'; ?>">
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
