<?php
/**
 * Posts Maintenance admin page.
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
 * Posts Maintenance admin page class.
 *
 * Handles the posts maintenance functionality including scanning,
 * background processing, and scheduling.
 */
class PostsMaintenance extends Base {

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
	protected $page_slug = 'wpmudev_plugintest_posts_maintenance';

	/**
	 * Initializes the page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		$this->page_title = __( 'Posts Maintenance', 'wpmudev-plugin-test' );

		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wpmudev_scan_posts', array( $this, 'ajax_scan_posts' ) );
		add_action( 'wp_ajax_wpmudev_get_scan_progress', array( $this, 'ajax_get_scan_progress' ) );
		add_action( 'wp_ajax_wpmudev_reset_scan_status', array( $this, 'ajax_reset_scan_status' ) );
		add_action( 'wp_ajax_wpmudev_clear_notification', array( $this, 'ajax_clear_notification' ) );
		add_action( 'init', array( $this, 'schedule_daily_scan' ) );
		add_action( 'wpmudev_daily_posts_scan', array( $this, 'run_daily_scan' ) );
		add_action( 'wpmudev_process_posts_batch', array( $this, 'process_posts_batch' ), 10, 3 );
	}

	/**
	 * Register admin page.
	 */
	public function register_admin_page() {
		$page = add_menu_page(
			'Posts Maintenance',
			$this->page_title,
			'manage_options',
			$this->page_slug,
			array( $this, 'callback' ),
			'dashicons-admin-tools',
			8
		);

		add_action( 'load-' . $page, array( $this, 'prepare_assets' ) );
	}

