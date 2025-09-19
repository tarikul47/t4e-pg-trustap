<?php

if (!defined('ABSPATH')) {
    exit;
}

use Trustap\PaymentGateway\Controller\AbstractController;

class WCFM_Trustap_Helper
{
    private $controller;

    public function __construct()
    {
        // This creates a direct dependency on the parent plugin's controller.
        $this->controller = new AbstractController('trustap/v1');
    }

    public function get_trustap_seller_id(array $items)
    {
        $first_item = reset($items);
        $product_id = $first_item->get_product_id();
        $vendor_id = wcfm_get_vendor_id_by_post($product_id);

        if ($vendor_id) {
            $seller_id = get_user_meta($vendor_id, 'trustap_user_id', true);
            if (empty($seller_id)) {
                return new WP_Error('no_trustap_account', __('The vendor for this product does not have a Trustap account configured.', 'wcfm-pg-trustap'));
            }
            return $seller_id;
        } else {
            // Get the admin seller ID from the controller instantiated in the constructor.
            return $this->controller->seller_id;
        }
    }

    public function get_trustap_buyer_id()
    {
        $buyer_id = get_current_user_id();
        $trustap_buyer_id = get_user_meta($buyer_id, 'trustap_guest_user_id', true);

        if (empty($trustap_buyer_id) && isset($_SESSION['buyer_id'])) {
            $trustap_buyer_id = $_SESSION['buyer_id'];
        }
        return $trustap_buyer_id;
    }
}