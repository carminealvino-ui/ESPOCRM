#!/usr/bin/env php
<?php
/**
 * Verifica deploy schema stati Contratto.
 *
 *   php tools/verify-quote-stati-deploy.php
 */

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

$files = [
    'custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json' => [
        '"Bozza"',
        '"Appuntamento fissato"',
        '"In attesa di OTP"',
        '"Chiuso"',
    ],
    'custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json' => [
        'statoFinanziamento',
    ],
    'custom/Espo/Custom/Resources/layouts/Quote/detail.json' => [
        'statoContratto',
        'statoFinanziamento',
    ],
    'custom/Espo/Custom/Actions/Opportunity/CreateContratto.php' => [
        "'Bozza'",
        "'Inserito'",
    ],
    'tools/migrate-quote-stati-semplificati.php' => [
        'statoFinanziamentoMap',
    ],
];

$failed = 0;

echo "=== Verifica deploy stati Contratto ===\n\n";

foreach ($files as $rel => $needles) {
    $path = $root . '/' . $rel;

    if (!is_file($path)) {
        $failed++;
        echo "[ERR] File mancante: {$rel}\n";
        continue;
    }

    $content = file_get_contents($path);

    if ($content === false) {
        $failed++;
        echo "[ERR] Lettura fallita: {$rel}\n";
        continue;
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $failed++;
            echo "[ERR] {$rel} — atteso: {$needle}\n";
            continue;
        }

        echo "[OK] {$rel} ({$needle})\n";
    }

    if (str_ends_with($rel, '.json')) {
        json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $failed++;
            echo "[ERR] {$rel} — JSON: " . json_last_error_msg() . "\n";
        }
    }
}

// Guardrail anti-regressione: enum Quote devono restare bonificati.
$quoteMetaPath = $root . '/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json';
$quoteI18nPath = $root . '/custom/Espo/Custom/Resources/i18n/it_IT/Quote.json';

if (is_file($quoteMetaPath)) {
    $metaRaw = file_get_contents($quoteMetaPath);
    $meta = is_string($metaRaw) ? json_decode($metaRaw, true) : null;

    if (!is_array($meta)) {
        $failed++;
        echo "[ERR] Quote metadata non leggibile come JSON.\n";
    } else {
        $expectedStatus = ['Bozza', 'In Gestione', 'Appuntamento fissato', 'Installato', 'Invalido'];
        $expectedContratto = ['', 'Inserito', 'In lavorazione', 'Chiuso', 'Sospeso', 'Annullato', 'Recesso'];
        $expectedFin = ['', 'In valutazione', 'In attesa OTP', 'Approvato', 'In rivalutazione', 'In attesa di documentazione', 'Respinto', 'Annullato'];

        $actualStatus = $meta['fields']['status']['options'] ?? null;
        $actualContratto = $meta['fields']['statoContratto']['options'] ?? null;
        $actualFin = $meta['fields']['statoFinanziamento']['options'] ?? null;

        if ($actualStatus !== $expectedStatus) {
            $failed++;
            echo "[ERR] Quote.status options non bonificate.\n";
        } else {
            echo "[OK] Quote.status options bonificate.\n";
        }

        if ($actualContratto !== $expectedContratto) {
            $failed++;
            echo "[ERR] Quote.statoContratto options non bonificate.\n";
        } else {
            echo "[OK] Quote.statoContratto options bonificate.\n";
        }

        if ($actualFin !== $expectedFin) {
            $failed++;
            echo "[ERR] Quote.statoFinanziamento options non bonificate.\n";
        } else {
            echo "[OK] Quote.statoFinanziamento options bonificate.\n";
        }
    }
}

if (is_file($quoteI18nPath)) {
    $i18nRaw = file_get_contents($quoteI18nPath);
    $forbidden = [
        '"Draft"',
        '"Presented"',
        '"Approved"',
        '"Canceled"',
        '"Finanziamento Rifiutato"',
        '"In Attesa Documentazione"',
        '"In attesa di OTP"',
    ];

    if (!is_string($i18nRaw)) {
        $failed++;
        echo "[ERR] Quote i18n non leggibile.\n";
    } else {
        foreach ($forbidden as $token) {
            if (str_contains($i18nRaw, $token)) {
                $failed++;
                echo "[ERR] Quote i18n contiene token legacy vietato: {$token}\n";
            }
        }
    }
}

if ($failed === 0) {
    echo "\nDeploy file OK.\n";
}

exit($failed === 0 ? 0 : 1);
