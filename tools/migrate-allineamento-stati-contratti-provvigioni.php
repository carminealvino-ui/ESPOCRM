<?php
/**
 * Riallinea stati Contratto + stato Provvigioni per tutti i contratti.
 *
 * - Normalizza enum Quote.status, Quote.statoContratto, Quote.statoFinanziamento
 * - Mappa Appuntamento fissato -> statoContratto In pagamento
 * - Mappa statoContratto verso statoProvvigione su tutte le provvigioni collegate
 * - Ricalcola totaleProvvigioni includendo anche Inesigibili
 *
 * Uso:
 *   php tools/migrate-allineamento-stati-contratti-provvigioni.php
 *   php tools/migrate-allineamento-stati-contratti-provvigioni.php --dry-run
 *   php tools/migrate-allineamento-stati-contratti-provvigioni.php --codice=Contratto_00152
 */

declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\Custom\Services\ProvvigioneStatusSync;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$onlyCodice = null;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--codice=')) {
        $onlyCodice = substr($arg, 9);
    }
}

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->get('entityManager');
$factory = $container->get('injectableFactory');
/** @var ProvvigioneStatusSync $statusSync */
$statusSync = $factory->create(ProvvigioneStatusSync::class);
/** @var ProvvigioneManager $provvigioneManager */
$provvigioneManager = $factory->create(ProvvigioneManager::class);

$statusMap = [
    'Draft' => 'Bozza',
    'Presented' => 'In lavorazione',
    'Approved' => 'In lavorazione',
    'Canceled' => 'Invalido',
    'Recesso' => 'Invalido',
    'Finanziamento Rifiutato' => 'Invalido',
];

$statoContrattoMap = [
    '' => 'Inserito',
    'In lavorazione' => 'Inserito',
    'Appuntamento Fissato' => 'In pagamento',
    'Appuntamento fissato' => 'In pagamento',
    'Installato' => 'Chiuso',
    'Approvato' => 'Chiuso',
];

$statoFinanziamentoMap = [
    'In lavorazione' => 'In valutazione',
    'In rivalutazione' => 'In valutazione',
    'In Attesa Documentazione' => 'In attesa documentazione',
    'In attesa di documentazione' => 'In attesa documentazione',
    'Annullato' => 'Respinto',
];

$where = [];

if ($onlyCodice) {
    $where['numberA'] = $onlyCodice;
}

$quotes = $em->getRDBRepository('Quote')
    ->select(['id', 'name', 'numberA', 'status', 'statoContratto', 'statoFinanziamento', 'finanziamento'])
    ->where($where)
    ->find();

$total = $quotes->count();
$updated = 0;
$unchanged = 0;
$errors = 0;
$legacyProvvigioniFixed = 0;

echo $dryRun
    ? "=== DRY RUN allineamento stati Contratti/Provvigioni ===\n"
    : "=== Allineamento stati Contratti/Provvigioni ===\n";
echo "Contratti trovati: {$total}\n\n";

$legacyStatoMap = [
    'Prevista' => 'Forecast',
    'Consolidata' => 'In pagamento',
    'InInvito' => 'In pagamento',
    'Fatturata' => 'Pagato',
    'Stornata' => 'Inesigibile',
];

if (!$dryRun) {
    $allProvvigioni = $em->getRDBRepository('Provvigione')
        ->select(['id', 'statoProvvigione'])
        ->find();

    foreach ($allProvvigioni as $provvigione) {
        $old = (string) ($provvigione->get('statoProvvigione') ?? '');
        $mapped = $legacyStatoMap[$old] ?? null;

        if ($mapped === null || $mapped === $old) {
            continue;
        }

        $entity = $em->getEntityById('Provvigione', $provvigione->getId());

        if (!$entity) {
            continue;
        }

        $entity->set('statoProvvigione', $mapped);
        $em->saveEntity($entity, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);
        $legacyProvvigioniFixed++;
    }
}

