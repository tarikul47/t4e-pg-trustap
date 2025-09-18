<?php

defined('ABSPATH') || exit;

?>

<div class="trustap-confirm-handover">
    <img src="<?php echo wp_kses_post($icon) ?>"
         alt="Confirm Handover"
         class="icon">
    <p>
        <?php echo esc_html__(
            "In order to confirm handover, click on the button below.",
            "trustap-payment-gateway")
        ?>
    </p>
    <button class="button-primary" type="button" onclick="confirmHandover()">
        <?php echo esc_html__("Confirm Handover (Custom)", "trustap-payment-gateway") ?>
    </button>
</div>
<script>
const confirmHandover = () => {
    const params = new Proxy(new URLSearchParams(window.location.search), {
        get: (searchParams, prop) => searchParams.get(prop),
    });
    let orderId = params.post;
    fetch("<?php echo esc_url($confirm_handover_url) ?>", {
        method: "POST",
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': "<?php echo esc_html($nonce) ?>"
        },
        credentials: "include",
        body: JSON.stringify({orderId})
    })
    .then(response => {
        location.reload();
    });
}
</script>
