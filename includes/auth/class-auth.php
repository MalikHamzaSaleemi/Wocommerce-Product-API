<?php
class Dialogpay_API_Auth {
    private $secret_key;

    public function __construct() {
        $this->secret_key = get_option( 'dialogpay_auth_secret_key', 'default-secret-key' );
    }

    public function authenticate_user( WP_REST_Request $request ) {
        $provided_key = $request->get_param( 'secret_key' );

        if ( $provided_key === $this->secret_key ) {
            // Token expiry
            $expiry_time = time() + (24 * 60 * 60); // Token valid for 24 hours
            $user_remote_id = $_SERVER['REMOTE_ADDR'];

            $token_payload = json_encode( [
                'key'            => $this->secret_key,
                'timestamp'      => time(),
                'expires'        => $expiry_time,
                'user_remote_id' => $user_remote_id,
            ]);

            $token_signature = hash_hmac( 'sha256', $token_payload, $this->secret_key );
            $token = base64_encode( json_encode( [
                'payload'   => $token_payload,
                'signature' => $token_signature,
            ]));

            // Invalidate old tokens
            $this->invalidate_old_tokens();

            // Store the new token
            set_transient( 'dialogpay_active_token', $token, $expiry_time - time() );

            return rest_ensure_response([
                'success' => true,
                'token'   => $token,
            ]);
        }

        return rest_ensure_response([
            'success' => false,
            'message' => 'Authentication failed. Invalid secret key.',
        ]);
    }

    public function verify_token( WP_REST_Request $request ) {
        $token = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : null;

        if ( ! $token || ! preg_match( '/Bearer\s+(.*)$/i', $token, $matches ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Token is missing or malformed.' ), [ 'status' => 401 ] );
        }

        $token = $matches[1];
        $active_token = get_transient( 'dialogpay_active_token' );

        if ( $token !== $active_token ) {
            return new WP_Error( 'rest_forbidden', __( 'Token is invalid or has been revoked.' ), [ 'status' => 401 ] );
        }

        $decoded_token = json_decode( base64_decode( $token ), true );
        if ( ! isset( $decoded_token['payload'], $decoded_token['signature'] ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Malformed token.' ), [ 'status' => 401 ] );
        }

        $payload = json_decode( $decoded_token['payload'], true );
        $signature = $decoded_token['signature'];

        // Verify the token signature
        $expected_signature = hash_hmac( 'sha256', $decoded_token['payload'], $this->secret_key );
        if ( ! hash_equals( $expected_signature, $signature ) ) 
        {
            return new WP_Error( 'rest_forbidden', __( 'Invalid token signature.' ), [ 'status' => 401 ] );
        }

        // Verify token expiry 
        if ( time() > $payload['expires'] ) 
        {
            return new WP_Error( 'rest_forbidden', __( 'Token has expired.' ), [ 'status' => 401 ] );
        }

        // Verify user remote ID
        if ( $payload['user_remote_id'] !== $_SERVER['REMOTE_ADDR'] ) {
            return new WP_Error( 'rest_forbidden', __( 'Token is not valid for this user.' ), [ 'status' => 401 ] );
        }

        return true;
    }
    private function invalidate_old_tokens() {
        delete_transient( 'dialogpay_active_token' );
    }


}
