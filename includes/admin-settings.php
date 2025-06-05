<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Hook to add the admin menu
add_action( 'admin_menu', 'aws_add_admin_menu' );
// Hook to initialize settings
add_action( 'admin_init', 'aws_settings_init' );
// Hook to handle manual sync POST request
add_action( 'admin_post_aws_manual_sync', 'aws_handle_manual_sync' );


// ... (aws_add_admin_menu, aws_settings_init, field renderers - keep existing code here) ...
// (Make sure all previous functions from admin-settings.php are here)

/**
 * Add the admin menu item for the plugin.
 */
function aws_add_admin_menu() {
    add_menu_page(
        __( 'Airtable WooCommerce Sync', 'airtable-woocommerce-sync' ),
        __( 'Airtable Sync', 'airtable-woocommerce-sync' ),
        'manage_options',
        'airtable_woocommerce_sync',
        'aws_settings_page_html',
        'dashicons-update-alt',
        30
    );
}

/**
 * Initialize plugin settings, register sections and fields.
 */
function aws_settings_init() {
    register_setting( 'airtable_woocommerce_sync_settings', 'aws_settings' );

    add_settings_section(
        'aws_api_settings_section',
        __( 'Airtable API Credentials', 'airtable-woocommerce-sync' ),
        'aws_api_settings_section_callback',
        'airtable_woocommerce_sync_settings'
    );

    add_settings_field('aws_api_key', __( 'Airtable Personal Access Token', 'airtable-woocommerce-sync' ), 'aws_api_key_field_html', 'airtable_woocommerce_sync_settings', 'aws_api_settings_section');
    add_settings_field('aws_base_id', __( 'Airtable Base ID', 'airtable-woocommerce-sync' ), 'aws_base_id_field_html', 'airtable_woocommerce_sync_settings', 'aws_api_settings_section');
    add_settings_field('aws_table_name', __( 'Airtable Table Name', 'airtable-woocommerce-sync' ), 'aws_table_name_field_html', 'airtable_woocommerce_sync_settings', 'aws_api_settings_section');

    add_settings_section('aws_sync_actions_section', __( 'Synchronization Actions', 'airtable-woocommerce-sync' ), 'aws_sync_actions_section_callback', 'airtable_woocommerce_sync_settings');
}

function aws_api_settings_section_callback() {
    echo '<p>' . esc_html__( 'Enter your Airtable API credentials and table information below.', 'airtable-woocommerce-sync' ) . '</p>';
}

function aws_sync_actions_section_callback() {
    // This section is now primarily for the button displayed in aws_settings_page_html
    // Or for displaying persistent sync status if we add that later.
}

function aws_api_key_field_html() {
    $options = get_option( 'aws_settings' );
    $api_key = isset( $options['api_key'] ) ? $options['api_key'] : ''; // Variable name $api_key can remain, it holds the token.
    echo '<input type="password" id="aws_api_key" name="aws_settings[api_key]" value="' . esc_attr( $api_key ) . '" class="regular-text">';
    echo '<p class="description">' . esc_html__( 'Your Airtable Personal Access Token. This is now preferred over API keys.', 'airtable-woocommerce-sync' ) . '</p>';
}

function aws_base_id_field_html() {
    $options = get_option( 'aws_settings' );
    $base_id = isset( $options['base_id'] ) ? $options['base_id'] : '';
    echo '<input type="text" id="aws_base_id" name="aws_settings[base_id]" value="' . esc_attr( $base_id ) . '" class="regular-text">';
    echo '<p class="description">' . esc_html__( 'Your Airtable Base ID.', 'airtable-woocommerce-sync' ) . '</p>';
}

function aws_table_name_field_html() {
    $options = get_option( 'aws_settings' );
    $table_name = isset( $options['table_name'] ) ? $options['table_name'] : '';
    echo '<input type="text" id="aws_table_name" name="aws_settings[table_name]" value="' . esc_attr( $table_name ) . '" class="regular-text">';
    echo '<p class="description">' . esc_html__( 'The name of your table in Airtable (e.g., Products).', 'airtable-woocommerce-sync' ) . '</p>';
}


/**
 * Handle the manual sync submission.
 */
