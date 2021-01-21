<?php
/**
 * WooCommerce Sendy Subscriptions
 *
 * @package  FWS_Woo_Sendy_Subscription_Integration
 * @category Integration
 * @author   Olaf Lederer
 */



class FWS_Woo_Moosend_Integration extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {

		$this->id = 'fws-woo-moosend';
		$this->method_title = __( 'Moosend Subscription', 'fws-woo-moosend' );
		$this->method_description = __( 'Add buyers to your Moosend Mailing list', 'fws-woo-moosend' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->moosend_api = $this->get_option( 'moosend_api' );
		$this->moosend_list = $this->get_option( 'moosend_list' );
		$this->moosend_first_name = $this->get_option( 'moosend_first_name' );
		$this->moosend_webshop = $this->get_option( 'moosend_webshop' );

		// Actions.
		add_action( 'woocommerce_update_options_integration_'.$this->id, array( $this, 'process_admin_options' ) );
  	add_action( 'woocommerce_checkout_order_processed', array( $this, 'add_to_moosend_callback' ) );

	}

	public function init_form_fields() {
		$this->form_fields = array(
			'moosend_api' => array(
				'title'             => __( 'Moosend API key', 'fws-woo-moosend' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'Your Moosend API key', 'fws-woo-moosend' ),
			),
			'moosend_list' => array(
				'title'             => __( 'Moosend mailing list ID', 'fws-woo-moosend' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'The hashed ID of your Moosend mailing list', 'fws-woo-moosend' ),
			),
			'moosend_first_name' => array(
				'title'             => __( 'Custom field ID "first name"', 'fws-woo-moosend' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => false,
				'description'       => __( 'Enter here the field ID for the first name. Keep the field empty to disable this option.', 'fws-woo-moosend' ),
			),
			'moosend_webshop' => array(
				'title'             => __( 'Optional name for Custom field "Webshop"', 'fws-woo-moosend' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => false,
				'description'       => __( 'First create a custom field with the name "Webshop" in Moosend. Than enter here the webshop name. This is useful if you use the same mailing list for multiple shops. Keep the field empty to disable this option.', 'fws-woo-moosend' ),
			),
			'moosend_subscribe_text' => array(
				'title'             => __( 'Text for newsletter subscription', 'fws-woo-moosend' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'The text for the subscription checkbox on your checkout page.', 'fws-woo-moosend' ),
			)
		);
	}

	public function add_to_moosend_callback( $order_id) {
		$order = new WC_Order($order_id);
		$subscribed = get_post_meta($order_id, 'moosend_subscribed', true);
		if ($this->moosend_list != '') {
			$result = $this->create_moosend_request($order, $subscribed);
			if (isset($result->Code) && $result->Code == 0) {
				if (empty($result->Context->UpdatedOn)) {
					$order->add_order_note( $order->get_billing_email().' added to the mailing list');
				} else {
					$order->add_order_note('Customer '. $order->get_billing_email().' was already subscribed to the mailing list');
				}
			} else {
				$order->add_order_note('Failed to add '. $order->get_billing_email().' to the mailing list');
			}
		}
	}

	public function create_moosend_request($order, $subscribed) {
		$url = 'https://api.moosend.com/v3/subscribers/'.$this->moosend_list.'/subscribe.json?apikey='.$this->moosend_api;
		if ($this->moosend_first_name != '') {
			$post_array = array(
				'Name' => $order->get_billing_last_name(),
				'CustomFields' => array(
					$this->moosend_first_name.'='.$order->get_billing_first_name()
				)
			);
		} else {
			$post_array = array(
				'Name' => $order->get_billing_first_name().' '.$order->get_billing_last_name()
			);
		}
		if ($this->moosend_webshop != '') {
			$post_array = array(
				'CustomFields' => array(
					'Webshop='.$this->moosend_webshop
				)
			);
		}
		$post_array['Email'] = $order->get_billing_email();
		if ($subscribed) $post_array['HasExternalDoubleOptIn'] = 'true';
		error_log(json_encode($post_array, JSON_PRETTY_PRINT));
	 	$response = wp_remote_post(
			$url,
			array(
				'headers' => array('Content-Type' => 'application/json', 'accept' => 'application/json'),
				'body' => json_encode($post_array, JSON_PRETTY_PRINT),
			)
		);
		$result = json_decode($response['body']);
		error_log(print_r($response['body'], true));
		return $result;
	}

	public function get_client_ip() {
		foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
			if (array_key_exists($key, $_SERVER) === true){
				foreach (explode(',', $_SERVER[$key]) as $ip){
					$ip = trim($ip); // just to be safe

					if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
						return $ip;
					}
				}
			}
		}
	}


	public function sanitize_settings( $settings ) {
		return $settings;
	}
}
