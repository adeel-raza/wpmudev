# WPMUDEV Plugin Test - Review & Testing Guide

## Quick Setup for Reviewers

### 1. Installation
```bash
# 1. Extract plugin to wp-content/plugins/wpmudev-plugin-test/
# 2. Install dependencies
cd wp-content/plugins/wpmudev-plugin-test/
composer install

# 3. Activate plugin in WordPress admin
```

### 2. Google Drive Setup (Optional)
1. Go to **Google Cloud Console** → Create project → Enable Google Drive API
2. Create OAuth 2.0 credentials (Web application)
3. Add redirect URI: `https://yoursite.com/wp-admin/admin.php?page=wpmudev_plugintest_googledrive`
4. In WordPress: **Google Drive Settings** → Enter credentials → Authenticate

### 3. Test Posts Maintenance
1. Go to **Posts Maintenance** in WordPress admin
2. Select post types (posts, pages)
3. Set batch size (10-20 recommended)
4. Click **Start Scan**
5. Watch real-time progress bar

## What to Test

### ✅ Core Functionality
- [ ] Plugin activates without errors
- [ ] Admin pages load correctly
- [ ] Posts scan works with different batch sizes
- [ ] Progress bar shows real-time updates
- [ ] Google Drive authentication (if configured)
- [ ] File upload/download (if Google Drive configured)

### ✅ Edge Cases
- [ ] Scan with 0 posts
- [ ] Scan with large number of posts (100+)
- [ ] Different post types (posts, pages, custom)
- [ ] Invalid batch sizes (should be limited to 1-100)
- [ ] Stop scan functionality

### ✅ Error Handling
- [ ] Invalid Google Drive credentials
- [ ] Network timeouts
- [ ] Memory limits
- [ ] Permission errors

## Dependencies

### Required
- WordPress 5.0+
- PHP 7.4+
- Composer

### Composer Dependencies
- `google/apiclient` - Google Drive API
- `firebase/php-jwt` - JWT token handling
- `phpunit/phpunit` - Unit testing (dev)

## Key Features Implemented

### 1. Google Drive Integration
- OAuth 2.0 authentication
- Encrypted credential storage
- File upload/download
- Folder creation
- File listing with pagination

### 2. Posts Maintenance
- Background batch processing
- Real-time progress updates
- Multiple post type support
- WordPress cron integration
- Meta timestamp updates

### 3. Security & Standards
- WordPress Coding Standards compliant
- Nonce verification
- Input sanitization
- Output escaping
- Capability checks

## Testing Commands

### Run Unit Tests
```bash
# Set up WordPress test environment
bin/install-wp-tests.sh wordpress_test [your_mysql_root_user] '[your_mysql_user_passwd]' localhost latest

# Run tests
vendor/bin/phpunit
```

### Code Standards Check
```bash
# Check coding standards
vendor/bin/phpcs --standard=WordPress app/

# Auto-fix issues
vendor/bin/phpcbf --standard=WordPress app/
```

## Design Decisions

### 1. Background Processing
- **Decision**: Use WordPress cron instead of immediate processing
- **Reason**: Prevents timeouts on large datasets
- **Trade-off**: Slight delay but better reliability

### 2. Batch Processing
- **Decision**: Process posts in configurable batches (1-100)
- **Reason**: Memory efficiency and progress visibility
- **Trade-off**: More complex but scalable

### 3. Polling vs WebSockets
- **Decision**: Use AJAX polling for progress updates
- **Reason**: Simpler implementation, no additional dependencies
- **Trade-off**: More server requests but easier to maintain

### 4. Encryption Method
- **Decision**: AES-256 with HMAC for credentials
- **Reason**: WordPress standard, secure, no external dependencies
- **Trade-off**: Slightly more complex than base64

## File Structure
```
wpmudev-plugin-test/
├── app/
│   ├── admin-pages/     # Admin interface classes
│   ├── cli/            # WP-CLI commands
│   └── endpoints/      # REST API endpoints
├── assets/             # CSS/JS files
├── tests/              # Unit tests
├── docs/               # Documentation
└── wpmudev-plugin-test.php  # Main plugin file
```

## Quick Troubleshooting

### Common Issues
1. **Scan not starting**: Check WordPress cron is working
2. **Progress not updating**: Check browser console for JS errors
3. **Google Drive auth fails**: Verify redirect URI matches exactly
4. **Memory errors**: Reduce batch size

### Debug Mode
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Review Checklist

- [ ] Code follows WordPress standards
- [ ] Security measures implemented
- [ ] Error handling comprehensive
- [ ] Performance optimized
- [ ] Documentation complete
- [ ] Tests pass
- [ ] No PHP warnings/errors
- [ ] UI is responsive and user-friendly
