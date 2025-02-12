<?php
/**
 * Class Iubenda_Configuration_Parser
 * Parses configurations from Iubenda code.
 *
 * @package  Iubenda
 */

/**
 * Class Iubenda_Configuration_Parser
 */
class Configuration_Parser {

	/**
	 * Extracts configuration data from an Iubenda CS code.
	 *
	 * Parses the code to extract configuration, specifically tailored for non-AMP (normal) web pages.
	 * This method optionally removes callback functions for easier parsing,
	 * and supports basic or banner modes for configuration extraction.
	 *
	 * @param   string $code  The Iubenda code snippet.
	 * @param   array  $args  Optional arguments to control parsing behavior.
	 *
	 * @return array Configuration data as an associative array.
	 */
	public function extract_cs_config_from_code( $code, $args = array() ) {
		// Check if the embed code have Callback Functions inside it or not.
		if ( strpos( $code, 'callback' ) !== false ) {
			$code = $this->remove_callbacks_for_parsing( $code );
		}

		$configuration = array();
		$defaults      = array(
			'mode'  => 'basic',
			'parse' => false,
		);

		// parse incoming $args into an array and merge it with $defaults.
		$args = wp_parse_args( $args, $defaults );

		if ( empty( $code ) ) {
			return $configuration;
		}

		// parse code if needed.
		$parsed_code = true === $args['parse'] ? $this->sanitize_and_prepare_code( $code, true ) : $code;

		// get script.
		$parsed_script = '';

		preg_match_all( '/src\=(?:[\"|\'])(.*?)(?:[\"|\'])/', $parsed_code, $matches );

		// find the iubenda script url.
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $found_script ) {
				if ( strpos( $found_script, 'iubenda_cs.js' ) ) {
					$parsed_script = $found_script;
					continue;
				}
			}
		}

		// strip tags.
		$parsed_code = wp_kses( $parsed_code, array() );

		// get configuration.
		preg_match( '/_iub.csConfiguration *= *{(.*?)\};/', $parsed_code, $matches );

		if ( ! empty( $matches[1] ) ) {
			$parsed_code = '{' . $matches[1] . '}';
		}

		// decode.
		$decoded = json_decode( $parsed_code, true );

		if ( ! empty( $decoded ) && is_array( $decoded ) ) {

			$decoded['script'] = $parsed_script;

			// basic mode.
			if ( 'basic' === $args['mode'] ) {
				if ( isset( $decoded['banner'] ) ) {
					unset( $decoded['banner'] );
				}
				if ( isset( $decoded['callback'] ) ) {
					unset( $decoded['callback'] );
				}
				if ( isset( $decoded['perPurposeConsent'] ) ) {
					unset( $decoded['perPurposeConsent'] );
				}
				// Banner mode to get banner configuration only.
			} elseif ( 'banner' === (string) $args['mode'] ) {
				if ( isset( $decoded['banner'] ) ) {
					return $decoded['banner'];
				}

				return array();
			}

			$configuration = $decoded;
		}

		return $configuration;
	}

	/**
	 * Get configuration data by Regex from iubenda code
	 *
	 * @param   string $code          CS Embed code.
	 * @return array
	 */
	public function extract_cs_config_from_code_by_regex( $code ) {
		$result = array();

		// Remove slashes and backslashes before use preg match.
		$code = stripslashes( $code );

		// Getting site_id.
		preg_match( '/siteId([\s\S]*?)(?:,|})/', $code, $matches );
		if ( isset( $matches[1] ) ) {
			$result['siteId'] = trim( preg_replace( "/(?:'|\"|}|:)/", ' ', $matches[1] ) );
		}

		// Getting cookie_policy_id.
		preg_match( '/cookiePolicyId([\s\S]*?)(?:,|})/', $code, $matches );
		if ( isset( $matches[1] ) ) {
			$result['cookiePolicyId'] = trim( preg_replace( "/(?:'|\"|}|:)/", ' ', $matches[1] ) );
		}

		return $result;
	}

	/**
	 * Extracts configuration data from an Iubenda CS code.
	 *
	 * Parses the code to extract configuration, specifically tailored for AMP (Accelerated Mobile Pages) web pages.
	 * This method optionally removes callback functions for easier parsing,
	 * and supports basic or banner modes for configuration extraction.
	 *
	 * @param   string $code  The Iubenda code snippet.
	 * @param   array  $args  Optional arguments to control parsing behavior.
	 *
	 * @return array Configuration data as an associative array.
	 */
	public function extract_cs_config_from_code_amp( $code, $args = array() ) {
		// Check if the embed code have Callback Functions inside it or not.
		if ( strpos( $code, 'callback' ) !== false ) {
			$code = $this->remove_callbacks_for_parsing( $code );
		}

		$configuration = array();
		$defaults      = array(
			'mode'  => 'basic',
			'parse' => false,
		);

		// parse incoming $args into an array and merge it with $defaults.
		$args = wp_parse_args( $args, $defaults );

		if ( empty( $code ) ) {
			return $configuration;
		}

		// parse code if needed.
		$parsed_code = true === $args['parse'] ? $this->sanitize_and_prepare_code( $code, true ) : $code;

		// get script.
		$parsed_script = '';

		preg_match_all( '/src\=(?:[\"|\'])(.*?)(?:[\"|\'])/', $parsed_code, $matches );

		// find the iubenda script url.
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $found_script ) {
				if ( wp_http_validate_url( $found_script ) && strpos( $found_script, 'iubenda_cs.js' ) ) {
					$parsed_script = $found_script;
					continue;
				}
			}
		}

		// strip tags.
		$parsed_code = wp_kses( $parsed_code, array() );

		// get configuration.
		preg_match( '/_iub.csConfiguration *= *{(.*?)\};/', $parsed_code, $matches );

		if ( ! empty( $matches[1] ) ) {
			$parsed_code = '{' . $matches[1] . '}';
		}

		// decode.
		$decoded = json_decode( $parsed_code, true );

		if ( ! empty( $decoded ) && is_array( $decoded ) ) {

			$decoded['script'] = $parsed_script;

			// basic mode.
			if ( 'basic' === $args['mode'] ) {
				if ( isset( $decoded['banner'] ) ) {
					unset( $decoded['banner'] );
				}
				if ( isset( $decoded['callback'] ) ) {
					unset( $decoded['callback'] );
				}
				if ( isset( $decoded['perPurposeConsent'] ) ) {
					unset( $decoded['perPurposeConsent'] );
				}
				// Banner mode to get banner configuration only.
			} elseif ( 'banner' === (string) $args['mode'] ) {
				if ( isset( $decoded['banner'] ) ) {
					return $decoded['banner'];
				}

				return array();
			}

			$configuration = $decoded;
		}

		return $configuration;
	}

	/**
	 * Extracts configuration data for Terms and Conditions (TC) & Privacy Policy (PP) from an Iubenda embed code.
	 * Analyzes the provided embed code to determine the style of the button (white or black) and the cookie policy ID
	 * by extracting the relevant URL from the code. Returns false if the code is empty or the necessary information
	 * cannot be extracted.
	 *
	 * @param   string $code  The Iubenda embed code containing TC & PP information.
	 *
	 * @return array|false An associative array with 'button_style' and 'cookie_policy_id', or false if extraction fails.
	 */
	public function extract_tc_pp_config_from_code( $code ) {
		if ( empty( $code ) ) {
			return false;
		}

		// Remove slashes and backslashes before use preg match all.
		$code = stripslashes( $code );

		preg_match_all( '/<a[^>]+href=([\'"])(?<href>.+?)\1[^>]*>/i', $code, $result );
		$url = iub_array_get( $result, 'href.0' );

		if ( ! $url ) {
			return false;
		}

		$button_style     = strpos( stripslashes( $code ), 'iubenda-white' ) !== false ? 'white' : 'black';
		$cookie_policy_id = basename( $url );

		return array(
			'button_style'     => $button_style,
			'cookie_policy_id' => $cookie_policy_id,
		);
	}

	/**
	 * Sanitizes Iubenda code by preparing it for parsing and optionally for display.
	 * - Replaces empty quotes to avoid parsing errors.
	 * - Isolates HTML/content within JSON for sanitization.
	 * - Applies content sanitization to ensure safety.
	 * - Optionally escapes HTML for display.
	 * - Removes placeholders post-sanitization.
	 *
	 * @param   string $source   The Iubenda code.
	 * @param   bool   $display  Prepare code for display (affects escaping).
	 *
	 * @return string Sanitized and prepared code.
	 */
	public function sanitize_and_prepare_code( $source, $display = false ) {
		// Replace empty quotes with a placeholder to avoid parsing issues.
		$source = str_replace( '""', '"IUBENDA_PLACEHOLDER"', trim( (string) $source ) );

		// Use a regular expression to find JSON strings that contain HTML or content fields.
		// This helps in isolating and manipulating HTML content within JSON structures.
		preg_match_all( '/(\"(?:html|content)\"(?:\s+)?\:(?:\s+)?)\"((?:.*?)(?:[^\\\\]))\"/s', $source, $matches );

		// Check if matches were found.
		if ( ! empty( $matches[1] ) && ! empty( $matches[2] ) ) {
			foreach ( $matches[2] as $no => $match ) {
				// Replace the matched string with a placeholder that marks the start and end of an HTML or content string.
				// This is done to temporarily isolate these strings from the rest of the code for safe manipulation.
				$source = str_replace( $matches[0][ $no ], $matches[1][ $no ] . '[[IUBENDA_TAG_START]]' . $match . '[[IUBENDA_TAG_END]]', $source );
			}

			// Apply WordPress content sanitization to the source, allowing only a predefined set of HTML tags and attributes.
			// This ensures that the code is safe from malicious content while retaining necessary HTML structures.
			$source = wp_kses( $source, $this->allowed_html_for_iubenda_scripts() );

			// Find and process all previously marked HTML or content strings for final inclusion.
			preg_match_all( '/\[\[IUBENDA_TAG_START\]\](.*?)\[\[IUBENDA_TAG_END\]\]/s', $source, $matches_tags );

			if ( ! empty( $matches_tags[1] ) ) {
				foreach ( $matches_tags[1] as $no => $match ) {
					// If the code is being prepared for display, escape closing tags within HTML content.
					// This prevents premature termination of the HTML element when embedded in a page.
					$replacement = $display ? str_replace( '</', '<\/', $matches[2][ $no ] ) : $matches[2][ $no ];

					// Replace the placeholders with the sanitized HTML or content strings.
					$source = str_replace( $matches_tags[0][ $no ], '"' . $replacement . '"', $source );
				}
			}
		}

		// Remove the placeholder used to mark empty string values, restoring the original state.
		$source = str_replace( '"IUBENDA_PLACEHOLDER"', '""', $source );

		return $source;
	}

	/**
	 * Retrieves a customized list of HTML tags and attributes that are permitted within Iubenda scripts.
	 * This method ensures the content being processed adheres to a defined standard of safety by specifying
	 * allowable HTML elements and their attributes. It integrates with WordPress' sanitization mechanisms,
	 * and includes a specific adjustment to accommodate Jetpack's handling of embedded HTML objects.
	 *
	 * @return array An associative array detailing the HTML elements and attributes that are considered safe for use.
	 */
	private function allowed_html_for_iubenda_scripts() {
		// Jetpack fix.
		remove_filter( 'pre_kses', array( 'Filter_Embedded_HTML_Objects', 'filter' ), 11 );

		$html = array_merge(
			wp_kses_allowed_html( 'post' ),
			array(
				'script'   => array(
					'type'    => array(),
					'src'     => array(),
					'charset' => array(),
					'async'   => array(),
				),
				'noscript' => array(),
				'style'    => array(
					'type' => array(),
				),
				'iframe'   => array(
					'src'             => array(),
					'height'          => array(),
					'width'           => array(),
					'frameborder'     => array(),
					'allowfullscreen' => array(),
				),
			)
		);

		return apply_filters( 'iub_code_allowed_html', $html );
	}

	/**
	 * Removes callback functions from the code to facilitate configuration parsing.
	 * This is a preprocessing step to simplify the structure of Iubenda code snippets.
	 *
	 * @param   string $code  The embed code that may contain callback functions.
	 *
	 * @return string The code with callback functions removed or replaced.
	 */
	private function remove_callbacks_for_parsing( $code ) {
		$callback_position       = strpos( $code, 'callback' );
		$opened_callback_braces  = strpos( $code, '{', $callback_position );
		$closing_callback_braces = $this->locate_closing_bracket( $code, $opened_callback_braces );

		return substr_replace( $code, '{', $opened_callback_braces, $closing_callback_braces - $opened_callback_braces );
	}

	/**
	 * Finds the position of the closing bracket that matches an opening bracket in a string.
	 * This is used to navigate and manipulate code structures accurately.
	 *
	 * @param   string $target_string  The string in which to find the closing bracket.
	 * @param   int    $open_position  The position of the opening bracket whose matching closing bracket is to be found.
	 *
	 * @return int The position of the matching closing bracket.
	 */
	private function locate_closing_bracket( $target_string, $open_position ) {
		$close_pos = $open_position;
		$counter   = 1;
		while ( $counter > 0 ) {

			// To Avoid the infinity loop.
			if ( ! isset( $target_string[ $close_pos + 1 ] ) ) {
				break;
			}

			$c = $target_string[ ++$close_pos ];
			if ( '{' === (string) $c ) {
				++$counter;
			} elseif ( '}' === (string) $c ) {
				--$counter;
			}
		}

		return $close_pos;
	}

	/**
	 * Retrieve information from the provided script by a given key.
	 *
	 * This function extracts specific information from a script based on the provided key.
	 * It first attempts to parse the configuration using iubenda()->configuration_parser->get_config_from_iubenda_code().
	 * If parsing fails, it tries parsing with $this->cs_product_service->configuration_parser->get_config_from_iubenda_code_by_regex().
	 *
	 * @param string $script The script from which to extract information.
	 * @param string $key    The key for the information to retrieve.
	 *
	 * @return string The extracted information, or an empty string if not found.
	 */
	public function retrieve_info_from_script_by_key( string $script, string $key ) {
		// Remove slashes from the script.
		$script = stripslashes( $script );

		// Try to parse the configuration using iubenda()->configuration_parser->get_config_from_iubenda_code().
		$parsed_configuration = $this->extract_cs_config_from_code( $script );
		if ( ! empty( $parsed_configuration ) && isset( $parsed_configuration[ $key ] ) ) {
			return $parsed_configuration[ $key ];
		}

		// If parsing fails, try parsing with $this->cs_product_service->configuration_parser->get_config_from_iubenda_code_by_regex().
		$parsed_configuration_by_regex = $this->extract_cs_config_from_code_by_regex( $script );
		if ( ! empty( $parsed_configuration_by_regex ) && isset( $parsed_configuration_by_regex[ $key ] ) ) {
			return $parsed_configuration_by_regex[ $key ];
		}

		// Return an empty string if the key is not found.
		return '';
	}
}
