/**
 * Content Components admin page JS.
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.ContentComponents = {
		components: [],
		counts: {},
		currentTab: 'all',
		searchTerm: '',
		typeFilter: 'all',
		usageFilter: 'all',
		currentComponent: null,
		rules: [],
		examples: [],

		init: function () {
			this.components = Array.isArray(aipsContentComponentsConfig.components) ? aipsContentComponentsConfig.components : [];
			this.counts = aipsContentComponentsConfig.counts || {};
			this.examples = Array.isArray(aipsContentComponentsConfig.exampleCatalog) ? aipsContentComponentsConfig.exampleCatalog : [];
			this.bindEvents();
			this.render();
		},

		bindEvents: function () {
			$(document).on('click', '#aips-add-content-component-btn, #aips-add-content-component-empty-btn', this.openAddFlow.bind(this));
			$(document).on('click', '#aips-content-component-example-modal .aips-modal-close', this.closeExampleModal.bind(this));
			$(document).on('click', '#aips-content-component-modal .aips-modal-close', this.closeModal.bind(this));
			$(document).on('click', '#aips-content-component-modal', this.onOverlayClick.bind(this));
			$(document).on('click', '#aips-content-component-example-modal', this.onExampleOverlayClick.bind(this));
			$(document).on('click', '#aips-content-component-refresh-examples-btn', this.fetchExamples.bind(this));
			$(document).on('click', '.aips-use-content-component-example', this.useExample.bind(this));
			$(document).on('click', '.aips-edit-content-component', this.openEditModal.bind(this));
			$(document).on('click', '#aips-save-content-component-btn', this.saveComponent.bind(this));
			$(document).on('click', '.aips-delete-content-component', this.deleteComponent.bind(this));
			$(document).on('click', '.aips-toggle-content-component', this.toggleComponent.bind(this));
			$(document).on('click', '.aips-tab-link', this.switchTab.bind(this));
			$(document).on('input', '#aips-content-component-search', this.onSearch.bind(this));
			$(document).on('click', '#aips-content-component-search-clear', this.clearSearch.bind(this));
			$(document).on('change', '#aips-content-component-type-filter', this.onTypeFilterChange.bind(this));
			$(document).on('change', '#aips-content-component-usage-filter', this.onUsageFilterChange.bind(this));
			$(document).on('click', '#aips-add-content-component-rule', this.addRuleRow.bind(this));
			$(document).on('click', '.aips-remove-content-component-rule', this.removeRuleRow.bind(this));
			$(document).on('input change', '#aips-content-component-form input, #aips-content-component-form textarea, #aips-content-component-form select', this.onFormChanged.bind(this));
			$(document).on('click', '#aips-content-component-run-qa', this.runQaValidation.bind(this));
			$(document).on('click', '#aips-content-component-dry-run-btn', this.runDryRun.bind(this));
		},

		render: function () {
			this.renderStats();
			this.renderTable();
			this.renderFooterCount();
		},

		renderStats: function () {
			$('#aips-cc-stat-total').text(this.counts.total || 0);
			$('#aips-cc-stat-active').text(this.counts.active || 0);
			$('#aips-cc-stat-inactive').text(this.counts.inactive || 0);
			$('#aips-cc-stat-needs-review').text(this.counts.needs_review || 0);
		},

		renderTable: function () {
			var filtered = this.getFilteredComponents();
			if (!filtered.length) {
				$('#aips-content-components-content').html(
					AIPS.Templates.render('aips-tmpl-content-components-empty', {
						title: aipsContentComponentsL10n.emptyTitle,
						description: aipsContentComponentsL10n.emptyDescription,
						buttonLabel: aipsContentComponentsL10n.addFirst
					})
				);
				return;
			}

			var rows = '';
			filtered.forEach(function (component) {
				rows += AIPS.ContentComponents.renderRow(component);
			});

			$('#aips-content-components-content').html(
				AIPS.Templates.renderRaw('aips-tmpl-content-components-table', {
					titleLabel: aipsContentComponentsL10n.tableTitle,
					typeLabel: aipsContentComponentsL10n.tableType,
					rulesLabel: aipsContentComponentsL10n.tableRules,
					usageLabel: aipsContentComponentsL10n.tableUsage,
					statusLabel: aipsContentComponentsL10n.tableStatus,
					qaLabel: aipsContentComponentsL10n.tableQa,
					updatedLabel: aipsContentComponentsL10n.tableUpdated,
					actionsLabel: aipsContentComponentsL10n.tableActions,
					rows: rows
				})
			);
		},

		renderRow: function (component) {
			var description = component.description || aipsContentComponentsL10n.noDescription;
			var statusBadge = component.is_active === 1
				? '<span class="aips-badge aips-badge-success">' + aipsContentComponentsL10n.active + '</span>'
				: '<span class="aips-badge aips-badge-neutral">' + aipsContentComponentsL10n.inactive + '</span>';
			var qaBadgeClass = component.qa_status === 'passed' ? 'aips-badge-success' : 'aips-badge-warning';
			if (component.qa_status === 'untested') {
				qaBadgeClass = 'aips-badge-neutral';
			}
			var qaBadge = '<span class="aips-badge ' + qaBadgeClass + '">' + this.getQaLabel(component.qa_status) + '</span>';
			var usage = component.analytics || {};
			var usageSummary = usage.injections && usage.injections > 0
				? usage.injections + ' injections / ' + (usage.unique_posts || 0) + ' posts'
				: '0 injections';

			return AIPS.Templates.renderRaw('aips-tmpl-content-component-row', {
				id: component.id,
				title: AIPS.Templates.escape(component.title),
				description: AIPS.Templates.escape(description),
				componentType: AIPS.Templates.escape(this.getTypeLabel(component.component_type)),
				ruleSummary: AIPS.Templates.escape(component.rule_summary || aipsContentComponentsL10n.noRuleSummary),
				usageSummary: AIPS.Templates.escape(usageSummary),
				statusBadge: statusBadge,
				qaBadge: qaBadge,
				updatedAt: AIPS.DateTime.formatDate(component.updated_at),
				editLabel: aipsContentComponentsL10n.edit,
				deleteLabel: aipsContentComponentsL10n.deleteLabel,
				toggleLabel: component.is_active === 1 ? aipsContentComponentsL10n.deactivate : aipsContentComponentsL10n.activate,
				isActive: component.is_active
			});
		},

		renderFooterCount: function () {
			var total = this.getFilteredComponents().length;
			var label = total === 1 ? aipsContentComponentsL10n.componentSingular : aipsContentComponentsL10n.componentPlural;
			$('#aips-content-components-result-count').text(total + ' ' + label);
		},

		getFilteredComponents: function () {
			var term = this.searchTerm;
			var typeFilter = this.typeFilter;
			var usageFilter = this.usageFilter;

			return this.components.filter(function (component) {
				var tabMatch = true;
				var usage = component.analytics || {};
				var hasUsage = parseInt(usage.injections || 0, 10) > 0;

				if (AIPS.ContentComponents.currentTab === 'active') {
					tabMatch = component.is_active === 1;
				} else if (AIPS.ContentComponents.currentTab === 'inactive') {
					tabMatch = component.is_active !== 1;
				} else if (AIPS.ContentComponents.currentTab === 'needs_review') {
					tabMatch = component.qa_status === 'needs_review';
				}

				if (!tabMatch) {
					return false;
				}

				if (typeFilter !== 'all' && component.component_type !== typeFilter) {
					return false;
				}

				if (usageFilter === 'used' && !hasUsage) {
					return false;
				}

				if (usageFilter === 'never_used' && hasUsage) {
					return false;
				}

				if (!term) {
					return true;
				}

				var haystack = ((component.title || '') + ' ' + (component.description || '') + ' ' + (component.component_type || '') + ' ' + (component.rule_summary || '')).toLowerCase();
				return haystack.indexOf(term) !== -1;
			});
		},

		switchTab: function (e) {
			e.preventDefault();
			this.currentTab = $(e.currentTarget).data('tab') || 'all';
			$('.aips-tab-link').removeClass('active');
			$(e.currentTarget).addClass('active');
			this.renderTable();
			this.renderFooterCount();
		},

		onSearch: function (e) {
			this.searchTerm = ($(e.currentTarget).val() || '').toLowerCase().trim();
			$('#aips-content-component-search-clear').toggle(this.searchTerm.length > 0);
			this.renderTable();
			this.renderFooterCount();
		},

		clearSearch: function (e) {
			e.preventDefault();
			$('#aips-content-component-search').val('');
			this.searchTerm = '';
			$('#aips-content-component-search-clear').hide();
			this.renderTable();
			this.renderFooterCount();
		},

		onTypeFilterChange: function (e) {
			this.typeFilter = $(e.currentTarget).val() || 'all';
			this.renderTable();
			this.renderFooterCount();
		},

		onUsageFilterChange: function (e) {
			this.usageFilter = $(e.currentTarget).val() || 'all';
			this.renderTable();
			this.renderFooterCount();
		},

		openAddFlow: function (e) {
			e.preventDefault();
			this.fetchExamples();
		},

		fetchExamples: function (e) {
			if (e) {
				e.preventDefault();
			}

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_get_content_component_examples',
				nonce: aipsAjax.nonce
			}).done(function (response) {
				if (!response.success) {
					AIPS.Utilities.showToast(aipsContentComponentsL10n.loadExamplesError, 'error');
					return;
				}
				AIPS.ContentComponents.examples = response.data.examples || [];
				AIPS.ContentComponents.renderExamples();
				$('#aips-content-component-example-modal').show();
			}).fail(function () {
				AIPS.Utilities.showToast(aipsContentComponentsL10n.loadExamplesError, 'error');
			});
		},

		renderExamples: function () {
			var html = '';
			(this.examples || []).forEach(function (example) {
				var hints = [];
				var key;
				for (key in (example.rule_hints || {})) {
					if (Object.prototype.hasOwnProperty.call(example.rule_hints, key)) {
						hints.push(key + ': ' + example.rule_hints[key]);
					}
				}
				html += AIPS.Templates.renderRaw('aips-tmpl-content-component-example-card', {
					key: AIPS.Templates.escape(example.key || ''),
					typeLabel: AIPS.Templates.escape(AIPS.ContentComponents.getTypeLabel(example.component_type)),
					name: AIPS.Templates.escape(example.name || ''),
					description: AIPS.Templates.escape(example.description || ''),
					snippet: AIPS.Templates.escape(example.content || ''),
					hints: AIPS.Templates.escape(hints.join(' | ')),
					useLabel: aipsContentComponentsL10n.useExample
				});
			});
			$('#aips-content-component-example-list').html(html);
		},

		useExample: function (e) {
			e.preventDefault();
			var key = $(e.currentTarget).data('example-key');
			var example = this.findExample(key);
			if (!example) {
				return;
			}

			this.currentComponent = null;
			this.rules = Array.isArray(example.rules && example.rules.conditions) ? example.rules.conditions.slice() : [];
			this.resetModalForm();
			$('#aips-content-component-modal-title').text(aipsContentComponentsL10n.addTitle);
			$('#aips-content-component-title').val(example.name || '');
			$('#aips-content-component-description').val(example.description || '');
			$('#aips-content-component-type').val(example.component_type || 'custom');
			$('#aips-content-component-content').val(example.content || '');
			$('#aips-content-component-rules-logic').val(example.rules && example.rules.logic ? example.rules.logic : 'and');
			$('#aips-content-component-rules-action').val(example.rules && example.rules.action ? example.rules.action : 'add_at_end');
			$('#aips-content-component-date-start').val(example.rules && example.rules.date_window && example.rules.date_window.start ? example.rules.date_window.start : '');
			$('#aips-content-component-date-end').val(example.rules && example.rules.date_window && example.rules.date_window.end ? example.rules.date_window.end : '');
			$('#aips-content-component-date-timezone').val(example.rules && example.rules.date_window && example.rules.date_window.timezone ? example.rules.date_window.timezone : '');
			this.rules = Array.isArray(example.rules && example.rules.conditions) ? example.rules.conditions.slice() : [];
			this.renderRulesRows();
			this.renderPreview(example.content || '');
			this.updateRuleSummary();
			this.renderAnalytics(null);
			this.closeExampleModal();
			$('#aips-content-component-modal').show();
			AIPS.Utilities.showToast(aipsContentComponentsL10n.exampleApplied, 'success');
		},

		openEditModal: function (e) {
			e.preventDefault();
			var component = this.findComponent(parseInt($(e.currentTarget).data('id'), 10));
			if (!component) {
				return;
			}
			this.populateEditModal(component);
			$('#aips-content-component-modal').show();
		},

		closeModal: function (e) {
			if (e) {
				e.preventDefault();
			}
			$('#aips-content-component-modal').hide();
		},

		closeExampleModal: function (e) {
			if (e) {
				e.preventDefault();
			}
			$('#aips-content-component-example-modal').hide();
		},

		onOverlayClick: function (e) {
			if ($(e.target).is('#aips-content-component-modal')) {
				$('#aips-content-component-modal').hide();
			}
		},

		onExampleOverlayClick: function (e) {
			if ($(e.target).is('#aips-content-component-example-modal')) {
				$('#aips-content-component-example-modal').hide();
			}
		},

		saveComponent: function (e) {
			e.preventDefault();
			var title = ($('#aips-content-component-title').val() || '').trim();
			if (!title) {
				AIPS.Utilities.showToast(aipsContentComponentsL10n.titleRequired, 'error');
				return;
			}

			var isCreate = parseInt($('#aips-content-component-id').val(), 10) === 0;
			var payload = this.getModalPayload();
			var $btn = $('#aips-save-content-component-btn');
			AIPS.Utilities.setButtonLoading($btn, aipsContentComponentsL10n.saving);

			$.post(aipsAjax.ajaxUrl, payload).done(function (response) {
				AIPS.Utilities.resetButton($btn);
				if (!response.success) {
					AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : aipsContentComponentsL10n.saveError, 'error');
					return;
				}

				AIPS.ContentComponents.upsertComponent(response.data.component);
				AIPS.ContentComponents.counts = response.data.counts || AIPS.ContentComponents.counts;
				AIPS.ContentComponents.render();
				AIPS.Utilities.showToast(response.data.message || aipsContentComponentsL10n.saved, 'success');

				if (isCreate) {
					AIPS.ContentComponents.openEditModalById(response.data.component.id);
					return;
				}

				$('#aips-content-component-modal').hide();
			}).fail(function () {
				AIPS.Utilities.resetButton($btn);
				AIPS.Utilities.showToast(aipsContentComponentsL10n.saveError, 'error');
			});
		},

		deleteComponent: function (e) {
			e.preventDefault();
			var id = parseInt($(e.currentTarget).data('id'), 10);
			if (!id) {
				return;
			}

			AIPS.Utilities.confirm(
				aipsContentComponentsL10n.deleteConfirm,
				aipsContentComponentsL10n.confirmHeading,
				[
					{ label: aipsContentComponentsL10n.cancel, className: 'aips-btn aips-btn-secondary' },
					{
						label: aipsContentComponentsL10n.deleteLabel,
						className: 'aips-btn aips-btn-danger-solid',
						action: function () {
							$.post(aipsAjax.ajaxUrl, {
								action: 'aips_delete_content_component',
								nonce: aipsAjax.nonce,
								component_id: id
							}).done(function (response) {
								if (!response.success) {
									AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : aipsContentComponentsL10n.deleteError, 'error');
									return;
								}
								AIPS.ContentComponents.removeComponent(id);
								AIPS.ContentComponents.counts = response.data.counts || AIPS.ContentComponents.counts;
								AIPS.ContentComponents.render();
								AIPS.Utilities.showToast(response.data.message || aipsContentComponentsL10n.deleted, 'success');
							}).fail(function () {
								AIPS.Utilities.showToast(aipsContentComponentsL10n.deleteError, 'error');
							});
						}
					}
				]
			);
		},

		toggleComponent: function (e) {
			e.preventDefault();
			var id = parseInt($(e.currentTarget).data('id'), 10);
			var isActive = parseInt($(e.currentTarget).data('active'), 10) === 1 ? 1 : 0;
			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_toggle_content_component_active',
				nonce: aipsAjax.nonce,
				component_id: id,
				is_active: isActive ? 0 : 1
			}).done(function (response) {
				if (!response.success) {
					AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : aipsContentComponentsL10n.toggleError, 'error');
					return;
				}
				AIPS.ContentComponents.upsertComponent(response.data.component);
				AIPS.ContentComponents.counts = response.data.counts || AIPS.ContentComponents.counts;
				AIPS.ContentComponents.render();
				AIPS.Utilities.showToast(response.data.message || aipsContentComponentsL10n.toggled, 'success');
			}).fail(function () {
				AIPS.Utilities.showToast(aipsContentComponentsL10n.toggleError, 'error');
			});
		},

		addRuleRow: function (e) {
			e.preventDefault();
			this.rules.push({ field: 'category', operator: 'is', values: [] });
			this.renderRulesRows();
			this.updateRuleSummary();
		},

		removeRuleRow: function (e) {
			e.preventDefault();
			var index = parseInt($(e.currentTarget).closest('.aips-content-component-rule-row').data('index'), 10);
			if (!Number.isInteger(index)) {
				return;
			}
			this.rules.splice(index, 1);
			this.renderRulesRows();
			this.updateRuleSummary();
		},

		renderRulesRows: function () {
			var html = '';
			for (var i = 0; i < this.rules.length; i++) {
				var rule = this.rules[i];
				html += AIPS.Templates.renderRaw('aips-tmpl-content-component-rule-row', {
					index: i,
					joinLabel: i === 0 ? aipsContentComponentsL10n.when : (this.getRuleLogic() === 'and' ? 'AND' : 'OR'),
					fieldOptions: this.buildSelectOptions(aipsContentComponentsConfig.conditions, rule.field),
					operatorOptions: this.buildSelectOptions(aipsContentComponentsConfig.operators, rule.operator),
					values: AIPS.Templates.escape(Array.isArray(rule.values) ? rule.values.join(', ') : ''),
					valuePlaceholder: aipsContentComponentsL10n.valuePlaceholder,
					removeLabel: aipsContentComponentsL10n.remove
				});
			}
			$('#aips-content-component-rules-list').html(html);
		},

		onFormChanged: function () {
			this.renderPreview($('#aips-content-component-content').val() || '');
			this.updateRuleSummary();
		},

		runQaValidation: function (e) {
			e.preventDefault();
			var rulesPayload = this.collectRulesFromUI();
			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_validate_content_component',
				nonce: aipsAjax.nonce,
				title: ($('#aips-content-component-title').val() || '').trim(),
				content: $('#aips-content-component-content').val() || '',
				rules: JSON.stringify(rulesPayload)
			}).done(function (response) {
				if (!response.success) {
					AIPS.Utilities.showToast(aipsContentComponentsL10n.qaError, 'error');
					return;
				}
				AIPS.ContentComponents.updateQaDisplay(response.data.qa_status, response.data.qa_notes || '');
				if (response.data.rule_summary) {
					$('#aips-content-component-rule-summary').text(response.data.rule_summary);
				}
				AIPS.Utilities.showToast(aipsContentComponentsL10n.qaDone, 'success');
			}).fail(function () {
				AIPS.Utilities.showToast(aipsContentComponentsL10n.qaError, 'error');
			});
		},

		runDryRun: function (e) {
			e.preventDefault();
			var payload = {
				action: 'aips_content_components_dry_run',
				nonce: aipsAjax.nonce,
				post_id: parseInt($('#aips-content-component-dry-run-post-id').val(), 10) || 0,
				draft_body: $('#aips-content-component-dry-run-draft-body').val() || $('#aips-content-component-content').val() || '',
				categories: $('#aips-content-component-dry-run-categories').val() || '',
				tags: $('#aips-content-component-dry-run-tags').val() || '',
				author_persona: $('#aips-content-component-dry-run-persona').val() || '',
				region: $('#aips-content-component-dry-run-region').val() || '',
				locale: $('#aips-content-component-dry-run-locale').val() || '',
				current_title: ($('#aips-content-component-title').val() || '').trim(),
				current_component_type: $('#aips-content-component-type').val() || 'custom',
				current_content: $('#aips-content-component-content').val() || '',
				current_rules: JSON.stringify(this.collectRulesFromUI())
			};
			var $btn = $('#aips-content-component-dry-run-btn');
			AIPS.Utilities.setButtonLoading($btn, aipsContentComponentsL10n.simulating);

			$.post(aipsAjax.ajaxUrl, payload).done(function (response) {
				AIPS.Utilities.resetButton($btn);
				if (!response.success) {
					AIPS.Utilities.showToast(aipsContentComponentsL10n.dryRunError, 'error');
					return;
				}
				AIPS.ContentComponents.renderDryRunResults(response.data);
			}).fail(function () {
				AIPS.Utilities.resetButton($btn);
				AIPS.Utilities.showToast(aipsContentComponentsL10n.dryRunError, 'error');
			});
		},

		renderDryRunResults: function (data) {
			var matchedItems = data.matched_components || [];
			var rejectedItems = data.rejected_components || [];
			var matchedHtml = '<p class="description">' + AIPS.Templates.escape(aipsContentComponentsL10n.noneMatched) + '</p>';
			var rejectedHtml = '<p class="description">' + AIPS.Templates.escape(aipsContentComponentsL10n.noneRejected) + '</p>';

			if (matchedItems.length) {
				matchedHtml = '<ul>';
				matchedItems.forEach(function (item) {
					matchedHtml += '<li><strong>' + AIPS.Templates.escape(item.title) + '</strong> - ' + AIPS.Templates.escape(item.rule_summary || item.placement || '') + '</li>';
				});
				matchedHtml += '</ul>';
			}

			if (rejectedItems.length) {
				rejectedHtml = '<ul>';
				rejectedItems.forEach(function (item) {
					rejectedHtml += '<li><strong>' + AIPS.Templates.escape(item.title) + '</strong> - ' + AIPS.Templates.escape(item.reason || '') + '</li>';
				});
				rejectedHtml += '</ul>';
			}

			var html = ''
				+ '<div class="aips-dry-run-summary">' + AIPS.Templates.escape(data.diff_summary || '') + '</div>'
				+ '<div class="aips-dry-run-columns">'
				+ '<div><h4>' + AIPS.Templates.escape(aipsContentComponentsL10n.matchedLabel) + '</h4>' + matchedHtml + '</div>'
				+ '<div><h4>' + AIPS.Templates.escape(aipsContentComponentsL10n.rejectedLabel) + '</h4>' + rejectedHtml + '</div>'
				+ '</div>'
				+ '<div class="aips-dry-run-preview-panels">'
				+ '<div><h4>' + AIPS.Templates.escape(aipsContentComponentsL10n.beforeLabel) + '</h4><pre>' + AIPS.Templates.escape(data.before_content || '') + '</pre></div>'
				+ '<div><h4>' + AIPS.Templates.escape(aipsContentComponentsL10n.afterLabel) + '</h4><div class="aips-dry-run-html-preview">' + (data.preview_html || '') + '</div></div>'
				+ '</div>';

			$('#aips-content-component-dry-run-results').html(html);
		},

		renderPreview: function (content) {
			if (!content || !content.trim()) {
				$('#aips-content-component-preview').text(aipsContentComponentsL10n.previewEmpty);
				return;
			}
			$('#aips-content-component-preview').html(content);
		},

		updateQaDisplay: function (status, notes) {
			var className = 'aips-badge aips-badge-neutral';
			if (status === 'passed') {
				className = 'aips-badge aips-badge-success';
			} else if (status === 'needs_review') {
				className = 'aips-badge aips-badge-warning';
			}
			$('#aips-content-component-qa-status').attr('class', className).text(this.getQaLabel(status));
			$('#aips-content-component-qa-notes').text(notes || '');
		},

		updateRuleSummary: function () {
			var title = ($('#aips-content-component-title').val() || '').trim() || 'This component';
			var rules = this.collectRulesFromUI();
			var actionLabel = this.findActionLabel(rules.action);
			var conditions = [];

			(rules.conditions || []).forEach(function (condition) {
				var fieldLabel = AIPS.ContentComponents.findConditionLabel(condition.field);
				var operatorLabel = AIPS.ContentComponents.findOperatorLabel(condition.operator);
				var values = Array.isArray(condition.values) ? condition.values.join(', ') : '';
				if (values) {
					conditions.push(fieldLabel + ' ' + operatorLabel + ' "' + values + '"');
				}
			});

			var summary = 'Inject "' + title + '" ' + actionLabel;
			if (conditions.length) {
				summary += ' when ' + conditions.join(rules.logic === 'or' ? ' or ' : ' and ');
			}
			if (rules.date_window && (rules.date_window.start || rules.date_window.end)) {
				if (rules.date_window.start && rules.date_window.end) {
					summary += ' between ' + rules.date_window.start + ' and ' + rules.date_window.end;
				} else if (rules.date_window.start) {
					summary += ' starting ' + rules.date_window.start;
				} else if (rules.date_window.end) {
					summary += ' until ' + rules.date_window.end;
				}
			}

			$('#aips-content-component-rule-summary').text(summary + '.');
		},

		renderAnalytics: function (component) {
			if (!aipsContentComponentsConfig.featureEnabled) {
				$('#aips-content-component-analytics').html('<p class="description">' + AIPS.Templates.escape(aipsContentComponentsL10n.engineDisabled) + '</p>');
				return;
			}
			var analytics = component && component.analytics ? component.analytics : null;
			if (!analytics) {
				$('#aips-content-component-analytics').html('<p class="description">' + AIPS.Templates.escape(aipsContentComponentsL10n.analyticsEmpty) + '</p>');
				return;
			}
			var dryRunRate = String(analytics.dry_run_match_rate || 0) + '%';
			var lastInjectedAt = analytics.last_injected_at ? AIPS.DateTime.formatDate(analytics.last_injected_at) : 'N/A';
			$('#aips-content-component-analytics').html(
				'<div class="aips-content-component-analytics-grid">'
				+ '<div><strong>' + AIPS.Templates.escape(String(analytics.impressions || 0)) + '</strong><span>' + AIPS.Templates.escape(aipsContentComponentsL10n.analyticsImpressions) + '</span></div>'
				+ '<div><strong>' + AIPS.Templates.escape(String(analytics.injections || 0)) + '</strong><span>' + AIPS.Templates.escape(aipsContentComponentsL10n.analyticsInjections) + '</span></div>'
				+ '<div><strong>' + AIPS.Templates.escape(String(analytics.regeneration_reinjections || 0)) + '</strong><span>' + AIPS.Templates.escape(aipsContentComponentsL10n.analyticsReinjections) + '</span></div>'
				+ '<div><strong>' + AIPS.Templates.escape(String(analytics.unique_posts || 0)) + '</strong><span>' + AIPS.Templates.escape(aipsContentComponentsL10n.analyticsUniquePosts) + '</span></div>'
				+ '<div><strong>' + AIPS.Templates.escape(dryRunRate) + '</strong><span>' + AIPS.Templates.escape(aipsContentComponentsL10n.analyticsDryRunRate) + '</span></div>'
				+ '<div><strong>' + AIPS.Templates.escape(lastInjectedAt) + '</strong><span>' + AIPS.Templates.escape(aipsContentComponentsL10n.analyticsLastSeen) + '</span></div>'
				+ '<div><strong>' + AIPS.Templates.escape(String(analytics.matched_count || 0)) + '</strong><span>' + AIPS.Templates.escape(aipsContentComponentsL10n.analyticsMatched) + '</span></div>'
				+ '<div><strong>' + AIPS.Templates.escape(String(analytics.skipped_conflict_count || 0)) + '</strong><span>' + AIPS.Templates.escape(aipsContentComponentsL10n.analyticsConflictSkips) + '</span></div>'
				+ '<div><strong>' + AIPS.Templates.escape(String(analytics.skipped_exclusion_count || 0)) + '</strong><span>' + AIPS.Templates.escape(aipsContentComponentsL10n.analyticsExclusionSkips) + '</span></div>'
				+ '</div>'
			);
		},

		getModalPayload: function () {
			return {
				action: 'aips_save_content_component',
				nonce: aipsAjax.nonce,
				component_id: parseInt($('#aips-content-component-id').val(), 10) || 0,
				title: ($('#aips-content-component-title').val() || '').trim(),
				description: ($('#aips-content-component-description').val() || '').trim(),
				component_type: $('#aips-content-component-type').val() || 'custom',
				content: $('#aips-content-component-content').val() || '',
				is_active: $('#aips-content-component-is-active').is(':checked') ? 1 : 0,
				rules: JSON.stringify(this.collectRulesFromUI())
			};
		},

		collectRulesFromUI: function () {
			var conditions = [];
			$('#aips-content-component-rules-list .aips-content-component-rule-row').each(function () {
				var valuesRaw = ($(this).find('.aips-cc-rule-values').val() || '').trim();
				conditions.push({
					field: $(this).find('.aips-cc-rule-field').val() || 'category',
					operator: $(this).find('.aips-cc-rule-operator').val() || 'is',
					values: valuesRaw ? valuesRaw.split(',').map(function (value) { return value.trim(); }).filter(Boolean) : []
				});
			});
			return {
				logic: this.getRuleLogic(),
				action: $('#aips-content-component-rules-action').val() || 'add_at_end',
				conditions: conditions,
				date_window: {
					start: $('#aips-content-component-date-start').val() || '',
					end: $('#aips-content-component-date-end').val() || '',
					timezone: $('#aips-content-component-date-timezone').val() || ''
				}
			};
		},

		resetModalForm: function () {
			$('#aips-content-component-id').val(0);
			$('#aips-content-component-title').val('');
			$('#aips-content-component-description').val('');
			$('#aips-content-component-type').val('cta');
			$('#aips-content-component-content').val('');
			$('#aips-content-component-is-active').prop('checked', true);
			$('#aips-content-component-rules-logic').val('and');
			this.rules = [];
			this.populateActionOptions();
			this.renderRulesRows();
			$('#aips-content-component-date-start').val('');
			$('#aips-content-component-date-end').val('');
			$('#aips-content-component-date-timezone').val('');
			this.updateQaDisplay('untested', '');
			this.renderPreview('');
			this.updateRuleSummary();
			$('#aips-content-component-dry-run-post-id').val('');
			$('#aips-content-component-dry-run-region').val('');
			$('#aips-content-component-dry-run-locale').val('');
			$('#aips-content-component-dry-run-categories').val('');
			$('#aips-content-component-dry-run-tags').val('');
			$('#aips-content-component-dry-run-persona').val('');
			$('#aips-content-component-dry-run-draft-body').val('');
			$('#aips-content-component-dry-run-results').html('<p class="description">' + AIPS.Templates.escape(aipsContentComponentsL10n.dryRunEmpty) + '</p>');
		},

		populateActionOptions: function () {
			$('#aips-content-component-rules-action').html(this.buildSelectOptions(aipsContentComponentsConfig.actions, 'add_at_end'));
		},

		buildSelectOptions: function (options, selected) {
			var html = '';
			(options || []).forEach(function (item) {
				var isSelected = item.value === selected ? ' selected' : '';
				html += '<option value="' + AIPS.Templates.escape(item.value) + '"' + isSelected + '>' + AIPS.Templates.escape(item.label) + '</option>';
			});
			return html;
		},

		openEditModalById: function (id) {
			var component = this.findComponent(id);
			if (!component) {
				return;
			}
			this.populateEditModal(component);
			$('#aips-content-component-modal').show();
		},

		populateEditModal: function (component) {
			this.currentComponent = component;
			this.rules = Array.isArray(component.rules && component.rules.conditions) ? component.rules.conditions.slice() : [];
			$('#aips-content-component-id').val(component.id);
			$('#aips-content-component-title').val(component.title || '');
			$('#aips-content-component-description').val(component.description || '');
			$('#aips-content-component-type').val(component.component_type || 'custom');
			$('#aips-content-component-content').val(component.content || '');
			$('#aips-content-component-is-active').prop('checked', component.is_active === 1);
			$('#aips-content-component-modal-title').text(aipsContentComponentsL10n.editTitle);
			this.populateActionOptions();
			$('#aips-content-component-rules-logic').val(component.rules && component.rules.logic ? component.rules.logic : 'and');
			$('#aips-content-component-rules-action').val(component.rules && component.rules.action ? component.rules.action : 'add_at_end');
			$('#aips-content-component-date-start').val(component.rules && component.rules.date_window && component.rules.date_window.start ? component.rules.date_window.start : '');
			$('#aips-content-component-date-end').val(component.rules && component.rules.date_window && component.rules.date_window.end ? component.rules.date_window.end : '');
			$('#aips-content-component-date-timezone').val(component.rules && component.rules.date_window && component.rules.date_window.timezone ? component.rules.date_window.timezone : '');
			this.renderRulesRows();
			this.updateQaDisplay(component.qa_status || 'untested', component.qa_notes || '');
			this.renderPreview(component.content || '');
			$('#aips-content-component-rule-summary').text(component.rule_summary || aipsContentComponentsL10n.noRuleSummary);
			this.renderAnalytics(component);
			$('#aips-content-component-dry-run-draft-body').val(component.content || '');
		},

		getRuleLogic: function () {
			return $('#aips-content-component-rules-logic').val() === 'or' ? 'or' : 'and';
		},

		findComponent: function (id) {
			return this.components.find(function (component) {
				return parseInt(component.id, 10) === parseInt(id, 10);
			}) || null;
		},

		findExample: function (key) {
			return (this.examples || []).find(function (example) {
				return example.key === key;
			}) || null;
		},

		upsertComponent: function (component) {
			var index = this.components.findIndex(function (item) {
				return parseInt(item.id, 10) === parseInt(component.id, 10);
			});
			if (index > -1) {
				this.components[index] = component;
			} else {
				this.components.unshift(component);
			}
		},

		removeComponent: function (id) {
			this.components = this.components.filter(function (component) {
				return parseInt(component.id, 10) !== parseInt(id, 10);
			});
		},

		getTypeLabel: function (type) {
			var labels = aipsContentComponentsConfig.typeLabels || {};
			return labels[type] || type || 'custom';
		},

		getQaLabel: function (status) {
			if (status === 'passed') {
				return aipsContentComponentsL10n.qaPassed;
			}
			if (status === 'needs_review') {
				return aipsContentComponentsL10n.qaNeedsReview;
			}
			return aipsContentComponentsL10n.qaUntested;
		},

		findActionLabel: function (value) {
			var item = (aipsContentComponentsConfig.actions || []).find(function (action) {
				return action.value === value;
			});
			return item ? item.label.toLowerCase() : value;
		},

		findConditionLabel: function (value) {
			var item = (aipsContentComponentsConfig.conditions || []).find(function (condition) {
				return condition.value === value;
			});
			return item ? item.label.toLowerCase() : value;
		},

		findOperatorLabel: function (value) {
			var item = (aipsContentComponentsConfig.operators || []).find(function (operator) {
				return operator.value === value;
			});
			return item ? operatorLabelToPhrase(item.label) : value;

			function operatorLabelToPhrase(label) {
				return String(label || '').toLowerCase();
			}
		}
	};

	$(document).ready(function () {
		if ($('#aips-content-components-panel').length) {
			AIPS.ContentComponents.populateActionOptions();
			AIPS.ContentComponents.init();
		}
	});
}(jQuery));
