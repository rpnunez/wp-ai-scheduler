import Backbone from 'backbone';
import $ from 'jquery';

/**
 * Calendar View
 */
export const CalendarView = Backbone.View.extend({
	el: 'body',

	events: {
		'click .aips-calendar-prev': 'calendarPrevious',
		'click .aips-calendar-next': 'calendarNext',
		'click .aips-calendar-today-btn': 'calendarToday',
		'click .aips-calendar-view-btn': 'switchCalendarView',
		'click .aips-calendar-event': 'showEventDetails',
		'click .aips-calendar-modal-close, .aips-calendar-modal-overlay': 'closeEventModal'
	},

	initialize() {
		this.calendarState = {
			currentYear: new Date().getFullYear(),
			currentMonth: new Date().getMonth() + 1, // 1-12
			currentView: 'month',
			events: [],
			selectedDate: new Date()
		};

		this.templateColors = ['color-1', 'color-2', 'color-3'];
		
		if (this.isCalendarPage()) {
			this.loadCalendarEvents();
		}
	},

	isCalendarPage() {
		return this.$('.aips-calendar-container').length > 0;
	},

	calendarPrevious(e) {
		e.preventDefault();
		const state = this.calendarState;
		
		if (state.currentView === 'month') {
			const newDate = new Date(state.currentYear, state.currentMonth - 1, 1);
			newDate.setMonth(newDate.getMonth() - 1);
			state.currentYear = newDate.getFullYear();
			state.currentMonth = newDate.getMonth() + 1;
			state.selectedDate = newDate;
		} else if (state.currentView === 'week') {
			const newDate = new Date(state.selectedDate);
			newDate.setDate(newDate.getDate() - 7);
			state.selectedDate = newDate;
			state.currentYear = newDate.getFullYear();
			state.currentMonth = newDate.getMonth() + 1;
		} else if (state.currentView === 'day') {
			const newDate = new Date(state.selectedDate);
			newDate.setDate(newDate.getDate() - 1);
			state.selectedDate = newDate;
			state.currentYear = newDate.getFullYear();
			state.currentMonth = newDate.getMonth() + 1;
		}
		
		this.loadCalendarEvents();
	},

	calendarNext(e) {
		e.preventDefault();
		const state = this.calendarState;
		
		if (state.currentView === 'month') {
			const newDate = new Date(state.currentYear, state.currentMonth - 1, 1);
			newDate.setMonth(newDate.getMonth() + 1);
			state.currentYear = newDate.getFullYear();
			state.currentMonth = newDate.getMonth() + 1;
			state.selectedDate = newDate;
		} else if (state.currentView === 'week') {
			const newDate = new Date(state.selectedDate);
			newDate.setDate(newDate.getDate() + 7);
			state.selectedDate = newDate;
			state.currentYear = newDate.getFullYear();
			state.currentMonth = newDate.getMonth() + 1;
		} else if (state.currentView === 'day') {
			const newDate = new Date(state.selectedDate);
			newDate.setDate(newDate.getDate() + 1);
			state.selectedDate = newDate;
			state.currentYear = newDate.getFullYear();
			state.currentMonth = newDate.getMonth() + 1;
		}
		
		this.loadCalendarEvents();
	},

	calendarToday(e) {
		e.preventDefault();
		const today = new Date();
		this.calendarState.currentYear = today.getFullYear();
		this.calendarState.currentMonth = today.getMonth() + 1;
		this.calendarState.selectedDate = today;
		
		this.loadCalendarEvents();
	},

	switchCalendarView(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const view = $btn.data('view');
		
		this.$('.aips-calendar-view-btn').removeClass('active');
		$btn.addClass('active');
		
		this.calendarState.currentView = view;
		
		this.$('.aips-calendar-grid, .aips-calendar-week-view, .aips-calendar-day-view').hide();
		
		if (view === 'month') {
			this.$('.aips-calendar-grid').show();
		} else if (view === 'week') {
			this.$('.aips-calendar-week-view').show();
		} else if (view === 'day') {
			this.$('.aips-calendar-day-view').show();
		}
		
		this.renderCalendar();
	},

	loadCalendarEvents() {
		this.$('.aips-calendar-loading').show();
		this.$('.aips-calendar-grid, .aips-calendar-week-view, .aips-calendar-day-view').hide();
		
		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_calendar_events',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				year: this.calendarState.currentYear,
				month: this.calendarState.currentMonth
			},
			success: (response) => {
				if (response.success) {
					this.calendarState.events = response.data.events || [];
					this.renderCalendar();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || 'Failed to load calendar events.', 'error');
					}
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('An error occurred while loading calendar events.', 'error');
				}
			},
			complete: () => {
				this.$('.aips-calendar-loading').hide();
				
				if (this.calendarState.currentView === 'month') {
					this.$('.aips-calendar-grid').show();
				} else if (this.calendarState.currentView === 'week') {
					this.$('.aips-calendar-week-view').show();
				} else if (this.calendarState.currentView === 'day') {
					this.$('.aips-calendar-day-view').show();
				}
			}
		});
	},

	renderCalendar() {
		const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
			'July', 'August', 'September', 'October', 'November', 'December'];
		this.$('.aips-calendar-month-year').text(monthNames[this.calendarState.currentMonth - 1] + ' ' + this.calendarState.currentYear);
		
		if (this.calendarState.currentView === 'month') {
			this.renderMonthView();
		} else if (this.calendarState.currentView === 'week') {
			this.renderWeekView();
		} else if (this.calendarState.currentView === 'day') {
			this.renderDayView();
		}
	},

	renderMonthView() {
		const year = this.calendarState.currentYear;
		const month = this.calendarState.currentMonth;
		
		const firstDay = new Date(year, month - 1, 1);
		const lastDay = new Date(year, month, 0);
		const daysInMonth = lastDay.getDate();
		const startDayOfWeek = firstDay.getDay();
		
		const prevMonth = month === 1 ? 12 : month - 1;
		const prevYear = month === 1 ? year - 1 : year;
		const daysInPrevMonth = new Date(prevYear, prevMonth, 0).getDate();
		
		const $daysContainer = this.$('.aips-calendar-days');
		$daysContainer.empty();
		
		const today = new Date();
		const todayStr = today.getFullYear() + '-' + 
					   String(today.getMonth() + 1).padStart(2, '0') + '-' + 
					   String(today.getDate()).padStart(2, '0');
		
		for (let i = startDayOfWeek - 1; i >= 0; i--) {
			const day = daysInPrevMonth - i;
			const dateStr = prevYear + '-' + String(prevMonth).padStart(2, '0') + '-' + String(day).padStart(2, '0');
			this.renderDay($daysContainer, day, dateStr, true, false);
		}
		
		for (let day = 1; day <= daysInMonth; day++) {
			const dateStr = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
			const isToday = dateStr === todayStr;
			this.renderDay($daysContainer, day, dateStr, false, isToday);
		}
		
		const totalCells = $daysContainer.children().length;
		const remainingCells = 42 - totalCells;
		const nextMonth = month === 12 ? 1 : month + 1;
		const nextYear = month === 12 ? year + 1 : year;
		
		for (let day = 1; day <= remainingCells; day++) {
			const dateStr = nextYear + '-' + String(nextMonth).padStart(2, '0') + '-' + String(day).padStart(2, '0');
			this.renderDay($daysContainer, day, dateStr, true, false);
		}
	},

	renderDay($container, dayNumber, dateStr, isOtherMonth, isToday) {
		const $day = $('<div>')
			.addClass('aips-calendar-day')
			.attr('data-date', dateStr);
		
		if (isOtherMonth) {
			$day.addClass('other-month');
		}
		
		if (isToday) {
			$day.addClass('today');
		}
		
		const $dayNumber = $('<div>')
			.addClass('aips-calendar-day-number')
			.text(dayNumber);
		
		$day.append($dayNumber);
		
		const dayEvents = this.getEventsForDate(dateStr);
		if (dayEvents.length > 0) {
			const $eventsContainer = $('<div>').addClass('aips-calendar-events');
			
			const maxVisible = 3;
			for (let i = 0; i < Math.min(dayEvents.length, maxVisible); i++) {
				const event = dayEvents[i];
				const $event = this.createEventElement(event);
				$eventsContainer.append($event);
			}
			
			if (dayEvents.length > maxVisible) {
				const $more = $('<div>')
					.addClass('aips-calendar-more-events')
					.text('+' + (dayEvents.length - maxVisible) + ' more');
				$eventsContainer.append($more);
			}
			
			$day.append($eventsContainer);
		}
		
		$container.append($day);
	},

	renderWeekView() {
		const $weekGrid = this.$('.aips-calendar-week-grid');
		$weekGrid.empty();
		$weekGrid.append('<div class="aips-calendar-week-time">Time</div>');
		
		const weekDates = this.getWeekDates(this.calendarState.selectedDate);
		
		const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
		for (let i = 0; i < 7; i++) {
			const date = weekDates[i];
			const header = dayNames[i] + ' ' + date.getDate();
			$weekGrid.append($('<div>').addClass('aips-calendar-week-time').text(header));
		}
		
		for (let hour = 0; hour < 24; hour++) {
			const timeLabel = hour === 0 ? '12 AM' : hour < 12 ? hour + ' AM' : hour === 12 ? '12 PM' : (hour - 12) + ' PM';
			$weekGrid.append($('<div>').addClass('aips-calendar-week-time').text(timeLabel));
			
			for (let i = 0; i < 7; i++) {
				const dateStr = this.formatDate(weekDates[i]);
				const $slot = $('<div>').addClass('aips-calendar-week-day').attr('data-date', dateStr).attr('data-hour', hour);
				
				const events = this.getEventsForDateTime(dateStr, hour);
				events.forEach(event => {
					$slot.append(this.createEventElement(event));
				});
				
				$weekGrid.append($slot);
			}
		}
	},

	renderDayView() {
		const $dayGrid = this.$('.aips-calendar-day-grid');
		$dayGrid.empty();
		
		const dateStr = this.formatDate(this.calendarState.selectedDate);
		
		for (let hour = 0; hour < 24; hour++) {
			const timeLabel = hour === 0 ? '12:00 AM' : hour < 12 ? hour + ':00 AM' : hour === 12 ? '12:00 PM' : (hour - 12) + ':00 PM';
			
			const $hourRow = $('<div>').addClass('aips-calendar-day-hour');
			const $label = $('<div>').addClass('aips-calendar-day-hour-label').text(timeLabel);
			const $content = $('<div>').addClass('aips-calendar-day-hour-content');
			
			const events = this.getEventsForDateTime(dateStr, hour);
			events.forEach(event => {
				$content.append(this.createEventElement(event));
			});
			
			$hourRow.append($label, $content);
			$dayGrid.append($hourRow);
		}
	},

	getEventsForDate(dateStr) {
		return this.calendarState.events.filter(event => event.start.startsWith(dateStr));
	},

	getEventsForDateTime(dateStr, hour) {
		return this.calendarState.events.filter(event => {
			if (!event.start.startsWith(dateStr)) {
				return false;
			}
			const eventHour = parseInt(event.start.split(' ')[1].split(':')[0]);
			return eventHour === hour;
		});
	},

	getWeekDates(date) {
		const dates = [];
		const day = date.getDay();
		const diff = date.getDate() - day;
		
		for (let i = 0; i < 7; i++) {
			const weekDate = new Date(date);
			weekDate.setDate(diff + i);
			dates.push(weekDate);
		}
		
		return dates;
	},

	formatDate(date) {
		return date.getFullYear() + '-' + 
			   String(date.getMonth() + 1).padStart(2, '0') + '-' + 
			   String(date.getDate()).padStart(2, '0');
	},

	createEventElement(event) {
		const time = event.start.split(' ')[1];
		const shortTime = time ? time.substring(0, 5) : '';
		
		let colorClass = 'color-default';
		if (event.template_id) {
			const templateId = parseInt(event.template_id, 10);
			if (!isNaN(templateId) && templateId >= 1 && templateId <= this.templateColors.length) {
				colorClass = this.templateColors[templateId - 1];
			}
		}
		
		const $event = $('<div>')
			.addClass('aips-calendar-event')
			.addClass(colorClass)
			.attr('data-event-id', event.id)
			.attr('data-template-id', event.template_id);
		
		const $time = $('<span>')
			.addClass('aips-calendar-event-time')
			.text(shortTime);
		
		const $title = $('<span>')
			.addClass('aips-calendar-event-title')
			.text(event.title);
		
		$event.append($time, $title);
		
		$event.data('event', event);
		
		return $event;
	},

	showEventDetails(e) {
		e.stopPropagation();
		
		const $event = $(e.currentTarget);
		const event = $event.data('event');
		
		if (!event) return;
		
		const dateTime = new Date(event.start.replace(' ', 'T'));
		const formattedDateTime = dateTime.toLocaleString();
		
		this.$('.aips-event-template').text(event.template_name || 'N/A');
		this.$('.aips-event-time').text(formattedDateTime);
		this.$('.aips-event-frequency').text(event.frequency ? event.frequency.replace('_', ' ') : 'N/A');
		this.$('.aips-event-topic').text(event.topic || 'N/A');
		this.$('.aips-event-category').text(event.category || 'N/A');
		this.$('.aips-event-author').text(event.author || 'N/A');
		
		this.$('#aips-calendar-event-modal').fadeIn(200);
	},

	closeEventModal(e) {
		if (e) e.preventDefault();
		this.$('#aips-calendar-event-modal').fadeOut(200);
	}
});
