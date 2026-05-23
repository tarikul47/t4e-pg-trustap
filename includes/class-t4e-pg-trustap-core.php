<?php
use Trustap\PaymentGateway\Controller\AbstractController;

class T4e_Pg_Trustap_Core
{

    protected $plugin_name;
    protected $version;
    protected $trustap_api;
    protected $helper;
    protected $controller;

    public function __construct($plugin_name, $version, $trustap_api)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->helper = new WCFM_Trustap_Helper();
        $this->trustap_api = $trustap_api;
        $this->controller = new AbstractController('trustap/v1');
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        register_rest_route('t4e-pg-trustap/v1', '/confirm-handover', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_confirm_handover_request'),
            'permission_callback' => '__return_true' // Adjust permissions as needed
        ));

        register_rest_route('t4e-pg-trustap/v1', '/accept-complaint', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_accept_complaint_request'),
            'permission_callback' => '__return_true'
        ));
    }

    public function handle_accept_complaint_request($request)
    {
        $order_id = $request->get_param('orderId');
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error(
                'invalid_order',
                'Order not found.',
                array('status' => 404)
            );
        }

        $result = $this->accept_complaint($order);

        if (is_wp_error($result)) {
            return $result;
        }

        $order->update_status('complaint-accepted');

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Complaint accepted successfully.'
            ),
            200
        );
    }

    public function accept_complaint($order)
    {
        $transaction_id = $order->get_meta('trustap_transaction_ID');
        $seller_trustap_id = $this->helper->get_trustap_seller_id($order->get_items());
        $model = $order->get_meta('model');
        $tx_type = (strpos($model, 'p2p') !== false) ? 'p2p/' : '';

        if (is_wp_error($seller_trustap_id)) {
            return $seller_trustap_id;
        }

        $raw_response = $this->controller->post_request(
            "{$tx_type}transactions/{$transaction_id}/accept_complaint",
            $seller_trustap_id,
            []
        );

        $response_status = $raw_response['response']['code'];
        $response_body = json_decode($raw_response['body'], true);

        if ($response_status != 200) {
            return new WP_Error(
                'accept_complaint_failed',
                $response_body['message'] ?? 'Failed to accept complaint.',
                array('status' => $response_status)
            );
        }

        // Update meta to prevent redundant sync calls
        $transaction_details = $order->get_meta('_trustap_transaction_details');
        if (!is_array($transaction_details)) {
            $transaction_details = [];
        }
        $transaction_details['status'] = 'complaint_accepted';
        $order->update_meta_data('_trustap_transaction_details', $transaction_details);
        $order->save();

        return true;
    }

    public function handle_confirm_handover_request($request)
    {
        $order_id = $request->get_param('orderId');
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error(
                'invalid_order',
                'Order not found.',
                array('status' => 404)
            );
        }

        $result = $this->confirm_handover($order);

        if (is_wp_error($result)) {
            return $result;
        }

        $order->update_status('completed');

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Handover confirmed successfully.'
            ),
            200
        );
    }

    public function confirm_handover($order)
    {
        $transaction_id = $order->get_meta('trustap_transaction_ID');
        $seller_trustap_id = $this->helper->get_trustap_seller_id($order->get_items());

        if (is_wp_error($seller_trustap_id)) {
            return $seller_trustap_id;
        }

        if (empty($seller_trustap_id)) {
            return new WP_Error(
                'no_seller_trustap_id',
                'Seller Trustap ID not found for order #' . $order->get_id(),
                array('status' => 400)
            );
        }

        $raw_response = $this->controller->post_request(
            "p2p/transactions/{$transaction_id}/confirm_handover",
            $seller_trustap_id,
            []
        );

        $response_status = $raw_response['response']['code'];
        $response_body = json_decode($raw_response['body'], true);

        if ($response_status != 200) {
            return new WP_Error(
                'handover_failed',
                $response_body['message'] ?? 'Handover confirmation failed.',
                array('status' => $response_status)
            );
        }

        // Update meta to prevent redundant sync calls
        $transaction_details = $order->get_meta('_trustap_transaction_details');
        if (!is_array($transaction_details)) {
            $transaction_details = [];
        }
        $transaction_details['status'] = 'completed';
        $order->update_meta_data('_trustap_transaction_details', $transaction_details);
        $order->save();

        return true;
    }

    /**
     * Synchronize Trustap handover when WooCommerce order status is changed to completed.
     * 
     * @param int $order_id
     */
    public function t4e_sync_handover_on_status_change($order_id)
    {
        static $syncing = [];
        if (isset($syncing[$order_id])) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'trustap') {
            return;
        }

        $syncing[$order_id] = true;

        // Check if Trustap transaction exists
        $transaction_id = $order->get_meta('trustap_transaction_ID');
        if (empty($transaction_id)) {
            return;
        }

        // Check if it's already confirmed in meta to avoid redundant API calls
        $transaction_details = $order->get_meta('_trustap_transaction_details');
        $terminal_statuses = ['completed', 'buyer_handover_confirmed', 'seller_handover_confirmed', 'Funds Released'];
        if (isset($transaction_details['status']) && in_array($transaction_details['status'], $terminal_statuses)) {
            return;
        }
        
        // Attempt to confirm handover via API
        $result = $this->confirm_handover($order);

        if (is_wp_error($result)) {
            $order->add_order_note(__('Trustap Handover Sync Error: ', 't4e-pg-trustap') . $result->get_error_message());
        } else {
            $order->add_order_note(__('Trustap Handover Sync: Handover confirmed successfully.', 't4e-pg-trustap'));
        }
    }

    /**
     * Synchronize Trustap complaint acceptance when WooCommerce order status is changed to complaint-accepted.
     * 
     * @param int $order_id
     */
    public function t4e_sync_complaint_acceptance_on_status_change($order_id)
    {
        static $syncing = [];
        if (isset($syncing[$order_id])) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'trustap') {
            return;
        }

        $syncing[$order_id] = true;

        // Check if Trustap transaction exists
        $transaction_id = $order->get_meta('trustap_transaction_ID');
        if (empty($transaction_id)) {
            return;
        }

        // Check if it's already accepted in meta
        $transaction_details = $order->get_meta('_trustap_transaction_details');
        $terminal_statuses = ['complaint_accepted', 'refunded', 'deposit_refunded'];
        if (isset($transaction_details['status']) && in_array($transaction_details['status'], $terminal_statuses)) {
            return;
        }
        
        // Attempt to accept complaint via API
        $result = $this->accept_complaint($order);

        if (is_wp_error($result)) {
            $order->add_order_note(__('Trustap Complaint Sync Error: ', 't4e-pg-trustap') . $result->get_error_message());
        } else {
            $order->add_order_note(__('Trustap Complaint Sync: Complaint accepted successfully.', 't4e-pg-trustap'));
        }
    }
}
