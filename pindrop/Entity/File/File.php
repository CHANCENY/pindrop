<?php

declare(strict_types=1);

namespace Simp\Pindrop\Entity\File;

use Simp\Pindrop\Database\DatabaseService;
use Simp\Pindrop\Logger\LoggerInterface;
use Simp\Pindrop\Logger\NullLogger;

/**
 * File Entity
 * 
 * Represents a file record in the file_managed table.
 * Provides CRUD operations and file management functionality.
 */
class File
{
    private ?int $id = null;
    private ?string $uuid = null;
    private string $filename;
    private string $uri;
    private string $filemime;
    private int $filesize;
    private int $status = 1;
    private int $timestamp;
    private ?int $uid = null;
    private ?string $fieldName = null;
    private ?string $entityType = null;
    private ?int $entityId = null;
    private ?string $bundle = null;
    private string $langcode = 'en';
    private ?string $alt = null;
    private ?string $title = null;
    private ?string $description = null;
    private ?int $width = null;
    private ?int $height = null;
    private ?float $duration = null;
    private ?string $checksum = null;
    private ?array $metadata = null;
    private ?\DateTime $createdAt = null;
    private ?\DateTime $updatedAt = null;
    private ?\DateTime $deletedAt = null;

    private DatabaseService $database;
    private LoggerInterface $logger;

    // File status constants
    public const int STATUS_TEMPORARY = 0;
    public const int STATUS_PERMANENT = 1;

    /**
     * Constructor
     */
    public function __construct(
        array $data = [],
        ?DatabaseService $database = null,
        ?LoggerInterface $logger = null
    ) {
        $this->database = $database ?? $this->getDefaultDatabase();
        $this->logger = $logger ?? new NullLogger();
        
        $this->fromArray($data);
    }

    /**
     * Get default database service
     */
    private function getDefaultDatabase(): DatabaseService
    {
        // Try to get from container if available
        if (function_exists('getAppContainer')) {
            $container = getAppContainer();
            if ($container->has('database')) {
                return $container->get('database');
            }
        }
        
        // Fallback to create new instance
        return new DatabaseService($this->getDefaultDbConfig());
    }

    /**
     * Get default database configuration
     */
    private function getDefaultDbConfig(): array
    {
        return [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_DATABASE'] ?? 'pindrop',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
        ];
    }

    /**
     * Load file by ID
     */
    public static function load(int $id, ?DatabaseService $database = null): ?self
    {
        $instance = new self([], $database);
        return $instance->loadById($id);
    }

    /**
     * Load file by UUID
     */
    public static function loadByUuid(string $uuid, ?DatabaseService $database = null): ?self
    {
        $instance = new self([], $database);
        return $instance->loadByUuidInstance($uuid);
    }

    /**
     * Load file by URI
     */
    public static function loadByUri(string $uri, ?DatabaseService $database = null): ?self
    {
        $instance = new self([], $database);
        return $instance->loadByUriInstance($uri);
    }

    /**
     * Load file by ID
     */
    public function loadById(int $id): ?self
    {
  
        $data = $this->database->table('file_managed')->where('id', $id)->whereNull('deleted_at')->first();
        
        if ($data) {
            $this->fromArray($data);
            return $this;
        }
        
        return null;
    }

    /**
     * Load file by UUID (instance method)
     */
    public function loadByUuidInstance(string $uuid): ?self
    {
        $data = $this->database->table('file_managed')->where('uuid', '=', $uuid)->whereNull('deleted_at')->first();
        
        if ($data) {
            $this->fromArray($data);
            return $this;
        }
        
        return null;
    }

    /**
     * Load file by URI (instance method)
     */
    public function loadByUriInstance(string $uri): ?self
    {
        $data = $this->database->table('file_managed')->where('uri', '=', $uri)->whereNull('deleted_at')->first();
        
        if ($data) {
            $this->fromArray($data);
            return $this;
        }
        
        return null;
    }

