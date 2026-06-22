# Riapplicare le variazioni dopo restore Softaculous

Dopo il restore completo (backup ~**2026-06-19 20:05**), il CRM ha di nuovo **tab e dati** di quella data.  
Nel repository Git restano tutte le modifiche successive: vanno **riapplicate a step**, con backup prima di ogni fase.

Riferimento regole: [`09-NO-CODICE-SENZA-BACKUP-E-HOOKVERSION.md`](09-NO-CODICE-SENZA-BACKUP-E-HOOKVERSION.md)

---

## Inventario variazioni (repo vs restore)

| Blocco | Contenuto | Script deploy | Tab dashboard | DB |
|--------|-----------|---------------|---------------|-----|
| **A — Call esito** | Popup Svolto/Non svolto, WhatsApp, sync Lead, Data Riscontro, dashlet Calls | `deploy-call-esito-canale-contatto.sh` | No (solo opzioni dashlet) | Fix script Call esistenti |
| **B — KPI codice** | API CrmKpi, filtri meseCorrente, CSS/JS dashlet | `deploy-crm-kpi-dashlet.sh` | **No** | No |
| **C — KPI tab CRM** | Dashlet KPI nel tab CRM (merge) | `applica-dashboard-crm-kpi.php` | **Sì** (merge) | No |
| **D — Report Vendite** | 4 report Advanced Pack | `crea-report-vendite-mese.php --reports-only` | No | Tabella `report` |
| **E — Calendario esito** | Click calendario → pannello esito | `deploy-appuntamento-calendario-esito.sh` (branch separato) | No | No |

Branch consigliato per A+B: **`cursor/crm-kpi-dashlet-9999`** (include Call esito + KPI + avvisi).

Allineamento completo repo → server: [`12-ALLINEA-SERVER-DA-REPO.md`](12-ALLINEA-SERVER-DA-REPO.md) e `tools/allinea-server-da-repo.sh`.

---

## Piano operativo (un passo alla volta)

### Step 0 — Backup (obbligatorio)

1. Softaculous → **Backup** manuale (annotare nome `.tar.gz`).
2. Backup dashboard:

```bash
cd ~/public_html/crm/mec-group
bash tools/backup-dashboard-utente.sh carmine_alvino
```

3. Screenshot percorso backup → **solo poi** step 1.

---

### Step 1 — Call esito (priorità funzionale)

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-dashlet-9999/tools/deploy-call-esito-canale-contatto.sh?t=$(date +%s)" | bash
cd ~/public_html/crm/mec-group
php clear_cache.php && php rebuild.php
```

**Verifica:** popup Call all’apertura CRM; scheda Call con esito; dashlet Contatti telefonici.

---

### Step 2 — Assegnazione Call esistenti

```bash
php tools/fix-call-assignment-from-appuntamento.php
```

**Verifica:** Call da appuntamento assegnate all’utente appuntamento, non Admin.

---

### Step 3 — KPI codice (senza toccare tab)

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-dashlet-9999/tools/deploy-crm-kpi-dashlet.sh?t=$(date +%s)" | bash
php clear_cache.php && php rebuild.php
```

**Verifica:** tab dashboard **identici** al restore; nessun tab nuovo.

---

### Step 4 — KPI nel tab CRM (opzionale, solo se richiesto)

```bash
bash tools/backup-dashboard-utente.sh carmine_alvino
php tools/applica-dashboard-crm-kpi.php --user=carmine_alvino
php clear_cache.php
```

**Verifica:** tab CRM = dashlet precedenti **+** KPI (merge, non sostituzione).

**Rollback:**

```bash
php tools/rollback-dashboard-pre-kpi.php --user=carmine_alvino --restore-latest
```

---

### Step 5 — Report Vendite Mese (opzionale, solo elenco)

```bash
php tools/crea-report-vendite-mese.php --reports-only --force --user=carmine_alvino
php clear_cache.php
```

**Verifica:** CRM → Report → 4 report “Vendite Mese - …”. **Nessun** tab dashboard nuovo.

---

### Step E (opzionale) — Calendario → esito

Solo se serve il click calendario sul pannello esito:

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/appuntamento-calendario-esito-9999/tools/deploy-appuntamento-calendario-esito.sh?t=$(date +%s)" | bash
php clear_cache.php && php rebuild.php
```

---

## Script unificato (stessi step)

```bash
cd ~/public_html/crm/mec-group
bash tools/riapplica-variazioni-post-restore.sh              # piano
bash tools/riapplica-variazioni-post-restore.sh --step=0
bash tools/riapplica-variazioni-post-restore.sh --step=1
# ... conferma screenshot tra uno step e l'altro
```

---

## Cosa NON rifare

| Azione | Motivo |
|--------|--------|
| Restore Softaculous completo | Perde dati CRM dopo il backup |
| `--force` su script dashboard vecchi | Sostituiva interi tab |
| `crea-report-vendite-mese.php --force` senza `--reports-only` | Aggiungeva tab Vendite Mese |
| Deploy KPI + dashboard nello stesso minuto senza backup | Impossibile rollback rapido |

---

## Dati creati dopo il restore Softaculous

Il restore ha riportato DB al **19/6 ~20:05**. Tutto ciò che avete inserito **dopo** quell’ora (appuntamenti, opportunità, contratti) **non c’è più** nel DB attuale — non si recupera dai file Git, solo da un backup SQL più recente (se esiste).

I **file custom** in Git si riapplicano con gli step sopra; i **dati** no.

---

## hookVersion

Ogni hook modificato in un prossimo fix → aggiornare versione in header PHP e `$entity->set('hookVersion', 'x.y.z')`.

---

*Creato: 2026-06-19 — post restore Softaculous.*
