# Testing Guide

## Unit Testing

### Setup
```bash
# Install test dependencies
composer install --dev

# Set up WordPress test environment
bin/install-wp-tests.sh wordpress_test [your_mysql_root_user] '[your_mysql_user_passwd]' localhost latest

# Run all tests
vendor/bin/phpunit
```

### Test Coverage
- **PostsMaintenanceTest.php** - 15+ test cases
  - Normal scanning functionality
  - Edge cases (empty DB, invalid types)
  - Meta updates verification
  - Different post types/statuses
  - Large datasets
  - Error handling
  - Memory limits

## Manual Testing Scenarios

### 1. Basic Functionality
1. **Activate Plugin**
   - No PHP errors in error log
   - Admin menu items appear
   - No JavaScript console errors

2. **Posts Maintenance**
   - Create 5-10 test posts
   - Start scan with batch size 5
   - Verify progress bar updates
   - Check post meta is updated

3. **Google Drive (if configured)**
   - Enter valid credentials
   - Test authentication flow
   - Upload a test file
   - Create a test folder

### 2. Edge Cases
1. **Empty Database**
   - Run scan with no posts
   - Should complete without errors

2. **Large Dataset**
   - Create 100+ posts
   - Run scan with batch size 10
   - Monitor memory usage

3. **Invalid Inputs**
   - Try batch size 0 or 101
   - Should be limited to 1-100

### 3. Error Scenarios
1. **Network Issues**
   - Disconnect internet during Google Drive auth
   - Should show appropriate error

2. **Memory Limits**
   - Set low memory limit in PHP
   - Run large scan
   - Should handle gracefully

3. **Permission Issues**
   - Test with user without manage_options
   - Should show permission denied

## Performance Testing

### Memory Usage
- Monitor memory during large scans
- Check for memory leaks
- Verify cleanup after completion

### Processing Speed
- Test different batch sizes
- Measure time per batch
- Check for bottlenecks

### AJAX Efficiency
- Count AJAX requests during scan
- Verify polling frequency (3 seconds)
- Check for unnecessary requests

## Browser Compatibility
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers

## Security Testing

### Input Validation
- Try SQL injection in form fields
- Test XSS in file names
- Verify nonce validation

### File Upload Security
- Try uploading malicious files
- Test file size limits
- Verify MIME type checking

### Authentication
- Test expired tokens
- Verify OAuth flow security
- Check credential encryption

## Test Data

### Sample Posts
```php
// Create test posts with different content
$post_ids = [];
for ($i = 1; $i <= 20; $i++) {
    $post_ids[] = wp_insert_post([
        'post_title' => "Test Post $i",
        'post_content' => "This is test content for post $i",
        'post_status' => 'publish',
        'post_type' => 'post'
    ]);
}
```

### Sample Files
- Small text file (1KB)
- Medium image (100KB)
- Large PDF (1MB)
- Various file types (.txt, .jpg, .pdf, .doc)

## Expected Results

### Successful Scan
- Progress bar shows 0% to 100%
- Status changes: idle → running → completed
- All posts get `wpmudev_test_last_scan` meta
- Success notification appears
- Page refreshes after completion

### Failed Scan
- Error message displayed
- Scan stops gracefully
- No partial data corruption
- User can retry

### Google Drive Success
- Authentication completes
- Files upload successfully
- Folders create properly
- File listing works
- Download links functional

## Reporting Issues

### Include These Details
1. WordPress version
2. PHP version
3. Plugin version
4. Error messages (exact text)
5. Steps to reproduce
6. Browser and version
7. Screenshots if applicable

### Common Issues & Solutions

**Issue**: Scan not starting
**Solution**: Check WordPress cron, verify permissions

**Issue**: Progress stuck at 0%
**Solution**: Check AJAX requests, verify nonce

**Issue**: Google Drive auth fails
**Solution**: Verify redirect URI, check credentials

**Issue**: Memory limit exceeded
**Solution**: Reduce batch size, increase PHP memory limit

