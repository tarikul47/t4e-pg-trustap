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

	public function t4e_wcfm_main_contentainer_before()
	{
		$current_user_id = get_current_user_id();

		if (!$current_user_id) {
			return;
		}

		// Detect environment
		$environment = method_exists($this, 'trustap_api') && isset($this->trustap_api->environment)
			? $this->trustap_api->environment
			: 'live';

		// Get Trustap ID
		$trustap_user_id = get_user_meta($current_user_id, "trustap_{$environment}_user_id", true);

		// Show notice if not connected
		if (empty($trustap_user_id)) {
			?>
			<div class="trustap-warning-box">
				<div class="trustap-warning-icon">
					<span class="wcfmfa fa-exclamation-triangle"></span>
				</div>
				<div class="trustap-warning-content">
					<strong>Warning</strong><br>
					You haven’t connected your <strong>Trustap</strong> account yet. Please connect it to receive payouts.
					<br><br>
					<a target="_self" href="<?php echo esc_url(get_wcfm_settings_url() . '#wcfm_settings_form_payment_head'); ?>"
						class="wcfm-button button">
						Connect Now
					</a>
				</div>
			</div>

			<style>
				.trustap-warning-box {
					display: flex;
					align-items: center;
					background-color: #ffa726;
					/* orange background */
					color: #fff;
					border-left: 6px solid #e65100;
					/* darker orange border */
					padding: 16px 20px;
					margin: 20px 0;
					border-radius: 6px;
					font-size: 15px;
					gap: 10px;
				}

				.trustap-warning-icon {
					font-size: 28px;
					margin-right: 15px;
					color: #fff;
				}

				.trustap-warning-content a.button {
					background: #fff;
					color: #e65100;
					border: none;
					border-radius: 4px;
					padding: 6px 12px;
					font-weight: 600;
					text-decoration: none;
				}

				.trustap-warning-content a.button:hover {
					background: #f5f5f5;
					color: #bf360c;
				}

				.trustap-warning-content strong {
					line-height: 25px;
				}
			</style>

			<script>
				jQuery(document).ready(function ($) {

					// Handle clicks on both Add New buttons
					$(document).on('click', '#add_new_product_dashboard, .wcfm_sub_menu_items_product_manage a', function (e) {
						e.preventDefault();

						// Show toast if available (WCFM notification)
						if (typeof wcfm_notification_message === 'function') {
							wcfm_notification_message('warning', '⚠️ Please connect your Trustap account before adding a product.');
						} else {
							alert('⚠️ Please connect your Trustap account before adding a product.');
						}

						// Optional: Redirect after 2 seconds to Payment Settings
						setTimeout(function () {
							window.location.href = "<?php echo esc_url(get_wcfm_settings_url() . '#wcfm_settings_form_payment_head'); ?>";
						}, 2000);
					});
				});
			</script>
			<?php
		}
	}

	public function wcfm_show_handover_button($order_id)
	{
		$order = wc_get_order($order_id);
		if (!$order || !$order->has_status('handoverpending')) {
			return;
		}

		$this->enqueue_scripts($order_id);

		include_once(plugin_dir_path(__FILE__) . 'partials/wcfm-confirm-handover.php');
	}

	public function enqueue_styles()
	{
		// Enqueue public-facing styles here.
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/t4e-pg-trustap-public.css', array(), $this->version, 'all');
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
