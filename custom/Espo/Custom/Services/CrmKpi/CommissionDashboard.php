<?php
declare(strict_types=1);
namespace Espo\Custom\Services\CrmKpi;

use Espo\Core\AclManager;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Custom\Services\ArielInvitiService;
use Espo\Custom\Services\ArielInvoicePolicy;
use Espo\Custom\Services\InvitoWorkflow;
use Espo\Custom\Tools\CrmKpi\DateRange;
use Espo\Custom\Tools\CrmKpi\KpiContext;

/** Read-only dashboard. Amounts are accumulated in cents, never by invoice join. */
class CommissionDashboard
{
    public function __construct(private EntityManager $em, private AclManager $acl,
        private CrmKpiService $kpi, private ArielInvitiService $ariel, private InvitoWorkflow $workflow) {}

    private function cents(mixed $amount): int { return (int) round(100 * (float) $amount); }
    public static function position(int $expected, int $paid, int $reserved, bool $closed): array
    {
        $residual=$closed ? 0 : $expected-$paid;
        return ['residual'=>$residual,'available'=>$closed ? 0 : $residual-$reserved];
    }
    public static function category(?string $type): string
    {
        return match ($type) {
            'Provvigione Base', 'Bonus Ordine Completo', 'Provvigione ordine completo', 'Patto di non concorrenza (PNC)' => 'provvigioni',
            'Plus Provvigionale' => 'plus',
            'Minus Provvigionale' => 'minus',
            'Bonus (Sabato-Domenica)', 'Gara (Soglia >2.5k)', 'Gara (Soglia >3.5)',
            'Gara (Soglia >5k)', 'Referenza Personale', 'Bonus Taxi', 'Bonus Estate 2026' => 'bonus',
            default => 'altre',
        };
    }
    private function addMetric(array &$b, array &$groups, string $category, string $key, int $amount, array $row): void
    {
        $this->add($b[$key],$amount,$row);
        $this->add($groups[$category][$key],$amount,$row);
    }
    private function bucket(): array { return ['cents'=>0, 'count'=>0, 'records'=>[]]; }
    private function add(array &$b, int $amount, array $row): void
    {
        $b['cents'] += $amount; $b['count']++;
        if (!empty($row['contractId'])) {
            $id=$row['contractId'];
            if (!isset($b['contracts'][$id])) $b['contracts'][$id]=['id'=>$id,'entityType'=>'Quote','name'=>$row['contractName'],'date'=>$row['date'] ?? null,'status'=>$row['status'] ?? '', 'amount'=>0,'components'=>[]];
            $b['contracts'][$id]['amount']=round($b['contracts'][$id]['amount']+$amount/100,2);
            $category=$row['category'] ?? 'provvigioni';
            $b['contracts'][$id]['components'][$category]=($b['contracts'][$id]['components'][$category] ?? 0)+$amount;
            $b['contractCount']=count($b['contracts']);
        }
        if (count($b['records']) < 100) $b['records'][] = $row + ['amount'=>$amount/100];
    }
    private function readable(User $u, Entity $e): bool { return $this->acl->check($u, $e, 'read'); }
    private function row(Entity $e, string $type, ?string $date=null): array
    {
        $contract=$type==='Provvigione' && $e->get('contrattoId') ? $this->em->getEntityById('Quote',$e->get('contrattoId')) : null;
        return ['contractId'=>$contract?->getId(),'contractName'=>$contract?->get('name'),'id'=>$e->getId(), 'entityType'=>$type, 'name'=>$e->get('name') ?: $e->getId(), 'date'=>$date];
    }
    private function requireFields(User $u): void
    {
        if ($u->isNew() || !$u->getId() || $u->get('type') === 'portal') throw new Forbidden();
        // All contributing fields are required: restricted fields must not leak via aggregates.
        $fields = [
            'Quote'=>['name','dateQuoted','productBrand','statoContratto','statoFinanziamento','totaleProvvigioni',
                'status','finanziamento','opportunita','numeroContratto','bloccoOrdineAriel','dataInstallazione','dataAttivazione','importoContratto','grandTotalAmount','importoCaparra'],
            'Provvigione'=>['name','contratto','importo','importoConsolidato','statoProvvigione','regimeProvvigione','tipo',
                'dataPagamento','dataLiquidazioneEffettiva','dataLiquidazionePrevista','dataInstallazione','dataAttivazione',
                'consolidata','fornitorePartner','productBrand','regolaProvvigionale','importoPagatoInviti','residuoProvvigionale','differenzaChiusura','differenzaDaVerificare'],
            'InvitoAFatturare'=>['name','stato','meseCompetenza','fornitorePartner','arielIncludiSaldoMensile'],
            'InvitoAFatturareItem'=>['name','provvigione','invito','importoConsolidato','movimento','tipoLiquidazione','contratto','tipo','voceAutomatica','rigaOrigine','importoPrevistoRiga','createdAt','incongruenzaStato','incongruenzaNote','incongruenzaStorico','incongruenzaInvitoRettifica','incongruenzaVersion'],
        ];
        foreach ($fields as $scope=>$names) {
            if (!$this->acl->check($u,$scope,'read')) throw new Forbidden();
            foreach ($names as $name) if (!$this->acl->checkField($u,$scope,$name,'read')) throw new Forbidden();
        }
    }

