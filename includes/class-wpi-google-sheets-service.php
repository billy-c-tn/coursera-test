<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Assume Google API Client Library is loaded via autoloader (e.g., Composer)
// If not, explicit require_once statements for Google_Client, Google_Service_Sheets etc. would be needed here,
// which is complex without the actual library structure.

class WPI_Google_Sheets_Service {

    private $service_account_json_key;
    private $google_client = null;
    private $sheets_service = null;

    /**
     * Constructor.
     *
     * @param string $json_key_string The content of the Service Account JSON key file.
     * @throws Exception If JSON key is invalid or Google Client cannot be initialized.
     */
    public function __construct( $json_key_string ) {
        if ( empty( $json_key_string ) ) {
            throw new Exception( __( 'Service Account JSON key is missing.', 'woocommerce-product-importer' ) );
        }
        $this->service_account_json_key = $json_key_string;

        if ( ! class_exists( 'Google_Client' ) ) {
            throw new Exception( __( 'Google API Client Library is not loaded. Please ensure it is installed and autoloaded (e.g., via Composer).', 'woocommerce-product-importer' ) );
        }

        $this->initialize_client();
    }

    /**
     * Initializes the Google Client.
     *
     * @throws Exception If authentication fails.
     */
    private function initialize_client() {
        try {
            $this->google_client = new Google_Client();

            $credentials = json_decode( $this->service_account_json_key, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                throw new Exception( __( 'Invalid Service Account JSON key format.', 'woocommerce-product-importer' ) . ' ' . json_last_error_msg() );
            }

            $this->google_client->setAuthConfig( $credentials );
            $this->google_client->addScope( Google_Service_Sheets::SPREADSHEETS_READONLY );
            $this->google_client->setApplicationName( 'WooCommerce Product Importer' );
            $this->google_client->setSubject(null);

        } catch ( Exception $e ) {
            error_log('[WPI] Google Client Initialization Error: ' . $e->getMessage());
            throw new Exception( __( 'Failed to initialize Google Client:', 'woocommerce-product-importer' ) . ' ' . $e->getMessage() );
        }
    }

    /**
     * Get the initialized Google Client.
     *
     * @return Google_Client|null
     */
    public function get_client() {
        return $this->google_client;
    }

    /**
     * Get the Google Sheets Service.
     *
     * @return Google_Service_Sheets|null
     * @throws Exception If client not initialized.
     */
    public function get_sheets_service() {
        if ( ! $this->google_client ) {
            throw new Exception( __( 'Google Client not initialized.', 'woocommerce-product-importer' ) );
        }
        if ( ! $this->sheets_service ) {
            if ( ! class_exists('Google_Service_Sheets') ) {
                 throw new Exception( __( 'Google_Service_Sheets class not found. Library might be incomplete.', 'woocommerce-product-importer' ) );
            }
            $this->sheets_service = new Google_Service_Sheets( $this->google_client );
        }
        return $this->sheets_service;
    }

    /**
     * Tests the connection by relying on successful client initialization.
     *
     * @return bool True on success, throws Exception on failure (though constructor handles most init errors).
     * @throws Exception If connection fails (e.g. client not available).
     */
    public function test_connection() {
        try {
            // If constructor and get_sheets_service() succeed without exceptions,
            // consider the basic connection established.
            $this->get_sheets_service();
            return true;

        } catch ( Exception $e ) {
            error_log('[WPI] Google Sheets Test Connection Error: ' . $e->getMessage());
            throw new Exception( __( 'Google Sheets connection test failed:', 'woocommerce-product-importer' ) . ' ' . $e->getMessage() );
        }
    }

    /**
     * Get spreadsheet details including sheet names.
     * @param string $spreadsheet_id
     * @return Google_Service_Sheets_Spreadsheet
     * @throws Exception
     */
    public function get_spreadsheet_details( $spreadsheet_id ) {
        if ( empty( $spreadsheet_id ) ) {
            throw new Exception( __( 'Spreadsheet ID is required.', 'woocommerce-product-importer' ) );
        }
        try {
            $service = $this->get_sheets_service();
            return $service->spreadsheets->get( $spreadsheet_id );
        } catch ( Exception $e ) {
            error_log( '[WPI] Error getting spreadsheet details for ID ' . $spreadsheet_id . ': ' . $e->getMessage() );
            throw new Exception( sprintf( __( 'Error getting spreadsheet details: %s', 'woocommerce-product-importer' ), $e->getMessage() ) );
        }
    }

    /**
     * Fetches data from a specific sheet within a spreadsheet.
     *
     * @param string $spreadsheet_id The ID of the spreadsheet.
     * @param string $sheet_name_or_range     The name of the sheet (e.g., "Sheet1") or a range (e.g. "Sheet1!A1:D10").
     * @return array An array of rows, where each row is an array of cell values. Returns null if no data.
     * @throws Exception If fetching fails.
     */
    public function get_sheet_data( $spreadsheet_id, $sheet_name_or_range ) {
        if ( empty( $spreadsheet_id ) ) {
            throw new Exception( __( 'Spreadsheet ID is required.', 'woocommerce-product-importer' ) );
        }
        if ( empty( $sheet_name_or_range ) ) {
            throw new Exception( __( 'Sheet name or range is required.', 'woocommerce-product-importer' ) );
        }

        try {
            $service = $this->get_sheets_service();
            $response = $service->spreadsheets_values->get( $spreadsheet_id, $sheet_name_or_range );
            $values = $response->getValues();
            return !empty($values) ? $values : null; // Return null if sheet/range is empty but accessible
        } catch ( Exception $e ) {
            $message = $e->getMessage();
            error_log( '[WPI] Error fetching sheet data for ' . $spreadsheet_id . ' (' . $sheet_name_or_range . '): ' . $message );

            // Check for common user-facing errors
            if (strpos($message, 'Requested entity was not found') !== false) {
                 throw new Exception( __( 'Spreadsheet or Sheet/Range not found. Please check your IDs/names and ensure the Service Account has access.', 'woocommerce-product-importer' ));
            } elseif (strpos($message, 'does not have permissions') !== false) { // This is a common substring
                 throw new Exception( __( 'Permission denied. Please ensure the Service Account email has been granted viewer (or editor) access to this Google Sheet.', 'woocommerce-product-importer' ));
            } elseif (strpos($message, 'Unable to parse range') !== false) {
                 throw new Exception( __( 'Invalid sheet name or range format. Please check your input (e.g., Sheet1 or Sheet1!A1:Z).', 'woocommerce-product-importer' ));
            }
            throw new Exception( sprintf( __( 'Error fetching Google Sheet data: %s', 'woocommerce-product-importer' ), $message ) );
        }
    }
}
?>
