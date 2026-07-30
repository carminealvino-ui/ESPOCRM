<?php
/**
 * Bonifica label/layout "To-Do verifica installazione" sul Contratto (Quote).
 *
 * Sintomo: label ripetuta più volte sotto Dettagli (layout con campo duplicato
 * o i18n corrotto dopo patch ripetute).
 *
 * Uso:
 *   php tools/fix-quote-todo-verifica-label.php
 *   php tools/fix-quote-todo-verifica-label.php --dry-run
 */

declare(strict_types=1);

$root = rtrim(getenv('CRM_ROOT') ?: (isset($argv[1]) && !str_starts_with((string) $argv[1], '--') ? $argv[1] : getcwd()), '/');
$dryRun = in_array('--dry-run', $argv, true);

$layoutPath = $root . '/custom/Espo/Custom/Resources/layouts/Quote/detail.json';
$i18nPath = $root . '/custom/Espo/Custom/Resources/i18n/it_IT/Quote.json';
$metaPath = $root . '/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json';

function loadJson(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "ERR file mancante: {$path}\n");
        exit(1);
    }

    $data = json_decode((string) file_get_contents($path), true);

    if (!is_array($data)) {
        fwrite(STDERR, "ERR JSON non valido: {$path}\n");
        exit(1);
    }

    return $data;
}

function saveJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        fwrite(STDERR, "ERR encode: {$path}\n");
        exit(1);
    }

    // Espo custom metadata tipicamente con indent a 4 spazi
    $json = preg_replace('/^(  +)/m', '$0$0', $json) ?? $json;
    file_put_contents($path, $json . "\n");
}

function cellName($cell): ?string
{
    if (!is_array($cell)) {
        return null;
    }

    $name = $cell['name'] ?? null;

    return is_string($name) && $name !== '' ? $name : null;
}

function isVerificaCell($cell): bool
{
    return cellName($cell) === 'verificaInstallazioneTask';
}

$changed = false;

// Snapshot layout originale (prima delle modifiche) per eventuali ripristini
$originalLayoutRaw = (string) file_get_contents($layoutPath);
$originalHadShipping = str_contains($originalLayoutRaw, '"shippingProvider"');

// --- 1) Layout: una sola occorrenza, affiancata a dataInstallazione ---
$layout = loadJson($layoutPath);
$occurrences = 0;

foreach ($layout as $panel) {
    if (!isset($panel['rows']) || !is_array($panel['rows'])) {
        continue;
    }

    foreach ($panel['rows'] as $row) {
        if (!is_array($row)) {
            continue;
        }

        foreach ($row as $cell) {
            if (isVerificaCell($cell)) {
                $occurrences++;
            }
        }
    }
}

echo "[INFO] occorrenze verificaInstallazioneTask nel layout: {$occurrences}\n";

$detailsIndex = null;

foreach ($layout as $i => $panel) {
    if (!isset($panel['rows']) || !is_array($panel['rows'])) {
        continue;
    }

    // Preferisci il pannello che contiene dataInstallazione / shippingAddress
    $json = json_encode($panel);

    if (is_string($json) && (str_contains($json, '"dataInstallazione"') || str_contains($json, '"shippingAddress"'))) {
        $detailsIndex = $i;
        break;
    }
}

if ($detailsIndex === null) {
    $detailsIndex = isset($layout[1]['rows']) ? 1 : 0;
}

// Rimuovi TUTTE le celle/righe del campo, poi reinserisci una sola volta
foreach ($layout as $pi => &$panel) {
    if (!isset($panel['rows']) || !is_array($panel['rows'])) {
        continue;
    }

    $newRows = [];

    foreach ($panel['rows'] as $row) {
        if (!is_array($row)) {
            $newRows[] = $row;
            continue;
        }

        $cleaned = [];
        $removed = false;

        foreach ($row as $cell) {
            if (isVerificaCell($cell)) {
                $removed = true;
                continue;
            }

            $cleaned[] = $cell;
        }

        if ($removed) {
            $changed = true;

            // Se la riga resta vuota o solo false, scartala
            $hasField = false;

            foreach ($cleaned as $cell) {
                if (is_array($cell) && isset($cell['name'])) {
                    $hasField = true;
                    break;
                }
            }

            if (!$hasField) {
                continue;
            }

            // Normalizza a 2 colonne
            if (count($cleaned) === 1) {
                $cleaned[] = false;
            }

            $newRows[] = $cleaned;
            continue;
        }

        $newRows[] = $row;
    }

    $panel['rows'] = $newRows;
}
unset($panel);

