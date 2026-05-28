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
		 * Most recent AI generation payload awaiting user confirmation.
		 *
		 * @type {?Object}
		 */
		pendingAiResult: null,

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

			if (!this.hasExistingDraft()) {
				this.showModeSelectionModal();
			}
		},

		/**
		 * Check whether an existing draft is already present.
		 *
		 * @return {boolean}
		 */
		hasExistingDraft: function() {
			return this.sanitizePlainText($('#aips_campaign_name').val()) !== '';
		},

		/**
		 * Open the initial mode-selection modal.
		 *
		 * @return {void}
		 */
		showModeSelectionModal: function() {
			AIPS.Utilities.showModal({
				heading: aipsCampaignWizardL10n.aiModeTitle,
				message: aipsCampaignWizardL10n.aiModeMessage,
				buttons: [
					{
						label: aipsCampaignWizardL10n.advancedModeTitle,
						className: 'aips-btn aips-btn-secondary',
					},
					{
						label: aipsCampaignWizardL10n.aiModeButton,
						className: 'aips-btn aips-btn-primary',
						action: AIPS.CampaignWizard.showAiIntakeModal,
					},
				],
			});
		},

		/**
		 * Return option objects for a select element.
		 *
		 * @param {string} selectSelector Select element selector.
		 * @return {Array<{value: string, label: string}>}
		 */
		getSelectOptions: function(selectSelector) {
			return $(selectSelector).find('option').map(function() {
				return { value: $(this).val(), label: $(this).text() };
			}).get();
		},

		/**
		 * Open Guided AI Setup intake form modal.
		 *
		 * @return {void}
		 */
		showAiIntakeModal: function() {
			var frequencyOptions = AIPS.CampaignWizard.getSelectOptions('#aips_frequency');
			var postTypeOptions = AIPS.CampaignWizard.getSelectOptions('#aips_post_type');
			var defaults = AIPS.CampaignWizard.pendingAiResult && AIPS.CampaignWizard.pendingAiResult.intake
				? AIPS.CampaignWizard.pendingAiResult.intake
				: {};

			AIPS.Utilities.showModal({
				heading: aipsCampaignWizardL10n.aiFormTitle,
				fields: [
					{
						name: 'topic_niche',
						label: aipsCampaignWizardL10n.topicNicheLabel,
						type: 'text',
						required: true,
						value: defaults.topic_niche || '',
						placeholder: 'WordPress SEO for local businesses',
						description: aipsCampaignWizardL10n.topicNicheExample,
					},
					{
						name: 'target_audience',
						label: aipsCampaignWizardL10n.targetAudienceLabel,
						type: 'text',
						required: true,
						value: defaults.target_audience || '',
						placeholder: 'Small business owners with limited technical knowledge',
						description: aipsCampaignWizardL10n.targetAudienceExample,
					},
					{
						name: 'content_tone',
						label: aipsCampaignWizardL10n.contentToneLabel,
						type: 'select',
						required: true,
						options: [
							{ value: 'conversational', label: aipsCampaignWizardL10n.toneConversational },
							{ value: 'professional', label: aipsCampaignWizardL10n.toneProfessional },
							{ value: 'technical', label: aipsCampaignWizardL10n.toneTechnical },
							{ value: 'friendly', label: aipsCampaignWizardL10n.toneFriendly },
						],
						value: defaults.content_tone || 'conversational',
					},
					{
						name: 'publishing_goal',
						label: aipsCampaignWizardL10n.publishingGoalLabel,
						type: 'text',
						required: true,
						value: defaults.publishing_goal || '',
						placeholder: 'Drive organic traffic and convert readers to consultation bookings',
						description: aipsCampaignWizardL10n.publishingGoalExample,
					},
					{
						name: 'output_style',
						label: aipsCampaignWizardL10n.outputStyleLabel,
						type: 'select',
						required: true,
						options: [
							{ value: 'educational_tutorial', label: aipsCampaignWizardL10n.outputStyleEducational },
							{ value: 'listicle', label: aipsCampaignWizardL10n.outputStyleListicle },
							{ value: 'comparison', label: aipsCampaignWizardL10n.outputStyleComparison },
							{ value: 'how_to_guide', label: aipsCampaignWizardL10n.outputStyleHowTo },
							{ value: 'opinion_editorial', label: aipsCampaignWizardL10n.outputStyleOpinion },
							{ value: 'faq_based', label: aipsCampaignWizardL10n.outputStyleFaq },
							{ value: 'case_study_style', label: aipsCampaignWizardL10n.outputStyleCaseStudy },
							{ value: 'news_analysis', label: aipsCampaignWizardL10n.outputStyleNews },
						],
						value: defaults.output_style || 'how_to_guide',
					},
					{
						name: 'frequency',
						label: aipsCampaignWizardL10n.preferredFrequencyLabel,
						type: 'select',
						required: true,
						options: frequencyOptions,
						value: defaults.frequency || $('#aips_frequency').val() || 'daily',
					},
					{
						name: 'post_type',
						label: aipsCampaignWizardL10n.postTypeLabel,
						type: 'select',
						required: true,
						options: postTypeOptions,
						value: defaults.post_type || $('#aips_post_type').val() || 'post',
					},
				],
				buttons: [
					{
						label: aipsCampaignWizardL10n.cancelButton,
						className: 'aips-btn aips-btn-secondary',
					},
					{
						label: aipsCampaignWizardL10n.aiGenerateButton,
						className: 'aips-btn aips-btn-primary',
						submit: true,
						action: AIPS.CampaignWizard.onAiIntakeSubmit,
					},
				],
			});
		},

		/**
		 * Handle AI intake submit action.
		 *
		 * @param {Object} formData AI intake form data.
		 * @return {void}
		 */
		onAiIntakeSubmit: function(formData) {
			AIPS.CampaignWizard.showNotice('success', aipsCampaignWizardL10n.aiGeneratingMessage);

			AIPS.CampaignWizard.sendAiAssistAjax(formData, function(err, out) {
				if (err) {
					AIPS.CampaignWizard.showNotice('error', err.message);
					return;
				}

				AIPS.CampaignWizard.pendingAiResult = {
					intake: AIPS.CampaignWizard.sanitizeAiIntake(formData),
					draft: out.draft || {},
					summary: out.summary || {},
					preview: out.preview || {},
					message: out.message || aipsCampaignWizardL10n.aiSuccessMessage,
				};
				AIPS.CampaignWizard.renderStrategyPreview(AIPS.CampaignWizard.pendingAiResult);
			});
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
			$(document).on('click', '#aips-add-post-type-rule', AIPS.CampaignWizard.onAddPostTypeRule);
			$(document).on('click', '.aips-remove-post-type-rule', AIPS.CampaignWizard.onRemovePostTypeRule);
			$(document).on('click', '#aips-ai-preview-accept', AIPS.CampaignWizard.onAcceptAiPreview);
			$(document).on('click', '#aips-ai-preview-regenerate', AIPS.CampaignWizard.onRegenerateAiPreview);
			$(document).on('click', '#aips-ai-preview-edit', AIPS.CampaignWizard.onEditAiIntake);
			$(document).on('click', '#aips-ai-preview-selective', AIPS.CampaignWizard.onApplyAiSelective);
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
			var $message = $(document.createElement('p')).text(this.sanitizePlainText(message));

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
				AIPS.Utilities.showToast(this.sanitizePlainText(message), type);
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

				data[item.name] = AIPS.CampaignWizard.sanitizeFieldValue(item.name, item.value);
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
		sanitizePlainText: function(value) {
			if (AIPS.Utilities && typeof AIPS.Utilities.sanitizePlainText === 'function') {
				return AIPS.Utilities.sanitizePlainText(value);
			}

			if (typeof value === 'undefined' || value === null) {
				return '';
			}

			return String(value).replace(/[\u0000-\u001F\u007F]/g, '').trim();
		},

		/**
		 * Sanitize textarea values while preserving line breaks and tabs.
		 *
		 * @param {*} value Value to sanitize.
		 * @return {string}
		 */
		sanitizeTextareaText: function(value) {
			if (AIPS.Utilities && typeof AIPS.Utilities.sanitizeTextareaText === 'function') {
				return AIPS.Utilities.sanitizeTextareaText(value);
			}

			if (typeof value === 'undefined' || value === null) {
				return '';
			}

			return String(value).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, '').trim();
		},

		/**
		 * Sanitize a form field based on its element type.
		 *
		 * @param {string} name  Field name.
		 * @param {*}      value Field value.
		 * @return {string}
		 */
		sanitizeFieldValue: function(name, value) {
			var $field = $('#aips-campaign-wizard-form')
				.find('[name]')
				.filter(function() {
					return this.name === name;
				})
				.first();

			if ($field.is('textarea')) {
				return this.sanitizeTextareaText(value);
			}

			return this.sanitizePlainText(value);
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
					action: this.sanitizePlainText(action),
					nonce: this.sanitizePlainText(aipsAjax.nonce),
					step: typeof step === 'undefined' ? this.steps[this.currentStepIndex] : this.sanitizePlainText(step),
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
		 * Send Guided AI Setup generation request.
		 *
		 * @param {Object}   intake AI intake form data.
		 * @param {Function} done   Completion callback.
		 * @return {void}
		 */
		sendAiAssistAjax: function(intake, done) {
			$('#aips-campaign-spinner').addClass('is-active');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_campaign_wizard_ai_generate',
					nonce: this.sanitizePlainText(aipsCampaignWizardL10n.nonceAiGenerate),
					intake: JSON.stringify(this.sanitizeAiIntake(intake)),
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
		 * Sanitize AI intake payload.
		 *
		 * @param {Object} intake Raw intake values.
		 * @return {Object}
		 */
		sanitizeAiIntake: function(intake) {
			return {
				topic_niche: this.sanitizePlainText(intake.topic_niche),
				target_audience: this.sanitizePlainText(intake.target_audience),
				content_tone: this.sanitizePlainText(intake.content_tone),
				publishing_goal: this.sanitizePlainText(intake.publishing_goal),
				output_style: this.sanitizePlainText(intake.output_style || 'how_to_guide'),
				frequency: this.sanitizePlainText(intake.frequency),
				post_type: this.sanitizePlainText(intake.post_type),
			};
		},

		/**
		 * Render AI strategy preview card prior to field hydration.
		 *
		 * @param {Object} aiResult AI result state.
		 * @return {void}
		 */
		renderStrategyPreview: function(aiResult) {
			var preview = aiResult && aiResult.preview ? aiResult.preview : {};
			$('#aips-ai-strategy-preview-title').text(aipsCampaignWizardL10n.strategyPreviewTitle);
			$('#aips-ai-strategy-preview-message').text(aipsCampaignWizardL10n.strategyPreviewMessage);

			$('#aips-ai-preview-campaign-name').text(this.sanitizePlainText(preview.campaign_name || ''));
			$('#aips-ai-preview-audience').text(this.sanitizePlainText(preview.audience || ''));
			$('#aips-ai-preview-angle').text(this.sanitizePlainText(preview.content_angle || ''));
			$('#aips-ai-preview-cadence').text(this.sanitizePlainText(preview.posting_cadence || ''));
			$('#aips-ai-preview-tone').text(this.sanitizePlainText(preview.recommended_tone || ''));
			$('#aips-ai-preview-style').text(this.sanitizePlainText(preview.template_style || ''));

			this.renderPreviewList('#aips-ai-preview-ideas', preview.sample_article_ideas || []);
			this.renderPreviewList('#aips-ai-preview-risks', preview.risks_assumptions || []);

			$('#aips-ai-strategy-preview').show();
			$('html, body').animate({
				scrollTop: $('#aips-ai-strategy-preview').offset().top - 20,
			}, 200);
		},

		/**
		 * Render sanitized preview list content.
		 *
		 * @param {string} listSelector UL selector.
		 * @param {Array<string>} items Preview list items.
		 * @return {void}
		 */
		renderPreviewList: function(listSelector, items) {
			var $list = $(listSelector).empty();
			var safeItems = $.isArray(items) ? items : [];

			if (!safeItems.length) {
				$(document.createElement('li'))
					.text(aipsCampaignWizardL10n.previewNoData)
					.appendTo($list);
				return;
			}

			$.each(safeItems, function(index, item) {
				var safeText = AIPS.CampaignWizard.sanitizePlainText(item);
				if (!safeText) {
					return;
				}
				$(document.createElement('li')).text(safeText).appendTo($list);
			});
		},

		/**
		 * Hide strategy preview panel.
		 *
		 * @return {void}
		 */
		hideStrategyPreview: function() {
			$('#aips-ai-strategy-preview').hide();
		},

		/**
		 * Return selectable AI-applied field definitions.
		 *
		 * @return {Array<Object>}
		 */
		getSelectiveApplyFields: function() {
			return [
				{ name: 'campaign_name', label: aipsCampaignWizardL10n.previewCampaignName },
				{ name: 'content_goal', label: aipsCampaignWizardL10n.previewContentAngle },
				{ name: 'post_type', label: aipsCampaignWizardL10n.postTypeLabel },
				{ name: 'prompt_template', label: 'Prompt Template' },
				{ name: 'title_prompt', label: 'Title Prompt' },
				{ name: 'frequency', label: aipsCampaignWizardL10n.previewCadence },
				{ name: 'review_policy', label: 'Review Policy' },
				{ name: 'campaign_mode', label: 'Campaign Mode' },
			];
		},

		/**
		 * Apply pending AI draft fields.
		 *
		 * @param {Array<string>|null} keys Keys to apply; when null applies all.
		 * @return {void}
		 */
		applyPendingAiDraft: function(keys) {
			if (!this.pendingAiResult || !this.pendingAiResult.draft) {
				return;
			}

			var draft = this.pendingAiResult.draft;
			var payload = draft;
			if ($.isArray(keys) && !keys.length) {
				this.showNotice('error', aipsCampaignWizardL10n.previewSelectRequired || 'Select at least one field.');
				return;
			}
			if ($.isArray(keys) && keys.length) {
				payload = {};
				for (var i = 0; i < keys.length; i++) {
					if (Object.prototype.hasOwnProperty.call(draft, keys[i])) {
						payload[keys[i]] = draft[keys[i]];
					}
				}
			}

			this.populateFieldsFromAi(payload);
			this.renderSummary(this.pendingAiResult.summary || this.getPayload());
			this.showNotice('success', this.pendingAiResult.message || aipsCampaignWizardL10n.aiSuccessMessage);
			this.hideStrategyPreview();
			this.showStep(0);
		},

		/**
		 * Handle preview "Accept all" action.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onAcceptAiPreview: function(e) {
			e.preventDefault();
			AIPS.CampaignWizard.applyPendingAiDraft(null);
		},

		/**
		 * Handle preview "Regenerate" action.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onRegenerateAiPreview: function(e) {
			e.preventDefault();
			if (!AIPS.CampaignWizard.pendingAiResult || !AIPS.CampaignWizard.pendingAiResult.intake) {
				AIPS.CampaignWizard.showAiIntakeModal();
				return;
			}

			AIPS.CampaignWizard.showNotice('success', aipsCampaignWizardL10n.regeneratingMessage || aipsCampaignWizardL10n.aiGeneratingMessage);
			AIPS.CampaignWizard.sendAiAssistAjax(AIPS.CampaignWizard.pendingAiResult.intake, function(err, out) {
				if (err) {
					AIPS.CampaignWizard.showNotice('error', err.message);
					return;
				}

				AIPS.CampaignWizard.pendingAiResult = {
					intake: AIPS.CampaignWizard.pendingAiResult.intake,
					draft: out.draft || {},
					summary: out.summary || {},
					preview: out.preview || {},
					message: out.message || aipsCampaignWizardL10n.aiSuccessMessage,
				};
				AIPS.CampaignWizard.renderStrategyPreview(AIPS.CampaignWizard.pendingAiResult);
			});
		},

		/**
		 * Handle preview "Edit answers" action.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onEditAiIntake: function(e) {
			e.preventDefault();
			AIPS.CampaignWizard.showAiIntakeModal();
		},

		/**
		 * Handle preview "Apply selectively" action.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onApplyAiSelective: function(e) {
			e.preventDefault();

			var fields = AIPS.CampaignWizard.getSelectiveApplyFields().map(function(field) {
				return {
					name: field.name,
					label: field.label,
					type: 'checkbox',
					value: true,
				};
			});

			AIPS.Utilities.showModal({
				heading: aipsCampaignWizardL10n.previewSelectHeading,
				fields: fields,
				buttons: [
					{
						label: aipsCampaignWizardL10n.cancelButton,
						className: 'aips-btn aips-btn-secondary',
					},
					{
						label: aipsCampaignWizardL10n.previewApplyButton,
						className: 'aips-btn aips-btn-primary',
						submit: true,
						action: function(formData) {
							var selectedKeys = [];
							$.each(formData || {}, function(key, selected) {
								if (selected) {
									selectedKeys.push(key);
								}
							});

							AIPS.CampaignWizard.applyPendingAiDraft(selectedKeys);
						},
					},
				],
			});
		},

		/**
		 * Populate wizard fields from AI-generated payload.
		 *
		 * @param {Object} draft AI-generated draft payload keyed by wizard field
		 *                       names (e.g. campaign_name, content_goal,
		 *                       prompt_template, review_policy, is_active).
		 * @return {void}
		 */
		populateFieldsFromAi: function(draft) {
			var self = this;

			$.each(draft, function(fieldName, value) {
				var sanitizedValue = value;
				if (typeof value === 'string') {
					if (fieldName === 'prompt_template' || fieldName === 'content_goal') {
						sanitizedValue = self.sanitizeTextareaText(value);
					} else {
						sanitizedValue = self.sanitizePlainText(value);
					}
				}

				if (fieldName === 'is_active') {
					self.populateCheckboxField('is_active', Number(value) === 1);
					return;
				}

				if (fieldName === 'day_preferences') {
					self.populateDayPreferences(value);
					return;
				}

				var $field = $('[name="' + fieldName + '"]');
				if (!$field.length) {
					return;
				}

				if ($field.is(':radio')) {
					self.populateRadioField(fieldName, sanitizedValue);
					return;
				}

				$field.val(sanitizedValue).trigger('change');
			});
		},

		/**
		 * Populate a checkbox field and trigger change.
		 *
		 * @param {string} fieldName Field name.
		 * @param {boolean} checked Whether checkbox is checked.
		 * @return {void}
		 */
		populateCheckboxField: function(fieldName, checked) {
			$('[name="' + fieldName + '"]').prop('checked', checked).trigger('change');
		},

		/**
		 * Populate a radio field and trigger change.
		 *
		 * @param {string} fieldName Field name.
		 * @param {string} value Field value.
		 * @return {void}
		 */
		populateRadioField: function(fieldName, value) {
			$('[name="' + fieldName + '"]').filter('[value="' + value + '"]').prop('checked', true).trigger('change');
		},

		/**
		 * Populate day preference checkboxes from comma-separated values.
		 *
		 * @param {string} values Comma-separated day numbers.
		 * @return {void}
		 */
		populateDayPreferences: function(values) {
			var self = this;
			$('[name="day_preferences[]"]').prop('checked', false);
			if (typeof values !== 'string' || values.length === 0) {
				return;
			}

			values.split(',').forEach(function(day) {
				var dayValue = self.sanitizePlainText(day);
				$('[name="day_preferences[]"][value="' + dayValue + '"]').prop('checked', true);
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
					.text(AIPS.CampaignWizard.sanitizePlainText(summary && summary[key] ? summary[key] : ''))
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

			var step = AIPS.CampaignWizard.sanitizePlainText($(this).data('step'));
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
				var redirectUrl = AIPS.Utilities && typeof AIPS.Utilities.sanitizeUrl === 'function'
					? AIPS.Utilities.sanitizeUrl(out.redirect_url)
					: out.redirect_url;

				if (redirectUrl) {
					window.location.href = redirectUrl;
				}
			}
		},

		/**
		 * Handle adding a new post-type rule row.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onAddPostTypeRule: function(e) {
			e.preventDefault();

			var $container = $('#aips-post-type-rules-container');
			var nextIndex = $container.find('.aips-post-type-rule').length;
			var postTypesJson = $('#aips_post_type').find('option').map(function() {
				return { value: $(this).val(), label: $(this).text() };
			}).get();

			var $newRule = $(
				'<div class="aips-post-type-rule" data-rule-index="' + nextIndex + '" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 12px; margin-bottom: 10px;">' +
					'<div style="display: grid; grid-template-columns: 1fr 1fr 80px 50px; gap: 10px; align-items: start;">' +
						'<div>' +
							'<label>Post Type</label>' +
							'<select name="post_type_rules[' + nextIndex + '][post_type]" class="regular-text"></select>' +
						'</div>' +
						'<div>' +
							'<label>Prompt Override (Optional)</label>' +
							'<input type="text" name="post_type_rules[' + nextIndex + '][prompt_override]" class="regular-text" placeholder="Leave empty to use main template">' +
						'</div>' +
						'<div>' +
							'<label>Quantity</label>' +
							'<input type="number" name="post_type_rules[' + nextIndex + '][quantity]" min="1" max="100" value="1" style="width: 100%;">' +
						'</div>' +
						'<div style="padding-top: 20px;">' +
							'<button type="button" class="button button-small aips-remove-post-type-rule" title="Remove">' +
								'<span class="dashicons dashicons-no-alt" style="margin-top: 2px;"></span>' +
							'</button>' +
						'</div>' +
					'</div>' +
				'</div>'
			);

			var $select = $newRule.find('select');
			for (var i = 0; i < postTypesJson.length; i++) {
				$select.append('<option value="' + postTypesJson[i].value + '">' + postTypesJson[i].label + '</option>');
			}

			$container.append($newRule);
		},

		/**
		 * Handle removing a post-type rule row.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onRemovePostTypeRule: function(e) {
			e.preventDefault();
			$(this).closest('.aips-post-type-rule').remove();
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
