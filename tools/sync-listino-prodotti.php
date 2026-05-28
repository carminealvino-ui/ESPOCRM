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
 *   --csv=PATH               CSV (;): brand;categoria;denominazione;codice_listino;prezzo_listino;prezzo_codice
 *                            Nome prodotto CRM = BRAND - CATEGORIA - DENOMINAZIONE
 *   --aliquota-iva=10        Aliquota IVA listino (default 10)
 *   --prezzi-iva-esclusa     CSV già in IVA esclusa (nessuna conversione)
 *                            prezzo_listino = TOTALE PDF (es. 3950 IVI → 3590,91 escl. in CRM)
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
    'aliquota-iva::',
    'prezzi-iva-esclusa',
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

$dryRun = array_key_exists('dry-run', $options);
$createMissing = !array_key_exists('no-create-missing', $options);
$dateStart = $options['date-start'] ?? '2026-05-07';
$brandId = $options['product-brand-id'] ?? null;
$aliquotaIva = isset($options['aliquota-iva']) ? (float) $options['aliquota-iva'] : 10.0;
$convertiIvaEsclusa = !array_key_exists('prezzi-iva-esclusa', $options);

$priceBook = resolvePriceBook($entityManager, $options);

if (!$priceBook) {
    fwrite(STDERR, "Price Book non trovato. Usare --price-book-id o --price-book-name.\n");
    exit(1);
}

fwrite(STDOUT, "Listino: {$priceBook->get('name')} ({$priceBook->getId()})\n");
fwrite(STDOUT, "CSV: {$csvPath}\n");

$priceBookIvaInclusa = (bool) $priceBook->get('isTaxInclusive');

if ($convertiIvaEsclusa) {
    fwrite(STDOUT, "IVA: CSV = PDF IVA {$aliquotaIva}% inclusa\n");
    fwrite(STDOUT, "     → Product listPrice/prezzoCodice: IVA esclusa (provvigioni)\n");

    if ($priceBookIvaInclusa) {
        fwrite(STDOUT, "     → ProductPrice.price: IVA inclusa (listino is_tax_inclusive=1)\n");
    } else {
        fwrite(STDOUT, "     → ProductPrice.price: IVA esclusa\n");
    }
} else {
    fwrite(STDOUT, "IVA: importi CSV già IVA esclusa ovunque\n");
}

fwrite(STDOUT, $dryRun ? "MODALITÀ: dry-run\n" : "MODALITÀ: APPLY\n");

$rows = readCsv($csvPath);
$stats = ['updated' => 0, 'created' => 0, 'prices' => 0, 'skipped' => 0, 'errors' => 0];

