<?php

declare(strict_types=1);

namespace Simp\Pindrop\FileSystem;

/**
 * FileSystem Interface
 * 
 * Provides a unified interface for file operations using stream wrappers.
 * Supports various stream protocols like public://, global://, etc.
 */
interface FileSystemInterface
{
    /**
     * Write content to a file
     */
    public function write(string $uri, string $content, int $flags = 0): bool|int;

    /**
     * Read content from a file
     */
    public function read(string $uri): string|false;

    /**
     * Check if a file exists
     */
    public function exists(string $uri): bool;

    /**
     * Delete a file
     */
    public function delete(string $uri): bool;

    /**
     * Copy a file
     */
    public function copy(string $sourceUri, string $destinationUri): bool;

    /**
     * Move/rename a file
     */
    public function move(string $sourceUri, string $destinationUri): bool;

    /**
     * Get file size
     */
    public function size(string $uri): int|false;

    /**
     * Get file modification time
     */
    public function modified(string $uri): int|false;

    /**
     * Create a directory
     */
    public function mkdir(string $uri, int $mode = 0755, bool $recursive = true): bool;

    /**
     * Remove a directory
     */
    public function rmdir(string $uri, bool $recursive = false): bool;

    /**
     * List directory contents
     */
    public function listFiles(string $uri, bool $recursive = false): array;

    /**
     * Check if path is a directory
     */
    public function isDir(string $uri): bool;

    /**
     * Check if path is a file
     */
    public function isFile(string $uri): bool;

    /**
     * Get file mime type
     */
    public function mimeType(string $uri): string|false;

    /**
     * Get file extension
     */
    public function extension(string $uri): string;

    /**
     * Get real path from URI
     */
    public function realPath(string $uri): string|false;

    /**
     * Upload file from HTTP upload
     */
    public function uploadFile(array $file, string $destinationUri, array $options = []): array;

    /**
     * Validate file upload
     */
    public function validateUpload(array $file, array $allowedTypes = [], ?int $maxSize = null): array;

    /**
     * Get public URL for a file
     */
    public function getPublicUrl(string $uri): string;
}
