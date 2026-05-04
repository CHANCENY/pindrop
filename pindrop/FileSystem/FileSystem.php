<?php

declare(strict_types=1);

namespace Simp\Pindrop\FileSystem;

use Simp\Pindrop\Logger\LoggerInterface;
use Simp\StreamWrapper\WrapperRegister\WrapperRegister;


/**
 * FileSystem Service
 * 
 * Provides unified file operations using stream wrappers.
 * Integrates with simp/streamwrapper for protocol support.
 */
class FileSystem implements FileSystemInterface
{
    private array $config;
    private ?LoggerInterface $logger;
    private array $registeredWrappers;

    public function __construct(array $config = [], ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->registeredWrappers = [];
        
        $this->initializeStreamWrappers();
    }

    /**
     * Initialize stream wrappers based on configuration
     */
    private function initializeStreamWrappers(): void
    {
        // Register pindrop public stream wrapper
        $this->registerPindropPublicWrapper();
    }

    /**
     * Register pindrop public stream wrapper
     */
    private function registerPindropPublicWrapper(): void
    {
        $protocol = str_replace('://', '', $this->config['public_stream'] ?? 'public://');
        $wrapperClass = ConfigurableStreamWrapper::class;
        
        // Create wrapper register instance to avoid the static method bug
        $wrapperRegister = new \Simp\StreamWrapper\WrapperRegister\WrapperRegister();
        
        // Register the wrapper
        if (!$wrapperRegister->isWrapperRegistered($protocol)) {
            $wrapperRegister->addWrapper($protocol, $wrapperClass);
            $this->registeredWrappers[$protocol] = $wrapperClass;
            
            if ($this->logger) {
                $this->logger->info("Registered pindrop stream wrapper: {$protocol}");
            }
        }
    }

