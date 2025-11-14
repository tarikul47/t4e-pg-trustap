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
	private $trustap_service;

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

		$gateways = WC()->payment_gateways->payment_gateways();
		if (isset($gateways['trustap'])) {
			$this->controller = new AbstractController('trustap/v1');
			$this->trustap_service = new Service_Override($gateways['trustap'], $this->controller);
		}

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

	public function save_trustap_transaction_details_on_payment_complete($order_id)
		{
			$logger = wc_get_logger();
			$context = ['source' => 't4e-pg-trustap-save-details'];
			$logger->info('Attempting to save Trustap transaction details for Order ID: ' . $order_id, $context);
	
			$order = wc_get_order($order_id);
			if (!$order) {
				$logger->error('Order not found for ID: ' . $order_id, $context);
				return;
			}
	
			$payment_method = $order->get_payment_method();
			$logger->info('Order ' . $order_id . ' payment method: ' . $payment_method, $context);
	
			if ($payment_method !== 'trustap') {
				$logger->info('Order ' . $order_id . ' is not a Trustap payment. Skipping.', $context);
				return;
			}
	
			$transaction_id = $order->get_meta('trustap_transaction_ID');
			if (empty($transaction_id)) {
				$logger->error('Order ' . $order_id . ' has no trustap_transaction_ID.', $context);
				return;
			}
			$logger->info('Order ' . $order_id . ' - trustap_transaction_ID: ' . $transaction_id, $context);
	
			$model = $order->get_meta('model');
			$type = (strpos($model, 'p2p') !== false) ? 'p2p' : '';
			$logger->info('Order ' . $order_id . ' - Model: ' . $model . ' | Type: ' . $type, $context);
	
			if ($this->trustap_service) {
				$transaction_details = $this->trustap_service->get_transaction($type, $transaction_id);
				$logger->info('Order ' . $order_id . ' - API Response from get_transaction: ' . print_r($transaction_details, true), $context);
	
				if ($transaction_details && !is_wp_error($transaction_details)) {
					$order->update_meta_data('_trustap_transaction_details', $transaction_details);
					$order->save();
					$logger->info('Order ' . $order_id . ' - Trustap transaction details successfully saved to order meta.', $context);
				} else {
					$logger->error('Order ' . $order_id . ' - Failed to get valid Trustap transaction details from API.', $context);
				}
			} else {
				$logger->error('Order ' . $order_id . ' - Trustap service not initialized.', $context);
			}
		}
	public function display_trustap_transaction_details($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'trustap') {
            return;
        }

        $transaction_details = $order->get_meta('_trustap_transaction_details');

        if (empty($transaction_details)) {
            return;
        }
        
        ?>
        <div class="wcfm-clearfix"></div>
        <br />
        <div class="wcfm-container">
            <div class="wcfm-content">
                <h2><?php _e('Trustap Transaction Details', 't4e-pg-trustap'); ?></h2>
                <?php
                if ($transaction_details && isset($transaction_details['status'])) {

					$deposit_pricing = isset($transaction_details['deposit_pricing']) ? $transaction_details['deposit_pricing'] : [];
					
					$amount_paid = 'N/A';
					if (isset($transaction_details['purchase_price'])) {
						$amount_paid = wc_price($transaction_details['purchase_price'] / 100);
					} elseif (isset($deposit_pricing['price'])) {
						$amount_paid = wc_price($deposit_pricing['price'] / 100);
					}
					
					$buyer_fees = isset($transaction_details['buyer_fee']) ? wc_price($transaction_details['buyer_fee'] / 100) : 'N/A';
					
					$seller_fees = 'N/A';
					if (isset($transaction_details['seller_fee'])) {
						$seller_fees = wc_price($transaction_details['seller_fee'] / 100);
					} elseif (isset($deposit_pricing['charge_seller'])) {
						$seller_fees = wc_price($deposit_pricing['charge_seller'] / 100);
					}
					
					$international_payment_fee = 'N/A';
					if (isset($transaction_details['international_payment_fee'])) {
						$international_payment_fee = wc_price($transaction_details['international_payment_fee'] / 100);
					} elseif (isset($deposit_pricing['charge_international_payment'])) {
						$international_payment_fee = wc_price($deposit_pricing['charge_international_payment'] / 100);
					}
					
					$expected_payout = isset($transaction_details['payout_amount']) ? wc_price($transaction_details['payout_amount'] / 100) : 'N/A';
					$status = isset($transaction_details['status']) ? esc_html(ucfirst(str_replace('_', ' ', $transaction_details['status']))) : 'N/A';
					$funds_released = isset($transaction_details['release_amount']) ? wc_price($transaction_details['release_amount'] / 100) : 'N/A';

                    if ($status === 'Funds Released') {
                        echo '<p><strong>' . __('Transaction Complete!', 't4e-pg-trustap') . '</strong> ' . __('We have released the funds. Depending on your bank, they should be available in 5-7 working days.', 't4e-pg-trustap') . '</p>';
                    }
                    ?>
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
                    <?php
                } else {
                    echo '<p>' . __('Could not retrieve transaction details from Trustap.', 't4e-pg-trustap') . '</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }
}