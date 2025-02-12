<?php

namespace PaymentPlugins\WooCommerce\PPCP\Factories;

use PaymentPlugins\PayPalSDK\Amount;
use PaymentPlugins\PayPalSDK\Collection;
use PaymentPlugins\PayPalSDK\ShippingOption;
use PaymentPlugins\WooCommerce\PPCP\Utilities\NumberUtil;

class ShippingOptionsFactory extends AbstractFactory {

	/**
	 * @return \PaymentPlugins\PayPalSDK\Collection
	 */
	public function from_cart() {
		// loop through shipping options and format then
		$incl_tax                = $this->display_prices_including_tax();
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods', [] );
		$methods                 = new Collection();
		foreach ( WC()->shipping()->get_packages() as $i => $package ) {
			foreach ( $package['rates'] as $method ) {
				/**
				 *
				 * @var WC_Shipping_Rate $method
				 */
				$amount   = $incl_tax ? $method->cost + $method->get_shipping_tax() : $method->cost;
				$selected = isset( $chosen_shipping_methods[ $i ] ) && $chosen_shipping_methods[ $i ] === $method->id;
				$methods->add( $this->get_shipping_method_option( $amount, $method, $i, $selected ) );
			}
		}

		return $methods;
	}

	public function from_order() {
		$methods          = new Collection();
		$shipping_methods = $this->order->get_shipping_methods();
		if ( is_array( $shipping_methods ) ) {
			if ( count( $shipping_methods ) > 1 ) {
				$shipping_methods = [ array_shift( $shipping_methods ) ];
			}
			foreach ( $shipping_methods as $idx => $method ) {
				$methods->add( $this->get_order_shipping_method_option(
					$method->get_total(),
					$method,
					$idx,
					true
				) );
			}
		}

		return $methods;
	}

	/**
	 * @param string            $amount
	 * @param \WC_Shipping_Rate $method
	 * @param                   $idx
	 * @param bool              $selected
	 *
	 * @return ShippingOption
	 */
	public function get_shipping_method_option( $amount, \WC_Shipping_Rate $method, $idx, bool $selected ) {
		return ( new ShippingOption() )->setId( $this->get_shipping_method_option_id( $idx, $method->id ) )
		                               ->setLabel( substr( $method->get_label(), 0, 127 ) )
		                               ->setType( 'SHIPPING' )
		                               ->setSelected( $selected )
		                               ->setAmount( ( new Amount() )
			                               ->setValue( $this->round( $amount ) )
			                               ->setCurrencyCode( $this->currency ) );
	}

	public function get_order_shipping_method_option( $amount, \WC_Order_Item_Shipping $method, $idx, $selected ) {
		return ( new ShippingOption() )->setId( $this->get_shipping_method_option_id( $idx, $method->get_method_id() ) )
		                               ->setLabel( substr( $method->get_name(), 0, 127 ) )
		                               ->setType( 'SHIPPING' )
		                               ->setSelected( $selected )
		                               ->setAmount( ( new Amount() )
			                               ->setValue( $this->round( $amount ) )
			                               ->setCurrencyCode( $this->currency ) );
	}

	/**
	 * @param $package_id
	 * @param $method
	 *
	 * @return string
	 */
	private function get_shipping_method_option_id( $package_id, $method ) {
		return sprintf( '%s:%s', $package_id, $method );
	}

}