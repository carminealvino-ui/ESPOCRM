#!/usr/bin/env bash
# Diagnostica errore Notification/action/notReadCount (EspoCRM 10).
set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
cd "$CRM_ROOT"

php -r '
require "bootstrap.php";
$app = new Espo\Core\Application();
$app->setupSystemUser();
$em = $app->getContainer()->get("entityManager");

echo "=== Colonne notification ===\n";
$cols = $em->getPDO()->query("SHOW COLUMNS FROM notification")->fetchAll(PDO::FETCH_COLUMN);
foreach (["action_id", "group_type", "number"] as $col) {
    echo in_array($col, $cols, true) ? "  OK $col\n" : "  MANCANTE $col\n";
}

echo "\n=== COUNT grezzo (PDO) ===\n";
$sth = $em->getPDO()->query("SELECT COUNT(id) AS c FROM notification WHERE deleted = 0 AND `read` = 0 LIMIT 1");
$row = $sth->fetch(PDO::FETCH_ASSOC);
$val = $row["c"] ?? null;
echo "  valore=" . var_export($val, true) . " tipo=" . gettype($val) . "\n";

echo "\n=== ORM repository count ===\n";
$ormCount = $em->getRDBRepository("Notification")->where(["read" => false])->count();
echo "  count=" . var_export($ormCount, true) . " tipo=" . gettype($ormCount) . "\n";

echo "\n=== RecordService getNotReadCount ===\n";
$user = $em->getEntityById("User", "1");
if (!$user) {
    $user = $em->getRDBRepository("User")->where(["isActive" => true])->findOne();
}
if (!$user) {
    echo "  Nessun utente attivo trovato\n";
    exit(1);
}
$factory = $app->getContainer()->get("injectableFactory");
$svc = $factory->create(Espo\Tools\Notification\RecordService::class);
try {
    $n = $svc->getNotReadCount($user);
    echo "  OK count=$n tipo=" . gettype($n) . "\n";
} catch (Throwable $e) {
    echo "  ERRORE: " . $e->getMessage() . "\n";
    echo "  " . $e->getFile() . ":" . $e->getLine() . "\n";
}
'
