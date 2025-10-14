<div class="trustap-confirm-handover">
    <img src="<?php echo wp_kses_post($icon) ?>" alt="Confirm Handover" class="icon">
    <p>
        <?php echo esc_html__(
            "In order to confirm handover, click on the button below.",
            "trustap-payment-gateway"
        )
            ?>
    </p>
    <button id="t4e-confirm-handover-button" class="button-primary" type="button" onclick="t4eConfirmHandover()">
        <?php echo esc_html__("Confirm Handover (Custom)", "trustap-payment-gateway") ?>
    </button>
    <div id="t4e-handover-spinner" style="display: none;">
        <div class="t4e-spinner"></div>
    </div>
</div>