foreach ($quotes as $quoteLite) {
    $id = $quoteLite->getId();
    $label = (string) ($quoteLite->get('numberA') ?: $quoteLite->get('name') ?: $id);
    $changes = [];

    $oldStatus = (string) ($quoteLite->get('status') ?? '');
    $status = $statusMap[$oldStatus] ?? $oldStatus;

    if (!in_array($status, ['Bozza', 'In lavorazione', 'Appuntamento fissato', 'Installato', 'Invalido'], true)) {
        $status = 'In lavorazione';
    }

    if ($status !== $oldStatus) {
        $changes['status'] = $status;
    }

    $oldStatoContratto = (string) ($quoteLite->get('statoContratto') ?? '');
    $statoContratto = $statoContrattoMap[$oldStatoContratto] ?? $oldStatoContratto;

    if ($status === 'Appuntamento fissato') {
        $statoContratto = 'In pagamento';
    } elseif ($status === 'Installato' && in_array($statoContratto, ['', 'Inserito', 'In pagamento'], true)) {
        $statoContratto = 'Chiuso';
    } elseif ($status === 'Invalido' && in_array($statoContratto, ['', 'Inserito', 'In pagamento'], true)) {
        $statoContratto = 'Annullato';
    }

    if (!in_array($statoContratto, ['Inserito', 'In pagamento', 'Chiuso', 'Sospeso', 'Recesso', 'Annullato'], true)) {
        $statoContratto = 'Inserito';
    }

    if ($statoContratto !== $oldStatoContratto) {
        $changes['statoContratto'] = $statoContratto;
    }

    $oldStatoFinanziamento = (string) ($quoteLite->get('statoFinanziamento') ?? '');
    $statoFinanziamento = $statoFinanziamentoMap[$oldStatoFinanziamento] ?? $oldStatoFinanziamento;
    $isFinanziato = (bool) $quoteLite->get('finanziamento');

    if (!$isFinanziato) {
        $statoFinanziamento = '';
    } elseif (
        $statoFinanziamento !== ''
        && !in_array($statoFinanziamento, ['In attesa di OTP', 'In valutazione', 'In attesa documentazione', 'Approvato', 'Respinto'], true)
    ) {
        $statoFinanziamento = 'In valutazione';
    }

    if ($statoFinanziamento !== $oldStatoFinanziamento) {
        $changes['statoFinanziamento'] = $statoFinanziamento;
    }

    if ($dryRun) {
        if ($changes === []) {
            $unchanged++;
            continue;
        }

        echo '[DRY] ' . $label . ' -> ' . json_encode($changes, JSON_UNESCAPED_UNICODE) . "\n";
        $updated++;
        continue;
    }

    try {
        $quote = $em->getEntityById('Quote', $id);

        if (!$quote) {
            throw new RuntimeException('Quote non trovata');
        }

        if ($changes !== []) {
            $quote->set($changes);
            $em->saveEntity($quote, [
                'skipHooks' => true,
                'silent' => true,
                'skipFormula' => true,
            ]);
            $quote = $em->getEntityById('Quote', $id);
        }

        if (!$quote) {
            throw new RuntimeException('Quote non ricaricata');
        }

        $statusSync->syncProvvigioniForQuote($quote);
        $provvigioneManager->refreshQuoteTotaleProvvigioni($quote);

        if ($changes === []) {
            $unchanged++;
            continue;
        }

        echo '[OK] ' . $label . ' -> ' . json_encode($changes, JSON_UNESCAPED_UNICODE) . "\n";
        $updated++;
    } catch (Throwable $e) {
        echo '[ERR] ' . $label . ' -> ' . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\nAggiornati: {$updated}, invariati: {$unchanged}, errori: {$errors}\n";
echo "Stati legacy provvigioni riallineati: {$legacyProvvigioniFixed}\n";

exit($errors > 0 ? 1 : 0);
