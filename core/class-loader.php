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

namespace WPMUDEV\PluginTest;

use WPMUDEV\PluginTest\Base;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

final class Loader extends Base {
	/**
	 * Settings helper class instance.
	 *
	 * @since 1.0.0
	 * @var object
	 *
	 */
	public $settings;

	/**
	 * Minimum supported php version.
	 *
	 * @since  1.0.0
	 * @var float
	 *
	 */
	public $php_version = '7.4';

	/**
	 * Minimum WordPress version.
	 *
	 * @since  1.0.0
	 * @var float
	 *
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
		// Initialize core functionality first
		App\Admin_Pages\Google_Drive::instance()->init();
		Endpoints\V1\Drive_API::instance()->init();
		
		// Try to load optional components
		$this->load_optional_components();
		
		// Add batch processing action
		add_action( 'wpmudev_process_posts_batch', array( $this, 'process_posts_batch' ), 10, 3 );
		
		// Register WP-CLI command
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WPMUDEV\\PluginTest\\App\\CLI\\Posts_Maintenance_Command' ) ) {
			try {
				\WP_CLI::add_command( 'wpmudev', 'WPMUDEV\\PluginTest\\App\\CLI\\Posts_Maintenance_Command' );
			} catch ( Exception $e ) {
				// Silently fail if WP-CLI command registration fails
				error_log( 'WPMU DEV Plugin Test: Failed to register WP-CLI command: ' . $e->getMessage() );
			}
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
		
        // Try to load Posts Maintenance
        if ( ! class_exists( 'WPMUDEV\\PluginTest\\App\\Admin_Pages\\PostsMaintenance' ) ) {
            $file_path = WPMUDEV_PLUGINTEST_DIR . 'app/admin-pages/class-posts-maintenance.php';
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
		
		// Try to load CLI Command (only when WP-CLI is available)
		if ( defined( 'WP_CLI' ) && WP_CLI && ! class_exists( 'WPMUDEV\\PluginTest\\App\\CLI\\Posts_Maintenance_Command' ) ) {
			$file_path = WPMUDEV_PLUGINTEST_DIR . 'app/cli/class-posts-maintenance-command.php';
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}
		
		// Initialize components if they exist
		if ( class_exists( 'WPMUDEV\\PluginTest\\Core\\Dependency_Manager' ) ) {
			Core\Dependency_Manager::instance()->init();
		}
		
        if ( class_exists( 'WPMUDEV\\PluginTest\\App\\Admin_Pages\\PostsMaintenance' ) ) {
            App\Admin_Pages\PostsMaintenance::instance()->init();
        }
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
