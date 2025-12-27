<div class="wrap aips-wrap">
    <h1><?php echo esc_html__('AI Post Scheduler', 'ai-post-scheduler'); ?></h1>

    <?php $active_tab = isset($active_tab) ? $active_tab : 'templates'; ?>
    <div class="nav-tab-wrapper">
        <a href="#dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>" data-tab="dashboard"><?php echo esc_html__('Dashboard', 'ai-post-scheduler'); ?></a>
        <a href="#templates" class="nav-tab <?php echo $active_tab === 'templates' ? 'nav-tab-active' : ''; ?>" data-tab="templates"><?php echo esc_html__('Templates', 'ai-post-scheduler'); ?></a>
        <a href="#planner" class="nav-tab <?php echo $active_tab === 'planner' ? 'nav-tab-active' : ''; ?>" data-tab="planner"><?php echo esc_html__('Planner', 'ai-post-scheduler'); ?></a>
        <a href="#schedule" class="nav-tab <?php echo $active_tab === 'schedule' ? 'nav-tab-active' : ''; ?>" data-tab="schedule"><?php echo esc_html__('Schedule', 'ai-post-scheduler'); ?></a>
        <a href="#history" class="nav-tab <?php echo $active_tab === 'history' ? 'nav-tab-active' : ''; ?>" data-tab="history"><?php echo esc_html__('History', 'ai-post-scheduler'); ?></a>
        <a href="#voices" class="nav-tab <?php echo $active_tab === 'voices' ? 'nav-tab-active' : ''; ?>" data-tab="voices"><?php echo esc_html__('Voices', 'ai-post-scheduler'); ?></a>
        <a href="#research" class="nav-tab <?php echo $active_tab === 'research' ? 'nav-tab-active' : ''; ?>" data-tab="research"><?php echo esc_html__('Research', 'ai-post-scheduler'); ?></a>
        <a href="#system-status" class="nav-tab <?php echo $active_tab === 'system-status' ? 'nav-tab-active' : ''; ?>" data-tab="system-status"><?php echo esc_html__('System Status', 'ai-post-scheduler'); ?></a>
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
            if ( is_array( $dashboard_data ) ) {
                $suggestions = isset( $dashboard_data['suggestions'] ) ? $dashboard_data['suggestions'] : array();
                $template_performance = isset( $dashboard_data['template_performance'] ) ? $dashboard_data['template_performance'] : array();
                $automation_settings = isset( $dashboard_data['automation_settings'] ) ? $dashboard_data['automation_settings'] : array();
            }
        }
        include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
        ?>
    </div>

    <!-- Templates Tab -->
    <div id="templates-tab" class="aips-tab-content <?php echo $active_tab === 'templates' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'templates' ? '' : 'display:none;'; ?>">
        <?php
        // Provide commonly needed data to the templates view
        if ( ! isset( $templates ) ) {
            $templates = ( new AIPS_Template_Repository() )->get_all();
        }
        if ( ! isset( $categories ) ) {
            $categories = get_categories();
        }
        if ( ! isset( $users ) ) {
            $users = get_users();
        }
        include AIPS_PLUGIN_DIR . 'templates/admin/templates.php'; ?>
    </div>

    <!-- Planner Tab -->
    <div id="planner-tab" class="aips-tab-content <?php echo $active_tab === 'planner' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'planner' ? '' : 'display:none;'; ?>">
        <?php
        if ( ! isset( $templates ) ) {
            $templates = ( new AIPS_Template_Repository() )->get_all( true );
        }
        include AIPS_PLUGIN_DIR . 'templates/admin/planner.php'; ?>

    </div>

    <!-- Schedule Tab -->
    <div id="schedule-tab" class="aips-tab-content <?php echo $active_tab === 'schedule' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'schedule' ? '' : 'display:none;'; ?>">
        <!-- Existing Schedule Content -->
         <?php
         $scheduler = new AIPS_Scheduler();
         $schedules = $scheduler->get_all_schedules();
         include AIPS_PLUGIN_DIR . 'templates/admin/schedule.php';
         ?>
    </div>

    <!-- History Tab -->
    <div id="history-tab" class="aips-tab-content <?php echo $active_tab === 'history' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'history' ? '' : 'display:none;'; ?>">
        <?php
        if ( ! isset( $history ) && class_exists( 'AIPS_History' ) ) {
            $history_service = new AIPS_History();
            $history = $history_service->get_history(array('per_page' => 20));
            $stats = $history_service->get_stats();
        }
        include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
        ?>
    </div>

    <!-- Voices Tab -->
    <div id="voices-tab" class="aips-tab-content <?php echo $active_tab === 'voices' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'voices' ? '' : 'display:none;'; ?>">
        <?php
        if ( ! isset( $voices ) && class_exists( 'AIPS_Voices' ) ) {
            $voices_service = new AIPS_Voices();
            $voices = $voices_service->get_all();
        }
        include AIPS_PLUGIN_DIR . 'templates/admin/voices.php';
        ?>
    </div>

    <!-- Research Tab -->
    <div id="research-tab" class="aips-tab-content <?php echo $active_tab === 'research' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'research' ? '' : 'display:none;'; ?>">
        <?php
        if ( ! isset( $templates ) ) {
            $templates = (new AIPS_Template_Repository())->get_all(true);
        }
        include AIPS_PLUGIN_DIR . 'templates/admin/research.php';
        ?>
    </div>

    <!-- System Status Tab -->
    <div id="system-status-tab" class="aips-tab-content <?php echo $active_tab === 'system-status' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'system-status' ? '' : 'display:none;'; ?>">
        <?php
        if ( ! isset( $system_info ) && class_exists( 'AIPS_System_Status' ) ) {
            $status = new AIPS_System_Status();
            $system_info = $status->get_checks();
        }
        include AIPS_PLUGIN_DIR . 'templates/admin/system-status.php';
        ?>
    </div>

    <!-- Settings Tab -->
    <div id="settings-tab" class="aips-tab-content <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'settings' ? '' : 'display:none;'; ?>">
        <?php include AIPS_PLUGIN_DIR . 'templates/admin/settings.php'; ?>
    </div>

    <!-- Other tabs would follow similarly -->
</div>
