#!/usr/bin/env php
<?php
/**
 * Verifica che il deploy KPI sia completo (file corretti sul server).
 *
 *   php tools/verify-crm-kpi-deploy.php
 */

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

$checks = [
    'client/custom/src/views/dashlets/crm-kpi.js' => 'Appuntamento/action/getSummary',
    'custom/Espo/Custom/Controllers/Appuntamento.php' => 'getActionGetSummary',
    'custom/Espo/Custom/Controllers/CrmKpi.php' => 'getActionGetSummary',
    'custom/Espo/Custom/Tools/CrmKpi/DateRange.php' => 'normalizePeriod',
    'custom/Espo/Custom/Tools/CrmKpi/Period.php' => 'DateRange::normalizePeriod',
    'custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php' => 'getPastPlannedSelectFields',
    'custom/Espo/Custom/Resources/metadata/dashlets/CrmKpi.json' => 'currentQuarter',
    'custom/Espo/Custom/Resources/i18n/it_IT/CrmKpi.json' => 'Totali Mese in Corso',
];

$failed = 0;

echo "=== Verifica deploy KPI CRM ===\n\n";

foreach ($checks as $rel => $needle) {
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

    echo "[OK] {$rel}\n";
}

if ($failed === 0) {
    echo "\nDeploy file OK. Poi: php clear_cache.php && php rebuild.php\n";
    echo "In browser (DevTools > Network) l'URL deve essere:\n";
    echo "  /api/v1/Appuntamento/action/getSummary?period=currentMonth\n";
    echo "Se vedi ancora CrmKpi/action/getSummary → svuota cache browser (Ctrl+Shift+R o finestra anonima).\n";
} else {
    echo "\nControlli falliti: {$failed}\n";
}

exit($failed === 0 ? 0 : 1);
