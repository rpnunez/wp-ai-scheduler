/**
 * AI Post Scheduler - Classic Editor Meta Box Logic
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */
(function ($) {
	'use strict';

	var l10n = window.aipsEditorL10n || {};

	function initMetaBox() {
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
					renderVariants(component, response.data.variants);
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

		function renderVariants(component, variants) {
			$variantsList.empty();
			$variantsContainer.show();

			$.each(variants, function (idx, text) {
				var $card = $('<div class="aips-variant-card"></div>');
				var $header = $('<div class="aips-variant-card-header"></div>')
					.append('<span>Variant ' + (idx + 1) + '</span>');
				var $body = $('<div class="aips-variant-card-body"></div>').text(text);
				var $applyBtn = $('<button type="button" class="button button-small button-primary"></button>')
					.text(l10n.labels.insert || 'Insert')
					.on('click', function () {
						if ('title' === component) {
							$('#title').val(text).trigger('change');
						} else if ('slug' === component) {
							$('#post_name').val(text).trigger('change');
							$('#editable-post-name, #editable-post-name-full').text(text);
							if ($('#new-post-slug').length) {
								$('#new-post-slug').val(text);
							}
						} else if ('excerpt' === component) {
							$('#excerpt').val(text).trigger('change');
						}
						$card.css('background-color', 'var(--aips-success-bg)');
					});

				$card.append($header).append($body).append($applyBtn);
				$variantsList.append($card);
			});
		}
	}

	$(document).ready(initMetaBox);
})(jQuery);
