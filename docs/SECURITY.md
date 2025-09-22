# Security Documentation

## Security Overview

This plugin implements comprehensive security measures following WordPress security best practices and OWASP guidelines. All user inputs are sanitized, outputs are escaped, and sensitive data is encrypted.

## Security Measures Implemented

### 1. Input Sanitization

#### Text Inputs
```php
// Sanitize text inputs
$client_id = sanitize_text_field( wp_unslash( $_POST['client_id'] ) );
$client_secret = sanitize_text_field( wp_unslash( $_POST['client_secret'] ) );
```

#### Numeric Inputs
```php
// Sanitize and validate numeric inputs
$batch_size = intval( wp_unslash( $_POST['batch_size'] ) );
$batch_size = max( 1, min( 100, $batch_size ) ); // Ensure within valid range
```

#### Array Inputs
```php
// Sanitize array inputs
$post_types = array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) );
```

### 2. Output Escaping

#### HTML Content
```php
// Escape HTML content
echo esc_html( $notification['message'] );
echo esc_attr( $notification['type'] );
```

#### URLs
```php
// Escape URLs
echo esc_url( $file['webViewLink'] );
```

#### JavaScript Variables
```php
// Escape JavaScript variables
wp_localize_script( 'script-handle', 'object_name', array(
    'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php' ) ),
    'nonce'   => esc_attr( wp_create_nonce( 'action_name' ) ),
) );
```

### 3. Nonce Verification

#### AJAX Requests
```php
// Verify nonce in AJAX handlers
$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
if ( ! wp_verify_nonce( $nonce, 'wpmudev_scan_posts' ) ) {
    wp_send_json_error( array( 'message' => 'Security check failed' ) );
}
```

#### Form Submissions
```php
// Verify nonce in forms
if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'form_action' ) ) {
    wp_die( 'Security check failed' );
}
```

### 4. Capability Checks

#### Admin Functions
```php
// Check user capabilities
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
}
```

#### AJAX Handlers
```php
// Verify capabilities in AJAX handlers
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Insufficient permissions' );
}
```

### 5. Data Encryption

#### Credentials Encryption
```php
// Encrypt sensitive data
function encrypt_credentials( $data ) {
    $key = wp_salt( 'AUTH_KEY' );
    $iv = wp_salt( 'AUTH_SALT' );
    
    $encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );
    $hmac = hash_hmac( 'sha256', $encrypted, $key );
    
    return base64_encode( $encrypted . ':' . $hmac );
}
```

#### Credentials Decryption
```php
// Decrypt sensitive data
function decrypt_credentials( $encrypted_data ) {
    $key = wp_salt( 'AUTH_KEY' );
    $iv = wp_salt( 'AUTH_SALT' );
    
    $data = base64_decode( $encrypted_data );
    list( $encrypted, $hmac ) = explode( ':', $data, 2 );
    
    // Verify HMAC
    if ( ! hash_equals( hash_hmac( 'sha256', $encrypted, $key ), $hmac ) ) {
        return false;
    }
    
    return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
}
```

### 6. SQL Injection Prevention

#### WordPress Database Methods
```php
// Use WordPress database methods
global $wpdb;
$results = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id = %d",
    'wpmudev_test_last_scan',
    $post_id
) );
```

#### Prepared Statements
```php
// Use prepared statements for dynamic queries
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
    'wpmudev_test_last_scan'
) );
```

### 7. File Upload Security

#### File Type Validation
```php
// Validate file types
$allowed_types = array( 'image/jpeg', 'image/png', 'application/pdf' );
if ( ! in_array( $file['type'], $allowed_types, true ) ) {
    wp_send_json_error( array( 'message' => 'Invalid file type' ) );
}
```

#### File Size Validation
```php
// Validate file size
$max_size = 10 * 1024 * 1024; // 10MB
if ( $file['size'] > $max_size ) {
    wp_send_json_error( array( 'message' => 'File too large' ) );
}
```

#### File Content Validation
```php
// Validate file content
if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
    wp_send_json_error( array( 'message' => 'Invalid file upload' ) );
}
```

### 8. OAuth 2.0 Security

