<?php
/**
 * Iubenda WooCommerce Form Consent Handler.
 *
 * Handles WooCommerce Form Consent.
 *
 * @package Iubenda
 */

/**
 * WooCommerce_Form_Consent class.
 *
 * This class handles the extraction and processing of WooCommerce order data for
 * form consents, specifically focusing on orders processed through the WooCommerce
 * Block Checkout.
 */
class WooCommerce_Form_Consent {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'woocommerce_order_processed' ) );
	}

	/**
	 * Handles the WooCommerce order processed event.
	 *
	 * This function is triggered when an order is processed via the WooCommerce Store API.
	 * It ensures the order object is valid, checks if it was processed by WooCommerce Block Checkout,
	 * and then processes the order data according to specified forms.
	 *
	 * @param   \WC_Order $order  WooCommerce Order object.
	 *
	 * @return void
	 */
	public function woocommerce_order_processed( $order ) {
		// Ensure the order object is loaded.
		if ( ! class_exists( '\WC_Order' ) || ! $order instanceof \WC_Order ) {
			return;
		}

		// Check if the order was processed by WooCommerce Block Checkout.
		if ( ! $this->is_block_checkout() ) {
			return;
		}

		// Define form arguments for fetching forms.
		$form_args = array(
			'post_status' => array( 'mapped' ),
			'form_source' => 'woocommerce',
		);

		// Retrieve forms based on specified arguments.
		$forms = iubenda()->forms->get_forms( $form_args );

		// If no forms are found, exit the function.
		if ( empty( $forms ) ) {
			return;
		}

		// Define include, exclude, and preferences fields based on the first form's properties.
		$include_fields     = $forms[0]->form_subject;
		$exclude_fields     = $forms[0]->form_exclude;
		$preferences_fields = $forms[0]->form_preferences;

		// Extract order data based on the specified include, exclude, and preferences fields.
		$order_data = $this->extract_woocommerce_order_fields( $order, $include_fields, $exclude_fields, $preferences_fields );

		// Prepare data for the API request.
		$data = array(
			'subject'     => $order_data['final_data'],
			'preferences' => $order_data['preferences_data'],
			'proofs'      => array(
				array(
					'content' => wp_json_encode( $order_data['full_data'] ),
				),
			),
		);

		// Send data to the specified endpoint via a POST request.
		wp_remote_post(
			iubenda()->options['cons']['cons_endpoint'],
			array(
				'body'    => wp_json_encode( $data ),
				'headers' => array(
					'apikey'       => iubenda()->options['cons']['public_api_key'],
					'Content-Type' => 'application/json',
				),
			)
		);
	}

	/**
	 * Extract order fields based on include, exclude, and preferences fields.
	 *
	 * This function extracts relevant order data fields based on specified inclusion and exclusion
	 * lists, as well as additional preferences fields.
	 *
	 * @param   \WC_Order $order               WooCommerce Order object.
	 * @param   array     $include_fields      Fields to include in the final data.
	 * @param   array     $exclude_fields      Fields to exclude from the final data.
	 * @param   array     $preferences_fields  Fields to include in preferences data.
	 *
	 * @return array Extracted order data with final, full, and preferences data.
	 */
	private function extract_woocommerce_order_fields( $order, $include_fields, $exclude_fields, $preferences_fields ) {
		// Default fields to extract.
		$default_fields = array(
			'billing_first_name' => $order->get_billing_first_name(),
			'billing_last_name'  => $order->get_billing_last_name(),
			'billing_company'    => $order->get_billing_company(),
			'billing_address_1'  => $order->get_billing_address_1(),
			'billing_address_2'  => $order->get_billing_address_2(),
			'billing_city'       => $order->get_billing_city(),
			'billing_postcode'   => $order->get_billing_postcode(),
			'billing_phone'      => $order->get_billing_phone(),
			'billing_email'      => $order->get_billing_email(),
		);

		// If coupon codes are applied, they are typically stored in order items.
		$coupons = $order->get_coupon_codes();
		if ( ! empty( $coupons ) ) {
			$default_fields['coupon_code'] = implode( ',', $coupons );
		}

		// Filter fields to include only specified ones.
		$included_data = array();
		foreach ( $include_fields as $field ) {
			if ( isset( $default_fields[ $field ] ) ) {
				$included_data[ $field ] = $default_fields[ $field ];
			}
		}

		// Remove excluded fields.
		foreach ( $exclude_fields as $field ) {
			if ( isset( $included_data[ $field ] ) ) {
				unset( $included_data[ $field ] );
			}
		}

		// Remove 'billing_' prefix from keys and apply preferences.
		$final_data = array();
		foreach ( $included_data as $key => $value ) {
			$new_key                = preg_replace( '/^billing_/', '', $key );
			$final_data[ $new_key ] = $value;
		}

		// Apply preferences fields.
		$preferences_data = array();
		foreach ( $preferences_fields as $pref_key => $pref_field ) {
			if ( isset( $default_fields[ $pref_field ] ) ) {
				$preferences_data[ $pref_key ] = $default_fields[ $pref_field ];
			}
		}

		return array(
			'final_data'       => $final_data,
			'full_data'        => $default_fields,
			'preferences_data' => $preferences_data,
		);
	}

	/**
	 * Check if the request is from WooCommerce Block Checkout.
	 *
	 * This function checks if the order was created via the WooCommerce Block Checkout.
	 *
	 * @return bool True if the request is from WooCommerce Block Checkout, false otherwise.
	 */
	private function is_block_checkout() {
		// Check if the necessary function and methods exist.
		if (
			! function_exists( 'wc_get_page_id' ) ||
			! class_exists( 'WC_Blocks_Utils' ) ||
			! method_exists( 'WC_Blocks_Utils', 'has_block_in_page' )
		) {
			return false;
		}

		// Check if the checkout page contains the WooCommerce checkout block.
		return WC_Blocks_Utils::has_block_in_page( wc_get_page_id( 'checkout' ), 'woocommerce/checkout' );
	}
}
