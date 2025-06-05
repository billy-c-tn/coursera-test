<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPI_Airtable_Service {

    private $personal_access_token;
    private $base_id;
    private $table_name_or_id;
    private $api_url_base = 'https://api.airtable.com/v0/';

    /**
     * Constructor.
     *
     * @param string $pat      Airtable Personal Access Token.
     * @param string $base_id  Airtable Base ID.
     * @param string $table_name_or_id Airtable Table Name or ID.
     * @throws Exception If any required credential is missing.
     */
    public function __construct( $pat, $base_id, $table_name_or_id ) {
        if ( empty( $pat ) ) {
            throw new Exception( __( 'Airtable Personal Access Token is missing.', 'woocommerce-product-importer' ) );
        }
        if ( empty( $base_id ) ) {
            throw new Exception( __( 'Airtable Base ID is missing.', 'woocommerce-product-importer' ) );
        }
        if ( empty( $table_name_or_id ) ) {
            throw new Exception( __( 'Airtable Table Name/ID is missing.', 'woocommerce-product-importer' ) );
        }

        $this->personal_access_token = $pat;
        $this->base_id = $base_id;
        $this->table_name_or_id = $table_name_or_id;
    }

    /**
     * Makes a request to the Airtable API.
     *
     * @param string $endpoint Specific table name/id for this request (usually $this->table_name_or_id).
     * @param array  $params   Query parameters for the request (e.g., maxRecords, view).
     * @param string $method   HTTP method (GET, POST, PATCH, DELETE).
     * @param array  $body     Request body for POST/PATCH.
     * @return array|WP_Error Decoded JSON response or WP_Error on failure.
     */
    private function make_request( $endpoint, $params = [], $method = 'GET', $body = [] ) {
        $url = $this->api_url_base . rawurlencode( $this->base_id ) . '/' . rawurlencode( $endpoint );

        if ( ! empty( $params ) ) {
            $url .= '?' . http_build_query( $params );
        }

        $request_args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->personal_access_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ( ! empty( $body ) && ($method === 'POST' || $method === 'PATCH') ) {
            $request_args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            error_log( '[WPI] Airtable API Request Error: ' . $response->get_error_message() );
            return $response;
        }

        $response_body = wp_remote_retrieve_body( $response );
        $response_code = wp_remote_retrieve_response_code( $response );
        $decoded_body = json_decode( $response_body, true );

        if ( $response_code >= 400 ) {
            $error_message = __( 'Unknown Airtable API error.', 'woocommerce-product-importer');
            $error_type = 'UNKNOWN_ERROR';
            if ( isset( $decoded_body['error'] ) ) {
                if ( is_array( $decoded_body['error'] ) ) {
                    $error_type = isset( $decoded_body['error']['type'] ) ? $decoded_body['error']['type'] : 'UNKNOWN_API_ERROR';
                    $error_message = isset( $decoded_body['error']['message'] ) ? $decoded_body['error']['message'] : $error_message;
                } elseif ( is_string( $decoded_body['error'] ) ) {
                    $error_message = $decoded_body['error']; // Sometimes it's just a string
                     $error_type = 'STRING_ERROR_RESPONSE';
                }
            }

            error_log( sprintf(
                '[WPI] Airtable API Error: Code %s, Type: %s, Message: %s, Endpoint: %s',
                $response_code,
                $error_type,
                $error_message,
                $endpoint
            ) );
            return new WP_Error( 'airtable_api_error', $error_message, [ 'status' => $response_code, 'type' => $error_type ] );
        }

        return $decoded_body;
    }

    /**
     * Tests the connection by fetching a single record from the table.
     *
     * @return bool True on success.
     * @throws Exception On failure.
     */
    public function test_connection() {
        $response = $this->make_request( $this->table_name_or_id, [ 'maxRecords' => 1, 'pageSize' => 1 ] );
        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }
        // If no WP_Error, the request was syntactically valid and authorized.
        // It might return an empty 'records' array if the table is empty, which is still a success for connection test.
        return true;
    }

    /**
     * Fetches records from the configured Airtable table.
     *
     * @param array $params Query parameters (e.g., maxRecords, view, offset, fields).
     * @return array List of records.
     * @throws Exception On failure.
     */
    public function get_records( $params = [] ) {
        $default_params = [
            // 'pageSize' => 100, // Airtable default, max 100
        ];
        $request_params = array_merge($default_params, $params);

        $response = $this->make_request( $this->table_name_or_id, $request_params );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        // TODO: Implement pagination handling if more than 'pageSize' records are needed for full sync.
        // For now, this fetches one page of records.
        return isset( $response['records'] ) ? $response['records'] : [];
    }
}
?>
