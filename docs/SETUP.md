# Setup Instructions

## Prerequisites

### System Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Composer (for dependency management)

### Server Requirements
- Memory: 256MB minimum (512MB recommended)
- Upload limit: 10MB minimum
- Cron: WordPress cron must be functional

## Installation Steps

### 1. Download & Extract
```bash
# Extract plugin to WordPress plugins directory
wp-content/plugins/wpmudev-plugin-test/
```

### 2. Install Dependencies
```bash
cd wp-content/plugins/wpmudev-plugin-test/
composer install
```

### 3. Activate Plugin
- Go to WordPress Admin → Plugins
- Find "WPMUDEV Plugin Test"
- Click "Activate"

### 4. Verify Installation
- Check for admin menu items:
  - "Posts Maintenance"
  - "Google Drive Settings"
- No PHP errors in error log

## Google Drive Configuration (Optional)

### 1. Google Cloud Console Setup
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create new project or select existing
3. Enable Google Drive API
4. Go to "Credentials" → "Create Credentials" → "OAuth 2.0 Client ID"
5. Set application type to "Web application"
6. Add authorized redirect URI:
   ```
   https://yoursite.com/wp-admin/admin.php?page=wpmudev_plugintest_googledrive
   ```

### 2. Plugin Configuration
1. Go to WordPress Admin → Google Drive Settings
2. Enter Client ID and Client Secret
3. Click "Save Credentials"
4. Click "Authenticate with Google Drive"
5. Complete OAuth flow in popup

## Testing Setup

### Unit Tests
```bash
# Install test dependencies
composer install --dev

# Set up WordPress test environment
bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run tests
vendor/bin/phpunit
```

### Manual Testing
1. Create test posts:
   ```php
   // Add to functions.php temporarily
   for ($i = 1; $i <= 10; $i++) {
       wp_insert_post([
           'post_title' => "Test Post $i",
           'post_content' => "Test content $i",
           'post_status' => 'publish'
       ]);
   }
   ```

2. Test Posts Maintenance:
   - Go to Posts Maintenance
   - Select post types
   - Set batch size to 5
   - Start scan
   - Verify progress updates

## Configuration Options

### Posts Maintenance Settings
- **Batch Size**: 1-100 posts per batch (default: 10)
- **Post Types**: Any public post type
- **Auto Scan**: Daily automatic scan (enabled by default)

### Google Drive Settings
- **Client ID**: From Google Cloud Console
- **Client Secret**: From Google Cloud Console
- **Redirect URI**: Automatically set

## Troubleshooting

### Common Issues

#### Plugin Won't Activate
- Check PHP version (7.4+ required)
- Check for PHP errors in error log
- Verify all files uploaded correctly

#### Composer Install Fails
- Check internet connection
- Verify Composer is installed
- Try: `composer install --no-dev`

#### Google Drive Auth Fails
- Verify redirect URI matches exactly
- Check Client ID and Secret are correct
- Ensure Google Drive API is enabled

#### Scan Not Working
- Check WordPress cron is functional
- Verify user has `manage_options` capability
- Check for JavaScript errors in browser console

### Debug Mode
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Log Files
- WordPress errors: `/wp-content/debug.log`
- Server errors: Check hosting provider's error logs

## Performance Optimization

### For Large Sites
- Increase PHP memory limit: `memory_limit = 512M`
- Increase max execution time: `max_execution_time = 300`
- Use smaller batch sizes (5-10)

### For High Traffic
- Consider using object caching
- Monitor database performance
- Use CDN for static assets

## Security Considerations

### File Permissions
```bash
# Set proper file permissions
find wp-content/plugins/wpmudev-plugin-test/ -type f -exec chmod 644 {} \;
find wp-content/plugins/wpmudev-plugin-test/ -type d -exec chmod 755 {} \;
```

### Database Security
- Use strong database passwords
- Limit database user permissions
- Regular database backups

### Server Security
- Keep WordPress and plugins updated
- Use HTTPS for all connections
- Regular security scans

## Maintenance

### Regular Tasks
- Monitor error logs
- Check scan completion status
- Verify Google Drive tokens are valid
- Update dependencies when available

### Backup
- Backup database before major updates
- Backup plugin files
- Test restore procedures

## Support

### Getting Help
1. Check this documentation first
2. Review error logs
3. Test in clean WordPress environment
4. Check WordPress and plugin compatibility

### Reporting Issues
Include:
- WordPress version
- PHP version
- Plugin version
- Error messages
- Steps to reproduce
- Screenshots if applicable

