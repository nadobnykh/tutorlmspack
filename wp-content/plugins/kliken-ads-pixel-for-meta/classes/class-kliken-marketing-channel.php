<?php
/**
 * Class to provide integration with WooCommere Multi-channel Marketing hub.
 *
 * @package Kliken: Ads + Pixel for Meta
 */

namespace Kliken\FbWcPlugin;

use Automattic\WooCommerce\Admin\Marketing\MarketingCampaign;
use Automattic\WooCommerce\Admin\Marketing\MarketingCampaignType;
use Automattic\WooCommerce\Admin\Marketing\MarketingChannelInterface;
use Automattic\WooCommerce\Admin\Marketing\Price;

defined( 'ABSPATH' ) || exit;

/**
 * Class to provide integration with WooCommere Multi-channel Marketing hub.
 */
class Kliken_Marketing_Channel implements MarketingChannelInterface {
	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $_plugin_settings;

	/**
	 * List of campaign types we support through MCM.
	 *
	 * @var array
	 */
	private $_supported_campaign_types;

	/**
	 * Plugin data coming back from API.
	 *
	 * @var array|null
	 */
	private $_api_plugin_data;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->_plugin_settings = Helper::get_plugin_settings();

		if ( ! Helper::is_plugin_configured( $this->_plugin_settings ) ) {
			return;
		}

		$this->_supported_campaign_types = $this->generate_supported_campaign_types();

