/**
 * Admin Logs Viewer Management
 *
 * Coordinates file selection, live tail polling, multi-level filtering,
 * formatted/raw segmented toggle, search, clipboard copying, and file operations.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * @namespace AIPS.DiagnosticsLogs
	 */
	AIPS.DiagnosticsLogs = {
		selectedFile: '',
		selectedDisplayName: '',
		lines: 100,
		selectedLevels: ['all'],
		search: '',
		viewMode: 'formatted',
		isAutoRefreshing: false,
		refreshInterval: 5000,
		timerId: null,
		cachedRaw: '',
		searchTimeout: null,

		/**
		 * Initialize controller.
		 */
		init: function () {
			// Determine initial selected file from active sidebar item
			var $activeItem = $('.aips-logs-file-item.is-selected');
			if ($activeItem.length) {
				this.selectedFile = $activeItem.data('file');
				this.selectedDisplayName = $activeItem.data('display-name') || this.selectedFile;
			} else {
				var $firstItem = $('.aips-logs-file-item').first();
				if ($firstItem.length) {
					$firstItem.addClass('is-selected').attr('aria-selected', 'true');
					this.selectedFile = $firstItem.data('file');
					this.selectedDisplayName = $firstItem.data('display-name') || this.selectedFile;
				}
			}

			this.bindEvents();

			if (this.selectedFile) {
				$('#aips-logs-current-display-name').text(this.selectedDisplayName);
				$('#aips-logs-current-filename').text(this.selectedFile);
				this.loadContent();
			} else {
				this.renderEmptyState(aipsLogsL10n.noLogsFound, false);
			}
		},

		/**
		 * Bind DOM and user interaction events.
		 * Delegates cleanly to named handler methods.
		 */
		bindEvents: function () {
			$(document).on('click', '.aips-logs-file-item', this.handleFileSelect.bind(this));
			$(document).on('click', '#aips-logs-refresh-files-btn', this.handleRefreshFilesClick.bind(this));
			$(document).on('click', '.aips-logs-level-filters .aips-filter-pill', this.handleLevelFilterClick.bind(this));
			$(document).on('input', '#aips-logs-search-input', this.handleSearchInput.bind(this));
			$(document).on('click', '#aips-logs-search-clear', this.handleSearchClear.bind(this));
			$(document).on('change', '#aips-logs-lines-select', this.handleLinesChange.bind(this));
			$(document).on('click', '#aips-logs-view-formatted-btn', this.handleViewFormattedClick.bind(this));
			$(document).on('click', '#aips-logs-view-raw-btn', this.handleViewRawClick.bind(this));
			$(document).on('click', '#aips-logs-autorefresh-toggle', this.handleAutoRefreshToggle.bind(this));
			$(document).on('change', '#aips-logs-autorefresh-interval', this.handleIntervalChange.bind(this));
			$(document).on('click', '#aips-logs-copy-btn', this.handleCopyClick.bind(this));
			$(document).on('click', '#aips-logs-download-btn', this.handleDownloadClick.bind(this));
			$(document).on('click', '#aips-logs-manage-menu-btn', this.handleManageMenuToggle.bind(this));
			$(document).on('click', this.handleDocumentClick.bind(this));
			$(document).on('click', '#aips-logs-empty-file-btn', this.handleEmptyFileClick.bind(this));
			$(document).on('click', '#aips-logs-delete-file-btn', this.handleDeleteFileClick.bind(this));
			$(document).on('click', '.aips-logs-entry-toggle-context', this.handleToggleContext.bind(this));
		},

		/**
		 * Handle click on a log file item in the sidebar.
		 *
		 * @param {jQuery.Event} e
		 */
		handleFileSelect: function (e) {
			e.preventDefault();
			var $target = $(e.currentTarget);
			var file = $target.data('file');
			var displayName = $target.data('display-name') || file;

			if (file && file !== this.selectedFile) {
				$('.aips-logs-file-item').removeClass('is-selected').attr('aria-selected', 'false');
				$target.addClass('is-selected').attr('aria-selected', 'true');
				this.selectedFile = file;
				this.selectedDisplayName = displayName;

				$('#aips-logs-current-display-name').text(displayName);
				$('#aips-logs-current-filename').text(file);
				this.loadContent();
			}
		},

		/**
		 * Handle refresh files list button click.
		 *
		 * @param {jQuery.Event} e
		 */
		handleRefreshFilesClick: function (e) {
			e.preventDefault();
			this.refreshFileList();
		},

		/**
		 * Handle multi-level filter pill click.
		 *
		 * @param {jQuery.Event} e
		 */
		handleLevelFilterClick: function (e) {
			e.preventDefault();
			var $target = $(e.currentTarget);
			var clickedLevel = $target.data('level');

			if (clickedLevel === 'all') {
				$('.aips-logs-level-filters .aips-filter-pill').removeClass('is-active');
				$target.addClass('is-active');
				this.selectedLevels = ['all'];
			} else {
				// Deselect 'all' when toggling an individual level
				$('.aips-logs-level-filters .aips-filter-pill[data-level="all"]').removeClass('is-active');
				$target.toggleClass('is-active');

				var activeLevels = [];
				$('.aips-logs-level-filters .aips-filter-pill:not([data-level="all"]).is-active').each(function () {
					activeLevels.push($(this).data('level'));
				});

				// If none or all 4 individual levels selected, reset to 'all'
				if (activeLevels.length === 0 || activeLevels.length === 4) {
					$('.aips-logs-level-filters .aips-filter-pill').removeClass('is-active');
					$('.aips-logs-level-filters .aips-filter-pill[data-level="all"]').addClass('is-active');
					this.selectedLevels = ['all'];
				} else {
					this.selectedLevels = activeLevels;
				}
			}

			this.loadContent();
		},

		/**
		 * Handle search input with debouncing.
		 *
		 * @param {jQuery.Event} e
		 */
		handleSearchInput: function (e) {
			var val = $(e.currentTarget).val();
			if (val.length > 0) {
				$('#aips-logs-search-clear').show();
			} else {
				$('#aips-logs-search-clear').hide();
			}

			clearTimeout(this.searchTimeout);
			var self = this;
			this.searchTimeout = setTimeout(function () {
				self.search = val;
				self.loadContent();
			}, 350);
		},

		/**
		 * Handle search clear button click.
		 *
		 * @param {jQuery.Event} e
		 */
		handleSearchClear: function (e) {
			e.preventDefault();
			$('#aips-logs-search-input').val('');
			$('#aips-logs-search-clear').hide();
			this.search = '';
			this.loadContent();
		},

		/**
		 * Handle change of lines limit dropdown.
		 *
		 * @param {jQuery.Event} e
		 */
		handleLinesChange: function (e) {
			this.lines = parseInt($(e.currentTarget).val(), 10) || 100;
			this.loadContent();
		},

		/**
		 * Handle switch to Formatted view mode.
		 *
		 * @param {jQuery.Event} e
		 */
		handleViewFormattedClick: function (e) {
			e.preventDefault();
			this.setViewMode('formatted');
		},

		/**
		 * Handle switch to Raw view mode.
		 *
		 * @param {jQuery.Event} e
		 */
		handleViewRawClick: function (e) {
			e.preventDefault();
			this.setViewMode('raw');
		},

		/**
		 * Handle toggle of auto-refresh.
		 *
		 * @param {jQuery.Event} e
		 */
		handleAutoRefreshToggle: function (e) {
			e.preventDefault();
			this.toggleAutoRefresh();
		},

		/**
		 * Handle change of auto-refresh interval.
		 *
		 * @param {jQuery.Event} e
		 */
		handleIntervalChange: function (e) {
			this.refreshInterval = parseInt($(e.currentTarget).val(), 10) || 5000;
			if (this.isAutoRefreshing) {
				this.stopAutoRefresh();
				this.startAutoRefresh();
			}
		},

		/**
		 * Handle copy to clipboard button click.
		 *
		 * @param {jQuery.Event} e
		 */
		handleCopyClick: function (e) {
			e.preventDefault();
			this.copyToClipboard();
		},

		/**
		 * Handle download file button click.
		 *
		 * @param {jQuery.Event} e
		 */
		handleDownloadClick: function (e) {
			e.preventDefault();
			this.downloadFile();
		},

		/**
		 * Handle manage dropdown menu toggle.
		 *
		 * @param {jQuery.Event} e
		 */
		handleManageMenuToggle: function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $menu = $('#aips-logs-manage-menu');
			var isOpen = $menu.is(':visible');
			$('.aips-dropdown-menu').hide();
			if (!isOpen) {
				$menu.show();
				$('#aips-logs-manage-menu-btn').attr('aria-expanded', 'true');
			} else {
				$('#aips-logs-manage-menu-btn').attr('aria-expanded', 'false');
			}
		},

		/**
		 * Handle click outside dropdown to close it.
		 */
		handleDocumentClick: function () {
			$('#aips-logs-manage-menu').hide();
			$('#aips-logs-manage-menu-btn').attr('aria-expanded', 'false');
		},

		/**
		 * Handle empty file action click.
		 *
		 * @param {jQuery.Event} e
		 */
		handleEmptyFileClick: function (e) {
			e.preventDefault();
			$('#aips-logs-manage-menu').hide();
			if (!confirm(aipsLogsL10n.confirmEmpty)) {
				return;
			}
			this.clearFile('empty');
		},

		/**
		 * Handle delete file action click.
		 *
		 * @param {jQuery.Event} e
		 */
		handleDeleteFileClick: function (e) {
			e.preventDefault();
			$('#aips-logs-manage-menu').hide();
			if (!confirm(aipsLogsL10n.confirmDelete)) {
				return;
			}
			this.clearFile('delete');
		},

		/**
		 * Handle toggle of context block in an entry.
		 *
		 * @param {jQuery.Event} e
		 */
		handleToggleContext: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var $wrapper = $btn.closest('.aips-logs-entry').find('.aips-logs-entry-context-wrap');
			if ($wrapper.is(':visible')) {
				$wrapper.slideUp(150);
				$btn.text($btn.data('label-expand') || 'Show Context');
			} else {
				$wrapper.slideDown(150);
				$btn.text($btn.data('label-collapse') || 'Hide Context');
			}
		},

		/**
		 * Load log content from the server.
		 *
		 * @param {boolean} silent If true, suppresses loading spinner (used for polling).
		 */
		loadContent: function (silent) {
			var self = this;
			if (!self.selectedFile) {
				return;
			}

			if (!silent) {
				if (window.AIPS && AIPS.Templates && AIPS.Templates.has('aips-tmpl-logs-loading')) {
					$('#aips-logs-stream').html(AIPS.Templates.render('aips-tmpl-logs-loading', {
						message: aipsLogsL10n.loadingLogs
					}));
				}
			}

			var levelsParam = self.selectedLevels.join(',');

			$.ajax({
				url: aipsLogsL10n.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_get_log_content',
					nonce: aipsLogsL10n.nonce,
					file: self.selectedFile,
					lines: self.lines,
					levels: levelsParam,
					search: self.search
				},
				success: function (res) {
					if (res && res.success && res.data) {
						var data = res.data;
						self.cachedRaw = data.raw_content || '';

						$('#aips-logs-current-filename').text(data.file || self.selectedFile);
						$('#aips-logs-current-filesize').text(self.formatBytes(data.total_bytes || 0));
						$('#aips-logs-current-linecount').text((data.total_lines || 0) + ' entries');

						if (data.is_truncated) {
							$('#aips-logs-truncation-notice').show();
						} else {
							$('#aips-logs-truncation-notice').hide();
						}

						self.renderEntries(data.entries || []);
						$('#aips-logs-raw-code').text(self.cachedRaw);
					} else {
						var msg = (res && res.data && res.data.message) ? res.data.message : aipsLogsL10n.errorLoading;
						self.renderEmptyState(msg, true);
					}
				},
				error: function () {
					self.renderEmptyState(aipsLogsL10n.errorLoading, true);
				}
			});
		},

		/**
		 * Render parsed log entries in formatted view using AIPS.Templates.
		 *
		 * @param {Array} entries
		 */
		renderEntries: function (entries) {
			var $stream = $('#aips-logs-stream');

			if (!entries || entries.length === 0) {
				this.renderEmptyState(aipsLogsL10n.noLogsFound, false);
				return;
			}

			var html = '';
			for (var i = 0; i < entries.length; i++) {
				var item = entries[i];
				var level = (item.level || 'INFO').toUpperCase();
				var badgeClass = 'aips-badge-info';

				if (level === 'ERROR' || level === 'FATAL') {
					badgeClass = 'aips-badge-danger';
				} else if (level === 'WARNING' || level === 'WARN') {
					badgeClass = 'aips-badge-warning';
				} else if (level === 'DEBUG') {
					badgeClass = 'aips-badge-purple';
				}

				var timeHtml = '';
				if (item.timestamp && window.AIPS && AIPS.Templates && AIPS.Templates.has('aips-tmpl-logs-entry-time')) {
					timeHtml = AIPS.Templates.render('aips-tmpl-logs-entry-time', {
						timestamp: item.timestamp
					});
				}

				var contextHtml = '';
				if (item.context && item.context.trim().length > 0 && window.AIPS && AIPS.Templates && AIPS.Templates.has('aips-tmpl-logs-entry-context')) {
					contextHtml = AIPS.Templates.render('aips-tmpl-logs-entry-context', {
						context: item.context
					});
				}

				if (window.AIPS && AIPS.Templates && AIPS.Templates.has('aips-tmpl-logs-entry')) {
					html += AIPS.Templates.renderRaw('aips-tmpl-logs-entry', {
						levelClass: 'aips-logs-entry-' + AIPS.Templates.escape(level.toLowerCase()),
						badgeClass: AIPS.Templates.escape(badgeClass),
						level: AIPS.Templates.escape(level),
						timeHtml: timeHtml,
						message: AIPS.Templates.escape(item.message),
						contextHtml: contextHtml
					});
				}
			}

			$stream.html(html);

			// If auto-refreshing, smoothly scroll to bottom
			if (this.isAutoRefreshing) {
				var bodyElem = document.getElementById('aips-logs-body');
				if (bodyElem) {
					bodyElem.scrollTop = bodyElem.scrollHeight;
				}
			}
		},

		/**
		 * Render empty or error message in viewer using AIPS.Templates.
		 *
		 * @param {string} message
		 * @param {boolean} isError
		 */
		renderEmptyState: function (message, isError) {
			var iconClass = isError ? 'dashicons-warning' : 'dashicons-info';
			if (window.AIPS && AIPS.Templates && AIPS.Templates.has('aips-tmpl-logs-empty-view')) {
				$('#aips-logs-stream').html(AIPS.Templates.render('aips-tmpl-logs-empty-view', {
					iconClass: iconClass,
					message: message
				}));
			}
			$('#aips-logs-raw-code').text('');
			$('#aips-logs-current-linecount').text('0 entries');
		},

		/**
		 * Switch between Formatted and Raw views.
		 *
		 * @param {string} mode ('formatted'|'raw')
		 */
		setViewMode: function (mode) {
			this.viewMode = mode;
			if (mode === 'raw') {
				$('#aips-logs-view-raw-btn').addClass('is-active').attr('aria-selected', 'true');
				$('#aips-logs-view-formatted-btn').removeClass('is-active').attr('aria-selected', 'false');
				$('#aips-logs-stream').hide();
				$('#aips-logs-raw-wrap').show();
			} else {
				$('#aips-logs-view-formatted-btn').addClass('is-active').attr('aria-selected', 'true');
				$('#aips-logs-view-raw-btn').removeClass('is-active').attr('aria-selected', 'false');
				$('#aips-logs-raw-wrap').hide();
				$('#aips-logs-stream').show();
			}
		},

		/**
		 * Toggle live polling auto-refresh.
		 */
		toggleAutoRefresh: function () {
			if (this.isAutoRefreshing) {
				this.stopAutoRefresh();
			} else {
				this.startAutoRefresh();
			}
		},

		/**
		 * Start live tail polling.
		 */
		startAutoRefresh: function () {
			var self = this;
			self.isAutoRefreshing = true;
			$('#aips-logs-autorefresh-toggle').addClass('aips-btn-primary').removeClass('aips-btn-secondary');
			$('#aips-logs-autorefresh-icon').removeClass('dashicons-controls-play').addClass('dashicons-controls-pause');
			$('#aips-logs-autorefresh-label').text('Tailing...');

			self.timerId = setInterval(function () {
				self.loadContent(true);
			}, self.refreshInterval);
		},

		/**
		 * Stop live tail polling.
		 */
		stopAutoRefresh: function () {
			this.isAutoRefreshing = false;
			if (this.timerId) {
				clearInterval(this.timerId);
				this.timerId = null;
			}
			$('#aips-logs-autorefresh-toggle').removeClass('aips-btn-primary').addClass('aips-btn-secondary');
			$('#aips-logs-autorefresh-icon').removeClass('dashicons-controls-pause').addClass('dashicons-controls-play');
			$('#aips-logs-autorefresh-label').text('Auto-Refresh');
		},

		/**
		 * Copy current view content to clipboard.
		 */
		copyToClipboard: function () {
			var text = this.cachedRaw;
			if (!text) {
				return;
			}

			var onCopied = function () {
				if (window.AIPS && AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
					AIPS.Utilities.showToast(aipsLogsL10n.copiedToClipboard, 'success');
				} else {
					alert(aipsLogsL10n.copiedToClipboard);
				}
			};

			var onFailed = function () {
				if (window.AIPS && AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
					AIPS.Utilities.showToast(aipsLogsL10n.copyFailed, 'error');
				} else {
					alert(aipsLogsL10n.copyFailed);
				}
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(onCopied).catch(onFailed);
			} else {
				try {
					var $temp = $('<textarea>');
					$('body').append($temp);
					$temp.val(text).select();
					document.execCommand('copy');
					$temp.remove();
					onCopied();
				} catch (err) {
					onFailed();
				}
			}
		},

		/**
		 * Download the complete raw log file directly.
		 */
		downloadFile: function () {
			if (!this.selectedFile) {
				return;
			}

			var downloadUrl = aipsLogsL10n.ajaxUrl + '?action=aips_download_log&file=' + encodeURIComponent(this.selectedFile) + '&nonce=' + encodeURIComponent(aipsLogsL10n.nonce);
			window.location.href = downloadUrl;
		},

		/**
		 * Empty or delete log file.
		 *
		 * @param {string} mode ('empty'|'delete')
		 */
		clearFile: function (mode) {
			var self = this;
			if (!self.selectedFile) {
				return;
			}

			$.ajax({
				url: aipsLogsL10n.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_clear_log',
					nonce: aipsLogsL10n.nonce,
					file: self.selectedFile,
					mode: mode
				},
				success: function (res) {
					if (res && res.success) {
						if (mode === 'delete') {
							self.refreshFileList();
						} else {
							self.loadContent();
						}
					} else {
						var msg = (res && res.data && res.data.message) ? res.data.message : 'Action failed.';
						if (window.AIPS && AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
							AIPS.Utilities.showToast(msg, 'error');
						} else {
							alert(msg);
						}
					}
				},
				error: function () {
					if (window.AIPS && AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
						AIPS.Utilities.showToast('Request failed.', 'error');
					} else {
						alert('Request failed.');
					}
				}
			});
		},

		/**
		 * Fetch updated list of log files for the sidebar using AIPS.Templates.
		 */
		refreshFileList: function () {
			var self = this;
			$.ajax({
				url: aipsLogsL10n.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_get_log_files',
					nonce: aipsLogsL10n.nonce
				},
				success: function (res) {
					if (res && res.success && res.data && res.data.files) {
						var files = res.data.files;
						$('#aips-logs-file-count').text(files.length);

						if (files.length === 0) {
							if (window.AIPS && AIPS.Templates && AIPS.Templates.has('aips-tmpl-logs-empty-files')) {
								$('#aips-logs-file-list').html(AIPS.Templates.render('aips-tmpl-logs-empty-files', {
									message: aipsLogsL10n.noLogsFound
								}));
							}
							self.selectedFile = '';
							self.selectedDisplayName = '';
							self.renderEmptyState(aipsLogsL10n.noLogsFound, false);
							return;
						}

						var html = '';
						var fileStillExists = false;

						for (var i = 0; i < files.length; i++) {
							var file = files[i];
							var isSelected = (file.name === self.selectedFile);
							if (isSelected) {
								fileStillExists = true;
							}

							var displayName = file.display_name || file.name;
							var activeBadge = '';
							if (file.is_active && window.AIPS && AIPS.Templates && AIPS.Templates.has('aips-tmpl-logs-active-badge')) {
								activeBadge = AIPS.Templates.render('aips-tmpl-logs-active-badge', {});
							}

							if (window.AIPS && AIPS.Templates && AIPS.Templates.has('aips-tmpl-logs-file-item')) {
								html += AIPS.Templates.renderRaw('aips-tmpl-logs-file-item', {
									selectedClass: isSelected ? 'is-selected' : '',
									name: AIPS.Templates.escape(file.name),
									displayName: AIPS.Templates.escape(displayName),
									isActive: file.is_active ? '1' : '0',
									ariaSelected: isSelected ? 'true' : 'false',
									activeBadge: activeBadge,
									modified: AIPS.Templates.escape(file.modified),
									size: AIPS.Templates.escape(file.size)
								});
							}
						}

						$('#aips-logs-file-list').html(html);

						if (!fileStillExists && files.length > 0) {
							self.selectedFile = files[0].name;
							self.selectedDisplayName = files[0].display_name || files[0].name;
							$('.aips-logs-file-item').first().addClass('is-selected').attr('aria-selected', 'true');
							$('#aips-logs-current-display-name').text(self.selectedDisplayName);
							$('#aips-logs-current-filename').text(self.selectedFile);
							self.loadContent();
						}
					}
				}
			});
		},

		/**
		 * Format bytes into human-readable text.
		 *
		 * @param {number} bytes
		 * @return {string}
		 */
		formatBytes: function (bytes) {
			if (!bytes || bytes <= 0) {
				return '0 B';
			}
			var k = 1024;
			var sizes = ['B', 'KB', 'MB', 'GB'];
			var i = Math.floor(Math.log(bytes) / Math.log(k));
			return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
		}
	};

	$(document).ready(function () {
		if ($('#aips-tab-logs-pane').length) {
			AIPS.DiagnosticsLogs.init();
		}
	});

})(jQuery);
