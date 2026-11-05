<?php
declare(strict_types=1);
namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Exceptions\BadRequest;

class ArielInvitiService
{
    public function __construct(private EntityManager $em) {}
    public function isAriel(Entity $entity): bool
    {
        if (in_array($entity->get('regimeProvvigione'), ['ARIEL 2026','ARIEL 2025'], true)) return true;
        if ($entity->get('productBrandId')) {
            $brand = $this->em->getEntityById('ProductBrand', $entity->get('productBrandId'));
            return strtoupper(trim((string)$brand?->get('name'))) === 'ARIEL';
        }
        if ($entity->get('fornitorePartnerId')) {
            $supplier = $this->em->getEntityById('FornitorePartner', $entity->get('fornitorePartnerId'));
            return strtoupper(trim((string)$supplier?->get('name'))) === 'GDL';
        }
        return false;
    }
    public function quoteData(Entity $q): array
    {
        $data=[];
        foreach (['status','statoContratto','finanziamento','statoFinanziamento','bloccoOrdineAriel','dataInstallazione','dataAttivazione','dateQuoted','importoContratto','grandTotalAmount','importoCaparra'] as $f) $data[$f]=$q->get($f);
        return $data;
    }
    public function maturity(Entity $q): ?string { return ArielInvoicePolicy::maturity($this->quoteData($q)); }
    public function monthlyOnly(Entity $p): bool
    {
        return $this->isAriel($p) && $p->get('regimeProvvigione') !== 'ARIEL 2025'
            && in_array($p->get('tipo'), ['Plus Provvigionale','Minus Provvigionale'], true);
    }
    public function quoteFor(Entity $p): ?Entity
    {
        return $p->get('contrattoId') ? $this->em->getEntityById('Quote',$p->get('contrattoId')) : null;
    }
    public function memberMonth(Entity $p): ?string
    {
        $q=$this->quoteFor($p);
        $date=$q ? $this->maturity($q) : null;
        return $date ? substr($date,0,7).'-01' : null;
    }
    public function validateMember(Entity $i, Entity $p): void
    {
        $manualExtra = $this->monthlyOnly($p) && !$i->get('arielIncludiSaldoMensile');
        if ($this->monthlyOnly($p) && !$manualExtra) throw new BadRequest('Disattivare Includi saldo minus/plus del mese per selezionare singoli plus o minus.');
        if ($manualExtra) {
            $quote = $this->quoteFor($p);
            $contractDate = $quote?->get('dateQuoted');
            $auto = $this->em->getPDO()->prepare("SELECT l.id FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id WHERE l.deleted=0 AND i.deleted=0 AND i.stato<>'Annullato' AND l.contratto_id=? AND l.tipo=? AND l.voce_automatica IS NOT NULL AND l.voce_automatica<>'' LIMIT 1");
            $auto->execute([$quote?->getId(), $p->get('tipo')]);
            if ($auto->fetchColumn()) throw new BadRequest('Questa voce e gia inclusa in un riepilogo automatico: rimuovere prima quel riepilogo per evitare duplicazioni.');
        }
        if (!$this->isAriel($p)) throw new BadRequest('L’invito può includere soltanto provvigioni del fornitore.');
        // Manual membership is independent of the invoice month; populate filters its own period.
        $q=$this->quoteFor($p);
        if ($p->get('tipo') === 'Bonus Ordine Completo' && $q?->get('ordineIncompletoAriel')) throw new BadRequest('L’ordine incompleto non dà diritto al bonus del 5%.');
        $s=$this->em->getPDO()->prepare("SELECT l.id FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id WHERE l.deleted=0 AND i.deleted=0 AND i.stato<>'Annullato' AND l.movimento='Addebito' AND l.provvigione_id=? AND i.id<>? AND (i.stato<>'Pagato' OR COALESCE(l.tipo_liquidazione,'Saldo') NOT IN ('Acconto','Da verificare')) LIMIT 1");
        $s->execute([$p->getId(),($i->isNew() ? null : $i->getId()) ?: '']);
        if ($s->fetchColumn()) throw new BadRequest('La provvigione è già presente in un altro invito attivo.');
    }
    public function prepare(Entity $i): void
    {
        if (!$this->isAriel($i)) { $i->set('arielRegolamento',false); return; }
        $i->set('arielRegolamento',true);
        $calendar = ArielInvoicePolicy::calendar($i->get('meseCompetenza'));
        $i->set(array_intersect_key($calendar, array_flip(['meseLiquidazione', 'arielDataPagamentoPrevista'])));
        if ($i->get('arielIncludiSaldoMensile') === null) $i->set('arielIncludiSaldoMensile',true);
        if ($i->get('arielIncludiSaldoMensile') && $i->get('stato') !== 'Annullato') {
            // Serialize all invitations of the same supplier before checking ownership.
            $s=$this->em->getPDO()->prepare('SELECT id FROM fornitore_partner WHERE id=? FOR UPDATE'); $s->execute([$i->get('fornitorePartnerId')]);
            foreach ($this->em->getRDBRepository('InvitoAFatturare')->where(['fornitorePartnerId'=>$i->get('fornitorePartnerId'),'meseCompetenza'=>$i->get('meseCompetenza')])->find() as $other) {
                if ($other->getId() !== ($i->isNew() ? null : $i->getId()) && $other->get('stato') !== 'Annullato' && $other->get('arielIncludiSaldoMensile')) throw new BadRequest('Il saldo minus/plus del mese è già incluso in un altro invito. Disattivare Includi saldo mensile in questo invito.');
            }
        }
        $old=$i->isNew()?null:$i->getFetched('stato');
        $wasFinal=in_array($old,['Definitivo','Pagato'],true);
        if ($wasFinal && in_array($i->get('stato'),['Definitivo','Pagato'],true)) {
            foreach (['arielIncludiSaldoMensile','arielSnapshot','arielPlusLordo','arielMinusLordo','arielCompensato','arielSaldoMinusPlus','arielProvvigionePlus','arielAddebitoMinus','arielPremioProduzione'] as $f) {
                if ($i->isAttributeChanged($f)) throw new BadRequest('Il riepilogo economico confermato non è modificabile. Riaprire prima l’invito.');
            }
        } else {
            $this->calculateSettlement($i);
        }
        if (in_array($i->get('stato'),['Definitivo','Pagato'],true)) {
            if (!$wasFinal && ArielInvoicePolicy::today() < $calendar['arielDataConfermaPrevista']) throw new BadRequest('Le provvigioni del fornitore diventano ufficiali il giorno 10 del mese successivo.');
            if (trim((string)$i->get('arielAnomalie'))) throw new BadRequest('Risolvere le verifiche indicate nel riepilogo prima della conferma.');
        }
        if ($i->get('dataProformaKingsistem')) {
            $date=ArielInvoicePolicy::date($i->get('dataProformaKingsistem'));
            if (!$date || $date > ArielInvoicePolicy::today()) throw new BadRequest('Inserire una data proforma valida e non futura.');
        }
        if ($i->get('stato') === 'Pagato') {
            $date=ArielInvoicePolicy::date($i->get('dataPagamento'));
            if (!$i->get('numeroFattura') || !$i->get('dataFattura')) throw new BadRequest('Per il pagamento servono numero e data fattura.');
            if (!$date || $date > ArielInvoicePolicy::today() || $date < ArielInvoicePolicy::date($i->get('dataFattura'))) throw new BadRequest('Inserire la data effettiva di accredito, non futura e non precedente alla fattura.');
        }
    }
    public function supplementCents(Entity $i): int
    {
        return $this->isAriel($i) ? (int)round(100*((float)$i->get('arielProvvigionePlus')+(float)$i->get('arielAddebitoMinus')+(float)$i->get('arielPremioProduzione'))) : 0;
    }
    /** Read-only estimate: calculate on an unsaved entity, without prepare/save hooks. */
    public function previewSettlement(string $supplierId, string $month): Entity
    {
        $i = $this->em->getNewEntity('InvitoAFatturare');
        $i->set(['fornitorePartnerId'=>$supplierId, 'meseCompetenza'=>$month, 'arielIncludiSaldoMensile'=>true]);
        $this->calculateSettlement($i);
        return $i;
    }

