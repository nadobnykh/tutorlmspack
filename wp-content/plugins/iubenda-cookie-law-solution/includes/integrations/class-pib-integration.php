<?php
/**
 * Class Pib_Integration
 *
 * Handles the integration with the Pib middleware, including setting options
 * and providing a REST API endpoint for injecting plugin options.
 *
 * @package Iubenda
 */

/**
 * Class Pib_Integration
 */
class Pib_Integration {

	/**
	 * Accepted options for products.
	 *
	 * @var array
	 */
	private $accepted_options = array(
		'activated_products' => array( 'terms', 'privacy_policy', 'cookie_solution', 'cookie_policy' ),
	);

	/**
	 * Supported languages for the plugin.
	 *
	 * @var array
	 */
	private $accepted_languages = array( 'bg', 'ca', 'cs', 'da', 'de', 'el', 'en-gb', 'en', 'es', 'et', 'fi', 'fr', 'hr', 'hu', 'it', 'lt', 'lv', 'nl', 'no', 'pl', 'pt', 'pt-br', 'ro', 'ru', 'sk', 'sl', 'sv' );

	/**
	 * Validated data from the API request.
	 *
	 * @var array
	 */
	private $validated_data;

	/**
	 * Default cookie solution options.
	 *
	 * @var array
	 */
	private $cs_default_options = array(
		'configuration_type' => 'manual',
		'parse'              => 1,
		'parser_engine'      => 'new',
	);

	/**
	 * Default privacy policy options.
	 *
	 * @var array
	 */
	private $pp_default_options = array(
		'button_style'    => 'black',
		'button_position' => 'automatic',
	);

	/**
	 * Default terms and conditions options.
	 *
	 * @var array
	 */
	private $tc_default_options = array(
		'button_style'    => 'black',
		'button_position' => 'automatic',
	);

	/**
	 * Error messages encountered during processing.
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * Language codes mapped to cookie policy IDs.
	 *
	 * @var array
	 */
	private $language_cookie_policy_map = array();

	/**
	 * Current website languages.
	 *
	 * @var array
	 */
	private $current_website_languages = array();

