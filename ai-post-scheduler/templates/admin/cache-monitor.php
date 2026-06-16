<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Cache Monitor admin template.
 *
 * Variables available:
 *   $summary         (array)  – Overview data from AIPS_Cache_Monitor_Service::get_summary()
 *   $nonce           (string) – wp_create_nonce('aips_cache_monitor')
 *   $dev_mode        (bool)   – Whether developer mode is enabled
 *   $monitor_enabled (bool)   – Whether the Cache Monitor is enabled
 *   $active_tab      (string) – Currently selected tab slug
 *   $embedded        (bool)   – Whether rendered inside Diagnostics tab
 */

if (!function_exists('aips_cache_monitor_format_bytes')) {
	function aips_cache_monitor_format_bytes( int $bytes ): string {
		if ($bytes < 1024) return $bytes . ' B';
		if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
		return round($bytes / 1048576, 2) . ' MB';
	}
}

$tabs = array(
	'overview'    => __('Overview', 'ai-post-scheduler'),
	'entries'     => __('Entries', 'ai-post-scheduler'),
	'tags'        => __('Tags', 'ai-post-scheduler'),
	'domains'     => __('Domains', 'ai-post-scheduler'),
	'operations'  => __('Operations', 'ai-post-scheduler'),
	'events'      => __('Events', 'ai-post-scheduler'),
	'driver'      => __('Driver', 'ai-post-scheduler'),
	'maintenance' => __('Maintenance', 'ai-post-scheduler'),
);

$is_embedded = !empty($embedded);
$tab_query_key = $is_embedded ? 'cache_tab' : 'tab';

