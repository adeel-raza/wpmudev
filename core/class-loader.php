<?php
/**
 * Class to boot up plugin.
 *
 * @link    https://wpmudev.com/
 * @since   1.0.0
 *
 * @author  WPMUDEV (https://wpmudev.com)
 * @package WPMUDEV_PluginTest
 *
 * @copyright (c) 2025, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\Core;

use WPMUDEV\PluginTest\Base;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

final class Loader extends Base {
	/**
	 * Settings helper class instance.
	 *
	 * @since 1.0.0
	 * @var object
	 */
	public $settings;

	/**
	 * Minimum supported php version.
	 *
	 * @since  1.0.0
	 * @var float
	 */
	public $php_version = '7.4';

	/**
	 * Minimum WordPress version.
	 *
	 * @since  1.0.0
	 * @var float
	 */
	public $wp_version = '6.1';

	/**
	 * Initialize functionality of the plugin.
	 *
	 * This is where we kick-start the plugin by defining
	 * everything required and register all hooks.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function __construct() {
		if ( ! $this->can_boot() ) {
			return;
		}

		$this->init();
	}

	/**
	 * Main condition that checks if plugin parts should continue loading.
	 *
	 * @return bool
	 */
	private function can_boot() {
		/**
		 * Checks
		 *  - PHP version
		 *  - WP Version
		 * If not then return.
		 */
		global $wp_version;

		return (
			version_compare( PHP_VERSION, $this->php_version, '>' ) &&
			version_compare( $wp_version, $this->wp_version, '>' )
		);
	}

	/**
	 * Register all the actions and filters.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function init() {
		// Load optional components first
		$this->load_optional_components();

		// Initialize core functionality after dependencies are loaded
		if ( class_exists( 'WPMUDEV\\PluginTest\\App\\Admin_Pages\\Google_Drive' ) ) {
			\WPMUDEV\PluginTest\App\Admin_Pages\Google_Drive::instance()->init();
		}
		if ( class_exists( 'WPMUDEV\\PluginTest\\Endpoints\\V1\\Drive_API' ) ) {
			\WPMUDEV\PluginTest\Endpoints\V1\Drive_API::instance()->init();
		}
	}

	/**
	 * Load optional components.
	 */
	private function load_optional_components() {
		// Try to load Dependency Manager
		if ( ! class_exists( 'WPMUDEV\\PluginTest\\Core\\Dependency_Manager' ) ) {
			$file_path = WPMUDEV_PLUGINTEST_DIR . 'core/class-dependency-manager.php';
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}

		// Try to load Google Drive admin page
		if ( ! class_exists( 'WPMUDEV\\PluginTest\\App\\Admin_Pages\\Google_Drive' ) ) {
			$file_path = WPMUDEV_PLUGINTEST_DIR . 'app/admin-pages/class-googledrive-settings.php';
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}

		// Try to load Google Drive REST API
		if ( ! class_exists( 'WPMUDEV\\PluginTest\\Endpoints\\V1\\Drive_API' ) ) {
			$file_path = WPMUDEV_PLUGINTEST_DIR . 'app/endpoints/v1/class-googledrive-rest.php';
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}

		// Try to load Posts Maintenance
		if ( ! class_exists( 'WPMUDEV\\PluginTest\\App\\Admin_Pages\\PostsMaintenance' ) ) {
			$file_path = WPMUDEV_PLUGINTEST_DIR . 'app/admin-pages/class-posts-maintenance.php';
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}

		// Load dependency checker
		$file_path = WPMUDEV_PLUGINTEST_DIR . 'core/class-dependency-checker.php';
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}

		// Try to load CLI Command (only when WP-CLI is available)
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$file_path = WPMUDEV_PLUGINTEST_DIR . 'app/cli/class-posts-maintenance-cli.php';
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}

		// Check dependencies before loading
		if ( class_exists( 'WPMUDEV\\PluginTest\\Core\\Dependency_Checker' ) ) {
			if ( ! \WPMUDEV\PluginTest\Core\Dependency_Checker::check_dependencies() ) {
				return; // Stop loading if dependencies are missing
			}
		}

		// PostsMaintenance is now initialized in the main plugin file
	}

	/**
	 * Process posts batch.
	 */
	public function process_posts_batch( $post_types, $batch_size, $offset ) {
		if ( class_exists( 'WPMUDEV\\PluginTest\\App\\Admin_Pages\\PostsMaintenance' ) ) {
			App\Admin_Pages\PostsMaintenance::instance()->process_posts_batch( $post_types, $batch_size, $offset );
		}
	}
}
