<?php

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="trustap-confirm-handover" style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">
    <h3><?php echo esc_html__("Trustap Handover", "t4e-pg-trustap"); ?></h3>
    <p>
        <?php
        echo esc_html__(
            "In order to confirm handover, click on the button below.",
            "t4e-pg-trustap"
        );
        ?>
    </p>
    <button type="button" id="t4e-confirm-handover-button" class="wcfm_submit_button" data-order-id="<?php echo esc_attr($order_id); ?>">
        <?php echo esc_html__("Confirm Handover", "t4e-pg-trustap"); ?>
    </button>
    <div id="t4e-handover-message" style="margin-top: 10px;"></div>
</div>
