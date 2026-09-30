<?php

namespace Simp\Pindrop\Sites;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 *  This class wrapps all functions needed for multi site support.
 */

class MultiSiteManager
{
    protected array $sites = [];
    protected ?string $theme = "";
    protected array $plugins = [
        'admin'
    ];


    public function __construct(array $env, protected Request $request)
    {
        $site_config = $env['ROOT'] . DIRECTORY_SEPARATOR . '.sites.yml';
        if (file_exists($site_config)) $this->sites = Yaml::parseFile($site_config);
    }

    public function isMultiSiteEnabled(): bool {
        return !empty($this->sites['MULTI_SITE_ENABLED']);
    }

    public function getThisSite(): array {
        return $this->isMultiSiteEnabled() ? $this->sites['SITES'][$this->request->getHost()] ?? [] : [];
    }

    public function startMultiSiteFeatures(): void {
        $thisSite = $this->getThisSite();
        
        if (empty($thisSite)) return;

        // Set the env keys
        foreach($thisSite as $key=>$value) {
            if ($key !== 'PLUGINS_ENABLED') {
                $_ENV[$key] = $value;
            }
        }
        $this->plugins = $thisSite['PLUGINS_ENABLED'] ?? [];
        $this->theme   = $thisSite['THEME_KEY'] ?? null;
    }

    public function getTheme(): ?string {
        if (!$this->isMultiSiteEnabled()) return null;
        return trim($this->theme);
    }

    public function getEnabledSitePlugins(): ?array {
         if (!$this->isMultiSiteEnabled()) return null;
         return $this->plugins;
    }
}
