# Appuntamento — Duplica → schermata bianca

## Causa (produzione MEC)

Console:

```text
404 .../client/lib/transpiled/modules/crm/src/views/meeting/record/edit.js
```

Su server **non esiste** `client/modules/crm/` né il file transpiled → il pacchetto client **CRM Meeting** non è disponibile in quella installazione (o non è mai stato compilato lì).

`clientDefs` puntava a `crm:views/meeting/record/edit` → la Duplica non può caricare la maschera.

## Fix (v2 — senza modulo CRM client)

1. `clientDefs/Appuntamento.json` → viste **standard** `views/record/*` + custom appuntamento
2. `entityDefs/Appuntamento.json` → campi data **senza** view `crm:views/meeting/fields/*`
3. `edit.js` / `edit-small.js` → estendono `views/record/edit` (durata default 90 min)

## Deploy produzione

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-fix-appuntamento-duplica.sh?t=$(date +%s)" | bash
```

Oppure dopo merge su `main`, sostituire il branch nell’URL con `main`.

Manuale:

```bash
php command.php rebuild
rm -rf data/cache/*
```

Poi **Ctrl+F5**.

## Nota

Calendario / promemoria avanzati del modulo CRM potrebbero essere limitati; la Duplica e il modulo completo Appuntamento devono funzionare con i campi datetime standard.