    public function summary(User $user, string $period, ?string $brand, ?string $dateFrom=null, ?string $dateTo=null): object
    {
        $this->requireFields($user);
        $excluded=$this->excludedPositions();
        [$from,$to] = [null,null]; // Total portfolio snapshot, independent of contract month.
        $today = ArielInvoicePolicy::today();
        $keys = ['maturateContratti','totali','recessi','lordi','finanziamentiRifiutati','sospesi','netti',
            'maturate','daMaturare','pagate','residuo','negliInviti','disponibili','differenze','daVerificare','arretrati','senzaData','inesigibili','mensili'];
        $b = array_fill_keys($keys, $this->bucket());
        $groups=array_fill_keys(['provvigioni','plus','minus','bonus','altre','nonAttribuite'],$b);
        $reportKeys=['aperteValide','aperteAttive','pagamentoEStima','inPagamento','stimaFutura'];
        $report=array_fill_keys($reportKeys,$this->bucket());
        $reportGroups=array_fill_keys(array_keys($groups),$report);
        $activeIds=[];$paymentIds=[];
        $quoteMap=[]; $selected=[];
        foreach ($this->kpi->commissionDashboardQuotes($user,new KpiContext(null,null,$brand)) as $item) {
            $q=$item['entity']; $quoteMap[$q->getId()]=$item;
            $date=$q->get('dateQuoted');
            if (($from && (!$date || $date<$from)) || ($to && (!$date || $date>$to))) continue;
            $selected[$q->getId()]=true;
            $rowSum=0; $rowCount=0;
            // Verify commission row access before allowing the inherited contract total.
            foreach ($this->em->getRDBRepository('Provvigione')->where(['contrattoId'=>$q->getId()])->find() as $p) {
                if (isset($excluded[$p->getId()])) continue;
                $rowCount++;
                if (!$this->readable($user,$p)) throw new Forbidden('Accesso parziale alle provvigioni del contratto.');
                if (($p->get('importoConsolidatoCurrency') ?: $p->get('importoCurrency') ?: 'EUR') !== 'EUR') throw new \Espo\Core\Exceptions\BadRequest('La dashboard richiede importi in EUR.');
                $value=$this->cents($p->get('importoConsolidato') ?? $p->get('importo')); $rowSum+=$value;
                $category=self::category($p->get('tipo')); $partRow=$this->row($p,'Provvigione',$date);
                $partRow['status']=$p->get('tipo');
                $partRow['category']=$category;
                $openAmount=$value-$this->cents($p->get('importoPagatoInviti'));
                if ($q->get('status')!=='Invalido') {
                    $this->addMetric($report,$reportGroups,$category,'aperteValide',$openAmount,$partRow);
                    if (!in_array($q->get('statoContratto'),['Sospeso','Sospesa'],true) && !in_array($q->get('status'),['Sospeso','Sospesa'],true)) {
                        $this->addMetric($report,$reportGroups,$category,'aperteAttive',$openAmount,$partRow);
                        $activeIds[$p->getId()]=true;
                    }
                }
                $this->add($groups[$category]['totali'],$value,$partRow);
                if ($item['bucket']!=='recessi') $this->add($groups[$category]['lordi'],$value,$partRow);
                $this->add($groups[$category][$item['bucket']],$value,$partRow);

            }
            if (!$rowCount) continue;
            $amount=$rowSum; $row=$this->row($q,'Quote',$date);
            if ($item['bucket']==='netti' && self::commerciallyMature($this->ariel->quoteData($q))) {
                $row['status']='Maturata · '.($q->get('statoFinanziamento') ?: $q->get('statoContratto'));
                $this->add($b['maturateContratti'],$amount,$row);
            }
            $this->add($b['totali'],$amount,$row);
            if ($item['bucket']!=='recessi') $this->add($b['lordi'],$amount,$row);
            $this->add($b[$item['bucket']],$amount,$row);
            $unattributed=$amount-$rowSum;
            if ($unattributed) {
                $this->add($groups['nonAttribuite']['totali'],$unattributed,$row);
                if ($item['bucket']!=='recessi') $this->add($groups['nonAttribuite']['lordi'],$unattributed,$row);
                $this->add($groups['nonAttribuite'][$item['bucket']],$unattributed,$row);
            }

        }

        $invoices=[];
        foreach ($this->em->getRDBRepository('InvitoAFatturare')->where(['stato!='=>'Annullato'])->find() as $i) $invoices[$i->getId()]=$i;
        $lines=[];
        foreach ($this->em->getRDBRepository('InvitoAFatturareItem')->find() as $line) {
            if (!$line->get('provvigioneId') || !isset($invoices[$line->get('invitoId')])) continue;
            $lines[$line->get('provvigioneId')][]=$line;
        }
        $forecast=[]; $monthlySuppliers=[]; $missing=0; $manualMonthly=[];
        foreach ($this->em->getRDBRepository('Provvigione')->find() as $p) {
            if (isset($excluded[$p->getId()])) continue;
            $qid=$p->get('contrattoId');
            if (!isset($quoteMap[$qid]) || !$this->readable($user,$p)) continue;
            $q=$quoteMap[$qid]['entity']; $isSelected=isset($selected[$qid]);
            if ($quoteMap[$qid]['bucket']!=='netti') continue;
            $amount=$this->cents($p->get('importoConsolidato') ?? $p->get('importo'));
            $state=$p->get('statoProvvigione'); $isAriel=$this->ariel->isAriel($p);
            $monthly=$this->ariel->monthlyOnly($p);
            $due=$this->workflow->dueDate($p);
            // Planned installation is enough to estimate a payment date; maturity is a separate condition.
            if ($isAriel) {
                $plannedInstallation=ArielInvoicePolicy::date($q->get('dataInstallazione')) ?: ArielInvoicePolicy::date($q->get('dataAttivazione'));
                if ($plannedInstallation) $due=ArielInvoicePolicy::calendar(substr($plannedInstallation,0,7).'-01')['arielDataPagamentoPrevista'];
                else $due=ArielInvoicePolicy::date($p->get('dataLiquidazionePrevista')) ?: $due;
            }
            $row=$this->row($p,'Provvigione',$due);
            $row['status']=$state; $row['category']=self::category($p->get('tipo')); $category=$row['category'];
            $supplier=(string)$p->get('fornitorePartnerId');
            if ($monthly) {
                if ($isSelected) $this->addMetric($b,$groups,$category,'mensili',$amount,$row);
                if ($supplier) $monthlySuppliers[$supplier]=true;
                foreach ($lines[$p->getId()] ?? [] as $line) {
                    $i=$invoices[$line->get('invitoId')];
                    if (substr((string)$q->get('dateQuoted'),0,7)===substr($today,0,7)) $manualMonthly[$supplier]=true;
                }
                continue;
            }
            if ($state==='Inesigibile') {
                if ($isSelected) $this->addMetric($b,$groups,$category,'inesigibili',$amount,$row);
                continue;
            }
            $paid=$this->cents($p->get('importoPagatoInviti'));
            // Historical direct payments may not have invoice ledger rows.
            if (!isset($lines[$p->getId()]) && ($state==='Pagato' || $p->get('dataPagamento') || $p->get('dataLiquidazioneEffettiva'))) $paid=$amount;
            $closed=in_array($state,['Pagato','Chiuso con differenza'],true);
            $difference=$closed ? $this->cents($p->get('differenzaChiusura')) : 0;
            $residual=$closed ? 0 : $amount-$paid;
            $reserved=0; $hasOpen=false;
            foreach ($lines[$p->getId()] ?? [] as $line) {
                $i=$invoices[$line->get('invitoId')];
                if (!$this->readable($user,$line) || !$this->readable($user,$i)) throw new Forbidden('Accesso parziale agli inviti collegati.');
                if ($i->get('stato')!=='Pagato') { $reserved+=$this->cents($line->get('importoConsolidato')); $hasOpen=true; }
            }
            $mature=$isAriel ? $this->ariel->maturity($q)!==null
                : in_array($state,['In pagamento','Pagato','Acconto provvigionale','Chiuso con differenza'],true);
            $mature=$mature || $paid!==0 || $closed || $hasOpen;
            $position=self::position($amount,$paid,$reserved,$closed);
            $residual=$position['residual']; $available=$position['available'];
            if ($isSelected) {
                $this->addMetric($b,$groups,$category,$mature?'maturate':'daMaturare',$amount,$row);
                if ($paid) $this->addMetric($b,$groups,$category,'pagate',$paid,$row);
                if ($residual) $this->addMetric($b,$groups,$category,'residuo',$residual,$row);
                if ($reserved) $this->addMetric($b,$groups,$category,'negliInviti',$reserved,$row);
                if ($difference) $this->addMetric($b,$groups,$category,'differenze',$difference,$row);
                if ($p->get('differenzaDaVerificare')) $this->addMetric($b,$groups,$category,'daVerificare',$this->cents($p->get('differenzaDaVerificare')),$row);
                if ($mature && $available && !$hasOpen) $this->addMetric($b,$groups,$category,'disponibili',$available,$row);
                if ($residual && !$due) $this->addMetric($b,$groups,$category,'senzaData',$residual,$row);
            }
            if ($isSelected && $mature && isset($activeIds[$p->getId()]) && $available) {
                $paymentIds[$p->getId()]=true;
                $this->addMetric($report,$reportGroups,$category,'inPagamento',$available,$row);
                $this->addMetric($report,$reportGroups,$category,'pagamentoEStima',$available,$row);
            }
            if (!$available || $hasOpen || !$supplier) continue;
            if (!$isAriel && !$mature) continue;
            if (!$isAriel && !$due) { $missing++; continue; }
            if ($isAriel) {
                // Payment forecast follows installation, not deposit/contract maturity.
                $installation=ArielInvoicePolicy::date($q->get('dataInstallazione')) ?: ArielInvoicePolicy::date($q->get('dataAttivazione'));
                if (!$installation) continue;
                $month=substr($installation,0,7).'-01';
                $calendar=ArielInvoicePolicy::calendar($month);
                $date=$calendar['arielDataCaricamento'];
                $row['date']=$installation;
                $row['status']='Installazione '.$installation.' · pagamento previsto '.$calendar['arielDataPagamentoPrevista'];
                $label='Competenza '.substr($month,0,7).' · caricamento '.$date;
            } else {
                $date=$due;
                $label='Liquidazione prevista '.$due;
            }
            $this->forecastAdd($forecast,$supplier,$date,$label,$available,$row);
        }

        // Monthly Ariel plus/minus and tier awards are estimated once per supplier/month.
        // This portfolio-wide calculation is only exposed to administrators.
        if ($user->isAdmin()) foreach (array_keys($monthlySuppliers) as $supplier) {
            $month=substr($today,0,7).'-01';
            $owned=false;
            foreach ($invoices as $i) if ($i->get('fornitorePartnerId')===$supplier && $i->get('meseCompetenza')===$month && $i->get('arielIncludiSaldoMensile')) $owned=true;
            if ($owned || isset($manualMonthly[$supplier])) continue;
            $preview=$this->ariel->previewSettlement($supplier,$month);
            $amount=$this->ariel->supplementCents($preview);
            $date=ArielInvoicePolicy::calendar($month)['arielDataCaricamento'];
            foreach (['arielProvvigionePlus'=>['plus','Quota plus dopo compensazione mensile'],
                'arielAddebitoMinus'=>['minus','Minus residuo dopo compensazione mensile'],
                'arielPremioProduzione'=>['bonus','Premio produzione mensile']] as $field=>[$category,$label]) {
                $part=$this->cents($preview->get($field));
                if ($part) $this->forecastAdd($forecast,$supplier,$date,'Competenza '.substr($month,0,7).' · caricamento '.$date,$part,
                    ['id'=>null,'entityType'=>null,'category'=>$category,'name'=>$label.' (stima)','date'=>$date,
                    'status'=>$preview->get('arielAnomalie') ?: 'Da confermare a chiusura mese']);
            }
        }
        // Explain the monthly aggregate without counting its source amounts twice.
        if ($user->isAdmin()) foreach ($forecast as &$entry) {
            $hasSettlement=false;
            foreach ($entry['records'] as $row) if (!$row['id']) $hasSettlement=true;
            if (!$hasSettlement) continue;
            $month=(new \DateTimeImmutable($entry['date']))->modify('first day of previous month')->format('Y-m-01');
            $preview=$this->ariel->previewSettlement($entry['supplierId'],$month);
            $events=json_decode($preview->get('arielSnapshot') ?: '[]',true,512,JSON_THROW_ON_ERROR);
            $entry['settlement']=['month'=>substr($month,0,7),'records'=>[]];
            foreach ($events as $event) {
                $q=$this->em->getEntityById('Quote',$event['quoteId']);
                if (!$q || !$this->readable($user,$q)) throw new Forbidden();
                $entry['settlement']['records'][]=['id'=>$q->getId(),'entityType'=>'Quote','name'=>$event['name'],
                    'date'=>$event['date'],'amount'=>$event['grossCents']/100,
                    'category'=>$event['grossCents']<0?'minus':'plus','status'=>$event['kind']==='rettifica'?'Rettifica · lordo commerciale':'Lordo commerciale'];
            }
            foreach (['arielPlusLordo','arielMinusLordo','arielCompensato','arielSaldoMinusPlus','arielProvvigionePlus','arielAddebitoMinus'] as $field)
                $entry['settlement'][$field]=$this->cents($preview->get($field));
        }
        unset($entry);
        foreach ($forecast as &$f) {
            $supplier=$this->em->getEntityById('FornitorePartner',$f['supplierId']);
            $f['supplierName']=$supplier && $this->readable($user,$supplier) ? $supplier->get('name') : 'Fornitore';
            $f['overdue']=$f['date']<$today;
        }
        unset($f);
        // Overdue amounts not reserved in open invoices belong to the review box.
        foreach ($forecast as $entry) {
            if (!$entry['overdue']) continue;
            foreach ($entry['allRecords'] ?? $entry['records'] as $row) {
                $this->addMetric($b,$groups,$row['category'] ?? 'altre','arretrati',$this->cents($row['amount']),$row);
            }
        }
        $forecast=array_values(array_filter($forecast,fn($entry)=>!$entry['overdue']));
        usort($forecast,fn($a,$b)=>strcmp($b['date'],$a['date']) ?: strcmp($a['supplierName'],$b['supplierName']));
        // Cards 3-5 describe dated invoices, not the general maturity of each commission.
        foreach (['pagamentoEStima','inPagamento','stimaFutura'] as $key) {
            $report[$key]=$this->bucket();
            foreach ($reportGroups as &$group) $group[$key]=$this->bucket();
            unset($group);
        }
        $nextDates=[];
        foreach ($forecast as $entry) {
            if (!$entry['date'] || $entry['date']<$today) continue;
            $supplier=$entry['supplierId'];
            if (!isset($nextDates[$supplier]) || $entry['date']<$nextDates[$supplier]) $nextDates[$supplier]=$entry['date'];
        }
        foreach ($forecast as $entry) {
            if (!$entry['date'] || $entry['date']<$today) continue;
            $invoiceKey=$entry['date']===$nextDates[$entry['supplierId']] ? 'inPagamento' : 'stimaFutura';
            foreach ($entry['allRecords'] ?? $entry['records'] as $row) {
                if (!empty($row['id']) && !isset($activeIds[$row['id']])) continue;
                $amount=$this->cents($row['amount']);$category=$row['category'] ?? 'altre';
                foreach ([$invoiceKey,'pagamentoEStima'] as $key) {
                    $this->addMetric($report,$reportGroups,$category,$key,$amount,$row);
                    // Monthly compensation stays a single amount; contributors carry no invented allocation.
                    if (empty($row['id'])) foreach ($entry['settlement']['records'] ?? [] as $source) {
                        $contributor=['contractId'=>$source['id'],'contractName'=>$source['name'],'date'=>$source['date'],
                            'category'=>$category,'status'=>'Concorre al saldo mensile riportato separatamente'];
                        $this->add($report[$key],0,$contributor);
                        $this->add($reportGroups[$category][$key],0,$contributor);
                    }
                }
            }
        }
        // Box 1 = next/future invoices plus suspended; Box 2 = suspended only.
        $report['aperteValide']=$report['pagamentoEStima'];
        $report['aperteAttive']=$this->bucket();
        foreach ($reportGroups as &$group) { $group['aperteValide']=$group['pagamentoEStima']; $group['aperteAttive']=$this->bucket(); }
        unset($group);
        foreach ($quoteMap as $qid=>$item) {
            $q=$item['entity'];
            if ($q->get('status')==='Invalido' || (!in_array($q->get('statoContratto'),['Sospeso','Sospesa'],true) && !in_array($q->get('status'),['Sospeso','Sospesa'],true))) continue;
            foreach ($this->em->getRDBRepository('Provvigione')->where(['contrattoId'=>$qid])->find() as $p) {
                if (isset($excluded[$p->getId()])) continue;
                if (!$this->readable($user,$p)) throw new Forbidden();
                $category=self::category($p->get('tipo'));
                $amount=$this->cents($p->get('importoConsolidato') ?? $p->get('importo'))-$this->cents($p->get('importoPagatoInviti'));
                $row=$this->row($p,'Provvigione',$q->get('dateQuoted'));
                $row['category']=$category;$row['status']='Contratto sospeso · '.$p->get('tipo');
                $this->addMetric($report,$reportGroups,$category,'aperteAttive',$amount,$row);
                $this->addMetric($report,$reportGroups,$category,'aperteValide',$amount,$row);
            }
        }
        // Missing dates must cover suspended positions too, independently of payment maturity.
        $b['senzaData']=$this->bucket();
        foreach ($groups as &$group) $group['senzaData']=$this->bucket();
        unset($group);
        foreach ($this->em->getRDBRepository('Provvigione')->find() as $p) {
            if (isset($excluded[$p->getId()]) || !$this->readable($user,$p) || $this->ariel->monthlyOnly($p)) continue;
            $item=$quoteMap[$p->get('contrattoId')] ?? null;
            if (!$item || in_array($item['bucket'],['recessi','finanziamentiRifiutati'],true)) continue;
            $q=$item['entity'];
            if ($q->get('status')==='Invalido' || in_array($q->get('statoContratto'),['Recesso','Annullato'],true)) continue;
            $installation=ArielInvoicePolicy::date($q->get('dataInstallazione')) ?: ArielInvoicePolicy::date($q->get('dataAttivazione'));
            $due=$this->ariel->isAriel($p)
                ? ($installation ? ArielInvoicePolicy::calendar(substr($installation,0,7).'-01')['arielDataPagamentoPrevista'] : ArielInvoicePolicy::date($p->get('dataLiquidazionePrevista')))
                : $this->workflow->dueDate($p);
            if ($due) continue;
            $amount=$this->cents($p->get('importoConsolidato') ?? $p->get('importo'))-$this->cents($p->get('importoPagatoInviti'));
            if (!$amount) continue;
            $row=$this->row($p,'Provvigione',$q->get('dateQuoted'));
            $row['category']=self::category($p->get('tipo'));
            $row['status']=($q->get('statoContratto') ?: $q->get('status')).' · installazione/pagamento da pianificare';
            $this->addMetric($b,$groups,$row['category'],'senzaData',$amount,$row);
        }
        $b['daVerificare']=$this->invitationDifferences($user,$brand,$invoices);
        $brandEntity=$brand ? $this->em->getEntityById('ProductBrand',$brand) : null;
        return (object)['from'=>$from,'to'=>$to,'today'=>$today,'brandName'=>$brandEntity?->get('name'),
            'liquidation'=>array_merge($this->paymentSummary($user,$quoteMap,$invoices,$excluded,$today),$this->reversalSummary($user,$quoteMap,$invoices)),'report'=>$report,'reportGroups'=>$reportGroups,
            'lifetimeFunnel'=>$this->lifetimeFunnel($user,$quoteMap,$invoices,$today),'openFunnel'=>$this->openFunnel($user,$brand,$quoteMap,$invoices),'metrics'=>$b,'groups'=>$groups,'forecast'=>array_values($forecast),'forecastMissingDates'=>$missing,
            'monthlyPreviewAvailable'=>$user->isAdmin(),
            'monthlyManualReview'=>count($manualMonthly),
            'reconciliationCents'=>$b['netti']['cents']-$b['maturate']['cents']-$b['daMaturare']['cents']-$b['mensili']['cents']-$b['inesigibili']['cents']];
    }

