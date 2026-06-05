<?php
if (!defined('ABSPATH')) {
	exit;
}

$tabs = array(
	'overview' => __('Overview', 'ai-post-scheduler'),
	'entries' => __('Entries', 'ai-post-scheduler'),
	'tags' => __('Tags', 'ai-post-scheduler'),
	'domains' => __('Domains', 'ai-post-scheduler'),
	'operations' => __('Operations', 'ai-post-scheduler'),
	'events' => __('Events', 'ai-post-scheduler'),
	'driver' => __('Driver', 'ai-post-scheduler'),
	'maintenance' => __('Maintenance', 'ai-post-scheduler'),
);
$base_url = admin_url('admin.php?page=aips-cache-monitor');

$format_timestamp = function( $timestamp ) {
	$timestamp = (int) $timestamp;
	return $timestamp > 0 ? esc_html( wp_date( 'Y-m-d H:i:s', $timestamp ) ) : esc_html__( 'Never', 'ai-post-scheduler' );
};
$format_bytes = function( $bytes ) {
	$bytes = (int) $bytes;
	if (function_exists('size_format')) {
		return size_format( $bytes );
	}
	return number_format_i18n( $bytes ) . ' B';
};
$action_url = admin_url('admin-post.php');
?>
<div class="wrap aips-cache-monitor-wrap">
	<h1><?php esc_html_e('Cache Monitor', 'ai-post-scheduler'); ?></h1>
	<p class="description"><?php esc_html_e('Inspect plugin-owned cache metadata, understand driver limitations, preview values safely, and perform guarded invalidation actions.', 'ai-post-scheduler'); ?></p>

	<?php if (!empty($notice)) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Cache Monitor tabs', 'ai-post-scheduler'); ?>">
		<?php foreach ($tabs as $tab_key => $tab_label) : ?>
			<a class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab', $tab_key, $base_url)); ?>"><?php echo esc_html($tab_label); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ($tab === 'overview') : ?>
		<div class="aips-status-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:16px;">
			<?php
			$cards = array(
				__('Cache Enabled', 'ai-post-scheduler') => $summary['enabled'] ? __('Yes', 'ai-post-scheduler') : __('No', 'ai-post-scheduler'),
				__('Active Driver', 'ai-post-scheduler') => $summary['active_driver'],
				__('Indexed Entries', 'ai-post-scheduler') => number_format_i18n($summary['total_indexed_entries']),
				__('Estimated Size', 'ai-post-scheduler') => $format_bytes($summary['total_estimated_size']),
				__('Expired Entries', 'ai-post-scheduler') => number_format_i18n($summary['expired_indexed_entries']),
				__('Hit Ratio', 'ai-post-scheduler') => $summary['hit_ratio'] . '%',
				__('Bypasses', 'ai-post-scheduler') => number_format_i18n($summary['bypass_count']),
				__('Last Flush', 'ai-post-scheduler') => $summary['last_plugin_cache_flush'] ? wp_date('Y-m-d H:i', $summary['last_plugin_cache_flush']) : __('Never', 'ai-post-scheduler'),
			);
			foreach ($cards as $label => $value) : ?>
				<div class="postbox" style="padding:12px;"><strong><?php echo esc_html($label); ?></strong><p style="font-size:20px;margin:.4em 0 0;"><?php echo esc_html($value); ?></p></div>
			<?php endforeach; ?>
		</div>

		<h2><?php esc_html_e('Entries by Group', 'ai-post-scheduler'); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Group', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Entries', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Size', 'ai-post-scheduler'); ?></th></tr></thead><tbody>
		<?php foreach ($summary['entries_by_group'] as $row) : ?>
			<tr><td><?php echo esc_html($row['item_key'] ?: __('(none)', 'ai-post-scheduler')); ?></td><td><?php echo esc_html(number_format_i18n($row['total'])); ?></td><td><?php echo esc_html($format_bytes($row['size_bytes'])); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>

		<h2><?php esc_html_e('Largest Indexed Entries', 'ai-post-scheduler'); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Key Hash', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Group', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Operation', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Size', 'ai-post-scheduler'); ?></th></tr></thead><tbody>
		<?php foreach ($summary['largest_entries'] as $entry) : ?>
			<tr><td><code><?php echo esc_html($entry['key_hash']); ?></code></td><td><?php echo esc_html($entry['cache_group']); ?></td><td><?php echo esc_html($entry['operation_id']); ?></td><td><?php echo esc_html($format_bytes($entry['estimated_size'])); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>

	<?php if ($tab === 'entries') : ?>
		<h2><?php esc_html_e('Cache Entries', 'ai-post-scheduler'); ?></h2>
		<form method="get" style="margin:12px 0;">
			<input type="hidden" name="page" value="aips-cache-monitor"><input type="hidden" name="tab" value="entries">
			<input type="search" name="search" value="<?php echo isset($_GET['search']) ? esc_attr(wp_unslash($_GET['search'])) : ''; ?>" placeholder="<?php esc_attr_e('Search key hash, group, operation, tag', 'ai-post-scheduler'); ?>">
			<input type="text" name="group" value="<?php echo isset($_GET['group']) ? esc_attr(wp_unslash($_GET['group'])) : ''; ?>" placeholder="<?php esc_attr_e('Group', 'ai-post-scheduler'); ?>">
			<input type="text" name="tier" value="<?php echo isset($_GET['tier']) ? esc_attr(wp_unslash($_GET['tier'])) : ''; ?>" placeholder="<?php esc_attr_e('Tier', 'ai-post-scheduler'); ?>">
			<input type="text" name="operation_id" value="<?php echo isset($_GET['operation_id']) ? esc_attr(wp_unslash($_GET['operation_id'])) : ''; ?>" placeholder="<?php esc_attr_e('Operation ID', 'ai-post-scheduler'); ?>">
			<input type="text" name="repository" value="<?php echo isset($_GET['repository']) ? esc_attr(wp_unslash($_GET['repository'])) : ''; ?>" placeholder="<?php esc_attr_e('Repository', 'ai-post-scheduler'); ?>">
			<input type="text" name="tag" value="<?php echo isset($_GET['tag']) ? esc_attr(wp_unslash($_GET['tag'])) : ''; ?>" placeholder="<?php esc_attr_e('Tag', 'ai-post-scheduler'); ?>">
			<select name="ttl_state"><option value=""><?php esc_html_e('Any TTL', 'ai-post-scheduler'); ?></option><?php foreach (array('active','expired','none') as $state) : ?><option value="<?php echo esc_attr($state); ?>" <?php selected(isset($_GET['ttl_state']) ? wp_unslash($_GET['ttl_state']) : '', $state); ?>><?php echo esc_html(ucfirst($state)); ?></option><?php endforeach; ?></select>
			<button class="button"><?php esc_html_e('Filter', 'ai-post-scheduler'); ?></button>
		</form>

		<?php if ($inspect) : ?>
			<div class="postbox" style="padding:16px;"><h2><?php esc_html_e('Safe Value Preview', 'ai-post-scheduler'); ?></h2>
				<p><strong><?php esc_html_e('Key Hash:', 'ai-post-scheduler'); ?></strong> <code><?php echo esc_html($inspect['key_hash']); ?></code></p>
				<p><strong><?php esc_html_e('Group:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html($inspect['cache_group']); ?> <strong><?php esc_html_e('Driver:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html($inspect['driver']); ?> <strong><?php esc_html_e('Type:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html($inspect['value_type']); ?></p>
				<pre style="white-space:pre-wrap;max-height:420px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;"><?php echo esc_html(wp_json_encode($inspect['safe_preview'], JSON_PRETTY_PRINT)); ?></pre>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url($action_url); ?>">
			<input type="hidden" name="action" value="aips_cache_monitor_action"><input type="hidden" name="monitor_action" value="delete_selected">
			<?php wp_nonce_field(AIPS_Cache_Monitor_Controller::NONCE_ACTION, '_aips_cache_monitor_nonce'); ?>
			<p><button class="button" onclick="return confirm('<?php echo esc_js(__('Delete selected cache entries?', 'ai-post-scheduler')); ?>');"><?php esc_html_e('Delete Selected', 'ai-post-scheduler'); ?></button></p>
			<table class="widefat striped"><thead><tr><th></th><th><?php esc_html_e('Key Hash', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Group', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Operation', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Tier', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Driver', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Tags', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Size', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('TTL', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Expires', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th></tr></thead><tbody>
			<?php foreach ($entries['items'] as $entry) : ?>
				<tr>
					<td><input type="checkbox" name="selected_entries[]" value="<?php echo esc_attr($entry['key_hash'] . '|' . $entry['cache_group']); ?>"></td>
					<td><code><?php echo esc_html($entry['key_hash']); ?></code></td><td><?php echo esc_html($entry['cache_group']); ?></td><td><?php echo esc_html($entry['operation_id']); ?></td><td><?php echo esc_html($entry['tier']); ?></td><td><?php echo esc_html($entry['driver']); ?></td><td><?php echo esc_html(implode(', ', (array) $entry['tags'])); ?></td><td><?php echo esc_html($entry['value_type']); ?></td><td><?php echo esc_html($format_bytes($entry['estimated_size'])); ?></td><td><?php echo $entry['ttl_remaining'] === null ? esc_html__('No expiration', 'ai-post-scheduler') : esc_html(number_format_i18n($entry['ttl_remaining']) . 's'); ?></td><td><?php echo $format_timestamp($entry['expires_at']); ?></td>
					<td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('tab'=>'entries','inspect'=>$entry['key_hash'],'group'=>$entry['cache_group']), $base_url)); ?>"><?php esc_html_e('Inspect', 'ai-post-scheduler'); ?></a> <button form="delete-entry-<?php echo esc_attr($entry['key_hash']); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Delete this cache entry?', 'ai-post-scheduler')); ?>');"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		</form>
		<?php foreach ($entries['items'] as $entry) : ?><form id="delete-entry-<?php echo esc_attr($entry['key_hash']); ?>" method="post" action="<?php echo esc_url($action_url); ?>"><input type="hidden" name="action" value="aips_cache_monitor_action"><input type="hidden" name="monitor_action" value="delete_entry"><input type="hidden" name="key_hash" value="<?php echo esc_attr($entry['key_hash']); ?>"><input type="hidden" name="group" value="<?php echo esc_attr($entry['cache_group']); ?>"><?php wp_nonce_field(AIPS_Cache_Monitor_Controller::NONCE_ACTION, '_aips_cache_monitor_nonce'); ?></form><?php endforeach; ?>
	<?php endif; ?>

	<?php if ($tab === 'tags') : ?>
		<h2><?php esc_html_e('Cache Tags', 'ai-post-scheduler'); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Tag', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Version', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Entries', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Size', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Last Invalidated', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th></tr></thead><tbody>
		<?php foreach ($tags as $tag_row) : ?>
			<tr><td><?php echo esc_html($tag_row['tag']); ?></td><td><?php echo esc_html($tag_row['version']); ?></td><td><?php echo esc_html(number_format_i18n($tag_row['entry_count'])); ?></td><td><?php echo esc_html($format_bytes($tag_row['estimated_size'])); ?></td><td><?php echo $format_timestamp($tag_row['last_invalidated']); ?></td><td><form method="post" action="<?php echo esc_url($action_url); ?>"><?php wp_nonce_field(AIPS_Cache_Monitor_Controller::NONCE_ACTION, '_aips_cache_monitor_nonce'); ?><input type="hidden" name="action" value="aips_cache_monitor_action"><input type="hidden" name="monitor_action" value="invalidate_tag"><input type="hidden" name="tag" value="<?php echo esc_attr($tag_row['tag']); ?>"><button class="button button-small"><?php esc_html_e('Invalidate', 'ai-post-scheduler'); ?></button></form></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>

	<?php if ($tab === 'domains') : ?>
		<h2><?php esc_html_e('Dependency Domains', 'ai-post-scheduler'); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Domain', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Affected Tags', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Operation IDs', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th></tr></thead><tbody>
		<?php foreach ($domains as $domain_row) : ?>
			<tr><td><?php echo esc_html($domain_row['domain']); ?></td><td><?php echo esc_html(implode(', ', $domain_row['tags'])); ?></td><td><?php echo esc_html(implode(', ', array_slice($domain_row['operation_ids'], 0, 8))); ?></td><td><form method="post" action="<?php echo esc_url($action_url); ?>"><?php wp_nonce_field(AIPS_Cache_Monitor_Controller::NONCE_ACTION, '_aips_cache_monitor_nonce'); ?><input type="hidden" name="action" value="aips_cache_monitor_action"><input type="hidden" name="monitor_action" value="invalidate_domain"><input type="hidden" name="domain" value="<?php echo esc_attr($domain_row['domain']); ?>"><button class="button button-small"><?php esc_html_e('Invalidate Domain', 'ai-post-scheduler'); ?></button></form></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>

	<?php if ($tab === 'operations') : ?>
		<h2><?php esc_html_e('Repository Operation Analytics', 'ai-post-scheduler'); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Operation ID', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Repository', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Tier', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('TTL', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Hits', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Misses', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Hit Ratio', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Bypasses', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Stale', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Avg Rebuild', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Entries', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Size', 'ai-post-scheduler'); ?></th></tr></thead><tbody>
		<?php foreach ($operations as $operation) : ?>
			<tr><td><?php echo esc_html($operation['operation_id']); ?></td><td><?php echo esc_html($operation['repository_class']); ?></td><td><?php echo esc_html($operation['tier']); ?></td><td><?php echo esc_html($operation['ttl']); ?></td><td><?php echo esc_html(number_format_i18n($operation['hit_count'])); ?></td><td><?php echo esc_html(number_format_i18n($operation['miss_count'])); ?></td><td><?php echo esc_html($operation['hit_ratio'] . '%'); ?></td><td><?php echo esc_html(number_format_i18n($operation['bypass_count'])); ?></td><td><?php echo esc_html(number_format_i18n($operation['stale_count'])); ?></td><td><?php echo esc_html($operation['average_rebuild_ms'] . ' ms'); ?></td><td><?php echo esc_html(number_format_i18n($operation['indexed_entry_count'])); ?></td><td><?php echo esc_html($format_bytes($operation['estimated_size'])); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>

	<?php if ($tab === 'events') : ?>
		<h2><?php esc_html_e('Cache Event Log', 'ai-post-scheduler'); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Time', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Event', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('User', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Group', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Key Hash', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Tags/Domain', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Affected', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Message', 'ai-post-scheduler'); ?></th></tr></thead><tbody>
		<?php foreach ($events as $event) : ?>
			<tr><td><?php echo $format_timestamp($event['created_at']); ?></td><td><?php echo esc_html($event['event_type']); ?></td><td><?php echo esc_html($event['user_id']); ?></td><td><?php echo esc_html($event['cache_group']); ?></td><td><code><?php echo esc_html($event['key_hash']); ?></code></td><td><?php echo esc_html(implode(', ', (array) $event['tags']) . ($event['domain'] ? ' / ' . $event['domain'] : '')); ?></td><td><?php echo esc_html(number_format_i18n($event['affected_count'])); ?></td><td><?php echo esc_html($event['message']); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>

	<?php if ($tab === 'driver') : ?>
		<h2><?php esc_html_e('Driver Status', 'ai-post-scheduler'); ?></h2>
		<p><strong><?php esc_html_e('Driver:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html($driver_status['name']); ?> <code><?php echo esc_html($driver_status['class']); ?></code></p>
		<h3><?php esc_html_e('Capabilities', 'ai-post-scheduler'); ?></h3>
		<table class="widefat striped"><tbody><?php foreach ($driver_status['capabilities'] as $capability => $enabled) : ?><tr><td><?php echo esc_html($capability); ?></td><td><?php echo $enabled ? esc_html__('Supported', 'ai-post-scheduler') : esc_html__('Limited / unavailable', 'ai-post-scheduler'); ?></td></tr><?php endforeach; ?></tbody></table>
		<h3><?php esc_html_e('Limitations', 'ai-post-scheduler'); ?></h3>
		<ul><?php foreach ($driver_status['limitations'] as $limitation) : ?><li><?php echo esc_html($limitation); ?></li><?php endforeach; ?></ul>
		<h3><?php esc_html_e('Storage Stats', 'ai-post-scheduler'); ?></h3>
		<pre><?php echo esc_html(wp_json_encode($driver_status['storage_stats'], JSON_PRETTY_PRINT)); ?></pre>
	<?php endif; ?>

	<?php if ($tab === 'maintenance') : ?>
		<h2><?php esc_html_e('Maintenance Tools', 'ai-post-scheduler'); ?></h2>
		<table class="widefat striped"><tbody>
		<?php foreach (array('prune_expired' => __('Prune expired cache entries', 'ai-post-scheduler'), 'rebuild_index' => __('Rebuild index from DB cache rows', 'ai-post-scheduler'), 'validate_policies' => __('Validate cache policy definitions', 'ai-post-scheduler'), 'compact_metrics' => __('Compact cache metrics', 'ai-post-scheduler')) as $maintenance_action => $label) : ?>
			<tr><td><?php echo esc_html($label); ?></td><td><form method="post" action="<?php echo esc_url($action_url); ?>"><?php wp_nonce_field(AIPS_Cache_Monitor_Controller::NONCE_ACTION, '_aips_cache_monitor_nonce'); ?><input type="hidden" name="action" value="aips_cache_monitor_action"><input type="hidden" name="monitor_action" value="maintenance"><input type="hidden" name="maintenance_action" value="<?php echo esc_attr($maintenance_action); ?>"><button class="button"><?php esc_html_e('Run', 'ai-post-scheduler'); ?></button></form></td></tr>
		<?php endforeach; ?>
			<tr><td><?php esc_html_e('Flush all plugin cache', 'ai-post-scheduler'); ?></td><td><form method="post" action="<?php echo esc_url($action_url); ?>"><?php wp_nonce_field(AIPS_Cache_Monitor_Controller::NONCE_ACTION, '_aips_cache_monitor_nonce'); ?><input type="hidden" name="action" value="aips_cache_monitor_action"><input type="hidden" name="monitor_action" value="flush_all"><label><input type="checkbox" name="confirm_flush_all" value="1"> <?php esc_html_e('I understand this clears all plugin-owned cache entries.', 'ai-post-scheduler'); ?></label> <button class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Flush all plugin cache?', 'ai-post-scheduler')); ?>');"><?php esc_html_e('Flush All', 'ai-post-scheduler'); ?></button></form></td></tr>
			<tr><td><?php esc_html_e('Reset cache index metadata', 'ai-post-scheduler'); ?></td><td><form method="post" action="<?php echo esc_url($action_url); ?>"><?php wp_nonce_field(AIPS_Cache_Monitor_Controller::NONCE_ACTION, '_aips_cache_monitor_nonce'); ?><input type="hidden" name="action" value="aips_cache_monitor_action"><input type="hidden" name="monitor_action" value="reset_index"><label><input type="checkbox" name="confirm_reset_index" value="1"> <?php esc_html_e('I understand this removes monitor metadata only.', 'ai-post-scheduler'); ?></label> <button class="button button-secondary"><?php esc_html_e('Reset Index', 'ai-post-scheduler'); ?></button></form></td></tr>
		</tbody></table>
	<?php endif; ?>
</div>
