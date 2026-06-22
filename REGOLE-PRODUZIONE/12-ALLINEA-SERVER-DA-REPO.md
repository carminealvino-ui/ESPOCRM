# Allineare il server al repository (repo → server)

**Direzione:** il codice su **GitHub** viene applicato in **produzione** con deploy mirati.  
**Non** si usa `apply-delta` sul server (sarebbe pericoloso: sovrascriverebbe tutto `custom/`).

Per il verso opposto (prod → GitHub) vedi [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md).

---

## Branch canonico (KPI + Call esito + avvisi)

`cursor/crm-kpi-dashlet-9999`

Include: dashlet KPI, funnel, avvisi, filtri periodo, popup Call, fix conteggi.

---

## Procedura (un passo alla volta)

### Passo 0 — Backup (obbligatorio)

**Dove:** `cd ~/public_html/crm/mec-group`

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-dashlet-9999/tools/allinea-server-da-repo.sh?t=$(date +%s)" -o tools/allinea-server-da-repo.sh
chmod +x tools/allinea-server-da-repo.sh
bash tools/allinea-server-da-repo.sh --step=0
```

**Verifica attesa:**

- Backup Softaculous avviato (annotare nome file)
- Output `backup_dev/dashboard-…/preferences-before.json`

**Rollback:** restore Softaculous o `rollback-dashboard-pre-kpi.php --restore-latest`

→ Screenshot, poi **Passo 1**.

---

### Passo 1 — Call esito + popup

```bash
bash tools/allinea-server-da-repo.sh --step=1
cd ~/public_html/crm/mec-group
php clear_cache.php && php rebuild.php
```

**Verifica:** popup Call all’apertura CRM; scheda Call con esito/canale.

→ Screenshot, poi **Passo 2**.

---

### Passo 2 — KPI completo (codice)

```bash
bash tools/allinea-server-da-repo.sh --step=2
```

**Verifica:** messaggi `OK` per ogni file; backup in `backup/crm-kpi-dashlet/server-…/`

→ **Passo 3**.

---

### Passo 3 — Rebuild + diagnostica

```bash
bash tools/allinea-server-da-repo.sh --step=3
```

**Verifica attesa:**

- `php -l` OK su DateRange, Alerts, CrmKpiService
- `getSummary(currentMonth)` OK
- `avvisi: 5`
- Browser: **Ctrl+Shift+R** sulla dashboard KPI

→ Screenshot dashboard (tile, funnel, avvisi).

---

### Passo 4 — Tab CRM con dashlet KPI (solo se manca)

```bash
bash tools/allinea-server-da-repo.sh --step=4
```

**Verifica:** tab CRM = dashlet precedenti **+** KPI (merge, non sostituzione).

**Rollback:**

```bash
php tools/rollback-dashboard-pre-kpi.php --user=carmine_alvino --restore-latest
```

---

## Deploy rapido (solo KPI, se Call esito già presente)

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-dashlet-9999/tools/deploy-crm-kpi-hotfix.sh?t=$(date +%s)" | bash
php clear_cache.php && php rebuild.php
php tools/verify-crm-kpi-deploy.php
php tools/diagnose-crm-kpi-api.php --user=carmine_alvino
```

---

## Dopo l’allineamento repo → server

Se in produzione sono state fatte modifiche manuali ai file custom, esportare verso GitHub **prima** del prossimo deploy massiccio:

```bash
php tools/sync-custom-prod-repo.php export-delta --branch=main
```

Vedi [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md).

---

## Cosa NON fare

| Azione | Motivo |
|--------|--------|
| `apply-delta` in produzione | Sovrascrive custom live con il repo |
| Deploy senza backup | Regola 9 |
| `--force` su dashboard | Sostituisce tab interi |
| Modificare etichette it_IT esistenti | Regola 11 |

---

*Creato: 2026-06-20 — allineamento KPI + avvisi.*
