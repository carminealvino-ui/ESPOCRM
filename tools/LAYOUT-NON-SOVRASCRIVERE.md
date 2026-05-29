# Layout Contratto — non sovrascrivere con deploy

Gli script `deploy-contratto-prezzi-curl.sh` e `deploy-emergency-restore-crm-ui.sh` **non** scaricano più `layouts/Quote/*.json` da GitHub.

Se esegui un deploy vecchio che include `detail.json`, il layout personalizzato (Layout Manager, pannello Commerciale, ecc.) viene **sostituito** con la versione nel repository.

## Backup prima del deploy

```bash
bash tools/backup-quote-layouts.sh
```

Copia in `custom/backup-layouts/YYYYMMDD-HHMMSS/Quote/`.

## Recupero layout perso

1. Cerca l’ultimo backup: `ls -lt custom/backup-layouts/*/Quote/`
2. Ripristina: `cp -a custom/backup-layouts/ULTIMO/Quote/* custom/Espo/Custom/Resources/layouts/Quote/`
3. `php command.php rebuild` e svuota cache

Oppure rifai le modifiche in **Admin → Layout Manager → Quote**.
