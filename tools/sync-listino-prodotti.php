<?php
/**
 * Allinea prodotti CRM con righe listino (CSV) — codice, nome, prezzi.
 *
 * Eseguire sul server EspoCRM (dove esiste data/config-internal.php):
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/sync-listino-prodotti.php \
 *     --csv=database/data/listino-ariel-climatizzatori-07052026.csv \
 *     --price-book-name='ARIEL' \
 *     --date-start=2026-05-07 \
 *     --dry-run
 *
 * Rimuovere --dry-run per applicare.
 *
 * Opzioni:
 *   --crm-root=PATH          Root installazione (default: cwd)
 *   --csv=PATH               File CSV (;) con colonne: codice,nome,prezzo_listino,prezzo_codice,tipo,note
 *   --price-book-id=ID       Listino Sales Pack (alternativa a --price-book-name)
 *   --price-book-name=TEXT   Cerca listino per nome (LIKE %TEXT%, ordine nome DESC)
 *   --date-start=YYYY-MM-DD  dateStart su nuove righe product_price (default 2026-05-07)
 *   --product-brand-id=ID    Filtra/imposta brand su prodotti creati
 *   --create-missing         Crea prodotto se codice assente (default: sì)
 *   --no-create-missing      Solo aggiorna prodotti esistenti
 *   --dry-run                Nessun salvataggio
 */

declare(strict_types=1);

function usage(): void
{
    fwrite(STDERR, "Usage: php sync-listino-prodotti.php --csv=FILE [--crm-root=DIR] [--dry-run]\n");
    exit(1);
}

$options = getopt('', [
    'crm-root::',
    'csv:',
    'price-book-id::',
    'price-book-name::',
    'date-start::',
    'product-brand-id::',
    'create-missing',
    'no-create-missing',
    'dry-run',
]);

if (empty($options['csv'])) {
    usage();
}

$crmRoot = rtrim($options['crm-root'] ?? getcwd(), '/');
$csvPath = $options['csv'];

if (!is_file($csvPath) && is_file($crmRoot . '/' . $csvPath)) {
    $csvPath = $crmRoot . '/' . $csvPath;
}

if (!is_file($csvPath)) {
    fwrite(STDERR, "CSV non trovato: {$csvPath}\n");
    exit(1);
}

$configInternal = $crmRoot . '/data/config-internal.php';

if (!is_file($configInternal)) {
    fwrite(STDERR, "config-internal.php non trovato in {$crmRoot}/data/\n");
    exit(1);
}

chdir($crmRoot);

require_once $crmRoot . '/bootstrap.php';

$app = new \Espo\Core\Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$metadata = $entityManager->getMetadata();

$dryRun = array_key_exists('dry-run', $options);
$createMissing = !array_key_exists('no-create-missing', $options);
$dateStart = $options['date-start'] ?? '2026-05-07';
$brandId = $options['product-brand-id'] ?? null;

$priceBook = resolvePriceBook($entityManager, $options);

if (!$priceBook) {
    fwrite(STDERR, "Price Book non trovato. Usare --price-book-id o --price-book-name.\n");
    exit(1);
}

fwrite(STDOUT, "Listino: {$priceBook->get('name')} ({$priceBook->getId()})\n");
fwrite(STDOUT, "CSV: {$csvPath}\n");
fwrite(STDOUT, $dryRun ? "MODALITÀ: dry-run\n" : "MODALITÀ: APPLY\n");

$rows = readCsv($csvPath);
$stats = ['updated' => 0, 'created' => 0, 'prices' => 0, 'skipped' => 0, 'errors' => 0];

foreach ($rows as $i => $row) {
    $line = $i + 2;
    $codice = normalizeCodice($row['codice'] ?? '');

    if ($codice === '') {
        fwrite(STDOUT, "[riga {$line}] SKIP: codice vuoto ({$row['nome']})\n");
        $stats['skipped']++;

        continue;
    }

    $nome = trim((string) ($row['nome'] ?? ''));
    $prezzoListino = parseEuro($row['prezzo_listino'] ?? null);
    $prezzoCodice = parseEuro($row['prezzo_codice'] ?? null);

    if ($prezzoCodice === null && $prezzoListino !== null) {
        $prezzoCodice = $prezzoListino;
    }

    try {
        $product = findProductByCodice($entityManager, $codice);

        if (!$product && $createMissing) {
            $product = $entityManager->getNewEntity('Product');
            $product->set('partNumber', $codice);
            $product->set('name', $nome !== '' ? $nome : $codice);
            $product->set('status', 'Available');
            $product->set('type', 'Regular');
            $product->set('itemType', ($row['tipo'] ?? '') === 'servizio' ? 'Service' : 'Goods');

            if ($brandId && $metadata->hasAttribute('Product', 'brandId')) {
                $product->set('brandId', $brandId);
            }

            if (!$dryRun) {
                $entityManager->saveEntity($product);
            }

            fwrite(STDOUT, "[{$codice}] CREATO prodotto: {$product->get('name')}\n");
            $stats['created']++;
        } elseif (!$product) {
            fwrite(STDERR, "[{$codice}] ERRORE: prodotto assente (--no-create-missing)\n");
            $stats['errors']++;

            continue;
        } else {
            $patch = [];

            if ($nome !== '' && $product->get('name') !== $nome) {
                $patch['name'] = $nome;
            }

            if ($metadata->hasAttribute('Product', 'denominazione') && $nome !== '') {
                $patch['denominazione'] = $nome;
            }

            if ($product->get('partNumber') !== $codice) {
                $patch['partNumber'] = $codice;
            }

            if ($prezzoListino !== null) {
                if ($metadata->hasAttribute('Product', 'listPrice')) {
                    $patch['listPrice'] = $prezzoListino;
                }

                if ($metadata->hasAttribute('Product', 'unitPrice')) {
                    $patch['unitPrice'] = $prezzoListino;
                }
            }

            if ($prezzoCodice !== null && $metadata->hasAttribute('Product', 'prezzoCodice')) {
                $patch['prezzoCodice'] = $prezzoCodice;
            }

            if ($patch !== []) {
                $product->set($patch);

                if (!$dryRun) {
                    $entityManager->saveEntity($product);
                }

                fwrite(STDOUT, "[{$codice}] AGGIORNATO: " . json_encode($patch, JSON_UNESCAPED_UNICODE) . "\n");
                $stats['updated']++;
            }
        }

        if ($prezzoListino !== null && $metadata->hasEntity('ProductPrice')) {
            $ppResult = upsertProductPrice(
                $entityManager,
                $product,
                $priceBook,
                $prezzoListino,
                $dateStart,
                $dryRun
            );

            if ($ppResult) {
                $stats['prices']++;
                fwrite(STDOUT, "[{$codice}] product_price: {$ppResult}\n");
            }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "[{$codice}] ERRORE: {$e->getMessage()}\n");
        $stats['errors']++;
    }
}

