<?php

use Trustap\PaymentGateway\Helper\Validator;

use Automattic\WooCommerce\Utilities\OrderUtil;

use Trustap\PaymentGateway\Enumerators\Uri as UriEnumerator;





class Service_Override
{

    private $wc_payment_gateway;

    public $controller;
    public $namespace;

    public $trustap_api_url;
    public $trustap_api;

    public $seller_id;


    public $username;

    public $password;



    public function __construct($gateway, $controller)
    {

        // Initialize properties from AbstractController's constructor
        $this->controller = $controller;
        $this->trustap_api = new WCFM_Trustap_API();

        $this->namespace = 'trustap_payment_gateway';

        $this->trustap_api_url = UriEnumerator::API_URL();

        $this->wc_payment_gateway = $gateway; // Assuming this is the main gateway class

        remove_all_actions('woocommerce_api_trustap_webhook');

        // Add your child webhook handler

        //add_action('woocommerce_api_trustap_webhook_raju', array($this, 'child_trustap_webhook'));

        add_action('woocommerce_api_trustap_webhook_raju', array($this, 'child_trustap_custom_webhook'));

        add_action('add_meta_boxes', array($this, 't4e_add_confirm_handover_meta_box', 10, 2));

    }

    public function t4e_add_confirm_handover_meta_box()
    {
        global $post;
        $order = wc_get_order($post->ID);


        if (!$order) {
            return;
        }
        if (strpos($order->get_meta('model'), "p2p/") === false) {
            return;
        }
        if ($order->get_payment_method() !== 'trustap') {
            return;
        }
        if (!$order->has_status('handoverpending')) {
            return;
        }
        add_meta_box(
            't4e-trustap-confirm-handover-meta-box',
            'Trustap Handover',
            array($this, 'confirm_handover_meta_box'),
            'woocommerce_page_wc-orders',
            'side',
            'high'
        );
    }


    public function confirm_handover_meta_box()
    {
        $template = new Template();
        $args = [
            'icon' => TRUSTAP_IMAGE_URL . "handshake-simple-solid.svg",
            'confirm_handover_url' => UriEnumerator::CONFIRM_HANDOVER_URL(),
            'nonce' => wp_create_nonce('wp_rest')
        ];
      //  echo $template->render('settings', 'ConfirmHandover', $args);
      echo "--------------------";
    }

    public function child_trustap_webhook()
    {

        $logger = wc_get_logger();

        // $logger->info('Child webhook triggered ✅', ['source' => 'trustap-child']);

        $state = isset($_GET['state']) ? $_GET['state'] : '';

        //  $logger->info('State parameter: ' . $state, ['source' => 'trustap-child']);

        // Here you can add custom service logic if needed

        wp_send_json_success(['message' => 'RajuHandled by child plugin----tt-']);

    }

    public function child_trustap_custom_webhook()
    {

        $logger = wc_get_logger();

        // $logger->info('Child webhook triggered ✅', ['source' => 'trustap-child']);



        if ($_SERVER['REQUEST_METHOD'] === 'GET') {

            //  $logger->info('Handling GET request for P2P webhook.', ['source' => 'trustap-child']);

            $this->p2p_webhook_get();

        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

            //  $logger->info('Handling POST request for P2P webhook.', ['source' => 'trustap-child']);

            $this->p2p_webhook_post();

        }

        die();

    }

