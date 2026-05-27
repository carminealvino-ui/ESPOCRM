# Aggiornamento EspoCRM 9.3.7 — MEC Group

## 1. Backup (obbligatorio)

Dalla root CRM:

```bash
cd ~/public_html/crm/mec-group
bash backup/pre-upgrade-9.3.7/backup-completo-pre-aggiornamento.sh
```

Verifica che in `backup/pre-upgrade-9.3.7/snapshots/YYYYMMDD-HHMM/` ci siano:

- `database-*.sql.gz`
- `custom.tar.gz`
- `client-custom.tar.gz`
- `data-config-upload.tar.gz`

**Consiglio:** copia l’intera cartella `snapshots/YYYYMMDD-HHMM/` anche fuori dal server (FTP, altro disco).

---

## 2. Cosa NON sovrascrivere in aggiornamento

| Mantieni | Non sostituire con il pacchetto nuovo |
|----------|----------------------------------------|
| `custom/` | Tutta la cartella |
| `client/custom/` | Tutta la cartella |
| `data/config.php` | Configurazione istanza |
| `data/config-internal.php` | Credenziali DB |
| `data/upload/` | Allegati utenti |

Il pacchetto ufficiale EspoCRM aggiorna tipicamente: `application/`, `client/` (tranne `client/custom/`), `vendor/`, file root (`index.php`, `bootstrap.php`, ecc.).

---

## 3. Aggiornamento manuale (ordine consigliato)

1. **Modalità manutenzione** (opzionale): Administration → Settings, o file `.htaccess` / pagina manutenzione hosting.
2. Scarica **EspoCRM 9.3.7** da [espocrm.com/download](https://www.espocrm.com/download/).
3. Estrai lo ZIP in locale; carica sul server **solo** i file del core (vedi tabella sopra).
4. **Outlook Integration 1.7.0** e **Advanced Pack 3.13.0**: aggiorna dalle rispettive pagine Administration → Extensions, seguendo la documentazione del fornitore (Advanced Pack dal portale clienti).
5. Da root CRM:
   ```bash
   cd ~/public_html/crm/mec-group
   php command.php upgrade
   ```
   Se non esiste:
   ```bash
   php rebuild.php
   php clear_cache.php
   ```
6. Browser: Administration → **Clear Cache** → **Rebuild**.
7. Controlla **Administration → Upgrade** che non ci siano errori.
8. Test funzionali:
   - Login
   - Calendario + Appuntamento (crea / Modulo completo)
   - Opportunità / Contratto / Listino
   - Hook `1.7.3` (durata 1h30 al salvataggio)

---

## 4. Ripristino da backup (emergenza)

### Solo database

```bash
cd ~/public_html/crm/mec-group
SNAP=backup/pre-upgrade-9.3.7/snapshots/YYYYMMDD-HHMM   # sostituire data

gunzip -c "${SNAP}/database-telcalli_espo.sql.gz" | mysql -u USER -p telcalli_espo
```

(Usare le stesse credenziali di `data/config-internal.php`.)

### Solo custom

```bash
cd ~/public_html/crm/mec-group
SNAP=backup/pre-upgrade-9.3.7/snapshots/YYYYMMDD-HHMM

rm -rf custom
tar -xzf "${SNAP}/custom.tar.gz"
tar -xzf "${SNAP}/client-custom.tar.gz" -C client/
php clear_cache.php
php rebuild.php
```

---

## 5. Note produzione MEC Group

- Deploy custom: **solo file mirati** o script in `backup/hooks_cleanup/` — non sostituire tutto `custom/` da Git senza verifica.
- Hook critici: `Appuntamento/GlobalLogic.php` (1.7.3), `Opportunity/GlobalLogic.php`.
- `clientDefs/Appuntamento.json`: viste Meeting standard (non lasciare `{}` vuoto).
- **Non** deployare `clientDefs/Calendar.json` con controller custom.
- Dopo ogni aggiornamento core: **rebuild + clear cache + Ctrl+F5**.

---

## 6. Estensioni in notifica

| Componente | Versione indicata |
|------------|-------------------|
| EspoCRM core | 9.3.7 |
| Outlook Integration | 1.7.0 |
| Advanced Pack | 3.13.0 |

Aggiornare le estensioni **dopo** il core, una alla volta, con backup già fatto.
