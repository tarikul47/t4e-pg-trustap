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

    public function sync_trustap_order_status($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order || $order->get_payment_method() !== 'trustap') {
            return;
        }

        $transaction_id = $order->get_meta('trustap_transaction_ID');
        if (empty($transaction_id)) {
            return;
        }

        $model = $order->get_meta('model');
        $type = (strpos($model, 'p2p') !== false) ? 'p2p' : '';
        $prefix = ($type === 'p2p') ? 'p2p' : '';

        try {
            $response = $this->trustap_api->get_request("{$prefix}/transactions/{$transaction_id}");
        } catch (Exception $error) {
            // Handle error if needed
            return;
        }

        if (is_wp_error($response) || !isset($response['body'])) {
            return;
        }

        $transaction_details = json_decode($response['body'], true);

        if (empty($transaction_details) || !isset($transaction_details['status'])) {
            return;
        }

        $trustap_status = $transaction_details['status'];
        $current_status = $order->get_status();

        // Remove "wc-" prefix from woocommerce status if present
        $current_status = str_replace('wc-', '', $current_status);


        // Basic status mapping, can be expanded
        $status_mapping = array(
            'created' => 'pending',
            'in_progress' => 'processing',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'complained' => 'on-hold',
            'deposit_paid' => 'processing',
            'buyer_handover_confirmed' => 'handoverconfirmed',
            'seller_handover_confirmed' => 'handoverconfirmed',
            'deposit_refunded' => 'refunded',
        );

        // a list of all available statutes can be found here: https://docs.trustap.com/docs/api-webhooks

        $new_status = isset($status_mapping[$trustap_status]) ? $status_mapping[$trustap_status] : null;

        if ($new_status && $new_status !== $current_status) {
            $order->update_status($new_status, __('Trustap status automatically updated.', 't4e-pg-trustap'));
        }
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

    public function display_trustap_transaction_details($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order || $order->get_payment_method() !== 'trustap') {
            return;
        }

        $transaction_details = $order->get_meta('_trustap_transaction_details');

        if (empty($transaction_details) || !isset($transaction_details['status'])) {
            return;
        }

        $deposit_pricing = isset($transaction_details['deposit_pricing']) ? $transaction_details['deposit_pricing'] : [];

        // Helper function to generate rows consistently
        $generate_row = function ($label, $value) {
            if ($value === 'N/A') {
                return; // Don't show rows with no value
            }
            ?>
            <tr>
                <th class="label" colspan="2" style="text-align:right;"><?php echo esc_html($label); ?>:</th>
                <td class="total" style="text-align:center; min-width: 225px;">
                    <div class="view">
                        <?php echo wp_kses_post($value); ?>
                    </div>
                </td>
            </tr>
            <?php
        };

        // --- Calculations ---
        $amount_paid = 'N/A';
        if (isset($deposit_pricing['price']) && isset($deposit_pricing['charge'])) {
            $amount_paid = wc_price(($deposit_pricing['price'] + $deposit_pricing['charge']) / 100);
        }

        $international_payment_fee = 'N/A';
        if (isset($deposit_pricing['charge_international_payment']) && $deposit_pricing['charge_international_payment'] > 0) {
            $international_payment_fee = wc_price($deposit_pricing['charge_international_payment'] / 100);
        }

        $service_fees = 'N/A';
        if (isset($deposit_pricing['charge_seller'])) {
            $service_fees = wc_price(($deposit_pricing['charge_seller']) / 100);
        }

        $seller_fees = 'N/A';
        if (isset($deposit_pricing['charge_seller']) && isset($deposit_pricing['charge_international_payment'])) {
            $seller_fees = wc_price(($deposit_pricing['charge_seller'] + $deposit_pricing['charge_international_payment']) / 100);
        }

        $expected_payout = 'N/A';
        if (isset($deposit_pricing['price']) && isset($deposit_pricing['charge_seller']) && isset($deposit_pricing['charge_international_payment'])) {
            $expected_payout = wc_price(($deposit_pricing['price'] - $deposit_pricing['charge_seller'] - $deposit_pricing['charge_international_payment']) / 100);
        }

        // Get raw key from API
        $raw_status = $transaction_details['status'] ?? 'N/A';

        // Status mapping
        $map = [
            'remainder_skipped' => 'Payment deposit',
            'seller_handover_confirmed' => 'Handover confirmed by Seller',
            'buyer_handover_confirmed' => 'Handover confirmed by Buyer',
        ];

        if (isset($map[$raw_status])) {
            $status = $map[$raw_status];
        } else {
            $status = ucfirst(str_replace('_', ' ', $raw_status));
        }

        $status = esc_html($status);

        // Build Trustap URL
        $trustap_transaction_ID = $order->get_meta('trustap_transaction_ID');
        $is_test_mode = ($this->trustap_api->environment === 'test');
        $base_trustap_url = $is_test_mode ? 'https://app.stage.trustap.com' : 'https://app.trustap.com';

        $status_link_html = '';
        $extra_message = '';
        $extra_button = '';

        // Vendor-only: show Trustap link
        /*
        if (!empty($trustap_transaction_ID) && wcfm_is_vendor()) {

            $trustap_transaction_url = "{$base_trustap_url}/transactions/{$trustap_transaction_ID}";

            $status_link_html = '<div>
            <a href="' . esc_url($trustap_transaction_url) . '" target="_blank"
               style="text-decoration:none; padding:3px 8px; border:1px solid #ccc; border-radius:3px; background:#f0f0f0; color:#333; font-size:0.85em;">
               ' . __('View', 't4e-pg-trustap') . '
            </a>
        </div>';
        }
        */

        // Show extra message for final handover status
        if (wcfm_is_vendor() && in_array($raw_status, ['seller_handover_confirmed', 'buyer_handover_confirmed'], true)) {

            $extra_message = __(
                'After 24 hours, your payment will be released automatically. You can also view your payout status in your Trustap dashboard. If the payment does not appear, please ensure your Trustap profile is fully updated.',
                't4e-pg-trustap'
            );

            $trustap_transaction_url = "{$base_trustap_url}/transactions/{$trustap_transaction_ID}";

            // Vendor-only dashboard button
            if (!empty($trustap_transaction_ID)) {
                $extra_button = '<div style="margin-top:6px;">
                <a href="' . esc_url($trustap_transaction_url) . '" target="_blank"
                   style="color: #ffffff !important;display:inline-block; padding:6px 12px; border-radius:4px; background:#007cba; color:#fff; font-size:0.85em; text-decoration:none;">
                   ' . __('Check in Trustap Dashboard', 't4e-pg-trustap') . '
                </a>
            </div>';
            }
        }

        // Build final Status HTML
        $status_display = '<div style="margin-bottom:4px;">' . $status . '</div>';

        if (!empty($status_link_html)) {
            $status_display .= $status_link_html;
        }

        if (!empty($extra_message)) {
            $status_display .= '<div style="font-size:0.85em; color:#555; margin-top:6px; text-align:left; padding: 5px 0px 0px 5px;">'
                . esc_html($extra_message)
                . '</div>';
        }

        if (!empty($extra_button)) {
            $status_display .= $extra_button;
        }

        // --- Display Rows ---
        ?>
        <tr>
            <th class="label" colspan="3" style="text-align:left; background-color:#f8f8f8; border-top:1px solid #eee;">
                <strong><?php _e('Trustap Details', 't4e-pg-trustap'); ?></strong>
            </th>
        </tr>
        <?php

        $generate_row(__('Amount Paid by Client', 't4e-pg-trustap'), $amount_paid);
        $generate_row(__('Withdraw Fees for International Payment', 't4e-pg-trustap'), $international_payment_fee);
        $generate_row(__('Seller Total Fees', 't4e-pg-trustap'), $seller_fees);
        $generate_row(__('Expected Payout', 't4e-pg-trustap'), $expected_payout);
        $generate_row(__('Status', 't4e-pg-trustap'), $status_display);
    }

}