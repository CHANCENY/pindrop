<?php

declare(strict_types=1);

namespace Simp\Pindrop\Templating;

use DI\DependencyException;
use DI\NotFoundException;
use Simp\Pindrop\FileSystem\FileSystem;
use Simp\Pindrop\Message\Message;
use Simp\Pindrop\Modules\commerce_store\src\Autocomplete\AutocompleteOrderNumber;
use Simp\Pindrop\Plugin\PluginManager;
use Simp\Pindrop\Routing\Url;
use Simp\Pindrop\Theme\ThemeManager;
use Simp\Pindrop\Services\EnvServiceProvider;
use DI\Container;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use Twig\Extension\StringLoaderExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig Engine
 * 
 * Provides Twig templating with theme integration.
 */
class TwigEngine
{
    private Environment $twig;
    private FilesystemLoader $loader;
    private ThemeManager $themeManager;
    private EnvServiceProvider $envProvider;
    private ?Container $container;
    
    public function __construct(
        ThemeManager $themeManager,
        EnvServiceProvider $envProvider,
        ?Container $container = null
    ) {
        $this->themeManager = $themeManager;
        $this->envProvider = $envProvider;
        $this->container = $container;
        $this->initializeTwig();
    }
    
    /**
     * Initialize Twig environment
     */
    private function initializeTwig(): void
    {
        // Create filesystem loader
        $this->loader = new FilesystemLoader();
        
        // Add theme paths
        $this->addThemePaths();
        
        // Create Twig environment
        $twigConfig = $this->getTwigConfig();
        $this->twig = new Environment($this->loader, $twigConfig);
        
        // Add extensions
        $this->addExtensions();
        
        // Add global functions
        $this->addGlobalFunctions();
    }
    
    /**
     * Add theme paths to Twig loader
     */
    private function addThemePaths(): void
    {
        $themes = $this->themeManager->getThemes();

        foreach ($themes as $themeName => $theme) {
            $templatesPath = $theme['path'];
            
            if (is_dir($templatesPath)) {
                // Add theme namespace
                $this->loader->addPath($templatesPath, $themeName);
                
                // Also add to the main namespace for fallback
                $this->loader->addPath($templatesPath);
            }
        }

       $otherSources = \getAppContainer()->get('plugin.manager')->getPluginTemplateSources();
       foreach ($otherSources as $source) {
           if (str_ends_with($source, '/templates')) {
               // remove templates from path
               $source = substr($source, 0, -10);
               $this->loader->addPath($source);
           }
       }
    }
    
