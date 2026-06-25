# Struttura `backup_dev/` — guida obbligatoria per i fix

Sul server: `~/public_html/crm/mec-group/backup_dev/`

**Non confondere** con le cartelle omonime in produzione:

| In produzione (live) | In `backup_dev` (solo copie di sicurezza) |
|----------------------|-------------------------------------------|
| `client/custom/` | `{Entità}/client/` oppure `backup_dev/client/` |
| `custom/Espo/Custom/` | `{Entità}/hooks/`, `layouts/`, `metadata/` |

---

## Albero standard (per entità CRM)

Ogni entità (Appuntamento, Opportunity, Quote, …) può avere:

```
backup_dev/
├── _scripts/              # script migrazione / ordinamento
├── _archives/             # .tar.gz, dump SQL
├── _misc/                 # log, file non classificati
├── client/                # ← vedi sezione «Cartella client in root» sotto
│   ├── css/               # backup CSS globali (es. appuntamento-list.css)
│   └── metadata/          # backup app/client.json, clientDefs globali
├── Appuntamento/
│   ├── hooks/             # → live: custom/Espo/Custom/Hooks/Appuntamento/
│   ├── layouts/           # → live: custom/Espo/Custom/Resources/layouts/Appuntamento/
│   ├── metadata/
│   │   ├── entityDefs/
│   │   ├── clientDefs/
│   │   └── logicDefs/
│   └── client/            # → live: client/custom/src/views/appuntamento/...
│       ├── detail/
│       ├── handlers/
│       └── runtime/
├── Opportunity/
│   └── client/handlers/   # es. create-contratto-handler
├── Quote/
└── …
```

---

## Cartella `backup_dev/client/` (in root)

**Non è** la cartella `mec-group/client/` dell’installazione Espo.

Serve per backup **trasversali** al front-end, creati dallo script di ordinamento legacy o a mano:

| Sottocartella | Contenuto tipico | Ripristino live |
|---------------|------------------|-----------------|
| `client/css/` | Fogli di stile custom globali | `client/custom/css/` |
| `client/metadata/` | `app/client.json`, scriptList | `custom/Espo/Custom/Resources/metadata/app/client.json` |

I file JS legati a **una entità** vanno sotto `{Entità}/client/...`, non in `backup_dev/client/` (salvo migrazione non ancora fatta).

Se in root vedi solo `client/` vuota o con pochi file vecchi: è normale dopo `organizza-file-legacy-root.sh`; il lavoro utile è sotto `Opportunity/client/`, `Appuntamento/`, ecc.

---

## Dove salvare un nuovo backup (regola)

Prima di modificare in produzione, usare **`tools/backup-dev-save.sh`** con l’entità e il tipo giusto:

| Tipo modifica | AGGIORNAMENTO | Esempio comando |
|---------------|---------------|-----------------|
| Hook PHP | `hooks` | `backup-dev-save.sh Appuntamento mio-fix hooks GlobalLogic.php` |
| Layout JSON | `layouts` | `backup-dev-save.sh Quote prezzi layouts detail.json` |
| entityDefs / clientDefs | `entityDefs` o path file | `backup-dev-save.sh Quote campi entityDefs Quote.json` |
| Vista JS detail | `client-detail` | `backup-dev-save.sh Opportunity btn-contratto client-detail edit.js` |
| Handler JS | `client-handlers` | `backup-dev-save.sh Opportunity handler client-handlers create-contratto.js` |

Layout massivi: anche `custom/backup-layouts/YYYYMMDD/Quote/` (snapshot intera cartella).

---

## Rollback (da backup_dev verso live)

1. Trovare il file con data nel nome in `backup_dev/{Entità}/...`
2. Copiare sulla path **live** indicata nella tabella sopra
3. `php command.php rebuild` e `rm -rf data/cache/*`

Esempio Opportunity handler:

```bash
cp -a backup_dev/Opportunity/client/handlers/20260529-120000_fix_client-handlers_create-contratto.js \
  client/custom/src/handlers/opportunity/create-contratto.js
```

---

## Script di ordinamento (server)

Se in root `backup_dev/` compaiono ancora file sparsi:

```bash
bash backup_dev/_scripts/organizza-file-legacy-root.sh
```

Vedi anche [`README.md`](README.md) e [`../REGOLE-PRODUZIONE/04-STRUTTURA-BACKUP-DEV.md`](../REGOLE-PRODUZIONE/04-STRUTTURA-BACKUP-DEV.md).
