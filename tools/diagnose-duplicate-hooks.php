<?php
/**
 * Trova classi PHP duplicate sotto custom/Espo/Custom/Hooks (causa fatal "already in use").
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/diagnose-duplicate-hooks.php
 */

declare(strict_types=1);

$crmRoot = dirname(__DIR__);
$hooksRoot = $crmRoot . '/custom/Espo/Custom/Hooks';

if (!is_dir($hooksRoot)) {
    fwrite(STDERR, "ERRORE: cartella Hooks non trovata: {$hooksRoot}\n");
    exit(1);
}

echo "=== Diagnostica hook duplicati ===\n";
echo "Root: {$hooksRoot}\n\n";

/** @var array<string, list<string>> */
$classes = [];
/** @var list<string> */
$suspiciousFiles = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($hooksRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();
    $basename = $fileInfo->getFilename();

    if (!str_ends_with(strtolower($basename), '.php')) {
        continue;
    }

    if (
        preg_match('/\.(bak|old|backup|orig|save|copy)(\.\d+)?$/i', $basename) ||
        preg_match('/^(backup|copy|old|tmp|~)/i', $basename)
    ) {
        $suspiciousFiles[] = $path;
    }

    $content = @file_get_contents($path);

    if ($content === false) {
        continue;
    }

    if (!preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
        continue;
    }

    if (!preg_match('/\bclass\s+([A-Za-z0-9_]+)/', $content, $classMatch)) {
        continue;
    }

    $fqcn = trim($nsMatch[1]) . '\\' . $classMatch[1];
    $classes[$fqcn][] = $path;
}

$duplicateCount = 0;

foreach ($classes as $fqcn => $paths) {
    if (count($paths) < 2) {
        continue;
    }

    $duplicateCount++;
    echo "[DUPLICATO] {$fqcn}\n";

    foreach ($paths as $path) {
        echo "  - {$path}\n";
    }

    echo "\n";
}

if ($duplicateCount === 0) {
    echo "[OK] Nessuna classe duplicata trovata.\n";
} else {
    echo "Trovate {$duplicateCount} classi duplicate.\n";
}

if ($suspiciousFiles !== []) {
    echo "\n=== File sospetti (backup/copy in Hooks) ===\n";

    foreach ($suspiciousFiles as $path) {
        echo "  - {$path}\n";
    }

    echo "\nSpostare in backup_dev/hooks_quarantine/ e ricostruire cache.\n";
} else {
    echo "\n[OK] Nessun file backup/copy in Hooks.\n";
}

echo "\nVerifica InvitoAFatturare: classe deve essere InvitoBeforeSave\n";
$invitoFile = $hooksRoot . '/InvitoAFatturare/BeforeSave.php';

if (is_file($invitoFile)) {
    $content = (string) file_get_contents($invitoFile);
    echo str_contains($content, 'class InvitoBeforeSave')
        ? "[OK] InvitoBeforeSave presente\n"
        : "[MANCA] Rinominare class BeforeSave -> InvitoBeforeSave\n";
} else {
    echo "[--] File InvitoAFatturare/BeforeSave.php assente\n";
}

exit($duplicateCount > 0 ? 1 : 0);
