#!/usr/bin/env php
<?php
/**
 * Verifica che il fix annullato→admin sia installato (hook 1.7.11).
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

$errors = [];

$globalLogic = 'custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php';
$hooksJson = 'custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json';
$afterSave = 'custom/Espo/Custom/Hooks/Appuntamento/NotHeldAdminAssignAfterSave.php';
$sync = 'custom/Espo/Custom/Services/AppuntamentoGoogleSync.php';

foreach ([$globalLogic, $hooksJson, $afterSave, $sync] as $path) {
    if (!is_file($path)) {
        $errors[] = "Manca file: {$path}";
    }
}

if (is_file($globalLogic)) {
    $content = file_get_contents($globalLogic);

    if ($content !== false && !str_contains($content, '1.7.11')) {
        $errors[] = 'GlobalLogic.php non contiene hookVersion 1.7.11 (deploy non applicato?)';
    }
}

if (is_file($hooksJson)) {
    $content = file_get_contents($hooksJson);

    if ($content !== false && !str_contains($content, 'NotHeldAdminAssignAfterSave')) {
        $errors[] = 'hooks/Appuntamento.json senza NotHeldAdminAssignAfterSave';
    }
}

if (is_file($sync)) {
    $content = file_get_contents($sync);

    if ($content !== false && !str_contains($content, 'resolvePrimarySystemAdminUserId')) {
        $errors[] = 'AppuntamentoGoogleSync.php senza resolvePrimarySystemAdminUserId';
    }

    if ($content !== false && str_contains($content, 'UpdateBuilder')) {
        $errors[] = 'AppuntamentoGoogleSync.php usa ancora UpdateBuilder (usa PDO diretto)';
    }
}

if ($errors !== []) {
    fwrite(STDERR, "VERIFICA FALLITA:\n");

    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }

    exit(1);
}

fwrite(STDOUT, "OK fix annullato→admin installato (hook 1.7.11)\n");
