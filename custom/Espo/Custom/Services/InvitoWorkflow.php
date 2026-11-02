<?php

namespace Espo\Custom\Services;



use Espo\Core\Acl;

use Espo\Core\Exceptions\BadRequest;

use Espo\Core\Exceptions\Forbidden;

use Espo\ORM\Entity;

use Espo\ORM\EntityManager;



/** Invoice membership and confirmation rules shared by API and ORM hooks. */

class InvitoWorkflow

{

    public function __construct(

        private EntityManager $em,

        private ProvvigioneStatusSync $statusSync,

        private ProvvigioneAccrual $accrual,

        private Acl $acl,

        private ArielInvitiService $ariel

    ) {}



    /** Read archived commissions only for invalid/cancelled contracts, without restoring them. */
    public function isHistoricalCommission(Entity $p): bool
    {
        $q=$p->get('contrattoId') ? $this->em->getEntityById('Quote',$p->get('contrattoId')) : null;
        return $q && ($q->get('status') === 'Invalido' || $q->get('statoContratto') === 'Annullato');
    }
    public function invoiceCommission(?string $id): ?Entity
    {
        if (!$id) return null;
        $p=$this->em->getEntityById('Provvigione',$id);
        if ($p) return $p;
        $query=$this->em->getQueryBuilder()->select()->from('Provvigione')->withDeleted()->where(['id'=>$id])->build();
        $p=$this->em->getRDBRepository('Provvigione')->clone($query)->findOne();
        return $p && $this->isHistoricalCommission($p) ? $p : null;
    }

    public function isDraft(Entity $i): bool

    {

        return in_array($i->get('stato'), ['Bozza', 'In verifica'], true);

    }



    public function isFinal(Entity $i): bool

    {

        return in_array($i->get('stato'), ['Definitivo', 'Pagato'], true);

    }



    public function lock(Entity $i): void

    {

        if (!$i->getId()) return;

        $s = $this->em->getPDO()->prepare('SELECT id FROM invito_a_fatturare WHERE id = ? FOR UPDATE');

        $s->execute([$i->getId()]);

    }



    public function rows(Entity $i): iterable

    {

        if (!$i->getId()) return [];

        return $this->em->getRDBRepository('InvitoAFatturareItem')->where(['invitoId' => $i->getId()])->find();

    }



    public function reservedCommissionIds(string $supplierId): array

    {

        $s=$this->em->getPDO()->prepare("SELECT p.id FROM provvigione p JOIN invito_a_fatturare_item l ON l.provvigione_id=p.id AND l.deleted=0 AND l.movimento='Addebito' JOIN invito_a_fatturare i ON i.id=l.invito_id AND i.deleted=0 AND i.stato<>'Annullato' WHERE p.deleted=0 AND p.fornitore_partner_id=? GROUP BY p.id,p.importo_consolidato,p.importo HAVING SUM(CASE WHEN l.movimento<>'Addebito' OR COALESCE(l.tipo_liquidazione,'Saldo')<>'Acconto' THEN 1 ELSE 0 END)>0 OR ABS(ROUND(SUM(COALESCE(l.importo_consolidato,0))*100))>=ABS(ROUND(COALESCE(p.importo_consolidato,p.importo,0)*100))");

        $s->execute([$supplierId]);$ids=$s->fetchAll(\PDO::FETCH_COLUMN);
        // Archived source rows remain owned by the active documentary settlement.
        $doc=$this->em->getPDO()->prepare("SELECT DISTINCT l.provvigione_id FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id AND i.deleted=0 JOIN invito_a_fatturare_item d ON d.invito_id=i.id AND d.deleted=0 AND (d.voce_automatica='saldoDocumentale' OR d.voce_automatica LIKE 'saldoDocumentale:%') WHERE l.deleted=1 AND l.description LIKE '[SALDO-DOCUMENTALE]%' AND l.provvigione_id IS NOT NULL AND i.stato<>'Annullato' AND i.fornitore_partner_id=?");
        $doc->execute([$supplierId]);return array_values(array_unique(array_merge($ids,$doc->fetchAll(\PDO::FETCH_COLUMN))));

    }



    public function dueDate(Entity $p): ?string

    {

        if ($this->ariel->isAriel($p)) {

            $month=$this->ariel->memberMonth($p);

            return $month ? ArielInvoicePolicy::calendar($month)['arielDataPagamentoPrevista'] : null;

        }

        $q = $p->get('contrattoId') ? $this->em->getEntityById('Quote', $p->get('contrattoId')) : null;

        if ($q && $this->statusSync->isProgetto($q)) return $this->statusSync->resolveDataPagamento($q);

        $r = $p->get('regolaProvvigionaleId') ? $this->em->getEntityById('RegolaProvvigionale', $p->get('regolaProvvigionaleId')) : null;

        $days = (int) ($r?->get('giorniLiquidazione') ?? $this->accrual->getLiquidationDays($p->get('regimeProvvigione') ?: 'GENERICO'));

        $event = $p->get('dataAttivazione') ?: $p->get('dataInstallazione') ?: $q?->get('dataAttivazione') ?: $q?->get('dataInstallazione');

        if ($days > 0 && $event) {

            return (new \DateTimeImmutable($event))->modify('last day of this month')->modify('+' . $days . ' days')->format('Y-m-d');

        }

        return ($q ? $this->statusSync->resolveDataPagamento($q) : null) ?: ($p->get('dataLiquidazionePrevista') ?: null);

    }



