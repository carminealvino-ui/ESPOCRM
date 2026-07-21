<?php
/**
 * Bonifica massiva: ricalcola provvigioni consolidate su tutti i contratti (o filtrati).
 *
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --dry-run
 *   php tools/migrate-ricalcola-provvigioni-contratti.php
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --force --verbose
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --legacy-only
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --ariel-only --force
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --codice=Contratto_00101 --verbose
 *   php tools/migrate-ricalcola-provvigioni-contratti.php --limit=50 --offset=0
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

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\ProvvigioneAccrual;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\Custom\Services\QuotePricingCalculator;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;

try {
    $options = parseCliOptions($argv ?? []);
    $dryRun = $options['dryRun'];
    $verbose = $options['verbose'];
    $force = $options['force'];
    $legacyOnly = $options['legacyOnly'];
    $arielOnly = $options['arielOnly'];
    $skipSeed = $options['skipSeed'];
    $fixStatus = $options['fixStatus'];

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

    if (!$dryRun && !$skipSeed) {
        echo "=== Seed regole provvigionali (2026 + legacy) ===\n";
        $seedExit = runSeedScript($crmRoot . '/tools/run-regola-provvigionale-seed.php');

        if ($seedExit !== 0) {
            fwrite(STDERR, "ERRORE seed regole (exit {$seedExit})\n");
            exit(1);
        }

        echo "\n";
    }

    $collection = resolveQuoteCollection($em, $options);
    $cutover = ProvvigioneAccrual::ARIEL_2026_CUTOVER;

    $total = $collection->count();
    $processed = 0;
    $skipped = 0;
    $errors = 0;
    $statusUpdated = 0;
    $changed = 0;
    $unchanged = 0;
    $filteredOut = 0;

    echo $dryRun ? "=== DRY RUN — bonifica provvigioni contratti ===\n" : "=== BONIFICA PROVVIGIONI SU TUTTI I CONTRATTI ===\n";
    echo "Record trovati: {$total}\n";

    if ($legacyOnly) {
        echo "Filtro: solo legacy (data < {$cutover})\n";
    }

    if ($arielOnly) {
        echo "Filtro: solo brand Ariel / partner GDL\n";
    }

    if ($force) {
        echo "Modalità: --force (ricalcola anche provvigioni Pagato)\n";
    }

    if ($options['onlyCodice'] && $total === 0) {
        echo "ATTENZIONE: nessun contratto con codice «{$options['onlyCodice']}» (campi number / numberA)\n";
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

        if ($legacyOnly && !isLegacyQuote($quote, $cutover)) {
            $filteredOut++;
            continue;
        }

        if ($arielOnly && !isArielQuote($quote)) {
            $filteredOut++;
            continue;
        }

        $oldTotale = $quote->get('totaleProvvigioni');
        $oldFormatted = formatMoney($oldTotale);

        if ($dryRun) {
            $refDate = resolveReferenceDate($quote);
            $legacyTag = isLegacyQuote($quote, $cutover) ? 'LEGACY' : '2026+';
            echo "[{$index}/{$total}] [DRY] {$label} — totale: €{$oldFormatted} | {$legacyTag} | ref {$refDate}\n";
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

            if ($fixStatus) {
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
            }

            if (!$quote) {
                throw new RuntimeException('Quote non trovato dopo aggiornamento stato');
            }

            $result = $provvigioneManager->recalculateAllForQuote($quote, $force);

            $quote = $em->getEntityById('Quote', $quote->getId());
            $newTotale = $quote?->get('totaleProvvigioni');
            $newFormatted = formatMoney($newTotale);

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
                        . number_format((float) ($p->get('importoConsolidato') ?? $p->get('importo') ?? 0), 2, '.', '');
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
    echo "Filtrati (fuori criterio): {$filteredOut}\n";
    echo "Totale modificato: {$changed}\n";
    echo "Totale invariato: {$unchanged}\n";
    echo "Stati aggiornati: {$statusUpdated}\n";
    echo "Errori: {$errors}\n";

    exit($errors > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERRORE FATALE: {$e->getMessage()}\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

/**
 * @param list<string> $argv
 * @return array{
 *   dryRun: bool,
 *   verbose: bool,
 *   force: bool,
 *   legacyOnly: bool,
 *   arielOnly: bool,
 *   skipSeed: bool,
 *   fixStatus: bool,
 *   onlyId: ?string,
 *   onlyCodice: ?string,
 *   limit: ?int,
 *   offset: int
 * }
 */