fwrite(STDOUT, "\nRiepilogo: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n");

exit($stats['errors'] > 0 ? 1 : 0);

function resolvePriceBook($entityManager, array $options): ?\Espo\ORM\Entity
{
    if (!empty($options['price-book-id'])) {
        return $entityManager->getEntityById('PriceBook', $options['price-book-id']);
    }

    $name = $options['price-book-name'] ?? 'ARIEL';

    return $entityManager
        ->getRDBRepository('PriceBook')
        ->where([
            'name*' => $name,
            'status' => 'Active',
        ])
        ->order('name', 'DESC')
        ->findOne();
}

function readCsv(string $path): array
{
    $fh = fopen($path, 'rb');

    if ($fh === false) {
        throw new RuntimeException("Impossibile aprire CSV");
    }

    $header = null;
    $rows = [];

    while (($data = fgetcsv($fh, 0, ';')) !== false) {
        if ($header === null) {
            $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $data);

            continue;
        }

        if (count(array_filter($data, static fn ($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }

        $row = [];

        foreach ($header as $idx => $key) {
            $row[$key] = $data[$idx] ?? '';
        }

        $rows[] = $row;
    }

    fclose($fh);

    return $rows;
}

function normalizeCodice(?string $codice): string
{
    return trim(str_replace(',', '.', (string) $codice));
}

function parseEuro(mixed $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    $s = trim((string) $value);
    $s = str_replace(['€', ' ', "\xc2\xa0"], '', $s);
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);

    if ($s === '' || !is_numeric($s)) {
        return null;
    }

    return round((float) $s, 2);
}

function findProductByCodice($entityManager, string $codice): ?\Espo\ORM\Entity
{
    $product = $entityManager
        ->getRDBRepository('Product')
        ->where(['partNumber' => $codice])
        ->findOne();

    if ($product) {
        return $product;
    }

    return $entityManager
        ->getRDBRepository('Product')
        ->where(['partNumber' => str_replace('.', '', $codice)])
        ->findOne();
}

function upsertProductPrice(
    $entityManager,
    \Espo\ORM\Entity $product,
    \Espo\ORM\Entity $priceBook,
    float $price,
    string $dateStart,
    bool $dryRun
): ?string {
    $existing = $entityManager
        ->getRDBRepository('ProductPrice')
        ->where([
            'productId' => $product->getId(),
            'priceBookId' => $priceBook->getId(),
            'status' => 'Active',
        ])
        ->order('dateStart', 'DESC')
        ->findOne();

    if ($existing) {
        $samePrice = abs((float) $existing->get('price') - $price) < 0.009;
        $sameStart = substr((string) ($existing->get('dateStart') ?? ''), 0, 10) === $dateStart;

        if ($samePrice && $sameStart) {
            return 'già allineato';
        }
    }

    if ($dryRun) {
        return $existing
            ? "dry-run: aggiorna prezzo a {$price} dal {$dateStart} (chiude riga precedente)"
            : "dry-run: nuovo prezzo {$price} dal {$dateStart}";
    }

    if ($existing && $existing->hasAttribute('dateEnd')) {
        $end = date('Y-m-d', strtotime($dateStart . ' -1 day'));
        $existing->set('dateEnd', $end);
        $entityManager->saveEntity($existing);
    }

    $productPrice = $entityManager->getNewEntity('ProductPrice');
    $productPrice->set([
        'productId' => $product->getId(),
        'priceBookId' => $priceBook->getId(),
        'price' => $price,
        'status' => 'Active',
    ]);

    if ($productPrice->hasAttribute('dateStart')) {
        $productPrice->set('dateStart', $dateStart);
    }

    $entityManager->saveEntity($productPrice);

    return "prezzo {$price} EUR dal {$dateStart}";
}
