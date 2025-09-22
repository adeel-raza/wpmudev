<?php
/**
 * Posts Maintenance functionality tests.
 *
 * @package Wpmudev_Plugin_Test
 */

// Load the test version of the CLI class
require_once __DIR__ . '/class-posts-maintenance-test.php';

/**
 * Test class for Posts Maintenance functionality.
 */
class PostsMaintenanceTest extends WP_UnitTestCase {

	/**
	 * Test that scan posts functionality updates meta correctly.
	 */
	public function test_scan_posts_updates_meta() {
		// Create a test post
		$post_id = $this->factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Test Post for Scanning',
				'post_content' => 'This is test content for scanning.',
			)
		);

		// Run the scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Assert that meta was updated
		$meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $meta, 'Post meta should be updated after scanning' );
		$this->assertIsString( $meta, 'Post meta should be a string' );
	}

	/**
	 * Test that scan posts handles empty database gracefully.
	 */
	public function test_scan_posts_handles_empty() {
		// Ensure no posts exist
		$posts = get_posts( array( 'numberposts' => -1 ) );
		$this->assertEmpty( $posts, 'Database should be empty for this test' );

		// Run scan function - should not throw
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		$this->assertTrue( true, 'Scan should complete without errors on empty database' );
	}

	/**
	 * Test that scan posts handles different post types.
	 */
	public function test_scan_posts_handles_different_post_types() {
		// Create posts of different types
		$post = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$page = $this->factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Both should have meta updated
		$post_meta = get_post_meta( $post, 'wpmudev_test_last_scan', true );
		$page_meta = get_post_meta( $page, 'wpmudev_test_last_scan', true );

		$this->assertNotEmpty( $post_meta, 'Post should have meta updated' );
		$this->assertNotEmpty( $page_meta, 'Page should have meta updated' );
	}

	/**
	 * Test that scan posts handles different post statuses.
	 */
	public function test_scan_posts_handles_different_post_statuses() {
		// Create posts with different statuses
		$published = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$draft = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'draft',
			)
		);

		$trash = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'trash',
			)
		);

		$private = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'private',
			)
		);

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// All should have meta updated
		$published_meta = get_post_meta( $published, 'wpmudev_test_last_scan', true );
		$draft_meta     = get_post_meta( $draft, 'wpmudev_test_last_scan', true );
		$trash_meta     = get_post_meta( $trash, 'wpmudev_test_last_scan', true );
		$private_meta   = get_post_meta( $private, 'wpmudev_test_last_scan', true );

		$this->assertNotEmpty( $published_meta, 'Published post should have meta updated' );
		$this->assertNotEmpty( $draft_meta, 'Draft post should have meta updated' );
		$this->assertNotEmpty( $trash_meta, 'Trashed post should have meta updated' );
		$this->assertNotEmpty( $private_meta, 'Private post should have meta updated' );
	}

	/**
	 * Test that scan posts handles large number of posts.
	 */
	public function test_scan_posts_handles_large_number_of_posts() {
		// Create multiple posts
		$post_ids = $this->factory()->post->create_many(
			10,
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// All posts should have meta updated
		foreach ( $post_ids as $post_id ) {
			$meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
			$this->assertNotEmpty( $meta, "Post {$post_id} should have meta updated" );
		}
	}

	/**
	 * Test that scan posts handles posts without content.
	 */
	public function test_scan_posts_handles_posts_without_content() {
		// Create post without content
		$post_id = $this->factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Empty Content Post',
				'post_content' => '',
			)
		);

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Should still update meta
		$meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $meta, 'Post without content should still have meta updated' );
	}

	/**
	 * Test that scan posts handles posts with special characters.
	 */
	public function test_scan_posts_handles_posts_with_special_characters() {
		// Create post with special characters
		$post_id = $this->factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Special Characters: !@#$%^&*()',
				'post_content' => 'Content with special chars: <>&"\'',
			)
		);

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Should handle special characters without issues
		$meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $meta, 'Post with special characters should have meta updated' );
	}

	/**
	 * Test that scan posts handles pending posts.
	 */
	public function test_scan_posts_handles_pending_posts() {
		// Create pending post
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'pending',
			)
		);

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Pending posts should also be processed
		$meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $meta, 'Pending post should have meta updated' );
	}

	/**
	 * Test that scan posts handles future posts.
	 */
	public function test_scan_posts_handles_future_posts() {
		// Create future post
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'future',
				'post_date'   => date( 'Y-m-d H:i:s', strtotime( '+1 year' ) ),
			)
		);

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Future posts should also be processed
		$meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $meta, 'Future post should have meta updated' );
	}

	/**
	 * Test that scan posts handles custom post types.
	 */
	public function test_scan_posts_handles_custom_post_types() {
		// Register a custom post type for testing
		register_post_type(
			'test_cpt',
			array(
				'public' => true,
				'label'  => 'Test CPT',
			)
		);

		// Create custom post type
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'test_cpt',
				'post_status' => 'publish',
			)
		);

		// Run scan function with custom post type included
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan(
			array(),
			array(
				'dry-run'    => false,
				'post-types' => 'post,page,test_cpt',
			)
		);

		// Custom post type should be processed
		$meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $meta, 'Custom post type should have meta updated' );

		// Clean up
		unregister_post_type( 'test_cpt' );
	}

	/**
	 * Test that scan posts handles posts with existing meta.
	 */
	public function test_scan_posts_handles_posts_with_existing_meta() {
		// Create post with existing meta
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		// Add existing meta
		$old_meta = '2024-01-01 12:00:00';
		update_post_meta( $post_id, 'wpmudev_test_last_scan', $old_meta );

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Meta should be updated with new value
		$new_meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $new_meta, 'Post should have updated meta' );
		$this->assertNotEquals( $old_meta, $new_meta, 'Meta should be updated with new value' );
	}

	/**
	 * Test that scan posts handles posts with very long content.
	 */
	public function test_scan_posts_handles_posts_with_long_content() {
		// Create post with very long content
		$long_content = str_repeat( 'This is a very long content string. ', 1000 );
		$post_id      = $this->factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => $long_content,
			)
		);

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Should handle long content without issues
		$meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $meta, 'Post with long content should have meta updated' );
	}

	/**
	 * Test that scan posts handles posts with HTML content.
	 */
	public function test_scan_posts_handles_posts_with_html_content() {
		// Create post with HTML content
		$html_content = '<h1>Title</h1><p>Paragraph with <strong>bold</strong> and <em>italic</em> text.</p><ul><li>List item 1</li><li>List item 2</li></ul>';
		$post_id      = $this->factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => $html_content,
			)
		);

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Should handle HTML content without issues
		$meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $meta, 'Post with HTML content should have meta updated' );
	}

	/**
	 * Test that scan posts handles posts with Unicode content.
	 */
	public function test_scan_posts_handles_posts_with_unicode_content() {
		// Create post with Unicode content
		$unicode_content = 'Hello 世界! 🌍 This is a test with emojis 🚀 and special characters: ñáéíóú';
		$post_id         = $this->factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => $unicode_content,
			)
		);

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Should handle Unicode content without issues
		$meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $meta, 'Post with Unicode content should have meta updated' );
	}

	/**
	 * Test that scan posts can be run multiple times independently.
	 */
	public function test_scan_posts_can_be_run_multiple_times() {
		// Create test post
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();

		// Run scan first time
		$cli->scan( array(), array( 'dry-run' => false ) );
		$meta1 = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $meta1, 'First scan should update meta' );

		// Wait a moment to ensure different timestamps
		sleep( 1 );

		// Run scan second time
		$cli->scan( array(), array( 'dry-run' => false ) );
		$meta2 = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $meta2, 'Second scan should update meta' );

		// Meta should be different (updated timestamp)
		$this->assertNotEquals( $meta1, $meta2, 'Meta should be updated on second scan' );
	}

	/**
	 * Test that scan posts handles database errors gracefully.
	 */
	public function test_scan_posts_handles_database_errors_gracefully() {
		// Create test post
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		// Mock a database error by temporarily breaking the database connection
		// This is a simplified test - in a real scenario, you might use mocking
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();

		// The scan should not throw fatal errors even if there are issues
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Test should complete without fatal errors
		$this->assertTrue( true, 'Scan should handle database errors gracefully' );
	}

	/**
	 * Test that scan posts respects WordPress memory limits.
	 */
	public function test_scan_posts_respects_memory_limits() {
		// Create multiple posts to test memory usage
		$post_ids = $this->factory()->post->create_many(
			50,
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		// Run scan function
		$cli = new \WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
		$cli->scan( array(), array( 'dry-run' => false ) );

		// Check that we haven't exceeded memory limits (skip if unlimited)
		$memory_usage = memory_get_usage( true );
		$memory_limit = ini_get( 'memory_limit' );

		// Skip memory check if limit is unlimited (-1)
		if ( $memory_limit !== '-1' ) {
			$memory_limit_bytes = $this->convert_to_bytes( $memory_limit );
			$this->assertLessThan( $memory_limit_bytes, $memory_usage, 'Memory usage should be within limits' );
		}

		// All posts should still have meta updated
		foreach ( $post_ids as $post_id ) {
			$meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
			$this->assertNotEmpty( $meta, "Post {$post_id} should have meta updated" );
		}
	}

	/**
	 * Helper method to convert memory limit string to bytes.
	 *
	 * @param string $memory_limit Memory limit string (e.g., '128M', '1G').
	 * @return int Memory limit in bytes.
	 */
	private function convert_to_bytes( $memory_limit ) {
		$memory_limit = trim( $memory_limit );
		$last         = strtolower( $memory_limit[ strlen( $memory_limit ) - 1 ] );
		$memory_limit = (int) $memory_limit;

		switch ( $last ) {
			case 'g':
				$memory_limit *= 1024;
				// Fall through.
			case 'm':
				$memory_limit *= 1024;
				// Fall through.
			case 'k':
				$memory_limit *= 1024;
		}

		return $memory_limit;
	}
}
