/**
 * AI Assistance JavaScript
 *
 * Provides a reusable AI field-assist system that injects sparkle buttons
 * next to form fields, fetches AI suggestions on click, and maintains a
 * suggestion history accessible via a clock/backup icon.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.2
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * Field maps for AI assistance, keyed by form context.
	 * Each entry maps a field's HTML id to its metadata.
	 *
	 * @type {Object}
	 */
	var AIPS_FIELD_MAPS = {
		authors: {
			author_name: {
				fieldName:        'Name',
				description:      'The display name for this AI author persona',
				influence:        'Sets the author byline on generated posts; used in topic-generation prompts as the attributed author',
				expectedResponse: 'A short, memorable author pen name (1–3 words)',
			},
			author_field_niche: {
				fieldName:        'Field/Niche',
				description:      'The subject-matter niche this author specialises in',
				influence:        'Focuses AI topic generation and content toward this niche',
				expectedResponse: 'A concise niche description (1–5 words, e.g. "Personal Finance for Millennials")',
			},
			author_keywords: {
				fieldName:        'Keywords',
				description:      'Comma-separated keywords associated with this author\'s content',
				influence:        'Guides keyword selection during post and topic generation',
				expectedResponse: 'A comma-separated list of 5–10 relevant keywords',
			},
			author_details: {
				fieldName:        'Details',
				description:      'Short background details about this author persona',
				influence:        'Provides context to the AI when generating posts in the author\'s voice',
				expectedResponse: 'Two to four sentences describing the author\'s background and expertise',
			},
			author_description: {
				fieldName:        'Description',
				description:      'A longer public-facing bio or description for this author',
				influence:        'May be appended to generated posts as an author bio section',
				expectedResponse: 'A 3–5 sentence author biography in first or third person',
			},
			voice_tone: {
				fieldName:        'Tone',
				description:      'The emotional tone this author uses when writing',
				influence:        'Instructs the AI to match this tone throughout every generated post',
				expectedResponse: 'One to three descriptive words (e.g. "friendly, authoritative, approachable")',
			},
			writing_style: {
				fieldName:        'Writing Style',
				description:      'The structural and stylistic approach this author takes',
				influence:        'Shapes sentence length, vocabulary, and narrative style in AI-generated content',
				expectedResponse: 'A brief style description (e.g. "conversational with data-driven examples")',
			},
			author_target_audience: {
				fieldName:        'Target Audience',
				description:      'The intended readership for this author\'s content',
				influence:        'Tailors complexity, vocabulary, and examples to this audience in generated posts',
				expectedResponse: 'A concise audience description (e.g. "beginner home cooks aged 25–45")',
			},
			author_content_goals: {
				fieldName:        'Content Goals',
				description:      'The primary objectives this author\'s content should achieve',
				influence:        'Aligns AI-generated post structure and calls-to-action with these goals',
				expectedResponse: 'One to three goal statements (e.g. "educate readers, drive newsletter signups")',
			},
			author_excluded_topics: {
				fieldName:        'Excluded Topics',
				description:      'Topics or subject areas this author should never write about',
				influence:        'Prevents the AI from generating posts or topics in these areas',
				expectedResponse: 'A comma-separated list of excluded topics or themes',
			},
		},
	};

	/**
	 * AIPS.AIAssistance module.
	 *
	 * Manages AI field-assist buttons, suggestion fetching, and history modal.
	 *
	 * @namespace AIPS.AIAssistance
	 */
	AIPS.AIAssistance = {

		/**
		 * Unique session identifier generated once per page load.
		 *
		 * @type {string}
		 */
		sessionId: '',

		/**
		 * The form context identifier used for all requests on this page.
		 *
		 * @type {string}
		 */
		formContext: 'authors',

		/**
		 * Initialise the module: generate session ID, inject buttons, bind events.
		 *
		 * @return {void}
		 */
		init: function () {
			this.sessionId = (typeof crypto !== 'undefined' && crypto.randomUUID)
			? crypto.randomUUID()
			: Date.now() + Math.random().toString(36).slice(2, 11);
			this.injectButtons();
			this.bindEvents();
		},

		/**
		 * Inject AI assist + history buttons after each supported form field.
		 *
		 * Iterates over the field map for the current form context and appends
		 * the button group HTML immediately after the input/textarea element,
		 * before any .description paragraph.
		 *
		 * @return {void}
		 */
		injectButtons: function () {
			var fieldMap = AIPS_FIELD_MAPS[this.formContext];
			if (!fieldMap) {
				return;
			}

			$.each(fieldMap, function (fieldId) {
				var $field = $('#' + fieldId);
				if (!$field.length) {
					return; // skip if field not in DOM
				}

				var $formGroup = $field.closest('.form-group');
				if ($formGroup.length) {
					$formGroup.addClass('aips-ai-assist-wrap');
				}

				var btnHtml = AIPS.Templates.renderRaw('aips-tmpl-ai-assist-btn', { fieldId: fieldId });
				var $description = $field.nextAll('.description').first();

				if ($description.length) {
					$description.before(btnHtml);
				} else {
					$field.after(btnHtml);
				}
			});
		},

		/**
		 * Bind all event listeners for the AI assistance module.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			$(document).on('click', '.aips-ai-assist-btn', this.onAssistClick.bind(this));
			$(document).on('click', '.aips-ai-assist-history-btn', this.onHistoryClick.bind(this));
			$(document).on('click', '#aips-ai-assist-history-modal .aips-modal-close', this.closeHistoryModal.bind(this));
			$(document).on('click', '.aips-ai-assist-history-use', this.useHistoryValue.bind(this));

			// Tab switching inside the history modal
			$(document).on('click', '.aips-tab-link[data-assist-tab]', function (e) {
				e.preventDefault();
				var tab = $(this).data('assist-tab');
				$('.aips-tab-link[data-assist-tab]').removeClass('active');
				$(this).addClass('active');
				$('.aips-ai-assist-tab-content').hide();
				$('#aips-ai-assist-history-' + tab).show();
			});
		},

		/**
		 * Handle a click on an AI suggest button.
		 *
		 * Reads field configuration, sends an AJAX request to the AI service,
		 * and fills the associated field with the suggestion on success.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onAssistClick: function (e) {
			e.preventDefault();
			var self = this;
			var $btn = $(e.currentTarget);
			var fieldId = $btn.data('field-id');
			var fieldConfig = AIPS_FIELD_MAPS[this.formContext][fieldId];

			if (!fieldConfig) {
				return;
			}

			var $field = $('#' + fieldId);
			var currentValue = $field.val() || '';
			var authorName = $('#author_name').val() || '';
			var fieldNiche = $('#author_field_niche').val() || '';

			// Loading state
			$btn.prop('disabled', true).addClass('loading');
			var $label = $btn.find('.aips-ai-assist-btn-label');
			var originalLabel = $label.text();
			$label.text(
				(typeof aipsAIAssistanceL10n !== 'undefined')
					? aipsAIAssistanceL10n.suggesting
					: 'Suggesting\u2026'
			);

			$.post(
				ajaxurl,
				{
					action:            'aips_ai_field_assist',
					nonce:             (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.nonce : '',
					form_context:      self.formContext,
					field_key:         fieldId,
					field_name:        fieldConfig.fieldName,
					description:       fieldConfig.description,
					influence:         fieldConfig.influence,
					expected_response: fieldConfig.expectedResponse,
					current_value:     currentValue,
					session_id:        self.sessionId,
					author_name:       authorName,
					field_niche:       fieldNiche,
				},
				function (response) {
					$btn.prop('disabled', false).removeClass('loading');
					$label.text(originalLabel);

					if (response.success && response.data && response.data.response) {
						$field.val(response.data.response).trigger('change');

						// Show the history button for this field
						$btn.closest('.aips-ai-assist-btn-group').find('.aips-ai-assist-history-btn[data-field-id="' + fieldId + '"]').show();

						if (typeof AIPS.Utilities !== 'undefined' && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(
								(typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.suggested : 'AI suggestion applied.',
								'success'
							);
						}
					} else {
						var errMsg = (response.data && response.data.message)
							? response.data.message
							: ((typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.errorSuggesting : 'Could not get AI suggestion.');

						if (typeof AIPS.Utilities !== 'undefined' && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(errMsg, 'error');
						}
					}
				}
			).fail(function () {
				$btn.prop('disabled', false).removeClass('loading');
				$label.text(originalLabel);
				if (typeof AIPS.Utilities !== 'undefined' && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast(
						(typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.errorSuggesting : 'Could not get AI suggestion.',
						'error'
					);
				}
			});
		},

		/**
		 * Handle a click on a history (backup icon) button.
		 *
		 * Fetches session and all-time suggestion history via AJAX and renders
		 * it inside the history modal.
		 *
		 * @param {jQuery.Event} e Click event.
		 * @return {void}
		 */
		onHistoryClick: function (e) {
			e.preventDefault();
			var self = this;
			var $btn = $(e.currentTarget);
			var fieldId = $btn.data('field-id');
			var fieldConfig = AIPS_FIELD_MAPS[this.formContext][fieldId];
			var fieldName = fieldConfig ? fieldConfig.fieldName : fieldId;

			// Show modal with loading state
			var $modal = $('#aips-ai-assist-history-modal');
			$modal.show();
			$('#aips-ai-assist-history-field-label').text(fieldName);
			$('#aips-ai-assist-history-session').html('<p class="description">' + ( (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.loading : 'Loading\u2026' ) + '</p>');
			$('#aips-ai-assist-history-alltime').html('<p class="description">' + ( (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.loading : 'Loading\u2026' ) + '</p>');

			// Reset tabs
			$('.aips-tab-link[data-assist-tab="session"]').addClass('active');
			$('.aips-tab-link[data-assist-tab="alltime"]').removeClass('active');
			$('#aips-ai-assist-history-session').show();
			$('#aips-ai-assist-history-alltime').hide();

			$.post(
				ajaxurl,
				{
					action:       'aips_get_field_assist_history',
					nonce:        (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.nonce : '',
					form_context: self.formContext,
					field_key:    fieldId,
					session_id:   self.sessionId,
				},
				function (response) {
					if (response.success && response.data) {
						self.renderHistoryTab( '#aips-ai-assist-history-session', response.data.session, fieldId );
						self.renderHistoryTab( '#aips-ai-assist-history-alltime', response.data.alltime, fieldId );
					} else {
						var noHistoryMsg = (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.noHistory : 'No AI suggestions found for this field yet.';
						$('#aips-ai-assist-history-session').html('<p class="description">' + noHistoryMsg + '</p>');
						$('#aips-ai-assist-history-alltime').html('<p class="description">' + noHistoryMsg + '</p>');
					}
				}
			).fail(function () {
				var noHistoryMsg = (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.noHistory : 'No AI suggestions found for this field yet.';
				$('#aips-ai-assist-history-session').html('<p class="description">' + noHistoryMsg + '</p>');
				$('#aips-ai-assist-history-alltime').html('<p class="description">' + noHistoryMsg + '</p>');
			});
		},

		/**
		 * Render a list of suggestion records into a history tab container.
		 *
		 * @param {string} selector CSS selector for the tab content element.
		 * @param {Array}  records  Array of record objects from the server.
		 * @param {string} fieldId  The field ID these records belong to.
		 * @return {void}
		 */
		renderHistoryTab: function (selector, records, fieldId) {
			var $container = $(selector);
			var noHistoryMsg = (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.noHistory : 'No AI suggestions found for this field yet.';

			if (!records || !records.length) {
				$container.html('<p class="description">' + noHistoryMsg + '</p>');
				return;
			}

			var html = '';
			$.each(records, function (i, record) {
				html += AIPS.Templates.renderRaw('aips-tmpl-ai-assist-history-item', {
					response:   record.response,
					created_at: record.created_at,
					fieldId:    fieldId,
				});
			});
			$container.html(html);
		},

		/**
		 * Apply a value from the history modal to the target field.
		 *
		 * @param {jQuery.Event} e Click event on a "Use This Value" button.
		 * @return {void}
		 */
		useHistoryValue: function (e) {
			e.preventDefault();
			var $btn    = $(e.currentTarget);
			var fieldId = $btn.data('field-id');
			var value   = $btn.data('value');

			$('#' + fieldId).val(value).trigger('change');
			this.closeHistoryModal();

			if (typeof AIPS.Utilities !== 'undefined' && AIPS.Utilities.showToast) {
				AIPS.Utilities.showToast(
					(typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.valueApplied : 'Value applied from history.',
					'success'
				);
			}
		},

		/**
		 * Close the AI suggestion history modal.
		 *
		 * @return {void}
		 */
		closeHistoryModal: function () {
			$('#aips-ai-assist-history-modal').hide();
		},
	};

	$(document).ready(function () {
		AIPS.AIAssistance.init();
	});

})(jQuery);
