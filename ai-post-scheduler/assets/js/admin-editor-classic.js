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

	// Register default templates in AIPS.Templates if not already defined in the DOM.
	if (window.AIPS && window.AIPS.Templates) {
		if (!window.AIPS.Templates.has('aips-tmpl-editor-revision-item')) {
			window.AIPS.Templates.register(
				'aips-tmpl-editor-revision-item',
				'<div class="aips-revision-item" data-revision-id="{{id}}">' +
					'<div class="aips-revision-header">' +
						'<span><span class="dashicons dashicons-backup"></span> {{sourceBadge}} {{timeText}}</span>' +
						'<button type="button" class="button button-small button-secondary aips-restore-revision-btn" data-revision-id="{{id}}">' +
							'<span class="dashicons dashicons-undo"></span> {{restoreLabel}}' +
						'</button>' +
					'</div>' +
					'<div class="aips-revision-body">{{snippet}}</div>' +
				'</div>'
			);
		}
		if (!window.AIPS.Templates.has('aips-tmpl-editor-variant-card')) {
			window.AIPS.Templates.register(
				'aips-tmpl-editor-variant-card',
				'<div class="aips-variant-card">' +
					'<div class="aips-variant-card-header"><span>Candidate {{candidateIndex}}</span></div>' +
					'<div class="aips-variant-card-body">{{text}}</div>' +
					'<button type="button" class="button button-small button-primary aips-apply-variant-btn">{{insertLabel}}</button>' +
				'</div>'
			);
		}
	}

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
		if ('slug' === component) {
			var $postName = $('#post_name');
			if ($postName.length) {
				return $postName.val() || '';
			}
			var $slugInput = $('#aips-editor-input-slug');
			return $slugInput.length ? ($slugInput.val() || '') : '';
		}
		if ('content' === component) {
			if (window.tinymce && window.tinymce.get('content') && !window.tinymce.get('content').isHidden()) {
				return window.tinymce.get('content').getContent();
			}
			return $('#content').val() || '';
		}
		if ('featured_image' === component) {
			var thumbId = parseInt($('#_thumbnail_id').val(), 10) || 0;
			if (thumbId > 0) {
				var previewSrc = $('.aips-classic-component-widget[data-component="featured_image"] .aips-featured-image-preview img').attr('src') || '';
				return {
					attachment_id: thumbId,
					url: previewSrc
				};
			}
			return '';
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
		} else if ('slug' === component) {
			var slugVal = typeof val === 'object' && val !== null ? (val.value || '') : String(val || '');
			$('#post_name').val(slugVal).trigger('change');
			$('#aips-editor-input-slug').val(slugVal).trigger('input');
			$('#editable-post-name, #editable-post-name-full').text(slugVal);
			if ($('#new-post-slug').length) {
				$('#new-post-slug').val(slugVal);
			}
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
					$preview.html('<img src="' + escapeHtml(imgUrl) + '" alt="" />');
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
			var html = '';
			if (window.AIPS && window.AIPS.Templates) {
				html = window.AIPS.Templates.render('aips-tmpl-editor-revision-item', {
					id: rev.id,
					sourceBadge: sourceBadge,
					timeText: timeText,
					restoreLabel: l10n.labels.restore || 'Restore',
					snippet: previewSnippet
				});
			}

			var $item = $(html);
			$item.find('.aips-restore-revision-btn').data('revision-id', rev.id).data('revision-val', rawVal);
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

			// Popover toggle for quick prompt steering
			$widget.on('click', '.aips-split-toggle-btn', function (e) {
				e.preventDefault();
				var $popover = $widget.find('.aips-directive-popover');
				var isExpanded = $(this).attr('aria-expanded') === 'true';
				$('.aips-directive-popover').not($popover).slideUp(150);
				$('.aips-split-toggle-btn').not(this).attr('aria-expanded', 'false');
				$popover.slideToggle(150);
				$(this).attr('aria-expanded', !isExpanded);
			});

			$widget.on('click', '.aips-popover-close-btn', function (e) {
				e.preventDefault();
				$widget.find('.aips-directive-popover').slideUp(150);
				$widget.find('.aips-split-toggle-btn').attr('aria-expanded', 'false');
			});

			// Preset chip selection
			$widget.on('click', '.aips-preset-chip', function (e) {
				e.preventDefault();
				var $chip = $(this);
				if ($chip.hasClass('active')) {
					$chip.removeClass('active').removeClass('button-primary');
				} else {
					$widget.find('.aips-preset-chip').removeClass('active').removeClass('button-primary');
					$chip.addClass('active').addClass('button-primary');
				}
			});

			$widget.on('click', '.aips-clear-directive-btn', function (e) {
				e.preventDefault();
				$widget.find('.aips-preset-chip').removeClass('active').removeClass('button-primary');
				$widget.find('.aips-custom-directive-input').val('');
			});

			$widget.on('click', '.aips-apply-regenerate-btn', function (e) {
				e.preventDefault();
				$widget.find('.aips-directive-popover').slideUp(150);
				$widget.find('.aips-split-toggle-btn').attr('aria-expanded', 'false');
				$widget.find('.aips-regenerate-btn').trigger('click');
			});

			// Advanced Directives & Model Accordion
			$widget.on('click', '.aips-advanced-accordion-toggle', function (e) {
				e.preventDefault();
				var $drawer = $widget.find('.aips-advanced-drawer');
				var isExpanded = $(this).attr('aria-expanded') === 'true';
				$drawer.slideToggle(150);
				$(this).attr('aria-expanded', !isExpanded);
				$(this).find('.aips-accordion-arrow').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
			});

			$widget.on('input change', '.aips-temperature-slider', function () {
				var val = parseFloat($(this).val()) || 0.7;
				$widget.find('.aips-temp-val-badge').text(val.toFixed(2));
				$widget.find('.aips-active-override-badge').show();
			});

			$widget.on('change', '.aips-model-select', function () {
				$widget.find('.aips-active-override-badge').show();
			});

			// Reset component overrides to template default
			$widget.on('click', '.aips-reset-overrides-btn', function (e) {
				e.preventDefault();
				var $resetBtn = $(this);
				$resetBtn.prop('disabled', true);
				$.post(l10n.ajaxurl, {
					action: 'aips_reset_component_overrides',
					nonce: l10n.nonce,
					post_id: l10n.postId,
					component: component
				}).done(function (res) {
					if (res && res.success && res.data) {
						$widget.find('.aips-model-select').val('');
						var defTemp = parseFloat(res.data.default_temperature) || 0.7;
						$widget.find('.aips-temperature-slider').val(defTemp);
						$widget.find('.aips-temp-val-badge').text(defTemp.toFixed(2));
						$widget.find('.aips-active-override-badge').hide();
					}
				}).always(function () {
					$resetBtn.prop('disabled', false);
				});
			});

			// Re-generate single component button
			$widget.on('click', '.aips-regenerate-btn', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var originalHtml = $btn.html();
				var currentVal = getCurrentComponentValue(component);
				var isDownstreamCascaded = !!$widget.data('downstream-cascaded');
				var upstreamContext = $widget.data('upstream-context') || {};
				$widget.removeData('downstream-cascaded');
				$widget.removeData('upstream-context');

				var activePreset = $widget.find('.aips-preset-chip.active').data('preset') || '';
				var customDirective = $widget.find('.aips-custom-directive-input').val() || '';
				var modelOverride = $widget.find('.aips-model-select').val() || '';
				var tempOverride = $widget.find('.aips-temperature-slider').val() || '';

				$btn.prop('disabled', true).addClass('regenerating').html('<span class="dashicons dashicons-update"></span> ' + (l10n.labels.generating || 'Generating…'));

				var regeneratePayload = {
					action: 'aips_regenerate_component',
					nonce: l10n.nonce,
					post_id: l10n.postId,
					history_id: l10n.historyId || 0,
					component: component,
					current_value: currentVal,
					custom_directive: customDirective,
					directive_preset: activePreset,
					model_override: modelOverride,
					temperature_override: tempOverride,
					downstream_cascaded: isDownstreamCascaded ? 'true' : 'false'
				};
				if (isDownstreamCascaded && upstreamContext.title) {
					regeneratePayload.upstream_title = upstreamContext.title;
				}
				if (isDownstreamCascaded && upstreamContext.content) {
					regeneratePayload.upstream_content = upstreamContext.content;
				}

				$.post(l10n.ajaxurl, regeneratePayload)
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
							$currentCol.html(currentThumb ? '<img src="' + escapeHtml(currentThumb) + '" />' : '<em>No current image</em>');
							var newThumb = typeof newVal === 'object' && newVal.url ? newVal.url : '';
							$regenCol.html(newThumb ? '<img src="' + escapeHtml(newThumb) + '" />' : '<em>Image generated</em>');
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

				// Check if user requested cascading downstream alignment
				var $downstreamCb = $widget.find('.aips-sync-downstream-cb');
				var shouldSyncDownstream = $downstreamCb.length && $downstreamCb.is(':checked');
				var downstreamTargets = shouldSyncDownstream ? ($downstreamCb.data('downstream') || '').split(',') : [];

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

				var upstreamContext = {};
				if ('title' === component) {
					upstreamContext.title = payloadComponents.title;
				} else if ('content' === component) {
					upstreamContext.content = payloadComponents.content;
				}

				var saveRequest = $.post(l10n.ajaxurl, {
					action: 'aips_save_post_components',
					nonce: l10n.nonce,
					post_id: l10n.postId,
					components: payloadComponents
				});

				$widget.find('.aips-diff-wrapper').slideUp(150);
				$widget.removeData('pending-value');
				loadRevisions($widget, component);

				// Wait until the accepted draft is persisted. Excerpt regeneration
				// reads title and content from that saved post (and from the
				// upstream fields below) to stay aligned with the accepted version.
				if (downstreamTargets.length > 0) {
					saveRequest.done(function (response) {
						if (!response || !response.success) {
							alert((response && response.data && response.data.message) || l10n.labels.actionError || 'Error saving component.');
							return;
						}
						$.each(downstreamTargets, function (i, targetComp) {
							targetComp = $.trim(targetComp);
							if (!targetComp) return;
							var $targetWidget = $('.aips-classic-component-widget[data-component="' + targetComp + '"]');
							if ($targetWidget.length) {
								$targetWidget.data('downstream-cascaded', true);
								$targetWidget.data('upstream-context', upstreamContext);
								$targetWidget.find('.aips-regenerate-btn').trigger('click');
							}
						});
					}).fail(function () {
						alert(l10n.labels.actionError || 'Error saving component.');
					});
				}
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
							var html = '';
							if (window.AIPS && window.AIPS.Templates) {
								html = window.AIPS.Templates.render('aips-tmpl-editor-variant-card', {
									candidateIndex: idx + 1,
									text: text,
									insertLabel: l10n.labels.insert || 'Apply'
								});
							}
							var $card = $(html);
							$card.find('.aips-apply-variant-btn').on('click', function () {
								applyComponentValue(component, text);
								$card.css('background-color', 'var(--aips-success-bg)');
							});
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

		$metaBox.on('click', '.aips-gen-titles-btn, .aips-gen-slugs-btn, .aips-gen-excerpts-btn', function (e) {
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
						var html = '';
						if (window.AIPS && window.AIPS.Templates) {
							html = window.AIPS.Templates.render('aips-tmpl-editor-variant-card', {
								candidateIndex: idx + 1,
								text: text,
								insertLabel: l10n.labels.insert || 'Insert'
							});
						}
						var $card = $(html);
						$card.find('.aips-apply-variant-btn').on('click', function () {
							applyComponentValue(component, text);
							$card.css('background-color', 'var(--aips-success-bg)');
						});
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
