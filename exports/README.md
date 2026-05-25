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
