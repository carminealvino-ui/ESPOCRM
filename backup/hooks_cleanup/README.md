# Backup produzione — struttura per entità

Copie di sicurezza **prima di modifiche mirate** (hook, layout, metadata, client JS).

**Non** qui: export ZIP completi di `custom/` → usare `exports/custom/` o `custom/backup-layouts/`.

## Albero cartelle

```
backup/hooks_cleanup/
├── README.md
├── _scripts/              # deploy / migrazione
├── _archives/             # tar.gz interi (deploy branch)
├── Appuntamento/
│   ├── hooks/
│   ├── layouts/
│   ├── metadata/
│   │   ├── entityDefs/
│   │   ├── logicDefs/
│   │   └── clientDefs/
│   └── client/
├── Opportunity/
│   ├── hooks/
│   ├── layouts/
│   ├── metadata/...
│   └── client/
│       ├── detail/
│       ├── handlers/
│       └── runtime/
├── Prospect/
├── Lead/
├── Quote/
├── Product/
├── FornitorePartner/
└── ProductBrand/
```

## Convenzione nomi file

```
{entità}/{tipo}/{descrizione}-{versione-o-data}.{ext}
```

Esempi:

- `Appuntamento/hooks/globallogic-1.7.0-category-cascade-stabile.php`
- `Appuntamento/metadata/entityDefs/2026-05-26-2014.json`
- `Opportunity/client/handlers/create-contratto-1.0.4-stabile.js`

## Ripristino manuale

Copiare il file dalla sottocartella verso il path reale sotto `custom/` o `client/custom/`, poi:

```bash
php command.php rebuild
rm -rf data/cache/*
```

## Migrazione da cartella piatta (server)

Se sul server avete ancora file `backup-appuntamento-*.php` nella root di `hooks_cleanup`:

```bash
cd ~/public_html/crm/mec-group
bash backup/hooks_cleanup/_scripts/migra-struttura-server.sh
```

## Script utili

| Script | Uso |
|--------|-----|
| `_scripts/migra-struttura-server.sh` | Sposta file flat → sottocartelle entità |
| `_scripts/deploy-produzione-sync-branch.sh` | Deploy branch Git (crea tar in `_archives/`) |
| `../../tools/restore-appuntamento-da-backup-hooks-cleanup.sh` | Ripristino Appuntamento + fix Crea |
