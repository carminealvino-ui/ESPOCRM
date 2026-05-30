# Backup `backup/hooks_cleanup` — guida

## Struttura (dopo riorganizzazione)

```
backup/hooks_cleanup/
├── Appuntamento/
│   ├── hooks/              ← GlobalLogic.php, ...
│   ├── layouts/            ← detail.json, ...
│   ├── metadata/
│   │   ├── entityDefs/
│   │   ├── logicDefs/
│   │   └── clientDefs/
│   └── client/
├── Opportunity/
│   ├── hooks/
│   ├── layouts/
│   ├── metadata/...
│   └── client/detail|handlers|runtime/
├── Prospect/ Lead/ Quote/ ...
├── _scripts/
└── _archives/              ← tar.gz deploy completi
```

## Sul server — allineare cartella piatta

Se i file sono ancora in root (`backup-appuntamento-*.php`):

```bash
cd ~/public_html/crm/mec-group
bash backup/hooks_cleanup/_scripts/migra-struttura-server.sh
```

## Salvare prima di ogni modifica

```bash
bash tools/backup-hooks-cleanup-save.sh Appuntamento hooks GlobalLogic.php
bash tools/backup-hooks-cleanup-save.sh Quote layouts detail.json
```

## Appuntamento — file da ripristinare (logica business)

| Sottocartella | File tipico |
|---------------|-------------|
| `Appuntamento/hooks/` | `*globallogic*2042*` o `*1.7.0*` (il più recente) |
| `Appuntamento/metadata/logicDefs/` | `*logicdefs*` |
| `Appuntamento/metadata/entityDefs/` | `*entitydefs*` o `Appuntamento.json` |

**Crea/Duplica** non si risolve solo con questi: serve anche `clientDefs` da script emergenza (vedi sotto).

## Ripristino Appuntamento + Crea funzionante

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/restore-appuntamento-da-backup-hooks-cleanup.sh?t=$(date +%s)" | bash
```

## Due cartelle backup sul server

| Cartella | Uso |
|----------|-----|
| `custom/backup-layouts/` | Snapshot prima deploy (Quote layout, clientDefs, …) |
| `backup/hooks_cleanup/` | Storico fix per **entità** |
