(function ($) {
  "use strict";

  $(document).ready(function () {
    // 1. Manual Handover Button
    $("#t4e-confirm-handover-button").on("click", function (e) {
      e.preventDefault();

      if (!confirm("Are you sure you want to confirm handover?")) {
        return;
      }

      const button = $(this);
      const messageDiv = $("#t4e-handover-message");
      const orderId = button.data("order-id");

      button.prop("disabled", true).text("Confirming...");
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
            messageDiv
              .css("color", "green")
              .text(
                data.message ||
                  "Handover confirmed successfully! Page will reload.",
              );
            setTimeout(() => window.location.reload(), 2000);
          } else {
            messageDiv
              .css("color", "red")
              .text(
                "Error: " + (data.message || "Handover confirmation failed!"),
              );
            button.prop("disabled", false).text("Confirm Handover");
          }
        })
        .catch((error) => {
          messageDiv.css("color", "red").text("Error: " + error.message);
          button.prop("disabled", false).text("Confirm Handover");
        });
    });

    // 2. Intercept Status Dropdown Changes in WCFM Order Details
    if (t4e_pg_trustap_public_data.payment_method === "trustap") {
      const $wcfmStatusSelect = $("#wcfm_order_status");

      if ($wcfmStatusSelect.length) {
        $wcfmStatusSelect.on("change", function () {
          const newStatus = $(this).val();
          let message = "";

          if (newStatus === "completed" || newStatus === "wc-completed") {
            message =
              "Changing status to 'Completed' will automatically release the funds to the seller on Trustap. Do you want to continue?";
          } else if (
            newStatus === "complaint-accepted" ||
            newStatus === "wc-complaint-accepted"
          ) {
            message =
              "Changing status to 'Complaint Accepted' will automatically trigger a refund to the buyer on Trustap. Do you want to continue?";
          }

          if (message && !confirm(message)) {
            $(this).val($(this).data("prev-val"));
            // If Select2 is used, we need to trigger an update
            if ($(this).hasClass("select2-hidden-accessible")) {
              $(this).trigger("change.select2");
            }
            return false;
          }

          $(this).data("prev-val", newStatus);
        });

        // Initialize prev-val
        $wcfmStatusSelect.data("prev-val", $wcfmStatusSelect.val());

        // Secondary check on Update button click (safety net)
        $("#wcfm_modify_order_status").on("click", function (e) {
          const newStatus = $wcfmStatusSelect.val();
          let message = "";

          if (newStatus === "completed" || newStatus === "wc-completed") {
            message =
              "Changing status to 'Completed' will automatically release the funds to the seller on Trustap. Do you want to continue?";
          } else if (
            newStatus === "complaint-accepted" ||
            newStatus === "wc-complaint-accepted"
          ) {
            message =
              "Changing status to 'Complaint Accepted' will automatically trigger a refund to the buyer on Trustap. Do you want to continue?";
          }

          if (message && !confirm(message)) {
            e.stopImmediatePropagation();
            return false;
          }
        });
      }
    }
  });
})(jQuery);