    public function eligible(Entity $p): bool

    {

        if ($this->ariel->isAriel($p)) return !$this->ariel->monthlyOnly($p)

            && !$p->get('consolidata') && !$p->get('dataPagamento') && !$p->get('dataLiquidazioneEffettiva') && $this->ariel->memberMonth($p) !== null;

        return !$p->get('consolidata') && !$p->get('dataLiquidazioneEffettiva') && !$p->get('dataPagamento')

            && in_array($p->get('statoProvvigione'), ['In pagamento', 'Pagato', 'Acconto provvigionale'], true);

    }



    public function validateMember(Entity $i, Entity $p, bool $checkPeriod = false): void

    {

        if ($this->ariel->isAriel($i)) {

            if (ArielInvoicePolicy::today() < ArielInvoicePolicy::calendar($i->get('meseCompetenza'))['arielDataCaricamento']) throw new BadRequest('Il caricamento Ariel Ã¨ disponibile dal giorno 5 del mese successivo.');

            $this->ariel->validateMember($i,$p);

        } elseif (!$this->isHistoricalCommission($p) && !in_array($p->get('statoProvvigione'), ['In pagamento', 'Pagato', 'Acconto provvigionale'], true)) throw new BadRequest('La provvigione non Ã¨ fatturabile.');

        if ($p->get('fornitorePartnerId') !== $i->get('fornitorePartnerId')) {

            throw new BadRequest('Il fornitore della provvigione deve coincidere con quello dellâ€™invito.');

        }

        if (($p->get('importoConsolidatoCurrency') ?: $p->get('importoCurrency') ?: 'EUR') !== 'EUR') {

            throw new BadRequest('Lâ€™invito gestisce provvigioni in EUR.');

        }

        if ($checkPeriod && $this->isArtel($i) && !$this->matchesArtelPeriod($i, $this->dueDate($p))) throw new BadRequest('La provvigione non appartiene alla quindicina selezionata.');

        if ($checkPeriod && !$this->ariel->isAriel($i) && substr((string) $this->dueDate($p), 0, 7) !== substr($i->get('meseCompetenza'), 0, 7)) {

            throw new BadRequest('La provvigione non rientra nel mese selezionato.');

        }

    }





    public function monthlyRowLabel(Entity $quote, string $tipo, float $amount): string

    {

        $date=$quote->get('dateQuoted');

        if (!$date) {

            $opportunity=$quote->get('opportunityId') ? $this->em->getEntityById('Opportunity',$quote->get('opportunityId')) : null;

            $date=$opportunity?->get('closeDate') ?: $quote->get('dataInstallazione') ?: $quote->get('dataAttivazione') ?: $quote->get('createdAt');

        }

        $date=$date ? substr((string)$date,0,10) : 'DATA-NON-DEFINITA';

        $number='';foreach(['numeroContratto','numberA','number'] as $field) { $number=trim((string)$quote->get($field));if($number!=='')break; }

        if($number==='')$number=$quote->getId();

        $client=strtoupper(trim((string)($quote->get('accountName') ?? 'Cliente')));

        return sprintf('%s - %s - %s - %s - â‚¬. %s',$date,$number,$client!==''?$client:'CLIENTE',strtoupper($tipo),number_format($amount,2,'.',''));

    }



    /** Contract-level signed components of the monthly settlement. */

    public function syncMonthlyRows(Entity $i): void

