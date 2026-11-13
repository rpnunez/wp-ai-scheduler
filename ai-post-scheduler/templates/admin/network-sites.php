<?php
/**
 * Multisite Network Sites Table Template
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var AIPS_Network_Sites_List_Table $list_table Loaded in AIPS_Network_Admin_Menu */
?>
<div class="wrap aips-wrap">
	<h1><?php esc_html_e('AI Post Scheduler - Network Sites', 'ai-post-scheduler'); ?></h1>
	<p class="description">
		<?php esc_html_e('View the operational status, schema initialization, AI credentials mode, and quota usage for each site on the network.', 'ai-post-scheduler'); ?>
	</p>

	<form method="get">
		<input type="hidden" name="page" value="aips-network-sites" />
		<?php $list_table->display(); ?>
	</form>
</div>
