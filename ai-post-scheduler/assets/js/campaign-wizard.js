/**
 * Campaign Wizard admin interactions.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.CampaignWizard = {

		/**
		 * Ordered wizard step keys.
		 *
		 * @type {Array<string>}
		 */
		steps: ['goal', 'template', 'defaults', 'schedule', 'review', 'confirm'],

		/**
		 * Current step index.
		 *
		 * @type {number}
		 */
		currentStepIndex: 0,

		/**
		 * Initialize the campaign wizard.
		 *
		 * @return {void}
		 */
		init: function() {
			if (!$('#aips-campaign-wizard-form').length) {
				return;
			}

			this.bindEvents();
			this.bootstrapSummary();
			this.showStep(0);
		},

		/**
		 * Bind campaign wizard event handlers.
		 *
		 * @return {void}
		 */
		bindEvents: function() {
			$(document).on('click', '#aips-wizard-next', AIPS.CampaignWizard.onNextClick);
			$(document).on('click', '#aips-wizard-prev', AIPS.CampaignWizard.onPreviousClick);
			$(document).on('click', '.aips-wizard-step-tab', AIPS.CampaignWizard.onStepTabClick);
			$(document).on('click', '#aips-wizard-finalize', AIPS.CampaignWizard.onFinalizeClick);
		},

		/**
		 * Show feedback in the wizard notice region and toast system.
		 *
		 * @param {string} type    Notice type: success, error, warning, or info.
		 * @param {string} message Plain-text notice message.
		 * @return {void}
		 */
		showNotice: function(type, message) {
			var noticeClass = type === 'success' ? 'notice notice-success' : 'notice notice-error';
			var $notice = $(document.createElement('div')).addClass(noticeClass);
			var $message = $(document.createElement('p')).text(this.sanitizeText(message));

			$('#aips-campaign-wizard-notice').empty().append($notice.append($message));
			this.showToast(message, type);
		},

		/**
		 * Show a toast when the shared utilities module is available.
		 *
		 * @param {string} message Plain-text toast message.
		 * @param {string} type    Toast type.
		 * @return {void}
		 */
		showToast: function(message, type) {
			if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
				AIPS.Utilities.showToast(this.sanitizeText(message), type);
			}
		},

		/**
		 * Return sanitized wizard form payload values.
		 *
		 * @return {Object}
		 */
		getPayload: function() {
			var data = {};

			$.each($('#aips-campaign-wizard-form').serializeArray(), function(index, item) {
				if (!item || !item.name) {
					return;
				}

				data[item.name] = AIPS.CampaignWizard.sanitizeText(item.value);
			});

			data.is_active = $('#aips-campaign-wizard-form [name="is_active"]').is(':checked') ? 1 : 0;

			return data;
		},

		/**
		 * Sanitize a scalar value before use in AJAX payloads or UI feedback.
		 *
		 * @param {*} value Value to sanitize.
		 * @return {string}
		 */
		sanitizeText: function(value) {
			if (typeof value === 'undefined' || value === null) {
				return '';
			}

			return String(value).replace(/[\u0000-\u001F\u007F]/g, '').trim();
		},

		/**
		 * Send a wizard AJAX request.
		 *
		 * @param {string}   action AJAX action name.
		 * @param {string}   step   Step key to validate/save.
		 * @param {Function} done   Completion callback.
		 * @return {void}
		 */
		sendAjax: function(action, step, done) {
			$('#aips-campaign-spinner').addClass('is-active');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: this.sanitizeText(action),
					nonce: this.sanitizeText(aipsAjax.nonce),
					step: typeof step === 'undefined' ? this.steps[this.currentStepIndex] : this.sanitizeText(step),
					payload: JSON.stringify(this.getPayload()),
				},
			})
				.done(function(response) {
					AIPS.CampaignWizard.handleAjaxDone(response, done);
				})
				.fail(function() {
					AIPS.CampaignWizard.handleAjaxFailure(done);
				})
				.always(function() {
					$('#aips-campaign-spinner').removeClass('is-active');
				});
		},

		/**
		 * Handle a completed AJAX response.
		 *
		 * @param {Object}   response AJAX response.
		 * @param {Function} done     Completion callback.
		 * @return {void}
		 */
		handleAjaxDone: function(response, done) {
			if (response && response.success) {
				done(null, response.data || {});
				return;
			}

			done(new Error(this.getAjaxErrorMessage(response)));
		},

		/**
		 * Handle a failed AJAX request.
		 *
		 * @param {Function} done Completion callback.
		 * @return {void}
		 */
		handleAjaxFailure: function(done) {
			done(new Error(aipsAdminL10n.errorTryAgain || 'An error occurred.'));
		},

		/**
		 * Extract an AJAX error message from a response.
		 *
		 * @param {Object} response AJAX response.
		 * @return {string}
		 */
		getAjaxErrorMessage: function(response) {
			if (response && response.data && response.data.message) {
				return response.data.message;
			}

			return aipsAdminL10n.errorTryAgain || 'An error occurred.';
		},

		/**
		 * Render the review summary from plain text values.
		 *
		 * @param {Object} summary Summary data from the server.
		 * @return {void}
		 */
		renderSummary: function(summary) {
			var labels = {
				campaign_name: 'Campaign',
				content_goal: 'Goal',
				post_type: 'Post type',
				template: 'Template',
				frequency: 'Cadence',
				start_time: 'First run',
				review_policy: 'Review policy',
				post_status: 'Post status',
			};
			var $list = $('#aips-campaign-summary dl').empty();

			$.each(labels, function(key, label) {
				$(document.createElement('dt'))
					.append($(document.createElement('strong')).text(label))
					.appendTo($list);

				$(document.createElement('dd'))
					.text(AIPS.CampaignWizard.sanitizeText(summary && summary[key] ? summary[key] : ''))
					.appendTo($list);
			});
		},

		/**
		 * Show a wizard step by index.
		 *
		 * @param {number} index Step index.
		 * @return {void}
		 */
		showStep: function(index) {
			var stepCount = this.steps.length;
			this.currentStepIndex = Math.max(0, Math.min(index, stepCount - 1));

			var step = this.steps[this.currentStepIndex];
			$('.aips-wizard-step').hide().filter('[data-step="' + step + '"]').show();
			$('.aips-wizard-step-tab').removeClass('aips-btn-primary').addClass('aips-btn-secondary');
			$('.aips-wizard-step-tab[data-step="' + step + '"]').removeClass('aips-btn-secondary').addClass('aips-btn-primary');
			$('#aips-wizard-prev').prop('disabled', this.currentStepIndex === 0);
			$('#aips-wizard-next').toggle(this.currentStepIndex < stepCount - 1);
			$('#aips-wizard-finalize').toggle(this.currentStepIndex === stepCount - 1);

			if (step === 'confirm') {
				this.validateConfirmStep();
			}
		},

		/**
		 * Validate all steps before showing the confirm summary.
		 *
		 * @return {void}
		 */
		validateConfirmStep: function() {
			this.sendAjax('aips_campaign_wizard_validate_step', '', AIPS.CampaignWizard.onConfirmValidated);
		},

		/**
		 * Handle confirm-step validation.
		 *
		 * @param {Error|null} err Error object when validation fails.
		 * @param {Object}     out Response data.
		 * @return {void}
		 */
		onConfirmValidated: function(err, out) {
			if (err) {
				AIPS.CampaignWizard.showNotice('error', err.message);
				return;
			}

			AIPS.CampaignWizard.renderSummary(out.summary || AIPS.CampaignWizard.getPayload());
		},

		/**
		 * Handle the next button click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onNextClick: function(e) {
			e.preventDefault();

			AIPS.CampaignWizard.sendAjax(
				'aips_campaign_wizard_save_draft',
				AIPS.CampaignWizard.steps[AIPS.CampaignWizard.currentStepIndex],
				AIPS.CampaignWizard.onDraftSaved
			);
		},

		/**
		 * Handle successful draft save.
		 *
		 * @param {Error|null} err Error object when save fails.
		 * @param {Object}     out Response data.
		 * @return {void}
		 */
		onDraftSaved: function(err, out) {
			if (err) {
				AIPS.CampaignWizard.showNotice('error', err.message);
				return;
			}

			if (out.summary) {
				AIPS.CampaignWizard.renderSummary(out.summary);
			}

			AIPS.CampaignWizard.showNotice('success', out.message || 'Saved.');
			AIPS.CampaignWizard.showStep(AIPS.CampaignWizard.currentStepIndex + 1);
		},

		/**
		 * Handle the previous button click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onPreviousClick: function(e) {
			e.preventDefault();
			AIPS.CampaignWizard.showStep(AIPS.CampaignWizard.currentStepIndex - 1);
		},

		/**
		 * Handle a direct step-tab click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onStepTabClick: function(e) {
			e.preventDefault();

			var step = AIPS.CampaignWizard.sanitizeText($(this).data('step'));
			var index = AIPS.CampaignWizard.steps.indexOf(step);

			if (index >= 0) {
				AIPS.CampaignWizard.showStep(index);
			}
		},

		/**
		 * Handle the finalize button click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onFinalizeClick: function(e) {
			e.preventDefault();

			var message = aipsCampaignWizardL10n.confirmFinalize || 'Create this campaign and schedule it now?';
			var cancelLabel = aipsAdminL10n.confirmCancelButton || 'Cancel';

			AIPS.Utilities.confirm(message, 'Confirm', [
				{ label: cancelLabel, className: 'aips-btn aips-btn-secondary' },
				{
					label: 'Create Campaign',
					className: 'aips-btn aips-btn-primary',
					action: AIPS.CampaignWizard.finalizeCampaign,
				},
			]);
		},

		/**
		 * Finalize campaign creation after user confirmation.
		 *
		 * @return {void}
		 */
		finalizeCampaign: function() {
			AIPS.CampaignWizard.sendAjax(
				'aips_campaign_wizard_finalize',
				'',
				AIPS.CampaignWizard.onCampaignFinalized
			);
		},

		/**
		 * Handle finalized campaign response.
		 *
		 * @param {Error|null} err Error object when finalize fails.
		 * @param {Object}     out Response data.
		 * @return {void}
		 */
		onCampaignFinalized: function(err, out) {
			if (err) {
				AIPS.CampaignWizard.showNotice('error', err.message);
				return;
			}

			AIPS.CampaignWizard.showNotice(
				'success',
				out.message || aipsCampaignWizardL10n.created || 'Campaign created.'
			);

			if (out.redirect_url) {
				window.location.href = AIPS.CampaignWizard.sanitizeText(out.redirect_url);
			}
		},

		/**
		 * Render the initial server-provided summary, if available.
		 *
		 * @return {void}
		 */
		bootstrapSummary: function() {
			var summary = {};

			try {
				summary = JSON.parse($('#aips-campaign-summary-json').text() || '{}');
			} catch (e) {
				summary = {};
			}

			this.renderSummary(summary);
		},
	};

	$(document).ready(function() {
		AIPS.CampaignWizard.init();
	});

})(jQuery);