    public function write(string $uri, string $content, int $flags = 0): bool|int
    {
        try {
            $this->ensureDirectoryExists(dirname($uri));
            
            $result = file_put_contents($uri, $content, $flags);
            
            if ($this->logger) {
                $this->logger->debug("File written: {$uri}", ['bytes' => strlen($content)]);
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to write file: {$uri}", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    public function read(string $uri): string|false
    {
        try {
            if (!$this->exists($uri)) {
                if ($this->logger) {
                    $this->logger->warning("File not found: {$uri}");
                }
                return false;
            }
            
            $content = file_get_contents($uri);
            
            if ($this->logger) {
                $this->logger->debug("File read: {$uri}", ['bytes' => strlen($content)]);
            }
            
            return $content;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to read file: {$uri}", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    public function exists(string $uri): bool
    {
        return file_exists($uri);
    }

    public function delete(string $uri): bool
    {
        try {
            if (!$this->exists($uri)) {
                return true;
            }
            
            $result = unlink($uri);
            
            if ($this->logger) {
                $this->logger->debug("File deleted: {$uri}", ['success' => $result]);
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to delete file: {$uri}", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    public function copy(string $sourceUri, string $destinationUri): bool
    {
        try {
            $this->ensureDirectoryExists(dirname($destinationUri));
            
            $result = copy($sourceUri, $destinationUri);
            
            if ($this->logger) {
                $this->logger->debug("File copied: {$sourceUri} -> {$destinationUri}", ['success' => $result]);
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to copy file: {$sourceUri} -> {$destinationUri}", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    public function move(string $sourceUri, string $destinationUri): bool
    {
        try {
            $this->ensureDirectoryExists(dirname($destinationUri));
            
            $result = rename($sourceUri, $destinationUri);
            
            if ($this->logger) {
                $this->logger->debug("File moved: {$sourceUri} -> {$destinationUri}", ['success' => $result]);
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to move file: {$sourceUri} -> {$destinationUri}", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    public function size(string $uri): int|false
    {
        return filesize($uri);
    }

    public function modified(string $uri): int|false
    {
        return filemtime($uri);
    }

    public function mkdir(string $uri, int $mode = 0755, bool $recursive = true): bool
    {
        try {
            $result = mkdir($uri, $mode, $recursive);
            
            if ($this->logger) {
                $this->logger->debug("Directory created: {$uri}", ['success' => $result]);
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to create directory: {$uri}", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    public function rmdir(string $uri, bool $recursive = false): bool
    {
        try {
            if (!$recursive) {
                $result = rmdir($uri);
            } else {
                $result = $this->removeDirectoryRecursive($uri);
            }
            
            if ($this->logger) {
                $this->logger->debug("Directory removed: {$uri}", ['recursive' => $recursive, 'success' => $result]);
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to remove directory: {$uri}", ['error' => $e->getMessage()]);
            }
            return false;
        }
    }

    public function listFiles(string $uri, bool $recursive = false): array
    {
        try {
            if (!$this->isDir($uri)) {
                return [];
            }
            
            $files = [];
            $iterator = $recursive ? 
                new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uri)) :
                new \DirectoryIterator($uri);
            
            foreach ($iterator as $file) {
                if ($file->isDot()) {
                    continue;
                }
                
                $files[] = [
                    'uri' => $file->getPathname(),
                    'name' => $file->getFilename(),
                    'type' => $file->isDir() ? 'directory' : 'file',
                    'size' => $file->isFile() ? $file->getSize() : 0,
                    'modified' => $file->getMTime(),
                    'extension' => $file->isFile() ? $file->getExtension() : ''
                ];
            }
            
            return $files;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to list files: {$uri}", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }

    public function isDir(string $uri): bool
    {
        return is_dir($uri);
    }

    public function isFile(string $uri): bool
    {
        return is_file($uri);
    }

    public function mimeType(string $uri): string|false
    {
        if (!function_exists('finfo_file')) {
            return false;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $uri);
        finfo_close($finfo);
        
        return $mimeType;
    }

    public function extension(string $uri): string
    {
        return pathinfo($uri, PATHINFO_EXTENSION);
    }

    public function realPath(string $uri): string|false
    {
        return realpath($uri);
    }

    public function uploadFile(array $file, string $destinationUri, array $options = []): array
    {
        try {
            // Validate upload
            $validation = $this->validateUpload($file, $options['allowed_types'] ?? [], $options['max_size'] ?? null);
            
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                    'errors' => $validation['errors']
                ];
            }
            
            // Ensure directory exists
            $this->ensureDirectoryExists(dirname($destinationUri));
            
            // Generate unique filename if needed
            if ($options['unique'] ?? false) {
                $extension = $this->extension($file['name']);
                $basename = pathinfo($file['name'], PATHINFO_FILENAME);
                $counter = 1;
                $originalUri = $destinationUri;
                
                while ($this->exists($destinationUri)) {
                    $destinationUri = dirname($originalUri) . '/' . $basename . '_' . $counter . '.' . $extension;
                    $counter++;
                }
            }
            
            // Move uploaded file
            $result = move_uploaded_file($file['tmp_name'], $destinationUri);
            
            if ($result) {
                $fileInfo = [
                    'name' => basename($destinationUri),
                    'original_name' => $file['name'],
                    'uri' => $destinationUri,
                    'size' => $file['size'],
                    'mime_type' => $this->mimeType($destinationUri),
                    'extension' => $this->extension($destinationUri)
                ];
                
                if ($this->logger) {
                    $this->logger->info("File uploaded successfully: {$destinationUri}", $fileInfo);
                }
                
                return [
                    'success' => true,
                    'data' => [$fileInfo],
                    'message' => 'File uploaded successfully'
                ];
            } else {
                throw new \Exception('Failed to move uploaded file');
            }
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("File upload failed: {$destinationUri}", ['error' => $e->getMessage()]);
            }
            
            return [
                'success' => false,
                'message' => 'File upload failed: ' . $e->getMessage()
            ];
        }
    }

    public function validateUpload(array $file, array $allowedTypes = [], ?int $maxSize = null): array
    {
        $errors = [];
        
        // Check upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($file['error']);
            return [
                'valid' => false,
                'message' => 'Upload error',
                'errors' => $errors
            ];
        }
        
        // Check file size
        if ($maxSize && $file['size'] > $maxSize) {
            $errors[] = "File size exceeds maximum allowed size of " . $this->formatBytes($maxSize);
        }
        
        // Check file type
        if (!empty($allowedTypes)) {
            $extension = strtolower($this->extension($file['name']));
            if (!in_array($extension, $allowedTypes) && !in_array('.' . $extension, $allowedTypes)) {
                $errors[] = "File type '{$extension}' is not allowed. Allowed types: " . implode(', ', $allowedTypes);
            }
        }
        
        return [
            'valid' => empty($errors),
            'message' => empty($errors) ? 'File is valid' : 'Validation failed',
            'errors' => $errors
        ];
    }

    public function getPublicUrl(string $uri): string
    {
        /**@var ConfigurableStreamWrapper $publicWrapper **/
        $publicWrapper = getAppContainer()->get('filesystem.public_stream');

        // Convert public:// URI to web-accessible URL
        if (str_starts_with($uri, "public://")) {
            return str_replace("public://", $_ENV['PUBLIC_WEB_FILE_ROOT'], $uri);
        }
        
        // For other protocols, return as-is or handle accordingly
        return $uri;
    }

    /**
     * Ensure directory exists
     */
    private function ensureDirectoryExists(string $uri): void
    {
        if (!$this->exists($uri)) {
            $this->mkdir($uri, 0755, true);
        }
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectoryRecursive(string $uri): bool
    {
        if (!is_dir($uri)) {
            return false;
        }
        
        $files = array_diff(scandir($uri), ['.', '..']);
        foreach ($files as $file) {
            $path = $uri . '/' . $file;
            is_dir($path) ? $this->removeDirectoryRecursive($path) : unlink($path);
        }
        
        return rmdir($uri);
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            default => 'Unknown upload error'
        };
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function resolvedRealPath(string $uri)
    {
        /**@var ConfigurableStreamWrapper $publicWrapper **/
        $publicWrapper = getAppContainer()->get('filesystem.public_stream');

        if (str_starts_with($uri, 'public://')) {
            return str_replace('public://', $publicWrapper->getbase_path().DIRECTORY_SEPARATOR, $uri);
        }
        return $uri;
    }
}
