/**
 * AIPS Shared Utilities
 *
 * Common/shared utility functions used across all AIPS admin pages.
 *
 * @package AI_Post_Scheduler
 * @since 2.8.3
 */

import $ from 'jquery';
import { DateTime } from './datetime';

// Shared constant reused by escapeAttribute — defined once to avoid per-call allocation.
const AIPS_ATTR_ENTITY_MAP = {
	'&':  '&amp;',
	'"':  '&quot;',
	"'":  '&#039;',
	'<':  '&lt;',
	'>':  '&gt;',
	'\r': '&#13;',
	'\n': '&#10;',
	'\t': '&#9;'
};

export const Utilities = {
	/**
	 * Placeholder initialisation hook for the Utilities namespace.
	 */
	init() {
		// Nothing needed on init currently; reserved for future use.
	},

	/**
	 * Displays a toast notification centered ~1/3 down from the top of the screen,
	 * aligned to the horizontal center of .aips-page-container when present.
	 *
	 * Accepts plain text or pre-built HTML (for links). Plain-text messages
	 * are auto-escaped; if you pass HTML, set `isHtml` to true.
	 *
	 * @param {string}  message           - The message to display.
	 * @param {string}  [type='info']     - One of 'success', 'error', 'warning', 'info'.
	 * @param {Object}  [opts]            - Optional settings.
	 * @param {boolean} [opts.isHtml]     - If true, message is inserted as raw HTML.
	 * @param {number}  [opts.duration]   - Auto-dismiss delay in ms (0 = no auto-dismiss). Default 6000.
	 */
	showToast(message, type = 'info', opts = {}) {
		const duration = opts.duration !== undefined ? opts.duration : 6000;
		const isHtml   = opts.isHtml || false;

		const iconMap = { success: '\u2713', error: '\u2715', warning: '\u26A0', info: '\u2139' };

		let $container = $('#aips-toast-container');
		if (!$container.length) {
			$container = $('<div id="aips-toast-container"></div>');
			$('body').append($container);
			this._positionToastContainer($container);
		}

		const closeLabel = (window.aipsUtilitiesL10n && window.aipsUtilitiesL10n.closeLabel) ? window.aipsUtilitiesL10n.closeLabel : 'Close notification';
		const safeMessage = isHtml ? message : $('<div>').text(message).html();

		const $toast = $('<div class="aips-toast ' + type + '">')
			.append('<span class="aips-toast-icon">' + iconMap[type] + '</span>')
			.append('<div class="aips-toast-message">' + safeMessage + '</div>')
			.append($('<button class="aips-toast-close">&times;</button>').attr('aria-label', closeLabel));

		$container.append($toast);

		$toast.find('.aips-toast-close').on('click', () => {
			$toast.addClass('closing');
			setTimeout(() => { $toast.remove(); }, 300);
		});

		if (duration > 0) {
			setTimeout(() => {
				if ($toast.parent().length) {
					$toast.addClass('closing');
					setTimeout(() => { $toast.remove(); }, 300);
				}
			}, duration);
		}
	},

	/**
	 * Aligns the toast container's horizontal center to .aips-page-container
	 * (or #wpcontent as a fallback) so toasts appear centered within the
	 * plugin's content area rather than the full viewport.
	 *
	 * Also attaches a debounced window resize listener so the position stays
	 * correct if the browser window is resized or the sidebar is toggled.
	 *
	 * @param {jQuery} $container - The #aips-toast-container element.
	 * @private
	 */
	_positionToastContainer($container) {
		const reposition = () => {
			const el = document.querySelector('.aips-page-container') ||
					 document.getElementById('wpcontent');
			if (!el) { return; }
			const rect = el.getBoundingClientRect();
			$container.css('left', Math.round(rect.left + rect.width / 2) + 'px');
		};

		reposition();

		let resizeTimer;
		$(window).on('resize.aips-toast', () => {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(reposition, 100);
		});
	},

	/**
	 * Shows a styled modal confirmation dialog.
	 *
	 * Replaces the native browser confirm() with a non-blocking, styled modal.
	 * The caller provides an action callback on the relevant button.
	 *
	 * @param {string} message            - The message/body text to display.
	 * @param {string} [heading='Notice'] - The modal heading/title.
	 * @param {Array}  [buttons]          - Array of button config objects.
	 */
	confirm(message, heading = 'Notice', buttons) {
		if (!buttons || !buttons.length) {
			buttons = [
				{ label: 'OK', className: 'aips-btn aips-btn-primary' }
			];
		}

		const headingId = 'aips-confirm-heading-' + Date.now() + '-' + Math.floor(Math.random() * 1000000);

		// Build the overlay
		const $overlay = $('<div></div>')
			.addClass('aips-confirm-overlay')
			.attr({ role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': headingId });

		const $dialog = $('<div class="aips-confirm-dialog"></div>');

		const $header = $('<div class="aips-confirm-header"></div>')
			.append($('<h3></h3>').attr({ id: headingId, 'class': 'aips-confirm-heading' }).text(heading));

		const $body = $('<div class="aips-confirm-body"></div>')
			.append($('<p class="aips-confirm-message"></p>').text(message));

		const $footer = $('<div class="aips-confirm-footer"></div>');

		const closeDialog = () => {
			$overlay.addClass('aips-confirm-closing');
			setTimeout(() => { $overlay.remove(); }, 200);
			$(document).off('keydown.aips-confirm');
		};

		$.each(buttons, (i, btn) => {
			const label     = btn.label     || 'OK';
			const className = btn.className || 'aips-btn aips-btn-secondary';
			const action    = typeof btn.action === 'function' ? btn.action : null;

			const $btn = $('<button type="button"></button>')
				.addClass(className)
				.text(label);

			$btn.on('click', () => {
				closeDialog();
				if (action) {
					action();
				}
			});

			$footer.append($btn);
		});

		$dialog.append($header, $body, $footer);
		$overlay.append($dialog);
		$('body').append($overlay);

		// Focus the first button for accessibility
		$footer.find('button').first().trigger('focus');

		// Close on Escape key
		$(document).on('keydown.aips-confirm', e => {
			if (e.key === 'Escape') {
				closeDialog();
			}
		});

		// Close when clicking the backdrop (outside the dialog)
		$overlay.on('click', e => {
			if ($(e.target).is($overlay)) {
				closeDialog();
			}
		});
	},

	/**
	 * Display a modal dialog with optional form inputs.
	 *
	 * This is a more flexible version of `confirm()` that supports form inputs.
	 * Form field values are collected and passed to button action callbacks.
	 *
	 * @param {Object} options - Configuration object.
	 */
	showModal(options = {}) {
		const heading = options.heading || 'Notice';
		const message = options.message || '';
		const fields  = options.fields  || [];
		const buttons = options.buttons || [{ label: 'OK', className: 'aips-btn aips-btn-primary' }];

		const headingId = 'aips-modal-heading-' + Date.now() + '-' + Math.floor(Math.random() * 1000000);
		const uniqueId  = Date.now() + '-' + Math.floor(Math.random() * 1000000);

		// Build the overlay
		const $overlay = $('<div></div>')
			.addClass('aips-confirm-overlay')
			.attr({ role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': headingId });

		const $dialog = $('<div class="aips-confirm-dialog"></div>');

		const $header = $('<div class="aips-confirm-header"></div>')
			.append($('<h3></h3>').attr({ id: headingId, 'class': 'aips-confirm-heading' }).text(heading));

		const $body = $('<div class="aips-confirm-body"></div>');

		if (message) {
			$body.append($('<p class="aips-confirm-message"></p>').text(message));
		}

		// Build form fields
		const fieldMap = {}; // Map field names to jQuery input elements

		$.each(fields, (i, field) => {
			const fieldId = field.id || ('aips-modal-field-' + uniqueId + '-' + i);
			const fieldName = field.name || ('field_' + i);
			const fieldType = field.type || 'text';

			const $formGroup = $('<div class="form-group"></div>')
				.css({ marginTop: i > 0 ? '15px' : '10px' });

			const $label = $('<label></label>')
				.attr('for', fieldId)
				.text(field.label || fieldName);

			if (field.required) {
				$label.append(' <span style="color: #d63638;">*</span>');
			}

			$formGroup.append($label);

			let $input;

			if (fieldType === 'select') {
				$input = $('<select></select>')
					.attr('id', fieldId)
					.css({ width: '100%', padding: '8px', marginTop: '5px' });

				if (field.options && field.options.length) {
					$.each(field.options, (j, opt) => {
						const $option = $('<option></option>')
							.val(opt.value)
							.text(opt.label || opt.value);
						if (opt.value === field.value) {
							$option.attr('selected', 'selected');
						}
						$input.append($option);
					});
				}
			} else if (fieldType === 'textarea') {
				$input = $('<textarea></textarea>')
					.attr('id', fieldId)
					.css({ width: '100%', padding: '8px', marginTop: '5px', minHeight: '80px' })
					.val(field.value || '');

				if (field.placeholder) {
					$input.attr('placeholder', field.placeholder);
				}
			} else if (fieldType === 'checkbox') {
				$input = $('<input type="checkbox" />')
					.attr('id', fieldId)
					.css({ marginTop: '5px' });

				if (field.value) {
					$input.prop('checked', true);
				}
			} else {
				$input = $('<input />')
					.attr({ type: fieldType, id: fieldId })
					.css({ width: '100%', padding: '8px', marginTop: '5px' })
					.val(field.value || '');

				if (field.placeholder) {
					$input.attr('placeholder', field.placeholder);
				}
				if (fieldType === 'number') {
					if (field.min !== undefined) {
						$input.attr('min', field.min);
					}
					if (field.max !== undefined) {
						$input.attr('max', field.max);
					}
				}
			}

			if (field.className) {
				$input.addClass(field.className);
			}

			fieldMap[fieldName] = {
				$input: $input,
				type: fieldType,
				required: field.required || false,
				validate: field.validate || null
			};

			$formGroup.append($input);

			if (field.description) {
				$formGroup.append(
					$('<p class="description"></p>')
						.css({ marginTop: '6px' })
						.text(field.description)
				);
			}

			$body.append($formGroup);
		});

		const $footer = $('<div class="aips-confirm-footer"></div>');

		const keydownNamespace = 'keydown.aips-modal-' + uniqueId;

		const closeDialog = () => {
			$overlay.addClass('aips-confirm-closing');
			setTimeout(() => { $overlay.remove(); }, 200);
			$(document).off(keydownNamespace);
		};

		function getFormData() {
			const formData = {};
			$.each(fieldMap, (fieldName, fieldInfo) => {
				let val;
				if (fieldInfo.type === 'checkbox') {
					val = fieldInfo.$input.prop('checked');
				} else if (fieldInfo.type === 'number') {
					val = parseFloat(fieldInfo.$input.val());
					if (isNaN(val)) {
						val = null;
					}
				} else {
					val = fieldInfo.$input.val();
				}
				formData[fieldName] = val;
			});
			return formData;
		}

		function validateForm() {
			let firstError = null;

			$.each(fieldMap, (fieldName, fieldInfo) => {
				if (firstError) {
					return;
				}

				const val = fieldInfo.type === 'checkbox' ? fieldInfo.$input.prop('checked') : fieldInfo.$input.val();

				if (fieldInfo.required) {
					const labelText = fieldInfo.$input.prev('label').text() || fieldName;
					const requiredTpl = (window.aipsUtilitiesL10n && window.aipsUtilitiesL10n.fieldRequired) || '%s is required.';
					const requiredMsg = requiredTpl.replace('%s', labelText);

					if (fieldInfo.type === 'checkbox') {
						if (!val) {
							firstError = requiredMsg;
							return;
						}
					} else {
						if (!val || (typeof val === 'string' && val.trim() === '')) {
							firstError = requiredMsg;
							return;
						}
					}
				}

				if (fieldInfo.validate && typeof fieldInfo.validate === 'function') {
					const error = fieldInfo.validate(val);
					if (error) {
						firstError = error;
						return;
					}
				}
			});

			return firstError;
		}

		$.each(buttons, (i, btn) => {
			const label            = btn.label            || 'OK';
			const className        = btn.className        || 'aips-btn aips-btn-secondary';
			const action           = typeof btn.action === 'function' ? btn.action : null;
			const submit           = btn.submit           || false;
			const closeAfterAction = btn.closeAfterAction !== undefined ? btn.closeAfterAction : true;

			const $btn = $('<button type="button"></button>')
				.addClass(className)
				.text(label);

			$btn.on('click', () => {
				if (submit) {
					const error = validateForm();
					if (error) {
						Utilities.showToast(error, 'error');
						return;
					}
				}

				if (action) {
					const formData = getFormData();
					if (closeAfterAction) {
						closeDialog();
					}
					action(formData);
				} else {
					closeDialog();
				}
			});

			$footer.append($btn);
		});

		$dialog.append($header, $body, $footer);
		$overlay.append($dialog);
		$('body').append($overlay);

		if (fields.length > 0 && fieldMap[fields[0].name]) {
			setTimeout(() => {
				fieldMap[fields[0].name].$input.trigger('focus');
				if (fieldMap[fields[0].name].type !== 'checkbox') {
					fieldMap[fields[0].name].$input.trigger('select');
				}
			}, 100);
		} else {
			$footer.find('button').first().trigger('focus');
		}

		$(document).on(keydownNamespace, e => {
			if (e.key === 'Escape') {
				closeDialog();
			}
		});

		$overlay.on('click', e => {
			if ($(e.target).is($overlay)) {
				closeDialog();
			}
		});
	},

	/**
	 * Show feedback in the wizard notice region and toast system.
	 */
	showNotice(type, message) {
		const noticeClass = type === 'success' ? 'notice notice-success' : 'notice notice-error';
		const $notice = $(document.createElement('div')).addClass(noticeClass);
		const $message = $(document.createElement('p')).text(this.sanitizePlainText(message));

		$('#aips-campaign-wizard-notice').empty().append($notice.append($message));
		this.showToast(message, type);
	},

	/**
	 * Opens a non-dismissable progress-bar modal to give feedback during a
	 * long-running async operation (e.g. bulk post generation).
	 */
	showProgressBar(options = {}) {
		const l10n = options.l10n || window.aipsUtilitiesL10n || {};

		const title        = options.title        || 'Processing…';
		const message      = options.message      || '';
		const totalSeconds = options.totalSeconds  > 0 ? options.totalSeconds : 30;
		let stallAt      = (options.stallAt !== undefined) ? options.stallAt : 92;

		stallAt = Math.min(Math.max(stallAt, 10), 99);

		const headingId = 'aips-progress-heading-' + Date.now() + '-' + Math.floor(Math.random() * 1e6);

		const $overlay = $('<div></div>')
			.addClass('aips-confirm-overlay aips-progress-overlay')
			.attr({ role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': headingId });

		const $dialog = $('<div class="aips-confirm-dialog aips-progress-dialog"></div>');

		const $header = $('<div class="aips-confirm-header"></div>')
			.append($('<h3></h3>').attr({ id: headingId, 'class': 'aips-confirm-heading' }).text(title));

		const $body = $('<div class="aips-confirm-body aips-progress-body"></div>');

		if (message) {
			$body.append($('<p class="aips-confirm-message aips-progress-description"></p>').text(message));
		}

		const $barWrap = $('<div class="aips-progress-bar-wrap"></div>');
		const $barFill = $('<div class="aips-progress-bar-fill"></div>')
			.attr({
				role: 'progressbar',
				'aria-valuemin': '0',
				'aria-valuemax': '100',
				'aria-valuenow': '0'
			})
			.css('width', '0%');
		$barWrap.append($barFill);
		$body.append($barWrap);

		const $statusLine = $('<p class="aips-progress-status"></p>');
		$body.append($statusLine);

		const $liveRegion = $('<span class="screen-reader-text" aria-live="polite" aria-atomic="true"></span>');
		$body.append($liveRegion);

		$dialog.append($header, $body);
		$overlay.append($dialog);
		$('body').append($overlay);

		$dialog.attr('tabindex', '-1');
		setTimeout(() => {
			$dialog.trigger('focus');
		}, 0);

		const startTime        = Date.now();
		let tickInterval;
		let closed           = false;
		let overdue          = false;
		let lastAnnounceTime = 0;
		const ANNOUNCE_INTERVAL_MS = 5000;

		const cancel = () => {
			if (closed) { return; }
			closed = true;
			clearInterval(tickInterval);
			$overlay.addClass('aips-confirm-closing');
			setTimeout(() => { $overlay.remove(); }, 200);
		};

		const complete = (completionMessage, type) => {
			if (closed) { return; }
			clearInterval(tickInterval);
			$barFill
				.removeClass('aips-progress-bar-fill--indeterminate')
				.css('width', '100%')
				.attr('aria-valuenow', '100');

			const msg = completionMessage || l10n.generationComplete || 'Generation complete!';
			$statusLine.text(msg);
			$liveRegion.text(msg);

			setTimeout(() => { cancel(); }, 1200);
		};

		function tick() {
			if (closed) { return; }

			const elapsed   = (Date.now() - startTime) / 1000;
			const remaining = Math.max(0, totalSeconds - elapsed);

			if (remaining <= 0) {
				if (!overdue) {
					overdue = true;
					$barFill
						.css('width', '100%')
						.attr('aria-valuenow', '100')
						.addClass('aips-progress-bar-fill--indeterminate');
					const overdueMsg = l10n.takingLonger || 'Taking a little bit longer than expected…';
					$statusLine.text(overdueMsg);
					$liveRegion.text(overdueMsg);
				}
				return;
			}

			const progress = Math.min((elapsed / totalSeconds) * 100, stallAt);
			const pct      = progress.toFixed(1);

			$barFill.css('width', pct + '%').attr('aria-valuenow', Math.round(progress));

			const tpl      = l10n.estimatedTimeRemaining || 'Estimated time remaining: %s';
			const timeText = tpl.replace('%s', DateTime.formatCountdown(remaining, l10n));

			$statusLine.text(timeText);

			const now = Date.now();
			if (now - lastAnnounceTime >= ANNOUNCE_INTERVAL_MS) {
				$liveRegion.text(timeText);
				lastAnnounceTime = now;
			}
		}

		tickInterval = setInterval(tick, 500);
		tick();

		return { complete: complete, cancel: cancel };
	},

	/**
	 * Puts a button into a loading state.
	 */
	setButtonLoading($btn, loadingLabel, opts = {}) {
		$btn.data('aips-btn-original', $btn.html());
		$btn.prop('disabled', true);
		if (opts.isHtml) {
			$btn.html(loadingLabel);
		} else {
			$btn.text(loadingLabel);
		}
	},

	/**
	 * Restores a button that was disabled by `setButtonLoading()`.
	 */
	resetButton($btn) {
		const original = $btn.data('aips-btn-original');
		if (original !== undefined) {
			$btn.html(original);
			$btn.removeData('aips-btn-original');
		}
		$btn.prop('disabled', false);
	},

	/**
	 * Escape a plain-text value for safe insertion as HTML content.
	 */
	escapeHtml(text) {
		if (text === null || text === undefined) {
			return '';
		}
		const div = document.createElement('div');
		div.textContent = String(text);
		return div.innerHTML;
	},

	/**
	 * Escape a plain-text value for safe use in an HTML attribute.
	 */
	escapeAttribute(text) {
		if (text === null || text === undefined) {
			return '';
		}
		return String(text).replace(/[&"'<>\r\n\t]/g, match => AIPS_ATTR_ENTITY_MAP[match]);
	},

	/**
	 * Sanitize a plain-text scalar by stripping ASCII control characters.
	 */
	sanitizePlainText(value) {
		if (value === null || value === undefined) {
			return '';
		}
		return String(value).replace(/[\u0000-\u001F\u007F]/g, '').trim();
	},

	/**
	 * Sanitize textarea text while preserving user-authored formatting.
	 */
	sanitizeTextareaText(value) {
		if (value === null || value === undefined) {
			return '';
		}
		return String(value).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, '').trim();
	},

	/**
	 * Sanitize a URL value for safe use in an href attribute.
	 */
	sanitizeUrl(url) {
		if (!url) {
			return '';
		}
		const urlStr = String(url).trim();
		if (!urlStr) {
			return '';
		}
		const dangerous = ['javascript:', 'data:', 'vbscript:', 'file:'];
		const lower = urlStr.toLowerCase();
		for (let i = 0; i < dangerous.length; i++) {
			if (lower.indexOf(dangerous[i]) === 0) {
				return '';
			}
		}
		if (urlStr.indexOf('http://') === 0 || urlStr.indexOf('https://') === 0) {
			try {
				return new URL(urlStr).href;
			} catch (e) {
				return '';
			}
		}
		if (urlStr.indexOf('/') === 0 && urlStr.indexOf('//') !== 0) {
			return urlStr;
		}
		return '';
	},

	/**
	 * Convert a string to Title Case.
	 */
	toTitleCase(text) {
		if (text === null || text === undefined) {
			return '';
		}
		return String(text)
			.toLowerCase()
			.replace(/[_-]/g, ' ')
			.replace(/\b\w/g, letter => letter.toUpperCase());
	},

	/**
	 * Apply alpha transparency to a hex colour string.
	 */
	toAlpha(hex, alpha) {
		if (typeof hex !== 'string' || !/^#[0-9a-fA-F]{6}$/.test(hex)) {
			return 'rgba(0,0,0,0)';
		}

		let normalizedAlpha = Number(alpha);

		if (!isFinite(normalizedAlpha)) {
			return 'rgba(0,0,0,0)';
		}

		normalizedAlpha = Math.max(0, Math.min(1, normalizedAlpha));
		const r = parseInt(hex.slice(1, 3), 16);
		const g = parseInt(hex.slice(3, 5), 16);
		const b = parseInt(hex.slice(5, 7), 16);

		return 'rgba(' + r + ',' + g + ',' + b + ',' + normalizedAlpha + ')';
	}
};

export default Utilities;