    {

        if (!$i->get('id')) return;

        $this->lock($i);$components=[];

        if ($this->ariel->isAriel($i)) {

            $target=(int)round(100*((float)$i->get('arielProvvigionePlus')+(float)$i->get('arielAddebitoMinus')));

            $events=json_decode($i->get('arielSnapshot') ?: '[]',true,512,JSON_THROW_ON_ERROR);

            foreach(MonthlyCommissionAllocation::rows($events,$target) as $key=>$e) {

                $quote=$this->em->getEntityById('Quote',$e['quoteId']);

                if(!$quote || $quote->get('fornitorePartnerId')!==$i->get('fornitorePartnerId')) throw new BadRequest('Contratto del movimento mensile non trovato o fornitore non corrispondente.');

                $positive=$e['grossCents']>0;

                $components[$key]=[

                    'name'=>$this->monthlyRowLabel($quote, ($positive?'Plus Provvigionale':'Minus Provvigionale').($e['kind']==='rettifica'?' - Rettifica':''), $e['amountCents']/100),

                    'contrattoId'=>$e['quoteId'],'tipo'=>$positive?'Plus Provvigionale':'Minus Provvigionale',

                    'baseCalcolo'=>$positive?'Plusvalenza':'Minusvalenza','importoBaseCalcolo'=>$e['grossCents']/100,

                    'importoBaseCalcoloCurrency'=>'EUR','tassoProvvigioni'=>$e['rate'],

                    'importoConsolidato'=>$e['amountCents']/100,

                    'description'=>'Quota del contratto nella compensazione mensile. '.($e['rate']===35?'Saldo positivo: aliquota 35% sulle componenti positive e negative.':'Saldo negativo: componenti positive e negative al 100% per determinare il minus residuo.').' Eventuali centesimi ripartiti per mantenere il totale esatto.'];

            }

            $award=round((float)$i->get('arielPremioProduzione'),2);

            if($award!=0)$components['premioProduzione']=['name'=>'Premio produzione del mese','tipo'=>'Premio produzione','importoConsolidato'=>$award,'description'=>'Premio calcolato sul risultato mensile.'];

        }

        $existing=[];$skip=['skipHooks'=>true,'skipFormula'=>true,'silent'=>true];

        foreach($this->rows($i) as $line) {

            $key=$line->get('voceAutomatica');if(!$key)continue;

            if(isset($existing[$key]))throw new BadRequest('Voce mensile duplicata.');

            $existing[$key]=$line;

        }

        // Preserve recorded documentary amounts and per-contract source rows, independently of current prices.
        foreach ($existing as $key=>$doc) {
            $adjustment=str_starts_with($key,'rettificaDocumentale:');
            $unclassified=$key==='integrazioneDocumentale';
            if ($key!=='saldoDocumentale' && !str_starts_with($key,'saldoDocumentale:') && !$adjustment && !$unclassified) continue;
            if ($i->get('arielIncludiSaldoMensile')) throw new BadRequest('Le voci documentali sono gia registrate: non sommare anche il saldo mensile automatico.');
            $raw=$doc->get('importoConsolidato');$detail=str_starts_with($key,'saldoDocumentale:') || $adjustment;
            if ($doc->get('provvigioneId') || !is_numeric($raw) || !is_finite((float)$raw) || abs((float)$raw)>100000000 || abs((float)$raw-round((float)$raw,2))>0.000001) throw new BadRequest('Importo documentale non valido.');
            if ($detail) {
                $q=$doc->get('contrattoId') ? $this->em->getEntityById('Quote',$doc->get('contrattoId')) : null;
                if (!$q || $q->get('fornitorePartnerId')!==$i->get('fornitorePartnerId') || $key!==($adjustment?'rettificaDocumentale:':'saldoDocumentale:').$q->getId()) throw new BadRequest('Contratto documentale non valido.');
                if ($adjustment) {
                    if ($doc->get('tipo')!=='Rettifica provvigioni' || $doc->get('movimento')!=='Storno' || $doc->get('tassoProvvigioni')!==null) throw new BadRequest('Rettifica documentale non valida.');
                } elseif (!in_array($doc->get('tipo'),['Plus Provvigionale','Minus Provvigionale'],true) || !is_numeric($doc->get('importoBaseCalcolo')) || !in_array((float)$doc->get('tassoProvvigioni'),[35.0,100.0],true)) throw new BadRequest('Dettaglio documentale non valido.');
            } elseif ($unclassified) {
                if ($doc->get('tipo')!=='Integrazione da classificare' || $doc->get('contrattoId') || $doc->get('movimento')!=='Addebito') throw new BadRequest('Integrazione documentale non valida.');
            } elseif ($doc->get('tipo')!=='Saldo Plus/Minus documentale' || $doc->get('contrattoId')) throw new BadRequest('Saldo documentale non valido.');
            $components[$key]=[];
            foreach (['name','tipo','movimento','tipoLiquidazione','contrattoId','baseCalcolo','importoBaseCalcolo','importoBaseCalcoloCurrency','tassoProvvigioni','description','importoConsolidato','importoOriginaleInvito','importoOriginaleInvitoCurrency','importoPrevistoRiga','importoPrevistoRigaCurrency'] as $field) $components[$key][$field]=$doc->get($field);
        }
        foreach($existing as $key=>$line)if(!isset($components[$key]))$this->em->removeEntity($line,$skip);

        foreach($components as $key=>$values) {

            $values+=['invitoId'=>$i->getId(),'voceAutomatica'=>$key,'movimento'=>'Addebito',

                'importoConsolidatoCurrency'=>'EUR','tipoLiquidazione'=>'Saldo','assignedUserId'=>$i->get('assignedUserId')];

            $line=$existing[$key]??$this->em->getNewEntity('InvitoAFatturareItem');$changed=$line->isNew();

            foreach($values as $field=>$value)if($line->get($field)!=$value)$changed=true;

            if(!$changed)continue;

            $line->set($values);$this->em->saveEntity($line,$skip);

        }

    }



    public function totals(Entity $i): int

