<?php

declare(strict_types=1);

namespace Simp\Pindrop\FileSystem;

/**
 * FileSystem Usage Examples
 * 
 * Demonstrates how to use the FileSystem service with the DI container.
 */

// Example 1: Using the service directly
function exampleDirectUsage()
{
    // Build container with all services
    $container = \Simp\Pindrop\Services\ServiceProvider::create();
    
    // Get FileSystem service
    $fileSystemService = $container->get('filesystem');
    $fileSystem = $fileSystemService->getFileSystem();
    
    // Write a file to public stream
    $fileSystem->write('public://example.txt', 'Hello from FileSystem!');
    
    // Read the file
    $content = $fileSystem->read('public://example.txt');
    echo $content; // Output: Hello from FileSystem!
    
    // Get public URL
    $url = $fileSystem->getPublicUrl('public://example.txt');
    echo $url; // Output: /files/example.txt
}

// Example 2: Using in a controller or form handler
function exampleInController()
{
    $container = \Simp\Pindrop\Services\ServiceProvider::create();
    
    // Get FileSystem interface
    $fileSystem = $container->get('filesystem.interface');
    
    // Handle file upload from form
    if (isset($_FILES['user_file'])) {
        $result = $fileSystem->uploadFile(
            $_FILES['user_file'],
            'public://uploads/documents/',
            [
                'allowed_types' => ['pdf', 'doc', 'docx'],
                'max_size' => 5 * 1024 * 1024, // 5MB
                'unique' => true
            ]
        );
        
        if ($result['success']) {
            $fileInfo = $result['data'][0];
            echo "File uploaded: " . $fileInfo['uri'];
            echo "Public URL: " . $fileSystem->getPublicUrl($fileInfo['uri']);
        }
    }
}

// Example 3: Using with form system
function exampleWithFormSystem()
{
    $container = \Simp\Pindrop\Services\ServiceProvider::create();
    $fileSystem = $container->get('filesystem.interface');
    
    // In your form submit handler
    $formState = new \Simp\Pindrop\Form\FormState();
    
    // Get uploaded files from form state
    $uploadedFiles = $formState->getFiles();
    
    foreach ($uploadedFiles as $fieldName => $file) {
        $result = $fileSystem->uploadFile(
            $file,
            'public://uploads/' . $fieldName . '/',
            [
                'allowed_types' => ['jpg', 'png', 'gif'],
                'max_size' => 2 * 1024 * 1024 // 2MB
            ]
        );
        
        if ($result['success']) {
            $formState->setMessage("File uploaded successfully: {$fieldName}");
        } else {
            $formState->setError("Upload failed: {$fieldName} - " . $result['message']);
        }
    }
}

// Example 4: Directory operations
function exampleDirectoryOperations()
{
    $container = \Simp\Pindrop\Services\ServiceProvider::create();
    $fileSystem = $container->get('filesystem.interface');
    
    // Create directory
    $fileSystem->mkdir('public://uploads/images', 0755, true);
    
    // List files recursively
    $files = $fileSystem->listFiles('public://uploads', true);
    
    foreach ($files as $file) {
        echo $file['name'] . ' (' . $file['type'] . ') - ' . $file['size'] . ' bytes\n';
    }
    
    // Check if path is directory
    if ($fileSystem->isDir('public://uploads')) {
        echo "Uploads directory exists";
    }
}

// Example 5: Stream wrapper information
function exampleStreamWrapperInfo()
{
    $container = \Simp\Pindrop\Services\ServiceProvider::create();
    $fileSystemService = $container->get('filesystem');
    
    // Get registered stream wrappers
    $registeredStreams = $fileSystemService->getRegisteredStreams();
    print_r($registeredStreams);
    
    // Check if public stream is registered
    if ($fileSystemService->isStreamRegistered('public')) {
        echo "Public stream is registered and ready to use";
    }
    
    // Get stream wrapper configuration
    $streamConfig = $container->get('filesystem.stream_wrappers');
    print_r($streamConfig);
}

// Example 6: File information and validation
function exampleFileInfo()
{
    $container = \Simp\Pindrop\Services\ServiceProvider::create();
    $fileSystem = $container->get('filesystem.interface');
    
    $filePath = 'public://documents/report.pdf';
    
    if ($fileSystem->exists($filePath)) {
        // Get file information
        $size = $fileSystem->size($filePath);
        $modified = $fileSystem->modified($filePath);
        $mimeType = $fileSystem->mimeType($filePath);
        $extension = $fileSystem->extension($filePath);
        $realPath = $fileSystem->realPath($filePath);
        
        echo "File: $filePath\n";
        echo "Size: $size bytes\n";
        echo "Modified: " . date('Y-m-d H:i:s', $modified) . "\n";
        echo "MIME Type: $mimeType\n";
        echo "Extension: $extension\n";
        echo "Real Path: $realPath\n";
    }
}

// Example 7: Service provider access
function exampleServiceProviderAccess()
{
    $serviceProvider = new \Simp\Pindrop\Services\ServiceProvider();
    $container = $serviceProvider->buildContainer();
    
    // Get specific service provider
    $fileSystemProvider = $serviceProvider->getFileSystemProvider();
    
    if ($fileSystemProvider) {
        echo "FileSystem service is registered";
        echo "Service name: " . $fileSystemProvider->getServiceName();
        echo "Interface service: " . $fileSystemProvider->getInterfaceServiceName();
    }
    
    // Check all available services
    $availableServices = $serviceProvider->getAvailableServices();
    echo "Available services: " . implode(', ', array_keys($availableServices));
}

// Example 8: Error handling and logging
function exampleErrorHandling()
{
    $container = \Simp\Pindrop\Services\ServiceProvider::create();
    $fileSystem = $container->get('filesystem.interface');
    
    try {
        // Try to read non-existent file
        $content = $fileSystem->read('public://nonexistent.txt');
        if ($content === false) {
            echo "File does not exist";
        }
        
        // Try to upload invalid file
        $invalidFile = [
            'name' => 'test.txt',
            'tmp_name' => '/tmp/nonexistent',
            'size' => 0,
            'error' => UPLOAD_ERR_NO_FILE
        ];
        
        $result = $fileSystem->uploadFile($invalidFile, 'public://uploads/');
        if (!$result['success']) {
            echo "Upload validation failed: " . $result['message'];
        }
        
    } catch (\Exception $e) {
        echo "FileSystem error: " . $e->getMessage();
    }
}