    /**
     * Get Twig configuration
     */
    private function getTwigConfig(): array
    {
        $debug = $this->envProvider->get('APP_DEBUG', false);
        $cache = $this->envProvider->get('TWIG_CACHE', false);
        
        $config = [
            'debug' => $debug,
            'auto_reload' => true,
            'strict_variables' => false,
            'autoescape' => 'html',
        ];
        
        // Add cache path if enabled
        if ($cache) {
            $cachePath = $this->envProvider->get('TWIG_CACHE_PATH', __DIR__ . '/../../var/cache/twig');
            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0755, true);
            }
            $config['cache'] = $cachePath;
        }
        
        return $config;
    }
    
    /**
     * Add Twig extensions
     */
    private function addExtensions(): void
    {
        // Debug extension
        if ($this->twig->isDebug()) {
            $this->twig->addExtension(new DebugExtension());
        }
        
        // String loader extension
        $this->twig->addExtension(new StringLoaderExtension());
    }
    
    /**
     * Add global functions and variables
     */
    private function addGlobalFunctions(): void
    {
        // Theme functions
        $this->twig->addFunction(new \Twig\TwigFunction('theme_asset', [$this, 'getThemeAsset']));
        $this->twig->addFunction(new \Twig\TwigFunction('theme_url', [$this, 'getThemeUrl']));
        $this->twig->addFunction(new \Twig\TwigFunction('theme_config', [$this, 'getThemeConfig']));
        $this->twig->addFunction(new \Twig\TwigFunction('current_theme', [$this, 'getCurrentTheme']));
        
        // Environment functions
        $this->twig->addFunction(new \Twig\TwigFunction('env', [$this->envProvider, 'get']));
        $this->twig->addFunction(new \Twig\TwigFunction('base_path', [$this, 'getBasePath']));
        
        // Global variables
        $this->twig->addGlobal('app_name', $this->envProvider->get('APP_NAME', 'Pindrop CMS'));
        $this->twig->addGlobal('app_version', $this->envProvider->get('APP_VERSION', '1.0.0'));
        $this->twig->addGlobal('app_env', $this->envProvider->get('APP_ENV', 'development'));
        $this->twig->addGlobal('request', Request::createFromGlobals());

        $this->twig->addFunction(new TwigFunction("supportLanguages", function (){
            return $this->supportLanguage();
        }));
        $this->twig->addFunction(new TwigFunction("language", function (){
            return $this->defaultLanguage();
        }));
        
        // Add menus if container is available
        if ($this->container && $this->container->has('menu.renderer')) {
            try {
                $menuRenderer = $this->container->get('menu.renderer');
                $menus = $menuRenderer->getMenuData();

                $this->twig->addGlobal('menus', $menus);
                
                // Add menu functions
                $this->twig->addFunction(new \Twig\TwigFunction('menu_group', function(string $group) use ($menuRenderer) {
                    return $menuRenderer->renderGroup($group);
                }));
                
                $this->twig->addFunction(new \Twig\TwigFunction('menu_data', function(?string $userRole = null) use ($menuRenderer) {
                    return $menuRenderer->getMenuData($userRole);
                }));
                
                $this->twig->addFunction(new \Twig\TwigFunction('menu_url', function(array $menu) use ($menuRenderer) {
                    // Use reflection to access private method
                    $reflection = new \ReflectionClass($menuRenderer);
                    $method = $reflection->getMethod('generateUrl');
                    $method->setAccessible(true);
                    return $method->invoke($menuRenderer, $menu);
                }));
                $this->addFunction(new \Twig\TwigFunction('url', function(?string $id, array $options = []){
                    if (empty($id)) return '';
                    return Url::routeByName($id, $options);
                }));
                $this->twig->addFunction(new TwigFunction('create_url', function (?string $uri){
                    if (empty($uri)) return $uri;

                    return $this->container->get('filesystem')->getPublicUrl($uri);

                }));
                $this->twig->addFunction(new TwigFunction('dump', function (...$value){
                    dump(...$value);
                }));
                $this->twig->addFunction(new TwigFunction('json_decode', function (?string $value){
                    if (empty($value)) return [];
                    return json_decode($value, true);
                }));
                $this->twig->addFilter(new TwigFilter('json_decode', function (?string $value){
                    if (empty($value)) return [];
                    return json_decode($value, true);
                }));
                $this->twig->addFilter(new TwigFilter('min', function ($value){
                    return min($value);
                }));
                $this->twig->addFilter(new TwigFilter('max', function ($value){
                    return max($value);
                }));

                $this->twig->addFilter(new TwigFilter('auto_complete_order_item', function (array $item){
                    $orderAutoComplete = $this->container->get(AutocompleteOrderNumber::class);

                    return $orderAutoComplete->generateAutoCompleteString($item, $this->container);
                }));

                $this->twig->addFunction(new TwigFunction('base64encode', function ($value){
                    return \base64_encode($value);
                }));
                $this->twig->addFunction(new TwigFunction('base64decode', function ($value){
                    return \base64_decode($value);
                }));

                $this->twig->addFunction(new TwigFunction("_config", function ($plugin, $yml_filename){
                    if (empty($plugin) || empty($yml_filename)) return '';
                    if (is_array($plugin) || is_array($yml_filename)) return '';
                    return $this->container->get('plugin.manager')->getPluginYamlContent($plugin, $yml_filename);
                }));

                $this->twig->addFunction(new TwigFunction("_get_session", function ($key){
                    return $_SESSION[$key] ?? null;
                }));

                $this->twig->addFunction(new TwigFunction("get_messages", function (){
                    return Message::send();
                }));

                $this->twig->addFunction(new TwigFunction("is_route", function (string $route_name){
                    if (!empty($GLOBALS['_SERVER']['ROUTE_ATTRIBUTES']['route_name'])) {
                        $current_route_name = $GLOBALS['_SERVER']['ROUTE_ATTRIBUTES']['route_name'];
                        return $route_name === $current_route_name;
                    }
                    return false;
                }));

            } catch (\Exception $e) {
                // Menu service not available, skip
            }
        }

    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function resolveThemeName(string $templateName): string
    {
        $list = explode('/', $templateName);
        $first = array_shift($list);

        $theme = $this->themeManager->getTheme($first);

        /**@var PluginManager $pluginManager */
        $pluginManager = \getAppContainer()->get('plugin.manager');
        $otherSources = $pluginManager->getPluginTemplateSources();

        // If the template name start with @ means the template is in plugins,
        // therefore, the first element after split "/" will be plugin id
        if (!str_starts_with($templateName, "@")) {
            if (empty($theme)) return $templateName;

            $templatePath = $theme['templates_path'] ?? null;

            if (empty($templatePath)) return $templateName;

            $templatePathRoot = substr($templatePath, strlen($theme['path']), strlen($templatePath));

            $newTemplatePath = [trim($templatePathRoot, '/'), ...$list];
            $newTemplatePath = array_filter($newTemplatePath);

            if (empty($newTemplatePath)) return $templateName;
            return implode('/', $newTemplatePath);
        }

        $plugin = $pluginManager->getPlugin(trim($first, "@"));

        if (empty($plugin)) return $templateName;

        $templatePath = $plugin['path'] . '/templates';
        $templatePathRoot = substr($templatePath, strlen($plugin['path']), strlen($templatePath));

        $newTemplatePath = [trim($templatePathRoot, '/'), ...$list];
        $newTemplatePath = array_filter($newTemplatePath);

        if (empty($newTemplatePath)) return $templateName;
        return implode('/', $newTemplatePath);
    }


    /**
     * Render template
     */
    public function render(string $template, array $data = []): string
    {
        try {
            $template = $this->resolveThemeName($template);
            return $this->twig->render($template, $data);
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to render template '{$template}': " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Render string template
     */
    public function renderString(string $template, array $data = []): string
    {
        try {
            return $this->twig->createTemplate($template)->render($data);
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to render string template: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Check if template exists
     */
    public function exists(string $template): bool
    {
        return $this->loader->exists($template);
    }
    
    /**
     * Get theme asset URL
     */
    public function getThemeAsset(string $asset, ?string $theme = null): string
    {
        $themeName = $theme ?: $this->getDefaultTheme();
        $themeData = $this->themeManager->getTheme($themeName);
        
        if (!$themeData) {
            return $asset;
        }
        
        $assetPath = $themeData['assets_path'] . '/' . ltrim($asset, '/');
        
        if (file_exists($assetPath)) {
            return '/themes/' . $themeName . '/assets/' . ltrim($asset, '/');
        }
        
        return $asset;
    }
    
    /**
     * Get theme URL
     */
    public function getThemeUrl(?string $theme = null): string
    {
        $themeName = $theme ?: $this->getDefaultTheme();
        return '/themes/' . $themeName;
    }
    
    /**
     * Get theme configuration
     */
    public function getThemeConfig(?string $key = null, ?string $theme = null)
    {
        $themeName = $theme ?: $this->getDefaultTheme();
        $themeData = $this->themeManager->getTheme($themeName);
        
        if (!$themeData) {
            return null;
        }
        
        $configFile = $themeData['path'] . '/config/theme.yml';
        
        if (!file_exists($configFile)) {
            return null;
        }
        
        try {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($configFile);
            return $key ? ($config[$key] ?? null) : $config;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get current theme
     */
    public function getCurrentTheme(): ?array
    {
        return $this->themeManager->getTheme($this->getDefaultTheme());
    }
    
    /**
     * Get default theme name
     */
    private function getDefaultTheme(): string
    {
        return $this->envProvider->get('DEFAULT_THEME', 'admin');
    }
    
    /**
     * Get base path
     */
    public function getBasePath(): string
    {
        return $this->envProvider->get('BASE_PATH', '');
    }
    
    /**
     * Get Twig environment
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }
    
    /**
     * Add template path
     */
    public function addPath(string $path, string $namespace = '__main__'): void
    {
        $this->loader->addPath($path, $namespace);
    }
    
    /**
     * Add global variable
     */
    public function addGlobal(string $name, $value): void
    {
        $this->twig->addGlobal($name, $value);
    }
    
    /**
     * Add function
     */
    public function addFunction(\Twig\TwigFunction $function): void
    {
        $this->twig->addFunction($function);
    }
    
    /**
     * Add extension
     */
    public function addExtension(\Twig\Extension\ExtensionInterface $extension): void
    {
        $this->twig->addExtension($extension);
    }

    public function supportLanguage()
    {
        if ($this->container->has('language.support.service')) return $this->container->get('language.support.service')->languages;
        return [];
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function defaultLanguage(): ?string
    {
        $request = $this->container->get(Request::class);
        $lang = $request->query->get('lang');
        if (empty($lang) && $this->container->has('language.support.service')) {
            $lang = $this->container->get('language.support.service')->getDefaultLanguage();
        }
        if (empty($lang)) return $lang;
        return $lang;
    }

    private function translate(string $string): string
    {
        if ($this->container->has('language.support.service')) {
            return $this->container->get('language.support.service')->translate($string,'en', $this->defaultLanguage());
        }
        return $string;
    }
}
