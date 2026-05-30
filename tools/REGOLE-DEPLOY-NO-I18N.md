# Deploy: mai toccare `i18n/it_IT`

## Regola

Tutti gli script in `tools/deploy-*.sh` e `tools/sync-custom-prod-repo.*` **non devono** leggere, copiare o sovrascrivere:

`custom/Espo/Custom/Resources/i18n/it_IT/**`

Le etichette italiane si aggiornano **solo** con commit dedicati nel repo e copia manuale sul server (o merge controllato).

## Causa tipica "Date End" / "Duration" in inglese

Manca la chiave in `it_IT/Appuntamento.json` → fallback `en_US`.  
Non dipende da `entityDefs` o layout.

## Applicare solo etichette Appuntamento (manuale, una tantum)

```bash
cd ~/public_html/crm/mec-group
cp -a custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json \
      custom/backup-layouts/it_IT-Appuntamento-$(date +%Y%m%d-%H%M%S).json.bak

curl -fsSL -o custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json \
  'https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json'

php command.php clearCache
rm -rf data/cache/*
```

Poi Ctrl+F5.

Documentazione completa: [`custom/Espo/Custom/Resources/i18n/it_IT/README-ETICHETTE.md`](../custom/Espo/Custom/Resources/i18n/it_IT/README-ETICHETTE.md)
