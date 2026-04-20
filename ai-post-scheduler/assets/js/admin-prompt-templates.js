/**
 * Prompt Templates Admin JS
 *
 * Handles all UI interactions on the Prompt Templates admin page:
 * listing groups, add/edit/delete groups, editing per-component prompt text,
 * and setting the default group.
 *
 * Relies on:
 *   - AIPS.Templates  (templates.js)  — client-side HTML templating engine.
 *   - AIPS.Utilities  (utilities.js)  — showToast() and confirm() helpers.
 *
 * @package AI_Post_Scheduler
 */

(function ($) {
'use strict';

window.AIPS = window.AIPS || {};

var AIPS = window.AIPS;

/**
 * AIPS.PromptTemplates — sub-module for the Prompt Templates page.
 */
AIPS.PromptTemplates = {

/**
 * Component definitions loaded from the inline JSON block.
 * @type {Array}
 */
components: [],

/**
 * The group currently open in the modal.
 * @type {Object|null}
 */
currentGroup: null,

/**
 * Initialise the module.
 *
 * @return {void}
 */
init: function () {
this.components = this.loadComponentDefinitions();
this.bindEvents();
},

/**
 * Parse the component definitions embedded in the page.
 *
 * @return {Array}
 */
loadComponentDefinitions: function () {
var el = document.getElementById('aips-pt-components-data');
if (!el) {
return [];
}
try {
return JSON.parse(el.textContent || el.innerText) || [];
} catch (e) {
return [];
}
},

/**
 * Bind all UI event listeners.
 *
 * @return {void}
 */
bindEvents: function () {
// Open modal for new group.
$(document).on('click', '#aips-add-pt-group-btn', this.openNewGroupModal.bind(this));

// Open modal to edit existing group.
$(document).on('click', '.aips-pt-edit-group', this.openEditGroupModal.bind(this));

// Set default group.
$(document).on('click', '.aips-pt-set-default', this.setDefaultGroup.bind(this));

// Delete group.
$(document).on('click', '.aips-pt-delete-group', this.deleteGroup.bind(this));

// Save group (modal footer).
$(document).on('click', '#aips-pt-modal-save', this.saveGroup.bind(this));

// Close modal.
$(document).on('click', '.aips-modal-close', this.closeModal.bind(this));
$(document).on('click', '#aips-pt-group-modal', this.closeModalOnOverlay.bind(this));

// Search filter.
$(document).on('input', '#aips-pt-search', this.filterTable.bind(this));

// Prevent modal content click from bubbling to overlay.
$(document).on('click', '#aips-pt-group-modal .aips-modal-content', function (e) {
e.stopPropagation();
});

// Reset to built-in default for a specific component.
$(document).on('click', '.aips-pt-reset-component', this.resetComponent.bind(this));
},

// -----------------------------------------------------------------
// Modal management
// -----------------------------------------------------------------

/**
 * Open the modal wired up for creating a new group.
 *
 * @param {Event} e Click event.
 * @return {void}
 */
openNewGroupModal: function (e) {
e.preventDefault();

this.currentGroup = null;
this.resetModal();

$('#aips-pt-modal-title').text(aipsPTL10n.add_group);

// Pre-fill each component with its built-in default prompt so
// the new group starts with meaningful content rather than blank fields.
var defaultItemMap = {};
$.each(this.components, function (i, comp) {
    defaultItemMap[comp.key] = comp.default_prompt || '';
});
this.renderComponentFields(defaultItemMap);
this.showModal();
},

/**
 * Open the modal wired up for editing an existing group.
 *
 * @param {Event} e Click event.
 * @return {void}
 */
openEditGroupModal: function (e) {
e.preventDefault();

var groupId = parseInt($(e.currentTarget).data('id'), 10);
if (!groupId) {
return;
}

$('#aips-pt-modal-title').text(aipsPTL10n.edit_group);
$('#aips-pt-components-container').html(
AIPS.Templates.render('aips-tmpl-pt-loading', { message: aipsPTL10n.loading })
);

this.showModal();

var self = this;
$.post(ajaxurl, {
action: 'aips_get_prompt_template_group',
id: groupId,
nonce: aipsPTL10n.nonce,
}).done(function (resp) {
if (!resp.success) {
AIPS.Utilities.showToast(resp.data.message || aipsPTL10n.error_generic, 'error');
self.closeModal();
return;
}

var data = resp.data;
self.currentGroup = data.group;

$('#aips-pt-modal-group-id').val(data.group.id);
$('#aips-pt-modal-name').val(data.group.name);
$('#aips-pt-modal-description').val(data.group.description || '');
$('#aips-pt-modal-is-default').prop('checked', parseInt(data.group.is_default, 10) === 1);

// Build a map of component_key => prompt_text from items.
var itemMap = {};
$.each(data.items || [], function (i, item) {
itemMap[item.component_key] = item.prompt_text;
});

self.renderComponentFields(itemMap);
}).fail(function () {
AIPS.Utilities.showToast(aipsPTL10n.error_generic, 'error');
self.closeModal();
});
},

/**
 * Render the per-component textarea fields inside the modal.
 *
 * Each field is built from the `aips-tmpl-pt-component-field` template.
 * Default prompts are stored in the in-memory `this.components` registry
 * so that multi-line defaults survive without being collapsed by HTML
 * attribute normalisation.
 *
 * @param {Object|null} itemMap Map of component_key => saved prompt_text.
 * @return {void}
 */
renderComponentFields: function (itemMap) {
if (!this.components.length) {
$('#aips-pt-components-container').html(
AIPS.Templates.render('aips-tmpl-pt-no-components', { message: aipsPTL10n.no_components })
);
return;
}

var html = '';

$.each(this.components, function (i, comp) {
var savedText   = (itemMap && itemMap[comp.key]) ? itemMap[comp.key] : '';
var descHtml    = comp.description
? AIPS.Templates.render('aips-tmpl-pt-component-description', { description: comp.description })
: '';

// Use renderRaw() so the pre-rendered descHtml is inserted as raw HTML.
// All other values are pre-escaped with AIPS.Templates.escape().
html += AIPS.Templates.renderRaw('aips-tmpl-pt-component-field', {
key:         AIPS.Templates.escape(comp.key),
label:       AIPS.Templates.escape(comp.label),
resetTitle:  AIPS.Templates.escape(aipsPTL10n.reset_default),
resetLabel:  AIPS.Templates.escape(aipsPTL10n.reset_default),
description: descHtml,
placeholder: AIPS.Templates.escape(aipsPTL10n.component_placeholder || ''),
savedText:   AIPS.Templates.escape(savedText),
});
});

$('#aips-pt-components-container').html(html);
},

/**
 * Reset a single component textarea to its built-in default text.
 *
 * The default text is sourced from the in-memory `this.components`
 * registry rather than a DOM attribute so multi-line defaults are
 * preserved exactly.
 *
 * @param {Event} e Click event.
 * @return {void}
 */
resetComponent: function (e) {
e.preventDefault();
var key  = $(e.currentTarget).data('key');
var comp = this.getComponentByKey(key);
var defaultText = (comp && comp.default_prompt) ? comp.default_prompt : '';
$('#aips-pt-comp-' + key).val(defaultText);
},

/**
 * Reset modal fields to their empty state.
 *
 * @return {void}
 */
resetModal: function () {
$('#aips-pt-modal-group-id').val('');
$('#aips-pt-modal-name').val('');
$('#aips-pt-modal-description').val('');
$('#aips-pt-modal-is-default').prop('checked', false);
$('#aips-pt-components-container').empty();
},

/**
 * Show the edit modal.
 *
 * @return {void}
 */
showModal: function () {
$('#aips-pt-group-modal').attr('aria-hidden', 'false').show();
$('body').addClass('aips-modal-open');
},

/**
 * Close the edit modal.
 *
 * @param {Event} [e] Optional click event.
 * @return {void}
 */
closeModal: function (e) {
if (e) {
e.preventDefault();
}
$('#aips-pt-group-modal').attr('aria-hidden', 'true').hide();
$('body').removeClass('aips-modal-open');
this.currentGroup = null;
},

/**
 * Close modal when clicking the backdrop overlay.
 *
 * @param {Event} e Click event.
 * @return {void}
 */
closeModalOnOverlay: function (e) {
if ($(e.target).is('#aips-pt-group-modal')) {
this.closeModal(e);
}
},

// -----------------------------------------------------------------
// AJAX actions
// -----------------------------------------------------------------

/**
 * Save the currently open group (create or update).
 *
 * @param {Event} e Click event.
 * @return {void}
 */
saveGroup: function (e) {
e.preventDefault();

var name = $.trim($('#aips-pt-modal-name').val());
if (!name) {
AIPS.Utilities.showToast(aipsPTL10n.name_required, 'error');
$('#aips-pt-modal-name').focus();
return;
}

var groupId     = $('#aips-pt-modal-group-id').val();
var description = $('#aips-pt-modal-description').val();
var isDefault   = $('#aips-pt-modal-is-default').is(':checked') ? 1 : 0;

// Collect component items.
var items = {};
$('.aips-pt-comp-textarea').each(function () {
var key  = $(this).data('key');
var text = $(this).val();
if (key) {
items[key] = text;
}
});

var $btn = $('#aips-pt-modal-save');
$btn.prop('disabled', true).addClass('loading');

var self = this;
var postData = {
action:      'aips_save_prompt_template_group',
nonce:       aipsPTL10n.nonce,
name:        name,
description: description,
is_default:  isDefault,
};

if (groupId) {
postData.id = groupId;
}

$.post(ajaxurl, postData).done(function (resp) {
if (!resp.success) {
AIPS.Utilities.showToast(resp.data.message || aipsPTL10n.error_generic, 'error');
$btn.prop('disabled', false).removeClass('loading');
return;
}

var savedGroup = resp.data.group;

// Save items for the group.
$.post(ajaxurl, {
action:   'aips_save_prompt_template_items',
nonce:    aipsPTL10n.nonce,
group_id: savedGroup.id,
items:    JSON.stringify(items),
}).done(function (itemsResp) {
$btn.prop('disabled', false).removeClass('loading');

if (!itemsResp.success) {
AIPS.Utilities.showToast(itemsResp.data.message || aipsPTL10n.error_generic, 'error');
return;
}

AIPS.Utilities.showToast(resp.data.message || aipsPTL10n.saved, 'success');
self.closeModal();
self.reloadGroupsTable();
}).fail(function () {
$btn.prop('disabled', false).removeClass('loading');
AIPS.Utilities.showToast(aipsPTL10n.error_generic, 'error');
});
}).fail(function () {
$btn.prop('disabled', false).removeClass('loading');
AIPS.Utilities.showToast(aipsPTL10n.error_generic, 'error');
});
},

/**
 * Set a group as the active default.
 *
 * @param {Event} e Click event.
 * @return {void}
 */
setDefaultGroup: function (e) {
e.preventDefault();

var groupId = parseInt($(e.currentTarget).data('id'), 10);
if (!groupId) {
return;
}

var self = this;
$.post(ajaxurl, {
action: 'aips_set_default_prompt_template_group',
id:     groupId,
nonce:  aipsPTL10n.nonce,
}).done(function (resp) {
if (!resp.success) {
AIPS.Utilities.showToast(resp.data.message || aipsPTL10n.error_generic, 'error');
return;
}
AIPS.Utilities.showToast(resp.data.message || aipsPTL10n.saved, 'success');
self.reloadGroupsTable();
}).fail(function () {
AIPS.Utilities.showToast(aipsPTL10n.error_generic, 'error');
});
},

/**
 * Delete a group after confirmation via AIPS.Utilities.confirm().
 *
 * @param {Event} e Click event.
 * @return {void}
 */
deleteGroup: function (e) {
e.preventDefault();

var groupId   = parseInt($(e.currentTarget).data('id'), 10);
var groupName = $(e.currentTarget).data('name') || '';

var msg  = aipsPTL10n.confirm_delete.replace('{name}', groupName);
var self = this;

AIPS.Utilities.confirm(
msg,
aipsPTL10n.delete_confirm_heading || aipsPTL10n.delete_label,
[
{ label: aipsPTL10n.cancel_label, className: 'aips-btn aips-btn-secondary' },
{
label:     aipsPTL10n.delete_label,
className: 'aips-btn aips-btn-danger-solid',
action:    function () {
$.post(ajaxurl, {
action: 'aips_delete_prompt_template_group',
id:     groupId,
nonce:  aipsPTL10n.nonce,
}).done(function (resp) {
if (!resp.success) {
AIPS.Utilities.showToast(resp.data.message || aipsPTL10n.error_generic, 'error');
return;
}
AIPS.Utilities.showToast(resp.data.message || aipsPTL10n.deleted, 'success');
self.reloadGroupsTable();
}).fail(function () {
AIPS.Utilities.showToast(aipsPTL10n.error_generic, 'error');
});
},
},
]
);
},

// -----------------------------------------------------------------
// Table helpers
// -----------------------------------------------------------------

/**
 * Reload the groups table via AJAX and refresh the DOM.
 *
 * @return {void}
 */
reloadGroupsTable: function () {
var self = this;

$.post(ajaxurl, {
action: 'aips_get_prompt_template_groups',
nonce:  aipsPTL10n.nonce,
}).done(function (resp) {
if (!resp.success) {
return;
}
self.renderGroupsTable(resp.data.groups || []);
});
},

/**
 * Re-render the groups table rows from a fresh data set.
 *
 * Uses AIPS.Templates.render/renderRaw to produce each row from
 * `<script type="text/html">` template blocks defined in the PHP template.
 *
 * @param {Array} groups Array of group objects.
 * @return {void}
 */
renderGroupsTable: function (groups) {
var $tbody = $('#aips-pt-groups-tbody');

if (!$tbody.length) {
// Table does not exist yet (empty-state view) — reload the page.
window.location.reload();
return;
}

if (!groups.length) {
window.location.reload();
return;
}

var html = '';
$.each(groups, function (i, g) {
var isDefault = parseInt(g.is_default, 10) === 1;

var statusBadge = isDefault
? AIPS.Templates.render('aips-tmpl-pt-badge-default',  { label: aipsPTL10n.badge_default })
: AIPS.Templates.render('aips-tmpl-pt-badge-inactive', { label: aipsPTL10n.badge_inactive });

var editBtn = AIPS.Templates.render('aips-tmpl-pt-action-edit', {
id:         String(g.id),
editTitle:  aipsPTL10n.edit,
editLabel:  aipsPTL10n.edit,
});

var setDefaultBtn = !isDefault
? AIPS.Templates.render('aips-tmpl-pt-action-set-default', {
id:              String(g.id),
setDefaultLabel: aipsPTL10n.set_default,
setDefaultTitle: aipsPTL10n.set_default,
})
: '';

var deleteBtn = AIPS.Templates.render('aips-tmpl-pt-action-delete', {
id:          String(g.id),
name:        g.name,
deleteTitle: aipsPTL10n.delete_label,
deleteLabel: aipsPTL10n.delete_label,
});

html += AIPS.Templates.renderRaw('aips-tmpl-pt-group-row', {
id:          AIPS.Templates.escape(String(g.id)),
name:        AIPS.Templates.escape(g.name),
description: AIPS.Templates.escape(g.description || '—'),
statusBadge: statusBadge,
actions:     editBtn + setDefaultBtn + deleteBtn,
});
});

$tbody.html(html);
},

/**
 * Filter the groups table rows based on the search input.
 *
 * @param {Event} e Input event.
 * @return {void}
 */
filterTable: function (e) {
var term = $.trim($(e.currentTarget).val()).toLowerCase();
$('#aips-pt-groups-tbody tr').each(function () {
var name = $(this).find('.column-name').text().toLowerCase();
$(this).toggle(name.indexOf(term) !== -1);
});
},

// -----------------------------------------------------------------
// Utility helpers
// -----------------------------------------------------------------

/**
 * Find a component definition by its key.
 *
 * Used by resetComponent() to retrieve the built-in default prompt text
 * from the in-memory registry rather than from a DOM attribute.
 *
 * @param {string} key Component key.
 * @return {Object|null} Component definition object, or null if not found.
 */
getComponentByKey: function (key) {
var found = null;
$.each(this.components, function (i, comp) {
if (comp.key === key) {
found = comp;
return false; // break $.each
}
});
return found;
},
};

$(document).ready(function () {
AIPS.PromptTemplates.init();
});

})(jQuery);
