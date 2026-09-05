/**
 * Editor Sidebar Meta-Box Module
 *
 * Handles live link suggestions, 1-click editor insertion (TinyMCE / textarea),
 * URL copying, and real-time SEO metrics for authors in WordPress post edit screens.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

(function ($) {
	'use strict';

	var AipsEditorMetaBox = {
		postId: 0,
		seoLoaded: false,
		searchTimer: null,

		init: function () {
			if (typeof aipsEditorMetaBox === 'undefined') {
				return;
			}

			this.postId = parseInt(aipsEditorMetaBox.postId, 10) || 0;
			this.bindEvents();
			this.loadSuggestions('');
			this.loadSeoMetrics();
		},

		bindEvents: function () {
			var self = this;

			// Tab switching
			$(document).on('click', '.aips-mb-tab-btn', function (e) {
				e.preventDefault();
				var tab = $(this).data('tab');
				$('.aips-mb-tab-btn').removeClass('active');
				$(this).addClass('active');

				if (tab === 'suggestions') {
					$('#aips-mb-panel-suggestions').show();
					$('#aips-mb-panel-seo').hide();
				} else {
					$('#aips-mb-panel-suggestions').hide();
					$('#aips-mb-panel-seo').show();
					if (!self.seoLoaded) {
						self.loadSeoMetrics();
					}
				}
			});

			// Search input debounce
			$(document).on('input', '#aips-mb-search-input', function () {
				var val = $(this).val().trim();
				clearTimeout(self.searchTimer);
				self.searchTimer = setTimeout(function () {
					self.loadSuggestions(val);
				}, 400);
			});

			// Search button click
			$(document).on('click', '#aips-mb-search-btn', function (e) {
				e.preventDefault();
				var val = $('#aips-mb-search-input').val().trim();
				self.loadSuggestions(val);
			});

			// Search enter key
			$(document).on('keydown', '#aips-mb-search-input', function (e) {
				if (e.which === 13) {
					e.preventDefault();
					var val = $(this).val().trim();
					self.loadSuggestions(val);
				}
			});

			// Insert link button
			$(document).on('click', '.aips-mb-insert-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var url = $btn.data('url');
				var title = $btn.data('title');
				self.insertLinkIntoEditor(url, title, $btn);
			});

			// Copy URL button
			$(document).on('click', '.aips-mb-copy-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var url = $btn.data('url');
				self.copyToClipboard(url, $btn);
			});
		},

		/**
		 * Fetch link suggestions via REST API.
		 */
		loadSuggestions: function (query) {
			var self = this;
			var $list = $('#aips-mb-suggestions-list');

			$list.html('<div class="aips-mb-loading"><span class="spinner is-active" style="float:none;vertical-align:middle;margin-right:6px;"></span>' + (aipsEditorMetaBox.i18n.loading || 'Searching…') + '</div>');

			// Get editor draft text for context if query is empty
			var draftContent = '';
			if (!query) {
				draftContent = self.getEditorContentSnippet();
			}

			$.ajax({
				url: aipsEditorMetaBox.restUrl + 'link-suggestions',
				method: 'POST',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', aipsEditorMetaBox.restNonce);
				},
				data: JSON.stringify({
					post_id: self.postId,
					content: draftContent,
					query: query || '',
					limit: 6
				}),
				contentType: 'application/json',
				dataType: 'json'
			}).done(function (response) {
				var items = Array.isArray(response) ? response : (response.data || []);
				if (!items || items.length === 0) {
					$list.html('<div class="aips-mb-empty">' + (aipsEditorMetaBox.i18n.noSuggestions || 'No suggestions found.') + '</div>');
					return;
				}

				var html = '';
				$.each(items, function (i, item) {
					var safeTitle = $('<div>').text(item.title || '(No title)').html();
					var safeUrl = $('<div>').text(item.url || '').html();
					var score = item.similarity ? Math.round(parseFloat(item.similarity) * 100) + '%' : 'Relevance';

					html += '<div class="aips-mb-card">' +
						'<div class="aips-mb-card-header">' +
							'<a href="' + safeUrl + '" target="_blank" class="aips-mb-card-title">' + safeTitle + '</a>' +
							'<span class="aips-mb-badge aips-mb-badge-blue">' + score + '</span>' +
						'</div>' +
						'<span class="aips-mb-card-url">' + safeUrl + '</span>' +
						'<div class="aips-mb-card-actions">' +
							'<button type="button" class="button button-secondary aips-mb-btn-action aips-mb-copy-btn" data-url="' + safeUrl + '">' +
								'<span class="dashicons dashicons-clipboard" style="font-size:12px;vertical-align:middle;margin-right:2px;"></span>' +
								(aipsEditorMetaBox.i18n.copyUrl || 'Copy') +
							'</button>' +
							'<button type="button" class="button button-primary aips-mb-btn-action aips-mb-insert-btn" data-url="' + safeUrl + '" data-title="' + safeTitle + '">' +
								'<span class="dashicons dashicons-admin-links" style="font-size:12px;vertical-align:middle;margin-right:2px;"></span>' +
								(aipsEditorMetaBox.i18n.insertLink || 'Insert') +
							'</button>' +
						'</div>' +
					'</div>';
				});

				$list.html(html);
			}).fail(function () {
				$list.html('<div class="aips-mb-empty" style="color:#d63638;">Failed to load suggestions.</div>');
			});
		},

		/**
		 * Insert internal link into TinyMCE or textarea editor.
		 */
		insertLinkIntoEditor: function (url, title, $btn) {
			var inserted = false;

			// 1. TinyMCE Visual Editor
			if (typeof window.tinyMCE !== 'undefined' && window.tinyMCE.activeEditor && !window.tinyMCE.activeEditor.isHidden()) {
				var ed = window.tinyMCE.activeEditor;
				var selected = ed.selection.getContent({ format: 'text' });
				var anchor = (selected && selected.trim().length > 0) ? selected : title;
				var linkHtml = '<a href="' + url + '">' + anchor + '</a>';
				ed.execCommand('mceInsertContent', false, linkHtml);
				inserted = true;
			}
			// 2. Standard #content textarea (Text tab / Classic Editor)
			else if ($('#content').length) {
				var textarea = $('#content')[0];
				var start = textarea.selectionStart;
				var end = textarea.selectionEnd;
				var selText = '';

				if (typeof start === 'number' && typeof end === 'number') {
					selText = textarea.value.substring(start, end);
					var anchorText = (selText && selText.trim().length > 0) ? selText : title;
					var linkCode = '<a href="' + url + '">' + anchorText + '</a>';
					textarea.value = textarea.value.substring(0, start) + linkCode + textarea.value.substring(end);
					textarea.selectionStart = textarea.selectionEnd = start + linkCode.length;
					textarea.focus();
					inserted = true;
				} else {
					textarea.value += '<a href="' + url + '">' + title + '</a>';
					inserted = true;
				}
			}
			// 3. Fallback to WordPress send_to_editor
			else if (typeof window.send_to_editor === 'function') {
				window.send_to_editor('<a href="' + url + '">' + title + '</a>');
				inserted = true;
			}

			if (inserted) {
				var originalHtml = $btn.html();
				$btn.prop('disabled', true).html('<span class="dashicons dashicons-yes" style="font-size:12px;vertical-align:middle;margin-right:2px;"></span> ' + (aipsEditorMetaBox.i18n.inserted || 'Inserted!'));
				setTimeout(function () {
					$btn.prop('disabled', false).html(originalHtml);
				}, 1800);
			}
		},

		/**
		 * Copy URL to clipboard.
		 */
		copyToClipboard: function (url, $btn) {
			var self = this;
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(function () {
					self.showCopiedState($btn);
				});
			} else {
				var $temp = $('<input>').val(url).appendTo('body').select();
				document.execCommand('copy');
				$temp.remove();
				self.showCopiedState($btn);
			}
		},

		showCopiedState: function ($btn) {
			var orig = $btn.html();
			$btn.html('<span class="dashicons dashicons-yes" style="font-size:12px;vertical-align:middle;margin-right:2px;"></span> ' + (aipsEditorMetaBox.i18n.copied || 'Copied!'));
			setTimeout(function () {
				$btn.html(orig);
			}, 1800);
		},

		/**
		 * Read snippet of editor content for relevance searching.
		 */
		getEditorContentSnippet: function () {
			if (typeof window.tinyMCE !== 'undefined' && window.tinyMCE.activeEditor && !window.tinyMCE.activeEditor.isHidden()) {
				var txt = window.tinyMCE.activeEditor.getContent({ format: 'text' }) || '';
				return txt.substring(0, 300);
			}
			if ($('#content').length) {
				return ($('#content').val() || '').substring(0, 300);
			}
			return '';
		},

		/**
		 * Load post SEO metrics via REST API.
		 */
		loadSeoMetrics: function () {
			var self = this;
			var $wrap = $('#aips-mb-seo-metrics-wrap');

			if (!self.postId) {
				$wrap.html('<div class="aips-mb-empty">Save this post first to view SEO link metrics.</div>');
				return;
			}

			$.ajax({
				url: aipsEditorMetaBox.restUrl + 'post-seo-metrics?post_id=' + self.postId,
				method: 'GET',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', aipsEditorMetaBox.restNonce);
				},
				dataType: 'json'
			}).done(function (data) {
				self.seoLoaded = true;

				var inbound = data.inbound_count !== undefined ? data.inbound_count : 0;
				var outbound = data.outbound_count !== undefined ? data.outbound_count : 0;
				var depth = data.depth_level !== undefined ? data.depth_level : 0;
				var isOrphan = !!data.is_orphan;
				var equityTier = data.equity_tier || 'standard';

				var orphanAlertHtml = '';
				if (isOrphan) {
					orphanAlertHtml = '<div class="aips-mb-alert-orphan">' +
						'<strong>⚠️ Orphan Post Detected</strong>' +
						(aipsEditorMetaBox.i18n.orphanAlert || '0 inbound links pointing to this article. Check linking opportunities to build internal equity.') +
					'</div>';
				}

				var depthLabel = depth === 0 ? 'L0 (Root)' : (depth >= 3 ? 'L' + depth + ' (Deep)' : 'L' + depth);
				var tierLabel = equityTier === 'hub' ? '🌟 Pillar Hub' : (isOrphan ? '⚠️ Orphan' : 'Healthy');
				var tierClass = equityTier === 'hub' ? 'aips-mb-badge-green' : (isOrphan ? 'aips-mb-badge-orange' : 'aips-mb-badge-blue');

				var html = orphanAlertHtml +
					'<div class="aips-mb-seo-grid">' +
						'<div class="aips-mb-metric-card">' +
							'<p class="aips-mb-metric-label">' + (aipsEditorMetaBox.i18n.inboundLinks || 'Inbound') + '</p>' +
							'<p class="aips-mb-metric-val" style="color:#2563eb;">' + inbound + '</p>' +
						'</div>' +
						'<div class="aips-mb-metric-card">' +
							'<p class="aips-mb-metric-label">' + (aipsEditorMetaBox.i18n.outboundLinks || 'Outbound') + '</p>' +
							'<p class="aips-mb-metric-val" style="color:#0f172a;">' + outbound + '</p>' +
						'</div>' +
						'<div class="aips-mb-metric-card">' +
							'<p class="aips-mb-metric-label">' + (aipsEditorMetaBox.i18n.crawlDepth || 'Depth') + '</p>' +
							'<p class="aips-mb-metric-val" style="font-size:14px;line-height:27px;">' + depthLabel + '</p>' +
						'</div>' +
						'<div class="aips-mb-metric-card">' +
							'<p class="aips-mb-metric-label">' + (aipsEditorMetaBox.i18n.seoStatus || 'Status') + '</p>' +
							'<p class="aips-mb-metric-val" style="font-size:13px;line-height:27px;"><span class="aips-mb-badge ' + tierClass + '">' + tierLabel + '</span></p>' +
						'</div>' +
					'</div>';

				$wrap.html(html);
			}).fail(function () {
				$wrap.html('<div class="aips-mb-empty" style="color:#d63638;">Failed to load SEO metrics.</div>');
			});
		}
	};

	$(document).ready(function () {
		AipsEditorMetaBox.init();
	});

})(jQuery);
