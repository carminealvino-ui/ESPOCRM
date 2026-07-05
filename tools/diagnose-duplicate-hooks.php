<?php
/**
 * Trova classi PHP duplicate e mismatch nome file/classe (Espo HookManager).
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
/** @var list<string> */
$nameMismatches = [];

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

    $namespace = trim($nsMatch[1]);
    $classShort = $classMatch[1];
    $fqcn = $namespace . '\\' . $classShort;
    $classes[$fqcn][] = $path;

    $expectedFromPath = pathToExpectedClass($path, $crmRoot);

    if ($expectedFromPath !== $fqcn) {
        $nameMismatches[] = "{$path}\n  atteso: {$expectedFromPath}\n  trovato: {$fqcn}";
    }

    if (!class_exists($fqcn, false)) {
        // Autoload non ancora caricato: verifica sintassi base
        continue;
    }
}

function pathToExpectedClass(string $filePath, string $crmRoot): string
{
    $relative = preg_replace('#^' . preg_quote($crmRoot . '/', '#') . '#', '', $filePath);
    $relative = preg_replace('/\.php$/i', '', (string) $relative);
    $relative = preg_replace('/^(application|custom)[\/\\\\]/i', '', (string) $relative);

    return str_replace('/', '\\', (string) $relative);
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
    echo "[OK] Nessuna classe duplicata.\n";
}

if ($nameMismatches !== []) {
    echo "\n=== Mismatch nome file / classe (causa get_class_methods error) ===\n";

    foreach ($nameMismatches as $line) {
        echo "[MISMATCH] {$line}\n\n";
    }
} else {
    echo "[OK] Tutti i file hook hanno nome classe coerente col path.\n";
}

if ($suspiciousFiles !== []) {
    echo "\n=== File sospetti (backup/copy in Hooks) ===\n";

    foreach ($suspiciousFiles as $path) {
        echo "  - {$path}\n";
    }
} else {
    echo "\n[OK] Nessun file backup/copy in Hooks.\n";
}

$invitoCanonical = $hooksRoot . '/InvitoAFatturare/InvitoBeforeSave.php';
$invitoLegacy = $hooksRoot . '/InvitoAFatturare/BeforeSave.php';

echo "\n=== InvitoAFatturare ===\n";
echo is_file($invitoCanonical) ? "[OK] InvitoBeforeSave.php presente\n" : "[MANCA] InvitoBeforeSave.php\n";
echo is_file($invitoLegacy) ? "[ERRORE] BeforeSave.php ancora presente (rimuovere)\n" : "[OK] BeforeSave.php assente\n";

$exitCode = ($duplicateCount > 0 || $nameMismatches !== [] || is_file($invitoLegacy)) ? 1 : 0;

exit($exitCode);
