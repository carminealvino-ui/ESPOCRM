# Passo 0 — Backup obbligatorio (NON saltare)

**Da oggi:** nessuna modifica a codice, layout o deploy senza backup in `backup_dev/` **nella stessa sessione**.

La cartella sul server è:

```text
~/public_html/crm/mec-group/backup_dev/
```

(vicino a `application/`, `client/`, `custom/`, `data/` — vedi screenshot file manager).

---

## Regola

| Ordine | Azione | Bloccante? |
|--------|--------|------------|
| **0** | Backup `backup_dev` | **Sì** — senza backup non si procede |
| 1 | Modifica / deploy | Solo dopo backup OK |
| 2 | Verifica (screenshot) | Prima del passo successivo |
| 3 | Sync GitHub | A fine intervento |

---

## Comando standard (più file — consigliato)

```bash
cd ~/public_html/crm/mec-group

# Esempio: prima di deploy Google Sync
bash tools/backup-dev-batch.sh google-sync --manifest tools/backup-manifests/google-sync.files
```

Output atteso: righe `OK path/file` + riepilogo `Salvati: N` con cartella `backup_dev/_sessions/YYYYMMDD-HHMMSS_google-sync/`.

---

## Comando singolo file

```bash
cd ~/public_html/crm/mec-group
bash tools/backup-dev-save.sh Appuntamento mio-fix hooks GlobalLogic.php
```

---

## Prima di ogni tipo di intervento

| Intervento | Backup |
|------------|--------|
| Deploy Google Sync | `backup-dev-batch.sh google-sync --manifest tools/backup-manifests/google-sync.files` |
| Layout UI (Quote, Account, …) | `bash tools/backup-quote-layouts.sh` / `backup-account-layouts.sh` |
| File singolo hook/metadata | `backup-dev-save.sh ENTITA FIX TIPO FILE` |
| Deploy generico N file | `backup-dev-batch.sh NOME-FIX path1 path2 ...` |

---

## Verifica backup (obbligatoria)

```bash
ls -la ~/public_html/crm/mec-group/backup_dev/_sessions/ | tail -5
# oppure per entità:
ls -lt ~/public_html/crm/mec-group/backup_dev/Appuntamento/hooks/ | head -5
```

Inviare **screenshot** o output terminale con timestamp prima di procedere.

---

## Rollback rapido

```bash
# Da manifest sessione (sostituire DATA e FIX):
SESSION=backup_dev/_sessions/20260609-120000_google-sync
cd ~/public_html/crm/mec-group
while IFS= read -r line; do
  [[ -z "${line}" || "${line}" == \#* ]] && continue
  rel="${line%% -> *}"
  dest="${line#* -> }"
  [[ -f "${dest}" ]] && cp -a "${dest}" "${rel}" && echo "Ripristinato ${rel}"
done < "${SESSION}/files.list"
rm -rf data/cache/*
php command.php rebuild
```

---

## Cosa NON è sufficiente

- Backup hosting generico (cPanel) **non** sostituisce `backup_dev` per file singoli
- `git commit` sul PC **non** sostituisce backup produzione pre-deploy
- `rsync` senza backup → **vietato**

---

## Per agent / sviluppatore

Ogni istruzione al server deve iniziare con il blocco backup. Se l’utente chiede un fix senza backup, rispondere con il comando backup da eseguire **prima**.

Vedi anche: [`00-ORDINE-DI-LAVORO.md`](00-ORDINE-DI-LAVORO.md), [`02-BACKUP-FIX-E-ROLLBACK.md`](02-BACKUP-FIX-E-ROLLBACK.md), [`backup_dev/STRUTTURA-CARTELLE.md`](../backup_dev/STRUTTURA-CARTELLE.md).
