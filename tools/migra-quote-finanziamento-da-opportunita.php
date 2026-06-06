<?php

declare(strict_types=1);

$options = getopt('', ['crm-root::', 'dry-run', 'force-rebuild']);

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function columnExists(\PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
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
$dataManager = $app->getContainer()->get('dataManager');
$pdo = $entityManager->getPDO();

$dryRun = array_key_exists('dry-run', $options);
$forceRebuild = array_key_exists('force-rebuild', $options);

out('=== Quote: campi finanziamento + migrazione da Opportunità ===');

$required = ['finanziamento', 'stato_finanziamento', 'stato_contratto'];
$missing = array_values(array_filter(
    $required,
    fn (string $col) => !columnExists($pdo, 'quote', $col)
));

if ($missing !== []) {
    out('Colonne quote mancanti: ' . implode(', ', $missing));

    if ($dryRun) {
        out('[dry-run] Servirebbe rebuild schema.');
        exit(0);
    }

    out('Rebuild schema...');
    $dataManager->rebuild();
    out('Rebuild completato.');
} elseif ($forceRebuild) {
    out('Rebuild schema (force)...');
    $dataManager->rebuild();
    out('Rebuild completato.');
}

$linked = (int) $pdo->query(
    'SELECT COUNT(*) FROM quote q
     INNER JOIN opportunity o ON o.id = q.opportunity_id AND o.deleted = 0
     WHERE q.deleted = 0'
)->fetchColumn();
out("Contratti con opportunità collegata: {$linked}");

if ($dryRun) {
    out('[dry-run] Nessuna modifica dati.');
    exit(0);
}

$sql = 'UPDATE quote q
    INNER JOIN opportunity o ON o.id = q.opportunity_id AND o.deleted = 0
    SET q.finanziamento = o.finanziamento,
        q.stato_finanziamento = o.stato_finanziamento,
        q.stato_contratto = o.stato_contratto
    WHERE q.deleted = 0';

$stmt = $pdo->prepare($sql);
$stmt->execute();
out('Contratti aggiornati da opportunità: ' . $stmt->rowCount());

$sample = $pdo->query(
    'SELECT q.number, q.finanziamento, q.stato_contratto, q.stato_finanziamento
     FROM quote q
     WHERE q.deleted = 0 AND q.opportunity_id IS NOT NULL
     ORDER BY q.modified_at DESC
     LIMIT 3'
)->fetchAll(\PDO::FETCH_ASSOC);

if ($sample !== []) {
    out('Esempio (ultimi 3):');
    foreach ($sample as $row) {
        out(sprintf(
            '  %s | fin=%s | stato=%s | finanz=%s',
            $row['number'] ?? '-',
            $row['finanziamento'] ?? '-',
            $row['stato_contratto'] ?? '-',
            $row['stato_finanziamento'] ?? '-'
        ));
    }
}

out('Fatto. Apri un Contratto e Ctrl+F5.');
