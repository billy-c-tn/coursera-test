<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WooCommerce_Product_Manager {

    public function create_update_variable_product( $product_data ) {
        if ( ! function_exists( 'wc_get_product_id_by_sku' ) || ! function_exists( 'wc_get_product' ) ) {
            $error_message = 'WooCommerce is not active or essential functions are missing.';
            error_log('[Airtable WooCommerce Sync] WooCommerce Check Error: ' . $error_message);
            return new WP_Error( 'woocommerce_not_active', $error_message );
        }

        $product_sku = isset($product_data['sku']) ? $product_data['sku'] : null;
        if (empty($product_sku)) {
            $error_message = 'Product SKU is missing in product_data.';
            error_log('[Airtable WooCommerce Sync] Product Data Error: ' . $error_message . ' Data: ' . print_r($product_data, true));
            return new WP_Error( 'missing_sku', $error_message );
        }

        $product_id = wc_get_product_id_by_sku( $product_sku );
        $product = $product_id ? wc_get_product( $product_id ) : null;
        $is_new_product = false;

        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            $product = new WC_Product_Variable();
            $is_new_product = true; // Mark as new if we have to create a new WC_Product_Variable instance
        }

        // If $product_id exists but it's not a variable product, we are effectively creating a new one.
        // The old simple/grouped/external product with the same SKU will not be modified by this logic directly,
        // but setting SKU on the new variable product might cause issues if SKUs must be unique across all types.
        // WooCommerce itself doesn't strictly enforce SKU uniqueness by default but can be configured to.

        $product->set_name( isset($product_data['name']) ? $product_data['name'] : 'Untitled Product' );
        $product->set_sku( $product_sku );
        $product->set_description( isset($product_data['description']) ? $product_data['description'] : '' );
        $product->set_short_description( isset($product_data['short_description']) ? $product_data['short_description'] : '' );
        $product->set_status( 'publish' );

        $wc_attributes = [];
        if ( ! empty( $product_data['attributes'] ) && is_array($product_data['attributes']) ) {
            foreach ( $product_data['attributes'] as $index => $attr_data ) {
                if (empty($attr_data['name']) || empty($attr_data['options'])) {
                    error_log('[Airtable WooCommerce Sync] Product Attribute Data Error: Missing name or options for SKU ' . $product_sku . '. Attribute data: ' . print_r($attr_data, true));
                    continue; // Skip this attribute
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
            error_log( '[Airtable WooCommerce Sync] Error saving parent product (SKU: ' . $product_sku . '): ' . $parent_product_id_or_error->get_error_message() );
            return $parent_product_id_or_error;
        }

        $parent_product_id = $product->get_id(); // Get ID after saving the product

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
                        // SKU exists but is not a variation of this product or not a variation at all.
                        error_log('[Airtable WooCommerce Sync] Variation SKU ' . $variation_sku . ' exists but is not a valid variation of parent SKU ' . $product_sku . '. Creating new variation.');
                        $variation_id = 0; // Reset to create new
                    }
                }

                if ( ! $variation ) {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id( $parent_product_id );
                }

                $var_attributes = [];
                if ( ! empty( $variation_data['attributes'] ) && is_array($variation_data['attributes']) ) {
                    foreach ( $variation_data['attributes'] as $attr ) {
                         if (empty($attr['name']) || !isset($attr['option'])) { // option can be empty string
                            error_log('[Airtable WooCommerce Sync] Variation Attribute Data Error: Missing name or option for variation SKU ' . $variation_sku . ' (Parent SKU: ' . $product_sku . '). Attribute data: ' . print_r($attr, true));
                            continue 2; // Skip this variation if attributes are malformed
                        }
                        $attribute_taxonomy_name = wc_attribute_taxonomy_name( $attr['name'] );
                        $var_attributes[ $attribute_taxonomy_name ] = $attr['option'];
                    }
                } else {
                     error_log('[Airtable WooCommerce Sync] Variation Data Error: Missing attributes for variation SKU ' . $variation_sku . ' (Parent SKU: ' . $product_sku . ').');
                     continue; // Skip this variation if attributes array is missing/empty
                }

                $variation->set_attributes( $var_attributes );

                if ( isset( $variation_data['regular_price'] ) ) {
                    $variation->set_regular_price( $variation_data['regular_price'] );
                }
                if ( !empty( $variation_sku ) ) { // Only set SKU if provided for variation
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
                    error_log( '[Airtable WooCommerce Sync] Error saving variation (SKU: ' . $variation_sku . ', Parent SKU: ' . $product_sku . '): ' . $variation_save_result->get_error_message() );
                    // Potentially add to a list of errors for this product if you want to return partial success
                }
            }
        }

        if ( function_exists('wc_delete_product_transients') ) {
            wc_delete_product_transients( $parent_product_id );
        }

        // Determine if product was created or updated for return value (though not strictly required by current usage)
        // $product_id was the initial ID from SKU, $parent_product_id is ID after save.
        // If $is_new_product was true, it's a creation.
        // If $product_id existed and $product_id == $parent_product_id, it's an update.
        return $parent_product_id;
    }
}
