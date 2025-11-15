<?php
use Trustap\PaymentGateway\Controller\AbstractController;

class T4e_Pg_Trustap_Core {

    protected $plugin_name;
    protected $version;
    protected $trustap_api;
    protected $helper;
    protected $controller;

    public function __construct($plugin_name, $version, $trustap_api) {
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

		$order->update_status('handoverconfirmed');

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Handover confirmed successfully.'
			),
			200
		);
	}

    public function confirm_handover( $order ) {
<<<<<<< HEAD
        $transaction_id = $order->get_meta('trustap_transaction_ID');
        $seller_trustap_id = $this->helper->get_trustap_seller_id($order->get_items());

        if (is_wp_error($seller_trustap_id)) {
=======
        $logger = wc_get_logger();
        $context = ['source' => 't4e-pg-trustap-handover'];
        $logger->info('Attempting to confirm handover for Order ID: ' . $order->get_id(), $context);

        $transaction_id = $order->get_meta('trustap_transaction_ID');
        $seller_trustap_id = $this->helper->get_trustap_seller_id($order->get_items());

        $logger->info('Transaction ID: ' . $transaction_id, $context);
        $logger->info('Seller Trustap ID: ' . $seller_trustap_id, $context);
        $logger->info('API Key used: ' . $this->controller->api_key, $context);

        if (is_wp_error($seller_trustap_id)) {
            $logger->error('Error getting seller Trustap ID: ' . $seller_trustap_id->get_error_message(), $context);
>>>>>>> 2f0c089e32c259bc60bf6e7bec0a512f751a442c
            return $seller_trustap_id;
        }

        if (empty($seller_trustap_id)) {
<<<<<<< HEAD
=======
            $logger->error('Seller Trustap ID not found for order #' . $order->get_id(), $context);
>>>>>>> 2f0c089e32c259bc60bf6e7bec0a512f751a442c
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

<<<<<<< HEAD
=======
        $logger->info('Raw response from Trustap: ' . print_r($raw_response, true), $context);

>>>>>>> 2f0c089e32c259bc60bf6e7bec0a512f751a442c
        $response_status = $raw_response['response']['code'];
        $response_body = json_decode($raw_response['body'], true);

        if ($response_status != 200) {
<<<<<<< HEAD
=======
            $logger->error('Handover confirmation failed. Status: ' . $response_status . ' Body: ' . print_r($response_body, true), $context);
>>>>>>> 2f0c089e32c259bc60bf6e7bec0a512f751a442c
            return new WP_Error(
                'handover_failed',
                $response_body['message'] ?? 'Handover confirmation failed.',
                array('status' => $response_status)
            );
        }

<<<<<<< HEAD
=======
        $logger->info('Handover confirmed successfully for Order ID: ' . $order->get_id(), $context);
>>>>>>> 2f0c089e32c259bc60bf6e7bec0a512f751a442c
        return true;
    }
}
