(function ($) {
  "use strict";

  $(document).ready(function () {
    $('#t4e-confirm-handover-button').on('click', function (e) {
      e.preventDefault();

      if (!confirm('Are you sure you want to confirm handover?')) {
        return;
      }

      var $button = $(this);
      var $messageDiv = $('#t4e-handover-message');

      $button.prop('disabled', true).text('Confirming...');
      $messageDiv.empty();

      $.ajax({
        url: t4e_pg_trustap_public_data.confirm_handover_url,
        method: 'POST',
        beforeSend: function (xhr) {
          xhr.setRequestHeader('X-WP-Nonce', t4e_pg_trustap_public_data.nonce);
        },
        data: {
          orderId: t4e_pg_trustap_public_data.order_id
        },
        success: function (response) {
          if (response.success) {
            $messageDiv.css('color', 'green').text('Handover confirmed successfully! Page will reload.');
            setTimeout(function () {
              window.location.reload();
            }, 2000);
          } else {
            var errorMessage = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
            $messageDiv.css('color', 'red').text('Error: ' + errorMessage);
            $button.prop('disabled', false).text('Confirm Handover');
          }
        },
        error: function (jqXHR) {
            var errorMessage = 'An unexpected error occurred. Please try again.';
            if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                errorMessage = jqXHR.responseJSON.message;
            }
            $messageDiv.css('color', 'red').text('Error: ' + errorMessage);
            $button.prop('disabled', false).text('Confirm Handover');
        }
      });
    });
  });

})(jQuery);