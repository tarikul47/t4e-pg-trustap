(function ($) {
  "use strict";

  $(document).ready(function () {
    $("#t4e-confirm-handover-button-admin").on("click", function () {
      const button = this;
      const spinner = document.getElementById("t4e-handover-spinner");

      // ✅ Confirm before proceeding
      const confirmed = confirm("Are you sure you want to confirm handover?");
      if (!confirmed) return;

      button.style.display = "none";
      spinner.style.display = "block";

      const params = new Proxy(new URLSearchParams(window.location.search), {
        get: (searchParams, prop) => searchParams.get(prop),
      });
      let orderId = params.id;

      fetch(t4e_pg_trustap_admin_data.confirm_handover_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": t4e_pg_trustap_admin_data.nonce,
        },
        credentials: "include",
        body: JSON.stringify({ orderId }),
      })
        .then(async (response) => {
          let data = await response.json();
          if (response.ok) {
            alert(data.message || "Handover confirmed successfully!");
            location.reload();
          } else {
            alert(data.message || "Handover confirmation failed!");
          }
        })
        .catch((error) => {
          alert("Error: " + error.message);
        })
        .finally(() => {
          button.style.display = "block";
          spinner.style.display = "none";
        });
    });
  });

  // $(document).ready(function () {
  //   $('#t4e-confirm-handover-button-admin').on('click', function (e) {
  //     e.preventDefault();

  //     if (!confirm('Are you sure you want to confirm handover?')) {
  //       return;
  //     }

  //     var $button = $(this);
  //     var $messageDiv = $('#t4e-handover-message-admin');

  //     $button.prop('disabled', true).text('Confirming...');
  //     $messageDiv.empty();

  //     $.ajax({
  //       url: t4e_pg_trustap_admin_data.confirm_handover_url,
  //       method: 'POST',
  //       beforeSend: function (xhr) {
  //         xhr.setRequestHeader('X-WP-Nonce', t4e_pg_trustap_admin_data.nonce);
  //       },
  //       data: {
  //         orderId: t4e_pg_trustap_admin_data.order_id
  //       },
  //       success: function (response) {
  //         if (response.success) {
  //           $messageDiv.css('color', 'green').text('Handover confirmed successfully! Page will reload.');
  //           setTimeout(function () {
  //             window.location.reload();
  //           }, 2000);
  //         } else {
  //           var errorMessage = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
  //           $messageDiv.css('color', 'red').text('Error: ' + errorMessage);
  //           $button.prop('disabled', false).text('Confirm Handover');
  //         }
  //       },
  //       error: function (jqXHR) {
  //           var errorMessage = 'An unexpected error occurred. Please try again.';
  //           if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
  //               errorMessage = jqXHR.responseJSON.message;
  //           }
  //           $messageDiv.css('color', 'red').text('Error: ' + errorMessage);
  //           $button.prop('disabled', false).text('Confirm Handover');
  //       }
  //     });
  //   });
  // });
})(jQuery);
