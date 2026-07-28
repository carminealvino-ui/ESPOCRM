<?php
/**
 * Riduce l'altezza griglia del dashlet CrmKpi (evita scroll pagina a vuoto).
 *
 *   php tools/fix-crm-kpi-dashlet-height.php --dry-run
 *   php tools/fix-crm-kpi-dashlet-height.php --apply --height=4
 *   php tools/fix-crm-kpi-dashlet-height.php --apply --height=4 --user=admin
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/lib/dashboard-report-helpers.php';

use Espo\Core\Application;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$apply = in_array('--apply', $argv ?? [], true);
$height = 4;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--height=')) {
        $height = max(2, (int) substr($arg, 9));
    }
}

if (!$dryRun && !$apply) {
    $dryRun = true;
}

$app = new Application();
$container = $app->getContainer();
$em = $container->get('entityManager');
$user = setupRunUser($container, $em, $argv ?? []);

$pref = $em->getEntityById('Preferences', $user->getId());

if (!$pref) {
    fail('Preferenze non trovate.');
}

$tabs = getPreferenceDashboardTabs($pref, $em) ?? [];
$changed = false;
$hits = 0;

echo "=== Fix altezza dashlet CrmKpi → {$height} ===\n";
echo ($dryRun && !$apply) ? "DRY-RUN\n\n" : "APPLY\n\n";

foreach ($tabs as $tabIndex => $tab) {
    if (!is_array($tab)) {
        continue;
    }

    $layout = $tab['layout'] ?? null;

    if (!is_array($layout)) {
        continue;
    }

    $tabName = (string) ($tab['name'] ?? $tabIndex);

    foreach ($layout as $i => $item) {
        if (!is_array($item) || ($item['name'] ?? '') !== 'CrmKpi') {
            continue;
        }

        $old = (int) ($item['height'] ?? 0);
        $hits++;

        if ($old === $height) {
            echo "[OK già {$height}] tab={$tabName} id=" . ($item['id'] ?? '?') . "\n";
            continue;
        }

        echo "[HIT] tab={$tabName} id=" . ($item['id'] ?? '?') . " height {$old} → {$height}\n";
        $tabs[$tabIndex]['layout'][$i]['height'] = $height;
        $changed = true;
    }
}

if ($hits === 0) {
    echo "Nessun dashlet CrmKpi trovato nel layout di {$user->get('userName')}.\n";
    exit(0);
}

if (!$changed) {
    echo "Nessuna modifica necessaria.\n";
    exit(0);
}

if ($dryRun && !$apply) {
    echo "\nApplica: php tools/fix-crm-kpi-dashlet-height.php --apply --height={$height}\n";
    exit(0);
}

savePreferenceDashboardTabs($pref, $em, $tabs);
$em->saveEntity($pref, ['silent' => true, 'skipHooks' => true]);
echo "\nSalvato. Ricarica la dashboard (Ctrl+Shift+R).\n";
