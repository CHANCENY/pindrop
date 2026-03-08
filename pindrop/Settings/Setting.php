<?php

namespace Simp\Pindrop\Settings;

class Setting
{
    protected string $key;
    protected array $value;

    public function __construct(string $key, array $value) {
        $this->key = $key;
        $this->value = $value;
    }
    public function getKey(): string {
        return $this->key;
    }

    public function getValue(): array
    {
        return $this->value;
    }

    public function get(string $key)
    {
        $settings = $this->value;
        if (isset($settings[$key])) {
            return $settings[$key];
        }

        foreach ($settings as $k => $value) {
            if ($k === $key) {
                return $value;
            }
            if (is_array($value)) {
                $t = $this->runRecursive($key, $value);
                if (!empty($t)) {
                    return $t;
                }
            }
        }
        return null;
    }

    private function runRecursive(string $key, array $values)
    {
        foreach ($values as $k=>$value) {
            if ($k === $key) {
                return $value;
            }
            if (is_array($value)) {
                $t = $this->runRecursive($key, $values);
                if (!empty($t)) {
                    return $t;
                }
            }
        }
        return null;
    }
}