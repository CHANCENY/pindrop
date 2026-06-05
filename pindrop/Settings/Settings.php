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
    }

    /**
     * @throws DatabaseException
     */
    public function createSetting(string $key, array $value): int
    {
        $settings = new Setting($key, $value);
        $this->databaseService->table('site_settings')->where('key_token','=', $key)->delete();

        $query = "INSERT INTO site_settings(key_token, content) VALUES(:key, :value)";
        $value = serialize($settings);
        return $this->databaseService->table('site_settings')->insert([
            'key_token' => $key,
            'content' => $value
        ]);
        
    }

    /**
     * @throws DatabaseException
     */
    public function getSetting(string $key): ?Setting {
        $st = $this->databaseService->table('site_settings')->where('key_token','=', $key)->first();
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
        return $this->databaseService->table('site_settings')->where('key_token','=', $key)->delete();
    }
}