    /** Reconciliation deliberately includes invited/closed positions, independently of the portfolio exclusion. */
    public function invitationDifferences(User $user, ?string $brand, array $invoices, ?string $contractId=null, bool $includeResolved=false): array
    {
        $result=$this->bucket();$contracts=[];
        $lines=$this->em->getRDBRepository('InvitoAFatturareItem');
        if ($contractId!==null) {
            // Limit record panels to their contract, retaining the legacy commission fallback.
            $select=$this->em->getPDO()->prepare("SELECT l.id FROM invito_a_fatturare_item l LEFT JOIN provvigione p ON p.id=l.provvigione_id WHERE l.deleted=0 AND (l.contratto_id=? OR ((l.contratto_id IS NULL OR l.contratto_id='') AND p.contratto_id=?))");
            $select->execute([$contractId,$contractId]);
            $ids=$select->fetchAll(\PDO::FETCH_COLUMN);
            if (!$ids) { $result['contractCount']=0; return $result; }
            $lines=$lines->where(['id'=>$ids]);
        }
        foreach ($lines->find() as $line) {
            $i=$invoices[$line->get('invitoId')] ?? null;
            if (!$i) continue;
            $p=$line->get('provvigioneId') ? $this->em->getEntityById('Provvigione',$line->get('provvigioneId')) : null;
            if (!$p && $line->get('provvigioneId')) {
                $query=$this->em->getQueryBuilder()->select()->from('Provvigione')->withDeleted()->where(['id'=>$line->get('provvigioneId')])->build();
                foreach ($this->em->getRDBRepository('Provvigione')->clone($query)->find() as $archived) {$p=$archived;break;}
            }
            $qid=$line->get('contrattoId') ?: $p?->get('contrattoId');
            $q=$qid ? $this->em->getEntityById('Quote',$qid) : null;
            if (!$q || ($brand && $q->get('productBrandId')!==$brand)) continue;
            if ($contractId && $qid!==$contractId) continue;
            if (!$this->readable($user,$q) || !$this->readable($user,$line) || !$this->readable($user,$i) || ($p && !$this->readable($user,$p))) throw new Forbidden('Accesso parziale al confronto degli inviti.');
            $expected=$line->get('importoPrevistoRiga');$source='Conteggio CRM conservato nella riga';
            if ($p && $line->get('movimento')==='Addebito' && in_array($line->get('tipoLiquidazione'),['Saldo','Da verificare'],true) && $p->get('importo')!==null) {
                $count=$this->em->getPDO()->prepare("SELECT COUNT(*) FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id AND i.deleted=0 AND i.stato!='Annullato' WHERE l.provvigione_id=? AND l.deleted=0");
                $count->execute([$p->getId()]);
                if ((int)$count->fetchColumn()===1) {$expected=$p->get('importo');$source='Conteggio CRM attuale';}
            }
            if ($expected===null) {
                if ($p) $expected=$p->get('importo') ?? $p->get('importoConsolidato');
                elseif ($line->get('contrattoId') && $line->get('tipo')) {
                    $expected=null;
                    foreach ($this->em->getRDBRepository('Provvigione')->where(['contrattoId'=>$qid,'tipo'=>$line->get('tipo')])->find() as $component) {
                        if (!$this->readable($user,$component)) throw new Forbidden();
                        $expected=($expected ?? 0)+(float)($component->get('importo') ?? $component->get('importoConsolidato'));
                    }
                }
                if ($expected===null) continue;
                if ($line->get('movimento')==='Storno') $expected=-abs((float)$expected);
                $source='Conteggio CRM attuale (storico non disponibile)';
            }
            $expected=$this->cents($expected);$actual=$this->cents($line->get('importoConsolidato'));
            // Partial advances are expected to differ from the remaining balance.
            if ($line->get('tipoLiquidazione')==='Acconto' && abs($actual)<=abs($expected) && $actual*$expected>=0) continue;
            if (!isset($contracts[$qid])) $contracts[$qid]=['id'=>$qid,'entityType'=>'Quote','name'=>$q->get('name'),'date'=>$q->get('dateQuoted'),'expectedCents'=>0,'invitedCents'=>0,'differenceCents'=>0,'absoluteCents'=>0,'lines'=>[]];
            $history=json_decode($line->get('incongruenzaStorico') ?: '[]',true,512,JSON_THROW_ON_ERROR);
            $reviewState=$line->get('incongruenzaStato') ?: 'Da verificare';
            $last=$history ? $history[count($history)-1] : null;
            $closed=in_array($reviewState,['Confermata','Rettificata'],true);
            $reopened=$closed && (!$last || ($last['expectedCents'] ?? null)!==$expected || ($last['actualCents'] ?? null)!==$actual);
            if ($reopened) {$reviewState='Da verificare';$closed=false;}
            if ($closed && !$includeResolved) continue;
            if ($expected===$actual && !$line->get('incongruenzaStato')) continue;
            $difference=$actual-$expected;$contract=&$contracts[$qid];
            $contract['hasReview']=true;
            $contract['expectedCents']+=$expected;$contract['invitedCents']+=$actual;$contract['differenceCents']+=$difference;$contract['absoluteCents']+=abs($difference);
            $contract['lines'][]=['invoiceId'=>$i->getId(),'invoiceName'=>$i->get('name') ?: $i->getId(),'invoiceState'=>$i->get('stato'),
                'name'=>$p?->get('tipo') ?: $line->get('tipo') ?: $line->get('name'), 'movement'=>$line->get('movimento'),
                'expectedCents'=>$expected,'invitedCents'=>$actual,'differenceCents'=>$difference,'reference'=>$source,
                'lineId'=>$line->getId(),'reviewState'=>$reviewState,'reviewNotes'=>$line->get('incongruenzaNote'),'reviewHistory'=>$history,'reviewVersion'=>(int)$line->get('incongruenzaVersion'),'reviewClosed'=>$closed,'reviewReopened'=>$reopened,'correctionInvoiceId'=>$line->get('incongruenzaInvitoRettificaId')];
            unset($contract);
        }
        foreach ($contracts as $row) {
            if (!$row['absoluteCents'] && empty($row['hasReview'])) continue;
            $this->add($result,$row['differenceCents'],$row);
        }
        $result['contractCount']=$result['count'];
        return $result;
    }