    private function calculateSettlement(Entity $i): void
    {
        $month=$i->get('meseCompetenza'); $grossPlus=0; $grossMinus=0; $events=[]; $warnings=[]; $details=[];
        if ($i->get('arielIncludiSaldoMensile')) {
            $quotes=[];
            foreach ($this->em->getRDBRepository('Quote')->where(['fornitorePartnerId'=>$i->get('fornitorePartnerId')])->find() as $q) if ($this->isAriel($q)) $quotes[$q->getId()]=$q;
            $previous=[]; $reversed=[];
            foreach ($this->em->getRDBRepository('InvitoAFatturare')->where(['fornitorePartnerId'=>$i->get('fornitorePartnerId')])->find() as $other) {
                if ($other->getId()===($i->isNew() ? null : $i->getId()) || $other->get('stato')==='Annullato' || $other->get('meseCompetenza') >= $month) continue;
                if (!in_array($other->get('stato'),['Definitivo','Pagato'],true) && !$other->get('numeroFattura')) continue;
                foreach (json_decode($other->get('arielSnapshot') ?: '[]',true,512,JSON_THROW_ON_ERROR) as $event) {
                    if (($event['kind']??'')==='vendita') $previous[$event['quoteId']]=$event;
                    if (($event['kind']??'')==='rettifica') $reversed[$event['quoteId']]=true;
                }
            }
            foreach ($quotes as $id=>$q) {
                $signed=ArielInvoicePolicy::date($q->get('dateQuoted'));
                // Existing legacy commercial rules remain in force before their cutover.
                if (!$signed || $signed < ProvvigioneAccrual::ARIEL_2026_CUTOVER || $q->get('status')==='Bozza') continue;
                // Commercial plus/minus follow the main status, independently of the operational contract substatus.
                $blocked=!in_array($q->get('status'), ['In Gestione','Installato'], true);
                if (substr($signed,0,7)===substr($month,0,7) && !$blocked) {
                    if ($q->get('minusPlus')===null) { $warnings[]=$q->get('name').': manca minus/plus'; continue; }
                    $events[]=['kind'=>'vendita','quoteId'=>$id,'name'=>$q->get('name'),'date'=>$signed,'grossCents'=>(int)round(100*(float)$q->get('minusPlus'))];
                }
                if ($blocked && isset($previous[$id]) && !isset($reversed[$id])) {
                    $cancel=ArielInvoicePolicy::date($q->get('dataRettificaAriel'));
                    if (!$cancel) { $warnings[]=$q->get('name').': indicare Data rettifica Ariel per invertire il precedente minus/plus'; continue; }
                    if (substr($cancel,0,7)===substr($month,0,7)) {
                        $events[]=['kind'=>'rettifica','quoteId'=>$id,'name'=>$q->get('name'),'date'=>$cancel,'grossCents'=>-$previous[$id]['grossCents'],'originDate'=>$previous[$id]['date']];
                    }
                }
            }
        }
        usort($events,fn($a,$b)=>[$a['date'],$a['quoteId'],$a['kind']] <=> [$b['date'],$b['quoteId'],$b['kind']]);
        foreach ($events as $event) {
            $c=$event['grossCents']; if ($c>=0) $grossPlus+=$c; else $grossMinus-=$c;
            $details[]=$event['date'].' | '.$event['kind'].' | '.$event['name'].' | '.number_format($c/100,2,',','.').' EUR';
        }
        $i->set(ArielInvoicePolicy::settlement($grossPlus,$grossMinus));
        foreach (['arielPlusLordo','arielMinusLordo','arielCompensato','arielSaldoMinusPlus','arielProvvigionePlus','arielAddebitoMinus','arielPremioProduzione'] as $f) $i->set($f.'Currency','EUR');
        if ($grossMinus > 99000) $details[]='Minusvalenza mensile oltre 990 EUR: previsto affiancamento del responsabile.';
        $i->set(['arielSnapshot'=>json_encode($events,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'arielDettaglioCalcolo'=>implode("\n",$details),'arielAnomalie'=>implode("\n",array_unique($warnings))]);
    }
}
