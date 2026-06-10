import { BaseFormView } from './base-form';
import { SectionModel } from '../models/section';
import $ from 'jquery';

/**
 * Sections View Controller
 */
export const SectionsView = BaseFormView.extend({
	el: 'body',
	formSelector: '#aips-section-form',
	modalSelector: '#aips-section-modal',
	submitButtonSelector: '.aips-save-section',
	contentPanelSelector: '.aips-sections-list',
	emptyStateSelector: '.aips-sections-container',
	l10n: window.aipsStructuresL10n || {}, // Shares l10n with structures

	events: {
		// Inherit form submit and close modal
		'submit #aips-section-form': 'onFormSubmit',
		'click #aips-section-modal .aips-modal-close': 'closeModal',

		// Section actions
		'click .aips-add-section-btn': 'openSectionModal',
		'click .aips-edit-section': 'editSection',
		'click .aips-delete-section': 'deleteSection',

		// Search
		'keyup #aips-section-search': 'filterSections',
		'search #aips-section-search': 'filterSections',
		'click #aips-section-search-clear': 'clearSectionSearch',
		'click .aips-clear-section-search-btn': 'clearSectionSearch'
	},

	initialize() {
		this.model = new SectionModel();
	},

	openSectionModal(e) {
		if (e) e.preventDefault();
		this.$(this.formSelector)[0].reset();
		this.$('#section_id').val('');
		this.$('#aips-section-modal-title').text(this.l10n.addNewSection || 'Add New Prompt Section');
		this.$(this.modalSelector).show();
	},

	editSection(e) {
		e.preventDefault();
		const id = $(e.currentTarget).data('id');
		const $btn = $(e.currentTarget);

		$btn.prop('disabled', true);

		const section = new SectionModel({ section_id: id });
		section.fetch({
			success: (model) => {
				const s = model.toJSON();
				this.$(this.formSelector)[0].reset();
				this.$('#section_id').val(s.section_id);
				this.$('#section_name').val(s.name);
				this.$('#section_key').val(s.section_key);
				this.$('#section_description').val(s.description || '');
				this.$('#section_content').val(s.content || '');
				this.$('#section_is_active').prop('checked', s.is_active == 1);

				this.$('#aips-section-modal-title').text(this.l10n.editSection || 'Edit Prompt Section');
				this.$(this.modalSelector).show();
				$btn.prop('disabled', false);
			},
			error: (model, err) => {
				const errMsg = (err && err.message) || this.l10n.errorLoading || 'Error loading section data.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
				}
				$btn.prop('disabled', false);
			}
		});
	},

	deleteSection(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const id = $btn.data('id');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(this.l10n.deleteSectionConfirm || 'Are you sure you want to delete this section?', 'Confirm', [
				{ label: (window.aipsAdminL10n && window.aipsAdminL10n.confirmCancelButton) || 'Cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: (window.aipsAdminL10n && window.aipsAdminL10n.confirmDeleteButton) || 'Delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const section = new SectionModel({ section_id: id });
						section.destroy({
							success: () => {
								if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
									window.AIPS.refreshContentPanel(this.contentPanelSelector, this.emptyStateSelector);
								} else {
									window.location.reload();
								}
							},
							error: (model, err) => {
								const errMsg = (err && err.message) || this.l10n.errorDeleting || 'Error deleting section.';
								window.AIPS.Utilities.showToast(errMsg, 'error');
							}
						});
					}
				}
			]);
		}
	},

	filterSections() {
		const term = this.$('#aips-section-search').val().toLowerCase().trim();
		const $rows = this.$('.aips-sections-list tbody tr');
		const $noResults = this.$('#aips-section-search-no-results');
		const $table = this.$('.aips-sections-list');
		const $clearBtn = this.$('#aips-section-search-clear');
		let hasVisible = false;

		if (term.length > 0) {
			$clearBtn.show();
		} else {
			$clearBtn.hide();
		}

		$rows.each(function() {
			const $row = $(this);
			const name = $row.find('.column-name').text().toLowerCase();
			const key = $row.find('.column-key code').text().toLowerCase();
			const description = $row.find('.column-description').text().toLowerCase();

			if (name.indexOf(term) > -1 || key.indexOf(term) > -1 || description.indexOf(term) > -1) {
				$row.show();
				hasVisible = true;
			} else {
				$row.hide();
			}
		});

		if (!hasVisible && term.length > 0) {
			$table.hide();
			$noResults.show();
		} else {
			$table.show();
			$noResults.hide();
		}
	},

	clearSectionSearch(e) {
		e.preventDefault();
		this.$('#aips-section-search').val('').trigger('keyup');
	}
});
