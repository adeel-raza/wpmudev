<?php
/**
 * Google Drive test block.
 *
 * @link          https://wpmudev.com/
 * @since         1.0.0
 *
 * @author        WPMUDEV (https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2025, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\App\Admin_Pages;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;

/**
 * Google Drive admin page class.
 *
 * Handles the Google Drive test page functionality including
 * credential management and authentication.
 */
class Google_Drive extends Base {
	/**
	 * The page title.
	 *
	 * @var string
	 */
	private $page_title;

	/**
	 * The page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'wpmudev_plugintest_drive';

	/**
	 * Google Drive auth credentials.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $creds = array();

	/**
	 * Option name for credentials (reusing the same as original auth).
	 *
	 * @var string
	 */
	private $option_name = 'wpmudev_plugin_tests_auth';

	/**
	 * Page Assets.
	 *
	 * @var array
	 */
	private $page_scripts = array();

	/**
	 * Assets version.
	 *
	 * @var string
	 */
	private $assets_version = '';

	/**
	 * A unique string id to be used in markup and jsx.
	 *
	 * @var string
	 */
	private $unique_id = '';

	/**
	 * Initializes the page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		$this->page_title     = __( 'Google Drive Test', 'wpmudev-plugin-test' );
		$this->creds          = get_option( $this->option_name, array() );
		$this->assets_version = ! empty( $this->script_data( 'version' ) ) ? $this->script_data( 'version' ) : WPMUDEV_PLUGINTEST_VERSION;
		$this->unique_id      = "wpmudev_plugintest_drive_main_wrap-{$this->assets_version}";

		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		// Add body class to admin pages.
		add_filter( 'admin_body_class', array( $this, 'admin_body_classes' ) );
	}

	/**
	 * Register the admin page.
	 *
	 * @since 1.0.0
	 */
	public function register_admin_page() {
		$page = add_menu_page(
			'Google Drive Test',
			$this->page_title,
			'manage_options',
			$this->page_slug,
			array( $this, 'callback' ),
			'dashicons-cloud',
			7
		);

		add_action( 'load-' . $page, array( $this, 'prepare_assets' ) );
	}

	/**
	 * The admin page callback method.
	 *
	 * @return void
	 */
	public function callback() {
		// Handle URL parameters for user feedback
		$this->handle_url_parameters();
		$this->view();
	}

	/**
	 * Handle URL parameters for user feedback and state management.
	 *
	 * @return void
	 */
	private function handle_url_parameters() {
		// Check for authentication success/error parameters
		if ( isset( $_GET['auth'] ) ) {
			if ( 'success' === $_GET['auth'] ) {
				add_action( 'admin_notices', array( $this, 'show_auth_success_notice' ) );
			} elseif ( 'error' === $_GET['auth'] ) {
				$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : 'Unknown error';
				add_action(
					'admin_notices',
					function () use ( $error ) {
						$this->show_auth_error_notice( $error );
					}
				);
			}
		}
	}

