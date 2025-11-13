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
	public function __construct($plugin_name, $version, $oauth_handler, $trustap_api)
	{

		parent::__construct($plugin_name, $version, $trustap_api);
		$this->oauth_handler = $oauth_handler;


		// WCFM handover confirmed button show 
		add_action('wcfm_order_details_after_order_table', array($this, 'wcfm_show_handover_button'), 10, 1);

	}

	public function wcfmmp_custom_pg_vendor_setting($vendor_billing_fields, $vendor_id)
	{
		$gateway_slug = WCFMTrustap_GATEWAY;

		$vendor_data = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
		$vendor_data = $vendor_data ? $vendor_data : [];
		$settings = array();

		// $trustap_settings = get_option('woocommerce_trustap_settings', array());
		// $test_mode = (isset($trustap_settings['testmode']) && $trustap_settings['testmode'] === 'yes') ? true : false;
		// $environment = $test_mode ? 'test' : 'live';

		$client_id = get_option("trustap_{$this->trustap_api->environment}_client_id");

		$trustap_user_id = get_user_meta($vendor_id, "trustap_{$this->trustap_api->environment}_user_id", true);

		//var_dump($trustap_user_id);

		//delete_user_meta($vendor_id, 'trustap_user_id');

		//var_dump($trustap_user_id);

		//$trustap_user_id = '';

		$is_test_mode = ($this->trustap_api->environment === 'test');
		$base_url = $is_test_mode ? 'https://app.stage.trustap.com' : 'https://app.trustap.com';
		$trustap_profile_link = "{$base_url}/profile/payout/personal?edit=true&client_id={$client_id}";

		if ($trustap_user_id) {
			$disconnect_url = admin_url('admin-ajax.php?action=wcfm_trustap_disconnect');
			$vendor_billing_fields += array(
				$gateway_slug . '_connection' => array(
					'label' => __('Connect Trustap account', 'wc-multivendor-marketplace'),
					'type' => 'html',
					'name' => 'payment[' . $gateway_slug . '][nationality]',
					'class' => 'wcfm-select wcfm_ele paymode_field paymode_' . $gateway_slug,
					'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_' . $gateway_slug,
					'value' => '<h5>You have connected successfully!</h5><p>Please completed your profile before withdrwa your earnings - <a target="_blank" href="' . $trustap_profile_link . '">Click Here</a></p><a href="' . esc_url($disconnect_url) . '" class="button">Disconnect</a>',
					'custom_attributes' => array(
						'required' => 'required'
					),
					'hints' => '',
					'in_table' => 'yes'
				),
			);
		} else {
			if (!session_id()) {
				session_start();
			}

			// Default redirect
			$redirect_url = home_url($_SERVER['REQUEST_URI']) . '#wcfm_settings_form_payment_head';

			// Check if current URL is from store setup wizard
			$current_url = home_url(add_query_arg(null, null));
			if (isset($_GET['store-setup']) && $_GET['store-setup'] === 'yes' && isset($_GET['step']) && $_GET['step'] === 'payment') {
				$redirect_url = $current_url;
			}

			$_SESSION['trustap_redirect_url'] = $redirect_url;

			// $_SESSION['trustap_redirect_url'] = home_url($_SERVER['REQUEST_URI']) . '#wcfm_settings_form_payment_head';

			$vendor_billing_fields += array(
				$gateway_slug . '_connection' => array(
					'label' => __('Connect Trustap account', 'wc-multivendor-marketplace'),
					'type' => 'html',
					'name' => 'payment[' . $gateway_slug . '][nationality]',
					'class' => 'wcfm-select wcfm_ele paymode_field paymode_' . $gateway_slug,
					'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_' . $gateway_slug,
					// 'value' => $settings['nationality'],
					'value' => '<a href="' . esc_url($this->oauth_handler->get_trustap_auth_url()) . '" class="button">Connect</a>',
					'custom_attributes' => array(
						'required' => 'required'
					),
					'hints' => '<p>To receive oayouts, you must connect your Trustap account.</p>',
					'in_table' => 'yes'
				),
			);
		}



		//TODO: condion wise need to show Connected button with logout button 
		//TODO: Need to show loading bar and sucsss message
		//TODO: profile link 

		return $vendor_billing_fields;
	}

	public function t4e_wcfm_main_contentainer_before()
	{
		$current_user_id = get_current_user_id();

		if (!$current_user_id) {
			return;
		}

		// Get Trustap ID
		$trustap_user_id = get_user_meta($current_user_id, "trustap_{$this->trustap_api->environment}_user_id", true);

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

	public function wcfm_show_handover_button($order)
	{
		if (!$order || !$order->has_status('handoverpending')) {
			return;
		}

		include_once(plugin_dir_path(__FILE__) . 'partials/wcfm-confirm-handover.php');
	}

	public function enqueue_styles()
	{
		// Enqueue public-facing styles here.
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/t4e-pg-trustap-public.css', array(), $this->version, 'all');
	}

	public function enqueue_scripts()
	{
		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/t4e-pg-trustap-public.js', array('jquery'), $this->version, true);

		$localized_data = array(
			'confirm_handover_url' => get_rest_url(null, 't4e-pg-trustap/v1/confirm-handover'),
			'nonce' => wp_create_nonce('wp_rest'),
		);
		wp_localize_script($this->plugin_name, 't4e_pg_trustap_public_data', $localized_data);
	}

	public function display_trustap_transaction_details($order)
    {
        if (is_a($order, 'WC_Order') && $order->get_payment_method() === 'trustap') {
            $transaction_id = $order->get_meta('trustap_transaction_ID');
            if (!$transaction_id) {
                return;
            }

            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            if (!isset($gateways['trustap']) || !property_exists($gateways['trustap'], 'my_custom_service')) {
                return;
            }

            $trustap_gateway = $gateways['trustap'];
            $service_override = $trustap_gateway->my_custom_service;

            $model = $order->get_meta('model');
            $type = (strpos($model, 'p2p') !== false) ? 'p2p' : '';

            $transaction_details = $service_override->get_transaction($type, $transaction_id);
            
            // For debugging, you can uncomment the next line to see the transaction data
            // echo '<pre>'; var_dump($transaction_details); echo '</pre>';

            if ($transaction_details && !is_wp_error($transaction_details)) {

                // Assuming field names based on user request. These may need to be adjusted based on the actual API response.
                $amount_paid = isset($transaction_details['purchase_price']) ? wc_price($transaction_details['purchase_price'] / 100) : 'N/A';
                $buyer_fees = isset($transaction_details['buyer_fee']) ? wc_price($transaction_details['buyer_fee'] / 100) : 'N/A';
                $seller_fees = isset($transaction_details['seller_fee']) ? wc_price($transaction_details['seller_fee'] / 100) : 'N/A';
                $expected_payout = isset($transaction_details['payout_amount']) ? wc_price($transaction_details['payout_amount'] / 100) : 'N/A';
                $status = isset($transaction_details['status']) ? esc_html(ucfirst(str_replace('_', ' ', $transaction_details['status']))) : 'N/A';
                $funds_released = isset($transaction_details['release_amount']) ? wc_price($transaction_details['release_amount'] / 100) : 'N/A';
                $international_payment_fee = isset($transaction_details['international_payment_fee']) ? wc_price($transaction_details['international_payment_fee'] / 100) : 'N/A'; // This is a guess

                ?>
                <div class="wcfm-clearfix"></div>
                <br />
                <div class="wcfm-container">
                    <div class="wcfm-content">
                        <h2><?php _e('Trustap Transaction Details', 't4e-pg-trustap'); ?></h2>
                        
                        <?php if ($status === 'Funds Released') : ?>
                            <p><strong><?php _e('Transaction Complete!', 't4e-pg-trustap'); ?></strong> <?php _e('We have released the funds. Depending on your bank, they should be available in 5-7 working days.', 't4e-pg-trustap'); ?></p>
                        <?php endif; ?>

                        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                            <tbody>
                                <tr>
                                    <th scope="row"><?php _e('Amount Paid:', 't4e-pg-trustap'); ?></th>
                                    <td><?php echo $amount_paid; ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Buyer Fees:', 't4e-pg-trustap'); ?></th>
                                    <td><?php echo $buyer_fees; ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Seller Fees:', 't4e-pg-trustap'); ?></th>
                                    <td><?php echo $seller_fees; ?></td>
                                </tr>
                                 <tr>
                                    <th scope="row"><?php _e('International Payment Fee:', 't4e-pg-trustap'); ?></th>
                                    <td><?php echo $international_payment_fee; ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Expected Payout:', 't4e-pg-trustap'); ?></th>
                                    <td><?php echo $expected_payout; ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Funds Released:', 't4e-pg-trustap'); ?></th>
                                    <td><?php echo $funds_released; ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Status:', 't4e-pg-trustap'); ?></th>
                                    <td><?php echo $status; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php
            }
        }
    }
}
