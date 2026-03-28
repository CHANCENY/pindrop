<?php

require_once '../vendor/autoload.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pindrop CMS Documentation</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php

// Allowed docs (whitelist for security)
$not_allowed_docs = [
        "index.php",
        "not-found.html",
        ".",
        "..",
        ".htaccess",
        "styles.css",
        "welcome.html"
];

$allowed_docs = array_diff(scandir(__DIR__),$not_allowed_docs);

$document_filename = $_GET["doc"] ?? "welcome.html";

// Prevent directory traversal
if (!file_exists($document_filename)) {
    $document_filename = "not-found.html";
}
?>

<div class="sidebar">
    <h2>Pindrop Docs</h2>

    <a href="?doc=welcome.html" class="<?= $document_filename === 'welcome.html' ? 'active' : '' ?>">Welcome</a>

    <?php

    foreach ($allowed_docs as $allowed_doc) {

        $name = pathinfo($allowed_doc, PATHINFO_FILENAME);
        $name = ucfirst(str_replace(['-', '_'], ' ', $name));
        $active = $document_filename === $allowed_doc ? 'active' : '';
        echo <<<A
<a href="?doc={$allowed_doc}" class="<?= $active ?>">{$name}</a>
A;

    }

    ?>

</div>

<section class="main-doc-section">
    <iframe
            class="docu-iframe-sec"
            src="/docs/<?= htmlspecialchars($document_filename) ?>"
            frameborder="0">
    </iframe>
</section>

</body>
</html>