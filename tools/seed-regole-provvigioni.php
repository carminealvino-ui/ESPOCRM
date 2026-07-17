#!/usr/bin/env php
<?php
/**
 * Popola regole provvigionali ARQUATI PNC e Ariel 2026 (upsert per id).
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/seed-regole-provvigioni.php --dry-run
 *   php tools/seed-regole-provvigioni.php
 *   php tools/seed-regole-provvigioni.php --only=arquati
 *   php tools/seed-regole-provvigioni.php --only=ariel
 *
 * Opzioni:
 *   --crm-root=PATH   Root installazione Espo (default: cwd)
 *   --dry-run         Anteprima senza salvataggio
 *   --only=SET        arquati | ariel | all (default: all)
 */

declare(strict_types=1);

function usage(): void
{
    fwrite(STDERR, "Usage: php seed-regole-provvigioni.php [--crm-root=DIR] [--dry-run] [--only=arquati|ariel|all]\n");
    exit(1);
}

$options = getopt('', ['crm-root::', 'dry-run', 'only::']);

$crmRoot = rtrim($options['crm-root'] ?? getenv('CRM_ROOT') ?: getcwd(), '/');
$dryRun = array_key_exists('dry-run', $options);
$only = strtolower($options['only'] ?? 'all');

if (!in_array($only, ['arquati', 'ariel', 'all'], true)) {
    fwrite(STDERR, "Valore --only non valido: {$only}\n");
    usage();
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

$config = include $configInternal;
$db = is_array($config) ? ($config['database'] ?? []) : [];
$host = $db['host'] ?? 'localhost';
$port = isset($db['port']) ? (int) $db['port'] : 3306;
$dbname = $db['dbname'] ?? '';
$user = $db['user'] ?? '';
$pass = $db['password'] ?? '';
$charset = $db['charset'] ?? 'utf8mb4';
$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'regola_provvigionale'")->fetchColumn();
} catch (PDOException $e) {
    fwrite(STDERR, 'Connessione DB fallita: ' . $e->getMessage() . "\n");
    exit(1);
}

if (!$tableExists) {
    fwrite(STDERR, "Tabella regola_provvigionale assente. Eseguire prima:\n");
    fwrite(STDERR, "  php tools/create-regola-provvigionale-table.php\n");
    exit(1);
}

$rules = getSeedRules($only);

fwrite(STDOUT, 'Regole da applicare: ' . count($rules) . " ({$only})\n");
fwrite(STDOUT, $dryRun ? "MODALITÀ: dry-run\n\n" : "MODALITÀ: APPLY\n\n");

$stats = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0];

foreach ($rules as $ruleData) {
    $id = $ruleData['id'];

    try {
        $result = upsertRule($entityManager, $ruleData, $dryRun);
        $stats[$result]++;
        $label = strtoupper($result);

        fwrite(STDOUT, "[{$label}] {$id} — {$ruleData['name']}\n");
    } catch (Throwable $e) {
        $stats['errors']++;
        fwrite(STDERR, "[ERR] {$id}: {$e->getMessage()}\n");
    }
}

fwrite(STDOUT, "\nRiepilogo: creati={$stats['created']}, aggiornati={$stats['updated']}, invariati={$stats['unchanged']}, errori={$stats['errors']}\n");

if ($stats['errors'] > 0) {
    exit(1);
}

if (!$dryRun && ($stats['created'] > 0 || $stats['updated'] > 0)) {
    fwrite(STDOUT, "Consigliato: php clear_cache.php\n");
}

/**
 * @return list<array<string, mixed>>
 */
