#!/usr/bin/env php
<?php
/**
 * Verifica deploy fix contratto Quote su server produzione.
 *
 *   php tools/verify-contratto-quote-deploy.php
 */

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

$errors = [];
$ok = [];

function check_contains(string $file, string $needle, string $label, array &$ok, array &$errors): void
{
    $path = $file;

    if (!is_file($path)) {
        $errors[] = "MANCANTE: {$label} ({$file})";
        return;
    }

    $content = file_get_contents($path);

    if ($content === false || !str_contains($content, $needle)) {
        $errors[] = "NON OK: {$label} — atteso contenuto «{$needle}» in {$file}";
        return;
    }

    $ok[] = $label;
}

function check_not_contains(string $file, string $needle, string $label, array &$ok, array &$errors): void
{
    $path = $file;

    if (!is_file($path)) {
        $errors[] = "MANCANTE: {$label} ({$file})";
        return;
    }

    $content = file_get_contents($path);

    if ($content !== false && str_contains($content, $needle)) {
        $errors[] = "NON OK: {$label} — ancora presente «{$needle}» in {$file}";
        return;
    }

    $ok[] = $label;
}

$base = rtrim($root, '/');

check_contains(
    "{$base}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json",
    '"orderBy": "dateQuoted"',
    'Ordinamento elenco dateQuoted',
    $ok,
    $errors
);

check_contains(
    "{$base}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json",
    '"defaultSortBy": "dateQuoted"',
    'clientDefs defaultSortBy dateQuoted',
    $ok,
    $errors
);

check_contains(
    "{$base}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json",
    '"name": "finanziamento"',
    'Finanziamento in bottomPanels clientDefs',
    $ok,
    $errors
);

check_contains(
    "{$base}/custom/Espo/Custom/Resources/layouts/Quote/bottomPanelsDetail.json",
    '"finanziamento"',
    'bottomPanelsDetail.json con finanziamento',
    $ok,
    $errors
);

check_not_contains(
    "{$base}/custom/Espo/Custom/Resources/layouts/Quote/detail.json",
    '"label": "Finanziamento"',
    'detail.json senza pannello Finanziamento in alto',
    $ok,
    $errors
);

check_contains(
    "{$base}/custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php",
    'refreshQuoteTotaleProvvigioni',
    'Hook AfterSaveTotaleProvvigioni',
    $ok,
    $errors
);

check_contains(
    "{$base}/custom/Espo/Custom/Services/ProvvigioneManager.php",
    'importoConsolidato',
    'ProvvigioneManager usa importoConsolidato',
    $ok,
    $errors
);

check_not_contains(
    "{$base}/custom/Espo/Custom/Hooks/Quote/BeforeSave.php",
    'function afterSave',
    'BeforeSave senza afterSave legacy',
    $ok,
    $errors
);

$legacy = [
    "{$base}/custom/Espo/Custom/Hooks/Quote/SyncTotaleProvvigioni.php",
    "{$base}/custom/Espo/Custom/Services/QuoteTotaleProvvigioniService.php",
];

foreach ($legacy as $file) {
    if (is_file($file)) {
        $errors[] = 'LEGACY ancora presente: ' . basename($file);
    } else {
        $ok[] = 'Assente legacy ' . basename($file);
    }
}

echo "=== Verifica deploy Contratto Quote ===\n";
echo "Root: {$base}\n\n";

foreach ($ok as $line) {
    echo "OK   {$line}\n";
}

foreach ($errors as $line) {
    echo "ERR  {$line}\n";
}

echo "\n";

if ($errors !== []) {
    echo "RISULTATO: DEPLOY INCOMPLETO (" . count($errors) . " errori)\n";
    exit(1);
}

echo "RISULTATO: DEPLOY OK\n";
exit(0);
