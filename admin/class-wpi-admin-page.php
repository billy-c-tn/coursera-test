<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPI_Admin_Page {

    private $option_group = 'wpi_settings_group';
    private $option_name = 'wpi_options';
    private $options;
    private $defined_woo_fields;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        $this->options = get_option( $this->option_name, [] );

        // Google Sheets actions
        add_action( 'admin_post_wpi_test_google_sheets', [ $this, 'handle_test_google_sheets_connection' ] );
        add_action( 'admin_post_wpi_disconnect_google_sheets', [ $this, 'handle_disconnect_google_sheets' ] );
        add_action( 'admin_post_wpi_fetch_google_sheets_sample', [ $this, 'handle_fetch_google_sheets_sample' ] );

        // Airtable actions
        add_action( 'admin_post_wpi_test_airtable', [ $this, 'handle_test_airtable_connection' ] );
        add_action( 'admin_post_wpi_disconnect_airtable', [ $this, 'handle_disconnect_airtable' ] );
        add_action( 'admin_post_wpi_fetch_airtable_sample', [ $this, 'handle_fetch_airtable_sample' ] );

        // Import action (New)
        add_action( 'admin_post_wpi_start_import', [ $this, 'handle_start_import' ] );

        $this->defined_woo_fields = [
            'product_name' => __( 'Product Name', 'woocommerce-product-importer' ),
            'sku' => __( 'SKU (Parent Product)', 'woocommerce-product-importer' ),
            'description' => __( 'Description', 'woocommerce-product-importer' ),
            'short_description' => __( 'Short Description', 'woocommerce-product-importer' ),
            'attributes_json' => __( 'Attributes JSON Column', 'woocommerce-product-importer' ),
            'variations_json' => __( 'Variations JSON Column', 'woocommerce-product-importer' ),
        ];
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'WooCommerce Product Importer', 'woocommerce-product-importer' ),
            __( 'Product Importer', 'woocommerce-product-importer' ),
            'manage_options',
            'wpi_settings',
            [ $this, 'render_settings_page' ],
            'dashicons-cloud-upload',
            30
        );
    }

    public function register_settings() {
        register_setting( $this->option_group, $this->option_name, [ $this, 'sanitize_options' ] );

        add_settings_section(
            'wpi_data_source_section',
            __( 'Data Source Selection', 'woocommerce-product-importer' ),
            [ $this, 'render_data_source_section_text' ],
            $this->option_group
        );
        add_settings_field(
            'wpi_selected_source',
            __( 'Choose Data Source', 'woocommerce-product-importer' ),
            [ $this, 'render_data_source_field' ],
            $this->option_group,
            'wpi_data_source_section'
        );

        $selected_source = isset( $this->options['selected_source'] ) ? $this->options['selected_source'] : '';

        if ( $selected_source === 'google_sheets' ) {
            add_settings_section(
                'wpi_google_sheets_auth_section',
                 __( 'Google Sheets Authentication', 'woocommerce-product-importer' ),
                [ $this, 'render_google_sheets_settings_section_text' ],
                $this->option_group);
            add_settings_field(
                'wpi_google_sheets_json_key',
                __( 'Service Account JSON Key', 'woocommerce-product-importer' ),
                [ $this, 'render_google_sheets_json_key_field' ],
                $this->option_group,
                'wpi_google_sheets_auth_section');
            add_settings_field(
                'wpi_google_sheets_connection_actions',
                __( 'Connection Actions', 'woocommerce-product-importer'),
                [ $this, 'render_google_sheets_connection_actions_field'],
                $this->option_group,
                'wpi_google_sheets_auth_section');
            add_settings_section(
                'wpi_google_sheets_data_location_section',
                __( 'Google Sheets Data Location', 'woocommerce-product-importer' ),
                [ $this, 'render_google_sheets_data_location_section_text' ],
                $this->option_group
            );
            add_settings_field(
                'wpi_google_sheets_spreadsheet_id',
                __( 'Spreadsheet ID', 'woocommerce-product-importer' ),
                [ $this, 'render_google_sheets_spreadsheet_id_field' ],
                $this->option_group,
                'wpi_google_sheets_data_location_section'
            );
            add_settings_field(
                'wpi_google_sheets_sheet_name',
                __( 'Sheet Name or Range', 'woocommerce-product-importer' ),
                [ $this, 'render_google_sheets_sheet_name_field' ],
                $this->option_group,
                'wpi_google_sheets_data_location_section'
            );
            add_settings_field(
                'wpi_google_sheets_fetch_sample_button',
                __( 'Fetch Sample', 'woocommerce-product-importer' ),
                [ $this, 'render_google_sheets_fetch_sample_button_field' ],
                $this->option_group,
                'wpi_google_sheets_data_location_section'
            );

        } elseif ( $selected_source === 'airtable' ) {
            add_settings_section(
                'wpi_airtable_auth_section',
                __( 'Airtable Authentication & Configuration', 'woocommerce-product-importer' ),
                [ $this, 'render_airtable_settings_section_text' ],
                $this->option_group
            );
            add_settings_field(
                'wpi_airtable_pat',
                __( 'Personal Access Token (PAT)', 'woocommerce-product-importer' ),
                [ $this, 'render_airtable_pat_field' ],
                $this->option_group,
                'wpi_airtable_auth_section'
            );
            add_settings_field(
                'wpi_airtable_base_id',
                __( 'Base ID', 'woocommerce-product-importer' ),
                [ $this, 'render_airtable_base_id_field' ],
                $this->option_group,
                'wpi_airtable_auth_section'
            );
            add_settings_field(
                'wpi_airtable_table_name',
                __( 'Table Name or ID', 'woocommerce-product-importer' ),
                [ $this, 'render_airtable_table_name_field' ],
                $this->option_group,
                'wpi_airtable_auth_section'
            );
            add_settings_field(
                'wpi_airtable_connection_actions',
                __( 'Connection Actions', 'woocommerce-product-importer'),
                [ $this, 'render_airtable_connection_actions_field'],
                $this->option_group,
                'wpi_airtable_auth_section'
            );
            add_settings_field(
                'wpi_airtable_fetch_sample_button',
                __( 'Fetch Sample', 'woocommerce-product-importer' ),
                [ $this, 'render_airtable_fetch_sample_button_field' ],
                $this->option_group,
                'wpi_airtable_auth_section'
            );
        }

        $headers_available = false;
        $mappings_exist = false;

        if ($selected_source === 'google_sheets') {
            $headers_available = !empty($this->options['google_sheets_headers']);
            $mappings_exist = !empty($this->options['google_sheets_mappings']);
        } elseif ($selected_source === 'airtable') {
            $headers_available = !empty($this->options['airtable_headers']);
            $mappings_exist = !empty($this->options['airtable_mappings']);
        }

        if ( $selected_source && $headers_available ) { // Show mapping if headers are there
            add_settings_section(
                'wpi_field_mapping_section',
                __( 'Field Mapping', 'woocommerce-product-importer' ),
                [ $this, 'render_field_mapping_section_text' ],
                $this->option_group
            );

            foreach ($this->defined_woo_fields as $field_key => $field_label) {
                add_settings_field(
                    'wpi_mapping_' . $field_key,
                    $field_label,
                    [ $this, 'render_mapping_dropdown_field' ],
                    $this->option_group,
                    'wpi_field_mapping_section',
                    [ 'field_key' => $field_key, 'label_for' => 'wpi_mapping_' . $field_key . '_select' ]
                );
            }
        }

        // Conditionally show Import Actions section
        $is_gs_configured = $selected_source === 'google_sheets' &&
                            !empty($this->options['google_sheets_json_key']) &&
                            !empty($this->options['google_sheets_spreadsheet_id']) &&
                            !empty($this->options['google_sheets_sheet_name']);

        $is_at_configured = $selected_source === 'airtable' &&
                            !empty($this->options['wpi_airtable_pat']) &&
                            !empty($this->options['wpi_airtable_base_id']) &&
                            !empty($this->options['wpi_airtable_table_name']);

        if ( ($is_gs_configured || $is_at_configured) && $mappings_exist ) {
            add_settings_section(
                'wpi_import_actions_section',
                __( 'Product Import Actions', 'woocommerce-product-importer' ),
                null,
                $this->option_group
            );
            add_settings_field(
                'wpi_start_import_button',
                __( 'Start Import', 'woocommerce-product-importer' ),
                [ $this, 'render_start_import_button_field' ],
                $this->option_group,
                'wpi_import_actions_section'
            );
        }
    }

    public function sanitize_options( $input ) {
        $current_options = get_option( $this->option_name, [] );
        $sanitized_input = [];

        $selected_source = isset($input['selected_source']) ? sanitize_text_field($input['selected_source']) : (isset($current_options['selected_source']) ? $current_options['selected_source'] : '');
        $sanitized_input['selected_source'] = $selected_source;

        foreach(['google_sheets_json_key', 'google_sheets_spreadsheet_id', 'google_sheets_sheet_name'] as $key){
            if(array_key_exists($key, $input)){ // Check if key exists, even if empty, to allow clearing
                $sanitized_input[$key] = sanitize_text_field(trim($input[$key]));
            } elseif (isset($current_options[$key])) { // Preserve if not in this submission
                $sanitized_input[$key] = $current_options[$key];
            }
        }
        foreach(['wpi_airtable_pat', 'wpi_airtable_base_id', 'wpi_airtable_table_name'] as $key){
             if(array_key_exists($key, $input)){
                $sanitized_input[$key] = sanitize_text_field(trim($input[$key]));
            } elseif (isset($current_options[$key])) {
                $sanitized_input[$key] = $current_options[$key];
            }
        }

        foreach(['google_sheets_headers', 'airtable_headers'] as $key){
            if (isset($current_options[$key]) && is_array($current_options[$key])) {
                 $sanitized_input[$key] = array_map('sanitize_text_field', $current_options[$key]);
            } elseif (isset($input[$key]) && is_array($input[$key])) { // Should not happen via form, but good to cover
                 $sanitized_input[$key] = array_map('sanitize_text_field', $input[$key]);
            }
        }

        if ( $selected_source ) {
            $mapping_key_prefix = $selected_source . '_mappings';
            $sanitized_input[$mapping_key_prefix] = isset($current_options[$mapping_key_prefix]) && is_array($current_options[$mapping_key_prefix])
                                                    ? $current_options[$mapping_key_prefix]
                                                    : []; // Initialize with current or empty array

            // Only update mappings if they are actually submitted (i.e., mapping section was visible)
            if(isset($input[$mapping_key_prefix]) && is_array($input[$mapping_key_prefix])){
                foreach ($this->defined_woo_fields as $woo_field_key => $label) {
                    if ( isset( $input[$mapping_key_prefix][$woo_field_key] ) ) {
                        $sanitized_input[$mapping_key_prefix][$woo_field_key] = sanitize_text_field( $input[$mapping_key_prefix][$woo_field_key] );
                    } elseif ( !isset($current_options[$mapping_key_prefix][$woo_field_key]) ){
                        // If not in input and not in current options (e.g. new field), set to empty
                        $sanitized_input[$mapping_key_prefix][$woo_field_key] = '';
                    }
                    // If not in input but in current_options, it's already preserved by initializing $sanitized_input[$mapping_key_prefix]
                }
            }
        }
        return array_merge($current_options, $sanitized_input);
    }

    public function render_data_source_section_text() { /* ... */ }
    public function render_data_source_field() { /* ... */ }
    public function render_google_sheets_settings_section_text() { /* ... */ }
    public function render_google_sheets_json_key_field() { /* ... */ }
    public function render_google_sheets_connection_actions_field() { /* ... */ }
    public function render_google_sheets_data_location_section_text() { /* ... */ }
    public function render_google_sheets_spreadsheet_id_field() { /* ... */ }
    public function render_google_sheets_sheet_name_field() { /* ... */ }
    public function render_google_sheets_fetch_sample_button_field() { /* ... */ }
    public function render_airtable_settings_section_text() { /* ... */ }
    public function render_airtable_pat_field() { /* ... */ }
    public function render_airtable_base_id_field() { /* ... */ }
    public function render_airtable_table_name_field() { /* ... */ }
    public function render_airtable_connection_actions_field() { /* ... */ }
    public function render_airtable_fetch_sample_button_field() { /* ... */ }
    public function render_field_mapping_section_text() { /* ... */ }
    public function render_mapping_dropdown_field( $args ) { /* ... */ }
    // Ensure the above stubs are replaced with their full implementations from previous steps.
    // For brevity in this diff, I'm only showing the new/changed methods fully.


    public function render_start_import_button_field() {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="wpi_start_import">
            <?php wp_nonce_field( 'wpi_start_import_nonce', 'wpi_start_import_nonce_field' ); ?>
            <?php submit_button( __( 'Start Product Import', 'woocommerce-product-importer' ), 'primary', 'wpi_start_import_submit', false ); ?>
            <p class="description"><?php esc_html_e('Ensure all settings and mappings are saved before starting the import. This may take some time depending on the amount of data.', 'woocommerce-product-importer'); ?></p>
        </form>
        <?php
    }

    public function handle_start_import() {
        if ( ! isset( $_POST['wpi_start_import_nonce_field'] ) || ! wp_verify_nonce( sanitize_key($_POST['wpi_start_import_nonce_field']), 'wpi_start_import_nonce' ) ) {
            wp_die( __( 'Security check failed.', 'woocommerce-product-importer' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'woocommerce-product-importer' ) );
        }

        $options = get_option( $this->option_name, [] );
        $selected_source = isset( $options['selected_source'] ) ? $options['selected_source'] : '';
        $source_mappings_key = $selected_source . '_mappings';
        $source_headers_key = $selected_source . '_headers';

        $mappings = isset( $options[$source_mappings_key] ) ? $options[$source_mappings_key] : [];
        $headers = isset( $options[$source_headers_key] ) ? $options[$source_headers_key] : [];

        $results = ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
        $all_data_rows = [];

        if ( empty($selected_source) || empty($mappings) || empty($headers) ) {
            $results['errors'][] = __( 'Import configuration incomplete (source, mappings, or headers missing). Please configure and save settings.', 'woocommerce-product-importer' );
            set_transient( 'wpi_import_status', $results, 300 );
            wp_redirect( admin_url( 'admin.php?page=wpi_settings' ) );
            exit;
        }

        // 1. Fetch Data
        try {
            if ( $selected_source === 'google_sheets' ) {
                $json_key = isset( $options['google_sheets_json_key'] ) ? trim($options['google_sheets_json_key']) : '';
                $spreadsheet_id = isset( $options['google_sheets_spreadsheet_id'] ) ? trim($options['google_sheets_spreadsheet_id']) : '';
                $sheet_name = isset( $options['google_sheets_sheet_name'] ) ? trim($options['google_sheets_sheet_name']) : '';
                if (empty($json_key) || empty($spreadsheet_id) || empty($sheet_name)) throw new Exception('Google Sheets configuration missing.');

                $service = new WPI_Google_Sheets_Service( $json_key );
                $fetched_data = $service->get_sheet_data( $spreadsheet_id, $sheet_name );
                if (is_array($fetched_data) && !empty($fetched_data)) {
                    if(count($fetched_data) > 0 && is_array($options['google_sheets_headers']) && count($options['google_sheets_headers']) > 0){
                         // Check if first row of fetched_data matches headers
                         $first_row_values = array_map('trim', (array) $fetched_data[0]);
                         if (count($first_row_values) === count($options['google_sheets_headers'])) {
                            $diff = array_diff($first_row_values, $options['google_sheets_headers']);
                            if (empty($diff)) { // First row is indeed the header
                                array_shift($fetched_data);
                            }
                         }
                    }
                    $all_data_rows = $fetched_data;
                }
            } elseif ( $selected_source === 'airtable' ) {
                $pat = isset( $options['wpi_airtable_pat'] ) ? trim($options['wpi_airtable_pat']) : '';
                $base_id = isset( $options['wpi_airtable_base_id'] ) ? trim($options['wpi_airtable_base_id']) : '';
                $table_name = isset( $options['wpi_airtable_table_name'] ) ? trim($options['wpi_airtable_table_name']) : '';
                if (empty($pat) || empty($base_id) || empty($table_name)) throw new Exception('Airtable configuration missing.');

                $service = new WPI_Airtable_Service( $pat, $base_id, $table_name );
                $fetched_records = $service->get_records();
                if (is_array($fetched_records)) {
                    foreach ($fetched_records as $record) {
                        $all_data_rows[] = $record['fields'];
                    }
                }
            }
        } catch (Exception $e) {
            $results['errors'][] = "Error fetching data: " . $e->getMessage();
            error_log("[WPI Import] Fetch Data Error: " . $e->getMessage());
            set_transient( 'wpi_import_status', $results, 300 );
            wp_redirect( admin_url( 'admin.php?page=wpi_settings' ) );
            exit;
        }

        if (empty($all_data_rows)) {
            $results['errors'][] = __( 'No data rows found to import from the source after potentially removing header.', 'woocommerce-product-importer' );
        } else {
            if (!class_exists('WPI_WooCommerce_Product_Manager')) {
                 $results['errors'][] = "WPI_WooCommerce_Product_Manager class not found.";
            } else {
                $product_manager = new WPI_WooCommerce_Product_Manager();
                $header_map_flipped = array_flip($headers);

                foreach ($all_data_rows as $row_index => $row_data_array_or_obj) {
                    $results['processed']++;
                    $product_data_to_import = [];
                    $source_row_data = ($selected_source === 'google_sheets') ? $row_data_array_or_obj : $row_data_array_or_obj; // GS is indexed, AT is associative 'fields'

                    foreach ($this->defined_woo_fields as $woo_key => $woo_label) {
                        $mapped_source_column_name = isset($mappings[$woo_key]) ? $mappings[$woo_key] : null;
                        if ($mapped_source_column_name) {
                            if ($selected_source === 'google_sheets') {
                                $column_index = isset($header_map_flipped[$mapped_source_column_name]) ? (int) $header_map_flipped[$mapped_source_column_name] : -1;
                                if ($column_index !== -1 && isset($source_row_data[$column_index])) {
                                    $product_data_to_import[$woo_key] = $source_row_data[$column_index];
                                }
                            } elseif ($selected_source === 'airtable') {
                                if (isset($source_row_data[$mapped_source_column_name])) {
                                    $product_data_to_import[$woo_key] = $source_row_data[$mapped_source_column_name];
                                }
                            }
                        }
                    }

                    if(isset($product_data_to_import['attributes_json']) && is_string($product_data_to_import['attributes_json'])) {
                        $decoded_attrs = json_decode($product_data_to_import['attributes_json'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_attrs)) {
                            $product_data_to_import['attributes'] = $decoded_attrs;
                        } else {
                            $results['failed']++; $results['errors'][] = "Row " . ($row_index + 2) . ": Invalid JSON in Attributes column. Error: " . json_last_error_msg(); // +2 because header might be skipped
                            unset($product_data_to_import['attributes_json']); continue;
                        }
                        unset($product_data_to_import['attributes_json']);
                    } else { $product_data_to_import['attributes'] = []; }

                    if(isset($product_data_to_import['variations_json']) && is_string($product_data_to_import['variations_json'])) {
                        $decoded_vars = json_decode($product_data_to_import['variations_json'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_vars)) {
                            $product_data_to_import['variations'] = $decoded_vars;
                        } else {
                            $results['failed']++; $results['errors'][] = "Row " . ($row_index + 2) . ": Invalid JSON in Variations column. Error: " . json_last_error_msg();
                             unset($product_data_to_import['variations_json']); continue;
                        }
                         unset($product_data_to_import['variations_json']);
                    } else { $product_data_to_import['variations'] = []; }

                    if (empty($product_data_to_import['sku'])) {
                        $results['failed']++; $results['errors'][] = "Row " . ($row_index + 2) . ": Missing SKU after mapping."; continue;
                    }

                    if(isset($product_data_to_import['product_name'])) {
                        $product_data_to_import['name'] = $product_data_to_import['product_name'];
                        unset($product_data_to_import['product_name']);
                    }

                    $existing_product_id = wc_get_product_id_by_sku( $product_data_to_import['sku'] );
                    $import_result = $product_manager->create_update_variable_product( $product_data_to_import );

                    if (is_wp_error($import_result)) {
                        $results['failed']++; $results['errors'][] = "Row " . ($row_index + 2) . " (SKU: " . $product_data_to_import['sku'] . "): " . $import_result->get_error_message();
                    } else {
                        if ($existing_product_id && $import_result == $existing_product_id) { $results['updated']++; }
                        else { $results['created']++; }
                    }
                }
            }
        }

        set_transient( 'wpi_import_status', $results, 300 );
        wp_redirect( admin_url( 'admin.php?page=wpi_settings' ) );
        exit;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $this->options = get_option( $this->option_name, [] );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php
            $gs_connection_status = get_transient( 'wpi_gs_connection_status' );
            if ( $gs_connection_status ) { echo '<div class="notice notice-' . esc_attr( $gs_connection_status['type'] ) . ' is-dismissible"><p>' . esc_html( $gs_connection_status['message'] ) . '</p></div>'; delete_transient( 'wpi_gs_connection_status' );}
            $gs_sample_status = get_transient( 'wpi_gs_fetch_sample_status' );
            if ( $gs_sample_status ) { /* ... GS sample display ... */ } // Assuming this part is filled from previous step
            $at_connection_status = get_transient( 'wpi_at_connection_status' );
            if ( $at_connection_status ) { echo '<div class="notice notice-' . esc_attr( $at_connection_status['type'] ) . ' is-dismissible"><p>' . esc_html( $at_connection_status['message'] ) . '</p></div>'; delete_transient( 'wpi_at_connection_status' );}
            $at_sample_status = get_transient( 'wpi_at_fetch_sample_status' );
            if ( $at_sample_status ) { /* ... AT sample display ... */ } // Assuming this part is filled from previous step

            $import_status = get_transient( 'wpi_import_status' );
            if ( $import_status ) {
                $notice_type = ($import_status['failed'] > 0 || !empty($import_status['errors'])) && $import_status['processed'] > 0 ? 'warning' : ($import_status['processed'] > 0 ? 'success' : 'error');
                if ($import_status['processed'] === 0 && empty($import_status['errors']) && $import_status['failed'] === 0) $notice_type = 'info'; // e.g. no data rows found

                echo '<div class="notice notice-' . esc_attr( $notice_type ) . ' is-dismissible">';
                echo '<p><strong>' . esc_html__( 'Import Status:', 'woocommerce-product-importer' ) . '</strong></p>';
                echo '<p>' . sprintf(esc_html__('Processed: %d, Created: %d, Updated: %d, Failed: %d', 'woocommerce-product-importer'),
                    isset($import_status['processed']) ? $import_status['processed'] : 0,
                    isset($import_status['created']) ? $import_status['created'] : 0,
                    isset($import_status['updated']) ? $import_status['updated'] : 0,
                    isset($import_status['failed']) ? $import_status['failed'] : 0
                ) . '</p>';
                if (!empty($import_status['errors'])) {
                    echo '<strong>' . esc_html__('Errors/Warnings:', 'woocommerce-product-importer') . '</strong>';
                    echo '<ul style="list-style-type: disc; margin-left: 20px;">';
                    foreach($import_status['errors'] as $error) {
                        echo '<li>' . esc_html($error) . '</li>';
                    }
                    echo '</ul>';
                }
                echo '</div>';
                delete_transient( 'wpi_import_status' );
            }
            ?>
            <form action="options.php" method="post">
                <?php
                settings_fields( $this->option_group );
                do_settings_sections( $this->option_group );

                $button_text = __( 'Save Settings', 'woocommerce-product-importer' );
                if (empty($this->options['selected_source'])) {
                    $button_text = __( 'Save Source Choice', 'woocommerce-product-importer' );
                }
                // The Start Import button is part of a section rendered by do_settings_sections
                // The main form's submit button is for saving settings.
                submit_button( $button_text );
                ?>
            </form>
        </div>
        <?php
    }
    // Placeholder for all other methods that should be present from previous steps
    // Make sure to copy them here for the actual overwrite
}
```
