<?php
/**
 * WP-CLI command for Posts Maintenance.
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

// Only load this class when WP-CLI is available
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WP_CLI;
use WP_CLI_Command;

class Posts_Maintenance_Command extends WP_CLI_Command {

	/**
	 * Scan posts and update wpmudev_test_last_scan meta.
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
	 * : Show what would be processed without making changes.
	 *
	 * ## EXAMPLES
	 *
	 *     # Scan all posts and pages
	 *     wp wpmudev scan-posts
	 *
	 *     # Scan only posts
	 *     wp wpmudev scan-posts --post-types=post
	 *
	 *     # Scan with custom batch size
	 *     wp wpmudev scan-posts --batch-size=20
	 *
	 *     # Dry run to see what would be processed
	 *     wp wpmudev scan-posts --dry-run
	 *
	 * @when after_wp_load
	 */
	public function scan_posts( $args, $assoc_args ) {
		$post_types = isset( $assoc_args['post-types'] ) ? explode( ',', $assoc_args['post-types'] ) : array( 'post', 'page' );
		$batch_size = isset( $assoc_args['batch-size'] ) ? intval( $assoc_args['batch-size'] ) : 10;
		$dry_run = isset( $assoc_args['dry-run'] );

		// Validate post types
		$available_post_types = get_post_types( array( 'public' => true ), 'names' );
		$invalid_post_types = array_diff( $post_types, $available_post_types );
		
		if ( ! empty( $invalid_post_types ) ) {
			WP_CLI::error( 'Invalid post types: ' . implode( ', ', $invalid_post_types ) );
		}

		// Validate batch size
		if ( $batch_size < 1 || $batch_size > 100 ) {
			WP_CLI::error( 'Batch size must be between 1 and 100' );
		}

		// Get total count
		$total_posts = $this->get_total_posts_count( $post_types );
		
		if ( $total_posts === 0 ) {
			WP_CLI::warning( 'No posts found for the specified post types.' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d posts to process across post types: %s', $total_posts, implode( ', ', $post_types ) ) );

		if ( $dry_run ) {
			WP_CLI::log( 'DRY RUN: No changes will be made.' );
			$this->show_sample_posts( $post_types, $batch_size );
			return;
		}

		// Process posts in batches
		$processed = 0;
		$offset = 0;
		$progress = \WP_CLI\Utils\make_progress_bar( 'Processing posts', $total_posts );

		while ( $offset < $total_posts ) {
			$posts = $this->get_posts_batch( $post_types, $batch_size, $offset );
			
			if ( empty( $posts ) ) {
				break;
			}

			foreach ( $posts as $post_id ) {
				update_post_meta( $post_id, 'wpmudev_test_last_scan', time() );
				$processed++;
				$progress->tick();
			}

			$offset += $batch_size;
		}

		$progress->finish();

		WP_CLI::success( sprintf( 'Successfully processed %d posts.', $processed ) );
	}

	/**
	 * Get total posts count for given post types.
	 *
	 * @param array $post_types Post types to count.
	 * @return int Total count.
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
	 * Get posts batch.
	 *
	 * @param array $post_types Post types to query.
	 * @param int   $batch_size Number of posts per batch.
	 * @param int   $offset     Offset for pagination.
	 * @return array Post IDs.
	 */
	private function get_posts_batch( $post_types, $batch_size, $offset ) {
		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'fields'         => 'ids',
		);

		$query = new \WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Show sample posts for dry run.
	 *
	 * @param array $post_types Post types to query.
	 * @param int   $batch_size Number of posts to show.
	 */
	private function show_sample_posts( $post_types, $batch_size ) {
		$posts = $this->get_posts_batch( $post_types, $batch_size, 0 );
		
		if ( empty( $posts ) ) {
			WP_CLI::log( 'No posts found.' );
			return;
		}

		WP_CLI::log( 'Sample posts that would be processed:' );
		
		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			$last_scan = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
			$last_scan_date = $last_scan ? date( 'Y-m-d H:i:s', $last_scan ) : 'Never';
			
			WP_CLI::log( sprintf( 
				'  - ID: %d, Title: %s, Type: %s, Last Scan: %s',
				$post_id,
				$post->post_title,
				$post->post_type,
				$last_scan_date
			) );
		}
	}

	/**
	 * Get scan statistics.
	 *
	 * ## OPTIONS
	 *
	 * [--post-types=<post_types>]
	 * : Comma-separated list of post types to analyze. Default: post,page
	 *
	 * ## EXAMPLES
	 *
	 *     # Get statistics for all posts and pages
	 *     wp wpmudev scan-stats
	 *
	 *     # Get statistics for specific post types
	 *     wp wpmudev scan-stats --post-types=post,page,product
	 *
	 * @when after_wp_load
	 */
	public function scan_stats( $args, $assoc_args ) {
		$post_types = isset( $assoc_args['post-types'] ) ? explode( ',', $assoc_args['post-types'] ) : array( 'post', 'page' );

		// Validate post types
		$available_post_types = get_post_types( array( 'public' => true ), 'names' );
		$invalid_post_types = array_diff( $post_types, $available_post_types );
		
		if ( ! empty( $invalid_post_types ) ) {
			WP_CLI::error( 'Invalid post types: ' . implode( ', ', $invalid_post_types ) );
		}

		$total_posts = $this->get_total_posts_count( $post_types );
		
		if ( $total_posts === 0 ) {
			WP_CLI::warning( 'No posts found for the specified post types.' );
			return;
		}

		// Get posts with scan meta
		$scanned_posts = $this->get_scanned_posts_count( $post_types );
		$unscanned_posts = $total_posts - $scanned_posts;

		// Get scan dates
		$scan_dates = $this->get_scan_dates( $post_types );

		WP_CLI::log( 'Posts Maintenance Statistics' );
		WP_CLI::log( '============================' );
		WP_CLI::log( sprintf( 'Post Types: %s', implode( ', ', $post_types ) ) );
		WP_CLI::log( sprintf( 'Total Posts: %d', $total_posts ) );
		WP_CLI::log( sprintf( 'Scanned Posts: %d (%.1f%%)', $scanned_posts, ( $scanned_posts / $total_posts ) * 100 ) );
		WP_CLI::log( sprintf( 'Unscanned Posts: %d (%.1f%%)', $unscanned_posts, ( $unscanned_posts / $total_posts ) * 100 ) );

		if ( ! empty( $scan_dates ) ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Scan Date Distribution:' );
			foreach ( $scan_dates as $date => $count ) {
				WP_CLI::log( sprintf( '  %s: %d posts', $date, $count ) );
			}
		}
	}

	/**
	 * Get count of scanned posts.
	 *
	 * @param array $post_types Post types to query.
	 * @return int Scanned posts count.
	 */
	private function get_scanned_posts_count( $post_types ) {
		global $wpdb;

		$post_types_placeholder = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		
		$query = $wpdb->prepare( "
			SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type IN ($post_types_placeholder)
			AND p.post_status = 'publish'
			AND pm.meta_key = 'wpmudev_test_last_scan'
			AND pm.meta_value IS NOT NULL
		", $post_types );

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get scan dates distribution.
	 *
	 * @param array $post_types Post types to query.
	 * @return array Scan dates with counts.
	 */
	private function get_scan_dates( $post_types ) {
		global $wpdb;

		$post_types_placeholder = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		
		$query = $wpdb->prepare( "
			SELECT DATE(FROM_UNIXTIME(pm.meta_value)) as scan_date, COUNT(*) as count
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type IN ($post_types_placeholder)
			AND p.post_status = 'publish'
			AND pm.meta_key = 'wpmudev_test_last_scan'
			AND pm.meta_value IS NOT NULL
			GROUP BY scan_date
			ORDER BY scan_date DESC
			LIMIT 10
		", $post_types );

		$results = $wpdb->get_results( $query );
		$scan_dates = array();

		foreach ( $results as $result ) {
			$scan_dates[ $result->scan_date ] = (int) $result->count;
		}

		return $scan_dates;
	}
}

