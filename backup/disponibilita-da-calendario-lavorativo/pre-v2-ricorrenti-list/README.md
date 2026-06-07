# Backup v1 — generazione da dettaglio calendario

Snapshot prima del passaggio a **Disponibilità Ricorrenti** (lista Disponibilità).

## Contenuto

- `WorkingTimeCalendarDisponibilitaGenerator.php` — service con utenti manuali nel pannello
- `GeneraDisponibilita.php` — action su calendario
- `detail.js` — pulsante «Genera Disponibilità» su dettaglio calendario
- metadata, layout, clientDefs v1

## Rollback in produzione

```bash
cd ~/public_html/crm/mec-group
bash tools/rollback-disponibilita-da-calendario-lavorativo.sh
```

Oppure ripristino manuale da `backup/disponibilita-da-calendario-lavorativo/server-YYYYMMDD-HHMMSS/` creato dal deploy.
