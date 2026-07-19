<?php

/**
 * Diagnostica 500 su GET/PUT Appuntamento.
 *
 *   php tools/diagnose-appuntamento-500.php 6900a442ee92bd824
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;

$id = null;
foreach ($argv ?? [] as $i => $arg) {
    if ($i === 0 || str_starts_with((string) $arg, '--')) {
        continue;
    }
    $id = trim((string) $arg);
    break;
}

if (!$id) {
    fwrite(STDERR, "Uso: php tools/diagnose-appuntamento-500.php <ID>\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

echo "=== Diagnosi Appuntamento {$id} ===\n";

try {
    $entity = $em->getEntityById('Appuntamento', $id);
} catch (Throwable $e) {
    echo "FATAL getEntityById: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

if (!$entity) {
    echo "ERR record non trovato\n";
    exit(1);
}

echo "OK load id={$id}\n";
echo "  name=" . $entity->get('name') . "\n";
echo "  status=" . $entity->get('status') . " sottostato=" . $entity->get('sottostato') . "\n";
echo "  prospectId=" . ($entity->get('prospectId') ?: '(vuoto)') . "\n";
echo "  prospectName=" . ($entity->get('prospectName') ?: '(vuoto)') . "\n";
echo "  parentType=" . ($entity->get('parentType') ?: '') . " parentId=" . ($entity->get('parentId') ?: '') . "\n";
echo "  dateStart=" . $entity->get('dateStart') . " dateEnd=" . $entity->get('dateEnd') . "\n";

$prospectId = trim((string) ($entity->get('prospectId') ?? ''));
if ($prospectId !== '') {
    try {
        $prospect = $em->getEntityById('Prospect', $prospectId);
        echo $prospect
            ? "OK Prospect {$prospectId} name=" . $prospect->get('name') . "\n"
            : "ERR Prospect {$prospectId} ASSENTE (orfano → 500 foreign fields)\n";
    } catch (Throwable $e) {
        echo "FATAL Prospect load: " . $e->getMessage() . "\n";
    }
}

$parentType = (string) ($entity->get('parentType') ?? '');
$parentId = trim((string) ($entity->get('parentId') ?? ''));
if ($parentType !== '' && $parentId !== '') {
    try {
        $parent = $em->getEntityById($parentType, $parentId);
        echo $parent
            ? "OK parent {$parentType}/{$parentId} name=" . $parent->get('name') . "\n"
            : "ERR parent {$parentType}/{$parentId} ASSENTE\n";
    } catch (Throwable $e) {
        echo "FATAL parent load: " . $e->getMessage() . "\n";
    }
}

$formulaPath = dirname(__DIR__) . '/custom/Espo/Custom/Resources/metadata/formula/Appuntamento.json';
$formula = is_file($formulaPath) ? (string) file_get_contents($formulaPath) : '';
if (str_contains($formula, 'beforeSaveApiScript') && preg_match('/"beforeSaveApiScript"\s*:\s*"([^"]+)/', $formula, $m)) {
    $api = $m[1];
    echo ($api === '' || str_starts_with($api, '//'))
        ? "OK beforeSaveApiScript disabilitato/vuoto\n"
        : "WARN beforeSaveApiScript ATTIVO (può causare 500 su save)\n";
}

echo "\n=== Simula save silent ===\n";
try {
    $entity->set('modifiedAt', date('Y-m-d H:i:s'));
    $em->saveEntity($entity, ['silent' => true, 'skipHooks' => true]);
    echo "OK save silent+skipHooks\n";
} catch (Throwable $e) {
    echo "ERR save silent: " . $e->getMessage() . "\n";
}

echo "\n=== Simula save con hooks (come UI) ===\n";
try {
    $fresh = $em->getEntityById('Appuntamento', $id);
    $fresh->set('description', (string) ($fresh->get('description') ?? ''));
    $em->saveEntity($fresh);
    echo "OK save con hooks\n";
} catch (Throwable $e) {
    echo "ERR save hooks: " . $e->getMessage() . "\n";
    echo "  " . $e->getFile() . ':' . $e->getLine() . "\n";
}

echo "\nFine diagnosi\n";