function aws_handle_manual_sync() {
    // Verify nonce
    if ( ! isset( $_POST['aws_manual_sync_nonce_field'] ) || ! wp_verify_nonce( $_POST['aws_manual_sync_nonce_field'], 'aws_manual_sync_nonce' ) ) {
        wp_die( __( 'Security check failed!', 'airtable-woocommerce-sync' ) );
    }

    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to perform this action.', 'airtable-woocommerce-sync' ) );
    }

    $options = get_option( 'aws_settings' );
    $personal_access_token = isset( $options['api_key'] ) ? $options['api_key'] : ''; // Value from 'api_key' field is the PAT
    $base_id = isset( $options['base_id'] ) ? $options['base_id'] : '';
    $table_name = isset( $options['table_name'] ) ? $options['table_name'] : '';

    if ( empty( $personal_access_token ) || empty( $base_id ) || empty( $table_name ) ) {
        wp_redirect( add_query_arg( 'aws_sync_message', 'missing_credentials', admin_url( 'admin.php?page=airtable_woocommerce_sync' ) ) );
        exit;
    }

    $airtable_api = new Airtable_API( $personal_access_token, $base_id ); // Pass PAT to constructor
    $product_manager = new WooCommerce_Product_Manager();

    $records = $airtable_api->get_records( $table_name );

    if ( is_wp_error( $records ) ) {
        $error_message = $records->get_error_message();
        // This error is already logged by the Airtable_API class.
        error_log('[Airtable WooCommerce Sync] Manual Sync Error: Failed to retrieve records from Airtable. Detail: ' . $error_message);
        wp_redirect( add_query_arg( ['aws_sync_message' => 'airtable_error', 'aws_sync_error_detail' => urlencode($error_message)], admin_url( 'admin.php?page=airtable_woocommerce_sync' ) ) );
        exit;
    }

    if ( empty( $records ) ) {
        wp_redirect( add_query_arg( 'aws_sync_message', 'no_records', admin_url( 'admin.php?page=airtable_woocommerce_sync' ) ) );
        exit;
    }

    $results = [
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'failed' => 0,
        'errors' => []
    ];

    foreach ( $records as $record ) {
        $results['processed']++;
        // **Data Mapping - This is a simplified example**
        // Assumes Airtable fields are named 'Name', 'SKU', 'Description', 'ShortDescription'
        // and 'AttributesJSON', 'VariationsJSON' for complex fields.
        // You WILL need to adjust this based on your actual Airtable structure.

        $product_data = [
            'name' => isset( $record['fields']['Name'] ) ? $record['fields']['Name'] : 'Untitled Product',
            'sku' => isset( $record['fields']['SKU'] ) ? $record['fields']['SKU'] : '',
            'description' => isset( $record['fields']['Description'] ) ? $record['fields']['Description'] : '',
            'short_description' => isset( $record['fields']['ShortDescription'] ) ? $record['fields']['ShortDescription'] : '',
            'attributes' => [], // Placeholder for attributes mapping
            'variations' => [], // Placeholder for variations mapping
        ];

        // Basic SKU check
        if ( empty( $product_data['sku'] ) ) {
            $results['failed']++;
            $error_detail = 'Record ID ' . (isset($record['id']) ? $record['id'] : 'N/A') . ' (' . $product_data['name'] . ') missing SKU.';
            $results['errors'][] = $error_detail;
            error_log('[Airtable WooCommerce Sync] Manual Sync Data Error: ' . $error_detail); // Added logging
            continue;
        }

        // --- TODO: Advanced Attribute Mapping ---
        // Example: if $record['fields']['AttributesText'] = "Color:Red,Blue;Size:Small,Medium"
        // Parse this string into the $product_data['attributes'] array structure.
        // For now, we'll assume attributes are pre-formatted if they exist or simple.
        // Let's assume a field 'ProductAttributes' (JSON string) for attributes definition for the product.
        if ( isset( $record['fields']['ProductAttributes'] ) ) {
            $attrs_json = $record['fields']['ProductAttributes'];
            $attrs_data = json_decode( $attrs_json, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array($attrs_data) ) {
                $product_data['attributes'] = $attrs_data; // Assumes direct mapping
            }
        }


        // --- TODO: Advanced Variation Mapping ---
        // Example: if $record['fields']['VariationsJSON'] contains the JSON string for variations.
        // Parse this into $product_data['variations']
        // Let's assume a field 'ProductVariations' (JSON string) for variations.
         if ( isset( $record['fields']['ProductVariations'] ) ) {
            $vars_json = $record['fields']['ProductVariations'];
            $vars_data = json_decode( $vars_json, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array($vars_data) ) {
                $product_data['variations'] = $vars_data; // Assumes direct mapping
            }
        }

        $existing_product_id = wc_get_product_id_by_sku( $product_data['sku'] );
        $result = $product_manager->create_update_variable_product( $product_data );

        if ( is_wp_error( $result ) ) {
            $results['failed']++;
            $error_message = 'SKU ' . $product_data['sku'] . ': ' . $result->get_error_message();
            $results['errors'][] = $error_message;
            error_log('[Airtable WooCommerce Sync] Manual Sync Product Error: ' . $error_message); // Added logging
        } else {
            if ( $existing_product_id && $result == $existing_product_id) {
                $results['updated']++;
            } else {
                 $results['created']++;
            }
        }
    }

    // Store results in a transient to display on the settings page
    set_transient( 'aws_sync_results', $results, 60 ); // Keep for 60 seconds
    wp_redirect( add_query_arg( 'aws_sync_message', 'sync_completed', admin_url( 'admin.php?page=airtable_woocommerce_sync' ) ) );
    exit;
}