function getSeedRules(string $only): array
{
    $arquati = [
        // Tende da sole
        rule('arqTdsGe25', 'ARQUATI Tende ≥25%', 'Allegato C 0.1 — Tende da sole', 410, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 18, margineMin: 25),
        rule('arqTds1524', 'ARQUATI Tende 15-24,9%', null, 400, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 15, margineMin: 15, margineMax: 24.9),
        rule('arqTds514', 'ARQUATI Tende 5-14,9%', null, 390, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 13, margineMin: 5, margineMax: 14.9),
        rule('arqTdsCod', 'ARQUATI Tende a codice-4,9%', null, 380, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 11, margineMin: 0, margineMax: 4.9),
        rule('arqTdsM05', 'ARQUATI Tende -0,1% / -5%', null, 370, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 9, margineMin: -5, margineMax: -0.1),
        rule('arqTdsM10', 'ARQUATI Tende -5,1% / -10%', null, 360, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 7, margineMin: -10, margineMax: -5.1),
        rule('arqTdsM20', 'ARQUATI Tende -10,1% / -20%', null, 350, 'ARQUATI_PNC', 'Tende da Sole', 'PercentualeMargine', 'Provvigione Base', 5, margineMin: -20, margineMax: -10.1),
        // Pergole
        rule('arqPerGe25', 'ARQUATI Pergole ≥25%', null, 410, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 15, margineMin: 25),
        rule('arqPer1524', 'ARQUATI Pergole 15-24,9%', null, 400, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 13, margineMin: 15, margineMax: 24.9),
        rule('arqPer514', 'ARQUATI Pergole 5-14,9%', null, 390, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 11, margineMin: 5, margineMax: 14.9),
        rule('arqPerCod', 'ARQUATI Pergole a codice-4,9%', null, 380, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 9, margineMin: 0, margineMax: 4.9),
        rule('arqPerM05', 'ARQUATI Pergole -0,1% / -5%', null, 370, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 7, margineMin: -5, margineMax: -0.1),
        rule('arqPerM10', 'ARQUATI Pergole -5,1% / -10%', null, 360, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 6, margineMin: -10, margineMax: -5.1),
        rule('arqPerM20', 'ARQUATI Pergole -10,1% / -20%', null, 350, 'ARQUATI_PNC', 'Pergole', 'PercentualeMargine', 'Provvigione Base', 4, margineMin: -20, margineMax: -10.1),
        // Vetrate
        rule('arqVetGe25', 'ARQUATI Vetrate ≥25%', null, 410, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 13, margineMin: 25),
        rule('arqVet1524', 'ARQUATI Vetrate 15-24,9%', null, 400, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 10, margineMin: 15, margineMax: 24.9),
        rule('arqVet514', 'ARQUATI Vetrate 5-14,9%', null, 390, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 9, margineMin: 5, margineMax: 14.9),
        rule('arqVetCod', 'ARQUATI Vetrate a codice-4,9%', null, 380, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 8, margineMin: 0, margineMax: 4.9),
        rule('arqVetM05', 'ARQUATI Vetrate -0,1% / -5%', null, 370, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 6, margineMin: -5, margineMax: -0.1),
        rule('arqVetM10', 'ARQUATI Vetrate -5,1% / -10%', null, 360, 'ARQUATI_PNC', 'Vetrate', 'PercentualeMargine', 'Provvigione Base', 4, margineMin: -10, margineMax: -5.1),
        // Clima / accessori
        rule('arqClima9', 'ARQUATI Clima/accessori 9% listino', 'Ciclamino, timpani, sensori, GHIBLI, SCIROCCO, BORA…', 320, 'ARQUATI_PNC', 'Clima e altro', 'PercentualeImponibile', 'Provvigione Base', 9),
        // Integrazione contatti personali
        rule('arqCpP5', 'ARQUATI integrazione contatti personali +5%', 'Somma al calcolo base se contattoPersonaleArquati', 520, 'ARQUATI_PNC', null, 'PercentualeImponibile', 'Plus Provvigionale', 5),
    ];

    $ariel = [
        rule('arielBase105', 'Ariel 2026 — 10% + 5% imponibile', 'Provvigione standard GDL/Ariel (mandato 10% + addizionale 5%)', 600, 'ARIEL_2026', null, 'PercentualeImponibileAddizionale', 'Provvigione Base', 10, percentualeAddizionale: 5),
        rule('arielBase10', 'Ariel 2026 — solo 10% (ordine incompleto)', 'Decurtazione 5 punti se ordine incompleto / sopralluogo tecnico', 610, 'ARIEL_2026', null, 'PercentualeImponibile', 'Provvigione Base', 10),
        rule('arielPlus35', 'Ariel 2026 — 35% su plusvalenza', 'Plus maturata sopra listino codice (contatore minus/plus)', 550, 'ARIEL_2026', null, 'PercentualePlusvalenza', 'Plus Provvigionale', 35),
    ];

    return match ($only) {
        'arquati' => $arquati,
        'ariel' => $ariel,
        default => array_merge($arquati, $ariel),
    };
}

