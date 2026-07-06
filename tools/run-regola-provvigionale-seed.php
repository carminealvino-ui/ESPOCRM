<?php

/**
 * Crea tabella regole (via rebuild) e inserisce seed Ariel/Arquati se mancanti.
 *
 * Uso: php tools/run-regola-provvigionale-seed.php
 */

require dirname(__DIR__) . '/bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$pdo = $app->getContainer()->get('entityManager')->getPDO();

$files = [
    __DIR__ . '/../database/2026-05-26-gdl-ariel-2026-regole-provvigioni-seed.sql',
    __DIR__ . '/../database/2026-05-26-arquati-pnc-regole-provvigioni-seed.sql',
    __DIR__ . '/../database/2026-07-06-bonus-weekend-regola-provvigioni-seed.sql',
    __DIR__ . '/../database/2026-07-06-ariel-minus-35-regola-provvigioni-seed.sql',
];

/**
 * @return list<string>
 */
function splitSqlStatements(string $sql): array
{
    $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;

    $parts = preg_split('/;\s*\n/', $sql) ?: [];

    $statements = [];

    foreach ($parts as $part) {
        $statement = trim($part);

        if ($statement !== '') {
            $statements[] = $statement;
        }
    }

    return $statements;
}

foreach ($files as $file) {
    if (!is_file($file)) {
        echo "SKIP file mancante: {$file}\n";
        continue;
    }

    echo "=== {$file} ===\n";
    $sql = file_get_contents($file);

    if ($sql === false) {
        echo "ERRORE lettura\n";
        continue;
    }

    foreach (splitSqlStatements($sql) as $statement) {
        try {
            $pdo->exec($statement);
            echo "OK   statement eseguito\n";
        } catch (Throwable $e) {
            echo 'WARN: ' . $e->getMessage() . "\n";
        }
    }
}

/** @var array<string, array<string, mixed>> $requiredRules */
$requiredRules = [
    'arielBase105' => [
        'name' => 'Ariel 2026 — 10% + 5% imponibile',
        'description' => 'Provvigione standard GDL/Ariel (mandato 10% + addizionale 5%)',
        'attiva' => 1,
        'priorita' => 600,
        'regime_provvigione' => 'ARIEL_2026',
        'tipo_calcolo' => 'PercentualeImponibileAddizionale',
        'tipo_provvigione_record' => 'Provvigione Base',
        'percentuale' => 10,
        'percentuale_addizionale' => 5,
    ],
    'arielPlus35' => [
        'name' => 'Ariel 2026 — 35% su plusvalenza',
        'description' => 'Plus maturata sopra listino codice (contatore minus/plus)',
        'attiva' => 1,
        'priorita' => 550,
        'regime_provvigione' => 'ARIEL_2026',
        'tipo_calcolo' => 'PercentualePlusvalenza',
        'tipo_provvigione_record' => 'Plus Provvigionale',
        'percentuale' => 35,
    ],
    'arielMinus35' => [
        'name' => 'Ariel 2026 — 35% su minusvalenza',
        'description' => 'Minus sotto listino codice (contatore minus/plus negativo)',
        'attiva' => 1,
        'priorita' => 545,
        'regime_provvigione' => 'ARIEL_2026',
        'tipo_calcolo' => 'PercentualePlusvalenza',
        'tipo_provvigione_record' => 'Minus Provvigionale',
        'percentuale' => 35,
    ],
    'arqCpP5' => [
        'name' => 'ARQUATI integrazione contatti personali +5%',
        'description' => 'Somma al calcolo base se contattoPersonaleArquati',
        'attiva' => 1,
        'priorita' => 520,
        'regime_provvigione' => 'ARQUATI_PNC',
        'tipo_calcolo' => 'PercentualeImponibile',
        'tipo_provvigione_record' => 'Plus Provvigionale',
        'percentuale' => 5,
    ],
    'bonusWeekendSd' => [
        'name' => 'Bonus Sabato-Domenica',
        'description' => 'Extra provvigione se data contratto/appuntamento cade sabato o domenica',
        'attiva' => 1,
        'priorita' => 530,
        'regime_provvigione' => '',
        'tipo_calcolo' => 'PercentualeImponibile',
        'tipo_provvigione_record' => 'Bonus (Sabato-Domenica)',
        'percentuale' => 2,
    ],
];

$upsert = $pdo->prepare(
    'INSERT INTO regola_provvigionale (
        id, name, description, deleted, attiva, priorita,
        regime_provvigione, tipo_calcolo, tipo_provvigione_record,
        percentuale, percentuale_addizionale
    ) VALUES (
        :id, :name, :description, 0, :attiva, :priorita,
        :regime_provvigione, :tipo_calcolo, :tipo_provvigione_record,
        :percentuale, :percentuale_addizionale
    )
    ON DUPLICATE KEY UPDATE
        deleted = 0,
        name = VALUES(name),
        description = VALUES(description),
        attiva = VALUES(attiva),
        priorita = VALUES(priorita),
        regime_provvigione = VALUES(regime_provvigione),
        tipo_calcolo = VALUES(tipo_calcolo),
        tipo_provvigione_record = VALUES(tipo_provvigione_record),
        percentuale = VALUES(percentuale),
        percentuale_addizionale = VALUES(percentuale_addizionale)'
);

foreach ($requiredRules as $id => $rule) {
    $upsert->execute([
        'id' => $id,
        'name' => $rule['name'],
        'description' => $rule['description'],
        'attiva' => $rule['attiva'],
        'priorita' => $rule['priorita'],
        'regime_provvigione' => $rule['regime_provvigione'],
        'tipo_calcolo' => $rule['tipo_calcolo'],
        'tipo_provvigione_record' => $rule['tipo_provvigione_record'],
        'percentuale' => $rule['percentuale'],
        'percentuale_addizionale' => $rule['percentuale_addizionale'] ?? null,
    ]);
}

$check = $pdo->query('SELECT COUNT(*) FROM regola_provvigionale WHERE deleted = 0');
$count = (int) $check->fetchColumn();

echo "\nRegole attive in DB: {$count}\n";

$requiredIds = array_keys($requiredRules);
$placeholders = implode(',', array_fill(0, count($requiredIds), '?'));
$stmt = $pdo->prepare(
    "SELECT id FROM regola_provvigionale WHERE deleted = 0 AND id IN ({$placeholders})"
);
$stmt->execute($requiredIds);
$found = $stmt->fetchAll(PDO::FETCH_COLUMN);

$errors = 0;

foreach ($requiredIds as $id) {
    if (in_array($id, $found, true)) {
        echo "OK   regola {$id}\n";
    } else {
        echo "ERR  regola mancante: {$id}\n";
        $errors++;
    }
}

if ($errors > 0) {
    echo "\nRISULTATO: SEED INCOMPLETO ({$errors} regole mancanti)\n";
    exit(1);
}

echo "Seed regole provvigionali completato.\n";
