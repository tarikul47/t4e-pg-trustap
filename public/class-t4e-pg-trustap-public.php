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
use Trustap\PaymentGateway\Controller\AbstractController;

class T4e_Pg_Trustap_Public extends T4e_Pg_Trustap_Core
{

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */

	private $version;

	/**
	 * The OAuth handler for the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      T4e_Pg_Trustap_OAuth_Handler    $oauth_handler    Handles OAuth2 flow.
	 */
	private $oauth_handler;

	/**
	 * The Trustap API instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WCFM_Trustap_API    $trustap_api    The Trustap API instance.
	 */

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 * @param      T4e_Pg_Trustap_OAuth_Handler    $oauth_handler    The OAuth handler instance.
	 */
	public function __construct($plugin_name, $version, $oauth_handler)
	{

		parent::__construct($plugin_name, $version);
		$this->oauth_handler = $oauth_handler;

		// WCFM handover confirmed button show 
		add_action('wcfm_order_details_after_order_table', array($this, 'wcfm_show_handover_button'), 10, 1);

	}

	public function wcfm_show_handover_button($order_id)
	{
		if (!$order->has_status('handoverpending')) {
			return;
		}

		$this->enqueue_scripts($order_id);

		include_once(plugin_dir_path(__FILE__) . 'partials/wcfm-confirm-handover.php');
	}

	public function enqueue_scripts($order_id = null)
	{

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/t4e-pg-trustap-public.js', array('jquery'), $this->version, true);

		if ($order_id) {
			$localized_data = array(
				'confirm_handover_url' => get_rest_url(null, 't4e-pg-trustap/v1/confirm-handover'),
				'nonce' => wp_create_nonce('wp_rest'),
				'order_id' => $order_id
			);
			wp_localize_script($this->plugin_name, 't4e_pg_trustap_public_data', $localized_data);
		}

	}

}