    {

        $this->syncMonthlyRows($i);

        $cents = 0;

        $count = 0;

        foreach ($this->rows($i) as $p) {

            $cents += (int) round(100 * (float) ($p->get('importoConsolidato') ?? $p->get('importo') ?? 0));

            $count++;

        }

        if (!$i->get('id')) $cents += $this->ariel->supplementCents($i);

        if ($this->ariel->isAriel($i) && $i->get('arielImportoPrimaAdeguamento') !== null) {

            $i->set(['arielDifferenzaAdeguamento'=>$cents/100-(float)$i->get('arielImportoPrimaAdeguamento'),'arielDifferenzaAdeguamentoCurrency'=>'EUR']);

        }

        $i->set([

            'importoTotalePrevisto' => $cents / 100,

            'importoTotalePrevistoCurrency' => 'EUR',

            'importoTotaleConsolidato' => $this->isFinal($i) ? $cents / 100 : 0,

            'importoTotaleConsolidatoCurrency' => 'EUR',

            'scostamentoTotale' => 0,

        ]);

        return $count;

    }



    public function validateSave(Entity $i): void

    {

        $this->lock($i);

        if (!$i->isNew()) {

            $current = $this->em->getEntityById('InvitoAFatturare', $i->getId());

            if ($current && $current->get('stato') !== $i->getFetched('stato')) {

                throw new BadRequest('Lâ€™invito Ã¨ stato modificato. Ricaricare la pagina.');

            }

        }

        if (!$i->get('fornitorePartnerId') || !$i->get('meseCompetenza')) {

            throw new BadRequest('Specificare fornitore e mese di liquidazione.');

        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $i->get('meseCompetenza'));

        if (!$date || $date->format('Y-m-d') !== $i->get('meseCompetenza')) throw new BadRequest('Mese non valido.');

        $i->set('meseCompetenza', $date->format('Y-m-01'));

        $old = $i->isNew() ? null : $i->getFetched('stato');

        if (in_array($old, ['Definitivo', 'Pagato'], true)) {

            if ($i->get('stato') === 'Annullato') {

                throw new BadRequest('Per modificare lâ€™invito selezionare Bozza o In verifica.');

            }

            foreach (['fornitorePartnerId', 'meseCompetenza', 'periodoInvito', 'numeroFattura', 'dataFattura'] as $f) {

                if ($this->ariel->isAriel($i) && $old === 'Definitivo' && in_array($f,['numeroFattura','dataFattura'],true) && !$i->getFetched($f)) continue;

                if ($this->isFinal($i) && $i->isAttributeChanged($f)) throw new BadRequest('I dati di un invito definitivo non sono modificabili.');

            }

            if ($old === 'Pagato' && $i->get('stato') === 'Pagato' && $i->isAttributeChanged('dataPagamento')) throw new BadRequest('Il pagamento Ã¨ giÃ  registrato.');

        }

        // Reopening reverses payment/consolidation through afterSave, preserving invoice references.

        if ($old === 'Pagato' && $i->get('stato') !== 'Pagato') $i->set('dataPagamento', null);

        if (in_array($old, ['Definitivo', 'Pagato'], true) && $this->isDraft($i)) {

            $q = $this->em->getPDO()->prepare("SELECT l.id FROM invito_a_fatturare_item l JOIN invito_a_fatturare_item origin ON origin.id=l.riga_origine_id JOIN invito_a_fatturare other ON other.id=l.invito_id WHERE origin.invito_id=? AND origin.deleted=0 AND l.deleted=0 AND other.deleted=0 AND other.stato<>'Annullato' LIMIT 1");

            $q->execute([$i->getId()]);

            if ($q->fetchColumn()) throw new BadRequest('Questo invito ha storni collegati: rimuovere prima gli storni dalle relative bozze per riaprirlo.');

        }

        if ($old === 'Annullato' && $i->get('stato') !== $old) throw new BadRequest('Un invito annullato non puÃ² essere riaperto.');

        if ($i->isNew() && !$this->isDraft($i)) throw new BadRequest('Creare prima un invito in bozza e verificare le provvigioni.');

        if (!in_array($i->get('stato'), ['Bozza', 'In verifica', 'Definitivo', 'Pagato', 'Annullato'], true)) throw new BadRequest('Stato non valido.');



        // Multiple invitations may share the same supplier and liquidation month.

        $stmt = $this->em->getPDO()->prepare('SELECT id FROM fornitore_partner WHERE id = ? AND deleted = 0');

        $stmt->execute([$i->get('fornitorePartnerId')]);

        if (!$stmt->fetchColumn()) throw new BadRequest('Fornitore non trovato.');

        if (!$i->get('name')) {

            $supplier = $this->em->getEntityById('FornitorePartner', $i->get('fornitorePartnerId'));

            $i->set('name', 'INV-' . $date->format('Ym') . ' - ' . ($supplier?->get('name') ?: $i->get('fornitorePartnerId')));

        }

        if ($this->isArtel($i)) {

            $period = $i->get('periodoInvito');

            if (!$period || $period === 'Mensile') $period = '1-15';

            if (!in_array($period, ['1-15', '16-fine mese'], true)) throw new BadRequest('Periodo invito non valido.');

            $i->set('periodoInvito', $period);

            $suggestedDate = $date->format($period === '1-15' ? 'Y-m-15' : 'Y-m-t');

            $i->set('name', 'INV-' . $date->format('Ym') . ' - AMG / ARTEL - ' . $period);

        } else {

            $i->set('periodoInvito', 'Mensile');

            $suggestedDate = $date->modify('first day of next month')->format('Y-m-15');

        }

        // Suggest only when empty; preserve dates entered by the user.

        if (!$i->get('dataInvito')) $i->set('dataInvito', $suggestedDate);

        if (!ArielInvoicePolicy::date($i->get('dataInvito'))) throw new BadRequest('Inserire una data invito valida.');

        $this->ariel->prepare($i);

        if ($this->ariel->isAriel($i) && ($i->get('numeroFattura') || $i->get('dataFattura'))) {

            if (!trim((string)$i->get('numeroFattura')) || !ArielInvoicePolicy::date($i->get('dataFattura')) || $i->get('dataFattura') > ArielInvoicePolicy::today()) throw new BadRequest('Specificare insieme numero e data valida della fattura.');

        }

        $count = $this->totals($i);

        if ($this->isFinal($i)) {

            if (!$this->ariel->isAriel($i) && (!trim((string) $i->get('numeroFattura')) || !$i->get('dataFattura'))) throw new BadRequest('Numero e data fattura sono obbligatori per rendere definitivo lâ€™invito.');

            if (!$count && !$this->ariel->supplementCents($i)) throw new BadRequest('Un invito senza provvigioni o saldo mensile non puÃ² diventare definitivo.');

        }

        if ($i->get('stato') === 'Pagato' && (!trim((string)$i->get('numeroFattura')) || !ArielInvoicePolicy::date($i->get('dataFattura')))) throw new BadRequest('Per registrare il pagamento indicare numero e data fattura.');

        if ($i->get('stato') === 'Pagato' && (!ArielInvoicePolicy::date($i->get('dataPagamento')) || $i->get('dataPagamento') > ArielInvoicePolicy::today())) throw new BadRequest('Inserire una data di pagamento valida, non futura.');

        if ($i->get('stato') === 'Pagato' && !$i->get('dataPagamento')) throw new BadRequest('Inserire la data di pagamento.');

        if ($i->get('dataPagamento') && $i->get('stato') !== 'Pagato') throw new BadRequest('Per registrare il pagamento selezionare lo stato Pagato.');

        if ($i->get('dataPagamento') && $i->get('dataPagamento') < $i->get('dataFattura')) throw new BadRequest('La data pagamento non puÃ² precedere la fattura.');

        foreach ($this->rows($i) as $p) {

            if ($this->isDraft($i) || ($this->isFinal($i) && !in_array($old, ['Definitivo', 'Pagato'], true))) {

                $this->validateLine($i, $p);

            }

        }

    }



