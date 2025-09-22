<?php
/**
 * Test Posts Maintenance functionality
 *
 * @package WPMUDEV_PluginTest
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only run for administrators
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}

echo "<h1>Posts Maintenance Test</h1>";

// Test 1: Check if class exists
echo "<h2>1. Class Existence</h2>";
if (class_exists('WPMUDEV\\PluginTest\\App\\Admin_Pages\\PostsMaintenance')) {
    echo "<p style='color: green;'>✅ Posts Maintenance class exists</p>";
} else {
    echo "<p style='color: red;'>❌ Posts Maintenance class not found</p>";
    exit;
}

// Test 2: Check if instance can be created
echo "<h2>2. Instance Creation</h2>";
try {
    $instance = WPMUDEV\PluginTest\App\Admin_Pages\PostsMaintenance::instance();
    if (is_object($instance)) {
        echo "<p style='color: green;'>✅ Instance created successfully</p>";
    } else {
        echo "<p style='color: red;'>❌ Instance creation failed</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Instance creation error: " . $e->getMessage() . "</p>";
    exit;
}

// Test 3: Check page slug
echo "<h2>3. Page Slug</h2>";
$page_slug = $instance->__get('page_slug');
if (!empty($page_slug)) {
    echo "<p style='color: green;'>✅ Page slug: " . $page_slug . "</p>";
} else {
    echo "<p style='color: red;'>❌ Page slug is empty</p>";
}

// Test 4: Check if init method works
echo "<h2>4. Initialization</h2>";
try {
    $instance->init();
    echo "<p style='color: green;'>✅ Init method called successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Init method error: " . $e->getMessage() . "</p>";
}

// Test 5: Check if admin menu action is registered
echo "<h2>5. Admin Menu Registration</h2>";
if (has_action('admin_menu', array($instance, 'register_admin_page'))) {
    echo "<p style='color: green;'>✅ Admin menu action registered</p>";
} else {
    echo "<p style='color: red;'>❌ Admin menu action not registered</p>";
}

// Test 6: Check if enqueue assets action is registered
echo "<h2>6. Asset Enqueue Registration</h2>";
if (has_action('admin_enqueue_scripts', array($instance, 'enqueue_assets'))) {
    echo "<p style='color: green;'>✅ Asset enqueue action registered</p>";
} else {
    echo "<p style='color: red;'>❌ Asset enqueue action not registered</p>";
}

// Test 7: Check if AJAX actions are registered
echo "<h2>7. AJAX Actions Registration</h2>";
$ajax_actions = array(
    'wp_ajax_wpmudev_scan_posts',
    'wp_ajax_wpmudev_get_scan_progress',
    'wp_ajax_wpmudev_reset_scan_status',
    'wp_ajax_wpmudev_clear_notification'
);

foreach ($ajax_actions as $action) {
    if (has_action($action)) {
        echo "<p style='color: green;'>✅ {$action} registered</p>";
    } else {
        echo "<p style='color: red;'>❌ {$action} not registered</p>";
    }
}

// Test 8: Check if CSS file exists
echo "<h2>8. CSS File</h2>";
$css_file = WPMUDEV_PLUGINTEST_ASSETS_URL . '/css/posts-maintenance-admin.css';
$css_path = str_replace(WPMUDEV_PLUGINTEST_ASSETS_URL, WPMUDEV_PLUGINTEST_ASSETS_DIR, $css_file);
if (file_exists($css_path)) {
    echo "<p style='color: green;'>✅ CSS file exists: " . $css_path . "</p>";
} else {
    echo "<p style='color: red;'>❌ CSS file not found: " . $css_path . "</p>";
}

// Test 9: Check if JS file exists
echo "<h2>9. JavaScript File</h2>";
$js_file = WPMUDEV_PLUGINTEST_ASSETS_URL . '/js/posts-maintenance-admin.js';
$js_path = str_replace(WPMUDEV_PLUGINTEST_ASSETS_URL, WPMUDEV_PLUGINTEST_ASSETS_DIR, $js_file);
if (file_exists($js_path)) {
    echo "<p style='color: green;'>✅ JS file exists: " . $js_path . "</p>";
} else {
    echo "<p style='color: red;'>❌ JS file not found: " . $js_path . "</p>";
}

// Test 10: Test page callback
echo "<h2>10. Page Callback</h2>";
try {
    ob_start();
    $instance->callback();
    $output = ob_get_clean();
    if (!empty($output)) {
        echo "<p style='color: green;'>✅ Page callback works. Output length: " . strlen($output) . "</p>";
        echo "<details><summary>View Output</summary><pre>" . htmlspecialchars($output) . "</pre></details>";
    } else {
        echo "<p style='color: red;'>❌ Page callback returns empty output</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Page callback error: " . $e->getMessage() . "</p>";
}

echo "<h2>Test Complete</h2>";
echo "<p>If you see any red errors above, those need to be fixed. Green checkmarks indicate working functionality.</p>";
?>