function parseCliOptions(array $argv): array
{
    $options = [
        'dryRun' => false,
        'verbose' => false,
        'force' => false,
        'legacyOnly' => false,
        'arielOnly' => false,
        'skipSeed' => false,
        'fixStatus' => false,
        'onlyId' => null,
        'onlyCodice' => null,
        'limit' => null,
        'offset' => 0,
    ];

    foreach ($argv as $arg) {
        if ($arg === '--dry-run') {
            $options['dryRun'] = true;
        } elseif ($arg === '--verbose') {
            $options['verbose'] = true;
        } elseif ($arg === '--force') {
            $options['force'] = true;
        } elseif ($arg === '--legacy-only') {
            $options['legacyOnly'] = true;
        } elseif ($arg === '--ariel-only') {
            $options['arielOnly'] = true;
        } elseif ($arg === '--skip-seed') {
            $options['skipSeed'] = true;
        } elseif ($arg === '--fix-status') {
            $options['fixStatus'] = true;
        } elseif (str_starts_with($arg, '--id=')) {
            $options['onlyId'] = substr($arg, 5);
        } elseif (str_starts_with($arg, '--codice=')) {
            $options['onlyCodice'] = substr($arg, 9);
        } elseif (str_starts_with($arg, '--limit=')) {
            $options['limit'] = max(1, (int) substr($arg, 8));
        } elseif (str_starts_with($arg, '--offset=')) {
            $options['offset'] = max(0, (int) substr($arg, 9));
        }
    }

    return $options;
}

/**
 * @param array<string, mixed> $options
 */
function resolveQuoteCollection(EntityManager $em, array $options): EntityCollection
{
    $repo = $em->getRDBRepository('Quote');

    if ($options['onlyId']) {
        return $repo->where(['id' => $options['onlyId']])->find();
    }

    if ($options['onlyCodice']) {
        foreach (['number', 'numberA'] as $field) {
            $collection = $repo->where([$field => $options['onlyCodice']])->find();

            if ($collection->count() > 0) {
                return $collection;
            }
        }

        return $repo->where(['id' => '___none___'])->find();
    }

    $builder = $repo->select()->order('createdAt', 'ASC');

    if ($options['limit'] !== null) {
        $builder->limit($options['limit'], $options['offset']);
    }

    return $builder->find();
}

function isLegacyQuote(Entity $quote, string $cutover): bool
{
    $ref = resolveReferenceDate($quote);

    if (!$ref) {
        return false;
    }

    return substr($ref, 0, 10) < $cutover;
}

function resolveReferenceDate(Entity $quote): ?string
{
    foreach (['dateQuoted', 'dataInstallazione', 'dataAttivazione', 'createdAt'] as $field) {
        $value = $quote->get($field);

        if (is_string($value) && $value !== '') {
            return substr($value, 0, 10);
        }
    }

    return null;
}

function isArielQuote(Entity $quote): bool
{
    $brand = strtoupper(trim((string) ($quote->get('productBrandName') ?? '')));
    $partner = strtoupper(trim((string) ($quote->get('fornitorePartnerName') ?? '')));

    return str_contains($brand, 'ARIEL') || str_contains($partner, 'GDL');
}

function formatMoney(mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return number_format((float) $value, 2, '.', '');
}

function runSeedScript(string $scriptPath): int
{
    if (!is_file($scriptPath)) {
        fwrite(STDERR, "Script seed mancante: {$scriptPath}\n");

        return 1;
    }

    $output = [];
    $exitCode = 0;
    exec('php ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $exitCode);

    foreach ($output as $line) {
        echo $line . "\n";
    }

    return $exitCode;
}