    public function validateLine(Entity $i, Entity $line): void

    {

        if ($line->get('invitoId') !== $i->getId()) throw new BadRequest('Riga non appartenente allâ€™invito.');

        if ($line->get('voceAutomatica')) return;

        $p = $this->invoiceCommission($line->get('provvigioneId'));

        if (!$p) throw new BadRequest('Provvigione non trovata.');

        if ($p->get('fornitorePartnerId') !== $i->get('fornitorePartnerId')) throw new BadRequest('Fornitore della riga non corrispondente.');

        if ($line->get('movimento') !== 'Storno') $this->validateAllocation($line,$p);

        if ($this->ariel->isAriel($i) && $line->get('movimento') !== 'Storno') $this->ariel->validateMember($i,$p);

    }





    public function validateAllocation(Entity $line, Entity $p): void

    {

        $s=$this->em->getPDO()->prepare("SELECT l.id,l.importo_consolidato,l.tipo_liquidazione,l.movimento FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id WHERE l.provvigione_id=? AND l.id<>? AND l.movimento='Addebito' AND l.deleted=0 AND i.deleted=0 AND i.stato<>'Annullato' FOR UPDATE");

        $s->execute([$p->getId(),$line->isNew() ? '' : $line->getId()]); $used=0;

        foreach($s->fetchAll(\PDO::FETCH_ASSOC) as $row) {

            if ($line->isNew() && ($row['movimento']!=='Addebito' || ($row['tipo_liquidazione'] ?: 'Saldo')!=='Acconto')) throw new BadRequest('Provvigione gia inserita in un invito attivo.');

            $used+=(int)round(100*(float)$row['importo_consolidato']);

            if ($row['movimento']==='Addebito' && !in_array(($row['tipo_liquidazione'] ?: 'Saldo'), ['Acconto','Da verificare'], true)) {

                $rev=$this->em->getPDO()->prepare("SELECT COUNT(*) FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id WHERE l.riga_origine_id=? AND l.deleted=0 AND i.deleted=0 AND i.stato IN ('Definitivo','Pagato')");

                $rev->execute([$row['id']]);

                if (!(int)$rev->fetchColumn()) throw new BadRequest('Esiste giÃ  una riga di saldo o chiusura per questa provvigione.');

            }

        }

        $raw=$line->get('importoConsolidato');

        if (!is_numeric($raw) || !is_finite((float)$raw) || abs((float)$raw-round((float)$raw,2))>0.000001) throw new BadRequest('Inserire un importo valido con massimo due decimali.');

        // Invoice-wide checks must preserve accepted historical rows when only another row changes.
        // Their commission may since have been recalculated, consolidated or reversed.
        if (!$line->isNew()
            && !$line->isAttributeChanged('importoConsolidato')
            && !$line->isAttributeChanged('tipoLiquidazione')
            && $line->getFetched('importoPrevistoRiga') !== null) {
            return;
        }

        $amount=(int)round(100*(float)$raw);

        $expected=(int)round(100*(float)($p->get('importoConsolidato') ?? $p->get('importo')));
        // With no other advances, an existing line retains the expected amount captured when it was invoiced.
        // A later credit note may have changed the commission's consolidated ledger balance.
        if (!$line->isNew() && $used===0 && is_numeric($line->getFetched('importoPrevistoRiga'))) {
            $expected=(int)round(100*(float)$line->getFetched('importoPrevistoRiga'));
        }


        $remaining=$expected-$used; $choice=$line->get('tipoLiquidazione') ?: 'Saldo';

        if (!in_array($choice,['Acconto','Saldo','Chiusura con differenza','Da verificare'],true)) throw new BadRequest('Tipo di liquidazione non valido.');

        if (($amount===0 && $choice!=='Da verificare') || ($expected>=0 && $amount<0) || ($expected<0 && $amount>0)) throw new BadRequest('Importo non valido per questa provvigione.');

        if ($choice==='Saldo' && $amount!==$remaining) { $choice='Da verificare'; $line->set('tipoLiquidazione',$choice); }

        if ($choice==='Acconto' && (abs($amount)>abs($remaining) || $amount*$remaining<=0)) throw new BadRequest('Acconto non valido: non puo superare il residuo e deve avere lo stesso segno.');

        if ($choice==='Chiusura con differenza' && $amount===$remaining) throw new BadRequest('Lâ€™importo coincide con il residuo: scegliere Saldo.');

        $line->set(['importoPrevistoRiga'=>$remaining/100, 'importoPrevistoRigaCurrency'=>'EUR', 'differenzaImporto'=>($choice==='Acconto'?0:($amount-$remaining)/100), 'differenzaImportoCurrency'=>'EUR']);

    }



