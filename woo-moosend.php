<?php
/*
Plugin Name: WooCommere to Moosend
Author: Olaf Lederer
Description: Add customers to your mailing list in Moosend
Version: 1.0


*/
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
if ( ! class_exists( 'FWS_Woo_Moosend' ) ) :

class FWS_Woo_Moosend {

	/**
	* Construct the plugin.
	*/
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	* Initialize the plugin.
	*/
	public function init() {

		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) ) {
			// Include our integration class.
			include_once WP_PLUGIN_DIR . '/woo-moosend/includes/class-woo-moosend-integration.php';

			// Register the integration.
			add_filter( 'woocommerce_integrations', array( $this, 'fws_add_integration' ) );
			add_action('woocommerce_checkout_after_terms_and_conditions', array( $this, 'fws_subscribe_checkbox_field'));
			add_action('woocommerce_checkout_update_order_meta', array( $this, 'fws_checkout_order_meta'));

		} else {
			// throw an admin error if you like
		}
	}

	/**
	 * Add a new integration to WooCommerce.
	 */
	public function fws_add_integration( $integrations ) {
		$integrations[] = 'FWS_Woo_Moosend_Integration';
		return $integrations;
	}

	public function fws_subscribe_checkbox_field(  ) {
		$settings = get_option('woocommerce_fws-woo-moosend_settings');
		$label = (!empty($settings['moosend_subscribe_text'])) ? $settings['moosend_subscribe_text'] : __( 'Subscribe newsletter', 'fws-woo-moosend' );
		echo '<div class="fws_custom_class">';
		woocommerce_form_field( 'fws_moosend_checkbox', array(
			'type'          => 'checkbox',
			'label'         => $label,
			'required'  => false
		), null);
		echo '</div>';
	}

	function fws_checkout_order_meta( $order_id ) {
		if (!empty($_POST['fws_moosend_checkbox'])) update_post_meta( $order_id, 'moosend_subscribed', esc_attr($_POST['fws_moosend_checkbox']));
	}

}

$FWS_Woo_Moosend = new FWS_Woo_Moosend();

endif;
