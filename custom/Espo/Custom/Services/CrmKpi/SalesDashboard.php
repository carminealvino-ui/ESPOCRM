<?php
declare(strict_types=1);
namespace Espo\Custom\Services\CrmKpi;

use Espo\Core\AclManager;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\Custom\Tools\CrmKpi\KpiContext;
use Espo\Custom\Tools\CrmKpi\DateRange;

/** Read-only sales portfolio; each readable contract contributes once. */
class SalesDashboard
{
    public function __construct(private EntityManager $em, private AclManager $acl, private CrmKpiService $kpi) {}

    public function summary(User $user, ?string $brand, string $period='totals', ?string $dateFrom=null, ?string $dateTo=null): object
    {
        if ($user->isNew() || !$user->getId() || $user->get('type') === 'portal' || !$this->acl->check($user, 'Quote', 'read')) throw new Forbidden();
        foreach (['name','dateQuoted','productBrand','statoContratto','causaleAnnullamento','statoFinanziamento','numeroContratto','opportunita','importoContratto','amount','grandTotalAmount'] as $field) {
            if (!$this->acl->checkField($user, 'Quote', $field, 'read')) throw new Forbidden();
        }
        // Classification also reads the linked opportunity; require its source fields.
        if (!$this->acl->check($user, 'Opportunity', 'read')) throw new Forbidden();
        foreach (['statoContratto','statoFinanziamento'] as $field) if (!$this->acl->checkField($user, 'Opportunity', $field, 'read')) throw new Forbidden();
        $empty=['cents'=>0,'count'=>0,'records'=>[]];
        $metrics=array_fill_keys(['totali','lordi','valide','netti','recessi','finanziamentiRifiutati','sospesi','senzaImporto','senzaData'], $empty);
        $months=[]; $states=[];
        foreach ($this->kpi->commissionDashboardQuotes($user, new KpiContext(null,null,$brand)) as $item) {
            $q=$item['entity'];
            if ($q->get('opportunitaId')) {
                $op=$this->em->getEntityById('Opportunity',$q->get('opportunitaId'));
                if ($op && !$this->acl->check($user,$op,'read')) continue;
            }
            $amount=null;
            foreach (['importoContratto','amount','grandTotalAmount'] as $field) {
                $v=$q->get($field);
                if ($v!==null && $v!=='' && (float)$v!==0.0) { $amount=(float)$v; break; }
            }
            $cents=(int)round(($amount??0)*100);
            $row=['id'=>$q->getId(),'entityType'=>'Quote','name'=>$q->get('name')?:$q->getId(),'date'=>$q->get('dateQuoted'),'status'=>$q->get('statoContratto'),'amount'=>$cents/100];
            $row['causaleAnnullamento']=$q->get('causaleAnnullamento');
            // A rejected loan is evidence only when no explicit cancellation cause is recorded.
            if ($row['status']==='Annullato' && !$row['causaleAnnullamento'] && $q->get('statoFinanziamento')==='Respinto') {
                $row['causaleAnnullamento']='Finanziamento KO';
            }
            $bucket=$item['bucket'];
            $keys=['totali',$bucket];
            if ($bucket!=='recessi') $keys[]='lordi';
            if (in_array($bucket,['netti','sospesi'],true)) $keys[]='valide';
            if ($amount===null || $cents===0) $keys[]='senzaImporto';
            if (!$row['date']) $keys[]='senzaData';
            foreach (array_unique($keys) as $key) self::add($metrics[$key],$row,$cents);
            $month=$row['date']?substr($row['date'],0,7):'Senza data';
            $months[$month]??=array_fill_keys(['totali','netti','recessi','finanziamentiRifiutati','sospesi'],$empty);
            self::add($months[$month]['totali'],$row,$cents);
            self::add($months[$month][$bucket],$row,$cents);
            $state=$row['status']?:'Senza stato'; $states[$state]??=$empty;
            self::add($states[$state],$row,$cents);
        }
        krsort($months); ksort($states);
        $period=DateRange::normalizePeriod($period);
        [$from,$to]=DateRange::resolve($period,$dateFrom,$dateTo);
        $cardMetrics=array_fill_keys(array_keys($metrics),$empty);
        foreach($metrics as $key=>$metric) foreach($metric['records'] as $row) {
            if(($from===null || ($row['date'] && $row['date'] >= $from)) && ($to===null || ($row['date'] && $row['date'] <= $to))) {
                self::add($cardMetrics[$key],$row,(int)round($row['amount']*100));
            }
        }
        $brandEntity=$brand?$this->em->getEntityById('ProductBrand',$brand):null;
        return (object)['metrics'=>$metrics,'cardMetrics'=>$cardMetrics,'cardPeriod'=>['period'=>$period,'from'=>$from,'to'=>$to],'months'=>$months,'states'=>$states,'brandName'=>$brandEntity && $this->acl->check($user,$brandEntity,'read')?$brandEntity->get('name'):null,'today'=>(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Rome')))->format('Y-m-d')];
    }

    private static function add(array &$bucket,array $row,int $cents): void
    {
        $bucket['cents']+=$cents; $bucket['count']++; $bucket['records'][]=$row;
    }
}