		// Call Web Event REST API.
		try {

			$res = wp_remote_get(
				KK_FB_WC_WEBEVENT_API_URL . 'Plugin',
				[
					'timeout' => 5,
					'headers' => [
						'Authorization' => Helper::get_authorization_header_for_api( $this->_plugin_settings ),
						'Accept'        => 'application/json',
					],
					// 'sslverify' => false,
				]
			);

			if ( 200 !== wp_remote_retrieve_response_code( $res ) ) {
				return;
			}

			$this->_api_plugin_data = json_decode( wp_remote_retrieve_body( $res ), true );
		} catch ( \Exception $ex ) {
			Helper::wc_log( 'error', 'Failed to make request to get Pixel data. Error: ' . $ex->getMessage() );
		}
	}

	/**
	 * Get slug of the channel being integrated.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return KK_FB_WC_MAIN_PAGE_SLUG;
	}

	/**
	 * Get name of the channel being integrated.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Meta Ads & Pixel for WooCommerce';
	}

	/**
	 * Get description of the channel being integrated.
	 *
	 * @return string
	 */
	public function get_description(): string {
		/* translators: Facebook, Instagram are brand names. Do not translate. */
		return __( 'Reach millions of shoppers on Facebook and Instagram. Complete setup in just 5 minutes to start driving traffic and sales today!', 'kliken-ads-pixel-for-meta' );
	}

	/**
	 * Get icon of the channel being integrated.
	 *
	 * @return string
	 */
	public function get_icon_url(): string {
		return KK_FB_WC_PLUGIN_URL . '/assets/MetaMark160White.png';
	}

	/**
	 * Get set up status of the channel being integrated.
	 *
	 * @return bool
	 */
	public function is_setup_completed(): bool {
		if ( ! Helper::is_plugin_configured( $this->_plugin_settings ) ) {
			return false;
		}

		// Check status from Web Event REST API.
		return $this->is_configured_remotely();
	}

	/**
	 * Get setup URL of the channel being integrated.
	 *
	 * @return string
	 */
	public function get_setup_url(): string {
		if ( ! Helper::is_plugin_configured() ) {
			return Helper::build_signup_link();
		} else {
			$default_url = KK_FB_WC_WOOKLIKEN_BASE_URL . 'meta/?acct=' . $this->_plugin_settings['account_id'];

			if ( ! $this->is_configured_remotely() ) {
				return KK_FB_WC_WOOKLIKEN_BASE_URL . 'meta/default.aspx/v4/Facebook/Account?acct=' . $this->_plugin_settings['account_id'];
			} elseif ( $this->get_errors_count() > 0 ) {
				if ( ! $this->_api_plugin_data['campaigns'] ) {
					return $default_url;
				}

				$url = KK_FB_WC_WOOKLIKEN_BASE_URL
					. 'meta/default.aspx/v4/Facebook/ChecklistShopping'
					. '?acct=' . $this->_plugin_settings['account_id']
					. '&ProtoCampaignId=';

				$cams = array_values(
					array_filter(
						$this->_api_plugin_data['campaigns'],
						function ( $c ) {
							return ! $c['cost'];
						}
					)
				);

				if ( count( $cams ) > 0 ) {
					return $url . $cams[0]['id'];
				} else {
					return $url . $this->_api_plugin_data['campaigns'][0]['id'];
				}
			}

			return $default_url;
		}
	}

	/**
	 * Get product listings status of the channel being integrated.
	 *
	 * @return string
	 */
	public function get_product_listings_status(): string {
		if ( ! isset( $this->_api_plugin_data ) ) {
			return self::PRODUCT_LISTINGS_NOT_APPLICABLE;
		} else {
			switch ( strtolower( $this->_api_plugin_data['storeSyncStatus'] ) ) {
				case 'succeeded':
					return self::PRODUCT_LISTINGS_SYNCED;
				case 'failed':
					return self::PRODUCT_LISTINGS_SYNC_FAILED;
				case 'inprogress':
					return self::PRODUCT_LISTINGS_SYNC_IN_PROGRESS;
			}
		}

		return self::PRODUCT_LISTINGS_SYNCED;
	}

	/**
	 * Get error counts of the channel being integrated.
	 *
	 * @return int
	 */
	public function get_errors_count(): int {
		return isset( $this->_api_plugin_data['numMerchantIssues'] )
			? intval( $this->_api_plugin_data['numMerchantIssues'] )
			: 0;
	}

	/**
	 * Get supported campaign types of the channel being integrated.
	 * This shows up in the modal for "Create a campaign" button.
	 *
	 * @return \Automattic\WooCommerce\Admin\Marketing\MarketingCampaignType[]
	 */
	public function get_supported_campaign_types(): array {
		return $this->_supported_campaign_types;
	}

	/**
	 * Get a list of campaigns of the channel being integrated.
	 *
	 * @return \Automattic\WooCommerce\Admin\Marketing\MarketingCampaign[]
	 */
	public function get_campaigns(): array {
		$campaigns = [];

		if ( ! isset( $this->_api_plugin_data['campaigns'] ) ) {
			return $campaigns;
		}

		foreach ( $this->_api_plugin_data['campaigns'] as $cam ) {
			$type = $this->get_marketing_campaign_type_for_campaign( $cam );

			if ( null === $type ) {
				continue;
			}

			$campaigns[] = new MarketingCampaign(
				$cam['id'],
				$type,
				$cam['name'],
				$this->get_manage_url_for_campaign( $cam ),
				$this->get_price_value_for_campaign( $cam, 'cost' ),
				$this->get_price_value_for_campaign( $cam, 'revenue' )
			);
		}

		return $campaigns;
	}

	/**
	 * Private method to populate a list of supported campaign types.
	 *
	 * @return \Automattic\WooCommerce\Admin\Marketing\MarketingCampaignType[]
	 */
	private function generate_supported_campaign_types() {
		return [
			'facebook-shopping-ads' => new MarketingCampaignType(
				'facebook-shopping-ads',
				$this,
				/* translators: "Facebook", "Instagram" are brand names. Do not translate. */
				__( 'Facebook and Instagram Ads', 'kliken-ads-pixel-for-meta' ),
				/* translators:" Facebook", "Instagram", and "Meta Advantage+" are brand and product names. Do not translate. */
				__( 'Reach millions of shoppers on Facebook and Instagram with Meta Advantage+. Drive sales effortlessly with powerful AI optimization.', 'kliken-ads-pixel-for-meta' ),
				KK_FB_WC_WOOKLIKEN_BASE_URL . 'meta/default.aspx?goto=shopnew&acct=' . $this->_plugin_settings['account_id'],
				KK_FB_WC_PLUGIN_URL . '/assets/MetaMark160White.png'
			),
		];
	}

	/**
	 * Private method to check if the account has been configured from API data.
	 */
	private function is_configured_remotely() {
		if ( ! isset( $this->_api_plugin_data ) ) {
			return true;
		}

		return isset( $this->_api_plugin_data['isConfigured'] ) && $this->_api_plugin_data['isConfigured'];
	}

	/**
	 * Get Marketing campaign type for the given campaign coming from API.
	 *
	 * @param mixed $api_campaign Campaign data, from API.
	 * @return \Automattic\WooCommerce\Admin\Marketing\MarketingCampaignType|null
	 */
	private function get_marketing_campaign_type_for_campaign( $api_campaign ) {
		if ( 'FacebookShoppingAds' === $api_campaign['productType'] ) {
			return $this->_supported_campaign_types['facebook-shopping-ads'];
		}

		return null;
	}

	/**
	 * Get URL to go manage the campaign coming from API.
	 *
	 * @param mixed $api_campaign Campaign data, from API.
	 * @return string
	 */
	private function get_manage_url_for_campaign( $api_campaign ) {
		if ( 'FacebookShoppingAds' === $api_campaign['productType'] ) {
			return KK_FB_WC_WOOKLIKEN_BASE_URL . 'meta/default.aspx/v4/Facebook/EditShopping'
				. '?ProtoCampaignId=' . $api_campaign['id'] . '&acct=' . $this->_plugin_settings['account_id'];
		}

		return Helper::get_plugin_page();
	}

	/**
	 * Get Price object for the given type, for the campaign comping from API.
	 *
	 * @param mixed $api_campaign Campaign data, from API.
	 * @param mixed $type Type of the Price value. Could be "cost" or "revenue".
	 * @return Price|null
	 */
	private function get_price_value_for_campaign( $api_campaign, $type ) {
		if ( ! isset( $api_campaign[ $type ] ) ) {
			return null;
		}

		return $api_campaign[ $type ] > 0
			? new Price( $api_campaign[ $type ], $api_campaign['currencyCode'] )
			: null;
	}
}
