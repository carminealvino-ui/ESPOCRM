# Push su GitHub direttamente dal server (senza PC)

Dopo `export-delta`, si può pubblicare su GitHub con **`push-delta`** (git + token).

---

## Prerequisiti

1. **Token GitHub (PAT)** con permesso **Contents: Read and write** e **Metadata: Read** sul repo `carminealvino-ui/ESPOCRM`.
2. **`git`** installato sul server (`which git`).
3. Cartella delta già creata (es. `exports/sync/delta-20260601-082922`).

Non incollare il token in chat né in file versionati.

---

## Passo A — Dry-run (solo conteggio file)

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php push-delta exports/sync/delta-20260601-082922 --dry-run
```

---

## Passo B — Push reale

```bash
cd ~/public_html/crm/mec-group
export GITHUB_TOKEN="ghp_XXXXXXXXX"   # PAT personale
export GITHUB_REPOSITORY="carminealvino-ui/ESPOCRM"
export GITHUB_BRANCH="main"

php tools/sync-custom-prod-repo.php push-delta exports/sync/delta-20260601-082922
```

**Verifica:** aprire  
https://github.com/carminealvino-ui/ESPOCRM/commits/main  
e controllare commit `sync: allineamento da produzione …`.

### Dopo il push — verifica allineamento

Vedi [`07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md`](07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md).

```bash
php tools/sync-custom-prod-repo.php status --branch=main
```

---

## Opzione: repo più leggero (senza moduli estensione in client)

Esclude `client/custom/modules/advanced`, `google`, `outlook` (migliaia di file pacchetto):

```bash
php tools/sync-custom-prod-repo.php push-delta exports/sync/delta-20260601-082922 --exclude-client-modules
```

Custom MEC (`client/custom/src`, `css`, `custom/Espo/Custom`, Sales Resources) restano inclusi.

---

## Alternativa senza token

- Scaricare ZIP delta sul PC (già fatto).
- `apply-delta` sul clone locale + `git push` (vedi `05-SYNC-REPO-DAL-SERVER.md`).

---

## Rollback GitHub

Revert del commit su GitHub (UI) o:

```bash
git revert HEAD
git push
```

(sul clone o da nuovo push-delta con stato precedente).
