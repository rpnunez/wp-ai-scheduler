/**
 * AIPS.DateTime — shared date/time formatting utilities.
 *
 * Provides canonical helpers for parsing timestamps (Unix integers, MySQL
 * datetime strings, ISO strings, or Date objects) and formatting them for
 * display.
 *
 * @package AI_Post_Scheduler
 * @since   2.8.3
 */

export const DateTime = {
	/**
	 * Get a localized string from an l10n object with an English fallback.
	 *
	 * Checks the provided l10n map first, then the global window.aipsDateTimeL10n,
	 * then uses the fallback string.
	 *
	 * @param {Object|null} l10n     L10n map with standard AIPS.DateTime keys.
	 * @param {string}      key      Standard AIPS.DateTime l10n key.
	 * @param {string}      fallback English fallback string.
	 * @return {string}
	 */
	_l10n(l10n, key, fallback) {
		const source = l10n || window.aipsDateTimeL10n || {};
		const val = source[key];
		return (val !== null && val !== undefined && String(val)) || fallback;
	},

	/**
	 * Parse any timestamp-like value into a JavaScript Date.
	 *
	 * Handles:
	 * - Date instance (returned as-is when valid)
	 * - Positive numeric value: treated as Unix seconds when <= 32503680000
	 *   (year 3000), otherwise treated as milliseconds.
	 * - MySQL datetime string 'YYYY-MM-DD HH:MM:SS' (space normalised to T).
	 * - ISO 8601 string.
	 *
	 * @param {*} value
	 * @return {Date|null} null when the value cannot be parsed.
	 */
	parse(value) {
		if (value instanceof Date) {
			return isNaN(value.getTime()) ? null : value;
		}

		if (value === null || value === undefined || value === '') {
			return null;
		}

		// Numeric: treat as Unix timestamp (seconds) within a sane range.
		const n = Number(value);
		if (!isNaN(n) && n > 0) {
			// Values <= 32503680000 are seconds (up to year 3000).
			// Larger values are assumed to be milliseconds.
			return new Date(n <= 32503680000 ? n * 1000 : n);
		}

		// String: normalise MySQL space separator to 'T' for ISO parsing.
		const str = String(value).replace(' ', 'T');
		const d = new Date(str);
		return isNaN(d.getTime()) ? null : d;
	},

	/**
	 * Return the Intl locale string for date/time formatting.
	 *
	 * Reads `locale` from the provided l10n object, then from the global
	 * window.aipsDateTimeL10n, then falls back to the browser default.
	 *
	 * @param {Object|null} l10n
	 * @return {string|undefined}
	 */
	getLocale(l10n) {
		const source = l10n || window.aipsDateTimeL10n || {};
		if (typeof source.locale !== 'string' || !source.locale) {
			return undefined;
		}

		// WordPress locales are often underscored (e.g. en_US). Intl expects
		// BCP-47 language tags, so normalize underscores to hyphens.
		return source.locale.replace(/_/g, '-');
	},

	/**
	 * Format a date as an abbreviated date stamp (e.g. "Apr-15-26").
	 *
	 * @param {Date}        date
	 * @param {Object|null} l10n  Used for the `locale` key.
	 * @return {string}
	 */
	formatDate(date, l10n) {
		const locale = this.getLocale(l10n);
		const parts = new Intl.DateTimeFormat(locale, {
			month: 'short',
			day: '2-digit',
			year: '2-digit'
		}).formatToParts(date);

		let month = '';
		let day = '';
		let year = '';

		parts.forEach(part => {
			if (part.type === 'month') {
				month = part.value;
			} else if (part.type === 'day') {
				day = part.value;
			} else if (part.type === 'year') {
				year = part.value;
			}
		});

		return `${month}-${day}-${year}`;
	},

	/**
	 * Format a time component without spaces before AM/PM (e.g. "2:30PM").
	 *
	 * @param {Date}        date
	 * @param {Object|null} l10n  Used for the `locale` key.
	 * @return {string}
	 */
	formatTime(date, l10n) {
		const locale = this.getLocale(l10n);
		const parts = new Intl.DateTimeFormat(locale, {
			hour: 'numeric',
			minute: '2-digit'
		}).formatToParts(date);

		let hour = '';
		let minute = '';
		let dayPeriod = '';

		parts.forEach(part => {
			if (part.type === 'hour') {
				hour = part.value;
			} else if (part.type === 'minute') {
				minute = part.value;
			} else if (part.type === 'dayPeriod') {
				dayPeriod = part.value.replace('.', '').toUpperCase();
			}
		});

		return `${hour}:${minute}${dayPeriod ? dayPeriod : ''}`;
	},

	/**
	 * Format a same-day relative duration (e.g. "2 minutes ago").
	 *
	 * Standard l10n keys: justNow, minuteAgo, minutesAgo (%s),
	 * hourAgo, hoursAgo (%s), hoursMinutesAgo (%1$s %2$s).
	 *
	 * @param {Date}        date Source timestamp.
	 * @param {Date}        now  Reference timestamp (current time).
	 * @param {Object|null} l10n
	 * @return {string}
	 */
	formatDurationSince(date, now, l10n) {
		const diffMinutes = Math.max(0, Math.floor((now.getTime() - date.getTime()) / 60000));

		if (diffMinutes < 1) {
			return this._l10n(l10n, 'justNow', 'just now');
		}

		if (diffMinutes < 60) {
			if (diffMinutes === 1) {
				return this._l10n(l10n, 'minuteAgo', '1 minute ago');
			}
			return this._l10n(l10n, 'minutesAgo', '%s minutes ago').replace('%s', diffMinutes);
		}

		const hours = Math.floor(diffMinutes / 60);
		const minutes = diffMinutes % 60;

		if (minutes === 0) {
			if (hours === 1) {
				return this._l10n(l10n, 'hourAgo', '1 hour ago');
			}
			return this._l10n(l10n, 'hoursAgo', '%s hours ago').replace('%s', hours);
		}

		return this._l10n(l10n, 'hoursMinutesAgo', '%1$s hours and %2$s minutes ago')
			.replace('%1$s', hours)
			.replace('%2$s', minutes);
	},

	/**
	 * Format a timestamp relative to now.
	 *
	 * - Today:     "2 minutes ago" / "1 hour ago" etc.
	 * - Yesterday: "yesterday at 2:30PM"
	 * - Older:     "Apr-15-26 2:30PM"
	 *
	 * Accepts Unix integer (seconds), MySQL datetime string, ISO string, or Date.
	 *
	 * Standard l10n keys: (all keys from formatDurationSince) +
	 * yesterdayAt (%s), absoluteDate (%1$s %2$s), locale.
	 *
	 * @param {*}           value
	 * @param {Object|null} l10n
	 * @return {string}
	 */
	formatRelative(value, l10n) {
		const date = this.parse(value);
		if (!date) {
			return String(value === null || value === undefined ? '\u2014' : value);
		}

		const now = new Date();
		const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
		const startOfYesterday = new Date(startOfToday.getTime() - 86400000);
		const timePart = this.formatTime(date, l10n);

		if (date >= startOfToday) {
			return this.formatDurationSince(date, now, l10n);
		}

		if (date >= startOfYesterday && date < startOfToday) {
			return this._l10n(l10n, 'yesterdayAt', 'yesterday at %s').replace('%s', timePart);
		}

		const absoluteTpl = this._l10n(l10n, 'absoluteDate', '%1$s %2$s');
		return absoluteTpl
			.replace('%1$s', this.formatDate(date, l10n))
			.replace('%2$s', timePart);
	},

	/**
	 * Format a timestamp as a labelled date+time display string.
	 *
	 * Unlike formatRelative, today's items show the "Today" label rather
	 * than a duration-based string like "2 minutes ago".
	 *
	 * - Today:     "Today, 2:32PM"
	 * - Yesterday: "Yesterday, 2:32PM"
	 * - Older:     "April 15, 2026 2:32PM"
	 *
	 * Accepts Unix integer (seconds), MySQL datetime string, ISO string, or Date.
	 *
	 * Standard l10n keys: today, yesterday, locale.
	 *
	 * @param {*}           value
	 * @param {Object|null} l10n
	 * @return {string}
	 */
	formatDateLabel(value, l10n) {
		const date = this.parse(value);
		if (!date) {
			return String(value === null || value === undefined ? '\u2014' : value);
		}

		const now = new Date();
		const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
		const startOfYesterday = new Date(startOfToday.getTime() - 86400000);
		const timePart = this.formatTime(date, l10n);
		const locale = this.getLocale(l10n);

		if (date >= startOfToday) {
			return this._l10n(l10n, 'today', 'Today') + ', ' + timePart;
		}

		if (date >= startOfYesterday && date < startOfToday) {
			return this._l10n(l10n, 'yesterday', 'Yesterday') + ', ' + timePart;
		}

		const dateStr = new Intl.DateTimeFormat(locale, {
			month: 'long',
			day: 'numeric',
			year: 'numeric'
		}).format(date);

		return `${dateStr} ${timePart}`;
	},

	/**
	 * Format a duration in seconds to a compact human-readable string.
	 *
	 * Examples: "45s", "1m 03s", "12m 00s".
	 * Used for history/run duration display.
	 *
	 * @param {number|string} seconds
	 * @return {string}
	 */
	formatDuration(seconds) {
		seconds = parseInt(seconds, 10);
		if (isNaN(seconds) || seconds < 0) {
			return '\u2014';
		}
		if (seconds < 60) {
			return seconds + 's';
		}
		const m = Math.floor(seconds / 60);
		const s = seconds % 60;
		return m + 'm ' + (s < 10 ? '0' : '') + s + 's';
	},

	/**
	 * Format a countdown in seconds to a human-readable string.
	 *
	 * Examples: "30 seconds", "1 minute", "2m 30s".
	 * Used for progress-bar estimated time remaining display.
	 *
	 * Standard l10n keys: seconds, minute, minutes (%d), minutesSeconds.
	 *
	 * @param {number}      secs
	 * @param {Object|null} l10n
	 * @return {string}
	 */
	formatCountdown(secs, l10n) {
		secs = Math.max(0, Math.round(secs));
		if (secs < 60) {
			return secs + ' ' + this._l10n(l10n, 'seconds', 'seconds');
		}
		const m = Math.floor(secs / 60);
		const s = secs % 60;
		if (s === 0) {
			if (m === 1) {
				return this._l10n(l10n, 'minute', '1 minute');
			}
			return this._l10n(l10n, 'minutes', '%d minutes').replace('%d', m);
		}
		const msTpl = this._l10n(l10n, 'minutesSeconds', '%dm %ds');
		const msParts = [m, s];
		let msIdx = 0;
		return msTpl.replace(/%d/g, () => msParts[msIdx++]);
	},

	/**
	 * Format elapsed milliseconds as a ms string (e.g. "12.34 ms").
	 *
	 * @param {*} value Milliseconds.
	 * @return {string}
	 */
	formatElapsed(value) {
		if (value === null || value === undefined || value === '') {
			return '\u2014';
		}
		return parseFloat(value).toFixed(2) + ' ms';
	},

	/**
	 * Format elapsed milliseconds as a seconds string (e.g. "1.234 s").
	 *
	 * @param {*} value Milliseconds.
	 * @return {string}
	 */
	formatElapsedSeconds(value) {
		if (value === null || value === undefined || value === '') {
			return '\u2014';
		}
		return (parseFloat(value) / 1000).toFixed(3) + ' s';
	},

	/**
	 * Format bytes as a megabyte string (e.g. "12.34 MB").
	 *
	 * @param {*} value Bytes.
	 * @return {string}
	 */
	formatMemory(value) {
		if (value === null || value === undefined || value === '') {
			return '\u2014';
		}
		return (parseFloat(value) / 1048576).toFixed(2) + ' MB';
	}
};

export default DateTime;
