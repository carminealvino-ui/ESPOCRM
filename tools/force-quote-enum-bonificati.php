#!/usr/bin/env php
<?php
/**
 * Forza enum bonificati + svuota TUTTA la cache metadata/client.
 *
 *   php tools/force-quote-enum-bonificati.php
 */

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();
$root = rtrim($root, '/');
chdir($root);

require_once $root . '/tools/patch-quote-enum-bonificati.php';

// Svuota cache filesystem aggressive
$paths = [
    $root . '/data/cache',
    $root . '/data/cache/application',
    $root . '/client/custom/lib',
];

foreach ([$root . '/data/cache'] as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
}

echo "[OK] data/cache svuotata\n";

if (is_file($root . '/clear_cache.php')) {
    passthru('php clear_cache.php', $code1);
}

if (is_file($root . '/rebuild.php')) {
    passthru('php rebuild.php', $code2);
}

// Verifica contenuto file
$meta = json_decode((string) file_get_contents(
    $root . '/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json'
), true);

$fin = $meta['fields']['statoFinanziamento']['options'] ?? [];
$legacy = array_values(array_intersect($fin, [
    'In lavorazione',
    'In Attesa Documentazione',
    'In attesa documentazione',
    'In attesa di OTP',
]));

echo 'statoFinanziamento options: ' . json_encode($fin, JSON_UNESCAPED_UNICODE) . "\n";

if ($legacy !== []) {
    fwrite(STDERR, 'ERR ancora legacy nel file: ' . json_encode($legacy) . "\n");
    exit(1);
}

echo "OK file pulito. Hard refresh browser (Ctrl+Shift+R).\n";
