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
    'client/custom/src/views/dashlets/crm-kpi.js' => 'mapValoreProduzioneTile',
    'client/custom/src/views/dashlets/options/crm-kpi.js' => 'applyCrmKpiFieldLabels',
    'client/custom/res/templates/dashlets/crm-kpi.tpl' => 'crm-kpi-yields-table',
    'client/custom/css/crm-kpi-dashlet.css' => 'crm-kpi-bottom-side',
    'custom/Espo/Custom/Controllers/Appuntamento.php' => 'getActionGetSummary',
    'custom/Espo/Custom/Controllers/CrmKpi.php' => 'getActionGetSummary',
    'custom/Espo/Custom/Tools/CrmKpi/DateRange.php' => 'normalizePeriod',
    'custom/Espo/Custom/Tools/CrmKpi/Period.php' => 'DateRange::normalizePeriod',
    'custom/Espo/Custom/Tools/CrmKpi/WeekOfMonth.php' => 'buildChartRows',
    'custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/Pianificato.php' => 'status',
    'custom/Espo/Custom/Tools/CrmKpi/Alerts.php' => 'formatPaymentMeta',
    'custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php' => 'financingRejectedWhere',
    'custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php' => 'buildSalesPipeline',
    'custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php' => 'applyEfficiencyPercents',
    'custom/Espo/Custom/Tools/CrmKpi/KpiContext.php' => 'productBrandId',
    'custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/SenzaRiscontroTelefonico.php' => 'SenzaRiscontroTelefonico',
    'custom/Espo/Custom/Classes/Select/Call/PrimaryFilters/ContattiDaFare.php' => 'ContattiDaFare',
    'client/custom/src/views/dashlets/records.js' => 'this.options && this.options.entityType',
    'custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php' => 'getPastPlannedSelectFields',
    'custom/Espo/Custom/Resources/metadata/dashlets/CrmKpi.json' => 'productBrand',
    'custom/Espo/Custom/Resources/i18n/it_IT/CrmKpi.json' => 'Totali Mese in Corso',
    'custom/Espo/Custom/Resources/i18n/it_IT/DashletOptions.json' => '"period": "Periodo"',
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

    $lintFiles = [
        'custom/Espo/Custom/Tools/CrmKpi/DateRange.php',
        'custom/Espo/Custom/Tools/CrmKpi/Period.php',
        'custom/Espo/Custom/Tools/CrmKpi/WeekOfMonth.php',
        'custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php',
        'custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php',
    ];

    foreach ($lintFiles as $rel) {
        $path = $root . '/' . $rel;
        $output = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);

        if ($code !== 0) {
            $failed++;
            echo "[ERR] php -l {$rel}\n";
            echo '      ' . implode("\n      ", $output) . "\n";
        } else {
            echo "[OK] php -l {$rel}\n";
        }
    }

    echo "\nIn browser (DevTools > Network) l'URL deve essere:\n";
    echo "  /api/v1/Appuntamento/action/getSummary?period=currentMonth\n";
    echo "Se vedi ancora CrmKpi/action/getSummary → svuota cache browser (Ctrl+Shift+R o finestra anonima).\n";
} else {
    echo "\nControlli falliti: {$failed}\n";
}

exit($failed === 0 ? 0 : 1);
