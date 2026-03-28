/**
 * Multi-Draft Compare Modal JavaScript
 *
 * Handles the multi-draft generation and side-by-side comparison UI that lets
 * editors generate 2–3 independent AI variants of post components (title,
 * excerpt, content) then cherry-pick the best version or merge sections before
 * applying selections back to the AI Edit modal.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};

	// Internal state for the multi-draft session.
	var multiDraftState = {
		postId: null,
		historyId: null,
		variants: [],
		components: [],
		selections: {},     // { component: { variantIndex, value } }
		maxVariants: 3
	};

	// Token-cost estimates per component (approximate tokens for a typical post).
	var TOKEN_ESTIMATES = {
		title:   200,
		excerpt: 500,
		content: 2000
	};

	/**
	 * Compute a human-readable cost estimate string.
	 *
	 * @param {number}   variantCount Number of variants.
	 * @param {string[]} components   Components selected.
	 * @return {string}
	 */
	function buildCostEstimate(variantCount, components) {
		if (!components || components.length === 0) {
			return aipsMultiDraftL10n.selectComponent;
		}

		var totalTokens = 0;
		var aiCalls     = 0;

		$.each(components, function(i, comp) {
			var tokens = TOKEN_ESTIMATES[comp] || 500;
			totalTokens += tokens * variantCount;
			aiCalls     += variantCount;
		});

		return aipsMultiDraftL10n.costEstimate
			.replace('{calls}',  aiCalls)
			.replace('{tokens}', totalTokens.toLocaleString());
	}

	/**
	 * Return the list of checked component checkboxes.
	 *
	 * @return {string[]}
	 */
	function getCheckedComponents() {
		var components = [];
		$('.aips-multi-draft-component-cb:checked').each(function() {
			components.push($(this).val());
		});
		return components;
	}

	/**
	 * Update the cost-estimate text whenever the configuration changes.
	 */
	function updateCostEstimate() {
		var count      = parseInt($('#aips-multi-draft-variant-count').val(), 10) || 2;
		var components = getCheckedComponents();
		$('#aips-multi-draft-cost-text').text(buildCostEstimate(count, components));

		var hasComponents = components.length > 0;
		$('#aips-multi-draft-generate').prop('disabled', !hasComponents);
	}

	/**
	 * Render the label for a component.
	 *
	 * @param {string} component
	 * @return {string}
	 */
	function componentLabel(component) {
		var labels = {
			title:   aipsMultiDraftL10n.title,
			excerpt: aipsMultiDraftL10n.excerpt,
			content: aipsMultiDraftL10n.content
		};
		return labels[component] || component;
	}

	/**
	 * Truncate text to a max length and add an ellipsis.
	 *
	 * @param {string} text
	 * @param {number} max
	 * @return {string}
	 */
	function truncate(text, max) {
		if (!text) {
			return '';
		}
		return text.length > max ? text.substring(0, max) + '…' : text;
	}

	/**
	 * Escape HTML special characters.
	 *
	 * @param {string} str
	 * @return {string}
	 */
	function escapeHtml(str) {
		if (!str) {
			return '';
		}
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	/**
	 * Render a single compare column for one variant.
	 *
	 * @param {number} variantIndex 0-based index.
	 * @param {Object} variant      Keyed by component name.
	 * @param {string[]} components Components to render.
	 * @return {string} HTML string.
	 */
	function renderVariantColumn(variantIndex, variant, components) {
		var label = aipsMultiDraftL10n.variantLabel.replace('{n}', variantIndex + 1);
		var html  = '<div class="aips-draft-col" data-variant="' + variantIndex + '">';
		html += '<div class="aips-draft-col-header">' + escapeHtml(label) + '</div>';

		$.each(components, function(i, component) {
			var value       = variant[component];
			var isNull      = value === null || value === undefined;
			var isSelected  = multiDraftState.selections[component] &&
				multiDraftState.selections[component].variantIndex === variantIndex;
			var cellClass   = 'aips-draft-cell aips-draft-cell-' + component;
			if (isSelected) {
				cellClass += ' aips-draft-cell-selected';
			}
			if (isNull) {
				cellClass += ' aips-draft-cell-error';
			}

			html += '<div class="' + cellClass + '"'
				+ ' data-component="' + escapeHtml(component) + '"'
				+ ' data-variant="' + variantIndex + '">';
			html += '<div class="aips-draft-cell-label">' + escapeHtml(componentLabel(component)) + '</div>';

			if (isNull) {
				html += '<div class="aips-draft-cell-body aips-draft-cell-body-error">'
					+ escapeHtml(aipsMultiDraftL10n.generationFailed) + '</div>';
			} else if (component === 'content') {
				// Show a readable preview of the content.
				html += '<div class="aips-draft-cell-body aips-draft-cell-content-preview">'
					+ escapeHtml(truncate(value, 600)) + '</div>';
			} else {
				html += '<div class="aips-draft-cell-body">' + escapeHtml(value) + '</div>';
			}

			if (!isNull) {
				var btnLabel  = isSelected ? aipsMultiDraftL10n.selected : aipsMultiDraftL10n.useThis;
				var btnClass  = 'aips-btn aips-btn-sm aips-multi-draft-select-btn';
				if (isSelected) {
					btnClass += ' aips-btn-primary';
				} else {
					btnClass += ' aips-btn-secondary';
				}
				html += '<div class="aips-draft-cell-actions">';
				html += '<button type="button"'
					+ ' class="' + btnClass + '"'
					+ ' data-component="' + escapeHtml(component) + '"'
					+ ' data-variant="' + variantIndex + '"'
					+ ' aria-pressed="' + (isSelected ? 'true' : 'false') + '">'
					+ escapeHtml(btnLabel) + '</button>';
				html += '</div>';
			}

			html += '</div>'; // .aips-draft-cell
		});

		html += '</div>'; // .aips-draft-col
		return html;
	}

	/**
	 * Re-render the full comparison grid.
	 */
	function renderCompareGrid() {
		var variants   = multiDraftState.variants;
		var components = multiDraftState.components;
		var $grid      = $('#aips-multi-draft-compare');

		var html = '<div class="aips-draft-grid aips-draft-cols-' + variants.length + '">';

		$.each(variants, function(i, variant) {
			html += renderVariantColumn(i, variant, components);
		});

		html += '</div>';

		$grid.html(html);
	}

	/**
	 * Rebuild the selection summary list.
	 */
	function renderSelectionSummary() {
		var $list      = $('#aips-multi-draft-selection-list');
		var selections = multiDraftState.selections;
		var hasAny     = false;
		var html       = '';

		$.each(multiDraftState.components, function(i, component) {
			if (selections[component]) {
				hasAny = true;
				var variantLabel = aipsMultiDraftL10n.variantLabel.replace(
					'{n}', selections[component].variantIndex + 1
				);
				var preview = truncate(selections[component].value, 80);
				html += '<li>'
					+ '<strong>' + escapeHtml(componentLabel(component)) + '</strong>: '
					+ escapeHtml(variantLabel) + ' — '
					+ '<em>' + escapeHtml(preview) + '</em>'
					+ '</li>';
			}
		});

		if (!hasAny) {
			html = '<li class="aips-multi-draft-no-selection">'
				+ escapeHtml(aipsMultiDraftL10n.noSelections) + '</li>';
		}

		$list.html(html);

		// Enable/disable Apply button.
		$('#aips-multi-draft-apply').prop('disabled', !hasAny);
	}

	// -------------------------------------------------------------------------
	// Event Handlers
	// -------------------------------------------------------------------------

	Object.assign(window.AIPS, {

		/**
		 * Initialize the multi-draft feature.
		 */
		initMultiDraft: function() {
			this.bindMultiDraftEvents();
		},

		/**
		 * Bind all multi-draft event handlers.
		 */
		bindMultiDraftEvents: function() {
			$(document).on('click', '.aips-multi-draft-btn', window.AIPS.openMultiDraftModal);
			$(document).on('click', '#aips-multi-draft-close, #aips-multi-draft-cancel-config, #aips-multi-draft-discard', window.AIPS.closeMultiDraftModal);
			$(document).on('click', '#aips-multi-draft-generate', window.AIPS.generateMultiDraft);
			$(document).on('click', '#aips-multi-draft-regenerate', window.AIPS.showMultiDraftConfig);
			$(document).on('click', '.aips-multi-draft-select-btn', window.AIPS.selectDraftVariant);
			$(document).on('click', '#aips-multi-draft-apply', window.AIPS.applyMultiDraftSelections);
			$(document).on('change', '#aips-multi-draft-variant-count', updateCostEstimate);
			$(document).on('change', '.aips-multi-draft-component-cb', updateCostEstimate);
		},

		/**
		 * Open the multi-draft modal for a given post.
		 *
		 * @param {Event} e Click event from a `.aips-multi-draft-btn` element.
		 */
		openMultiDraftModal: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);

			multiDraftState.postId    = $btn.data('post-id');
			multiDraftState.historyId = $btn.data('history-id');
			multiDraftState.variants  = [];
			multiDraftState.selections = {};

			window.AIPS.showMultiDraftConfig();

			$('#aips-multi-draft-modal').show();
			$('body').addClass('aips-modal-open');
			updateCostEstimate();
		},

		/**
		 * Close the multi-draft modal.
		 */
		closeMultiDraftModal: function() {
			$('#aips-multi-draft-modal').hide();
			$('body').removeClass('aips-modal-open');
		},

		/**
		 * Show the configuration panel and hide results.
		 */
		showMultiDraftConfig: function() {
			$('#aips-multi-draft-config').show();
			$('#aips-multi-draft-loading').hide();
			$('#aips-multi-draft-results').hide();
			$('#aips-multi-draft-footer').hide();
			updateCostEstimate();
		},

		/**
		 * Kick off the multi-draft generation AJAX request.
		 */
		generateMultiDraft: function() {
			var variantCount = parseInt($('#aips-multi-draft-variant-count').val(), 10) || 2;
			var components   = getCheckedComponents();

			if (components.length === 0) {
				AIPS.Utilities.showToast(aipsMultiDraftL10n.selectComponent, 'warning');
				return;
			}

			// Transition to loading state.
			$('#aips-multi-draft-config').hide();
			$('#aips-multi-draft-results').hide();
			$('#aips-multi-draft-footer').hide();
			$('#aips-multi-draft-loading').show();

			$.ajax({
				url:  aipsMultiDraftL10n.ajaxUrl,
				type: 'POST',
				data: {
					action:        'aips_generate_multi_draft',
					nonce:         aipsMultiDraftL10n.nonce,
					post_id:       multiDraftState.postId,
					history_id:    multiDraftState.historyId,
					variant_count: variantCount,
					components:    components
				},
				success: window.AIPS.onMultiDraftGenerated,
				error: function() {
					$('#aips-multi-draft-loading').hide();
					$('#aips-multi-draft-config').show();
					AIPS.Utilities.showToast(aipsMultiDraftL10n.generateError, 'error');
				}
			});
		},

		/**
		 * Handle the successful multi-draft AJAX response.
		 *
		 * @param {Object} response jQuery AJAX response.
		 */
		onMultiDraftGenerated: function(response) {
			$('#aips-multi-draft-loading').hide();

			if (!response.success) {
				$('#aips-multi-draft-config').show();
				var msg = (response.data && response.data.message)
					? response.data.message
					: aipsMultiDraftL10n.generateError;
				AIPS.Utilities.showToast(msg, 'error');
				return;
			}

			var data = response.data;

			multiDraftState.variants   = data.variants;
			multiDraftState.components = data.components;
			multiDraftState.selections = {};

			// Show summary.
			$('#aips-multi-draft-summary').text(
				aipsMultiDraftL10n.variantsSummary
					.replace('{count}', data.variant_count)
					.replace('{components}', data.components.map(componentLabel).join(', '))
			);

			// Show partial errors if any.
			if (data.errors && data.errors.length > 0) {
				var errorHtml = '';
				$.each(data.errors, function(i, err) {
					errorHtml += '<li>' + escapeHtml(err) + '</li>';
				});
				$('#aips-multi-draft-error-list').html(errorHtml);
				$('#aips-multi-draft-errors').show();
			} else {
				$('#aips-multi-draft-errors').hide();
			}

			// Render comparison grid.
			renderCompareGrid();
			renderSelectionSummary();

			$('#aips-multi-draft-results').show();
			$('#aips-multi-draft-footer').show();
		},

		/**
		 * Handle "Use this" button click — record the selection for a component.
		 *
		 * @param {Event} e Click event.
		 */
		selectDraftVariant: function(e) {
			e.preventDefault();
			var $btn         = $(e.currentTarget);
			var component    = $btn.data('component');
			var variantIndex = parseInt($btn.data('variant'), 10);
			var value        = multiDraftState.variants[variantIndex]
				? multiDraftState.variants[variantIndex][component]
				: null;

			if (value === null || value === undefined) {
				return;
			}

			multiDraftState.selections[component] = {
				variantIndex: variantIndex,
				value: value
			};

			// Refresh grid and summary to reflect the new selection.
			renderCompareGrid();
			renderSelectionSummary();
		},

		/**
		 * Apply all current selections back to the AI Edit modal and close.
		 */
		applyMultiDraftSelections: function() {
			var selections = multiDraftState.selections;
			var hasAny = false;

			$.each(selections, function(component, sel) {
				if (!sel || sel.value === null) {
					return;
				}
				hasAny = true;

				// Write into the AI Edit modal inputs.
				switch (component) {
					case 'title':
						var $titleInput = $('#aips-component-title');
						if ($titleInput.length) {
							$titleInput.val(sel.value).trigger('input');
						}
						break;

					case 'excerpt':
						var $excerptInput = $('#aips-component-excerpt');
						if ($excerptInput.length) {
							$excerptInput.val(sel.value).trigger('input');
						}
						break;

					case 'content':
						var $contentInput = $('#aips-component-content');
						if ($contentInput.length) {
							$contentInput.val(sel.value).trigger('input');
						}
						break;
				}
			});

			window.AIPS.closeMultiDraftModal();

			if (hasAny) {
				AIPS.Utilities.showToast(aipsMultiDraftL10n.appliedSuccess, 'success');

				// Make sure the AI Edit modal is visible.
				if ($('#aips-ai-edit-modal').is(':visible') === false && multiDraftState.postId) {
					// Programmatically open the AI edit modal if it is not already open.
					$('.aips-ai-edit-btn[data-post-id="' + multiDraftState.postId + '"]').first().trigger('click');
				}
			}
		}
	});

	// Initialise on DOM ready.
	$(document).ready(function() {
		if (typeof window.AIPS.initMultiDraft === 'function') {
			window.AIPS.initMultiDraft();
		}
	});

})(jQuery);