    public function afterSave(Entity $i): void

    {

        $this->syncMonthlyRows($i);

        foreach ($this->rows($i) as $line) {

            if ($line->get('voceAutomatica')) {
                foreach ($this->em->getRDBRepository('Provvigione')->where(['contrattoId'=>$line->get('contrattoId'),'tipo'=>$line->get('tipo')])->find() as $monthlyCommission) {
                    CommissionConsolidation::apply($this->em,$monthlyCommission);
                    $this->em->saveEntity($monthlyCommission,['skipHooks'=>true,'skipFormula'=>true,'silent'=>true]);
                }
                continue;
            }

            $p = $this->invoiceCommission($line->get('provvigioneId'));

            if ($p) $this->refreshCommission($p);

        }

    }



    public function refreshCommission(Entity $p): void

    {
        if ($p->get('deleted')) return; // Historical invoice rows must not revive archived commissions.


        $lock = $this->em->getPDO()->prepare('SELECT id FROM provvigione WHERE id = ? FOR UPDATE');

        $lock->execute([$p->getId()]);

        $differenceReview = 0; $sum = 0; $paid = 0; $closed = false; $hasPaid = false; $invoices = []; $first = null;

        $baseline = $p->get('datiPrimaFatturazione') ? json_decode($p->get('datiPrimaFatturazione'), true, 512, JSON_THROW_ON_ERROR) : [

            'statoProvvigione' => $p->get('statoProvvigione'),

            'dataLiquidazioneEffettiva' => $p->get('dataLiquidazioneEffettiva'),

        ];

        if ($p->get('contrattoId')) {
            $quote=$this->em->getEntityById('Quote',$p->get('contrattoId'));
            if ($quote && ($quote->get('status')==='Invalido' || ($baseline['statoProvvigione'] ?? null)==='Inesigibile')) $baseline['statoProvvigione']=$this->statusSync->resolveStatoFromQuote($quote);
        }

        $query = $this->em->getPDO()->prepare('SELECT i.id, i.stato, i.numero_fattura, i.data_fattura, i.data_pagamento, l.importo_consolidato, l.tipo_liquidazione, l.differenza_importo, l.movimento, l.id AS line_id FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id AND i.deleted=0 WHERE l.provvigione_id=? AND l.deleted=0 ORDER BY l.created_at, l.id FOR UPDATE');

        $query->execute([$p->getId()]);

        $allocations=$query->fetchAll(\PDO::FETCH_ASSOC);
        $activeAllocations=array_filter($allocations,fn($row)=>$row['stato']!=='Annullato');
        foreach ($allocations as $i) {

            if ($i['stato'] === 'Annullato') continue;

            if ($i['tipo_liquidazione']==='Da verificare') {
                if (count($activeAllocations)===1 && $i['movimento']==='Addebito' && $p->get('importo')!==null) $differenceReview+=(int)round(100*(float)$i['importo_consolidato'])-(int)round(100*(float)$p->get('importo'));
                else $differenceReview+=(int)round(100*(float)$i['differenza_importo']);
            }

            $first ??= $i;

            if (!in_array($i['stato'], ['Definitivo', 'Pagato'], true)) continue;

            $sum += (int) round(100 * (float) $i['importo_consolidato']);

            $invoices[$i['id']] = $i;

            if ($i['stato'] === 'Pagato') {

                $paid += (int)round(100*(float)$i['importo_consolidato']);

                $hasPaid = true;

                if ($i['movimento'] === 'Addebito' && !in_array(($i['tipo_liquidazione'] ?: 'Saldo'), ['Acconto','Da verificare'], true)) {

                    $reverse = $this->em->getPDO()->prepare("SELECT COUNT(*) FROM invito_a_fatturare_item l JOIN invito_a_fatturare i ON i.id=l.invito_id WHERE l.riga_origine_id=? AND l.deleted=0 AND i.deleted=0 AND i.stato='Pagato'");

                    $reverse->execute([$i['line_id']]);

                    if (!(int)$reverse->fetchColumn()) $closed = true;

                }

            }

        }

        $single = count($invoices) === 1 ? reset($invoices) : null;

        $allPaid = count($invoices) > 0;

        $paymentDates = [];

        foreach ($invoices as $invoice) {

            if ($invoice['stato'] !== 'Pagato') $allPaid = false;

            if ($invoice['data_pagamento']) $paymentDates[] = $invoice['data_pagamento'];

        }

        $expected = (int)round(100*(float)$p->get('importo'));

        $settlement = CommissionSettlement::calculate($expected, $paid, $hasPaid, $closed);

        $p->set([

            'differenzaDaVerificare'=>$differenceReview/100, 'differenzaDaVerificareCurrency'=>'EUR',

            'importoPagatoInviti' => $paid/100, 'importoPagatoInvitiCurrency'=>'EUR',

            'residuoProvvigionale' => $settlement['residual']/100, 'residuoProvvigionaleCurrency'=>'EUR',

            'differenzaChiusura' => $settlement['difference']/100, 'differenzaChiusuraCurrency'=>'EUR',

            'datiPrimaFatturazione' => json_encode($baseline, JSON_THROW_ON_ERROR),

            'invitoAFatturareId' => ($single ?: $first)['id'] ?? null,

            'importoConsolidatoInviti' => $sum / 100,

            'importoConsolidatoInvitiCurrency' => 'EUR',

            'consolidata' => $sum !== 0,

            'numeroFattura' => $single['numero_fattura'] ?? null,

            'dataFattura' => $single['data_fattura'] ?? null,

            'dataPagamento' => $single['data_pagamento'] ?? null,

            'statoProvvigione' => $settlement['state'] ?? (count($invoices) ? 'In pagamento' : $baseline['statoProvvigione']),

            'dataLiquidazioneEffettiva' => count($invoices) ? ($settlement['closed'] && $paymentDates ? max($paymentDates) : null) : $baseline['dataLiquidazioneEffettiva'],

        ]);

        CommissionConsolidation::apply($this->em,$p);
        $this->em->saveEntity($p, ['skipHooks' => true, 'skipFormula' => true, 'silent' => true]);

        if ($p->get('contrattoId')) {

            $q = $this->em->getEntityById('Quote', $p->get('contrattoId'));

            if ($q) {

                $total = 0; $paidTotal = 0; $residual = 0; $difference = 0; $count = 0; $closedCount = 0; $anyPaid = false; $anyDifference = false;

                foreach ($this->em->getRDBRepository('Provvigione')->where(['contrattoId'=>$q->getId()])->find() as $item) {

                    if ($item->get('statoProvvigione') === 'Inesigibile' || $this->ariel->monthlyOnly($item)) continue;

                    $count++;

                    $total += (int)round(100*(float)$item->get('importoConsolidatoInviti'));

                    $paidTotal += (int)round(100*(float)$item->get('importoPagatoInviti'));

                    $residual += (int)round(100*(float)($item->get('residuoProvvigionale') ?? $item->get('importoConsolidato') ?? $item->get('importo')));

                    $difference += (int)round(100*(float)$item->get('differenzaChiusura'));

                    $state=$item->get('statoProvvigione');

                    if (in_array($state,['Pagato','Chiuso con differenza'],true)) $closedCount++;

                    if (in_array($state,['Pagato','Chiuso con differenza','Acconto provvigionale'],true)) $anyPaid=true;

                    if ($state==='Chiuso con differenza') $anyDifference=true;

                }

                $q->set(['totaleConsolidatoInviti'=>$total/100, 'totaleConsolidatoInvitiCurrency'=>'EUR',

                    'importoPagatoInviti'=>$paidTotal/100,'importoPagatoInvitiCurrency'=>'EUR',

                    'residuoProvvigionale'=>$residual/100,'residuoProvvigionaleCurrency'=>'EUR',

                    'differenzaChiusura'=>$difference/100,'differenzaChiusuraCurrency'=>'EUR',

                    'esitoLiquidazione'=>($count && $closedCount===$count) ? ($anyDifference?'Chiuso con differenza':'Pagato') : ($anyPaid?'Acconto provvigionale':'Da liquidare')]);

                $this->em->saveEntity($q, ['skipHooks' => true, 'skipFormula' => true, 'silent' => true]);

            }

        }

    }





