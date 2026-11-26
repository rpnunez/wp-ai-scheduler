/**
 * TinyMCE Plugin for AI Edit Directives & Internal Linking
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */
(function (tinymce, $) {
	'use strict';

	if (!tinymce) {
		return;
	}

	var l10n = window.aipsEditorL10n || {};

	tinymce.PluginManager.add('aips_editor_tinymce', function (editor) {
		var $popover = null;

		function closePopover() {
			if ($popover) {
				$popover.remove();
				$popover = null;
			}
		}

		function showPopover(selectedText) {
			closePopover();

			var $wrap = $('<div class="aips-editor-popover"></div>');
			var $header = $('<div class="aips-editor-popover-header"></div>')
				.append('<h4 class="aips-editor-popover-title"><span class="dashicons dashicons-update"></span> ' + (l10n.labels.popoverTitle || 'AI Edit') + '</h4>')
				.append($('<button type="button" class="dashicons dashicons-no-alt" style="border:none;background:none;cursor:pointer;"></button>').on('click', closePopover));

			var $pills = $('<div class="aips-preset-pills"></div>');
			var presets = l10n.presets || {};

			$.each(presets, function (key, preset) {
				var $pill = $('<button type="button" class="aips-preset-pill"></button>')
					.text(preset.label)
					.on('click', function () {
						applyAction(selectedText, key, '');
					});
				$pills.append($pill);
			});

			var $weavePill = $('<button type="button" class="aips-preset-pill"></button>')
				.html('<span class="dashicons dashicons-admin-links" style="font-size:12px;width:12px;height:12px;"></span> ' + (l10n.labels.weaveBtn || 'Weave Links'))
				.on('click', function () {
					weaveLinks(selectedText);
				});
			$pills.append($weavePill);

			var $inputRow = $('<div class="aips-editor-input-row"></div>');
			var $input = $('<input type="text" class="aips-editor-input" placeholder="' + (l10n.labels.instruction || 'Custom instruction...') + '"/>');
			var $submitBtn = $('<button type="button" class="button button-small button-primary"><span class="dashicons dashicons-arrow-right-alt2" style="margin-top:2px;"></span></button>')
				.on('click', function () {
					var val = $.trim($input.val());
					if (val) {
						applyAction(selectedText, '', val);
					}
				});

			$input.on('keypress', function (e) {
				if (e.which === 13) {
					$submitBtn.trigger('click');
				}
			});

			$inputRow.append($input).append($submitBtn);

			var $statusArea = $('<div class="aips-editor-status-area" style="display:none;margin-top:8px;"></div>');

			$wrap.append($header).append($pills).append($inputRow).append($statusArea);

			// Position popover relative to editor container
			var $container = $(editor.getContainer());
			$wrap.css({
				position: 'absolute',
				top: '50px',
				left: '20px',
				zIndex: 999999
			});

			$container.css('position', 'relative').append($wrap);
			$popover = $wrap;
		}

		function applyAction(text, preset, custom) {
			if (!$popover) {
				return;
			}
			var $status = $popover.find('.aips-editor-status-area').empty().show();
			$status.html('<em>' + (l10n.labels.generating || 'Generating…') + '</em>');

			$.post(l10n.ajaxurl, {
				action: 'aips_editor_apply_directive',
				nonce: l10n.nonce,
				post_id: l10n.postId,
				content: text,
				directive_preset: preset,
				custom_directive: custom,
				variants_count: 1
			})
			.done(function (response) {
				if (response && response.success && response.data && response.data.variants && response.data.variants.length) {
					var replacement = response.data.variants[0];
					$status.empty();
					var $preview = $('<div style="padding:6px;background:var(--aips-gray-50);border-radius:4px;margin-bottom:6px;max-height:100px;overflow-y:auto;"></div>').text(replacement);
					var $insertBtn = $('<button type="button" class="button button-primary button-small">' + (l10n.labels.replace || 'Replace') + '</button>')
						.on('click', function () {
							editor.selection.setContent(replacement);
							closePopover();
						});
					$status.append($preview).append($insertBtn);
				} else {
					$status.html('<span style="color:var(--aips-error);">' + ((response && response.data && response.data.message) || l10n.labels.actionError) + '</span>');
				}
			})
			.fail(function () {
				$status.html('<span style="color:var(--aips-error);">' + l10n.labels.actionError + '</span>');
			});
		}

		function weaveLinks(text) {
			if (!$popover) {
				return;
			}
			var $status = $popover.find('.aips-editor-status-area').empty().show();
			$status.html('<em>' + (l10n.labels.weaving || 'Weaving internal links…') + '</em>');

			$.post(l10n.ajaxurl, {
				action: 'aips_editor_weave_internal_links',
				nonce: l10n.nonce,
				post_id: l10n.postId,
				content: text
			})
			.done(function (response) {
				if (response && response.success && response.data && response.data.modified_html) {
					var newHtml = response.data.modified_html;
					$status.empty();

					var $preview = $('<div style="padding:6px;background:var(--aips-success-bg);border-radius:4px;margin-bottom:6px;max-height:100px;overflow-y:auto;"></div>').html(newHtml);
					var $insertBtn = $('<button type="button" class="button button-primary button-small">' + (l10n.labels.replace || 'Replace') + '</button>')
						.on('click', function () {
							editor.selection.setContent(newHtml);
							closePopover();
						});
					$status.append($preview).append($insertBtn);
				} else {
					$status.html('<span style="color:var(--aips-error);">' + ((response && response.data && response.data.message) || l10n.labels.actionError) + '</span>');
				}
			})
			.fail(function () {
				$status.html('<span style="color:var(--aips-error);">' + l10n.labels.actionError + '</span>');
			});
		}

		editor.addButton('aips_editor_tinymce_btn', {
			title: l10n.labels.popoverTitle || 'AI Edit & Directives',
			icon: 'dashicons dashicons-update',
			onclick: function () {
				var content = editor.selection.getContent({ format: 'text' });
				if (!$.trim(content)) {
					alert('Please highlight some text in the editor first.');
					return;
				}
				showPopover(content);
			}
		});
	});
})(window.tinymce, window.jQuery);
