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
use Espo\Custom\Tools\Activities\PopupNotificationsProvider;
use Espo\ORM\EntityManager;

$options = getopt('', ['user::']);
$userName = $options['user'] ?? 'carmine_alvino';

$app = new Application();
$app->setupSystemUser();

$container = $app->getContainer();
$entityManager = $container->getByClass(EntityManager::class);
$injectableFactory = $container->getByClass(InjectableFactory::class);
$user = $entityManager->getRDBRepository('User')->where(['userName' => $userName])->findOne();

if (!$user) {
    fwrite(STDERR, "Utente non trovato: {$userName}\n");
    exit(1);
}

echo "=== Diagnostica KPI CRM (user: {$userName}) ===\n\n";

$failed = 0;

$checks = [
    'Appuntamenti Held mese corrente' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Appuntamento')->where([
            'status' => 'Held',
            'dataAppuntamento>=' => date('Y-m-01'),
            'dataAppuntamento<=' => date('Y-m-t'),
        ])->count();
    },
    'Opportunità aperte mese corrente' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Opportunity')->where([
            'AND' => [
                ['stage!=' => 'Closed Won'],
                ['stage!=' => 'Closed Lost'],
                ['dataOpportunit>=' => date('Y-m-01')],
                ['dataOpportunit<=' => date('Y-m-t')],
            ],
        ])->count();
    },
    'Somma importoOpportunit mese' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Opportunity')->where([
            'AND' => [
                ['stage!=' => 'Closed Won'],
                ['stage!=' => 'Closed Lost'],
                ['dataOpportunit>=' => date('Y-m-01')],
                ['dataOpportunit<=' => date('Y-m-t')],
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
    'Alert opportunità senza riscontro' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Opportunity')->where([
            'AND' => [
                ['stage!=' => 'Closed Won'],
                ['stage!=' => 'Closed Lost'],
            ],
        ])->limit(0, 5)->find();
    },
    'Alert contatti telefonici da fare' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Call')->where([
            'status' => 'Planned',
        ])->count();
    },
    'Alert contratti backlog' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Opportunity')->where([
            'stage' => 'Closed Won',
            'statoContratto' => 'Sospeso',
        ])->count();
    },
    'Alert contratti in lavorazione' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Opportunity')->where([
            'stage' => 'Closed Won',
            'statoContratto' => 'In lavorazione',
        ])->count();
    },
    'Alert contratti in pagamento' => function () use ($entityManager): void {
        $entityManager->getRDBRepository('Quote')->where([
            'AND' => [
                ['dataInstallazione!=' => null],
                ['dataInstallazione!=' => ''],
                ['dataInstallazione>=' => date('Y-m-01')],
                ['dataInstallazione<=' => date('Y-m-t')],
            ],
        ])->count();
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
    'Popup notifications (Call senza dateStartDate)' => function () use ($injectableFactory, $user): void {
        $provider = $injectableFactory->create(PopupNotificationsProvider::class);
        $provider->get($user);
    },
];

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
    $service = $injectableFactory->create(CrmKpiService::class);

    foreach ([
        'currentMonth',
        'previousMonth',
        'currentQuarter',
        'currentYear',
        'totals',
    ] as $period) {
        $summary = $service->getSummary($user, $period);
        echo "[OK] getSummary({$period})\n";
        echo "     appuntamentiSvolti: " . ($summary->tiles->appuntamentiSvolti->value ?? '?') . "\n";
        $tileOpp = $summary->tiles->opportunitaAperte->count ?? null;
        $funnelOpp = null;

        foreach ($summary->funnel ?? [] as $step) {
            if (($step->key ?? '') === 'opportunity') {
                $funnelOpp = $step->value ?? null;
                break;
            }
        }

        echo "     opportunita tile: " . ($tileOpp ?? '?') . " | funnel: " . ($funnelOpp ?? '?');

        if ($tileOpp !== null && $funnelOpp !== null && (int) $tileOpp !== (int) $funnelOpp) {
            echo " [WARN allineamento]";
        }

        echo "\n";
        echo "     contratti settimana mese: " . count($summary->contractsByWeekOfMonth ?? []) . " righe\n";
        echo "     avvisi: " . count($summary->alerts ?? []) . "\n";
    }
} catch (Throwable $e) {
    $failed++;
    echo "[ERR] getSummary()\n";
    echo "      " . $e->getMessage() . "\n";
    echo "      " . $e->getFile() . ':' . $e->getLine() . "\n";

    $previous = $e->getPrevious();

    if ($previous) {
        echo "      causa: " . $previous->getMessage() . "\n";
        echo "      " . $previous->getFile() . ':' . $previous->getLine() . "\n";
    }
}

echo $failed === 0 ? "\nTutti i controlli OK.\n" : "\nControlli falliti: {$failed}\n";

exit($failed === 0 ? 0 : 1);
