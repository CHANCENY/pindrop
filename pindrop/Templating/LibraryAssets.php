<?php

namespace Simp\Pindrop\Templating;

use Simp\Pindrop\Database\DatabaseException;
use Simp\Pindrop\Database\DatabaseService;
use Simp\Pindrop\Plugin\PluginManager;
use Twig\Markup;

class LibraryAssets
{
    protected array $cssAssets = [];
    protected array $jsAssets = [];

    public function __construct(protected DatabaseService $databaseService)
    {
        $this->schema();
        $assetsCollections = $this->loadDatabaseAssets();
        if (empty($assetsCollections)) {
            $this->bootAssets();
            $assetsCollections = $this->loadDatabaseAssets();
        }

        foreach ($assetsCollections as $assetsCollection) {
            if ($assetsCollection['section_type'] === 'css') {
                $this->cssAssets[] = $assetsCollection;
            }
            elseif ($assetsCollection['section_type'] === 'js') {
                $this->jsAssets[] = $assetsCollection;
            }
        }
    }

    /**
     * @throws DatabaseException
     */
    private function schema(): void
    {
        if (!$this->databaseService->tableExists("theme_library_assets")) {
            $query = "CREATE TABLE IF NOT EXISTS `theme_library_assets` (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                      filename VARCHAR(1000) NOT NULL, dependencies JSON NULL, section_type ENUM('css', 'js') NOT NULL DEFAULT 'css')";
            $this->databaseService->query($query);
        }
    }

    private function loadDatabaseAssets(): array
    {
        $query = "SELECT * FROM theme_library_assets;";
        $st = $this->databaseService->query($query);
        $data = $st->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(function ($row) {
            $row['dependencies'] = json_decode($row['dependencies'], true);
            return $row;
        }, $data);
    }

    private function bootAssets(): void
    {
        /**@var PluginManager $pluginManager **/
        $pluginManager = \getAppContainer()->get('plugin.manager');

        $assetsLibraries = $pluginManager->getPluginsYamlContent('libraries.assets');

        foreach ($assetsLibraries as $library){
            foreach ($library as $name=>$file){
                $css = $file['theme']['css'] ?? [];
                $js = $file['theme']['js'] ?? [];
                foreach ($css as $file=>$dependencies){
                    if (is_array($dependencies)){
                        $collectionCss = [
                            'filename' => $file,
                            'dependencies' => json_encode($dependencies),
                            'section_type' => 'css'
                        ];

                        $query = "INSERT INTO theme_library_assets (filename, dependencies, section_type) VALUES (:filename, :dependencies, :section_type)";
                        $this->databaseService->query($query, ...$collectionCss);
                    }
                }
                foreach ($js as $file=>$dependencies){
                    if (is_array($dependencies)){
                        $collectionCss = [
                            'filename' => $file,
                            'dependencies' => json_encode($dependencies),
                            'section_type' => 'js'
                        ];

                        $query = "INSERT INTO theme_library_assets (filename, dependencies, section_type) VALUES (:filename, :dependencies, :section_type)";
                        $this->databaseService->query($query, ...$collectionCss);
                    }
                }
            }
        }
    }


    public function attach(string $library_name): void
    {
        /**@var PluginManager $pluginManager **/
        $pluginManager = \getAppContainer()->get('plugin.manager');

        $assetsLibraries = $pluginManager->getPluginsYamlContent('libraries.assets');

        foreach ($assetsLibraries as $library){
            $flag=false;
            foreach ($library as $lib_name=>$file){
               if ($lib_name === $library_name){
                   $css = $file['css'] ?? [];
                   $js = $file['js'] ?? [];
                   foreach ($css as $file=>$dependencies){
                       if (is_array($dependencies)){
                           $collectionCss = [
                               'filename' => $file,
                               'dependencies' => json_encode($dependencies),
                               'section_type' => 'css'
                           ];
                           $this->cssAssets[] = $collectionCss;
                       }
                   }
                   foreach ($js as $file=>$dependencies){
                       if (is_array($dependencies)){
                           $collectionCss = [
                               'filename' => $file,
                               'dependencies' => json_encode($dependencies),
                               'section_type' => 'js'
                           ];
                           $this->jsAssets[] = $collectionCss;
                       }
                   }
                   $flag=true;
                   break;
               }
            }
            if ($flag){
                break;
            }
        }
    }

    public function renderCss(): Markup
    {
        $cssMarkupLine = "";
        foreach ($this->cssAssets as $cssAsset){
            $dependenciesLine = "";
            foreach ($cssAsset['dependencies'] as $key=>$value){
                $dependenciesLine .= "{$key}=\"{$value}\"";
            }

            $line = "<link rel='stylesheet' type='text/css' href='{$cssAsset['filename']}' $dependenciesLine />";
            $cssMarkupLine .= $line;
        }
        return new Markup($cssMarkupLine, 'utf-8');
    }

    public function renderJs(): Markup
    {
        $jsMarkupLine = "";
        $nonceHash = bin2hex(random_bytes(16));
        foreach ($this->jsAssets as $jsAsset){
            $dependenciesLine = "";
            $jsAsset['dependencies']['nonce'] = $nonceHash;
            foreach ($jsAsset['dependencies'] as $key=>$value){
                $dependenciesLine .= "{$key}=\"{$value}\"";
            }
            $line = "<script src='{$jsAsset['filename']}' $dependenciesLine ></script>";
            $jsMarkupLine .= $line;
        }
        return new Markup($jsMarkupLine, 'utf-8');
    }
}