$action_nonce = wp_create_nonce('aips_cache_monitor_action');
?>
<?php if (!$is_embedded) : ?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
<?php endif; ?>

		<!-- Page Header -->
		<?php if (!$is_embedded) : ?>
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title">
						<span class="dashicons dashicons-database-view" style="font-size:30px;vertical-align:middle;margin-right:6px;" aria-hidden="true"></span>
						<?php esc_html_e('Cache Monitor', 'ai-post-scheduler'); ?>
					</h1>
					<p class="aips-page-description"><?php esc_html_e('Inspect, manage, and maintain the plugin cache. View entries, tags, domains, and operation metrics.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>
		<?php endif; ?>

			<div class="aips-content-panel">
				<div class="aips-panel-header">
					<h2>
						<?php if ($is_embedded) : ?>
							<?php esc_html_e('Cache Monitor', 'ai-post-scheduler'); ?>
						<?php else : ?>
							<?php esc_html_e('Cache Monitor Tabs', 'ai-post-scheduler'); ?>
						<?php endif; ?>
					</h2>
					<div class="aips-btn-group">
						<button type="button" class="aips-btn aips-btn-secondary aips-cache-monitor-refresh" data-nonce="<?php echo esc_attr($nonce); ?>">
							<span class="dashicons dashicons-update" aria-hidden="true"></span>
							<?php esc_html_e('Refresh', 'ai-post-scheduler'); ?>
						</button>
						<?php if (!$monitor_enabled): ?>
							<span class="aips-badge aips-badge-warning"><?php esc_html_e('Monitor Disabled', 'ai-post-scheduler'); ?></span>
						<?php endif; ?>
						<?php if ($dev_mode): ?>
							<span class="aips-badge aips-badge-info"><?php esc_html_e('Dev Mode', 'ai-post-scheduler'); ?></span>
						<?php endif; ?>
					</div>
				</div>
				<div class="aips-panel-body">

					<!-- Tab navigation -->
					<div class="aips-tab-nav">
						<ul class="aips-tab-list">
							<?php foreach ($tabs as $tab_slug => $tab_label): ?>
								<li class="aips-tab-item">
									<?php
									$link_args = $is_embedded
										? array('page' => 'aips-diagnostics', 'tab' => 'cache-monitor', $tab_query_key => $tab_slug)
										: array('page' => 'aips-cache-monitor', $tab_query_key => $tab_slug);
									?>
									<a href="<?php echo esc_url(add_query_arg($link_args, admin_url('admin.php'))); ?>"
								   class="aips-tab-link nav-tab<?php echo $active_tab === $tab_slug ? ' nav-tab-active' : ''; ?>">
								<?php echo esc_html($tab_label); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
		</div>

		<!-- Tab Content -->
		<div class="aips-cache-monitor-content">

			<?php /* ===================== OVERVIEW TAB ===================== */ ?>
			<?php if ($active_tab === 'overview'): ?>
			<div class="aips-tab-content" data-tab="overview">

				<?php
				$cache_enabled = $summary['cache_enabled'] ?? false;
				$driver_name   = $summary['driver_name'] ?? 'unknown';
				$driver_label  = $summary['driver_label'] ?? $driver_name;
				$index         = $summary['index'] ?? array();
				$driver_size   = $summary['driver_size'] ?? array();
				$caps          = $summary['capabilities'] ?? array();
				$last_flush    = $summary['last_flush_ts'] ?? null;
				?>

				<!-- Health Cards -->
				<div class="aips-stats-grid aips-cache-monitor-cards">

					<div class="aips-stat-card <?php echo $cache_enabled ? 'aips-stat-card--success' : 'aips-stat-card--error'; ?>">
						<div class="aips-stat-label"><?php esc_html_e('Cache Status', 'ai-post-scheduler'); ?></div>
						<div class="aips-stat-value">
							<?php if ($cache_enabled): ?>
								<span class="aips-badge aips-badge-success"><?php esc_html_e('Enabled', 'ai-post-scheduler'); ?></span>
							<?php else: ?>
								<span class="aips-badge aips-badge-error"><?php esc_html_e('Disabled', 'ai-post-scheduler'); ?></span>
							<?php endif; ?>
						</div>
					</div>

					<div class="aips-stat-card">
						<div class="aips-stat-label"><?php esc_html_e('Active Driver', 'ai-post-scheduler'); ?></div>
						<div class="aips-stat-value"><?php echo esc_html($driver_label); ?></div>
						<div class="aips-stat-meta"><?php echo esc_html($driver_name); ?></div>
					</div>

					<div class="aips-stat-card">
						<div class="aips-stat-label"><?php esc_html_e('Indexed Entries', 'ai-post-scheduler'); ?></div>
						<div class="aips-stat-value"><?php echo esc_html(number_format_i18n($index['total_entries'] ?? 0)); ?></div>
						<?php if (!empty($index['expired_count'])): ?>
							<div class="aips-stat-meta aips-text-warning">
								<?php
								echo esc_html(sprintf(
									/* translators: %d: expired count */
									_n('%d expired', '%d expired', $index['expired_count'], 'ai-post-scheduler'),
									$index['expired_count']
								));
								?>
							</div>
						<?php endif; ?>
					</div>

					<div class="aips-stat-card">
						<div class="aips-stat-label"><?php esc_html_e('Est. Cache Size', 'ai-post-scheduler'); ?></div>
						<div class="aips-stat-value">
							<?php
							$total_size = $index['total_size'] ?? 0;
							echo esc_html(aips_cache_monitor_format_bytes($total_size));
							?>
						</div>
					</div>

					<div class="aips-stat-card">
						<div class="aips-stat-label"><?php esc_html_e('Last Flush', 'ai-post-scheduler'); ?></div>
						<div class="aips-stat-value">
							<?php if ($last_flush): ?>
								<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_flush)); ?>
							<?php else: ?>
								<span class="aips-text-muted"><?php esc_html_e('Never', 'ai-post-scheduler'); ?></span>
							<?php endif; ?>
						</div>
					</div>

				</div><!-- /.aips-stats-grid -->

				<!-- Driver Capabilities -->
				<?php if (!empty($caps)): ?>
				<div class="aips-content-panel">
					<div class="aips-panel-header">
						<h2><?php esc_html_e('Driver Capabilities', 'ai-post-scheduler'); ?></h2>
					</div>
					<div class="aips-panel-body">
						<div class="aips-capability-grid">
							<?php
							$cap_labels = array(
								'list_keys'     => __('Key Listing', 'ai-post-scheduler'),
								'inspect_entry' => __('Entry Inspection', 'ai-post-scheduler'),
								'delete_key'    => __('Delete Entry', 'ai-post-scheduler'),
								'delete_group'  => __('Delete Group', 'ai-post-scheduler'),
								'flush_plugin'  => __('Flush Plugin', 'ai-post-scheduler'),
								'size_bytes'    => __('Size Estimate', 'ai-post-scheduler'),
								'ttl_remaining' => __('TTL Info', 'ai-post-scheduler'),
								'tag_versions'  => __('Tag Versions', 'ai-post-scheduler'),
								'live_metrics'  => __('Live Metrics', 'ai-post-scheduler'),
							);
							foreach ($cap_labels as $cap_key => $cap_label):
								$supported = !empty($caps[$cap_key]);
							?>
								<div class="aips-capability-item">
									<span class="aips-badge <?php echo $supported ? 'aips-badge-success' : 'aips-badge-secondary'; ?>">
										<span class="dashicons <?php echo $supported ? 'dashicons-yes-alt' : 'dashicons-minus'; ?>" aria-hidden="true"></span>
										<?php echo esc_html($cap_label); ?>
									</span>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<!-- Entries by Group -->
				<?php if (!empty($index['by_group'])): ?>
				<div class="aips-content-panel">
					<div class="aips-panel-header">
						<h2><?php esc_html_e('Entries by Group', 'ai-post-scheduler'); ?></h2>
					</div>
					<div class="aips-panel-body no-padding">
						<table class="aips-table aips-cache-monitor-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Group', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Entries', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Est. Size', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($index['by_group'] as $group_row): ?>
								<tr>
									<td><code><?php echo esc_html($group_row['cache_group']); ?></code></td>
									<td><?php echo esc_html(number_format_i18n($group_row['cnt'])); ?></td>
									<td><?php echo esc_html(aips_cache_monitor_format_bytes((int) $group_row['sz'])); ?></td>
									<td>
										<button type="button"
											class="aips-btn aips-btn-sm aips-btn-danger aips-cache-flush-group"
											data-group="<?php echo esc_attr($group_row['cache_group']); ?>"
											data-nonce="<?php echo esc_attr($action_nonce); ?>"
											data-confirm="<?php echo esc_attr(sprintf(__('Flush group "%s"?', 'ai-post-scheduler'), $group_row['cache_group'])); ?>">
											<?php esc_html_e('Flush Group', 'ai-post-scheduler'); ?>
										</button>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<!-- Largest Entries -->
				<?php if (!empty($index['largest'])): ?>
				<div class="aips-content-panel">
					<div class="aips-panel-header">
						<h2><?php esc_html_e('Largest Cached Entries', 'ai-post-scheduler'); ?></h2>
					</div>
					<div class="aips-panel-body no-padding">
						<table class="aips-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Key Hash', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Group', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Operation', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Size', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($index['largest'] as $lr): ?>
								<tr>
									<td><code class="aips-key-hash" title="<?php echo esc_attr($lr['key_hash']); ?>"><?php echo esc_html(substr($lr['key_hash'], 0, 12) . '…'); ?></code></td>
									<td><?php echo esc_html($lr['cache_group']); ?></td>
									<td><?php echo esc_html($lr['operation_id']); ?></td>
									<td><?php echo esc_html($lr['value_type']); ?></td>
									<td><?php echo esc_html(aips_cache_monitor_format_bytes((int) $lr['value_size'])); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<!-- Quick actions -->
				<div class="aips-content-panel">
					<div class="aips-panel-header">
						<h2><?php esc_html_e('Quick Actions', 'ai-post-scheduler'); ?></h2>
					</div>
					<div class="aips-panel-body">
						<div class="aips-btn-group">
							<button type="button" class="aips-btn aips-btn-secondary aips-cache-flush-expired"
								data-nonce="<?php echo esc_attr($action_nonce); ?>">
								<span class="dashicons dashicons-trash" aria-hidden="true"></span>
								<?php esc_html_e('Flush Expired', 'ai-post-scheduler'); ?>
							</button>
							<button type="button" class="aips-btn aips-btn-danger aips-cache-flush-all-btn"
								data-nonce="<?php echo esc_attr($action_nonce); ?>">
								<span class="dashicons dashicons-warning" aria-hidden="true"></span>
								<?php esc_html_e('Flush All Plugin Cache', 'ai-post-scheduler'); ?>
							</button>
						</div>
						<div class="aips-cache-action-result" style="margin-top:10px;"></div>
					</div>
				</div>

			</div><!-- /#overview -->
			<?php endif; ?>

			<?php /* ===================== ENTRIES TAB ===================== */ ?>
			<?php if ($active_tab === 'entries'): ?>
			<div class="aips-tab-content" data-tab="entries">
				<div class="aips-content-panel">
					<div class="aips-panel-header">
						<h2><?php esc_html_e('Cache Entries', 'ai-post-scheduler'); ?></h2>
					</div>
					<!-- Filter Bar -->
					<div class="aips-filter-bar" id="aips-cache-entries-filters">
						<div class="aips-filter-row">
							<input type="text" id="aips-cache-search" class="aips-form-input aips-input-sm"
								placeholder="<?php esc_attr_e('Search key hash, group, or operation…', 'ai-post-scheduler'); ?>" />
							<select id="aips-cache-filter-group" class="aips-form-select aips-input-sm">
								<option value=""><?php esc_html_e('All Groups', 'ai-post-scheduler'); ?></option>
								<?php
								if (!empty($index['by_group'])) {
									foreach ($index['by_group'] as $g) {
										echo '<option value="' . esc_attr($g['cache_group']) . '">' . esc_html($g['cache_group']) . '</option>';
									}
								}
								?>
							</select>
							<select id="aips-cache-filter-tier" class="aips-form-select aips-input-sm">
								<option value=""><?php esc_html_e('All Tiers', 'ai-post-scheduler'); ?></option>
								<option value="hot"><?php esc_html_e('Hot', 'ai-post-scheduler'); ?></option>
								<option value="warm"><?php esc_html_e('Warm', 'ai-post-scheduler'); ?></option>
								<option value="cold"><?php esc_html_e('Cold', 'ai-post-scheduler'); ?></option>
							</select>
							<select id="aips-cache-filter-ttl" class="aips-form-select aips-input-sm">
								<option value=""><?php esc_html_e('Any TTL State', 'ai-post-scheduler'); ?></option>
								<option value="active"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></option>
								<option value="expired"><?php esc_html_e('Expired', 'ai-post-scheduler'); ?></option>
								<option value="no_expiration"><?php esc_html_e('No Expiration', 'ai-post-scheduler'); ?></option>
							</select>
							<button type="button" class="aips-btn aips-btn-primary" id="aips-cache-entries-search-btn">
								<?php esc_html_e('Search', 'ai-post-scheduler'); ?>
							</button>
						</div>
					</div>

					<!-- Bulk actions bar -->
					<div class="aips-panel-toolbar">
						<div class="aips-bulk-actions">
							<input type="checkbox" id="aips-cache-select-all" title="<?php esc_attr_e('Select all', 'ai-post-scheduler'); ?>" />
							<select id="aips-cache-bulk-action" class="aips-form-select aips-input-sm">
								<option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
								<option value="delete"><?php esc_html_e('Delete Selected', 'ai-post-scheduler'); ?></option>
							</select>
							<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-cache-bulk-apply"
								data-nonce="<?php echo esc_attr($action_nonce); ?>">
								<?php esc_html_e('Apply', 'ai-post-scheduler'); ?>
							</button>
						</div>
						<div class="aips-per-page">
							<label><?php esc_html_e('Per page:', 'ai-post-scheduler'); ?>
								<select id="aips-cache-per-page" class="aips-form-select aips-input-sm">
									<option value="25">25</option>
									<option value="50" selected>50</option>
									<option value="100">100</option>
								</select>
							</label>
						</div>
					</div>

					<div class="aips-panel-body no-padding">
						<div id="aips-cache-entries-table-wrap">
							<table class="aips-table aips-cache-entries-table">
								<thead>
									<tr>
										<th class="check-column"></th>
										<th data-col="key_hash"><?php esc_html_e('Key Hash', 'ai-post-scheduler'); ?></th>
										<th data-col="cache_group"><?php esc_html_e('Group', 'ai-post-scheduler'); ?></th>
										<th data-col="operation_id"><?php esc_html_e('Operation', 'ai-post-scheduler'); ?></th>
										<th data-col="tier"><?php esc_html_e('Tier', 'ai-post-scheduler'); ?></th>
										<th data-col="driver"><?php esc_html_e('Driver', 'ai-post-scheduler'); ?></th>
										<th data-col="value_type"><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
										<th data-col="value_size"><?php esc_html_e('Size', 'ai-post-scheduler'); ?></th>
										<th data-col="expires_at"><?php esc_html_e('Expires', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
									</tr>
								</thead>
								<tbody id="aips-cache-entries-tbody">
									<tr><td colspan="10" class="aips-loading-placeholder"><?php esc_html_e('Loading…', 'ai-post-scheduler'); ?></td></tr>
								</tbody>
							</table>
						</div>
						<div id="aips-cache-entries-pagination" class="aips-pagination"></div>
					</div>
				</div>

				<!-- Inspect Modal -->
				<div id="aips-cache-inspect-modal" class="aips-modal" style="display:none;">
					<div class="aips-modal-content aips-modal-large">
						<div class="aips-modal-header">
							<h2><?php esc_html_e('Inspect Cache Entry', 'ai-post-scheduler'); ?></h2>
							<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
							</button>
						</div>
						<div class="aips-modal-body" id="aips-cache-inspect-body">
							<p class="aips-loading-placeholder"><?php esc_html_e('Loading…', 'ai-post-scheduler'); ?></p>
						</div>
					</div>
				</div>
			</div><!-- /#entries -->
			<?php endif; ?>

			<?php /* ===================== TAGS TAB ===================== */ ?>
			<?php if ($active_tab === 'tags'): ?>
			<div class="aips-tab-content" data-tab="tags">
				<div class="aips-content-panel">
					<div class="aips-panel-header">
						<h2><?php esc_html_e('Cache Tags', 'ai-post-scheduler'); ?></h2>
						<p class="aips-panel-description"><?php esc_html_e('Tag versions control cache invalidation. Bumping a tag version immediately makes all entries tagged with it stale.', 'ai-post-scheduler'); ?></p>
					</div>
					<?php if (empty($tags_data)): ?>
					<div class="aips-panel-body">
						<p class="aips-text-muted"><?php esc_html_e('No tagged cache entries found in the index.', 'ai-post-scheduler'); ?></p>
					</div>
					<?php else: ?>
					<div class="aips-panel-body no-padding">
						<table class="aips-table aips-cache-tags-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Tag', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Entries', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Current Version', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($tags_data as $tag_row): ?>
								<tr>
									<td><code><?php echo esc_html($tag_row['tag']); ?></code></td>
									<td><?php echo esc_html(number_format_i18n($tag_row['entry_count'])); ?></td>
									<td><span class="aips-badge aips-badge-secondary">v<?php echo esc_html($tag_row['version']); ?></span></td>
									<td>
										<button type="button"
											class="aips-btn aips-btn-sm aips-btn-danger aips-cache-invalidate-tag"
											data-tag="<?php echo esc_attr($tag_row['tag']); ?>"
											data-nonce="<?php echo esc_attr($action_nonce); ?>">
											<?php esc_html_e('Invalidate', 'ai-post-scheduler'); ?>
										</button>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>
				</div>
			</div><!-- /#tags -->
			<?php endif; ?>

			<?php /* ===================== DOMAINS TAB ===================== */ ?>
			<?php if ($active_tab === 'domains'): ?>
			<div class="aips-tab-content" data-tab="domains">
				<div class="aips-content-panel">
					<div class="aips-panel-header">
						<h2><?php esc_html_e('Cache Dependency Domains', 'ai-post-scheduler'); ?></h2>
						<p class="aips-panel-description"><?php esc_html_e('Domains are high-level invalidation scopes. Invalidating a domain bumps all its associated tags.', 'ai-post-scheduler'); ?></p>
					</div>
					<div class="aips-panel-body no-padding">
						<table class="aips-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Domain', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Affected Tags', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($domains_data as $domain_row): ?>
								<tr>
									<td><code><?php echo esc_html($domain_row['domain']); ?></code></td>
									<td>
										<?php foreach ($domain_row['tags'] as $tag): ?>
											<span class="aips-badge aips-badge-secondary" style="margin-right:3px;"><?php echo esc_html($tag); ?></span>
										<?php endforeach; ?>
									</td>
									<td>
										<button type="button"
											class="aips-btn aips-btn-sm aips-btn-danger aips-cache-invalidate-domain"
											data-domain="<?php echo esc_attr($domain_row['domain']); ?>"
											data-nonce="<?php echo esc_attr($action_nonce); ?>">
											<?php esc_html_e('Invalidate', 'ai-post-scheduler'); ?>
										</button>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div><!-- /#domains -->
			<?php endif; ?>

			<?php /* ===================== OPERATIONS TAB ===================== */ ?>
			<?php if ($active_tab === 'operations'): ?>
			<div class="aips-tab-content" data-tab="operations">
				<div class="aips-content-panel">
					<div class="aips-panel-header">
						<h2><?php esc_html_e('Repository Operations', 'ai-post-scheduler'); ?></h2>
						<p class="aips-panel-description"><?php esc_html_e('Aggregated stats for each cache operation ID from the index.', 'ai-post-scheduler'); ?></p>
					</div>
					<div class="aips-filter-bar">
						<input type="text" id="aips-ops-filter-repo" class="aips-form-input aips-input-sm"
							placeholder="<?php esc_attr_e('Filter by repository class…', 'ai-post-scheduler'); ?>" />
						<select id="aips-ops-filter-tier" class="aips-form-select aips-input-sm">
							<option value=""><?php esc_html_e('All Tiers', 'ai-post-scheduler'); ?></option>
							<option value="hot"><?php esc_html_e('Hot', 'ai-post-scheduler'); ?></option>
							<option value="warm"><?php esc_html_e('Warm', 'ai-post-scheduler'); ?></option>
							<option value="cold"><?php esc_html_e('Cold', 'ai-post-scheduler'); ?></option>
						</select>
						<button type="button" class="aips-btn aips-btn-primary" id="aips-ops-search-btn"
							data-nonce="<?php echo esc_attr($nonce); ?>">
							<?php esc_html_e('Load', 'ai-post-scheduler'); ?>
						</button>
					</div>
					<div class="aips-panel-body no-padding" id="aips-ops-table-wrap">
						<table class="aips-table aips-ops-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Operation ID', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Repository', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Tier', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Entries', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Est. Size', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Last Updated', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody id="aips-ops-tbody">
								<tr><td colspan="6" class="aips-loading-placeholder"><?php esc_html_e('Click Load to fetch operations.', 'ai-post-scheduler'); ?></td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div><!-- /#operations -->
			<?php endif; ?>

			<?php /* ===================== EVENTS TAB ===================== */ ?>
			<?php if ($active_tab === 'events'): ?>
			<div class="aips-tab-content" data-tab="events">
				<div class="aips-content-panel">
					<div class="aips-panel-header">
						<h2><?php esc_html_e('Cache Events Log', 'ai-post-scheduler'); ?></h2>
						<p class="aips-panel-description"><?php esc_html_e('Audit trail of cache operations performed by the Cache Monitor.', 'ai-post-scheduler'); ?></p>
					</div>
					<div class="aips-filter-bar">
						<select id="aips-events-filter-type" class="aips-form-select aips-input-sm">
							<option value=""><?php esc_html_e('All Event Types', 'ai-post-scheduler'); ?></option>
							<option value="entry_deleted"><?php esc_html_e('Entry Deleted', 'ai-post-scheduler'); ?></option>
							<option value="group_flushed"><?php esc_html_e('Group Flushed', 'ai-post-scheduler'); ?></option>
							<option value="tag_invalidated"><?php esc_html_e('Tag Invalidated', 'ai-post-scheduler'); ?></option>
							<option value="domain_invalidated"><?php esc_html_e('Domain Invalidated', 'ai-post-scheduler'); ?></option>
							<option value="flush_all"><?php esc_html_e('Flush All', 'ai-post-scheduler'); ?></option>
							<option value="index_reset"><?php esc_html_e('Index Reset', 'ai-post-scheduler'); ?></option>
							<option value="maintenance_run"><?php esc_html_e('Maintenance Run', 'ai-post-scheduler'); ?></option>
						</select>
						<button type="button" class="aips-btn aips-btn-primary" id="aips-events-load-btn"
							data-nonce="<?php echo esc_attr($nonce); ?>">
							<?php esc_html_e('Load Events', 'ai-post-scheduler'); ?>
						</button>
					</div>
					<div class="aips-panel-body no-padding">
						<table class="aips-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Time', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Group', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Affected', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('User', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Message', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody id="aips-events-tbody">
								<tr><td colspan="6" class="aips-loading-placeholder"><?php esc_html_e('Click Load Events to populate this table.', 'ai-post-scheduler'); ?></td></tr>
							</tbody>
						</table>
						<div id="aips-events-pagination" class="aips-pagination"></div>
					</div>
				</div>
			</div><!-- /#events -->
			<?php endif; ?>

			<?php /* ===================== DRIVER TAB ===================== */ ?>
			<?php if ($active_tab === 'driver'): ?>
			<div class="aips-tab-content" data-tab="driver">
				<?php $driver_info = $service->get_driver_info(); ?>
				<div class="aips-content-panel">
					<div class="aips-panel-header">
						<h2>
							<?php
							echo esc_html(sprintf(
								/* translators: %s: driver label */
								__('Driver: %s', 'ai-post-scheduler'),
								$driver_info['label'] ?? $driver_info['driver'] ?? __('Unknown', 'ai-post-scheduler')
							));
							?>
						</h2>
					</div>
					<div class="aips-panel-body">
						<table class="aips-table aips-health-check-table">
							<tbody>
								<?php foreach ($driver_info as $key => $value):
									if (in_array($key, array('driver', 'label', 'limitations', 'largest_rows', 'memory_info'), true)) continue;
								?>
								<tr>
									<td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></strong></td>
									<td>
										<?php
										if (is_bool($value)) {
											echo $value
												? '<span class="aips-badge aips-badge-success">' . esc_html__('Yes', 'ai-post-scheduler') . '</span>'
												: '<span class="aips-badge aips-badge-secondary">' . esc_html__('No', 'ai-post-scheduler') . '</span>';
										} elseif (is_int($value) && strpos($key, 'bytes') !== false) {
											echo esc_html(aips_cache_monitor_format_bytes($value));
										} else {
											echo esc_html((string) $value);
										}
										?>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<?php if (!empty($driver_info['limitations'])): ?>
						<div class="aips-notice aips-notice-warning" style="margin-top:15px;">
							<ul>
								<?php foreach ($driver_info['limitations'] as $limitation): ?>
									<li><?php echo esc_html($limitation); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
						<?php endif; ?>

						<?php if (!empty($driver_info['memory_info'])): ?>
						<h3><?php esc_html_e('Cache Backend Memory Info', 'ai-post-scheduler'); ?></h3>
						<table class="aips-table">
							<tbody>
								<?php foreach ($driver_info['memory_info'] as $mk => $mv): ?>
								<tr>
									<td><strong><?php echo esc_html($mk); ?></strong></td>
									<td><?php echo esc_html((string) $mv); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php endif; ?>

						<?php if (!empty($driver_info['largest_rows'])): ?>
						<h3><?php esc_html_e('Largest DB Cache Rows', 'ai-post-scheduler'); ?></h3>
						<table class="aips-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Key', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Group', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Size', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($driver_info['largest_rows'] as $lr): ?>
								<tr>
									<td><code><?php echo esc_html(substr($lr['cache_key'], 0, 60)); ?></code></td>
									<td><?php echo esc_html($lr['cache_group']); ?></td>
									<td><?php echo esc_html(aips_cache_monitor_format_bytes((int) $lr['value_size'])); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php endif; ?>

					</div>
				</div>
			</div><!-- /#driver -->
			<?php endif; ?>

			<?php /* ===================== MAINTENANCE TAB ===================== */ ?>
			<?php if ($active_tab === 'maintenance'): ?>
			<div class="aips-tab-content" data-tab="maintenance">
				<div class="aips-content-panel">
					<div class="aips-panel-header">
						<h2><?php esc_html_e('Cache Maintenance', 'ai-post-scheduler'); ?></h2>
					</div>
					<div class="aips-panel-body">

						<div class="aips-maintenance-actions">

							<div class="aips-maintenance-action">
								<h3><?php esc_html_e('Prune Expired Entries', 'ai-post-scheduler'); ?></h3>
								<p><?php esc_html_e('Remove all expired entries from the cache index and the DB cache table (if active).', 'ai-post-scheduler'); ?></p>
								<button type="button" class="aips-btn aips-btn-secondary aips-maintenance-action-btn"
									data-action="prune_expired" data-nonce="<?php echo esc_attr($action_nonce); ?>">
									<?php esc_html_e('Prune Expired', 'ai-post-scheduler'); ?>
								</button>
							</div>

							<div class="aips-maintenance-action">
								<h3><?php esc_html_e('Prune Orphaned Index Entries', 'ai-post-scheduler'); ?></h3>
								<p><?php esc_html_e('Remove index rows that no longer correspond to a live cache entry. Safe only when using the DB driver.', 'ai-post-scheduler'); ?></p>
								<button type="button" class="aips-btn aips-btn-secondary aips-maintenance-action-btn"
									data-action="prune_orphans" data-nonce="<?php echo esc_attr($action_nonce); ?>">
									<?php esc_html_e('Prune Orphans', 'ai-post-scheduler'); ?>
								</button>
							</div>

							<div class="aips-maintenance-action">
								<h3><?php esc_html_e('Rebuild Index from DB', 'ai-post-scheduler'); ?></h3>
								<p><?php esc_html_e('Reconstruct the cache index from the DB cache table. Only available when the DB driver is active.', 'ai-post-scheduler'); ?></p>
								<button type="button" class="aips-btn aips-btn-secondary aips-maintenance-action-btn"
									data-action="rebuild_index" data-nonce="<?php echo esc_attr($action_nonce); ?>">
									<?php esc_html_e('Rebuild Index', 'ai-post-scheduler'); ?>
								</button>
							</div>

							<div class="aips-maintenance-action">
								<h3><?php esc_html_e('Run All Maintenance', 'ai-post-scheduler'); ?></h3>
								<p><?php esc_html_e('Prune expired entries, remove orphaned rows, and compact old event logs in one operation.', 'ai-post-scheduler'); ?></p>
								<button type="button" class="aips-btn aips-btn-primary aips-maintenance-action-btn"
									data-action="run_all" data-nonce="<?php echo esc_attr($action_nonce); ?>">
									<?php esc_html_e('Run All', 'ai-post-scheduler'); ?>
								</button>
							</div>

							<div class="aips-maintenance-action">
								<h3><?php esc_html_e('Export Diagnostics', 'ai-post-scheduler'); ?></h3>
								<p><?php esc_html_e('Download a JSON bundle with cache summary, driver info, tags, domains, and operations.', 'ai-post-scheduler'); ?></p>
								<button type="button" class="aips-btn aips-btn-ghost aips-maintenance-action-btn"
									data-action="export_diagnostics" data-nonce="<?php echo esc_attr($action_nonce); ?>">
									<?php esc_html_e('Export JSON', 'ai-post-scheduler'); ?>
								</button>
							</div>

						</div>

						<div id="aips-maintenance-result" class="aips-cache-action-result" style="margin-top:15px;display:none;"></div>
					</div>
				</div>
			</div><!-- /#maintenance -->
			<?php endif; ?>

		</div><!-- /.aips-cache-monitor-content -->
		<?php if (!$is_embedded) : ?>
	</div><!-- /.aips-page-container -->
</div><!-- /.wrap -->
		<?php endif; ?>
