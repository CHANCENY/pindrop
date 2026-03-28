<?php

namespace Simp\Pindrop\Content\Storage;

use DateTime;
use Simp\Pindrop\Database\DatabaseService;
use Simp\Pindrop\Entity\User\User;
use Simp\Pindrop\Logger\LoggerInterface;

interface ContentEntityInterface
{
    public function __construct(DatabaseService $database, LoggerInterface $logger);

    public function id(): int;

    public function getTitle(): string;
    public function setTitle(string $title);

    public function getContent(): string;
    public function setContent(string $content);
    
    public function getAuthor(): User;
    public function setAuthor(User $author);
    public function setAuthorId(int $authorId);
    
    public function getCreatedAt(): DateTime;
    public function setCreatedAt(DateTime $createdAt);
    
    public function getUpdatedAt(): DateTime;
    public function setUpdatedAt(DateTime $updatedAt);
    
    public function getPublishedAt(): DateTime;
    public function setPublishedAt(DateTime $publishedAt);
    
    public function isPublished(): bool;
    public function setPublished(bool $published);
    public function setStatus(string $status);

    public function get(string $key);

    public function set(string $key, $value);
    public function unset(string $key);

    public function getValue(string $key);
    public function setValue(string $key, $value);
    public function getValues(): array;
    public function setValues(array $values);

    public function save();
    public function fieldDefinitions(): array;

    public static function filter(array $fields, array $options = []);

    public static function load(int $id);

    public static function create(array $values);

    public static function delete(int $id);

    public static function all(array $options = []);

    public static function count(array $options = []);

    public static function exists(int $id);

    public function find(int $id);

    public function findByUid(int $uid);

    public function findByUuid(string $uuid);

    public function findBySlug(string $slug);

    public function findByTitle(string $title);

    public function findByStatus(string $status);

    public function findByPublishedAt(DateTime $publishedAt);

    public function findByCreatedAt(DateTime $createdAt);

    public function findByUpdatedAt(DateTime $updatedAt);

    public function findByFeatured(bool $featured);

    public function findBy(string $field, $value);

    public function findManyBy(string $field, array $values);

    public function findByParentId(int $parentId);

    public function findManyByParentIds(array $parentIds);

    public function getEntityForm(): string;

    public function getView(): string;

}