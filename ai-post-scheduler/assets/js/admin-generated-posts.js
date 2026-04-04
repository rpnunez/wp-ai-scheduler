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
  'use strict';

  window.AIPS = window.AIPS || {};
  var AIPS = window.AIPS;

  Object.assign(AIPS, {
    /**
     * Initialise the Generated Posts page.
     *
     * @return {void}
     */
    initGeneratedPosts: function() {
      this.bindGeneratedPostsEvents();
    },

    /**
     * Bind UI event listeners for the Generated Posts page.
     *
     * @return {void}
     */
    bindGeneratedPostsEvents: function() {
      $(document).on('click', '.aips-recover-image-btn', this.handleRecoverImage.bind(this));
    },

    /**
     * Handle a click on the "Recover Image" button.
     *
     * Sends an AJAX request to regenerate the featured image for an image-only
     * recoverable partial generation.  On success the row state badge is updated
     * so the operator sees the result without a full page reload.
     *
     * @param {Event} e Click event.
     * @return {void}
     */
    handleRecoverImage: function(e) {
      var $btn    = $(e.currentTarget);
      var postId  = $btn.data('post-id');
      var historyId = $btn.data('history-id');
      var config  = window.aipsGeneratedPostsConfig || {};

      if (!postId || !config.ajaxUrl || !config.nonce) {
        return;
      }

      $btn.prop('disabled', true).addClass('aips-btn-loading');

      $.ajax({
        url: config.ajaxUrl,
        type: 'POST',
        data: {
          action:     'aips_recover_post_image',
          nonce:      config.nonce,
          post_id:    postId,
          history_id: historyId,
        },
        success: function(response) {
          if (response && response.success) {
            var $row = $btn.closest('tr');
            var resolvedLabel = (config.recoverImageResolved) ? config.recoverImageResolved : AIPS._recoveredLabel;
            $row.find('.aips-badge-info, .aips-badge-warning').first().replaceWith(
              '<span class="aips-badge aips-badge-success">' + resolvedLabel + '</span>'
            );
            $btn.remove();
          } else {
            var errorLabel = (config.recoverImageError) ? config.recoverImageError : AIPS._recoverErrorLabel;
            var msg = (response && response.data && response.data.message)
              ? response.data.message
              : errorLabel;
            alert(msg);
            $btn.prop('disabled', false).removeClass('aips-btn-loading');
          }
        },
        error: function() {
          var errorLabel = (config.recoverImageError) ? config.recoverImageError : AIPS._recoverErrorLabel;
          alert(errorLabel);
          $btn.prop('disabled', false).removeClass('aips-btn-loading');
        },
      });
    },

    /** @type {string} Fallback resolved label (overridden by aipsGeneratedPostsConfig.recoverImageResolved). */
    _recoveredLabel: 'Resolved',
    /** @type {string} Fallback error message (overridden by aipsGeneratedPostsConfig.recoverImageError). */
    _recoverErrorLabel: 'Image recovery failed. Please try again.',
  });

  // Initialize on document ready
  $(document).ready(function() {
      AIPS.initGeneratedPosts();

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

      // Close preview modal
      /**
       * Fade out the post preview modal and clear the iframe `src`.
       *
       * Clearing the `src` stops any in-progress page load inside the iframe
       * and frees the memory used by the preview URL.
       */
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
