<?php

namespace Simp\Pindrop\FactorAuthentication;

use Simp\Pindrop\Plugin\PluginManager;

class TwoFactorManager
{
    protected array $twofactorAuthenticationProviders = [];

    public function __construct(protected PluginManager $plugin_manager)
    {
        $plugins = $this->plugin_manager->getPluginsYamlContent('two_factor.provider');
        foreach ($plugins as $plugin) {
            foreach ($plugin as $plugin_name => $plugin_data) {
                $class = $plugin_data["class"] ?? null;
                if ($class && class_exists($class)) {
                    $object = getAppContainer()->get($class);
                    if ($object instanceof TwoFactorInterface) {
                        $this->twofactorAuthenticationProviders[$object->key()] = $object;
                    }
                }
            }
        }
    }

    public function getTwofactorAuthenticationProviders(): array
    {
        return $this->twofactorAuthenticationProviders;
    }

    public function getTwofactorAuthenticationProvider(string $key): ?TwoFactorInterface
    {
        return $this->twofactorAuthenticationProviders[$key] ?? null;
    }
}
