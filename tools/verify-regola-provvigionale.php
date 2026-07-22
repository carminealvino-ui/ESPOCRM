<?php

/**
 * Verifica che RegolaProvvigionale sia raggiungibile (no 404).
 *
 *   php tools/verify-regola-provvigionale.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

$metadata = $container->get('metadata');
$em = $container->get('entityManager');

$ok = true;

$scope = $metadata->get(['scopes', 'RegolaProvvigionale']);
echo $scope ? "OK scopes/RegolaProvvigionale\n" : "ERR scopes mancante\n";
$ok = $ok && (bool) $scope;

if ($scope) {
    echo '  entity=' . json_encode($scope['entity'] ?? null)
        . ' disabled=' . json_encode($scope['disabled'] ?? null)
        . ' tab=' . json_encode($scope['tab'] ?? null)
        . PHP_EOL;
}

$client = $metadata->get(['clientDefs', 'RegolaProvvigionale']);
echo $client ? "OK clientDefs controller=" . ($client['controller'] ?? '?') . "\n" : "ERR clientDefs mancante\n";
$ok = $ok && (bool) $client;

$controllerClass = 'Espo\\Custom\\Controllers\\RegolaProvvigionale';
echo class_exists($controllerClass) ? "OK Controller class\n" : "ERR Controller class assente\n";
$ok = $ok && class_exists($controllerClass);

if (class_exists($controllerClass)) {
    $parent = get_parent_class($controllerClass) ?: '';
    echo "  parent={$parent}\n";
    if ($parent !== 'Espo\\Core\\Controllers\\Record') {
        echo "WARN preferire extends Espo\\Core\\Controllers\\Record (Espo 10)\n";
    }
}

$controllerFile = dirname(__DIR__) . '/custom/Espo/Custom/Controllers/RegolaProvvigionale.php';
echo is_file($controllerFile) ? "OK Controller file\n" : "ERR Controller file assente\n";
$ok = $ok && is_file($controllerFile);

try {
    $count = $em->getRDBRepository('RegolaProvvigionale')->count();
    echo "OK repository count={$count}\n";
} catch (Throwable $e) {
    echo 'ERR repository: ' . $e->getMessage() . "\n";
    $ok = false;
}

$ariel = $em->getEntityById('RegolaProvvigionale', 'arielBase105');
echo $ariel
    ? 'OK arielBase105 name=' . $ariel->get('name') . "\n"
    : "WARN arielBase105 assente — eseguire: php tools/seed-regole-provvigioni.php --only=ariel\n";

exit($ok ? 0 : 1);
