import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseListView } from './base-list';

/**
 * Campaigns View
 * Combines campaign management listing with Campaign Creation Wizard.
 */
export const CampaignsView = BaseListView.extend({
	el: 'body',

	listSelector: '.aips-campaigns-table',
	rowSelector: 'tbody tr',

	steps: ['goal', 'template', 'defaults', 'schedule', 'review', 'confirm'],
	currentStepIndex: 0,
	pendingAiResult: null,

	events: _.extend({}, BaseListView.prototype.events, {
		// Campaign Listing Events
		'click .aips-toggle-campaign': 'handleToggleCampaign',
		'click .aips-duplicate-campaign': 'handleDuplicateCampaign',
		'click .aips-archive-campaign': 'handleArchiveCampaign',
		'click .aips-restore-campaign': 'handleRestoreCampaign',
		'click .aips-delete-campaign': 'handleDeleteCampaign',
		'click .aips-campaign-run-now': 'handleRunNow',
		'click .aips-link-existing-template-btn': 'handleLinkExistingTemplate',
		'click .aips-unlink-template-btn': 'handleUnlinkTemplate',

		// Wizard Events
		'click #aips-wizard-next': 'onNextClick',
		'click #aips-wizard-prev': 'onPreviousClick',
		'click .aips-wizard-step-tab': 'onStepTabClick',
		'click #aips-wizard-finalize': 'onFinalizeClick',
		'click #aips-add-post-type-rule': 'onAddPostTypeRule',
		'click .aips-remove-post-type-rule': 'onRemovePostTypeRule',
		'click #aips-ai-preview-accept': 'onAcceptAiPreview',
		'click #aips-ai-preview-regenerate': 'onRegenerateAiPreview',
		'click #aips-ai-preview-edit': 'onEditAiIntake',
		'click #aips-ai-preview-selective': 'onApplyAiSelective',
		'change input[name="review_policy"]': 'onReviewPolicyChange'
	}),

	initialize() {
		BaseListView.prototype.initialize.apply(this, arguments);

		if (this.isWizardPage()) {
			this.bootstrapSummary();
			this.showStep(0);

			if (!this.hasExistingDraft()) {
				this.showModeSelectionModal();
			}
		}
	},

	isCampaignsPage() {
		return this.$('.aips-admin-page').length && 
			(window.location.href.indexOf('aips-campaigns') > -1 || window.location.href.indexOf('aips-campaign-detail') > -1);
	},

	isWizardPage() {
		return this.$('#aips-campaign-wizard-form').length > 0;
	},

	// -----------------------------------------------------------------
	// Campaign Listing Handlers
	// -----------------------------------------------------------------

	handleToggleCampaign(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const campaignId = $button.data('campaign-id');
		const isActive = $button.data('is-active');
		const newStatus = isActive ? 0 : 1;
		const l10n = window.aipsCampaignsL10n || {};

		$button.prop('disabled', true);

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_toggle_campaign',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				campaign_id: campaignId,
				is_active: newStatus
			},
			success: (response) => {
				if (response.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message, 'success');
					}
					this.refreshPage();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || l10n.errorToggle || 'Failed to toggle campaign status.', 'error');
					}
					$button.prop('disabled', false);
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.errorNetwork || 'Network error.', 'error');
				}
				$button.prop('disabled', false);
			}
		});
	},

	handleDuplicateCampaign(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const campaignId = $button.data('campaign-id');
		const l10n = window.aipsCampaignsL10n || {};

		if (!confirm(l10n.confirmDuplicate || 'Duplicate this campaign?')) {
			return;
		}

		$button.prop('disabled', true);

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_duplicate_campaign',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				campaign_id: campaignId
			},
			success: (response) => {
				if (response.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message, 'success');
					}
					this.refreshPage();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || l10n.errorDuplicate || 'Failed to duplicate.', 'error');
					}
					$button.prop('disabled', false);
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.errorNetwork || 'Network error.', 'error');
				}
				$button.prop('disabled', false);
			}
		});
	},

	handleArchiveCampaign(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const campaignId = $button.data('campaign-id');
		const l10n = window.aipsCampaignsL10n || {};

		if (!confirm(l10n.confirmArchive || 'Archive this campaign?')) {
			return;
		}

		$button.prop('disabled', true);

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_archive_campaign',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				campaign_id: campaignId
			},
			success: (response) => {
				if (response.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message, 'success');
					}
					this.refreshPage();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || l10n.errorArchive || 'Failed to archive.', 'error');
					}
					$button.prop('disabled', false);
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.errorNetwork || 'Network error.', 'error');
				}
				$button.prop('disabled', false);
			}
		});
	},

	handleRestoreCampaign(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const campaignId = $button.data('campaign-id');
		const l10n = window.aipsCampaignsL10n || {};

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_restore_campaign',
			nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
			campaign_id: campaignId
		}).done((response) => {
			if (response.success) {
				this.refreshPage();
			} else {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(response.data.message || l10n.errorRestore || 'Failed to restore.', 'error');
				}
			}
		}).fail(() => {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.errorNetwork || 'Network error.', 'error');
			}
		});
	},

	handleRunNow(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const scheduleId = $button.data('schedule-id');
		const l10n = window.aipsCampaignsL10n || {};

		const errorMessage = l10n.errorRunNow || 'Failed to run campaign schedule.';
		const confirmMessage = l10n.confirmRunNow || 'Run this campaign schedule now? This will immediately generate content.';
		const successMessage = l10n.runNowSuccess || 'Campaign schedule completed.';

		if (!scheduleId) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(errorMessage, 'error');
			}
			return;
		}

		if (!confirm(confirmMessage)) {
			return;
		}

		$button.prop('disabled', true).addClass('is-busy');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_run_now',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				schedule_id: scheduleId
			},
			success: (response) => {
				if (response.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || successMessage, 'success');
					}
					this.refreshPage();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || errorMessage, 'error');
					}
					$button.prop('disabled', false).removeClass('is-busy');
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.errorNetwork || 'Network error.', 'error');
				}
				$button.prop('disabled', false).removeClass('is-busy');
			}
		});
	},

	handleDeleteCampaign(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const campaignId = $button.data('campaign-id');
		const l10n = window.aipsCampaignsL10n || {};

		if (!confirm(l10n.confirmDelete || 'Delete this campaign permanently?')) {
			return;
		}

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_delete_campaign',
			nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
			campaign_id: campaignId
		}).done((response) => {
			if (response.success) {
				this.refreshPage();
			} else {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(response.data.message || l10n.errorDelete || 'Failed to delete.', 'error');
				}
			}
		}).fail(() => {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.errorNetwork || 'Network error.', 'error');
			}
		});
	},

	handleLinkExistingTemplate(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const campaignId = $button.data('campaign-id');
		const templateId = this.$('#aips-add-existing-template-select').val();
		const l10n = window.aipsCampaignsL10n || {};

		if (!templateId) {
			alert(l10n.selectTemplate || 'Please select a template first.');
			return;
		}

		$button.prop('disabled', true);

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_link_existing_template',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				campaign_id: campaignId,
				template_id: templateId
			},
			success: (response) => {
				if (response.success) {
					this.refreshPage();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || 'Failed to link template.', 'error');
					}
					$button.prop('disabled', false);
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.errorNetwork || 'Network error.', 'error');
				}
				$button.prop('disabled', false);
			}
		});
	},

	handleUnlinkTemplate(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const campaignId = $button.data('campaign-id');
		const templateId = $button.data('template-id');
		const templateName = $button.data('template-name');
		const l10n = window.aipsCampaignsL10n || {};

		let confirmMessage = l10n.confirmUnlink || 'Are you sure you want to remove this template from this campaign?';
		confirmMessage = confirmMessage.replace('%s', templateName);

		if (!confirm(confirmMessage)) {
			return;
		}

		$button.prop('disabled', true);

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_unlink_template_from_campaign',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				campaign_id: campaignId,
				template_id: templateId
			},
			success: (response) => {
				if (response.success) {
					this.refreshPage();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || 'Failed to remove template.', 'error');
					}
					$button.prop('disabled', false);
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.errorNetwork || 'Network error.', 'error');
				}
				$button.prop('disabled', false);
			}
		});
	},

	// -----------------------------------------------------------------
	// Wizard Step Navigation and Validation Handlers
	// -----------------------------------------------------------------

	hasExistingDraft() {
		return this.sanitizePlainText(this.$('#aips_campaign_name').val()) !== '';
	},

	showModeSelectionModal() {
		const l10n = window.aipsCampaignWizardL10n || {};
		if (window.AIPS && window.AIPS.Utilities && typeof window.AIPS.Utilities.showModal === 'function') {
			window.AIPS.Utilities.showModal({
				heading: l10n.aiModeTitle || 'Campaign setup mode',
				message: l10n.aiModeMessage || 'Select your setup mode.',
				buttons: [
					{
						label: l10n.advancedModeTitle || 'Manual Setup',
						className: 'aips-btn aips-btn-secondary'
					},
					{
						label: l10n.aiModeButton || 'Guided AI Setup',
						className: 'aips-btn aips-btn-primary',
						action: this.showAiIntakeModal.bind(this)
					}
				]
			});
		}
	},

	getSelectOptions(selectSelector) {
		return this.$(selectSelector).find('option').map(function() {
			return { value: $(this).val(), label: $(this).text() };
		}).get();
	},

	showAiIntakeModal() {
		const l10n = window.aipsCampaignWizardL10n || {};
		const frequencyOptions = this.getSelectOptions('#aips_frequency');
		const postTypeOptions = this.getSelectOptions('#aips_post_type');
		const defaults = this.pendingAiResult && this.pendingAiResult.intake
			? this.pendingAiResult.intake
			: {};

		if (window.AIPS && window.AIPS.Utilities && typeof window.AIPS.Utilities.showModal === 'function') {
			window.AIPS.Utilities.showModal({
				heading: l10n.aiFormTitle || 'Guided AI Setup',
				fields: [
					{
						name: 'topic_niche',
						label: l10n.topicNicheLabel || 'Topic / Niche',
						type: 'text',
						required: true,
						value: defaults.topic_niche || '',
						description: l10n.topicNicheExample
					},
					{
						name: 'target_audience',
						label: l10n.targetAudienceLabel || 'Target Audience',
						type: 'text',
						required: true,
						value: defaults.target_audience || '',
						description: l10n.targetAudienceExample
					},
					{
						name: 'content_tone',
						label: l10n.contentToneLabel || 'Content Tone',
						type: 'select',
						required: true,
						options: [
							{ value: 'conversational', label: l10n.toneConversational || 'Conversational' },
							{ value: 'professional', label: l10n.toneProfessional || 'Professional' },
							{ value: 'technical', label: l10n.toneTechnical || 'Technical' },
							{ value: 'friendly', label: l10n.toneFriendly || 'Friendly' }
						],
						value: defaults.content_tone || 'conversational'
					},
					{
						name: 'publishing_goal',
						label: l10n.publishingGoalLabel || 'Publishing Goal',
						type: 'text',
						required: true,
						value: defaults.publishing_goal || '',
						description: l10n.publishingGoalExample
					},
					{
						name: 'output_style',
						label: l10n.outputStyleLabel || 'Output Style',
						type: 'select',
						required: true,
						options: [
							{ value: 'educational_tutorial', label: l10n.outputStyleEducational || 'Educational' },
							{ value: 'listicle', label: l10n.outputStyleListicle || 'Listicle' },
							{ value: 'comparison', label: l10n.outputStyleComparison || 'Comparison' },
							{ value: 'how_to_guide', label: l10n.outputStyleHowTo || 'How-To Guide' },
							{ value: 'opinion_editorial', label: l10n.outputStyleOpinion || 'Opinion' },
							{ value: 'faq_based', label: l10n.outputStyleFaq || 'FAQ-Based' },
							{ value: 'case_study_style', label: l10n.outputStyleCaseStudy || 'Case Study' },
							{ value: 'news_analysis', label: l10n.outputStyleNews || 'News Analysis' }
						],
						value: defaults.output_style || 'how_to_guide'
					},
					{
						name: 'frequency',
						label: l10n.preferredFrequencyLabel || 'Preferred Cadence',
						type: 'select',
						required: true,
						options: frequencyOptions,
						value: defaults.frequency || this.$('#aips_frequency').val() || 'daily'
					},
					{
						name: 'post_type',
						label: l10n.postTypeLabel || 'Post Type',
						type: 'select',
						required: true,
						options: postTypeOptions,
						value: defaults.post_type || this.$('#aips_post_type').val() || 'post'
					}
				],
				buttons: [
					{
						label: l10n.cancelButton || 'Cancel',
						className: 'aips-btn aips-btn-secondary'
					},
					{
						label: l10n.aiGenerateButton || 'Generate Strategy',
						className: 'aips-btn aips-btn-primary',
						submit: true,
						action: this.onAiIntakeSubmit.bind(this)
					}
				]
			});
		}
	},

	onAiIntakeSubmit(formData) {
		const l10n = window.aipsCampaignWizardL10n || {};
		this.showNotice('info', l10n.aiGeneratingMessage || 'Guided setup initializing...');

		this.sendAiAssistAjax(formData, (err, out) => {
			if (err) {
				this.showNotice('error', err.message);
				return;
			}

			this.pendingAiResult = {
				intake: this.sanitizeAiIntake(formData),
				draft: out.draft || {},
				summary: out.summary || {},
				preview: out.preview || {},
				message: out.message || l10n.aiSuccessMessage || 'Strategy generated successfully.'
			};
			this.renderStrategyPreview(this.pendingAiResult);
		});
	},

	onReviewPolicyChange(e) {
		const $radio = $(e.target);
		if (!$radio.length || !$radio.val()) {
			return;
		}
		this.$('[name="review_policy"]').prop('checked', false);
		$radio.prop('checked', true);
	},

	showNotice(type, message) {
		let noticeClass = 'notice notice-error';
		if (type === 'success') {
			noticeClass = 'notice notice-success';
		} else if (type === 'warning') {
			noticeClass = 'notice notice-warning';
		} else if (type === 'info') {
			noticeClass = 'notice notice-info';
		}
		const $notice = $(document.createElement('div')).addClass(noticeClass);
		const $message = $(document.createElement('p')).text(this.sanitizePlainText(message));

		this.$('#aips-campaign-wizard-notice').empty().append($notice.append($message));
		
		if (window.AIPS && window.AIPS.Utilities && typeof window.AIPS.Utilities.showToast === 'function') {
			window.AIPS.Utilities.showToast(this.sanitizePlainText(message), type);
		}
	},

	getPayload() {
		const data = {};
		this.$('#aips-campaign-wizard-form').serializeArray().forEach(item => {
			if (!item || !item.name) return;
			data[item.name] = this.sanitizeFieldValue(item.name, item.value);
		});
		data.is_active = this.$('#aips-campaign-wizard-form [name="is_active"]').is(':checked') ? 1 : 0;
		return data;
	},

	sanitizePlainText(value) {
		if (window.AIPS && window.AIPS.Utilities && typeof window.AIPS.Utilities.sanitizePlainText === 'function') {
			return window.AIPS.Utilities.sanitizePlainText(value);
		}
		if (typeof value === 'undefined' || value === null) return '';
		return String(value).replace(/[\u0000-\u001F\u007F]/g, '').trim();
	},

	sanitizeTextareaText(value) {
		if (window.AIPS && window.AIPS.Utilities && typeof window.AIPS.Utilities.sanitizeTextareaText === 'function') {
			return window.AIPS.Utilities.sanitizeTextareaText(value);
		}
		if (typeof value === 'undefined' || value === null) return '';
		return String(value).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, '').trim();
	},

	sanitizeFieldValue(name, value) {
		const $field = this.$('#aips-campaign-wizard-form [name="' + name + '"]').first();
		if ($field.is('textarea')) {
			return this.sanitizeTextareaText(value);
		}
		return this.sanitizePlainText(value);
	},

	sendAjax(action, step, done) {
		this.$('#aips-campaign-spinner').addClass('is-active');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: this.sanitizePlainText(action),
				nonce: this.sanitizePlainText((window.aipsAjax && window.aipsAjax.nonce) || ''),
				step: typeof step === 'undefined' ? this.steps[this.currentStepIndex] : this.sanitizePlainText(step),
				payload: JSON.stringify(this.getPayload())
			}
		}).done(response => {
			this.handleAjaxDone(response, done);
		}).fail(() => {
			this.handleAjaxFailure(done);
		}).always(() => {
			this.$('#aips-campaign-spinner').removeClass('is-active');
		});
	},

	sendAiAssistAjax(intake, done) {
		const l10n = window.aipsCampaignWizardL10n || {};
		this.$('#aips-campaign-spinner').addClass('is-active');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'aips_campaign_wizard_ai_generate',
				nonce: this.sanitizePlainText(l10n.campaignWizardAIGenerateNonce || ''),
				intake: JSON.stringify(this.sanitizeAiIntake(intake))
			}
		}).done(response => {
			this.handleAjaxDone(response, done);
		}).fail(() => {
			this.handleAjaxFailure(done);
		}).always(() => {
			this.$('#aips-campaign-spinner').removeClass('is-active');
		});
	},

	sanitizeAiIntake(intake) {
		return {
			topic_niche: this.sanitizePlainText(intake.topic_niche),
			target_audience: this.sanitizePlainText(intake.target_audience),
			content_tone: this.sanitizePlainText(intake.content_tone),
			publishing_goal: this.sanitizePlainText(intake.publishing_goal),
			output_style: this.sanitizePlainText(intake.output_style || 'how_to_guide'),
			frequency: this.sanitizePlainText(intake.frequency),
			post_type: this.sanitizePlainText(intake.post_type)
		};
	},

	renderStrategyPreview(aiResult) {
		const preview = aiResult && aiResult.preview ? aiResult.preview : {};
		const l10n = window.aipsCampaignWizardL10n || {};

		this.$('#aips-ai-strategy-preview-title').text(l10n.strategyPreviewTitle || 'Recommended Angle');
		this.$('#aips-ai-strategy-preview-message').text(l10n.strategyPreviewMessage || 'Summary checklist.');

		this.$('#aips-ai-preview-campaign-name').text(this.sanitizePlainText(preview.campaign_name || ''));
		this.$('#aips-ai-preview-audience').text(this.sanitizePlainText(preview.audience || ''));
		this.$('#aips-ai-preview-angle').text(this.sanitizePlainText(preview.content_angle || ''));
		this.$('#aips-ai-preview-cadence').text(this.sanitizePlainText(preview.posting_cadence || ''));
		this.$('#aips-ai-preview-tone').text(this.sanitizePlainText(preview.recommended_tone || ''));
		this.$('#aips-ai-preview-style').text(this.sanitizePlainText(preview.template_style || ''));

		this.renderPreviewList('#aips-ai-preview-ideas', preview.sample_article_ideas || []);
		this.renderPreviewList('#aips-ai-preview-risks', preview.risks_assumptions || []);

		this.$('#aips-ai-strategy-preview').show();
		$('html, body').animate({
			scrollTop: this.$('#aips-ai-strategy-preview').offset().top - 20
		}, 200);
	},

	renderPreviewList(listSelector, items) {
		const $list = this.$(listSelector).empty();
		const safeItems = $.isArray(items) ? items : [];
		const l10n = window.aipsCampaignWizardL10n || {};

		if (!safeItems.length) {
			$(document.createElement('li'))
				.text(l10n.previewNoData || 'No sample data.')
				.appendTo($list);
			return;
		}

		safeItems.forEach(item => {
			const safeText = this.sanitizePlainText(item);
			if (!safeText) return;
			$(document.createElement('li')).text(safeText).appendTo($list);
		});
	},

	hideStrategyPreview() {
		this.$('#aips-ai-strategy-preview').hide();
	},

	getSelectiveApplyFields() {
		const l10n = window.aipsCampaignWizardL10n || {};
		return [
			{ name: 'campaign_name', label: l10n.previewCampaignName || 'Campaign Name' },
			{ name: 'content_goal', label: l10n.previewContentAngle || 'Campaign Goal' },
			{ name: 'post_type', label: l10n.postTypeLabel || 'Post Type' },
			{ name: 'prompt_template', label: l10n.promptTemplateLabel || 'Prompt Template' },
			{ name: 'title_prompt', label: l10n.titlePromptLabel || 'Title Prompt' },
			{ name: 'frequency', label: l10n.previewCadence || 'Cadence' },
			{ name: 'review_policy', label: l10n.reviewPolicyLabel || 'Review Policy' },
			{ name: 'campaign_mode', label: l10n.campaignModeLabel || 'Campaign Mode' }
		];
	},

	applyPendingAiDraft(keys) {
		const l10n = window.aipsCampaignWizardL10n || {};
		if (!this.pendingAiResult || !this.pendingAiResult.draft) return;

		const draft = this.pendingAiResult.draft;
		let payload = draft;
		
		if ($.isArray(keys) && !keys.length) {
			this.showNotice('error', l10n.previewSelectRequired || 'Please check at least one.');
			return;
		}
		if ($.isArray(keys) && keys.length) {
			payload = {};
			for (let i = 0; i < keys.length; i++) {
				if (Object.prototype.hasOwnProperty.call(draft, keys[i])) {
					payload[keys[i]] = draft[keys[i]];
				}
			}
		}

		this.populateFieldsFromAi(payload);
		this.renderSummary(this.pendingAiResult.summary || this.getPayload());
		this.showNotice('success', this.pendingAiResult.message || l10n.aiSuccessMessage || 'Draft applied.');
		this.hideStrategyPreview();
		this.showStep(0);
	},

	onAcceptAiPreview(e) {
		e.preventDefault();
		this.applyPendingAiDraft(null);
	},

	onRegenerateAiPreview(e) {
		e.preventDefault();
		const l10n = window.aipsCampaignWizardL10n || {};
		if (!this.pendingAiResult || !this.pendingAiResult.intake) {
			this.showAiIntakeModal();
			return;
		}

		this.showNotice('info', l10n.regeneratingMessage || l10n.aiGeneratingMessage || 'Re-generating strategy...');
		this.sendAiAssistAjax(this.pendingAiResult.intake, (err, out) => {
			if (err) {
				this.showNotice('error', err.message);
				return;
			}

			this.pendingAiResult = {
				intake: this.pendingAiResult.intake,
				draft: out.draft || {},
				summary: out.summary || {},
				preview: out.preview || {},
				message: out.message || l10n.aiSuccessMessage || 'Strategy updated.'
			};
			this.renderStrategyPreview(this.pendingAiResult);
		});
	},

	onEditAiIntake(e) {
		e.preventDefault();
		this.showAiIntakeModal();
	},

	onApplyAiSelective(e) {
		e.preventDefault();
		const l10n = window.aipsCampaignWizardL10n || {};

		const fields = this.getSelectiveApplyFields().map(field => {
			return {
				name: field.name,
				label: field.label,
				type: 'checkbox',
				value: true
			};
		});

		if (window.AIPS && window.AIPS.Utilities && typeof window.AIPS.Utilities.showModal === 'function') {
			window.AIPS.Utilities.showModal({
				heading: l10n.previewSelectHeading || 'Selective Apply',
				fields: fields,
				buttons: [
					{
						label: l10n.cancelButton || 'Cancel',
						className: 'aips-btn aips-btn-secondary'
					},
					{
						label: l10n.previewApplyButton || 'Apply Selected',
						className: 'aips-btn aips-btn-primary',
						submit: true,
						action: (formData) => {
							const selectedKeys = [];
							$.each(formData || {}, (key, selected) => {
								if (selected) {
									selectedKeys.push(key);
								}
							});

							this.applyPendingAiDraft(selectedKeys);
						}
					}
				]
			});
		}
	},

	populateFieldsFromAi(draft) {
		$.each(draft, (fieldName, value) => {
			let sanitizedValue = value;
			if (typeof value === 'string') {
				if (fieldName === 'prompt_template' || fieldName === 'content_goal') {
					sanitizedValue = this.sanitizeTextareaText(value);
				} else {
					sanitizedValue = this.sanitizePlainText(value);
				}
			}

			if (fieldName === 'is_active') {
				this.populateCheckboxField('is_active', Number(value) === 1);
				return;
			}

			if (fieldName === 'day_preferences') {
				this.populateDayPreferences(value);
				return;
			}

			const $field = this.$('[name="' + fieldName + '"]');
			if (!$field.length) return;

			if ($field.is(':radio')) {
				this.populateRadioField(fieldName, sanitizedValue);
				return;
			}

			$field.val(sanitizedValue).trigger('change');
		});
	},

	populateCheckboxField(fieldName, checked) {
		this.$('[name="' + fieldName + '"]').prop('checked', checked).trigger('change');
	},

	populateRadioField(fieldName, value) {
		this.$('[name="' + fieldName + '"]').filter('[value="' + value + '"]').prop('checked', true).trigger('change');
	},

	populateDayPreferences(values) {
		this.$('[name="day_preferences[]"]').prop('checked', false);
		if (typeof values !== 'string' || values.length === 0) return;

		values.split(',').forEach(day => {
			const dayValue = this.sanitizePlainText(day);
			this.$('[name="day_preferences[]"][value="' + dayValue + '"]').prop('checked', true);
		});
	},

	handleAjaxDone(response, done) {
		if (response && response.success) {
			done(null, response.data || {});
			return;
		}
		done(new Error(this.getAjaxErrorMessage(response)));
	},

	handleAjaxFailure(done) {
		const l10n = window.aipsAdminL10n || {};
		done(new Error(l10n.errorTryAgain || 'An error occurred.'));
	},

	getAjaxErrorMessage(response) {
		const l10n = window.aipsAdminL10n || {};
		if (response && response.data && response.data.message) {
			return response.data.message;
		}
		return l10n.errorTryAgain || 'An error occurred.';
	},

	renderSummary(summary) {
		const labels = {
			campaign_name: 'Campaign',
			content_goal: 'Goal',
			post_type: 'Post type',
			template: 'Template',
			frequency: 'Cadence',
			start_time: 'First run',
			review_policy: 'Review policy',
			post_status: 'Post status'
		};
		const $list = this.$('#aips-campaign-summary dl').empty();

		$.each(labels, (key, label) => {
			$(document.createElement('dt'))
				.append($(document.createElement('strong')).text(label))
				.appendTo($list);

			$(document.createElement('dd'))
				.text(this.sanitizePlainText(summary && summary[key] ? summary[key] : ''))
				.appendTo($list);
		});
	},

	showStep(index) {
		const stepCount = this.steps.length;
		this.currentStepIndex = Math.max(0, Math.min(index, stepCount - 1));

		const step = this.steps[this.currentStepIndex];
		this.$('.aips-wizard-step').hide().filter('[data-step="' + step + '"]').show();
		this.$('.aips-wizard-step-tab').removeClass('aips-btn-primary').addClass('aips-btn-secondary');
		this.$('.aips-wizard-step-tab[data-step="' + step + '"]').removeClass('aips-btn-secondary').addClass('aips-btn-primary');
		this.$('#aips-wizard-prev').prop('disabled', this.currentStepIndex === 0);
		this.$('#aips-wizard-next').toggle(this.currentStepIndex < stepCount - 1);
		this.$('#aips-wizard-finalize').toggle(this.currentStepIndex === stepCount - 1);

		if (step === 'confirm') {
			this.validateConfirmStep();
		}
	},

	validateConfirmStep() {
		this.sendAjax('aips_campaign_wizard_validate_step', '', this.onConfirmValidated.bind(this));
	},

	onConfirmValidated(err, out) {
		if (err) {
			this.showNotice('error', err.message);
			return;
		}
		this.renderSummary(out.summary || this.getPayload());
	},

	onNextClick(e) {
		e.preventDefault();
		this.sendAjax(
			'aips_campaign_wizard_save_draft',
			this.steps[this.currentStepIndex],
			this.onDraftSaved.bind(this)
		);
	},

	onDraftSaved(err, out) {
		if (err) {
			this.showNotice('error', err.message);
			return;
		}
		if (out.summary) {
			this.renderSummary(out.summary);
		}
		this.showNotice('success', out.message || 'Saved.');
		this.showStep(this.currentStepIndex + 1);
	},

	onPreviousClick(e) {
		e.preventDefault();
		this.showStep(this.currentStepIndex - 1);
	},

	onStepTabClick(e) {
		e.preventDefault();
		const step = this.sanitizePlainText($(e.currentTarget).data('step'));
		const index = this.steps.indexOf(step);

		if (index >= 0) {
			this.showStep(index);
		}
	},

	onFinalizeClick(e) {
		e.preventDefault();
		const l10n = window.aipsCampaignWizardL10n || {};
		const adminL10n = window.aipsAdminL10n || {};

		const message = l10n.confirmFinalize || 'Create this campaign and schedule it now?';
		const cancelLabel = adminL10n.confirmCancelButton || 'Cancel';

		if (window.AIPS && window.AIPS.Utilities && typeof window.AIPS.Utilities.confirm === 'function') {
			window.AIPS.Utilities.confirm(message, 'Confirm', [
				{ label: cancelLabel, className: 'aips-btn aips-btn-secondary' },
				{
					label: 'Create Campaign',
					className: 'aips-btn aips-btn-primary',
					action: this.finalizeCampaign.bind(this)
				}
			]);
		}
	},

	finalizeCampaign() {
		this.sendAjax('aips_campaign_wizard_finalize', '', this.onCampaignFinalized.bind(this));
	},

	onCampaignFinalized(err, out) {
		const l10n = window.aipsCampaignWizardL10n || {};
		if (err) {
			this.showNotice('error', err.message);
			return;
		}

		this.showNotice('success', out.message || l10n.created || 'Campaign created.');

		if (out.redirect_url) {
			const redirectUrl = window.AIPS && window.AIPS.Utilities && typeof window.AIPS.Utilities.sanitizeUrl === 'function'
				? window.AIPS.Utilities.sanitizeUrl(out.redirect_url)
				: out.redirect_url;

			if (redirectUrl) {
				window.location.href = redirectUrl;
			}
		}
	},

	onAddPostTypeRule(e) {
		e.preventDefault();

		const $container = this.$('#aips-post-type-rules-container');
		const nextIndex = $container.find('.aips-post-type-rule').length;
		const postTypesJson = this.$('#aips_post_type').find('option').map(function() {
			return { value: $(this).val(), label: $(this).text() };
		}).get();

		const $newRule = $(
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

		const $select = $newRule.find('select');
		for (let i = 0; i < postTypesJson.length; i++) {
			$select.append('<option value="' + postTypesJson[i].value + '">' + postTypesJson[i].label + '</option>');
		}

		$container.append($newRule);
	},

	onRemovePostTypeRule(e) {
		e.preventDefault();
		$(e.currentTarget).closest('.aips-post-type-rule').remove();
	},

	bootstrapSummary() {
		let summary = {};
		try {
			summary = JSON.parse(this.$('#aips-campaign-summary-json').text() || '{}');
		} catch (e) {
			summary = {};
		}
		this.renderSummary(summary);
	},

	refreshPage() {
		window.location.reload();
	}
});
export default CampaignsView;
