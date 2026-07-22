<?php

/**
 * Diagnosi 404 su #RegolaProvvigionale.
 *
 *   php tools/diagnose-regola-provvigionale-404.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;

$app = new Application();
$app->setupSystemUser();
$c = $app->getContainer();
$metadata = $c->get('metadata');
$em = $c->get('entityManager');

$ok = true;

echo "=== Diagnosi RegolaProvvigionale 404 ===\n\n";

$fileChecks = [
    'Controllers/RegolaProvvigionale.php',
    'Entities/RegolaProvvigionale.php',
    'Repositories/RegolaProvvigionale.php',
    'Resources/metadata/scopes/RegolaProvvigionale.json',
    'Resources/metadata/entityDefs/RegolaProvvigionale.json',
    'Resources/metadata/clientDefs/RegolaProvvigionale.json',
];

foreach ($fileChecks as $rel) {
    $path = dirname(__DIR__) . '/custom/Espo/Custom/' . $rel;
    $exists = is_file($path);
    echo ($exists ? 'OK  ' : 'ERR ') . "file custom/Espo/Custom/{$rel}\n";
    $ok = $ok && $exists;

    if ($exists && str_ends_with($rel, 'Controllers/RegolaProvvigionale.php')) {
        $src = (string) file_get_contents($path);
        if (str_contains($src, 'Espo\\Core\\Controllers\\Record')) {
            echo "OK  Controller extends Record (Espo 10)\n";
        } elseif (str_contains($src, 'Templates\\Controllers\\Base')) {
            echo "WARN Controller extends Templates\\Base (preferire Record)\n";
        } else {
            echo "WARN Controller parent class non riconosciuto\n";
        }
    }
}

$scope = $metadata->get(['scopes', 'RegolaProvvigionale']);
if ($scope) {
    echo 'OK  metadata scopes entity=' . json_encode($scope['entity'] ?? null)
        . ' disabled=' . json_encode($scope['disabled'] ?? null)
        . ' tab=' . json_encode($scope['tab'] ?? null) . "\n";
    if (($scope['disabled'] ?? false) === true || ($scope['entity'] ?? null) !== true) {
        echo "ERR scopes non utilizzabile (disabled o entity!=true)\n";
        $ok = false;
    }
} else {
    echo "ERR metadata scopes/RegolaProvvigionale assente — serve rebuild/clear_cache\n";
    $ok = false;
}

$client = $metadata->get(['clientDefs', 'RegolaProvvigionale']);
echo $client
    ? 'OK  clientDefs controller=' . ($client['controller'] ?? '?') . "\n"
    : "ERR clientDefs assente\n";
$ok = $ok && (bool) $client;

$class = 'Espo\\Custom\\Controllers\\RegolaProvvigionale';
if (class_exists($class)) {
    echo "OK  class_exists Controller\n";
    $parent = get_parent_class($class) ?: '?';
    echo "    parent={$parent}\n";
} else {
    echo "ERR class_exists Controller fallito\n";
    $ok = false;
}

try {
    $count = $em->getRDBRepository('RegolaProvvigionale')->count();
    echo "OK  repository count={$count}\n";
} catch (Throwable $e) {
    echo 'ERR repository: ' . $e->getMessage() . "\n";
    $ok = false;
}

$ariel = $em->getEntityById('RegolaProvvigionale', 'arielBase105');
echo $ariel
    ? 'OK  arielBase105 = ' . $ariel->get('name') . "\n"
    : "WARN arielBase105 assente (seed mancante)\n";

echo "\n--- ACL ruoli ---\n";
$roles = $em->getRDBRepository('Role')->find();
$rolesOk = 0;
$rolesNo = 0;

foreach ($roles as $role) {
    $data = $role->get('data');
    if (is_string($data)) {
        $data = json_decode($data);
    }
    if (is_array($data)) {
        $data = (object) $data;
    }

    $entry = is_object($data) ? ($data->RegolaProvvigionale ?? null) : null;
    $read = null;
    if (is_object($entry)) {
        $read = $entry->read ?? null;
    } elseif (is_array($entry)) {
        $read = $entry['read'] ?? null;
    }

    $allowed = $read !== null && $read !== 'no' && $read !== false;
    if ($allowed) {
        $rolesOk++;
        echo "OK  Role {$role->get('name')}: read={$read}\n";
    } else {
        $rolesNo++;
        echo "ERR Role {$role->get('name')}: RegolaProvvigionale assente/no → 404 per utenti non-admin\n";
    }
}

if ($rolesNo > 0) {
    echo "\nFIX: php tools/enable-regola-provvigionale-acl.php\n";
    $ok = false;
} else {
    echo "OK  tutti i ruoli hanno accesso\n";
}

echo "\n=== " . ($ok ? 'PASS' : 'FAIL') . " ===\n";
if (!$ok) {
    echo "Poi: php clear_cache.php && php rebuild.php && logout/login browser\n";
}

exit($ok ? 0 : 1);
