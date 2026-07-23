define('custom:helpers/appuntamento-sottostato-map', [], function () {

    const allowedMap = {
        Held: [
            'Pending',
            'Gestito',
            'Non Interessato',
            'Chiuso Positivamente',
        ],
        'Not Held': [
            'Annullato',
            'Non Gestito',
            'Non Ricevuto',
            'Rifissato',
        ],
        Ingestibile: [
            'Infattibilità Tecnica',
            'Solo Informazioni',
            'Prodotto non Conforme',
            'Fuori Target',
        ],
    };

    /** Esito → {status, sottostato} */
    const esitoMap = {
        'In Trattativa': {status: 'Held', sottostato: 'Pending'},
        'Marito/Moglie Assente': {status: 'Held', sottostato: 'Pending'},
        'Ripasso per info': {status: 'Held', sottostato: 'Gestito'},
        'Appuntamento non in agenda': {status: 'Held', sottostato: 'Gestito'},
        'Prezzo Elevato': {status: 'Held', sottostato: 'Non Interessato'},
        'Modalità di pagamento': {status: 'Held', sottostato: 'Non Interessato'},
        'No Caparra': {status: 'Held', sottostato: 'Non Interessato'},
        'Venduto Tablet': {status: 'Held', sottostato: 'Chiuso Positivamente'},
        'Venduto Cartaceo': {status: 'Held', sottostato: 'Chiuso Positivamente'},
        'Annullato dal Potenziale': {status: 'Not Held', sottostato: 'Annullato'},
        'Annullato Azienda': {status: 'Not Held', sottostato: 'Annullato'},
        'Annullato Call Center': {status: 'Not Held', sottostato: 'Annullato'},
        'Annullato dal Consulente': {status: 'Not Held', sottostato: 'Non Gestito'},
        'Cliente Assente': {status: 'Not Held', sottostato: 'Non Ricevuto'},
        'Rimandato dal Potenziale': {status: 'Not Held', sottostato: 'Rifissato'},
        'Rimandato da cliente': {status: 'Not Held', sottostato: 'Rifissato'},
        'Rimandato da consulente': {status: 'Not Held', sottostato: 'Rifissato'},
        'Solo Preventivo': {status: 'Ingestibile', sottostato: ''},
        'Non Finanziabile': {status: 'Ingestibile', sottostato: ''},
        'Non detraibile per età': {status: 'Ingestibile', sottostato: ''},
        'Non detraibile per prodotto': {status: 'Ingestibile', sottostato: ''},
        'Non detraibile per esposizione': {status: 'Ingestibile', sottostato: ''},
        'Permessi': {status: 'Ingestibile', sottostato: ''},
        'Casa Popolare/Affitto': {status: 'Ingestibile', sottostato: ''},
        'In ristrutturazione/In costruzione/Cantiere': {status: 'Ingestibile', sottostato: ''},
        'Cambio Telo': {status: 'Ingestibile', sottostato: ''},
        'Copertura Auto': {status: 'Ingestibile', sottostato: ''},
        'Tenda a Capanno': {status: 'Ingestibile', sottostato: ''},
        'Infattibilità Tecnica': {status: 'Ingestibile', sottostato: 'Infattibilità Tecnica'},
        'Prodotto non Conforme': {status: 'Ingestibile', sottostato: 'Prodotto non Conforme'},
    };

    const esitiBySottostato = {
        Pending: ['In Trattativa', 'Marito/Moglie Assente'],
        Gestito: ['Ripasso per info', 'Appuntamento non in agenda'],
        'Non Interessato': ['Prezzo Elevato', 'Modalità di pagamento', 'No Caparra'],
        'Chiuso Positivamente': ['Venduto Tablet', 'Venduto Cartaceo'],
        Annullato: [
            'Annullato dal Potenziale',
            'Annullato Azienda',
            'Annullato Call Center',
        ],
        'Non Gestito': ['Annullato dal Consulente'],
        'Non Ricevuto': ['Cliente Assente'],
        Rifissato: [
            'Rimandato dal Potenziale',
            'Rimandato da cliente',
            'Rimandato da consulente',
        ],
        // Esiti quando Stato=Ingestibile (anche senza sottostato ancora scelto)
        Ingestibile: [
            'Solo Preventivo',
            'Non Finanziabile',
            'Non detraibile per età',
            'Non detraibile per prodotto',
            'Non detraibile per esposizione',
            'Permessi',
            'Casa Popolare/Affitto',
            'In ristrutturazione/In costruzione/Cantiere',
            'Cambio Telo',
            'Copertura Auto',
            'Tenda a Capanno',
            'Infattibilità Tecnica',
            'Prodotto non Conforme',
        ],
    };

    const unionEsitiForStatus = function (status) {
        if (status === 'Ingestibile') {
            return esitiBySottostato.Ingestibile.slice();
        }

        const sottostati = allowedMap[status] || [];
        const out = [];

        sottostati.forEach(function (s) {
            (esitiBySottostato[s] || []).forEach(function (e) {
                if (out.indexOf(e) === -1) {
                    out.push(e);
                }
            });
        });

        return out;
    };

    return {
        getAllowedForStatus: function (status) {
            return allowedMap[status] || [];
        },
        getEsitoMapping: function (esito) {
            return esitoMap[esito] || null;
        },
        getAllowedEsiti: function (status, sottostato) {
            if (status === 'Planned' || status === '') {
                return [];
            }

            // Ingestibile: motivo in sottostato + esito sempre selezionabile
            if (status === 'Ingestibile') {
                return esitiBySottostato.Ingestibile.slice();
            }

            // Senza sottostato: tutti gli esiti ammessi per lo Stato (poi l'esito setta il sottostato).
            if (!sottostato) {
                return unionEsitiForStatus(status);
            }

            return (esitiBySottostato[sottostato] || []).slice();
        },
        allowedMap: allowedMap,
        esitoMap: esitoMap,
        esitiBySottostato: esitiBySottostato,
    };
});
