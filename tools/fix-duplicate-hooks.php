<?php
/**
 * Ripulisce hook duplicati e allinea InvitoAFatturare (nome file = nome classe).
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/fix-duplicate-hooks.php
 */

declare(strict_types=1);

$crmRoot = dirname(__DIR__);
$hooksRoot = $crmRoot . '/custom/Espo/Custom/Hooks';
$stamp = date('Ymd-His');
$quarantine = $crmRoot . '/backup_dev/hooks_quarantine/' . $stamp;

if (!is_dir($hooksRoot)) {
    fwrite(STDERR, "ERRORE: Hooks non trovato: {$hooksRoot}\n");
    exit(1);
}

echo "=== Fix hook duplicati (PHP) ===\n";
echo "Quarantena: {$quarantine}\n\n";

mkdir($quarantine, 0755, true);

$moved = 0;

$quarantineFile = static function (string $path) use ($hooksRoot, $quarantine, &$moved): void {
    if (!is_file($path)) {
        return;
    }

    $rel = substr($path, strlen($hooksRoot) + 1);
    $dest = $quarantine . '/' . $rel;
    $destDir = dirname($dest);

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    rename($path, $dest);
    echo "QUARANTINE {$rel}\n";
    $moved++;
};

// Backup/copy in Hooks
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($hooksRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $basename = $fileInfo->getFilename();

    if (!str_ends_with(strtolower($basename), '.php')) {
        continue;
    }

    if (
        preg_match('/\.(bak|old|backup|orig|save|copy)(\.\d+)?$/i', $basename) ||
        preg_match('/^(backup|copy|old|tmp|~)/i', $basename)
    ) {
        $quarantineFile($fileInfo->getPathname());
    }
}

// InvitoAFatturare: rimuovi vecchio BeforeSave.php (classe errata / duplicato)
$legacyInvitoFiles = [
    $hooksRoot . '/InvitoAFatturare/BeforeSave.php',
    $hooksRoot . '/InvitoAFatturare/BeforeSave.php.bak',
    $hooksRoot . '/InvitoAFatturare/BeforeSave.bak.php',
    $hooksRoot . '/InvitoAFatturare/backup-BeforeSave.php',
];

foreach ($legacyInvitoFiles as $legacyFile) {
    $quarantineFile($legacyFile);
}

$invitoCanonical = $hooksRoot . '/InvitoAFatturare/InvitoBeforeSave.php';

if (!is_file($invitoCanonical)) {
    fwrite(STDERR, "ERRORE: manca InvitoBeforeSave.php — rieseguire deploy.\n");
    exit(1);
}

echo "\nFile spostati in quarantena: {$moved}\n";

echo "\n=== Verifica nomi file/classe ===\n";
passthru('php ' . escapeshellarg($crmRoot . '/tools/diagnose-duplicate-hooks.php'), $diagExit);

echo "\n=== Pulizia cache hook ===\n";
chdir($crmRoot);
passthru('php clear_cache.php 2>/dev/null');
$cacheDir = $crmRoot . '/data/cache';

if (is_dir($cacheDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileInfo) {
        if ($fileInfo->isDir()) {
            @rmdir($fileInfo->getPathname());
        } else {
            @unlink($fileInfo->getPathname());
        }
    }
}

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "opcache_reset OK\n";
}

echo "\nPoi: php tools/diagnose-appuntamento-save.php\n";

exit($diagExit === 0 ? 0 : 1);
