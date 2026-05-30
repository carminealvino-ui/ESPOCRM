# Deploy Account subpanel (Appuntamenti + Contratti)

Il repo GitHub è **privato**: `curl raw.githubusercontent.com` dal server restituisce **404**.

## Metodo consigliato (SFTP / copia locale)

1. Dal PC (repo clonato), carica sul server la cartella:
   `tools/deploy-bundles/account-subpanel/`
   in `~/public_html/crm/mec-group/tools/deploy-bundles/account-subpanel/`

2. Sul server:
```bash
cd ~/public_html/crm/mec-group
bash tools/deploy-bundles/account-subpanel/applica-locale.sh
php tools/backfill-appuntamento-account-link.php --account-id=ID_CLIENTE
```

## Alternativa: git sul server (se hai clone con credenziali)

```bash
cd /percorso/clone/ESPOCRM
git fetch origin cursor/account-subpanel-appuntamenti-contratti-9999
git checkout cursor/account-subpanel-appuntamenti-contratti-9999
cp -a custom/Espo/Custom/Resources/layouts/Account/bottomPanelsDetail.json \
  ~/public_html/crm/mec-group/custom/Espo/Custom/Resources/layouts/Account/
# ... altri file come in applica-locale.sh
```

## Solo layout (Contratti visibili subito, senza hook)

Modifica manuale `custom/Espo/Custom/Resources/layouts/Account/bottomPanelsDetail.json`
aggiungendo `"quotes": { "index": 2 }` e `"appuntamenti": { "index": 0 }`, poi:
```bash
php command.php rebuild && rm -rf data/cache/*
```
