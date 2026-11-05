<?php
declare(strict_types=1);
namespace Espo\Custom\Services\CrmKpi;

use Espo\Core\AclManager;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\Custom\Tools\CrmKpi\DateRange;

/** Read-only appointment portfolio; each appointment contributes once. */
class AppointmentDashboard
{
    private const CANCELLED = ['Annullato dal Potenziale','Annullato dal Consulente','Annullato Azienda','Annullato Call Center','Appuntamento non in agenda'];
    public function __construct(private EntityManager $em, private AclManager $acl) {}

    public function summary(User $user, ?string $brand=null, string $period='totals', ?string $dateFrom=null, ?string $dateTo=null, ?string $areaId=null): object
    {
        if ($user->isNew() || !$user->getId() || $user->get('type')==='portal' || !$this->acl->check($user,'Appuntamento','read')) throw new Forbidden();
        foreach (['name','status','sottostato','esito','dataAppuntamento','dateStart','dateStartDate','productBrand','cAP'] as $field) {
            if (!$this->acl->checkField($user,'Appuntamento',$field,'read')) throw new Forbidden();
        }
        $period=DateRange::normalizePeriod($period);
        [$from,$to]=DateRange::resolve($period,$dateFrom,$dateTo);
        $rows=[]; $caps=[]; $areas=[]; $seen=[]; $products=[];
        foreach ($this->em->getRDBRepository('Appuntamento')->where($brand?['productBrandId'=>$brand]:[])->find() as $a) {
            if (isset($seen[$a->getId()])) continue;
            $seen[$a->getId()]=true;
            if (!$this->acl->check($user,$a,'read')) continue;
            $capId=$a->get('cAPId'); $area=null;
            if ($capId && $this->acl->check($user,'CAP','read') && $this->acl->checkField($user,'CAP','area','read')) {
                if (!array_key_exists($capId,$caps)) $caps[$capId]=$this->em->getEntityById('CAP',$capId);
                $cap=$caps[$capId];
                if ($cap && $this->acl->check($user,$cap,'read') && $cap->get('areaId') && $this->acl->check($user,'Area','read') && $this->acl->checkField($user,'Area','name','read')) {
                    $id=$cap->get('areaId');
                    if (!array_key_exists($id,$areas)) $areas[$id]=$this->em->getEntityById('Area',$id);
                    if ($areas[$id] && $this->acl->check($user,$areas[$id],'read')) $area=$areas[$id];
                }
            }
            if ($areaId && (!$area || $area->getId()!==$areaId)) continue;
            $date=$a->get('dataAppuntamento') ?: $a->get('dateStartDate');
            if (!$date && $a->get('dateStart')) $date=(new \DateTimeImmutable($a->get('dateStart'),new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Rome'))->format('Y-m-d');
            $product=null;$productId=$a->get('productCategoryId');
            if ($productId && $this->acl->checkField($user,'Appuntamento','productCategory','read') && $this->acl->check($user,'ProductCategory','read') && $this->acl->checkField($user,'ProductCategory','name','read')) {
                if (!array_key_exists($productId,$products)) $products[$productId]=$this->em->getEntityById('ProductCategory',$productId);
                if ($products[$productId] && $this->acl->check($user,$products[$productId],'read')) $product=$products[$productId];
            }
            $visibleCap=null;
            if ($capId && $this->acl->check($user,'CAP','read') && $this->acl->checkField($user,'CAP','name','read')) {
                if (!array_key_exists($capId,$caps)) $caps[$capId]=$this->em->getEntityById('CAP',$capId);
                if ($caps[$capId] && $this->acl->check($user,$caps[$capId],'read')) $visibleCap=$caps[$capId];
            }
            $rows[]=['id'=>$a->getId(),'name'=>$a->get('name')?:$a->getId(),'date'=>$date?substr($date,0,10):null,'status'=>$a->get('status'),'sottostato'=>$a->get('sottostato'),'esito'=>$a->get('esito'),'productId'=>$product?->getId(),'productName'=>$product?->get('name')?:'Prodotto non disponibile','capId'=>$visibleCap?->getId(),'capName'=>$visibleCap?->get('name'),'areaId'=>$area?->getId(),'areaName'=>$area?->get('name')?:'Area non disponibile'];
        }
        $filtered=array_values(array_filter($rows,fn($r)=>($from===null||($r['date']&&$r['date']>=$from))&&($to===null||($r['date']&&$r['date']<=$to))));
        $months=[]; foreach ($rows as $row) { $month=$row['date']?substr($row['date'],0,7):'Senza data'; $months[$month][]=$row; }
        ksort($months); foreach ($months as &$bucket) $bucket=self::aggregate($bucket); unset($bucket);
        $groups=[]; foreach ($filtered as $row) { $key=$row['areaId']?:'unavailable'; $groups[$key]['name']=$row['areaName']; $groups[$key]['rows'][]=$row; }
        foreach ($groups as &$group) { $group['metrics']=self::aggregate($group['rows']); unset($group['rows']); } unset($group);
        uasort($groups,fn($a,$b)=>$b['metrics']['totali']['count']<=>$a['metrics']['totali']['count']);
        $productGroups=[];
        foreach($filtered as $row){$key=$row['productId']?:'unavailable';$productGroups[$key]['name']=$row['productName'];$productGroups[$key]['rows'][]=$row;}
        foreach($productGroups as &$group){$group['metrics']=self::aggregate($group['rows']);unset($group['rows']);}unset($group);
        uasort($productGroups,fn($a,$b)=>$b['metrics']['totali']['count']<=>$a['metrics']['totali']['count']);
        $capGroups=[];
        foreach($filtered as $row){
            if (!$row['capId'] || !$row['capName']) continue;
            $key=$row['capId'];$capGroups[$key]['name']=$row['capName'];$capGroups[$key]['rows'][]=$row;
        }
        foreach($capGroups as &$group){$group['metrics']=self::aggregate($group['rows']);unset($group['rows']);}unset($group);
        uasort($capGroups,fn($a,$b)=>($b['metrics']['totali']['count']<=>$a['metrics']['totali']['count']) ?: strcmp((string)$a['name'],(string)$b['name']));
        $capGroups=array_slice($capGroups,0,15,true);
        $brandEntity=$brand?$this->em->getEntityById('ProductBrand',$brand):null;
        return (object)['metrics'=>self::aggregate($rows),'cardMetrics'=>self::aggregate($filtered),'months'=>(object)$months,'areas'=>(object)$groups,'products'=>(object)$productGroups,'caps'=>(object)$capGroups,'cardPeriod'=>['period'=>$period,'from'=>$from,'to'=>$to],'brandName'=>$brandEntity&&$this->acl->check($user,$brandEntity,'read')&&$this->acl->checkField($user,'ProductBrand','name','read')?$brandEntity->get('name'):null];
    }

    /** Same funnel exclusions as CRM KPI; Planned and Rifissato are separate. */
    public static function aggregate(array $rows): array
    {
        $metrics=array_fill_keys(['agenda','totali','lordi','netti','conclusi','annullati','ingestibili','pianificati','rifissati','svolti','senzaData','senzaArea'],['count'=>0,'records'=>[]]);
        foreach ($rows as $row) {
            $keys=['agenda'];
            if ($row['sottostato']==='Rifissato') $keys[]='rifissati';
            elseif ($row['status']==='Planned') $keys[]='pianificati';
            else {
                $keys[]='totali';
                if ($row['sottostato']==='Annullato'||in_array($row['esito'],self::CANCELLED,true)) $keys[]='annullati';
                else { $keys[]='lordi'; $keys[]=$row['status']==='Ingestibile'?'ingestibili':'netti'; }
            }
            if ($row['status']==='Held') $keys[]='svolti';
            if ($row['sottostato']==='Chiuso Positivamente') $keys[]='conclusi';
            if (!$row['date']) $keys[]='senzaData';
            if (!$row['areaId']) $keys[]='senzaArea';
            foreach ($keys as $key) { $metrics[$key]['count']++; $metrics[$key]['records'][]=$row; }
        }
        return $metrics;
    }
}