    private function reversalSummary(User $user, array $quotes, array $invoices): array
    {
        $b=['stornate'=>$this->bucket(),'daStornare'=>$this->bucket()];$positions=[];$charges=[];$allLines=$this->em->getRDBRepository('InvitoAFatturareItem')->find();
        foreach ($allLines as $candidate) if ($candidate->get('movimento')==='Addebito' && isset($invoices[$candidate->get('invitoId')]) && $candidate->get('provvigioneId')) $charges[$candidate->get('provvigioneId')][]=$candidate;
        foreach ($allLines as $line) {
            $i=$invoices[$line->get('invitoId')] ?? null;if (!$i) continue;
            $p=$line->get('provvigioneId') ? $this->em->getEntityById('Provvigione',$line->get('provvigioneId')) : null;
            if (!$p && $line->get('provvigioneId')) {
                $query=$this->em->getQueryBuilder()->select()->from('Provvigione')->withDeleted()->where(['id'=>$line->get('provvigioneId')])->build();
                foreach ($this->em->getRDBRepository('Provvigione')->clone($query)->find() as $archived) {$p=$archived;break;}
            }
            $qid=$line->get('contrattoId') ?: $p?->get('contrattoId');$item=$quotes[$qid] ?? null;if (!$item) continue;
            $q=$item['entity'];
            if (!$this->readable($user,$line) || !$this->readable($user,$i) || ($p && !$this->readable($user,$p))) throw new Forbidden('Accesso parziale agli storni.');
            $value=$this->cents($line->get('importoConsolidato'));if (!$value) continue;
            $category=self::category($line->get('tipo') ?: $p?->get('tipo'));
            $row=['id'=>$line->getId(),'entityType'=>'InvitoAFatturareItem','contractId'=>$qid,'contractName'=>$q->get('name'),
                'name'=>$q->get('name'),'date'=>$q->get('dateQuoted'),'category'=>$category,'status'=>'Storno registrato · '.$i->get('name')];
            $isReversal=$line->get('movimento')==='Storno';
            if ($isReversal) {
                $originId=$line->get('rigaOrigineId');$origins=[];
                if ($originId) {
                    $origin=$this->em->getEntityById('InvitoAFatturareItem',$originId);
                    if ($origin) $origins[]=$origin;
                } else {
                    foreach ($charges[$line->get('provvigioneId')] ?? [] as $candidate) {
                        $origins[]=$candidate;
                    }
                }
                $detail=$row+['commissionType'=>$line->get('tipo') ?: $p?->get('tipo'),'reversalId'=>$i->getId(),'reversalName'=>$i->get('name'),'reversalCents'=>$value,'origins'=>[],'explicitOrigin'=>(bool)$originId];
                foreach ($origins as $origin) {
                    $originalInvoice=$this->em->getEntityById('InvitoAFatturare',$origin->get('invitoId'));
                    if (!$originalInvoice) continue;
                    if (!$this->readable($user,$origin) || !$this->readable($user,$originalInvoice)) throw new Forbidden('Accesso parziale agli inviti originari.');
                    $detail['origins'][]=['id'=>$originalInvoice->getId(),'name'=>$originalInvoice->get('name'),'cents'=>$this->cents($origin->get('importoConsolidato'))];
                }
                $this->add($b['stornate'],$value,$row);
                $b['stornate']['reversalLines'][]=$detail;
            }
            $key=$p?->getId() ?: $qid.'|'.$line->get('tipo');
            if (!isset($positions[$key])) $positions[$key]=['booked'=>0,'reversed'=>0,'item'=>$item,'row'=>$row];
            if ($isReversal) $positions[$key]['reversed']+=$value;
            elseif ($line->get('movimento')==='Addebito' && in_array($i->get('stato'),['Definitivo','Pagato'],true)) $positions[$key]['booked']+=$value;
        }
        foreach ($positions as $position) {
            $item=$position['item'];$q=$item['entity'];
            $invalid=in_array($item['bucket'],['recessi','finanziamentiRifiutati'],true) || in_array($q->get('statoContratto'),['Recesso','Annullato'],true);
            if (!$invalid || !$position['booked']) continue;
            $remaining=$position['booked']+$position['reversed'];
            // A full or excess reversal has no remaining amount to reverse.
            if (!$remaining || $remaining*$position['booked']<=0) continue;
            $row=$position['row'];$row['status']='Da stornare · '.($q->get('statoContratto') ?: $q->get('statoFinanziamento'));
            $this->add($b['daStornare'],-$remaining,$row);
        }
        return $b;
    }

