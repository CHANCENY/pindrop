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

        // json_encode instead of serialize — serialize() is a RCE vector via
        // PHP object injection if an attacker controls stored content.
        $encoded = json_encode(['key' => $settings->getKey(), 'value' => $settings->getValue()]);
        return $this->databaseService->table('site_settings')->insert([
            'key_token' => $key,
            'content'   => $encoded,
        ]);
    }

    /**
     * @throws DatabaseException
     */
    public function getSetting(string $key): ?Setting {
        $st = $this->databaseService->table('site_settings')->where('key_token','=', $key)->first();
        if (!empty($st)) {
            $decoded = json_decode($st['content'], true);
            if (is_array($decoded)) {
                return new Setting($decoded['key'] ?? $key, $decoded['value'] ?? []);
            }
            // Fallback: migrate legacy serialized rows on read.
            // Remove this block after running a one-time migration on existing data.
            if (is_string($st['content']) && str_starts_with($st['content'], 'O:')) {
                $legacy = @unserialize($st['content'], ['allowed_classes' => [Setting::class]]);
                if ($legacy instanceof Setting) {
                    $this->createSetting($legacy->getKey(), $legacy->getValue());
                    return $legacy;
                }
            }
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