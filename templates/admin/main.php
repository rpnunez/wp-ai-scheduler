<?php
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'templates';
$valid_tabs = array('templates', 'schedule', 'history');
if (!in_array($active_tab, $valid_tabs, true)) {
    $active_tab = 'templates';
}
?>

<div class="wrap aips-wrap">
    <h1><?php echo esc_html__('AI Post Scheduler', 'ai-post-scheduler'); ?></h1>

    <div class="nav-tab-wrapper">
        <a href="#templates" class="nav-tab<?php echo $active_tab === 'templates' ? ' nav-tab-active' : ''; ?>" data-tab="templates"><?php echo esc_html__('Templates', 'ai-post-scheduler'); ?></a>
        <a href="#schedule" class="nav-tab<?php echo $active_tab === 'schedule' ? ' nav-tab-active' : ''; ?>" data-tab="schedule"><?php echo esc_html__('Schedule', 'ai-post-scheduler'); ?></a>
        <a href="#history" class="nav-tab<?php echo $active_tab === 'history' ? ' nav-tab-active' : ''; ?>" data-tab="history"><?php echo esc_html__('History', 'ai-post-scheduler'); ?></a>
    </div>

    <!-- Templates Tab -->
    <div id="templates-tab" class="aips-tab-content<?php echo $active_tab === 'templates' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'templates' ? '' : 'display:none;'; ?>">
        <!-- Existing Templates Content -->
        <?php include AIPS_PLUGIN_DIR . 'templates/admin/templates.php'; ?>
    </div>

    <!-- Schedule Tab -->
    <div id="schedule-tab" class="aips-tab-content<?php echo $active_tab === 'schedule' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'schedule' ? '' : 'display:none;'; ?>">
        <!-- Existing Schedule Content -->
         <?php
         $scheduler = new AIPS_Scheduler();
         $schedules = $scheduler->get_all_schedules();
         include AIPS_PLUGIN_DIR . 'templates/admin/schedule.php';
         ?>
    </div>

    <!-- History Tab -->
    <div id="history-tab" class="aips-tab-content<?php echo $active_tab === 'history' ? ' active' : ''; ?>" style="<?php echo $active_tab === 'history' ? '' : 'display:none;'; ?>">
        <?php
        //if ( $active_tab === 'history' ) {
            $is_history_tab = true;
            if ( ! isset( $history_base_page ) ) {
                $history_base_page = 'aips-templates';
            }
            if ( ! isset( $history_base_args ) ) {
                $history_base_args = array( 'tab' => 'history' );
            }
            include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
        //}
        ?>
    </div>
</div>
