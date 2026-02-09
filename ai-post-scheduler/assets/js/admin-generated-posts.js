/**
 * Generated Posts Admin JavaScript
 *
 * Handles the Generated Posts page.
 * View Session functionality is now in admin-view-session.js (reusable module).
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
(function($) {

  // Initialize on document ready
  $(document).ready(function() {
      // View Session functionality is automatically initialized by admin-view-session.js

      // Post Preview Modal
        $(document).on('click', '.aips-preview-post', function(e) {
            e.preventDefault();
            var postId = $(this).data('post-id');
            var previewUrl = '/?p=' + postId + '&preview=true';
            
            $('#aips-post-preview-iframe').attr('src', previewUrl);
            $('#aips-post-preview-modal').fadeIn(200);
        });

        // Close post preview modal
        $(document).on('click', '#aips-post-preview-modal .aips-modal-close, #aips-post-preview-modal .aips-modal-overlay', function(e) {
            e.preventDefault();
            $('#aips-post-preview-modal').fadeOut(200, function() {
                $('#aips-post-preview-iframe').attr('src', '');
            });
        });

        // Close modal on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#aips-post-preview-modal').is(':visible')) {
                $('#aips-post-preview-modal .aips-modal-close').click();
            }
        });
  });
})(jQuery);