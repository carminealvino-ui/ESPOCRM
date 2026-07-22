<?php

/**
 * Verifica e ripristina Appuntamento soft-deleted collegato a un'Opportunità.
 *
 * Caso tipico: in scheda Opportunity compare l'ID grezzo (es. 67ebb599b5324ca2c)
 * perché il record esiste ancora in `appuntamento` con deleted=1.
 *
 *   php tools/verifica-ripristina-appuntamento.php --dry-run 67ebb599b5324ca2c
 *   php tools/verifica-ripristina-appuntamento.php 67ebb599b5324ca2c
 *   php tools/verifica-ripristina-appuntamento.php --dry-run berana
 *   php tools/verifica-ripristina-appuntamento.php berana
 *   php tools/verifica-ripristina-appuntamento.php --scan-orphans --dry-run
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\OpportunityAppuntamentoOrphanRepair;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$scanOrphans = in_array('--scan-orphans', $argv ?? [], true);
$filter = null;

foreach ($argv ?? [] as $i => $arg) {
    if ($i === 0 || str_starts_with((string) $arg, '--')) {
        continue;
    }

    $filter = trim((string) $arg);
    break;
}

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');
$repair = new OpportunityAppuntamentoOrphanRepair($em);
$pdo = $em->getPDO();

/**
 * @return list<string>
 */
function collectAppuntamentoIds($em, $pdo, ?string $filter, bool $scanOrphans): array
{
    $ids = [];

    if ($filter && preg_match('/^[a-f0-9]{17}$/i', $filter)) {
        $ids[] = $filter;
    }

    $opportunities = $em->getRDBRepository('Opportunity')->find();

    foreach ($opportunities as $opportunity) {
        $appId = trim((string) $opportunity->get('appuntamentoId'));

        if ($appId === '') {
            continue;
        }

        if ($filter) {
            $needle = mb_strtolower($filter);
            $hay = mb_strtolower(implode(' ', [
                (string) $opportunity->getId(),
                (string) $opportunity->get('name'),
                (string) $opportunity->get('leadName'),
                (string) $opportunity->get('prospectName'),
                $appId,
            ]));

            if ($opportunity->getId() !== $filter && !str_contains($hay, $needle) && $appId !== $filter) {
                continue;
            }
        } elseif ($scanOrphans) {
            $active = $em->getEntityById('Appuntamento', $appId);

            if ($active) {
                continue;
            }
        } elseif (!$filter) {
            // Senza filtro e senza --scan-orphans: non scansionare tutto.
            continue;
        }

        $ids[] = $appId;
    }

    return array_values(array_unique($ids));
}

if (!$filter && !$scanOrphans) {
    fwrite(STDERR, "Uso:\n");
    fwrite(STDERR, "  php tools/verifica-ripristina-appuntamento.php [--dry-run] <id|berana>\n");
    fwrite(STDERR, "  php tools/verifica-ripristina-appuntamento.php --scan-orphans [--dry-run]\n");
    exit(1);
}

$ids = collectAppuntamentoIds($em, $pdo, $filter, $scanOrphans);

if ($ids === []) {
    echo "Nessun Appuntamento da verificare.\n";
    exit(0);
}

$restored = 0;
$active = 0;
$missing = 0;
$linked = 0;

foreach ($ids as $appuntamentoId) {
    $info = $repair->inspectAppuntamento($appuntamentoId);

    echo "=== Appuntamento {$appuntamentoId} ===\n";
    echo "status=" . $info['status'] . "\n";

    if ($info['exists']) {
        echo "name=" . ($info['name'] ?: '(vuoto)') . "\n";
        echo "dateStart=" . ($info['dateStart'] ?: '(vuoto)') . "\n";
        echo "deleted=" . ($info['deleted'] ? '1' : '0') . "\n";
    } else {
        echo "DB: record ASSENTE (né attivo né soft-deleted)\n";
        $missing++;
        echo "\n";
        continue;
    }

    // Opportunità che puntano a questo ID
    $opps = $em->getRDBRepository('Opportunity')
        ->where(['appuntamentoId' => $appuntamentoId])
        ->find();

    $oppCount = 0;

    foreach ($opps as $opp) {
        $oppCount++;
        echo "opportunity: {$opp->getId()} | {$opp->get('name')}\n";
    }

    if ($oppCount === 0) {
        echo "opportunity: (nessuna Opportunity con questo appuntamentoId)\n";
    }

    if ($info['status'] === 'active') {
        $active++;

        // Solo sync nome se Opportunity mostra ID grezzo.
        foreach ($opps as $opp) {
            $name = trim((string) $opp->get('appuntamentoName'));

            if ($name !== '' && $name !== $appuntamentoId) {
                continue;
            }

            $live = trim((string) ($info['name'] ?: ''));

            if ($live === '') {
                continue;
            }

            echo ($dryRun ? 'DRY ' : 'OK  ')
                . "sync-name opportunity {$opp->getId()} → {$live}\n";

            if (!$dryRun) {
                $opp->set('appuntamentoName', $live);
                $em->saveEntity($opp, ['skipHooks' => true, 'silent' => true]);
            }

            $linked++;
        }

        echo "\n";
        continue;
    }

    // soft-deleted → ripristina
    $result = $repair->restoreAppuntamento($appuntamentoId, $dryRun);

    if ($result['restored']) {
        echo ($dryRun ? 'DRY ' : 'OK  ')
            . "RESTORE deleted=0 | name=" . ($result['name'] ?: '(vuoto)') . "\n";
        $restored++;

        foreach ($opps as $opp) {
            $live = trim((string) ($result['name'] ?: $info['name'] ?: ''));

            echo ($dryRun ? 'DRY ' : 'OK  ')
                . "relink opportunity {$opp->getId()} name=" . ($live ?: $appuntamentoId) . "\n";

            if (!$dryRun) {
                $opp->set('appuntamentoId', $appuntamentoId);
                $opp->set('appuntamentoName', $live !== '' ? $live : $appuntamentoId);
                $em->saveEntity($opp, ['skipHooks' => true, 'silent' => true]);
            }

            $linked++;
        }
    }

    echo "\n";
}

echo "Riepilogo: restored={$restored} active={$active} missing={$missing} opportunities-updated={$linked}"
    . ($dryRun ? ' (dry-run)' : '')
    . PHP_EOL;

if ($missing > 0) {
    echo "Nota: record ASSENTI non sono recuperabili senza backup DB.\n";
}
