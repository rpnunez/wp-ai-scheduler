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
	var AIPS = window.AIPS;

	/** Mutable state for the currently-open compare session. */
	var multiDraftState = {
		postId:          null,
		historyId:       null,
		currentComponents: {},
		variants:        [],
		selections:      {},
		tokensPerVariant: null
	};

	Object.assign(AIPS, {

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
			$(document).on('input change', '#aips-multi-draft-count',                 AIPS.onMultiDraftCountChange);
		},

		/**
		 * Open the multi-draft modal for a specific post.
		 *
		 * @param {Event} e Click event from `.aips-generate-variants-btn`.
		 */
		openMultiDraftModal: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);

			multiDraftState.postId           = $btn.data('post-id');
			multiDraftState.historyId        = $btn.data('history-id');
			multiDraftState.currentComponents = {};
			multiDraftState.variants         = [];
			multiDraftState.selections       = {};
			multiDraftState.tokensPerVariant  = null;

			var maxVariants = parseInt(aipsMultiDraftL10n.maxVariants, 10) || 3;
			$('#aips-multi-draft-count')
				.attr('max', maxVariants)
				.val(maxVariants);

			// Show "Estimating cost…" while we fetch the real token count.
			$('#aips-multi-draft-cost-estimate').text(aipsMultiDraftL10n.estimatingCost || '');
			AIPS.showMultiDraftStep('config');
			$('#aips-multi-draft-modal').show();
			$('body').addClass('aips-modal-open');

			// Fetch real token estimate from the server (non-blocking).
			AIPS.fetchMultiDraftTokenEstimate(multiDraftState.postId, maxVariants);
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
		 * Request a real token-count estimate from the server via `mwai_estimate_tokens`.
		 *
		 * Fires the `aips_estimate_variant_tokens` AJAX endpoint and, on success,
		 * stores the per-variant token count in state and refreshes the cost notice.
		 * Silently falls back to the formula-based estimate on failure.
		 *
		 * @param {number} postId        The post to estimate tokens for.
		 * @param {number} variantCount  The currently selected variant count.
		 */
		fetchMultiDraftTokenEstimate: function(postId, variantCount) {
			$.ajax({
				url:  aipsMultiDraftL10n.ajaxUrl,
				type: 'POST',
				data: {
					action:        'aips_estimate_variant_tokens',
					nonce:         aipsMultiDraftL10n.nonce,
					post_id:       postId,
					variant_count: variantCount
				},
				success: function(response) {
					if (response.success && response.data && response.data.tokens_per_variant) {
						multiDraftState.tokensPerVariant = response.data.tokens_per_variant
						? parseInt(response.data.tokens_per_variant, 10)
						: null;
					}
					AIPS.updateMultiDraftCostEstimate();
				},
				error: function() {
					// Silently fall back to formula-based estimate.
					AIPS.updateMultiDraftCostEstimate();
				}
			});
		},

		/**
		 * Format the cost estimate message using the localized template.
		 *
		 * Uses the token-enriched string when a token estimate is available,
		 * falling back to the basic API-call count string otherwise.
		 *
		 * @param {number}      variants Number of variants to generate.
		 * @param {number}      calls    Estimated total API call count.
		 * @param {number|null} tokens   Estimated total token count (null = unknown).
		 * @returns {string} Formatted cost estimate message.
		 */
		formatMultiDraftCostEstimate: function(variants, calls, tokens) {
			var l10n = (typeof aipsMultiDraftL10n !== 'undefined') ? aipsMultiDraftL10n : {};

			if (tokens !== null && tokens !== undefined && l10n.costEstimateTokens) {
				var formattedTokens = tokens.toLocaleString();
				return l10n.costEstimateTokens
					.replace(/%1\$d/g, variants)
					.replace(/%2\$d/g, calls)
					.replace(/%3\$s/g, formattedTokens);
			}

			var template = l10n.costEstimate || '';
			if (!template) {
				return '';
			}

			return template
				.replace(/%1\$d/g, variants)
				.replace(/%2\$d/g, calls);
		},

		/**
		 * Recalculate and display the expected API-call / token impact before generating.
		 */
		updateMultiDraftCostEstimate: function() {
			var count = parseInt($('#aips-multi-draft-count').val(), 10) || 2;
			var calls = count * 3; // content + title + excerpt per variant
			var totalTokens = (multiDraftState.tokensPerVariant !== null)
				? multiDraftState.tokensPerVariant * count * 3
				: null;
			var message = AIPS.formatMultiDraftCostEstimate(count, calls, totalTokens);
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
			var errorHeading = aipsMultiDraftL10n.errorHeading || 'Generation Failed';
			var okLabel = aipsMultiDraftL10n.okLabel || 'OK';

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
						multiDraftState.currentComponents = response.data.current_components || {};
						multiDraftState.variants = response.data.variants;
						AIPS.renderMultiDraftComparison(response.data.variants, multiDraftState.currentComponents);
						AIPS.showMultiDraftStep('compare');
					} else {
						AIPS.showMultiDraftStep('config');
						AIPS.Utilities.alertAcknowledge(
							(response.data && response.data.message) || aipsMultiDraftL10n.generateError,
							errorHeading,
							okLabel
						);
					}
				},
				error: function(jqXHR) {
					var message = aipsMultiDraftL10n.generateError;

					if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
						message = jqXHR.responseJSON.data.message;
					}

					AIPS.showMultiDraftStep('config');
					AIPS.Utilities.alertAcknowledge(
						message,
						errorHeading,
						okLabel
					);
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
		 * @param {Array}  variants          Array of variant objects: {index, title, excerpt, content}.
		 * @param {Object} currentComponents Current post values keyed by title/excerpt/content.
		 */
		renderMultiDraftComparison: function(variants, currentComponents) {
			var sections = ['title', 'excerpt', 'content'];
			var labels   = {
				title:   aipsMultiDraftL10n.labelTitle,
				excerpt: aipsMultiDraftL10n.labelExcerpt,
				content: aipsMultiDraftL10n.labelContent
			};
			var currentLabel = aipsMultiDraftL10n.currentLabel || 'Current Value (Keep Existing)';

			currentComponents = currentComponents || {};

			var compareChoices = [
				{
					key: 'current',
					label: currentLabel,
					data: {
						title: currentComponents.title || '',
						excerpt: currentComponents.excerpt || '',
						content: currentComponents.content || ''
					}
				}
			];

			variants.forEach(function(variant) {
				compareChoices.push({
					key: String(variant.index),
					label: aipsMultiDraftL10n.variantLabel.replace('%d', variant.index),
					data: variant
				});
			});

			var html = '';

			sections.forEach(function(section) {
				html += '<div class="aips-mdc-section">';
				html += '<h4 class="aips-mdc-section-label">' + AIPS.Templates.escape(labels[section]) + '</h4>';
				html += '<div class="aips-mdc-columns aips-mdc-cols-' + compareChoices.length + '">';

				compareChoices.forEach(function(choice, idx) {
					var value      = (choice.data && choice.data[section]) ? choice.data[section] : '';
					var isSelected = idx === 0; // default: first variant selected

					html += '<div class="aips-mdc-column' + (isSelected ? ' is-selected' : '') + '">';
					html += '<label class="aips-mdc-column-header">';
					html += '<input type="radio" class="aips-variant-select-radio"'
						+ ' name="aips_variant_' + section + '"'
						+ ' value="' + AIPS.Templates.escape(choice.key) + '"'
						+ ' data-section="' + AIPS.Templates.escape(section) + '"'
						+ (isSelected ? ' checked' : '') + '>';
					html += ' ' + AIPS.Templates.escape(choice.label);
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

				// Default selection to current value to avoid accidental overwrite.
				multiDraftState.selections[section] = 'current';
			});

			$('#aips-multi-draft-compare-body').html(html);
		},

		/**
		 * Update the cost estimate when the variant count input changes.
		 *
		 * Re-fetches the server-side token estimate (since total tokens depend on
		 * the count) and immediately refreshes the display with the formula-based
		 * value while the fetch is in flight.
		 */
		onMultiDraftCountChange: function() {
			var count = parseInt($('#aips-multi-draft-count').val(), 10) || 2;
			// Show current estimate immediately, then refresh with updated server data.
			AIPS.updateMultiDraftCostEstimate();
			if (multiDraftState.postId) {
				AIPS.fetchMultiDraftTokenEstimate(multiDraftState.postId, count);
			}
		},

		/**
		 * Update internal selections map when the editor picks a variant radio.
		 *
		 * @param {Event} e Change event from `.aips-variant-select-radio`.
		 */
		onVariantRadioChange: function(e) {
			var $radio  = $(e.currentTarget);
			var section = $radio.data('section');
			var selected = String($radio.val());

			multiDraftState.selections[section] = selected;

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
			var currentValues = multiDraftState.currentComponents || {};
			['title', 'excerpt', 'content'].forEach(function(section) {
				var selectedChoice = String(multiDraftState.selections[section] || 'current');

				if (selectedChoice === 'current') {
					components[section] = currentValues[section] || '';
					return;
				}

				var variantIndex = parseInt(selectedChoice, 10);
				var found = null;
				$.each(multiDraftState.variants, function(i, v) {
					if (v.index === variantIndex) {
						found = v;
						return false;
					}
				});
				if (found) {
					components[section] = found[section];
				} else {
					components[section] = currentValues[section] || '';
				}
			});

			$.ajax({
				url:  aipsMultiDraftL10n.ajaxUrl,
				type: 'POST',
				data: {
					action:     'aips_apply_merged_draft',
					nonce:      aipsMultiDraftL10n.nonce,
					post_id:    multiDraftState.postId,
					history_id: multiDraftState.historyId,
					components: components
				},
				success: function(response) {
					if (response.success) {
						var updatedComponents = (response.data && response.data.updated_components) ? response.data.updated_components : [];
						var isNoChangeApply = !updatedComponents.length;
						var toastMessage = (response.data && response.data.message)
							|| (isNoChangeApply ? aipsMultiDraftL10n.noChangesApplied : aipsMultiDraftL10n.applySuccess);

						AIPS.Utilities.showToast(
							toastMessage,
							isNoChangeApply ? 'info' : 'success'
						);
						AIPS.closeMultiDraftModal();

						// Refresh the post list if the page provides a loader.
						if (!isNoChangeApply) {
							if (typeof AIPS.loadDraftPosts === 'function') {
								AIPS.loadDraftPosts();
							} else {
								location.reload();
							}
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
