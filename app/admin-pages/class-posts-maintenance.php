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
defined('WPINC') || die;

use WPMUDEV\PluginTest\Base;

/**
 * Posts Maintenance admin page class.
 *
 * Handles the posts maintenance functionality including scanning,
 * background processing, and scheduling.
 */
class PostsMaintenance extends Base
{
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
    private $page_slug = 'wpmudev_plugintest_posts_maintenance';

    /**
     * Initializes the page.
     *
     * @return void
     * @since 1.0.0
     */
    public function init()
    {
        $this->page_title = __('Posts Maintenance', 'wpmudev-plugin-test');

        add_action('admin_menu', array( $this, 'register_admin_page' ));
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_assets' ));
        add_action('wp_ajax_wpmudev_scan_posts', array( $this, 'ajax_scan_posts' ));
        add_action('wp_ajax_wpmudev_get_scan_progress', array( $this, 'ajax_get_scan_progress' ));
        add_action('init', array( $this, 'schedule_daily_scan' ));
        add_action('wpmudev_daily_posts_scan', array( $this, 'run_daily_scan' ));
    }

    /**
     * Register admin page.
     */
    public function register_admin_page()
    {
        $page = add_menu_page(
            'Posts Maintenance',
            $this->page_title,
            'manage_options',
            $this->page_slug,
            array( $this, 'callback' ),
            'dashicons-admin-tools',
            8
        );

        add_action('load-' . $page, array( $this, 'prepare_assets' ));
    }

    /**
     * The admin page callback method.
     *
     * @return void
     */
    public function callback()
    {
        $this->view();
    }

