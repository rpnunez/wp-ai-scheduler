/**
 * Content Components admin page JS.
 *
 * @package AI_Post_Scheduler
 * @since 2.7.0
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
		currentComponent: null,
		rules: [],

		/**
		 * Bootstrap module.
		 *
		 * @return {void}
		 */
		init: function () {
			this.components = Array.isArray(aipsContentComponentsConfig.components) ? aipsContentComponentsConfig.components : [];
			this.counts = aipsContentComponentsConfig.counts || {};
			this.bindEvents();
			this.render();
		},

		/**
		 * Bind UI events.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			$(document).on('click', '#aips-add-content-component-btn, #aips-add-content-component-empty-btn', this.openAddModal.bind(this));
			$(document).on('click', '.aips-edit-content-component', this.openEditModal.bind(this));
			$(document).on('click', '#aips-save-content-component-btn', this.saveComponent.bind(this));
			$(document).on('click', '.aips-delete-content-component', this.deleteComponent.bind(this));
			$(document).on('click', '.aips-toggle-content-component', this.toggleComponent.bind(this));
			$(document).on('click', '.aips-tab-link', this.switchTab.bind(this));
			$(document).on('input', '#aips-content-component-search', this.onSearch.bind(this));
			$(document).on('click', '#aips-content-component-search-clear', this.clearSearch.bind(this));
			$(document).on('click', '#aips-content-component-modal .aips-modal-close', this.closeModal.bind(this));
			$(document).on('click', '#aips-content-component-modal', this.onOverlayClick.bind(this));
			$(document).on('click', '#aips-add-content-component-rule', this.addRuleRow.bind(this));
			$(document).on('click', '.aips-remove-content-component-rule', this.removeRuleRow.bind(this));
			$(document).on('input', '#aips-content-component-content', this.renderPreviewFromInput.bind(this));
			$(document).on('click', '#aips-content-component-run-qa', this.runQaValidation.bind(this));
		},

		/**
		 * Render screen.
		 *
		 * @return {void}
		 */
		render: function () {
			this.renderStats();
			this.renderTable();
			this.renderFooterCount();
		},

		/**
		 * Render stat cards.
		 *
		 * @return {void}
		 */
		renderStats: function () {
			$('#aips-cc-stat-total').text(this.counts.total || 0);
			$('#aips-cc-stat-active').text(this.counts.active || 0);
			$('#aips-cc-stat-inactive').text(this.counts.inactive || 0);
			$('#aips-cc-stat-needs-review').text(this.counts.needs_review || 0);
		},

		/**
		 * Render table for current filters.
		 *
		 * @return {void}
		 */
		renderTable: function () {
			var filtered = this.getFilteredComponents();
			if (filtered.length === 0) {
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
			var i;
			for (i = 0; i < filtered.length; i++) {
				rows += this.renderRow(filtered[i]);
			}

			$('#aips-content-components-content').html(
				AIPS.Templates.renderRaw('aips-tmpl-content-components-table', {
					titleLabel: aipsContentComponentsL10n.tableTitle,
					typeLabel: aipsContentComponentsL10n.tableType,
					statusLabel: aipsContentComponentsL10n.tableStatus,
					qaLabel: aipsContentComponentsL10n.tableQa,
					updatedLabel: aipsContentComponentsL10n.tableUpdated,
					actionsLabel: aipsContentComponentsL10n.tableActions,
					rows: rows
				})
			);
		},

		/**
		 * Render one table row.
		 *
		 * @param {Object} component Component payload.
		 * @return {string}
		 */
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

			return AIPS.Templates.renderRaw('aips-tmpl-content-component-row', {
				id: component.id,
				title: AIPS.Templates.escape(component.title),
				description: AIPS.Templates.escape(description),
				componentType: AIPS.Templates.escape(this.getTypeLabel(component.component_type)),
				statusBadge: statusBadge,
				qaBadge: qaBadge,
				updatedAt: AIPS.DateTime.formatDate(component.updated_at),
				editLabel: aipsContentComponentsL10n.edit,
				deleteLabel: aipsContentComponentsL10n.deleteLabel,
				toggleLabel: component.is_active === 1 ? aipsContentComponentsL10n.deactivate : aipsContentComponentsL10n.activate,
				isActive: component.is_active
			});
		},

		/**
		 * Render footer count.
		 *
		 * @return {void}
		 */
		renderFooterCount: function () {
			var total = this.getFilteredComponents().length;
			var label = total === 1 ? aipsContentComponentsL10n.componentSingular : aipsContentComponentsL10n.componentPlural;
			$('#aips-content-components-result-count').text(total + ' ' + label);
		},

		/**
		 * Get filtered component list.
		 *
		 * @return {Array}
		 */
		getFilteredComponents: function () {
			var term = this.searchTerm;
			return this.components.filter(function (component) {
				var tabMatch = true;
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

				if (!term) {
					return true;
				}

				var haystack = ((component.title || '') + ' ' + (component.description || '') + ' ' + (component.component_type || '')).toLowerCase();
				return haystack.indexOf(term) !== -1;
			});
		},

		/**
		 * Switch active tab.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		switchTab: function (e) {
			e.preventDefault();
			var tab = $(e.currentTarget).data('tab');
			this.currentTab = tab || 'all';
			$('.aips-tab-link').removeClass('active');
			$(e.currentTarget).addClass('active');
			this.renderTable();
			this.renderFooterCount();
		},

		/**
		 * Handle search input.
		 *
		 * @param {Event} e Input event.
		 * @return {void}
		 */
		onSearch: function (e) {
			this.searchTerm = ($(e.currentTarget).val() || '').toLowerCase().trim();
			$('#aips-content-component-search-clear').toggle(this.searchTerm.length > 0);
			this.renderTable();
			this.renderFooterCount();
		},

		/**
		 * Clear search input.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		clearSearch: function (e) {
			e.preventDefault();
			$('#aips-content-component-search').val('');
			this.searchTerm = '';
			$('#aips-content-component-search-clear').hide();
			this.renderTable();
			this.renderFooterCount();
		},

		/**
		 * Open add modal.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		openAddModal: function (e) {
			e.preventDefault();
			this.currentComponent = null;
			this.rules = [];
			this.resetModalForm();
			$('#aips-content-component-modal-title').text(aipsContentComponentsL10n.addTitle);
			$('#aips-content-component-rules-wrap').hide();
			$('#aips-content-component-modal').show();
			$('#aips-content-component-title').trigger('focus');
		},

		/**
		 * Open edit modal.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		openEditModal: function (e) {
			e.preventDefault();
			var componentId = parseInt($(e.currentTarget).data('id'), 10);
			var component = this.findComponent(componentId);
			if (!component) {
				return;
			}

			this.currentComponent = component;
			this.rules = Array.isArray(component.rules && component.rules.conditions) ? component.rules.conditions.slice() : [];

			$('#aips-content-component-id').val(component.id);
			$('#aips-content-component-title').val(component.title || '');
			$('#aips-content-component-description').val(component.description || '');
			$('#aips-content-component-type').val(component.component_type || 'custom');
			$('#aips-content-component-content').val(component.content || '');
			$('#aips-content-component-is-active').prop('checked', component.is_active === 1);
			$('#aips-content-component-rules-logic').val(component.rules && component.rules.logic ? component.rules.logic : 'and');
			$('#aips-content-component-rules-action').val(component.rules && component.rules.action ? component.rules.action : 'add_at_end');
			$('#aips-content-component-rules-wrap').show();
			$('#aips-content-component-modal-title').text(aipsContentComponentsL10n.editTitle);
			this.renderRulesRows();
			this.updateQaDisplay(component.qa_status || 'untested', component.qa_notes || '');
			this.renderPreview(component.content || '');
			$('#aips-content-component-modal').show();
		},

		/**
		 * Close modal.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		closeModal: function (e) {
			e.preventDefault();
			$('#aips-content-component-modal').hide();
		},

		/**
		 * Handle modal backdrop click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onOverlayClick: function (e) {
			if ($(e.target).is('#aips-content-component-modal')) {
				$('#aips-content-component-modal').hide();
			}
		},

		/**
		 * Save current component.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		saveComponent: function (e) {
			e.preventDefault();

			var title = ($('#aips-content-component-title').val() || '').trim();
			if (!title) {
				AIPS.Utilities.showToast(aipsContentComponentsL10n.titleRequired, 'error');
				$('#aips-content-component-title').trigger('focus');
				return;
			}

			var isCreate = parseInt($('#aips-content-component-id').val(), 10) === 0;
			var payload = this.getModalPayload();
			var $btn = $('#aips-save-content-component-btn');

			AIPS.Utilities.setButtonLoading($btn, aipsContentComponentsL10n.saving);

			$.post(aipsAjax.ajaxUrl, payload)
				.done(function (response) {
					AIPS.Utilities.resetButton($btn);
					if (!response.success) {
						AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : aipsContentComponentsL10n.saveError, 'error');
						return;
					}

					AIPS.Utilities.showToast(response.data.message || aipsContentComponentsL10n.saved, 'success');
					AIPS.ContentComponents.upsertComponent(response.data.component);
					AIPS.ContentComponents.counts = response.data.counts || AIPS.ContentComponents.counts;
					AIPS.ContentComponents.render();

					if (isCreate) {
						AIPS.ContentComponents.currentComponent = response.data.component;
						AIPS.ContentComponents.openEditModalById(response.data.component.id);
						return;
					}

					$('#aips-content-component-modal').hide();
				})
				.fail(function () {
					AIPS.Utilities.resetButton($btn);
					AIPS.Utilities.showToast(aipsContentComponentsL10n.saveError, 'error');
				});
		},

		/**
		 * Delete component.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
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

		/**
		 * Toggle component active state.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
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

		/**
		 * Add a rules row.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		addRuleRow: function (e) {
			e.preventDefault();
			this.rules.push({
				field: 'category',
				operator: 'is',
				values: []
			});
			this.renderRulesRows();
		},

		/**
		 * Remove a rules row.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		removeRuleRow: function (e) {
			e.preventDefault();
			var index = parseInt($(e.currentTarget).closest('.aips-content-component-rule-row').data('index'), 10);
			if (!Number.isInteger(index)) {
				return;
			}
			this.rules.splice(index, 1);
			this.renderRulesRows();
		},

		/**
		 * Render all rules rows.
		 *
		 * @return {void}
		 */
		renderRulesRows: function () {
			var html = '';
			var i;

			for (i = 0; i < this.rules.length; i++) {
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

		/**
		 * Run QA check for modal data.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
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
					AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : aipsContentComponentsL10n.qaError, 'error');
					return;
				}
				AIPS.ContentComponents.updateQaDisplay(response.data.qa_status, response.data.qa_notes || '');
				AIPS.Utilities.showToast(aipsContentComponentsL10n.qaDone, 'success');
			}).fail(function () {
				AIPS.Utilities.showToast(aipsContentComponentsL10n.qaError, 'error');
			});
		},

		/**
		 * Render preview from textarea input.
		 *
		 * @return {void}
		 */
		renderPreviewFromInput: function () {
			this.renderPreview($('#aips-content-component-content').val() || '');
		},

		/**
		 * Render preview panel.
		 *
		 * @param {string} content Content HTML/text.
		 * @return {void}
		 */
		renderPreview: function (content) {
			if (!content || !content.trim()) {
				$('#aips-content-component-preview').html('<em>' + AIPS.Templates.escape(aipsContentComponentsL10n.previewEmpty) + '</em>');
				return;
			}
			$('#aips-content-component-preview').html(content);
		},

		/**
		 * Update QA display badge and notes.
		 *
		 * @param {string} status QA status.
		 * @param {string} notes QA notes.
		 * @return {void}
		 */
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

		/**
		 * Build modal payload.
		 *
		 * @return {Object}
		 */
		getModalPayload: function () {
			var rulesPayload = this.collectRulesFromUI();
			return {
				action: 'aips_save_content_component',
				nonce: aipsAjax.nonce,
				component_id: parseInt($('#aips-content-component-id').val(), 10) || 0,
				title: ($('#aips-content-component-title').val() || '').trim(),
				description: ($('#aips-content-component-description').val() || '').trim(),
				component_type: $('#aips-content-component-type').val() || 'custom',
				content: $('#aips-content-component-content').val() || '',
				is_active: $('#aips-content-component-is-active').is(':checked') ? 1 : 0,
				rules: JSON.stringify(rulesPayload)
			};
		},

		/**
		 * Collect rules from UI controls.
		 *
		 * @return {Object}
		 */
		collectRulesFromUI: function () {
			var conditions = [];
			$('#aips-content-component-rules-list .aips-content-component-rule-row').each(function () {
				var valuesRaw = ($(this).find('.aips-cc-rule-values').val() || '').trim();
				var values = valuesRaw ? valuesRaw.split(',').map(function (value) { return value.trim(); }).filter(Boolean) : [];
				conditions.push({
					field: $(this).find('.aips-cc-rule-field').val() || 'category',
					operator: $(this).find('.aips-cc-rule-operator').val() || 'is',
					values: values
				});
			});

			return {
				logic: this.getRuleLogic(),
				action: $('#aips-content-component-rules-action').val() || 'add_at_end',
				conditions: conditions
			};
		},

		/**
		 * Reset modal fields.
		 *
		 * @return {void}
		 */
		resetModalForm: function () {
			$('#aips-content-component-id').val(0);
			$('#aips-content-component-title').val('');
			$('#aips-content-component-description').val('');
			$('#aips-content-component-type').val('cta');
			$('#aips-content-component-content').val('');
			$('#aips-content-component-is-active').prop('checked', true);
			$('#aips-content-component-rules-logic').val('and');
			$('#aips-content-component-rules-list').empty();
			this.populateActionOptions();
			this.updateQaDisplay('untested', '');
			this.renderPreview('');
		},

		/**
		 * Populate action dropdown.
		 *
		 * @return {void}
		 */
		populateActionOptions: function () {
			var optionsHtml = this.buildSelectOptions(aipsContentComponentsConfig.actions, 'add_at_end');
			$('#aips-content-component-rules-action').html(optionsHtml);
		},

		/**
		 * Build select option HTML.
		 *
		 * @param {Array} options Option list.
		 * @param {string} selected Selected value.
		 * @return {string}
		 */
		buildSelectOptions: function (options, selected) {
			var html = '';
			var i;
			for (i = 0; i < options.length; i++) {
				var item = options[i];
				var isSelected = item.value === selected ? ' selected' : '';
				html += '<option value="' + AIPS.Templates.escape(item.value) + '"' + isSelected + '>' + AIPS.Templates.escape(item.label) + '</option>';
			}
			return html;
		},

		/**
		 * Open edit modal directly by ID.
		 *
		 * @param {number} id Component ID.
		 * @return {void}
		 */
		openEditModalById: function (id) {
			var component = this.findComponent(id);
			if (!component) {
				return;
			}
			this.currentComponent = component;
			this.rules = Array.isArray(component.rules && component.rules.conditions) ? component.rules.conditions.slice() : [];
			$('#aips-content-component-id').val(component.id);
			$('#aips-content-component-title').val(component.title || '');
			$('#aips-content-component-description').val(component.description || '');
			$('#aips-content-component-type').val(component.component_type || 'custom');
			$('#aips-content-component-content').val(component.content || '');
			$('#aips-content-component-is-active').prop('checked', component.is_active === 1);
			$('#aips-content-component-rules-wrap').show();
			$('#aips-content-component-modal-title').text(aipsContentComponentsL10n.editTitle);
			this.populateActionOptions();
			$('#aips-content-component-rules-logic').val(component.rules && component.rules.logic ? component.rules.logic : 'and');
			$('#aips-content-component-rules-action').val(component.rules && component.rules.action ? component.rules.action : 'add_at_end');
			this.renderRulesRows();
			this.updateQaDisplay(component.qa_status || 'untested', component.qa_notes || '');
			this.renderPreview(component.content || '');
			$('#aips-content-component-modal').show();
		},

		/**
		 * Get logic value.
		 *
		 * @return {string}
		 */
		getRuleLogic: function () {
			return $('#aips-content-component-rules-logic').val() === 'or' ? 'or' : 'and';
		},

		/**
		 * Find component by id.
		 *
		 * @param {number} id Component id.
		 * @return {Object|null}
		 */
		findComponent: function (id) {
			var i;
			for (i = 0; i < this.components.length; i++) {
				if (parseInt(this.components[i].id, 10) === parseInt(id, 10)) {
					return this.components[i];
				}
			}
			return null;
		},

		/**
		 * Upsert component in local cache.
		 *
		 * @param {Object} component Component payload.
		 * @return {void}
		 */
		upsertComponent: function (component) {
			var existingIndex = -1;
			var i;
			for (i = 0; i < this.components.length; i++) {
				if (parseInt(this.components[i].id, 10) === parseInt(component.id, 10)) {
					existingIndex = i;
					break;
				}
			}

			if (existingIndex > -1) {
				this.components[existingIndex] = component;
			} else {
				this.components.unshift(component);
			}
		},

		/**
		 * Remove component from local cache.
		 *
		 * @param {number} id Component ID.
		 * @return {void}
		 */
		removeComponent: function (id) {
			this.components = this.components.filter(function (component) {
				return parseInt(component.id, 10) !== parseInt(id, 10);
			});
		},

		/**
		 * Get label for component type.
		 *
		 * @param {string} type Type key.
		 * @return {string}
		 */
		getTypeLabel: function (type) {
			var labels = aipsContentComponentsConfig.typeLabels || {};
			return labels[type] || type || 'custom';
		},

		/**
		 * Get label for QA status.
		 *
		 * @param {string} status QA status.
		 * @return {string}
		 */
		getQaLabel: function (status) {
			if (status === 'passed') {
				return aipsContentComponentsL10n.qaPassed;
			}
			if (status === 'needs_review') {
				return aipsContentComponentsL10n.qaNeedsReview;
			}
			return aipsContentComponentsL10n.qaUntested;
		}
	};

	$(document).ready(function () {
		if ($('#aips-content-components-panel').length) {
			AIPS.ContentComponents.populateActionOptions();
			AIPS.ContentComponents.init();
		}
	});
})(jQuery);
