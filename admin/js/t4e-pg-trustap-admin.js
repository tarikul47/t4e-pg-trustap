(function ($) {
  "use strict";

  $(document).ready(function () {
    // 1. Existing Manual Buttons
    $("#t4e-confirm-handover-button-admin").on("click", function () {
      const button = this;
      const spinner = document.getElementById("t4e-handover-spinner");

      const confirmed = confirm("Are you sure you want to confirm handover and release the funds?");
      if (!confirmed) return;

      button.style.display = "none";
      spinner.style.display = "block";

      const params = new Proxy(new URLSearchParams(window.location.search), {
        get: (searchParams, prop) => searchParams.get(prop) || searchParams.get('post'),
      });
      let orderId = params.id || params.post;

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
            // Sync the dropdown status to 'completed'
            const $statusSelect = $('select#order_status, select[name="order_status"]');
            if ($statusSelect.length) {
              $statusSelect.val('wc-completed');
            }

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

    $("#t4e-accept-complaint-button").on("click", function () {
      const button = this;
      const confirmed = confirm("This action will trigger a refund to the buyer. Are you sure you want to proceed?");
      if (!confirmed) return;

      $(button).prop('disabled', true);

      const params = new Proxy(new URLSearchParams(window.location.search), {
        get: (searchParams, prop) => searchParams.get(prop) || searchParams.get('post'),
      });
      let orderId = params.id || params.post;

      fetch(t4e_pg_trustap_admin_data.accept_complaint_url, {
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
            // Sync the dropdown status to 'complaint-accepted'
            const $statusSelect = $('select#order_status, select[name="order_status"]');
            if ($statusSelect.length) {
              $statusSelect.val('wc-complaint-accepted');
            }

            alert(data.message || "Complaint accepted successfully!");
            location.reload();
          } else {
            alert(data.message || "Failed to accept complaint!");
          }
        })
        .catch((error) => {
          alert("Error: " + error.message);
        })
        .finally(() => {
          $(button).prop('disabled', false);
        });
    });

    // 2. Intercept Status Dropdown Changes in Admin Order Details
    if (t4e_pg_trustap_admin_data.payment_method === 'trustap') {
      const $statusSelect = $('select#order_status, select[name="order_status"]');
      
      // We listen for the 'change' on the select, but we need to intercept the 'Update' button click
      // or provide a warning immediately on change.
      $statusSelect.on('change', function() {
        const newStatus = $(this).val();
        let message = '';

        if (newStatus === 'wc-completed' || newStatus === 'completed') {
          message = "Changing status to 'Completed' will automatically release the funds to the seller on Trustap. Do you want to continue?";
        } else if (newStatus === 'wc-complaint-accepted' || newStatus === 'complaint-accepted') {
          message = "Changing status to 'Complaint Accepted' will automatically trigger a refund to the buyer on Trustap. Do you want to continue?";
        }

        if (message && !confirm(message)) {
          // Revert to previous value (this is tricky, so we store it)
          $(this).val($(this).data('prev-val'));
          return false;
        }
        
        $(this).data('prev-val', newStatus);
      });

      // Initialize prev-val
      $statusSelect.data('prev-val', $statusSelect.val());
    }
  });

})(jQuery);