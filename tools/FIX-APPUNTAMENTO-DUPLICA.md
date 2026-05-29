# Appuntamento — Duplica → schermata bianca

## Sintomo

Console browser:

```text
404 .../client/lib/transpiled/modules/crm/src/views/meeting/record/edit.js
Could not load script ... meeting/record/edit.js
```

Il modulo **Appuntamento** usa le viste **Meeting** del pacchetto CRM (`crm:views/meeting/record/edit`). Se `client/lib/transpiled/...` non esiste (cache cancellata senza rebuild, deploy incompleto), la Duplica apre edit vuoto.

## Fix rapido in produzione

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-fix-appuntamento-duplica.sh?t=$(date +%s)" | bash
```

Oppure manuale:

```bash
php command.php rebuild
rm -rf data/cache/*
```

Poi **Ctrl+F5** nel browser.

## Verifica

```bash
test -f client/lib/transpiled/modules/crm/src/views/meeting/record/edit.js && echo OK
```

## Metadata

`clientDefs/Appuntamento.json` punta a `custom:views/appuntamento/record/*` (wrapper su meeting) per detail/edit; serve comunque il file CRM transpiled come dipendenza.
