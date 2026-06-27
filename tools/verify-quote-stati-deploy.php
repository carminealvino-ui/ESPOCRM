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
        '"Approvato"',
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

if ($failed === 0) {
    echo "\nDeploy file OK.\n";
}

exit($failed === 0 ? 0 : 1);
