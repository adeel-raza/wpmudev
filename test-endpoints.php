<?php
/**
 * Test script for Google Drive endpoints
 * 
 * This script tests all the Google Drive REST API endpoints to ensure they're working correctly.
 * Run this from the WordPress admin or via WP-CLI.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only run for administrators
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}

echo "<h1>Google Drive Endpoints Test</h1>";

// Test 1: Check if credentials endpoint works
echo "<h2>1. Testing Credentials Endpoint</h2>";
$credentials_response = wp_remote_get(
    home_url('/wp-json/wpmudev/v1/drive/credentials'),
    array(
        'headers' => array(
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        )
    )
);

if (is_wp_error($credentials_response)) {
    echo "<p style='color: red;'>❌ Credentials endpoint failed: " . $credentials_response->get_error_message() . "</p>";
} else {
    $credentials_data = json_decode(wp_remote_retrieve_body($credentials_response), true);
    echo "<p style='color: green;'>✅ Credentials endpoint working</p>";
    echo "<pre>" . print_r($credentials_data, true) . "</pre>";
}

// Test 2: Check auth status endpoint
echo "<h2>2. Testing Auth Status Endpoint</h2>";
$auth_status_response = wp_remote_get(
    home_url('/wp-json/wpmudev/v1/drive/auth-status'),
    array(
        'headers' => array(
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        )
    )
);

if (is_wp_error($auth_status_response)) {
    echo "<p style='color: red;'>❌ Auth status endpoint failed: " . $auth_status_response->get_error_message() . "</p>";
} else {
    $auth_status_data = json_decode(wp_remote_retrieve_body($auth_status_response), true);
    echo "<p style='color: green;'>✅ Auth status endpoint working</p>";
    echo "<pre>" . print_r($auth_status_data, true) . "</pre>";
}

// Test 3: Check files endpoint
echo "<h2>3. Testing Files Endpoint</h2>";
$files_response = wp_remote_get(
    home_url('/wp-json/wpmudev/v1/drive/files'),
    array(
        'headers' => array(
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        )
    )
);

if (is_wp_error($files_response)) {
    echo "<p style='color: red;'>❌ Files endpoint failed: " . $files_response->get_error_message() . "</p>";
} else {
    $files_data = json_decode(wp_remote_retrieve_body($files_response), true);
    echo "<p style='color: green;'>✅ Files endpoint working</p>";
    echo "<pre>" . print_r($files_data, true) . "</pre>";
}

// Test 4: Check if Google Client is properly initialized
echo "<h2>4. Testing Google Client Initialization</h2>";
$client_id = get_option('wpmudev_drive_client_id', '');
$client_secret = get_option('wpmudev_drive_client_secret', '');

if (empty($client_id) || empty($client_secret)) {
    echo "<p style='color: orange;'>⚠️ No credentials stored yet</p>";
} else {
    echo "<p style='color: green;'>✅ Credentials found in database</p>";
    echo "<p>Client ID: " . (strlen($client_id) > 20 ? substr($client_id, 0, 20) . '...' : $client_id) . "</p>";
    echo "<p>Client Secret: " . (strlen($client_secret) > 20 ? substr($client_secret, 0, 20) . '...' : $client_secret) . "</p>";
}

// Test 5: Check if REST API routes are registered
echo "<h2>5. Testing REST API Routes Registration</h2>";
$rest_server = rest_get_server();
$routes = $rest_server->get_routes();

$drive_routes = array_filter($routes, function($route) {
    return strpos($route, '/wpmudev/v1/drive/') === 0;
});

echo "<p>Found " . count($drive_routes) . " Google Drive routes:</p>";
echo "<ul>";
foreach ($drive_routes as $route => $handlers) {
    echo "<li>" . $route . "</li>";
}
echo "</ul>";

// Test 6: Check WordPress options
echo "<h2>6. Testing WordPress Options</h2>";
$options_to_check = array(
    'wpmudev_drive_client_id',
    'wpmudev_drive_client_secret',
    'wpmudev_drive_access_token',
    'wpmudev_drive_refresh_token',
    'wpmudev_drive_token_expires'
);

foreach ($options_to_check as $option) {
    $value = get_option($option, 'NOT_SET');
    if ($value === 'NOT_SET') {
        echo "<p style='color: orange;'>⚠️ {$option}: Not set</p>";
    } else {
        $display_value = is_string($value) && strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value;
        echo "<p style='color: green;'>✅ {$option}: {$display_value}</p>";
    }
}

echo "<h2>Test Complete</h2>";
echo "<p>If you see any red errors above, those need to be fixed. Green checkmarks indicate working functionality.</p>";
?>

