/**
 * AI Post Scheduler - Classic Editor Meta Box Logic
 *
 * Provides dedicated per-component AI Edit widgets (Title, Excerpt, Content, Featured Image)
 * with inline Side-by-Side comparison, Accept & Replace, Discard, and View Revisions drawer.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */
(function ($) {
	'use strict';

	var l10n = window.aipsEditorL10n || {};

	function escapeHtml(str) {
		if (typeof str !== 'string') {
			return '';
		}
		return str
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function getCurrentComponentValue(component) {
		if ('title' === component) {
			var $titleInput = $('#title');
			if ($titleInput.length && $titleInput.val()) {
				return $titleInput.val();
			}
			return $('#aips-editor-input-title').val() || '';
		}
		if ('excerpt' === component) {
			var $excerptArea = $('#excerpt');
			if ($excerptArea.length && $excerptArea.val()) {
				return $excerptArea.val();
			}
			return $('#aips-editor-input-excerpt').val() || '';
		}
		if ('content' === component) {
			if (window.tinymce && window.tinymce.get('content') && !window.tinymce.get('content').isHidden()) {
				return window.tinymce.get('content').getContent();
			}
			return $('#content').val() || '';
		}
		if ('featured_image' === component) {
			var thumbId = $('#_thumbnail_id').val();
			return thumbId && thumbId !== '-1' ? thumbId : '';
		}
		return '';
	}

	function applyComponentValue(component, val) {
		if ('title' === component) {
			var textVal = typeof val === 'object' && val !== null ? (val.value || '') : String(val || '');
			$('#title').val(textVal).trigger('change').trigger('input');
			$('#aips-editor-input-title').val(textVal).trigger('input');
		} else if ('excerpt' === component) {
			var textVal = typeof val === 'object' && val !== null ? (val.value || '') : String(val || '');
			$('#excerpt').val(textVal).trigger('change').trigger('input');
			$('#aips-editor-input-excerpt').val(textVal).trigger('input');
		} else if ('content' === component) {
			var htmlVal = typeof val === 'object' && val !== null ? (val.value || '') : String(val || '');
			if (window.tinymce && window.tinymce.get('content') && !window.tinymce.get('content').isHidden()) {
				window.tinymce.get('content').setContent(htmlVal);
			}
			$('#content').val(htmlVal).trigger('change');
			$('.aips-classic-component-widget[data-component="content"] .aips-content-preview').html(htmlVal.substring(0, 300) + '…');
		} else if ('featured_image' === component) {
			var attachmentId = 0;
			var imgUrl = '';
			if (typeof val === 'object' && val !== null) {
				attachmentId = val.attachment_id || val.id || 0;
				imgUrl = val.url || '';
			} else {
				attachmentId = parseInt(val, 10) || 0;
			}

			if (attachmentId > 0) {
				$('#_thumbnail_id').val(attachmentId);
				if (typeof window.WPSetThumbnailID === 'function') {
					window.WPSetThumbnailID(attachmentId);
				}
				if (imgUrl) {
					var $preview = $('.aips-classic-component-widget[data-component="featured_image"] .aips-featured-image-preview');
					$preview.html('<img src="' + escapeHtml(imgUrl) + '" alt="" style="max-width:100%;height:auto;border:1px solid #dcdcde;border-radius:4px;" />');
				}
			}
		}
	}

	function updateCharCount($widget, text) {
		var $counter = $widget.find('.aips-char-count');
		if ($counter.length) {
			var len = (text || '').length;
			$counter.text(len + ' ' + (l10n.labels.characters || 'characters'));
		}
	}

	function loadRevisions($widget, component) {
		var $drawer = $widget.find('.aips-component-revisions');
		var $loading = $widget.find('.aips-revisions-loading');
		var $list = $widget.find('.aips-revisions-list');
		var $empty = $widget.find('.aips-revisions-empty');
		var $countBadge = $widget.find('.revision-count');

		$loading.show();
		$list.empty();
		$empty.hide();

		$.post(l10n.ajaxurl, {
			action: 'aips_get_component_revisions',
			nonce: l10n.nonce,
			post_id: l10n.postId,
			component: component
		})
		.done(function (response) {
			if (response && response.success && response.data) {
				var revisions = response.data.revisions || [];
				if (revisions.length > 0) {
					$countBadge.text(revisions.length).show();
					renderRevisionsList($widget, component, revisions);
				} else {
					$countBadge.text('0').hide();
					$empty.show();
				}
			} else {
				$empty.show();
			}
		})
		.fail(function () {
			$empty.show();
		})
		.always(function () {
			$loading.hide();
		});
	}

	function renderRevisionsList($widget, component, revisions) {
		var $list = $widget.find('.aips-revisions-list');
		$list.empty();

		$.each(revisions, function (idx, rev) {
			var timeText = rev.timestamp ? (new Date(rev.timestamp * 1000)).toLocaleString() : (rev.formatted_time || 'Revision ' + (idx + 1));
			var rawVal = rev.value;
			var previewSnippet = '';
			if (typeof rawVal === 'string') {
				previewSnippet = rawVal.replace(/<[^>]+>/g, '').substring(0, 90);
				if (rawVal.length > 90) {
					previewSnippet += '…';
				}
			} else if (typeof rawVal === 'object' && rawVal !== null) {
				previewSnippet = rawVal.url ? ('Image ID: ' + (rawVal.attachment_id || '')) : JSON.stringify(rawVal);
			}

			var sourceBadge = rev.source === 'manual_edit' ? 'Manual' : 'AI Generated';
			var $item = $('<div class="aips-revision-item" style="border: 1px solid #dcdcde; background: #fff; border-radius: 4px; padding: 8px 10px; margin-bottom: 8px;"></div>');
			var $header = $('<div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; color:#646970; margin-bottom:4px;"></div>')
				.append('<span><span class="dashicons dashicons-backup" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom;"></span> ' + escapeHtml(sourceBadge) + ' ' + escapeHtml(timeText) + '</span>');

			var $restoreBtn = $('<button type="button" class="button button-small button-secondary aips-restore-revision-btn" style="margin-left:auto;"><span class="dashicons dashicons-undo" style="font-size:13px;width:13px;height:13px;vertical-align:text-bottom;"></span> ' + (l10n.labels.restore || 'Restore') + '</button>');
			$restoreBtn.data('revision-id', rev.id);
			$restoreBtn.data('revision-val', rawVal);

			$header.append($restoreBtn);

			var $body = $('<div style="font-size:12px; color:#1d2327; line-height:1.4; word-break:break-word;"></div>').text(previewSnippet);

			$item.append($header).append($body);
			$list.append($item);
		});
	}

	function initComponentWidgets() {
		var $widgets = $('.aips-classic-component-widget');
		if (!$widgets.length) {
			return;
		}

		$widgets.each(function () {
			var $widget = $(this);
			var component = $widget.data('component');

			// Character counters
			if ('title' === component) {
				var $titleInput = $('#aips-editor-input-title');
				updateCharCount($widget, $titleInput.val());
				$titleInput.on('input', function () {
					updateCharCount($widget, $(this).val());
					$('#title').val($(this).val());
				});
				$('#title').on('input change', function () {
					$titleInput.val($(this).val());
					updateCharCount($widget, $(this).val());
				});
			} else if ('excerpt' === component) {
				var $excerptInput = $('#aips-editor-input-excerpt');
				updateCharCount($widget, $excerptInput.val());
				$excerptInput.on('input', function () {
					updateCharCount($widget, $(this).val());
					$('#excerpt').val($(this).val());
				});
				$('#excerpt').on('input change', function () {
					$excerptInput.val($(this).val());
					updateCharCount($widget, $(this).val());
				});
			}

			// Preload revisions count
			loadRevisions($widget, component);

			// Re-generate single component button
			$widget.on('click', '.aips-regenerate-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var originalHtml = $btn.html();
				var currentVal = getCurrentComponentValue(component);

				$btn.prop('disabled', true).addClass('regenerating').html('<span class="dashicons dashicons-update"></span> ' + (l10n.labels.generating || 'Generating…'));

				$.post(l10n.ajaxurl, {
					action: 'aips_regenerate_component',
					nonce: l10n.nonce,
					post_id: l10n.postId,
					history_id: l10n.historyId || 0,
					component: component,
					current_value: currentVal
				})
				.done(function (response) {
					if (response && response.success && response.data && response.data.new_value) {
						var newVal = response.data.new_value;
						$widget.data('pending-value', newVal);

						// Render Side-by-Side Diff Comparison
						var $diffWrapper = $widget.find('.aips-diff-wrapper');
						var $currentCol = $diffWrapper.find('.aips-diff-current-val');
						var $regenCol = $diffWrapper.find('.aips-diff-regenerated-val');

						if ('featured_image' === component) {
							var currentThumb = $widget.find('.aips-featured-image-preview img').attr('src') || '';
							$currentCol.html(currentThumb ? '<img src="' + escapeHtml(currentThumb) + '" style="max-width:100%;" />' : '<em>No current image</em>');
							var newThumb = typeof newVal === 'object' && newVal.url ? newVal.url : '';
							$regenCol.html(newThumb ? '<img src="' + escapeHtml(newThumb) + '" style="max-width:100%;" />' : '<em>Image generated</em>');
						} else {
							$currentCol.text(typeof currentVal === 'string' ? currentVal : JSON.stringify(currentVal));
							$regenCol.text(typeof newVal === 'string' ? newVal : (newVal.value || JSON.stringify(newVal)));
						}

						$diffWrapper.slideDown(200);
						loadRevisions($widget, component);
					} else {
						var msg = (response && response.data && response.data.message) || l10n.labels.actionError;
						alert(msg);
					}
				})
				.fail(function () {
					alert(l10n.labels.actionError || 'Error regenerating component.');
				})
				.always(function () {
					$btn.prop('disabled', false).removeClass('regenerating').html(originalHtml);
				});
			});

			// Accept & Replace diff
			$widget.on('click', '.aips-accept-diff-btn', function (e) {
				e.preventDefault();
				var newVal = $widget.data('pending-value');
				if (newVal === undefined || newVal === null) {
					return;
				}

				applyComponentValue(component, newVal);

				// Persist via save_post_components to log timeline activity
				var payloadComponents = {};
				if ('title' === component) {
					payloadComponents.title = typeof newVal === 'object' ? newVal.value : newVal;
				} else if ('excerpt' === component) {
					payloadComponents.excerpt = typeof newVal === 'object' ? newVal.value : newVal;
				} else if ('content' === component) {
					payloadComponents.content = typeof newVal === 'object' ? newVal.value : newVal;
				} else if ('featured_image' === component) {
					payloadComponents.featured_image_id = typeof newVal === 'object' ? newVal.attachment_id : newVal;
				}

				$.post(l10n.ajaxurl, {
					action: 'aips_save_post_components',
					nonce: l10n.nonce,
					post_id: l10n.postId,
					components: payloadComponents
				});

				$widget.find('.aips-diff-wrapper').slideUp(150);
				$widget.removeData('pending-value');
				loadRevisions($widget, component);
			});

			// Discard diff
			$widget.on('click', '.aips-discard-diff-btn', function (e) {
				e.preventDefault();
				$widget.removeData('pending-value');
				$widget.find('.aips-diff-wrapper').slideUp(150);
			});

			// Toggle Revisions drawer
			$widget.on('click', '.aips-view-revisions-btn', function (e) {
				e.preventDefault();
				var $drawer = $widget.find('.aips-component-revisions');
				$drawer.slideToggle(200);
				if ($drawer.is(':visible')) {
					loadRevisions($widget, component);
				}
			});

			// Restore specific revision
			$widget.on('click', '.aips-restore-revision-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var revId = $btn.data('revision-id');
				var revVal = $btn.data('revision-val');

				$btn.prop('disabled', true);

				$.post(l10n.ajaxurl, {
					action: 'aips_restore_component_revision',
					nonce: l10n.nonce,
					post_id: l10n.postId,
					component: component,
					revision_id: revId
				})
				.done(function (response) {
					if (response && response.success) {
						var restoredVal = response.data.value !== undefined ? response.data.value : revVal;
						applyComponentValue(component, restoredVal);
						loadRevisions($widget, component);
					} else {
						alert((response && response.data && response.data.message) || l10n.labels.actionError);
					}
				})
				.fail(function () {
					alert(l10n.labels.actionError);
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
			});

			// 3 Candidates Button
			$widget.on('click', '.aips-gen-variants-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var originalHtml = $btn.html();
				var $container = $widget.find('.aips-classic-variants-container');
				var $carousel = $container.find('.aips-variant-carousel');

				$btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + (l10n.labels.generating || 'Generating…'));

				$.post(l10n.ajaxurl, {
					action: 'aips_editor_generate_component_variants',
					nonce: l10n.nonce,
					post_id: l10n.postId,
					component: component
				})
				.done(function (response) {
					if (response && response.success && response.data && response.data.variants) {
						$carousel.empty();
						$container.show();
						$.each(response.data.variants, function (idx, text) {
							var $card = $('<div class="aips-variant-card" style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:8px 10px;margin-bottom:6px;"></div>');
							var $cardHeader = $('<div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#646970;margin-bottom:4px;"><span>Candidate ' + (idx + 1) + '</span></div>');
							var $cardBody = $('<div style="font-size:12px;color:#1d2327;line-height:1.4;margin-bottom:6px;"></div>').text(text);
							var $applyBtn = $('<button type="button" class="button button-small button-primary">' + (l10n.labels.insert || 'Apply') + '</button>');

							$applyBtn.on('click', function () {
								applyComponentValue(component, text);
								$card.css('background-color', '#edfaef');
							});

							$card.append($cardHeader).append($cardBody).append($applyBtn);
							$carousel.append($card);
						});
					} else {
						alert((response && response.data && response.data.message) || l10n.labels.actionError);
					}
				})
				.fail(function () {
					alert(l10n.labels.actionError);
				})
				.always(function () {
					$btn.prop('disabled', false).html(originalHtml);
				});
			});
		});
	}

	function initLegacyMetaBox() {
		var $metaBox = $('#aips_classic_ai_edit_metabox');
		if (!$metaBox.length) {
			return;
		}

		var $variantsContainer = $metaBox.find('.aips-classic-variants-container');
		var $variantsList = $metaBox.find('.aips-classic-variants-list');

		$metaBox.on('click', '.aips-gen-titles-btn, .aips-gen-excerpts-btn', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var component = $btn.data('component');
			var originalHtml = $btn.html();

			$btn.prop('disabled', true).text(l10n.labels.generating || 'Generating…');

			$.post(l10n.ajaxurl, {
				action: 'aips_editor_generate_component_variants',
				nonce: l10n.nonce,
				post_id: l10n.postId,
				component: component
			})
			.done(function (response) {
				if (response && response.success && response.data && response.data.variants) {
					$variantsList.empty();
					$variantsContainer.show();
					$.each(response.data.variants, function (idx, text) {
						var $card = $('<div class="aips-variant-card"></div>');
						var $header = $('<div class="aips-variant-card-header"><span>Variant ' + (idx + 1) + '</span></div>');
						var $body = $('<div class="aips-variant-card-body"></div>').text(text);
						var $applyBtn = $('<button type="button" class="button button-small button-primary"></button>')
							.text(l10n.labels.insert || 'Insert')
							.on('click', function () {
								applyComponentValue(component, text);
								$card.css('background-color', 'var(--aips-success-bg)');
							});

						$card.append($header).append($body).append($applyBtn);
						$variantsList.append($card);
					});
				} else {
					alert((response && response.data && response.data.message) || l10n.labels.actionError);
				}
			})
			.fail(function () {
				alert(l10n.labels.actionError);
			})
			.always(function () {
				$btn.prop('disabled', false).html(originalHtml);
			});
		});
	}

	$(document).ready(function () {
		initComponentWidgets();
		initLegacyMetaBox();
	});
})(jQuery);
