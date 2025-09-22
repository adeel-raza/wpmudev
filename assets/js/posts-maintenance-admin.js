/**
 * Posts Maintenance Admin JavaScript
 *
 * @package WPMUDEV_PluginTest
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Main Posts Maintenance object
    window.WPMUDEVPostsMaintenance = {
        
        // Configuration
        config: {
            ajaxUrl: wpmudevPostsMaintenance.ajaxUrl,
            nonce: wpmudevPostsMaintenance.nonce,
            pollInterval: 2000, // 2 seconds
            maxPollAttempts: 300 // 10 minutes max
        },

        // State
        state: {
            isScanning: false,
            pollAttempts: 0,
            pollTimer: null
        },

        // Initialize
        init: function() {
            this.bindEvents();
            this.loadInitialState();
        },

        // Bind events
        bindEvents: function() {
            var self = this;

            // Start scan button
            $(document).on('click', '#start-scan', function(e) {
                e.preventDefault();
                self.startScan();
            });

            // Stop scan button
            $(document).on('click', '#stop-scan', function(e) {
                e.preventDefault();
                self.stopScan();
            });

            // Reset scan button
            $(document).on('click', '#reset-status', function(e) {
                e.preventDefault();
                self.resetScan();
            });

            // Clear notification button
            $(document).on('click', '.wpmudev-clear-notification', function(e) {
                e.preventDefault();
                self.clearNotification($(this).data('notification-id'));
            });

            // Post type checkboxes
            $(document).on('change', 'input[name="post_types[]"]', function() {
                self.updateStartButtonState();
            });

            // Batch size input
            $(document).on('input', '#batch_size', function() {
                self.updateStartButtonState();
            });
        },

        // Load initial state
        loadInitialState: function() {
            var self = this;
            
            // Hide progress bar by default
            $('#progress-bar').hide();
            
            // Check if there's an active scan
            $.post(this.config.ajaxUrl, {
                action: 'wpmudev_get_scan_progress',
                nonce: this.config.nonce
            }, function(response) {
                if (response.success && response.data) {
                    self.updateUI(response.data);
                    
                    // If scan is running, start polling
                    if (response.data.is_running) {
                        self.state.isScanning = true;
                        self.startPolling();
                    } else {
                        // Ensure scanning state is false if no scan is running
                        self.state.isScanning = false;
                        self.updateButtons(false);
                    }
                }
            }).fail(function() {
                self.showNotification('error', 'Failed to load scan status');
                // Ensure scanning state is false on error
                self.state.isScanning = false;
                self.updateButtons(false);
            });
        },

        // Start scan
        startScan: function() {
            var self = this;
            var postTypes = [];
            var batchSize = parseInt($('#batch_size').val()) || 10;

            // Get selected post types
            $('input[name="post_types[]"]:checked').each(function() {
                postTypes.push($(this).val());
            });

            if (postTypes.length === 0) {
                this.showNotification('error', 'Please select at least one post type');
                return;
            }

            if (batchSize < 1 || batchSize > 100) {
                this.showNotification('error', 'Batch size must be between 1 and 100');
                return;
            }

            // Update UI
            this.state.isScanning = true;
            this.updateUI({
                is_running: true,
                progress: 0,
                current_batch: 0,
                total_batches: 0,
                processed_posts: 0,
                total_posts: 0,
                status: 'starting'
            });
            
            // Ensure progress bar is visible
            $('#progress-bar').show();

            // Start the scan
            $.post(this.config.ajaxUrl, {
                action: 'wpmudev_scan_posts',
                post_types: postTypes,
                batch_size: batchSize,
                nonce: this.config.nonce
            }, function(response) {
                if (response.success) {
                    self.showNotification('success', 'Scan started successfully');
                    self.startPolling();
                } else {
                    self.showNotification('error', response.data || 'Failed to start scan');
                    self.state.isScanning = false;
                    self.updateStartButtonState();
                }
            }).fail(function() {
                self.showNotification('error', 'Failed to start scan');
                self.state.isScanning = false;
                self.updateStartButtonState();
            });
        },

        // Stop scan
        stopScan: function() {
            var self = this;
            
            this.state.isScanning = false;
            this.stopPolling();
            
            // Update UI to show stopped state
            this.updateUI({
                is_running: false,
                status: 'stopped'
            });
            
            this.showNotification('warning', 'Scan stopped by user');
        },

        // Reset scan
        resetScan: function() {
            var self = this;
            
            if (confirm('Are you sure you want to reset the scan? This will clear all progress and statistics.')) {
                $.post(this.config.ajaxUrl, {
                    action: 'wpmudev_reset_scan_status',
                    nonce: this.config.nonce
                }, function(response) {
                    if (response.success) {
                        self.state.isScanning = false;
                        self.stopPolling();
                        self.updateUI({
                            is_running: false,
                            progress: 0,
                            current_batch: 0,
                            total_batches: 0,
                            processed_posts: 0,
                            total_posts: 0,
                            status: 'idle'
                        });
                        self.showNotification('success', 'Scan reset successfully');
                    } else {
                        self.showNotification('error', response.data || 'Failed to reset scan');
                    }
                }).fail(function() {
                    self.showNotification('error', 'Failed to reset scan');
                });
            }
        },

        // Start polling for progress
        startPolling: function() {
            var self = this;
            
            if (this.state.pollTimer) {
                clearInterval(this.state.pollTimer);
            }
            
            this.state.pollAttempts = 0;
            this.state.pollTimer = setInterval(function() {
                self.pollProgress();
            }, this.config.pollInterval);
        },

        // Stop polling
        stopPolling: function() {
            if (this.state.pollTimer) {
                clearInterval(this.state.pollTimer);
                this.state.pollTimer = null;
            }
        },

        // Poll for progress
        pollProgress: function() {
            var self = this;
            
            this.state.pollAttempts++;
            
            // Stop polling if we've exceeded max attempts
            if (this.state.pollAttempts > this.config.maxPollAttempts) {
                this.stopPolling();
                this.showNotification('error', 'Scan polling timeout. Please refresh the page.');
                return;
            }
            
            $.post(this.config.ajaxUrl, {
                action: 'wpmudev_get_scan_progress',
                nonce: this.config.nonce
            }, function(response) {
                if (response.success && response.data) {
                    self.updateUI(response.data);
                    
                    // Stop polling if scan is complete or stopped
                    if (!response.data.is_running) {
                        self.state.isScanning = false;
                        self.stopPolling();
                    }
                }
            }).fail(function() {
                self.state.pollAttempts++;
                if (self.state.pollAttempts > 5) {
                    self.stopPolling();
                    self.showNotification('error', 'Failed to get scan progress');
                }
            });
        },

        // Update UI based on scan data
        updateUI: function(data) {
            // Update progress bar with post counts
            this.updateProgressBar(
                data.progress || 0,
                data.processed_posts,
                data.total_posts
            );
            
            // Update statistics
            this.updateStatistics(data);
            
            // Update status
            this.updateStatus(data.status || 'idle');
            
            // Update buttons
            this.updateButtons(data.is_running || false);
            
            // Update notifications
            if (data.notification) {
                this.showNotification(data.notification.type, data.notification.message);
            }
            
            // Handle scan completion and reset redirects
            this.handleScanCompletion(data);
        },

        // Update progress bar
        updateProgressBar: function(progress, processedPosts, totalPosts) {
            var $progressBar = $('.progress-fill');
            var $progressContainer = $('#progress-bar');
            var $progressText = $('#progress-text');
            
            // Ensure progress is a valid number between 0 and 100
            var validProgress = 0;
            if (typeof progress === 'number' && !isNaN(progress)) {
                validProgress = Math.max(0, Math.min(100, progress));
            }
            
            if ($progressBar.length) {
                $progressBar.css('width', validProgress + '%');
            }
            
            // Show progress bar only when scanning
            if ($progressContainer.length) {
                if (this.state.isScanning) {
                    $progressContainer.show();
                } else {
                    $progressContainer.hide();
                }
            }
            
            if ($progressText.length) {
                var progressText = '';
                if (processedPosts !== undefined && totalPosts !== undefined) {
                    progressText = processedPosts + ' / ' + totalPosts + ' posts (' + Math.round(validProgress) + '%)';
                } else {
                    progressText = Math.round(validProgress) + '%';
                }
                $progressText.text(progressText);
            }
        },

        // Handle scan completion
        handleScanCompletion: function(data) {
            // Check if scan is completed
            if (!data.is_running && (data.status === 'completed' || data.status === 'stopped')) {
                // Reset scanning state
                this.state.isScanning = false;
                
                // Hide progress bar after completion
                $('#progress-bar').hide();
                
                // Show completion message
                if (data.status === 'completed') {
                    this.showNotification('success', 'Scan completed successfully!');
                } else if (data.status === 'stopped') {
                    this.showNotification('warning', 'Scan stopped.');
                }
                
                // Update buttons to enable start button
                this.updateButtons(false);
            }
        },

        // Update statistics
        updateStatistics: function(data) {
            // Update status display with progress information
            if (data.processed_posts !== undefined && data.total_posts !== undefined) {
                var statusText = this.getStatusText(data.status || 'idle');
                if (data.is_running) {
                    statusText += ' (' + data.processed_posts + '/' + data.total_posts + ')';
                }
                $('#current-status').text(statusText);
            }
        },

        // Update status
        updateStatus: function(status) {
            var $statusElement = $('#current-status');
            
            if ($statusElement.length) {
                var statusText = this.getStatusText(status);
                $statusElement.text(statusText);
            }
        },

        // Get status text
        getStatusText: function(status) {
            var statusTexts = {
                'idle': 'Idle',
                'starting': 'Starting',
                'running': 'Running',
                'paused': 'Paused',
                'completed': 'Completed',
                'stopped': 'Stopped',
                'error': 'Error'
            };
            
            return statusTexts[status] || 'Unknown';
        },

        // Update buttons
        updateButtons: function(isRunning) {
            var $startBtn = $('#start-scan');
            var $stopBtn = $('#stop-scan');
            var $resetBtn = $('#reset-status');
            
            if (isRunning) {
                $startBtn.prop('disabled', true).addClass('wpmudev-hidden');
                $stopBtn.prop('disabled', false).removeClass('wpmudev-hidden');
                $resetBtn.prop('disabled', true);
            } else {
                $startBtn.prop('disabled', false).removeClass('wpmudev-hidden');
                $stopBtn.prop('disabled', true).addClass('wpmudev-hidden');
                $resetBtn.prop('disabled', false);
            }
            
            this.updateStartButtonState();
        },

        // Update start button state
        updateStartButtonState: function() {
            var $startBtn = $('#start-scan');
            var hasPostTypes = $('input[name="post_types[]"]:checked').length > 0;
            var batchSize = parseInt($('#batch_size').val()) || 0;
            var isValidBatchSize = batchSize >= 1 && batchSize <= 100;
            
            if (hasPostTypes && isValidBatchSize && !this.state.isScanning) {
                $startBtn.prop('disabled', false);
            } else {
                $startBtn.prop('disabled', true);
            }
        },

        // Show notification
        showNotification: function(type, message) {
            // Use WordPress admin notice system
            var noticeClass = 'notice notice-' + type + ' is-dismissible';
            var noticeId = 'notification-' + Date.now();
            
            var noticeHtml = '<div id="' + noticeId + '" class="' + noticeClass + '">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss" onclick="jQuery(\'#' + noticeId + '\').fadeOut(300, function() { jQuery(this).remove(); });">' +
                '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
                '</div>';
            
            // Remove existing notices
            $('.notice').remove();
            
            // Add new notice after the h1
            $('.wrap h1').after(noticeHtml);
            
            // Auto-remove success notifications after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $('#' + noticeId).fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },

        // Get notification icon
        getNotificationIcon: function(type) {
            var icons = {
                'success': '✓',
                'error': '✗',
                'warning': '⚠',
                'info': 'ℹ'
            };
            
            return icons[type] || 'ℹ';
        },

        // Clear notification
        clearNotification: function(notificationId) {
            $('#' + notificationId).fadeOut(300, function() {
                $(this).remove();
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WPMUDEVPostsMaintenance.init();
    });

})(jQuery);