    private function p2p_webhook_get()
    {

        try {

            if (!isset($_GET['tx_id'])) {

                $logger = wc_get_logger();

                $logger->error('Missing tx_id in GET request', ['source' => 'trustap-child']);

                exit();

            }



            $transaction_id = Validator::sanitize_string($_GET['tx_id']);

            $order = $this->get_order_by_transaction_id($transaction_id);



            if (!$order) {

                $logger = wc_get_logger();

                $logger->error("Order not found for transaction ID: {$transaction_id}", ['source' => 'trustap-child']);

                exit();

            }



            $transaction = $this->get_transaction('p2p', $transaction_id);

            if (!isset($transaction['deposit_paid']) || !$transaction['deposit_paid']) {

                $logger = wc_get_logger();

                $logger->error("Deposit not paid for transaction ID: {$transaction_id}", ['source' => 'trustap-child']);

                wc_add_notice(__('Please try again.', 'trustap-payment-gateway'), 'error');

                exit();

            }



            $order->payment_complete();

            $order->add_order_note(__('Paid and confirmed (P2P via GET)', 'trustap-payment-gateway'), true);



            if (function_exists('WC') && is_a(WC()->cart, 'WC_Cart')) {

                WC()->cart->empty_cart();

            }



            $this->accept_deposit($transaction_id, $order);

            $this->handle_handover_confirmation($transaction_id, $order);



            return wp_redirect($this->wc_payment_gateway->get_return_url($order));

        } catch (Exception $e) {

            $logger = wc_get_logger();

            $logger->error('p2p_webhook_get error: ' . $e->getMessage(), ['source' => 'trustap-child']);

            wc_add_notice(__('An error occurred. Please try again later.', 'trustap-payment-gateway'), 'error');

        }

    }

    private function p2p_webhook_post()
    {

        try {

            $request_body = file_get_contents('php://input');

            $body_webhook = json_decode($request_body);



            if (!$body_webhook || !isset($body_webhook->code) || strpos($body_webhook->code, 'p2p_tx') === false) {

                wc_add_notice(__('Invalid webhook data.', 'trustap-payment-gateway'), 'error');

                exit();

            }



            $logger = wc_get_logger();

            //   $logger->info("Received P2P POST webhook with code: {$body_webhook->code}", ['source' => 'trustap-child']);



            $transaction_id = isset($body_webhook->target_id) ? $body_webhook->target_id : null;

            $order = $transaction_id ? $this->get_order_by_transaction_id($transaction_id) : null;



            if ($body_webhook->code === "p2p_tx.deposit_paid") {

                if ($order && $this->is_deposit_paid($transaction_id)) {

                    $order->payment_complete();

                    $order->add_order_note(__('Paid and confirmed (P2P via POST)', 'trustap-payment-gateway'), true);

                    $this->accept_deposit($transaction_id, $order);

                    $this->handle_handover_confirmation($transaction_id, $order);

                }

            } elseif ($body_webhook->code === "p2p_tx.cancelled") {

                if ($order) {

                    $this->cancel_order($transaction_id);

                }

            } elseif (

                $body_webhook->code === "p2p_tx.buyer_handover_confirmed" ||

                $body_webhook->code === "p2p_tx.seller_handover_confirmed"

            ) {

                if ($order) {

                    $order->update_status('handoverconfirmed');

                }

            } elseif ($body_webhook->code === "p2p_tx.complained") {

                if ($order) {

                    $order->update_status('complainedbybuyer');

                    $order->add_order_note(__('Complaint raised by the buyer.', 'trustap-payment-gateway'));

                }

            } elseif ($body_webhook->code === "p2p_tx.deposit_refunded") {

                if ($order) {

                    $order->update_status('refundedtobuyer');

                    $order->add_order_note(__('Funds were refunded to the buyer.', 'trustap-payment-gateway'));

                }

            }

            status_header(200);

        } catch (Exception $e) {

            $logger = wc_get_logger();

            $logger->error('p2p_webhook_post error: ' . $e->getMessage(), ['source' => 'trustap-child']);

            wc_add_notice(__('An error occurred. Please try again later.', 'trustap-payment-gateway'), 'error');

        }

    }

    private function is_deposit_paid($transaction_id)
    {

        $transaction = $this->get_transaction('p2p', $transaction_id);

        if (!$transaction || !isset($transaction['deposit_paid']) || !$transaction['deposit_paid']) {

            wc_add_notice(__('Please try again.', 'trustap-payment-gateway'), 'error');

            return false;

        }

        return true;

    }