    /** Payment-only view: no monthly commercial compensation and no already-invited position. */
    private function paymentSummary(User $user, array $quotes, array $invoices, array $excluded, string $today): array
    {
        $result=array_fill_keys(['pagate','recessi','finanziamentiKo','maturate','daMaturare'],$this->bucket());
        $invited=[];$automatic=[];
        foreach ($this->em->getRDBRepository('InvitoAFatturareItem')->find() as $line) {
            if (!isset($invoices[$line->get('invitoId')])) continue;
            if ($line->get('provvigioneId')) $invited[$line->get('provvigioneId')]=true;
            if ($line->get('voceAutomatica') && $line->get('contrattoId')) $automatic[$line->get('contrattoId').'|'.$line->get('tipo')]=true;
        }
        foreach ($this->em->getRDBRepository('Provvigione')->find() as $p) {
            $item=$quotes[$p->get('contrattoId')] ?? null;
            if (!$item || !$this->readable($user,$p) || $this->ariel->monthlyOnly($p)) continue;
            if (isset($invited[$p->getId()]) || isset($automatic[$p->get('contrattoId').'|'.$p->get('tipo')])) continue;
            $q=$item['entity'];$amount=$this->cents($p->get('importoConsolidato') ?? $p->get('importo'));
            $row=$this->row($p,'Provvigione',$q->get('dateQuoted'));
            $row['category']=self::category($p->get('tipo'));
            // A payment date/amount is evidence of receipt; the label "In pagamento" is not.
            $paymentDate=ArielInvoicePolicy::date($p->get('dataPagamento')) ?: ArielInvoicePolicy::date($p->get('dataLiquidazioneEffettiva'));
            $paid=$this->cents($p->get('importoPagatoInviti'));
            if ($paymentDate && $paymentDate<=$today && $p->get('statoProvvigione')!=='Acconto provvigionale') $paid=$amount;
            if ($paid) { $paidRow=$row;$paidRow['date']=$paymentDate;$paidRow['status']='Pagamento registrato';$this->add($result['pagate'],$paid,$paidRow); }
            if (isset($excluded[$p->getId()])) continue;
            $remaining=$amount-$paid;
            if (!$remaining) continue;
            $key=match($item['bucket']) {
                'recessi'=>'recessi','finanziamentiRifiutati'=>'finanziamentiKo',default=>null,
            };
            if (!$key) {
                $eligible=$item['bucket']==='netti' && $p->get('statoProvvigione')!=='Inesigibile'
                    && ($this->ariel->isAriel($p) ? $this->ariel->maturity($q)!==null : in_array($p->get('statoProvvigione'),['In pagamento','Acconto provvigionale'],true));
                $key=$eligible?'maturate':'daMaturare';
            }
            $row['status']=match($key) {'recessi'=>'Recesso','finanziamentiKo'=>'Finanziamento respinto/annullato','maturate'=>'Condizioni di pagamento soddisfatte',default=>'Condizioni di pagamento da soddisfare o posizione bloccata'};
            $this->add($result[$key],$remaining,$row);
        }
        return $result;
    }

