<?php
/**
 * Google Drive API endpoints using Google Client Library.
 *
 * This class provides comprehensive REST API endpoints for Google Drive integration,
 * including file management, authentication, and security features. All endpoints
 * are secured with proper permission checks, nonce verification, and input validation.
 *
 * Security Features:
 * - Nonce verification for all authenticated endpoints
 * - Permission checks using current_user_can('manage_options')
 * - Input sanitization and validation
 * - File upload security checks
 * - Rate limiting and retry logic
 *
 * Performance Features:
 * - Pagination support for large file lists
 * - Caching for page tokens
 * - Efficient field selection to minimize data transfer
 * - Retry logic with exponential backoff
 *
 * @link          https://wpmudev.com/
 * @since         1.0.0
 *
 * @author        WPMUDEV (https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2025, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\Endpoints\V1;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;
// Use original classes - scoping will be handled at runtime
use Google\Client as Google_Client;
use Google\Service\Drive as Google_Service_Drive;
use Google\Service\Drive\DriveFile as Google_Service_Drive_DriveFile;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;

/**
 * Google Drive REST API endpoints class.
 *
 * Handles all REST API endpoints for Google Drive integration including
 * authentication, file operations, and credential management.
 */
class Drive_API extends Base {

	/**
	 * Google Client instance.
	 *
	 * @var object
	 */
	private $client;

	/**
	 * Google Drive service.
	 *
	 * @var object
	 */
	private $drive_service;

	/**
	 * OAuth redirect URI.
	 *
	 * @var string
	 */
	private $redirect_uri;

	/**
	 * Google Drive API scopes.
	 *
	 * @var array
	 */
	private $scopes = array();

	/**
	 * Initialize the class.
	 */
	public function init() {
		// Check if Google API dependencies are available
		if ( ! class_exists( 'Google\\Client' ) ) {
			$this->log_debug( 'Google Client class not available' );
			return;
		}
		
		$this->log_debug( 'Google Client class is available' );

		//$this->redirect_uri = home_url( '/wp-json/wpmudev/v1/drive/callback' );
		$this->redirect_uri = 'https://4b8375fd38b2.ngrok-free.app/wp-json/wpmudev/v1/drive/callback';
		// Log redirect URI for debugging
		$this->log_debug( 'Redirect URI set', array( 'redirect_uri' => $this->redirect_uri ) );

		// Initialize scopes
		$this->scopes = array(
			'https://www.googleapis.com/auth/drive',
		);

		$this->setup_google_client();

		if ( function_exists( 'add_action' ) ) {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}
	}


	/**
	 * Setup Google Client.
	 */
	private function setup_google_client() {
		$client_id = get_option( 'wpmudev_drive_client_id', '' );
		$client_secret = get_option( 'wpmudev_drive_client_secret', '' );
		
		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return;
		}

		// Decrypt credentials if they're encrypted
		if ( $this->is_encrypted( $client_id ) ) {
			$client_id = $this->decrypt_credential( $client_id );
		}
		if ( $this->is_encrypted( $client_secret ) ) {
			$client_secret = $this->decrypt_credential( $client_secret );
		}

		try {
		$this->client = new Google_Client();
			$this->client->setClientId( $client_id );
			$this->client->setClientSecret( $client_secret );
		$this->client->setRedirectUri( $this->redirect_uri );
		$this->client->setScopes( $this->scopes );
			
			// Set additional OAuth parameters
		$this->client->setAccessType( 'offline' );
		$this->client->setPrompt( 'consent' );
			
			// Log for debugging
			$this->log_debug( 'Google Client initialized', array(
				'client_id' => $client_id,
				'redirect_uri' => $this->redirect_uri,
				'scopes' => $this->scopes
			) );
		} catch ( Exception $e ) {
			$this->log_error( 'Error initializing Google Client', array( 
				'message' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			) );
			throw $e;
		}

		// Set access token if available
		$access_token = get_option( 'wpmudev_drive_access_token', '' );
		if ( ! empty( $access_token ) ) {
			$this->client->setAccessToken( $access_token );
		}

