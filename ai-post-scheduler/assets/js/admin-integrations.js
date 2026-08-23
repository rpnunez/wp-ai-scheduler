/**
 * Third-Party Plugin Integrations (bridge) admin UI.
 *
 * Lives inside the Template editor's "Third-Party Plugin Integrations"
 * disclosure panel: lets an admin pick a detected integration (e.g. ACF, or
 * native WordPress Custom Fields), pick one of its schema groups, and choose
 * which fields AIPS should generate content for — with an optional per-field
 * custom prompt.
 *
 * Two row styles are used depending on the selected integration's
 * supports_custom_field_keys flag:
 *  - false (e.g. ACF): a checkbox per fully-discoverable field
 *    (aips-tmpl-integration-field-row) — every field in the group is shown
 *    at once and the admin checks the ones to generate.
 *  - true (e.g. native WordPress meta): a growable repeater of "field slot"
 *    rows (aips-tmpl-integration-custom-field-row), each independently
 *    choosing its own field from a dropdown (with a "Custom meta key…"
 *    escape hatch), since the full set of possible fields isn't always
 *    listable or even discoverable.
 *
 * @since 2.10.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	// Shapes AIPS_Integration_Manager currently knows how to generate for.
	// Keep in sync with AIPS_Integration_Manager::$generatable_shapes.
	var GENERATABLE_SHAPES = ['short_text', 'long_text', 'html'];

	// A hand-typed field key must look like a real meta key. Client-side
	// mirror of AIPS_Integration_Native_Meta::is_valid_field_key() — the
	// server remains authoritative.
	var CUSTOM_FIELD_KEY_PATTERN = /^[a-zA-Z0-9_]+$/;

	AIPS.Integrations = {

		/** @type {Object<string, Object>} Saved mappings for the current template, keyed by field_key. */
		_savedMappings: {},

		/** @type {Array<Object>} Fields discovered for the currently-selected group. */
		_availableFields: [],

		/** @type {boolean} Whether the currently-selected integration allows hand-typed field keys. */
		_supportsCustomFieldKeys: false,

		init: function () {
			this.bindEvents();
		},

		bindEvents: function () {
			$(document).on('toggle', '.aips-integrations-panel details', this.onPanelToggle.bind(this));
			$(document).on('change', '#aips-integration-select', this.onIntegrationChange.bind(this));
			$(document).on('change', '#aips-integration-group-select', this.onGroupChange.bind(this));
			$(document).on('click', '#aips-save-integration-mappings', this.onSaveClick.bind(this));
			$(document).on('click', '#aips-add-custom-field-row', this.onAddCustomFieldRowClick.bind(this));
			$(document).on('change', '.aips-integration-field-key-select', this.onCustomFieldKeySelectChange.bind(this));
			$(document).on('click', '.aips-remove-custom-field-row', this.onRemoveCustomFieldRowClick.bind(this));
			$(document).on('change', 'input[name="aips-integration-field-visibility"]', this.onFieldVisibilityChange.bind(this));
		},

		onPanelToggle: function (e) {
			if (!e.target.open) {
				return;
			}

			var templateId = $('#template_id').val();
			var $panel = $('#aips-integrations-panel-body');

			if (!templateId) {
				$panel.find('.aips-integrations-unsaved-notice').show();
				$panel.find('.aips-integrations-config').hide();
				return;
			}

			$panel.find('.aips-integrations-unsaved-notice').hide();
			$panel.find('.aips-integrations-config').show();

			var self = this;
			this.loadSavedMappings(templateId, function () {
				self.loadIntegrations();
			});
		},

		loadIntegrations: function () {
			var $select = $('#aips-integration-select');

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_get_available_integrations',
				nonce: aipsAjax.nonce
			}, function (response) {
				if (!response.success) {
					AIPS.Utilities.showToast(response.data.message, 'error');
					return;
				}

				var integrations = response.data.integrations || [];
				$select.empty();

				if (!integrations.length) {
					$select.append($('<option>', { value: '', text: aipsIntegrationsL10n.noneAvailable }));
					return;
				}

				$select.append($('<option>', { value: '', text: aipsIntegrationsL10n.selectIntegration }));
				integrations.forEach(function (integration) {
					$select.append($('<option>', {
						value: integration.id,
						text: integration.label,
						'data-supports-custom-field-keys': integration.supports_custom_field_keys ? '1' : ''
					}));
				});

				// Re-select whatever was previously mapped for this template, if any.
				var previousIntegrationId = AIPS.Integrations._firstSavedValue('integration_id');
				if (previousIntegrationId) {
					$select.val(previousIntegrationId).trigger('change');
				}
			}).fail(function () {
				AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
			});
		},

		loadSavedMappings: function (templateId, callback) {
			var self = this;

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_get_field_mappings',
				nonce: aipsAjax.nonce,
				template_id: templateId
			}, function (response) {
				self._savedMappings = {};

				if (response.success && response.data.mappings) {
					response.data.mappings.forEach(function (mapping) {
						self._savedMappings[mapping.field_key] = mapping;
					});
				}

				if (typeof callback === 'function') {
					callback();
				}
			}).fail(function () {
				if (typeof callback === 'function') {
					callback();
				}
			});
		},

		_firstSavedValue: function (key) {
			for (var fieldKey in this._savedMappings) {
				if (Object.prototype.hasOwnProperty.call(this._savedMappings, fieldKey)) {
					return this._savedMappings[fieldKey][key];
				}
			}
			return '';
		},

		onIntegrationChange: function () {
			var $selectedOption = $('#aips-integration-select option:selected');
			var integrationId = $selectedOption.val();
			var $groupSelect = $('#aips-integration-group-select');

			this._supportsCustomFieldKeys = !!$selectedOption.data('supports-custom-field-keys');
			$('#aips-add-custom-field-row').toggle(this._supportsCustomFieldKeys);
			$('#aips-integration-field-visibility-toggle').toggle(this._supportsCustomFieldKeys);
			$('input[name="aips-integration-field-visibility"][value="standard"]').prop('checked', true);
			$('#aips-integration-fields-tbody').empty();

			if (!integrationId) {
				$groupSelect.prop('disabled', true).empty()
					.append($('<option>', { value: '', text: aipsIntegrationsL10n.selectIntegrationFirst }));
				return;
			}

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_get_integration_schema',
				nonce: aipsAjax.nonce,
				integration_id: integrationId,
				post_type: $('#template_post_type').val()
			}, function (response) {
				if (!response.success) {
					AIPS.Utilities.showToast(response.data.message, 'error');
					return;
				}

				var groups = response.data.field_groups || [];
				$groupSelect.prop('disabled', false).empty();

				if (!groups.length) {
					$groupSelect.append($('<option>', { value: '', text: aipsIntegrationsL10n.noGroupsFound }));
					return;
				}

				$groupSelect.append($('<option>', { value: '', text: aipsIntegrationsL10n.selectFieldGroup }));
				groups.forEach(function (group) {
					$groupSelect.append($('<option>', { value: group.id, text: group.label }));
				});

				var previousGroupId = AIPS.Integrations._firstSavedValue('source_key');
				if (previousGroupId) {
					$groupSelect.val(previousGroupId).trigger('change');
				} else if (groups.length === 1) {
					// Only one possible group (always true for native meta,
					// often true for a small ACF site) — skip making the admin
					// click through a dropdown with one option.
					$groupSelect.val(groups[0].id).trigger('change');
				}
			}).fail(function () {
				AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
			});
		},

		onGroupChange: function () {
			var integrationId = $('#aips-integration-select').val();
			var groupId = $('#aips-integration-group-select').val();
			var $tbody = $('#aips-integration-fields-tbody');
			var self = this;

			$tbody.empty();

			if (!integrationId || !groupId) {
				return;
			}

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_get_integration_schema',
				nonce: aipsAjax.nonce,
				integration_id: integrationId,
				group_id: groupId,
				include_protected: this._includeProtectedFields()
			}, function (response) {
				if (!response.success) {
					AIPS.Utilities.showToast(response.data.message, 'error');
					return;
				}

				self._availableFields = response.data.fields || [];

				if (self._supportsCustomFieldKeys) {
					self._renderCustomFieldRows(groupId);
					return;
				}

				var rows = self._availableFields.map(function (field) {
					return self._renderFieldRow(field);
				});

				$tbody.html(rows.join(''));
			}).fail(function () {
				AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
			});
		},

		/**
		 * Whether protected/internal ('_'-prefixed) meta keys should be
		 * included in the fields dropdown, per the "Show Advanced Custom
		 * Meta Fields" radio toggle. Defaults to false (hidden) when the
		 * toggle isn't present for the current integration.
		 *
		 * @return {boolean}
		 */
		_includeProtectedFields: function () {
			return $('input[name="aips-integration-field-visibility"]:checked').val() === 'advanced';
		},

		onFieldVisibilityChange: function () {
			this.onGroupChange();
		},

		_renderFieldRow: function (field) {
			var saved = this._savedMappings[field.key] || {};
			var supported = GENERATABLE_SHAPES.indexOf(field.shape) !== -1;
			var checked = supported && (saved.is_active === undefined ? false : !!parseInt(saved.is_active, 10));

			return AIPS.Templates.render('aips-tmpl-integration-field-row', {
				field_key: field.key,
				label: field.label,
				native_type: field.native_type,
				checked_attr: checked ? 'checked' : '',
				disabled_attr: supported ? '' : 'disabled',
				prompt_value: saved.custom_prompt || field.instructions || '',
				prompt_placeholder: aipsIntegrationsL10n.promptPlaceholder,
				unsupported_class: supported ? '' : 'aips-integration-field-unsupported',
				// Plain text only — AIPS.Templates.render() HTML-escapes every
				// value, so the note is a static <p> in the row template with
				// its text/visibility driven by these two tokens rather than
				// injected as a raw HTML string.
				unsupported_note_style: supported ? 'display:none;' : '',
				unsupported_note_text: supported ? '' : aipsIntegrationsL10n.unsupportedFieldType
			});
		},

		/**
		 * Render one repeater row per already-saved mapping for this group,
		 * falling back to a single empty starter row when there are none yet.
		 *
		 * @param {string} groupId Currently-selected group id (== source_key).
		 */
		_renderCustomFieldRows: function (groupId) {
			var $tbody = $('#aips-integration-fields-tbody');
			var savedForGroup = [];

			for (var fieldKey in this._savedMappings) {
				if (Object.prototype.hasOwnProperty.call(this._savedMappings, fieldKey)) {
					var mapping = this._savedMappings[fieldKey];
					if (mapping.source_key === groupId) {
						savedForGroup.push(mapping);
					}
				}
			}

			if (!savedForGroup.length) {
				$tbody.append(this._buildCustomFieldRow(null));
				return;
			}

			var self = this;
			savedForGroup.forEach(function (mapping) {
				$tbody.append(self._buildCustomFieldRow(mapping));
			});
		},

		/**
		 * Build one repeater "field slot" row as a detached jQuery element,
		 * populated from a saved mapping when provided.
		 *
		 * @param {Object|null} savedMapping Existing mapping row to restore, or null for an empty row.
		 * @return {jQuery}
		 */
		_buildCustomFieldRow: function (savedMapping) {
			var html = AIPS.Templates.render('aips-tmpl-integration-custom-field-row', {
				customKeyPlaceholder: aipsIntegrationsL10n.customKeyPlaceholder,
				shapeShortText: aipsIntegrationsL10n.shapeShortText,
				shapeLongText: aipsIntegrationsL10n.shapeLongText,
				shapeHtml: aipsIntegrationsL10n.shapeHtml,
				prompt_placeholder: aipsIntegrationsL10n.promptPlaceholder,
				removeLabel: aipsIntegrationsL10n.removeField
			});
			var $row = $($.trim(html));
			var $keySelect = $row.find('.aips-integration-field-key-select');

			$keySelect.append($('<option>', { value: '', text: aipsIntegrationsL10n.selectFieldPlaceholder }));
			this._availableFields.forEach(function (field) {
				$keySelect.append($('<option>', {
					value: field.key,
					text: field.label,
					'data-native-type': field.native_type,
					'data-label': field.label,
					'data-instructions': field.instructions || ''
				}));
			});
			$keySelect.append($('<option>', { value: '__custom__', text: aipsIntegrationsL10n.customFieldKeyOption }));

			if (savedMapping) {
				var matchesDiscoveredField = this._availableFields.some(function (field) {
					return field.key === savedMapping.field_key;
				});

				$row.find('.aips-integration-field-enabled').prop('checked', !!parseInt(savedMapping.is_active, 10));
				$row.find('.aips-integration-field-prompt').val(savedMapping.custom_prompt || '');

				if (matchesDiscoveredField) {
					$keySelect.val(savedMapping.field_key);
				} else {
					$keySelect.val('__custom__');
					$row.find('.aips-integration-custom-field-key-input').val(savedMapping.field_key);
					var shapeValue = /^freeform_/.test(savedMapping.field_type) ? savedMapping.field_type : 'freeform_long_text';
					$row.find('.aips-integration-custom-field-shape-select').val(shapeValue);
				}
			}

			this._syncCustomFieldRowVisibility($row);

			return $row;
		},

		/**
		 * Show/hide a repeater row's custom-key input, shape select, and
		 * native-type display based on its dropdown's current selection.
		 *
		 * @param {jQuery} $row
		 */
		_syncCustomFieldRowVisibility: function ($row) {
			var $select = $row.find('.aips-integration-field-key-select');
			var $customInput = $row.find('.aips-integration-custom-field-key-input');
			var $shapeSelect = $row.find('.aips-integration-custom-field-shape-select');
			var $nativeTypeDisplay = $row.find('.aips-integration-field-native-type-display');
			var isCustom = $select.val() === '__custom__';

			$customInput.toggle(isCustom);
			$shapeSelect.toggle(isCustom);

			if (isCustom) {
				$nativeTypeDisplay.text('');
				return;
			}

			var $selected = $select.find('option:selected');
			$nativeTypeDisplay.text($select.val() ? ($selected.data('native-type') || '') : '');
		},

		onAddCustomFieldRowClick: function (e) {
			e.preventDefault();
			$('#aips-integration-fields-tbody').append(this._buildCustomFieldRow(null));
		},

		onCustomFieldKeySelectChange: function (e) {
			var $select = $(e.target);
			var $row = $select.closest('tr');

			this._syncCustomFieldRowVisibility($row);

			if ($select.val() && $select.val() !== '__custom__') {
				var $promptField = $row.find('.aips-integration-field-prompt');
				if (!$promptField.val()) {
					$promptField.val($select.find('option:selected').data('instructions') || '');
				}
			}
		},

		onRemoveCustomFieldRowClick: function (e) {
			e.preventDefault();
			$(e.target).closest('tr').remove();
		},

		onSaveClick: function () {
			var templateId = $('#template_id').val();
			var integrationId = $('#aips-integration-select').val();
			var groupId = $('#aips-integration-group-select').val();

			if (!templateId || !integrationId || !groupId) {
				AIPS.Utilities.showToast(aipsIntegrationsL10n.selectGroupFirst, 'warning');
				return;
			}

			var mappings = this._supportsCustomFieldKeys
				? this._collectCustomFieldMappings(integrationId, groupId)
				: this._collectDiscoveredFieldMappings(integrationId, groupId);

			if (mappings === false) {
				return; // Validation already reported via toast.
			}

			var self = this;

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_save_field_mappings',
				nonce: aipsAjax.nonce,
				template_id: templateId,
				integration_id: integrationId,
				source_key: groupId,
				mappings: JSON.stringify(mappings)
			}, function (response) {
				if (!response.success) {
					AIPS.Utilities.showToast(response.data.message, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message, 'success');
				self.loadSavedMappings(templateId);
			}).fail(function () {
				AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
			});
		},

		_collectDiscoveredFieldMappings: function (integrationId, groupId) {
			var mappings = [];
			$('#aips-integration-fields-tbody .aips-integration-field-row').each(function () {
				var $row = $(this);
				mappings.push({
					integration_id: integrationId,
					source_key: groupId,
					field_key: $row.data('field-key'),
					field_label: $row.find('td').eq(0).text(),
					field_type: $row.data('native-type'),
					custom_prompt: $row.find('.aips-integration-field-prompt').val(),
					is_active: $row.find('.aips-integration-field-enabled').is(':checked')
				});
			});
			return mappings;
		},

		/**
		 * Build the mappings array from the repeater rows, validating any
		 * hand-typed custom key client-side. Returns false (after showing a
		 * toast) if any row fails validation, so onSaveClick() can abort.
		 *
		 * @param {string} integrationId
		 * @param {string} groupId
		 * @return {Array<Object>|false}
		 */
		_collectCustomFieldMappings: function (integrationId, groupId) {
			var mappings = [];
			var invalid = false;

			$('#aips-integration-fields-tbody .aips-integration-custom-field-row').each(function () {
				if (invalid) {
					return;
				}

				var $row = $(this);
				var $select = $row.find('.aips-integration-field-key-select');
				var selectedValue = $select.val();

				if (!selectedValue) {
					return; // Empty slot — nothing chosen yet, skip silently.
				}

				var fieldKey, fieldType, fieldLabel;

				if (selectedValue === '__custom__') {
					fieldKey = $.trim($row.find('.aips-integration-custom-field-key-input').val());
					fieldType = $row.find('.aips-integration-custom-field-shape-select').val();
					fieldLabel = fieldKey;

					if (!fieldKey || !CUSTOM_FIELD_KEY_PATTERN.test(fieldKey)) {
						AIPS.Utilities.showToast(aipsIntegrationsL10n.invalidCustomKey, 'warning');
						invalid = true;
						return;
					}
				} else {
					var $selectedOption = $select.find('option:selected');
					fieldKey = selectedValue;
					fieldType = $selectedOption.data('native-type');
					fieldLabel = $selectedOption.data('label');
				}

				mappings.push({
					integration_id: integrationId,
					source_key: groupId,
					field_key: fieldKey,
					field_label: fieldLabel,
					field_type: fieldType,
					custom_prompt: $row.find('.aips-integration-field-prompt').val(),
					is_active: $row.find('.aips-integration-field-enabled').is(':checked')
				});
			});

			return invalid ? false : mappings;
		}
	};

	$(document).ready(function () {
		AIPS.Integrations.init();
	});
})(jQuery);
