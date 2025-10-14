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
    <button id="t4e-confirm-handover-button-admin" class="button-primary" type="button">
        <?php echo esc_html__("Confirm Handover", "trustap-payment-gateway") ?>
    </button>
    <div id="t4e-handover-message-admin" style="margin-top: 10px;"></div>
</div>