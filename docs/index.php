<?php

require_once '../vendor/autoload.php';

// Allowed docs (whitelist for security)
$not_allowed_docs = [
    "index.php", "not-found.html", ".", "..", ".htaccess", "styles.css", "welcome.html"
];

$allowed_docs = array_diff(scandir(__DIR__), $not_allowed_docs);

$document_filename = $_GET["doc"] ?? "welcome.html";

// Prevent directory traversal
$requested = realpath(__DIR__ . '/' . basename($document_filename));
if (!$requested || strpos($requested, realpath(__DIR__)) !== 0 || !file_exists($requested)) {
    $document_filename = "not-found.html";
} else {
    $document_filename = basename($requested);
}

// Sidebar navigation definition
$nav = [
    'Getting Started' => [
        ['file' => 'welcome.html',       'label' => 'Welcome',         'icon' => '👋'],
        ['file' => 'installation.html',  'label' => 'Installation',    'icon' => '⚙️'],
        ['file' => 'quickstart.html',    'label' => 'Quick Start',     'icon' => '🚀'],
    ],
    'Core Concepts' => [
        ['file' => 'plugin-system.html', 'label' => 'Plugin System',   'icon' => '🧩'],
        ['file' => 'controllers.html',   'label' => 'Controllers',     'icon' => '🎮'],
        ['file' => 'theming.html',       'label' => 'Theming',         'icon' => '🎨'],
        ['file' => 'forms.html',         'label' => 'Forms',           'icon' => '📋'],
        ['file' => 'events.html',        'label' => 'Events',          'icon' => '📡'],
    ],
    'Data Layer' => [
        ['file' => 'database.html',      'label' => 'Database',        'icon' => '🗄️'],
        ['file' => 'entities.html',      'label' => 'Entities',        'icon' => '📦'],
        ['file' => 'filesystem.html',    'label' => 'File System',     'icon' => '📁'],
    ],
    'Users & Auth' => [
        ['file' => 'auth.html',          'label' => 'Authentication',  'icon' => '🔐'],
        ['file' => 'users.html',         'label' => 'Users',           'icon' => '👤'],
    ],
    'Services' => [
        ['file' => 'mail.html',          'label' => 'Mail',            'icon' => '✉️'],
        ['file' => 'logging.html',       'label' => 'Logging',         'icon' => '📝'],
        ['file' => 'cli.html',           'label' => 'CLI (pindro)',    'icon' => '💻'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pindrop CMS Documentation</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">📍</div>
            <span class="sidebar-logo-text">Pindrop</span>
        </div>
        <div class="sidebar-subtitle">Documentation</div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($nav as $section => $links): ?>
        <div class="sidebar-section-label"><?= htmlspecialchars($section) ?></div>
        <?php foreach ($links as $link): ?>
            <?php $active = $document_filename === $link['file'] ? 'active' : ''; ?>
            <a href="?doc=<?= htmlspecialchars($link['file']) ?>" class="<?= $active ?>">
                <span style="font-size:14px;width:18px;text-align:center;flex-shrink:0"><?= $link['icon'] ?></span>
                <?= htmlspecialchars($link['label']) ?>
            </a>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        Pindrop CMS &nbsp;·&nbsp; v1.0
    </div>
</aside>

<section class="main-doc-section">
    <iframe
        class="docu-iframe-sec"
        src="/docs/<?= htmlspecialchars($document_filename) ?>"
        frameborder="0">
    </iframe>
</section>

</body>
</html>
