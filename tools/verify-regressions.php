#!/usr/bin/env php
<?php
/**
 * Framework unico per verifiche anti-regressione.
 *
 * Uso:
 *   php tools/verify-regressions.php --profile=quote-stati
 *   php tools/verify-regressions.php --profile=quote-stati --root=/path/crm
 */

declare(strict_types=1);

$options = getopt('', ['profile:', 'root::']);
$profile = (string) ($options['profile'] ?? '');
$root = rtrim((string) ($options['root'] ?? (getenv('CRM_ROOT') ?: getcwd())), '/');

if ($profile === '') {
    fwrite(STDERR, "Uso: php tools/verify-regressions.php --profile=<nome>\n");
    exit(2);
}

$profiles = [
    'quote-stati' => [
        'title' => 'Quote stati / enum bonificati',
        'files' => [
            'custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json' => [
                '"Bozza"',
                '"Appuntamento fissato"',
                '"Chiuso"',
            ],
            'custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json' => [
                'statoFinanziamento',
            ],
            'custom/Espo/Custom/Resources/layouts/Quote/detail.json' => [
                'statoContratto',
                'statoFinanziamento',
            ],
        ],
        'quoteEnumExpected' => [
            'status' => ['Bozza', 'In Gestione', 'Appuntamento fissato', 'Installato', 'Invalido'],
            'statoContratto' => ['', 'Inserito', 'In lavorazione', 'Chiuso', 'Sospeso', 'Annullato', 'Recesso'],
            'statoFinanziamento' => ['', 'In valutazione', 'In attesa OTP', 'Approvato', 'In rivalutazione', 'In attesa di documentazione', 'Respinto', 'Annullato'],
        ],
        'quoteI18nForbiddenTokens' => [
            '"Draft"',
            '"Presented"',
            '"Approved"',
            '"Canceled"',
            '"Finanziamento Rifiutato"',
            '"In Attesa Documentazione"',
            '"In attesa di OTP"',
        ],
    ],
];

if (!isset($profiles[$profile])) {
    fwrite(STDERR, "Profilo sconosciuto: {$profile}\n");
    fwrite(STDERR, 'Profili disponibili: ' . implode(', ', array_keys($profiles)) . "\n");
    exit(2);
}

$config = $profiles[$profile];
$failed = 0;

echo "=== Verify regressions: {$config['title']} ({$profile}) ===\n\n";

foreach (($config['files'] ?? []) as $rel => $needles) {
    $path = "{$root}/{$rel}";

    if (!is_file($path)) {
        $failed++;
        echo "[ERR] File mancante: {$rel}\n";
        continue;
    }

    $content = file_get_contents($path);

    if (!is_string($content)) {
        $failed++;
        echo "[ERR] Lettura fallita: {$rel}\n";
        continue;
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, (string) $needle)) {
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

if (isset($config['quoteEnumExpected'])) {
    $metaPath = "{$root}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json";
    $metaRaw = is_file($metaPath) ? file_get_contents($metaPath) : false;
    $meta = is_string($metaRaw) ? json_decode($metaRaw, true) : null;

    if (!is_array($meta)) {
        $failed++;
        echo "[ERR] Quote metadata non leggibile come JSON.\n";
    } else {
        foreach ($config['quoteEnumExpected'] as $field => $expected) {
            $actual = $meta['fields'][$field]['options'] ?? null;
            if ($actual !== $expected) {
                $failed++;
                echo "[ERR] Quote.{$field} options non bonificate.\n";
            } else {
                echo "[OK] Quote.{$field} options bonificate.\n";
            }
        }
    }
}

if (isset($config['quoteI18nForbiddenTokens'])) {
    $i18nPath = "{$root}/custom/Espo/Custom/Resources/i18n/it_IT/Quote.json";
    $i18nRaw = is_file($i18nPath) ? file_get_contents($i18nPath) : false;

    if (!is_string($i18nRaw)) {
        $failed++;
        echo "[ERR] Quote i18n non leggibile.\n";
    } else {
        foreach ($config['quoteI18nForbiddenTokens'] as $token) {
            if (str_contains($i18nRaw, (string) $token)) {
                $failed++;
                echo "[ERR] Quote i18n contiene token legacy vietato: {$token}\n";
            }
        }
    }
}

if ($failed === 0) {
    echo "\nOK: nessuna regressione rilevata.\n";
}

exit($failed === 0 ? 0 : 1);