    /**
     * Enqueue assets.
     */
    public function enqueue_assets()
    {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'toplevel_page_' . $this->page_slug) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('wp-util');
        }
    }

    /**
     * Prepare assets.
     */
    public function prepare_assets()
    {
        // Assets preparation if needed.
    }

    /**
     * AJAX handler for scanning posts.
     */
    public function ajax_scan_posts()
    {
        // Check nonce.
        if (! wp_verify_nonce($_POST['nonce'], 'wpmudev_scan_posts')) {
            wp_die('Security check failed');
        }

        // Check permissions.
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $post_types = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : array( 'post', 'page' );
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 10;

        // Start background processing.
        $this->start_background_scan($post_types, $batch_size);

        wp_send_json_success(array(
            'message' => __('Scan started successfully', 'wpmudev-plugin-test'),
        ));
    }

    /**
     * AJAX handler for getting scan progress.
     */
    public function ajax_get_scan_progress()
    {
        // Check nonce.
        if (! wp_verify_nonce($_POST['nonce'], 'wpmudev_scan_posts')) {
            wp_die('Security check failed');
        }

        // Check permissions.
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $progress = get_option('wpmudev_scan_progress', array());
        $status = get_option('wpmudev_scan_status', 'idle');

        wp_send_json_success(array(
            'progress' => $progress,
            'status'   => $status,
        ));
    }

    /**
     * Start background scan process.
     *
     * @param array $post_types Array of post types to scan.
     * @param int   $batch_size Number of posts to process per batch.
     */
    private function start_background_scan($post_types, $batch_size)
    {
        // Update scan status.
        update_option('wpmudev_scan_status', 'running');
        update_option('wpmudev_scan_progress', array(
            'processed' => 0,
            'total'     => 0,
            'current_batch' => 0,
            'post_types' => $post_types,
        ));

        // Get total count.
        $total_posts = $this->get_total_posts_count($post_types);
        update_option('wpmudev_scan_progress', array(
            'processed' => 0,
            'total'     => $total_posts,
            'current_batch' => 0,
            'post_types' => $post_types,
        ));

        // Schedule first batch.
        wp_schedule_single_event(time(), 'wpmudev_process_posts_batch', array( $post_types, $batch_size, 0 ));
    }

    /**
     * Get total posts count for given post types.
     *
     * @param array $post_types Array of post types to count.
     * @return int Total number of posts.
     */
    private function get_total_posts_count($post_types)
    {
        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );

        $query = new \WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Process posts batch.
     *
     * @param array $post_types Array of post types to process.
     * @param int   $batch_size Number of posts to process per batch.
     * @param int   $offset     Offset for the current batch.
     */
    public function process_posts_batch($post_types, $batch_size, $offset)
    {
        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'fields'         => 'ids',
        );

        $query = new \WP_Query($args);
        $posts = $query->posts;

        $processed = 0;
        foreach ($posts as $post_id) {
            // Update post meta with current timestamp.
            update_post_meta($post_id, 'wpmudev_test_last_scan', time());
            $processed++;
        }

        // Update progress.
        $progress = get_option('wpmudev_scan_progress', array());
        $progress['processed'] += $processed;
        $progress['current_batch'] = $offset + $batch_size;
        update_option('wpmudev_scan_progress', $progress);

        // Check if we need to process more batches.
        if ($progress['processed'] < $progress['total']) {
            // Schedule next batch.
            wp_schedule_single_event(time() + 5, 'wpmudev_process_posts_batch', array( $post_types, $batch_size, $offset + $batch_size ));
        } else {
            // Scan completed.
            update_option('wpmudev_scan_status', 'completed');
            update_option('wpmudev_last_scan_time', time());
        }
    }

    /**
     * Schedule daily scan.
     */
    public function schedule_daily_scan()
    {
        if (! wp_next_scheduled('wpmudev_daily_posts_scan')) {
            wp_schedule_event(time(), 'daily', 'wpmudev_daily_posts_scan');
        }
    }

    /**
     * Run daily scan.
     */
    public function run_daily_scan()
    {
        $post_types = get_option('wpmudev_scan_post_types', array( 'post', 'page' ));
        $batch_size = get_option('wpmudev_scan_batch_size', 10);

        $this->start_background_scan($post_types, $batch_size);
    }

    /**
     * Prints the admin page content.
     *
     * @return void
     */
    protected function view()
    {
        $progress = get_option('wpmudev_scan_progress', array());
        $status = get_option('wpmudev_scan_status', 'idle');
        $last_scan = get_option('wpmudev_last_scan_time', 0);
        $post_types = get_option('wpmudev_scan_post_types', array( 'post', 'page' ));

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->page_title); ?></h1>
            
            <div class="sui-box">
                <div class="sui-box-header">
                    <h2 class="sui-box-title"><?php _e('Posts Scan Status', 'wpmudev-plugin-test'); ?></h2>
                </div>
                <div class="sui-box-body">
                    <div id="scan-status">
                        <p><strong><?php _e('Status:', 'wpmudev-plugin-test'); ?></strong> <span id="current-status"><?php echo esc_html(ucfirst($status)); ?></span></p>
                        <?php if ($last_scan > 0) : ?>
                            <p><strong><?php _e('Last Scan:', 'wpmudev-plugin-test'); ?></strong> <?php echo esc_html(date('Y-m-d H:i:s', $last_scan)); ?></p>
                        <?php endif; ?>
                        <div id="progress-bar" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 0%;"></div>
                            </div>
                            <p id="progress-text">0% (0/0)</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sui-box">
                <div class="sui-box-header">
                    <h2 class="sui-box-title"><?php _e('Scan Configuration', 'wpmudev-plugin-test'); ?></h2>
                </div>
                <div class="sui-box-body">
                    <form id="scan-config-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Post Types', 'wpmudev-plugin-test'); ?></th>
                                <td>
                                    <?php
                                    $available_post_types = get_post_types(array( 'public' => true ), 'objects');
                                    foreach ($available_post_types as $post_type) :
                                        $checked = in_array($post_type->name, $post_types) ? 'checked' : '';
                                        ?>
                                        <label>
                                            <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php echo $checked; ?>>
                                            <?php echo esc_html($post_type->label); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Batch Size', 'wpmudev-plugin-test'); ?></th>
                                <td>
                                    <input type="number" name="batch_size" value="10" min="1" max="100" class="regular-text">
                                    <p class="description"><?php _e('Number of posts to process per batch (1-100)', 'wpmudev-plugin-test'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
                <div class="sui-box-footer">
                    <div class="sui-actions-right">
                        <button type="button" id="start-scan" class="button button-primary" <?php echo $status === 'running' ? 'disabled' : ''; ?>>
                            <?php _e('Start Scan', 'wpmudev-plugin-test'); ?>
                        </button>
                        <button type="button" id="stop-scan" class="button button-secondary" style="display: none;">
                            <?php _e('Stop Scan', 'wpmudev-plugin-test'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .progress-bar {
                width: 100%;
                height: 20px;
                background-color: #f0f0f0;
                border-radius: 10px;
                overflow: hidden;
                margin: 10px 0;
            }
            .progress-fill {
                height: 100%;
                background-color: #0073aa;
                transition: width 0.3s ease;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            let scanInterval;
            
            $('#start-scan').on('click', function() {
                const postTypes = $('input[name="post_types[]"]:checked').map(function() {
                    return this.value;
                }).get();
                const batchSize = $('input[name="batch_size"]').val();
                
                if (postTypes.length === 0) {
                    alert('<?php _e('Please select at least one post type', 'wpmudev-plugin-test'); ?>');
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'wpmudev_scan_posts',
                    post_types: postTypes,
                    batch_size: batchSize,
                    nonce: '<?php echo wp_create_nonce('wpmudev_scan_posts'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#current-status').text('Running');
                        $('#progress-bar').show();
                        $('#start-scan').prop('disabled', true);
                        $('#stop-scan').show();
                        
                        // Start polling for progress
                        scanInterval = setInterval(updateProgress, 2000);
                    } else {
                        alert('<?php _e('Failed to start scan', 'wpmudev-plugin-test'); ?>');
                    }
                });
            });
            
            $('#stop-scan').on('click', function() {
                if (scanInterval) {
                    clearInterval(scanInterval);
                }
                $('#current-status').text('Stopped');
                $('#start-scan').prop('disabled', false);
                $('#stop-scan').hide();
            });
            
            function updateProgress() {
                $.post(ajaxurl, {
                    action: 'wpmudev_get_scan_progress',
                    nonce: '<?php echo wp_create_nonce('wpmudev_scan_posts'); ?>'
                }, function(response) {
                    if (response.success) {
                        const progress = response.data.progress;
                        const status = response.data.status;
                        
                        $('#current-status').text(status.charAt(0).toUpperCase() + status.slice(1));
                        
                        if (progress.total > 0) {
                            const percentage = Math.round((progress.processed / progress.total) * 100);
                            $('.progress-fill').css('width', percentage + '%');
                            $('#progress-text').text(percentage + '% (' + progress.processed + '/' + progress.total + ')');
                        }
                        
                        if (status === 'completed' || status === 'idle') {
                            clearInterval(scanInterval);
                            $('#start-scan').prop('disabled', false);
                            $('#stop-scan').hide();
                        }
                    }
                });
            }
            
            // Initial progress check
            if ($('#current-status').text() === 'Running') {
                updateProgress();
                scanInterval = setInterval(updateProgress, 2000);
            }
        });
        </script>
        <?php
    }
}