// Reinserisci UNA volta: preferibilmente nella stessa riga di dataInstallazione
$placed = false;

foreach ($layout[$detailsIndex]['rows'] as &$row) {
    if (!is_array($row)) {
        continue;
    }

    $names = array_values(array_filter(array_map('cellName', $row)));

    if (in_array('dataInstallazione', $names, true)) {
        // dataInstallazione | verificaInstallazioneTask
        $left = null;
        $right = null;

        foreach ($row as $cell) {
            if (cellName($cell) === 'dataInstallazione') {
                $left = $cell;
            } elseif (is_array($cell) && isset($cell['name'])) {
                // Mantieni eventuale altro campo solo se non è shippingProvider vuoto di senso
                // Preferiamo affiancare il To-Do a dataInstallazione
                $right = $cell;
            }
        }

        if ($left === null) {
            $left = ['name' => 'dataInstallazione'];
        }

        // Se a destra c'era shippingProvider, lo lasciamo fuori da questa riga:
        // Data Installazione | To-Do verifica installazione (come in UI attesa)
        $row = [$left, ['name' => 'verificaInstallazioneTask']];
        $placed = true;
        $changed = true;
        break;
    }
}
unset($row);

if (!$placed) {
    $layout[$detailsIndex]['rows'][] = [
        ['name' => 'verificaInstallazioneTask'],
        false,
    ];
    $placed = true;
    $changed = true;
}

// Se shippingProvider era sulla stessa riga di dataInstallazione ed è sparito, rimettilo
// in una riga dedicata SOLO se esisteva prima nel file originale e non è più presente.
$layoutJson = json_encode($layout);
$hasShippingProvider = is_string($layoutJson) && str_contains($layoutJson, '"shippingProvider"');

if ($originalHadShipping && !$hasShippingProvider) {
    // Inserisci shippingProvider subito prima della riga dataInstallazione/verifica
    $rows = $layout[$detailsIndex]['rows'];
    $insertAt = count($rows);

    foreach ($rows as $i => $row) {
        if (!is_array($row)) {
            continue;
        }

        foreach ($row as $cell) {
            if (cellName($cell) === 'dataInstallazione' || isVerificaCell($cell)) {
                $insertAt = $i;
                break 2;
            }
        }
    }

    array_splice($layout[$detailsIndex]['rows'], $insertAt, 0, [[
        ['name' => 'shippingProvider'],
        false,
    ]]);
    $changed = true;
    echo "[INFO] ripristinata riga shippingProvider\n";
}

if ($changed) {
    if ($dryRun) {
        echo "[DRY] layout Quote/detail.json verrebbe aggiornato (1 sola verificaInstallazioneTask)\n";
    } else {
        saveJson($layoutPath, $layout);
        echo "[OK] layout Quote/detail.json bonificato\n";
    }
} else {
    echo "[SKIP] layout già ok\n";
}

// --- 2) entityDefs: assicurati che il campo esista ---
$meta = loadJson($metaPath);
$metaChanged = false;

if (!isset($meta['fields']['verificaInstallazioneTask']) || !is_array($meta['fields']['verificaInstallazioneTask'])) {
    $meta['fields']['verificaInstallazioneTask'] = [
        'type' => 'link',
        'entity' => 'Task',
        'readOnly' => true,
        'isCustom' => true,
    ];
    $metaChanged = true;
}

if (!isset($meta['links']['verificaInstallazioneTask']) || !is_array($meta['links']['verificaInstallazioneTask'])) {
    $meta['links']['verificaInstallazioneTask'] = [
        'type' => 'belongsTo',
        'entity' => 'Task',
        'foreign' => 'quotesVerificaInstallazione',
        'isCustom' => true,
    ];
    $metaChanged = true;
}

if ($metaChanged) {
    if ($dryRun) {
        echo "[DRY] entityDefs Quote.json verrebbe patchato\n";
    } else {
        saveJson($metaPath, $meta);
        echo "[OK] entityDefs Quote.json: campo/link verificaInstallazioneTask\n";
    }
} else {
    echo "[SKIP] entityDefs già ok\n";
}

