import { BaseFormView } from './base-form';
import { StructureModel } from '../models/structure';
import $ from 'jquery';

/**
 * Structures View Controller
 */
export const StructuresView = BaseFormView.extend({
	el: 'body',
	formSelector: '#aips-structure-form',
	modalSelector: '#aips-structure-modal',
	submitButtonSelector: '.aips-save-structure',
	contentPanelSelector: '.aips-structures-list',
	emptyStateSelector: '.aips-structures-container',
	l10n: window.aipsStructuresL10n || {},

	events: {
		// Inherit form submit and close modal
		'submit #aips-structure-form': 'onFormSubmit',
		'click #aips-structure-modal .aips-modal-close': 'closeModal',

		// Structure actions
		'click .aips-add-structure-btn': 'openStructureModal',
		'click .aips-edit-structure': 'editStructure',
		'click .aips-delete-structure': 'deleteStructure',

		// Search
		'keyup #aips-structure-search': 'filterStructures',
		'search #aips-structure-search': 'filterStructures',
		'click #aips-structure-search-clear': 'clearStructureSearch',
		'click .aips-clear-structure-search-btn': 'clearStructureSearch'
	},

	initialize() {
		this.model = new StructureModel();
	},

	openStructureModal(e) {
		if (e) e.preventDefault();
		this.$(this.formSelector)[0].reset();
		this.$('#structure_id').val('');
		this.$('#structure_sections').val([]);
		this.$('#aips-structure-modal-title').text(this.l10n.addNewStructure || 'Add New Article Structure');
		this.$(this.modalSelector).show();
	},

	editStructure(e) {
		e.preventDefault();
		const id = $(e.currentTarget).data('id');
		const $btn = $(e.currentTarget);

		$btn.prop('disabled', true);

		const structure = new StructureModel({ structure_id: id });
		structure.fetch({
			success: (model) => {
				const s = model.toJSON();
				this.$(this.formSelector)[0].reset();
				this.$('#structure_id').val(s.structure_id);
				this.$('#structure_name').val(s.name);
				this.$('#structure_description').val(s.description || '');
				this.$('#prompt_template').val(s.prompt_template || '');
				this.$('#structure_is_active').prop('checked', s.is_active == 1);

				// Sections list is parsed as array in parse()
				this.$('#structure_sections').val(s.sections || []);

				this.$('#aips-structure-modal-title').text(this.l10n.editStructure || 'Edit Article Structure');
				this.$(this.modalSelector).show();
				$btn.prop('disabled', false);
			},
			error: (model, err) => {
				const errMsg = (err && err.message) || this.l10n.errorLoading || 'Error loading structure data.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
				}
				$btn.prop('disabled', false);
			}
		});
	},

	deleteStructure(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const id = $btn.data('id');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(this.l10n.deleteStructureConfirm || 'Are you sure you want to delete this structure?', 'Confirm', [
				{ label: (window.aipsAdminL10n && window.aipsAdminL10n.confirmCancelButton) || 'Cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: (window.aipsAdminL10n && window.aipsAdminL10n.confirmDeleteButton) || 'Delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const structure = new StructureModel({ structure_id: id });
						structure.destroy({
							success: () => {
								if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
									window.AIPS.refreshContentPanel(this.contentPanelSelector, this.emptyStateSelector);
								} else {
									window.location.reload();
								}
							},
							error: (model, err) => {
								const errMsg = (err && err.message) || this.l10n.errorDeleting || 'Error deleting structure.';
								window.AIPS.Utilities.showToast(errMsg, 'error');
							}
						});
					}
				}
			]);
		}
	},

	filterStructures() {
		const term = this.$('#aips-structure-search').val().toLowerCase().trim();
		const $rows = this.$('.aips-structures-list tbody tr');
		const $noResults = this.$('#aips-structure-search-no-results');
		const $table = this.$('.aips-structures-list');
		const $clearBtn = this.$('#aips-structure-search-clear');
		let hasVisible = false;

		if (term.length > 0) {
			$clearBtn.show();
		} else {
			$clearBtn.hide();
		}

		$rows.each(function() {
			const $row = $(this);
			const name = $row.find('.column-name').text().toLowerCase();

			if (name.indexOf(term) > -1) {
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

	clearStructureSearch(e) {
		e.preventDefault();
		this.$('#aips-structure-search').val('').trigger('keyup');
	}
});
