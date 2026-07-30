#!/usr/bin/env php
<?php
/**
 * Import legacy da Opportunità → Contratto (Quote).
 *
 * Campi:
 *   Opportunity.finanziamento      → Quote.finanziamento
 *   Opportunity.statoFinanziamento → Quote.statoFinanziamento
 *   Opportunity.statoContratto     → Quote.statoContratto
 *   Opportunity.installazione      → Quote.dataInstallazione
 *
 * Default: riempie solo campi vuoti sul Contratto (non sovrascrive dati già presenti).
 * Per finanziamento (bool con default false): copia se Opp=true e Quote=false.
 *
 * Uso:
 *   cd ~/public_html/crm/mec-group
 *   php tools/backfill-quote-legacy-da-opportunita.php --dry-run
 *   php tools/backfill-quote-legacy-da-opportunita.php --apply
 *   php tools/backfill-quote-legacy-da-opportunita.php --apply --overwrite
 *   php tools/backfill-quote-legacy-da-opportunita.php --dry-run --limit=50
 *   php tools/backfill-quote-legacy-da-opportunita.php --dry-run --quote-id=XXXX
 */

declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: '';

if ($crmRoot === '' || !is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

if (!is_file($crmRoot . '/bootstrap.php')) {
    fwrite(STDERR, "Root CRM non valida: {$crmRoot}\n");
    exit(1);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\ContrattoStatiRules;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv, true);
$apply = in_array('--apply', $argv, true);
$overwrite = in_array('--overwrite', $argv, true);
$normalize = !in_array('--no-normalize', $argv, true);
$limit = null;
$quoteId = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, strlen('--limit=')));
    }

    if (str_starts_with($arg, '--quote-id=')) {
        $quoteId = substr($arg, strlen('--quote-id='));
    }
}

if (!$dryRun && !$apply) {
    fwrite(STDERR, "Specificare --dry-run oppure --apply\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

$rules = null;

if ($normalize && class_exists(ContrattoStatiRules::class)) {
    $rules = new ContrattoStatiRules();
}

echo "=== Backfill legacy Opportunity → Quote ===\n";
echo 'Mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo 'Strategy: ' . ($overwrite ? 'OVERWRITE se Opp ha valore' : 'solo campi vuoti (+ finanziamento Opp=true→Quote=false)') . "\n";
echo 'Normalize enums: ' . ($normalize && $rules ? 'yes' : 'no') . "\n";

$where = ['deleted' => false];

if ($quoteId) {
    $where['id'] = $quoteId;
}

$query = $em->getRDBRepository('Quote')
    ->where($where)
    ->order('modifiedAt', 'DESC');

if ($limit !== null) {
    $query = $query->limit(0, $limit);
}

$collection = $query->find();

$scanned = 0;
$withOpp = 0;
$updated = 0;
$skippedNoOpp = 0;
$skippedNoPatch = 0;
$fieldHits = [
    'finanziamento' => 0,
    'statoFinanziamento' => 0,
    'statoContratto' => 0,
    'dataInstallazione' => 0,
];

/**
 * @return array<string, mixed>
 */
function buildLegacyPatch(Entity $quote, Entity $opportunity, bool $overwrite): array
{
    $patch = [];

    // Bool finanziamento
    $qFin = $quote->get('finanziamento');
    $oFin = $opportunity->get('finanziamento');

    if ($oFin !== null) {
        $oFinBool = (bool) $oFin;
        $qFinBool = $qFin === null ? null : (bool) $qFin;

        if ($overwrite) {
            if ($qFinBool !== $oFinBool) {
                $patch['finanziamento'] = $oFinBool;
            }
        } elseif ($oFinBool === true && ($qFinBool === null || $qFinBool === false)) {
            $patch['finanziamento'] = true;
        } elseif ($qFinBool === null && $oFin !== null) {
            $patch['finanziamento'] = $oFinBool;
        }
    }

    // Enums
    foreach (['statoFinanziamento', 'statoContratto'] as $field) {
        $qVal = $quote->get($field);
        $oVal = $opportunity->get($field);

        if ($oVal === null || $oVal === '') {
            continue;
        }

        $qEmpty = ($qVal === null || $qVal === '');

        if ($overwrite || $qEmpty) {
            if ((string) $qVal !== (string) $oVal) {
                $patch[$field] = $oVal;
            }
        }
    }

    // Date: Opportunity.installazione → Quote.dataInstallazione
    $qDate = $quote->get('dataInstallazione');
    $oDate = $opportunity->get('installazione');

    if ($oDate !== null && $oDate !== '') {
        $qEmpty = ($qDate === null || $qDate === '');

        if ($overwrite || $qEmpty) {
            if ((string) $qDate !== (string) $oDate) {
                $patch['dataInstallazione'] = $oDate;
            }
        }
    }

    return $patch;
}

foreach ($collection as $quote) {
    $scanned++;

    $opportunityId = $quote->get('opportunityId');

    if (!$opportunityId) {
        $skippedNoOpp++;
        continue;
    }

    $opportunity = $em->getEntityById('Opportunity', $opportunityId);

    if (!$opportunity || $opportunity->get('deleted')) {
        $skippedNoOpp++;
        continue;
    }

    $withOpp++;
    $patch = buildLegacyPatch($quote, $opportunity, $overwrite);

    if ($patch === []) {
        $skippedNoPatch++;
        continue;
    }

    foreach (array_keys($patch) as $field) {
        if (isset($fieldHits[$field])) {
            $fieldHits[$field]++;
        }
    }

    $label = $quote->get('number') ?: $quote->get('name') ?: $quote->getId();
    $oppLabel = $opportunity->get('name') ?: $opportunityId;

    echo sprintf(
        "- Quote %s ← Opp %s | %s\n",
        $label,
        $oppLabel,
        json_encode($patch, JSON_UNESCAPED_UNICODE)
    );

    if ($apply) {
        $quote->set($patch);

        if ($rules) {
            $rules->apply($quote);
        }

        $em->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);
    }

    $updated++;
}

echo "\n=== Riepilogo ===\n";
echo "Scansionati: {$scanned}\n";
echo "Con opportunità: {$withOpp}\n";
echo "Da aggiornare / aggiornati: {$updated}\n";
echo "Skip senza opp: {$skippedNoOpp}\n";
echo "Skip senza patch: {$skippedNoPatch}\n";
echo "Campi toccati:\n";

foreach ($fieldHits as $field => $count) {
    echo "  - {$field}: {$count}\n";
}

if ($dryRun) {
    echo "\nDRY-RUN: nessuna scrittura. Rilancia con --apply per salvare.\n";
} else {
    echo "\nAPPLY completato.\n";
}