/**
 * Display the HTML for the settings page.
 */
function aws_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Display messages from sync operation
    if ( isset( $_GET['aws_sync_message'] ) ) {
        $message_type = sanitize_key( $_GET['aws_sync_message'] );
        $class = 'notice notice-info';
        $message = '';

        switch ( $message_type ) {
            case 'missing_credentials':
                $class = 'notice notice-error';
                $message = __( 'Airtable Personal Access Token, Base ID, or Table Name is missing. Please fill in all required fields.', 'airtable-woocommerce-sync' );
                break;
            case 'airtable_error':
                $class = 'notice notice-error';
                $error_detail = isset($_GET['aws_sync_error_detail']) ? esc_html(urldecode($_GET['aws_sync_error_detail'])) : __('Unknown error.', 'airtable-woocommerce-sync');
                $message = sprintf(__( 'Error connecting to Airtable: %s', 'airtable-woocommerce-sync' ), $error_detail);
                break;
            case 'no_records':
                $message = __( 'No records found in the specified Airtable table or view.', 'airtable-woocommerce-sync' );
                break;
            case 'sync_completed':
                $class = 'notice notice-success';
                $message = __( 'Manual synchronization process completed.', 'airtable-woocommerce-sync' );
                $sync_results = get_transient( 'aws_sync_results' );
                if ( $sync_results ) {
                    $message .= '<br/>' . sprintf( __( 'Processed: %d, Created: %d, Updated: %d, Failed: %d.', 'airtable-woocommerce-sync' ),
                        $sync_results['processed'], $sync_results['created'], $sync_results['updated'], $sync_results['failed']
                    );
                    if ( ! empty( $sync_results['errors'] ) ) {
                        $message .= '<br/><strong>' . __( 'Errors:', 'airtable-woocommerce-sync' ) . '</strong><ul>';
                        foreach ( $sync_results['errors'] as $error ) {
                            $message .= '<li>' . esc_html( $error ) . '</li>';
                        }
                        $message .= '</ul>';
                    }
                    delete_transient( 'aws_sync_results' );
                }
                break;
        }
        if ( $message ) {
            echo '<div class="' . esc_attr( $class ) . ' is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'airtable_woocommerce_sync_settings' );
            do_settings_sections( 'airtable_woocommerce_sync_settings' );
            submit_button( __( 'Save Settings', 'airtable-woocommerce-sync' ) );
            ?>
        </form>

        <hr/>
        <h2><?php esc_html_e( 'Manual Synchronization', 'airtable-woocommerce-sync' ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="aws_manual_sync">
            <?php
            wp_nonce_field( 'aws_manual_sync_nonce', 'aws_manual_sync_nonce_field' );
            ?>
            <p>
                <?php esc_html_e( 'Click the button below to manually synchronize products from Airtable to WooCommerce.', 'airtable-woocommerce-sync' ); ?>
            </p>
            <p>
                <?php submit_button( __( 'Start Manual Sync', 'airtable-woocommerce-sync' ), 'secondary', 'aws_manual_sync_button', false ); ?>
            </p>
            <div id="aws-sync-status">
                <!-- Real-time status might require AJAX, for now, messages are shown on page reload -->
            </div>
        </form>
    </div>
    <?php
}
?>
