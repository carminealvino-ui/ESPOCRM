# Cartella sync (sul server CRM)

## `token.txt` (solo produzione, mai su GitHub)

Percorso sul server:

```text
~/public_html/crm/mec-group/exports/sync/token.txt
```

- Contenuto: **solo** il PAT GitHub (`ghp_...`), una riga.
- Permessi: `chmod 600 token.txt`
- Modello: `token.txt.example` (in repo)
- **Non** committare `token.txt` su Git.

## Delta export

```text
exports/sync/delta-YYYYMMDD-HHMMSS/
exports/sync/delta-YYYYMMDD-HHMMSS.zip
```

Generati da `php tools/sync-custom-prod-repo.php export-delta --branch=main`

## Procedura completa

Vedi [`REGOLE-PRODUZIONE/REGOLE.md`](../../REGOLE-PRODUZIONE/REGOLE.md) (sezioni 7–8)
