<?php

use Trustap\PaymentGateway\Helper\Template;
use Trustap\PaymentGateway\Controller\AbstractController;
use Trustap\PaymentGateway\Enumerators\Uri as UriEnumerator;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://onlytarikul.com
 * @since      1.0.0
 *
 * @package    T4e_Pg_Trustap
 * @subpackage T4e_Pg_Trustap/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    T4e_Pg_Trustap
 * @subpackage T4e_Pg_Trustap/admin
 * @author     Tarikul Islam <tarikul47@gmail.com>
 */
class T4e_Pg_Trustap_Admin
{

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	private $trustap_api;


	private $helper;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */

	protected $controller;

	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->helper = new WCFM_Trustap_Helper();
		$this->trustap_api = new WCFM_Trustap_API();
		$this->controller = new AbstractController('trustap/v1');
		add_action('rest_api_init', array($this, 'register_routes'));

	}

	public function register_routes()
	{
		register_rest_route('t4e-pg-trustap/v1', '/confirm-handover', array(
			'methods' => 'POST',
			'callback' => array($this, 't4e_confirm_handover'),
			'permission_callback' => '__return_true' // Adjust permissions as needed
		));
	}

	public function t4e_confirm_handover($request)
	{
		$order_id = $request->get_param('orderId');
		$order = wc_get_order($order_id);
		$transaction_id = $order->get_meta('trustap_transaction_ID');

		$seller_trustap_id = $this->helper->get_trustap_seller_id($order->get_items());

		if (is_wp_error($seller_trustap_id)) {
			return new WP_Error(
				'no_seller_trustap_id',
				$seller_trustap_id->get_error_message(),
				array('status' => 400)
			);
		}

		if (empty($seller_trustap_id)) {
			return new WP_Error(
				'no_seller_trustap_id',
				'Seller Trustap ID not found for order #' . $order->get_id(),
				array('status' => 400)
			);
		}

		$data = ['transactionId' => $transaction_id];
		$raw_response = $this->controller->post_request(
			"p2p/transactions/{$transaction_id}/confirm_handover",
			$seller_trustap_id,
			//$data
			''
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

		$order->update_status('handoverconfirmed');

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Handover confirmed successfully.'
			),
			200
		);
	}


	public function t4e_add_confirm_handover_meta_box($post_type, $post)
	{

		// global $post;
		$order = wc_get_order($post->ID);

		$logger = wc_get_logger();
		$logger->info('t4e_add_confirm_handover_meta_box', ['source' => 'service-override']);

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
			't4e-trustap-confirm-handover-meta-box_ffnnn',
			'Trustap Handover Custopmmm',
			[$this, 't4e_confirm_handover_meta_box'],
			'woocommerce_page_wc-orders',
			'side',
			'high'
		);
	}


	public function t4e_confirm_handover_meta_box()
	{
		$args = [
			'icon' => TRUSTAP_IMAGE_URL . "handshake-simple-solid.svg",
			'confirm_handover_url' => UriEnumerator::CONFIRM_HANDOVER_URL(),
			'nonce' => wp_create_nonce('wp_rest')
		];
		// Make $icon, $confirm_handover_url, $nonce available in partial
		extract($args);
		include(plugin_dir_path(__FILE__) . 'partials/t4e-confirm-handover.php');
	}

	public function wcfmmp_custom_pg($payment_methods)
	{
		$payment_methods[WCFMTrustap_GATEWAY] = __(WCFMTrustap_GATEWAY_LABEL, 'wcfm-pg-trustap');
		return $payment_methods;
	}

	public function override_trustap_gateway($gateways)
	{
		$trustap_gateway_key = array_search('Trustap\PaymentGateway\Gateway', $gateways);

		if ($trustap_gateway_key !== false) {
			// Unset the original gateway using the key we found.
			unset($gateways[$trustap_gateway_key]);
			// Add our overridden gateway class with the correct key.
			$gateways['trustap'] = 'Override_Gateway_Trustap';
		}
		return $gateways;
	}

	public function create_trustap_guest_user_on_registration($user_id)
	{
		
		if (get_user_meta($user_id, "trustap_guest_{$this->trustap_api->environment}_user_id", true)) {
			return;
		}

		WCFMTrustap_Logger::log('Attempting to create Trustap guest user for user ID: ' . $user_id);
		global $WCFMmp;
		$user_data = get_userdata($user_id);
		$user_roles = (array) $user_data->roles;

		// Proceed only for customers and vendors
		if (in_array('customer', $user_roles, true) || in_array('wcfm_vendor', $user_roles, true)) {
			WCFMTrustap_Logger::log('User is a customer or vendor. Proceeding.');
			$email = $user_data->user_email;
			$first_name = $user_data->first_name;
			$last_name = $user_data->last_name;

			// Fallback to username if first/last name are not set
			if (empty($first_name)) {
				$first_name = $user_data->user_login;
				WCFMTrustap_Logger::log('First name empty, using username as first name: ' . $first_name);
			}
			if (empty($last_name)) {
				$last_name = $user_data->user_login;
				WCFMTrustap_Logger::log('Last name empty, using username as last name: ' . $last_name);
			}

			// Get country from WooCommerce billing info if available
			$country = get_user_meta($user_id, 'billing_country', true);
			if (empty($country)) {
				// Default country if not set, required by Trustap API
				$country = 'IE'; // Or get from store base location
				WCFMTrustap_Logger::log('Country empty, defaulting to IE.');
			}

			$wcfm_withdrawal_options = get_option('wcfm_withdrawal_options', array());
			WCFMTrustap_Logger::log('Retrieved WCFM Withdrawal Options: ' . print_r($wcfm_withdrawal_options, true));
			$test_mode = isset($wcfm_withdrawal_options['test_mode']) ? true : false;
			$client_id = $test_mode ? (isset($wcfm_withdrawal_options[WCFMTrustap_GATEWAY . '_test_client_id']) ? $wcfm_withdrawal_options[WCFMTrustap_GATEWAY . '_test_client_id'] : '') : (isset($wcfm_withdrawal_options[WCFMTrustap_GATEWAY . '_client_id']) ? $wcfm_withdrawal_options[WCFMTrustap_GATEWAY . '_client_id'] : '');

			if (empty($client_id)) {
				WCFMTrustap_Logger::log('Trustap guest user creation failed: API keys not set.');
				return;
			}

			$api_url = $test_mode ? 'https://api-sandbox.trustap.com/api/v1/guest_users' : 'https://api.trustap.com/api/v1/guest_users';
			WCFMTrustap_Logger::log('Trustap API URL: ' . $api_url);

			$body = array(
				'email' => $email,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'country_code' => $country,
				'tos_acceptance' => array(
					'unix_timestamp' => time(),
					'ip' => $_SERVER['REMOTE_ADDR']
				)
			);
			WCFMTrustap_Logger::log('Request body for guest user creation: ' . print_r($body, true));

			$args = array(
				'method' => 'POST',
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode($client_id . ':'),
					'Content-Type' => 'application/json',
				),
				'body' => json_encode($body),
				'timeout' => 60,
			);

			$response = wp_remote_post($api_url, $args);

			if (is_wp_error($response)) {
				WCFMTrustap_Logger::log('WP Error during guest user creation: ' . $response->get_error_message());
			} else {
				$response_code = wp_remote_retrieve_response_code($response);
				$response_body = wp_remote_retrieve_body($response);
				$decoded_response = json_decode($response_body, true);

				WCFMTrustap_Logger::log('Trustap guest user creation response code: ' . $response_code);
				WCFMTrustap_Logger::log('Trustap guest user creation response body: ' . print_r($decoded_response, true));

				if (isset($decoded_response['id'])) {
					update_user_meta($user_id, "trustap_guest_{$this->trustap_api->environment}_user_id", $decoded_response['id']);
					WCFMTrustap_Logger::log('Trustap guest user ID saved for user ' . $user_id . ': ' . $decoded_response['id']);
				} else {
					WCFMTrustap_Logger::log('Trustap guest user ID not found in response.');
				}
			}
		} else {
			WCFMTrustap_Logger::log('User is not a customer or vendor. Skipping guest user creation.');
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in T4e_Pg_Trustap_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The T4e_Pg_Trustap_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/t4e-pg-trustap-admin.css', array(), $this->version, 'all');

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in T4e_Pg_Trustap_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The T4e_Pg_Trustap_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/t4e-pg-trustap-admin.js', array('jquery'), $this->version, true);

		$localized_data = array(
			'confirm_handover_url' => get_rest_url(null, 't4e-pg-trustap/v1/confirm-handover'),
			'nonce' => wp_create_nonce('wp_rest')
		);
		wp_localize_script($this->plugin_name, 't4e_pg_trustap_admin_data', $localized_data);

	}

}