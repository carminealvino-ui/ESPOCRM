<?php
/**
 * Verifica hotfix Crea Contratto (division by zero formula Quote).
 * Uso: php tools/verify-create-contratto-hotfix.php
 */

$root = dirname(__DIR__);
$errors = [];

$createContratto = $root . '/custom/Espo/Custom/Actions/Opportunity/CreateContratto.php';
$formula = $root . '/custom/Espo/Custom/Resources/metadata/formula/Quote.json';

if (!is_readable($createContratto)) {
    $errors[] = 'File mancante: CreateContratto.php';
} else {
    $php = file_get_contents($createContratto);

    if (preg_match("/createEntity\s*\(\s*['\"]Quote['\"]/", $php)) {
        $errors[] = 'CreateContratto.php usa ancora createEntity(Quote) — causa division by zero';
    }

    if (!preg_match("/getNewEntity\s*\(\s*['\"]Quote['\"]/", $php)) {
        $errors[] = 'CreateContratto.php non usa getNewEntity(Quote)';
    }

    if (!preg_match("/saveEntity\s*\(\s*\\\$quote\s*,\s*\[\s*'skipHooks'\s*=>\s*true/", $php)) {
        $errors[] = 'Primo saveEntity($quote) senza skipHooks => true';
    }
}

if (!is_readable($formula)) {
    $errors[] = 'File mancante: formula/Quote.json';
} else {
    $data = json_decode(file_get_contents($formula), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'formula/Quote.json JSON non valido: ' . json_last_error_msg();
    } else {
        $script = $data['beforeSaveCustomScript'] ?? '';

        if (!str_contains($script, "string\\contains(hookVersion, 'CreateContratto')")) {
            $errors[] = 'Formula Quote senza guard CreateContratto su tax/margini';
        }

        if (!preg_match('/amount\s*!=\s*null\s*&&\s*amount\s*!=\s*0/', $script)) {
            $errors[] = 'Formula Quote senza guard amount != 0 nel calcolo taxRate';
        }
    }
}

if ($errors) {
    fwrite(STDERR, "VERIFICA FALLITA:\n");

    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }

    exit(1);
}

echo "OK: hotfix Crea Contratto presente in locale.\n";
exit(0);
