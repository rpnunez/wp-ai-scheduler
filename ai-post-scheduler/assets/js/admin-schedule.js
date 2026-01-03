(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};

    var calendarState = {
        currentDate: new Date(),
        view: 'calendar' // calendar | list
    };

    function initCalendar() {
        // Init View Switcher
        $('.aips-view-btn').on('click', function() {
            var view = $(this).data('view');
            switchView(view);
        });

        // Init Nav
        $('#prev-month').on('click', function() {
            calendarState.currentDate.setMonth(calendarState.currentDate.getMonth() - 1);
            renderCalendar();
        });
        $('#next-month').on('click', function() {
            calendarState.currentDate.setMonth(calendarState.currentDate.getMonth() + 1);
            renderCalendar();
        });

        // Initial Render
        switchView('calendar'); // Default
    }

    function switchView(view) {
        calendarState.view = view;
        $('.aips-view-btn').removeClass('button-primary').addClass('button-secondary');
        $('.aips-view-btn[data-view="' + view + '"]').removeClass('button-secondary').addClass('button-primary');

        $('.aips-view-container').hide();
        $('#aips-' + view + '-view').show();

        if (view === 'calendar') {
            renderCalendar();
        }
    }

    function renderCalendar() {
        var year = calendarState.currentDate.getFullYear();
        var month = calendarState.currentDate.getMonth();

        // Update Header (use localized month names when available)
        var monthLabelDate = new Date(year, month, 1);
        var monthName;

        if (typeof Intl !== 'undefined' && typeof Intl.DateTimeFormat === 'function') {
            monthName = new Intl.DateTimeFormat(undefined, { month: 'long' }).format(monthLabelDate);
        } else {
            var monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            monthName = monthNames[month];
        }
        $('#current-month-label').text(monthName + ' ' + year);
        // Fetch Events
        var startStr = formatDate(new Date(year, month, 1));
        var endStr = formatDate(new Date(year, month + 1, 0));

        $('#aips-calendar-grid').html('<p class="aips-loading">Loading...</p>');

        $.ajax({
            url: aipsAjax.ajaxUrl,
            type: 'GET',
            data: {
                action: 'aips_get_calendar_events',
                nonce: aipsAjax.nonce,
                start: startStr,
                end: endStr
            },
            success: function(response) {
                if (response.success) {
                    drawGrid(year, month, response.data.events);
                } else {
                    $('#aips-calendar-grid').html('<p class="error">Failed to load events.</p>');
                }
            }
        });
    }

    function drawGrid(year, month, events) {
        var $grid = $('#aips-calendar-grid');
        $grid.empty();

        // Get week start from WordPress settings (0 = Sunday, 1 = Monday)
        var weekStartsOn = (typeof aipsAjax !== 'undefined' && typeof aipsAjax.weekStartsOn !== 'undefined') 
            ? parseInt(aipsAjax.weekStartsOn, 10) 
            : 0; // Default to Sunday if not set

        // Build days array starting from the configured day
        var allDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        var days = [];
        for (var i = 0; i < 7; i++) {
            days.push(allDays[(weekStartsOn + i) % 7]);
        }
        
        var headerHtml = '';
        days.forEach(function(d) {
            headerHtml += '<div class="aips-calendar-header">' + d + '</div>';
        });
        $grid.append(headerHtml);

        var firstDay = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0);

        // Determine start offset based on WordPress week start setting
        // JS getDay(): Sun=0, Mon=1...Sat=6.
        var startDay = firstDay.getDay();
        // Calculate offset from configured week start
        var startOffset = (startDay - weekStartsOn + 7) % 7;

        var totalDays = lastDay.getDate();
        var cells = [];

        // Empty cells for previous month
        for (var i = 0; i < startOffset; i++) {
            cells.push('<div class="aips-calendar-day other-month"></div>');
        }

        // Days
        for (var d = 1; d <= totalDays; d++) {
            var dateStr = formatDate(new Date(year, month, d));
            var dayEvents = events.filter(function(e) {
                return e.start.startsWith(dateStr);
            });

            var html = '<div class="aips-calendar-day">';
            html += '<span class="aips-day-number">' + d + '</span>';

            dayEvents.forEach(function(e) {
                var style = 'background-color:' + e.color + ';';
                if (e.textColor) style += 'color:' + e.textColor + ';';

                var title = e.title;
                if(e.type === 'schedule') {
                    // It's a projected schedule
                    // Maybe add a dashed border?
                    style += 'border: 1px dashed #999;';
                }

                html += '<a href="' + (e.url || '#') + '" class="aips-event" style="' + style + '" title="' + title + '">';
                html += title;
                html += '</a>';
            });

            html += '</div>';
            cells.push(html);
        }

        $grid.append(cells.join(''));
    }

    function formatDate(date) {
        var d = new Date(date),
            month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    }

    $(document).ready(function() {
        if ($('#aips-calendar-view').length) {
            initCalendar();
        }
    });

})(jQuery);