foreach ($rows as $i => $row) {
    $line = $i + 2;
    $identity = parseProductIdentity($row);
    $codiceListino = resolveCodiceListino($row);
    $prezzoListinoIvi = parseEuro($row['prezzo_listino'] ?? null);
    $prezzoCodiceIvi = parseEuro($row['prezzo_codice'] ?? null);

    if ($prezzoCodiceIvi === null && $codiceListino !== '') {
        $prezzoCodiceIvi = derivePrezzoCodiceFromArticolo($codiceListino);
    }

    [$prezzoListino, $prezzoCodice, $ivaLog] = applyIvaConversion(
        $prezzoListinoIvi,
        $prezzoCodiceIvi,
        $aliquotaIva,
        $convertiIvaEsclusa
    );

    $prezzoPerProductPrice = $prezzoListino;

    if ($priceBookIvaInclusa && $prezzoListinoIvi !== null) {
        $prezzoPerProductPrice = $prezzoListinoIvi;
    }

    if ($identity['denominazione'] === '' && $identity['name'] === '') {
        fwrite(STDOUT, "[riga {$line}] SKIP: denominazione / brand-categoria vuoti\n");
        $stats['skipped']++;

        continue;
    }

    $skipReason = shouldSkipImportRow($row, $identity);

    if ($skipReason !== null) {
        fwrite(STDOUT, "[riga {$line}] SKIP: {$skipReason}\n");
        $stats['skipped']++;

        continue;
    }

    $filterCategoria = getenv('FILTER_CATEGORIA') ?: '';

    if ($filterCategoria !== '' && stripos((string) ($row['categoria'] ?? ''), $filterCategoria) === false) {
        $stats['skipped']++;

        continue;
    }

    $label = $identity['denominazione'] !== '' ? $identity['denominazione'] : $identity['name'];

    try {
        $product = findProductByIdentity($entityManager, $identity);

        if (!$product && $createMissing) {
            $product = $entityManager->getNewEntity('Product');
            $identityPatch = buildIdentityPatch($entityManager, $product, $identity, $brandId);
            $product->set($identityPatch);
            $product->set('status', 'Available');
            $product->set('type', 'Regular');
            $product->set('itemType', ($row['tipo'] ?? '') === 'servizio' ? 'Service' : 'Goods');

            $pricePatch = buildProductPricePatch($entityManager, $prezzoListino, $prezzoCodice);

            if ($pricePatch !== []) {
                $product->set($pricePatch);
            }

            $patch = array_merge($identityPatch, $pricePatch);

            if (!$dryRun) {
                $entityManager->saveEntity($product);
            }

            fwrite(STDOUT, "[{$label}] CREATO prodotto: {$product->get('name')}\n");

            if ($pricePatch !== []) {
                fwrite(STDOUT, "  prezzi: " . json_encode($pricePatch, JSON_UNESCAPED_UNICODE) . "\n");
            }

            $catWarn = formatCategoryWarning($identity, $patch);

            if ($catWarn !== null) {
                fwrite(STDERR, "  ATTENZIONE: {$catWarn}\n");
            }

            if ($ivaLog !== '') {
                fwrite(STDOUT, "  {$ivaLog}\n");
            }

            $stats['created']++;
        } elseif (!$product) {
            fwrite(STDERR, "[{$label}] ERRORE: prodotto assente (--no-create-missing)\n");
            $stats['errors']++;

            continue;
        } else {
            $patch = [];
            $label = $product->get('name') ?: $identity['denominazione'];

            $identityPatch = buildIdentityPatch($entityManager, $product, $identity, $brandId);
            $patch = array_merge($patch, $identityPatch, buildProductPricePatch($entityManager, $prezzoListino, $prezzoCodice));

            if ($patch !== []) {
                $product->set($patch);

                if (!$dryRun) {
                    $entityManager->saveEntity($product);
                }

                fwrite(STDOUT, "[{$label}] AGGIORNATO: " . json_encode($patch, JSON_UNESCAPED_UNICODE) . "\n");

                if ($ivaLog !== '') {
                    fwrite(STDOUT, "  {$ivaLog}\n");
                }

                $catWarn = formatCategoryWarning($identity, $patch);

                if ($catWarn !== null) {
                    fwrite(STDERR, "  ATTENZIONE: {$catWarn}\n");
                }

                $stats['updated']++;

                $priceOnlyPatch = buildProductPricePatch($entityManager, $prezzoListino, $prezzoCodice);
                $stats['updated'] += syncAliasProductsPrices(
                    $entityManager,
                    $product,
                    $identity,
                    $priceOnlyPatch,
                    $dryRun
                );
            }
        }

        if ($product && $prezzoPerProductPrice !== null && metadataHasEntity($entityManager, 'ProductPrice')) {
            $productId = $product->getId();

            if ($dryRun && !$productId) {
                $stats['prices']++;
                fwrite(STDOUT, "[{$label}] product_price (listino): dry-run: {$prezzoPerProductPrice} EUR dal {$dateStart} (con creazione prodotto)\n");
            } else {
                $ppResult = upsertProductPrice(
                    $entityManager,
                    $product,
                    $priceBook,
                    $prezzoPerProductPrice,
                    $dateStart,
                    $dryRun
                );

                if ($ppResult) {
                    $stats['prices']++;
                    fwrite(STDOUT, "[{$label}] product_price (listino): {$ppResult}\n");
                }
            }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "[{$label}] ERRORE: {$e->getMessage()}\n");
        $stats['errors']++;
    }
}

