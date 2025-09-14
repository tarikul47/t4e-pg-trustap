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
	 * The Trustap API instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WCFM_Trustap_API    $trustap_api    The Trustap API instance.
	 */
	private $trustap_api;

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
		$this->trustap_api = new WCFM_Trustap_API();

		//TODO: Need to set in special class 
		add_action('wp_ajax_wcfm_trustap_oauth_callback', array($this, 'handle_oauth_callback_ajax'));
		add_action('wp_ajax_wcfm_trustap_disconnect', array($this, 'handle_disconnect_ajax'));

	}

	public function wcfmmp_custom_pg_vendor_setting($vendor_billing_fields, $vendor_id)
	{
		$gateway_slug = WCFMTrustap_GATEWAY;

		$vendor_data = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
		$vendor_data = $vendor_data ? $vendor_data : [];
		$settings = array();

		$trustap_settings = get_option('woocommerce_trustap_settings', array());
		$test_mode = (isset($trustap_settings['testmode']) && $trustap_settings['testmode'] === 'yes') ? true : false;
		$environment = $test_mode ? 'test' : 'live';
		$client_id = get_option("trustap_{$environment}_client_id");


		$trustap_user_id = get_user_meta($vendor_id, 'trustap_user_id', true);

		//var_dump($trustap_user_id);

		//delete_user_meta($vendor_id, 'trustap_user_id');

		//var_dump($trustap_user_id);

		//$trustap_user_id = '';

		$trustap_profile_link = "https://app.stage.trustap.com/profile/payout/personal?edit=true&client_id={$client_id}";

		if ($trustap_user_id) {
			$disconnect_url = admin_url('admin-ajax.php?action=wcfm_trustap_disconnect');
			$vendor_billing_fields += array(
				$gateway_slug . '_connection' => array(
					'label' => __('Connect Trustap account', 'wc-multivendor-marketplace'),
					'type' => 'html',
					'name' => 'payment[' . $gateway_slug . '][nationality]',
					'class' => 'wcfm-select wcfm_ele paymode_field paymode_' . $gateway_slug,
					'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_' . $gateway_slug,
					'value' => '<p>You have connected successfully! <a href="' . esc_url($disconnect_url) . '" class="button">Disconnect</a></p><p>Please completed your profile before withdrwa your earnings - <a target="_blank" href="' . $trustap_profile_link . '">Click Here</a></p>',
					'custom_attributes' => array(
						'required' => 'required'
					),
					'hints' => ''
				),
			);
		} else {
			if (!session_id()) {
				session_start();
			}
			$_SESSION['trustap_redirect_url'] = home_url($_SERVER['REQUEST_URI']) . '#wcfm_settings_form_payment_head';

			$vendor_billing_fields += array(
				$gateway_slug . '_connection' => array(
					'label' => __('Connect Trustap account', 'wc-multivendor-marketplace'),
					'type' => 'html',
					'name' => 'payment[' . $gateway_slug . '][nationality]',
					'class' => 'wcfm-select wcfm_ele paymode_field paymode_' . $gateway_slug,
					'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_' . $gateway_slug,
					// 'value' => $settings['nationality'],
					'value' => '<a href="' . esc_url($this->get_trustap_auth_url()) . '" class="button">Connect</a>',
					'custom_attributes' => array(
						'required' => 'required'
					),
					'hints' => '<p>To receive oayouts, you must connect your Trustap account.</p>'
				),
			);
		}



		//TODO: condion wise need to show Connected button with logout button 
		//TODO: Need to show loading bar and sucsss message
		//TODO: profile link 

		return $vendor_billing_fields;
	}

	public function handle_disconnect_ajax()
	{
		if (!is_user_logged_in()) {
			wp_die('You must be logged in to perform this action.');
		}

		$user_id = get_current_user_id();

		delete_user_meta($user_id, 'trustap_user_id');
		delete_user_meta($user_id, 'trustap_access_token');
		delete_user_meta($user_id, 'trustap_refresh_token');

		if (!session_id()) {
			session_start();
		}
		$redirect_url = isset($_SESSION['trustap_redirect_url']) ? $_SESSION['trustap_redirect_url'] : get_wcfm_url();
		unset($_SESSION['trustap_redirect_url']);

		wp_redirect($redirect_url);
		exit;
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
			if ($this->trustap_api->handle_oauth_callback($code, $state)) {
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
			$logger->error('Code and/or state parameter not found in request.', $context);
			wp_die('Code and/or state parameter not found in request.');
		}
	}


	//TODO: The function need to set in Special class for server this purpose 

	public function get_trustap_auth_url()
	{
		return $this->trustap_api->get_auth_url();
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
