<?php
/**
 * Test version of Posts Maintenance CLI for unit testing.
 *
 * @package Wpmudev_Plugin_Test
 */

namespace WPMUDEV\PluginTest\App\CLI;

/**
 * Test version of Posts Maintenance CLI.
 *
 * This class provides the same functionality as PostsMaintenanceCLI
 * but without WP_CLI dependencies for unit testing.
 */
class Posts_Maintenance_CLI {

	/**
	 * Scan posts for maintenance.
	 *
	 * This corresponds to the "Scan Posts" functionality in the admin.
	 * It processes posts in batches and updates their scan timestamp.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function scan( $args = array(), $assoc_args = array() ) {
		// Default values
		$post_types = isset( $assoc_args['post-types'] ) ? explode( ',', $assoc_args['post-types'] ) : array( 'post', 'page' );
		$batch_size = isset( $assoc_args['batch-size'] ) ? intval( $assoc_args['batch-size'] ) : 10;
		$dry_run    = isset( $assoc_args['dry-run'] ) ? (bool) $assoc_args['dry-run'] : false;

		// Validate batch size
		$batch_size = max( 1, min( 100, $batch_size ) );

		// Get all posts of specified types
		$posts = get_posts(
			array(
				'post_type'   => $post_types,
				'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future', 'trash' ),
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		if ( empty( $posts ) ) {
			return;
		}

		// Process posts in batches
		$batches      = array_chunk( $posts, $batch_size );
		$current_time = current_time( 'mysql' );

		foreach ( $batches as $batch ) {
			foreach ( $batch as $post_id ) {
				if ( ! $dry_run ) {
					// Update the post meta with current timestamp
					update_post_meta( $post_id, 'wpmudev_test_last_scan', $current_time );
				}
			}
		}
	}
}
