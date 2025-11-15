(function ($) {
  "use strict";

  $(document).ready(function () {
    $('#t4e-confirm-handover-button').on('click', function (e) {
      e.preventDefault();

      if (!confirm('Are you sure you want to confirm handover?')) {
        return;
      }

      const button = $(this);
      const messageDiv = $('#t4e-handover-message');
      const orderId = button.data('order-id');

      button.prop('disabled', true).text('Confirming...');
      messageDiv.empty();

      fetch(t4e_pg_trustap_public_data.confirm_handover_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": t4e_pg_trustap_public_data.nonce,
        },
        credentials: "include",
        body: JSON.stringify({ orderId: orderId }),
      })
      .then(async (response) => {
        let data = await response.json();
        if (response.ok) {
          messageDiv.css('color', 'green').text(data.message || 'Handover confirmed successfully! Page will reload.');
          setTimeout(() => window.location.reload(), 2000);
        } else {
          messageDiv.css('color', 'red').text('Error: ' + (data.message || 'Handover confirmation failed!'));
          button.prop('disabled', false).text('Confirm Handover');
        }
      })
      .catch((error) => {
        messageDiv.css('color', 'red').text('Error: ' + error.message);
        button.prop('disabled', false).text('Confirm Handover');
      });
    });
  });

})(jQuery);