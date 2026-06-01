# Deploy: non toccare etichette né layout

## Cosa non deve mai essere sovrascritto dai deploy

| Percorso | Contenuto |
|----------|-----------|
| `custom/Espo/Custom/Resources/i18n/it_IT/**` | Etichette italiane |
| `custom/Espo/Custom/Resources/layouts/**` | Elenco, scheda, filtri, mass update |

Aggiornamenti **solo** con commit dedicati e copia manuale (o export da Layout Manager → repo).

## Deploy tecnici (consentito)

- `metadata/` (clientDefs, entityDefs, hooks, …) — **senza** sovrascrivere layout
- `client/custom/src/…`
- `Hooks/…`

Script: `tools/deploy-appuntamento-produzione.sh` (non include `layouts/` né `i18n/`).

## Elenco Appuntamenti — colonne

Tutti i campi operativi restano in `list.json` (nessuna rimozione da script/deploy). Eventuali aggiustamenti = solo larghezze % e ordine, con commit dedicato.

## Applicare solo elenco Appuntamenti (manuale)

```bash
cd ~/public_html/crm/mec-group
cp -a custom/Espo/Custom/Resources/layouts/Appuntamento/list.json \
  custom/backup-layouts/list-Appuntamento-$(date +%Y%m%d-%H%M%S).json.bak

curl -fsSL -o custom/Espo/Custom/Resources/layouts/Appuntamento/list.json \
  'https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/custom/Espo/Custom/Resources/layouts/Appuntamento/list.json'

php command.php clearCache && rm -rf data/cache/*
```

Vedi anche: [`REGOLE-DEPLOY-NO-I18N.md`](REGOLE-DEPLOY-NO-I18N.md), [`../custom/Espo/Custom/Resources/layouts/README-LAYOUTS.md`](../custom/Espo/Custom/Resources/layouts/README-LAYOUTS.md)