fwrite(STDOUT, "\nRiepilogo: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n");

exit($stats['errors'] > 0 ? 1 : 0);

/**
 * @return array<string, float>
 */
function buildProductPricePatch(
    \Espo\ORM\EntityManager $entityManager,
    ?float $prezzoListino,
    ?float $prezzoCodice
): array {
    $patch = [];

    if ($prezzoListino !== null) {
        if (defsHasAttribute($entityManager, 'Product', 'listPrice')) {
            $patch['listPrice'] = $prezzoListino;
        }

        if (defsHasAttribute($entityManager, 'Product', 'unitPrice')) {
            $patch['unitPrice'] = $prezzoListino;
        }
    }

    if ($prezzoCodice !== null && defsHasAttribute($entityManager, 'Product', 'prezzoCodice')) {
        $patch['prezzoCodice'] = $prezzoCodice;
    }

    return $patch;
}

function defsHasAttribute(\Espo\ORM\EntityManager $entityManager, string $entityType, string $attribute): bool
{
    try {
        return $entityManager->getDefs()->getEntity($entityType)->hasAttribute($attribute);
    } catch (Throwable) {
        return false;
    }
}

function metadataHasEntity(\Espo\ORM\EntityManager $entityManager, string $entityType): bool
{
    return $entityManager->getMetadata()->has($entityType);
}

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

/**
 * @return array{brand: string, categoria: string, denominazione: string, name: string}
 */
function parseProductIdentity(array $row): array
{
    $brand = trim((string) ($row['brand'] ?? ''));
    $categoria = trim((string) ($row['categoria'] ?? $row['category'] ?? ''));
    $denominazione = trim((string) ($row['denominazione'] ?? ''));
    $nome = trim((string) ($row['nome'] ?? ''));

    if ($brand === '' && $categoria === '' && $denominazione === '' && $nome !== '') {
        $parts = preg_split('/\s+-\s+/', $nome, 3);

        if (is_array($parts) && count($parts) === 3) {
            $brand = trim($parts[0]);
            $categoria = trim($parts[1]);
            $denominazione = trim($parts[2]);
        }
    }

    return [
        'brand' => $brand,
        'categoria' => $categoria,
        'denominazione' => $denominazione,
        'name' => buildProductName($brand, $categoria, $denominazione),
    ];
}

function buildProductName(string $brand, string $categoria, string $denominazione): string
{
    $parts = [];

    foreach ([$brand, $categoria, $denominazione] as $part) {
        $part = trim($part);

        if ($part !== '') {
            $parts[] = $part;
        }
    }

    return implode(' - ', $parts);
}

/** Codice listino PDF (es. 00.02.95.0) → deriva prezzo a codice, non è il part_number. */
function resolveCodiceListino(array $row): string
{
    foreach (['codice_listino', 'codice_articolo', 'codice'] as $key) {
        $value = trim((string) ($row[$key] ?? ''));

        if ($value !== '' && !looksLikeEuroAmount($value)) {
            return trim(str_replace(',', '.', $value));
        }
    }

    return '';
}

function looksLikeEuroAmount(string $value): bool
{
    $n = parseEuro($value);

    return $n !== null && $n >= 100;
}

