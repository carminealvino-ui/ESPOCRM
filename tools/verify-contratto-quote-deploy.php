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
    'custom:views/quote/record/panels/finanziamento',
    'Finanziamento panel con view definita',
    $ok,
    $errors
);

check_contains(
    "{$base}/client/custom/src/views/quote/record/panels/finanziamento.js",
    'this.fieldList.push(col.field)',
    'Finanziamento panel registra campi in fieldList',
    $ok,
    $errors
);

check_not_contains(
    "{$base}/client/custom/src/views/quote/record/panels/finanziamento.js",
    'afterRender: function',
    'Finanziamento panel senza render manuale campi',
    $ok,
    $errors
);

check_not_contains(
    "{$base}/client/custom/src/views/quote/record/panels/finanziamento.js",
    'importoSaldo',
    'Importo saldo fuori dal pannello finanziamento',
    $ok,
    $errors
);

check_contains(
    "{$base}/custom/Espo/Custom/Resources/layouts/Quote/detail.json",
    '"name": "importoSaldo"',
    'Importo saldo in Overview detail',
    $ok,
    $errors
);

check_not_contains(
    "{$base}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json",
    '"layout": "finanziamento"',
    'Finanziamento senza layout senza view',
    $ok,
    $errors
);

check_contains(
    "{$base}/custom/Espo/Custom/Resources/metadata/entityDefs/QuoteItem.json",
    '"itemNotReadOnly": true',
    'QuoteItem prezzoCodice editabile in riga',
    $ok,
    $errors
);

check_contains(
    "{$base}/client/custom/src/handlers/quote/catalog-prices.js",
    'getItemCatalogPrices',
    'Handler catalog-prices client',
    $ok,
    $errors
);

check_contains(
    "{$base}/client/custom/src/views/quote/fields/item-list.js",
    'custom:views/quote/record/item',
    'item-list usa custom item view',
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
    "{$base}/client/custom/src/handlers/quote/refresh-provvigioni-on-save.js",
    'refreshProvvigioniUi',
    'Handler refresh provvigioni dopo save',
    $ok,
    $errors
);

check_contains(
    "{$base}/custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php",
    'itemList',
    'Hook provvigioni su modifica articoli',
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

check_contains(
    "{$base}/custom/Espo/Custom/Services/QuotePricingCalculator.php",
    'resolveMinusPlusForQuote',
    'QuotePricingCalculator B2C minusPlus',
    $ok,
    $errors
);

check_contains(
    "{$base}/custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php",
    'syncOnBeforeSave',
    'Hook SyncContractPricing',
    $ok,
    $errors
);

check_contains(
    "{$base}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json",
    '"prezzoCodiceIvaInclusa"',
    'Campo prezzoCodiceIvaInclusa su Quote',
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

check_contains(
    "{$base}/custom/Espo/Custom/Resources/metadata/scopes/RegolaProvvigionale.json",
    '"entity": true',
    'Entity RegolaProvvigionale in scopes',
    $ok,
    $errors
);

check_contains(
    "{$base}/client/custom/src/views/quote/record/detail.js",
    'custom:views/quote/record/detail',
    'Client view quote/record/detail.js',
    $ok,
    $errors
);

check_contains(
    "{$base}/client/custom/src/views/quote/record/panels/items.js",
    'custom:views/quote/record/panels/items',
    'Client view quote/panels/items.js',
    $ok,
    $errors
);

$formulaFile = "{$base}/custom/Espo/Custom/Resources/metadata/formula/Quote.json";

if (!is_file($formulaFile)) {
    $errors[] = "MANCANTE: formula Quote.json";
} else {
    $formulaRaw = file_get_contents($formulaFile) ?: '';
    $formulaScript = $formulaRaw;

    $decoded = json_decode($formulaRaw, true);

    if (is_array($decoded) && isset($decoded['beforeSaveCustomScript'])) {
        $formulaScript = (string) $decoded['beforeSaveCustomScript'];
    }

    if (str_contains($formulaScript, 'math\\round') || str_contains($formulaRaw, 'math\\\\round')) {
        $errors[] = 'NON OK: formula Quote usa math\\round (deve essere number\\round)';
    } else {
        $ok[] = 'formula Quote senza math\\round';
    }

    if (
        !str_contains($formulaScript, 'number\\round')
        && !str_contains($formulaRaw, 'number\\\\round')
    ) {
        $errors[] = 'NON OK: formula Quote senza number\\round';
    } else {
        $ok[] = 'formula Quote con number\\round';
    }

    if (
        !str_contains($formulaScript, 'isTaxInclusive != true')
        && !str_contains($formulaRaw, 'isTaxInclusive != true')
    ) {
        $errors[] = 'NON OK: formula Quote non delega minusPlus B2C a PHP';
    } else {
        $ok[] = 'formula Quote minusPlus B2C delegato a QuotePricingCalculator';
    }
}

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
