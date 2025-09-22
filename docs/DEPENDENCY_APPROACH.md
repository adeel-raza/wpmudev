# Dependency Management Approach

## Strategy

**Simple, reliable approach using standard Composer packages with namespace isolation.**

### 1. Namespace Isolation
- All plugin classes use `WPMUDEV\PluginTest` namespace
- Prevents class name conflicts with other plugins

### 2. Standard Composer Dependencies
- `google/apiclient: ^2.16` - Google Drive API client
- `firebase/php-jwt: ^6.10` - JWT token handling
- No complex scoping or build processes

### 3. Conflict Prevention
- Version constraints in `composer.json`
- Graceful dependency checking
- WordPress coding standards compliance

## Implementation

### Composer Configuration
```json
{
  "require": {
    "php": ">=7.4",
    "google/apiclient": "^2.16",
    "firebase/php-jwt": "^6.10"
  },
  "conflict": {
    "google/apiclient": "<2.15.0",
    "firebase/php-jwt": "<6.0.0"
  },
  "autoload": {
    "psr-4": {
      "WPMUDEV\\PluginTest\\": "app/"
    }
  }
}
```

### Setup
```bash
# Install dependencies
composer install

# Verify installation
php -r "require 'vendor/autoload.php'; echo class_exists('Google\\Client') ? 'OK' : 'Missing';"
```
