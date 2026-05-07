<?php

require_once __DIR__ . "/../vendor/autoload.php";

require_once __DIR__ . "/colors.php";
require_once __DIR__ . "/cli_printer.php";

use Simp\Pindrop\Services\EnvServiceProvider;
use Symfony\Component\Yaml\Yaml;


new EnvServiceProvider();

$cliPrinter = new \CLIPrinter();

$cliPrinter->printLine("System checking...", GREEN);

// Scanning required directories and files for system information, if there is any missing or not writable directory or file, the system will print error message and exit.

$directories = [
    __DIR__ . "/../modules",
    __DIR__ . "/../config/sync",
    __DIR__ . "/../sites"
];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
        $cliPrinter->printLine("Directory $directory is missing, creating it now.", YELLOW);
    }
}

// Scanning for mandatory plugins.
$mandatoryPlugins = [
    "admin",
    "terminal_command",
];

foreach ($mandatoryPlugins as $plugin) {
    $pluginPath = __DIR__ . "/../modules/$plugin";
    if (!is_dir($pluginPath)) {
        downloadPlugin($cliPrinter, $plugin);
    }
}

$configFile = __DIR__ . "/../config/sync/core.plugin.yml";
if (!file_exists($configFile)) {
    touch($configFile);
    $cliPrinter->printLine("Config file $configFile is missing, creating it now.", YELLOW);
}

$installedEnabledPlugin = Yaml::parseFile($configFile) ?? [];

$installedEnabledPlugin2 = [
    'admin' => 1,
    'terminal_command' => 1,
];

$installedEnabledPlugin = array_merge($installedEnabledPlugin, $installedEnabledPlugin2);

file_put_contents($configFile, Yaml::dump($installedEnabledPlugin, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));

$cliPrinter->printLine("Mandatory plugins are all set.", GREEN);

$instructions = <<<INSTRUCTION
System is ready, you can now run these commands.

These commands can be run directly or with lando if your are using lando server environment.

1. ./pindro db:schema:create or lando ssh exec -c "./pindro db:schema:create" - This command will create the database schema for the system, it should be run after you have set up your database connection and before you run the system:local-update command.

Then all is good, your system is ready to use.

INSTRUCTION;

$cliPrinter->printLine($instructions, GREEN);

function downloadPlugin(\CLIPrinter $printer, string $plugin_id): void
{
    if (empty($plugin_id)) {
        $printer->printLine("Plugin name not given please run like ./pindro plugin:download <plugin_id>", RED);
        return;
    }

     $printer->printLine("Downloading..." . $plugin_id, GREEN);
        $gitBinary = exec("which git");
        $downloadDir = __DIR__ . DIRECTORY_SEPARATOR . "downloads";

        if (!is_dir($downloadDir)) {
            mkdir($downloadDir, 0777, true);
        }
        if (!is_writable($downloadDir)) {
            chmod($downloadDir, 0777);
        }

        if (str_ends_with($gitBinary, "git")) {
            $command = "{$gitBinary} clone -b {$plugin_id} --single-branch https://github.com/CHANCENY/pindrop-features.git {$downloadDir}";
            $finished = exec($command, $output, $exitCode);

            if ($exitCode === 0) {
                $pluginsPath = __DIR__ . "/../modules";
                if (!is_dir($pluginsPath)) {
                    mkdir($pluginsPath, 0777, true);
                }

                $pluginPath = $pluginsPath . DIRECTORY_SEPARATOR . $plugin_id;
                
                if (rename($downloadDir.DIRECTORY_SEPARATOR.$plugin_id, $pluginPath)) {
                    $printer->printLine("Plugin downloaded: " . $plugin_id, GREEN);
                }
                else {
                    $printer->printLine("Failed to movie plugin: " . $plugin_id. " from ".$downloadDir, RED);
                }

                deleteDirectory($downloadDir);
            }
        }
        else {
            $printer->printLine("Git binary not found", RED);
        }

        return;

}

function deleteDirectory(string $dir): bool {
    if (!is_dir($dir)) {
        return false;
    }

    $files = scandir($dir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $file;

        if (is_dir($path)) {
            deleteDirectory($path); // recursive call
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}