/**
 * @return array<string, mixed>
 */
function rule(
    string $id,
    string $name,
    ?string $description,
    int $priorita,
    string $regimeProvvigione,
    ?string $gruppoProvvigione,
    string $tipoCalcolo,
    string $tipoProvvigioneRecord,
    float $percentuale,
    ?float $percentualeAddizionale = null,
    ?float $margineMin = null,
    ?float $margineMax = null
): array {
    return [
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'attiva' => true,
        'priorita' => $priorita,
        'regimeProvvigione' => $regimeProvvigione,
        'gruppoProvvigione' => $gruppoProvvigione,
        'tipoCalcolo' => $tipoCalcolo,
        'tipoProvvigioneRecord' => $tipoProvvigioneRecord,
        'percentuale' => $percentuale,
        'percentualeAddizionale' => $percentualeAddizionale,
        'margineMin' => $margineMin,
        'margineMax' => $margineMax,
    ];
}

/**
 * @param array<string, mixed> $ruleData
 */
function upsertRule(\Espo\ORM\EntityManager $entityManager, array $ruleData, bool $dryRun): string
{
    $id = $ruleData['id'];
    $entity = $entityManager->getEntityById('RegolaProvvigionale', $id);
    $isNew = $entity === null;

    if ($isNew) {
        $entity = $entityManager->getNewEntity('RegolaProvvigionale');
        $entity->set('id', $id);
    }

    $payload = [
        'name' => $ruleData['name'],
        'description' => $ruleData['description'],
        'attiva' => $ruleData['attiva'],
        'priorita' => $ruleData['priorita'],
        'regimeProvvigione' => $ruleData['regimeProvvigione'],
        'gruppoProvvigione' => $ruleData['gruppoProvvigione'],
        'tipoCalcolo' => $ruleData['tipoCalcolo'],
        'tipoProvvigioneRecord' => $ruleData['tipoProvvigioneRecord'],
        'percentuale' => $ruleData['percentuale'],
        'percentualeAddizionale' => $ruleData['percentualeAddizionale'],
        'margineMin' => $ruleData['margineMin'],
        'margineMax' => $ruleData['margineMax'],
    ];

    if (!$isNew && entityMatches($entity, $payload)) {
        return 'unchanged';
    }

    $entity->set($payload);

    if ($dryRun) {
        return $isNew ? 'created' : 'updated';
    }

    $entityManager->saveEntity($entity);

    return $isNew ? 'created' : 'updated';
}

/**
 * @param array<string, mixed> $payload
 */
function entityMatches(\Espo\ORM\Entity $entity, array $payload): bool
{
    foreach ($payload as $field => $value) {
        $current = $entity->get($field);

        if (is_float($value) || is_int($value)) {
            if ($current === null || $current === '') {
                if ($value !== null) {
                    return false;
                }

                continue;
            }

            if (abs((float) $current - (float) $value) > 0.001) {
                return false;
            }

            continue;
        }

        if ((string) ($current ?? '') !== (string) ($value ?? '')) {
            return false;
        }
    }

    return true;
}
