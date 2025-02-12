<?php
/**
 * Handles the integration with the WordPress Consent API within the Iubenda context.
 *
 * This class manages the loading and configuration of JavaScript for consent management
 * based on user settings and compliance requirements.
 *
 * @package Iubenda
 */

/**
 * Class Wp_Consent_Api_Integration.
 */
class Wp_Consent_Api_Integration {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		if ( ! $this->is_wp_consent_api_installed() || ! $this->is_wp_consent_api_integrate_enabled() ) {
			return;
		}

		add_filter( 'wp_consent_api_registered_' . IUBENDA_PLUGIN_BASENAME, '__return_true' );
		add_filter( 'wp_get_consent_type', array( $this, 'get_consent_type' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_functional_consent_script' ), PHP_INT_MAX );
	}

	/**
	 * Enqueues the WP Consent API script and adds inline JavaScript to allow functional consent by default.
	 *
	 * This function enqueues the WP Consent API script and configures it to set the consent type 'functional' to 'allow' by default.
	 */
	public function enqueue_functional_consent_script() {
		wp_add_inline_script( 'wp-consent-api', "wp_set_consent('functional', 'allow');" );
	}

	/**
	 * Checks if the integration with the WP Consent API is enabled in the CS options.
	 *
	 * @return bool True if integration is enabled, false otherwise.
	 */
	public function is_wp_consent_api_integrate_enabled() {
		return (bool) iubenda()->options['cs']['integrate_with_wp_consent_api'];
	}

	/**
	 * Enqueues the integration script for the WP Consent API.
	 *
	 * @return void
	 */
	public function enqueue_integration_script() {
		wp_enqueue_script( 'iubenda-wp-consent-api-integration', sprintf( '%s/assets/js/wp-consent-api-integration.js', IUBENDA_PLUGIN_URL ), array(), iubenda()->version, true );
	}

	/**
	 * Check if the WordPress Consent API is installed and available.
	 *
	 * @return bool True if both the wp_has_consent function exists and WP_CONSENT_API class is available, false otherwise.
	 */
	public function is_wp_consent_api_installed() {
		return function_exists( 'wp_has_consent' ) && class_exists( 'WP_CONSENT_API' );
	}

	/**
	 * Get the consent type.
	 *
	 * @return string The consent type, either 'optin' or 'optout'.
	 */
	public function get_consent_type() {
		return 'optin';
	}
}
