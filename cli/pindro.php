 <?php

 use Simp\Pindrop\Plugin\PluginManager;

 require_once __DIR__ . "/cli.inc.php";

$printer = new \CLIPrinter();

/**@var PluginManager $pluginManager **/
$pluginManager = \getAppContainer()->get('plugin.manager');

// Load cli tools
$cliTools = $pluginManager->getPluginsYamlContent('pindro.commands');

$registrableTools = [];

foreach ($cliTools as $cliTool) {
    foreach ($cliTool as $tool) {
        if (!empty($tool['status']) && !empty($tool['script'])) {
            $fullPath = $_ENV['ROOT'].DIRECTORY_SEPARATOR .trim($tool['script'], DIRECTORY_SEPARATOR);
            if (file_exists($fullPath)) {
                $commands = require_once $fullPath;
                if (is_array($commands)) {
                    $registrableTools = array_merge($registrableTools, $commands);
                }
            }
        }
    }
}

$command = $argv[1] ?? null;
if (empty($command)) {
    $printer->printLine("Error: Command is not given yet.", "red");
    return 0;
}

$commandHandler = $registrableTools[$command] ?? null;
if (empty($commandHandler)) {
    $printer->printLine("Error: Unknown command handler given.", "red");
    return 0;
}

return call_user_func($commandHandler, $printer, $command, $argv);