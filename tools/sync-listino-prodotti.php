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
 *   --csv=PATH               CSV (;): codice_articolo,nome,prezzo_listino,prezzo_codice,tipo,note
 *                            Colonna PDF "Codice" (evidenziata) = prezzo_codice (es. 2950), ≠ listino
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
    $codiceArticolo = resolveCodiceArticolo($row);
    $nome = trim((string) ($row['nome'] ?? ''));
    $prezzoListino = parseEuro($row['prezzo_listino'] ?? null);
    $prezzoCodice = parseEuro($row['prezzo_codice'] ?? null);

    if ($codiceArticolo === '' && $nome === '') {
        fwrite(STDOUT, "[riga {$line}] SKIP: codice_articolo e nome vuoti\n");
        $stats['skipped']++;

        continue;
    }

    try {
        $product = findProduct($entityManager, $codiceArticolo, $nome);

        if (!$product && $createMissing) {
            $product = $entityManager->getNewEntity('Product');

            if ($codiceArticolo !== '') {
                $product->set('partNumber', $codiceArticolo);
            }

            $product->set('name', $nome !== '' ? $nome : $codiceArticolo);
            $product->set('status', 'Available');
            $product->set('type', 'Regular');
            $product->set('itemType', ($row['tipo'] ?? '') === 'servizio' ? 'Service' : 'Goods');

            if ($brandId && $metadata->hasAttribute('Product', 'brandId')) {
                $product->set('brandId', $brandId);
            }

            if (!$dryRun) {
                $entityManager->saveEntity($product);
            }

            $label = $codiceArticolo !== '' ? $codiceArticolo : $nome;
            fwrite(STDOUT, "[{$label}] CREATO prodotto: {$product->get('name')}\n");
            $stats['created']++;
        } elseif (!$product) {
            $label = $codiceArticolo !== '' ? $codiceArticolo : $nome;
            fwrite(STDERR, "[{$label}] ERRORE: prodotto assente (--no-create-missing)\n");
            $stats['errors']++;

            continue;
        } else {
            $patch = [];
            $label = $product->get('partNumber') ?: $product->get('name');

            if ($nome !== '' && $product->get('name') !== $nome) {
                $patch['name'] = $nome;
            }

            if ($metadata->hasAttribute('Product', 'denominazione') && $nome !== '') {
                $patch['denominazione'] = $nome;
            }

            if ($codiceArticolo !== '' && $product->get('partNumber') !== $codiceArticolo) {
                $patch['partNumber'] = $codiceArticolo;
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

                fwrite(STDOUT, "[{$label}] AGGIORNATO: " . json_encode($patch, JSON_UNESCAPED_UNICODE) . "\n");
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
                fwrite(STDOUT, "[{$label}] product_price (listino): {$ppResult}\n");
            }
        }
    } catch (Throwable $e) {
        $label = $codiceArticolo !== '' ? $codiceArticolo : $nome;
        fwrite(STDERR, "[{$label}] ERRORE: {$e->getMessage()}\n");
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

function resolveCodiceArticolo(array $row): string
{
    $articolo = trim((string) ($row['codice_articolo'] ?? ''));

    if ($articolo !== '') {
        return normalizePartNumber($articolo);
    }

    // Legacy: colonna "codice" = SKU articolo (non il prezzo a codice del PDF)
    $legacy = trim((string) ($row['codice'] ?? ''));

    if ($legacy === '') {
        return '';
    }

    if (looksLikeEuroAmount($legacy)) {
        return '';
    }

    return normalizePartNumber($legacy);
}

function normalizePartNumber(string $value): string
{
    return trim(str_replace(',', '.', $value));
}

function looksLikeEuroAmount(string $value): bool
{
    $n = parseEuro($value);

    return $n !== null && $n >= 100;
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

function findProduct($entityManager, string $codiceArticolo, string $nome): ?\Espo\ORM\Entity
{
    if ($codiceArticolo !== '') {
        $product = $entityManager
            ->getRDBRepository('Product')
            ->where(['partNumber' => $codiceArticolo])
            ->findOne();

        if ($product) {
            return $product;
        }

        $alt = str_replace('.', '', $codiceArticolo);

        if ($alt !== $codiceArticolo) {
            $product = $entityManager
                ->getRDBRepository('Product')
                ->where(['partNumber' => $alt])
                ->findOne();

            if ($product) {
                return $product;
            }
        }
    }

    if ($nome === '') {
        return null;
    }

    return $entityManager
        ->getRDBRepository('Product')
        ->where(['name' => $nome])
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
