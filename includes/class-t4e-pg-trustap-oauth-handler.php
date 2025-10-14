<?php

class T4e_Pg_Trustap_OAuth_Handler {

    private $trustap_api;

    public function __construct($trustap_api) {
        $this->trustap_api = $trustap_api;
        add_action('wp_ajax_wcfm_trustap_oauth_callback', array($this, 'handle_oauth_callback_ajax'));
        add_action('wp_ajax_wcfm_trustap_disconnect', array($this, 'handle_disconnect_ajax'));
    }

    public function handle_disconnect_ajax() {
        if (!is_user_logged_in()) {
            wp_die('You must be logged in to perform this action.');
        }

        $user_id = get_current_user_id();

        delete_user_meta($user_id, "trustap_{$this->trustap_api->environment}_user_id");

        $redirect_url = get_wcfm_settings_url() . '#wcfm_settings_form_payment_head';

        wp_redirect($redirect_url);
        exit;
    }

    public function handle_oauth_callback_ajax() {
        $logger = wc_get_logger();
        $context = array('source' => 't4e-pg-trustap');

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

    public function get_trustap_auth_url() {
        return $this->trustap_api->get_auth_url();
    }
}
