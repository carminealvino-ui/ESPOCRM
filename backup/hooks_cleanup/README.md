# Backup produzione — `backup/hooks_cleanup`

Copie **prima di modifiche mirate** (hook, layout, metadata, client).  
Export ZIP interi di `custom/` → `exports/custom/` o `custom/backup-layouts/`.

---

## 1. Struttura cartelle (dove va ogni file)

```
backup/hooks_cleanup/
│
├── _scripts/                 Script (migrazione, deploy)
├── _archives/                Archivi completi .tar.gz
│
├── Appuntamento/             ← un’entità = una cartella
│   ├── hooks/                PHP hook (es. GlobalLogic.php)
│   ├── layouts/              JSON layout (detail, list, …)
│   ├── metadata/
│   │   ├── entityDefs/       Campi entità
│   │   ├── logicDefs/        Regole dinamiche form
│   │   └── clientDefs/       Viste client metadata
│   └── client/               JS (se non in client/custom root)
│       ├── detail/
│       ├── handlers/
│       └── runtime/
│
├── Opportunity/              Stessa logica
├── Prospect/
├── Lead/
├── Quote/
├── Product/
├── FornitorePartner/
└── ProductBrand/
```

**Regola:** il path è sempre  
`{Entità}/{AGGIORNAMENTO}/`  
dove **AGGIORNAMENTO** è il *tipo* di file (non la data).

---

## 2. Convenzione nome file (obbligatoria dai fix nuovi)

```
{DATA}_{FIX}_{AGGIORNAMENTO}_{OBIETTIVO}.{ext}
```

| Parte | Significato | Esempio |
|--------|-------------|---------|
| **DATA** | Quando salvi `YYYYMMDD` o `YYYYMMDD-HHMMSS` | `20260529-143052` |
| **FIX** | Identificativo intervento / branch / ticket | `duplica-appuntamento`, `prezzi-quote`, `layout-quote` |
| **AGGIORNAMENTO** | Tipo file (cartella sotto l’entità) | `hooks`, `layouts`, `entityDefs`, `logicDefs`, `clientDefs`, `client-detail`, `client-handlers` |
| **OBIETTIVO** | Cosa stai salvando (nome breve) | `GlobalLogic`, `detail`, `Appuntamento`, `create-contratto-handler` |

### Esempi completi (path + nome)

| File backup | Significato |
|-------------|-------------|
| `Appuntamento/hooks/20260529-143052_duplica-appuntamento_hooks_GlobalLogic.php` | Hook prima del fix Duplica |
| `Appuntamento/metadata/entityDefs/20260526-201400_pre-migrazione_entityDefs_Appuntamento.json` | entityDefs del 26/05 |
| `Opportunity/client/handlers/20260525_create-contratto_client-handlers_handler-v1.0.4.js` | Handler crea contratto |
| `Quote/layouts/20260529_layout-quote_layouts_detail.json` | Layout detail Quote |

Usa solo **minuscole, numeri e trattini** in FIX e OBIETTIVO (niente spazi).

---

## 3. Come salvare un backup (produzione)

```bash
cd ~/public_html/crm/mec-group

# Sintassi:
#   bash tools/backup-hooks-cleanup-save.sh <Entità> <FIX> <AGGIORNAMENTO> <file-sorgente>

bash tools/backup-hooks-cleanup-save.sh Appuntamento duplica-appuntamento hooks GlobalLogic.php

bash tools/backup-hooks-cleanup-save.sh Quote layout-quote layouts detail.json

bash tools/backup-hooks-cleanup-save.sh Opportunity create-contratto client-handlers create-contratto.js
```

La **DATA** viene aggiunta automaticamente (oggi + ora).

---

## 4. Ripristino manuale

1. Trova il file in `backup/hooks_cleanup/{Entità}/{AGGIORNAMENTO}/`
2. Copialo sul path live (es. `custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php`)
3. `php command.php rebuild` e `rm -rf data/cache/*`

---

## 5. File vecchi (legacy)

I file già presenti con nomi tipo `appuntamento-globallogic-1.7.0-...` o `backup-appuntamento-...` restano validi finché non li rinomini.  
Dopo `migra-struttura-server.sh` stanno nella cartella giusta; al prossimo intervento salva con la **nuova convenzione**.

---

## 6. Script

| Script | Uso |
|--------|-----|
| `tools/backup-hooks-cleanup-save.sh` | Nuovo backup con DATA_FIX_AGGIORNAMENTO_OBIETTIVO |
| `_scripts/migra-struttura-server.sh` | Sposta file piatti in sottocartelle entità |
| `tools/rollback-produzione.sh` | Rollback da `custom/backup-layouts/` (deploy) |
| `_scripts/deploy-produzione-sync-branch.sh` | Tar completo in `_archives/` |
