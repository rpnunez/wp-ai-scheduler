/**
 * AI Assistance JavaScript
 *
 * Provides a reusable AI field-assist system that injects sparkle buttons
 * next to form fields, fetches AI suggestions on click, and maintains a
 * suggestion history accessible via a clock/backup icon.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.1
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * In-memory cache mapping record IDs to their raw AI response text.
	 * Populated by renderHistoryTab(); used by useHistoryValue() to avoid
	 * storing unescaped AI content in DOM attributes.
	 *
	 * @type {Object<string, string>}
	 */
	var _historyRecordCache = {};

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
				contextFields:    [],
			},
			author_field_niche: {
				fieldName:        'Field/Niche',
				description:      'The subject-matter niche this author specialises in',
				influence:        'Focuses AI topic generation and content toward this niche',
				expectedResponse: 'A concise niche description (1–5 words, e.g. "Personal Finance for Millennials")',
				contextFields:    [],
			},
			author_keywords: {
				fieldName:        'Keywords',
				description:      'Comma-separated keywords associated with this author\'s content',
				influence:        'Guides keyword selection during post and topic generation',
				expectedResponse: 'A comma-separated list of 5–10 relevant keywords',
				contextFields:    [ 'author_name', 'author_field_niche' ],
			},
			author_details: {
				fieldName:        'Details',
				description:      'Short background details about this author persona',
				influence:        'Provides context to the AI when generating posts in the author\'s voice',
				expectedResponse: 'Two to four sentences describing the author\'s background and expertise',
				contextFields:    [ 'author_name', 'author_field_niche' ],
			},
			author_description: {
				fieldName:        'Description',
				description:      'A longer public-facing bio or description for this author',
				influence:        'May be appended to generated posts as an author bio section',
				expectedResponse: 'A 3–5 sentence author biography in first or third person',
				contextFields:    [ 'author_name', 'author_field_niche' ],
			},
			voice_tone: {
				fieldName:        'Tone',
				description:      'The emotional tone this author uses when writing',
				influence:        'Instructs the AI to match this tone throughout every generated post',
				expectedResponse: 'One to three descriptive words (e.g. "friendly, authoritative, approachable")',
				contextFields:    [ 'author_name', 'author_field_niche' ],
			},
			writing_style: {
				fieldName:        'Writing Style',
				description:      'The structural and stylistic approach this author takes',
				influence:        'Shapes sentence length, vocabulary, and narrative style in AI-generated content',
				expectedResponse: 'A brief style description (e.g. "conversational with data-driven examples")',
				contextFields:    [ 'author_name', 'author_field_niche' ],
			},
			author_target_audience: {
				fieldName:        'Target Audience',
				description:      'The intended readership for this author\'s content',
				influence:        'Tailors complexity, vocabulary, and examples to this audience in generated posts',
				expectedResponse: 'A concise audience description (e.g. "beginner home cooks aged 25–45")',
				contextFields:    [ 'author_name', 'author_field_niche' ],
			},
			author_content_goals: {
				fieldName:        'Content Goals',
				description:      'The primary objectives this author\'s content should achieve',
				influence:        'Aligns AI-generated post structure and calls-to-action with these goals',
				expectedResponse: 'One to three goal statements (e.g. "educate readers, drive newsletter signups")',
				contextFields:    [ 'author_name', 'author_field_niche' ],
			},
			author_excluded_topics: {
				fieldName:        'Excluded Topics',
				description:      'Topics or subject areas this author should never write about',
				influence:        'Prevents the AI from generating posts or topics in these areas',
				expectedResponse: 'A comma-separated list of excluded topics or themes',
				contextFields:    [ 'author_name', 'author_field_niche' ],
			},
			'aips-suggest-site-niche': {
				fieldName:        'Site Niche / Primary Topic',
				description:      'The main topic or industry the site covers',
				influence:        'Steers which author personas the AI suggests in this bulk-suggestion request',
				expectedResponse: 'A concise niche description (1-5 words)',
				contextFields:    [],
			},
			'aips-suggest-target-audience': {
				fieldName:        'Target Audience',
				description:      'Who the suggested authors\' content should be written for',
				influence:        'Steers which author personas the AI suggests in this bulk-suggestion request',
				expectedResponse: 'A concise audience description',
				contextFields:    [ 'aips-suggest-site-niche' ],
			},
			'aips-suggest-content-goals': {
				fieldName:        'Content Goals',
				description:      'What the suggested authors\' content should achieve',
				influence:        'Steers which author personas the AI suggests in this bulk-suggestion request',
				expectedResponse: 'One to three goal statements',
				contextFields:    [ 'aips-suggest-site-niche', 'aips-suggest-target-audience' ],
			},
		},
		templates: {
			template_name: {
				fieldName:        'Template Name',
				description:      'A descriptive name for this content-generation template',
				influence:        'Used only to identify the template in lists; does not affect generated content',
				expectedResponse: 'A short, descriptive template name (e.g. "Weekly Tech News Roundup")',
				contextFields:    [],
			},
			template_description: {
				fieldName:        'Template Description',
				description:      'Optional notes about what this template is used for',
				influence:        'Used only to help identify the template later; does not affect generated content',
				expectedResponse: 'One to two sentences describing the template\'s purpose',
				contextFields:    [ 'template_name' ],
			},
			title_prompt: {
				fieldName:        'Title Prompt',
				description:      'Instructions telling the AI how to generate post titles',
				influence:        'Directly shapes how titles are generated for every post created from this template',
				expectedResponse: 'A short instruction for generating titles, optionally using {{AI variable}} placeholders',
				contextFields:    [ 'template_name', 'prompt_template' ],
			},
			prompt_template: {
				fieldName:        'Content Prompt',
				description:      'The master prompt instructing the AI what content to generate',
				influence:        'Directly determines the structure, topic, and style of every post generated from this template',
				expectedResponse: 'A detailed, specific content prompt (topic, style, length, audience)',
				contextFields:    [ 'template_name', 'template_description' ],
			},
			image_prompt: {
				fieldName:        'Image Prompt',
				description:      'Instructions describing the featured image to generate with AI',
				influence:        'Directly shapes the AI-generated featured image for every post created from this template',
				expectedResponse: 'A vivid, descriptive image prompt',
				contextFields:    [ 'template_name', 'prompt_template' ],
			},
		},
		sources: {
			'aips-source-label': {
				fieldName:        'Label',
				description:      'A short name identifying this content source',
				influence:        'Used only to identify the source in lists; does not affect fetched content',
				expectedResponse: 'A short, descriptive label (2-5 words)',
				contextFields:    [],
			},
			'aips-source-description': {
				fieldName:        'Notes',
				description:      'Notes about why this source is trusted or how it\'s used',
				influence:        'Used only for the site administrator\'s reference; does not affect fetched content',
				expectedResponse: 'One to two sentences of notes about this source',
				contextFields:    [ 'aips-source-label' ],
			},
		},
		// Shared by the Onboarding wizard's Strategy step and the Settings ›
		// Site Content Strategy tab — both edit the same aips_site_* options,
		// so suggestions and history are shared across whichever page the
		// user is currently editing them from.
		content_strategy: {
			aips_site_niche: {
				fieldName:        'Site Niche',
				description:      'The overall subject-matter niche for this site\'s content strategy',
				influence:        'Seeds the default author\'s niche and every topic/content prompt generated site-wide',
				expectedResponse: 'A concise niche description (1-5 words)',
				contextFields:    [],
			},
			aips_site_target_audience: {
				fieldName:        'Target Audience',
				description:      'The intended readership for this site\'s content',
				influence:        'Tailors complexity, vocabulary, and examples in generated content to this audience',
				expectedResponse: 'A concise audience description',
				contextFields:    [ 'aips_site_niche' ],
			},
			aips_site_content_goals: {
				fieldName:        'Content Goals',
				description:      'The primary objectives this site\'s content should achieve',
				influence:        'Aligns generated post structure and calls-to-action with these goals',
				expectedResponse: 'One to three goal statements',
				contextFields:    [ 'aips_site_niche', 'aips_site_target_audience' ],
			},
			aips_site_brand_voice: {
				fieldName:        'Brand Voice',
				description:      'The tone and style this site\'s content should be written in',
				influence:        'Instructs the AI to match this tone throughout every generated post',
				expectedResponse: 'One to three descriptive words (e.g. "friendly, authoritative, approachable")',
				contextFields:    [ 'aips_site_niche' ],
			},
			aips_site_content_guidelines: {
				fieldName:        'Content Guidelines',
				description:      'Specific rules or guidelines generated content must follow',
				influence:        'Directly constrains how the AI writes every generated post',
				expectedResponse: 'A short list of concrete content guidelines',
				contextFields:    [ 'aips_site_niche', 'aips_site_brand_voice' ],
			},
			aips_site_excluded_topics: {
				fieldName:        'Excluded Topics',
				description:      'Topics or subject areas this site should never write about',
				influence:        'Prevents the AI from generating posts or topics in these areas, site-wide',
				expectedResponse: 'A comma-separated list of excluded topics or themes',
				contextFields:    [ 'aips_site_niche' ],
			},
		},
	};

	/**
	 * sessionStorage key used to persist the field-assist session id across
	 * page loads within the same browser session.
	 *
	 * @type {string}
	 */
	var SESSION_STORAGE_KEY = 'aipsAIAssistanceSessionId';

	/**
	 * AIPS.AIAssistance module.
	 *
	 * Manages AI field-assist buttons, suggestion fetching, and history modal.
	 *
	 * @namespace AIPS.AIAssistance
	 */
	AIPS.AIAssistance = {

		/**
		 * Unique session identifier, persisted in sessionStorage so the
		 * "This Session" history tab survives a page reload.
		 *
		 * @type {string}
		 */
		sessionId: '',

		/**
		 * The form context identifier used for all requests on this page.
		 * Set via init({ formContext: '...' }); defaults to 'authors' for
		 * backward compatibility with callers that don't pass one.
		 *
		 * @type {string}
		 */
		formContext: 'authors',

		/**
		 * Guards against double-initialisation (and therefore duplicate
		 * document-delegated event bindings) if init() is ever called more
		 * than once on the same page.
		 *
		 * @type {boolean}
		 */
		_initialized: false,

		/**
		 * Generate a new random identifier for session grouping.
		 *
		 * @return {string}
		 */
		_generateSessionId: function () {
			if (typeof crypto !== 'undefined' && crypto.randomUUID) {
				return crypto.randomUUID();
			}
			if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
				var buf = new Uint8Array(16);
				crypto.getRandomValues(buf);
				return Array.prototype.map.call(buf, function (b) {
					return ('0' + b.toString(16)).slice(-2);
				}).join('');
			}
			// Last-resort fallback (non-security context: session grouping only).
			return Date.now().toString(36) + Math.random().toString(36).slice(2);
		},

		/**
		 * Load the persisted session id from sessionStorage, or generate and
		 * persist a new one. Falls back to an in-memory id if storage is
		 * unavailable (private browsing, blocked site data, etc).
		 *
		 * @return {string}
		 */
		_loadOrCreateSessionId: function () {
			try {
				var stored = window.sessionStorage.getItem(SESSION_STORAGE_KEY);
				if (stored) {
					return stored;
				}
				var fresh = this._generateSessionId();
				window.sessionStorage.setItem(SESSION_STORAGE_KEY, fresh);
				return fresh;
			} catch (e) {
				return this._generateSessionId();
			}
		},

		/**
		 * Initialise the module: resolve form context, load/generate the
		 * session ID, inject buttons, bind events. Safe to call only once per
		 * page — subsequent calls are no-ops so document-delegated handlers
		 * are never bound twice.
		 *
		 * @param {Object} [options] Optional configuration.
		 * @param {string} [options.formContext] The form context key to use (see AIPS_FIELD_MAPS).
		 * @return {void}
		 */
		init: function (options) {
			if (this._initialized) {
				return;
			}
			this._initialized = true;

			if (options && options.formContext) {
				this.formContext = options.formContext;
			}

			this.sessionId = this._loadOrCreateSessionId();
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

				// Different admin forms use different row-wrapper class names
				// (Authors: .form-group, Templates and others: .aips-form-row);
				// this is purely a CSS positioning hook, so it's harmless to
				// no-op when neither is found.
				var $formGroup = $field.closest('.form-group, .aips-form-row');
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
			$(document).on('click', '.aips-ai-assist-history-delete', this.deleteHistoryValue.bind(this));
			$(document).on('click', '.aips-ai-assist-history-clear', this.clearHistory.bind(this));
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
			if (!aipsAIAssistanceL10n.policy.contexts || aipsAIAssistanceL10n.policy.contexts.indexOf(this.formContext) === -1) { return; }

			if (!fieldConfig) {
				return;
			}

			var $field = $('#' + fieldId);
			var currentValue = $field.val() || '';

			// Generic sibling-field context: each field map entry declares
			// which other fields on the same form should be read and sent
			// along as extra context, instead of hardcoding specific IDs here.
			var context = {};
			$.each(fieldConfig.contextFields || [], function (i, contextFieldId) {
				context[contextFieldId] = $('#' + contextFieldId).val() || '';
			});

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
					context:           context,
				},
				function (response) {
					$btn.prop('disabled', false).removeClass('loading');
					$label.text(originalLabel);

					if (response.success && response.data && response.data.hasOwnProperty('response')) {
						if (currentValue && aipsAIAssistanceL10n.policy.overwriteMode === 'preview') {
							self.showPreview($field, response.data.response, function () {
								$field.val(response.data.response).trigger('change');
							});
						} else {
							$field.val(response.data.response).trigger('change');
						}

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
			$('#aips-ai-assist-history-session-tab').html('<p class="description">' + ( (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.loading : 'Loading\u2026' ) + '</p>');
			$('#aips-ai-assist-history-alltime-tab').html('<p class="description">' + ( (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.loading : 'Loading\u2026' ) + '</p>');

			// Reset tabs
			$modal.find('.aips-tab-link[data-tab="aips-ai-assist-history-session"]').addClass('active');
			$modal.find('.aips-tab-link[data-tab="aips-ai-assist-history-alltime"]').removeClass('active');
			$('#aips-ai-assist-history-session-tab').show();
			$('#aips-ai-assist-history-alltime-tab').hide();

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
						self.renderHistoryTab( '#aips-ai-assist-history-session-tab', response.data.session, fieldId );
						self.renderHistoryTab( '#aips-ai-assist-history-alltime-tab', response.data.alltime, fieldId );
					} else {
						var noHistoryMsg = (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.noHistory : 'No AI suggestions found for this field yet.';
						$('#aips-ai-assist-history-session-tab').html('<p class="description">' + noHistoryMsg + '</p>');
						$('#aips-ai-assist-history-alltime-tab').html('<p class="description">' + noHistoryMsg + '</p>');
					}
				}
			).fail(function () {
				var noHistoryMsg = (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.noHistory : 'No AI suggestions found for this field yet.';
				$('#aips-ai-assist-history-session-tab').html('<p class="description">' + noHistoryMsg + '</p>');
				$('#aips-ai-assist-history-alltime-tab').html('<p class="description">' + noHistoryMsg + '</p>');
			});
		},

		/**
		 * Render a list of suggestion records into a history tab container.
		 *
		 * Uses AIPS.Templates.render() (HTML-escaped) for safe display of AI
		 * response text. The raw response is stored in _historyRecordCache keyed
		 * by record ID so it can be applied to the field without embedding
		 * unescaped content in DOM attributes.
		 *
		 * @param {string} selector CSS selector for the tab content element.
		 * @param {Array}  records  Array of record objects from the server.
		 * @param {string} fieldId  The field ID these records belong to.
		 * @return {void}
		 */
		renderHistoryTab: function (selector, records, fieldId) {
			var $container = $(selector);
			var noHistoryMsg = (typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.noHistory : 'No AI suggestions found for this field yet.';
			var self = this;

			if (!records || !records.length) {
				$container.html('<p class="description">' + noHistoryMsg + '</p>');
				return;
			}

			var html = '';
			$.each(records, function (i, record) {
				// Cache raw response by record ID to avoid embedding unescaped
				// AI content in DOM attributes.
				_historyRecordCache[record.id] = record.response;

				html += AIPS.Templates.render('aips-tmpl-ai-assist-history-item', {
					id:         record.id,
					response:   record.response,
					created_at: record.created_at,
					fieldId:    fieldId,
					context:    self.getHistoryContext(record.context),
				});
			});
			$container.html(html);
		},

		/**
		 * Format the workflow and declared sibling fields that informed a saved
		 * suggestion. Template rendering escapes the resulting string.
		 *
		 * @param {Object} context Saved suggestion context.
		 * @return {string} Explainable context summary.
		 */
		getHistoryContext: function (context) {
			var parts = [];

			if (!context || typeof context !== 'object') {
				return '';
			}

			if (context.workflow) {
				parts.push(context.workflow);
			}

			if (context.sibling_fields && typeof context.sibling_fields === 'object') {
				$.each(context.sibling_fields, function (fieldName, value) {
					if (typeof value === 'string' && value) {
						parts.push(fieldName + ': ' + value);
					}
				});
			}

			return parts.join(' | ');
		},

		/**
		 * Apply a value from the history modal to the target field.
		 *
		 * Reads the record ID from `data-record-id` and retrieves the raw
		 * response text from _historyRecordCache to avoid relying on
		 * unescaped content stored in DOM attributes.
		 *
		 * @param {jQuery.Event} e Click event on a "Use This Value" button.
		 * @return {void}
		 */
		useHistoryValue: function (e) {
			e.preventDefault();
			var $btn     = $(e.currentTarget);
			var fieldId  = $btn.data('field-id');
			var recordId = $btn.data('record-id');
			var value    = _historyRecordCache[recordId] || '';

			$('#' + fieldId).val(value).trigger('change');
			this.closeHistoryModal();

			if (typeof AIPS.Utilities !== 'undefined' && AIPS.Utilities.showToast) {
				AIPS.Utilities.showToast(
					(typeof aipsAIAssistanceL10n !== 'undefined') ? aipsAIAssistanceL10n.valueApplied : 'Value applied from history.',
					'success'
				);
			}
		},

		showPreview: function ($field, value, apply) {
			var previewHtml = '<div class="notice notice-info aips-ai-assist-preview">' +
				'<p><strong></strong></p>' +
				'<p class="aips-ai-assist-preview-value"></p>' +
				'<p><button type="button" class="button button-primary">' +
					AIPS.Templates.escape(aipsAIAssistanceL10n.apply) +
				'</button> <button type="button" class="button-link">' +
					AIPS.Templates.escape(aipsAIAssistanceL10n.keepCurrent) +
				'</button></p>' +
			'</div>';
			var $preview = $(previewHtml);
			var $container = $field.closest('.form-group, .aips-form-row, tr');

			$preview.find('strong').text(aipsAIAssistanceL10n.previewTitle);
			$preview.find('.aips-ai-assist-preview-value').text(value);
			$preview.find('.button-primary').on('click', function () {
				apply();
				$preview.remove();
			});
			$preview.find('.button-link').on('click', function () {
				$preview.remove();
			});

			if ($container.length) {
				$container.append($preview);
				return;
			}

			$field.after($preview);
		},

		deleteHistoryValue: function (e) {
			e.preventDefault(); var $btn = $(e.currentTarget);
			$.post(ajaxurl, {action: 'aips_delete_field_assist_history', nonce: aipsAIAssistanceL10n.nonce, record_id: $btn.data('record-id')}, function (response) { if (response.success) { $btn.closest('.aips-ai-assist-history-item').remove(); } });
		},

		clearHistory: function (e) {
			e.preventDefault(); if (!window.confirm('Clear all AI Assistance history?')) { return; }
			$.post(ajaxurl, {action: 'aips_clear_field_assist_history', nonce: aipsAIAssistanceL10n.nonce}, function (response) { if (response.success) { $('#aips-ai-assist-history-session-tab, #aips-ai-assist-history-alltime-tab').empty(); } });
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

	// Intentionally no auto-init here: each admin page that wants AI
	// Assistance must call AIPS.AIAssistance.init({ formContext: '...' })
	// itself (see assets/js/authors.js for the Authors page). init() is
	// idempotent, but each page should only call it once, with its own
	// formContext, so the right field map and sibling-field context apply.

})(jQuery);
