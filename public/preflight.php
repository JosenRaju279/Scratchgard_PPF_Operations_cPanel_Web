<?php
$checks = [
    'PHP >= 8.3' => version_compare(PHP_VERSION,'8.3.0','>='),
    'PDO' => extension_loaded('pdo'),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'PDO PostgreSQL' => extension_loaded('pdo_pgsql'),
    'cURL' => extension_loaded('curl'),
    'mbstring' => extension_loaded('mbstring'),
    'OpenSSL' => extension_loaded('openssl'),
    'Fileinfo' => extension_loaded('fileinfo'),
    'Zip (plugins)' => extension_loaded('zip'),
];
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font:16px system-ui;background:#07111e;color:#edf7ff;max-width:760px;margin:40px auto;padding:20px}.ok{color:#45dea5}.bad{color:#ff7f91}code{background:#122338;padding:3px 6px}</style></head><body>
<h1>Scratchgard cPanel Preflight</h1><p>This page works even before Composer dependencies are installed.</p>
<?php foreach($checks as $name=>$ok): ?><p class="<?= $ok?'ok':'bad' ?>"><?= $ok?'✓':'✕' ?> <?= htmlspecialchars($name) ?></p><?php endforeach; ?>
<p>PHP version: <code><?= htmlspecialchars(PHP_VERSION) ?></code></p>
<p>After Composer is installed, open <code>/install</code> for the full setup wizard.</p></body></html>
