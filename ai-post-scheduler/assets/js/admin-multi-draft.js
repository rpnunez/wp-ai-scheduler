/**
 * Multi-Draft Compare UI
 *
 * Handles the "Generate Variants" workflow: opens a modal, calls the backend
 * to generate N independent draft previews, renders them side-by-side so the
 * editor can select the best title / excerpt / content, then applies the
 * merged selection back to the existing draft post.
 *
 * @package AI_Post_Scheduler
 * @since   2.1.0
 */

(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};

	/** Mutable state for the currently-open compare session. */
	var multiDraftState = {
		postId:     null,
		historyId:  null,
		variants:   [],
		selections: {}
	};

	Object.assign(window.AIPS, {

		/**
		 * Bootstrap multi-draft functionality.
		 */
		initMultiDraft: function() {
			this.bindMultiDraftEvents();
		},

		/**
		 * Bind all multi-draft event handlers using delegation.
		 */
		bindMultiDraftEvents: function() {
			$(document).on('click',  '.aips-generate-variants-btn',                   AIPS.openMultiDraftModal);
			$(document).on('click',  '#aips-multi-draft-close, #aips-multi-draft-cancel', AIPS.closeMultiDraftModal);
			$(document).on('click',  '.aips-modal-overlay',                           AIPS.closeMultiDraftModal);
			$(document).on('click',  '#aips-multi-draft-generate',                    AIPS.generateVariants);
			$(document).on('click',  '#aips-multi-draft-apply',                       AIPS.applyMergedDraft);
			$(document).on('change', '.aips-variant-select-radio',                    AIPS.onVariantRadioChange);
			$(document).on('input change', '#aips-multi-draft-count',                 AIPS.updateMultiDraftCostEstimate);
		},

		/**
		 * Open the multi-draft modal for a specific post.
		 *
		 * @param {Event} e Click event from `.aips-generate-variants-btn`.
		 */
		openMultiDraftModal: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);

			multiDraftState.postId    = $btn.data('post-id');
			multiDraftState.historyId = $btn.data('history-id');
			multiDraftState.variants  = [];
			multiDraftState.selections = {};

			var maxVariants = parseInt(aipsMultiDraftL10n.maxVariants, 10) || 3;
			$('#aips-multi-draft-count')
				.attr('max', maxVariants)
				.val(maxVariants);

			AIPS.updateMultiDraftCostEstimate();
			AIPS.showMultiDraftStep('config');
			$('#aips-multi-draft-modal').show();
			$('body').addClass('aips-modal-open');
		},

		/**
		 * Close the multi-draft modal.
		 */
		closeMultiDraftModal: function() {
			$('#aips-multi-draft-modal').hide();
			$('body').removeClass('aips-modal-open');
		},

		/**
		 * Transition the modal between its three steps: config / generating / compare.
		 *
		 * @param {string} step One of 'config', 'generating', 'compare'.
		 */
		showMultiDraftStep: function(step) {
			$('#aips-multi-draft-step-config, #aips-multi-draft-step-generating, #aips-multi-draft-step-compare').hide();
			$('#aips-multi-draft-step-' + step).show();

			$('#aips-multi-draft-generate').toggle(step === 'config');
			$('#aips-multi-draft-apply').toggle(step === 'compare');
		},

		/**
		 * Format the cost estimate message using the localized template.
		 *
		 * Supports both numbered placeholders like `%1$d` / `%2$d` and the
		 * legacy `%d_variants` / `%d_calls` tokens for backwards compatibility.
		 *
		 * @param {number} variants Number of variants to generate.
		 * @param {number} calls    Estimated API call count.
		 * @returns {string} Formatted cost estimate message.
		 */
		formatMultiDraftCostEstimate: function(variants, calls) {
			var template = (typeof aipsMultiDraftL10n !== 'undefined' && aipsMultiDraftL10n && aipsMultiDraftL10n.costEstimate)
				? aipsMultiDraftL10n.costEstimate
				: '';

			if (!template) {
				return '';
			}

			// Prefer numbered placeholders if present, e.g. "%1$d" and "%2$d".
			if (/%[12]\$d/.test(template)) {
				return template
					.replace(/%1\$d/g, variants)
					.replace(/%2\$d/g, calls);
			}

			// Fallback for legacy tokens used in existing translations.
			return template
				.replace('%d_variants', variants)
				.replace('%d_calls',    calls);
		},

		/**
		 * Recalculate and display the expected API-call impact before generating.
		 */
		updateMultiDraftCostEstimate: function() {
			var count = parseInt($('#aips-multi-draft-count').val(), 10) || 2;
			var calls = count * 3; // content + title + excerpt per variant
			var message = AIPS.formatMultiDraftCostEstimate(count, calls);
			$('#aips-multi-draft-cost-estimate').text(message);
		},

		/**
		 * Send the AJAX request to generate variant previews.
		 *
		 * @param {Event} e Click event from `#aips-multi-draft-generate`.
		 */
		generateVariants: function(e) {
			e.preventDefault();
			var variantCount = parseInt($('#aips-multi-draft-count').val(), 10) || 2;

			AIPS.showMultiDraftStep('generating');

			$.ajax({
				url:     aipsMultiDraftL10n.ajaxUrl,
				type:    'POST',
				timeout: 300000, // 5-minute timeout — generation is slow
				data: {
					action:        'aips_generate_variants',
					nonce:         aipsMultiDraftL10n.nonce,
					post_id:       multiDraftState.postId,
					history_id:    multiDraftState.historyId,
					variant_count: variantCount
				},
				success: function(response) {
					if (response.success) {
						multiDraftState.variants = response.data.variants;
						AIPS.renderMultiDraftComparison(response.data.variants);
						AIPS.showMultiDraftStep('compare');
					} else {
						AIPS.showMultiDraftStep('config');
						AIPS.Utilities.showToast(
							(response.data && response.data.message) || aipsMultiDraftL10n.generateError,
							'error'
						);
					}
				},
				error: function() {
					AIPS.showMultiDraftStep('config');
					AIPS.Utilities.showToast(aipsMultiDraftL10n.generateError, 'error');
				}
			});
		},

		/**
		 * Render the side-by-side comparison grid inside the modal.
		 *
		 * Produces a separate row for title, excerpt, and content. Each row
		 * contains one column per variant. A radio button on each column header
		 * lets the editor select which variant's value to keep.
		 *
		 * @param {Array} variants Array of variant objects: {index, title, excerpt, content}.
		 */
		renderMultiDraftComparison: function(variants) {
			var sections = ['title', 'excerpt', 'content'];
			var labels   = {
				title:   aipsMultiDraftL10n.labelTitle,
				excerpt: aipsMultiDraftL10n.labelExcerpt,
				content: aipsMultiDraftL10n.labelContent
			};

			var html = '';

			sections.forEach(function(section) {
				html += '<div class="aips-mdc-section">';
				html += '<h4 class="aips-mdc-section-label">' + AIPS.Templates.escape(labels[section]) + '</h4>';
				html += '<div class="aips-mdc-columns aips-mdc-cols-' + variants.length + '">';

				variants.forEach(function(variant, idx) {
					var value      = variant[section] || '';
					var isSelected = idx === 0; // default: first variant selected
					var varLabel   = aipsMultiDraftL10n.variantLabel.replace('%d', variant.index);

					html += '<div class="aips-mdc-column' + (isSelected ? ' is-selected' : '') + '">';
					html += '<label class="aips-mdc-column-header">';
					html += '<input type="radio" class="aips-variant-select-radio"'
						+ ' name="aips_variant_' + section + '"'
						+ ' value="' + variant.index + '"'
						+ ' data-section="' + AIPS.Templates.escape(section) + '"'
						+ (isSelected ? ' checked' : '') + '>';
					html += ' ' + AIPS.Templates.escape(varLabel);
					html += '</label>';

					if (section === 'content') {
						html += '<div class="aips-mdc-content-preview">' + AIPS.Templates.escape(value) + '</div>';
					} else {
						html += '<div class="aips-mdc-field-value">' + AIPS.Templates.escape(value) + '</div>';
					}

					html += '</div>'; // .aips-mdc-column
				});

				html += '</div>'; // .aips-mdc-columns
				html += '</div>'; // .aips-mdc-section

				// Default selection to first variant.
				multiDraftState.selections[section] = 1;
			});

			$('#aips-multi-draft-compare-body').html(html);
		},

		/**
		 * Update internal selections map when the editor picks a variant radio.
		 *
		 * @param {Event} e Change event from `.aips-variant-select-radio`.
		 */
		onVariantRadioChange: function(e) {
			var $radio  = $(e.currentTarget);
			var section = $radio.data('section');
			var index   = parseInt($radio.val(), 10);

			multiDraftState.selections[section] = index;

			// Visual feedback: highlight the chosen column.
			$radio.closest('.aips-mdc-section')
				.find('.aips-mdc-column').removeClass('is-selected');
			$radio.closest('.aips-mdc-column').addClass('is-selected');
		},

		/**
		 * Collect the editor's per-section selections and apply them to the post.
		 *
		 * @param {Event} e Click event from `#aips-multi-draft-apply`.
		 */
		applyMergedDraft: function(e) {
			e.preventDefault();
			var $btn = $('#aips-multi-draft-apply');
			$btn.prop('disabled', true).text(aipsMultiDraftL10n.applying);

			// Build component map from current selections.
			var components = {};
			['title', 'excerpt', 'content'].forEach(function(section) {
				var variantIndex = multiDraftState.selections[section] || 1;
				var found = null;
				$.each(multiDraftState.variants, function(i, v) {
					if (v.index === variantIndex) {
						found = v;
						return false;
					}
				});
				if (found) {
					components[section] = found[section];
				}
			});

			$.ajax({
				url:  aipsMultiDraftL10n.ajaxUrl,
				type: 'POST',
				data: {
					action:     'aips_apply_merged_draft',
					nonce:      aipsMultiDraftL10n.nonce,
					post_id:    multiDraftState.postId,
					components: components
				},
				success: function(response) {
					if (response.success) {
						AIPS.Utilities.showToast(aipsMultiDraftL10n.applySuccess, 'success');
						AIPS.closeMultiDraftModal();

						// Refresh the post list if the page provides a loader.
						if (typeof AIPS.loadDraftPosts === 'function') {
							AIPS.loadDraftPosts();
						} else {
							location.reload();
						}
					} else {
						AIPS.Utilities.showToast(
							(response.data && response.data.message) || aipsMultiDraftL10n.applyError,
							'error'
						);
						$btn.prop('disabled', false).text(aipsMultiDraftL10n.applyDraft);
					}
				},
				error: function() {
					AIPS.Utilities.showToast(aipsMultiDraftL10n.applyError, 'error');
					$btn.prop('disabled', false).text(aipsMultiDraftL10n.applyDraft);
				}
			});
		}
	});

	$(document).ready(function() {
		AIPS.initMultiDraft();
	});

})(jQuery);