	/**
	 * The admin page callback method.
	 *
	 * @return void
	 */
	public function callback() {
		$this->view();
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_assets() {
		$screen = get_current_screen();
		$is_posts_maintenance_page = false;
		
		// Check if we're on the Posts Maintenance page
		if ( $screen ) {
			$is_posts_maintenance_page = (
				'toplevel_page_' . $this->page_slug === $screen->id ||
				( isset( $_GET['page'] ) && $_GET['page'] === $this->page_slug )
			);
		}
		
		if ( $is_posts_maintenance_page ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'wp-util' );
			
			// Enqueue custom CSS
			wp_enqueue_style(
				'wpmudev-posts-maintenance-admin',
				WPMUDEV_PLUGINTEST_ASSETS_URL . '/css/posts-maintenance-admin.css',
				array(),
				WPMUDEV_PLUGINTEST_VERSION
			);
			
			// Enqueue custom JavaScript
			wp_enqueue_script(
				'wpmudev-posts-maintenance-admin',
				WPMUDEV_PLUGINTEST_ASSETS_URL . '/js/posts-maintenance-admin.js',
				array( 'jquery', 'wp-util' ),
				WPMUDEV_PLUGINTEST_VERSION,
				true
			);
			
			// Localize script for AJAX
			wp_localize_script(
				'wpmudev-posts-maintenance-admin',
				'wpmudevPostsMaintenance',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wpmudev_scan_posts' ),
				)
			);
		}
	}

	/**
	 * Prepare assets.
	 */
	public function prepare_assets() {
		// Assets preparation if needed.
	}

	/**
	 * AJAX handler for scanning posts.
	 */
	public function ajax_scan_posts() {
		// Check nonce.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wpmudev_scan_posts' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) ) : array( 'post', 'page' );
		$batch_size = isset( $_POST['batch_size'] ) ? intval( wp_unslash( $_POST['batch_size'] ) ) : 10;
		
		// Ensure batch size is within valid range
		$batch_size = max( 1, min( 100, $batch_size ) );

		// Start background processing.
		$this->start_background_scan( $post_types, $batch_size );

		wp_send_json_success(
			array(
				'message' => __( 'Scan started successfully', 'wpmudev-plugin-test' ),
			)
		);
	}

	/**
	 * AJAX handler for getting scan progress.
	 */
	public function ajax_get_scan_progress() {
		// Check nonce.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wpmudev_scan_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$progress = get_option( 'wpmudev_scan_progress', array() );
		$status   = get_option( 'wpmudev_scan_status', 'idle' );

		// If status is 'running' but no progress has been made, reset to idle
		if ( $status === 'running' ) {
			$progress = get_option( 'wpmudev_scan_progress', array() );
			if ( empty( $progress ) || ( isset( $progress['processed'] ) && 0 === $progress['processed'] ) ) {
				// Check if scan has been running for more than 60 seconds without progress
				$scan_start = get_option( 'wpmudev_scan_start_time', 0 );
				if ( $scan_start > 0 && ( time() - $scan_start ) > 60 ) {
					update_option( 'wpmudev_scan_status', 'idle' );
					$status = 'idle';
					update_option(
						'wpmudev_scan_notification',
						array(
							'type'      => 'error',
							'message'   => 'Scan failed to start properly. Please try again.',
							'timestamp' => time(),
						)
					);
					
					// Clear progress data to prevent issues with subsequent scans
					delete_option( 'wpmudev_scan_progress' );
				}
			}
		}

		// Calculate progress percentage
		$progress_percentage = 0;
		if ( ! empty( $progress ) && isset( $progress['total'] ) && $progress['total'] > 0 ) {
			$progress_percentage = ( $progress['processed'] / $progress['total'] ) * 100;
		}

		wp_send_json_success(
			array(
				'progress'         => $progress_percentage,
				'processed_posts'  => isset( $progress['processed'] ) ? $progress['processed'] : 0,
				'total_posts'      => isset( $progress['total'] ) ? $progress['total'] : 0,
				'is_running'       => $status === 'running',
				'status'           => $status,
			)
		);
	}

	/**
	 * AJAX handler for resetting scan status.
	 */
	public function ajax_reset_scan_status() {
		// Check nonce.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wpmudev_scan_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Reset status to idle
		update_option( 'wpmudev_scan_status', 'idle' );

		// Clear any scheduled events
		wp_clear_scheduled_hook( 'wpmudev_process_posts_batch' );

		// Clear all scan-related options
		delete_option( 'wpmudev_scan_progress' );
		delete_option( 'wpmudev_scan_notification' );
		delete_option( 'wpmudev_scan_start_time' );

		wp_send_json_success(
			array(
				'message' => 'Status reset successfully',
				'status'  => 'idle',
			)
		);
	}

	/**
	 * AJAX handler for clearing notifications.
	 */
	public function ajax_clear_notification() {
		// Check nonce.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wpmudev_scan_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Clear notification
		delete_option( 'wpmudev_scan_notification' );

		wp_send_json_success(
			array(
				'message' => 'Notification cleared successfully',
			)
		);
	}



	/**
	 * Start background scan process.
	 *
	 * @param array $post_types Array of post types to scan.
	 * @param int   $batch_size Number of posts to process per batch.
	 */
	private function start_background_scan( $post_types, $batch_size ) {
		// Clear any existing scan data and scheduled events
		wp_clear_scheduled_hook( 'wpmudev_process_posts_batch' );
		delete_option( 'wpmudev_scan_notification' );

		// Set scan start time to prevent duplicate processing
		$scan_start_time = time();
		update_option( 'wpmudev_scan_start_time', $scan_start_time );

		// Update scan status.
		update_option( 'wpmudev_scan_status', 'running' );

		// Get total count first
		$total_posts = $this->get_total_posts_count( $post_types );

		// Initialize progress with correct total
		update_option(
			'wpmudev_scan_progress',
			array(
				'processed'     => 0,
				'total'         => $total_posts,
				'current_batch' => 0,
				'post_types'    => $post_types,
			)
		);

		// Schedule first batch with immediate execution
		wp_schedule_single_event( time(), 'wpmudev_process_posts_batch', array( $post_types, $batch_size, 0 ) );
		
		// Trigger WordPress cron manually to ensure immediate execution
		spawn_cron();
	}

	/**
	 * Get total posts count for given post types.
	 *
	 * @param array $post_types Array of post types to count.
	 * @return int Total number of posts.
	 */
	private function get_total_posts_count( $post_types ) {
		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$query = new \WP_Query( $args );
		$count = $query->found_posts;

		// Debug logging removed for performance

		return $count;
	}

	/**
	 * Process posts batch.
	 *
	 * @param array $post_types Array of post types to process.
	 * @param int   $batch_size Number of posts to process per batch.
	 * @param int   $offset     Offset for the current batch.
	 */
	public function process_posts_batch( $post_types, $batch_size, $offset ) {
		// Debug logging removed for performance

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'fields'         => 'ids',
		);

		$query = new \WP_Query( $args );
		$posts = $query->posts;

		// Debug logging removed for performance

		$processed = 0;
		foreach ( $posts as $post_id ) {
			// Always process posts - update post meta with current timestamp
			update_post_meta( $post_id, 'wpmudev_test_last_scan', time() );
			++$processed;
		}

		// Update progress.
		$progress                  = get_option( 'wpmudev_scan_progress', array() );
		$progress['processed']    += $processed;
		$progress['current_batch'] = $offset + $batch_size;

		// Ensure processed count doesn't exceed total
		if ( $progress['processed'] > $progress['total'] ) {
			$progress['processed'] = $progress['total'];
		}

		update_option( 'wpmudev_scan_progress', $progress );

		// Debug logging removed for performance

		// Check for timeout (scan running for more than 5 minutes)
		$scan_start_time = get_option( 'wpmudev_scan_start_time', 0 );
		if ( $scan_start_time && ( time() - $scan_start_time ) > 300 ) { // 5 minutes timeout
			update_option( 'wpmudev_scan_status', 'idle' );
			update_option(
				'wpmudev_scan_notification',
				array(
					'type'      => 'error',
					'message'   => 'Scan timed out after 5 minutes. Please try again with a smaller batch size.',
					'timestamp' => time(),
				)
			);
			wp_clear_scheduled_hook( 'wpmudev_process_posts_batch' );
			
			// Clear progress data to prevent issues with subsequent scans
			delete_option( 'wpmudev_scan_progress' );
			return;
		}

		// Check if we need to process more batches.
		// If we got no posts or have processed all posts, we've reached the end
		if ( count( $posts ) == 0 || $progress['processed'] >= $progress['total'] ) {
			// Scan completed - show completion notification
			update_option( 'wpmudev_scan_status', 'completed' );
			update_option( 'wpmudev_last_scan_time', time() );

			// Store completion notification with correct count
			$actual_processed = min( $progress['processed'], $progress['total'] );
			update_option(
				'wpmudev_scan_notification',
				array(
					'type'      => 'success',
					'message'   => sprintf( 'Scan completed successfully! Processed %d posts.', $actual_processed ),
					'timestamp' => time(),
				)
			);

			// Clear any remaining scheduled events
			wp_clear_scheduled_hook( 'wpmudev_process_posts_batch' );
			
			// Clear progress data to prevent issues with subsequent scans
			delete_option( 'wpmudev_scan_progress' );
		} else {
			// Schedule next batch with a 1-second delay
			wp_schedule_single_event( time() + 1, 'wpmudev_process_posts_batch', array( $post_types, $batch_size, $offset + $batch_size ) );
		}
	}


	/**
	 * Schedule daily scan.
	 */
	public function schedule_daily_scan() {
		if ( ! wp_next_scheduled( 'wpmudev_daily_posts_scan' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmudev_daily_posts_scan' );
		}
	}

	/**
	 * Run daily scan.
	 */
	public function run_daily_scan() {
		$post_types = get_option( 'wpmudev_scan_post_types', array( 'post', 'page' ) );
		$batch_size = get_option( 'wpmudev_scan_batch_size', 10 );

		$this->start_background_scan( $post_types, $batch_size );
	}


	/**
	 * Prints the admin page content.
	 *
	 * @return void
	 */
	protected function view() {
		$progress     = get_option( 'wpmudev_scan_progress', array() );
		$status       = get_option( 'wpmudev_scan_status', 'idle' );
		$last_scan    = get_option( 'wpmudev_last_scan_time', 0 );
		$post_types   = get_option( 'wpmudev_scan_post_types', array( 'post', 'page' ) );
		$notification = get_option( 'wpmudev_scan_notification', null );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->page_title ); ?></h1>

			<?php if ( $notification ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notification['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $notification['message'] ); ?></p>
					<button type="button" class="notice-dismiss" onclick="clearNotification()">
						<span class="screen-reader-text">Dismiss this notice.</span>
					</button>
				</div>
			<?php endif; ?>

			<div class="sui-box">
				<div class="sui-box-header">
					<h2 class="sui-box-title"><?php _e( 'Posts Scan Status', 'wpmudev-plugin-test' ); ?></h2>
				</div>
				<div class="sui-box-body">
					<div id="scan-status">
						<p><strong><?php _e( 'Status:', 'wpmudev-plugin-test' ); ?></strong> <span id="current-status"><?php echo esc_html( ucfirst( $status ) ); ?></span></p>
						<?php if ( $last_scan > 0 ) : ?>
							<p><strong><?php _e( 'Last Scan:', 'wpmudev-plugin-test' ); ?></strong> <?php echo esc_html( date( 'Y-m-d H:i:s', $last_scan ) ); ?></p>
						<?php endif; ?>
						<div id="progress-bar">
							<div class="progress-bar">
								<div class="progress-fill"></div>
							</div>
							<p id="progress-text"></p>
						</div>
					</div>
				</div>
			</div>

			<div class="sui-box">
				<div class="sui-box-header">
					<h2 class="sui-box-title"><?php _e( 'Scan Configuration', 'wpmudev-plugin-test' ); ?></h2>
				</div>
				<div class="sui-box-body">
					<form id="scan-config-form">
						<input type="hidden" id="scan_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpmudev_scan_posts' ) ); ?>">
						<table class="form-table">
							<tr>
								<th scope="row"><?php _e( 'Post Types', 'wpmudev-plugin-test' ); ?></th>
								<td>
									<?php
									$available_post_types = get_post_types( array( 'public' => true ), 'objects' );
									foreach ( $available_post_types as $post_type ) :
										$checked = in_array( $post_type->name, $post_types ) ? 'checked' : '';
										?>
										<label>
											<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php echo $checked; ?>>
											<?php echo esc_html( $post_type->label ); ?>
										</label><br>
									<?php endforeach; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php _e( 'Batch Size', 'wpmudev-plugin-test' ); ?></th>
								<td>
									<input type="number" id="batch_size" name="batch_size" value="10" min="1" max="100" class="regular-text">
									<p class="description"><?php _e( 'Number of posts to process per batch (1-100)', 'wpmudev-plugin-test' ); ?></p>
								</td>
							</tr>
						</table>
					</form>
				</div>
					<div class="sui-box-footer">
						<div class="sui-actions-right">
							<button type="button" id="reset-status" class="button button-secondary">
								<?php _e( 'Reset Status', 'wpmudev-plugin-test' ); ?>
							</button>
							<button type="button" id="start-scan" class="button button-primary" <?php echo ( $status === 'running' ) ? 'disabled' : ''; ?>>
								<?php _e( 'Start Scan', 'wpmudev-plugin-test' ); ?>
							</button>
							<button type="button" id="stop-scan" class="button button-secondary">
								<?php _e( 'Stop Scan', 'wpmudev-plugin-test' ); ?>
							</button>
						</div>
					</div>
			</div>
		</div>
		<?php
	}
}
