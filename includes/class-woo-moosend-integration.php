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
		global $woocommerce;

		$this->id                 = 'fws-woo-moosend';
		$this->method_title       = __( 'Moosend Subscription', 'fws-woo-moosend' );
		$this->method_description = __( 'Add buyers to your Moosend Mailing list', 'fws-woo-moosend' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->moosend_api          = $this->get_option( 'moosend_api' );
		$this->moosend_list        = $this->get_option( 'moosend_list' );
		$this->moosend_first_name        = $this->get_option( 'moosend_first_name' );

		// Actions.
		add_action( 'woocommerce_update_options_integration_'.$this->id, array( $this, 'process_admin_options' ) );
        //add_action( 'woocommerce_order_status_processing', array( $this, 'add_to_sendy_mailer' ) );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'add_to_moosend_callback' ) );

	}


	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
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
				'title'             => __( 'Custom field for "first name"', 'fws-woo-moosend' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'Enter hier the field ID for the first. Keep the field empty to disabled this option.', 'fws-woo-moosend' ),
			),
			'moosend_subscribe_text' => array(
				'title'             => __( 'Text for newsletter subscription', 'fws-woo-moosend' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'The text for the subscription on the checkout page.', 'fws-woo-moosend' ),
			)
		);
	}

	public function add_to_moosend_callback( $order_id) {
		global $woocommerce;
		$order = new WC_Order($order_id);
		$checkout_url = $woocommerce->cart->get_checkout_url();
		$subscribed = get_post_meta($order_id, 'moosend_subscribed', true);
		if ($list != '') {
			$result = $this->create_moosend_request($order, $subscribed, $checkout_url);
			if ($result == 1) {
				$order->add_order_note( $order->billing_email.' added to the mailing list');
			} elseif ($result == 'Already subscribed.') {
				$order->add_order_note('Customer '. $order->billing_email.' was already subscribed to the mailing list');
			} else {
				$order->add_order_note('Failed to add '. $order->billing_email.' to the mailing list');
			}
		}
	}

	public function create_moosend_request($order, $subscribed, $checkout_url) {
		$url = 'https://api.moosend.com/v3';
		$list = $this->moosend_list;
		$api_key = $this->moosend_api;
		$qs_values = '';
		$endpoint = 'subscribers';
		if ($this->moosend_first_name != '') {
			$post_array = array(
				'Name' => $order->billing_last_name,
				$this->moosend_first_name => $order->billing_first_name
			);
		} else {
			$post_array = array(
			'Name' => $order->billing_first_name.' '.$order->billing_last_name
		);
		
			'Email' => $order->billing_email,
			'Ipaddress' => $this->get_client_ip(),
			'Referrer' => $checkout_url,
			'HasExternalDoubleOptIn' => 'true'
		);
		$ch = curl_init();
		$url .= '/'.$endpoint;
		if ($list != '') $url .= '/'.$list;
		if ($action != '') $url .= '/'.$action;
		$url .= '.json?apikey='.$api_key;
		if ($qs_values != '') $url .= $qs_values;

		curl_setopt($ch, CURLOPT_URL, $url);
		if (!empty($post_array)) {
			$postdata = http_build_query($post_array);
			curl_setopt($ch,CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-type: application/x-www-form-urlencoded'
		));
		$raw = curl_exec($ch);
		$result = json_decode($raw);
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
	/**
	 * Santize our settings
	 * @see process_admin_options()
	 */
	public function sanitize_settings( $settings ) {

		return $settings;
	}

}
