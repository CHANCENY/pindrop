<?php

namespace Simp\Pindrop\Settings;

use Symfony\Component\HttpFoundation\Request;

interface SettingsInterface
{

    /**
     * Your settings grouping key.
     * @return string
     */
    public function settingKey(): string;

    /**
     * Return HTML form fields for your settings
     * @param Request $request
     * @param Setting|null $setting
     * @return string
     */
    public function formBuild(Request $request, ?Setting $setting): string;

    /**
     * Get values of fields after submission
     * @param Request $request
     * @return array array values need to be saved in settings storage.
     */
    public function savableValues(Request $request): array;
}