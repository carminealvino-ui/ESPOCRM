# Cartella `backup/` — panoramica

| Percorso | Contenuto |
|----------|-----------|
| **`hooks_cleanup/`** | Backup **mirati** per entità (hook, layout, metadata, client) — struttura principale |
| `create-contratto-2026-05-25/` | Snapshot storico CreateContratto / formula Quote |
| `provvigioni-2026-05-26/` | PHP provvigioni (fase branch) |
| `pre-upgrade-9.3.7/` | Pre-aggiornamento Espo |
| `custom/backup-layouts/` (sul **server**, non sempre in git) | Layout Quote timestamp |

## hooks_cleanup — struttura rapida

Vedi [`hooks_cleanup/README.md`](hooks_cleanup/README.md).

```
hooks_cleanup/
  Appuntamento/hooks|layouts|metadata|client/
  Opportunity/...
  Prospect/ Lead/ Quote/ ...
  _scripts/   _archives/
```

## Sul server (prima volta dopo pull)

```bash
cd ~/public_html/crm/mec-group
git pull   # quando disponibile
bash backup/hooks_cleanup/_scripts/migra-struttura-server.sh
```

Sposta i file `backup-appuntamento-*.php` ancora in root verso le sottocartelle.

## Salvare un nuovo backup prima di una modifica

```bash
bash tools/backup-hooks-cleanup-save.sh Appuntamento hooks GlobalLogic.php
```