function applyIvaConversion(
    ?float $prezzoListino,
    ?float $prezzoCodice,
    float $aliquotaIva,
    bool $converti
): array {
    if (!$converti || $aliquotaIva <= 0) {
        return [$prezzoListino, $prezzoCodice, ''];
    }

    $parts = [];
    $listinoNetto = $prezzoListino;
    $codiceNetto = $prezzoCodice;

    if ($prezzoListino !== null) {
        $listinoNetto = toIvaEsclusa($prezzoListino, $aliquotaIva);
        $parts[] = sprintf(
            'listino %s IVI → %s IVA escl.',
            formatEuro($prezzoListino),
            formatEuro($listinoNetto)
        );
    }

    if ($prezzoCodice !== null) {
        $codiceNetto = toIvaEsclusa($prezzoCodice, $aliquotaIva);
        $parts[] = sprintf(
            'codice %s IVI → %s IVA escl.',
            formatEuro($prezzoCodice),
            formatEuro($codiceNetto)
        );
    }

    return [$listinoNetto, $codiceNetto, implode('; ', $parts)];
}

function toIvaEsclusa(float $importoIvi, float $aliquotaPercent): float
{
    return round($importoIvi / (1 + $aliquotaPercent / 100), 2);
}

function formatEuro(float $amount): string
{
    return number_format($amount, 2, ',', '.') . ' €';
}

/**
 * Da codice listino Ariel (es. 00.02.95.0) → prezzo a codice 2950 IVI (segmenti 02.95.0).
 */
