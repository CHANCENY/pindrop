<?php

namespace Simp\Pindrop\Content\Storage;

use DateTime;
use DI\DependencyException;
use DI\NotFoundException;
use Simp\Pindrop\Database\DatabaseException;
use Simp\Pindrop\Database\DatabaseService;
use Simp\Pindrop\Entity\User\User;
use Simp\Pindrop\Logger\LoggerInterface;

abstract class StorageEntity implements ContentEntityInterface
{
    protected ?int $id = null;
    protected ?string $uuid = null;
    protected ?string $title = null;
    protected ?string $slug = null;
    protected ?string $content = null;
    protected ?string $excerpt = null;
    protected ?User $author = null;
    protected ?string $nodeType = null;
    protected ?string $status = null;
    protected ?bool $isPublished = false;
    protected ?bool $featured = false;
    protected ?bool $sticky = false;
    protected ?bool $allowComments = true;
    protected ?string $password = null;
    protected ?string $template = null;
    protected ?string $language = 'en';
    protected ?int $parentId = null;
    protected ?int $order = 0;
    protected ?int $menuOrder = 0;
    protected ?string $metaTitle = null;
    protected ?string $metaDescription = null;
    protected ?string $metaKeywords = null;
    protected ?string $canonicalUrl = null;
    protected ?string $redirectUrl = null;
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;
    protected ?DateTime $publishedAt = null;
    protected ?DateTime $deletedAt = null;

    // Dynamic field storage
    protected array $dynamicValues = [];

    public function __construct(protected DatabaseService $database, protected LoggerInterface $logger)
    {
        $this->persistStorageDefinition();
        
        // Set node type based on class name
        $className = static::class;
        $this->nodeType = strtolower(substr($className, strrpos($className, '\\') + 1));
    }

    public function id(): int
    {
        return $this->id ?? 0;
    }

    public function getId()
    {
        return $this->id();
    }

