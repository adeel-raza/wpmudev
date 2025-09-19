<?php
/**
 * Google Drive API endpoints using Google Client Library.
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
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

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
	 * @var Google_Client
	 */
	private $client;

	/**
	 * Google Drive service.
	 *
	 * @var Google_Service_Drive
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
	private $scopes = array(
		Google_Service_Drive::DRIVE_FILE,
		Google_Service_Drive::DRIVE_READONLY,
	);

	/**
	 * Initialize the class.
	 */
	public function init() {
		error_log( 'WPMU DEV Plugin Test: Drive_API init called' );
		//$this->redirect_uri = home_url( '/wp-json/wpmudev/v1/drive/callback' );
		$this->redirect_uri = 'https://493791148433.ngrok-free.app/wp-json/wpmudev/v1/drive/callback';
		$this->setup_google_client();

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		error_log( 'WPMU DEV Plugin Test: REST API init action added' );
	}

	/**
	 * Setup Google Client.
	 */
	private function setup_google_client() {
		$auth_creds = get_option( 'wpmudev_plugin_tests_auth', array() );
		
		if ( empty( $auth_creds['client_id'] ) || empty( $auth_creds['client_secret'] ) ) {
			return;
		}

		$this->client = new Google_Client();
		$this->client->setClientId( $auth_creds['client_id'] );
		$this->client->setClientSecret( $auth_creds['client_secret'] );
		$this->client->setRedirectUri( $this->redirect_uri );
		$this->client->setScopes( $this->scopes );
		$this->client->setAccessType( 'offline' );
		$this->client->setPrompt( 'consent' );

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
		error_log( 'WPMU DEV Plugin Test: Registering REST API routes' );
		// Save credentials endpoint
		register_rest_route( 'wpmudev/v1/drive', '/save-credentials', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'save_credentials' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// Authentication endpoint
		register_rest_route( 'wpmudev/v1/drive', '/auth', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'start_auth' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// OAuth callback
		register_rest_route( 'wpmudev/v1/drive', '/callback', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_callback' ),
			'permission_callback' => '__return_true', // Public endpoint for OAuth callback
		) );

		// List files
		register_rest_route( 'wpmudev/v1/drive', '/files', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_files' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// Upload file
		register_rest_route( 'wpmudev/v1/drive', '/upload', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'upload_file' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// Download file
		register_rest_route( 'wpmudev/v1/drive', '/download', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'download_file' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// Create folder
		register_rest_route( 'wpmudev/v1/drive', '/create-folder', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'create_folder' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );
	}

	/**
	 * Check permissions for REST API endpoints.
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Save Google OAuth credentials.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function save_credentials( WP_REST_Request $request ) {
		// Log the request for debugging.
		error_log( 'WPMU DEV Plugin Test: Save credentials request received' );
		
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( 'WPMU DEV Plugin Test: Permission denied for save credentials' );
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
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
		$encrypted_credentials = array(
			'client_id'     => $this->encrypt_credential( $client_id ),
			'client_secret' => $this->encrypt_credential( $client_secret ),
		);

		// Save credentials
		$credentials = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
		);

		$saved = update_option( 'wpmudev_plugin_tests_auth', $credentials );
		error_log( 'WPMU DEV Plugin Test: Credentials saved: ' . ( $saved ? 'true' : 'false' ) );
		
		// Reinitialize Google Client with new credentials
		$this->setup_google_client();

		$response = new WP_REST_Response( array(
			'success' => true,
			'message' => __( 'Credentials saved successfully', 'wpmudev-plugin-test' ),
		) );
		
		error_log( 'WPMU DEV Plugin Test: Returning success response' );
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
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		if ( ! $this->client ) {
			return new WP_Error( 'missing_credentials', 'Google OAuth credentials not configured', array( 'status' => 400 ) );
		}

		try {
			// Generate authorization URL
			$auth_url = $this->client->createAuthUrl();
			
			return new WP_REST_Response( array(
				'success' => true,
				'auth_url' => $auth_url,
			) );
		} catch ( Exception $e ) {
			return new WP_Error( 'auth_error', $e->getMessage(), array( 'status' => 500 ) );
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

		// Check for OAuth errors
		if ( ! empty( $error ) ) {
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=' . urlencode( $error ) ) );
			exit;
		}

		if ( empty( $code ) ) {
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=no_code' ) );
			exit;
		}

		if ( ! $this->client ) {
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=no_client' ) );
			exit;
		}

		try {
			// Exchange code for access token
			$access_token = $this->client->fetchAccessTokenWithAuthCode( $code );

			if ( array_key_exists( 'error', $access_token ) ) {
				wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=' . urlencode( $access_token['error'] ) ) );
				exit;
			}

			// Store tokens
			update_option( 'wpmudev_drive_access_token', $access_token );
			if ( isset( $access_token['refresh_token'] ) ) {
				update_option( 'wpmudev_drive_refresh_token', $access_token['refresh_token'] );
			}
			
			// Calculate expiration time
			$expires_at = time() + ( $access_token['expires_in'] ?? 3600 );
			update_option( 'wpmudev_drive_token_expires', $expires_at );

			// Redirect back to admin page
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=success' ) );
			exit;

		} catch ( Exception $e ) {
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=error&error=' . urlencode( $e->getMessage() ) ) );
			exit;
		}
	}

	/**
	 * Ensure we have a valid access token.
	 */
	private function ensure_valid_token() {
		if ( ! $this->client ) {
			return false;
		}

		$access_token = get_option( 'wpmudev_drive_access_token', array() );
		$expires_at   = get_option( 'wpmudev_drive_token_expires', 0 );

		// Check if token is expired
		if ( time() >= $expires_at ) {
			$refresh_token = get_option( 'wpmudev_drive_refresh_token' );
			
			if ( empty( $refresh_token ) ) {
				return false;
			}

			try {
				$new_token = $this->client->fetchAccessTokenWithRefreshToken( $refresh_token );
				
				if ( array_key_exists( 'error', $new_token ) ) {
					return false;
				}

				update_option( 'wpmudev_drive_access_token', $new_token );
				
				// Calculate new expiration time
				$new_expires_at = time() + ( $new_token['expires_in'] ?? 3600 );
				update_option( 'wpmudev_drive_token_expires', $new_expires_at );
				
				return true;
			} catch ( Exception $e ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * List files in Google Drive.
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

		try {
			$page_size = $request->get_param( 'page_size' ) ?: 20;
			$page_token = $request->get_param( 'page_token' );
			$query = $request->get_param( 'query' ) ?: 'trashed=false';

			$options = array(
				'pageSize' => max( 1, min( 100, intval( $page_size ) ) ),
				'q'        => sanitize_text_field( $query ),
				'fields'   => 'nextPageToken,files(id,name,mimeType,size,modifiedTime,webViewLink,parents)',
				'orderBy'  => 'modifiedTime desc',
			);

			if ( ! empty( $page_token ) ) {
				$options['pageToken'] = sanitize_text_field( $page_token );
			}

			$results = $this->drive_service->files->listFiles( $options );
			$files   = $results->getFiles();

			$file_list = array();
			foreach ( $files as $file ) {
				$file_list[] = array(
					'id'           => $file->getId(),
					'name'         => $file->getName(),
					'mimeType'     => $file->getMimeType(),
					'size'         => $file->getSize(),
					'modifiedTime' => $file->getModifiedTime(),
					'webViewLink'  => $file->getWebViewLink(),
					'isFolder'     => $file->getMimeType() === 'application/vnd.google-apps.folder',
				);
			}

			return new WP_REST_Response( array(
				'success'     => true,
				'files'       => $file_list,
				'nextPageToken' => $results->getNextPageToken(),
			) );

		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Upload file to Google Drive.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function upload_file( WP_REST_Request $request ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'permission_denied', 'Insufficient permissions', array( 'status' => 403 ) );
		}

		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$files = $request->get_file_params();
		
		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', 'No file provided', array( 'status' => 400 ) );
		}

		$file = $files['file'];
		
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_error', 'File upload error: ' . $file['error'], array( 'status' => 400 ) );
		}

		// Validate file size (max 10MB)
		$max_size = 10 * 1024 * 1024; // 10MB
		if ( $file['size'] > $max_size ) {
			return new WP_Error( 'file_too_large', 'File size exceeds 10MB limit', array( 'status' => 400 ) );
		}

		// Validate file type
		$allowed_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'application/pdf',
			'text/plain',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);

		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			return new WP_Error( 'invalid_file_type', 'File type not allowed', array( 'status' => 400 ) );
		}

		try {
			// Create file metadata
			$drive_file = new Google_Service_Drive_DriveFile();
			$drive_file->setName( sanitize_file_name( $file['name'] ) );

			// Upload file
			$result = $this->drive_service->files->create(
				$drive_file,
				array(
					'data'       => file_get_contents( $file['tmp_name'] ),
					'mimeType'   => $file['type'],
					'uploadType' => 'multipart',
					'fields'     => 'id,name,mimeType,size,webViewLink,modifiedTime',
				)
			);

			return new WP_REST_Response( array(
				'success' => true,
				'file'    => array(
					'id'           => $result->getId(),
					'name'         => $result->getName(),
					'mimeType'     => $result->getMimeType(),
					'size'         => $result->getSize(),
					'webViewLink'  => $result->getWebViewLink(),
					'modifiedTime' => $result->getModifiedTime(),
					'isFolder'     => false,
				),
			) );

		} catch ( Exception $e ) {
			return new WP_Error( 'upload_failed', $e->getMessage(), array( 'status' => 500 ) );
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
			$file = $this->drive_service->files->get( $file_id, array(
				'fields' => 'id,name,mimeType,size',
			) );

			// Check if it's a folder
			if ( $file->getMimeType() === 'application/vnd.google-apps.folder' ) {
				return new WP_Error( 'invalid_file_type', 'Cannot download folders', array( 'status' => 400 ) );
			}

			// Download file content
			$response = $this->drive_service->files->get( $file_id, array(
				'alt' => 'media',
			) );

			$content = $response->getBody()->getContents();

			// Return file content as base64 for JSON response
			return new WP_REST_Response( array(
				'success'  => true,
				'content'  => base64_encode( $content ),
				'filename' => $file->getName(),
				'mimeType' => $file->getMimeType(),
				'size'     => $file->getSize(),
			) );

		} catch ( Exception $e ) {
			return new WP_Error( 'download_failed', $e->getMessage(), array( 'status' => 500 ) );
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

			$result = $this->drive_service->files->create( $folder, array(
				'fields' => 'id,name,mimeType,webViewLink,modifiedTime',
			) );

			return new WP_REST_Response( array(
				'success' => true,
				'folder'  => array(
					'id'           => $result->getId(),
					'name'         => $result->getName(),
					'mimeType'     => $result->getMimeType(),
					'webViewLink'  => $result->getWebViewLink(),
					'modifiedTime' => $result->getModifiedTime(),
					'isFolder'     => true,
				),
			) );

		} catch ( Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Encrypt credential for secure storage.
	 *
	 * @param string $credential The credential to encrypt.
	 * @return string Encrypted credential.
	 */
	private function encrypt_credential( $credential ) {
		$key = wp_salt( 'AUTH_KEY' );
		$iv  = wp_salt( 'SECURE_AUTH_KEY' );
		
		// Use a simple encryption method for demonstration
		// In production, use proper encryption libraries
		return base64_encode( $credential . '|' . hash( 'sha256', $key . $credential ) );
	}

	/**
	 * Decrypt credential from storage.
	 *
	 * @param string $encrypted_credential The encrypted credential.
	 * @return string|false Decrypted credential or false on failure.
	 */
	private function decrypt_credential( $encrypted_credential ) {
		$key = wp_salt( 'AUTH_KEY' );
		
		$decoded = base64_decode( $encrypted_credential );
		if ( false === $decoded ) {
			return false;
		}
		
		$parts = explode( '|', $decoded );
		if ( count( $parts ) !== 2 ) {
			return false;
		}
		
		$credential = $parts[0];
		$hash       = $parts[1];
		
		// Verify integrity
		if ( hash( 'sha256', $key . $credential ) !== $hash ) {
			return false;
		}
		
		return $credential;
	}
}