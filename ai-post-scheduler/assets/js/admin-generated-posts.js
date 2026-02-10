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

      // Post Preview Modal - Hover functionality
      var previewModal = $('#aips-post-preview-modal');
      var previewIframe = $('#aips-post-preview-iframe');
      var hoverTimeout;
      
      // Show preview on hover
      $(document).on('click', '.aips-preview-trigger', function(e) {
          clearTimeout(hoverTimeout);
          
          var postId = $(this).data('post-id');
          var siteUrl = (aipsGeneratedPostsConfig && aipsGeneratedPostsConfig.siteUrl) ? aipsGeneratedPostsConfig.siteUrl : '';
          var previewUrl = siteUrl + '/?p=' + postId + '&preview=true';
          
          previewIframe.attr('src', previewUrl);
          previewModal.fadeIn(200);
      });
      
      // Hide preview when leaving icon
    //   $(document).on('mouseleave', '.aips-preview-trigger', function() {
    //       hoverTimeout = setTimeout(function() {
    //           if (!previewModal.is(':hover')) {
    //               closePreviewModal();
    //           }
    //       }, 200);
    //   });
      
      // Keep modal open when hovering over it
    //   previewModal.on('mouseenter', function() {
    //       clearTimeout(hoverTimeout);
    //   });
      
    //   // Close when leaving modal
    //   previewModal.on('mouseleave', function() {
    //       hoverTimeout = setTimeout(function() {
    //           closePreviewModal();
    //       }, 200);
    //   });
      
      // Close preview modal
      function closePreviewModal() {
          previewModal.fadeOut(200, function() {
              previewIframe.attr('src', '');
          });
      }
      
      // Close button handler
      $(document).on('click', '#aips-post-preview-modal .aips-modal-close', function(e) {
          e.preventDefault();
          closePreviewModal();
      });
      
      // Close on Escape key
      $(document).on('keydown', function(e) {
          if (e.key === 'Escape' && previewModal.is(':visible')) {
              closePreviewModal();
          }
      });
  });
})(jQuery);