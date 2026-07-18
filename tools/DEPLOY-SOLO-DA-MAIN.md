# Deploy — solo da `main`

Dopo la pulizia GitHub (2026-07-18):

- Produzione e GitHub sono allineati su **`main`**
- Tutti i branch `cursor/*` sono stati **eliminati**
- Qualsiasi `curl` verso `.../cursor/...` è **vietato** e fallisce (404)

## Regole

1. Scaricare script e file **solo** da:
   `https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/...`
2. Prima di deploy: backup (`tools/backup-dev-save.sh` / `backup-quote-layouts.sh`)
3. Un tema per deploy (mai layout + hook + migrazione insieme)
4. Nessuna migrazione bulk automatica senza dry-run + OK esplicito

## Verifica allineamento

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php status --branch=main
```

Atteso: Diversi/Solo prod/Solo repo ≈ 0.
