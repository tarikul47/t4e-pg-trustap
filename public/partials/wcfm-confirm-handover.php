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
    <form method="post" action="">
        <input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>">
        <input type="hidden" name="trustap_confirm_handover_vendor" value="true">
        <?php wp_nonce_field('trustap_confirm_handover_vendor_nonce', 'trustap_confirm_handover_vendor_nonce_field'); ?>
        <button type="submit" class="wcfm_submit_button">
            <?php echo esc_html__("Confirm Handover", "t4e-pg-trustap"); ?>
        </button>
    </form>
</div>