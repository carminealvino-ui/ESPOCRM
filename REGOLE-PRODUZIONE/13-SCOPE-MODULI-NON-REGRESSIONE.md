# Scope moduli — non rompere ciò che funziona

Ogni intervento deve restare **nel proprio ambito**. Modifiche fuori scope sono la causa principale delle regressioni (KPI a zero, finanziamento sparito, provvigioni sbagliate).

## Mappa moduli (non mescolare)

| Modulo | File tipici | Non toccare quando lavori su… |
|--------|-------------|-------------------------------|
| **KPI dashlet** | `Services/CrmKpi/`, `Tools/CrmKpi/`, `client/.../dashlets/crm-kpi.*` | Contratto layout, provvigioni, CreateContratto |
| **Contratto / Quote** | `layouts/Quote/`, `entityDefs/Quote.json`, `formula/Quote.json`, hook Quote | KPI, dashlet, Opportunity layout |
| **Provvigioni** | `ProvvigioneManager`, hook Provvigione, subpanel provvigioni | Layout finanziamento, KPI, campi unrelated |
| **Finanziamento** | campi `finanziamento`, `importoCaparra`, `statoFinanziamento`, pannello Finanziamento | Provvigioni, KPI service |
| **Opportunità** | `layouts/Opportunity/`, hook Opportunity | Quote detail (salvo copia esplicita in CreateContratto) |

## Regole operative

1. **Un task = un branch = un PR** con nome descrittivo (`cursor/crm-kpi-…`, `cursor/fix-quote-…`).
2. **Prima di modificare un layout JSON**, confrontare con `git show main:path` o ultimo commit noto funzionante; non sostituire l’intero file se serve solo rimuovere un pannello.
3. **Mai rimuovere campi da `entityDefs`** se restano nel layout o sono usati da KPI / formula / CreateContratto.
4. **Deploy KPI**: usare solo `tools/deploy-kpi-completo.sh` (branch `cursor/crm-kpi-recessi-finanziamento-9999` o successivo). Non copiare a mano singoli file da branch provvigioni.
5. **Deploy Contratto / provvigioni**: branch dedicato; non includere `CrmKpiService.php` nello stesso deploy salvo coordinamento esplicito.
6. **Verifica post-deploy** obbligatoria per il modulo toccato:
   - KPI: `php tools/verify-crm-kpi-deploy.php` + screenshot tile Recessi / Finanziamenti rifiutati
   - Contratto: aprire record con finanziamento e con recesso
   - Provvigioni: Ricalcola provvigioni + totale in Articoli

## KPI — recessi e finanziamenti KO

Il dashlet **v2 senza patch rese-periodo** conta solo `Quote.statoContratto` e `Quote.statoFinanziamento`. In produzione i dati sono spesso sull’**Opportunità** collegata.

Versione corretta (`CrmKpiService` con `isQuoteRecesso` / `filterQuotesForTile`):
- **Recesso**: `Quote.statoContratto = Recesso` **oppure** `Opportunity.statoContratto = Recesso`
- **Finanziamento KO**: `statoFinanziamento = Respinto` su contratto o opportunità (esclusi i recessi)

Se in dashboard vedi Recessi = 0 e Finanziamenti rifiutati = 0 con contratti noti in recesso/respinto → deploy KPI non aggiornato.

## Checklist prima di chiudere un PR

- [ ] Diff limitato al modulo del task (no file Quote se task KPI e viceversa)
- [ ] Layout: solo righe/pannelli richiesti, non rewrite completo
- [ ] `entityDefs` / `logicDefs` allineati ai campi in layout
- [ ] Screenshot o verify script per la funzione modificata
- [ ] Nessun pannello/campo rimosso senza richiesta esplicita
