(function($) {
  'use strict';
  window.AIPS = window.AIPS || {};
  var AIPS = window.AIPS;

  /**
   * Cloudflare AI Gateway settings UI:
   *   - show/hide settings whose data-aips-depends-on condition is unmet
   *     (used to reveal the Cloudflare fields only when that provider is picked)
   *   - the "Test connection" button
   */
  AIPS.CfGateway = {

    init: function() {
      this.L = window.aipsCfGatewayL10n || {};
      this.applyDependencies();
      this.bindEvents();
    },

    bindEvents: function() {
      // Any control change may flip a dependency condition; re-evaluate all.
      $(document).on('change', 'select, input[type="radio"], input[type="checkbox"]', this.applyDependencies.bind(this));
      $(document).on('click', '#aips-cf-test-connection', this.testConnection.bind(this));
    },

    /**
     * Read the current value of a settings control by its field name.
     *
     * @param {string} name Field name.
     * @return {string|null}
     */
    getFieldValue: function(name) {
      var $field = $('[name="' + name + '"]');
      if (!$field.length) {
        return null;
      }
      if ($field.is(':radio')) {
        return $field.filter(':checked').val();
      }
      if ($field.is(':checkbox')) {
        return $field.filter(':checked').length ? $field.val() : '';
      }
      return $field.val();
    },

    /**
     * Toggle every setting carrying data-aips-depends-on so it shows only when
     * its controlling field equals data-aips-depends-value. Hides the whole
     * table row when there is one.
     */
    applyDependencies: function() {
      var self = this;
      $('[data-aips-depends-on]').each(function() {
        var $el     = $(this);
        var name    = $el.attr('data-aips-depends-on');
        var want    = String($el.attr('data-aips-depends-value'));
        var current = String(self.getFieldValue(name));
        var show    = current === want;
        var $row    = $el.closest('tr');
        ($row.length ? $row : $el).toggle(show);
      });
    },

    testConnection: function(e) {
      e.preventDefault();

      var L     = this.L;
      var $btn  = $('#aips-cf-test-connection');
      var $out  = $('#aips-cf-test-result');

      if ($btn.prop('disabled')) {
        return;
      }

      $btn.prop('disabled', true);
      $out.removeClass('is-error is-success').text(L.testing || 'Testing…');

      $.post(L.ajaxUrl || window.ajaxurl, {
        action: 'aips_cf_gateway_test_connection',
        nonce: L.nonce
      }).done(function(response) {
        var ok  = !!(response && response.success);
        var msg = (response && response.data && response.data.message)
          ? response.data.message
          : (ok ? '' : (L.error || 'The connection test failed.'));

        $out.addClass(ok ? 'is-success' : 'is-error').text(msg);

        if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
          AIPS.Utilities.showToast(msg, ok ? 'success' : 'error');
        }
      }).fail(function() {
        var msg = L.error || 'The connection test failed.';
        $out.addClass('is-error').text(msg);
        if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
          AIPS.Utilities.showToast(msg, 'error');
        }
      }).always(function() {
        $btn.prop('disabled', false);
      });
    }
  };

  $(document).ready(function() {
    AIPS.CfGateway.init();
  });
})(jQuery);
