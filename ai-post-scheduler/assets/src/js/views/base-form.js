import Backbone from 'backbone';
import $ from 'jquery';

/**
 * Reusable Base View for generic AJAX form-submitting pages
 */
export const BaseFormView = Backbone.View.extend({
	formSelector: '',
	modalSelector: '',
	submitButtonSelector: '',
	contentPanelSelector: '',
	emptyStateSelector: '',
	l10n: {},

	events: {
		'submit form': 'onFormSubmit',
		'click .aips-modal-close': 'closeModal'
	},

	onFormSubmit(e) {
		if (e) e.preventDefault();
		const $form = this.formSelector ? this.$(this.formSelector) : this.$('form').first();
		if (!$form.length) return;

		if (!$form[0].checkValidity()) {
			$form[0].reportValidity();
			return;
		}

		const $btn = this.submitButtonSelector ? this.$(this.submitButtonSelector) : $form.find('[type="submit"]');
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, this.l10n.saving || 'Saving...');
		}

		const data = {};
		$form.serializeArray().forEach(item => {
			if (item.name.endsWith('[]')) {
				const cleanName = item.name.substring(0, item.name.length - 2);
				data[cleanName] = data[cleanName] || [];
				data[cleanName].push(item.value);
			} else {
				data[item.name] = item.value;
			}
		});

		$form.find('input[type="checkbox"]').each(function() {
			const name = $(this).attr('name');
			if (name) {
				if (!name.endsWith('[]')) {
					data[name] = $(this).is(':checked') ? 1 : 0;
				}
			}
		});

		this.model.clear({ silent: true });
		this.model.set(data);

		this.model.save(null, {
			success: (model, response) => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast((response && response.message) || this.l10n.saved || 'Saved successfully.', 'success');
				}
				this.closeModal();

				if (this.contentPanelSelector && window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
					window.AIPS.refreshContentPanel(this.contentPanelSelector, this.emptyStateSelector);
				} else {
					setTimeout(() => location.reload(), 1000);
				}
			},
			error: (model, err) => {
				const errMsg = (err && err.message) || this.l10n.errorSaving || 'Error saving.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	closeModal(e) {
		if (e) e.preventDefault();
		if (this.modalSelector) {
			this.$(this.modalSelector).fadeOut();
		}
	}
});
