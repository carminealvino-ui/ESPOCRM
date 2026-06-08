# Deprecato

Gli export completi di `custom/` sono stati spostati in **`exports/custom/`**.

Usare:

```bash
php tools/export-custom-for-github.php
```

I backup delle singole fix restano in **`backup_dev/`**.

Per spostare export già generati nella vecchia cartella:

```bash
bash tools/migrate-custom-exports-folder.sh
```
