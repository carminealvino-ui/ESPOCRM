# Produzione — Appuntamento non si crea (emergenza)

## Cosa è successo

Il CRM cerca file JavaScript del modulo **CRM Meeting** (`crm:views/meeting/record/edit.js`) che **non esistono** sul server (`client/modules/crm` assente). Dopo pulizia cache o deploy parziali, **Crea** e **Duplica** aprono pagina bianca.

Non è il database: sono i **metadata client** che puntano a viste inesistenti.

Apertura scheda: il campo **Promemoria** (`reminders`, tipo `jsonArray`) nel layout detail richiede `views/fields/json-array.js`, che **non esiste** in Espo open source → 404 e pagina bloccata.

## Fix (con backup automatico)

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-appuntamento-produzione.sh?t=$(date +%s)" | bash
```

Verifica (solo lettura):

```bash
bash tools/verifica-appuntamento-produzione.sh
```

1. Salva copia in `custom/backup-layouts/YYYYMMDD-HHMMSS/`
2. Imposta **viste standard Espo** (`views/record/edit`) per Crea/Duplica
3. `rebuild` + cache pulita

Poi **Ctrl+F5** nel browser.

## Rollback

```bash
cd ~/public_html/crm/mec-group
bash tools/rollback-produzione.sh
# oppure con data precisa:
bash tools/rollback-produzione.sh 20260529-143000
```

Ultimo backup salvato anche in: `custom/backup-layouts/LAST_APPUNTAMENTO_BACKUP.txt`

## Solo backup (senza deploy)

```bash
bash tools/backup-produzione.sh manuale
```

## Dopo il fix

- **Crea** dall’elenco e **Duplica** devono aprire il form standard.
- La scheda dettaglio custom (es. «Crea Opportunità» da Svolto) resta se `detail.js` custom è presente.
- Durata default 90 min dal calendario: se il calendario dà errore, segnalare (usa ancora `crm:views/calendar`).
