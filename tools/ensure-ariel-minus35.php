#!/usr/bin/env php
<?php

/**
 * Inserisce/ripristina la regola arielMinus35 (diagnostica inclusa).
 *
 *   php tools/ensure-ariel-minus35.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$pdo = $app->getContainer()->get('entityManager')->getPDO();

echo "=== ensure-ariel-minus35 v1 ===\n";

$check = $pdo->prepare('SELECT id, deleted, attiva, tipo_provvigione_record, percentuale FROM regola_provvigionale WHERE id = ?');
$check->execute(['arielMinus35']);
$existing = $check->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo 'PRIMA: ' . json_encode($existing, JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "PRIMA: regola assente\n";
}

$sql = <<<'SQL'
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record,
    percentuale
) VALUES (
    'arielMinus35',
    'Ariel 2026 — 35% su minusvalenza',
    'Minus sotto listino codice (contatore minus/plus negativo)',
    0, 1, 545,
    'ARIEL_2026',
    'PercentualePlusvalenza',
    'Minus Provvigionale',
    35
)
ON DUPLICATE KEY UPDATE
    deleted = 0,
    attiva = 1,
    name = VALUES(name),
    description = VALUES(description),
    priorita = VALUES(priorita),
    regime_provvigione = VALUES(regime_provvigione),
    tipo_calcolo = VALUES(tipo_calcolo),
    tipo_provvigione_record = VALUES(tipo_provvigione_record),
    percentuale = VALUES(percentuale)
SQL;

try {
    $pdo->exec($sql);
    echo "OK   upsert arielMinus35 eseguito\n";
} catch (Throwable $e) {
    echo 'ERRORE upsert: ' . $e->getMessage() . "\n";
    exit(1);
}

$check->execute(['arielMinus35']);
$after = $check->fetch(PDO::FETCH_ASSOC);

if (!$after || (int) ($after['deleted'] ?? 1) !== 0) {
    echo 'ERRORE: regola ancora assente o deleted=1 dopo upsert' . "\n";
    exit(1);
}

echo 'DOPO: ' . json_encode($after, JSON_UNESCAPED_UNICODE) . "\n";
echo "RISULTATO: OK arielMinus35 presente e attiva\n";
