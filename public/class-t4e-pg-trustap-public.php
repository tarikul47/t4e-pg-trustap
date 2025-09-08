<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://onlytarikul.com
 * @since      1.0.0
 *
 * @package    T4e_Pg_Trustap
 * @subpackage T4e_Pg_Trustap/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    T4e_Pg_Trustap
 * @subpackage T4e_Pg_Trustap/public
 * @author     Tarikul Islam <tarikul47@gmail.com>
 */
class T4e_Pg_Trustap_Public
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

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		//TODO: Need to set in special class 
		add_action('wp_ajax_wcfm_trustap_oauth_callback', array($this, 'handle_oauth_callback_ajax'));

	}

	public function wcfmmp_custom_pg_vendor_setting($vendor_billing_fields, $vendor_id)
	{
		$gateway_slug = WCFMTrustap_GATEWAY;

		$vendor_data = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
		$vendor_data = $vendor_data ? $vendor_data : [];
		$settings = array();

		$trustap_user_id = get_user_meta($vendor_id, 'trustap_user_id', true);

		print_r($trustap_user_id);

		delete_user_meta( $vendor_id, 'trustap_user_id');

		print_r($trustap_user_id);

		//$trustap_user_id = 'kkkk';

		$trustap_profile_link = '#';

		if ($trustap_user_id) {
			$vendor_billing_fields += array(
				$gateway_slug . '_connection' => array(
					'label' => __('Connect Your Trustap account', 'wc-multivendor-marketplace'),
					'type' => 'html',
					'name' => 'payment[' . $gateway_slug . '][nationality]',
					'class' => 'wcfm-select wcfm_ele paymode_field paymode_' . $gateway_slug,
					'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_' . $gateway_slug,
					// 'value' => $settings['nationality'],
					'value' => '<button>You have connected successfully!</button> <p>Please completed your profile before withdrwa your earnings - <a href="' . $trustap_profile_link . '">Click Here</a></p>',
					'custom_attributes' => array(
						'required' => 'required'
					),
					'hints' => 'dddfddfdfe bddehdedhf djehehd'
				),
			);
		} else {
			$vendor_billing_fields += array(
				$gateway_slug . '_connection' => array(
					'label' => __('Connect Your Trustap account', 'wc-multivendor-marketplace'),
					'type' => 'html',
					'name' => 'payment[' . $gateway_slug . '][nationality]',
					'class' => 'wcfm-select wcfm_ele paymode_field paymode_' . $gateway_slug,
					'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_' . $gateway_slug,
					// 'value' => $settings['nationality'],
					'value' => '<p>To receive oayouts, you must connect your Trustap account.</p><a href="' . esc_url($this->get_trustap_auth_url()) . '" class="button">Connect</a>',
					'custom_attributes' => array(
						'required' => 'required'
					),
					'hints' => 'dddfddfdfe bddehdedhf djehehd'
				),
			);
		}



		//TODO: condion wise need to show Connected button with logout button 
		//TODO: Need to show loading bar and sucsss message
		//TODO: profile link 

		return $vendor_billing_fields;
	}

	//TODO: The function need to set in Special class for server this purpose 
	public function handle_oauth_callback_ajax()
	{
		$logger = wc_get_logger();
		$context = array('source' => 't4e-pg-trustap');

		$logger->info('OAuth callback initiated.', $context);

		if (!is_user_logged_in()) {
			$logger->error('User is not logged in. Aborting.', $context);
			wp_die('You must be logged in to perform this action.');
		}

		$code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : null;
		$state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : null;

		if (isset($code) && isset($state)) {
			$logger->info('Code and state parameter found.', $context);

			$user_id = get_current_user_id();
			$saved_state = get_transient('trustap_oauth_state_' . $user_id);

			if (!$saved_state || $state !== $saved_state) {
				$logger->error('State verification failed.', $context);
				wp_die('State verification failed. Please try again.');
			}

			delete_transient('trustap_oauth_state_' . $user_id);
			$logger->info('State verified.', $context);

			$trustap_settings = get_option('woocommerce_trustap_settings', array());
			$test_mode = (isset($trustap_settings['testmode']) && $trustap_settings['testmode'] === 'yes');
			$environment = $test_mode ? 'test' : 'live';
			$client_id = get_option("trustap_{$environment}_client_id");
			$client_secret = get_option("trustap_{$environment}_client_secret");

			$logger->info('Environment: ' . $environment, $context);

			$realm = $test_mode ? 'trustap-stage' : 'trustap';
			$token_url = sprintf('https://sso.trustap.com/auth/realms/%s/protocol/openid-connect/token', $realm);

			$logger->info('Requesting token from: ' . $token_url, $context);

			$response = wp_remote_post($token_url, array(
				'method' => 'POST',
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body' => array(
					'grant_type' => 'authorization_code',
					'code' => $code,
					'redirect_uri' => admin_url('admin-ajax.php?action=wcfm_trustap_oauth_callback'),
					'client_id' => $client_id,
					'client_secret' => $client_secret,
				),
			));

			if (is_wp_error($response)) {
				$logger->error('Token request failed: ' . $response->get_error_message(), $context);
				wp_die('Token request failed.');
			}

			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);

			$logger->info('Token response body: ' . print_r($data, true), $context);

			// --- FIX: Store both id_token and access_token
			if (isset($data['id_token']) && isset($data['access_token'])) {
				$id_token_parts = explode('.', $data['id_token']);
				$id_token_payload = json_decode(base64_decode($id_token_parts[1]), true);

				if (isset($id_token_payload['sub'])) {
					$logger->info('Trustap user ID found: ' . $id_token_payload['sub'], $context);
					$logger->info('Access Token found: ' . $data['access_token'], $context);

					update_user_meta($user_id, 'trustap_user_id', $id_token_payload['sub']);
					update_user_meta($user_id, 'trustap_access_token', $data['access_token']);
					update_user_meta($user_id, 'trustap_refresh_token', $data['refresh_token']);

					$logger->info('User meta updated for user ID: ' . $user_id, $context);

					if (!session_id()) {
						session_start();
					}
					$redirect_url = isset($_SESSION['trustap_redirect_url']) ? $_SESSION['trustap_redirect_url'] : get_wcfm_url();
					unset($_SESSION['trustap_redirect_url']);

					$logger->info('Redirecting to: ' . $redirect_url, $context);
					wp_redirect($redirect_url);
					exit;
				} else {
					$logger->error('Trustap user ID (sub) not found in token payload.', $context);
					wp_die('Trustap user ID not found in token payload.');
				}
			} else {
				$logger->error('ID token or Access Token not found in response.', $context);
				wp_die('Authentication failed. Required tokens not found.');
			}
		} else {
			$logger->error('Code and/or state parameter not found in request.', $context);
			wp_die('Code and/or state parameter not found in request.');
		}
	}


	//TODO: The function need to set in Special class for server this purpose 

	public function get_trustap_auth_url()
	{
		$trustap_settings = get_option('woocommerce_trustap_settings', array());
		$test_mode = (isset($trustap_settings['testmode']) && $trustap_settings['testmode'] === 'yes') ? true : false;
		$environment = $test_mode ? 'test' : 'live';
		$client_id = get_option("trustap_{$environment}_client_id");

		$redirect_uri = urlencode(admin_url('admin-ajax.php?action=wcfm_trustap_oauth_callback'));

		// Generate a random state and store it in a transient
		$state = bin2hex(random_bytes(16));
		set_transient('trustap_oauth_state_' . get_current_user_id(), $state, 15 * 60); // 15 minute expiration

		$scope = urlencode('openid p2p_tx:offline_create_join p2p_tx:offline_accept_deposit p2p_tx:offline_cancel p2p_tx:offline_confirm_handover');

		$realm = $test_mode ? 'trustap-stage' : 'trustap';

		$auth_url = sprintf(
			'https://sso.trustap.com/auth/realms/%s/protocol/openid-connect/auth?client_id=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s',
			$realm,
			$client_id,
			$redirect_uri,
			$scope,
			$state
		);

		return $auth_url;
	}
	/**
	 * Register the stylesheets for the public-facing side of the site.
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

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/t4e-pg-trustap-public.css', array(), $this->version, 'all');

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
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

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/t4e-pg-trustap-public.js', array('jquery'), $this->version, false);

	}

}
