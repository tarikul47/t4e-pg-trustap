<?php

if (!defined('ABSPATH')) {
    exit;
}

use Trustap\PaymentGateway\Gateway as Trustap_Gateway;
use Trustap\PaymentGateway\Helper\Validator;

class Override_Gateway_Trustap extends Trustap_Gateway
{
    protected $logger;

    public function __construct()
    {
        parent::__construct();
       // $my_custom_service = new Service_Override($this);

        // WooCommerce logger
        $this->logger = wc_get_logger();
    }

    private function log($message)
    {
        $this->logger->info($message, array('source' => 'trustap'));
    }

    public function process_payment($order_id)
    {
        global $testmode;
        $trustap_settings = get_option('woocommerce_trustap_settings', array());
        $test_mode = (isset($trustap_settings['testmode']) && $trustap_settings['testmode'] === 'yes') ? true : false;
        $testmode = $test_mode;
        $action_url = \Trustap\PaymentGateway\Enumerators\Uri::ACTION_PAGE_URL();

        $this->log('Starting process_payment by process payment in our custom plugin');


        $this->log('Starting process_payment for Order ID: ' . $order_id);

        global $woocommerce;
        $order = wc_get_order($order_id);
        $items = $order->get_items();
        $first_item = reset($items);
        $product_id = $first_item->get_product_id();

        $product_post = get_post($product_id);
        $post_author_id = $product_post ? $product_post->post_author : 'not_found';
        $this->log('Debugging Vendor ID. Product ID: ' . $product_id . ', Post Author ID: ' . $post_author_id);

        $vendor_id = wcfm_get_vendor_id_by_post($product_id);
        $this->log('Result from wcfm_get_vendor_id_by_post: ' . ($vendor_id ? $vendor_id : 'EMPTY'));

        foreach ($items as $item) {
            $item_product_id = $item->get_product_id();
            $item_vendor_id = wcfm_get_vendor_id_by_post($item_product_id);
            if ($item_vendor_id !== $vendor_id) {
                $this->log('Cart contains products from multiple vendors. Item product ID: ' . $item_product_id . ', Vendor ID: ' . $item_vendor_id . ' (expected: ' . $vendor_id . ')');
                wc_add_notice(__('You can only purchase from one vendor at a time using Trustap. Please split your cart.', 'wcfm-pg-trustap'), 'error');
                return;
            }
        }
        $this->log('All cart items belong to the same vendor or admin.');

        $seller_id = '';
        if ($vendor_id) {
            $seller_id = get_user_meta($vendor_id, 'trustap_user_id', true);
            $this->log('Determined seller is a vendor. Vendor ID: ' . $vendor_id . ', Trustap User ID: ' . $seller_id);
            if (empty($seller_id)) {
                $this->log('Error: The vendor for this product does not have a Trustap account configured (trustap_user_id is empty).');
                wc_add_notice(__('The vendor for this product does not have a Trustap account configured.', 'wcfm-pg-trustap'), 'error');
                return;
            }
        } else {
            $seller_id = $this->controller->seller_id;
            $this->log('Determined seller is admin. Admin Trustap Seller ID: ' . $seller_id);
        }

        $items = $woocommerce->cart->get_cart();
        $product_names = array();
        $payment_method = array();
        foreach ($items as $item => $values) {
            $_product = $values['data']->post;
            $product_names[] = $_product->post_title;
            $product_ID = $_product->ID;
            $_pf = new WC_Product_Factory();
            $product_single = $_pf->get_product($product_ID)->get_data();

            $this->log('Product: ' . $_product->post_title . ' (ID: ' . $product_ID . ') - Virtual: ' . ($product_single['virtual'] ? 'Yes' : 'No') . ', Downloadable: ' . ($product_single['downloadable'] ? 'Yes' : 'No'));

            if ($product_single['virtual'] || $product_single['downloadable']) {
                array_push($payment_method, "f2f");
            } else {
                array_push($payment_method, "online");
            }
        }
        $this->log('Determined payment methods for products: ' . implode(', ', $payment_method));

        $shippable = !empty(WC()->shipping->packages);
        $this->log('Is shippable: ' . ($shippable ? 'Yes' : 'No'));

        if ($order->get_total() * 100 < 100) {
            wc_add_notice(__("In order to use Trustap Payment Gateway, total price of the order has to be larger than 1.00 " . get_woocommerce_currency(), 'trustap-payment-gateway'), 'error');
            return;
        }

        if (in_array("online", $payment_method) && in_array("f2f", $payment_method)) {
            wc_add_notice(__("We currently don't support different types of products in cart. Please select only digital products and continue or choose only products which will get delivered to you by post.", 'trustap-payment-gateway'), 'error');
            return;
        }

        if ($shippable === false) {
            $GLOBALS['model'] = "p2p/";
        } else {
            $GLOBALS['model'] = "";
        }

        $this->log('Final determined Trustap model: ' . ($GLOBALS['model'] === '' ? 'Online' : $GLOBALS['model']));

        $allproductname = implode(", ", $product_names);
        $data = array(
            'price' => ($order->get_total()) * 100,
            'currency' => strtolower(get_woocommerce_currency())
        );
        $this->log('Data for charge calculation: ' . print_r($data, true));

        try {
            $response = $this->controller->get_request($GLOBALS['model'] . 'charge', $data);
            $body = json_decode($response['body'], true);
            $_SESSION['charge'] = $body;
            $this->log('Charge calculation API response: ' . print_r($body, true));
        } catch (Exception $error) {
            $this->log('Error during charge calculation: ' . $error->getMessage());
            wc_add_notice($error);
        }

        $buyer_id = get_current_user_id();
        $trustap_buyer_id = get_user_meta($buyer_id, 'trustap_guest_user_id', true);
        $this->log('Buyer WordPress User ID: ' . $buyer_id . ', Trustap Guest Buyer ID: ' . $trustap_buyer_id);

        if (empty($trustap_buyer_id)) {
            $trustap_buyer_id = $_SESSION['buyer_id'];
            $this->log('Trustap Guest Buyer ID was empty, falling back to session: ' . $trustap_buyer_id);
        }

        $data = [
            'buyer_id' => Validator::sanitize_string($trustap_buyer_id),
            'creator_role' => 'seller',
            'description' => 'Order ID ' . $order_id . ': ' . $allproductname,
            'currency' => Validator::sanitize_string($_SESSION['charge']['currency']),
            'charge_calculator_version' => Validator::sanitize_integer($_SESSION['charge']['charge_calculator_version']),
            'charge_seller' => Validator::sanitize_integer($_SESSION['charge']['charge_seller']),
            'seller_id' => $seller_id
        ];
        $this->log('Data for transaction creation API call: ' . print_r($data, true));

        if ($GLOBALS['model'] === 'p2p/') {
            $data['deposit_price'] = Validator::sanitize_integer($_SESSION['charge']['price']);
            $data['deposit_charge'] = Validator::sanitize_integer($_SESSION['charge']['charge']);
            $data['skip_remainder'] = true;
        } else {
            $data['price'] = Validator::sanitize_integer($_SESSION['charge']['price']);
            $data['charge'] = Validator::sanitize_integer($_SESSION['charge']['charge']);
        }

        $endpoint = $GLOBALS['model'] . 'me/transactions/' . 'create_with_guest_user';
        $this->log('Transaction creation API endpoint: ' . $endpoint);

        try {
            $response = $this->controller->post_request($endpoint, $seller_id, $data);
            $body = json_decode($response['body'], true);
            $_SESSION['transaction'] = $body;
            $this->log('Transaction creation API response: ' . print_r($body, true));

            if ($order->meta_exists('trustap_transaction_ID') && $order->get_status() !== 'payment') {
                $this->log('Order already has trustap_transaction_ID or status is not payment. Skipping update.');
                return;
            } else {
                $order->update_meta_data('trustap_transaction_ID', Validator::sanitize_integer($_SESSION['transaction']['id']));
                $this->log('Trustap transaction ID saved to order meta: ' . $_SESSION['transaction']['id']);
            }

            $order->update_meta_data('model', $GLOBALS['model']);
            $orderID = $order->get_id();
            $order->save();
            $this->log('Order meta updated and order saved. Order ID: ' . $orderID);

            $action_token = md5(uniqid(mt_rand(), true));
            $_SESSION['token'] = $action_token;
            $billing_details = $this->get_billing_details($orderID);
            $this->log('Billing details for redirect: ' . print_r($billing_details, true));

            if ($GLOBALS['model'] === 'p2p/') {
                $state = "token={$action_token}:tx_type=p2p:order_id={$orderID}:name={$billing_details['name']}:line1={$billing_details['line1']}:city={$billing_details['city']}:state={$billing_details['state']}:postcode={$billing_details['postcode']}:country={$billing_details['country']}";
                $state = base64_encode($state);
                $this->log('Redirecting to F2F payment page. State: ' . $state);
                return array(
                    'result' => 'success',
                    'redirect' => $action_url . 'f2f/transactions/' . Validator::sanitize_integer($_SESSION['transaction']['id']) . '/pay_deposit?redirect_uri=' . get_home_url() . '/wc-api/trustap_webhook_raju&state=' . $state
                );
            } else {
                $this->service->send_shipping_details($orderID);
                $state = "token={$action_token}:tx_type=online:order_id={$orderID}:name={$billing_details['name']}:line1={$billing_details['line1']}:city={$billing_details['city']}:state={$billing_details['state']}:postcode={$billing_details['postcode']}:country={$billing_details['country']}";
                $state = base64_encode($state);
                $this->log('Redirecting to Online payment page. State: ' . $state);
                return array(
                    'result' => 'success',
                    'redirect' => $action_url . 'online/transactions/' . Validator::sanitize_integer($_SESSION['transaction']['id']) . '/guest_pay?redirect_uri=' . get_home_url() . '/wc-api/trustap_webhook_raju&state=' . $state
                );
            }
        } catch (Exception $error) {
            $this->log('Error during transaction creation: ' . $error->getMessage());
            wc_add_notice($error);
        }
    }
}