<?php

// =============================================================================
// VERSIONE: 1.0.0
// DATA: 2026-05-27
// FILE: tools/sync-custom-prod-repo.php
//
// Allinea custom produzione <-> repository GitHub (modifiche fatte a mano in prod).
//
// COMANDI:
//   status       Confronto produzione vs branch GitHub (o cartella locale)
//   export-delta Esporta solo file diversi / solo in produzione (per commit su GitHub)
//   apply-delta  Applica un export-delta nel working tree Git (con backup)
//
// ESEMPI (sul server CRM):
//   cd ~/public_html/crm/mec-group
//   php tools/sync-custom-prod-repo.php status
//   php tools/sync-custom-prod-repo.php export-delta
//
// ESEMPI (sul PC con clone Git):
//   php tools/sync-custom-prod-repo.php status --local-repo=/path/to/ESPOCRM
//   php tools/sync-custom-prod-repo.php apply-delta exports/sync/delta-20260527-120000
//
// OPZIONI:
//   --branch=NAME
//   --repo=owner/name
//   --local-repo=PATH     usa repo locale invece di scaricare GitHub
//   --limit=N             limita righe in output status (default 80)
// =============================================================================

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Eseguire da CLI.\n";
    exit(1);
}

main($argv);

function main(array $argv): void
{
    $command = $argv[1] ?? 'help';

    $options = parseOptions($argv);
    $config = loadConfig();
    $crmRoot = findEspoRoot();
    $config = mergeOptionsIntoConfig($config, $options);

    switch ($command) {
        case 'status':
            runStatus($crmRoot, $config, $options);
            break;
        case 'export-delta':
            runExportDelta($crmRoot, $config, $options);
            break;
        case 'apply-delta':
            $deltaPath = $argv[2] ?? '';
            runApplyDelta($crmRoot, $deltaPath, $options);
            break;
        case 'help':
        default:
            printHelp();
            break;
    }
}

function printHelp(): void
{
    echo <<<TXT
sync-custom-prod-repo.php — allinea produzione e GitHub

  php tools/sync-custom-prod-repo.php status
  php tools/sync-custom-prod-repo.php export-delta
  php tools/sync-custom-prod-repo.php apply-delta exports/sync/delta-YYYYMMDD-HHMMSS

Config: tools/sync-custom-prod-repo.config.json

TXT;
}

function parseOptions(array $argv): array
{
    $options = [
        'limit' => 80,
    ];

    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--branch=')) {
            $options['branch'] = substr($arg, 9);
        } elseif (str_starts_with($arg, '--repo=')) {
            $options['repo'] = substr($arg, 7);
        } elseif (str_starts_with($arg, '--local-repo=')) {
            $options['local_repo'] = substr($arg, 13);
        } elseif (str_starts_with($arg, '--limit=')) {
            $options['limit'] = (int) substr($arg, 8);
        }
    }

    return $options;
}

function loadConfig(): array
{
    $path = __DIR__ . '/sync-custom-prod-repo.config.json';

    if (!is_file($path)) {
        fail("Config mancante: {$path}");
    }

    $config = json_decode(file_get_contents($path), true);

    if (!is_array($config)) {
        fail('Config JSON non valido.');
    }

    return $config;
}

function mergeOptionsIntoConfig(array $config, array $options): array
{
    if (!empty($options['branch'])) {
        $config['github']['branch'] = $options['branch'];
    }

    if (!empty($options['repo'])) {
        $config['github']['repository'] = $options['repo'];
    }

    if (!empty($options['local_repo'])) {
        $config['local_repo'] = realpath($options['local_repo']) ?: $options['local_repo'];
    }

    $config['limit'] = $options['limit'] ?? 80;

    return $config;
}

function findEspoRoot(): string
{
    $candidates = [getcwd(), __DIR__, dirname(__DIR__)];

    foreach ($candidates as $candidate) {
        $real = realpath($candidate);

        if (!$real) {
            continue;
        }

        if (is_dir($real . '/custom/Espo/Custom')) {
            return $real;
        }
    }

    fail('Root EspoCRM non trovata. Eseguire dalla root del CRM.');
}

