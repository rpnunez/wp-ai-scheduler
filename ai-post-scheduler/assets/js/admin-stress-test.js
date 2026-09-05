/* global AIPS, aipsAjax, jQuery */
/**
 * Stress Test admin page JS.
 *
 * Responsibilities:
 *  - Run a single case, or every case in sequence via "Run All"
 *  - Render each result: status indicator, summary line, elapsed time
 *  - Expand a row to reveal the AI request/response and the AI vs plugin values
 *  - Show a pass/fail banner and per-case summary once a full run finishes
 *  - Delete posts and attachments the page created
 *
 * Cases run one request at a time rather than in parallel: they share a rate
 * limiter and circuit breaker, so firing them together would make one case fail
 * because of another and report a fault that is not there.
 *
 * Dependencies: jQuery, AIPS.Utilities (aipsAjax localized by AIPS_Admin_Assets)
 *
 * @since 3.2.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	var settings = window.aipsStressTest || {};
	var i18n = settings.i18n || {};

	/**
	 * Translate a key, falling back to the supplied default.
	 *
	 * @param {string} key
	 * @param {string} fallback
	 * @returns {string}
	 */
	function t(key, fallback) {
		return i18n[key] || fallback;
	}

	AIPS.StressTest = {

		/** @type {boolean} Guards against overlapping runs. */
		running: false,

		/** @type {boolean} Set by Reset/cleanup to stop an in-flight Run All. */
		aborted: false,

		init: function () {
			if (!$('#aips-stress-test').length && !$('#aips-stress-test-history').length) {
				return;
			}

			this.bindEvents();
			this.loadRunHistory();
		},

		bindEvents: function () {
			$(document)
				.on('click', '#aips-stress-run-all', this.handleRunAll.bind(this))
				.on('click', '#aips-stress-reset', this.handleReset.bind(this))
				.on('click', '#aips-stress-export', this.handleExport.bind(this))
				.on('click', '#aips-stress-cleanup', this.handleCleanup.bind(this))
				.on('click', '.aips-stress-run-one', this.handleRunOne.bind(this))
				.on('click', '.aips-stress-row', this.handleRowToggle.bind(this))
				// Keyboard parity for the row: the toggle button carries the
				// aria state, so Enter/Space on it drives the same toggle.
				.on('keydown', '.aips-stress-toggle', this.handleToggleKeydown.bind(this))
				// History & Diff bindings
				.on('change', '#aips-stress-history-select', this.handleHistorySelectChange.bind(this))
				.on('click', '#aips-stress-compare-btn', this.handleCompareCurrentVsSelected.bind(this))
				.on('change', '.aips-stress-history-checkbox', this.handleHistoryCheckboxChange.bind(this))
				.on('click', '#aips-stress-history-diff-btn', this.handleCompareDualHistory.bind(this))
				.on('click', '.aips-stress-view-run-btn', this.handleViewRunDetails.bind(this))
				.on('click', '#aips-stress-diff-modal .aips-modal-close, #aips-stress-diff-modal .aips-modal-close-btn, #aips-stress-diff-modal .aips-modal-backdrop', this.closeDiffModal.bind(this));
		},

		// -------------------------------------------------------------------
		// Running
		// -------------------------------------------------------------------

		/**
		 * Run one case from its row button.
		 *
		 * @param {Event} e
		 */
		handleRunOne: function (e) {
			e.preventDefault();

			if (this.running) {
				return;
			}

			var caseId = $(e.currentTarget).closest('.aips-stress-row').data('case');

			this.running = true;
			this.aborted = false;
			$('#aips-stress-summary').attr('hidden', true);

			var self = this;

			this.runCase(caseId).always(function () {
				self.running = false;
			});
		},

		/**
		 * Run every case in order, then show the summary banner.
		 *
		 * @param {Event} e
		 */
		handleRunAll: function (e) {
			e.preventDefault();

			if (this.running) {
				return;
			}

			var self = this;
			var $rows = $('.aips-stress-row');
			var caseIds = $rows.map(function () {
				return $(this).data('case');
			}).get();

			if (!caseIds.length) {
				return;
			}

			this.running = true;
			this.aborted = false;
			this.resetRows();
			$('#aips-stress-summary').attr('hidden', true);
			$('#aips-stress-run-all').prop('disabled', true);
			this.showProgress(0, caseIds.length);

			var index = 0;

			/**
			 * Run the next queued case, or finish when the queue is empty.
			 *
			 * @returns {void}
			 */
			function next() {
				if (self.aborted || index >= caseIds.length) {
					self.running = false;
					$('#aips-stress-run-all').prop('disabled', false);
					self.hideProgress();

					if (!self.aborted) {
						self.renderSummary();
						self.saveRunToHistory();
					}

					return;
				}

				var caseId = caseIds[index];
				index++;

				self.showProgress(index, caseIds.length, caseId);
				self.runCase(caseId).always(next);
			}

			next();
		},

		/**
		 * Dispatch a single case and render whatever comes back.
		 *
		 * @param {string} caseId
		 * @returns {jqXHR}
		 */
		runCase: function (caseId) {
			var self = this;
			var $row = $('.aips-stress-row[data-case="' + caseId + '"]');

			this.setRowState($row, 'running', t('running', 'Running…'), '—');

			return $.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				// A full pipeline case can legitimately take minutes; the default
				// timeout would abort a run that is still healthy.
				timeout: 300000,
				data: {
					action: 'aips_stress_test_run',
					nonce: settings.nonce,
					'case': caseId
				}
			}).done(function (response) {
				if (response && response.success && response.data && response.data.result) {
					self.renderResult(response.data.result);
					return;
				}

				var message = (response && response.data && response.data.message)
					? response.data.message
					: t('requestFailed', 'Request failed.');

				self.renderResult(self.errorResult(caseId, message));
			}).fail(function (xhr, textStatus) {
				var message = (textStatus === 'timeout')
					? t('timedOut', 'The request timed out.')
					: t('requestFailed', 'Request failed.');

				self.renderResult(self.errorResult(caseId, message));
			});
		},

		/**
		 * Build a client-side failure result so transport errors render like any
		 * other failed case instead of leaving the row stuck on "Running…".
		 *
		 * @param {string} caseId
		 * @param {string} message
		 * @returns {Object}
		 */
		errorResult: function (caseId, message) {
			var $row = $('.aips-stress-row[data-case="' + caseId + '"]');

			return {
				'case': caseId,
				label: $row.find('.aips-stress-case-label').text(),
				status: 'failed',
				summary: message,
				error: message,
				duration_ms: 0,
				ai_calls: [],
				ai_value: null,
				plugin_value: null,
				artifacts: {}
			};
		},

		// -------------------------------------------------------------------
		// Rendering
		// -------------------------------------------------------------------

		/**
		 * Apply a result to its row and build the expandable detail panel.
		 *
		 * @param {Object} result
		 */
		renderResult: function (result) {
			var $row = $('.aips-stress-row[data-case="' + result['case'] + '"]');

			if (!$row.length) {
				return;
			}

			var duration = result.duration_ms ? this.formatDuration(result.duration_ms) : '—';

			this.setRowState($row, result.status, result.summary || '', duration);
			$row.data('result', result);

			// A rendered result means there is something to export.
			$('#aips-stress-export').prop('disabled', false);

			var $details = $('#aips-stress-details-' + result['case']).find('.aips-stress-details');
			$details.empty().append(this.buildDetails(result));

			if (result.artifacts && result.artifacts.post_ids && result.artifacts.post_ids.length) {
				this.bumpTestDataCount(result.artifacts.post_ids.length);
			}

			if (result.artifacts && result.artifacts.attachment_ids && result.artifacts.attachment_ids.length) {
				this.bumpTestDataCount(result.artifacts.attachment_ids.length);
			}
		},

		/**
		 * Set the visual state of a case row.
		 *
		 * @param {jQuery} $row
		 * @param {string} status  idle | running | passed | failed
		 * @param {string} text    Result column text
		 * @param {string} duration
		 */
		setRowState: function ($row, status, text, duration) {
			$row.attr('data-status', status);
			$row.find('.aips-stress-result-text').text(text || '');
			$row.find('.aips-stress-duration').text(duration || '—');
			$row.find('.aips-stress-run-one').prop('disabled', status === 'running');
		},

		/**
		 * Build the expanded panel: AI vs plugin values, then each AI call.
		 *
		 * @param {Object} result
		 * @returns {jQuery}
		 */
		buildDetails: function (result) {
			var $wrap = $('<div class="aips-stress-detail-body"></div>');

			if (result.error) {
				$wrap.append(
					$('<div class="aips-stress-notice aips-stress-notice-danger"></div>')
						.append($('<span class="dashicons dashicons-warning"></span>'))
						.append($('<span></span>').text(result.error))
				);
			}

			// AI calls first: the raw request/response traffic is the primary
			// diagnostic, and the compared values below are derived from it.
			var calls = Array.isArray(result.ai_calls) ? result.ai_calls : [];

			var $callsSection = $('<div class="aips-stress-calls"></div>');
			$callsSection.append(
				$('<h4></h4>').text(t('aiCalls', 'AI calls') + ' (' + calls.length + ')')
			);
			if (result.ai_statistics) {
				$callsSection.append(this.optionsBlock({
					'Usage summary': result.ai_statistics.estimated_usage || result.ai_statistics
				}, []));
			}

			if (!calls.length) {
				$callsSection.append(
					$('<p class="aips-no-data"></p>').text(t('noCalls', 'No AI calls were recorded for this case.'))
				);
			} else {
				calls.forEach(function (call, index) {
					$callsSection.append(this.buildCallBlock(call, index));
				}.bind(this));
			}

			$wrap.append($callsSection);

			// The AI-vs-plugin comparison sits below, as the summary of what those
			// calls ultimately produced.
			var $columns = $('<div class="aips-stress-compare"></div>');

			$columns.append(this.buildValueBlock(
				t('aiValue', 'AI response value'),
				t('aiValueHint', 'Exactly what the provider returned.'),
				result.ai_value
			));

			$columns.append(this.buildValueBlock(
				t('pluginValue', 'Plugin final value'),
				t('pluginValueHint', 'After the plugin parsed and normalized it.'),
				result.plugin_value
			));

			$wrap.append($columns);

			if (result.artifacts && result.artifacts.preview_url) {
				$wrap.append(
					$('<div class="aips-stress-preview"></div>').append(
						$('<img alt="" />').attr('src', result.artifacts.preview_url)
					)
				);
			}

			return $wrap;
		},

		/**
		 * Render one labelled value, as text when scalar and as JSON otherwise.
		 *
		 * @param {string} label
		 * @param {string} hint
		 * @param {*}      value
		 * @returns {jQuery}
		 */
		buildValueBlock: function (label, hint, value) {
			var $block = $('<div class="aips-stress-compare-col"></div>');

			$block.append($('<h4></h4>').text(label));
			$block.append($('<p class="aips-stress-hint"></p>').text(hint));

			if (value === null || typeof value === 'undefined' || value === '') {
				$block.append($('<p class="aips-no-data"></p>').text(t('noValue', 'No value returned.')));

				return $block;
			}

			$block.append(this.jsonViewer(value));

			return $block;
		},

		/**
		 * Build one AI request/response pair.
		 *
		 * @param {Object} call
		 * @param {number} index
		 * @returns {jQuery}
		 */
		buildCallBlock: function (call, index) {
			var $block = $('<div class="aips-stress-call"></div>');
			var status = call.response && call.response.success ? 'passed' : 'failed';

			$block.append(
				$('<div class="aips-stress-call-head"></div>')
					.attr('data-status', status)
					.append($('<strong></strong>').text('#' + (index + 1) + ' · ' + (call.type || 'text')))
					.append($('<span class="aips-stress-call-time"></span>').text(call.time || ''))
			);

			var request = call.request || {};
			var response = call.response || {};
			var options = request.options || {};

			// The system instruction (context/instructions) is stable boilerplate
			// that dwarfs the prompt. It moves into a hover tooltip on the Prompt
			// label so it stays inspectable without burying the actual prompt.
			var tooltip = [options.context, options.instructions]
				.filter(function (v) { return typeof v === 'string' && v !== ''; })
				.join('\n\n');

			// Request and Response are wrapped in distinct panels so the boundary
			// between what was sent and what came back is obvious at a glance.
			var $request = $('<div class="aips-stress-side aips-stress-side-request"></div>');
			$request.append($('<div class="aips-stress-side-head"></div>').text(t('request', 'Request')));

			var $requestBody = $('<div class="aips-stress-side-body"></div>');
			$requestBody.append(this.textBlock(t('prompt', 'Prompt'), request.prompt, tooltip));

			var $condensed = this.optionsBlock(options, ['context', 'instructions']);
			if ($condensed) {
				$requestBody.append($condensed);
			}

			var usage = call.usage || {};
			var routing = {};
			['resolved_provider', 'resolved_connector', 'resolved_model'].forEach(function (key) {
				if (options[key]) {
					routing[key.replace('resolved_', '')] = options[key];
				}
			});
			if (Object.keys(routing).length || Object.keys(usage).length) {
				$requestBody.append(this.optionsBlock({
					'Routing': routing,
					'Usage': usage
				}, []));
			}

			$request.append($requestBody);
			$block.append($request);

			var $response = $('<div class="aips-stress-side aips-stress-side-response"></div>');
			$response.append($('<div class="aips-stress-side-head"></div>').text(t('response', 'Response')));

			var $responseBody = $('<div class="aips-stress-side-body"></div>');

			if (response.error) {
				$responseBody.append(this.textBlock(t('error', 'Error'), response.error));
			}

			$responseBody.append(this.textBlock(t('content', 'Content'), response.content));
			$response.append($responseBody);
			$block.append($response);

			return $block;
		},

		/**
		 * Render a labelled multi-line string with its newlines intact.
		 *
		 * @param {string}  label
		 * @param {*}       text
		 * @param {string} [tooltip] Optional help text shown from a "?" beside the label.
		 * @returns {jQuery}
		 */
		textBlock: function (label, text, tooltip) {
			var $block = $('<div class="aips-stress-field"></div>');
			var $label = $('<h6></h6>').text(label);

			if (tooltip) {
				$label.append(this.helpTip(tooltip));
			}

			$block.append($label);

			if (text === null || typeof text === 'undefined' || text === '') {
				$block.append($('<p class="aips-no-data"></p>').text(t('noValue', 'No value returned.')));

				return $block;
			}

			$block.append(
				$('<div class="aips-json-viewer"><pre></pre></div>')
					.find('pre').text(typeof text === 'string' ? text : JSON.stringify(text, null, 2)).end()
			);

			return $block;
		},

		/**
		 * Build a "?" affordance that reveals help text on hover or focus.
		 *
		 * Keyboard-reachable via tabindex, and the text is set with .text() since
		 * it originates from provider-facing prompt content.
		 *
		 * @param {string} text
		 * @returns {jQuery}
		 */
		helpTip: function (text) {
			return $('<span class="aips-stress-help" tabindex="0" role="button"></span>')
				.attr('aria-label', t('showContext', 'Show system instruction'))
				.text('?')
				.append($('<span class="aips-stress-tooltip" role="tooltip"></span>').text(text));
		},

		/**
		 * Render request options condensed: scalars as inline pills, and any
		 * object/array values (a JSON schema, say) in a compact JSON block below.
		 *
		 * @param {Object}   options
		 * @param {string[]} exclude Keys to omit (shown elsewhere).
		 * @returns {jQuery|null} Null when nothing is left to show.
		 */
		optionsBlock: function (options, exclude) {
			var $pills = $('<div class="aips-stress-pills"></div>');
			var complex = {};
			var pillCount = 0;
			var complexCount = 0;

			Object.keys(options).forEach(function (key) {
				if (exclude.indexOf(key) !== -1) {
					return;
				}

				var value = options[key];

				if (value === null || typeof value === 'object') {
					if (value !== null) {
						complex[key] = value;
						complexCount++;
					}
					return;
				}

				$pills.append(
					$('<span class="aips-stress-pill"></span>')
						.append($('<span class="aips-stress-pill-key"></span>').text(key))
						.append($('<span class="aips-stress-pill-val"></span>').text(String(value)))
				);
				pillCount++;
			});

			if (!pillCount && !complexCount) {
				return null;
			}

			var $block = $('<div class="aips-stress-field"></div>');
			$block.append($('<h6></h6>').text(t('options', 'Options')));

			if (pillCount) {
				$block.append($pills);
			}

			if (complexCount) {
				$block.append(this.jsonViewer(complex));
			}

			return $block;
		},

		/**
		 * Build the shared JSON viewer used by the History modal.
		 *
		 * Always assigned via .text() so provider output — which is arbitrary and
		 * may contain markup — is never parsed as HTML.
		 *
		 * @param {*} value
		 * @returns {jQuery}
		 */
		jsonViewer: function (value) {
			var text;

			if (typeof value === 'string') {
				text = value;
			} else {
				try {
					text = JSON.stringify(value, null, 2);
				} catch (err) {
					text = String(value);
				}
			}

			return $('<div class="aips-json-viewer"><pre></pre></div>')
				.find('pre').text(text).end();
		},

		// -------------------------------------------------------------------
		// Expand / collapse
		// -------------------------------------------------------------------

		/**
		 * Toggle a case's detail row from a click anywhere on the row.
		 *
		 * The Run button lives inside the row; its own handler owns those clicks,
		 * so they are ignored here. An active text selection is also left alone so
		 * a user highlighting the description does not collapse the row.
		 *
		 * @param {Event} e
		 */
		handleRowToggle: function (e) {
			if ($(e.target).closest('.aips-stress-run-one').length) {
				return;
			}

			var selection = window.getSelection && window.getSelection().toString();
			if (selection) {
				return;
			}

			this.toggleRow($(e.currentTarget));
		},

		/**
		 * Toggle when the visible caret button is activated via keyboard.
		 *
		 * @param {Event} e
		 */
		handleToggleKeydown: function (e) {
			if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') {
				return;
			}

			e.preventDefault();
			this.toggleRow($(e.currentTarget).closest('.aips-stress-row'));
		},

		/**
		 * Expand or collapse a case's detail row.
		 *
		 * @param {jQuery} $row
		 */
		toggleRow: function ($row) {
			if (!$row.length) {
				return;
			}

			var $details = $('#aips-stress-details-' + $row.data('case'));
			var $toggle = $row.find('.aips-stress-toggle');
			var expanded = $row.hasClass('is-expanded');

			if (expanded) {
				$details.attr('hidden', true);
				$toggle.attr('aria-expanded', 'false');
				$row.removeClass('is-expanded');

				return;
			}

			if (!$row.data('result')) {
				$details.find('.aips-stress-details')
					.empty()
					.append($('<p class="aips-no-data"></p>').text(t('notRunYet', 'Run this case to see the request and response.')));
			}

			$details.removeAttr('hidden');
			$toggle.attr('aria-expanded', 'true');
			$row.addClass('is-expanded');
		},

		// -------------------------------------------------------------------
		// Progress + summary
		// -------------------------------------------------------------------

		/**
		 * Update the progress bar.
		 *
		 * @param {number} current
		 * @param {number} total
		 * @param {string} [caseId]
		 */
		showProgress: function (current, total, caseId) {
			var $progress = $('#aips-stress-progress');
			var percent = total ? Math.round((current / total) * 100) : 0;
			var label = current + ' / ' + total;

			if (caseId) {
				var caseLabel = $('.aips-stress-row[data-case="' + caseId + '"]').find('.aips-stress-case-label').text();
				label += ' · ' + caseLabel;
			}

			$progress.removeAttr('hidden');
			$progress.find('.aips-stress-progress-bar span').css('width', percent + '%');
			$progress.find('.aips-stress-progress-label').text(label);
		},

		hideProgress: function () {
			$('#aips-stress-progress').attr('hidden', true);
		},

		/**
		 * Render the pass/fail banner and the per-case list.
		 */
		renderSummary: function () {
			var $summary = $('#aips-stress-summary');
			var results = [];

			$('.aips-stress-row').each(function () {
				var result = $(this).data('result');

				if (result) {
					results.push(result);
				}
			});

			if (!results.length) {
				return;
			}

			var failed = results.filter(function (r) {
				return r.status !== 'passed';
			});

			var allPassed = failed.length === 0;
			var totalMs = results.reduce(function (sum, r) {
				return sum + (r.duration_ms || 0);
			}, 0);

			$summary
				.attr('data-status', allPassed ? 'passed' : 'failed')
				.removeAttr('hidden');

			$summary.find('.aips-stress-summary-icon')
				.attr('class', 'aips-stress-summary-icon dashicons ' + (allPassed ? 'dashicons-yes-alt' : 'dashicons-dismiss'));

			$summary.find('.aips-stress-summary-text h3').text(
				allPassed
					? t('allPassed', 'All tests passed')
					: t('someFailed', 'Some tests failed')
			);

			$summary.find('.aips-stress-summary-text p').text(
				(results.length - failed.length) + ' / ' + results.length + ' ' +
				t('passedIn', 'passed in') + ' ' + this.formatDuration(totalMs)
			);

			var $list = $summary.find('.aips-stress-summary-list').empty();

			results.forEach(function (result) {
				var itemHtml = (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-stress-summary-item'))
					? AIPS.Templates.render('aips-tmpl-stress-summary-item', {
						status: result.status,
						label:  result.label,
						detail: result.summary || '',
						time:   this.formatDuration(result.duration_ms || 0)
					})
					: '';

				if (itemHtml) {
					$list.append(itemHtml);
				} else {
					$list.append(
						$('<li></li>')
							.attr('data-status', result.status)
							.append($('<span class="aips-stress-summary-dot"></span>'))
							.append($('<span class="aips-stress-summary-label"></span>').text(result.label))
							.append($('<span class="aips-stress-summary-detail"></span>').text(result.summary || ''))
							.append($('<span class="aips-stress-summary-time"></span>').text(this.formatDuration(result.duration_ms || 0)))
					);
				}
			}.bind(this));

			// Restart the banner animation even when the previous run left the
			// element in the same state.
			$summary.removeClass('is-animating');
			void $summary[0].offsetWidth;
			$summary.addClass('is-animating');
		},

		// -------------------------------------------------------------------
		// Reset + cleanup
		// -------------------------------------------------------------------

		/**
		 * Clear every rendered result without touching created data.
		 *
		 * @param {Event} e
		 */
		handleReset: function (e) {
			e.preventDefault();

			this.aborted = true;
			this.resetRows();
			$('#aips-stress-summary').attr('hidden', true);
			this.hideProgress();
		},

		resetRows: function () {
			var self = this;

			$('.aips-stress-row').each(function () {
				var $row = $(this);

				$row.removeData('result').removeClass('is-expanded');
				self.setRowState($row, 'idle', t('notRun', 'Not run'), '—');
				$row.find('.aips-stress-toggle').attr('aria-expanded', 'false');
			});

			$('.aips-stress-details-row').attr('hidden', true).find('.aips-stress-details').empty();
			$('#aips-stress-export').prop('disabled', true);
		},

		// -------------------------------------------------------------------
		// Export
		// -------------------------------------------------------------------

		/**
		 * Read the environment/version snapshot embedded in the page.
		 *
		 * @returns {Object}
		 */
		readExportMeta: function () {
			var raw = $('#aips-stress-export-meta').text();

			if (!raw) {
				return {};
			}

			try {
				return JSON.parse(raw) || {};
			} catch (err) {
				return {};
			}
		},

		/**
		 * Download every rendered result as a single JSON file.
		 *
		 * The file bundles the provider/model/version snapshot with each case's
		 * status, timing, compared values, and full AI request/response log, so
		 * one run can be shared verbatim for analysis.
		 *
		 * @param {Event} e
		 */
		handleExport: function (e) {
			e.preventDefault();

			var results = [];

			$('.aips-stress-row').each(function () {
				var result = $(this).data('result');

				if (result) {
					results.push(result);
				}
			});

			if (!results.length) {
				AIPS.Utilities.showToast(t('nothingToExport', 'Run at least one test case before exporting.'), 'info');
				return;
			}

			var meta = this.readExportMeta();
			var passed = results.filter(function (r) { return r.status === 'passed'; }).length;

			var payload = {
				exported_at: new Date().toISOString(),
				plugin_version: meta.plugin_version || '',
				environment: meta.environment || {},
				totals: {
					cases: results.length,
					passed: passed,
					failed: results.length - passed,
					duration_ms: results.reduce(function (sum, r) { return sum + (r.duration_ms || 0); }, 0)
				},
				results: results
			};

			var stamp = new Date().toISOString().replace(/[:.]/g, '-').replace('T', '_').slice(0, 19);
			this.downloadJson('aips-stress-test-results-' + stamp + '.json', payload);
		},

		/**
		 * Trigger a client-side download of a pretty-printed JSON object.
		 *
		 * @param {string} filename
		 * @param {Object} data
		 */
		downloadJson: function (filename, data) {
			var json = JSON.stringify(data, null, 2);
			var blob = new Blob([json], { type: 'application/json' });
			var url = window.URL.createObjectURL(blob);
			var $link = $('<a></a>')
				.attr('href', url)
				.attr('download', filename)
				.css('display', 'none')
				.appendTo('body');

			$link[0].click();
			$link.remove();
			window.URL.revokeObjectURL(url);
		},

		/**
		 * Delete every post and attachment the page created.
		 *
		 * @param {Event} e
		 */
		handleCleanup: function (e) {
			e.preventDefault();

			var self = this;

			AIPS.Utilities.confirm(
				t('confirmCleanup', 'This permanently deletes every post and image created by the Stress Test page. Continue?'),
				t('confirmCleanupHeading', 'Delete test data'),
				[
					{ label: t('cancel', 'Cancel'), className: 'aips-btn aips-btn-secondary' },
					{
						label: t('confirmCleanupAction', 'Yes, delete'),
						className: 'aips-btn aips-btn-danger-solid',
						action: function () {
							self.runCleanup();
						}
					}
				]
			);
		},

		runCleanup: function () {
			var self = this;

			$('#aips-stress-cleanup').prop('disabled', true);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_stress_test_cleanup',
					nonce: settings.nonce
				}
			}).done(function (response) {
				if (response && response.success) {
					AIPS.Utilities.showToast(response.data.message, 'success');
					self.setTestDataCount(0);
					return;
				}

				AIPS.Utilities.showToast(t('requestFailed', 'Request failed.'), 'error');
			}).fail(function () {
				AIPS.Utilities.showToast(t('requestFailed', 'Request failed.'), 'error');
			}).always(function () {
				$('#aips-stress-cleanup').prop('disabled', false);
			});
		},

		/**
		 * Increase the badge counting leftover test data.
		 *
		 * @param {number} delta
		 */
		bumpTestDataCount: function (delta) {
			var $badge = $('.aips-stress-testdata-count');
			var current = parseInt($badge.text(), 10) || 0;

			this.setTestDataCount(current + delta);
		},

		/**
		 * Set the leftover test-data badge, hiding it at zero.
		 *
		 * @param {number} count
		 */
		setTestDataCount: function (count) {
			var $badge = $('.aips-stress-testdata-count');

			$badge.text(String(count));

			if (count > 0) {
				$badge.removeAttr('hidden');
			} else {
				$badge.attr('hidden', true);
			}
		},

		// -------------------------------------------------------------------
		// Helpers
		// -------------------------------------------------------------------

		/**
		 * Human-readable elapsed time.
		 *
		 * @param {number} ms
		 * @returns {string}
		 */
		formatDuration: function (ms) {
			ms = parseInt(ms, 10) || 0;

			if (ms < 1000) {
				return ms + ' ms';
			}

			if (ms < 60000) {
				return (ms / 1000).toFixed(1) + ' s';
			}

			var minutes = Math.floor(ms / 60000);
			var seconds = Math.round((ms % 60000) / 1000);

			return minutes + 'm ' + seconds + 's';
		},

		// -------------------------------------------------------------------
		// History & Comparison / Diff Handling
		// -------------------------------------------------------------------

		/**
		 * Load recent history runs and populate the select box.
		 */
		loadRunHistory: function () {
			var $select = $('#aips-stress-history-select');
			if (!$select.length) {
				return;
			}

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_stress_test_get_history',
					nonce: settings.nonce,
					limit: 25
				}
			}).done(function (res) {
				if (res && res.success && res.data && res.data.runs) {
					var currentVal = $select.val();
					$select.find('option:not(:first)').remove();
					res.data.runs.forEach(function (run) {
						var label = run.formatted_date + ' (' + run.passed + '/' + run.total_cases + ' passed, ' + run.provider + ')';
						$('<option></option>').val(run.id).text(label).appendTo($select);
					});
					if (currentVal) {
						$select.val(currentVal);
					}
				}
			});
		},

		/**
		 * Auto-save completed suite run into the History API.
		 */
		saveRunToHistory: function () {
			var self = this;
			var results = [];
			$('.aips-stress-row').each(function () {
				var result = $(this).data('result');
				if (result) {
					results.push(result);
				}
			});

			if (!results.length) {
				return;
			}

			var meta = this.readExportMeta();
			var passed = results.filter(function (r) { return r.status === 'passed'; }).length;
			var payload = {
				environment: meta.environment || {},
				totals: {
					cases: results.length,
					passed: passed,
					failed: results.length - passed,
					duration_ms: results.reduce(function (sum, r) { return sum + (r.duration_ms || 0); }, 0)
				},
				results: results
			};

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_stress_test_save_run',
					nonce: settings.nonce,
					run_data: JSON.stringify(payload)
				}
			}).done(function (res) {
				if (res && res.success) {
					self.loadRunHistory();
				}
			});
		},

		/**
		 * Enable Compare button when a historical run is chosen.
		 */
		handleHistorySelectChange: function () {
			var val = $('#aips-stress-history-select').val();
			$('#aips-stress-compare-btn').prop('disabled', !val);
		},

		/**
		 * Compare selected historical run against the latest historical run or current results.
		 */
		handleCompareCurrentVsSelected: function (e) {
			e.preventDefault();
			var selectedId = parseInt($('#aips-stress-history-select').val(), 10);
			if (!selectedId) {
				return;
			}

			var $select = $('#aips-stress-history-select');
			var $options = $select.find('option:not(:first)');
			var baseId = selectedId;
			var targetId = $options.length > 1 && parseInt($options.first().val(), 10) !== selectedId ? parseInt($options.first().val(), 10) : selectedId;

			this.fetchAndShowDiff(baseId, targetId);
		},

		/**
		 * Handle checkboxes in the Stress Test History tab (max 2).
		 */
		handleHistoryCheckboxChange: function () {
			var $checked = $('.aips-stress-history-checkbox:checked');
			if ($checked.length > 2) {
				$(this).prop('checked', false);
				$checked = $('.aips-stress-history-checkbox:checked');
				AIPS.Utilities.showToast(t('maxTwoRuns', 'You can select up to 2 runs to compare.'), 'warning');
			}

			$('#aips-stress-history-diff-btn').prop('disabled', $checked.length !== 2);
		},

		/**
		 * Compare the two checked historical runs.
		 */
		handleCompareDualHistory: function (e) {
			e.preventDefault();
			var $checked = $('.aips-stress-history-checkbox:checked');
			if ($checked.length !== 2) {
				return;
			}

			var idA = parseInt($checked.eq(1).val(), 10); // earlier
			var idB = parseInt($checked.eq(0).val(), 10); // later

			this.fetchAndShowDiff(idA, idB);
		},

		/**
		 * View single run details.
		 */
		handleViewRunDetails: function (e) {
			e.preventDefault();
			var historyId = $(e.currentTarget).data('history-id');
			if (!historyId) {
				return;
			}

			var self = this;
			this.openDiffModal();
			var spinnerHtml = (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-stress-spinner'))
				? AIPS.Templates.render('aips-tmpl-stress-spinner', {})
				: '<div class="aips-spinner" style="text-align:center;padding:32px;"><span class="dashicons dashicons-update" style="animation:spin 1s infinite linear;font-size:28px;"></span></div>';
			$('#aips-stress-diff-body').html(spinnerHtml);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_stress_test_get_run',
					nonce: settings.nonce,
					history_id: historyId
				}
			}).done(function (res) {
				if (res && res.success && res.data && res.data.run) {
					self.renderSingleRunModal(res.data.run);
				} else {
					var msg = res.data ? res.data.message : 'Error loading run.';
					var noticeHtml = (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-stress-notice'))
						? AIPS.Templates.render('aips-tmpl-stress-notice', { type: 'danger', message: msg })
						: '<div class="aips-stress-notice aips-stress-notice-danger"><p>' + msg + '</p></div>';
					$('#aips-stress-diff-body').html(noticeHtml);
				}
			}).fail(function () {
				var failMsg = t('requestFailed', 'Request failed.');
				var noticeHtml = (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-stress-notice'))
					? AIPS.Templates.render('aips-tmpl-stress-notice', { type: 'danger', message: failMsg })
					: '<div class="aips-stress-notice aips-stress-notice-danger"><p>' + failMsg + '</p></div>';
				$('#aips-stress-diff-body').html(noticeHtml);
			});
		},

		/**
		 * Fetch diff between two runs and render modal.
		 */
		fetchAndShowDiff: function (runAId, runBId) {
			var self = this;
			this.openDiffModal();
			var spinnerHtml = (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-stress-spinner'))
				? AIPS.Templates.render('aips-tmpl-stress-spinner', {})
				: '<div class="aips-spinner" style="text-align:center;padding:32px;"><span class="dashicons dashicons-update" style="animation:spin 1s infinite linear;font-size:28px;"></span></div>';
			$('#aips-stress-diff-body').html(spinnerHtml);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_stress_test_diff_runs',
					nonce: settings.nonce,
					run_a_id: runAId,
					run_b_id: runBId
				}
			}).done(function (res) {
				if (res && res.success && res.data && res.data.diff) {
					self.renderDiffModal(res.data.diff);
				} else {
					var msg = res.data ? res.data.message : 'Error calculating diff.';
					var noticeHtml = (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-stress-notice'))
						? AIPS.Templates.render('aips-tmpl-stress-notice', { type: 'danger', message: msg })
						: '<div class="aips-stress-notice aips-stress-notice-danger"><p>' + msg + '</p></div>';
					$('#aips-stress-diff-body').html(noticeHtml);
				}
			}).fail(function () {
				var failMsg = t('requestFailed', 'Request failed.');
				var noticeHtml = (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-stress-notice'))
					? AIPS.Templates.render('aips-tmpl-stress-notice', { type: 'danger', message: failMsg })
					: '<div class="aips-stress-notice aips-stress-notice-danger"><p>' + failMsg + '</p></div>';
				$('#aips-stress-diff-body').html(noticeHtml);
			});
		},

		openDiffModal: function () {
			$('#aips-stress-diff-modal').removeAttr('hidden').css('display', 'flex');
			$('body').addClass('modal-open');
		},

		closeDiffModal: function () {
			$('#aips-stress-diff-modal').attr('hidden', true).css('display', 'none');
			$('body').removeClass('modal-open');
		},

		/**
		 * Render comparative diff modal view using AIPS.Templates.
		 */
		renderDiffModal: function (diff) {
			var runA = diff.run_a;
			var runB = diff.run_b;
			var caseDiffs = diff.case_diffs || [];
			var totalsDiff = diff.totals_diff || {};

			var durDiff = totalsDiff.duration_diff || 0;
			var durBadge = durDiff < 0
				? '<span class="aips-stress-diff-delta-faster">(' + (durDiff / 1000).toFixed(2) + ' s faster)</span>'
				: (durDiff > 0 ? '<span class="aips-stress-diff-delta-slower">(+' + (durDiff / 1000).toFixed(2) + ' s slower)</span>' : '');

			var rowsHtml = '';
			caseDiffs.forEach(function (c) {
				var changeClass = c.status_change === 'regressed' ? 'aips-stress-diff-change-regressed' : (c.status_change === 'improved' ? 'aips-stress-diff-change-improved' : '');
				var deltaBadge = c.duration_diff < 0
					? '<span class="aips-stress-diff-delta-faster">' + c.duration_diff + ' ms</span>'
					: (c.duration_diff > 0 ? '<span class="aips-stress-diff-delta-slower">+' + c.duration_diff + ' ms</span>' : '0 ms');

				var changeLabel = c.status_change === 'regressed'
					? '<span class="aips-badge aips-badge-error">Regressed</span>'
					: (c.status_change === 'improved' ? '<span class="aips-badge aips-badge-success">Improved</span>' : '<span class="aips-badge aips-badge-neutral">Unchanged</span>');

				if (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-stress-diff-row')) {
					rowsHtml += AIPS.Templates.renderRaw('aips-tmpl-stress-diff-row', {
						changeClass: changeClass,
						label: AIPS.Templates.escape(c.label || c.case),
						summary: AIPS.Templates.escape(c.summary_b || c.summary_a || ''),
						statusA: AIPS.Templates.escape(c.status_a),
						statusABadgeClass: c.status_a === 'passed' ? 'aips-badge-success' : 'aips-badge-error',
						durationA: c.duration_a,
						statusB: AIPS.Templates.escape(c.status_b),
						statusBBadgeClass: c.status_b === 'passed' ? 'aips-badge-success' : 'aips-badge-error',
						durationB: c.duration_b,
						deltaBadgeHtml: deltaBadge,
						changeBadgeHtml: changeLabel
					});
				} else {
					rowsHtml += '<tr class="' + changeClass + '">' +
						'<td><strong>' + (c.label || c.case) + '</strong><div style="font-size:11px;color:#64748b;">' + (c.summary_b || c.summary_a) + '</div></td>' +
						'<td><span class="aips-badge ' + (c.status_a === 'passed' ? 'aips-badge-success' : 'aips-badge-error') + '">' + c.status_a + '</span> (' + c.duration_a + ' ms)</td>' +
						'<td><span class="aips-badge ' + (c.status_b === 'passed' ? 'aips-badge-success' : 'aips-badge-error') + '">' + c.status_b + '</span> (' + c.duration_b + ' ms)</td>' +
						'<td>' + deltaBadge + '</td>' +
						'<td>' + changeLabel + '</td>' +
					'</tr>';
				}
			});

			if (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-stress-diff-modal')) {
				var modalHtml = AIPS.Templates.renderRaw('aips-tmpl-stress-diff-modal', {
					runAId: runA.id,
					runAStatus: AIPS.Templates.escape(runA.status),
					runABadgeClass: runA.status === 'completed' ? 'aips-badge-success' : 'aips-badge-warning',
					runADate: AIPS.Templates.escape(runA.formatted_date),
					runAProvider: AIPS.Templates.escape(runA.environment.provider || 'Default'),
					runAModel: AIPS.Templates.escape(runA.environment.model || 'Default Model'),
					runAPassed: runA.totals.passed || 0,
					runATotal: runA.totals.cases || 0,
					runADuration: ((runA.totals.duration_ms || 0) / 1000).toFixed(2) + ' s',

					runBId: runB.id,
					runBStatus: AIPS.Templates.escape(runB.status),
					runBBadgeClass: runB.status === 'completed' ? 'aips-badge-success' : 'aips-badge-warning',
					runBDate: AIPS.Templates.escape(runB.formatted_date),
					runBProvider: AIPS.Templates.escape(runB.environment.provider || 'Default'),
					runBModel: AIPS.Templates.escape(runB.environment.model || 'Default Model'),
					runBPassed: runB.totals.passed || 0,
					runBTotal: runB.totals.cases || 0,
					runBDuration: ((runB.totals.duration_ms || 0) / 1000).toFixed(2) + ' s',
					durBadgeHtml: durBadge,
					rowsHtml: rowsHtml
				});
				$('#aips-stress-diff-body').html(modalHtml);
			} else {
				var fallbackHtml = '<div class="aips-stress-diff-meta-grid">' +
					'<div class="aips-stress-diff-card">' +
						'<h4>Base Run (# ' + runA.id + ') <span class="aips-badge ' + (runA.status === 'completed' ? 'aips-badge-success' : 'aips-badge-warning') + '">' + runA.status + '</span></h4>' +
						'<div class="aips-stress-diff-meta-list">' +
							'<div><strong>Date:</strong> ' + runA.formatted_date + '</div>' +
							'<div><strong>Provider:</strong> ' + (runA.environment.provider || 'Default') + ' (' + (runA.environment.model || 'Default Model') + ')</div>' +
							'<div><strong>Passed:</strong> ' + (runA.totals.passed || 0) + ' / ' + (runA.totals.cases || 0) + ' cases</div>' +
							'<div><strong>Total Duration:</strong> ' + ((runA.totals.duration_ms || 0) / 1000).toFixed(2) + ' s</div>' +
						'</div>' +
					'</div>' +
					'<div class="aips-stress-diff-card">' +
						'<h4>Target / Comparison Run (# ' + runB.id + ') <span class="aips-badge ' + (runB.status === 'completed' ? 'aips-badge-success' : 'aips-badge-warning') + '">' + runB.status + '</span></h4>' +
						'<div class="aips-stress-diff-meta-list">' +
							'<div><strong>Date:</strong> ' + runB.formatted_date + '</div>' +
							'<div><strong>Provider:</strong> ' + (runB.environment.provider || 'Default') + ' (' + (runB.environment.model || 'Default Model') + ')</div>' +
							'<div><strong>Passed:</strong> ' + (runB.totals.passed || 0) + ' / ' + (runB.totals.cases || 0) + ' cases</div>' +
							'<div><strong>Total Duration:</strong> ' + ((runB.totals.duration_ms || 0) / 1000).toFixed(2) + ' s ' + durBadge + '</div>' +
						'</div>' +
					'</div>' +
				'</div>';

				fallbackHtml += '<table class="aips-stress-diff-table">' +
					'<thead>' +
						'<tr>' +
							'<th>Test Case</th>' +
							'<th>Base Run Status</th>' +
							'<th>Target Run Status</th>' +
							'<th>Duration Diff</th>' +
							'<th>Outcome Delta</th>' +
						'</tr>' +
					'</thead>' +
					'<tbody>' + rowsHtml + '</tbody></table>';

				$('#aips-stress-diff-body').html(fallbackHtml);
			}
		},

		/**
		 * Render single run modal view using AIPS.Templates.
		 */
		renderSingleRunModal: function (run) {
			var results = run.results || [];
			var rowsHtml = '';

			results.forEach(function (r) {
				if (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-stress-single-run-row')) {
					rowsHtml += AIPS.Templates.render('aips-tmpl-stress-single-run-row', {
						label: r.label || r.case,
						status: r.status,
						badgeClass: r.status === 'passed' ? 'aips-badge-success' : 'aips-badge-error',
						duration: r.duration_ms || 0,
						summary: r.summary || ''
					});
				} else {
					rowsHtml += '<tr>' +
						'<td><strong>' + (r.label || r.case) + '</strong></td>' +
						'<td><span class="aips-badge ' + (r.status === 'passed' ? 'aips-badge-success' : 'aips-badge-error') + '">' + r.status + '</span></td>' +
						'<td>' + (r.duration_ms || 0) + ' ms</td>' +
						'<td>' + (r.summary || '') + '</td>' +
					'</tr>';
				}
			});

			if (window.AIPS && AIPS.Templates && AIPS.Templates.get('aips-tmpl-stress-single-run')) {
				var modalHtml = AIPS.Templates.renderRaw('aips-tmpl-stress-single-run', {
					id: run.id,
					status: AIPS.Templates.escape(run.status),
					badgeClass: run.status === 'completed' ? 'aips-badge-success' : 'aips-badge-warning',
					date: AIPS.Templates.escape(run.formatted_date),
					provider: AIPS.Templates.escape(run.environment.provider || 'Default'),
					model: AIPS.Templates.escape(run.environment.model || 'Default'),
					passed: run.totals.passed || 0,
					failed: run.totals.failed || 0,
					total: run.totals.cases || 0,
					duration: ((run.totals.duration_ms || 0) / 1000).toFixed(2) + ' s',
					rowsHtml: rowsHtml
				});
				$('#aips-stress-diff-body').html(modalHtml);
			} else {
				var fallbackHtml = '<div class="aips-stress-diff-card" style="margin-bottom:16px;">' +
					'<h4>Stress Test Run #' + run.id + ' <span class="aips-badge ' + (run.status === 'completed' ? 'aips-badge-success' : 'aips-badge-warning') + '">' + run.status + '</span></h4>' +
					'<div class="aips-stress-diff-meta-list">' +
						'<div><strong>Timestamp:</strong> ' + run.formatted_date + '</div>' +
						'<div><strong>Provider:</strong> ' + (run.environment.provider || 'Default') + ' | <strong>Model:</strong> ' + (run.environment.model || 'Default') + '</div>' +
						'<div><strong>Results:</strong> ' + (run.totals.passed || 0) + ' passed, ' + (run.totals.failed || 0) + ' failed across ' + (run.totals.cases || 0) + ' cases</div>' +
						'<div><strong>Total Execution Time:</strong> ' + ((run.totals.duration_ms || 0) / 1000).toFixed(2) + ' s</div>' +
					'</div>' +
				'</div>';

				fallbackHtml += '<table class="aips-table" style="width:100%;">' +
					'<thead>' +
						'<tr>' +
							'<th>Case</th>' +
							'<th>Status</th>' +
							'<th>Duration</th>' +
							'<th>Summary</th>' +
						'</tr>' +
					'</thead>' +
					'<tbody>' + rowsHtml + '</tbody></table>';

				$('#aips-stress-diff-body').html(fallbackHtml);
			}
		}
	};

	$(document).ready(function () {
		AIPS.StressTest.init();
	});
})(jQuery);