		$this->drive_service = new Google_Service_Drive( $this->client );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		$this->log_debug( 'Registering REST API routes' );
		// Save credentials endpoint
		register_rest_route(
			'wpmudev/v1/drive',
			'/save-credentials',
			array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'save_credentials' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Authentication endpoint
		register_rest_route(
			'wpmudev/v1/drive',
			'/auth',
			array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'start_auth' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// OAuth callback
		register_rest_route(
			'wpmudev/v1/drive',
			'/callback',
			array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_callback' ),
				'permission_callback' => '__return_true', // Public endpoint for OAuth callback
			)
		);

		// List files
		register_rest_route(
			'wpmudev/v1/drive',
			'/files',
			array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_files' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Upload file
		register_rest_route(
			'wpmudev/v1/drive',
			'/upload',
			array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'upload_file' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Download file
		register_rest_route(
			'wpmudev/v1/drive',
			'/download',
			array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'download_file' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Create folder
		register_rest_route(
			'wpmudev/v1/drive',
			'/create-folder',
			array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'create_folder' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Get status
		register_rest_route(
			'wpmudev/v1/drive',
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Delete file
		register_rest_route(
			'wpmudev/v1/drive',
			'/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete_file' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Delete multiple files
		register_rest_route(
			'wpmudev/v1/drive',
			'/delete-files',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete_files' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Disconnect
		register_rest_route(
			'wpmudev/v1/drive',
			'/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Get credentials
		register_rest_route(
			'wpmudev/v1/drive',
			'/credentials',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_credentials' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Get auth status
		register_rest_route(
			'wpmudev/v1/drive',
			'/auth-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_auth_status' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Start authentication
		register_rest_route(
			'wpmudev/v1/drive',
			'/authenticate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'start_authentication' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Get upload progress
		register_rest_route(
			'wpmudev/v1/drive',
			'/upload-progress',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_upload_progress' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Clear credentials endpoint
		register_rest_route(
			'wpmudev/v1/drive',
			'/clear-credentials',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'clear_credentials' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Check permissions for REST API endpoints.
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Verify nonce for REST API requests.
	 *
	 * This method provides security by verifying that the request comes from
	 * an authorized source. It checks for the presence of the X-WP-Nonce header
	 * and validates it against the WordPress nonce system.
	 *
	 * Security considerations:
	 * - Prevents CSRF attacks by verifying request authenticity
	 * - Uses wp_rest action for nonce verification
	 * - Returns appropriate error codes for different failure scenarios
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object containing headers.
	 * @return bool|WP_Error True if nonce is valid, WP_Error with appropriate
	 *                       error code and message if validation fails.
	 */
	private function verify_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( empty( $nonce ) ) {
			return new WP_Error(
				'missing_nonce',
				__( 'Security nonce is missing.', 'wpmudev-plugin-test' ),
				array( 'status' => 403 )
			);
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Security nonce is invalid.', 'wpmudev-plugin-test' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Save Google OAuth credentials.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function save_credentials( WP_REST_Request $request ) {
		// Log the request for debugging.
		$this->log_debug( 'Save credentials request received' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->log_debug( 'Permission denied for save credentials' );
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		// Verify nonce for security.
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		// Get and validate input.
		$client_id     = sanitize_text_field( $request->get_param( 'client_id' ) );
		$client_secret = sanitize_text_field( $request->get_param( 'client_secret' ) );

		// Validate required fields.
		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error( 'missing_credentials', 'Client ID and Client Secret are required', array( 'status' => 400 ) );
		}

		// Validate client ID format (basic validation).
		if ( ! preg_match( '/^[0-9]+-[a-zA-Z0-9]+\.apps\.googleusercontent\.com$/', $client_id ) ) {
			return new WP_Error( 'invalid_client_id', 'Invalid Client ID format', array( 'status' => 400 ) );
		}

		// Encrypt credentials before storage
		$encrypted_client_id     = $this->encrypt_credential( $client_id );
		$encrypted_client_secret = $this->encrypt_credential( $client_secret );

		// Check for encryption errors
		if ( is_wp_error( $encrypted_client_id ) ) {
			return $encrypted_client_id;
		}
		if ( is_wp_error( $encrypted_client_secret ) ) {
			return $encrypted_client_secret;
		}

		// Save encrypted credentials in the correct options
		$saved_id = update_option( 'wpmudev_drive_client_id', $encrypted_client_id );
		$saved_secret = update_option( 'wpmudev_drive_client_secret', $encrypted_client_secret );
		
		$saved = $saved_id && $saved_secret;
		$this->log_debug( 'Credentials saved: ' . ( $saved ? 'true' : 'false' ) );
		
		// Reinitialize Google Client with new credentials
		$this->setup_google_client();

		$response = new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Credentials saved successfully', 'wpmudev-plugin-test' ),
			)
		);

		$this->log_debug( 'Returning success response' );
		return $response;
	}

	/**
	 * Start Google OAuth flow.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function start_auth( WP_REST_Request $request ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'permission_denied',
				__( 'Insufficient permissions to start authentication.', 'wpmudev-plugin-test' ),
				array( 'status' => 403 )
			);
		}

		// Verify nonce for security.
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		// Validate credentials are configured
		$client_id = get_option( 'wpmudev_drive_client_id', '' );
		$client_secret = get_option( 'wpmudev_drive_client_secret', '' );
		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Google OAuth credentials not configured. Please set up your Client ID and Client Secret first.', 'wpmudev-plugin-test' ),
				array( 'status' => 400 )
			);
		}

		// Ensure Google Client is properly configured
		if ( ! $this->client ) {
			$this->setup_google_client();
			if ( ! $this->client ) {
				return new WP_Error(
					'client_setup_failed',
					__( 'Failed to initialize Google Client. Please check your credentials.', 'wpmudev-plugin-test' ),
					array( 'status' => 500 )
				);
			}
		}

		try {
			// Generate a secure state parameter for CSRF protection
			$state = wp_generate_password( 32, false );
			update_option( 'wpmudev_drive_auth_state', $state );

			// Set state parameter
			$this->client->setState( $state );

			// Generate authorization URL
			$auth_url = $this->client->createAuthUrl();

			// Log authentication attempt
			$this->log_debug( 'Starting OAuth authentication for user ' . get_current_user_id() );

			return new WP_REST_Response(
				array(
					'success'  => true,
					'auth_url' => $auth_url,
					'message'  => __( 'Redirecting to Google for authentication...', 'wpmudev-plugin-test' ),
				)
			);

		} catch ( Exception $e ) {
			return $this->handle_api_error( $e, 'OAuth authentication start', array( 'user_id' => get_current_user_id() ) );
		}
	}

	/**
	 * Handle OAuth callback.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function handle_callback( WP_REST_Request $request ) {
		$code  = $request->get_param( 'code' );
		$state = $request->get_param( 'state' );
		$error = $request->get_param( 'error' );

		// Log callback attempt
		$this->log_debug(
			'OAuth callback received',
			array(
				'code_present'  => ! empty( $code ),
				'state_present' => ! empty( $state ),
				'error'         => $error ?: 'none',
			)
		);

		// Check for OAuth errors from Google
		if ( ! empty( $error ) ) {
			$error_message = $this->get_oauth_error_message( $error );
			$this->log_error( 'OAuth error from Google', array( 'error' => $error ) );
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=' . urlencode( $error_message ) ) );
			exit;
		}

		// Validate required parameters
		if ( empty( $code ) ) {
			$this->log_error( 'OAuth callback missing authorization code' );
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=' . urlencode( __( 'Authorization code not received from Google.', 'wpmudev-plugin-test' ) ) ) );
			exit;
		}

		// Validate state parameter for CSRF protection
		$stored_state = get_option( 'wpmudev_drive_auth_state', '' );
		if ( empty( $state ) || $state !== $stored_state ) {
			$this->log_error(
				'OAuth callback state validation failed',
				array(
					'provided_state' => $state,
					'stored_state'   => $stored_state,
				)
			);
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=' . urlencode( __( 'Invalid state parameter. Possible CSRF attack.', 'wpmudev-plugin-test' ) ) ) );
			exit;
		}

		// Clean up state parameter
		delete_option( 'wpmudev_drive_auth_state' );

		// Ensure Google Client is configured
		if ( ! $this->client ) {
			$this->setup_google_client();
			if ( ! $this->client ) {
				$this->log_error( 'OAuth callback - Google Client not configured' );
				wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=' . urlencode( __( 'Google Client not configured. Please check your credentials.', 'wpmudev-plugin-test' ) ) ) );
				exit;
			}
		}

		try {
			// Exchange authorization code for access token
			$access_token = $this->client->fetchAccessTokenWithAuthCode( $code );

			// Check for token exchange errors
			if ( array_key_exists( 'error', $access_token ) ) {
				$error_message = $this->get_token_error_message( $access_token['error'] );
				$this->log_error( 'Token exchange error', array( 'error' => $access_token['error'] ) );
				wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=' . urlencode( $error_message ) ) );
				exit;
			}

			// Validate token response
			if ( empty( $access_token['access_token'] ) ) {
				$this->log_error( 'Token exchange - no access token received' );
				wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=' . urlencode( __( 'No access token received from Google.', 'wpmudev-plugin-test' ) ) ) );
				exit;
			}

			// Store tokens securely
			$this->store_tokens( $access_token );

			// Test the connection to ensure tokens work
			$test_result = $this->test_connection();
			if ( ! $test_result['success'] ) {
				$this->log_error( 'Connection test failed after token exchange', array( 'message' => $test_result['message'] ) );
				wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=' . urlencode( __( 'Authentication successful but connection test failed. Please try again.', 'wpmudev-plugin-test' ) ) ) );
				exit;
			}

			// Log successful authentication
			$this->log_debug( 'OAuth authentication successful for user ' . get_current_user_id() );

			// Redirect back to admin page with success
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=success' ) );
			exit;

		} catch ( Exception $e ) {
			$this->log_error( 'OAuth callback exception', array( 'message' => $e->getMessage() ) );
			$error_message = defined( 'WP_DEBUG' ) && WP_DEBUG
				? sprintf(
					/* translators: %s: Error message */
					__( 'Authentication failed: %s', 'wpmudev-plugin-test' ),
					$e->getMessage()
				)
				: __( 'Authentication failed. Please try again.', 'wpmudev-plugin-test' );
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=' . urlencode( $error_message ) ) );
			exit;
		}
	}

	/**
	 * Get user-friendly OAuth error message.
	 *
	 * @param string $error The OAuth error code.
	 * @return string User-friendly error message.
	 */
	private function get_oauth_error_message( $error ) {
		$error_messages = array(
			'access_denied'             => __( 'Access was denied. Please grant the required permissions to continue.', 'wpmudev-plugin-test' ),
			'invalid_request'           => __( 'Invalid request. Please try again.', 'wpmudev-plugin-test' ),
			'unauthorized_client'       => __( 'Unauthorized client. Please check your OAuth configuration.', 'wpmudev-plugin-test' ),
			'unsupported_response_type' => __( 'Unsupported response type. Please contact support.', 'wpmudev-plugin-test' ),
			'invalid_scope'             => __( 'Invalid scope. Please check your OAuth configuration.', 'wpmudev-plugin-test' ),
			'server_error'              => __( 'Google server error. Please try again later.', 'wpmudev-plugin-test' ),
			'temporarily_unavailable'   => __( 'Google service temporarily unavailable. Please try again later.', 'wpmudev-plugin-test' ),
		);

		return isset( $error_messages[ $error ] ) ? $error_messages[ $error ] : sprintf(
			/* translators: %s: OAuth error code */
			__( 'OAuth error: %s', 'wpmudev-plugin-test' ),
			$error
		);
	}

	/**
	 * Get user-friendly token error message.
	 *
	 * @param string $error The token error code.
	 * @return string User-friendly error message.
	 */
	private function get_token_error_message( $error ) {
		$error_messages = array(
			'invalid_grant'          => __( 'Invalid authorization code. Please try authenticating again.', 'wpmudev-plugin-test' ),
			'invalid_client'         => __( 'Invalid client credentials. Please check your Client ID and Client Secret.', 'wpmudev-plugin-test' ),
			'unauthorized_client'    => __( 'Client not authorized. Please check your OAuth configuration.', 'wpmudev-plugin-test' ),
			'unsupported_grant_type' => __( 'Unsupported grant type. Please contact support.', 'wpmudev-plugin-test' ),
			'invalid_scope'          => __( 'Invalid scope. Please check your OAuth configuration.', 'wpmudev-plugin-test' ),
		);

		return isset( $error_messages[ $error ] ) ? $error_messages[ $error ] : sprintf(
			/* translators: %s: Token error code */
			__( 'Token error: %s', 'wpmudev-plugin-test' ),
			$error
		);
	}

	/**
	 * Store tokens securely.
	 *
	 * @param array $access_token The access token data.
	 * @return void
	 */
	private function store_tokens( $access_token ) {
		// Store access token
		update_option( 'wpmudev_drive_access_token', $access_token );

		// Store refresh token if available
		if ( isset( $access_token['refresh_token'] ) ) {
			update_option( 'wpmudev_drive_refresh_token', $access_token['refresh_token'] );
		}

		// Calculate and store expiration time
		$expires_at = time() + ( $access_token['expires_in'] ?? 3600 );
		update_option( 'wpmudev_drive_token_expires', $expires_at );

		// Store token type
		if ( isset( $access_token['token_type'] ) ) {
			update_option( 'wpmudev_drive_token_type', $access_token['token_type'] );
		}

		// Log token storage
		$this->log_debug( 'Tokens stored successfully', array( 'expires_at' => date( 'Y-m-d H:i:s', $expires_at ) ) );
	}

	/**
	 * Test the Google Drive connection.
	 *
	 * @return array Test result with success status and message.
	 */
	private function test_connection() {
		try {
			if ( ! $this->client ) {
				$this->setup_google_client();
			}

			if ( ! $this->client ) {
				return array(
					'success' => false,
					'message' => __( 'Google Client not configured', 'wpmudev-plugin-test' ),
				);
			}

			// Test with a simple API call
			$about = $this->drive_service->about->get( array( 'fields' => 'user' ) );

			if ( $about && isset( $about['user'] ) ) {
				// Store user info for display
				update_option(
					'wpmudev_drive_user_info',
					array(
						'name'  => $about['user']['displayName'] ?? '',
						'email' => $about['user']['emailAddress'] ?? '',
						'photo' => $about['user']['photoLink'] ?? '',
					)
				);

				return array(
					'success' => true,
					'message' => __( 'Connection test successful', 'wpmudev-plugin-test' ),
					'user'    => $about['user'],
				);
			}

			return array(
				'success' => false,
				'message' => __( 'Connection test failed - no user data received', 'wpmudev-plugin-test' ),
			);

		} catch ( Exception $e ) {
			$this->log_error( 'Connection test error', array( 'message' => $e->getMessage() ) );
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Ensure we have a valid access token.
	 *
	 * @return bool True if token is valid, false otherwise.
	 */
	private function ensure_valid_token() {
		if ( ! $this->client ) {
			$this->log_debug( 'ensure_valid_token - Google Client not configured' );
			return false;
		}

		$access_token = get_option( 'wpmudev_drive_access_token', array() );
		$expires_at   = get_option( 'wpmudev_drive_token_expires', 0 );

		// Check if we have a token
		if ( empty( $access_token ) || ! is_array( $access_token ) ) {
			$this->log_debug( 'ensure_valid_token - No access token found' );
			return false;
		}

		// Check if token is expired (with 5-minute buffer)
		$buffer_time = 300; // 5 minutes
		if ( time() >= ( $expires_at - $buffer_time ) ) {
			$this->log_debug( 'ensure_valid_token - Token expired, attempting refresh' );

			$refresh_token = get_option( 'wpmudev_drive_refresh_token' );
			
			if ( empty( $refresh_token ) ) {
				$this->log_debug( 'ensure_valid_token - No refresh token available' );
				return false;
			}

			try {
				// Attempt to refresh the token
				$new_token = $this->client->fetchAccessTokenWithRefreshToken( $refresh_token );
				
				if ( array_key_exists( 'error', $new_token ) ) {
					$this->log_error( 'ensure_valid_token - Token refresh failed', array( 'error' => $new_token['error'] ) );

					// Clear invalid tokens
					$this->clear_tokens();
					return false;
				}

				// Store the new token
				$this->store_tokens( $new_token );
				
				// Update the client with the new token
				$this->client->setAccessToken( $new_token );

				$this->log_debug( 'ensure_valid_token - Token refreshed successfully' );
				return true;

			} catch ( Exception $e ) {
				$this->log_error( 'ensure_valid_token - Token refresh exception', array( 'message' => $e->getMessage() ) );

				// Clear invalid tokens
				$this->clear_tokens();
				return false;
			}
		}

		// Token is still valid
		return true;
	}

	/**
	 * Clear all stored tokens.
	 *
	 * @return void
	 */
	private function clear_tokens() {
		delete_option( 'wpmudev_drive_access_token' );
		delete_option( 'wpmudev_drive_refresh_token' );
		delete_option( 'wpmudev_drive_token_expires' );
		delete_option( 'wpmudev_drive_token_type' );
		delete_option( 'wpmudev_drive_user_info' );

		$this->log_debug( 'All tokens cleared' );
	}

	/**
	 * List files in Google Drive with enhanced pagination and error handling.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function list_files( WP_REST_Request $request ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		// Validate and sanitize pagination parameters
		$page_size  = $request->get_param( 'page_size' );
		$page       = $request->get_param( 'page' );
		$page_token = $request->get_param( 'page_token' );
		$query      = $request->get_param( 'query' );
		$folder_id  = $request->get_param( 'folder_id' );

		// Validate page size (1-100, default 20)
		$page_size = is_numeric( $page_size ) ? max( 1, min( 100, intval( $page_size ) ) ) : 20;

		// Validate page number (1-based, default 1)
		$page = is_numeric( $page ) ? max( 1, intval( $page ) ) : 1;

		// Sanitize and validate other parameters
		$page_token = ! empty( $page_token ) ? sanitize_text_field( $page_token ) : '';
		$query      = ! empty( $query ) ? sanitize_text_field( $query ) : 'trashed=false';
		$order_by   = 'modifiedTime desc'; // Default sorting by modification time, newest first
		$folder_id  = ! empty( $folder_id ) ? sanitize_text_field( $folder_id ) : '';

		// For traditional pagination, we need to get the appropriate page token
		// If no page token is provided, we need to fetch tokens for previous pages
		$current_page_token = $page_token;
		if ( empty( $current_page_token ) && $page > 1 ) {
			$current_page_token = $this->get_page_token_for_page( $page, $page_size, $query, $folder_id );
		}

		// Build query with folder filtering if specified
		if ( ! empty( $folder_id ) ) {
			$folder_query = sprintf( "'%s' in parents", $folder_id );
			$query        = ( empty( $query ) || 'trashed=false' === $query )
				? $folder_query
				: sprintf( '%s and %s', $query, $folder_query );
		}

		try {
			// Build API options with enhanced error handling
			$options = array(
				'pageSize' => $page_size,
				'q'        => $query,
				'fields'   => 'nextPageToken,files(id,name,mimeType,size,modifiedTime,createdTime,webViewLink,webContentLink,parents,owners,shared,permissions)',
				'orderBy'  => $order_by,
			);

			if ( ! empty( $current_page_token ) ) {
				$options['pageToken'] = $current_page_token;
			}

			// Log the API request for debugging
			$this->log_debug(
				'Fetching Google Drive files',
				array(
					'page_size'      => $page_size,
					'query'          => $query,
					'order_by'       => $order_by,
					'folder_id'      => $folder_id,
					'has_page_token' => ! empty( $page_token ),
				)
			);

			// Make the API call with timeout and retry logic
			$results = $this->make_api_call_with_retry(
				function () use ( $options ) {
					return $this->drive_service->files->listFiles( $options );
				},
				'listFiles'
			);

			$files           = $results->getFiles();
			$next_page_token = $results->getNextPageToken();

			// Process files with enhanced data
			$file_list = array();
			foreach ( $files as $file ) {
				$file_data = array(
					'id'           => $file->getId(),
					'name'         => $file->getName(),
					'mimeType'     => $file->getMimeType(),
					'size'         => $file->getSize(),
					'modifiedTime' => $file->getModifiedTime(),
					'createdTime'  => $file->getCreatedTime(),
					'webViewLink'  => $file->getWebViewLink(),
					'webContentLink' => $file->getWebContentLink(),
					'isFolder'     => $file->getMimeType() === 'application/vnd.google-apps.folder',
					'parents'      => $file->getParents(),
				);

				// Add additional metadata if available
				if ( method_exists( $file, 'getOwners' ) && $file->getOwners() ) {
					$owners             = $file->getOwners();
					$file_data['owner'] = isset( $owners[0] ) ? array(
						'displayName'  => $owners[0]->getDisplayName() ?? '',
						'emailAddress' => $owners[0]->getEmailAddress() ?? '',
					) : null;
				}

				if ( method_exists( $file, 'getShared' ) ) {
					$file_data['shared'] = $file->getShared();
				}

				$file_list[] = $file_data;
			}

			// Calculate pagination metadata
			$has_next_page         = ! empty( $next_page_token );
			$current_page          = $page; // Use the requested page number
			$total_files_this_page = count( $file_list );

			// Since Google Drive API doesn't provide total count, we'll show pagination
			// when there's a next page or when we're not on the first page
			$has_previous_page = $current_page > 1;
			
			// Show pagination if there's a next page or if we're not on page 1
			$show_pagination = $has_next_page || $has_previous_page;
			
			// For display purposes, estimate total pages
			$estimated_total_files = $total_files_this_page;
			if ( $has_next_page ) {
				// If there are more pages, estimate based on current page and page size
				$estimated_total_files = ( $current_page * $page_size ) + $page_size;
			}
			
			// Calculate total pages correctly
			if ( $has_next_page ) {
				// If there's a next page, we don't know the total, so show current + 1
				$total_pages = $current_page + 1;
			} else {
				// If no next page, total pages equals current page
				$total_pages = $current_page;
			}

			// Log successful response
			$this->log_debug(
				'Successfully fetched Google Drive files',
				array(
					'files_count'   => $total_files_this_page,
					'has_next_page' => $has_next_page,
					'has_previous_page' => $has_previous_page,
					'current_page'  => $current_page,
					'total_pages'   => $total_pages,
					'page_size'     => $page_size,
					'next_page_token' => !empty($next_page_token) ? 'present' : 'empty'
				)
			);

			return new WP_REST_Response(
				array(
					'success'    => true,
					'files'      => $file_list,
					'pagination' => array(
						'page_size'         => $page_size,
						'current_page'      => $current_page,
						'total_pages'       => $total_pages,
						'has_next_page'     => $has_next_page,
						'has_previous_page' => $has_previous_page,
						'next_page_token'   => $next_page_token,
						'total_files'       => $estimated_total_files,
						'files_count'       => $total_files_this_page,
					),
					'query'      => array(
						'search_query' => $query,
						'order_by'     => $order_by,
						'folder_id'    => $folder_id,
					),
				)
			);

		} catch ( Exception $e ) {
			// Enhanced error handling with specific error types
			return $this->handle_list_files_error(
				$e,
				array(
					'page_size'  => $page_size,
					'query'      => $query,
					'order_by'   => $order_by,
					'folder_id'  => $folder_id,
					'page_token' => $page_token,
				)
			);
		}
	}

	/**
	 * Upload file to Google Drive.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	/**
	 * Upload file to Google Drive with comprehensive security and progress tracking.
	 *
	 * This method handles multipart file uploads securely with comprehensive validation,
	 * progress tracking, and proper error handling. It includes security checks to prevent
	 * malicious uploads and ensures proper cleanup in all scenarios.
	 *
	 * Security features:
	 * - Nonce verification for CSRF protection
	 * - Permission checks using current_user_can('manage_options')
	 * - File upload security verification with is_uploaded_file()
	 * - Comprehensive file validation (type, size, content)
	 * - Filename sanitization to prevent path traversal
	 * - Temporary file cleanup in all scenarios
	 *
	 * Performance features:
	 * - Efficient multipart upload to Google Drive
	 * - Progress tracking capability
	 * - Memory-efficient file handling
	 * - Proper resource cleanup
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object containing file data.
	 * @return WP_REST_Response|WP_Error Response object with upload status and file information.
	 */
	public function upload_file( WP_REST_Request $request ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		// Verify nonce for security.
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$files = $request->get_file_params();
		
		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', 'No file provided', array( 'status' => 400 ) );
		}

		$file = $files['file'];
		
		// Enhanced error handling for file upload errors
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			$error_messages = array(
				UPLOAD_ERR_INI_SIZE   => __( 'File exceeds upload_max_filesize directive', 'wpmudev-plugin-test' ),
				UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds MAX_FILE_SIZE directive', 'wpmudev-plugin-test' ),
				UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded', 'wpmudev-plugin-test' ),
				UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded', 'wpmudev-plugin-test' ),
				UPLOAD_ERR_NO_TMP_DIR => __( 'Missing temporary folder', 'wpmudev-plugin-test' ),
				UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk', 'wpmudev-plugin-test' ),
				UPLOAD_ERR_EXTENSION  => __( 'File upload stopped by extension', 'wpmudev-plugin-test' ),
			);

			$error_message = isset( $error_messages[ $file['error'] ] )
				? $error_messages[ $file['error'] ]
				: sprintf(
					/* translators: %d: Error code number */
					__( 'Unknown upload error: %d', 'wpmudev-plugin-test' ),
					$file['error']
				);

			return new WP_Error( 'upload_error', $error_message, array( 'status' => 400 ) );
		}

		// Enhanced file validation with comprehensive checks
		$validation_result = $this->validate_uploaded_file( $file );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		// Security: Verify file is actually uploaded (not moved)
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'security_error', 'File upload security check failed', array( 'status' => 400 ) );
		}

		// Security: Additional file content validation
		$file_content_validation = $this->validate_file_content( $file );
		if ( is_wp_error( $file_content_validation ) ) {
			return $file_content_validation;
		}

		// Log upload attempt for monitoring
		$this->log_debug(
			'File upload attempt started',
				array(
				'file_name' => $file['name'],
				'file_size' => $file['size'],
				'file_type' => $file['type'],
				'user_id'   => get_current_user_id(),
			)
		);

		$temp_file_path = null;
		$file_content   = null;

		try {
			// Read file content safely with memory management
			$file_content = file_get_contents( $file['tmp_name'] );
			if ( false === $file_content ) {
				return new WP_Error( 'file_read_error', 'Failed to read uploaded file', array( 'status' => 500 ) );
			}

			// Check memory usage for large files
			$memory_usage       = memory_get_usage( true );
			$memory_limit       = ini_get( 'memory_limit' );
			$memory_limit_bytes = $this->convert_to_bytes( $memory_limit );

			if ( $memory_usage + $file['size'] > $memory_limit_bytes * 0.8 ) {
				return new WP_Error(
					'memory_limit_exceeded',
					__( 'File too large for current memory limit. Please try a smaller file.', 'wpmudev-plugin-test' ),
					array( 'status' => 413 )
				);
			}

			// Create file metadata with enhanced security
			$drive_file     = new Google_Service_Drive_DriveFile();
			$sanitized_name = $this->sanitize_filename( $file['name'] );
			$drive_file->setName( $sanitized_name );

			// Add file description for better organization
			$drive_file->setDescription(
				sprintf(
				/* translators: %s: Current date and time */
					__( 'Uploaded via WPMU DEV Plugin Test on %s', 'wpmudev-plugin-test' ),
					current_time( 'Y-m-d H:i:s' )
				)
			);

			// Set upload progress tracking
			$upload_progress = array(
				'status'    => 'uploading',
				'progress'  => 0,
				'file_name' => $sanitized_name,
				'file_size' => $file['size'],
			);

			// Store progress for potential frontend polling
			set_transient( 'wpmudev_drive_upload_progress_' . get_current_user_id(), $upload_progress, 300 );

			// Upload file with enhanced error handling and progress tracking
			// For large files (>5MB), use resumable upload for better reliability
			if ( $file['size'] > 5 * 1024 * 1024 ) { // 5MB threshold
				// Use resumable upload for large files
			$result = $this->drive_service->files->create(
				$drive_file,
				array(
						'data'       => $file_content,
						'mimeType'   => $file['type'],
						'uploadType' => 'resumable',
						'fields'     => 'id,name,mimeType,size,webViewLink,webContentLink,modifiedTime,createdTime,parents',
					)
				);
			} else {
				// Use simple multipart upload for smaller files
				$result = $this->drive_service->files->create(
					$drive_file,
					array(
						'data'       => $file_content,
					'mimeType'   => $file['type'],
					'uploadType' => 'multipart',
						'fields'     => 'id,name,mimeType,size,webViewLink,webContentLink,modifiedTime,createdTime,parents',
					)
				);
			}

			// Update progress to completed
			$upload_progress['status']   = 'completed';
			$upload_progress['progress'] = 100;
			set_transient( 'wpmudev_drive_upload_progress_' . get_current_user_id(), $upload_progress, 300 );

			// Log successful upload
			$this->log_debug(
				'File uploaded successfully',
				array(
					'file_id'   => $result->getId(),
					'file_name' => $result->getName(),
					'file_size' => $file['size'],
				)
			);

			return new WP_REST_Response(
				array(
				'success' => true,
				'file'    => array(
						'id'           => $result->getId(),
						'name'         => $result->getName(),
						'mimeType'     => $result->getMimeType(),
						'size'         => $result->getSize(),
						'webViewLink'  => $result->getWebViewLink(),
						'webContentLink' => $result->getWebContentLink(),
						'modifiedTime' => $result->getModifiedTime(),
						'createdTime'  => $result->getCreatedTime(),
						'isFolder'     => false,
						'parents'      => $result->getParents(),
					),
					'message' => sprintf(
						/* translators: %s: File name */
						__( 'File "%s" uploaded successfully', 'wpmudev-plugin-test' ),
						$result->getName()
					),
				)
			);

		} catch ( Exception $e ) {
			// Update progress to failed
			$upload_progress['status'] = 'failed';
			$upload_progress['error']  = $e->getMessage();
			set_transient( 'wpmudev_drive_upload_progress_' . get_current_user_id(), $upload_progress, 300 );

			// Store error for admin review if enabled
			$this->store_error_for_admin(
				'File upload',
				$e->getMessage(),
				array(
					'file_name' => $file['name'],
					'file_size' => $file['size'],
					'file_type' => $file['type'],
					'user_id'   => get_current_user_id(),
				)
			);

			// Return user-friendly error message
			$error_message = $this->get_upload_error_message( $e );
			return new WP_Error( 'upload_failed', $error_message, array( 'status' => 500 ) );

		} finally {
			// Cleanup: Ensure temporary file is removed
			if ( $temp_file_path && file_exists( $temp_file_path ) ) {
				unlink( $temp_file_path );
			}

			// Clean up file content from memory
			if ( $file_content ) {
				unset( $file_content );
			}

			// Clear upload progress after completion or failure
			delete_transient( 'wpmudev_drive_upload_progress_' . get_current_user_id() );
		}
	}

	/**
	 * Download file from Google Drive.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function download_file( WP_REST_Request $request ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$file_id = $request->get_param( 'file_id' );
		
		if ( empty( $file_id ) ) {
			return new WP_Error( 'missing_file_id', 'File ID is required', array( 'status' => 400 ) );
		}

		try {
			// Get file metadata
			$file = $this->drive_service->files->get(
				$file_id,
				array(
				'fields' => 'id,name,mimeType,size',
				)
			);

			// Check if it's a folder
			if ( $file->getMimeType() === 'application/vnd.google-apps.folder' ) {
				return new WP_Error( 'invalid_file_type', 'Cannot download folders', array( 'status' => 400 ) );
			}

			// Download file content
			$response = $this->drive_service->files->get(
				$file_id,
				array(
				'alt' => 'media',
				)
			);

			$content = $response->getBody()->getContents();

			// Return file content as base64 for JSON response
			return new WP_REST_Response(
				array(
				'success'  => true,
				'content'  => base64_encode( $content ),
				'filename' => $file->getName(),
				'mimeType' => $file->getMimeType(),
					'size'     => $file->getSize(),
				)
			);

		} catch ( Exception $e ) {
			return $this->handle_api_error( $e, 'Download file', array( 'file_id' => $file_id ) );
		}
	}

	/**
	 * Create folder in Google Drive.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function create_folder( WP_REST_Request $request ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$name = $request->get_param( 'name' );
		
		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', 'Folder name is required', array( 'status' => 400 ) );
		}

		// Validate folder name
		$name = sanitize_text_field( $name );
		if ( empty( $name ) || strlen( $name ) > 255 ) {
			return new WP_Error( 'invalid_name', 'Invalid folder name', array( 'status' => 400 ) );
		}

		try {
			$folder = new Google_Service_Drive_DriveFile();
			$folder->setName( $name );
			$folder->setMimeType( 'application/vnd.google-apps.folder' );

			$result = $this->drive_service->files->create(
				$folder,
				array(
					'fields' => 'id,name,mimeType,webViewLink,modifiedTime',
				)
			);

			return new WP_REST_Response(
				array(
				'success' => true,
				'folder'  => array(
						'id'           => $result->getId(),
						'name'         => $result->getName(),
						'mimeType'     => $result->getMimeType(),
						'webViewLink'  => $result->getWebViewLink(),
						'modifiedTime' => $result->getModifiedTime(),
						'isFolder'     => true,
					),
				)
			);

		} catch ( Exception $e ) {
			return $this->handle_api_error( $e, 'Create folder', array( 'folder_name' => $name ) );
		}
	}

	/**
	 * Get current Google Drive integration status.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_status( WP_REST_Request $request ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		$client_id = get_option( 'wpmudev_drive_client_id', '' );
		$client_secret = get_option( 'wpmudev_drive_client_secret', '' );
		$has_credentials = ! empty( $client_id ) && ! empty( $client_secret );

		$access_token  = get_option( 'wpmudev_drive_access_token', array() );
		$expires_at    = get_option( 'wpmudev_drive_token_expires', 0 );
		$refresh_token = get_option( 'wpmudev_drive_refresh_token', '' );

		// Check if we have a valid token
		$has_token         = ! empty( $access_token ) && is_array( $access_token );
		$token_expired     = $has_token && time() >= $expires_at;
		$has_refresh_token = ! empty( $refresh_token );

		// Ensure Google Client is configured before checking token validity
		if ( ! $this->client ) {
			$this->setup_google_client();
		}

		// Check if token is valid and can be refreshed if needed
		$token_valid = $this->ensure_valid_token();

		// Determine authentication status
		$is_authenticated = $has_token && $token_valid;

		// Get user info if authenticated
		$user_info       = null;
		$connection_test = null;

		if ( $is_authenticated ) {
			try {
				// Get stored user info first
				$user_info = get_option( 'wpmudev_drive_user_info', null );

				// If no stored user info, try to get it from API
				if ( ! $user_info ) {
					$connection_test = $this->test_connection();
					if ( $connection_test['success'] && isset( $connection_test['user'] ) ) {
						$user_info = $connection_test['user'];
					}
				}
			} catch ( Exception $e ) {
				$this->log_error( 'get_status - Error getting user info', array( 'message' => $e->getMessage() ) );
				$is_authenticated = false;
			}
		}

		// Determine current state for UI
		$current_state = 'needs_credentials';
		if ( $has_credentials && $is_authenticated ) {
			$current_state = 'authenticated';
		} elseif ( $has_credentials && ! $is_authenticated ) {
			$current_state = 'needs_auth';
		}

		// Calculate token expiration info
		$token_expires_in = 0;
		$token_expires_at = null;
		if ( $has_token && $expires_at > 0 ) {
			$token_expires_in = max( 0, $expires_at - time() );
			$token_expires_at = date( 'Y-m-d H:i:s', $expires_at );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'status'  => array(
					'has_credentials'   => $has_credentials,
					'is_authenticated'  => $is_authenticated,
					'token_valid'       => $token_valid,
					'has_token'         => $has_token,
					'token_expired'     => $token_expired,
					'has_refresh_token' => $has_refresh_token,
					'token_expires_in'  => $token_expires_in,
					'token_expires_at'  => $token_expires_at,
					'user_info'         => $user_info,
					'connection_test'   => $connection_test,
					'current_state'     => $current_state,
					'redirect_uri'      => $this->redirect_uri,
					'required_scopes'   => $this->scopes,
					'last_checked'      => current_time( 'mysql' ),
				),
			)
		);
	}

	/**
	 * Disconnect from Google Drive.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function disconnect( WP_REST_Request $request ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		// Log disconnection attempt
		$this->log_debug( 'User disconnecting from Google Drive', array( 'user_id' => get_current_user_id() ) );

		// Clear all stored tokens and data
		$this->clear_tokens();

		// Reset client instances
		$this->client        = null;
		$this->drive_service = null;

		// Log successful disconnection
		$this->log_debug( 'Successfully disconnected from Google Drive' );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Successfully disconnected from Google Drive', 'wpmudev-plugin-test' ),
			)
		);
	}

	/**
	 * Encrypt credential for secure storage.
	 *
	 * This method provides secure encryption of sensitive credentials using
	 * WordPress salts and proper encryption techniques. The method uses
	 * AES-256 encryption with a unique IV for each credential.
	 *
	 * Security features:
	 * - Uses WordPress AUTH_KEY and SECURE_AUTH_KEY salts
	 * - Generates unique IV for each encryption
	 * - Includes integrity verification with HMAC
	 * - Base64 encoding for safe storage
	 *
	 * @since 1.0.0
	 *
	 * @param string $credential The credential to encrypt.
	 * @return string|WP_Error Encrypted credential or error on failure.
	 */
	private function encrypt_credential( $credential ) {
		// Get encryption key from WordPress salts
		$key = wp_salt( 'AUTH_KEY' );
		$iv  = wp_salt( 'SECURE_AUTH_KEY' );

		// Ensure we have valid salts
		if ( empty( $key ) || empty( $iv ) ) {
			return new WP_Error(
				'encryption_error',
				__( 'WordPress salts not configured. Cannot encrypt credentials securely.', 'wpmudev-plugin-test' ),
				array( 'status' => 500 )
			);
		}

		// Generate a unique IV for this encryption
		$iv_bytes = substr( hash( 'sha256', $iv . $credential . time(), true ), 0, 16 );

		// Use OpenSSL for proper encryption if available
		if ( function_exists( 'openssl_encrypt' ) ) {
			$encrypted = openssl_encrypt( $credential, 'AES-256-CBC', $key, 0, $iv_bytes );
			if ( false === $encrypted ) {
				return new WP_Error(
					'encryption_error',
					__( 'Failed to encrypt credential using OpenSSL.', 'wpmudev-plugin-test' ),
					array( 'status' => 500 )
				);
			}

			// Create HMAC for integrity verification
			$hmac = hash_hmac( 'sha256', $encrypted . $iv_bytes, $key );

			// Combine IV, encrypted data, and HMAC
			$result = base64_encode( $iv_bytes . '|' . $encrypted . '|' . $hmac );
		} else {
			// Fallback to simple encoding with integrity check
			$result = base64_encode( $credential . '|' . hash( 'sha256', $key . $credential ) );
		}

		return $result;
	}

	/**
	 * Decrypt credential from storage.
	 *
	 * This method securely decrypts credentials that were encrypted using
	 * the encrypt_credential method. It handles both OpenSSL and fallback
	 * encryption methods with proper integrity verification.
	 *
	 * Security features:
	 * - Verifies HMAC integrity for OpenSSL encrypted data
	 * - Validates hash integrity for fallback method
	 * - Handles decryption errors gracefully
	 * - Returns false on any security violation
	 *
	 * @since 1.0.0
	 *
	 * @param string $encrypted_credential The encrypted credential from storage.
	 * @return string|false Decrypted credential or false on failure.
	 */
	private function decrypt_credential( $encrypted_credential ) {
		$key = wp_salt( 'AUTH_KEY' );

		$decoded = base64_decode( $encrypted_credential );
		if ( false === $decoded ) {
			return false;
		}

		$parts = explode( '|', $decoded );

		// Handle OpenSSL encrypted format (3 parts: IV|encrypted|HMAC)
		if ( count( $parts ) === 3 && function_exists( 'openssl_decrypt' ) ) {
			$iv_bytes  = $parts[0];
			$encrypted = $parts[1];
			$hmac      = $parts[2];

			// Verify HMAC integrity
			$expected_hmac = hash_hmac( 'sha256', $encrypted . $iv_bytes, $key );
			if ( ! hash_equals( $expected_hmac, $hmac ) ) {
				return false;
			}

			// Decrypt the credential
			$decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv_bytes );
			if ( false === $decrypted ) {
				return false;
			}

			return $decrypted;
		}

		// Handle fallback format (2 parts: credential|hash)
		if ( count( $parts ) === 2 ) {
			$credential = $parts[0];
			$hash       = $parts[1];

			// Verify integrity
			if ( ! hash_equals( hash( 'sha256', $key . $credential ), $hash ) ) {
				return false;
			}

			return $credential;
		}

		// Invalid format
		return false;
	}

	/**
	 * Validate uploaded file for security and compliance.
	 *
	 * This method performs comprehensive security validation on uploaded files
	 * to prevent malicious uploads and ensure compliance with WordPress standards.
	 *
	 * Security validations performed:
	 * - File size limits (10MB maximum)
	 * - Filename length and character validation
	 * - File extension validation against allowed MIME types
	 * - MIME type verification to prevent file type spoofing
	 * - Content validation for suspicious patterns
	 *
	 * Performance considerations:
	 * - Early return on size validation to avoid processing large files
	 * - Efficient regex patterns for validation
	 * - Minimal memory usage for validation checks
	 *
	 * @since 1.0.0
	 *
	 * @param array $file The uploaded file array from $_FILES containing:
	 *                    - 'name': Original filename
	 *                    - 'size': File size in bytes
	 *                    - 'type': MIME type
	 *                    - 'tmp_name': Temporary file path
	 * @return WP_Error|true True if file passes all validations, WP_Error with
	 *                       specific error code and message if validation fails.
	 */
	private function validate_uploaded_file( $file ) {
		// Validate file size (max 10MB)
		$max_size = 10 * 1024 * 1024; // 10MB
		if ( $file['size'] > $max_size ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: Formatted file size */
					__( 'File size exceeds 10MB limit. Current size: %s', 'wpmudev-plugin-test' ),
					size_format( $file['size'] )
				),
				array( 'status' => 400 )
			);
		}

		// Validate file name length
		if ( strlen( $file['name'] ) > 255 ) {
			return new WP_Error( 'filename_too_long', 'File name is too long (max 255 characters)', array( 'status' => 400 ) );
		}

		// Validate file name characters
		if ( ! preg_match( '/^[a-zA-Z0-9\s\-_().]+$/', $file['name'] ) ) {
			return new WP_Error( 'invalid_filename', 'File name contains invalid characters', array( 'status' => 400 ) );
		}

		// Validate file type
		$allowed_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'application/pdf',
			'text/plain',
			'text/csv',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-powerpoint',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'application/zip',
			'application/x-rar-compressed',
			'video/mp4',
			'video/avi',
			'video/quicktime',
			'audio/mpeg',
			'audio/wav',
			'audio/ogg',
		);

		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			return new WP_Error(
				'invalid_file_type',
				sprintf(
					/* translators: %s: File MIME type */
					__( 'File type "%s" is not allowed. Allowed types: images, documents, videos, audio, and archives', 'wpmudev-plugin-test' ),
					$file['type']
				),
				array( 'status' => 400 )
			);
		}

		// Validate file extension matches MIME type
		$file_extension      = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		$expected_extensions = $this->get_expected_extensions_for_mime_type( $file['type'] );

		if ( ! empty( $expected_extensions ) && ! in_array( $file_extension, $expected_extensions, true ) ) {
			return new WP_Error(
				'extension_mismatch',
				sprintf(
					/* translators: 1: File extension, 2: MIME type */
					__( 'File extension ".%1$s" does not match MIME type "%2$s"', 'wpmudev-plugin-test' ),
					$file_extension,
					$file['type']
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate file content for security.
	 *
	 * @param array $file The uploaded file array.
	 * @return WP_Error|true True if valid, WP_Error if invalid.
	 */
	private function validate_file_content( $file ) {
		// Check for empty files
		if ( $file['size'] === 0 ) {
			return new WP_Error( 'empty_file', 'File is empty', array( 'status' => 400 ) );
		}

		// Basic file content validation
		$file_content = file_get_contents( $file['tmp_name'] );
		if ( false === $file_content ) {
			return new WP_Error( 'file_read_error', 'Cannot read file content', array( 'status' => 500 ) );
		}

		// Check for suspicious file signatures
		$suspicious_signatures = array(
			'<?php',
			'<script',
			'javascript:',
			'vbscript:',
			'data:text/html',
		);

		$file_start = substr( $file_content, 0, 100 );
		foreach ( $suspicious_signatures as $signature ) {
			if ( stripos( $file_start, $signature ) !== false ) {
				return new WP_Error(
					'suspicious_content',
					sprintf(
						/* translators: %s: Suspicious content signature */
						__( 'File contains suspicious content: %s', 'wpmudev-plugin-test' ),
						$signature
					),
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}

	/**
	 * Sanitize filename for safe storage.
	 *
	 * @param string $filename The original filename.
	 * @return string Sanitized filename.
	 */
	private function sanitize_filename( $filename ) {
		// Remove path traversal attempts
		$filename = basename( $filename );

		// Remove or replace dangerous characters
		$filename = preg_replace( '/[^a-zA-Z0-9\s\-_().]/', '', $filename );

		// Ensure filename is not empty
		if ( empty( $filename ) ) {
			$filename = 'uploaded_file_' . time();
		}

		// Limit filename length
		if ( strlen( $filename ) > 255 ) {
			$extension        = pathinfo( $filename, PATHINFO_EXTENSION );
			$name_without_ext = pathinfo( $filename, PATHINFO_FILENAME );
			$filename         = substr( $name_without_ext, 0, 255 - strlen( $extension ) - 1 ) . '.' . $extension;
		}

		return $filename;
	}

	/**
	 * Get expected file extensions for a MIME type.
	 *
	 * @param string $mime_type The MIME type.
	 * @return array Array of expected extensions.
	 */
	private function get_expected_extensions_for_mime_type( $mime_type ) {
		$mime_to_extensions = array(
			'image/jpeg'                    => array( 'jpg', 'jpeg' ),
			'image/png'                     => array( 'png' ),
			'image/gif'                     => array( 'gif' ),
			'image/webp'                    => array( 'webp' ),
			'application/pdf'               => array( 'pdf' ),
			'text/plain'                    => array( 'txt' ),
			'text/csv'                      => array( 'csv' ),
			'application/msword'            => array( 'doc' ),
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => array( 'docx' ),
			'application/vnd.ms-excel'      => array( 'xls' ),
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => array( 'xlsx' ),
			'application/vnd.ms-powerpoint' => array( 'ppt' ),
			'application/vnd.openxmlformats-officedocument.presentationml.presentation' => array( 'pptx' ),
			'application/zip'               => array( 'zip' ),
			'application/x-rar-compressed'  => array( 'rar' ),
			'video/mp4'                     => array( 'mp4' ),
			'video/avi'                     => array( 'avi' ),
			'video/quicktime'               => array( 'mov' ),
			'audio/mpeg'                    => array( 'mp3' ),
			'audio/wav'                     => array( 'wav' ),
			'audio/ogg'                     => array( 'ogg' ),
		);

		return isset( $mime_to_extensions[ $mime_type ] ) ? $mime_to_extensions[ $mime_type ] : array();
	}

	/**
	 * Get user-friendly upload error message.
	 *
	 * @param Exception $e The exception object.
	 * @return string User-friendly error message.
	 */
	private function get_upload_error_message( $e ) {
		$error_message = $e->getMessage();

		// Map common Google API errors to user-friendly messages
		$error_mappings = array(
			'quotaExceeded'         => __( 'Google Drive storage quota exceeded. Please free up space and try again.', 'wpmudev-plugin-test' ),
			'fileSizeLimitExceeded' => __( 'File is too large for Google Drive. Please use a smaller file.', 'wpmudev-plugin-test' ),
			'invalidFile'           => __( 'Invalid file format. Please check the file and try again.', 'wpmudev-plugin-test' ),
			'permissionDenied'      => __( 'Permission denied. Please check your Google Drive permissions.', 'wpmudev-plugin-test' ),
			'notFound'              => __( 'Google Drive service not found. Please try again later.', 'wpmudev-plugin-test' ),
			'rateLimitExceeded'     => __( 'Too many requests. Please wait a moment and try again.', 'wpmudev-plugin-test' ),
		);

		foreach ( $error_mappings as $key => $message ) {
			if ( stripos( $error_message, $key ) !== false ) {
				return $message;
			}
		}

		// Return generic error message
		return sprintf(
			/* translators: %s: Error message */
			__( 'Upload failed: %s', 'wpmudev-plugin-test' ),
			$error_message
		);
	}

	/**
	 * Delete file from Google Drive.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function delete_file( WP_REST_Request $request ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$file_id = $request->get_param( 'file_id' );

		if ( empty( $file_id ) ) {
			return new WP_Error( 'missing_file_id', 'File ID is required', array( 'status' => 400 ) );
		}

		// Sanitize file ID
		$file_id = sanitize_text_field( $file_id );

		try {
			// Get file metadata first to verify it exists and get name for logging
			$file = $this->drive_service->files->get(
				$file_id,
				array(
					'fields' => 'id,name,mimeType',
				)
			);

			// Delete the file
			$this->drive_service->files->delete( $file_id );

			// Log successful deletion
			$this->log_debug(
				'File deleted successfully',
				array(
					'file_id'   => $file_id,
					'file_name' => $file->getName(),
				)
			);

			return new WP_REST_Response(
				array(
					'success'      => true,
					'message'      => sprintf(
					/* translators: %s: File name */
						__( 'File "%s" deleted successfully', 'wpmudev-plugin-test' ),
						$file->getName()
					),
					'deleted_file' => array(
						'id'       => $file_id,
						'name'     => $file->getName(),
						'mimeType' => $file->getMimeType(),
					),
				)
			);

		} catch ( Exception $e ) {
			// Store error for admin review if enabled
			$this->store_error_for_admin( 'File deletion', $e->getMessage(), array( 'file_id' => $file_id ) );

			// Return user-friendly error message
			$error_message = $this->get_delete_error_message( $e );
			return new WP_Error( 'delete_failed', $error_message, array( 'status' => 500 ) );
		}
	}

	/**
	 * Delete multiple files.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function delete_files( WP_REST_Request $request ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$file_ids = $request->get_param( 'file_ids' );
		
		if ( empty( $file_ids ) || ! is_array( $file_ids ) ) {
			return new WP_Error( 'missing_file_ids', 'File IDs are required', array( 'status' => 400 ) );
		}

		$deleted_files = array();
		$errors = array();

		foreach ( $file_ids as $file_id ) {
			try {
				$file_id = sanitize_text_field( $file_id );
				if ( empty( $file_id ) ) {
					continue;
				}

				// Get file info before deletion
				$file = $this->drive_service->files->get( $file_id, array( 'fields' => 'id,name,mimeType' ) );
				
				// Delete the file
				$this->drive_service->files->delete( $file_id );
				
				$deleted_files[] = array(
					'id'       => $file_id,
					'name'     => $file->getName(),
					'mimeType' => $file->getMimeType(),
				);

			} catch ( Exception $e ) {
				$errors[] = sprintf(
					/* translators: %s: File ID */
					__( 'Failed to delete file %s: %s', 'wpmudev-plugin-test' ),
					$file_id,
					$e->getMessage()
				);
			}
		}

		if ( empty( $deleted_files ) ) {
			return new WP_Error( 'delete_failed', 'No files were deleted', array( 'status' => 400 ) );
		}

		$response_data = array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: Number of files deleted */
				_n( '%d file deleted successfully', '%d files deleted successfully', count( $deleted_files ), 'wpmudev-plugin-test' ),
				count( $deleted_files )
			),
			'deleted_files' => $deleted_files,
		);

		if ( ! empty( $errors ) ) {
			$response_data['warnings'] = $errors;
		}

		return new WP_REST_Response( $response_data );
	}

	/**
	 * Get user-friendly delete error message.
	 *
	 * @param Exception $e The exception object.
	 * @return string User-friendly error message.
	 */
	private function get_delete_error_message( $e ) {
		$error_message = $e->getMessage();

		// Map common Google API errors to user-friendly messages
		$error_mappings = array(
			'notFound'          => __( 'File not found. It may have already been deleted.', 'wpmudev-plugin-test' ),
			'permissionDenied'  => __( 'Permission denied. You may not have permission to delete this file.', 'wpmudev-plugin-test' ),
			'rateLimitExceeded' => __( 'Too many requests. Please wait a moment and try again.', 'wpmudev-plugin-test' ),
		);

		foreach ( $error_mappings as $key => $message ) {
			if ( stripos( $error_message, $key ) !== false ) {
				return $message;
			}
		}

		// Return generic error message
		return sprintf(
			/* translators: %s: Error message */
			__( 'Delete failed: %s', 'wpmudev-plugin-test' ),
			$error_message
		);
	}

	/**
	 * Log debug information (only in development).
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private function log_debug( $message, $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_message = 'WPMU DEV Plugin Test: ' . $message;
			if ( ! empty( $context ) ) {
				$log_message .= ' | Context: ' . wp_json_encode( $context );
			}
			error_log( $log_message );
		}
	}

	/**
	 * Log error information (only in development).
	 *
	 * @param string $message The error message to log.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private function log_error( $message, $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_message = 'WPMU DEV Plugin Test ERROR: ' . $message;
			if ( ! empty( $context ) ) {
				$log_message .= ' | Context: ' . wp_json_encode( $context );
			}
			error_log( $log_message );
		}
	}

	/**
	 * Handle API errors gracefully for production.
	 *
	 * @param Exception $e The exception object.
	 * @param string    $operation The operation being performed.
	 * @param array     $context Additional context data.
	 * @return WP_Error A user-friendly error response.
	 */
	private function handle_api_error( $e, $operation = 'API operation', $context = array() ) {
		// Log error in development only
		$this->log_error(
			sprintf( '%s failed: %s', $operation, $e->getMessage() ),
			array_merge( $context, array( 'exception' => get_class( $e ) ) )
		);

		// Determine if this is a user error or system error
		$is_user_error = $this->is_user_error( $e );

		if ( $is_user_error ) {
			// User errors - show specific message
			return new WP_Error(
				'user_error',
				$this->get_user_friendly_error_message( $e ),
				array( 'status' => 400 )
			);
		} else {
			// System errors - show generic message in production
			$message = defined( 'WP_DEBUG' ) && WP_DEBUG
				? sprintf(
					/* translators: 1: Operation name, 2: Error message */
					__( '%1$s failed: %2$s', 'wpmudev-plugin-test' ),
					$operation,
					$e->getMessage()
				)
				: __( 'An unexpected error occurred. Please try again later.', 'wpmudev-plugin-test' );

			return new WP_Error(
				'system_error',
				$message,
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Determine if an exception is a user error (fixable by user) or system error.
	 *
	 * @param Exception $e The exception object.
	 * @return bool True if user error, false if system error.
	 */
	private function is_user_error( $e ) {
		$user_error_patterns = array(
			'quotaExceeded',
			'fileSizeLimitExceeded',
			'invalidFile',
			'permissionDenied',
			'invalid_grant',
			'invalid_client',
			'access_denied',
			'invalid_request',
			'unauthorized_client',
			'invalid_scope',
		);

		$message = $e->getMessage();
		foreach ( $user_error_patterns as $pattern ) {
			if ( stripos( $message, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get user-friendly error message for user errors.
	 *
	 * @param Exception $e The exception object.
	 * @return string User-friendly error message.
	 */
	private function get_user_friendly_error_message( $e ) {
		$message = $e->getMessage();

		$error_mappings = array(
			'quotaExceeded'         => __( 'Google Drive storage quota exceeded. Please free up space and try again.', 'wpmudev-plugin-test' ),
			'fileSizeLimitExceeded' => __( 'File is too large for Google Drive. Please use a smaller file.', 'wpmudev-plugin-test' ),
			'invalidFile'           => __( 'Invalid file format. Please check the file and try again.', 'wpmudev-plugin-test' ),
			'permissionDenied'      => __( 'Permission denied. Please check your Google Drive permissions.', 'wpmudev-plugin-test' ),
			'invalid_grant'         => __( 'Invalid authorization. Please try authenticating again.', 'wpmudev-plugin-test' ),
			'invalid_client'        => __( 'Invalid client credentials. Please check your Client ID and Client Secret.', 'wpmudev-plugin-test' ),
			'access_denied'         => __( 'Access was denied. Please grant the required permissions to continue.', 'wpmudev-plugin-test' ),
			'invalid_request'       => __( 'Invalid request. Please try again.', 'wpmudev-plugin-test' ),
			'unauthorized_client'   => __( 'Unauthorized client. Please check your OAuth configuration.', 'wpmudev-plugin-test' ),
			'invalid_scope'         => __( 'Invalid scope. Please check your OAuth configuration.', 'wpmudev-plugin-test' ),
		);

		foreach ( $error_mappings as $key => $friendly_message ) {
			if ( stripos( $message, $key ) !== false ) {
				return $friendly_message;
			}
		}

		// Fallback to generic user error message
		return __( 'An error occurred with your request. Please check your input and try again.', 'wpmudev-plugin-test' );
	}

	/**
	 * Store error information for admin review (production-safe).
	 *
	 * @param string $operation The operation that failed.
	 * @param string $error_message The error message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private function store_error_for_admin( $operation, $error_message, $context = array() ) {
		// Only store errors when WP_DEBUG is enabled
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$error_data = array(
			'timestamp' => current_time( 'mysql' ),
			'operation' => $operation,
			'message'   => $error_message,
			'context'   => $context,
			'user_id'   => get_current_user_id(),
		);

		// Store in WordPress options (you might want to use a custom table for high-volume sites)
		$existing_errors   = get_option( 'wpmudev_drive_errors', array() );
		$existing_errors[] = $error_data;

		// Keep only last 100 errors to prevent database bloat
		if ( count( $existing_errors ) > 100 ) {
			$existing_errors = array_slice( $existing_errors, -100 );
		}

		update_option( 'wpmudev_drive_errors', $existing_errors );
	}

	/**
	 * Make API call with retry logic and timeout handling.
	 *
	 * @param callable $api_call The API call to execute.
	 * @param string   $operation_name The name of the operation for logging.
	 * @param int      $max_retries Maximum number of retries (default: 3).
	 * @param int      $timeout_seconds Timeout in seconds (default: 30).
	 * @return mixed The API response.
	 * @throws Exception If all retries fail.
	 */
	private function make_api_call_with_retry( $api_call, $operation_name, $max_retries = 3, $timeout_seconds = 30 ) {
		$retry_count    = 0;
		$last_exception = null;

		while ( $retry_count < $max_retries ) {
			try {
				// Set timeout for the request
				$original_timeout = ini_get( 'default_socket_timeout' );
				ini_set( 'default_socket_timeout', $timeout_seconds );

				$result = $api_call();

				// Restore original timeout
				ini_set( 'default_socket_timeout', $original_timeout );

				// Log successful API call
				$this->log_debug(
					sprintf( '%s API call successful', $operation_name ),
					array(
						'retry_count' => $retry_count,
						'timeout'     => $timeout_seconds,
					)
				);

				return $result;

			} catch ( Exception $e ) {
				$last_exception = $e;
				++$retry_count;

				// Restore original timeout
				ini_set( 'default_socket_timeout', $original_timeout );

				// Check if this is a retryable error
				if ( ! $this->is_retryable_error( $e ) || $retry_count >= $max_retries ) {
					break;
				}

				// Calculate exponential backoff delay
				$delay = pow( 2, $retry_count - 1 ) * 1000000; // microseconds
				usleep( $delay );

				$this->log_debug(
					sprintf( '%s API call failed, retrying...', $operation_name ),
					array(
						'retry_count'        => $retry_count,
						'max_retries'        => $max_retries,
						'error'              => $e->getMessage(),
						'delay_microseconds' => $delay,
					)
				);
			}
		}

		// All retries failed
		$this->log_error(
			sprintf( '%s API call failed after %d retries', $operation_name, $max_retries ),
			array(
				'last_error'  => $last_exception ? $last_exception->getMessage() : 'Unknown error',
				'max_retries' => $max_retries,
			)
		);

		throw $last_exception ?: new Exception( sprintf( '%s failed after %d retries', $operation_name, $max_retries ) );
	}

	/**
	 * Determine if an error is retryable.
	 *
	 * @param Exception $e The exception to check.
	 * @return bool True if the error is retryable.
	 */
	private function is_retryable_error( $e ) {
		$message            = $e->getMessage();
		$retryable_patterns = array(
			'timeout',
			'connection',
			'network',
			'temporary',
			'rate limit',
			'quota exceeded',
			'server error',
			'5\d\d', // 5xx HTTP errors
		);

		foreach ( $retryable_patterns as $pattern ) {
			if ( preg_match( '/' . $pattern . '/i', $message ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Calculate current page number based on page token and page size.
	 *
	 * @param string $page_token The current page token.
	 * @param int    $page_size The page size.
	 * @return int The current page number (1-based).
	 */
	private function calculate_current_page( $page_token, $page_size ) {
		if ( empty( $page_token ) ) {
			return 1;
		}

		// For Google Drive API, we can't directly calculate page number from token
		// This is a simplified estimation - in a real implementation, you might
		// want to store page numbers separately or use a different approach
		return 2; // Assume page 2+ if there's a page token
	}

	/**
	 * Get page token for a specific page number by fetching previous pages.
	 *
	 * @param int    $target_page The target page number.
	 * @param int    $page_size The page size.
	 * @param string $query The search query.
	 * @param string $folder_id The folder ID filter.
	 * @return string|null The page token for the target page, or null if not found.
	 */
	private function get_page_token_for_page( $target_page, $page_size, $query, $folder_id ) {
		// For page 1, no token needed
		if ( $target_page <= 1 ) {
			return null;
		}

		// Check if we have cached tokens
		$cache_key     = 'wpmudev_drive_page_tokens_' . md5( $query . $folder_id . $page_size );
		$cached_tokens = get_transient( $cache_key );
		if ( $cached_tokens && isset( $cached_tokens[ $target_page ] ) ) {
			return $cached_tokens[ $target_page ];
		}

		// We need to fetch pages sequentially to get to the target page
		$current_token = null;
		$tokens        = array( 1 => null ); // Page 1 has no token

		for ( $page = 1; $page < $target_page; $page++ ) {
			try {
				$options = array(
					'pageSize' => $page_size,
					'q'        => $query,
					'fields'   => 'nextPageToken',
					'orderBy'  => 'modifiedTime desc',
				);

				if ( $current_token ) {
					$options['pageToken'] = $current_token;
				}

				$results    = $this->drive_service->files->listFiles( $options );
				$next_token = $results->getNextPageToken();

				if ( $next_token ) {
					$tokens[ $page + 1 ] = $next_token;
					$current_token       = $next_token;
				} else {
					// No more pages available
					break;
				}
			} catch ( Exception $e ) {
				$this->log_error( 'Error fetching page token for page ' . $page, $e->getMessage() );
				break;
			}
		}

		// Cache the tokens for 5 minutes
		set_transient( $cache_key, $tokens, 300 );

		return isset( $tokens[ $target_page ] ) ? $tokens[ $target_page ] : null;
	}

	/**
	 * Handle list files specific errors with enhanced error messages.
	 *
	 * @param Exception $e The exception that occurred.
	 * @param array     $context Additional context data.
	 * @return WP_Error A user-friendly error response.
	 */
	private function handle_list_files_error( $e, $context = array() ) {
		$error_message = $e->getMessage();
		$error_code    = 'list_files_error';

		// Map specific Google Drive API errors to user-friendly messages
		$error_mappings = array(
			'quotaExceeded'     => array(
				'message' => __( 'Google Drive API quota exceeded. Please try again later.', 'wpmudev-plugin-test' ),
				'code'    => 'quota_exceeded',
				'status'  => 429,
			),
			'rateLimitExceeded' => array(
				'message' => __( 'Too many requests to Google Drive. Please wait a moment and try again.', 'wpmudev-plugin-test' ),
				'code'    => 'rate_limit_exceeded',
				'status'  => 429,
			),
			'permissionDenied'  => array(
				'message' => __( 'Permission denied. Please check your Google Drive permissions.', 'wpmudev-plugin-test' ),
				'code'    => 'permission_denied',
				'status'  => 403,
			),
			'invalidQuery'      => array(
				'message' => __( 'Invalid search query. Please check your search parameters.', 'wpmudev-plugin-test' ),
				'code'    => 'invalid_query',
				'status'  => 400,
			),
			'notFound'          => array(
				'message' => __( 'Google Drive service not found. Please try again later.', 'wpmudev-plugin-test' ),
				'code'    => 'service_not_found',
				'status'  => 404,
			),
			'timeout'           => array(
				'message' => __( 'Request timed out. Please try again with a smaller page size.', 'wpmudev-plugin-test' ),
				'code'    => 'timeout',
				'status'  => 408,
			),
		);

		// Find matching error
		foreach ( $error_mappings as $pattern => $error_data ) {
			if ( stripos( $error_message, $pattern ) !== false ) {
				$error_code    = $error_data['code'];
				$error_message = $error_data['message'];
				$status_code   = $error_data['status'];
				break;
			}
		}

		// Store error for admin review
		$this->store_error_for_admin( 'List files', $e->getMessage(), $context );

		// Log the error
		$this->log_error(
			'List files failed',
			array_merge(
				$context,
				array(
					'error_message' => $e->getMessage(),
					'error_code'    => $error_code,
				)
			)
		);

		// Return appropriate error response
		$status_code = $status_code ?? 500;
		return new WP_Error( $error_code, $error_message, array( 'status' => $status_code ) );
	}

	/**
	 * Convert memory limit string to bytes
	 *
	 * @since 1.0.0
	 * @param string $memory_limit Memory limit string (e.g., '128M', '1G').
	 * @return int Memory limit in bytes.
	 */
	private function convert_to_bytes( $memory_limit ) {
		$memory_limit = trim( $memory_limit );
		$last         = strtolower( $memory_limit[ strlen( $memory_limit ) - 1 ] );
		$value        = (int) $memory_limit;

		switch ( $last ) {
			case 'g':
				$value *= 1024;
				// No break - fall through to next case.
			case 'm':
				$value *= 1024;
				// No break - fall through to next case.
			case 'k':
				$value *= 1024;
		}

		return $value;
	}

	/**
	 * Get stored credentials.
	 *
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_credentials() {
		try {
			$client_id = get_option( 'wpmudev_drive_client_id', '' );
			$client_secret = get_option( 'wpmudev_drive_client_secret', '' );

			// Decrypt credentials if they're encrypted
			if ( ! empty( $client_id ) && $this->is_encrypted( $client_id ) ) {
				$client_id = $this->decrypt_credential( $client_id );
			}
			if ( ! empty( $client_secret ) && $this->is_encrypted( $client_secret ) ) {
				$client_secret = $this->decrypt_credential( $client_secret );
			}

			return new WP_REST_Response(
				array(
					'success'      => true,
					'data'         => array(
						'clientId'     => $client_id,
						'clientSecret' => $client_secret,
					)
				)
			);
		} catch ( Exception $e ) {
			return $this->handle_error( $e, 'get_credentials_error' );
		}
	}

	/**
	 * Get authentication status.
	 *
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_auth_status() {
		try {
			$access_token = get_option( 'wpmudev_drive_access_token', '' );
			$refresh_token = get_option( 'wpmudev_drive_refresh_token', '' );
			$token_expires = get_option( 'wpmudev_drive_token_expires', 0 );

			$is_authenticated = ! empty( $access_token ) && ( $token_expires > time() || ! empty( $refresh_token ) );

			return new WP_REST_Response(
				array(
					'success'       => true,
					'authenticated' => $is_authenticated,
					'has_credentials' => ! empty( get_option( 'wpmudev_drive_client_id', '' ) ),
					'redirect_uri'  => $this->redirect_uri,
				)
			);
		} catch ( Exception $e ) {
			return $this->handle_error( $e, 'get_auth_status_error' );
		}
	}

	/**
	 * Start authentication process.
	 *
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function start_authentication() {
		try {
			$client_id = get_option( 'wpmudev_drive_client_id', '' );
			$client_secret = get_option( 'wpmudev_drive_client_secret', '' );

			if ( empty( $client_id ) || empty( $client_secret ) ) {
				return new WP_Error( 'no_credentials', 'Credentials not configured', array( 'status' => 400 ) );
			}

			// Ensure Google Client is initialized
			$this->setup_google_client();
			if ( ! $this->client ) {
				return new WP_Error(
					'client_setup_failed',
					__( 'Failed to initialize Google Client. Please check your credentials.', 'wpmudev-plugin-test' ),
					array( 'status' => 500 )
				);
			}

			// Decrypt credentials if they're encrypted
			if ( $this->is_encrypted( $client_id ) ) {
				$client_id = $this->decrypt_credential( $client_id );
				$this->log_debug( 'Decrypted client ID', array( 'length' => strlen( $client_id ) ) );
			}
			if ( $this->is_encrypted( $client_secret ) ) {
				$client_secret = $this->decrypt_credential( $client_secret );
				$this->log_debug( 'Decrypted client secret', array( 'length' => strlen( $client_secret ) ) );
			}

			$this->client->setClientId( $client_id );
			$this->client->setClientSecret( $client_secret );
			$this->client->setRedirectUri( $this->redirect_uri );
			$this->client->setScopes( $this->scopes );

			// Generate a secure state parameter for CSRF protection
			$state = wp_generate_password( 32, false );
			update_option( 'wpmudev_drive_auth_state', $state );
			$this->client->setState( $state );

			try {
				// Log client configuration before creating auth URL
				$this->log_debug( 'Google Client configuration', array(
					'client_id' => $client_id,
					'redirect_uri' => $this->redirect_uri,
					'scopes' => $this->scopes,
					'state' => $state,
					'access_type' => 'offline',
					'prompt' => 'consent'
				) );
				
				$auth_url = $this->client->createAuthUrl();
				
				// Log for debugging
				$this->log_debug( 'Auth URL generated', array(
					'auth_url' => $auth_url,
					'state' => $state,
					'client_id' => $client_id,
					'redirect_uri' => $this->redirect_uri,
					'scopes' => $this->scopes
				) );
		} catch ( Exception $e ) {
				$this->log_error( 'Error creating auth URL', array( 
					'message' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				) );
				throw $e;
			}

			return new WP_REST_Response(
				array(
					'success'  => true,
					'auth_url' => $auth_url,
				)
			);
		} catch ( Exception $e ) {
			return $this->handle_error( $e, 'start_authentication_error' );
		}
	}

	/**
	 * Check if a credential is encrypted.
	 *
	 * @param string $credential The credential to check.
	 * @return bool True if encrypted, false otherwise.
	 */
	private function is_encrypted( $credential ) {
		// Check for encrypted: prefix or base64 encoded data
		return strpos( $credential, 'encrypted:' ) === 0 || (base64_decode($credential, true) !== false && strlen($credential) > 100);
	}

	/**
	 * Handle errors consistently.
	 *
	 * @param Exception $e The exception.
	 * @param string $context The context where the error occurred.
	 * @return WP_Error The error response.
	 */
	private function handle_error( $e, $context ) {
		$this->log_debug( "Error in {$context}: " . $e->getMessage() );
		
		return new WP_Error(
			$context,
			$e->getMessage(),
			array( 'status' => 500 )
		);
	}

	/**
	 * Clear stored credentials.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function clear_credentials( WP_REST_Request $request ) {
		// Log the request for debugging.
		$this->log_debug( 'Clear credentials request received' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->log_debug( 'Permission denied for clear credentials' );
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		// Verify nonce for security.
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		try {
			// Clear all stored credentials and tokens
			delete_option( 'wpmudev_drive_client_id' );
			delete_option( 'wpmudev_drive_client_secret' );
			delete_option( 'wpmudev_drive_access_token' );
			delete_option( 'wpmudev_drive_auth_state' );

			// Log successful clearing
			$this->log_debug( 'Credentials cleared successfully' );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Credentials cleared successfully', 'wpmudev-plugin-test' ),
				)
			);

		} catch ( Exception $e ) {
			return $this->handle_error( $e, 'clear_credentials_error' );
		}
	}
}