function runStatus(string $crmRoot, array $config, array $options): void
{
    $repoRoot = resolveRepoRoot($crmRoot, $config);
    $prodIndex = buildFileIndex($crmRoot, $config, 'production');
    $repoIndex = buildFileIndex($repoRoot, $config, 'repo');

    $onlyProd = array_diff_key($prodIndex, $repoIndex);
    $onlyRepo = array_diff_key($repoIndex, $prodIndex);
    $common = array_intersect_key($prodIndex, $repoIndex);

    $different = [];
    $identical = 0;

    foreach ($common as $path => $prodMeta) {
        if ($prodMeta['sha256'] === $repoIndex[$path]['sha256']) {
            $identical++;
            continue;
        }

        $different[$path] = $prodMeta;
    }

    echo "=== SYNC PRODUZIONE <-> REPO ===\n";
    echo "Produzione: {$crmRoot}\n";
    echo "Repository: {$repoRoot}\n";
    echo "Branch: {$config['github']['branch']}\n";
    echo "\n";
    echo "Identici:     {$identical}\n";
    echo "Diversi:      " . count($different) . "\n";
    echo "Solo prod:    " . count($onlyProd) . "\n";
    echo "Solo repo:    " . count($onlyRepo) . "\n";
    echo "\n";

    printPathList('DIVERSI (prod vs repo)', array_keys($different), $config['limit']);
    printPathList('SOLO IN PRODUZIONE (da portare su GitHub)', array_keys($onlyProd), $config['limit']);
    printPathList('SOLO IN REPO (da deployare in prod o rimossi in prod)', array_keys($onlyRepo), $config['limit']);

    $manifestDir = $crmRoot . '/exports/sync';
    if (!is_dir($manifestDir)) {
        mkdir($manifestDir, 0755, true);
    }

    $ts = date('Ymd-His');
    $manifestPath = "{$manifestDir}/status-{$ts}.json";
    file_put_contents($manifestPath, json_encode([
        'generatedAt' => date('c'),
        'crmRoot' => $crmRoot,
        'repoRoot' => $repoRoot,
        'branch' => $config['github']['branch'],
        'counts' => [
            'identical' => $identical,
            'different' => count($different),
            'onlyProduction' => count($onlyProd),
            'onlyRepo' => count($onlyRepo),
        ],
        'different' => array_keys($different),
        'onlyProduction' => array_keys($onlyProd),
        'onlyRepo' => array_keys($onlyRepo),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    echo "\nManifest: {$manifestPath}\n";
    echo "\nProssimo passo: php tools/sync-custom-prod-repo.php export-delta\n";
}

function runExportDelta(string $crmRoot, array $config, array $options): void
{
    $repoRoot = resolveRepoRoot($crmRoot, $config);
    $prodIndex = buildFileIndex($crmRoot, $config, 'production');
    $repoIndex = buildFileIndex($repoRoot, $config, 'repo');

    $toExport = [];

    foreach ($prodIndex as $path => $meta) {
        if (!isset($repoIndex[$path])) {
            $toExport[$path] = $meta;
            continue;
        }

        if ($repoIndex[$path]['sha256'] !== $meta['sha256']) {
            $toExport[$path] = $meta;
        }
    }

    if ($toExport === []) {
        echo "Nessuna differenza: produzione e repo già allineati (nei percorsi configurati).\n";
        return;
    }

    $ts = date('Ymd-His');
    $deltaDir = $crmRoot . "/exports/sync/delta-{$ts}";
    $backupDir = $crmRoot . "/exports/sync/delta-{$ts}-repo-backup";

    mkdir($deltaDir, 0755, true);
    mkdir($backupDir, 0755, true);

    foreach ($toExport as $path => $meta) {
        $target = $deltaDir . '/' . $path;
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        copy($meta['absolute'], $target);

        $repoFile = $repoRoot . '/' . $path;
        if (is_file($repoFile)) {
            $backupTarget = $backupDir . '/' . $path;
            $backupTargetDir = dirname($backupTarget);
            if (!is_dir($backupTargetDir)) {
                mkdir($backupTargetDir, 0755, true);
            }
            copy($repoFile, $backupTarget);
        }
    }

    $zipPath = $crmRoot . "/exports/sync/delta-{$ts}.zip";
    createZipFromDirectory($deltaDir, $zipPath);

    $manifest = [
        'version' => '1.0.0',
        'generatedAt' => date('c'),
        'crmRoot' => $crmRoot,
        'repoRoot' => $repoRoot,
        'branch' => $config['github']['branch'],
        'fileCount' => count($toExport),
        'deltaDir' => $deltaDir,
        'zipPath' => $zipPath,
        'files' => [],
    ];

    foreach ($toExport as $path => $meta) {
        $manifest['files'][] = [
            'path' => $path,
            'sha256' => $meta['sha256'],
        ];
    }

    $manifestPath = "{$deltaDir}/manifest.json";
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    echo "Export delta completato.\n";
    echo "File: " . count($toExport) . "\n";
    echo "Cartella: {$deltaDir}\n";
    echo "ZIP: {$zipPath}\n";
    echo "Manifest: {$manifestPath}\n";
    echo "\n";
    echo "Su PC con Git:\n";
    echo "  git pull\n";
    echo "  php tools/sync-custom-prod-repo.php apply-delta {$deltaDir}\n";
    echo "  git add custom client/custom\n";
    echo "  git commit -m \"sync: allineamento da produzione\"\n";
}

function runApplyDelta(string $crmRoot, string $deltaPath, array $options): void
{
    if ($deltaPath === '') {
        fail('Specificare il percorso: apply-delta exports/sync/delta-YYYYMMDD-HHMMSS');
    }

    $deltaPath = realpath($deltaPath);

    if (!$deltaPath || !is_dir($deltaPath)) {
        fail("Cartella delta non trovata: {$deltaPath}");
    }

    $manifestPath = $deltaPath . '/manifest.json';
    $files = [];

    if (is_file($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        foreach ($manifest['files'] ?? [] as $item) {
            $files[] = $item['path'];
        }
    }

    if ($files === []) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($deltaPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->getFilename() === 'manifest.json') {
                continue;
            }

            $relative = ltrim(str_replace($deltaPath, '', $item->getPathname()), DIRECTORY_SEPARATOR);
            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }
    }

    $backupDir = $crmRoot . '/exports/sync/apply-backup-' . date('Ymd-His');
    mkdir($backupDir, 0755, true);

    $applied = 0;

    foreach ($files as $path) {
        $source = $deltaPath . '/' . $path;
        $target = $crmRoot . '/' . $path;

        if (!is_file($source)) {
            continue;
        }

        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (is_file($target)) {
            $backupTarget = $backupDir . '/' . $path;
            $backupTargetDir = dirname($backupTarget);
            if (!is_dir($backupTargetDir)) {
                mkdir($backupTargetDir, 0755, true);
            }
            copy($target, $backupTarget);
        }

        copy($source, $target);
        $applied++;
    }

    echo "Apply delta completato.\n";
    echo "File applicati: {$applied}\n";
    echo "Backup locale precedente: {$backupDir}\n";
    echo "\nVerifica con: git status\n";
}

function resolveRepoRoot(string $crmRoot, array $config): string
{
    if (!empty($config['local_repo']) && is_dir($config['local_repo'])) {
        return $config['local_repo'];
    }

    $repo = $config['github']['repository'];
    $branch = $config['github']['branch'];
    $cacheDir = $crmRoot . '/exports/sync/.cache';
    $cacheKey = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $branch);
    $extracted = "{$cacheDir}/repo-{$cacheKey}";

    if (is_dir($extracted . '/custom/Espo/Custom')) {
        return $extracted;
    }

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $tmp = sys_get_temp_dir() . '/espo-sync-' . uniqid('', true);
    mkdir($tmp, 0700, true);

    $archiveUrl = "https://github.com/{$repo}/archive/refs/heads/" . rawurlencode($branch) . '.tar.gz';
    $tarPath = "{$tmp}/branch.tar.gz";

    echo "Download branch {$branch} da GitHub...\n";

    $ch = curl_init($archiveUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FAILONERROR => true,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code >= 400) {
        fail("Download GitHub fallito (HTTP {$code}). Branch: {$branch}");
    }

    file_put_contents($tarPath, $body);

    $phar = new PharData($tarPath);
    $phar->extractTo($tmp);

    $src = findExtractedRepoRoot($tmp);

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    rename($src, $extracted);
    removeDirectory($tmp);

    return $extracted;
}

function findExtractedRepoRoot(string $tmp): string
{
    foreach (scandir($tmp) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $tmp . '/' . $entry;

        if (is_dir($path . '/custom/Espo/Custom')) {
            return $path;
        }
    }

    fail('Archivio GitHub estratto ma custom/Espo/Custom non trovato.');
}

function buildFileIndex(string $root, array $config, string $label): array
{
    $index = [];

    foreach ($config['scanRoots'] as $scanRoot) {
        $absolute = $root . '/' . $scanRoot;

        if (!is_dir($absolute)) {
            continue;
        }

        scanDirectoryIntoIndex($absolute, $root, $scanRoot, $config, $index);
    }

    if ($label === 'production' && !empty($config['prodToRepoPathAliases'])) {
        foreach ($config['prodToRepoPathAliases'] as $alias) {
            $prodPrefix = $alias['prodPrefix'];
            $repoPrefix = $alias['repoPrefix'];
            $absolute = $root . '/' . $prodPrefix;

            if (!is_dir($absolute)) {
                continue;
            }

            scanDirectoryIntoIndex($absolute, $root, $repoPrefix, $config, $index, true);
        }
    }

    return $index;
}

function scanDirectoryIntoIndex(
    string $absolute,
    string $root,
    string $relativePrefix,
    array $config,
    array &$index,
    bool $overwrite = false
): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $fullPath = $item->getPathname();
        $innerRelative = ltrim(str_replace($absolute, '', $fullPath), DIRECTORY_SEPARATOR);
        $repoRelative = trim($relativePrefix . '/' . $innerRelative, '/');
        $repoRelative = str_replace(DIRECTORY_SEPARATOR, '/', $repoRelative);

        if (shouldExclude($repoRelative, $config)) {
            continue;
        }

        if (!$overwrite && isset($index[$repoRelative])) {
            continue;
        }

        $index[$repoRelative] = [
            'absolute' => $fullPath,
            'sha256' => hash_file('sha256', $fullPath),
        ];
    }
}

