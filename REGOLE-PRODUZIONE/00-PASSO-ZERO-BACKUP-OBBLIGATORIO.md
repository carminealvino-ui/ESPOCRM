# Passo 0 — Backup obbligatorio (NON saltare)

## UNICA CARTELLA BACKUP: `backup_dev/`

Tutti i backup vanno **solo** qui:

```text
~/public_html/crm/mec-group/backup_dev/
```

**Non usare** `custom/backup-layouts/` né altre cartelle per i backup.

(vicino a `application/`, `client/`, `custom/`, `data/` nel file manager)

---

## Regola

| Ordine | Azione | Bloccante? |
|--------|--------|------------|
| **0** | Backup in `backup_dev/` | **Sì** — senza backup non si procede |
| 1 | Modifica / deploy | Solo dopo backup OK |
| 2 | Verifica (screenshot) | Prima del passo successivo |
| 3 | Sync GitHub | A fine intervento |

---

## Struttura in `backup_dev/`

```
backup_dev/
├── _sessions/              # log sessione backup-dev-batch.sh
├── _scripts/               # script archiviati
├── Appuntamento/
│   ├── hooks/
│   ├── layouts/
│   ├── services/
│   └── snapshots/          # backup-produzione.sh
├── Quote/
│   └── layouts-snapshots/  # backup-quote-layouts.sh
├── Account/
│   └── snapshots/          # backup-account-layouts.sh
└── {Entità}/...
```

---

## Comando standard (più file)

```bash
cd ~/public_html/crm/mec-group
bash tools/backup-dev-batch.sh NOME-FIX --manifest tools/backup-manifests/google-sync.files
```

## Comando singolo file

```bash
bash tools/backup-dev-save.sh Appuntamento mio-fix hooks GlobalLogic.php
```

## Layout intera entità

```bash
bash tools/backup-quote-layouts.sh    # → backup_dev/Quote/layouts-snapshots/
bash tools/backup-account-layouts.sh  # → backup_dev/Account/snapshots/
```

---

## Verifica (obbligatoria)

```bash
ls -la ~/public_html/crm/mec-group/backup_dev/_sessions/ | tail -5
ls -lt ~/public_html/crm/mec-group/backup_dev/Appuntamento/hooks/ | head -5
```

Screenshot/output con timestamp **prima** di procedere.

---

## Rollback

```bash
# Da sessione batch:
SESSION=backup_dev/_sessions/20260609-120000_google-sync
cd ~/public_html/crm/mec-group
while IFS= read -r line; do
  [[ -z "${line}" || "${line}" == \#* ]] && continue
  rel="${line%% -> *}"
  dest="${line#* -> }"
  [[ -f "${dest}" ]] && cp -a "${dest}" "${rel}" && echo "Ripristinato ${rel}"
done < "${SESSION}/files.list"

# Snapshot Appuntamento:
bash tools/rollback-produzione.sh 20260609-120000

# Layout Quote:
bash tools/restore-quote-layouts.sh 20260609-120000

rm -rf data/cache/* && php command.php rebuild
```

---

## Vietato

- Backup in `custom/backup-layouts/` (deprecato — solo lettura legacy per rollback vecchi)
- Deploy senza backup in `backup_dev/`
- `rsync` massivo senza backup preventivo

Vedi: [`backup_dev/STRUTTURA-CARTELLE.md`](../backup_dev/STRUTTURA-CARTELLE.md), [`02-BACKUP-FIX-E-ROLLBACK.md`](02-BACKUP-FIX-E-ROLLBACK.md).
