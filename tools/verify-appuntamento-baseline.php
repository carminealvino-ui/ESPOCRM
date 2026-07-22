<?php

/**
 * Verifica che il repo contenga la baseline Appuntamento corretta.
 * Eseguire PRIMA di ogni deploy e DOPO ogni merge su main.
 *
 *   php tools/verify-appuntamento-baseline.php
 *   php tools/verify-appuntamento-baseline.php --path=/path/to/ESPOCRM
 *
 * Exit 0 = OK, exit 1 = repo contiene regressioni (bloccare deploy).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$root = getcwd() ?: '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--path=')) {
        $root = substr($arg, 7);
    }
}

$entityDefsPath = $root . '/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json';
$hooksPath = $root . '/custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json';
$globalLogicPath = $root . '/custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php';

$errors = [];

if (!is_file($entityDefsPath)) {
    fail(['File mancante: ' . $entityDefsPath]);
}

$entityDefs = json_decode((string) file_get_contents($entityDefsPath), true);

if (!is_array($entityDefs)) {
    fail(['entityDefs/Appuntamento.json non è JSON valido']);
}

$sottostato = $entityDefs['fields']['sottostato']['options'] ?? [];
$esitoField = $entityDefs['fields']['esito'] ?? [];

$allowedSottostato = [
    '',
    'Pending',
    'Gestito',
    'Non Interessato',
    'Chiuso Positivamente',
    'Annullato',
    'Non Gestito',
    'Non Ricevuto',
    'Rifissato',
];

$legacySottostato = [
    'Fuori Target',
    'Solo Informazioni',
    'Infattibilità Tecnica',
    'Prodotto non Conforme',
    'Non Confermato',
];

foreach ($legacySottostato as $legacy) {
    if (in_array($legacy, $sottostato, true)) {
        $errors[] = "REGRESSIONE sottostato: valore legacy \"{$legacy}\" presente in entityDefs";
    }
}

$extra = array_diff($sottostato, $allowedSottostato);
$missing = array_diff($allowedSottostato, $sottostato);

if ($extra !== []) {
    $errors[] = 'Sottostato enum non conforme — valori extra: ' . implode(', ', $extra);
}

if ($missing !== []) {
    $errors[] = 'Sottostato enum incompleto — mancano: ' . implode(', ', $missing);
}

$esitoView = $esitoField['view'] ?? null;
if ($esitoView !== 'custom:views/fields/appuntamento-esito') {
    $errors[] = 'Esito senza view custom (atteso custom:views/fields/appuntamento-esito, trovato: '
        . ($esitoView ?? 'null') . ')';
}

$sottostatoView = $entityDefs['fields']['sottostato']['view'] ?? null;
if ($sottostatoView !== 'custom:views/fields/appuntamento-sottostato') {
    $errors[] = 'Sottostato senza view custom (atteso custom:views/fields/appuntamento-sottostato)';
}

if (is_file($hooksPath)) {
    $hooks = json_decode((string) file_get_contents($hooksPath), true);
    $beforeSave = $hooks['beforeSave'] ?? [];
    if (!isset($beforeSave['syncStatiEsito'])) {
        $errors[] = 'Hook SyncStatiEsito mancante in hooks/Appuntamento.json';
    }
} else {
    $errors[] = 'File mancante: hooks/Appuntamento.json';
}

if (is_file($globalLogicPath)) {
    $gl = file_get_contents($globalLogicPath);
    if (!is_string($gl) || !preg_match("/'1\\.7\\.1[7-9]'|'1\\.7\\.[2-9][0-9]'|'1\\.8\\./", $gl)) {
        $errors[] = 'GlobalLogic.php: hookVersion atteso >= 1.7.17';
    }
}

$syncRulesPath = $root . '/custom/Espo/Custom/Services/AppuntamentoStatiRules.php';
if (!is_file($syncRulesPath)) {
    $errors[] = 'Mancante: Services/AppuntamentoStatiRules.php';
}

$syncHookPath = $root . '/custom/Espo/Custom/Hooks/Appuntamento/SyncStatiEsito.php';
if (!is_file($syncHookPath)) {
    $errors[] = 'Mancante: Hooks/Appuntamento/SyncStatiEsito.php';
}

if ($errors === []) {
    echo "OK baseline Appuntamento\n";
    echo "  sottostato: " . count(array_filter($sottostato)) . " valori\n";
    echo "  esito view: {$esitoView}\n";
    echo "  SyncStatiEsito: presente\n";
    exit(0);
}

echo "ERRORE baseline Appuntamento — DEPLOY BLOCCATO\n\n";
foreach ($errors as $error) {
    echo "  - {$error}\n";
}
echo "\nAzione: export-delta da produzione → apply-delta → push main\n";
echo "Vedi REGOLE-PRODUZIONE/12-NO-DEPLOY-SENZA-REPO-ALLINEATO.md\n";
exit(1);

/** @param list<string> $errors */
function fail(array $errors): void
{
    echo "ERRORE baseline Appuntamento — DEPLOY BLOCCATO\n\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}