    public function getTitle(): string
    {
        return $this->title ?? '';
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): string
    {
        return $this->content ?? '';
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getAuthor(): User
    {
        return $this->author ?? new User([], $this->database, $this->logger);
    }

    public function setAuthor(User $author): void
    {
        $this->author = $author;
    }

    public function setAuthorId(int $authorId): void
    {
        // Load user if needed, or just store the ID for save time
        $this->author = User::loadById($authorId, $this->database);
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt ?? new DateTime();
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt ?? new DateTime();
    }

    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getPublishedAt(): DateTime
    {
        return $this->publishedAt ?? new DateTime();
    }

    public function setPublishedAt(DateTime $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function isPublished(): bool
    {
        return $this->isPublished ?? false;
    }

    public function setPublished(bool $published): void
    {
        $this->isPublished = $published;
        $this->status = $published ? 'published' : 'draft';
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->isPublished = ($status === 'published');
    }

    public function getStatus(): string
    {
        return $this->status ?? 'draft';
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function getNodeType(): ?string
    {
        return $this->nodeType;
    }

    public function getIsPublished(): ?bool
    {
        return $this->isPublished;
    }

    public function getFeatured(): ?bool
    {
        return $this->featured;
    }

    public function getSticky(): ?bool
    {
        return $this->sticky;
    }

    public function getAllowComments(): ?bool
    {
        return $this->allowComments;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function getMenuOrder(): ?int
    {
        return $this->menuOrder;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function getMetaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    public function getDeletedAt(): ?DateTime
    {
        return $this->deletedAt;
    }

    public function getDynamicValues(): array
    {
        return $this->dynamicValues;
    }

    public function get(string $key)
    {
        return $this->dynamicValues[$key] ?? null;
    }

    public function set(string $key, $value): void
    {
        $this->dynamicValues[$key] = $value;
    }

    public function unset(string $key): void
    {
        unset($this->dynamicValues[$key]);
    }

    public function getValue(string $key)
    {
        return $this->get($key);
    }

    public function setValue(string $key, $value): void
    {
        $this->set($key, $value);
    }

    public function getValues(): array
    {
        return $this->dynamicValues;
    }

    public function setValues(array $values): void
    {
        $this->dynamicValues = $values;
    }

    public function save(): bool
    {
        try {
            $this->database->beginTransaction();

            // Save core data to node_data table
            $nodeId = $this->saveNodeData();
            
            // Set the ID on the entity if it's a new record
            if (!$this->id) {
                $this->id = $nodeId;
            }

            // Save dynamic fields to specific content table
            $this->saveDynamicFields($nodeId);

            $this->database->commit();
            return true;
        } catch (\Exception $e) {
            dd($e);
            $this->database->rollBack();
            $this->logger->error('Failed to save content entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * @throws DatabaseException
     */
    protected function saveNodeData(): int
    {
        $now = new DateTime();
        
        if ($this->id) {
            // Update existing record
            $sql = "UPDATE node_data SET 
                title = :title, slug = :slug, content = :content, excerpt = :excerpt, 
                node_type = :node_type, status = :status, is_published = :is_published, 
                featured = :featured, sticky = :sticky, allow_comments = :allow_comments, 
                password = :password, template = :template, language = :language, 
                parent_id = :parent_id, `order` = :order, menu_order = :menu_order, 
                meta_title = :meta_title, meta_description = :meta_description, meta_keywords = :meta_keywords, 
                canonical_url = :canonical_url, redirect_url = :redirect_url, updated_at = :updated_at, 
                published_at = :published_at, deleted_at = :deleted_at
                WHERE id = :id";
            
            $values = [
                'title' => $this->title,
                'slug' => $this->slug,
                'content' => $this->content,
                'excerpt' => $this->excerpt,
                'node_type' => $this->nodeType,
                'status' => $this->status,
                'is_published' => (int) $this->isPublished,
                'featured' => (int) $this->featured,
                'sticky' => (int) $this->sticky,
                'allow_comments' => (int) $this->allowComments,
                'password' => $this->password,
                'template' => $this->template,
                'language' => $this->language,
                'parent_id' => $this->parentId,
                'order' => $this->order,
                'menu_order' => $this->menuOrder,
                'meta_title' => $this->metaTitle,
                'meta_description' => $this->metaDescription,
                'meta_keywords' => $this->metaKeywords,
                'canonical_url' => $this->canonicalUrl,
                'redirect_url' => $this->redirectUrl,
                'updated_at' => $now->format("Y-m-d H:i:s"),
                'published_at' => $this->publishedAt?->format("Y-m-d H:i:s") ?? null,
                'deleted_at' => $this->deletedAt?->format("Y-m-d H:i:s") ?? null,
                'id' => $this->id
            ];
            
            $this->database->query($sql, ...$values);
            
            return $this->id;
        } else {
            // Insert new record
            $sql = "INSERT INTO node_data (
                uuid, title, slug, content, excerpt, author_id,
                node_type, status, is_published, featured, sticky,
                allow_comments, password, template, language,
                parent_id, `order`, menu_order, meta_title,
                meta_description, meta_keywords, canonical_url,
                redirect_url, created_at, updated_at, published_at
            ) VALUES (
                :uuid, :title, :slug, :content, :excerpt, :author_id,
                :node_type, :status, :is_published, :featured, :sticky,
                :allow_comments, :password, :template, :language,
                :parent_id, :order, :menu_order, :meta_title,
                :meta_description, :meta_keywords, :canonical_url,
                :redirect_url, :created_at, :updated_at, :published_at
            )";

            $values = [
                'uuid' => $this->uuid ?? $this->generateUuid(),
                'title' => $this->title ?? '',
                'slug' => $this->slug,
                'content' => $this->content ?? '',
                'excerpt' => $this->excerpt,
                'author_id' => $this->author?->getId() ?? 1,
                'node_type' => $this->nodeType,
                'status' => $this->status ?? 'draft',
                'is_published' => (int) ($this->isPublished ?? false),
                'featured' => (int) ($this->featured ?? false),
                'sticky' => (int) ($this->sticky ?? false),
                'allow_comments' => (int) ($this->allowComments ?? true),
                'password' => $this->password,
                'template' => $this->template,
                'language' => $this->language ?? 'en',
                'parent_id' => $this->parentId,
                'order' => $this->order ?? 0,
                'menu_order' => $this->menuOrder ?? 0,
                'meta_title' => $this->metaTitle,
                'meta_description' => $this->metaDescription,
                'meta_keywords' => $this->metaKeywords,
                'canonical_url' => $this->canonicalUrl,
                'redirect_url' => $this->redirectUrl,
                'created_at' => $now->format("Y-m-d H:i:s"),
                'updated_at' => $now->format("Y-m-d H:i:s"),
                'published_at' => $this->publishedAt?->format("Y-m-d H:i:s") ?? null,
            ];

            $this->database->query($sql, ...$values);
            
            return $this->database->lastInsertId();
        }
    }

    protected function saveDynamicFields(int $nodeId): void
    {
        $definitions = $this->fieldDefinitions();
        $tableName = $definitions['table'];
        $referenceField = $definitions['reference_field'] ?? 'entity_id';
        $dataField = $definitions['data_field'] ?? 'field_data';
        
        // Get all dynamic values
        $dynamicData = $this->getValues();
        
        // Check if record exists
        $existing = $this->database->query(
            "SELECT id FROM {$tableName} WHERE {$referenceField} = ?",
            $nodeId
        )->fetch();
        
        if ($existing) {
            // Update existing record
            $sql = "UPDATE {$tableName} SET {$dataField} = ? WHERE {$referenceField} = ?";
            $this->database->query($sql, ...$values = [json_encode($dynamicData), $nodeId]);
        } else {
            // Insert new record
            $sql = "INSERT INTO {$tableName} ({$referenceField}, {$dataField}) VALUES (?, ?)";
            $this->database->query($sql,...$values =  [$nodeId, json_encode($dynamicData)]);
        }
    }

    protected function generateUuid(): string
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

    abstract public function fieldDefinitions(): array;

    private function persistStorageDefinition()
    {
        $definitions = $this->fieldDefinitions();
        $tableName = $definitions['table'];
        
        // Check if table already exists
        $existingTable = $this->database->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            $tableName
        )->fetch();
        
        if ($existingTable) {
            // Table already exists, no need to create
            return;
        }
        
        // Create table if not exists
        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_id INT UNSIGNED NOT NULL,
            field_data JSON NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_entity (entity_id),
            FOREIGN KEY (entity_id) REFERENCES node_data(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->database->query($sql);
        
        $this->logger->info('Created content storage table', [
            'table' => $tableName,
            'entity_type' => static::class
        ]);
    }
    
    public static function filter(array $fields, array $options = [])
    {
        $instance = new static(getAppContainer()->get('database'), getAppContainer()->get('logger'));
        $definitions = $instance->fieldDefinitions();
        $tableName = $definitions['table'];
        $referenceField = $definitions['reference_field'] ?? 'entity_id';
        $dataField = $definitions['data_field'] ?? 'field_data';
        
        $database = getAppContainer()->get('database');
        
        // Build WHERE conditions
        $whereConditions = [];
        $params = [];
        
        foreach ($fields as $field => $value) {
            if (is_array($value)) {
                // JSON array search
                $whereConditions[] = "JSON_CONTAINS({$dataField}, ?, '$.{$field}')";
                $params[] = json_encode($value);
            } else {
                // JSON value search
                $whereConditions[] = "JSON_EXTRACT({$dataField}, '$.{$field}') = ?";
                $params[] = json_encode($value);
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Add pagination
        $limit = $options['limit'] ?? 20;
        $offset = $options['offset'] ?? 0;
        $limitClause = "LIMIT {$limit} OFFSET {$offset}";
        
        // Add ordering
        $orderBy = $options['order_by'] ?? 'created_at';
        $orderDirection = $options['order_direction'] ?? 'DESC';
        $orderClause = "ORDER BY {$orderBy} {$orderDirection}";
        
        $sql = "SELECT entity_id FROM {$tableName} {$whereClause} {$orderClause} {$limitClause}";
        
        $results = $database->query($sql, ...$params)->fetchAll();
        
        // Load full entities
        $entities = [];
        foreach ($results as $result) {
            $entities[] = static::load($result['entity_id']);
        }
        
        return $entities;
    }

    /**
     * @throws \DateMalformedStringException
     * @throws NotFoundException
     * @throws DependencyException
     */
    public static function load(int $id)
    {
        $database = \getAppContainer()->get('database');
        $logger = \getAppContainer()->get('logger');
        
        // Load core data from node_data
        $nodeData = $database->query(
            "SELECT * FROM node_data WHERE id = ? AND deleted_at IS NULL",
            $id
        )->fetch();
        
        if (!$nodeData) {
            return null;
        }
        
        // Create entity instance
        $instance = new static($database, $logger);
        
        // Set core properties
        $instance->id = $nodeData['id'];
        $instance->uuid = $nodeData['uuid'];
        $instance->title = $nodeData['title'];
        $instance->slug = $nodeData['slug'];
        $instance->content = $nodeData['content'];
        $instance->excerpt = $nodeData['excerpt'];
        $instance->nodeType = $nodeData['node_type'];
        $instance->status = $nodeData['status'];
        $instance->isPublished = (bool) $nodeData['is_published'];
        $instance->featured = (bool) $nodeData['featured'];
        $instance->sticky = (bool) $nodeData['sticky'];
        $instance->allowComments = (bool) $nodeData['allow_comments'];
        $instance->password = $nodeData['password'];
        $instance->template = $nodeData['template'];
        $instance->language = $nodeData['language'];
        $instance->parentId = $nodeData['parent_id'];
        $instance->order = $nodeData['order'];
        $instance->menuOrder = $nodeData['menu_order'];
        $instance->metaTitle = $nodeData['meta_title'];
        $instance->metaDescription = $nodeData['meta_description'];
        $instance->metaKeywords = $nodeData['meta_keywords'];
        $instance->canonicalUrl = $nodeData['canonical_url'];
        $instance->redirectUrl = $nodeData['redirect_url'];
        $instance->createdAt = new DateTime($nodeData['created_at']);
        $instance->updatedAt = new DateTime($nodeData['updated_at']);
        $instance->publishedAt = $nodeData['published_at'] ? new DateTime($nodeData['published_at']) : null;
        $instance->deletedAt = $nodeData['deleted_at'] ? new DateTime($nodeData['deleted_at']) : null;
        
        // Load author
        if ($nodeData['author_id']) {
            $instance->author = User::loadById($nodeData['author_id'], $database);
        }
        
        // Load dynamic fields
        $definitions = $instance->fieldDefinitions();
        $tableName = $definitions['table'];
        $referenceField = $definitions['reference_field'] ?? 'entity_id';
        $dataField = $definitions['data_field'] ?? 'field_data';
        
        $dynamicData = $database->query(
            "SELECT {$dataField} FROM {$tableName} WHERE {$referenceField} = ?",
            $id
        )->fetch();
        
        if ($dynamicData && $dynamicData[$dataField]) {
            $instance->dynamicValues = json_decode($dynamicData[$dataField], true) ?? [];
        }
        
        return $instance;
    }

    public static function create(array $values)
    {
        $database = getAppContainer()->get('database');
        $logger = getAppContainer()->get('logger');
        
        $instance = new static($database, $logger);
        
        // Set core values
        if (isset($values['title'])) $instance->title = $values['title'];
        if (isset($values['content'])) $instance->content = $values['content'];
        if (isset($values['excerpt'])) $instance->excerpt = $values['excerpt'];
        if (isset($values['author_id'])) {
            $instance->author = User::loadById($values['author_id'], $database);
        }
        if (isset($values['node_type'])) $instance->nodeType = $values['node_type'];
        if (isset($values['status'])) $instance->status = $values['status'];
        if (isset($values['is_published'])) $instance->isPublished = (bool) $values['is_published'];
        if (isset($values['featured'])) $instance->featured = (bool) $values['featured'];
        if (isset($values['sticky'])) $instance->sticky = (bool) $values['sticky'];
        if (isset($values['allow_comments'])) $instance->allowComments = (bool) $values['allow_comments'];
        if (isset($values['password'])) $instance->password = $values['password'];
        if (isset($values['template'])) $instance->template = $values['template'];
        if (isset($values['language'])) $instance->language = $values['language'];
        if (isset($values['parent_id'])) $instance->parentId = $values['parent_id'];
        if (isset($values['order'])) $instance->order = $values['order'];
        if (isset($values['menu_order'])) $instance->menuOrder = $values['menu_order'];
        if (isset($values['meta_title'])) $instance->metaTitle = $values['meta_title'];
        if (isset($values['meta_description'])) $instance->metaDescription = $values['meta_description'];
        if (isset($values['meta_keywords'])) $instance->metaKeywords = $values['meta_keywords'];
        if (isset($values['canonical_url'])) $instance->canonicalUrl = $values['canonical_url'];
        if (isset($values['redirect_url'])) $instance->redirectUrl = $values['redirect_url'];
        
        // Set dynamic values
        $dynamicValues = array_diff_key($values, array_flip([
            'id', 'uuid', 'title', 'content', 'excerpt', 'author_id', 'node_type',
            'status', 'is_published', 'featured', 'sticky', 'allow_comments',
            'password', 'template', 'language', 'parent_id', 'order', 'menu_order',
            'meta_title', 'meta_description', 'meta_keywords', 'canonical_url',
            'redirect_url', 'created_at', 'updated_at', 'published_at', 'deleted_at'
        ]));
        
        $instance->setValues($dynamicValues);

        // Save to database
        if ($instance->save()) {
            return $instance;
        }
        
        return null;
    }

    public static function delete(int $id)
    {
        $database = getAppContainer()->get('database');
        $logger = getAppContainer()->get('logger');
        
        try {
            $database->beginTransaction();
            
            // Soft delete from node_data
            $sql = "UPDATE node_data SET deleted_at = NOW() WHERE id = ?";
            $database->query($sql, $id);
            
            // Delete from content table (hard delete)
            $instance = new static($database, $logger);
            $definitions = $instance->fieldDefinitions();
            $tableName = $definitions['table'];
            $referenceField = $definitions['reference_field'] ?? 'entity_id';
            
            $sql = "DELETE FROM {$tableName} WHERE {$referenceField} = ?";
            $database->query($sql, $id);
            
            $database->commit();
            
            $logger->info('Content entity deleted', [
                'id' => $id,
                'entity_type' => static::class
            ]);
            
            return true;
        } catch (\Exception $e) {
            $database->rollBack();
            $logger->error('Failed to delete content entity', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function all(array $options = [])
    {
        $database = getAppContainer()->get('database');
        $logger = getAppContainer()->get('logger');
        
        $instance = new static($database, $logger);
        $nodeType = $instance->nodeType;
        
        // Build query
        $limit = $options['limit'] ?? 20;
        $offset = $options['offset'] ?? 0;
        $orderBy = $options['order_by'] ?? 'created_at';
        $orderDirection = $options['order_direction'] ?? 'DESC';
        $status = $options['status'] ?? null;
        
        $whereConditions = ["deleted_at IS NULL"];
        $params = [];
        
        if ($nodeType) {
            $whereConditions[] = "node_type = ?";
            $params[] = $nodeType;
        }
        
        if ($status) {
            $whereConditions[] = "status = ?";
            $params[] = $status;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $sql = "SELECT id FROM node_data {$whereClause} ORDER BY {$orderBy} {$orderDirection} LIMIT {$limit} OFFSET {$offset}";
        
        $results = $database->query($sql, ...$params)->fetchAll();
        
        $entities = [];
        foreach ($results as $result) {
            $entities[] = static::load($result['id']);
        }
        
        return $entities;
    }

    public static function count(array $options = [])
    {
        $database = getAppContainer()->get('database');
        $logger = getAppContainer()->get('logger');
        
        $instance = new static($database, $logger);
        $nodeType = $instance->nodeType;
        
        $whereConditions = ["deleted_at IS NULL"];
        $params = [];
        
        if ($nodeType) {
            $whereConditions[] = "node_type = ?";
            $params[] = $nodeType;
        }
        
        if (isset($options['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $options['status'];
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $sql = "SELECT COUNT(*) as total FROM node_data {$whereClause}";
        $result = $database->query($sql, ...$params)->fetch();
        
        return (int) $result['total'];
    }

    public static function exists(int $id): bool
    {
        $database = getAppContainer()->get('database');
        
        $result = $database->query(
            "SELECT id FROM node_data WHERE id = ? AND deleted_at IS NULL",
            $id
        )->fetch();
        
        return !empty($result);
    }

    /**
     * @throws \DateMalformedStringException
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function find(int $id): ?self
    {
        return static::load($id);
    }

    public function findByUid(int $uid): ?self
    {
        return $this->findBy('author_id', $uid);
    }

    public function findByUuid(string $uuid): ?self
    {
        return $this->findBy('uuid', $uuid);
    }

    public function findBySlug(string $slug): ?self
    {
        return $this->findBy('slug', $slug);
    }

    public function findByTitle(string $title): ?self
    {
        return $this->findBy('title', $title);
    }

    public function findByStatus(string $status): ?self
    {
        return $this->findBy('status', $status);
    }

    public function findByPublishedAt(DateTime $publishedAt): ?self
    {
        return $this->findBy('published_at', $publishedAt->format('Y-m-d H:i:s'));
    }

    public function findByCreatedAt(DateTime $createdAt): ?self
    {
        return $this->findBy('created_at', $createdAt->format('Y-m-d H:i:s'));
    }

    public function findByUpdatedAt(DateTime $updatedAt): ?self
    {
        return $this->findBy('updated_at', $updatedAt->format('Y-m-d H:i:s'));
    }

    public function findByFeatured(bool $featured): ?self
    {
        return $this->findBy('featured', (int) $featured);
    }

    public function findBy(string $field, $value): ?self
    {
        $database = \getAppContainer()->get('database');
        $logger = \getAppContainer()->get('logger');
        
        // Validate field name to prevent SQL injection
        $allowedFields = [
            'id', 'uuid', 'title', 'slug', 'content', 'excerpt', 'author_id',
            'node_type', 'status', 'is_published', 'featured', 'sticky',
            'allow_comments', 'password', 'template', 'language', 'parent_id',
            'order', 'menu_order', 'meta_title', 'meta_description',
            'meta_keywords', 'canonical_url', 'redirect_url', 'created_at',
            'updated_at', 'published_at', 'deleted_at'
        ];
        
        if (!in_array($field, $allowedFields)) {
            throw new \InvalidArgumentException("Field '{$field}' is not allowed for search");
        }
        
        $nodeType = $this->nodeType;
        
        $sql = "SELECT * FROM node_data WHERE {$field} = ? AND node_type = ? AND deleted_at IS NULL LIMIT 1";
        $result = $database->query($sql, $value, $nodeType)->fetch();
        
        if (!$result) {
            return null;
        }
        
        return static::hydrateFromDatabase($result, $database, $logger);
    }

    public function findManyBy(string $field, array $values): array
    {
        if (empty($values)) {
            return [];
        }
        
        $database = \getAppContainer()->get('database');
        $logger = \getAppContainer()->get('logger');
        
        // Validate field name
        $allowedFields = [
            'id', 'uuid', 'title', 'slug', 'content', 'excerpt', 'author_id',
            'node_type', 'status', 'is_published', 'featured', 'sticky',
            'allow_comments', 'password', 'template', 'language', 'parent_id',
            'order', 'menu_order', 'meta_title', 'meta_description',
            'meta_keywords', 'canonical_url', 'redirect_url', 'created_at',
            'updated_at', 'published_at', 'deleted_at'
        ];
        
        if (!in_array($field, $allowedFields)) {
            throw new \InvalidArgumentException("Field '{$field}' is not allowed for search");
        }
        
        // Create placeholders for IN clause
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        $nodeType = $this->nodeType;
        
        $sql = "SELECT * FROM node_data WHERE {$field} IN ({$placeholders}) AND node_type = ? AND deleted_at IS NULL";
        $allParams = [...$values, $nodeType];
        
        $results = $database->query($sql, ...$allParams)->fetchAll();
        
        $entities = [];
        foreach ($results as $result) {
            $entities[] = static::hydrateFromDatabase($result, $database, $logger);
        }
        
        return $entities;
    }

    public function findByParentId(int $parentId): ?self
    {
        return $this->findBy('parent_id', $parentId);
    }

    public function findManyByParentIds(array $parentIds): array
    {
        return $this->findManyBy('parent_id', $parentIds);
    }

    /**
     * Helper method to hydrate entity from database row
     */
    private static function hydrateFromDatabase(array $result, $database, $logger): self
    {
        $className = static::class;
        $instance = new $className($database, $logger);
        
        // Populate core properties
        $instance->id = $result['id'];
        $instance->uuid = $result['uuid'];
        $instance->title = $result['title'];
        $instance->slug = $result['slug'];
        $instance->content = $result['content'];
        $instance->excerpt = $result['excerpt'];
        $instance->nodeType = $result['node_type'];
        $instance->status = $result['status'];
        $instance->isPublished = (bool) $result['is_published'];
        $instance->featured = (bool) $result['featured'];
        $instance->sticky = (bool) $result['sticky'];
        $instance->allowComments = (bool) $result['allow_comments'];
        $instance->password = $result['password'];
        $instance->template = $result['template'];
        $instance->language = $result['language'];
        $instance->parentId = $result['parent_id'];
        $instance->order = $result['order'];
        $instance->menuOrder = $result['menu_order'];
        $instance->metaTitle = $result['meta_title'];
        $instance->metaDescription = $result['meta_description'];
        $instance->metaKeywords = $result['meta_keywords'];
        $instance->canonicalUrl = $result['canonical_url'];
        $instance->redirectUrl = $result['redirect_url'];
        $instance->createdAt = new DateTime($result['created_at']);
        $instance->updatedAt = new DateTime($result['updated_at']);
        $instance->publishedAt = $result['published_at'] ? new DateTime($result['published_at']) : null;
        $instance->deletedAt = $result['deleted_at'] ? new DateTime($result['deleted_at']) : null;
        
        // Load author
        if ($result['author_id']) {
            $instance->author = User::loadById($result['author_id'], $database);
        }
        
        // Load dynamic fields
        $definitions = $instance->fieldDefinitions();
        $tableName = $definitions['table'];
        $referenceField = $definitions['reference_field'] ?? 'entity_id';
        $dataField = $definitions['data_field'] ?? 'field_data';
        
        $dynamicData = $database->query(
            "SELECT {$dataField} FROM {$tableName} WHERE {$referenceField} = ?",
            $result['id']
        )->fetch();
        
        if ($dynamicData && $dynamicData[$dataField]) {
            $instance->setValues(json_decode($dynamicData[$dataField], true) ?: []);
        }
        
        return $instance;
    }

    public function getEntityForm(): string
    {
        $view = $this->fieldDefinitions();
        if (!empty($view['form_template'])) {
            $template = $view['form_template'];
            return getAppContainer()->get('twig')->render($template, ['entity' => $this]);
        }

        return getAppContainer()->get('twig')->render("admin/content/entity_form_{$this->nodeType}.twig", ['entity' => $this]);
    }
}