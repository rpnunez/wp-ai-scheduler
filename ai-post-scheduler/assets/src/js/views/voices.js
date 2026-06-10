import { BaseFormView } from './base-form';
import { VoiceModel } from '../models/voice';
import $ from 'jquery';

/**
 * Voices View Controller
 */
export const VoicesView = BaseFormView.extend({
	el: 'body',
	formSelector: '#aips-voice-form',
	modalSelector: '#aips-voice-modal',
	submitButtonSelector: '.aips-save-voice',
	contentPanelSelector: '.aips-voices-list',
	emptyStateSelector: '.aips-voices-container',
	l10n: window.aipsVoicesL10n || {},

	events: {
		// Inherit base form submit and close modal
		'submit #aips-voice-form': 'onFormSubmit',
		'click #aips-voice-modal .aips-modal-close': 'closeModal',

		// Voice specific actions
		'click .aips-add-voice-btn': 'openVoiceModal',
		'click .aips-edit-voice': 'editVoice',
		'click .aips-delete-voice': 'deleteVoice',

		// Search
		'keyup #aips-voice-search': 'filterVoices',
		'search #aips-voice-search': 'filterVoices',
		'click #aips-voice-search-clear': 'clearVoiceSearch',
		'click .aips-clear-voice-search-btn': 'clearVoiceSearch'
	},

	initialize() {
		this.model = new VoiceModel();
	},

	openVoiceModal(e) {
		if (e) e.preventDefault();
		this.$(this.formSelector)[0].reset();
		this.$('#voice_id').val('');
		this.$('#aips-voice-modal-title').text(this.l10n.addNewVoice || 'Add New Voice');
		this.$(this.modalSelector).show();
	},

	editVoice(e) {
		e.preventDefault();
		const id = $(e.currentTarget).data('id');
		const $btn = $(e.currentTarget);

		$btn.prop('disabled', true);

		const voice = new VoiceModel({ voice_id: id });
		voice.fetch({
			success: (model) => {
				const v = model.toJSON();
				this.$(this.formSelector)[0].reset();
				this.$('#voice_id').val(v.voice_id);
				this.$('#voice_name').val(v.name);
				this.$('#voice_title_prompt').val(v.title_prompt || '');
				this.$('#voice_content_instructions').val(v.content_instructions || '');
				this.$('#voice_excerpt_instructions').val(v.excerpt_instructions || '');
				this.$('#voice_is_active').prop('checked', v.is_active == 1);

				this.$('#aips-voice-modal-title').text(this.l10n.editVoice || 'Edit Voice');
				this.$(this.modalSelector).show();
				$btn.prop('disabled', false);
			},
			error: (model, err) => {
				const errMsg = (err && err.message) || this.l10n.errorLoading || 'Error loading voice data.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
				}
				$btn.prop('disabled', false);
			}
		});
	},

	deleteVoice(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const id = $btn.data('id');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(this.l10n.deleteVoiceConfirm || 'Are you sure you want to delete this voice?', 'Confirm', [
				{ label: (window.aipsAdminL10n && window.aipsAdminL10n.confirmCancelButton) || 'Cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: (window.aipsAdminL10n && window.aipsAdminL10n.confirmDeleteButton) || 'Delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const voice = new VoiceModel({ voice_id: id });
						voice.destroy({
							success: () => {
								if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
									window.AIPS.refreshContentPanel(this.contentPanelSelector, this.emptyStateSelector);
								} else {
									window.location.reload();
								}
							},
							error: (model, err) => {
								const errMsg = (err && err.message) || this.l10n.errorDeleting || 'Error deleting voice.';
								window.AIPS.Utilities.showToast(errMsg, 'error');
							}
						});
					}
				}
			]);
		}
	},

	filterVoices() {
		const term = this.$('#aips-voice-search').val().toLowerCase().trim();
		const $rows = this.$('.aips-voices-list tbody tr');
		const $noResults = this.$('#aips-voice-search-no-results');
		const $table = this.$('.aips-voices-list');
		const $clearBtn = this.$('#aips-voice-search-clear');
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

	clearVoiceSearch(e) {
		e.preventDefault();
		this.$('#aips-voice-search').val('').trigger('keyup');
	}
});
