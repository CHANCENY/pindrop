# Pindrop FileSystem Service

The FileSystem service provides unified file operations using stream wrappers, integrating with the `simp/streamwrapper` library for protocol support.

## Configuration

### Environment Variables

```env
# Stream configuration
PUBLIC_STREAM="public://"
PUBLIC_STREAM_DIR="[ROOT]/sites/default/files"
```

### Service Registration

```php
// In your service container or bootstrap
use Simp\Pindrop\FileSystem\FileSystemService;

// Create from environment
$fileSystemService = FileSystemService::createFromEnv($logger);

// Or create with custom config
$fileSystemService = new FileSystemService([
    'public_stream' => 'public://',
    'public_stream_dir' => '/var/www/html/files'
], $logger);
```

## Usage Examples

### Basic File Operations

```php
$fileSystem = $fileSystemService->getFileSystem();

// Write to public stream
$fileSystem->write('public://example.txt', 'Hello World!');

// Read from public stream
$content = $fileSystem->read('public://example.txt');

// Check if file exists
if ($fileSystem->exists('public://example.txt')) {
    echo "File exists!";
}

// Delete file
$fileSystem->delete('public://example.txt');
```

### Directory Operations

```php
// Create directory
$fileSystem->mkdir('public://uploads/images', 0755, true);

// List files
$files = $fileSystem->listFiles('public://uploads', true);
foreach ($files as $file) {
    echo $file['name'] . ' (' . $file['type'] . ")\n";
}

// Remove directory
$fileSystem->rmdir('public://uploads', true);
```

### File Uploads

```php
// Handle HTTP file upload
$uploadResult = $fileSystem->uploadFile(
    $_FILES['user_file'],
    'public://uploads/documents/',
    [
        'allowed_types' => ['pdf', 'doc', 'docx'],
        'max_size' => 5 * 1024 * 1024, // 5MB
        'unique' => true
    ]
);

if ($uploadResult['success']) {
    $fileInfo = $uploadResult['data'][0];
    echo "File uploaded: " . $fileInfo['uri'];
    echo "Public URL: " . $fileSystem->getPublicUrl($fileInfo['uri']);
} else {
    echo "Upload failed: " . $uploadResult['message'];
}
```

### File Information

```php
// Get file info
if ($fileSystem->exists('public://document.pdf')) {
    $size = $fileSystem->size('public://document.pdf');
    $modified = $fileSystem->modified('public://document.pdf');
    $mimeType = $fileSystem->mimeType('public://document.pdf');
    $extension = $fileSystem->extension('public://document.pdf');
    
    echo "Size: {$size} bytes\n";
    echo "Modified: " . date('Y-m-d H:i:s', $modified) . "\n";
    echo "MIME Type: {$mimeType}\n";
    echo "Extension: {$extension}\n";
}
```

### Stream Wrapper Management

```php
// Check registered streams
$registeredStreams = $fileSystemService->getRegisteredStreams();
print_r($registeredStreams);

// Check if specific stream is registered
if ($fileSystemService->isStreamRegistered('public')) {
    echo "Public stream is registered!";
}
```

## Integration with Form System

The FileSystem service integrates seamlessly with the pindrop form system for file uploads:

```php
// In your form submit handler
public function submitForm(array $form, FormStateInterface $formState)
{
    $fileSystem = $this->fileSystemService->getFileSystem();
    
    // Handle uploaded files
    $uploadedFiles = $formState->getFiles();
    foreach ($uploadedFiles as $fieldName => $file) {
        $result = $fileSystem->uploadFile(
            $file,
            'public://uploads/' . $fieldName . '/',
            [
                'allowed_types' => ['jpg', 'png', 'gif'],
                'max_size' => 2 * 1024 * 1024
            ]
        );
        
        if ($result['success']) {
            $formState->setMessage("File uploaded successfully: {$fieldName}");
        } else {
            $formState->setError("Upload failed: {$fieldName} - " . $result['message']);
        }
    }
}
```

## Stream Protocol Support

### Public Stream (public://)

Maps to the configured public files directory, typically used for web-accessible files:

```php
// Write to public files directory
$fileSystem->write('public://images/logo.png', $imageData);

// Get public URL
$url = $fileSystem->getPublicUrl('public://images/logo.png');
// Returns: /files/images/logo.png
```

### Custom Stream Wrappers

You can register additional stream wrappers:

```php
// Register custom wrapper
\Simp\StreamWrapper\WrapperRegister\WrapperRegister::register(
    'private',
    'App\\FileSystem\\PrivateWrapper'
);

// Use custom wrapper
$fileSystem->write('private://config/secret.txt', $secretData);
```

## Error Handling

The FileSystem service provides comprehensive error handling and logging:

```php
try {
    $result = $fileSystem->write('public://data.txt', $content);
    if ($result === false) {
        echo "Write operation failed";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
```

All operations are logged when a logger is provided, making debugging and monitoring easier.

## Security Considerations

- File uploads are validated against allowed types and size limits
- Directory traversal is prevented by stream wrapper path translation
- Public URLs are properly sanitized
- File permissions are set appropriately for web access

## Performance

- Stream wrappers provide efficient file operations
- Directory listing supports recursive iteration
- File information is cached where possible
- Large file uploads are handled efficiently
