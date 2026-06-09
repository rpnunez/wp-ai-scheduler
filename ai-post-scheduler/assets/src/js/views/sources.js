import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseListView } from './base-list';
import { BaseModalView } from './base-modal';
import { SourceModel } from '../models/source';

/**
 * Sources View
 */
export const SourcesView = BaseListView.extend({
	el: 'body',

	listSelector: '#aips-sources-table',
	rowSelector: '#aips-sources-table tbody tr',
	searchSelector: '#aips-source-search',
	selectAllSelector: '',
	checkboxSelector: '',
	bulkApplySelector: '',

	currentSourceId: 0,

	events: _.extend({}, BaseListView.prototype.events, {
		// Open modal for a new source
		'click #aips-add-source-btn, #aips-add-source-empty-btn': 'openAddModal',

		// Open modal for an existing source
		'click .aips-edit-source': 'openEditModal',

		// Save source
		'click #aips-save-source-btn': 'saveSource',

		// Delete source
		'click .aips-delete-source': 'deleteSource',

		// Toggle active status
		'click .aips-toggle-source': 'toggleSource',

		// Fetch content now
		'click .aips-fetch-source-now': 'fetchSourceNow',

		// Search
		'input #aips-source-search': 'filterSources',
		'click #aips-source-search-clear, #aips-source-search-clear-2': 'clearSearch',

		// Source Groups
		'click #aips-manage-source-groups-btn': 'openGroupsModal',
		'click #aips-add-group-btn': 'addSourceGroup',
		'click .aips-delete-source-group': 'deleteSourceGroup'
	}),

	initialize() {
		BaseListView.prototype.initialize.apply(this, arguments);

		this.model = new SourceModel();

		// Initialize modals if elements exist in DOM
		if ($('#aips-source-modal').length) {
			this.sourceModal = new BaseModalView({ el: '#aips-source-modal' });
		}
		if ($('#aips-groups-modal').length) {
			this.groupsModal = new BaseModalView({ el: '#aips-groups-modal' });
		}
	},

	isSourcesPage() {
		return this.$('#aips-sources-table').length > 0;
	},

	openAddModal(e) {
		e.preventDefault();
		this.currentSourceId = 0;
		this.resetForm();
		
		const l10n = window.aipsSourcesL10n || {};
		this.$('#aips-source-modal-title').text(l10n.addNewSource || 'Add New Source');
		
		if (this.sourceModal) {
			this.sourceModal.open();
		}
	},

	openEditModal(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const id   = parseInt($btn.data('id'), 10);
		const $row = $btn.closest('tr');

		this.currentSourceId = id;

		this.$('#aips-source-id').val(id);
		this.$('#aips-source-url').val($row.data('url'));
		this.$('#aips-source-label').val($row.data('label'));
		this.$('#aips-source-description').val($row.data('description'));
		this.$('#aips-source-is-active').prop('checked', parseInt($row.data('active'), 10) === 1);
		this.$('#aips-source-fetch-interval').val($row.data('fetch-interval') || '');

		// Restore group checkboxes.
		let termIds = [];
		try {
			termIds = JSON.parse($row.attr('data-term-ids') || '[]');
		} catch (err) {
			termIds = [];
		}
		this.$('.aips-source-group-checkbox').prop('checked', false);
		termIds.forEach(tid => {
			this.$('.aips-source-group-checkbox[value="' + tid + '"]').prop('checked', true);
		});

		const l10n = window.aipsSourcesL10n || {};
		this.$('#aips-source-modal-title').text(l10n.editSource || 'Edit Source');
		
		if (this.sourceModal) {
			this.sourceModal.open();
		}
	},

	resetForm() {
		this.$('#aips-source-id').val(0);
		this.$('#aips-source-url').val('');
		this.$('#aips-source-label').val('');
		this.$('#aips-source-description').val('');
		this.$('#aips-source-is-active').prop('checked', true);
		this.$('#aips-source-fetch-interval').val('');
		this.$('.aips-source-group-checkbox').prop('checked', false);
	},

	saveSource(e) {
		e.preventDefault();

		const url = this.$('#aips-source-url').val().trim();
		const l10n = window.aipsSourcesL10n || {};

		if (!url) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.urlRequired || 'URL is required.', 'error');
			}
			return;
		}

		const termIds = [];
		this.$('.aips-source-group-checkbox:checked').each(function() {
			termIds.push(parseInt($(this).val(), 10));
		});

		const data = {
			id: this.currentSourceId,
			url: url,
			label: this.$('#aips-source-label').val().trim(),
			description: this.$('#aips-source-description').val().trim(),
			term_ids: termIds,
			fetch_interval: this.$('#aips-source-fetch-interval').val(),
			is_active: this.$('#aips-source-is-active').is(':checked') ? 1 : 0
		};

		const $btn = this.$('#aips-save-source-btn');
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, l10n.saving || 'Saving...');
		}

		this.model.clear({ silent: true });
		this.model.set(data);

		this.model.save(null, {
			success: (model, response) => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast((response && response.message) || (l10n.saveSuccess || 'Saved successfully.'), 'success');
					window.AIPS.Utilities.resetButton($btn);
				}
				if (this.sourceModal) {
					this.sourceModal.close();
				}
				this.refreshPage();
			},
			error: (model, err) => {
				const errMsg = (err && err.message) || (l10n.saveFailed || 'Save failed.');
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	deleteSource(e) {
		e.preventDefault();
		const id = parseInt($(e.currentTarget).data('id'), 10);
		const l10n = window.aipsSourcesL10n || {};

		if (!confirm(l10n.deleteConfirm || 'Are you sure you want to delete this source?')) {
			return;
		}

		const tempModel = new SourceModel({ id: id });
		tempModel.destroy({
			success: (model, response) => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast((response && response.message) || 'Source deleted successfully.', 'success');
				}
				this.refreshPage();
			},
			error: (model, err) => {
				const errMsg = (err && err.message) || (l10n.deleteFailed || 'Delete failed.');
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
				}
			}
		});
	},

	toggleSource(e) {
		e.preventDefault();
		const $btn      = $(e.currentTarget);
		const id        = parseInt($btn.data('id'), 10);
		const isActive  = parseInt($btn.data('active'), 10);
		const newStatus = isActive === 1 ? 0 : 1;
		const l10n = window.aipsSourcesL10n || {};

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action:    'aips_toggle_source_active',
			nonce:     (window.aipsAjax && window.aipsAjax.nonce) || '',
			source_id: id,
			is_active: newStatus
		}, (response) => {
			if (!response.success) {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(response.data.message || (l10n.toggleFailed || 'Failed to toggle status.'), 'error');
				}
				return;
			}

			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(response.data.message, 'success');
			}
			this.refreshPage();
		}).fail(() => {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.toggleFailed || 'Failed to toggle status.', 'error');
			}
		});
	},

	openGroupsModal(e) {
		e.preventDefault();
		this.$('#aips-new-group-name').val('');
		
		if (this.groupsModal) {
			this.groupsModal.open();
		}
	},

	addSourceGroup(e) {
		e.preventDefault();
		const name = this.$('#aips-new-group-name').val().trim();
		const l10n = window.aipsSourcesL10n || {};

		if (!name) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.groupNameRequired || 'Please enter a group name.', 'error');
			}
			return;
		}

		const $btn = this.$('#aips-add-group-btn');
		$btn.prop('disabled', true);

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_save_source_group',
			nonce:  (window.aipsAjax && window.aipsAjax.nonce) || '',
			name:   name
		}, (response) => {
			$btn.prop('disabled', false);
			if (!response.success) {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(response.data.message || 'Failed to create group.', 'error');
				}
				return;
			}
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(response.data.message, 'success');
			}
			this.refreshPage();
		}).fail(() => {
			$btn.prop('disabled', false);
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Failed to create group.', 'error');
			}
		});
	},

	deleteSourceGroup(e) {
		e.preventDefault();
		const termId = parseInt($(e.currentTarget).data('term-id'), 10);
		const l10n = window.aipsSourcesL10n || {};

		if (!confirm(l10n.deleteGroupConfirm || 'Delete this Source Group? Sources in this group will not be deleted.')) {
			return;
		}

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action:  'aips_delete_source_group',
			nonce:   (window.aipsAjax && window.aipsAjax.nonce) || '',
			term_id: termId
		}, (response) => {
			if (!response.success) {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(response.data.message || 'Failed to delete group.', 'error');
				}
				return;
			}
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(response.data.message, 'success');
			}
			this.refreshPage();
		}).fail(() => {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Failed to delete group.', 'error');
			}
		});
	},

	fetchSourceNow(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const id   = parseInt($btn.data('id'), 10);

		$btn.prop('disabled', true);
		const $icon = $btn.find('.dashicons');
		$icon.removeClass('dashicons-download').addClass('dashicons-update aips-spin');

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action:    'aips_fetch_source_now',
			nonce:     (window.aipsAjax && window.aipsAjax.nonce) || '',
			source_id: id
		}, (response) => {
			$btn.prop('disabled', false);
			$icon.removeClass('dashicons-update aips-spin').addClass('dashicons-download');

			if (!response.success) {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(response.data.message || 'Fetch failed.', 'error');
				}
				return;
			}

			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(response.data.message, 'success');
			}
			this.refreshPage();
		}).fail(() => {
			$btn.prop('disabled', false);
			$icon.removeClass('dashicons-update aips-spin').addClass('dashicons-download');
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Fetch failed.', 'error');
			}
		});
	},

	filterSources(e) {
		const term   = $(e.currentTarget).val().toLowerCase().trim();
		const $rows  = this.$('#aips-sources-table tbody tr');
		let visible = 0;

		this.$('#aips-source-search-clear').toggle(term.length > 0);

		$rows.each(function () {
			const text = $(this).text().toLowerCase();
			const show = !term || text.indexOf(term) !== -1;
			$(this).toggle(show);
			if (show) {
				visible++;
			}
		});

		this.$('#aips-source-search-no-results').toggle(visible === 0 && term.length > 0);
	},

	clearSearch(e) {
		e.preventDefault();
		this.$('#aips-source-search').val('').trigger('input');
	},

	refreshPage() {
		if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
			window.AIPS.refreshContentPanel('.aips-content-panel', '.aips-content-panel');
		} else {
			window.location.reload();
		}
	}
});
export default SourcesView;
