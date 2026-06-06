# Avvio sessione — allineamento produzione → GitHub (cPanel)

**Usare questa checklist ogni volta** che si parte per portare la produzione su `main`.  
**Un passo per messaggio** (screenshot o output prima del successivo).

Ambiente:

| Cosa | Percorso |
|------|----------|
| CRM produzione | `~/public_html/crm/mec-group` |
| Clone Git (push) | `~/ESPOCRM-git` |
| Token GitHub (solo server) | `~/public_html/crm/mec-group/exports/sync/token.txt` |
| Delta export | `~/public_html/crm/mec-group/exports/sync/delta-YYYYMMDD-HHMMSS/` |

Il file **`token.txt` resta sul server** (non va su GitHub). Contiene **solo** il PAT (`ghp_...`), una riga, senza spazi.

```bash
chmod 600 ~/public_html/crm/mec-group/exports/sync/token.txt
```

Modello vuoto in repo: `exports/sync/token.txt.example`

---

## Ordine fisso (ogni sessione)

```
1. export-delta     ← SEMPRE per primo (obbligatorio)
2. apply-delta      ← sul clone ~/ESPOCRM-git
3. commit + push    ← script con token.txt
4. status           ← verifica (opzionale ma consigliata)
```

`status` **prima** dell’export è solo diagnosi; **non** sostituisce il passo 1.

---

## Setup una tantum (se manca)

**Clone Git:**

```bash
cd ~
git clone https://github.com/carminealvino-ui/ESPOCRM.git ESPOCRM-git
cd ~/ESPOCRM-git
git config user.email "sync@mec-group.local"
git config user.name "MEC Sync Produzione"
```

**Token:** creare PAT GitHub (scope **`repo`**) e salvarlo in `exports/sync/token.txt` (vedi sopra).

**Tools sul CRM:**

```bash
cd ~/public_html/crm/mec-group
test -f tools/sync-custom-prod-repo.php || curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
```

---

## Passo 1 — Export (obbligatorio)

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php export-delta --branch=main
```

Annotare la cartella creata, es. `exports/sync/delta-20260605-085058`.

---

## Passo 2 — Apply sul clone Git

Sostituire `DELTA` con la cartella del passo 1:

```bash
cd ~/ESPOCRM-git
git checkout main
git pull origin main
php tools/sync-custom-prod-repo.php apply-delta "/home/telcalli/public_html/crm/mec-group/exports/sync/DELTA"
```

Oppure script:

```bash
bash ~/public_html/crm/mec-group/tools/sync-apply-delta-cpanel.sh DELTA
```

(`DELTA` = solo il nome cartella, es. `delta-20260605-085058`)

---

## Passo 3 — Commit e push (token da file)

```bash
bash ~/public_html/crm/mec-group/tools/sync-push-github-cpanel.sh
```

Messaggio commit opzionale:

```bash
bash ~/public_html/crm/mec-group/tools/sync-push-github-cpanel.sh "sync: allineamento da produzione $(date +%Y-%m-%d)"
```

---

## Passo 4 — Verifica

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php status --branch=main
```

Dopo un sync completo ci si aspetta **Solo prod** basso o 0. Se restano migliaia di voci (es. `client/custom/modules/advanced/`), ripetere **export → apply → push** nella stessa sessione o valutare `--exclude-client-modules` (vedi `06-PUSH-GITHUB-DAL-SERVER.md`).

---

## Dopo il sync su GitHub

Prima di **qualsiasi modifica codice** in produzione:

1. Backup in `backup_dev/{Entità}/` (vedi `02-BACKUP-FIX-E-ROLLBACK.md`)
2. Una istruzione alla volta
3. Deploy mirato (non sovrascrivere tutto `custom/`)

---

## Rollback

| Cosa | Come |
|------|------|
| Clone Git | `git restore .` o backup in `exports/sync/apply-backup-…` |
| GitHub | Revert commit su https://github.com/carminealvino-ui/ESPOCRM/commits/main |
| Produzione | L’export non modifica i file live |

---

Vedi anche: [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md), [`06-PUSH-GITHUB-DAL-SERVER.md`](06-PUSH-GITHUB-DAL-SERVER.md)
