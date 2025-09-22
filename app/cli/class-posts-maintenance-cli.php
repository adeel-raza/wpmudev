<?php
/**
 * WP-CLI command for Posts Maintenance functionality.
 *
 * @link          https://wpmudev.com/
 * @since         1.0.0
 *
 * @author        WPMUDEV (https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2025, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\App\CLI;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WP_CLI;
use WP_CLI_Command;

/**
 * WP-CLI command for Posts Maintenance.
 *
 * Handles scanning posts via command line interface.
 */
class PostsMaintenanceCLI extends WP_CLI_Command {

	/**
	 * Initialize the CLI command.
	 */
	public function __construct() {
		// Don't instantiate PostsMaintenance here as WordPress may not be fully loaded
	}

	/**
	 * Scan posts for maintenance.
	 *
	 * This corresponds to the "Scan Posts" functionality in the admin.
	 * It processes posts in batches and updates their scan timestamp.
	 *
	 * ## OPTIONS
	 *
	 * [--post-types=<post_types>]
	 * : Comma-separated list of post types to scan. Default: post,page
	 *
	 * [--batch-size=<batch_size>]
	 * : Number of posts to process per batch. Default: 10
	 *
	 * [--dry-run]
	 * : Show what would be processed without actually updating posts.
	 *
	 * [--verbose]
	 * : Show detailed progress information.
	 *
	 * ## EXAMPLES
	 *
	 *     # Basic scan (posts and pages)
	 *     wp wpmudev posts scan
	 *
	 *     # Scan only posts with custom batch size
	 *     wp wpmudev posts scan --post-types=post --batch-size=20
	 *
	 *     # Scan multiple post types
	 *     wp wpmudev posts scan --post-types=post,page,product --batch-size=5
	 *
	 *     # Dry run to see what would be processed
	 *     wp wpmudev posts scan --dry-run --verbose
	 *
	 *     # Verbose output for detailed progress
	 *     wp wpmudev posts scan --verbose --batch-size=15
	 *
	 *     # Large batch processing for performance
	 *     wp wpmudev posts scan --post-types=post,page,product --batch-size=50
	 *
	 *     # Small batch for memory-constrained environments
	 *     wp wpmudev posts scan --post-types=post --batch-size=5 --verbose
	 *
	 *     # Development/testing workflow
	 *     wp wpmudev posts scan --dry-run --post-types=post
	 *     wp wpmudev posts scan --post-types=post --batch-size=5 --verbose
	 *
	 * ## USAGE SCENARIOS
	 *
	 * **Daily Maintenance:**
	 *     wp wpmudev posts scan --post-types=post,page --batch-size=25
	 *
	 * **Large Site Processing:**
	 *     wp wpmudev posts scan --post-types=post,page,product --batch-size=50 --verbose
	 *
	 * **Memory-Constrained Environment:**
	 *     wp wpmudev posts scan --post-types=post --batch-size=5 --verbose
	 *
	 * **Testing/Development:**
	 *     wp wpmudev posts scan --dry-run --post-types=post --verbose
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function scan( $args, $assoc_args ) {
		// Parse arguments
		$post_types = isset( $assoc_args['post-types'] ) ?
			array_map( 'trim', explode( ',', $assoc_args['post-types'] ) ) :
			array( 'post', 'page' );

		$batch_size = isset( $assoc_args['batch-size'] ) ?
			intval( $assoc_args['batch-size'] ) :
			10;

		$dry_run = isset( $assoc_args['dry-run'] );
		$verbose = isset( $assoc_args['verbose'] );

		// Validate batch size
		if ( $batch_size < 1 || $batch_size > 100 ) {
			WP_CLI::error( 'Batch size must be between 1 and 100.' );
		}

		// Validate post types
		$available_post_types = get_post_types( array( 'public' => true ), 'names' );
		$invalid_post_types   = array_diff( $post_types, $available_post_types );

		if ( ! empty( $invalid_post_types ) ) {
			WP_CLI::error( 'Invalid post types: ' . implode( ', ', $invalid_post_types ) );
		}

		// Get total count
		$total_posts = $this->get_total_posts_count( $post_types );

		if ( $total_posts === 0 ) {
			WP_CLI::warning( 'No posts found for the specified post types.' );
			return;
		}

		// Show scan information
		WP_CLI::log( '' );
		WP_CLI::log( 'Posts Maintenance Scan' );
		WP_CLI::log( '=====================' );
		WP_CLI::log( 'Post Types: ' . implode( ', ', $post_types ) );
		WP_CLI::log( 'Total Posts: ' . $total_posts );
		WP_CLI::log( 'Batch Size: ' . $batch_size );
		WP_CLI::log( 'Mode: ' . ( $dry_run ? 'Dry Run' : 'Live' ) );
		WP_CLI::log( '' );

		if ( $dry_run ) {
			$this->dry_run_scan( $post_types, $batch_size, $total_posts, $verbose );
		} else {
			$this->execute_scan( $post_types, $batch_size, $total_posts, $verbose );
		}
	}

	/**
	 * Execute the actual scan.
	 *
	 * @param array $post_types   Post types to scan.
	 * @param int   $batch_size   Batch size.
	 * @param int   $total_posts  Total posts count.
	 * @param bool  $verbose      Verbose output.
	 */
	private function execute_scan( $post_types, $batch_size, $total_posts, $verbose ) {
		$start_time = microtime( true );
		$progress   = \WP_CLI\Utils\make_progress_bar( 'Scanning posts', $total_posts );

		$processed   = 0;
		$paged       = 1;
		$batch_count = 0;

		while ( $processed < $total_posts ) {
			++$batch_count;

			// Safety check to prevent infinite loops
			if ( $batch_count > 1000 ) {
				WP_CLI::warning( 'Safety limit reached (1000 batches). Stopping scan.' );
				break;
			}

			if ( $verbose ) {
				WP_CLI::log( "Processing batch {$batch_count} (page: {$paged})" );
			}

			// Get posts for this batch using paged instead of offset
			$args = array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $batch_size,
				'paged'          => $paged,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			);

			$query = new \WP_Query( $args );
			$posts = $query->posts;

			if ( empty( $posts ) ) {
				break;
			}

			$batch_processed = 0;
			foreach ( $posts as $post_id ) {
				// Update post meta with current timestamp
				update_post_meta( $post_id, 'wpmudev_test_last_scan', time() );
				++$batch_processed;
				++$processed;

				$progress->tick();

				if ( $verbose ) {
					WP_CLI::log( "  Processed post ID: {$post_id}" );
				}
			}

			if ( $verbose ) {
				WP_CLI::log( "  Batch {$batch_count} completed: {$batch_processed} posts processed" );
			}

			// If we got fewer posts than requested, we've reached the end
			if ( count( $posts ) < $batch_size ) {
				break;
			}

			++$paged;
		}

