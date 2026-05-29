# Flusso Appuntamento → Lead → Cliente → Contratto

## Catena dati

1. **Appuntamento** `status = Held` → `Hooks/Appuntamento/GlobalLogic.php` (beforeSave)
2. Crea/aggiorna **Lead** da **Prospect** (`syncLeadFromProspect`)
3. **Opportunità** (chiusa vinta) → `Actions/Opportunity/CreateContratto.php`
4. Crea/aggiorna **Account (Cliente)** e **Quote (Contratto)**

## Lacune risolte (1.7.0 / 1.8.0)

| Campo | Prima | Dopo |
|--------|--------|------|
| Telefono Lead | Solo `phoneNumber` prospect | Anche `telefono`, estrazione da `wa.me/...` |
| Email / WhatsApp / Web | Non copiati | Da Prospect → Lead |
| Fornitore / Brand | Solo se vuoti, a volte persi | Sync + fallback da `azienda` |
| Nome Lead | `personName` vuoto | first+last, referente, ragione sociale |
| Cliente (Account) | Id grezzo, campi vuoti | CreateContratto 1.8.0 + repair |
| Lead esistenti | `setLeadFieldIfEmpty` non aggiornava phone/email | `Lead/action/repairFromProspect` |

## Repair massivo lead esistenti

`POST /api/v1/Lead/action/repairFromProspect`

Body JSON opzionale:

```json
{ "onlyEmpty": true, "limit": 500 }
```

Risposta: `processed`, `updated`, `accountsUpdated`, `skipped`, `errors`.

## Deploy file

- `Services/LeadProspectSync.php`
- `Hooks/Appuntamento/GlobalLogic.php`
- `Actions/Lead/RepairFromProspect.php`
- `Controllers/Lead.php`
- `Actions/Opportunity/CreateContratto.php` (già 1.8.0)

Poi **Clear Cache**.
