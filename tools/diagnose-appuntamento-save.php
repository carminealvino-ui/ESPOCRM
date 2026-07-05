<?php
/**
 * Diagnostica salvataggio Appuntamento (replica scenario calendario Not Held).
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/diagnose-appuntamento-save.php
 */

declare(strict_types=1);

$crmRoot = dirname(__DIR__);

if (!is_file($crmRoot . '/bootstrap.php')) {
    fwrite(STDERR, "ERRORE: eseguire da root CRM (bootstrap.php mancante)\n");
    exit(1);
}

require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

echo "=== Diagnostica Appuntamento save ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

$checks = [
    'GlobalLogic BeforeSave' => is_file($crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php')
        && str_contains(
            (string) file_get_contents($crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php'),
            'implements BeforeSave'
        ),
    'RequiredDefaults hook' => is_file($crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/RequiredDefaults.php'),
    'Services/Appuntamento.php' => is_file($crmRoot . '/custom/Espo/Custom/Services/Appuntamento.php'),
    'ProvvigioneForecast Held guard' => is_file($crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/ProvvigioneForecast.php')
        && str_contains(
            (string) file_get_contents($crmRoot . '/custom/Espo/Custom/Hooks/Appuntamento/ProvvigioneForecast.php'),
            "status') !== 'Held'"
        ),
];

foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK] ' : '[MANCA] ') . $label . "\n";
}

echo "\n=== Test INSERT simulato (Not Held, senza prospect) ===\n";

$entity = $em->createEntity('Appuntamento');

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$dateStart = $now->format('Y-m-d H:i:s');
$dateEnd = $now->modify('+90 minutes')->format('Y-m-d H:i:s');

$brand = $em->getRDBRepository('ProductBrand')->where(['name' => 'ARIEL'])->findOne();
$partner = $em->getRDBRepository('FornitorePartner')->where(['name' => 'GDL'])->findOne();
$category = $em->getRDBRepository('ProductCategory')->where(['name' => 'CLIMATIZZATORI'])->findOne();
$user = $em->getRDBRepository('User')->where(['userName' => 'admin'])->findOne();

$entity->set([
    'name' => 'DIAG TEST ' . date('His'),
    'status' => 'Not Held',
    'sottostato' => 'Non Gestito',
    'esito' => 'Annullato dal Consulente',
    'dateStart' => $dateStart,
    'dateEnd' => $dateEnd,
    'fornitorePartnerId' => $partner?->getId(),
    'fornitorePartnerName' => $partner?->get('name'),
    'productBrandId' => $brand?->getId(),
    'productBrandName' => $brand?->get('name'),
    'productCategoryId' => $category?->getId(),
    'productCategoryName' => $category?->get('name'),
    'assignedUserId' => $user?->getId(),
]);

try {
    $em->saveEntity($entity);
    echo "[OK] Salvataggio riuscito — id: " . $entity->getId() . "\n";
    echo "     videoCallTelefonico=" . var_export($entity->get('videoCallTelefonico'), true) . "\n";
    echo "     zTL=" . var_export($entity->get('zTL'), true) . "\n";
    echo "     hookVersion=" . $entity->get('hookVersion') . "\n";

    $em->removeEntity($entity);
    echo "[OK] Record di test rimosso\n";
} catch (Throwable $e) {
    echo "[ERRORE] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nSe il test e' OK ma il calendario no: svuota cache browser (Ctrl+Shift+R).\n";
echo "Log errori: tail -80 data/logs/espo-" . date('Y-m-d') . ".log | grep ERROR\n";