		$progress->finish();
		$end_time   = microtime( true );
		$total_time = round( $end_time - $start_time, 2 );

		// Show completion summary
		WP_CLI::log( '' );
		WP_CLI::success( 'Scan completed successfully!' );
		WP_CLI::log( "Total posts processed: {$processed}" );
		WP_CLI::log( "Batches processed: {$batch_count}" );
		WP_CLI::log( 'Average posts per batch: ' . round( $processed / $batch_count, 2 ) );
		WP_CLI::log( "Total time: {$total_time} seconds" );
	}

	/**
	 * Execute a dry run scan.
	 *
	 * @param array $post_types   Post types to scan.
	 * @param int   $batch_size   Batch size.
	 * @param int   $total_posts  Total posts count.
	 * @param bool  $verbose      Verbose output.
	 */
	private function dry_run_scan( $post_types, $batch_size, $total_posts, $verbose ) {
		WP_CLI::log( 'DRY RUN - No posts will be modified' );
		WP_CLI::log( '' );

		$start_time = microtime( true );
		$progress   = \WP_CLI\Utils\make_progress_bar( 'Analyzing posts', $total_posts );

		$processed   = 0;
		$paged       = 1;
		$batch_count = 0;

		while ( $processed < $total_posts ) {
			++$batch_count;

			// Safety check to prevent infinite loops
			if ( $batch_count > 1000 ) {
				WP_CLI::warning( 'Safety limit reached (1000 batches). Stopping dry run.' );
				break;
			}

			if ( $verbose ) {
				WP_CLI::log( "Would process batch {$batch_count} (page: {$paged})" );
			}

			// Get posts for this batch using paged instead of offset
			$args = array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $batch_size,
				'paged'          => $paged,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			);

			$query = new \WP_Query( $args );
			$posts = $query->posts;

			if ( empty( $posts ) ) {
				break;
			}

			$batch_processed = 0;
			foreach ( $posts as $post_id ) {
				++$batch_processed;
				++$processed;

				$progress->tick();

				if ( $verbose ) {
					WP_CLI::log( "  Would process post ID: {$post_id}" );
				}
			}

			if ( $verbose ) {
				WP_CLI::log( "  Batch {$batch_count} would process: {$batch_processed} posts" );
			}

			// If we got fewer posts than requested, we've reached the end
			if ( count( $posts ) < $batch_size ) {
				break;
			}

			++$paged;
		}

		$progress->finish();
		$end_time   = microtime( true );
		$total_time = round( $end_time - $start_time, 2 );

		// Show dry run summary
		WP_CLI::log( '' );
		WP_CLI::success( 'Dry run completed!' );
		WP_CLI::log( "Posts that would be processed: {$processed}" );
		WP_CLI::log( "Batches that would be processed: {$batch_count}" );
		WP_CLI::log( 'Average posts per batch: ' . round( $processed / $batch_count, 2 ) );
		WP_CLI::log( "Total time: {$total_time} seconds" );
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
		return $query->found_posts;
	}

	/**
	 * Reset scan status.
	 *
	 * Resets the scan status to idle and clears all scan-related data.
	 * This corresponds to the "Reset Scan Status" functionality in the admin.
	 *
	 * ## OPTIONS
	 *
	 * [--confirm]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Reset scan status (with confirmation)
	 *     wp wpmudev posts reset
	 *
	 *     # Reset without confirmation
	 *     wp wpmudev posts reset --confirm
	 *
	 *     # Reset in automated scripts
	 *     wp wpmudev posts reset --confirm
	 *
	 *     # Reset before starting a new scan
	 *     wp wpmudev posts reset --confirm && wp wpmudev posts scan --post-types=post,page
	 *
	 * ## USAGE SCENARIOS
	 *
	 * **Troubleshooting:**
	 *     wp wpmudev posts reset --confirm
	 *
	 * **Automated Workflows:**
	 *     wp wpmudev posts reset --confirm
	 *     wp wpmudev posts scan --post-types=post,page --batch-size=25
	 *
	 * **Development/Testing:**
	 *     wp wpmudev posts reset --confirm
	 *     wp wpmudev posts scan --dry-run --post-types=post --verbose
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function reset( $args, $assoc_args ) {
		$confirm = isset( $assoc_args['confirm'] );

		if ( ! $confirm ) {
			WP_CLI::confirm( 'Are you sure you want to reset the scan status and clear all scan data?' );
		}

		// Reset status to idle (same as admin functionality)
		update_option( 'wpmudev_scan_status', 'idle' );

		// Clear any scheduled events
		wp_clear_scheduled_hook( 'wpmudev_process_posts_batch' );

		// Clear all scan-related options
		delete_option( 'wpmudev_scan_progress' );
		delete_option( 'wpmudev_scan_notification' );
		delete_option( 'wpmudev_scan_start_time' );

		WP_CLI::success( 'Scan status reset successfully!' );
	}
}