function derivePrezzoCodiceFromArticolo(string $codiceArticolo): ?float
{
    $parts = array_values(array_filter(explode('.', $codiceArticolo), static fn ($p) => $p !== ''));

    if (count($parts) < 4) {
        return null;
    }

    $segments = array_slice($parts, 1, 3);
    $digits = ltrim(implode('', $segments), '0');

    if ($digits === '' || !ctype_digit($digits)) {
        return null;
    }

    $value = (float) $digits;

    return $value > 0 ? round($value, 2) : null;
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

/**
 * Chiave per abbinare PROMO 1+1 / COMBO / OMAGGIO alla stessa riga listino.
 */
function normalizeDenominazioneKey(string $denominazione): string
{
    $s = strtoupper(trim($denominazione));
    $s = preg_replace('/\s+/', ' ', $s);
    $s = str_replace(' OMAGGIO', '', $s);
    $s = preg_replace('/\bBTU\b/', '', $s);
    $s = preg_replace('/\bPROMO\s*1\s*\+\s*1\b/', 'COMBO', $s);

    return trim(preg_replace('/\s+/', ' ', $s));
}

function extractDenominazioneFromProduct(\Espo\ORM\Entity $product): string
{
    $denominazione = trim((string) ($product->get('denominazione') ?? ''));

    if ($denominazione !== '') {
        return $denominazione;
    }

    $name = (string) ($product->get('name') ?? '');
    $parts = preg_split('/\s+-\s+/', $name, 3);

    if (is_array($parts) && count($parts) === 3) {
        return trim($parts[2]);
    }

    return $name;
}

/**
 * @return \Espo\ORM\Entity[]
 */
function findProductsByDenomKey(
    \Espo\ORM\EntityManager $entityManager,
    string $denomKey,
    ?string $brandId,
    ?string $categoryId
): array {
    if ($denomKey === '') {
        return [];
    }

    $where = [];

    if ($brandId) {
        $where['brandId'] = $brandId;
    }

    if ($categoryId) {
        $where['categoryId'] = $categoryId;
    }

    $collection = $entityManager
        ->getRDBRepository('Product')
        ->where($where)
        ->find();

    $matched = [];

    foreach ($collection as $product) {
        $candidateKey = normalizeDenominazioneKey(extractDenominazioneFromProduct($product));

        if ($candidateKey === $denomKey) {
            $matched[$product->getId()] = $product;
        }
    }

    return array_values($matched);
}

function findProductByIdentity(\Espo\ORM\EntityManager $entityManager, array $identity): ?\Espo\ORM\Entity
{
    $name = $identity['name'] ?? '';
    $denominazione = $identity['denominazione'] ?? '';

    if ($name !== '') {
        $product = $entityManager
            ->getRDBRepository('Product')
            ->where(['name' => $name])
            ->findOne();

        if ($product) {
            return $product;
        }
    }

    if ($denominazione === '') {
        return null;
    }

    $brand = resolveProductBrand($entityManager, $identity['brand']);
    $category = resolveProductCategory($entityManager, $identity['categoria'], $brand);
    $brandId = $brand?->getId();
    $categoryId = $category?->getId();

    // Denominazione + brand (+ categoria se risolta)
    $where = ['denominazione' => $denominazione];

    if ($brand) {
        $where['brandId'] = $brandId;
    }

    if ($category) {
        $where['categoryId'] = $categoryId;
    }

    $product = $entityManager
        ->getRDBRepository('Product')
        ->where($where)
        ->findOne();

    if ($product) {
        return $product;
    }

    // Alias CRM: COMBO 9000+9000 ↔ PROMO 1+1 9000+9000 OMAGGIO (stesso listino)
    $denomKey = normalizeDenominazioneKey($denominazione);
    $aliases = findProductsByDenomKey($entityManager, $denomKey, $brandId, $categoryId);

    if ($aliases !== []) {
        return $aliases[0];
    }

    // Fallback: nome contiene denominazione (prodotti creati senza denominazione in sync precedenti)
    $fallbackWhere = ['name*' => $denominazione];

    if ($brand) {
        $fallbackWhere['brandId'] = $brandId;
    }

    $collection = $entityManager
        ->getRDBRepository('Product')
        ->where($fallbackWhere)
        ->limit(0, 5)
        ->find();

    foreach ($collection as $candidate) {
        $candidateName = (string) $candidate->get('name');

        if (stripos($candidateName, $denominazione) !== false) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Aggiorna anche prodotti con denominazione equivalente (es. COMBO vs PROMO 1+1).
 *
 * @return int numero prodotti aggiuntivi aggiornati
 */
function syncAliasProductsPrices(
    \Espo\ORM\EntityManager $entityManager,
    \Espo\ORM\Entity $primaryProduct,
    array $identity,
    array $pricePatch,
    bool $dryRun
): int {
    if ($pricePatch === []) {
        return 0;
    }

    $brand = resolveProductBrand($entityManager, $identity['brand']);
    $category = resolveProductCategory($entityManager, $identity['categoria'], $brand);
    $denomKey = normalizeDenominazioneKey($identity['denominazione'] ?? '');

    if ($denomKey === '') {
        return 0;
    }

    $aliases = findProductsByDenomKey(
        $entityManager,
        $denomKey,
        $brand?->getId(),
        $category?->getId()
    );

    $count = 0;

    foreach ($aliases as $aliasProduct) {
        if ($aliasProduct->getId() === $primaryProduct->getId()) {
            continue;
        }

        $aliasProduct->set($pricePatch);

        if (!$dryRun) {
            $entityManager->saveEntity($aliasProduct);
        }

        $label = $aliasProduct->get('name') ?: extractDenominazioneFromProduct($aliasProduct);
        fwrite(STDOUT, "[{$label}] AGGIORNATO (alias denominazione): " . json_encode($pricePatch, JSON_UNESCAPED_UNICODE) . "\n");
        $count++;
    }

    return $count;
}

function resolveProductBrand(\Espo\ORM\EntityManager $entityManager, string $brandName): ?\Espo\ORM\Entity
{
    if ($brandName === '' || !metadataHasEntity($entityManager, 'ProductBrand')) {
        return null;
    }

    return $entityManager
        ->getRDBRepository('ProductBrand')
        ->where(['name' => $brandName])
        ->findOne();
}

function resolveProductCategory(
    \Espo\ORM\EntityManager $entityManager,
    string $categoryName,
    ?\Espo\ORM\Entity $brand
): ?\Espo\ORM\Entity {
    if ($categoryName === '' || !metadataHasEntity($entityManager, 'ProductCategory')) {
        return null;
    }

    foreach (categoryNameCandidates($categoryName) as $candidate) {
        $where = ['name' => $candidate];

        if ($brand) {
            $where['productBrandId'] = $brand->getId();
        }

        $category = $entityManager
            ->getRDBRepository('ProductCategory')
            ->where($where)
            ->findOne();

        if ($category) {
            return $category;
        }
    }

    return null;
}

/** @return list<string> */
function categoryNameCandidates(string $categoryName): array
{
    $categoryName = trim($categoryName);
    $aliases = [
        'CALDAIE' => ['CALDAIE', 'CALDAIE A GAS'],
        'STUFE' => ['STUFE', 'STUFE A PELLET'],
        'CLIMATIZZAZIONE' => ['CLIMATIZZAZIONE', 'CLIMATIZZATORI'],
    ];

    $candidates = $aliases[$categoryName] ?? [$categoryName];

    return array_values(array_unique($candidates));
}

function applyProductIdentity(
    \Espo\ORM\EntityManager $entityManager,
    \Espo\ORM\Entity $product,
    array $identity,
    ?string $defaultBrandId
): void {
    $patch = buildIdentityPatch($entityManager, $product, $identity, $defaultBrandId);
    $product->set($patch);
}

function buildIdentityPatch(
    \Espo\ORM\EntityManager $entityManager,
    \Espo\ORM\Entity $product,
    array $identity,
    ?string $defaultBrandId
): array {
    $patch = [];

    if ($identity['name'] !== '' && $product->get('name') !== $identity['name']) {
        $patch['name'] = $identity['name'];
    }

    if (defsHasAttribute($entityManager, 'Product', 'denominazione') && $identity['denominazione'] !== '') {
        $patch['denominazione'] = $identity['denominazione'];
    }

    $brand = resolveProductBrand($entityManager, $identity['brand']);

    if ($brand && defsHasAttribute($entityManager, 'Product', 'brandId')) {
        $patch['brandId'] = $brand->getId();
    } elseif ($defaultBrandId && defsHasAttribute($entityManager, 'Product', 'brandId')) {
        $patch['brandId'] = $defaultBrandId;
    }

    $brandForCategory = $brand;

    if (!$brandForCategory && !empty($patch['brandId'])) {
        $brandForCategory = $entityManager->getEntityById('ProductBrand', $patch['brandId']);
    }

    $category = resolveProductCategory($entityManager, $identity['categoria'], $brandForCategory);

    if ($category && defsHasAttribute($entityManager, 'Product', 'categoryId')) {
        $patch['categoryId'] = $category->getId();
    }

    return $patch;
}

function formatCategoryWarning(array $identity, array $patch): ?string
{
    if ($identity['categoria'] === '') {
        return null;
    }

    if (!empty($patch['categoryId'])) {
        return null;
    }

    return "categoria CSV «{$identity['categoria']}» non trovata in ProductCategory (brand ARIEL)";
}

/**
 * Esclude righe CSV generate male dal PDF (testi legali, nomi troppo lunghi).
 */
function shouldSkipImportRow(array $row, array $identity): ?string
{
    $denom = $identity['denominazione'] ?? '';

    if (strlen($denom) > 95) {
        return 'denominazione troppo lunga (probabile testo PDF)';
    }

    if (preg_match(
        '/CONDIZIONI DI VENDITA|PER LEGGE LA NUOVA|SPECIFICHE PROMO|MESSAGGIO PROMO:|INSTALLAZIONE PRODOTTI A PELLET\s+1\s+CURVA|10%\s+INCLUSA/i',
        $denom
    )) {
        return 'testo informativo PDF, non prodotto';
    }

    return null;
}

function upsertProductPrice(
    $entityManager,
    \Espo\ORM\Entity $product,
    \Espo\ORM\Entity $priceBook,
    float $price,
    string $dateStart,
    bool $dryRun
): ?string {
    $productId = $product->getId();

    if (!$productId) {
        if ($dryRun) {
            return "dry-run: nuovo prezzo {$price} dal {$dateStart}";
        }

        throw new RuntimeException('Entity ID is not set.');
    }

    $existing = $entityManager
        ->getRDBRepository('ProductPrice')
        ->where([
            'productId' => $productId,
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
