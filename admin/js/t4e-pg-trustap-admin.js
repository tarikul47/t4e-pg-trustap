(function ($) {
  "use strict";

  window.t4eConfirmHandover = function () {
    const button = document.getElementById("t4e-confirm-handover-button");
    const spinner = document.getElementById("t4e-handover-spinner");

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
          alert(data.message || 'Handover confirmed successfully!');
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
  };
})(jQuery);
