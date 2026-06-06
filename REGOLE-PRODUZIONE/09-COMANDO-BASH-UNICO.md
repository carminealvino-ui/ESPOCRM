# Regola 9 — Comando bash unico («tutto in un colpo»)

## Obbligo

Ogni istruzione operativa sul server deve includere **sempre** un blocco bash **unico e copiabile** che esegue l’intero flusso del passo (o del fix), senza obbligare l’operatore a incollare più comandi separati.

## Formato standard

```bash
cd ~/public_html/crm/mec-group && \
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/BRANCH/tools/NOME-SCRIPT.sh?t=$(date +%s)" | bash
```

Oppure, se gli script sono già sul server:

```bash
cd ~/public_html/crm/mec-group && bash tools/NOME-SCRIPT.sh
```

## Regole del blocco

1. **Un solo blocco** per messaggio (incolla → Invio → screenshot).
2. **Niente `...`** nei comandi: path e parametri completi.
3. **`cd` esplicito** all’inizio (`~/public_html/crm/mec-group`).
4. Se serve backup prima dello script, lo script deve farlo **internamente** (non chiedere backup manuale separato salvo eccezioni documentate).
5. Dopo il blocco, indicare **verifica attesa** (terminale + schermata CRM).

## Convivenza con Regola 1

- **Regola 1**: l’operatore esegue **un passo** per messaggio e manda screenshot prima del successivo.
- **Regola 9**: quel passo va consegnato come **un comando unico**, non come lista di 5 comandi da eseguire a mano.

Esempio corretto:

> **Passo 2 — Deploy listino dual IVA**  
> Esegui tutto in un colpo:
> ```bash
> cd ~/public_html/crm/mec-group && curl -fsSL "…/applica-listino-dual-iva-produzione.sh?t=$(date +%s)" | bash
> ```
> Screenshot terminale + listino ARIEL con Imposta - Codice IVA10.

## Script «tutto in un colpo» già disponibili

| Fix | Script |
|-----|--------|
| Listino dual IVA + taxCode + backfill | `tools/applica-listino-dual-iva-produzione.sh` |
| **Fix prezzi listino (inline, no cache GitHub)** | `tools/applica-fix-prezzi-listino-produzione.sh` |
| Bootstrap tools sul server | `tools/bootstrap-server-tools.sh` |
| Deploy duplica appuntamento | `tools/deploy-fix-appuntamento-duplica.sh` |

Quando si aggiunge un nuovo fix in produzione, creare o aggiornare lo script wrapper corrispondente.
