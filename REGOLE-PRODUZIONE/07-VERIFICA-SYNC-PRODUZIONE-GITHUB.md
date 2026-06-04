# Verificare allineamento produzione ↔ GitHub

Confronto **solo lettura** tra ciò che c’è sul server e il branch `main` su GitHub.

---

**Dove:** `cd ~/public_html/crm/mec-group`

**Comando:**

```bash
php tools/sync-custom-prod-repo.php status --branch=main
```

**Verifica attesa:**

| Voce | Atteso |
|------|--------|
| **Identici** | Numero alto (ordine migliaia se prod e GitHub sono allineati) |
| **Diversi** | 0 o pochi |
| **Solo prod** | 0 o pochi |
| **Solo repo** | 0 o pochi |

Viene creato un manifest: `exports/sync/status-YYYYMMDD-HHMMSS.json`

→ Screenshot con i quattro totali prima di export/push o deploy.

---

Vedi anche: [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md), [`06-PUSH-GITHUB-DAL-SERVER.md`](06-PUSH-GITHUB-DAL-SERVER.md)