// --- 3) i18n: label corretta SOLO sul campo giusto; ripristina address se corrotti ---
$i18n = loadJson($i18nPath);
$i18nChanged = false;
$todoLabel = 'To-Do verifica installazione';

$expectedAddressLabels = [
    'billingAddress' => 'Indirizzo di fatturazione',
    'billingAddressStreet' => 'Via (Fatturazione)',
    'billingAddressCity' => "Citta' (Fatturazione)",
    'billingAddressState' => 'Stato (Fatturazione)',
    'billingAddressPostalCode' => 'Codice Postale (Fatturazione)',
    'billingAddressCountry' => 'Nazione (Fatturazione)',
    'billingAddressMap' => 'Mappa (Fatturazione)',
    'shippingAddress' => 'Indirizzo di Installazione',
    'shippingAddressStreet' => 'Via (Installazione)',
    'shippingAddressCity' => "Citta' (Installazione)",
    'shippingAddressState' => 'Stato (Installazione)',
    'shippingAddressPostalCode' => 'Codice Postale (Installazione)',
    'shippingAddressCountry' => 'Nazione (Installazione)',
    'shippingAddressMap' => 'Mappa (Installazione)',
    'dataInstallazione' => 'Data Installazione',
];

if (!isset($i18n['fields']) || !is_array($i18n['fields'])) {
    $i18n['fields'] = [];
}

if (!isset($i18n['links']) || !is_array($i18n['links'])) {
    $i18n['links'] = [];
}

// Conta quante chiavi hanno la label To-Do (sintomo di corruzione)
$corruptedKeys = [];

foreach ($i18n['fields'] as $key => $label) {
    if (!is_string($label)) {
        continue;
    }

    if ($label === $todoLabel && $key !== 'verificaInstallazioneTask') {
        $corruptedKeys[] = $key;
    }
}

if ($corruptedKeys !== []) {
    echo "[WARN] i18n fields con label To-Do errata: " . implode(', ', $corruptedKeys) . "\n";

    foreach ($corruptedKeys as $key) {
        if (isset($expectedAddressLabels[$key])) {
            $i18n['fields'][$key] = $expectedAddressLabels[$key];
            $i18nChanged = true;
        } else {
            // Rimuovi label errata: Espo userà il fallback del nome campo
            unset($i18n['fields'][$key]);
            $i18nChanged = true;
        }
    }
}

if (($i18n['fields']['verificaInstallazioneTask'] ?? null) !== $todoLabel) {
    $i18n['fields']['verificaInstallazioneTask'] = $todoLabel;
    $i18nChanged = true;
}

if (($i18n['links']['verificaInstallazioneTask'] ?? null) !== $todoLabel) {
    $i18n['links']['verificaInstallazioneTask'] = $todoLabel;
    $i18nChanged = true;
}

// Ripristina address labels se assenti
foreach ($expectedAddressLabels as $key => $label) {
    if (!isset($i18n['fields'][$key]) || !is_string($i18n['fields'][$key]) || trim($i18n['fields'][$key]) === '') {
        $i18n['fields'][$key] = $label;
        $i18nChanged = true;
    }
}

if ($i18nChanged) {
    if ($dryRun) {
        echo "[DRY] i18n it_IT/Quote.json verrebbe bonificato\n";
    } else {
        saveJson($i18nPath, $i18n);
        echo "[OK] i18n it_IT/Quote.json bonificato\n";
    }
} else {
    echo "[SKIP] i18n già ok\n";
}

// Verifica finale layout
$final = loadJson($layoutPath);
$count = 0;

foreach ($final as $panel) {
    if (!isset($panel['rows']) || !is_array($panel['rows'])) {
        continue;
    }

    foreach ($panel['rows'] as $row) {
        if (!is_array($row)) {
            continue;
        }

        foreach ($row as $cell) {
            if (isVerificaCell($cell)) {
                $count++;
            }
        }
    }
}

if (!$dryRun && $count !== 1) {
    fwrite(STDERR, "ERR: dopo bonifica occorrenze layout = {$count} (atteso 1)\n");
    exit(1);
}

echo $dryRun ? "[DRY] done\n" : "[OK] bonifica completata (layout occorrenze={$count})\n";
echo "Poi: php clear_cache.php && php rebuild.php  (o command.php clearCache/rebuild)\n";
