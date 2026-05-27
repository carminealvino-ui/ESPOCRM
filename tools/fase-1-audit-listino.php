<?php
/**
 * FASE 1 — Audit listino Ariel (solo lettura, nessuna modifica DB).
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/fase-1-audit-listino.php
 *
 * Verifica stato attuale vs valori attesi listino 07/05/2026 (es. Falcon).
 */

declare(strict_types=1);

const FALCON_PART = '00.02.95.0';
const EXPECT_LISTINO_ESCL = 3590.91;
const EXPECT_CODICE_ESCL = 2681.82;
const EXPECT_LISTINO_IVI = 3950.0;
const EXPECT_CODICE_IVI = 2950.0;
const IVA_PERCENT = 10.0;

$crmRoot = rtrim($argv[1] ?? getcwd(), '/');
$configInternal = $crmRoot . '/data/config-internal.php';

if (!is_file($configInternal)) {
    fwrite(STDERR, "Eseguire dalla root CRM. config-internal.php non trovato.\n");
    exit(1);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

$app = new \Espo\Core\Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

section('FASE 1 — Audit listino Ariel 07/05/2026 (sola lettura)');

section('B) Listini Price Book ARIEL');
$books = $em->getRDBRepository('PriceBook')
    ->where(['name*' => 'ARIEL'])
    ->order('name', 'ASC')
    ->find();

if (count($books) === 0) {
    writeln('  Nessun listino con nome ARIEL trovato.');
} else {
    foreach ($books as $pb) {
        $line = sprintf(
            '  - %s | id=%s | dateStart=%s | dateEnd=%s | status=%s',
            $pb->get('name'),
            $pb->getId(),
            $pb->get('dateStart') ?? '—',
            $pb->get('dateEnd') ?? '—',
            $pb->get('status') ?? '—'
        );
        writeln($line);
    }
}

section('C) Prodotto Falcon (' . FALCON_PART . ')');
$product = $em->getRDBRepository('Product')
    ->where(['partNumber' => FALCON_PART])
    ->findOne();

if (!$product) {
    $product = $em->getRDBRepository('Product')
        ->where(['name*' => 'Falcon'])
        ->findOne();
}

if (!$product) {
    writeln('  PRODOTTO NON TROVATO → Fase 3 dovrà crearlo/aggiornarlo.');
} else {
    $list = (float) ($product->get('listPrice') ?? 0);
    $codice = $product->get('prezzoCodice');
    $codiceF = $codice !== null && $codice !== '' ? (float) $codice : null;

    writeln('  id=' . $product->getId());
    writeln('  name=' . $product->get('name'));
    writeln('  partNumber=' . ($product->get('partNumber') ?? '—'));
    writeln(sprintf('  listPrice (IVA escl.)=%s → IVI ~%s', fmt($list), fmt($list * 1.1)));
    writeln(sprintf(
        '  prezzoCodice (IVA escl.)=%s → IVI ~%s',
        $codiceF !== null ? fmt($codiceF) : '—',
        $codiceF !== null ? fmt($codiceF * 1.1) : '—'
    ));
    writeln('  check listino: ' . checkAmount($list, EXPECT_LISTINO_ESCL));
    writeln('  check codice:  ' . ($codiceF !== null ? checkAmount($codiceF, EXPECT_CODICE_ESCL) : 'MANCANTE'));
}

section('D) ProductPrice Falcon per listini ARIEL');
if (!$product) {
    writeln('  (saltato: prodotto assente)');
} elseif (!$em->getMetadata()->hasEntity('ProductPrice')) {
    writeln('  Entity ProductPrice non disponibile.');
} else {
    $prices = $em->getRDBRepository('ProductPrice')
        ->where(['productId' => $product->getId()])
        ->order('dateStart', 'DESC')
        ->find();

    $found = false;

    foreach ($prices as $pp) {
        $pbId = $pp->get('priceBookId');
        $pb = $pbId ? $em->getEntityById('PriceBook', $pbId) : null;
        $pbName = $pb ? $pb->get('name') : $pbId;

        if ($pb && stripos((string) $pbName, 'ARIEL') === false) {
            continue;
        }

        $found = true;
        $price = (float) $pp->get('price');
        writeln(sprintf(
            '  - %s | price=%s (IVI ~%s) | %s → %s | %s',
            $pbName,
            fmt($price),
            fmt($price * 1.1),
            $pp->get('dateStart') ?? '—',
            $pp->get('dateEnd') ?? '—',
            $pp->get('status')
        ));
        writeln('    check: ' . checkAmount($price, EXPECT_LISTINO_ESCL));
    }

    if (!$found) {
        writeln('  Nessun prezzo Falcon su listini ARIEL.');
    }
}

section('E) Riepilogo Fase 1');
writeln('  Attesi (PDF IVA 10% inclusa → CRM IVA esclusa):');
writeln('    Listino TOTALE: ' . fmt(EXPECT_LISTINO_IVI) . ' IVI = ' . fmt(EXPECT_LISTINO_ESCL) . ' escl.');
writeln('    Prezzo codice:  ' . fmt(EXPECT_CODICE_IVI) . ' IVI = ' . fmt(EXPECT_CODICE_ESCL) . ' escl.');
writeln('');
writeln('  Prossimo passo dopo audit: inviare id/nome listino ARIEL corretto → Fase 2.');
writeln('  SQL equivalente: database/2026-05-27-fase-1-audit-solo-lettura.sql');

function section(string $title): void
{
    writeln('');
    writeln('=== ' . $title . ' ===');
}

function writeln(string $line): void
{
    fwrite(STDOUT, $line . PHP_EOL);
}

function fmt(float $n): string
{
    return number_format($n, 2, ',', '.') . ' €';
}

function checkAmount(float $actual, float $expected): string
{
    if (abs($actual - $expected) < 0.02) {
        return 'OK';
    }

    return sprintf('DA ALLINEARE (atteso %s, trovato %s)', fmt($expected), fmt($actual));
}
