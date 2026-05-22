<?php
if (!defined('ABSPATH')) {
	exit;
}

$tabs = array(
	'health'      => __('Health', 'ai-post-scheduler'),
	'performance' => __('Performance', 'ai-post-scheduler'),
	'events'      => __('Events', 'ai-post-scheduler'),
);
?>
<div class="wrap aips-wrap">
	<h1><?php esc_html_e('Observability', 'ai-post-scheduler'); ?></h1>
	<h2 class="nav-tab-wrapper">
		<?php foreach ($tabs as $tab_key => $tab_label) : ?>
			<a class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('observability', array('tab' => $tab_key))); ?>"><?php echo esc_html($tab_label); ?></a>
		<?php endforeach; ?>
	</h2>
</div>
<?php
if ('health' === $tab) {
	$status_handler = new AIPS_System_Status();
	$status_handler->render_page();
	return;
}

if ('performance' === $tab) {
	$controller = new AIPS_Operations_Insights_Controller();
	$controller->render_page();
	return;
}

$telemetry_controller = new AIPS_Telemetry_Controller();
$telemetry_controller->render_page();
