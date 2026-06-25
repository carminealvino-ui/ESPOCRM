# Regola 9 — Mai codice senza backup · hookVersion obbligatoria

**Lezione 2026-06-19:** deploy KPI dashboard senza backup mirato → restore Softaculous totale necessario.

Da questo momento: **nessun nuovo codice in produzione** (file custom, script `tools/`, deploy curl, modifica dashboard) **senza backup verificato e percorso di rollback annotato**.

---

## 1. Backup obbligatorio PRIMA di ogni modifica

### Checklist (tutti i punti obbligatori)

- [ ] **Backup file** del fix (`backup_dev/{Entità}/…` o `custom/backup-layouts/…`)
- [ ] **Backup dashboard** se lo script tocca preferenze utente / tab CRM (vedi sotto)
- [ ] **Backup Softaculous** (o snapshot hosting) se l’intervento è **ampio** (nuovo dashlet, script dashboard, deploy multi-file)
- [ ] Percorso backup **annotato** nel messaggio / commit (timestamp + cartella)
- [ ] Comando **rollback** scritto prima di eseguire il deploy

### A) Fix su file (hook, layout, metadata, client)

```bash
cd ~/public_html/crm/mec-group
bash tools/backup-dev-save.sh ENTITA FIX_TIPO NOME_FILE
```

Vedi [`02-BACKUP-FIX-E-ROLLBACK.md`](02-BACKUP-FIX-E-ROLLBACK.md).

### B) Script che modificano tab dashboard (preferenze utente)

Prima di `applica-dashboard-*.php`, `crea-report-*-dashboard`, ecc.:

```bash
cd ~/public_html/crm/mec-group
STAMP=$(date +%Y%m%d-%H%M%S)
mkdir -p "backup_dev/Appuntamento/dashboard-backup-carmine_alvino-${STAMP}"
php -r "
require 'bootstrap.php';
\$em = (new Espo\Core\Application())->getContainer()->get('entityManager');
\$u = \$em->getRDBRepository('User')->where(['userName'=>'carmine_alvino'])->findOne();
\$pref = \$em->getEntityById('Preferences', \$u->getId());
\$data = \$pref->get('data');
file_put_contents('backup_dev/Appuntamento/dashboard-backup-carmine_alvino-${STAMP}/preferences-data-raw.json', json_encode(\$data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
\$tabs = \$pref->get('dashboardLayout') ?? (is_object(\$data) ? (\$data->dashboardLayout ?? null) : null);
file_put_contents('backup_dev/Appuntamento/dashboard-backup-carmine_alvino-${STAMP}/dashboard-layout.json', json_encode(\$tabs, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'OK backup dashboard ', '${STAMP}', PHP_EOL;
"
```

Rollback dashboard (se esiste backup):

```bash
php tools/rollback-dashboard-pre-kpi.php --user=carmine_alvino --restore-dir=backup_dev/Appuntamento/dashboard-backup-carmine_alvino-STAMP
php clear_cache.php
```

### C) Backup Softaculous (interventi grossi)

Prima di deploy multi-file, nuovi dashlet, script che toccano DB + UI:

1. Softaculous → **Backup** manuale EspoCRM (non Restore).
2. Annotare nome file (es. `espo.501_89949.2026-06-19_20-05-01.tar.gz`).
3. Procedere solo dopo conferma backup completato.

**Mai** usare “Ripristina Installazione” Softaculous per un fix puntuale: ripristina **tutto** DB + file e cancella dati CRM successivi.

Per tab dashboard senza perdere dati: estrarre solo riga `preferences` dal `.sql` del backup (vedi istruzioni agent / [`02-BACKUP-FIX-E-ROLLBACK.md`](02-BACKUP-FIX-E-ROLLBACK.md)).

---

## 2. hookVersion — versione del fix obbligatoria

Ogni hook PHP che modifica entità con campo **`hookVersion`** (Appuntamento, Opportunity, Quote, …) deve:

1. **Header file** con `VERSIONE: x.y.z` e data (già in uso).
2. **`$entity->set('hookVersion', 'x.y.z')`** nel BeforeSave (o hook equivalente), **stesso valore** dell’header.
3. **Incremento** ad ogni fix (patch `z`, minor `y` se comportamento nuovo).

### Esempio (Appuntamento)

```php
// ========================================
// VERSIONE: 1.7.4
// DATA: 2026-06-19
// FIX: descrizione breve
// ========================================

$entity->set('hookVersion', '1.7.4');
```

### Esempio (Opportunity)

```php
// VERSIONE: 2.2.7
$entity->set('hookVersion', '2.2.7');
```

### CreateContratto / casi speciali

- Quote da CreateContratto: prefisso `CreateContratto-x.y.z` (vedi `CreateContratto.php`).
- Non copiare `hookVersion` da Opportunity a Quote se la formula Quote reagisce al valore.

### Verifica in CRM

Dopo deploy: apri un record modificato → campo **HookVersion** (o traduzione IT) = versione attesa.

---

## 3. Cosa NON fare

| Vietato | Motivo |
|---------|--------|
| Deploy curl / copia file senza backup | Nessun rollback rapido |
| `--force` su script dashboard che sostituisce layout | Cancella tab esistenti |
| Restore Softaculous completo per un fix piccolo | Perdita dati CRM |
| Hook modificato senza aggiornare `hookVersion` | Impossibile capire quale fix è in produzione |
| Proseguire senza screenshot di backup OK | Regola 1 — un passo alla volta |

---

## 4. Ordine operativo (riepilogo)

```
1. Backup file (backup_dev)
2. Backup dashboard / Softaculous se serve
3. Annotare path rollback
4. Una sola modifica / deploy
5. Verifica + hookVersion su record test
6. Solo allora passo successivo o sync GitHub
```

Vedi anche [`00-ORDINE-DI-LAVORO.md`](00-ORDINE-DI-LAVORO.md).

---

*Aggiunta: 2026-06-19 — post restore Softaculous dashboard KPI.*
