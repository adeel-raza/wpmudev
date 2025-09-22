<?php
/**
 * Simple Dependency Checker for WPMU DEV Plugin Test.
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

/**
 * Simple dependency checker class.
 */
class Dependency_Checker {

	/**
	 * Check if required dependencies are available.
	 *
	 * @return bool True if all dependencies are available, false otherwise.
	 */
	public static function check_dependencies() {
		// Basic WordPress compatibility check.
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return false;
		}

		// Check if Google API Client is available.
		if ( ! class_exists( 'Google\\Client' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'show_missing_dependency_notice' ) );
			return false;
		}

		// Check if Firebase JWT is available.
		if ( ! class_exists( 'Firebase\\JWT\\JWT' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'show_missing_dependency_notice' ) );
			return false;
		}

		return true;
	}

	/**
	 * Show missing dependency notice.
	 */
	public static function show_missing_dependency_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'WPMU DEV Plugin Test - Missing Dependencies', 'wpmudev-plugin-test' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'Required dependencies are missing. Please run "composer install" in the plugin directory.', 'wpmudev-plugin-test' ); ?>
			</p>
		</div>
		<?php
	}
}
