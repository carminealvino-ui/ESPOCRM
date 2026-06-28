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
Procedura completa: [`REGOLE-PRODUZIONE/REGOLE.md`](../REGOLE-PRODUZIONE/REGOLE.md) (sezioni 7–8)

### Ordine obbligatorio (prod → repo)

1. **`export-delta`** sul server (sempre per primo, ogni sessione).
2. **`apply-delta`** sul clone Git + **`commit` + `push`**.

**cPanel (server):** sezione 7.B in [`REGOLE-PRODUZIONE/REGOLE.md`](../REGOLE-PRODUZIONE/REGOLE.md) — token in `exports/sync/token.txt` (resta sul server).

**PC:** scarica `delta-….zip` e `apply-delta` sul clone locale.

`status` è opzionale (solo diagnosi); non sostituisce l’export.

```bash
# Sul server CRM
php tools/sync-custom-prod-repo.php export-delta --branch=main

# Sul PC con clone Git
php tools/sync-custom-prod-repo.php apply-delta /percorso/delta-YYYYMMDD-HHMMSS
```

Config: `tools/sync-custom-prod-repo.config.json`

## Migrazione export vecchi

Se hai ancora file `custom-export-*.zip` / `.json` in `tools/custom-exports/`:

```bash
bash tools/migrate-custom-exports-folder.sh
```