    public function addSelected(Entity $i, array $ids): int

    {

        if (!$this->acl->checkEntity($i, 'edit') || !$this->acl->checkScope('InvitoAFatturareItem', 'create')) throw new Forbidden();

        if (!$ids || count($ids) > 200) throw new BadRequest('Selezionare da 1 a 200 provvigioni.');

        foreach ($ids as $id) if (!is_string($id) || !preg_match('/^[a-zA-Z0-9]+$/', $id)) throw new BadRequest('Selezione non valida.');

        $ids = array_values(array_unique($ids)); sort($ids);

        $tm = $this->em->getTransactionManager(); $tm->start();

        try {

            $this->lock($i);

            $i = $this->em->getEntityById('InvitoAFatturare', $i->getId());

            if (!$i || !$this->acl->checkEntity($i, 'edit')) throw new Forbidden();

            if (!$this->isDraft($i)) throw new BadRequest('Salvare prima lâ€™invito in Bozza o In verifica per aggiungere provvigioni.');

            $count = 0;

            foreach ($ids as $id) {

                $lock = $this->em->getPDO()->prepare('SELECT id FROM provvigione WHERE id=? AND deleted=0 FOR UPDATE'); $lock->execute([$id]);

                $p = $this->em->getEntityById('Provvigione', $id);

                if (!$p || !$this->acl->checkEntity($p, 'read')) throw new Forbidden();

                $this->validateMember($i, $p);

                if ($this->em->getRDBRepository('InvitoAFatturareItem')->where(['invitoId'=>$i->getId(), 'provvigioneId'=>$id, 'movimento'=>'Addebito'])->findOne()) continue;

                $line = $this->em->getNewEntity('InvitoAFatturareItem');

                $line->set(['invitoId'=>$i->getId(), 'provvigioneId'=>$id, 'movimento'=>'Addebito']);

                $this->em->saveEntity($line); $count++;

            }

            $tm->commit(); return $count;

        } catch (\Throwable $e) { $tm->rollback(); throw $e; }

    }



