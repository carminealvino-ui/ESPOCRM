<?php
/**
 * Migra i valori enum legacy dei Contratti al nuovo schema semplificato.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/migrate-quote-stati-semplificati.php
 *   php tools/migrate-quote-stati-semplificati.php --dry-run
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

$statusMap = [
    'Draft' => 'Bozza',
    'Presented' => 'In lavorazione',
    'Approved' => 'In lavorazione',
    'In lavorazione' => 'In lavorazione',
    'Installato' => 'Installato',
    'Recesso' => 'Invalido',
    'Finanziamento Rifiutato' => 'Invalido',
    'Canceled' => 'Invalido',
    'Bozza' => 'Bozza',
    'Appuntamento fissato' => 'Appuntamento fissato',
    'Invalido' => 'Invalido',
];

$statoContrattoMap = [
    '' => 'Inserito',
    'Inserito' => 'Inserito',
    'In lavorazione' => 'Approvato',
    'Approvato' => 'Approvato',
    'Sospeso' => 'Sospeso',
    'Recesso' => 'Recesso',
    'Annullato' => 'Annullato',
    'Installato' => 'Approvato',
    'Appuntamento Fissato' => 'Approvato',
];

$statoFinanziamentoMap = [
    'In lavorazione' => 'In valutazione',
    'In rivalutazione' => 'In valutazione',
    'In Attesa Documentazione' => 'In attesa documentazione',
    'In attesa di documentazione' => 'In attesa documentazione',
    'In attesa documentazione' => 'In attesa documentazione',
    'In attesa di OTP' => 'In attesa di OTP',
    'In valutazione' => 'In valutazione',
    'Approvato' => 'Approvato',
    'Respinto' => 'Respinto',
    'Annullato' => 'Respinto',
];

$updated = 0;
$skipped = 0;

$collection = $em
    ->getRDBRepository('Quote')
    ->select(['id', 'name', 'status', 'statoContratto', 'statoFinanziamento'])
    ->find();

echo '=== Migrazione stati Contratto ===' . PHP_EOL;
echo $dryRun ? "(dry-run)\n" : '';

foreach ($collection as $quote) {
    $changes = [];

    $status = (string) ($quote->get('status') ?? '');
    $newStatus = $statusMap[$status] ?? null;

    if ($newStatus !== null && $newStatus !== $status) {
        $changes['status'] = $newStatus;
    } elseif ($status !== '' && !in_array($status, array_values(array_unique($statusMap)), true)) {
        echo "[WARN] {$quote->getId()} status sconosciuto: {$status}\n";
    }

    $statoContratto = (string) ($quote->get('statoContratto') ?? '');
    $newStatoContratto = $statoContrattoMap[$statoContratto] ?? null;

    if ($newStatoContratto !== null && $newStatoContratto !== $statoContratto) {
        $changes['statoContratto'] = $newStatoContratto;
    } elseif ($statoContratto !== '' && !in_array($statoContratto, array_values(array_unique($statoContrattoMap)), true)) {
        echo "[WARN] {$quote->getId()} statoContratto sconosciuto: {$statoContratto}\n";
    }

    $statoFinanziamento = (string) ($quote->get('statoFinanziamento') ?? '');

    if ($statoFinanziamento !== '') {
        $newStatoFinanziamento = $statoFinanziamentoMap[$statoFinanziamento] ?? null;

        if ($newStatoFinanziamento !== null && $newStatoFinanziamento !== $statoFinanziamento) {
            $changes['statoFinanziamento'] = $newStatoFinanziamento;
        } elseif (!in_array($statoFinanziamento, array_values(array_unique($statoFinanziamentoMap)), true)) {
            echo "[WARN] {$quote->getId()} statoFinanziamento sconosciuto: {$statoFinanziamento}\n";
        }
    }

    if ($changes === []) {
        $skipped++;
        continue;
    }

    $label = $quote->get('name') ?: $quote->getId();
    echo "[UPD] {$label}: " . json_encode($changes, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    if (!$dryRun) {
        $entity = $em->getEntityById('Quote', $quote->getId());

        if ($entity) {
            $entity->set($changes);
            $em->saveEntity($entity);
        }
    }

    $updated++;
}

echo PHP_EOL . "Aggiornati: {$updated}, invariati: {$skipped}" . PHP_EOL;

if ($dryRun) {
    echo "Nessuna modifica scritta (dry-run)." . PHP_EOL;
}