    /** Exclude membership, not just paid amounts: drafts and unpaid invitations reserve the entire position. */
    private function excludedPositions(): array
    {
        $invoices=[];$months=[];$direct=[];$automatic=[];$excluded=[];
        foreach ($this->em->getRDBRepository('InvitoAFatturare')->where(['stato!='=>'Annullato'])->find() as $i) {
            $invoices[$i->getId()]=true;
            if ($i->get('arielIncludiSaldoMensile')) $months[$i->get('fornitorePartnerId').'|'.substr((string)$i->get('meseCompetenza'),0,7)]=true;
        }
        foreach ($this->em->getRDBRepository('InvitoAFatturareItem')->find() as $line) {
            if (!isset($invoices[$line->get('invitoId')])) continue;
            if ($line->get('provvigioneId')) $direct[$line->get('provvigioneId')]=true;
            if ($line->get('voceAutomatica') && $line->get('contrattoId')) $automatic[$line->get('contrattoId').'|'.$line->get('tipo')]=true;
        }
        foreach ($this->em->getRDBRepository('Provvigione')->find() as $p) {
            $state=$p->get('statoProvvigione');
            $exclude=isset($direct[$p->getId()]) || isset($automatic[$p->get('contrattoId').'|'.$p->get('tipo')]) || self::isFinanciallyClosed($state)
                || ($state!=='Acconto provvigionale' && ($p->get('dataPagamento') || $p->get('dataLiquidazioneEffettiva')));
            if (!$exclude && $this->ariel->monthlyOnly($p)) {
                $q=$this->ariel->quoteFor($p);
                $exclude=isset($months[$p->get('fornitorePartnerId').'|'.substr((string)$q?->get('dateQuoted'),0,7)]);
            }
            if ($exclude) $excluded[$p->getId()]=true;
        }
        return $excluded;
    }

