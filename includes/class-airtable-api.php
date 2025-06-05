<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Airtable_API {
    private $api_key;
    private $base_id;
    private $api_url = 'https://api.airtable.com/v0/';

    public function __construct( $api_key, $base_id ) {
        $this->api_key = $api_key;
        $this->base_id = $base_id;
    }

    private function make_request( $endpoint, $params = [] ) {
        $url = $this->api_url . $this->base_id . '/' . rawurlencode($endpoint);

        if ( ! empty( $params ) ) {
            $url .= '?' . http_build_query( $params );
        }

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ];

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( '[Airtable WooCommerce Sync] Airtable API Request Error: ' . $response->get_error_message() );
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        $http_code = wp_remote_retrieve_response_code( $response );

        if ( $http_code !== 200 ) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown Airtable API error';
            $error_type = isset($data['error']['type']) ? $data['error']['type'] : 'UNKNOWN_ERROR_TYPE';
            error_log( sprintf(
                '[Airtable WooCommerce Sync] Airtable API Error: Code %s, Type: %s, Message: %s, Endpoint: %s',
                $http_code,
                $error_type,
                $error_message,
                $endpoint
            ) );
            return new WP_Error( 'airtable_api_error', $error_message, [ 'status' => $http_code, 'type' => $error_type ] );
        }

        return $data;
    }

    public function get_records( $table_name, $params = [] ) {
        $response = $this->make_request( $table_name, $params );

        if ( is_wp_error( $response ) ) {
            // Error already logged in make_request
            return $response;
        }

        return isset( $response['records'] ) ? $response['records'] : [];
    }
}
