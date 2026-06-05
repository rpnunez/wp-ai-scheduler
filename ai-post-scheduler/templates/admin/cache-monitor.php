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

$action_nonce = wp_create_nonce('aips_cache_monitor_action');
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title">
						<span class="dashicons dashicons-database-view" style="font-size:30px;vertical-align:middle;margin-right:6px;"></span>
						<?php esc_html_e('Cache Monitor', 'ai-post-scheduler'); ?>
					</h1>
					<p class="aips-page-description"><?php esc_html_e('Inspect, manage, and maintain the plugin cache. View entries, tags, domains, and operation metrics.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-btn-group">
					<button type="button" class="aips-btn aips-btn-secondary aips-cache-monitor-refresh" data-nonce="<?php echo esc_attr($nonce); ?>">
						<span class="dashicons dashicons-update"></span>
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

			<!-- Tab navigation -->
			<div class="aips-tab-nav">
				<ul class="aips-tab-list">
					<?php foreach ($tabs as $tab_slug => $tab_label): ?>
						<li class="aips-tab-item">
							<a href="<?php echo esc_url(add_query_arg(array('page' => 'aips-cache-monitor', 'tab' => $tab_slug), admin_url('admin.php'))); ?>"
							   class="aips-tab-link nav-tab<?php echo $active_tab === $tab_slug ? ' nav-tab-active' : ''; ?>"
							   data-tab="<?php echo esc_attr($tab_slug); ?>">
								<?php echo esc_html($tab_label); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
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
										<span class="dashicons <?php echo $supported ? 'dashicons-yes-alt' : 'dashicons-minus'; ?>"></span>
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
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e('Flush Expired', 'ai-post-scheduler'); ?>
							</button>
							<button type="button" class="aips-btn aips-btn-danger aips-cache-flush-all-btn"
								data-nonce="<?php echo esc_attr($action_nonce); ?>">
								<span class="dashicons dashicons-warning"></span>
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
								<span class="dashicons dashicons-no-alt"></span>
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
				<?php
				$tags_data = $service->list_tags();
				?>
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
				<?php $domains_data = $service->list_domains(); ?>
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
						<h3><?php esc_html_e('Redis Memory Info', 'ai-post-scheduler'); ?></h3>
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
	</div><!-- /.aips-page-container -->
</div><!-- /.wrap -->

<?php /* HTML templates for AJAX-driven Entries table rows */ ?>
<script type="text/html" id="tmpl-aips-cache-entry-row">
	<tr data-hash="{{key_hash}}">
		<td class="check-column"><input type="checkbox" class="aips-cache-entry-cb" value="{{key_hash}}" /></td>
		<td class="cell-primary">
			<code class="aips-key-hash" title="{{key_hash}}">{{key_hash_short}}</code>
			<div class="row-actions">
				<span class="inspect"><a href="#" class="aips-cache-inspect-link" data-hash="{{key_hash}}" data-nonce="<?php echo esc_attr($nonce); ?>"><?php esc_html_e('Inspect', 'ai-post-scheduler'); ?></a> |</span>
				<span class="delete"><a href="#" class="aips-cache-delete-link aips-text-danger" data-hash="{{key_hash}}" data-nonce="<?php echo esc_attr($action_nonce); ?>"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></a></span>
			</div>
		</td>
		<td>{{cache_group}}</td>
		<td><small>{{operation_id}}</small></td>
		<td>{{tier}}</td>
		<td>{{driver}}</td>
		<td><small>{{value_type}}</small></td>
		<td>{{value_size_fmt}}</td>
		<td>{{expires_fmt}}</td>
		<td>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-cache-inspect-btn"
				data-hash="{{key_hash}}" data-nonce="<?php echo esc_attr($nonce); ?>">
				<?php esc_html_e('Inspect', 'ai-post-scheduler'); ?>
			</button>
		</td>
	</tr>
</script>

<script type="text/html" id="tmpl-aips-cache-inspect-content">
	<dl class="aips-dl">
		<dt><?php esc_html_e('Key Hash', 'ai-post-scheduler'); ?></dt><dd><code>{{key_hash}}</code></dd>
		<dt><?php esc_html_e('Cache Key', 'ai-post-scheduler'); ?></dt><dd><code>{{cache_key}}</code></dd>
		<dt><?php esc_html_e('Group', 'ai-post-scheduler'); ?></dt><dd>{{cache_group}}</dd>
		<dt><?php esc_html_e('Driver', 'ai-post-scheduler'); ?></dt><dd>{{driver}}</dd>
		<dt><?php esc_html_e('Tier', 'ai-post-scheduler'); ?></dt><dd>{{tier}}</dd>
		<dt><?php esc_html_e('Operation ID', 'ai-post-scheduler'); ?></dt><dd>{{operation_id}}</dd>
		<dt><?php esc_html_e('Tags', 'ai-post-scheduler'); ?></dt><dd>{{tags}}</dd>
		<dt><?php esc_html_e('TTL', 'ai-post-scheduler'); ?></dt><dd>{{ttl}}s</dd>
		<dt><?php esc_html_e('Expires At', 'ai-post-scheduler'); ?></dt><dd>{{expires_fmt}}</dd>
		<dt><?php esc_html_e('TTL Remaining', 'ai-post-scheduler'); ?></dt><dd>{{ttl_remaining_fmt}}</dd>
		<dt><?php esc_html_e('Value Type', 'ai-post-scheduler'); ?></dt><dd>{{value_type}}</dd>
		<dt><?php esc_html_e('Value Size', 'ai-post-scheduler'); ?></dt><dd>{{value_size_fmt}}</dd>
	</dl>
	<h4><?php esc_html_e('Preview', 'ai-post-scheduler'); ?></h4>
	<div class="aips-cache-preview-note" style="font-style:italic;margin-bottom:6px;">{{preview_note}}</div>
	<pre class="aips-cache-preview-value" style="max-height:400px;overflow:auto;background:#f9f9f9;padding:10px;border:1px solid #ddd;">{{preview_json}}</pre>
</script>

<script>
(function($) {
	'use strict';

	var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
	var nonce   = '<?php echo esc_js($nonce); ?>';

	// ---- format helpers ----
	function formatBytes(bytes) {
		bytes = parseInt(bytes, 10) || 0;
		if (bytes < 1024) return bytes + ' B';
		if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
		return (bytes / 1048576).toFixed(1) + ' MB';
	}
	function formatTs(ts) {
		if (!ts || ts === '0') return '<?php echo esc_js(__('Never', 'ai-post-scheduler')); ?>';
		var d = new Date(parseInt(ts, 10) * 1000);
		return d.toLocaleString();
	}

	// ---- Refresh button ----
	$('.aips-cache-monitor-refresh').on('click', function() {
		location.reload();
	});

	// ---- Flush expired ----
	$(document).on('click', '.aips-cache-flush-expired', function() {
		var $btn    = $(this);
		var $result = $btn.closest('.aips-content-panel').find('.aips-cache-action-result');
		$btn.prop('disabled', true);
		$.post(ajaxUrl, {
			action: 'aips_cache_monitor_flush_expired',
			nonce:  $btn.data('nonce') || $btn.closest('[data-nonce]').data('nonce')
		}, function(res) {
			$btn.prop('disabled', false);
			if (res.success) {
				AIPS.Utilities.showToast(res.data.message, 'success');
			} else {
				AIPS.Utilities.showToast(res.data.message, 'error');
			}
		});
	});

	// ---- Flush All ----
	$(document).on('click', '.aips-cache-flush-all-btn', function() {
		var $btn    = $(this);
		var actionNonce = $btn.data('nonce');
		AIPS.Utilities.confirm(
			'<?php echo esc_js(__('This will flush ALL plugin-owned cache. Are you sure?', 'ai-post-scheduler')); ?>',
			'<?php echo esc_js(__('Flush All Plugin Cache', 'ai-post-scheduler')); ?>',
			[{
				label: '<?php echo esc_js(__('Confirm Flush', 'ai-post-scheduler')); ?>',
				className: 'aips-btn-danger',
				action: function() {
					$.post(ajaxUrl, {
						action:    'aips_cache_monitor_flush_all',
						nonce:     actionNonce,
						confirmed: 1
					}, function(res) {
						if (res.success) {
							AIPS.Utilities.showToast(res.data.message, 'success');
						} else {
							AIPS.Utilities.showToast(res.data.message, 'error');
						}
					});
				}
			}]
		);
	});

	// ---- Flush group ----
	$(document).on('click', '.aips-cache-flush-group', function() {
		var $btn  = $(this);
		var group = $btn.data('group');
		var actionNonce = $btn.data('nonce');
		var confirmMsg = $btn.data('confirm') || '<?php echo esc_js(__('Flush this group?', 'ai-post-scheduler')); ?>';

		AIPS.Utilities.confirm(confirmMsg, '<?php echo esc_js(__('Flush Cache Group', 'ai-post-scheduler')); ?>', [{
			label: '<?php echo esc_js(__('Flush Group', 'ai-post-scheduler')); ?>',
			className: 'aips-btn-danger',
			action: function() {
				$.post(ajaxUrl, { action: 'aips_cache_monitor_flush_group', nonce: actionNonce, cache_group: group }, function(res) {
					if (res.success) {
						AIPS.Utilities.showToast(res.data.message, 'success');
						setTimeout(function() { location.reload(); }, 1200);
					} else {
						AIPS.Utilities.showToast(res.data.message, 'error');
					}
				});
			}
		}]);
	});

	// ---- Invalidate tag ----
	$(document).on('click', '.aips-cache-invalidate-tag', function() {
		var $btn = $(this);
		var tag  = $btn.data('tag');
		$.post(ajaxUrl, {
			action: 'aips_cache_monitor_invalidate_tag',
			nonce:  $btn.data('nonce'),
			tag:    tag
		}, function(res) {
			if (res.success) {
				AIPS.Utilities.showToast(res.data.message, 'success');
				$btn.closest('tr').find('.aips-badge').text('v' + res.data.new_version);
			} else {
				AIPS.Utilities.showToast(res.data.message, 'error');
			}
		});
	});

	// ---- Invalidate domain ----
	$(document).on('click', '.aips-cache-invalidate-domain', function() {
		var $btn   = $(this);
		var domain = $btn.data('domain');
		$.post(ajaxUrl, {
			action: 'aips_cache_monitor_invalidate_domain',
			nonce:  $btn.data('nonce'),
			domain: domain
		}, function(res) {
			if (res.success) {
				AIPS.Utilities.showToast(res.data.message, 'success');
			} else {
				AIPS.Utilities.showToast(res.data.message, 'error');
			}
		});
	});

	// ---- Entries tab: load + paginate ----
	var entriesState = { page: 1, per_page: 50, filters: {}, orderby: 'updated_at', order: 'DESC' };

	function loadEntries() {
		var params = $.extend({}, entriesState.filters, {
			action:   'aips_cache_monitor_entries',
			nonce:    nonce,
			page:     entriesState.page,
			per_page: entriesState.per_page,
			orderby:  entriesState.orderby,
			order:    entriesState.order
		});

		$('#aips-cache-entries-tbody').html('<tr><td colspan="10"><?php echo esc_js(__('Loading…', 'ai-post-scheduler')); ?></td></tr>');
		$.post(ajaxUrl, params, function(res) {
			if (!res.success) {
				AIPS.Utilities.showToast(res.data.message, 'error');
				return;
			}
			var rows = res.data.rows || [];
			var html = '';
			$.each(rows, function(i, row) {
				var expires_fmt = row.expires_at > 0 ? formatTs(row.expires_at) : '<?php echo esc_js(__('Never', 'ai-post-scheduler')); ?>';
				var ttl_class   = row.is_expired ? ' style="opacity:0.5;"' : '';
				html += '<tr data-hash="' + AIPS.Templates.escAttr(row.key_hash) + '"' + ttl_class + '>';
				html += '<td class="check-column"><input type="checkbox" class="aips-cache-entry-cb" value="' + AIPS.Templates.escAttr(row.key_hash) + '" /></td>';
				html += '<td class="cell-primary"><code class="aips-key-hash" title="' + AIPS.Templates.escAttr(row.key_hash) + '">' + AIPS.Templates.esc(row.key_hash.substring(0, 12) + '…') + '</code>';
				html += '<div class="row-actions"><span><a href="#" class="aips-cache-inspect-link" data-hash="' + AIPS.Templates.escAttr(row.key_hash) + '"><?php echo esc_js(__('Inspect', 'ai-post-scheduler')); ?></a></span> | ';
				html += '<span class="delete"><a href="#" class="aips-cache-delete-link aips-text-danger" data-hash="' + AIPS.Templates.escAttr(row.key_hash) + '"><?php echo esc_js(__('Delete', 'ai-post-scheduler')); ?></a></span></div></td>';
				html += '<td>' + AIPS.Templates.esc(row.cache_group) + '</td>';
				html += '<td><small>' + AIPS.Templates.esc(row.operation_id) + '</small></td>';
				html += '<td>' + AIPS.Templates.esc(row.tier) + '</td>';
				html += '<td>' + AIPS.Templates.esc(row.driver) + '</td>';
				html += '<td><small>' + AIPS.Templates.esc(row.value_type) + '</small></td>';
				html += '<td>' + formatBytes(row.value_size) + '</td>';
				html += '<td>' + AIPS.Templates.esc(expires_fmt) + '</td>';
				html += '<td><button class="aips-btn aips-btn-sm aips-btn-ghost aips-cache-inspect-link" data-hash="' + AIPS.Templates.escAttr(row.key_hash) + '"><?php echo esc_js(__('Inspect', 'ai-post-scheduler')); ?></button></td>';
				html += '</tr>';
			});
			if (!html) {
				html = '<tr><td colspan="10"><?php echo esc_js(__('No entries found.', 'ai-post-scheduler')); ?></td></tr>';
			}
			$('#aips-cache-entries-tbody').html(html);

			// Pagination
			var totalPages = res.data.total_pages || 1;
			var currentPage = res.data.page || 1;
			var pagHtml = '';
			if (totalPages > 1) {
				pagHtml = '<span class="aips-pag-info">' + AIPS.Templates.esc('Page ' + currentPage + ' / ' + totalPages) + ' (' + AIPS.Templates.esc(res.data.total) + ' total)</span> ';
				if (currentPage > 1) pagHtml += '<button class="aips-btn aips-btn-sm aips-btn-ghost aips-entries-prev">&laquo; <?php echo esc_js(__('Prev', 'ai-post-scheduler')); ?></button> ';
				if (currentPage < totalPages) pagHtml += '<button class="aips-btn aips-btn-sm aips-btn-ghost aips-entries-next"><?php echo esc_js(__('Next', 'ai-post-scheduler')); ?> &raquo;</button>';
			}
			$('#aips-cache-entries-pagination').html(pagHtml);
		});
	}

	// Auto-load entries when on entries tab.
	if ($('[data-tab="entries"]').length) {
		loadEntries();
	}

	$('#aips-cache-entries-search-btn').on('click', function() {
		entriesState.filters.search  = $('#aips-cache-search').val();
		entriesState.filters.group   = $('#aips-cache-filter-group').val();
		entriesState.filters.tier    = $('#aips-cache-filter-tier').val();
		entriesState.filters.ttl_state = $('#aips-cache-filter-ttl').val();
		entriesState.page = 1;
		loadEntries();
	});

	$(document).on('click', '.aips-entries-prev', function() { entriesState.page--; loadEntries(); });
	$(document).on('click', '.aips-entries-next', function() { entriesState.page++; loadEntries(); });

	$('#aips-cache-per-page').on('change', function() {
		entriesState.per_page = parseInt($(this).val(), 10);
		entriesState.page = 1;
		loadEntries();
	});

	// ---- Inspect entry ----
	$(document).on('click', '.aips-cache-inspect-link, .aips-cache-inspect-btn', function(e) {
		e.preventDefault();
		var hash = $(this).data('hash');
		$('#aips-cache-inspect-modal').show();
		$('#aips-cache-inspect-body').html('<p><?php echo esc_js(__('Loading…', 'ai-post-scheduler')); ?></p>');
		$.post(ajaxUrl, { action: 'aips_cache_monitor_inspect', nonce: nonce, key_hash: hash }, function(res) {
			if (!res.success) { $('#aips-cache-inspect-body').html('<p>' + AIPS.Templates.esc(res.data.message) + '</p>'); return; }
			var d = res.data;
			var expires_fmt = d.expires_at > 0 ? formatTs(d.expires_at) : '<?php echo esc_js(__('Never', 'ai-post-scheduler')); ?>';
			var ttl_rem_fmt = d.ttl_remaining !== null ? d.ttl_remaining + 's' : '<?php echo esc_js(__('N/A', 'ai-post-scheduler')); ?>';
			var html = '<dl class="aips-dl">';
			html += '<dt><?php echo esc_js(__('Key Hash', 'ai-post-scheduler')); ?></dt><dd><code>' + AIPS.Templates.esc(d.key_hash) + '</code></dd>';
			html += '<dt><?php echo esc_js(__('Group', 'ai-post-scheduler')); ?></dt><dd>' + AIPS.Templates.esc(d.cache_group) + '</dd>';
			html += '<dt><?php echo esc_js(__('Driver', 'ai-post-scheduler')); ?></dt><dd>' + AIPS.Templates.esc(d.driver) + '</dd>';
			html += '<dt><?php echo esc_js(__('Tier', 'ai-post-scheduler')); ?></dt><dd>' + AIPS.Templates.esc(d.tier) + '</dd>';
			html += '<dt><?php echo esc_js(__('Operation', 'ai-post-scheduler')); ?></dt><dd>' + AIPS.Templates.esc(d.operation_id) + '</dd>';
			html += '<dt><?php echo esc_js(__('Tags', 'ai-post-scheduler')); ?></dt><dd>' + AIPS.Templates.esc(d.tags) + '</dd>';
			html += '<dt><?php echo esc_js(__('TTL', 'ai-post-scheduler')); ?></dt><dd>' + AIPS.Templates.esc(d.ttl) + 's</dd>';
			html += '<dt><?php echo esc_js(__('Expires', 'ai-post-scheduler')); ?></dt><dd>' + AIPS.Templates.esc(expires_fmt) + '</dd>';
			html += '<dt><?php echo esc_js(__('TTL Remaining', 'ai-post-scheduler')); ?></dt><dd>' + AIPS.Templates.esc(ttl_rem_fmt) + '</dd>';
			html += '<dt><?php echo esc_js(__('Value Type', 'ai-post-scheduler')); ?></dt><dd>' + AIPS.Templates.esc(d.value_type) + '</dd>';
			html += '<dt><?php echo esc_js(__('Size', 'ai-post-scheduler')); ?></dt><dd>' + formatBytes(d.value_size) + '</dd>';
			html += '</dl>';
			if (d.preview !== null && d.preview !== undefined) {
				html += '<h4><?php echo esc_js(__('Preview', 'ai-post-scheduler')); ?></h4>';
				html += '<p style="font-style:italic;">' + AIPS.Templates.esc(d.preview_note || '') + '</p>';
				html += '<pre style="max-height:400px;overflow:auto;background:#f9f9f9;padding:10px;border:1px solid #ddd;">' + AIPS.Templates.esc(JSON.stringify(d.preview, null, 2)) + '</pre>';
			}
			$('#aips-cache-inspect-body').html(html);
		});
	});

	$(document).on('click', '.aips-modal-close', function() {
		$(this).closest('.aips-modal').hide();
	});

	// ---- Delete single entry ----
	$(document).on('click', '.aips-cache-delete-link', function(e) {
		e.preventDefault();
		var $el  = $(this);
		var hash = $el.data('hash');
		var actionNonce = $el.closest('[data-nonce]').data('nonce') || '<?php echo esc_js($action_nonce); ?>';
		$.post(ajaxUrl, { action: 'aips_cache_monitor_delete_entry', nonce: actionNonce, key_hash: hash }, function(res) {
			if (res.success) {
				AIPS.Utilities.showToast(res.data.message, 'success');
				$el.closest('tr').fadeOut(300, function() { $(this).remove(); });
			} else {
				AIPS.Utilities.showToast(res.data.message, 'error');
			}
		});
	});

	// ---- Bulk delete ----
	$('#aips-cache-bulk-apply').on('click', function() {
		var action = $('#aips-cache-bulk-action').val();
		if (action !== 'delete') return;
		var hashes = [];
		$('.aips-cache-entry-cb:checked').each(function() { hashes.push($(this).val()); });
		if (!hashes.length) { AIPS.Utilities.showToast('<?php echo esc_js(__('No entries selected.', 'ai-post-scheduler')); ?>', 'warning'); return; }
		$.post(ajaxUrl, { action: 'aips_cache_monitor_delete_bulk', nonce: $(this).data('nonce'), key_hashes: hashes }, function(res) {
			if (res.success) {
				AIPS.Utilities.showToast(res.data.message, 'success');
				loadEntries();
			} else {
				AIPS.Utilities.showToast(res.data.message, 'error');
			}
		});
	});

	$('#aips-cache-select-all').on('change', function() {
		$('.aips-cache-entry-cb').prop('checked', $(this).is(':checked'));
	});

	// ---- Operations tab ----
	$('#aips-ops-search-btn').on('click', function() {
		var params = {
			action:           'aips_cache_monitor_operations',
			nonce:            $(this).data('nonce'),
			repository_class: $('#aips-ops-filter-repo').val(),
			tier:             $('#aips-ops-filter-tier').val()
		};
		$('#aips-ops-tbody').html('<tr><td colspan="6"><?php echo esc_js(__('Loading…', 'ai-post-scheduler')); ?></td></tr>');
		$.post(ajaxUrl, params, function(res) {
			if (!res.success) { AIPS.Utilities.showToast(res.data.message, 'error'); return; }
			var ops  = res.data.operations || [];
			var html = '';
			$.each(ops, function(i, op) {
				html += '<tr>';
				html += '<td><code>' + AIPS.Templates.esc(op.operation_id) + '</code></td>';
				html += '<td><small>' + AIPS.Templates.esc(op.repository_class) + '</small></td>';
				html += '<td>' + AIPS.Templates.esc(op.tier) + '</td>';
				html += '<td>' + AIPS.Templates.esc(op.index_count) + '</td>';
				html += '<td>' + formatBytes(op.total_size) + '</td>';
				html += '<td>' + formatTs(op.last_updated) + '</td>';
				html += '</tr>';
			});
			if (!html) html = '<tr><td colspan="6"><?php echo esc_js(__('No operations found.', 'ai-post-scheduler')); ?></td></tr>';
			$('#aips-ops-tbody').html(html);
		});
	});

	// ---- Events tab ----
	var eventsPage = 1;
	function loadEvents() {
		var params = {
			action:     'aips_cache_monitor_events',
			nonce:      nonce,
			event_type: $('#aips-events-filter-type').val(),
			page:       eventsPage,
			per_page:   50
		};
		$('#aips-events-tbody').html('<tr><td colspan="6"><?php echo esc_js(__('Loading…', 'ai-post-scheduler')); ?></td></tr>');
		$.post(ajaxUrl, params, function(res) {
			if (!res.success) { AIPS.Utilities.showToast(res.data.message, 'error'); return; }
			var rows = res.data.rows || [];
			var html = '';
			$.each(rows, function(i, ev) {
				html += '<tr>';
				html += '<td>' + formatTs(ev.created_at) + '</td>';
				html += '<td><code>' + AIPS.Templates.esc(ev.event_type) + '</code></td>';
				html += '<td>' + AIPS.Templates.esc(ev.cache_group) + '</td>';
				html += '<td>' + AIPS.Templates.esc(ev.affected_count) + '</td>';
				html += '<td>' + AIPS.Templates.esc(ev.user_id) + '</td>';
				html += '<td>' + AIPS.Templates.esc(ev.message) + '</td>';
				html += '</tr>';
			});
			if (!html) html = '<tr><td colspan="6"><?php echo esc_js(__('No events found.', 'ai-post-scheduler')); ?></td></tr>';
			$('#aips-events-tbody').html(html);
		});
	}
	$('#aips-events-load-btn').on('click', function() { eventsPage = 1; loadEvents(); });

	// ---- Maintenance ----
	$('.aips-maintenance-action-btn').on('click', function() {
		var $btn   = $(this);
		var action = $btn.data('action');
		var $result = $('#aips-maintenance-result');
		$btn.prop('disabled', true);
		$.post(ajaxUrl, {
			action:             'aips_cache_monitor_maintenance',
			nonce:              $btn.data('nonce'),
			maintenance_action: action
		}, function(res) {
			$btn.prop('disabled', false);
			if (action === 'export_diagnostics' && res.success) {
				var blob = new Blob([JSON.stringify(res.data.diagnostics, null, 2)], {type: 'application/json'});
				var url  = URL.createObjectURL(blob);
				var a    = document.createElement('a');
				a.href     = url;
				a.download = 'aips-cache-diagnostics-' + Date.now() + '.json';
				a.click();
				URL.revokeObjectURL(url);
				return;
			}
			if (res.success) {
				$result.show().html('<div class="notice notice-success inline"><p>' + AIPS.Templates.esc(res.data.message) + '</p></div>');
				AIPS.Utilities.showToast(res.data.message, 'success');
			} else {
				$result.show().html('<div class="notice notice-error inline"><p>' + AIPS.Templates.esc(res.data.message) + '</p></div>');
				AIPS.Utilities.showToast(res.data.message, 'error');
			}
		});
	});

})(jQuery);
</script>
