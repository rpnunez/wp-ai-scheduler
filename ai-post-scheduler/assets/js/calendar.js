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

	// Template colors mapping
	var templateColors = ['color-1', 'color-2', 'color-3'];

	Object.assign(AIPS, {
		initCalendar: function() {
			this.bindCalendarEvents();
			this.loadCalendarEvents();
		},

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

		calendarToday: function(e) {
			e.preventDefault();
			
			var today = new Date();
			calendarState.currentYear = today.getFullYear();
			calendarState.currentMonth = today.getMonth() + 1;
			calendarState.selectedDate = today;
			
			this.loadCalendarEvents();
		},

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
						self.renderCalendar();
					} else {
						alert(response.data.message || 'Failed to load calendar events.');
					}
				},
				error: function() {
					alert('An error occurred while loading calendar events.');
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

		getEventsForDate: function(dateStr) {
			return calendarState.events.filter(function(event) {
				return event.start.startsWith(dateStr);
			});
		},

		getEventsForDateTime: function(dateStr, hour) {
			return calendarState.events.filter(function(event) {
				if (!event.start.startsWith(dateStr)) {
					return false;
				}
				var eventHour = parseInt(event.start.split(' ')[1].split(':')[0]);
				return eventHour === hour;
			});
		},

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

		formatDate: function(date) {
			return date.getFullYear() + '-' + 
				   String(date.getMonth() + 1).padStart(2, '0') + '-' + 
				   String(date.getDate()).padStart(2, '0');
		},

		createEventElement: function(event) {
			var time = event.start.split(' ')[1];
			var shortTime = time ? time.substring(0, 5) : '';
			
			// Determine color based on template ID
			// Only assign specific colors to the first 3 templates; others get default color
			var colorClass = 'color-default';
			if (event.template_id) {
				var templateId = parseInt(event.template_id, 10);
				if (!isNaN(templateId) && templateId >= 1 && templateId <= templateColors.length) {
					colorClass = templateColors[templateId - 1];
				}
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
