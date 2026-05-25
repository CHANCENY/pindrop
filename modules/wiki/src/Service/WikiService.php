<?php

namespace Simp\Pindrop\Modules\wiki\src\Service;

use Simp\Pindrop\Database\DatabaseService;
use PDO;

class WikiService
{
    protected PDO $pdo;

    public function __construct(
        protected DatabaseService $databaseService
    ) {
        $this->pdo = $this->databaseService->getPdo();
    }

    /**
     * Create wiki page
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO wiki_pages (
                uuid,
                title,
                slug,
                summary,
                content,
                css,
                parent_id,
                sort_order,
                author_id,
                status,
                visibility,
                revision,
                meta_title,
                meta_description,
                published_at
            ) VALUES (
                :uuid,
                :title,
                :slug,
                :summary,
                :content,
                :css,
                :parent_id,
                :sort_order,
                :author_id,
                :status,
                :visibility,
                :revision,
                :meta_title,
                :meta_description,
                :published_at
            )
        ";

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            ':uuid' => $data['uuid'] ?? $this->generateUuid(),
            ':title' => $data['title'],
            ':slug' => $this->generateSlug($data['slug'] ?? $data['title']),
            ':summary' => $data['summary'] ?? null,
            ':content' => $data['content'] ?? null,
            ':css' => $data['css'] ?? null,
            ':parent_id' => $data['parent_id'] ?? null,
            ':sort_order' => $data['sort_order'] ?? 0,
            ':author_id' => $data['author_id'],
            ':status' => $data['status'] ?? 'draft',
            ':visibility' => $data['visibility'] ?? 'public',
            ':revision' => 1,
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':published_at' => (
                ($data['status'] ?? 'draft') === 'published'
            ) ? date('Y-m-d H:i:s') : null,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $this->createRevision($id);

        return $id;
    }

    /**
     * Update wiki page
     */
    public function update(int $id, array $data): bool
    {
        $page = $this->find($id);

        if (!$page) {
            return false;
        }

        $revision = ((int) $page['revision']) + 1;

        $sql = "
            UPDATE wiki_pages SET
                title = :title,
                slug = :slug,
                summary = :summary,
                content = :content,
                css = :css,
                parent_id = :parent_id,
                sort_order = :sort_order,
                status = :status,
                visibility = :visibility,
                revision = :revision,
                meta_title = :meta_title,
                meta_description = :meta_description
            WHERE id = :id
        ";

        $statement = $this->pdo->prepare($sql);

        $updated = $statement->execute([
            ':id' => $id,
            ':title' => $data['title'] ?? $page['title'],
            ':slug' => $this->generateSlug($data['slug'] ?? $data['title'] ?? $page['title'], $id),
            ':summary' => $data['summary'] ?? $page['summary'] ?? null,
            ':content' => $data['content'] ?? $page['content'] ?? null,
            ':css' => $data['css'] ?? $page['css'] ?? null,
            ':parent_id' => $data['parent_id'] ?? $page['parent_id'] ?? null,
            ':sort_order' => $data['sort_order'] ?? $page['sort_order'] ?? null,
            ':status' => $data['status'] ?? $page['status'] ?? 'draft',
            ':visibility' => $data['visibility'] ?? $page['visibility'] ?? 'public',
            ':revision' => $revision,
            ':meta_title' => $data['meta_title'] ?? $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? $data['meta_description'] ?? null,
        ]);

        if ($updated) {
            $this->createRevision($id);
        }

        return $updated;
    }

    /**
     * Delete wiki page
     */
    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare("
            DELETE FROM wiki_pages
            WHERE id = :id
        ");

        return $statement->execute([
            ':id' => $id,
        ]);
    }

    /**
     * Find page by ID
     */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare("
            SELECT *
            FROM wiki_pages
            WHERE id = :id
            LIMIT 1
        ");

        $statement->execute([
            ':id' => $id,
        ]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Find by slug
     */
    public function findBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare("
            SELECT *
            FROM wiki_pages
            WHERE slug = :slug
            LIMIT 1
        ");

        $statement->execute([
            ':slug' => $slug,
        ]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Get all pages
     */
    public function all(
        int $limit = 50,
        int $offset = 0
    ): array {

        $statement = $this->pdo->prepare("
            SELECT *
            FROM wiki_pages
            ORDER BY updated_at DESC
            LIMIT :limit OFFSET :offset
        ");

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search wiki pages
     */
    public function search(string $query): array
    {
        $statement = $this->pdo->prepare("
            SELECT *
            FROM wiki_pages
            WHERE MATCH(title, summary, content)
            AGAINST(:query IN NATURAL LANGUAGE MODE)
            ORDER BY updated_at DESC
        ");

        $statement->execute([
            ':query' => $query,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get published pages
     */
    public function published(): array
    {
        $statement = $this->pdo->query("
            SELECT *
            FROM wiki_pages
            WHERE status = 'published'
            ORDER BY updated_at DESC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create revision snapshot
     */
    protected function createRevision(int $wikiPageId): void
    {
        $page = $this->find($wikiPageId);

        if (!$page) {
            return;
        }

        $sql = "
            INSERT INTO wiki_page_revisions (
                wiki_page_id,
                revision_number,
                title,
                summary,
                content,
                css,
                author_id
            ) VALUES (
                :wiki_page_id,
                :revision_number,
                :title,
                :summary,
                :content,
                :css,
                :author_id
            )
        ";

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            ':wiki_page_id' => $page['id'],
            ':revision_number' => $page['revision'],
            ':title' => $page['title'],
            ':summary' => $page['summary'],
            ':content' => $page['content'],
            ':css' => $page['css'],
            ':author_id' => $page['author_id'],
        ]);
    }

    /**
     * Get revisions
     */
    public function revisions(int $wikiPageId): array
    {
        $statement = $this->pdo->prepare("
            SELECT *
            FROM wiki_page_revisions
            WHERE wiki_page_id = :wiki_page_id
            ORDER BY revision_number DESC
        ");

        $statement->execute([
            ':wiki_page_id' => $wikiPageId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate slug
     */
    protected function generateSlug(
        string $value,
        ?int $ignoreId = null
    ): string {

        $slug = strtolower(trim($value));

        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $originalSlug = $slug;
        $count = 1;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     */
    protected function slugExists(
        string $slug,
        ?int $ignoreId = null
    ): bool {

        $sql = "
            SELECT COUNT(*)
            FROM wiki_pages
            WHERE slug = :slug
        ";

        if ($ignoreId !== null) {
            $sql .= " AND id != :ignore_id";
        }

        $statement = $this->pdo->prepare($sql);

        $statement->bindValue(':slug', $slug);

        if ($ignoreId !== null) {
            $statement->bindValue(':ignore_id', $ignoreId);
        }

        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * Generate UUID v4
     */
    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

     /**
     * Find page by author
     */
    public function findByAuthor(int $id): ?array
    {
        $statement = $this->pdo->prepare("
            SELECT *
            FROM wiki_pages
            WHERE author_id = :id
             ORDER BY updated_at DESC
        ");

        $statement->execute([
            ':id' => $id,
        ]);

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}