    public static function commerciallyMature(array $q): bool
    {
        if (ArielInvoicePolicy::blocked($q) || ($q['status'] ?? '')==='Bozza') return false;
        if (in_array($q['statoFinanziamento'] ?? '', ['Approvato','In valutazione'],true)) return true;
        if (($q['status'] ?? '')==='Installato' || in_array($q['statoContratto'] ?? '', ['Installato','Chiuso'],true)) return true;
        return ArielInvoicePolicy::maturity($q)!==null;
    }

    public static function isFinanciallyClosed(?string $state): bool
    {
        return in_array($state,['Pagato','Chiuso con differenza'],true);
    }

    /** Lifetime positions. Dates and contract production filters do not constrain this funnel. */
    /** Lifetime portfolio, including invited and settled commissions. */
    private function lifetimeFunnel(User $user, array $quotes, array $invoices, string $today): array
    {
        $metrics=array_fill_keys(['totali','finanziamentiOk','recessi','finanziamentiKo','netti','pagate'],$this->bucket());
        $groups=array_fill_keys(['provvigioni','plus','minus','bonus','altre','nonAttribuite'],$metrics);
        $linked=[];
        foreach ($this->em->getRDBRepository('InvitoAFatturareItem')->find() as $line) {
            $i=$invoices[$line->get('invitoId')] ?? null;
            if (!$i) continue;
            if ($line->get('provvigioneId')) $linked[$line->get('provvigioneId')]=true;
            if ($i->get('stato')!=='Pagato') continue;
            $p=$line->get('provvigioneId') ? $this->em->getEntityById('Provvigione',$line->get('provvigioneId')) : null;
            if (!$p && $line->get('provvigioneId')) {
                $query=$this->em->getQueryBuilder()->select()->from('Provvigione')->withDeleted()->where(['id'=>$line->get('provvigioneId')])->build();
                foreach($this->em->getRDBRepository('Provvigione')->clone($query)->find() as $archived){$p=$archived;break;}
            }
            $qid=$line->get('contrattoId') ?: $p?->get('contrattoId');
            $item=$quotes[$qid] ?? null;
            if (!$item) continue;
            if (!$this->readable($user,$line) || !$this->readable($user,$i) || ($p && !$this->readable($user,$p))) throw new Forbidden('Accesso parziale ai pagamenti storici.');
            $category=self::category($line->get('tipo') ?: $p?->get('tipo'));
            $row=['id'=>$line->getId(),'entityType'=>'InvitoAFatturareItem','name'=>$line->get('name'),
                'contractId'=>$qid,'contractName'=>$item['entity']->get('name'),'date'=>$i->get('dataPagamento'),
                'status'=>'Invito pagato · '.$i->get('name'),'category'=>$category];
            $this->addMetric($metrics,$groups,$category,'pagate',$this->cents($line->get('importoConsolidato')),$row);
        }
        foreach ($quotes as $item) {
            $q=$item['entity'];$count=0;
            $recesso=$item['bucket']==='recessi';
            $ko=!$recesso && $item['bucket']==='finanziamentiRifiutati';
            $ok=!$recesso && !$ko && $q->get('statoFinanziamento')==='Approvato';
            foreach ($this->em->getRDBRepository('Provvigione')->where(['contrattoId'=>$q->getId()])->find() as $p) {
                if (!$this->readable($user,$p)) throw new Forbidden('Accesso parziale alle provvigioni storiche.');
                $count++;
                $category=self::category($p->get('tipo'));
                $amount=$this->cents($p->get('importoConsolidato') ?? $p->get('importo'));
                $row=$this->row($p,'Provvigione',$q->get('dateQuoted'));
                $row['category']=$category;$row['status']=$p->get('statoProvvigione');
                foreach (['totali'=>true,'finanziamentiOk'=>$ok,'recessi'=>$recesso,'finanziamentiKo'=>$ko,'netti'=>!$recesso&&!$ko] as $key=>$include) {
                    if ($include) $this->addMetric($metrics,$groups,$category,$key,$amount,$row);
                }
                // Invoice payments are authoritative; never add their cached commission total twice.
                if (isset($linked[$p->getId()])) continue;
                $date=ArielInvoicePolicy::date($p->get('dataPagamento')) ?: ArielInvoicePolicy::date($p->get('dataLiquidazioneEffettiva'));
                $paid=$this->cents($p->get('importoPagatoInviti'));
                if ($p->get('statoProvvigione')==='Pagato' || ($date && $date<=$today && $p->get('statoProvvigione')!=='Acconto provvigionale')) $paid=$amount;
                if ($paid) {$paidRow=$row;$paidRow['date']=$date;$paidRow['status']='Pagamento storico registrato';$this->addMetric($metrics,$groups,$category,'pagate',$paid,$paidRow);}
            }
            if (!$count) {
                $amount=$this->cents($item['amount']);
                if (!$amount) continue;
                $row=['id'=>$q->getId(),'entityType'=>'Quote','name'=>$q->get('name'),'contractId'=>$q->getId(),
                    'contractName'=>$q->get('name'),'date'=>$q->get('dateQuoted'),'status'=>'Importo del contratto da riconciliare','category'=>'nonAttribuite'];
                foreach (['totali'=>true,'finanziamentiOk'=>$ok,'recessi'=>$recesso,'finanziamentiKo'=>$ko,'netti'=>!$recesso&&!$ko] as $key=>$include) {
                    if ($include) $this->addMetric($metrics,$groups,'nonAttribuite',$key,$amount,$row);
                }
            }
        }
        return ['metrics'=>$metrics,'groups'=>$groups];
    }

