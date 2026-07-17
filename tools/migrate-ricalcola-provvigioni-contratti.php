<?php
/**
 * Ricalcola provvigioni consolidate su tutti i contratti (o uno specifico).
 *
 *   php tools/migrate-ricalcola-provvigioni-contratti.php
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --dry-run
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --id=6a4e3247d258ac77f
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --codice=Contratto_00101
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --codice=Contratto_00101 --verbose
 */

declare(strict_types=1);

fwrite(STDOUT, "migrate-ricalcola-provvigioni: avvio\n");

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

if (!is_file($crmRoot . '/bootstrap.php')) {
    fwrite(STDERR, "ERRORE: bootstrap.php non trovato in {$crmRoot}\n");
    exit(1);
}

chdir($crmRoot);

require_once $crmRoot . '/bootstrap.php';
require_once __DIR__ . '/seed-regole-provvigioni-ariel.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\EntityManager;
use Espo\ORM\EntityCollection;

try {
    $dryRun = in_array('--dry-run', $argv ?? [], true);
    $verbose = in_array('--verbose', $argv ?? [], true);
    $onlyId = null;
    $onlyCodice = null;

    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--id=')) {
            $onlyId = substr($arg, 5);
        }
        if (str_starts_with($arg, '--codice=')) {
            $onlyCodice = substr($arg, 9);
        }
    }

    $app = new Application();
    $app->setupSystemUser();

    $container = $app->getContainer();

    /** @var EntityManager $em */
    $em = $container->get('entityManager');

    /** @var InjectableFactory $injectableFactory */
    $injectableFactory = $container->get('injectableFactory');

    /** @var ProvvigioneManager $provvigioneManager */
    $provvigioneManager = $injectableFactory->create(ProvvigioneManager::class);

    /** @var QuotePricingCalculator $quotePricingCalculator */
    $quotePricingCalculator = $injectableFactory->create(QuotePricingCalculator::class);

    if (!$dryRun) {
        echo "=== Seed regole provvigionali ===\n";
        seedRegoleProvvigioniAriel($em);
        echo "OK regole arielMinus35, bonusWeekendSd, referenzaPersonale\n\n";
    }

    $collection = resolveQuoteCollection($em, $onlyId, $onlyCodice);

    $total = $collection->count();
    $processed = 0;
    $skipped = 0;
    $errors = 0;
    $statusUpdated = 0;
    $changed = 0;
    $unchanged = 0;

    echo $dryRun ? "=== DRY RUN — ricalcolo provvigioni contratti ===\n" : "=== RICALCOLO PROVVIGIONI SU TUTTI I CONTRATTI ===\n";
    echo "Record trovati: {$total}\n";

    if ($onlyCodice && $total === 0) {
        echo "ATTENZIONE: nessun contratto con codice «{$onlyCodice}» (campi number / numberA)\n";
    }

    echo "\n";

    $index = 0;

    foreach ($collection as $quote) {
        $index++;
        $codice = $quote->get('number') ?: $quote->get('numberA') ?: $quote->getId();
        $cliente = $quote->get('accountName') ?: '';
        $label = "{$codice}" . ($cliente ? " — {$cliente}" : '');

        if (!$quote->get('opportunityId')) {
            echo "[{$index}/{$total}] [SKIP] {$label} (senza opportunità)\n";
            $skipped++;
            continue;
        }

        $oldTotale = $quote->get('totaleProvvigioni');
        $oldFormatted = $oldTotale !== null && $oldTotale !== '' ? number_format((float) $oldTotale, 2, '.', '') : '—';

        if ($dryRun) {
            echo "[{$index}/{$total}] [DRY] {$label} — totale attuale: €{$oldFormatted}\n";
            $processed++;
            continue;
        }

        try {
            $quote = $em->getEntityById('Quote', $quote->getId());

            if (!$quote) {
                echo "[{$index}/{$total}] [SKIP] {$label} (non trovato dopo reload)\n";
                $skipped++;
                continue;
            }

            $hasNumero = trim((string) ($quote->get('numeroContratto') ?? '')) !== ''
                || trim((string) ($quote->get('number') ?? '')) !== '';

            $nextStatus = match ($quote->get('status')) {
                'Bozza' => 'In lavorazione',
                'Draft' => 'Presented',
                default => null,
            };

            if ($hasNumero && $nextStatus !== null) {
                $quote->set('status', $nextStatus);
                $em->saveEntity($quote, [
                    'skipHooks' => true,
                    'silent' => true,
                    'skipFormula' => true,
                ]);
                $statusUpdated++;
                $quote = $em->getEntityById('Quote', $quote->getId());
            }

            if (!$quote) {
                throw new RuntimeException('Quote non trovato dopo aggiornamento stato');
            }

            $result = $provvigioneManager->recalculateAllForQuote($quote);

            $quote = $em->getEntityById('Quote', $quote->getId());
            $newTotale = $quote?->get('totaleProvvigioni');
            $newFormatted = $newTotale !== null && $newTotale !== '' ? number_format((float) $newTotale, 2, '.', '') : '—';

            $detail = '';

            if ($verbose && $quote) {
                $net = $quotePricingCalculator->resolveImponibileNetto($quote);
                $minusPlus = $quotePricingCalculator->resolveMinusPlusForQuote($quote);
                $netFmt = $net !== null ? number_format($net, 2, '.', '') : '—';
                $mpFmt = $minusPlus !== null ? number_format($minusPlus, 2, '.', '') : '—';

                $provvigioni = $em
                    ->getRDBRepository('Provvigione')
                    ->where(['contrattoId' => $quote->getId()])
                    ->find();

                $righe = [];

                foreach ($provvigioni as $p) {
                    $righe[] = ($p->get('tipo') ?: '?')
                        . ' €'
                        . number_format((float) ($p->get('importoConsolidato') ?? 0), 2, '.', '');
                }

                $detail = " | net €{$netFmt} mp €{$mpFmt} | " . ($righe ? implode(', ', $righe) : 'nessuna riga');
            }

            $delta = '';

            if ($oldFormatted !== '—' && $newFormatted !== '—' && $oldFormatted !== $newFormatted) {
                $delta = " (era €{$oldFormatted})";
                $changed++;
            } elseif ($oldFormatted === $newFormatted) {
                $unchanged++;
            } else {
                $changed++;
            }

            echo "[{$index}/{$total}] [OK] {$label} — totale: €{$newFormatted}{$delta}{$detail}"
                . " | provvigioni: {$result['created']}, purge: {$result['purged']}\n";
            $processed++;
        } catch (Throwable $e) {
            echo "[{$index}/{$total}] [ERR] {$label} — {$e->getMessage()}\n";
            $errors++;
        }
    }

    echo "\n--- Riepilogo ---\n";
    echo "Elaborati: {$processed}\n";
    echo "Saltati: {$skipped}\n";
    echo "Totale modificato: {$changed}\n";
    echo "Totale invariato: {$unchanged}\n";
    echo "Stati aggiornati (Bozza→In lavorazione): {$statusUpdated}\n";
    echo "Errori: {$errors}\n";

    exit($errors > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERRORE FATALE: {$e->getMessage()}\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

function resolveQuoteCollection(EntityManager $em, ?string $onlyId, ?string $onlyCodice): EntityCollection
{
    $repo = $em->getRDBRepository('Quote');

    if ($onlyId) {
        return $repo->where(['id' => $onlyId])->find();
    }

    if ($onlyCodice) {
        foreach (['number', 'numberA'] as $field) {
            $collection = $repo->where([$field => $onlyCodice])->find();

            if ($collection->count() > 0) {
                return $collection;
            }
        }

        return $repo->where(['id' => '___none___'])->find();
    }

    return $repo->order('createdAt', 'ASC')->find();
}
