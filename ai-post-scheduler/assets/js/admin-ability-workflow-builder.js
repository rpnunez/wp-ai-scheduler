(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	var L10n = window.aipsAbilityWorkflowBuilderL10n || {};

	/**
	 * Input values are stored typed (text, number, true/false, JSON list/object),
	 * so abilities whose schema expects a number, boolean, array or object get
	 * the right type instead of a string.
	 */
	var InputTypes = {
		/**
		 * Which type selector option and text value represent a stored value.
		 *
		 * @param {*} value Stored input value.
		 * @return {{type: string, text: string}}
		 */
		describe: function(value) {
			if (typeof value === 'number') {
				return { type: 'number', text: String(value) };
			}

			if (typeof value === 'boolean') {
				return { type: 'boolean', text: value ? 'true' : 'false' };
			}

			if (value !== null && typeof value === 'object') {
				return { type: 'json', text: JSON.stringify(value) };
			}

			return { type: 'string', text: value === null || value === undefined ? '' : String(value) };
		},

		/**
		 * Turn the text a user typed into the stored value for a type.
		 * A reference such as {{steps.a.output.count}} stays a string: the
		 * executor substitutes the referenced value, keeping its type, at run time.
		 *
		 * @param {string} type Selected type.
		 * @param {string} text Raw text.
		 * @return {{value: *}|{error: string}}
		 */
		convert: function(type, text) {
			var trimmed = String(text).trim();

			if (type === 'string' || trimmed.indexOf('{{') !== -1) {
				return { value: text };
			}

			if (type === 'number') {
				var number = Number(trimmed);

				if (trimmed === '' || !isFinite(number)) {
					return { error: L10n.invalidNumber };
				}

				return { value: number };
			}

			if (type === 'boolean') {
				if (trimmed.toLowerCase() === 'true') {
					return { value: true };
				}

				if (trimmed.toLowerCase() === 'false') {
					return { value: false };
				}

				return { error: L10n.invalidBoolean };
			}

			try {
				return { value: JSON.parse(trimmed) };
			} catch (e) {
				return { error: L10n.invalidJson };
			}
		}
	};

	AIPS.AbilityWorkflowBuilder = {
		InputTypes: InputTypes,
		workflowId: 0,
		steps: [],
		abilities: [],
		abilitiesByName: {},
		editingStepIndex: -1,
		runsLoaded: false,
		dirty: false,

		init() {
			this.workflowId = window.aipsAbilityWorkflowId || 0;

			if (!this.workflowId) {
				return;
			}

			this.bindEvents();
			this.loadAbilities();
			this.loadWorkflow();
		},

		bindEvents() {
			$(document).on('click', '.aips-tab-link', this.switchTab.bind(this));

			$(document).on('click', '#aips-add-step-btn', this.openAddStepModal.bind(this));
			$(document).on('click', '.aips-edit-step', this.openEditStepModal.bind(this));
			$(document).on('click', '.aips-remove-step', this.removeStep.bind(this));
			$(document).on('click', '.aips-move-step-up', this.moveStepUp.bind(this));
			$(document).on('click', '.aips-move-step-down', this.moveStepDown.bind(this));

			$(document).on('click', '#aips-step-modal .aips-modal-close', this.closeStepModal.bind(this));
			$(document).on('click', '#aips-save-step-btn', this.saveStep.bind(this));
			$(document).on('change', '#aips-step-ability', this.onAbilityChange.bind(this));

			$(document).on('click', '#aips-add-input-map-row-btn', this.addInputMapRow.bind(this));
			$(document).on('click', '.aips-remove-input-map-row', this.removeInputMapRow.bind(this));

			$(document).on('click', '#aips-add-condition-rule-btn', this.addConditionRuleRow.bind(this));
			$(document).on('click', '.aips-remove-condition-rule', this.removeConditionRuleRow.bind(this));

			$(document).on('click', '#aips-save-steps-btn', this.saveSteps.bind(this));
			$(document).on('click', '#aips-builder-run-now-btn', this.onRunNowClick.bind(this));

			$(document).on('click', '#aips-run-detail-modal .aips-modal-close', function() {
				$('#aips-run-detail-modal').hide();
			});
			$(document).on('click', '.aips-view-run', this.viewRun.bind(this));

			$(window).on('beforeunload', this.onBeforeUnload.bind(this));
		},

		// -------------------------------------------------------------------
		// Unsaved changes
		// -------------------------------------------------------------------

		setDirty(isDirty) {
			this.dirty = !!isDirty;
			$('#aips-builder-dirty').prop('hidden', !this.dirty);
		},

		onBeforeUnload(e) {
			if (!this.dirty) {
				return undefined;
			}

			e.preventDefault();
			e.returnValue = '';

			return '';
		},

		switchTab(e) {
			e.preventDefault();
			var tab = $(e.currentTarget).data('tab');

			$('.aips-tab-link').removeClass('active');
			$(e.currentTarget).addClass('active');

			$('#aips-builder-tab-steps').toggle(tab === 'steps');
			$('#aips-builder-tab-runs').toggle(tab === 'runs');

			if (tab === 'runs' && !this.runsLoaded) {
				this.loadRuns();
			}
		},

		// -------------------------------------------------------------------
		// Workflow + steps loading
		// -------------------------------------------------------------------

		loadWorkflow() {
			var self = this;

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_get_ability_workflow',
				nonce: aipsAjax.nonce,
				workflow_id: this.workflowId
			}, function(response) {
				if (!response.success) {
					AIPS.Utilities.showToast((response.data && response.data.message) || L10n.loadWorkflowFailed, 'error');
					return;
				}

				$('#aips-builder-workflow-name').text(response.data.workflow.name);
				self.steps = response.data.steps || [];
				self.setDirty(false);
				self.renderSteps();
			});
		},

		loadAbilities() {
			var self = this;

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_list_abilities',
				nonce: aipsAjax.nonce
			}, function(response) {
				if (!response.success) {
					AIPS.Utilities.showToast(L10n.loadAbilitiesFailed, 'error');
					return;
				}

				self.abilities = response.data.abilities || [];
				self.abilitiesByName = {};
				self.abilities.forEach(function(ability) {
					self.abilitiesByName[ability.name] = ability;
				});

				// The step rows show per-ability notes, which need the catalog.
				self.renderSteps();
			});
		},

		/**
		 * A short note about an ability for the step list.
		 *
		 * @param {string} abilityName Ability slug.
		 * @return {string}
		 */
		abilityNote(abilityName) {
			var ability = this.abilitiesByName[abilityName];

			if (!Object.keys(this.abilitiesByName).length) {
				return '';
			}

			if (!ability) {
				return L10n.unknownAbilityNote;
			}

			if (ability.is_available === false) {
				return L10n.unavailableNote;
			}

			return ability.requires_approval ? L10n.requiresApprovalNote : '';
		},

		renderSteps() {
			var self = this;
			var $body = $('#aips-steps-table-body');
			$body.empty();

			this.steps.forEach(function(step, index) {
				var html = AIPS.Templates.render('aips-tmpl-step-row', {
					index: index,
					step_key: step.step_key,
					name: step.name || '',
					ability_name: step.ability_name,
					ability_note: self.abilityNote(step.ability_name),
					output_alias: step.output_alias || ''
				});
				$body.append(html);
			});

			$('#aips-steps-empty').toggle(this.steps.length === 0);
		},

		// -------------------------------------------------------------------
		// Step modal
		// -------------------------------------------------------------------

		populateAbilitySelect(selectedValue) {
			var $select = $('#aips-step-ability');
			$select.empty();

			var names = this.abilities.map(function(ability) {
				return ability.name;
			});

			// Keep a saved ability selectable even if it is no longer registered.
			if (selectedValue && names.indexOf(selectedValue) === -1) {
				$select.append($('<option></option>').val(selectedValue).text(selectedValue + ' ' + L10n.unknownAbilityNote));
			}

			this.abilities.forEach(function(ability) {
				var label = ability.label + ' (' + ability.provider + ')';

				if (ability.is_available === false) {
					label += ' - ' + L10n.unavailableShort;
				} else if (ability.requires_approval) {
					label += ' - ' + L10n.requiresApprovalShort;
				}

				$select.append($('<option></option>').val(ability.name).text(label));
			});

			if (selectedValue) {
				$select.val(selectedValue);
			}

			this.onAbilityChange();
		},

		onAbilityChange() {
			var ability = this.abilitiesByName[$('#aips-step-ability').val()];

			$('#aips-step-ability-description').text(ability && ability.description ? ability.description : '');
			$('#aips-step-ability-hint').text(this.schemaHint(ability));
		},

		/**
		 * Describe the input fields an ability declares, so the input map can be
		 * filled in without guessing.
		 *
		 * @param {Object|undefined} ability Catalog entry.
		 * @return {string}
		 */
		schemaHint(ability) {
			var schema = ability && ability.input_schema;

			if (!schema || !schema.properties || !Object.keys(schema.properties).length) {
				return ability ? L10n.noInputFields : '';
			}

			var required = Array.isArray(schema.required) ? schema.required : [];
			var fields = Object.keys(schema.properties).map(function(name) {
				var property = schema.properties[name] || {};
				var type = Array.isArray(property.type) ? property.type.join('|') : (property.type || '');
				var detail = [type, required.indexOf(name) !== -1 ? L10n.requiredField : ''].filter(Boolean).join(', ');

				return detail ? name + ' (' + detail + ')' : name;
			});

			return L10n.inputFieldsPrefix + ' ' + fields.join(', ');
		},

		populateDependsOnSelect(selectedValues, excludeIndex) {
			var $select = $('#aips-step-depends-on');
			$select.empty();
			selectedValues = selectedValues || [];

			this.steps.forEach(function(step, index) {
				if (index === excludeIndex) {
					return;
				}

				var $option = $('<option></option>').val(step.step_key).text(step.step_key);
				if (selectedValues.indexOf(step.step_key) !== -1) {
					$option.prop('selected', true);
				}
				$select.append($option);
			});
		},

		openAddStepModal() {
			this.editingStepIndex = -1;
			$('#aips-step-modal-title').text(L10n.addStep);
			this.resetStepForm();
			this.populateAbilitySelect('');
			this.populateDependsOnSelect([], -1);
			this.openInputMapRows({});
			this.openConditionRules({});
			$('#aips-step-modal').css('display', 'flex');
		},

		openEditStepModal(e) {
			var index = parseInt($(e.currentTarget).data('index'), 10);
			var step = this.steps[index];

			if (!step) {
				return;
			}

			this.editingStepIndex = index;
			$('#aips-step-modal-title').text(L10n.editStep);
			$('#aips-step-index').val(index);
			$('#aips-step-key').val(step.step_key);
			$('#aips-step-name').val(step.name || '');
			$('#aips-step-output-alias').val(step.output_alias || '');
			$('#aips-step-on-success').val((step.on_success && step.on_success.strategy) || 'continue');
			$('#aips-step-on-failure').val((step.on_failure && step.on_failure.strategy) || 'stop');
			$('#aips-step-retry-attempts').val((step.retry_policy && step.retry_policy.attempts) || 0);
			$('#aips-step-retry-backoff').val((step.retry_policy && step.retry_policy.backoff_seconds) || 5);

			this.populateAbilitySelect(step.ability_name);
			this.populateDependsOnSelect(step.depends_on || [], index);
			this.openInputMapRows(step.input_map || {});
			this.openConditionRules(step.condition_tree || {});
			this.warnIfStepHasNestedConditions(step);

			$('#aips-step-modal').css('display', 'flex');
		},

		/**
		 * The condition editor only renders flat rules; nested AND/OR groups are
		 * skipped on load and would be dropped on save. Warn up front so an admin
		 * editing a step built through the API is not surprised by a silent change.
		 *
		 * @param {Object} step Step being opened for editing.
		 */
		warnIfStepHasNestedConditions(step) {
			var hasNestedCondition = !!(step.condition_tree && Array.isArray(step.condition_tree.rules) &&
				step.condition_tree.rules.some(function(rule) { return !!rule.rules; }));

			if (hasNestedCondition) {
				AIPS.Utilities.showToast(L10n.nestedConditionWarning, 'warning');
			}
		},

		resetStepForm() {
			$('#aips-step-form')[0].reset();
			$('#aips-step-index').val(-1);
		},

		closeStepModal() {
			$('#aips-step-modal').hide();
		},

		saveStep() {
			var stepKey = $('#aips-step-key').val().trim();
			var abilityName = $('#aips-step-ability').val();

			if (!stepKey) {
				AIPS.Utilities.showToast(L10n.stepKeyRequired, 'error');
				return;
			}

			if (!abilityName) {
				AIPS.Utilities.showToast(L10n.abilityRequired, 'error');
				return;
			}

			var inputMap = this.collectInputMap();

			if (inputMap === null) {
				return;
			}

			var step = {
				step_key: stepKey,
				name: $('#aips-step-name').val(),
				ability_name: abilityName,
				depends_on: $('#aips-step-depends-on').val() || [],
				output_alias: $('#aips-step-output-alias').val(),
				input_map: inputMap,
				condition_tree: this.collectConditionTree(),
				on_success: { strategy: $('#aips-step-on-success').val() },
				on_failure: { strategy: $('#aips-step-on-failure').val() },
				retry_policy: {
					attempts: parseInt($('#aips-step-retry-attempts').val(), 10) || 0,
					backoff_seconds: parseInt($('#aips-step-retry-backoff').val(), 10) || 5
				}
			};

			if (this.editingStepIndex === -1) {
				this.steps.push(step);
			} else {
				this.steps[this.editingStepIndex] = step;
			}

			this.setDirty(true);
			this.renderSteps();
			this.closeStepModal();
		},

		removeStep(e) {
			var index = parseInt($(e.currentTarget).data('index'), 10);
			var self = this;

			AIPS.Utilities.confirm(
				L10n.removeStepConfirm,
				L10n.removeStepHeading,
				[
					{ label: L10n.cancel, className: 'aips-btn aips-btn-secondary' },
					{ label: L10n.remove, className: 'aips-btn aips-btn-danger', action: function() {
						self.steps.splice(index, 1);
						self.setDirty(true);
						self.renderSteps();
					} }
				]
			);
		},

		moveStepUp(e) {
			var index = parseInt($(e.currentTarget).data('index'), 10);
			if (index <= 0) {
				return;
			}
			var tmp = this.steps[index - 1];
			this.steps[index - 1] = this.steps[index];
			this.steps[index] = tmp;
			this.setDirty(true);
			this.renderSteps();
		},

		moveStepDown(e) {
			var index = parseInt($(e.currentTarget).data('index'), 10);
			if (index >= this.steps.length - 1) {
				return;
			}
			var tmp = this.steps[index + 1];
			this.steps[index + 1] = this.steps[index];
			this.steps[index] = tmp;
			this.setDirty(true);
			this.renderSteps();
		},

		// -------------------------------------------------------------------
		// Input map row builder
		// -------------------------------------------------------------------

		openInputMapRows(inputMap) {
			var $body = $('#aips-input-map-table-body');
			$body.empty();

			var rowIndex = 0;
			Object.keys(inputMap).forEach(function(field) {
				var described = InputTypes.describe(inputMap[field]);
				var $row = $(AIPS.Templates.render('aips-tmpl-input-map-row', {
					row_index: rowIndex++,
					field: field,
					value: described.text
				}));

				$row.find('.aips-input-map-type').val(described.type);
				$body.append($row);
			});
		},

		addInputMapRow() {
			var $body = $('#aips-input-map-table-body');
			var nextIndex = $body.find('tr').length;
			var html = AIPS.Templates.render('aips-tmpl-input-map-row', { row_index: nextIndex, field: '', value: '' });
			$body.append(html);
		},

		removeInputMapRow(e) {
			$(e.currentTarget).closest('tr').remove();
		},

		/**
		 * Read the input map, converting each value to its selected type.
		 *
		 * @return {Object|null} The map, or null (after a toast) when a value does not match its type.
		 */
		collectInputMap() {
			var map = {};
			var failed = false;

			$('#aips-input-map-table-body tr').each(function() {
				var field = $(this).find('.aips-input-map-field').val();

				if (!field) {
					return;
				}

				var converted = InputTypes.convert($(this).find('.aips-input-map-type').val(), $(this).find('.aips-input-map-value').val());

				if (converted.error) {
					AIPS.Utilities.showToast(field + ': ' + converted.error, 'error');
					failed = true;
					return false;
				}

				map[field] = converted.value;
			});

			return failed ? null : map;
		},

		// -------------------------------------------------------------------
		// Condition rule builder
		// -------------------------------------------------------------------

		openConditionRules(conditionTree) {
			$('#aips-condition-operator').val(conditionTree.operator || 'AND');

			var $body = $('#aips-condition-rules-table-body');
			$body.empty();

			var rules = Array.isArray(conditionTree.rules) ? conditionTree.rules : [];
			var rowIndex = 0;

			rules.forEach(function(rule) {
				if (rule.rules) {
					// Nested groups are not editable in this builder; skip.
					return;
				}

				var html = AIPS.Templates.render('aips-tmpl-condition-rule-row', {
					row_index: rowIndex++,
					left: rule.left || '',
					right: rule.right === undefined || rule.right === null ? '' : String(rule.right)
				});
				var $row = $(html);
				$row.find('.aips-rule-operator').val(rule.operator || 'equals');
				$body.append($row);
			});
		},

		addConditionRuleRow() {
			var $body = $('#aips-condition-rules-table-body');
			var nextIndex = $body.find('tr').length;
			var html = AIPS.Templates.render('aips-tmpl-condition-rule-row', { row_index: nextIndex, left: '', right: '' });
			$body.append(html);
		},

		removeConditionRuleRow(e) {
			$(e.currentTarget).closest('tr').remove();
		},

		collectConditionTree() {
			var rules = [];

			$('#aips-condition-rules-table-body tr').each(function() {
				var left = $(this).find('.aips-rule-left').val();
				var operator = $(this).find('.aips-rule-operator').val();
				var right = $(this).find('.aips-rule-right').val();

				if (left) {
					rules.push({ left: left, operator: operator, right: right });
				}
			});

			if (!rules.length) {
				return {};
			}

			return { operator: $('#aips-condition-operator').val(), rules: rules };
		},

		// -------------------------------------------------------------------
		// Save steps / run now
		// -------------------------------------------------------------------

		saveSteps() {
			var self = this;
			var payload = this.steps.map(function(step, index) {
				return $.extend({}, step, { position: index });
			});

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_save_ability_workflow_steps',
				nonce: aipsAjax.nonce,
				workflow_id: this.workflowId,
				steps: JSON.stringify(payload)
			}, function(response) {
				if (!response.success) {
					var message = (response.data && response.data.message) || L10n.saveFailed;
					if (response.data && response.data.errors && response.data.errors.length) {
						message += ' ' + response.data.errors.join(' ');
					}
					AIPS.Utilities.showToast(message, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message || L10n.saveSteps, 'success');
				self.steps = response.data.steps || self.steps;
				self.setDirty(false);
				self.renderSteps();
			}).fail(function() {
				AIPS.Utilities.showToast(L10n.saveFailed, 'error');
			});
		},

		/**
		 * Run Now dispatches the last saved steps, not the edits on screen, so
		 * confirm before running when there are unsaved changes.
		 */
		onRunNowClick() {
			var self = this;

			if (!this.dirty) {
				this.runNow();
				return;
			}

			AIPS.Utilities.confirm(
				L10n.runUnsavedConfirm,
				L10n.runUnsavedHeading,
				[
					{ label: L10n.cancel, className: 'aips-btn aips-btn-secondary' },
					{ label: L10n.runSavedVersion, className: 'aips-btn aips-btn-primary', action: function() {
						self.runNow();
					} }
				]
			);
		},

		runNow() {
			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_run_ability_workflow_now',
				nonce: aipsAjax.nonce,
				workflow_id: this.workflowId
			}, function(response) {
				if (!response.success) {
					AIPS.Utilities.showToast((response.data && response.data.message) || L10n.runFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message, 'success');
				AIPS.AbilityWorkflowBuilder.runsLoaded = false;
			}).fail(function() {
				AIPS.Utilities.showToast(L10n.runFailed, 'error');
			});
		},

		// -------------------------------------------------------------------
		// Runs tab
		// -------------------------------------------------------------------

		loadRuns() {
			var self = this;

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_list_ability_workflow_runs',
				nonce: aipsAjax.nonce,
				workflow_id: this.workflowId,
				per_page: 50
			}, function(response) {
				if (!response.success) {
					AIPS.Utilities.showToast(L10n.loadRunsFailed, 'error');
					return;
				}

				self.runsLoaded = true;
				self.renderRuns(response.data.runs || []);
			});
		},

		renderRuns(runs) {
			var $body = $('#aips-runs-table-body');
			$body.empty();

			var statusMeta = {
				queued: 'aips-badge-neutral',
				running: 'aips-badge-warning',
				completed: 'aips-badge-success',
				failed: 'aips-badge-error',
				cancelled: 'aips-badge-neutral'
			};

			runs.forEach(function(run) {
				var html = AIPS.Templates.render('aips-tmpl-run-row', {
					id: run.id,
					status: run.status,
					status_badge_class: statusMeta[run.status] || 'aips-badge-neutral',
					started_display: run.started_at ? new Date(run.started_at * 1000).toLocaleString() : '',
					finished_display: run.finished_at ? new Date(run.finished_at * 1000).toLocaleString() : ''
				});
				$body.append(html);
			});

			$('#aips-runs-empty').toggle(runs.length === 0);
		},

		viewRun(e) {
			var runId = $(e.currentTarget).data('id');

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_get_ability_workflow_run',
				nonce: aipsAjax.nonce,
				run_id: runId
			}, function(response) {
				if (!response.success) {
					AIPS.Utilities.showToast(L10n.loadRunsFailed, 'error');
					return;
				}

				var $body = $('#aips-run-detail-table-body');
				$body.empty();

				var statusMeta = {
					pending: 'aips-badge-neutral',
					running: 'aips-badge-warning',
					completed: 'aips-badge-success',
					failed: 'aips-badge-error',
					skipped: 'aips-badge-neutral'
				};

				(response.data.step_runs || []).forEach(function(stepRun) {
					var payload = stepRun.status === 'failed' ? stepRun.error : stepRun.output_snapshot;

					var html = AIPS.Templates.render('aips-tmpl-step-run-row', {
						step_key: stepRun.step_key,
						ability_name: stepRun.ability_name,
						status: stepRun.status,
						status_badge_class: statusMeta[stepRun.status] || 'aips-badge-neutral',
						payload: JSON.stringify(payload || {}, null, 2)
					});
					$body.append(html);
				});

				$('#aips-run-detail-modal').css('display', 'flex');
			});
		}
	};

	$(document).ready(function() {
		AIPS.AbilityWorkflowBuilder.init();
	});
})(jQuery);
