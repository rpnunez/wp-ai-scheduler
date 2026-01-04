/**
 * Admin Templates Script
 *
 * Handles interactions for the Templates page, including
 * creating, editing, and duplicating templates.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
    'use strict';

    // Extend the global AIPS object
    window.AIPS = window.AIPS || {};

    $(document).ready(function() {

        // Initialize template modal
        const templateModal = $('#aips-template-modal');
        const form = $('#aips-template-form');

        // Open modal for adding new template
        $('.aips-add-template-btn').on('click', function() {
            resetForm();
            $('#aips-modal-title').text(AIPS.l10n.addTemplate);
            templateModal.fadeIn(200);
        });

        // Edit template
        $('.aips-edit-template').on('click', function(e) {
            e.preventDefault();
            const templateId = $(this).data('id');
            const row = $(this).closest('tr');

            // Populate form with existing data (simplified for this context)
            // In a real scenario, you might fetch full details via AJAX if not all are in the row
            // Here we assume we fetch details via AJAX
            loadTemplateDetails(templateId);
        });

        // Clone Template
        $('.aips-clone-template').on('click', function(e) {
            e.preventDefault();
            const templateId = $(this).data('id');
            cloneTemplate(templateId);
        });

        // Save template
        $('.aips-save-template').on('click', function() {
            saveTemplate();
        });

        // Test template
        $('.aips-test-template').on('click', function() {
            testTemplate();
        });

        // Delete template
        $('.aips-delete-template').on('click', function() {
            const templateId = $(this).data('id');
            deleteTemplate(templateId);
        });

        // Helper to reset form
        function resetForm() {
            form[0].reset();
            $('#template_id').val('');
            $('#image_prompt').prop('disabled', true);
        }

        // Fetch template details
        function loadTemplateDetails(id) {
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_template',
                    nonce: aipsAjax.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        $('#template_id').val(data.id);
                        $('#template_name').val(data.name);
                        $('#prompt_template').val(data.prompt_template);
                        $('#title_prompt').val(data.title_prompt);
                        $('#voice_id').val(data.voice_id);
                        $('#post_quantity').val(data.post_quantity);
                        $('#generate_featured_image').prop('checked', data.generate_featured_image == 1);
                        $('#image_prompt').val(data.image_prompt).prop('disabled', data.generate_featured_image != 1);
                        $('#post_status').val(data.post_status);
                        $('#post_category').val(data.post_category);
                        $('#post_tags').val(data.post_tags);
                        $('#post_author').val(data.post_author);
                        $('#is_active').prop('checked', data.is_active == 1);

                        $('#aips-modal-title').text(AIPS.l10n.editTemplate);
                        templateModal.fadeIn(200);
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        }

        // Clone template logic
        function cloneTemplate(id) {
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_template',
                    nonce: aipsAjax.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        // Populate form but clear ID and append (Copy) to name
                        $('#template_id').val(''); // Clear ID to create new
                        $('#template_name').val(data.name + ' (Copy)');
                        $('#prompt_template').val(data.prompt_template);
                        $('#title_prompt').val(data.title_prompt);
                        $('#voice_id').val(data.voice_id);
                        $('#post_quantity').val(data.post_quantity);
                        $('#generate_featured_image').prop('checked', data.generate_featured_image == 1);
                        $('#image_prompt').val(data.image_prompt).prop('disabled', data.generate_featured_image != 1);
                        $('#post_status').val(data.post_status);
                        $('#post_category').val(data.post_category);
                        $('#post_tags').val(data.post_tags);
                        $('#post_author').val(data.post_author);
                        $('#is_active').prop('checked', data.is_active == 1);

                        $('#aips-modal-title').text(AIPS.l10n.addTemplate); // It's a new add
                        templateModal.fadeIn(200);
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        }

        function saveTemplate() {
            const data = form.serialize() + '&action=aips_save_template&nonce=' + aipsAjax.nonce;

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        }

        function deleteTemplate(id) {
            // Soft confirm
            const btn = $(`.aips-delete-template[data-id="${id}"]`);
            if (btn.data('is-confirming')) {
                 $.ajax({
                    url: aipsAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aips_delete_template',
                        nonce: aipsAjax.nonce,
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            btn.closest('tr').fadeOut(400, function() { $(this).remove(); });
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            } else {
                btn.data('is-confirming', true);
                const originalText = btn.text();
                btn.text(AIPS.l10n.confirmDelete || 'Click to Confirm');
                setTimeout(function() {
                    btn.data('is-confirming', false);
                    btn.text(originalText);
                }, 3000);
            }
        }

        function testTemplate() {
            // Implementation for testing template...
            alert('Test feature not fully implemented in this snippet.');
        }

        // Toggle image prompt based on checkbox
        $('#generate_featured_image').on('change', function() {
            $('#image_prompt').prop('disabled', !this.checked);
        });

    });

})(jQuery);
