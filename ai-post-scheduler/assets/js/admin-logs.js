/**
 * Admin Logs Viewer Management
 *
 * Coordinates file selection, live tail polling, formatted/raw views,
 * search/level filtering, clipboard copying, downloads, and clearing.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

(function ($) {
	'use strict';

	var AipsLogs = {
		selectedFile: '',
		lines: 100,
		level: 'all',
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
			var self = this;

			// Determine initial selected file from active sidebar item
			var $activeItem = $('.aips-logs-file-item.is-selected');
			if ($activeItem.length) {
				self.selectedFile = $activeItem.data('file');
			} else {
				var $firstItem = $('.aips-logs-file-item').first();
				if ($firstItem.length) {
					$firstItem.addClass('is-selected');
					self.selectedFile = $firstItem.data('file');
				}
			}

			self.bindEvents();

			if (self.selectedFile) {
				self.loadContent();
			} else {
				self.renderEmptyState(aipsLogsL10n.noLogsFound);
			}
		},

		/**
		 * Bind DOM and user interaction events.
		 */
		bindEvents: function () {
			var self = this;

			// File selection in sidebar
			$(document).on('click', '.aips-logs-file-item', function (e) {
				e.preventDefault();
				var file = $(this).data('file');
				if (file && file !== self.selectedFile) {
					$('.aips-logs-file-item').removeClass('is-selected').attr('aria-selected', 'false');
					$(this).addClass('is-selected').attr('aria-selected', 'true');
					self.selectedFile = file;
					$('#aips-logs-current-filename').text(file);
					self.loadContent();
				}
			});

			// Refresh files list button
			$('#aips-logs-refresh-files-btn').on('click', function (e) {
				e.preventDefault();
				self.refreshFileList();
			});

			// Level filter pills
			$(document).on('click', '.aips-logs-level-filters .aips-filter-pill', function (e) {
				e.preventDefault();
				$('.aips-logs-level-filters .aips-filter-pill').removeClass('is-active');
				$(this).addClass('is-active');
				self.level = $(this).data('level') || 'all';
				self.loadContent();
			});

			// Search input with debounce
			$('#aips-logs-search-input').on('input', function () {
				var val = $(this).val();
				if (val.length > 0) {
					$('#aips-logs-search-clear').show();
				} else {
					$('#aips-logs-search-clear').hide();
				}

				clearTimeout(self.searchTimeout);
				self.searchTimeout = setTimeout(function () {
					self.search = val;
					self.loadContent();
				}, 350);
			});

			// Clear search button
			$('#aips-logs-search-clear').on('click', function (e) {
				e.preventDefault();
				$('#aips-logs-search-input').val('');
				$(this).hide();
				self.search = '';
				self.loadContent();
			});

			// Lines dropdown selector
			$('#aips-logs-lines-select').on('change', function () {
				self.lines = parseInt($(this).val(), 10) || 100;
				self.loadContent();
			});

			// View Mode Toggle (Formatted vs Raw)
			$('#aips-logs-view-formatted-btn').on('click', function (e) {
				e.preventDefault();
				self.setViewMode('formatted');
			});

			$('#aips-logs-view-raw-btn').on('click', function (e) {
				e.preventDefault();
				self.setViewMode('raw');
			});

			// Auto-Refresh Toggle
			$('#aips-logs-autorefresh-toggle').on('click', function (e) {
				e.preventDefault();
				self.toggleAutoRefresh();
			});

			$('#aips-logs-autorefresh-interval').on('change', function () {
				self.refreshInterval = parseInt($(this).val(), 10) || 5000;
				if (self.isAutoRefreshing) {
					self.stopAutoRefresh();
					self.startAutoRefresh();
				}
			});

			// Copy to Clipboard
			$('#aips-logs-copy-btn').on('click', function (e) {
				e.preventDefault();
				self.copyToClipboard();
			});

			// Download Log File
			$('#aips-logs-download-btn').on('click', function (e) {
				e.preventDefault();
				self.downloadFile();
			});

			// Manage Dropdown Toggle
			$('#aips-logs-manage-menu-btn').on('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var $menu = $('#aips-logs-manage-menu');
				var isOpen = $menu.is(':visible');
				$('.aips-dropdown-menu').hide();
				if (!isOpen) {
					$menu.show();
					$(this).attr('aria-expanded', 'true');
				} else {
					$(this).attr('aria-expanded', 'false');
				}
			});

			$(document).on('click', function () {
				$('#aips-logs-manage-menu').hide();
				$('#aips-logs-manage-menu-btn').attr('aria-expanded', 'false');
			});

			// Empty File Action
			$('#aips-logs-empty-file-btn').on('click', function (e) {
				e.preventDefault();
				$('#aips-logs-manage-menu').hide();
				if (!confirm(aipsLogsL10n.confirmEmpty)) {
					return;
				}
				self.clearFile('empty');
			});

			// Delete File Action
			$('#aips-logs-delete-file-btn').on('click', function (e) {
				e.preventDefault();
				$('#aips-logs-manage-menu').hide();
				if (!confirm(aipsLogsL10n.confirmDelete)) {
					return;
				}
				self.clearFile('delete');
			});

			// Expand / Collapse context in entry
			$(document).on('click', '.aips-logs-entry-toggle-context', function (e) {
				e.preventDefault();
				var $wrapper = $(this).closest('.aips-logs-entry').find('.aips-logs-entry-context-wrap');
				if ($wrapper.is(':visible')) {
					$wrapper.slideUp(150);
					$(this).text($(this).data('label-expand') || 'Show Context');
				} else {
					$wrapper.slideDown(150);
					$(this).text($(this).data('label-collapse') || 'Hide Context');
				}
			});
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
				$('#aips-logs-stream').html(
					'<div class="aips-logs-loading">' +
					'<span class="dashicons dashicons-update aips-spin"></span>' +
					'<p>' + self.escapeHtml(aipsLogsL10n.loadingLogs) + '</p>' +
					'</div>'
				);
			}

			$.ajax({
				url: aipsLogsL10n.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_get_log_content',
					nonce: aipsLogsL10n.nonce,
					file: self.selectedFile,
					lines: self.lines,
					level: self.level,
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
		 * Render parsed log entries in formatted view.
		 *
		 * @param {Array} entries
		 */
		renderEntries: function (entries) {
			var self = this;
			var $stream = $('#aips-logs-stream');

			if (!entries || entries.length === 0) {
				self.renderEmptyState(aipsLogsL10n.noLogsFound);
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
				}

				html += '<article class="aips-logs-entry aips-logs-entry-' + self.escapeHtml(level.toLowerCase()) + '">';
				html += '<div class="aips-logs-entry-header">';
				html += '<span class="aips-badge ' + badgeClass + ' aips-logs-entry-level">' + self.escapeHtml(level) + '</span>';
				if (item.timestamp) {
					html += '<time class="aips-logs-entry-time">' + self.escapeHtml(item.timestamp) + '</time>';
				}
				html += '</div>';

				html += '<div class="aips-logs-entry-message">' + self.escapeHtml(item.message) + '</div>';

				if (item.context && item.context.trim().length > 0) {
					html += '<div class="aips-logs-entry-context-toolbar">';
					html += '<button type="button" class="aips-btn aips-btn-xs aips-btn-ghost aips-logs-entry-toggle-context" data-label-expand="Show Context" data-label-collapse="Hide Context">';
					html += 'Show Context';
					html += '</button>';
					html += '</div>';
					html += '<div class="aips-logs-entry-context-wrap" style="display:none;">';
					html += '<pre class="aips-logs-entry-context"><code>' + self.escapeHtml(item.context) + '</code></pre>';
					html += '</div>';
				}

				html += '</article>';
			}

			$stream.html(html);

			// If auto-refreshing, smoothly scroll to bottom
			if (self.isAutoRefreshing) {
				var bodyElem = document.getElementById('aips-logs-body');
				if (bodyElem) {
					bodyElem.scrollTop = bodyElem.scrollHeight;
				}
			}
		},

		/**
		 * Render empty or error message in viewer.
		 *
		 * @param {string} message
		 * @param {boolean} isError
		 */
		renderEmptyState: function (message, isError) {
			var icon = isError ? 'dashicons-warning' : 'dashicons-info';
			var iconColor = isError ? 'style="color:#d63638;"' : '';
			var html = '<div class="aips-logs-empty-view">' +
				'<span class="dashicons ' + icon + '" ' + iconColor + '></span>' +
				'<p>' + this.escapeHtml(message) + '</p>' +
				'</div>';
			$('#aips-logs-stream').html(html);
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
				$('#aips-logs-view-raw-btn').addClass('is-active');
				$('#aips-logs-view-formatted-btn').removeClass('is-active');
				$('#aips-logs-stream').hide();
				$('#aips-logs-raw-wrap').show();
			} else {
				$('#aips-logs-view-formatted-btn').addClass('is-active');
				$('#aips-logs-view-raw-btn').removeClass('is-active');
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

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () {
					alert(aipsLogsL10n.copiedToClipboard);
				}).catch(function () {
					alert(aipsLogsL10n.copyFailed);
				});
			} else {
				var $temp = $('<textarea>');
				$('body').append($temp);
				$temp.val(text).select();
				document.execCommand('copy');
				$temp.remove();
				alert(aipsLogsL10n.copiedToClipboard);
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
						alert(msg);
					}
				},
				error: function () {
					alert('Request failed.');
				}
			});
		},

		/**
		 * Fetch updated list of log files for the sidebar.
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
							$('#aips-logs-file-list').html(
								'<div class="aips-logs-empty-files">' +
								'<span class="dashicons dashicons-info"></span>' +
								'<p>' + self.escapeHtml(aipsLogsL10n.noLogsFound) + '</p>' +
								'</div>'
							);
							self.selectedFile = '';
							self.renderEmptyState(aipsLogsL10n.noLogsFound);
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

							html += '<button type="button" class="aips-logs-file-item ' + (isSelected ? 'is-selected' : '') + '" data-file="' + self.escapeHtml(file.name) + '" role="option" aria-selected="' + (isSelected ? 'true' : 'false') + '">';
							html += '<div class="aips-logs-file-item-header">';
							html += '<span class="aips-logs-file-name" title="' + self.escapeHtml(file.name) + '">' + self.escapeHtml(file.name) + '</span>';
							if (file.is_active) {
								html += '<span class="aips-badge aips-badge-success aips-logs-active-badge">Active</span>';
							}
							html += '</div>';
							html += '<div class="aips-logs-file-meta">';
							html += '<span class="aips-logs-file-size">' + self.escapeHtml(file.size) + '</span>';
							html += '<span class="aips-logs-file-date">' + self.escapeHtml(file.modified) + '</span>';
							html += '</div>';
							html += '</button>';
						}

						$('#aips-logs-file-list').html(html);

						if (!fileStillExists && files.length > 0) {
							self.selectedFile = files[0].name;
							$('.aips-logs-file-item').first().addClass('is-selected').attr('aria-selected', 'true');
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
		},

		/**
		 * HTML escape helper.
		 *
		 * @param {string} str
		 * @return {string}
		 */
		escapeHtml: function (str) {
			if (str === null || str === undefined) {
				return '';
			}
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}
	};

	$(document).ready(function () {
		if ($('#aips-tab-logs-pane').length) {
			AipsLogs.init();
		}
	});

})(jQuery);
