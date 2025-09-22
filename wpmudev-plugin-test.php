<?php
/**
 * Plugin Name:       WPMU DEV Plugin Test - Forminator Developer Position
 * Description:       A plugin focused on testing coding skills.
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Version:           0.1.0
 * Author:            PLEASE ADD YOU FULL NAME HERE
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpmudev-plugin-test
 *
 * @package           create-block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


// Load Composer autoloader
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}


// Plugin version.
if ( ! defined( 'WPMUDEV_PLUGINTEST_VERSION' ) ) {
	define( 'WPMUDEV_PLUGINTEST_VERSION', '1.0.0' );
}

// Define WPMUDEV_PLUGINTEST_PLUGIN_FILE.
if ( ! defined( 'WPMUDEV_PLUGINTEST_PLUGIN_FILE' ) ) {
	define( 'WPMUDEV_PLUGINTEST_PLUGIN_FILE', __FILE__ );
}

// Plugin directory.
if ( ! defined( 'WPMUDEV_PLUGINTEST_DIR' ) ) {
	define( 'WPMUDEV_PLUGINTEST_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin url.
if ( ! defined( 'WPMUDEV_PLUGINTEST_URL' ) ) {
	define( 'WPMUDEV_PLUGINTEST_URL', plugin_dir_url( __FILE__ ) );
}

// Assets url.
if ( ! defined( 'WPMUDEV_PLUGINTEST_ASSETS_URL' ) ) {
	define( 'WPMUDEV_PLUGINTEST_ASSETS_URL', WPMUDEV_PLUGINTEST_URL . '/assets' );
}

// Shared UI Version.
if ( ! defined( 'WPMUDEV_PLUGINTEST_SUI_VERSION' ) ) {
	define( 'WPMUDEV_PLUGINTEST_SUI_VERSION', '2.12.23' );
}


/**
 * WPMUDEV_PluginTest class.
 */
class WPMUDEV_PluginTest {

	/**
	 * Holds the class instance.
	 *
	 * @var WPMUDEV_PluginTest $instance
	 */
	private static $instance = null;

	/**
	 * Return an instance of the class
	 *
	 * Return an instance of the WPMUDEV_PluginTest Class.
	 *
	 * @return WPMUDEV_PluginTest class instance.
	 * @since 1.0.0
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class initializer.
	 */
	public function load() {
		// Load translations on init hook
		add_action(
			'init',
			function () {
				load_plugin_textdomain(
					'wpmudev-plugin-test',
					false,
					dirname( plugin_basename( __FILE__ ) ) . '/languages'
				);
			}
		);

		// Load Posts Maintenance file
		$posts_file = __DIR__ . '/app/admin-pages/class-posts-maintenance.php';
		if ( file_exists( $posts_file ) ) {
			require_once $posts_file;
		}

		// Initialize Posts Maintenance on init hook (after translations are loaded)
		add_action(
			'init',
			function () {
				if ( class_exists( 'WPMUDEV\\PluginTest\\App\\Admin_Pages\\PostsMaintenance' ) ) {
					\WPMUDEV\PluginTest\App\Admin_Pages\PostsMaintenance::instance()->init();
				}
			}
		);

		// Initialize Core Loader on init hook
		add_action(
			'init',
			function () {
				WPMUDEV\PluginTest\Core\Loader::instance();
			}
		);

		// Initialize dependency management

		// Load Google Drive components
		$google_drive_file = __DIR__ . '/app/admin-pages/class-googledrive-settings.php';
		if ( file_exists( $google_drive_file ) ) {
			require_once $google_drive_file;
		}

		$google_drive_rest_file = __DIR__ . '/app/endpoints/v1/class-googledrive-rest.php';
		if ( file_exists( $google_drive_rest_file ) ) {
			require_once $google_drive_rest_file;
		}

		// Initialize Google Drive components on init hook (after translations are loaded)
		add_action(
			'init',
			function () {
				if ( class_exists( 'WPMUDEV\\PluginTest\\App\\Admin_Pages\\Google_Drive' ) ) {
					\WPMUDEV\PluginTest\App\Admin_Pages\Google_Drive::instance()->init();
				}
				if ( class_exists( 'WPMUDEV\\PluginTest\\Endpoints\\V1\\Drive_API' ) ) {
					\WPMUDEV\PluginTest\Endpoints\V1\Drive_API::instance()->init();
				}
			}
		);

		// Load WP-CLI commands if WP-CLI is available
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/app/cli/class-posts-maintenance-cli.php';
			\WP_CLI::add_command( 'wpmudev posts', 'WPMUDEV\\PluginTest\\App\\CLI\\PostsMaintenanceCLI' );
		}
	}
}

// Init the plugin and load the plugin instance for the first time.
if ( function_exists( 'add_action' ) ) {
	add_action(
		'plugins_loaded',
		function () {
			WPMUDEV_PluginTest::get_instance()->load();
		},
		1
	);

	// Cleanup on plugin deactivation
	register_deactivation_hook(
		__FILE__,
		function () {
		}
	);
}
