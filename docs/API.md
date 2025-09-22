# API Documentation

## REST API Endpoints

### Google Drive Endpoints

#### Save Credentials
**POST** `/wp-json/wpmudev/v1/drive/save-credentials`

Saves encrypted Google Drive credentials.

**Parameters:**
- `client_id` (string, required) - Google OAuth Client ID
- `client_secret` (string, required) - Google OAuth Client Secret

**Response:**
```json
{
  "success": true,
  "message": "Credentials saved successfully"
}
```

**Security:**
- Requires `manage_options` capability
- Nonce verification required
- Credentials encrypted using AES-256

#### Authenticate
**POST** `/wp-json/wpmudev/v1/drive/authenticate`

Initiates OAuth 2.0 authentication flow.

**Response:**
```json
{
  "success": true,
  "data": {
    "auth_url": "https://accounts.google.com/oauth/authorize?..."
  }
}
```

#### OAuth Callback
**GET** `/wp-json/wpmudev/v1/drive/callback`

Handles OAuth callback and exchanges authorization code for access token.

**Parameters:**
- `code` (string, required) - Authorization code from Google
- `state` (string, required) - State parameter for security

**Response:**
```json
{
  "success": true,
  "message": "Authentication successful"
}
```

#### List Files
**GET** `/wp-json/wpmudev/v1/drive/files`

Lists Google Drive files with pagination.

**Parameters:**
- `page_token` (string, optional) - Token for pagination
- `per_page` (integer, optional) - Number of files per page (default: 10)

**Response:**
```json
{
  "success": true,
  "data": {
    "files": [
      {
        "id": "file_id",
        "name": "filename.pdf",
        "mimeType": "application/pdf",
        "size": "1024",
        "createdTime": "2023-01-01T00:00:00.000Z",
        "webViewLink": "https://drive.google.com/file/d/...",
        "webContentLink": "https://drive.google.com/uc?export=download&id=..."
      }
    ],
    "nextPageToken": "next_page_token"
  }
}
```

#### Upload File
**POST** `/wp-json/wpmudev/v1/drive/upload`

Uploads file to Google Drive.

**Parameters:**
- `file` (file, required) - File to upload (multipart)
- `folder_id` (string, optional) - Parent folder ID

**Response:**
```json
{
  "success": true,
  "data": {
    "file_id": "uploaded_file_id",
    "name": "filename.pdf",
    "webViewLink": "https://drive.google.com/file/d/..."
  }
}
```

#### Create Folder
**POST** `/wp-json/wpmudev/v1/drive/create-folder`

Creates new folder in Google Drive.

**Parameters:**
- `name` (string, required) - Folder name
- `parent_id` (string, optional) - Parent folder ID

**Response:**
```json
{
  "success": true,
  "data": {
    "folder_id": "created_folder_id",
    "name": "folder_name",
    "webViewLink": "https://drive.google.com/drive/folders/..."
  }
}
```

### Posts Maintenance Endpoints

#### Start Scan
**POST** `/wp-admin/admin-ajax.php?action=wpmudev_scan_posts`

Initiates posts scan process.

**Parameters:**
- `post_types[]` (array, required) - Array of post types to scan
- `batch_size` (integer, required) - Number of posts per batch (1-100)
- `nonce` (string, required) - Security nonce

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Scan started successfully"
  }
}
```

#### Get Scan Progress
**POST** `/wp-admin/admin-ajax.php?action=wpmudev_get_scan_progress`

Gets current scan progress.

**Parameters:**
- `nonce` (string, required) - Security nonce

**Response:**
```json
{
  "success": true,
  "data": {
    "progress": {
      "processed": 25,
      "total": 100,
      "current_batch": 30,
      "post_types": ["post", "page"]
    },
    "status": "running"
  }
}
```

#### Reset Scan Status
**POST** `/wp-admin/admin-ajax.php?action=wpmudev_reset_scan_status`

Resets scan status and clears all scan data.

**Parameters:**
- `nonce` (string, required) - Security nonce

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Scan status reset successfully"
  }
}
```

#### Clear Notification
**POST** `/wp-admin/admin-ajax.php?action=wpmudev_clear_notification`

Clears user notifications.

**Parameters:**
- `nonce` (string, required) - Security nonce

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Notification cleared successfully"
  }
}
```

## Database Schema

### Options Table

#### Google Drive Options
- `wpmudev_drive_credentials` - Encrypted Google Drive credentials
- `wpmudev_drive_tokens` - OAuth access and refresh tokens
- `wpmudev_drive_page_token` - Pagination token for file listing

#### Posts Maintenance Options
- `wpmudev_scan_status` - Current scan status (idle/running/completed)
- `wpmudev_scan_progress` - Scan progress data
- `wpmudev_scan_start_time` - Scan start timestamp
- `wpmudev_scan_post_types` - Post types being scanned
- `wpmudev_scan_batch_size` - Batch size for current scan
- `wpmudev_scan_notification` - User notifications
- `wpmudev_last_scan_time` - Last completed scan timestamp

### Post Meta Table

#### Posts Maintenance Meta
- `wpmudev_test_last_scan` - Timestamp of last scan for each post

## Error Handling

### Common Error Responses

#### Authentication Errors
```json
{
  "success": false,
  "message": "Authentication failed",
  "error_code": "auth_failed"
}
```

#### Permission Errors
```json
{
  "success": false,
  "message": "Insufficient permissions",
  "error_code": "permission_denied"
}
```

#### Validation Errors
```json
{
  "success": false,
  "message": "Invalid input parameters",
  "error_code": "validation_failed",
  "errors": {
    "batch_size": "Must be between 1 and 100"
  }
}
```

#### API Errors
```json
{
  "success": false,
  "message": "Google Drive API error",
  "error_code": "api_error",
  "details": "Rate limit exceeded"
}
```

## Rate Limiting

### Google Drive API
- **Queries per 100 seconds per user**: 1,000
- **Queries per 100 seconds**: 10,000
- **Files per 100 seconds**: 1,000

### WordPress AJAX
- **Posts scan**: No rate limiting (uses WordPress cron)
- **Progress checks**: 3-second intervals maximum
- **File uploads**: Limited by server upload limits

## Security Considerations

### Authentication
- All endpoints require proper authentication
- OAuth 2.0 for Google Drive API
- WordPress nonce verification for AJAX requests

### Data Protection
- Credentials encrypted using AES-256
- HMAC verification for data integrity
- Secure key generation using WordPress salts

### Input Validation
- All inputs sanitized and validated
- File type and size restrictions
- SQL injection prevention

### Output Escaping
- All outputs properly escaped
- XSS prevention
- Safe HTML rendering
