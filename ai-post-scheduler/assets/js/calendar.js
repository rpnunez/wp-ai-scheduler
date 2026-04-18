(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	// Calendar state
	var calendarState = {
		currentYear: new Date().getFullYear(),
		currentMonth: new Date().getMonth() + 1, // 1-12
		currentView: 'month',
		events: [],
		selectedDate: new Date()
	};

	// Template color slots — up to 6 named colours before falling back to default.
	var MAX_NAMED_COLORS = 6;

	// Maps template_id (int) → { colorClass (string), name (string) }.
	// Rebuilt by buildTemplateColorMap() every time fresh events are loaded.
	var templateColorMap = {};

	Object.assign(AIPS, {
		/**
		 * Bootstrap the calendar widget.
		 *
		 * Registers all delegated event listeners and fetches the initial
		 * calendar events for the current month.
		 */
		initCalendar: function() {
			this.bindCalendarEvents();
			this.loadCalendarEvents();
		},

		/**
		 * Register all delegated event listeners for calendar navigation,
		 * view switching, event clicking, and modal close actions.
		 */
		bindCalendarEvents: function() {
			// Navigation
			$(document).on('click', '.aips-calendar-prev', this.calendarPrevious.bind(this));
			$(document).on('click', '.aips-calendar-next', this.calendarNext.bind(this));
			$(document).on('click', '.aips-calendar-today-btn', this.calendarToday.bind(this));

			// View switching
			$(document).on('click', '.aips-calendar-view-btn', this.switchCalendarView.bind(this));

			// Event clicking
			$(document).on('click', '.aips-calendar-event', this.showEventDetails.bind(this));

			// Modal
			$(document).on('click', '.aips-calendar-modal-close, .aips-calendar-modal-overlay', this.closeEventModal.bind(this));
		},

		/**
		 * Navigate the calendar backward by one period (month, week, or day).
		 *
		 * Updates `calendarState` to reflect the new period and triggers an
		 * AJAX reload via `loadCalendarEvents`.
		 *
		 * @param {Event} e - Click event from an `.aips-calendar-prev` element.
		 */
		calendarPrevious: function(e) {
			e.preventDefault();
			
			if (calendarState.currentView === 'month') {
				// Move back one month based on the currently displayed month
				var newDate = new Date(calendarState.currentYear, calendarState.currentMonth - 1, 1);
				newDate.setMonth(newDate.getMonth() - 1);
				calendarState.currentYear = newDate.getFullYear();
				calendarState.currentMonth = newDate.getMonth() + 1;
				calendarState.selectedDate = newDate;
			} else if (calendarState.currentView === 'week') {
				// Move back one week
				var newDate = new Date(calendarState.selectedDate);
				newDate.setDate(newDate.getDate() - 7);
				calendarState.selectedDate = newDate;
				calendarState.currentYear = newDate.getFullYear();
				calendarState.currentMonth = newDate.getMonth() + 1;
			} else if (calendarState.currentView === 'day') {
				// Move back one day
				var newDate = new Date(calendarState.selectedDate);
				newDate.setDate(newDate.getDate() - 1);
				calendarState.selectedDate = newDate;
				calendarState.currentYear = newDate.getFullYear();
				calendarState.currentMonth = newDate.getMonth() + 1;
			}
			
			this.loadCalendarEvents();
		},

		/**
		 * Navigate the calendar forward by one period (month, week, or day).
		 *
		 * Updates `calendarState` to reflect the new period and triggers an
		 * AJAX reload via `loadCalendarEvents`.
		 *
		 * @param {Event} e - Click event from an `.aips-calendar-next` element.
		 */
		calendarNext: function(e) {
			e.preventDefault();
			
			if (calendarState.currentView === 'month') {
				// Move forward one month based on the currently displayed month
				var newDate = new Date(calendarState.currentYear, calendarState.currentMonth - 1, 1);
				newDate.setMonth(newDate.getMonth() + 1);
				calendarState.currentYear = newDate.getFullYear();
				calendarState.currentMonth = newDate.getMonth() + 1;
				calendarState.selectedDate = newDate;
			} else if (calendarState.currentView === 'week') {
				// Move forward one week
				var newDate = new Date(calendarState.selectedDate);
				newDate.setDate(newDate.getDate() + 7);
				calendarState.selectedDate = newDate;
				calendarState.currentYear = newDate.getFullYear();
				calendarState.currentMonth = newDate.getMonth() + 1;
			} else if (calendarState.currentView === 'day') {
				// Move forward one day
				var newDate = new Date(calendarState.selectedDate);
				newDate.setDate(newDate.getDate() + 1);
				calendarState.selectedDate = newDate;
				calendarState.currentYear = newDate.getFullYear();
				calendarState.currentMonth = newDate.getMonth() + 1;
			}
			
			this.loadCalendarEvents();
		},

		/**
		 * Jump the calendar to the current date (today).
		 *
		 * Resets `calendarState` to today's year, month, and `selectedDate`,
		 * then triggers a reload via `loadCalendarEvents`.
		 *
		 * @param {Event} e - Click event from an `.aips-calendar-today-btn` element.
		 */
		calendarToday: function(e) {
			e.preventDefault();
			
			var today = new Date();
			calendarState.currentYear = today.getFullYear();
			calendarState.currentMonth = today.getMonth() + 1;
			calendarState.selectedDate = today;
			
			this.loadCalendarEvents();
		},

		/**
		 * Switch between the month, week, and day calendar views.
		 *
		 * Reads the target view from the clicked button's `data-view` attribute.
		 * Updates the active class on view buttons, hides all view panels, shows
		 * the selected one, and re-renders the calendar for the new view.
		 *
		 * @param {Event} e - Click event from an `.aips-calendar-view-btn` element.
		 */
		switchCalendarView: function(e) {
			e.preventDefault();
			
			var $btn = $(e.currentTarget);
			var view = $btn.data('view');
			
			$('.aips-calendar-view-btn').removeClass('active');
			$btn.addClass('active');
			
			calendarState.currentView = view;
			
			// Hide all views
			$('.aips-calendar-grid, .aips-calendar-week-view, .aips-calendar-day-view').hide();
			
			// Show selected view
			if (view === 'month') {
				$('.aips-calendar-grid').show();
			} else if (view === 'week') {
				$('.aips-calendar-week-view').show();
			} else if (view === 'day') {
				$('.aips-calendar-day-view').show();
			}
			
			this.renderCalendar();
		},

		/**
		 * Fetch scheduled-post events for the current calendar period via AJAX.
		 *
		 * Shows the loading indicator, hides the calendar view panels, sends the
		 * `aips_get_calendar_events` action, and on success stores the returned
		 * events in `calendarState.events`, builds the dynamic colour map via
		 * `buildTemplateColorMap`, renders the live legend, and calls
		 * `renderCalendar`. Re-shows the appropriate view panel in the `complete`
		 * callback.
		 */
		loadCalendarEvents: function() {
			var self = this;
			
			$('.aips-calendar-loading').show();
			$('.aips-calendar-grid, .aips-calendar-week-view, .aips-calendar-day-view').hide();
			
			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_calendar_events',
					nonce: aipsAjax.nonce,
					year: calendarState.currentYear,
					month: calendarState.currentMonth
				},
				success: function(response) {
					if (response.success) {
						calendarState.events = response.data.events || [];
						self.buildTemplateColorMap(response.data.template_map || []);
						self.renderLegend(response.data.template_map || []);
						self.renderCalendar();
					} else {
						AIPS.Utilities.showToast(response.data.message || 'Failed to load calendar events.', 'error');
					}
				},
				error: function() {
					AIPS.Utilities.showToast('An error occurred while loading calendar events.', 'error');
				},
				complete: function() {
					$('.aips-calendar-loading').hide();
					
					// Show appropriate view
					if (calendarState.currentView === 'month') {
						$('.aips-calendar-grid').show();
					} else if (calendarState.currentView === 'week') {
						$('.aips-calendar-week-view').show();
					} else if (calendarState.currentView === 'day') {
						$('.aips-calendar-day-view').show();
					}
				}
			});
		},

		/**
		 * Build a stable colour map from the ordered template list returned by
		 * the server.
		 *
		 * Each template receives a colour class based on its position in the
		 * list (colour-1 through colour-6, then colour-default for extras).
		 * Because the server orders templates by first appearance in the
		 * current month's events, the assignment is consistent for the same
		 * set of active schedules regardless of their database IDs.
		 *
		 * @param {Array<{id: number, name: string}>} templateMap Ordered array
		 *   of unique templates as returned by the `template_map` AJAX key.
		 * @return {void}
		 */
		buildTemplateColorMap: function(templateMap) {
			templateColorMap = {};
			if (!templateMap || !templateMap.length) {
				return;
			}
			templateMap.forEach(function(tpl, index) {
				var slot       = index < MAX_NAMED_COLORS ? (index + 1) : 'default';
				var colorClass = 'color-' + slot;
				templateColorMap[tpl.id] = {
					colorClass: colorClass,
					name:       tpl.name
				};
			});
		},

		/**
		 * Dynamically render the calendar legend from the ordered template list.
		 *
		 * Each entry shows a colour swatch (matching the event chips) and the
		 * real template name.  Templates beyond the six named slots are grouped
		 * under a single "Other Templates" entry.  The entire legend section is
		 * hidden when there are no active-schedule templates to display.
		 *
		 * @param {Array<{id: number, name: string}>} templateMap Ordered array
		 *   of unique templates as returned by the `template_map` AJAX key.
		 * @return {void}
		 */
		renderLegend: function(templateMap) {
			var $legend      = $('.aips-calendar-legend');
			var $legendItems = $legend.find('.aips-calendar-legend-items');

			$legendItems.empty();

			if (!templateMap || !templateMap.length) {
				$legend.hide();
				return;
			}

			templateMap.forEach(function(tpl, index) {
				if (index >= MAX_NAMED_COLORS) {
					return; // "Other Templates" catch-all added below.
				}
				var colorClass = 'color-' + (index + 1);
				var $item      = $('<div>').addClass('aips-calendar-legend-item');
				var $swatch    = $('<span>').addClass('aips-calendar-legend-color ' + colorClass);
				var $label     = $('<span>').text(tpl.name);
				$item.append($swatch, $label);
				$legendItems.append($item);
			});

			// If more templates exist than named colour slots, add a catch-all row.
			if (templateMap.length > MAX_NAMED_COLORS) {
				var $item   = $('<div>').addClass('aips-calendar-legend-item');
				var $swatch = $('<span>').addClass('aips-calendar-legend-color color-default');
				var $label  = $('<span>').text(
					/* translators: catch-all legend entry for templates beyond the 6 named colour slots */
					'Other Templates'
				);
				$item.append($swatch, $label);
				$legendItems.append($item);
			}

			$legend.show();
		},

		/**
		 * Update the calendar header label and delegate to the view-specific
		 * render method (`renderMonthView`, `renderWeekView`, or `renderDayView`).
		 */
		renderCalendar: function() {
			// Update title
			var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
				'July', 'August', 'September', 'October', 'November', 'December'];
			$('.aips-calendar-month-year').text(monthNames[calendarState.currentMonth - 1] + ' ' + calendarState.currentYear);
			
			if (calendarState.currentView === 'month') {
				this.renderMonthView();
			} else if (calendarState.currentView === 'week') {
				this.renderWeekView();
			} else if (calendarState.currentView === 'day') {
				this.renderDayView();
			}
		},

		/**
		 * Render the monthly grid view into `.aips-calendar-days`.
		 *
		 * Calculates the first day of the week, fills leading cells with
		 * days from the previous month (marked as "other-month"), fills the
		 * current month's days (marking today), and pads trailing cells from the
		 * next month to complete 6 × 7 = 42 cells.
		 */
		renderMonthView: function() {
			var year = calendarState.currentYear;
			var month = calendarState.currentMonth;
			
			// Get first day of month and number of days
			var firstDay = new Date(year, month - 1, 1);
			var lastDay = new Date(year, month, 0);
			var daysInMonth = lastDay.getDate();
			var startDayOfWeek = firstDay.getDay();
			
			// Get days from previous month
			var prevMonth = month === 1 ? 12 : month - 1;
			var prevYear = month === 1 ? year - 1 : year;
			var daysInPrevMonth = new Date(prevYear, prevMonth, 0).getDate();
			
			var $daysContainer = $('.aips-calendar-days');
			$daysContainer.empty();
			
			var today = new Date();
			var todayStr = today.getFullYear() + '-' + 
						   String(today.getMonth() + 1).padStart(2, '0') + '-' + 
						   String(today.getDate()).padStart(2, '0');
			
			// Add days from previous month
			for (var i = startDayOfWeek - 1; i >= 0; i--) {
				var day = daysInPrevMonth - i;
				var dateStr = prevYear + '-' + String(prevMonth).padStart(2, '0') + '-' + String(day).padStart(2, '0');
				this.renderDay($daysContainer, day, dateStr, true, false);
			}
			
			// Add days of current month
			for (var day = 1; day <= daysInMonth; day++) {
				var dateStr = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
				var isToday = dateStr === todayStr;
				this.renderDay($daysContainer, day, dateStr, false, isToday);
			}
			
			// Add days from next month to complete the grid
			var totalCells = $daysContainer.children().length;
			var remainingCells = 42 - totalCells; // 6 rows x 7 days
			var nextMonth = month === 12 ? 1 : month + 1;
			var nextYear = month === 12 ? year + 1 : year;
			
			for (var day = 1; day <= remainingCells; day++) {
				var dateStr = nextYear + '-' + String(nextMonth).padStart(2, '0') + '-' + String(day).padStart(2, '0');
				this.renderDay($daysContainer, day, dateStr, true, false);
			}
		},

		/**
		 * Create and append a single day cell to the month-view grid container.
		 *
		 * Adds `.other-month` or `.today` CSS classes as appropriate, renders up
		 * to three event chips, and appends a "+N more" label when additional
		 * events exist.
		 *
		 * @param {jQuery}  $container    - The `.aips-calendar-days` container.
		 * @param {number}  dayNumber     - The day-of-month integer to display.
		 * @param {string}  dateStr       - ISO date string (`YYYY-MM-DD`) for the cell.
		 * @param {boolean} isOtherMonth  - `true` if this day belongs to the
		 *                                  previous or next month.
		 * @param {boolean} isToday       - `true` if this day is today's date.
		 */
		renderDay: function($container, dayNumber, dateStr, isOtherMonth, isToday) {
			var $day = $('<div>')
				.addClass('aips-calendar-day')
				.attr('data-date', dateStr);
			
			if (isOtherMonth) {
				$day.addClass('other-month');
			}
			
			if (isToday) {
				$day.addClass('today');
			}
			
			var $dayNumber = $('<div>')
				.addClass('aips-calendar-day-number')
				.text(dayNumber);
			
			$day.append($dayNumber);
			
			// Add events for this day
			var dayEvents = this.getEventsForDate(dateStr);
			if (dayEvents.length > 0) {
				var $eventsContainer = $('<div>').addClass('aips-calendar-events');
				
				var maxVisible = 3;
				for (var i = 0; i < Math.min(dayEvents.length, maxVisible); i++) {
					var event = dayEvents[i];
					var $event = this.createEventElement(event);
					$eventsContainer.append($event);
				}
				
				if (dayEvents.length > maxVisible) {
					var $more = $('<div>')
						.addClass('aips-calendar-more-events')
						.text('+' + (dayEvents.length - maxVisible) + ' more');
					$eventsContainer.append($more);
				}
				
				$day.append($eventsContainer);
			}
			
			$container.append($day);
		},

		/**
		 * Render the weekly grid view into `.aips-calendar-week-grid`.
		 *
		 * Generates a day-header row for each day of the week (starting Sunday),
		 * then iterates through 24 hourly slots adding event chips for any
		 * schedule events whose start hour matches.
		 */
		renderWeekView: function() {
			var $weekGrid = $('.aips-calendar-week-grid');
			$weekGrid.empty();
			$weekGrid.append('<div class="aips-calendar-week-time">Time</div>');
			
			// Get week dates
			var weekDates = this.getWeekDates(calendarState.selectedDate);
			
			// Add day headers
			var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
			for (var i = 0; i < 7; i++) {
				var date = weekDates[i];
				var header = dayNames[i] + ' ' + date.getDate();
				$weekGrid.append($('<div>').addClass('aips-calendar-week-time').text(header));
			}
			
			// Add time slots
			for (var hour = 0; hour < 24; hour++) {
				var timeLabel = hour === 0 ? '12 AM' : hour < 12 ? hour + ' AM' : hour === 12 ? '12 PM' : (hour - 12) + ' PM';
				$weekGrid.append($('<div>').addClass('aips-calendar-week-time').text(timeLabel));
				
				for (var i = 0; i < 7; i++) {
					var dateStr = this.formatDate(weekDates[i]);
					var $slot = $('<div>').addClass('aips-calendar-week-day').attr('data-date', dateStr).attr('data-hour', hour);
					
					// Add events for this time slot
					var events = this.getEventsForDateTime(dateStr, hour);
					events.forEach(function(event) {
						$slot.append(this.createEventElement(event));
					}.bind(this));
					
					$weekGrid.append($slot);
				}
			}
		},

		/**
		 * Render the daily view into `.aips-calendar-day-grid`.
		 *
		 * Iterates through 24 hourly slots for `calendarState.selectedDate`,
		 * adding event chips for any schedule events whose start hour matches.
		 */
		renderDayView: function() {
			var $dayGrid = $('.aips-calendar-day-grid');
			$dayGrid.empty();
			
			var dateStr = this.formatDate(calendarState.selectedDate);
			
			// Add time slots for each hour
			for (var hour = 0; hour < 24; hour++) {
				var timeLabel = hour === 0 ? '12:00 AM' : hour < 12 ? hour + ':00 AM' : hour === 12 ? '12:00 PM' : (hour - 12) + ':00 PM';
				
				var $hourRow = $('<div>').addClass('aips-calendar-day-hour');
				var $label = $('<div>').addClass('aips-calendar-day-hour-label').text(timeLabel);
				var $content = $('<div>').addClass('aips-calendar-day-hour-content');
				
				// Add events for this hour
				var events = this.getEventsForDateTime(dateStr, hour);
				events.forEach(function(event) {
					$content.append(this.createEventElement(event));
				}.bind(this));
				
				$hourRow.append($label, $content);
				$dayGrid.append($hourRow);
			}
		},

		/**
		 * Return all events whose `start` value begins with the given date string.
		 *
		 * @param  {string}        dateStr - ISO date string (`YYYY-MM-DD`).
		 * @return {Array<Object>}         Matching event objects from `calendarState.events`.
		 */
		getEventsForDate: function(dateStr) {
			return calendarState.events.filter(function(event) {
				return event.start.startsWith(dateStr);
			});
		},

		/**
		 * Return events that fall on a specific date **and** hour.
		 *
		 * @param  {string}        dateStr - ISO date string (`YYYY-MM-DD`).
		 * @param  {number}        hour    - 24-hour integer (0–23).
		 * @return {Array<Object>}         Matching event objects.
		 */
		getEventsForDateTime: function(dateStr, hour) {
			return calendarState.events.filter(function(event) {
				if (!event.start.startsWith(dateStr)) {
					return false;
				}
				var eventHour = parseInt(event.start.split(' ')[1].split(':')[0]);
				return eventHour === hour;
			});
		},

		/**
		 * Return an array of seven `Date` objects representing a full week
		 * (Sunday through Saturday) that contains the given date.
		 *
		 * @param  {Date}        date - Any date within the target week.
		 * @return {Array<Date>}      Seven `Date` objects, index 0 = Sunday.
		 */
		getWeekDates: function(date) {
			var dates = [];
			var day = date.getDay();
			var diff = date.getDate() - day; // Start from Sunday
			
			for (var i = 0; i < 7; i++) {
				var weekDate = new Date(date);
				weekDate.setDate(diff + i);
				dates.push(weekDate);
			}
			
			return dates;
		},

		/**
		 * Format a `Date` object as an ISO `YYYY-MM-DD` string.
		 *
		 * @param  {Date}   date - The date to format.
		 * @return {string}      ISO date string.
		 */
		formatDate: function(date) {
			return date.getFullYear() + '-' + 
				   String(date.getMonth() + 1).padStart(2, '0') + '-' + 
				   String(date.getDate()).padStart(2, '0');
		},

		/**
		 * Build a jQuery event chip element for a single calendar event.
		 *
		 * Assigns one of up to six named colour classes (colour-1 … colour-6)
		 * or `colour-default` using the `templateColorMap` built by
		 * `buildTemplateColorMap()`.  Colour assignment is position-based
		 * (first unique template seen = colour-1, second = colour-2, …) so
		 * any template ID — not just IDs 1–3 — receives a proper colour.
		 *
		 * @param  {Object} event              - Calendar event data object.
		 * @param  {string} event.start        - Start datetime string (`YYYY-MM-DD HH:MM:SS`).
		 * @param  {string} event.title        - Display title.
		 * @param  {number} event.id           - Event ID.
		 * @param  {number} event.template_id  - Template ID used for colour coding.
		 * @return {jQuery}                    The `.aips-calendar-event` element.
		 */
		createEventElement: function(event) {
			var time = event.start.split(' ')[1];
			var shortTime = time ? time.substring(0, 5) : '';

			// Look up the colour class from the dynamic map built at load time.
			var colorClass = 'color-default';
			if (event.template_id && templateColorMap[event.template_id]) {
				colorClass = templateColorMap[event.template_id].colorClass;
			}
			
			var $event = $('<div>')
				.addClass('aips-calendar-event')
				.addClass(colorClass)
				.attr('data-event-id', event.id)
				.attr('data-template-id', event.template_id);
			
			var $time = $('<span>')
				.addClass('aips-calendar-event-time')
				.text(shortTime);
			
			var $title = $('<span>')
				.addClass('aips-calendar-event-title')
				.text(event.title);
			
			$event.append($time, $title);
			
			// Store event data
			$event.data('event', event);
			
			return $event;
		},

		/**
		 * Populate the event-detail modal with data from the clicked chip and
		 * fade the modal in.
		 *
		 * Reads the event object from the element's jQuery data store, formats
		 * the start datetime with `toLocaleString`, and updates the modal's
		 * template, time, frequency, topic, category, and author labels.
		 *
		 * @param {Event} e - Click event from an `.aips-calendar-event` element.
		 */
		showEventDetails: function(e) {
			e.stopPropagation();
			
			var $event = $(e.currentTarget);
			var event = $event.data('event');
			
			if (!event) {
				return;
			}
			
			// Format the date and time
			var dateTime = new Date(event.start.replace(' ', 'T'));
			var formattedDateTime = dateTime.toLocaleString();
			
			// Update modal content
			$('.aips-event-template').text(event.template_name || 'N/A');
			$('.aips-event-time').text(formattedDateTime);
			$('.aips-event-frequency').text(event.frequency ? event.frequency.replace('_', ' ') : 'N/A');
			$('.aips-event-topic').text(event.topic || 'N/A');
			$('.aips-event-category').text(event.category || 'N/A');
			$('.aips-event-author').text(event.author || 'N/A');
			
			// Show modal
			$('#aips-calendar-event-modal').fadeIn(200);
		},

		/**
		 * Fade the calendar event-detail modal out.
		 *
		 * @param {Event} [e] - Click event from `.aips-calendar-modal-close` or
		 *                      `.aips-calendar-modal-overlay` (optional when
		 *                      called programmatically).
		 */
		closeEventModal: function(e) {
			if (e) {
				e.preventDefault();
			}
			$('#aips-calendar-event-modal').fadeOut(200);
		}
	});

	// Initialize on document ready
	$(document).ready(function() {
		if ($('.aips-calendar-container').length) {
			AIPS.initCalendar();
		}
	});

})(jQuery);