    private function isArtel(Entity $i): bool

    {

        // ARTEL is billed by supplier AMG in this CRM.

        return $i->get('fornitorePartnerId') === '69db55750fce3b776';

    }



    public function matchesArtelPeriod(Entity $i, ?string $due): bool

    {

        if (!$due || substr($due, 0, 7) !== substr((string) $i->get('meseCompetenza'), 0, 7)) return false;

        $first = (int) substr($due, 8, 2) <= 15;

        return $i->get('periodoInvito') === '16-fine mese' ? !$first : $first;

    }



    public function populate(Entity $i): int

    {

        if (!$this->acl->checkEntity($i, 'edit')) throw new Forbidden();

        $pdo = $this->em->getPDO();

        $tm = $this->em->getTransactionManager();

        $tm->start();

        try {

            $this->lock($i);

            $i = $this->em->getEntityById('InvitoAFatturare', $i->getId());

            if (!$this->isDraft($i)) throw new BadRequest('Ãˆ possibile raccogliere provvigioni solo in Bozza o In verifica.');

            $count = 0;

            $reserved=$this->reservedCommissionIds((string)$i->get('fornitorePartnerId'));

            foreach ($this->em->getRDBRepository('Provvigione')->where([

                'fornitorePartnerId' => $i->get('fornitorePartnerId'), 'invitoAFatturareId' => null,

                'statoProvvigione' => $this->ariel->isAriel($i) ? ['Forecast','In pagamento','Pagato'] : ['In pagamento','Pagato'],

            ])->find() as $p) {

                if (in_array($p->getId(),$reserved,true) || !$this->acl->checkEntity($p, 'edit') || !$this->eligible($p)) continue;

                $due = $this->ariel->isAriel($i) ? $this->ariel->memberMonth($p) : $this->dueDate($p);

                if (!$due || substr($due, 0, 7) !== substr($i->get('meseCompetenza'), 0, 7)) continue;

                if ($this->isArtel($i) && !$this->matchesArtelPeriod($i, $due)) continue;

                $stmt = $pdo->prepare('SELECT invito_a_fatturare_id FROM provvigione WHERE id = ? FOR UPDATE');

                $stmt->execute([$p->getId()]);

                if ($stmt->fetchColumn()) continue;

                $this->validateMember($i, $p, true);

                $line = $this->em->getNewEntity('InvitoAFatturareItem');

                $line->set(['invitoId' => $i->getId(), 'provvigioneId' => $p->getId(), 'movimento' => 'Addebito']);

                $this->em->saveEntity($line);

                $count++;

            }

            $this->em->saveEntity($i);

            $tm->commit();

            return $count;

        } catch (\Throwable $e) {

            $tm->rollback();

            throw $e;

        }

    }

}

