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

  
    private function loadDatabaseAssets(): array
    {
        $data = $this->databaseService->table('theme_library_assets')->get();
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

                        $this->databaseService->table('theme_library_assets')->insert($collectionCss);
                    }
                }
                foreach ($js as $file=>$dependencies){
                    if (is_array($dependencies)){
                        $collectionCss = [
                            'filename' => $file,
                            'dependencies' => json_encode($dependencies),
                            'section_type' => 'js'
                        ];

                        $this->databaseService->table('theme_library_assets')->insert($collectionCss);
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

    public function clearCache(): bool {
        return $this->databaseService->table('theme_library_assets')->delete() > 0;
    }
}