#### State Parameter
```php
// Generate and verify state parameter
$state = wp_generate_password( 32, false );
set_transient( 'wpmudev_oauth_state_' . $state, $user_id, 600 ); // 10 minutes

// Verify state parameter
$stored_state = get_transient( 'wpmudev_oauth_state_' . $state );
if ( ! $stored_state || $stored_state !== $user_id ) {
    wp_send_json_error( array( 'message' => 'Invalid state parameter' ) );
}
```

#### Token Storage
```php
// Store tokens securely
$tokens = array(
    'access_token'  => $access_token,
    'refresh_token' => $refresh_token,
    'expires_in'    => $expires_in,
    'created_at'    => time(),
);

update_option( 'wpmudev_drive_tokens', encrypt_credentials( wp_json_encode( $tokens ) ) );
```

### 9. Error Handling Security

#### Error Information Disclosure
```php
// Don't expose sensitive information in errors
if ( WP_DEBUG ) {
    error_log( 'Google Drive API Error: ' . $e->getMessage() );
}

// Return generic error to user
wp_send_json_error( array( 'message' => 'An error occurred. Please try again.' ) );
```

#### Logging Security
```php
// Log security events
if ( ! wp_verify_nonce( $nonce, 'action' ) ) {
    error_log( 'Security: Invalid nonce attempt from IP: ' . $_SERVER['REMOTE_ADDR'] );
    wp_send_json_error( array( 'message' => 'Security check failed' ) );
}
```

### 10. Session Security

#### Secure Cookies
```php
// Use secure cookies for sensitive data
setcookie( 'wpmudev_temp_data', $data, time() + 3600, '/', '', is_ssl(), true );
```

#### Session Validation
```php
// Validate session data
if ( ! isset( $_SESSION['wpmudev_user_id'] ) || $_SESSION['wpmudev_user_id'] !== get_current_user_id() ) {
    wp_send_json_error( array( 'message' => 'Invalid session' ) );
}
```

## Security Best Practices

### 1. Principle of Least Privilege
- Users only have access to what they need
- Admin functions require `manage_options` capability
- AJAX handlers verify user permissions

### 2. Defense in Depth
- Multiple security layers
- Input validation + output escaping
- Authentication + authorization
- Encryption + integrity verification

### 3. Secure by Default
- Safe defaults for all settings
- Secure configuration out of the box
- Minimal required permissions

### 4. Regular Security Audits
- Code review for security issues
- Automated security scanning
- Penetration testing
- Vulnerability assessments

## Security Checklist

### Development
- [ ] All inputs sanitized
- [ ] All outputs escaped
- [ ] Nonce verification implemented
- [ ] Capability checks in place
- [ ] SQL injection prevention
- [ ] XSS prevention
- [ ] CSRF protection
- [ ] File upload security
- [ ] Error handling security
- [ ] Logging security

### Testing
- [ ] Security testing performed
- [ ] Penetration testing completed
- [ ] Vulnerability scanning done
- [ ] Code review completed
- [ ] Security documentation updated

### Deployment
- [ ] Security headers configured
- [ ] SSL/TLS enabled
- [ ] Firewall configured
- [ ] Access controls in place
- [ ] Monitoring enabled
- [ ] Backup security verified

## Security Incident Response

### 1. Detection
- Monitor error logs
- Watch for suspicious activity
- Automated security alerts
- User reports

### 2. Response
- Immediate containment
- Assess impact
- Notify stakeholders
- Document incident

### 3. Recovery
- Fix vulnerabilities
- Restore services
- Verify security
- Update documentation

### 4. Lessons Learned
- Post-incident review
- Update security measures
- Improve monitoring
- Train team

## Security Resources

### WordPress Security
- [WordPress Security Codex](https://codex.wordpress.org/Security)
- [WordPress Security Best Practices](https://wordpress.org/support/article/hardening-wordpress/)
- [WordPress Security Team](https://make.wordpress.org/core/handbook/security/)

### OWASP Resources
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)
- [OWASP Cheat Sheets](https://cheatsheetseries.owasp.org/)

### Security Tools
- [WordPress Security Plugins](https://wordpress.org/plugins/tags/security/)
- [Security Scanners](https://wordpress.org/plugins/tags/security-scanner/)
- [Vulnerability Databases](https://cve.mitre.org/)