	/**
	 * Constructor to initialize plugin functionality.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'iubenda-cookie-law-solution/v1',
			'/inject-plugin-options',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'inject_plugin_options' ),
				'permission_callback' => array( $this, 'check_user_permissions' ),
			)
		);
	}

	/**
	 * Handle the plugin options injection API request.
	 *
	 * @param WP_REST_Request $request Request data.
	 * @return WP_REST_Response|WP_Error
	 */
	public function inject_plugin_options( $request ) {
		$request_data      = $request->get_params();
		$validation_result = $this->validate_request( $request_data );

		if ( ! empty( $validation_result['errors'] ) ) {
			return new WP_Error( 'validation_error', implode( ' ', $validation_result['errors'] ), array( 'status' => 400 ) );
		}

		$this->validated_data            = $validation_result['validated_data'];
		$this->current_website_languages = ( new Product_Helper() )->get_languages();
		$this->map_translated_languages_to_cookie_policies();
		$this->process_plugin_options();

		// Check if there were any errors during processing.
		if ( ! empty( $this->errors ) ) {
			return new WP_Error( 'processing_error', implode( ' ', $this->errors ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => 'Plugin options injected successfully.',
			)
		);
	}

	/**
	 * Validate and sanitize the API request data.
	 *
	 * @param array $data  request data.
	 * @return array An array with 'errors' and 'validated_data'.
	 */
	private function validate_request( $data ) {
		$errors         = array();
		$validated_data = array();

		// Validate and sanitize site_id.
		if ( isset( $data['site_id'] ) && is_numeric( $data['site_id'] ) && $data['site_id'] > 0 ) {
			$validated_data['site_id'] = (int) $data['site_id'];
		} else {
			$errors[] = 'site_id must be a positive numeric value.';
		}

		// Validate and sanitize cookie_policy_id.
		if ( isset( $data['cookie_policy_id'] ) && is_numeric( $data['cookie_policy_id'] ) && $data['cookie_policy_id'] > 0 ) {
			$validated_data['cookie_policy_id'] = (int) $data['cookie_policy_id'];
		} else {
			$errors[] = 'cookie_policy_id must be a positive numeric value.';
		}

		// Validate and sanitize activated_products.
		if ( isset( $data['activated_products'] ) && is_array( $data['activated_products'] ) ) {
			$validated_data['activated_products'] = array();
			foreach ( $data['activated_products'] as $product_key => $product_value ) {
				if ( in_array( $product_key, $this->accepted_options['activated_products'], true ) ) {
					$validated_data['activated_products'][ $product_key ] = $product_value;
				} else {
					$errors[] = sprintf(
						'Invalid value in activated_products: %s',
						esc_html( $product_key )
					);
				}
			}
		} else {
			$errors[] = 'activated_products must be an array.';
		}

		// Validate and sanitize translated_cookie_policy_ids.
		if ( isset( $data['translated_cookie_policy_ids'] ) && is_array( $data['translated_cookie_policy_ids'] ) ) {
			$validated_data['translated_cookie_policy_ids'] = array();
			foreach ( $data['translated_cookie_policy_ids'] as $language_key => $cookie_policy_id ) {
				if ( ! in_array( strtolower( $language_key ), $this->accepted_languages, true ) ) {
					$errors[] = sprintf( 'Invalid language code: %s', esc_html( $language_key ) );
					continue;
				}

				if ( ! is_numeric( $cookie_policy_id ) || $cookie_policy_id <= 0 ) {
					$errors[] = sprintf(
						'Invalid cookie policy ID for language %s: must be a positive numeric value.',
						esc_html( $language_key )
					);
					continue;
				}

				$validated_data['translated_cookie_policy_ids'][ $language_key ] = (int) $cookie_policy_id;
			}
		} else {
			$errors[] = 'translated_cookie_policy_ids must be an array.';
		}

		return array(
			'errors'         => $errors,
			'validated_data' => $validated_data,
		);
	}

	/**
	 * Process plugin options based on the request data.
	 */
	private function process_plugin_options() {
		$cookie_solution = iub_array_get( $this->validated_data, 'activated_products.cookie_solution' );
		if ( ! empty( $cookie_solution ) ) {
			$cookie_solution_options = $this->build_cookie_solution_options( $cookie_solution );

			try {
				// Saving CS data with CS function.
				( new Iubenda_CS_Product_Service() )->saving_cs_options( $cookie_solution_options );
			} catch ( Exception $e ) {
				$this->errors[] = $e->getMessage();
			}
		}

		$privacy_policy = iub_array_get( $this->validated_data, 'activated_products.privacy_policy' );
		if ( ! empty( $privacy_policy ) ) {
			$privacy_policy_options = $this->build_privacy_policy_options( $privacy_policy );

			try {
				// Saving PP data with PP function.
				( new Iubenda_PP_Product_Service() )->saving_pp_options( $privacy_policy_options );
			} catch ( Exception $e ) {
				$this->errors[] = $e->getMessage();
			}
		}

		$terms = iub_array_get( $this->validated_data, 'activated_products.terms' );
		if ( ! empty( $terms ) ) {
			$terms_options = $this->build_terms_options( $terms );

			try {
				// Saving TC data with TC function.
				( new Iubenda_TC_Product_Service() )->saving_tc_options( $terms_options );
			} catch ( Exception $e ) {
				$this->errors[] = $e->getMessage();
			}
		}
	}

	/**
	 * Build cookie solution options for each language.
	 *
	 * @param string $cs_code Cookie solution code.
	 * @return array Cookie solution options.
	 */
	private function build_cookie_solution_options( $cs_code ) {
		$cs_options = $this->cs_default_options;

		// handle CS codes for each language.
		foreach ( $this->current_website_languages as $lang_id => $v ) {
			$cs_options[ "code_{$lang_id}" ] = $cs_code;
		}

		return $cs_options;
	}

	/**
	 * Build privacy policy options for each language.
	 *
	 * @param string $pp_code Privacy policy code.
	 * @return array Privacy policy options.
	 */
	private function build_privacy_policy_options( $pp_code ) {
		$pp_options = $this->pp_default_options;

		// handle PP codes for each language.
		foreach ( $this->current_website_languages as $lang_id => $v ) {
			$pp_options[ "code_{$lang_id}" ] = $this->replace_public_id_for_lang( $pp_code, $lang_id );
		}

		return $pp_options;
	}

	/**
	 * Build terms and conditions options for each language.
	 *
	 * @param string $tc_code Terms and conditions code.
	 * @return array Terms and conditions options.
	 */
	private function build_terms_options( $tc_code ) {
		$tc_options = $this->tc_default_options;

		// handle PP codes for each language.
		foreach ( $this->current_website_languages as $lang_id => $v ) {
			$tc_options[ "code_{$lang_id}" ] = $this->replace_public_id_for_lang( $tc_code, $lang_id );
		}

		return $tc_options;
	}

	/**
	 * Check if the current user has the required permissions.
	 *
	 * @return bool True if the user has permissions, false otherwise.
	 */
	public function check_user_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Replace the public ID in the snippet code with the one for the specified language.
	 *
	 * @param string $snippet_code The snippet code in which to replace the public ID.
	 * @param string $lang_id The language ID for which the public ID needs to be replaced.
	 * @return string The updated snippet code with the replaced public ID.
	 */
	private function replace_public_id_for_lang( $snippet_code, $lang_id ) {
		// Validate the snippet code.
		if ( empty( $snippet_code ) || ! is_string( $snippet_code ) ) {
			return $snippet_code; // Return the original code if invalid.
		}

		// Retrieve the default public ID.
		$default_public_id = (string) iub_array_get( $this->validated_data, 'cookie_policy_id' );

		// Retrieve the public ID specific to the language.
		$public_id_for_lang = (string) iub_array_get( $this->language_cookie_policy_map, $lang_id, $default_public_id );

		// If the default public ID and language-specific public ID are the same, no replacement is needed.
		if ( $default_public_id === $public_id_for_lang ) {
			return $snippet_code;
		}

		// Replace all occurrences of the default public ID with the language-specific public ID.
		return str_replace( $default_public_id, $public_id_for_lang, $snippet_code );
	}

	/**
	 * Prepare the language mappings for translated cookie policy IDs.
	 *
	 * This function maps the language codes provided in the API request
	 * to the corresponding language codes used in the plugin, and stores
	 * the resulting mapping in `$language_cookie_policy_map`.
	 */
	private function map_translated_languages_to_cookie_policies() {
		$multi_lang = ( iubenda()->multilang && ! empty( iubenda()->languages ) );

		if ( $multi_lang ) {
			$qg_service = new Quick_Generator_Service();

			// Get the translated cookie policy IDs from validated data.
			$translated_policies = iub_array_get( $this->validated_data, 'translated_cookie_policy_ids', array() );

			// Iterate over each translated policy ID.
			foreach ( $translated_policies as $requested_language_code => $cookie_policy_id ) {
				// Get the mapped language codes for the requested language.
				$mapped_languages = $qg_service->get_mapped_language_on_local( $requested_language_code );

				// Map each derived language code to the provided cookie policy ID.
				foreach ( $mapped_languages as $plugin_language_code ) {
					$this->language_cookie_policy_map[ $plugin_language_code ] = (string) $cookie_policy_id;
				}
			}
		} else {
			$this->language_cookie_policy_map['default'] = (string) iub_array_get( $this->validated_data, 'cookie_policy_id' );
		}
	}
}
