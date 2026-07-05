#!/usr/bin/env php
<?php
/**
 * Verifica deploy modulo Invito a fatturare.
 *
 *   php tools/verify-invito-a-fatturare-deploy.php
 */

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

$checks = [
    ['custom/Espo/Custom/Services/InvitoAFatturareManager.php', 'getProvvigioniEleggibili'],
    ['custom/Espo/Custom/Services/InvitoAFatturareManager.php', 'collegaProvvigioni'],
    ['custom/Espo/Custom/Actions/InvitoAFatturare/GetProvvigioniEleggibili.php', 'GetProvvigioniEleggibili'],
    ['custom/Espo/Custom/Actions/InvitoAFatturare/CollegaProvvigioni.php', 'collegaProvvigioni'],
    ['custom/Espo/Custom/Controllers/InvitoAFatturare.php', 'postActionCollegaProvvigioni'],
    ['custom/Espo/Custom/Hooks/InvitoAFatturare/InvitoBeforeSave.php', 'recalculateTotals'],
    ['client/custom/src/views/invito-a-fatturare/modals/select-provvigioni.js', 'enrichGroups'],
    ['client/custom/src/views/invito-a-fatturare/record/detail.js', 'actionSelezionaProvvigioni'],
    ['client/custom/res/templates/invito-a-fatturare/modals/select-provvigioni.tpl', 'Tipo Provv:'],
    ['custom/Espo/Custom/Resources/metadata/app/actions.json', 'collegaProvvigioni'],
];

$failed = 0;

echo "=== Verifica deploy Invito a fatturare ===\n\n";

foreach ($checks as [$rel, $needle]) {
    $path = $root . '/' . $rel;

    if (!is_file($path)) {
        $failed++;
        echo "[ERR] File mancante: {$rel}\n";
        continue;
    }

    $content = file_get_contents($path);

    if ($content === false || !str_contains($content, $needle)) {
        $failed++;
        echo "[ERR] {$rel} — stringa attesa non trovata: {$needle}\n";
        continue;
    }

    echo "[OK] {$rel} ({$needle})\n";
}

$lintFiles = [
    'custom/Espo/Custom/Services/InvitoAFatturareManager.php',
    'custom/Espo/Custom/Controllers/InvitoAFatturare.php',
    'custom/Espo/Custom/Actions/InvitoAFatturare/GetProvvigioniEleggibili.php',
    'custom/Espo/Custom/Actions/InvitoAFatturare/CollegaProvvigioni.php',
];

foreach ($lintFiles as $rel) {
    $path = $root . '/' . $rel;
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);

    if ($code !== 0) {
        $failed++;
        echo "[ERR] php -l {$rel}\n";
    } else {
        echo "[OK] php -l {$rel}\n";
    }
}

if ($failed === 0) {
    echo "\nDeploy OK. Poi: php clear_cache.php && php rebuild.php\n";
} else {
    echo "\nControlli falliti: {$failed}\n";
}

exit($failed === 0 ? 0 : 1);
