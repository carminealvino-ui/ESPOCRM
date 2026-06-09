# Google Calendar sync + bonifica ghost — sessione 11 giugno 2026

**Sintomi tipici:** appuntamenti reali non compaiono su Google, lista piena di `(APPUNTAMENTO SENZA PROSPECT)`, duplicati sullo stesso slot (es. stesso codice CAP a orari diversi).

**Consulente calendario:** Alvino Carmine — id `67c93e694705fde80`

---

## Diagnosi rapida

| Problema | Causa | Fix |
|----------|-------|-----|
| `(APPUNTAMENTO SENZA PROSPECT)` in lista | Hook Google legacy creava dummy senza prospect | Deploy fix + `--only-purge-ghosts` |
| Duplicati su Google (stesso cliente, slot vicini) | Doppio push (hook + job Espo) | `--only-purge-duplicates` |
| Appuntamenti reali non su Google | `syncConGoogle=false` su record vecchi, link morti, `assignedUserId` errato | Deploy + `--backfill-sync-flag` + `--only-push` |
| Annullati ancora su Google | Not Held non rimossi | bonifica completa o `--reconcile` |

---

## PASSO 0 — Backup obbligatorio

```bash
cd ~/public_html/crm/mec-group
bash tools/backup-dev-batch.sh google-sync --manifest tools/backup-manifests/google-sync.files
```

---

## PASSO 1 — Verifica deploy sync (se non fatto di recente)

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-fix-appuntamento-google-sync.sh" -o tools/deploy-fix-appuntamento-google-sync.sh
bash tools/deploy-fix-appuntamento-google-sync.sh
```

Attendere **rebuild completo** (non interrompere).

---

## PASSO 2 — Bonifica (UN COMANDO PER RIGA)

Anteprima completa:

```bash
cd ~/public_html/crm/mec-group
php tools/bonifica-appuntamento-google-calendar.php --dry-run --verbose --user-id=67c93e694705fde80
```

Esecuzione — **ordine consigliato:**

```bash
php tools/bonifica-appuntamento-google-calendar.php --apply --only-purge-ghosts --user-id=67c93e694705fde80
```

```bash
php tools/bonifica-appuntamento-google-calendar.php --apply --backfill-sync-flag --user-id=67c93e694705fde80
```

```bash
php tools/bonifica-appuntamento-google-calendar.php --apply --verbose --user-id=67c93e694705fde80
```

Duplicati Google (settimana corrente — adattare date):

```bash
php tools/bonifica-appuntamento-google-calendar.php --dry-run --only-purge-duplicates --from-date=2026-06-01 --to-date=2026-06-14 --user-id=67c93e694705fde80
```

```bash
php tools/bonifica-appuntamento-google-calendar.php --apply --only-purge-duplicates --from-date=2026-06-01 --to-date=2026-06-14 --user-id=67c93e694705fde80
```

Solo push mancanti (ultimi 21 giorni):

```bash
php tools/bonifica-appuntamento-google-calendar.php --apply --only-push --verbose --user-id=67c93e694705fde80
```

---

## PASSO 3 — Verifica

1. Lista Appuntamenti: niente (o pochissimi) `(APPUNTAMENTO SENZA PROSPECT)` Planned
2. Google Calendar Alvino: ogni appuntamento Planned/Held/Ingestibile con prospect presente
3. Crea un appuntamento test con `Sync con Google` attivo → compare su Google entro pochi secondi
4. Ctrl+F5 su Espo

---

## Script guidato (opzionale)

```bash
bash tools/run-google-sync-bonifica-session.sh          # solo anteprima
bash tools/run-google-sync-bonifica-session.sh --apply  # esegue tutte le fasi
```

---

## Non fare

- Non cancellare manualmente centinaia di record senza bonifica (rischio link Google orfani)
- Non `rsync` massivo su `custom/`
- Non interrompere `rebuild.php`

Vedi anche: [`09-ALLINEAMENTO-GOOGLE-SYNC-20260609.md`](09-ALLINEAMENTO-GOOGLE-SYNC-20260609.md)
