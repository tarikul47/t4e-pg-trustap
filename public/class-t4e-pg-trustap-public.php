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

	}

	public function wcfmmp_custom_pg_vendor_setting($vendor_billing_fields, $vendor_id)
	{
		$gateway_slug = WCFMTrustap_GATEWAY;

		$vendor_data = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
		$vendor_data = $vendor_data ? $vendor_data : [];
		$settings = array();

		$trustap_user_id = get_user_meta($vendor_id, 'trustap_user_id', true);
		$trustap_user_id = 'kkkk';

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
					'value' => '<p>To receive oayouts, you must connect your Trustap account.</p><button>Connect</button>',
					'custom_attributes' => array(
						'required' => 'required'
					),
					'hints' => 'dddfddfdfe bddehdedhf djehehd'
				),
			);
		}



		//TODO: condion wise need to show Connected button with logout button 
		//TODO: Need to show loading bar and sucsss message

		return $vendor_billing_fields;
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