    private function openFunnel(User $user, ?string $brand, array $quotes, array $invoices): array
    {
        $excluded=$this->excludedPositions();
        $metrics=array_fill_keys(['aperte','maturate','disponibili','daMaturare','negliInviti','bloccate','mensili'],$this->bucket());
        $groups=array_fill_keys(['provvigioni','plus','minus','bonus','altre'],$metrics);
        $snapshot=array_fill_keys(['provvigioniMaturate','bonusMaturati','plusMinusMaturati','sospese'],$this->bucket());
        $direct=[]; $automatic=[]; $reversed=[];
        foreach ($this->em->getRDBRepository('InvitoAFatturareItem')->find() as $line) {
            $i=$invoices[$line->get('invitoId')] ?? null;
            if (!$i) continue;
            if ($line->get('rigaOrigineId') && $i->get('stato')==='Pagato') $reversed[$line->get('rigaOrigineId')]=true;
            if ($line->get('provvigioneId')) $direct[$line->get('provvigioneId')][]=$line;
            elseif ($line->get('voceAutomatica') && $line->get('contrattoId')) $automatic[$line->get('contrattoId').'|'.$line->get('tipo')][]=$line;
        }
        foreach ($this->em->getRDBRepository('Provvigione')->find() as $p) {
            if (isset($excluded[$p->getId()])) continue;
            if (!$this->readable($user,$p)) continue;
            $q=$p->get('contrattoId') ? $this->em->getEntityById('Quote',$p->get('contrattoId')) : null;
            if ($q && !$this->readable($user,$q)) continue;
            if ($brand && ($q?->get('productBrandId') ?: $p->get('productBrandId'))!==$brand) continue;
            $state=$p->get('statoProvvigione');
            if (self::isFinanciallyClosed($state)) continue;
            $monthly=$this->ariel->monthlyOnly($p);
            $rows=$direct[$p->getId()] ?? [];
            if ($monthly) $rows=array_merge($rows,$automatic[$p->get('contrattoId').'|'.$p->get('tipo')] ?? []);
            $closed=false; $paid=0; $reserved=0;
            foreach ($rows as $line) {
                $i=$invoices[$line->get('invitoId')];
                if (!$this->readable($user,$line) || !$this->readable($user,$i)) throw new Forbidden('Accesso parziale agli inviti collegati.');
                $value=$this->cents($line->get('importoConsolidato'));
                if ($i->get('stato')==='Pagato') {
                    $paid+=$value;
                    if ($line->get('movimento')==='Addebito'
                        && !in_array($line->get('tipoLiquidazione') ?: 'Saldo',['Acconto','Da verificare'],true)
                        && !isset($reversed[$line->getId()])) $closed=true;
                } else $reserved+=$value;
            }
            if ($closed) continue;
            if (!$rows) {
                // Historical direct settlement is final, but an advance remains open even with a payment date.
                if ($state!=='Acconto provvigionale' && ($p->get('dataPagamento') || $p->get('dataLiquidazioneEffettiva'))) continue;
                $paid=$this->cents($p->get('importoPagatoInviti'));
            }
            if (($p->get('importoConsolidatoCurrency') ?: $p->get('importoCurrency') ?: 'EUR')!=='EUR') throw new \Espo\Core\Exceptions\BadRequest('La dashboard richiede importi in EUR.');
            $residual=$this->cents($p->get('importoConsolidato') ?? $p->get('importo'))-$paid;
            $category=self::category($p->get('tipo'));
            $row=$this->row($p,'Provvigione',$q?->get('dateQuoted'));
            $row['category']=$category;$row['status']=$state;
            $this->addMetric($metrics,$groups,$category,'aperte',$residual,$row);
            $blocked=$state==='Inesigibile' || ($q && (($quotes[$q->getId()]['bucket'] ?? 'netti')!=='netti'
                || ArielInvoicePolicy::blocked($this->ariel->quoteData($q))));
            $commercialMature=!$blocked && ($q ? self::commerciallyMature($this->ariel->quoteData($q)) : in_array($state,['In pagamento','Acconto provvigionale'],true));
            $snapshotKey=!$commercialMature ? 'sospese' : match($category) {
                'bonus'=>'bonusMaturati', 'plus','minus'=>'plusMinusMaturati', default=>'provvigioniMaturate'
            };
            $this->add($snapshot[$snapshotKey],$residual,$row);
            if ($blocked) { $this->addMetric($metrics,$groups,$category,'bloccate',$residual,$row);continue; }
            if ($monthly) { $this->addMetric($metrics,$groups,$category,'mensili',$residual,$row);continue; }
            $mature=($q && $this->ariel->isAriel($p)) ? $this->ariel->maturity($q)!==null
                : in_array($state,['In pagamento','Acconto provvigionale'],true);
            $mature=$mature || $paid!==0 || $reserved!==0;
            if (!$mature) { $this->addMetric($metrics,$groups,$category,'daMaturare',$residual,$row);continue; }
            $this->addMetric($metrics,$groups,$category,'maturate',$residual,$row);
            if ($reserved) $this->addMetric($metrics,$groups,$category,'negliInviti',$reserved,$row);
            $available=$residual-$reserved;
            if ($available) $this->addMetric($metrics,$groups,$category,'disponibili',$available,$row);
        }
        return ['metrics'=>$metrics,'groups'=>$groups,'snapshot'=>$snapshot];
    }

    private function forecastAdd(array &$forecast,string $supplier,string $date,string $label,int $amount,array $row): void
    {
        $key=$supplier.'|'.$date;
        if (!isset($forecast[$key])) $forecast[$key]=$this->bucket()+['supplierId'=>$supplier,'date'=>$date,'label'=>$label];
        $this->add($forecast[$key],$amount,$row);
        $forecast[$key]['allRecords'][]=$row+['amount'=>$amount/100];
        $category=$row['category'] ?? 'altre';
        if (!isset($forecast[$key]['parts'][$category])) $forecast[$key]['parts'][$category]=$this->bucket();
        $this->add($forecast[$key]['parts'][$category],$amount,$row);
    }
}
