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
$pdo = $entityManager->getPDO();

$dryRun = array_key_exists('dry-run', $options);
$quoteIdFilter = $options['quote-id'] ?? null;

out('=== Backfill prodotticontratti da articoli contratto (SQL diretto) ===');

$where = 'qi.deleted = 0 AND q.deleted = 0 AND (qi.product_id IS NOT NULL OR p.id IS NOT NULL)';
$params = [];

if ($quoteIdFilter) {
    $where .= ' AND q.id = ?';
    $params[] = $quoteIdFilter;
}

$diag = $pdo->prepare(
    "SELECT COUNT(DISTINCT CONCAT(COALESCE(qi.product_id, p.id), ':', qi.quote_id)) AS coppie
     FROM quote_item qi
     INNER JOIN quote q ON q.id = qi.quote_id
     LEFT JOIN product p ON p.deleted = 0 AND p.name = qi.name
     WHERE {$where}"
);
$diag->execute($params);
out('Coppie prodotto-contratto negli articoli: ' . (int) $diag->fetchColumn());

$missing = $pdo->prepare(
    "SELECT COUNT(DISTINCT CONCAT(COALESCE(qi.product_id, p.id), ':', qi.quote_id)) AS mancanti
     FROM quote_item qi
     INNER JOIN quote q ON q.id = qi.quote_id
     LEFT JOIN product p ON p.deleted = 0 AND p.name = qi.name
     LEFT JOIN prodotticontratti pc ON pc.product_id = COALESCE(qi.product_id, p.id)
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

$reactivate = $pdo->prepare(
    "UPDATE prodotticontratti pc
     INNER JOIN quote_item qi ON qi.quote_id = pc.quote_id AND qi.deleted = 0
     INNER JOIN quote q ON q.id = qi.quote_id AND q.deleted = 0
     LEFT JOIN product p ON p.deleted = 0 AND p.name = qi.name
     SET pc.deleted = 0
     WHERE pc.deleted = 1
       AND pc.product_id = COALESCE(qi.product_id, p.id)
       AND {$where}"
);
$reactivate->execute($params);
out('Collegamenti riattivati (deleted=0): ' . $reactivate->rowCount());

$insertSql = "INSERT INTO prodotticontratti (product_id, quote_id, deleted)
     SELECT DISTINCT COALESCE(qi.product_id, p.id), qi.quote_id, 0
     FROM quote_item qi
     INNER JOIN quote q ON q.id = qi.quote_id
     LEFT JOIN product p ON p.deleted = 0 AND p.name = qi.name
     LEFT JOIN prodotticontratti pc ON pc.product_id = COALESCE(qi.product_id, p.id)
         AND pc.quote_id = qi.quote_id
     WHERE {$where} AND pc.id IS NULL";

$insert = $pdo->prepare($insertSql);
$insert->execute($params);
out('Nuovi collegamenti inseriti: ' . $insert->rowCount());

$missing->execute($params);
out('Collegamenti mancanti dopo backfill: ' . (int) $missing->fetchColumn());
out('Fatto. Apri Prodotto > tab Contratti e Ctrl+F5.');
