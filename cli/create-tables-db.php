<?php

require_once __DIR__ . "/cli.inc.php";

$printer = new \CLIPrinter();

/**@var Simp\Pindrop\Mysql\SchemaHandler $schemaHandler**/
$schemaHandler = getAppContainer()->get('schema.handler');
$tables = $schemaHandler->getSchemaInfo()['schema_files'] ?? [];
$tables[-1] = "ALL";

$selectedTable = $printer->askChoice("Which schema do you wish to create?", $tables, -1);

if ($selectedTable === "ALL") {
    $returns  =  $schemaHandler->createTables();
    $results = [ ["TABLE", "STATUS", "MESSAGE"] ];
    foreach ($returns as $return) {
        $results[] = [ $return['table'], $return['success'], $return['message'] ];
    }
    $printer->printTable($results);
    exit(0);
}

$filename = pathinfo($selectedTable, PATHINFO_FILENAME);
$returns = $schemaHandler->createTables([$filename]);
$results = [ ["TABLE", "STATUS", "MESSAGE"] ];
foreach ($returns as $return) {
    $results[] = [ $return['table'], $return['success'], $return['message'] ];
}
$printer->printTable($results);
exit(0);





