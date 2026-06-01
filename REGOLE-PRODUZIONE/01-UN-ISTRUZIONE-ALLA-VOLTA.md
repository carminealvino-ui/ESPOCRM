# Regola 1 — Un’istruzione alla volta

## Perché

In produzione, più comandi insieme rendono impossibile capire quale passo ha rotto layout, cache o dati.

## Cosa fare

### Chi dà le istruzioni (umano / agente)

- Un messaggio = **un passo** (es. solo `bash tools/backup-quote-layouts.sh`).
- Il passo successivo compare **solo** dopo conferma con screenshot.

### Chi esegue sul server

- Eseguire **solo** il comando indicato.
- Inviare screenshot che mostri:
  - comando eseguito;
  - ultime righe di output (`OK`, percorso backup, `rebuild` completato);
  - oppure schermata CRM se il passo è visivo.

### Esempi

| Vietato | Consentito |
|---------|------------|
| «Fai backup, curl deploy, rebuild e backfill» | «Passo 1: bootstrap tools» → screenshot → «Passo 2: backup Quote» → … |
| Incollare 10 comandi senza verifica | Un comando + «mandami screenshot» |

## Frase standard per passare avanti

> «Passo N completato» + screenshot → si può dare il passo N+1.
