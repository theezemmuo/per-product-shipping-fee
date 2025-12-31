<?php
/**
 * Plugin Name: Per Product Shipping Fee for WooCommerce
 * Plugin URI:  https://github.com/theezemmuo/per-product-shipping-fee.git
 * Description: Adds a per-product, flat shipping fee applied once per product and disables Free Shipping when applicable.
 * Version:     1.0.0
 * Author:      Dominic
 * Author URI:  https://theezemmuo.work
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: per-product-shipping-fee
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add shipping fee field to product admin
 */
add_action( 'woocommerce_product_options_shipping', function () {
    woocommerce_wp_text_input( array(
        'id'          => '_product_shipping_fee',
        'label'       => 'Additional Shipping Fee',
        'desc_tip'    => true,
        'description' => 'Flat shipping fee applied once per product (global)',
        'type'        => 'number',
        'custom_attributes' => array(
            'step' => 'any',
            'min'  => '0',
        ),
    ));
    wp_nonce_field( 'save_product_shipping_fee', '_product_shipping_fee_nonce' );
});

/**
 * Save shipping fee field securely
 */
add_action( 'woocommerce_process_product_meta', function ( $post_id ) {

    $nonce = isset( $_POST['_product_shipping_fee_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_product_shipping_fee_nonce'] ) ) : '';
    $fee   = isset( $_POST['_product_shipping_fee'] ) ? sanitize_text_field( wp_unslash( $_POST['_product_shipping_fee'] ) ) : '';

    if ( $nonce && wp_verify_nonce( $nonce, 'save_product_shipping_fee' ) ) {
        $fee = floatval( $fee ); // fully sanitized as float
        update_post_meta( $post_id, '_product_shipping_fee', $fee );
    }
});

/**
 * Apply per-product shipping fees at checkout (once per product)
 */
add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {

    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    $applied_products = array();
    $total_fee = 0;

    foreach ( $cart->get_cart() as $cart_item ) {
        $product_id = $cart_item['product_id'];

        if ( in_array( $product_id, $applied_products, true ) ) {
            continue;
        }

        $fee = get_post_meta( $product_id, '_product_shipping_fee', true );

        if ( $fee !== '' && is_numeric( $fee ) && floatval( $fee ) > 0 ) {
            $total_fee += floatval( $fee );
            $applied_products[] = $product_id;
        }
    }

    if ( $total_fee > 0 ) {
        $cart->add_fee( 'Product Shipping', $total_fee, false );
    }
});

/**
 * Disable Free Shipping if any product has a shipping fee
 */
add_filter( 'woocommerce_package_rates', function ( $rates, $package ) {

    foreach ( $package['contents'] as $item ) {
        $fee = get_post_meta( $item['product_id'], '_product_shipping_fee', true );

        if ( $fee !== '' && is_numeric( $fee ) && floatval( $fee ) > 0 ) {
            foreach ( $rates as $rate_id => $rate ) {
                if ( 'free_shipping' === $rate->method_id ) {
                    unset( $rates[ $rate_id ] );
                }
            }
            break;
        }
    }

    return $rates;
}, 100, 2 );