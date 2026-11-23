<?php
/**
 * Admin Template: Diagnostics > Logs Tab
 *
 * Master-detail log viewer for AIPS plugin log files with search,
 * multi-level filters, auto-refresh tailing, raw/formatted toggle, and file actions.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var array<int, array<string, mixed>> $log_files */
$active_file_name = !empty($log_files[0]['name']) ? $log_files[0]['name'] : '';
$active_display_name = !empty($log_files[0]['display_name']) ? $log_files[0]['display_name'] : $active_file_name;
?>

<div class="aips-tab-pane aips-tab-logs" id="aips-tab-logs-pane">
	<!-- Tab Header -->
	<div class="aips-tab-header">
		<div>
			<h2 class="aips-tab-title"><?php esc_html_e('Plugin System Logs', 'ai-post-scheduler'); ?></h2>
			<p class="aips-tab-desc"><?php esc_html_e('Inspect, search, live-tail, and download plugin diagnostic log files from the server.', 'ai-post-scheduler'); ?></p>
		</div>
	</div>

	<!-- Master-Detail Container -->
	<div class="aips-logs-master-detail">
		<!-- Left Sidebar: Log Files List -->
		<aside class="aips-logs-sidebar">
			<div class="aips-logs-sidebar-header">
				<div class="aips-logs-sidebar-title-wrap">
					<span class="dashicons dashicons-media-text"></span>
					<h3 class="aips-logs-sidebar-title"><?php esc_html_e('Log Files', 'ai-post-scheduler'); ?></h3>
					<span class="aips-badge aips-badge-neutral" id="aips-logs-file-count"><?php echo esc_html(count($log_files)); ?></span>
				</div>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost" id="aips-logs-refresh-files-btn" title="<?php esc_attr_e('Refresh log file list', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>

			<div class="aips-logs-file-list" id="aips-logs-file-list" role="listbox" aria-label="<?php esc_attr_e('Available Log Files', 'ai-post-scheduler'); ?>">
				<?php if (empty($log_files)) : ?>
					<div class="aips-logs-empty-files">
						<span class="dashicons dashicons-info"></span>
						<p><?php esc_html_e('No log files found.', 'ai-post-scheduler'); ?></p>
					</div>
				<?php else : ?>
					<?php foreach ($log_files as $index => $file) :
						$is_selected = ($file['name'] === $active_file_name);
						?>
						<button type="button"
							class="aips-logs-file-item <?php echo $is_selected ? 'is-selected' : ''; ?>"
							data-file="<?php echo esc_attr($file['name']); ?>"
							data-display-name="<?php echo esc_attr(!empty($file['display_name']) ? $file['display_name'] : $file['name']); ?>"
							data-is-active="<?php echo !empty($file['is_active']) ? '1' : '0'; ?>"
							role="option"
							aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>">
							<div class="aips-logs-file-item-header">
								<span class="aips-logs-file-title">
									<?php echo esc_html(!empty($file['display_name']) ? $file['display_name'] : $file['name']); ?>
								</span>
								<?php if (!empty($file['is_active'])) : ?>
									<span class="aips-badge aips-badge-success aips-logs-active-badge"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></span>
								<?php endif; ?>
							</div>
							<div class="aips-logs-file-name" title="<?php echo esc_attr($file['name']); ?>">
								<?php echo esc_html($file['name']); ?>
							</div>
							<div class="aips-logs-file-meta">
								<span class="aips-logs-file-date">
									<span class="dashicons dashicons-clock"></span> <?php echo esc_html($file['modified']); ?>
								</span>
								<span class="aips-logs-file-size"><?php echo esc_html($file['size']); ?></span>
							</div>
						</button>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</aside>

		<!-- Right Main Stage: Viewer Pane -->
		<section class="aips-logs-main" aria-label="<?php esc_attr_e('Log Viewer Pane', 'ai-post-scheduler'); ?>">
			<!-- Toolbar Controls Header -->
			<div class="aips-logs-toolbar">
				<!-- Top Row: File Details & Primary Actions -->
				<div class="aips-logs-toolbar-top">
					<div class="aips-logs-active-file-info">
						<span class="aips-logs-active-display-name" id="aips-logs-current-display-name">
							<?php echo esc_html($active_display_name ?: __('No file selected', 'ai-post-scheduler')); ?>
						</span>
						<span class="aips-logs-active-raw-name" id="aips-logs-current-filename">
							<?php echo esc_html($active_file_name); ?>
						</span>
						<span class="aips-badge aips-badge-neutral" id="aips-logs-current-filesize">&mdash;</span>
						<span class="aips-badge aips-badge-info" id="aips-logs-current-linecount">&mdash;</span>
					</div>

					<div class="aips-logs-actions">
						<!-- Auto-Refresh Button & Interval Dropdown Group -->
						<div class="aips-logs-refresh-group">
							<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-logs-refresh-btn" id="aips-logs-autorefresh-toggle" title="<?php esc_attr_e('Toggle live tail auto-refresh', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-controls-play" id="aips-logs-autorefresh-icon"></span>
								<span id="aips-logs-autorefresh-label"><?php esc_html_e('Auto-Refresh', 'ai-post-scheduler'); ?></span>
							</button>
							<select id="aips-logs-autorefresh-interval" class="aips-form-select aips-form-select-sm aips-logs-interval-select" title="<?php esc_attr_e('Refresh interval', 'ai-post-scheduler'); ?>">
								<option value="3000">3s</option>
								<option value="5000" selected>5s</option>
								<option value="10000">10s</option>
							</select>
						</div>

						<!-- Copy to Clipboard -->
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-logs-copy-btn" title="<?php esc_attr_e('Copy current log view to clipboard', 'ai-post-scheduler'); ?>">
							<span class="dashicons dashicons-clipboard"></span>
							<span><?php esc_html_e('Copy', 'ai-post-scheduler'); ?></span>
						</button>

						<!-- Download Raw File -->
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-logs-download-btn" title="<?php esc_attr_e('Download complete log file', 'ai-post-scheduler'); ?>">
							<span class="dashicons dashicons-download"></span>
							<span><?php esc_html_e('Download', 'ai-post-scheduler'); ?></span>
						</button>

						<!-- Clear / Delete Action Dropdown -->
						<div class="aips-dropdown aips-logs-manage-dropdown">
							<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-btn-danger-hover aips-logs-manage-trigger" id="aips-logs-manage-menu-btn" aria-haspopup="true" aria-expanded="false" title="<?php esc_attr_e('Manage Log File', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-trash"></span>
								<span class="dashicons dashicons-arrow-down-alt2"></span>
							</button>
							<div class="aips-dropdown-menu aips-dropdown-menu-right" id="aips-logs-manage-menu" role="menu">
								<button type="button" class="aips-dropdown-item" id="aips-logs-empty-file-btn" role="menuitem">
									<span class="dashicons dashicons-editor-removeformatting"></span>
									<?php esc_html_e('Empty File (0 bytes)', 'ai-post-scheduler'); ?>
								</button>
								<button type="button" class="aips-dropdown-item aips-dropdown-item-danger" id="aips-logs-delete-file-btn" role="menuitem">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e('Delete File from Disk', 'ai-post-scheduler'); ?>
								</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Bottom Row: Filters, Search, Lines & Segmented View Switcher -->
				<div class="aips-logs-toolbar-bottom">
					<!-- Multi-Level Filter Pills -->
					<div class="aips-logs-level-filters" role="group" aria-label="<?php esc_attr_e('Filter logs by level', 'ai-post-scheduler'); ?>">
						<button type="button" class="aips-filter-pill is-active" data-level="all"><?php esc_html_e('All', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-filter-pill aips-pill-error" data-level="error"><?php esc_html_e('Error', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-filter-pill aips-pill-warning" data-level="warning"><?php esc_html_e('Warning', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-filter-pill aips-pill-info" data-level="info"><?php esc_html_e('Info', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-filter-pill aips-pill-debug" data-level="debug"><?php esc_html_e('Debug', 'ai-post-scheduler'); ?></button>
					</div>

					<!-- Right Controls: Search, Lines & Segmented Control -->
					<div class="aips-logs-controls-right">
						<!-- Search Box -->
						<div class="aips-logs-search-wrap">
							<span class="dashicons dashicons-search aips-search-icon"></span>
							<input type="text" id="aips-logs-search-input" class="aips-form-input aips-form-input-sm" placeholder="<?php esc_attr_e('Filter log entries…', 'ai-post-scheduler'); ?>" />
							<button type="button" class="aips-logs-search-clear" id="aips-logs-search-clear" title="<?php esc_attr_e('Clear search', 'ai-post-scheduler'); ?>" style="display:none;">
								<span class="dashicons dashicons-dismiss"></span>
							</button>
						</div>

						<!-- Compact Lines Selector -->
						<div class="aips-logs-lines-wrap">
							<label for="aips-logs-lines-select"><?php esc_html_e('Lines:', 'ai-post-scheduler'); ?></label>
							<select id="aips-logs-lines-select" class="aips-form-select aips-form-select-sm aips-logs-lines-select">
								<option value="100" selected>100</option>
								<option value="250">250</option>
								<option value="500">500</option>
								<option value="1000">1,000</option>
								<option value="2000">2,000</option>
							</select>
						</div>

						<!-- Segmented View Mode Switcher -->
						<div class="aips-segmented-control" role="tablist" aria-label="<?php esc_attr_e('View Mode', 'ai-post-scheduler'); ?>">
							<button type="button" class="aips-segmented-btn is-active" id="aips-logs-view-formatted-btn" role="tab" aria-selected="true">
								<span class="dashicons dashicons-list-view"></span>
								<span><?php esc_html_e('Formatted', 'ai-post-scheduler'); ?></span>
							</button>
							<button type="button" class="aips-segmented-btn" id="aips-logs-view-raw-btn" role="tab" aria-selected="false">
								<span class="dashicons dashicons-editor-code"></span>
								<span><?php esc_html_e('Raw', 'ai-post-scheduler'); ?></span>
							</button>
						</div>
					</div>
				</div>
			</div><!-- /.aips-logs-toolbar -->

			<!-- Truncation / Large File Notice -->
			<div class="aips-alert aips-alert-info aips-logs-truncation-notice" id="aips-logs-truncation-notice" style="display:none;">
				<span class="dashicons dashicons-info"></span>
				<div class="aips-alert-content">
					<p><?php esc_html_e('Displaying tail preview (truncated for performance). Use the Download button to obtain the complete raw file.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>

			<!-- Log Viewer Body -->
			<div class="aips-logs-body" id="aips-logs-body">
				<!-- Formatted View Container -->
				<div class="aips-logs-stream" id="aips-logs-stream" role="log" aria-live="polite">
					<div class="aips-logs-loading">
						<span class="dashicons dashicons-update aips-spin"></span>
						<p><?php esc_html_e('Loading log entries…', 'ai-post-scheduler'); ?></p>
					</div>
				</div>

				<!-- Raw Text View Container -->
				<div class="aips-logs-raw-wrap" id="aips-logs-raw-wrap" style="display:none;">
					<pre class="aips-logs-raw-code" id="aips-logs-raw-code"></pre>
				</div>
			</div>
		</section>
	</div><!-- /.aips-logs-master-detail -->

	<!-- Templates for Client-Side Rendering -->
	<script type="text/html" id="aips-tmpl-logs-file-item">
		<button type="button" class="aips-logs-file-item {{selectedClass}}" data-file="{{name}}" data-display-name="{{displayName}}" data-is-active="{{isActive}}" role="option" aria-selected="{{ariaSelected}}">
			<div class="aips-logs-file-item-header">
				<span class="aips-logs-file-title">{{displayName}}</span>
				{{activeBadge}}
			</div>
			<div class="aips-logs-file-name" title="{{name}}">{{name}}</div>
			<div class="aips-logs-file-meta">
				<span class="aips-logs-file-date">
					<span class="dashicons dashicons-clock"></span> {{modified}}
				</span>
				<span class="aips-logs-file-size">{{size}}</span>
			</div>
		</button>
	</script>

	<script type="text/html" id="aips-tmpl-logs-active-badge">
		<span class="aips-badge aips-badge-success aips-logs-active-badge"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></span>
	</script>

	<script type="text/html" id="aips-tmpl-logs-entry">
		<article class="aips-logs-entry {{levelClass}}">
			<div class="aips-logs-entry-header">
				<span class="aips-badge {{badgeClass}} aips-logs-entry-level">{{level}}</span>
				{{timeHtml}}
			</div>
			<div class="aips-logs-entry-message">{{message}}</div>
			{{contextHtml}}
		</article>
	</script>

	<script type="text/html" id="aips-tmpl-logs-entry-time">
		<time class="aips-logs-entry-time">{{timestamp}}</time>
	</script>

	<script type="text/html" id="aips-tmpl-logs-entry-context">
		<div class="aips-logs-entry-context-toolbar">
			<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-logs-entry-toggle-context" data-label-expand="<?php esc_attr_e('Show Context', 'ai-post-scheduler'); ?>" data-label-collapse="<?php esc_attr_e('Hide Context', 'ai-post-scheduler'); ?>">
				<?php esc_html_e('Show Context', 'ai-post-scheduler'); ?>
			</button>
		</div>
		<div class="aips-logs-entry-context-wrap" style="display:none;">
			<pre class="aips-logs-entry-context"><code>{{context}}</code></pre>
		</div>
	</script>

	<script type="text/html" id="aips-tmpl-logs-empty-view">
		<div class="aips-logs-empty-view">
			<span class="dashicons {{iconClass}}"></span>
			<p>{{message}}</p>
		</div>
	</script>

	<script type="text/html" id="aips-tmpl-logs-loading">
		<div class="aips-logs-loading">
			<span class="dashicons dashicons-update aips-spin"></span>
			<p>{{message}}</p>
		</div>
	</script>

	<script type="text/html" id="aips-tmpl-logs-empty-files">
		<div class="aips-logs-empty-files">
			<span class="dashicons dashicons-info"></span>
			<p>{{message}}</p>
		</div>
	</script>
</div>
