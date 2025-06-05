<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPI_WooCommerce_Product_Manager { // Renamed class

    public function create_update_variable_product( $product_data ) {
        if ( ! function_exists( 'wc_get_product_id_by_sku' ) || ! function_exists( 'wc_get_product' ) ) {
            $error_message = 'WooCommerce is not active or essential functions are missing.';
            error_log('[WPI] WooCommerce Check Error: ' . $error_message); // Updated log prefix
            return new WP_Error( 'woocommerce_not_active', $error_message );
        }

        $product_sku = isset($product_data['sku']) ? $product_data['sku'] : null;
        if (empty($product_sku)) {
            $error_message = 'Product SKU is missing in product_data.';
            error_log('[WPI] Product Data Error: ' . $error_message . ' Data: ' . print_r($product_data, true)); // Updated log prefix
            return new WP_Error( 'missing_sku', $error_message );
        }

        $product_id = wc_get_product_id_by_sku( $product_sku );
        $product = $product_id ? wc_get_product( $product_id ) : null;

        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            $product = new WC_Product_Variable();
        }

        $product->set_name( isset($product_data['name']) ? $product_data['name'] : 'Untitled Product' );
        $product->set_sku( $product_sku );
        $product->set_description( isset($product_data['description']) ? $product_data['description'] : '' );
        $product->set_short_description( isset($product_data['short_description']) ? $product_data['short_description'] : '' );
        $product->set_status( 'publish' );

        $wc_attributes = [];
        if ( ! empty( $product_data['attributes'] ) && is_array($product_data['attributes']) ) {
            foreach ( $product_data['attributes'] as $index => $attr_data ) {
                if (empty($attr_data['name']) || empty($attr_data['options'])) {
                    error_log('[WPI] Product Attribute Data Error: Missing name or options for SKU ' . $product_sku . '. Attribute data: ' . print_r($attr_data, true));
                    continue;
                }
                $attribute = new WC_Product_Attribute();
                $attribute->set_name( $attr_data['name'] );
                $attribute->set_options( $attr_data['options'] );
                $attribute->set_position( $index );
                $attribute->set_visible( ! empty( $attr_data['is_visible'] ) );
                $attribute->set_variation( ! empty( $attr_data['is_variation'] ) );
                $wc_attributes[] = $attribute;
            }
        }
        $product->set_attributes( $wc_attributes );

        $parent_product_id_or_error = $product->save();

        if ( is_wp_error( $parent_product_id_or_error ) ) {
            error_log( '[WPI] Error saving parent product (SKU: ' . $product_sku . '): ' . $parent_product_id_or_error->get_error_message() );
            return $parent_product_id_or_error;
        }

        $parent_product_id = $product->get_id();

        if ( ! empty( $product_data['variations'] ) && is_array($product_data['variations']) ) {
            foreach ( $product_data['variations'] as $variation_data ) {
                $variation_sku = isset( $variation_data['sku'] ) ? $variation_data['sku'] : '';
                $variation_id = 0;
                if(!empty($variation_sku)){
                    $variation_id = wc_get_product_id_by_sku( $variation_sku );
                }

                $variation = null;

                if( $variation_id ){
                    $variation_post_obj = get_post($variation_id);
                    if($variation_post_obj && 'product_variation' === $variation_post_obj->post_type && $parent_product_id === (int) $variation_post_obj->post_parent){
                         $variation = wc_get_product($variation_id);
                    } else {
                        error_log('[WPI] Variation SKU ' . $variation_sku . ' exists but is not a valid variation of parent SKU ' . $product_sku . '. Creating new variation.');
                        $variation_id = 0;
                    }
                }

                if ( ! $variation ) {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id( $parent_product_id );
                }

                $var_attributes = [];
                if ( ! empty( $variation_data['attributes'] ) && is_array($variation_data['attributes']) ) {
                    foreach ( $variation_data['attributes'] as $attr ) {
                         if (empty($attr['name']) || !isset($attr['option'])) {
                            error_log('[WPI] Variation Attribute Data Error: Missing name or option for variation SKU ' . $variation_sku . ' (Parent SKU: ' . $product_sku . '). Attribute data: ' . print_r($attr, true));
                            continue 2;
                        }
                        $attribute_taxonomy_name = wc_attribute_taxonomy_name( $attr['name'] );
                        $var_attributes[ $attribute_taxonomy_name ] = $attr['option'];
                    }
                } else {
                     error_log('[WPI] Variation Data Error: Missing attributes for variation SKU ' . $variation_sku . ' (Parent SKU: ' . $product_sku . ').');
                     continue;
                }
                $variation->set_attributes( $var_attributes );

                if ( isset( $variation_data['regular_price'] ) ) {
                    $variation->set_regular_price( $variation_data['regular_price'] );
                }
                if ( !empty( $variation_sku ) ) {
                    $variation->set_sku( $variation_sku );
                }
                if ( isset( $variation_data['stock_quantity'] ) ) {
                    $variation->set_manage_stock( true );
                    $variation->set_stock_quantity( $variation_data['stock_quantity'] );
                } else {
                    $variation->set_manage_stock( false );
                }

                $variation_save_result = $variation->save();
                if ( is_wp_error( $variation_save_result ) ) {
                    error_log( '[WPI] Error saving variation (SKU: ' . $variation_sku . ', Parent SKU: ' . $product_sku . '): ' . $variation_save_result->get_error_message() );
                }
            }
        }

        if ( function_exists('wc_delete_product_transients') ) {
            wc_delete_product_transients( $parent_product_id );
        }
        return $parent_product_id;
   }
}
?>