	/**
	 * Show authentication success notice.
	 *
	 * @return void
	 */
	public function show_auth_success_notice() {
		// Get user info if available
		$user_info  = get_option( 'wpmudev_drive_user_info', null );
		$user_name  = $user_info['name'] ?? '';
		$user_email = $user_info['email'] ?? '';

		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>' . esc_html__( 'Successfully authenticated with Google Drive!', 'wpmudev-plugin-test' ) . '</strong></p>';

		if ( $user_name || $user_email ) {
			echo '<p>';
			if ( $user_name ) {
				printf(
					/* translators: %s: User display name */
					esc_html__( 'Connected as: %s', 'wpmudev-plugin-test' ),
					'<strong>' . esc_html( $user_name ) . '</strong>'
				);
			}
			if ( $user_email ) {
				if ( $user_name ) {
					echo ' (';
				}
				echo esc_html( $user_email );
				if ( $user_name ) {
					echo ')';
				}
			}
			echo '</p>';
		}

		echo '<p>' . esc_html__( 'You can now manage your Google Drive files from this page.', 'wpmudev-plugin-test' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Show authentication error notice.
	 *
	 * @param string $error The error message.
	 * @return void
	 */
	public function show_auth_error_notice( $error ) {
		// Decode URL-encoded error messages
		$error = urldecode( $error );

		// Map common error codes to user-friendly messages
		$error_messages = array(
			'access_denied'           => __( 'Access was denied. Please grant the required permissions to continue.', 'wpmudev-plugin-test' ),
			'invalid_request'         => __( 'Invalid request. Please try again.', 'wpmudev-plugin-test' ),
			'unauthorized_client'     => __( 'Unauthorized client. Please check your OAuth configuration.', 'wpmudev-plugin-test' ),
			'invalid_scope'           => __( 'Invalid scope. Please check your OAuth configuration.', 'wpmudev-plugin-test' ),
			'server_error'            => __( 'Google server error. Please try again later.', 'wpmudev-plugin-test' ),
			'temporarily_unavailable' => __( 'Google service temporarily unavailable. Please try again later.', 'wpmudev-plugin-test' ),
			'invalid_grant'           => __( 'Invalid authorization code. Please try authenticating again.', 'wpmudev-plugin-test' ),
			'invalid_client'          => __( 'Invalid client credentials. Please check your Client ID and Client Secret.', 'wpmudev-plugin-test' ),
			'no_code'                 => __( 'Authorization code not received from Google.', 'wpmudev-plugin-test' ),
			'no_client'               => __( 'Google Client not configured. Please check your credentials.', 'wpmudev-plugin-test' ),
		);

		$friendly_error = isset( $error_messages[ $error ] ) ? $error_messages[ $error ] : $error;

		echo '<div class="notice notice-error is-dismissible">';
		echo '<p><strong>' . esc_html__( 'Authentication failed', 'wpmudev-plugin-test' ) . '</strong></p>';
		echo '<p>' . esc_html( $friendly_error ) . '</p>';

		// Add helpful suggestions based on error type
		if ( strpos( $error, 'client' ) !== false ) {
			echo '<p><strong>' . esc_html__( 'Suggestion:', 'wpmudev-plugin-test' ) . '</strong> ';
			echo esc_html__( 'Please verify your Client ID and Client Secret in the Google Cloud Console.', 'wpmudev-plugin-test' );
			echo '</p>';
		} elseif ( strpos( $error, 'scope' ) !== false ) {
			echo '<p><strong>' . esc_html__( 'Suggestion:', 'wpmudev-plugin-test' ) . '</strong> ';
			echo esc_html__( 'Please ensure the required scopes are enabled in your Google Cloud Console project.', 'wpmudev-plugin-test' );
			echo '</p>';
		} elseif ( strpos( $error, 'access_denied' ) !== false ) {
			echo '<p><strong>' . esc_html__( 'Suggestion:', 'wpmudev-plugin-test' ) . '</strong> ';
			echo esc_html__( 'Please click "Allow" when prompted by Google to grant the required permissions.', 'wpmudev-plugin-test' );
			echo '</p>';
		}

		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wpmudev_plugintest_drive' ) ) . '" class="button button-primary">';
		echo esc_html__( 'Try Again', 'wpmudev-plugin-test' );
		echo '</a></p>';
		echo '</div>';
	}

	/**
	 * Prepares assets.
	 *
	 * @return void
	 */
	public function prepare_assets() {
		if ( ! is_array( $this->page_scripts ) ) {
			$this->page_scripts = array();
		}

		$handle       = 'wpmudev_plugintest_drivepage';
		$src          = WPMUDEV_PLUGINTEST_ASSETS_URL . '/js/drivetestpage.min.js';
		$style_src    = WPMUDEV_PLUGINTEST_ASSETS_URL . '/css/drivetestpage.min.css';
		$dependencies = array(
			'wp-element',
			'wp-components',
			'wp-i18n',
			'wp-api-fetch',
			'wp-polyfill',
		);

		// Get comprehensive status for conditional rendering
		$status = $this->get_comprehensive_status();

		$this->page_scripts[ $handle ] = array(
			'src'       => $src,
			'style_src' => $style_src,
			'deps'      => $dependencies,
			'ver'       => $this->assets_version,
			'strategy'  => true,
			'localize'  => array(
				'dom_element_id'         => $this->unique_id,
				'restEndpointSave'       => 'wpmudev/v1/drive/save-credentials',
				'restEndpointAuth'       => 'wpmudev/v1/drive/auth',
				'restEndpointFiles'      => 'wpmudev/v1/drive/files',
				'restEndpointUpload'     => 'wpmudev/v1/drive/upload',
				'restEndpointDownload'   => 'wpmudev/v1/drive/download',
				'restEndpointDelete'     => 'wpmudev/v1/drive/delete',
				'restEndpointCreate'     => 'wpmudev/v1/drive/create-folder',
				'restEndpointStatus'     => 'wpmudev/v1/drive/status',
				'restEndpointDisconnect' => 'wpmudev/v1/drive/disconnect',
				'nonce'                  => wp_create_nonce( 'wp_rest' ),
				// Legacy fields for backward compatibility
				'authStatus'             => $status['is_authenticated'],
				'redirectUri'            => $status['redirect_uri'],
				'hasCredentials'         => $status['has_credentials'],
				// Enhanced status information
				'status'                 => $status,
				// Translation strings.
				'i18n'                   => $this->get_i18n_strings(),
			),
		);

		// Enqueue the assets immediately
		$this->enqueue_assets();
	}

	/**
	 * Checks if user is authenticated with Google Drive.
	 *
	 * @return bool
	 */
	private function get_auth_status() {
		$access_token = get_option( 'wpmudev_drive_access_token', '' );
		$expires_at   = get_option( 'wpmudev_drive_token_expires', 0 );

		return ! empty( $access_token ) && time() < $expires_at;
	}

	/**
	 * Get comprehensive status information for conditional rendering.
	 *
	 * @return array
	 */
	private function get_comprehensive_status() {
		$auth_creds      = get_option( $this->option_name, array() );
		$has_credentials = ! empty( $auth_creds['client_id'] ) && ! empty( $auth_creds['client_secret'] );

		$access_token     = get_option( 'wpmudev_drive_access_token', '' );
		$expires_at       = get_option( 'wpmudev_drive_token_expires', 0 );
		$is_authenticated = ! empty( $access_token ) && time() < $expires_at;

		// Check if we have a valid Google Client setup
		$client_configured = $has_credentials;

		// Determine the current state for conditional rendering
		$current_state = 'setup'; // Default state

		if ( $has_credentials && $is_authenticated ) {
			$current_state = 'authenticated';
		} elseif ( $has_credentials && ! $is_authenticated ) {
			$current_state = 'needs_auth';
		} elseif ( ! $has_credentials ) {
			$current_state = 'needs_credentials';
		}

		return array(
			'has_credentials'   => $has_credentials,
			'is_authenticated'  => $is_authenticated,
			'client_configured' => $client_configured,
			'current_state'     => $current_state,
			'redirect_uri'      => 'https://4b8375fd38b2.ngrok-free.app/wp-json/wpmudev/v1/drive/callback',
			
			'required_scopes'   => array(
				'https://www.googleapis.com/auth/drive.file',
				'https://www.googleapis.com/auth/drive.readonly',
			),
		);
	}

	/**
	 * Get internationalization strings for JavaScript.
	 *
	 * @return array
	 */
	private function get_i18n_strings() {
		return array(
			// Page titles and descriptions.
			'pageTitle'                     => __( 'Google Drive Test', 'wpmudev-plugin-test' ),
			'pageDescription'               => __( 'Test Google Drive API integration for applicant assessment', 'wpmudev-plugin-test' ),

			// Credentials section.
			'credentialsTitle'              => __( 'Set Google Drive Credentials', 'wpmudev-plugin-test' ),
			'clientIdLabel'                 => __( 'Client ID', 'wpmudev-plugin-test' ),
			'clientIdHelp'                  => __( 'You can get Client ID from Google Cloud Console. Make sure to enable Google Drive API.', 'wpmudev-plugin-test' ),
			'clientSecretLabel'             => __( 'Client Secret', 'wpmudev-plugin-test' ),
			'clientSecretHelp'              => __( 'You can get Client Secret from Google Cloud Console.', 'wpmudev-plugin-test' ),
			'redirectUriLabel'              => __( 'Please use this URL in your Google API\'s Authorized redirect URIs field.', 'wpmudev-plugin-test' ),
			'requiredScopesTitle'           => __( 'Required scopes for Google Drive API:', 'wpmudev-plugin-test' ),
			'saveCredentialsButton'         => __( 'Save Credentials', 'wpmudev-plugin-test' ),

			// Authentication section.
			'authTitle'                     => __( 'Authenticate with Google Drive', 'wpmudev-plugin-test' ),
			'authDescription'               => __( 'Please authenticate with Google Drive to proceed with the test.', 'wpmudev-plugin-test' ),
			'authPermissionsTitle'          => __( 'This test will require the following permissions:', 'wpmudev-plugin-test' ),
			'authPermission1'               => __( 'View and manage Google Drive files', 'wpmudev-plugin-test' ),
			'authPermission2'               => __( 'Upload new files to Drive', 'wpmudev-plugin-test' ),
			'authPermission3'               => __( 'Create folders in Drive', 'wpmudev-plugin-test' ),
			'changeCredentialsButton'       => __( 'Change Credentials', 'wpmudev-plugin-test' ),
			'authenticateButton'            => __( 'Authenticate with Google Drive', 'wpmudev-plugin-test' ),

			// File management section.
			'filesTitle'                    => __( 'Google Drive Files', 'wpmudev-plugin-test' ),
			'uploadFileButton'              => __( 'Upload File', 'wpmudev-plugin-test' ),
			'createFolderButton'            => __( 'Create Folder', 'wpmudev-plugin-test' ),
			'folderNamePlaceholder'         => __( 'Enter folder name', 'wpmudev-plugin-test' ),
			'selectFileButton'              => __( 'Select File', 'wpmudev-plugin-test' ),
			'noFileSelected'                => __( 'No file selected', 'wpmudev-plugin-test' ),
			'refreshFilesButton'            => __( 'Refresh Files', 'wpmudev-plugin-test' ),

			// File types and actions.
			'fileTypeFolder'                => __( 'Folder', 'wpmudev-plugin-test' ),
			'fileTypeFile'                  => __( 'File', 'wpmudev-plugin-test' ),
			'downloadButton'                => __( 'Download', 'wpmudev-plugin-test' ),
			'viewInDriveButton'             => __( 'View in Drive', 'wpmudev-plugin-test' ),

			// Messages and notices.
			'credentialsSaved'              => __( 'Credentials saved successfully!', 'wpmudev-plugin-test' ),
			'credentialsSaveError'          => __( 'Failed to save credentials', 'wpmudev-plugin-test' ),
			'authSuccess'                   => __( 'Authentication successful!', 'wpmudev-plugin-test' ),
			'authError'                     => __( 'Authentication failed', 'wpmudev-plugin-test' ),
			'fileUploaded'                  => __( 'File uploaded successfully!', 'wpmudev-plugin-test' ),
			'fileUploadError'               => __( 'Failed to upload file', 'wpmudev-plugin-test' ),
			'folderCreated'                 => __( 'Folder created successfully!', 'wpmudev-plugin-test' ),
			'folderCreateError'             => __( 'Failed to create folder', 'wpmudev-plugin-test' ),
			'filesLoaded'                   => __( 'Files loaded successfully!', 'wpmudev-plugin-test' ),
			'filesLoadError'                => __( 'Failed to load files', 'wpmudev-plugin-test' ),

			// Validation messages.
			'enterClientId'                 => __( 'Please enter Client ID', 'wpmudev-plugin-test' ),
			'enterClientSecret'             => __( 'Please enter Client Secret', 'wpmudev-plugin-test' ),
			'enterBothCredentials'          => __( 'Please enter both Client ID and Client Secret', 'wpmudev-plugin-test' ),
			'enterFolderName'               => __( 'Please enter a folder name', 'wpmudev-plugin-test' ),
			'selectFile'                    => __( 'Please select a file', 'wpmudev-plugin-test' ),

			// Error messages.
			'errorSavingCredentials'        => __( 'Error saving credentials:', 'wpmudev-plugin-test' ),
			'errorAuthenticating'           => __( 'Error authenticating:', 'wpmudev-plugin-test' ),
			'errorUploadingFile'            => __( 'Error uploading file:', 'wpmudev-plugin-test' ),
			'errorCreatingFolder'           => __( 'Error creating folder:', 'wpmudev-plugin-test' ),
			'errorLoadingFiles'             => __( 'Error loading files:', 'wpmudev-plugin-test' ),

			// Loading states.
			'loading'                       => __( 'Loading...', 'wpmudev-plugin-test' ),
			'saving'                        => __( 'Saving...', 'wpmudev-plugin-test' ),
			'uploading'                     => __( 'Uploading...', 'wpmudev-plugin-test' ),
			'creating'                      => __( 'Creating...', 'wpmudev-plugin-test' ),

			// Empty states.
			'noFilesFound'                  => __( 'No files found in your Drive. Upload a file or create a folder to get started.', 'wpmudev-plugin-test' ),

			// Conditional rendering states.
			'stateNeedsCredentials'         => __( 'Please configure your Google Drive credentials to get started.', 'wpmudev-plugin-test' ),
			'stateNeedsAuth'                => __( 'Credentials configured. Please authenticate with Google Drive to access your files.', 'wpmudev-plugin-test' ),
			'stateAuthenticated'            => __( 'Successfully connected to Google Drive. You can now manage your files.', 'wpmudev-plugin-test' ),
			'stateError'                    => __( 'There was an error with your Google Drive connection. Please check your credentials.', 'wpmudev-plugin-test' ),

			// Status messages.
			'statusChecking'                => __( 'Checking connection status...', 'wpmudev-plugin-test' ),
			'statusConnected'               => __( 'Connected to Google Drive', 'wpmudev-plugin-test' ),
			'statusDisconnected'            => __( 'Not connected to Google Drive', 'wpmudev-plugin-test' ),
			'statusExpired'                 => __( 'Connection expired. Please re-authenticate.', 'wpmudev-plugin-test' ),

			// Action buttons based on state.
			'configureCredentials'          => __( 'Configure Credentials', 'wpmudev-plugin-test' ),
			'reconfigureCredentials'        => __( 'Reconfigure Credentials', 'wpmudev-plugin-test' ),
			'reconnect'                     => __( 'Reconnect to Google Drive', 'wpmudev-plugin-test' ),
			'disconnect'                    => __( 'Disconnect from Google Drive', 'wpmudev-plugin-test' ),

			// Additional missing strings
			'errorLoadingStatus'            => __( 'Error loading status', 'wpmudev-plugin-test' ),
			'tryAgain'                      => __( 'Try Again', 'wpmudev-plugin-test' ),
			'fileSelected'                  => __( 'Selected file', 'wpmudev-plugin-test' ),
			'confirmDelete'                 => __( 'Are you sure you want to delete this file?', 'wpmudev-plugin-test' ),
			'deleteButton'                  => __( 'Delete', 'wpmudev-plugin-test' ),
			'deleting'                      => __( 'Deleting...', 'wpmudev-plugin-test' ),

			// Enhanced credentials management strings
			'credentialsDescription'        => __( 'Configure your Google OAuth credentials to enable Google Drive integration.', 'wpmudev-plugin-test' ),
			'clientIdPlaceholder'           => __( 'Enter Client ID', 'wpmudev-plugin-test' ),
			'clientIdFormatHelp'            => __( 'Format: 123456789-abcdefg.apps.googleusercontent.com', 'wpmudev-plugin-test' ),
			'clientSecretPlaceholder'       => __( 'Enter Client Secret', 'wpmudev-plugin-test' ),
			'clientSecretSecurityHelp'      => __( 'This will be encrypted before storage.', 'wpmudev-plugin-test' ),
			'invalidClientIdFormat'         => __( 'Invalid Client ID format. Please check your Google Console credentials.', 'wpmudev-plugin-test' ),

			// Redirect URI configuration strings
			'redirectUriTitle'              => __( 'Redirect URI Configuration', 'wpmudev-plugin-test' ),
			'redirectUriDescription'        => __( 'Add this exact URL to your Google Cloud Console OAuth 2.0 Client configuration:', 'wpmudev-plugin-test' ),
			'redirectUriHelp'               => __( 'Copy this URL and paste it in the "Authorized redirect URIs" field in your Google Cloud Console.', 'wpmudev-plugin-test' ),

			// OAuth Scopes strings
			'oauthScopesTitle'              => __( 'Required OAuth Scopes', 'wpmudev-plugin-test' ),
			'oauthScopesDescription'        => __( 'This plugin requires the following Google Drive API permissions:', 'wpmudev-plugin-test' ),
			'scopeDriveFile'                => __( 'Drive File Access', 'wpmudev-plugin-test' ),
			'scopeDriveFileDescription'     => __( 'View and manage Google Drive files and folders', 'wpmudev-plugin-test' ),
			'scopeDriveReadonly'            => __( 'Drive Read-Only Access', 'wpmudev-plugin-test' ),
			'scopeDriveReadonlyDescription' => __( 'View Google Drive files and folders', 'wpmudev-plugin-test' ),
			'scopesSecurityNote'            => __( 'These permissions are required for the plugin to function properly. The plugin will only access files you explicitly interact with.', 'wpmudev-plugin-test' ),
			'deleteError'                   => __( 'Failed to delete file', 'wpmudev-plugin-test' ),
			'folderNameLabel'               => __( 'Folder Name', 'wpmudev-plugin-test' ),

			// File operations interface strings
			'uploadFileDescription'         => __( 'Choose a file to upload to your Google Drive. Maximum file size: 10MB.', 'wpmudev-plugin-test' ),
			'folderNameTooLong'             => __( 'Folder name is too long (max 255 characters)', 'wpmudev-plugin-test' ),
			'invalidFolderName'             => __( 'Folder name contains invalid characters', 'wpmudev-plugin-test' ),
		);
	}

	/**
	 * Gets assets data for given key.
	 *
	 * @param string $key The data key to retrieve.
	 *
	 * @return string|array
	 */
	protected function script_data( string $key = '' ) {
		$raw_script_data = $this->raw_script_data();

		return ! empty( $key ) && ! empty( $raw_script_data[ $key ] ) ? $raw_script_data[ $key ] : '';
	}

	/**
	 * Gets the script data from assets php file.
	 *
	 * @return array
	 */
	protected function raw_script_data(): array {
		static $script_data = null;

		if ( is_null( $script_data ) && file_exists( WPMUDEV_PLUGINTEST_DIR . 'assets/js/drivetestpage.min.asset.php' ) ) {
			$script_data = include WPMUDEV_PLUGINTEST_DIR . 'assets/js/drivetestpage.min.asset.php';
		}

		return (array) $script_data;
	}

	/**
	 * Prepares assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! empty( $this->page_scripts ) ) {
			foreach ( $this->page_scripts as $handle => $page_script ) {
				// Check if script is already enqueued to avoid duplicates
				if ( wp_script_is( $handle, 'enqueued' ) ) {
					continue;
				}

				wp_register_script(
					$handle,
					$page_script['src'],
					$page_script['deps'],
					$page_script['ver'],
					$page_script['strategy']
				);

				if ( ! empty( $page_script['localize'] ) ) {
					wp_localize_script( $handle, 'wpmudevDriveTest', $page_script['localize'] );
				}

				wp_enqueue_script( $handle );

				if ( ! empty( $page_script['style_src'] ) ) {
					wp_enqueue_style( $handle, $page_script['style_src'], array(), $this->assets_version );
				}
			}
		}
	}

	/**
	 * Prints the wrapper element which React will use as root.
	 *
	 * @return void
	 */
	protected function view() {
		echo '<div id="' . esc_attr( $this->unique_id ) . '" class="sui-wrap"></div>';
	}

	/**
	 * Adds the SUI class on markup body.
	 *
	 * @param string $classes The existing body classes.
	 *
	 * @return string
	 */
	public function admin_body_classes( $classes = '' ) {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $classes;
		}

		$current_screen = get_current_screen();

		if ( empty( $current_screen->id ) || ! strpos( $current_screen->id, $this->page_slug ) ) {
			return $classes;
		}

		$classes .= ' sui-' . str_replace( '.', '-', WPMUDEV_PLUGINTEST_SUI_VERSION ) . ' ';

		return $classes;
	}
}
