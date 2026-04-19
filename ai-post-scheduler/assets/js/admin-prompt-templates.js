/**
 * Prompt Templates Admin JS
 *
 * Handles all UI interactions on the Prompt Templates admin page:
 * listing groups, add/edit/delete groups, editing per-component prompt text,
 * and setting the default group.
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
			this.renderComponentFields(null);
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
				'<div class="aips-loading-spinner"><span class="spinner is-active"></span> ' + aipsPTL10n.loading + '</div>'
			);

			this.showModal();

			var self = this;
			$.post(ajaxurl, {
				action: 'aips_get_prompt_template_group',
				id: groupId,
				nonce: aipsPTL10n.nonce,
			}).done(function (resp) {
				if (!resp.success) {
					self.showNotice(resp.data.message || aipsPTL10n.error_generic, 'error');
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
				self.showNotice(aipsPTL10n.error_generic, 'error');
				self.closeModal();
			});
		},

		/**
		 * Render the per-component textarea fields inside the modal.
		 *
		 * @param {Object|null} itemMap Map of component_key => saved prompt_text.
		 * @return {void}
		 */
		renderComponentFields: function (itemMap) {
			if (!this.components.length) {
				$('#aips-pt-components-container').html('<p>' + aipsPTL10n.no_components + '</p>');
				return;
			}

			var html = '';
			$.each(this.components, function (i, comp) {
				var savedText = (itemMap && itemMap[comp.key]) ? itemMap[comp.key] : '';
				var placeholder = comp.default_prompt || '';

				html += '<div class="aips-pt-component aips-form-row" data-key="' + AIPS.PromptTemplates.escAttr(comp.key) + '">';
				html += '<div class="aips-pt-component-header">';
				html += '<label for="aips-pt-comp-' + AIPS.PromptTemplates.escAttr(comp.key) + '" class="aips-form-label">';
				html += AIPS.PromptTemplates.escHtml(comp.label);
				html += '</label>';
				html += '<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-pt-reset-component" ';
				html += 'data-key="' + AIPS.PromptTemplates.escAttr(comp.key) + '" ';
				html += 'title="' + AIPS.PromptTemplates.escAttr(aipsPTL10n.reset_default) + '">';
				html += '<span class="dashicons dashicons-undo"></span> ' + AIPS.PromptTemplates.escHtml(aipsPTL10n.reset_default);
				html += '</button>';
				html += '</div>';

				if (comp.description) {
					html += '<p class="aips-form-help">' + AIPS.PromptTemplates.escHtml(comp.description) + '</p>';
				}

				html += '<textarea id="aips-pt-comp-' + AIPS.PromptTemplates.escAttr(comp.key) + '" ';
				html += 'class="aips-form-textarea aips-pt-comp-textarea" rows="4" ';
				html += 'data-key="' + AIPS.PromptTemplates.escAttr(comp.key) + '" ';
				html += 'data-default="' + AIPS.PromptTemplates.escAttr(placeholder) + '" ';
				html += 'placeholder="' + AIPS.PromptTemplates.escAttr(placeholder) + '">';
				html += AIPS.PromptTemplates.escHtml(savedText);
				html += '</textarea>';
				html += '</div>';
			});

			$('#aips-pt-components-container').html(html);
		},

		/**
		 * Reset a single component textarea to its built-in default text.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		resetComponent: function (e) {
			e.preventDefault();
			var key = $(e.currentTarget).data('key');
			var $textarea = $('#aips-pt-comp-' + key);
			var defaultText = $textarea.data('default') || '';
			$textarea.val(defaultText);
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
				this.showNotice(aipsPTL10n.name_required, 'error');
				$('#aips-pt-modal-name').focus();
				return;
			}

			var groupId    = $('#aips-pt-modal-group-id').val();
			var description = $('#aips-pt-modal-description').val();
			var isDefault  = $('#aips-pt-modal-is-default').is(':checked') ? 1 : 0;

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
					self.showNotice(resp.data.message || aipsPTL10n.error_generic, 'error');
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
						self.showNotice(itemsResp.data.message || aipsPTL10n.error_generic, 'error');
						return;
					}

					self.showNotice(resp.data.message || aipsPTL10n.saved, 'success');
					self.closeModal();
					self.reloadGroupsTable();
				}).fail(function () {
					$btn.prop('disabled', false).removeClass('loading');
					self.showNotice(aipsPTL10n.error_generic, 'error');
				});
			}).fail(function () {
				$btn.prop('disabled', false).removeClass('loading');
				self.showNotice(aipsPTL10n.error_generic, 'error');
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
					self.showNotice(resp.data.message || aipsPTL10n.error_generic, 'error');
					return;
				}
				self.showNotice(resp.data.message || aipsPTL10n.saved, 'success');
				self.reloadGroupsTable();
			}).fail(function () {
				self.showNotice(aipsPTL10n.error_generic, 'error');
			});
		},

		/**
		 * Delete a group after confirmation.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		deleteGroup: function (e) {
			e.preventDefault();

			var groupId   = parseInt($(e.currentTarget).data('id'), 10);
			var groupName = $(e.currentTarget).data('name') || '';

			var msg = aipsPTL10n.confirm_delete.replace('{name}', groupName);
			if (!window.confirm(msg)) {
				return;
			}

			var self = this;
			$.post(ajaxurl, {
				action: 'aips_delete_prompt_template_group',
				id:     groupId,
				nonce:  aipsPTL10n.nonce,
			}).done(function (resp) {
				if (!resp.success) {
					self.showNotice(resp.data.message || aipsPTL10n.error_generic, 'error');
					return;
				}
				self.showNotice(resp.data.message || aipsPTL10n.deleted, 'success');
				self.reloadGroupsTable();
			}).fail(function () {
				self.showNotice(aipsPTL10n.error_generic, 'error');
			});
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
					? '<span class="aips-badge aips-badge-success"><span class="dashicons dashicons-yes-alt"></span> ' + aipsPTL10n.badge_default + '</span>'
					: '<span class="aips-badge aips-badge-neutral">' + aipsPTL10n.badge_inactive + '</span>';

				var setDefaultBtn = !isDefault
					? '<button class="aips-btn aips-btn-sm aips-btn-secondary aips-pt-set-default" data-id="' + AIPS.PromptTemplates.escAttr(String(g.id)) + '">'
					  + '<span class="dashicons dashicons-yes"></span> ' + AIPS.PromptTemplates.escHtml(aipsPTL10n.set_default)
					  + '</button>'
					: '';

				html += '<tr data-group-id="' + AIPS.PromptTemplates.escAttr(String(g.id)) + '">';
				html += '<td class="column-name cell-primary"><strong>' + AIPS.PromptTemplates.escHtml(g.name) + '</strong></td>';
				html += '<td class="column-description cell-meta">' + AIPS.PromptTemplates.escHtml(g.description || '—') + '</td>';
				html += '<td class="column-status">' + statusBadge + '</td>';
				html += '<td class="column-actions"><div class="aips-action-buttons">';
				html += '<button class="aips-btn aips-btn-sm aips-pt-edit-group" data-id="' + AIPS.PromptTemplates.escAttr(String(g.id)) + '">'
				      + '<span class="dashicons dashicons-edit"></span><span class="screen-reader-text">' + AIPS.PromptTemplates.escHtml(aipsPTL10n.edit) + '</span></button>';
				html += setDefaultBtn;
				html += '<button class="aips-btn aips-btn-sm aips-btn-danger aips-pt-delete-group" data-id="' + AIPS.PromptTemplates.escAttr(String(g.id)) + '" data-name="' + AIPS.PromptTemplates.escAttr(g.name) + '">'
				      + '<span class="dashicons dashicons-trash"></span><span class="screen-reader-text">' + AIPS.PromptTemplates.escHtml(aipsPTL10n.delete_label) + '</span></button>';
				html += '</div></td>';
				html += '</tr>';
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
		// Notice helpers
		// -----------------------------------------------------------------

		/**
		 * Show an admin notice at the top of the content panel.
		 *
		 * @param {string} message Notice message.
		 * @param {string} type    'success' | 'error'.
		 * @return {void}
		 */
		showNotice: function (message, type) {
			$('.aips-pt-notice').remove();

			var cls = type === 'error' ? 'notice-error' : 'notice-success';
			var $notice = $('<div class="notice ' + cls + ' is-dismissible aips-pt-notice"><p>' + $('<span>').text(message).html() + '</p></div>');

			$('.aips-page-header').after($notice);

			// Auto-dismiss after 4 s.
			setTimeout(function () {
				$notice.fadeOut(400, function () { $(this).remove(); });
			}, 4000);
		},

		// -----------------------------------------------------------------
		// Utility
		// -----------------------------------------------------------------

		/**
		 * Escape a string for safe insertion as HTML text.
		 *
		 * @param {string} str Input string.
		 * @return {string}
		 */
		escHtml: function (str) {
			return $('<span>').text(String(str)).html();
		},

		/**
		 * Escape a string for safe insertion as an HTML attribute value.
		 *
		 * @param {string} str Input string.
		 * @return {string}
		 */
		escAttr: function (str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/"/g, '&quot;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;');
		},
	};

	$(document).ready(function () {
		AIPS.PromptTemplates.init();
	});

})(jQuery);
