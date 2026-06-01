# Regola 2 — Backup del fix e rollback

## Prima di qualsiasi modifica

Leggere la struttura cartelle: [`04-STRUTTURA-BACKUP-DEV.md`](04-STRUTTURA-BACKUP-DEV.md).

### A) File singoli (hook, PHP, JS, metadata JSON)

```bash
cd ~/public_html/crm/mec-group
bash tools/backup-dev-save.sh ENTITA FIX_TIPO NOME_FILE
```

Esempio:

```bash
bash tools/backup-dev-save.sh Appuntamento elenco-produzione hooks GlobalLogic.php
```

Copia in: `backup_dev/Appuntamento/hooks/YYYYMMDD-HHMMSS_elenco-produzione_hooks_GlobalLogic.php`

### B) Layout (intera cartella entità)

```bash
bash tools/backup-quote-layouts.sh      # Contratto → custom/backup-layouts/.../Quote/
bash tools/backup-account-layouts.sh    # Cliente
```

Se `tools/` manca:

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
```

### C) Backup manuale d’emergenza (senza script)

```bash
STAMP=$(date +%Y%m%d-%H%M%S)
mkdir -p "custom/backup-layouts/${STAMP}/Quote"
cp -a custom/Espo/Custom/Resources/layouts/Quote/. "custom/backup-layouts/${STAMP}/Quote/"
echo "Salvato in custom/backup-layouts/${STAMP}"
```

## Rollback layout Contratto

```bash
bash tools/restore-quote-layouts.sh YYYYMMDD-HHMMSS
php command.php rebuild && rm -rf data/cache/*
```

## Rollback file in backup_dev

```bash
cp -a backup_dev/Quote/layouts/20260529-120000_nome-fix_layouts_detail.json \
  custom/Espo/Custom/Resources/layouts/Quote/detail.json
php command.php rebuild && rm -rf data/cache/*
```

(adattare percorso al file salvato)

## Cosa non sostituisce il backup file

- Backup **database** (cliente, contratti): export MySQL o snapshot hosting se serve rollback dati.
- Cartella `tools/` sul server: non è in Espo; va scaricata con bootstrap (vedi `tools/LEGGI-PRIMA.md`).