function shouldExclude(string $relativePath, array $config): bool
{
    $basename = basename($relativePath);

    foreach ($config['excludePatterns'] ?? [] as $pattern) {
        if (fnmatch($pattern, $basename, FNM_CASEFOLD)) {
            return true;
        }
    }

    foreach ($config['excludePathContains'] ?? [] as $needle) {
        if (str_contains($relativePath, $needle)) {
            return true;
        }
    }

    foreach ($config['excludePathRegex'] ?? [] as $regex) {
        if (@preg_match($regex, $relativePath) === 1) {
            return true;
        }
    }

    return false;
}

function printPathList(string $title, array $paths, int $limit): void
{
    echo "--- {$title} ---\n";

    if ($paths === []) {
        echo "(nessuno)\n\n";
        return;
    }

    sort($paths);
    $shown = array_slice($paths, 0, $limit);

    foreach ($shown as $path) {
        echo "  {$path}\n";
    }

    if (count($paths) > $limit) {
        echo "  ... altri " . (count($paths) - $limit) . " (usa --limit=N)\n";
    }

    echo "\n";
}

function createZipFromDirectory(string $sourceDir, string $zipPath): void
{
    if (!class_exists('ZipArchive')) {
        echo "ZipArchive non disponibile, skip ZIP.\n";
        return;
    }

    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fail("Impossibile creare ZIP: {$zipPath}");
    }

    $sourceDir = realpath($sourceDir);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $relative = ltrim(str_replace($sourceDir, '', $path), DIRECTORY_SEPARATOR);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

        if ($item->isDir()) {
            $zip->addEmptyDir($relative);
            continue;
        }

        $zip->addFile($path, $relative);
    }

    $zip->close();
}

function removeDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
            continue;
        }

        unlink($item->getPathname());
    }

    rmdir($dir);
}

function fail(string $message): void
{
    fwrite(STDERR, "ERRORE: {$message}\n");
    exit(1);
}
