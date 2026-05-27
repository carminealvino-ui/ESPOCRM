# Export sviluppo

## `exports/custom/`

Destinazione degli **export completi** della cartella `custom/` (ZIP + manifest JSON).

Generazione:

```bash
php tools/export-custom-for-github.php
```

Upload opzionale su GitHub (`exports/custom/` nel repository):

```bash
GITHUB_TOKEN="..." GITHUB_REPOSITORY="carminealvino-ui/ESPOCRM" GITHUB_BRANCH="main" \
php tools/export-custom-for-github.php --upload-github
```

## `exports/sync/` — allineamento produzione ↔ GitHub

Tool: `tools/sync-custom-prod-repo.php`

```bash
# Sul server CRM (confronto con branch GitHub)
php tools/sync-custom-prod-repo.php status

# Esporta solo file modificati in produzione rispetto al repo
php tools/sync-custom-prod-repo.php export-delta

# Sul PC con clone Git (applica delta e poi commit)
php tools/sync-custom-prod-repo.php apply-delta exports/sync/delta-YYYYMMDD-HHMMSS
```

Config: `tools/sync-custom-prod-repo.config.json`

## Migrazione export vecchi

Se hai ancora file `custom-export-*.zip` / `.json` in `tools/custom-exports/`:

```bash
bash tools/migrate-custom-exports-folder.sh
```
