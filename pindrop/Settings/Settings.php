<?php

namespace Simp\Pindrop\Settings;

use Simp\Pindrop\Database\DatabaseException;
use Simp\Pindrop\Database\DatabaseService;

class Settings
{
    /**
     * @throws DatabaseException
     */
    public function __construct(protected DatabaseService $databaseService)
    {
        if (!$this->databaseService->tableExists('site_settings')){
            $query = "CREATE TABLE IF NOT EXISTS `site_settings` (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, key_token VARCHAR(300) NOT NULL, content TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)";
            $this->databaseService->query($query);
            if (!$this->databaseService->tableExists('site_settings')){
                throw new DatabaseException("Table 'site_settings' does not exist");
            }
        }
    }

    /**
     * @throws DatabaseException
     */
    public function createSetting(string $key, array $value): int
    {
        $settings = new Setting($key, $value);

        $query = "DELETE FROM site_settings WHERE key_token = :key";
        $this->databaseService->query($query, $key);

        $query = "INSERT INTO site_settings(key_token, content) VALUES(:key, :value)";
        $value = serialize($settings);
        return $this->databaseService->query($query, $key, $value)->rowCount();
    }

    /**
     * @throws DatabaseException
     */
    public function getSetting(string $key): ?Setting {
        $st = $this->databaseService->query("SELECT content FROM site_settings WHERE key_token = :key",$key)->fetch();

        if (!empty($st)) {
            return unserialize($st['content']);
        }
        return null;
    }

    /**
     * @throws DatabaseException
     */
    public function deleteSetting(string $key): int
    {
        $query = "DELETE FROM site_settings WHERE key_token = :key";
        return $this->databaseService->query($query, $key)->rowCount();
    }
}