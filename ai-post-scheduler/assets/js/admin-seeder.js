jQuery(document).ready(function($) {
    const $form = $('#aips-seeder-form');
    const $submitBtn = $('#aips-seeder-submit');
    const $spinner = $form.find('.spinner');
    const $results = $('#aips-seeder-results');
    const $log = $('#aips-seeder-log');

    $form.on('submit', function(e) {
        e.preventDefault();

        const queue = [];
        const keywords = $('#seeder-keywords').val();
        const voices = parseInt($('#seeder-voices').val()) || 0;
        const templates = parseInt($('#seeder-templates').val()) || 0;
        const schedule = parseInt($('#seeder-schedule').val()) || 0;
        const planner = parseInt($('#seeder-planner').val()) || 0;

        if (voices > 0) queue.push({ type: 'voices', count: voices, label: 'Voices', keywords: keywords });
        if (templates > 0) queue.push({ type: 'templates', count: templates, label: 'Templates', keywords: keywords });
        if (schedule > 0) queue.push({ type: 'schedule', count: schedule, label: 'Scheduled Templates', keywords: keywords });
        if (planner > 0) queue.push({ type: 'planner', count: planner, label: 'Planner Entries', keywords: keywords });

        if (queue.length === 0) {
            AIPS.Utilities.showToast('Please enter at least one quantity.', 'warning');
            return;
        }

        AIPS.Utilities.confirm('This will generate dummy data in your database. Are you sure?', 'Notice', [
            { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
            { label: 'Yes, generate', className: 'aips-btn aips-btn-danger-solid', action: function() {
                $submitBtn.prop('disabled', true);
                $spinner.addClass('is-active');
                $results.show();
                $log.empty().append('<div>Starting Seeder...</div>');
                processQueue(queue);
            }}
        ]);
    });

    /**
     * Recursively process a queue of seeder tasks one item at a time.
     *
     * Shifts the first task off the array, appends a progress message to
     * `#aips-seeder-log`, and sends the `aips_process_seeder` AJAX action.
     * On both success and error it calls itself again with the remaining queue
     * so all tasks run sequentially. Marks the form as complete and re-enables
     * the submit button when the queue is empty.
     *
     * @param {Array<Object>} queue - Array of task objects, each with `type`,
     *                                `count`, `label`, and `keywords` properties.
     */
    function processQueue(queue) {
        if (queue.length === 0) {
            $log.append('<div><strong>All Done!</strong></div>');
            $submitBtn.prop('disabled', false);
            $spinner.removeClass('is-active');
            return;
        }

        const task = queue.shift();
        $log.append(`<div>Generating ${task.count} ${task.label}...</div>`);

        $.ajax({
            url: aipsAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aips_process_seeder',
                nonce: aipsAjax.nonce,
                type: task.type,
                count: task.count,
                keywords: task.keywords
            },
            success: function(response) {
                if (response.success) {
                    $log.append(`<div style="color: green;">✔ ${response.data.message}</div>`);
                } else {
                    $log.append(`<div style="color: red;">✘ Error: ${response.data.message}</div>`);
                }
                processQueue(queue);
            },
            error: function(xhr, status, error) {
                $log.append(`<div style="color: red;">✘ AJAX Error: ${error}</div>`);
                processQueue(queue); // Continue anyway
            }
        });
    }
});