    public static function count(mixed $database)
    {

        return $database->table('file_managed')->whereNull('deleted_at')->count();
    }

    /**
     * Save file to database
     */
    public function save(): bool
    {
        try {
            if ($this->id) {
                return $this->update();
            } else {
                return $this->insert();
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to save file', [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'filename' => $this->filename,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Insert new file record
     */
    private function insert(): bool
    {
        $this->uuid = $this->uuid ?? $this->generateUuid();
        $this->timestamp = $this->timestamp ?? time();
        $this->createdAt = $this->createdAt ?? new \DateTime();
        $this->updatedAt = new \DateTime();

        $data = [
            'uuid' => $this->uuid,
            'filename' => $this->filename,
            'uri' => $this->uri,
            'filemime' => $this->filemime,
            'filesize' => $this->filesize,
            'status' => $this->status,
            'timestamp' => $this->timestamp,
            'uid' => $this->uid,
            'field_name' => $this->fieldName,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'bundle' => $this->bundle,
            'langcode' => $this->langcode,
            'alt' => $this->alt,
            'title' => $this->title,
            'description' => $this->description,
            'width' => $this->width,
            'height' => $this->height,
            'duration' => $this->duration,
            'checksum' => $this->checksum,
            'metadata' => $this->metadata ? json_encode($this->metadata) : null,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s')
        ];

        $this->id = $this->database->table('file_managed')->insert($data);
        
        if ($this->id) {
            $this->logger->info('File created', [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'filename' => $this->filename
            ]);
            return true;
        }

        return false;
    }

    /**
     * Update existing file record
     */
    private function update(): bool
    {
        $this->updatedAt = new \DateTime();

        $data = [
            'filename' => $this->filename,
            'uri' => $this->uri,
            'filemime' => $this->filemime,
            'filesize' => $this->filesize,
            'status' => $this->status,
            'uid' => $this->uid,
            'field_name' => $this->fieldName,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'bundle' => $this->bundle,
            'langcode' => $this->langcode,
            'alt' => $this->alt,
            'title' => $this->title,
            'description' => $this->description,
            'width' => $this->width,
            'height' => $this->height,
            'duration' => $this->duration,
            'checksum' => $this->checksum,
            'metadata' => $this->metadata ? json_encode($this->metadata) : null,
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s')
        ];

        $result = $this->database->table('file_managed')->where('id', '=', $this->id)->update($data);

        if ($result) {
            $this->logger->info('File updated', [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'filename' => $this->filename
            ]);
            return true;
        }

        return false;
    }

    /**
     * Delete file (soft delete)
     */
    public function delete(): bool
    {
        if (!$this->id) {
            return false;
        }

        try {
            $this->deletedAt = new \DateTime();
            
            $data = ['deleted_at' => $this->deletedAt->format('Y-m-d H:i:s')];
            $result = $this->database->table('file_managed')->where('id', '=', $this->id)->update($data);

            if ($result) {
                $this->logger->info('File deleted', [
                    'id' => $this->id,
                    'uuid' => $this->uuid,
                    'filename' => $this->filename
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete file', [
                'id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Permanently delete file (hard delete)
     */
    public function permanentDelete(): bool
    {
        if (!$this->id) {
            return false;
        }

        try {
            // Check if DatabaseService has a delete method, otherwise use query
            $result = $this->database->table('file_managed')->where('id', '=', $this->id)->delete();
            if ($result) {
                $this->logger->info('File permanently deleted', [
                    'id' => $this->id,
                    'uuid' => $this->uuid,
                    'filename' => $this->filename
                ]);
                unlink($this->uri);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to permanently delete file', [
                'id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get files by user ID
     */
    public static function loadByUser(int $uid, ?DatabaseService $database = null): array
    {
        $instance = new self([], $database);
        return $instance->loadByUserId($uid);
    }

    /**
     * Get files by user ID
     */
    public function loadByUserId(int $uid): array
    {
   
        $files = $this->database->table('file_managed')->where('uid', '=', $uid)->whereNull('deleted_at')->orderBy('created_at', 'desc')->get();
        
        return array_map(function ($fileData) {
            return new self($fileData, $this->database, $this->logger);
        }, $files);
    }

    /**
     * Get files by entity
     */
    public static function loadByEntity(string $entityType, int $entityId, ?DatabaseService $database = null): array
    {
        $instance = new self([], $database);
        return $instance->loadByEntityTypeAndId($entityType, $entityId);
    }

    /**
     * Get files by entity
     */
    public function loadByEntityTypeAndId(string $entityType, int $entityId): array
    {
        $files = $this->database->table('file_managed')->where('entity_type', '=', $entityType)->where('entity_id', '=', $entityId)->whereNull('deleted_at')->orderBy('created_at', 'desc')->get();

        return array_map(function ($fileData) {
            return new self($fileData, $this->database, $this->logger);
        }, $files);
    }

    /**
     * Get public URL
     */
    public function getPublicUrl(): string
    {
        // Use the database function if available
        try {
            return $this->uri;
        } catch (\Exception $e) {
           return \getAppContainer()->get('filesystem')->getPublicUrl($this->uri);
        }
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSize(): string
    {
        try {
         
            return  $this->formatFileSize($this->filesize);
        } catch (\Exception $e) {
            return $this->formatFileSize($this->filesize);
        }
    }

    /**
     * Format file size (fallback)
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        } elseif ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        } else {
            return round($bytes / 1073741824, 2) . ' GB';
        }
    }

    /**
     * Generate UUID
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Convert entity to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'filename' => $this->filename,
            'uri' => $this->uri,
            'filemime' => $this->filemime,
            'filesize' => $this->filesize,
            'status' => $this->status,
            'timestamp' => $this->timestamp,
            'uid' => $this->uid,
            'field_name' => $this->fieldName,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'bundle' => $this->bundle,
            'langcode' => $this->langcode,
            'alt' => $this->alt,
            'title' => $this->title,
            'description' => $this->description,
            'width' => $this->width,
            'height' => $this->height,
            'duration' => $this->duration,
            'checksum' => $this->checksum,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s'),
            'public_url' => $this->getPublicUrl(),
            'formatted_size' => $this->getFormattedSize()
        ];
    }

    /**
     * Populate entity from array
     */
    public function fromArray(array $data): void
    {
        // Handle metadata separately to avoid type issues
        $metadata = null;
        if (isset($data['metadata'])) {
            if (is_string($data['metadata'])) {
                $metadata = json_decode($data['metadata'], true);
            } elseif (is_array($data['metadata'])) {
                $metadata = $data['metadata'];
            }
            unset($data['metadata']);
        }
        
        // Handle datetime fields separately to avoid type issues
        $createdAt = null;
        $updatedAt = null;
        $deletedAt = null;
        
        if (isset($data['created_at'])) {
            if (is_string($data['created_at'])) {
                $createdAt = new \DateTime($data['created_at']);
            } elseif ($data['created_at'] instanceof \DateTime) {
                $createdAt = $data['created_at'];
            }
            unset($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            if (is_string($data['updated_at'])) {
                $updatedAt = new \DateTime($data['updated_at']);
            } elseif ($data['updated_at'] instanceof \DateTime) {
                $updatedAt = $data['updated_at'];
            }
            unset($data['updated_at']);
        }
        
        if (isset($data['deleted_at'])) {
            if (is_string($data['deleted_at'])) {
                $deletedAt = new \DateTime($data['deleted_at']);
            } elseif ($data['deleted_at'] instanceof \DateTime) {
                $deletedAt = $data['deleted_at'];
            }
            unset($data['deleted_at']);
        }
        
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            } elseif (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        
        // Set datetime fields after processing to avoid type issues
        if ($createdAt !== null) {
            $this->createdAt = $createdAt;
        }
        if ($updatedAt !== null) {
            $this->updatedAt = $updatedAt;
        }
        if ($deletedAt !== null) {
            $this->deletedAt = $deletedAt;
        }
        
        // Set metadata after processing to avoid type issues
        if ($metadata !== null) {
            $this->metadata = $metadata;
        }
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getUuid(): ?string { return $this->uuid; }
    public function getFilename(): string { return $this->filename; }
    public function getUri(): string { return $this->uri; }
    public function getFilemime(): string { return $this->filemime; }
    public function getFilesize(): int { return $this->filesize; }
    public function getStatus(): int { return $this->status; }
    public function getTimestamp(): int { return $this->timestamp; }
    public function getUid(): ?int { return $this->uid; }
    public function getFieldName(): ?string { return $this->fieldName; }
    public function getEntityType(): ?string { return $this->entityType; }
    public function getEntityId(): ?int { return $this->entityId; }
    public function getBundle(): ?string { return $this->bundle; }
    public function getLangcode(): string { return $this->langcode; }
    public function getAlt(): ?string { return $this->alt; }
    public function getTitle(): ?string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getWidth(): ?int { return $this->width; }
    public function getHeight(): ?int { return $this->height; }
    public function getDuration(): ?float { return $this->duration; }
    public function getChecksum(): ?string { return $this->checksum; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }
    public function getDeletedAt(): ?\DateTime { return $this->deletedAt; }

    public function setUuid(?string $uuid): void { $this->uuid = $uuid; }
    public function setFilename(string $filename): void { $this->filename = $filename; }
    public function setUri(string $uri): void { $this->uri = $uri; }
    public function setFilemime(string $filemime): void { $this->filemime = $filemime; }
    public function setFilesize(int $filesize): void { $this->filesize = $filesize; }
    public function setStatus(int $status): void { $this->status = $status; }
    public function setTimestamp(int $timestamp): void { $this->timestamp = $timestamp; }
    public function setUid(?int $uid): void { $this->uid = $uid; }
    public function setFieldName(?string $fieldName): void { $this->fieldName = $fieldName; }
    public function setEntityType(?string $entityType): void { $this->entityType = $entityType; }
    public function setEntityId(?int $entityId): void { $this->entityId = $entityId; }
    public function setBundle(?string $bundle): void { $this->bundle = $bundle; }
    public function setLangcode(string $langcode): void { $this->langcode = $langcode; }
    public function setAlt(?string $alt): void { $this->alt = $alt; }
    public function setTitle(?string $title): void { $this->title = $title; }
    public function setDescription(?string $description): void { $this->description = $description; }
    public function setWidth(?int $width): void { $this->width = $width; }
    public function setHeight(?int $height): void { $this->height = $height; }
    public function setDuration(?float $duration): void { $this->duration = $duration; }
    public function setChecksum(?string $checksum): void { $this->checksum = $checksum; }
    public function setMetadata(?array $metadata): void { $this->metadata = $metadata; }
    public function setCreatedAt(?\DateTime $createdAt): void { $this->createdAt = $createdAt; }
    public function setUpdatedAt(?\DateTime $updatedAt): void { $this->updatedAt = $updatedAt; }
    public function setDeletedAt(?\DateTime $deletedAt): void { $this->deletedAt = $deletedAt; }

    /**
     * Check if file is permanent
     */
    public function isPermanent(): bool
    {
        return $this->status === self::STATUS_PERMANENT;
    }

    /**
     * Check if file is temporary
     */
    public function isTemporary(): bool
    {
        return $this->status === self::STATUS_TEMPORARY;
    }

    /**
     * Check if file is deleted
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Make file permanent
     */
    public function makePermanent(): bool
    {
        $this->status = self::STATUS_PERMANENT;
        return $this->save();
    }

    /**
     * Make file temporary
     */
    public function makeTemporary(): bool
    {
        $this->status = self::STATUS_TEMPORARY;
        return $this->save();
    }
}