<?php

declare(strict_types=1);

$options = getopt('', ['crm-root::', 'dry-run', 'quote-id::']);

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

$crmRoot = rtrim($options['crm-root'] ?? getcwd(), '/');

if (!is_file($crmRoot . '/data/config-internal.php')) {
    fwrite(STDERR, "Root CRM errata: {$crmRoot}\n");
    exit(1);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

$app = new \Espo\Core\Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$sync = new \Espo\Custom\Services\ProductContrattiSync($entityManager);

$dryRun = array_key_exists('dry-run', $options);
$quoteIdFilter = $options['quote-id'] ?? null;

out('=== Backfill prodotticontratti da articoli contratto ===');

$pdo = $entityManager->getPDO();
$where = 'qi.deleted = 0 AND qi.product_id IS NOT NULL AND q.deleted = 0';
$params = [];

if ($quoteIdFilter) {
    $where .= ' AND q.id = ?';
    $params[] = $quoteIdFilter;
}

$diag = $pdo->prepare(
    "SELECT COUNT(DISTINCT CONCAT(qi.product_id, ':', qi.quote_id)) AS coppie
     FROM quote_item qi
     INNER JOIN quote q ON q.id = qi.quote_id
     WHERE {$where}"
);
$diag->execute($params);
$coppie = (int) $diag->fetchColumn();
out("Coppie prodotto-contratto negli articoli: {$coppie}");

$missing = $pdo->prepare(
    "SELECT COUNT(DISTINCT CONCAT(qi.product_id, ':', qi.quote_id)) AS mancanti
     FROM quote_item qi
     INNER JOIN quote q ON q.id = qi.quote_id
     LEFT JOIN prodotticontratti pc ON pc.product_id = qi.product_id
         AND pc.quote_id = qi.quote_id AND pc.deleted = 0
     WHERE {$where} AND pc.id IS NULL"
);
$missing->execute($params);
$mancanti = (int) $missing->fetchColumn();
out("Collegamenti mancanti in prodotticontratti: {$mancanti}");

if ($dryRun) {
    out('[dry-run] Nessuna modifica.');
    exit(0);
}

$quoteIds = $pdo->prepare(
    "SELECT DISTINCT q.id
     FROM quote_item qi
     INNER JOIN quote q ON q.id = qi.quote_id
     LEFT JOIN prodotticontratti pc ON pc.product_id = qi.product_id
         AND pc.quote_id = qi.quote_id AND pc.deleted = 0
     WHERE {$where} AND pc.id IS NULL"
);
$quoteIds->execute($params);

$processed = 0;

while ($row = $quoteIds->fetch(\PDO::FETCH_ASSOC)) {
    $quote = $entityManager->getEntityById('Quote', $row['id']);

    if (!$quote) {
        continue;
    }

    $sync->syncFromQuoteItems($quote);
    $processed++;
}

out("Contratti sincronizzati: {$processed}");

$missing->execute($params);
$rimasti = (int) $missing->fetchColumn();
out("Collegamenti mancanti dopo sync: {$rimasti}");
out('Fatto. Apri Prodotto > tab Contratti e Ctrl+F5.');
