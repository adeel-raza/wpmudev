<?php
/**
 * Dependency Manager for conflict prevention and version management.
 *
 * @link          https://wpmudev.com/
 * @since         1.0.0
 *
 * @author        WPMUDEV (https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2025, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\Core;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;

class Dependency_Manager extends Base {

	/**
	 * Required PHP version.
	 *
	 * @var string
	 */
	private $required_php_version = '7.4';

	/**
	 * Required WordPress version.
	 *
	 * @var string
	 */
	private $required_wp_version = '6.1';

	/**
	 * Required plugins and their versions.
	 *
	 * @var array
	 */
	private $required_plugins = array();

	/**
	 * Conflicting plugins.
	 *
	 * @var array
	 */
	private $conflicting_plugins = array();

	/**
	 * Namespace prefixes to isolate.
	 *
	 * @var array
	 */
	private $namespace_prefixes = array(
		'WPMUDEV\\PluginTest\\',
		'Google\\',
		'GuzzleHttp\\',
		'Psr\\',
		'Monolog\\',
		'Paragonie\\',
		'Phpseclib\\',
		'Symfony\\',
		'Ralouphie\\',
		'Firebase\\JWT\\',
	);

	/**
	 * Initialize dependency manager.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'check_dependencies' ) );
		add_action( 'admin_notices', array( $this, 'show_dependency_notices' ) );
		add_action( 'init', array( $this, 'isolate_namespaces' ), 1 );
	}

	/**
	 * Check all dependencies.
	 */
	public function check_dependencies() {
		$issues = array();

		// Check PHP version
		if ( ! $this->check_php_version() ) {
				$issues[] = array(
					'type'    => 'error',
					'message' => sprintf(
						/* translators: 1: Required PHP version, 2: Current PHP version */
						__( 'WPMU DEV Plugin Test requires PHP %1$s or higher. You are running PHP %2$s.', 'wpmudev-plugin-test' ),
						$this->required_php_version,
						PHP_VERSION
					),
				);
		}

		// Check WordPress version
		if ( ! $this->check_wp_version() ) {
			global $wp_version;
			$issues[] = array(
				'type'    => 'error',
				'message' => sprintf(
					/* translators: 1: Required WordPress version, 2: Current WordPress version */
					__( 'WPMU DEV Plugin Test requires WordPress %1$s or higher. You are running WordPress %2$s.', 'wpmudev-plugin-test' ),
					$this->required_wp_version,
					$wp_version
				),
			);
		}

		// Check conflicting plugins
		$conflicts = $this->check_conflicting_plugins();
		if ( ! empty( $conflicts ) ) {
			foreach ( $conflicts as $conflict ) {
				$issues[] = array(
					'type'    => 'warning',
					'message' => $conflict,
				);
			}
		}

		// Check required plugins
		$missing_plugins = $this->check_required_plugins();
		if ( ! empty( $missing_plugins ) ) {
			foreach ( $missing_plugins as $missing ) {
				$issues[] = array(
					'type'    => 'warning',
					'message' => $missing,
				);
			}
		}

		// Store issues for display
		update_option( 'wpmudev_plugin_test_dependency_issues', $issues );
	}

	/**
	 * Check PHP version.
	 *
	 * @return bool
	 */
	private function check_php_version() {
		return version_compare( PHP_VERSION, $this->required_php_version, '>=' );
	}

	/**
	 * Check WordPress version.
	 *
	 * @return bool
	 */
	private function check_wp_version() {
		global $wp_version;
		return version_compare( $wp_version, $this->required_wp_version, '>=' );
	}

	/**
	 * Check for conflicting plugins.
	 *
	 * @return array
	 */
	private function check_conflicting_plugins() {
		$conflicts = array();
		$active_plugins = get_option( 'active_plugins', array() );

		// Check for Google API client conflicts
		$google_conflicts = array(
			'google-api-php-client/google-api-php-client.php' => 'Google API PHP Client',
			'google-api-php-client/google-api-php-client/google-api-php-client.php' => 'Google API PHP Client (Alternative)',
		);

		foreach ( $google_conflicts as $plugin_file => $plugin_name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$conflicts[] = sprintf(
					/* translators: %s: Plugin name */
					__( 'Warning: %s is active and may conflict with WPMU DEV Plugin Test\'s Google API integration.', 'wpmudev-plugin-test' ),
					$plugin_name
				);
			}
		}

		// Check for Guzzle conflicts
		$guzzle_conflicts = array(
			'guzzlehttp/guzzle/guzzle.php' => 'Guzzle HTTP Client',
		);

		foreach ( $guzzle_conflicts as $plugin_file => $plugin_name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$conflicts[] = sprintf(
					/* translators: %s: Plugin name */
					__( 'Warning: %s is active and may conflict with WPMU DEV Plugin Test\'s HTTP client.', 'wpmudev-plugin-test' ),
					$plugin_name
				);
			}
		}

		return $conflicts;
	}

	/**
	 * Check for required plugins.
	 *
	 * @return array
	 */
	private function check_required_plugins() {
		$missing = array();

		// No required plugins for this plugin, but this is where you'd add them
		// Example:
		// if ( ! is_plugin_active( 'required-plugin/required-plugin.php' ) ) {
		//     $missing[] = __( 'Required Plugin is not active.', 'wpmudev-plugin-test' );
		// }

		return $missing;
	}

	/**
	 * Show dependency notices in admin.
	 */
	public function show_dependency_notices() {
		$issues = get_option( 'wpmudev_plugin_test_dependency_issues', array() );

		if ( empty( $issues ) ) {
			return;
		}

		foreach ( $issues as $issue ) {
			$class = 'notice notice-' . $issue['type'];
			printf(
				'<div class="%1$s"><p>%2$s</p></div>',
				esc_attr( $class ),
				esc_html( $issue['message'] )
			);
		}

		// Clear issues after display
		delete_option( 'wpmudev_plugin_test_dependency_issues' );
	}

	/**
	 * Isolate namespaces to prevent conflicts.
	 */
	public function isolate_namespaces() {
		// This is a conceptual implementation
		// In practice, namespace isolation is handled by Composer's autoloader
		// and the way we structure our code

		// We can add additional isolation measures here if needed
		$this->prevent_global_pollution();
	}

	/**
	 * Prevent global namespace pollution.
	 */
	private function prevent_global_pollution() {
		// Ensure our classes don't conflict with global names
		// This is mainly handled by proper namespacing, but we can add additional checks

		// Check for function conflicts
		$our_functions = array(
			'wpmudev_plugin_test_init',
			'wpmudev_plugin_test_activate',
			'wpmudev_plugin_test_deactivate',
		);

		foreach ( $our_functions as $function ) {
			if ( function_exists( $function ) && ! function_exists( 'WPMUDEV\\PluginTest\\' . $function ) ) {
				// Function already exists, add prefix
				wp_die( sprintf( 'Function %s already exists. Please deactivate conflicting plugins.', $function ) );
			}
		}
	}

	/**
	 * Get dependency information for debugging.
	 *
	 * @return array
	 */
	public function get_dependency_info() {
		global $wp_version;

		return array(
			'php_version'        => PHP_VERSION,
			'wp_version'         => $wp_version,
			'required_php'       => $this->required_php_version,
			'required_wp'        => $this->required_wp_version,
			'active_plugins'     => get_option( 'active_plugins', array() ),
			'namespace_prefixes' => $this->namespace_prefixes,
			'composer_autoload'  => class_exists( 'Composer\\Autoload\\ClassLoader' ),
			'google_client'      => class_exists( 'Google_Client' ),
			'guzzle_client'      => class_exists( 'GuzzleHttp\\Client' ),
		);
	}

	/**
	 * Check if a specific dependency is available.
	 *
	 * @param string $dependency Dependency name.
	 * @return bool
	 */
	public function is_dependency_available( $dependency ) {
		switch ( $dependency ) {
			case 'google_client':
				return class_exists( 'Google_Client' );
			case 'guzzle_client':
				return class_exists( 'GuzzleHttp\\Client' );
			case 'composer_autoload':
				return class_exists( 'Composer\\Autoload\\ClassLoader' );
			default:
				return false;
		}
	}

	/**
	 * Get version of a specific dependency.
	 *
	 * @param string $dependency Dependency name.
	 * @return string|false
	 */
	public function get_dependency_version( $dependency ) {
		switch ( $dependency ) {
			case 'php':
				return PHP_VERSION;
			case 'wordpress':
				global $wp_version;
				return $wp_version;
			case 'google_client':
				if ( class_exists( 'Google_Client' ) ) {
					// Try to get version from composer
					$composer_file = WPMUDEV_PLUGINTEST_DIR . 'vendor/google/apiclient/composer.json';
					if ( file_exists( $composer_file ) ) {
						$composer_data = json_decode( file_get_contents( $composer_file ), true );
						return $composer_data['version'] ?? 'Unknown';
					}
				}
				return false;
			case 'guzzle_client':
				if ( class_exists( 'GuzzleHttp\\Client' ) ) {
					$composer_file = WPMUDEV_PLUGINTEST_DIR . 'vendor/guzzlehttp/guzzle/composer.json';
					if ( file_exists( $composer_file ) ) {
						$composer_data = json_decode( file_get_contents( $composer_file ), true );
						return $composer_data['version'] ?? 'Unknown';
					}
				}
				return false;
			default:
				return false;
		}
	}

	/**
	 * Log dependency information for debugging.
	 */
	public function log_dependency_info() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$info = $this->get_dependency_info();
			error_log( 'WPMU DEV Plugin Test - Dependency Info: ' . wp_json_encode( $info, JSON_PRETTY_PRINT ) );
		}
	}
}




