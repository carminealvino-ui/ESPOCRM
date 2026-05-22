<?php

// ========================================
// VERSIONE: 1.0.0
// DATA: 2026-05-22
// AUTORE: CARMINE ALVINO + IA
// FILE:
// tools/export-custom-for-github.php
// ========================================
//
// OBIETTIVO:
// Creare un export completo della cartella custom per conservarlo
// su GitHub tra una sessione di sviluppo e la successiva.
//
// MODALITA SICURA:
// - di default crea uno ZIP locale in backup/hooks_cleanup
// - non contiene password
// - non contiene token GitHub
// - non modifica i file EspoCRM
//
// MODALITA GITHUB OPZIONALE:
// Se viene passato --upload-github, lo script carica lo ZIP su GitHub
// usando SOLO variabili ambiente:
//
// GITHUB_TOKEN       token GitHub con permesso contents:write
// GITHUB_REPOSITORY  es. carminealvino-ui/ESPOCRM
// GITHUB_BRANCH      es. main
//
// ESEMPIO:
// php tools/export-custom-for-github.php
//
// ESEMPIO UPLOAD:
// GITHUB_TOKEN="..." GITHUB_REPOSITORY="carminealvino-ui/ESPOCRM" GITHUB_BRANCH="main" \
// php tools/export-custom-for-github.php --upload-github
//
// ROLLBACK:
// cancellare lo ZIP generato.
//
// ========================================

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Questo script va eseguito da SSH/CLI.\n";
    exit(1);
}

main($argv);

function main(array $argv): void
{
    $uploadToGitHub = in_array('--upload-github', $argv, true);

    $rootPath = findEspoRoot();
    $customPath = $rootPath . DIRECTORY_SEPARATOR . 'custom';
    $backupPath = $rootPath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'hooks_cleanup';

    if (!is_dir($customPath)) {
        fail("Cartella custom non trovata: {$customPath}");
    }

    if (!is_dir($backupPath) && !mkdir($backupPath, 0755, true) && !is_dir($backupPath)) {
        fail("Impossibile creare cartella backup: {$backupPath}");
    }

    if (!class_exists('ZipArchive')) {
        fail('Estensione PHP ZipArchive non disponibile. Attivare zip o comprimere da cPanel.');
    }

    $timestamp = date('Ymd-His');
    $zipFileName = "custom-export-{$timestamp}.zip";
    $zipPath = $backupPath . DIRECTORY_SEPARATOR . $zipFileName;

    $fileCount = zipDirectory($customPath, $zipPath, $rootPath);
    $sha256 = hash_file('sha256', $zipPath);

    $manifest = [
        'version' => '1.0.0',
        'generatedAt' => date('c'),
        'rootPath' => $rootPath,
        'sourcePath' => $customPath,
        'zipPath' => $zipPath,
        'fileCount' => $fileCount,
        'sha256' => $sha256,
    ];

    $manifestPath = $backupPath . DIRECTORY_SEPARATOR . "custom-export-{$timestamp}.json";
    file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );

    echo "Export creato correttamente.\n";
    echo "ZIP: {$zipPath}\n";
    echo "Manifest: {$manifestPath}\n";
    echo "File inclusi: {$fileCount}\n";
    echo "SHA256: {$sha256}\n";

    if ($uploadToGitHub) {
        uploadZipToGitHub($zipPath, $zipFileName, $sha256);
    } else {
        echo "\n";
        echo "Upload GitHub non eseguito.\n";
        echo "Per caricare manualmente: scarica lo ZIP e caricalo nel repository GitHub.\n";
        echo "Per upload automatico: riesegui con --upload-github e variabili ambiente GitHub.\n";
    }
}

function findEspoRoot(): string
{
    $candidates = [
        getcwd(),
        __DIR__,
        dirname(__DIR__),
        dirname(__DIR__, 2),
    ];

    foreach ($candidates as $candidate) {
        $real = realpath($candidate);

        if (!$real) {
            continue;
        }

        if (is_dir($real . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'Espo')) {
            return $real;
        }
    }

    fail('Root EspoCRM non trovata. Eseguire dalla root del CRM.');
}

function zipDirectory(string $sourcePath, string $zipPath, string $rootPath): int
{
    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fail("Impossibile creare ZIP: {$zipPath}");
    }

    $sourcePath = realpath($sourcePath);
    $rootPath = realpath($rootPath);

    if (!$sourcePath || !$rootPath) {
        fail('Percorso sorgente non valido.');
    }

    $fileCount = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $relativePath = ltrim(str_replace($rootPath, '', $path), DIRECTORY_SEPARATOR);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        if ($item->isDir()) {
            $zip->addEmptyDir($relativePath);
            continue;
        }

        $zip->addFile($path, $relativePath);
        $fileCount++;
    }

    $zip->close();

    return $fileCount;
}

function uploadZipToGitHub(string $zipPath, string $zipFileName, string $sha256): void
{
    $token = getenv('GITHUB_TOKEN') ?: '';
    $repository = getenv('GITHUB_REPOSITORY') ?: 'carminealvino-ui/ESPOCRM';
    $branch = getenv('GITHUB_BRANCH') ?: 'main';
    $targetPath = getenv('GITHUB_TARGET_PATH') ?: "exports/custom/{$zipFileName}";

    if ($token === '') {
        fail('GITHUB_TOKEN mancante. Upload GitHub annullato.');
    }

    if (!function_exists('curl_init')) {
        fail('Estensione PHP cURL non disponibile. Upload GitHub annullato.');
    }

    $content = base64_encode(file_get_contents($zipPath));

    $payload = [
        'message' => "Export custom folder {$zipFileName}",
        'content' => $content,
        'branch' => $branch,
    ];

    $url = 'https://api.github.com/repos/' . $repository . '/contents/' . rawurlencode($targetPath);
    $url = str_replace('%2F', '/', $url);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/vnd.github+json',
            'Content-Type: application/json',
            'User-Agent: EspoCRM-Custom-Exporter',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        fail("Upload GitHub fallito. HTTP {$httpCode}. {$error}\n{$response}");
    }

    echo "\n";
    echo "Upload GitHub completato.\n";
    echo "Repository: {$repository}\n";
    echo "Branch: {$branch}\n";
    echo "Path: {$targetPath}\n";
    echo "SHA256 locale: {$sha256}\n";
}

function fail(string $message): void
{
    fwrite(STDERR, "ERRORE: {$message}\n");
    exit(1);
}
