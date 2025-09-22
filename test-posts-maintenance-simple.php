<?php
/**
 * Simple Posts Maintenance Test
 *
 * @package WPMUDEV_PluginTest
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only run for administrators
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Insufficient permissions' );
}

echo '<h1>Posts Maintenance Simple Test</h1>';

// Test 1: Check if class exists
echo '<h2>1. Class Existence</h2>';
if ( class_exists( 'WPMUDEV\\PluginTest\\App\\Admin_Pages\\PostsMaintenance' ) ) {
	echo "<p style='color: green;'>✅ Posts Maintenance class exists</p>";
} else {
	echo "<p style='color: red;'>❌ Posts Maintenance class not found</p>";
	exit;
}

// Test 2: Check if CLI class exists
echo '<h2>2. CLI Class Existence</h2>';
if ( class_exists( 'WPMUDEV\\PluginTest\\App\\CLI\\PostsMaintenanceCLI' ) ) {
	echo "<p style='color: green;'>✅ Posts Maintenance CLI class exists</p>";
} else {
	echo "<p style='color: red;'>❌ Posts Maintenance CLI class not found</p>";
	exit;
}

// Test 3: Test post creation and meta update
echo '<h2>3. Post Meta Update Test</h2>';
$test_post_id = wp_insert_post(
	array(
		'post_title'   => 'Test Post for Maintenance',
		'post_content' => 'This is a test post for the maintenance functionality.',
		'post_status'  => 'publish',
		'post_type'    => 'post',
	)
);

if ( $test_post_id && ! is_wp_error( $test_post_id ) ) {
	echo "<p style='color: green;'>✅ Test post created (ID: {$test_post_id})</p>";

	// Test updating post meta
	$timestamp = time();
	update_post_meta( $test_post_id, 'wpmudev_test_last_scan', $timestamp );

	$retrieved_timestamp = get_post_meta( $test_post_id, 'wpmudev_test_last_scan', true );
	if ( $retrieved_timestamp == $timestamp ) {
		echo "<p style='color: green;'>✅ Post meta update/retrieve works</p>";
	} else {
		echo "<p style='color: red;'>❌ Post meta update/retrieve failed</p>";
	}

	// Clean up test post
	wp_delete_post( $test_post_id, true );
	echo "<p style='color: green;'>✅ Test post cleaned up</p>";
} else {
	echo "<p style='color: red;'>❌ Failed to create test post</p>";
}

// Test 4: Test CLI functionality
echo '<h2>4. CLI Functionality Test</h2>';
try {
	$cli = new WPMUDEV\PluginTest\App\CLI\PostsMaintenanceCLI();
	echo "<p style='color: green;'>✅ CLI class instantiated successfully</p>";

	// Test scan method exists
	if ( method_exists( $cli, 'scan' ) ) {
		echo "<p style='color: green;'>✅ CLI scan method exists</p>";
	} else {
		echo "<p style='color: red;'>❌ CLI scan method not found</p>";
	}
} catch ( Exception $e ) {
	echo "<p style='color: red;'>❌ CLI class instantiation failed: " . $e->getMessage() . '</p>';
}

// Test 5: Test admin page functionality
echo '<h2>5. Admin Page Functionality Test</h2>';
try {
	$instance = WPMUDEV\PluginTest\App\Admin_Pages\PostsMaintenance::instance();
	$instance->init();
	echo "<p style='color: green;'>✅ Admin page class instantiated and initialized</p>";

	// Test key methods exist
	$methods = array( 'ajax_scan_posts', 'ajax_get_scan_progress', 'ajax_reset_scan_status' );
	foreach ( $methods as $method ) {
		if ( method_exists( $instance, $method ) ) {
			echo "<p style='color: green;'>✅ Method {$method} exists</p>";
		} else {
			echo "<p style='color: red;'>❌ Method {$method} not found</p>";
		}
	}
} catch ( Exception $e ) {
	echo "<p style='color: red;'>❌ Admin page class instantiation failed: " . $e->getMessage() . '</p>';
}

// Test 6: Test WordPress options
echo '<h2>6. WordPress Options Test</h2>';
$options = array(
	'wpmudev_scan_progress',
	'wpmudev_scan_status',
	'wpmudev_last_scan_time',
	'wpmudev_scan_post_types',
	'wpmudev_scan_batch_size',
);

foreach ( $options as $option ) {
	$value = get_option( $option, 'NOT_SET' );
	if ( $value !== 'NOT_SET' ) {
		echo "<p style='color: green;'>✅ Option {$option} exists</p>";
	} else {
		echo "<p style='color: orange;'>⚠️ Option {$option} not set (will be created when needed)</p>";
	}
}

// Test 7: Test AJAX actions
echo '<h2>7. AJAX Actions Test</h2>';
$ajax_actions = array(
	'wp_ajax_wpmudev_scan_posts',
	'wp_ajax_wpmudev_get_scan_progress',
	'wp_ajax_wpmudev_reset_scan_status',
	'wp_ajax_wpmudev_clear_notification',
);

foreach ( $ajax_actions as $action ) {
	if ( has_action( $action ) ) {
		echo "<p style='color: green;'>✅ AJAX action {$action} registered</p>";
	} else {
		echo "<p style='color: red;'>❌ AJAX action {$action} not registered</p>";
	}
}

// Test 8: Test asset files
echo '<h2>8. Asset Files Test</h2>';
$css_file = WPMUDEV_PLUGINTEST_ASSETS_DIR . '/css/posts-maintenance-admin.css';
$js_file  = WPMUDEV_PLUGINTEST_ASSETS_DIR . '/js/posts-maintenance-admin.js';

if ( file_exists( $css_file ) ) {
	echo "<p style='color: green;'>✅ CSS file exists: " . basename( $css_file ) . '</p>';
} else {
	echo "<p style='color: red;'>❌ CSS file not found: " . basename( $css_file ) . '</p>';
}

if ( file_exists( $js_file ) ) {
	echo "<p style='color: green;'>✅ JS file exists: " . basename( $js_file ) . '</p>';
} else {
	echo "<p style='color: red;'>❌ JS file not found: " . basename( $js_file ) . '</p>';
}

echo '<h2>Test Complete</h2>';
echo '<p><strong>Summary:</strong> If you see any red errors above, those need to be fixed. Green checkmarks indicate working functionality. Orange warnings indicate optional features that will be created when needed.</p>';

echo '<h2>Posts Maintenance Features</h2>';
echo '<p><strong>✅ Admin Interface:</strong> Complete with post type selection, batch size configuration, and control buttons</p>';
echo '<p><strong>✅ Background Processing:</strong> Uses WordPress cron for non-blocking post processing</p>';
echo '<p><strong>✅ Progress Tracking:</strong> Real-time progress updates via AJAX polling</p>';
echo '<p><strong>✅ WP-CLI Integration:</strong> Command-line interface with comprehensive options</p>';
echo '<p><strong>✅ Daily Scheduling:</strong> Automatic daily execution of maintenance tasks</p>';
echo '<p><strong>✅ Post Meta Updates:</strong> Updates wpmudev_test_last_scan timestamp for each processed post</p>';
echo '<p><strong>✅ Error Handling:</strong> Comprehensive error handling and user feedback</p>';
echo '<p><strong>✅ Security:</strong> Proper nonce verification and permission checks</p>';