    private function get_transaction($type, $transaction_id)
    {
        $prefix = ($type === 'p2p') ? 'p2p' : '';
        try {
            $response = $this->controller->get_request(
                "{$prefix}/transactions/{$transaction_id}",
                ''
            );

        } catch (Exception $error) {
            wc_add_notice($error);
            return false;
        }

        $body = json_decode($response['body'], true);
        return $body;
    }

    private function accept_deposit($transaction_id, $order)
    {
        $logger = wc_get_logger();
        try {
            $seller_id = '';
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product) {
                    $seller_id = get_post_field('post_author', $product->get_id());
                    break;
                }
            }

            if (empty($seller_id)) {
                throw new Exception('Seller ID not found for order #' . $order->get_id());
            }

            $seller_trustap_id = get_user_meta($seller_id, 'trustap_user_id', true);
            $access_token = get_user_meta($seller_id, 'trustap_access_token', true);

            // $logger->info(

            //     'accept_deposit request: ' . print_r([
            //         'transaction_id' => $transaction_id,
            //         'seller_id' => $seller_id,
            //         'seller_trustap_id' => $seller_trustap_id,
            //         'access_token' => $access_token,
            //         'order_id' => $order->get_id()
            //     ], true),

            //     ['source' => 'trustap-child']

            // );

            $result = $this->controller->post_request(
                "p2p/transactions/{$transaction_id}/accept_deposit",
                $seller_trustap_id,
                '',
            );
            //   $logger->info('accept_deposit response: ' . print_r($result, true), ['source' => 'trustap-child']);

        } catch (Exception $exception) {

            $logger->error('accept_deposit error: ' . $exception->getMessage(), ['source' => 'trustap-child']);
            $order->add_order_note(__('Accept deposit manually.', 'trustap-payment-gateway'), false);
            return wp_redirect($this->wc_payment_gateway->get_return_url($order));
        }
    }

    private function handle_handover_confirmation($transaction_id, $order)
    {

        if (isset($this->wc_payment_gateway->confirm_handover) && $this->wc_payment_gateway->confirm_handover === 'manually') {

            $order->update_status('handoverpending');

        } else {

            $this->confirm_handover($transaction_id, $order);

        }

    }

    public function confirm_handover($transaction_id, $order)
    {

        try {

            $this->post_request(

                "p2p/transactions/{$transaction_id}/confirm_handover",

                $this->seller_id,

                ''

            );

        } catch (Exception $exception) {

            $order->add_order_note(

                __('Confirm handover manually.', 'trustap-payment-gateway'),

                false

            );

            return wp_redirect($this->wc_payment_gateway->get_return_url($order));

        }

    }
    private function cancel_order($transaction_id)
    {

        $order = $this->get_order_by_transaction_id($transaction_id);

        if ($order) {

            $order->update_status('cancelled');

        }

    }

    public static function get_order_by_transaction_id($transaction_id)
    {

        global $wpdb;

        $order_id = null;

        if (OrderUtil::custom_orders_table_usage_is_enabled()) {

            $order_id = $wpdb->get_var(

                $wpdb->prepare(

                    "

            SELECT order_id

            FROM {$wpdb->prefix}wc_orders_meta

            WHERE meta_key = 'trustap_transaction_ID'

            AND meta_value = %s

            ",

                    $transaction_id

                )

            );

        } else {

            $order_id = $wpdb->get_var(

                $wpdb->prepare(

                    "

                    SELECT DISTINCT ID FROM

                    $wpdb->posts as posts

                    LEFT JOIN $wpdb->postmeta as meta

                        ON posts.ID = meta.post_id

                        WHERE meta.meta_value = %s

                        AND meta.meta_key = %s

                ",

                    $transaction_id,

                    'trustap_transaction_ID'

                )

            );

        }

        if (empty($order_id)) {

            return false;

        }

        return wc_get_order($order_id);

    }


}

