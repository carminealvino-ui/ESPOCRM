# Regola — Tenere sempre conto di `backup_dev/`

Ogni fix in produzione deve rispettare la **struttura delle cartelle** in `backup_dev/`, non solo «fare un backup generico».

## Obbligo prima del fix

1. **Identificare** cosa si sta per toccare:
   - hook PHP → `custom/Espo/Custom/Hooks/{Entità}/`
   - layout → `custom/Espo/Custom/Resources/layouts/{Entità}/`
   - metadata → `custom/Espo/Custom/Resources/metadata/...`
   - front-end → `client/custom/src/...` (o `custom/.../Resources/client/custom/...`)

2. **Salvare** nella sottocartella **corrispondente** di `backup_dev/{Entità}/` (vedi guida completa).

3. **Non** copiare file live dentro `backup_dev/client/` se il fix è su una singola entità — usare `{Entità}/client/detail|handlers|runtime/`.

## `backup_dev/client/` ≠ `mec-group/client/`

| Path | Significato |
|------|-------------|
| `~/public_html/crm/mec-group/client/` | Front-end Espo **attivo** |
| `~/public_html/crm/mec-group/backup_dev/client/` | Solo **archivio** CSS/metadata globali |

Confonderli causa rollback sul path sbagliato.

## Documentazione di riferimento

- Struttura dettagliata: [`backup_dev/STRUTTURA-CARTELLE.md`](../backup_dev/STRUTTURA-CARTELLE.md)
- Naming file: [`backup_dev/README.md`](../backup_dev/README.md) (`DATA_FIX_AGGIORNAMENTO_OBIETTIVO`)
- Comando: `bash tools/backup-dev-save.sh ENTITA FIX AGGIORNAMENTO FILE`

## Checklist (da spuntare)

- [ ] So se il fix è hooks / layouts / metadata / client JS
- [ ] Backup in `backup_dev/{Entità}/...` (non cartella sbagliata)
- [ ] Se layout massivo: `backup_dev/{Entità}/layouts-snapshots/` o `snapshots/` (mai `custom/backup-layouts/`)
- [ ] Annotato timestamp file per rollback
