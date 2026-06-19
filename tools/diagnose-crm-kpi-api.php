<?php

/**
 * Diagnostica API KPI CRM sul server Espo.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/diagnose-crm-kpi-api.php
 *   php tools/diagnose-crm-kpi-api.php --user=carmine_alvino
 */

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (bootstrap.php).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\CrmKpi\CrmKpiService;
use Espo\ORM\EntityManager;

$options = getopt('', ['user::']);
$userName = $options['user'] ?? 'carmine_alvino';

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->getByClass(EntityManager::class);
$user = $entityManager->getRDBRepository('User')->where(['userName' => $userName])->findOne();

if (!$user) {
    fwrite(STDERR, "Utente non trovato: {$userName}\n");
    exit(1);
}

echo "=== Diagnostica KPI CRM (user: {$userName}) ===\n\n";

$checks = [
    'Appuntamenti Held mese corrente' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Appuntamento')->where([
            'status' => 'Held',
            'dataAppuntamento>=' => date('Y-m-01'),
            'dataAppuntamento<=' => date('Y-m-t'),
        ])->count();
    },
    'Opportunità aperte (stage AND)' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Opportunity')->where([
            'AND' => [
                ['stage!=' => 'Closed Won'],
                ['stage!=' => 'Closed Lost'],
            ],
        ])->count();
    },
    'Somma importoOpportunit' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Opportunity')->where([
            'AND' => [
                ['stage!=' => 'Closed Won'],
                ['stage!=' => 'Closed Lost'],
            ],
        ])->sum('importoOpportunit');
    },
    'Contratti mese corrente' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Quote')->where([
            'dateQuoted>=' => date('Y-m-01'),
            'dateQuoted<=' => date('Y-m-t'),
        ])->count();
    },
    'Alert Pending senza opportunità' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Appuntamento')->where([
            'status' => 'Held',
            'sottostato' => 'Pending',
            'dataAppuntamento<=' => date('Y-m-d', strtotime('-3 days')),
        ])->limit(0, 5)->find();
    },
    'Alert trattativa senza contratto' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Opportunity')->where([
            'stage' => ['Proposal', 'Negotiation'],
        ])->limit(0, 5)->find();
    },
    'Alert call scadute' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Call')->where([
            'status' => 'Planned',
            'dateStart<' => date('Y-m-d H:i:s'),
        ])->count();
    },
];

$failed = 0;

foreach ($checks as $label => $callback) {
    try {
        $callback();
        echo "[OK] {$label}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "[ERR] {$label}\n";
        echo "      " . $e->getMessage() . "\n";
    }
}

echo "\n=== Test servizio completo ===\n";

try {
    $injectableFactory = $app->getContainer()->getByClass(InjectableFactory::class);
    $service = $injectableFactory->create(CrmKpiService::class);
    $summary = $service->getSummary($user, 'currentMonth');

    echo "[OK] getSummary()\n";
    echo "     appuntamentiSvolti: " . ($summary->tiles->appuntamentiSvolti->value ?? '?') . "\n";
    echo "     opportunitaAperte: " . ($summary->tiles->opportunitaAperte->count ?? '?') . "\n";
    echo "     contrattiFirmati: " . ($summary->tiles->contrattiFirmati->value ?? '?') . "\n";
} catch (Throwable $e) {
    $failed++;
    echo "[ERR] getSummary()\n";
    echo "      " . $e->getMessage() . "\n";
    echo "      " . $e->getFile() . ':' . $e->getLine() . "\n";
}

echo $failed === 0 ? "\nTutti i controlli OK.\n" : "\nControlli falliti: {$failed}\n";

exit($failed === 0 ? 0 : 1);
