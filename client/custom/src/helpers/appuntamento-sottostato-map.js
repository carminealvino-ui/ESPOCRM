define('custom:helpers/appuntamento-sottostato-map', [], function () {

    const allowedMap = {
        Held: [
            'Pending',
            'Gestito',
            'Chiuso Positivamente',
            'Non Interessato',
        ],
        'Not Held': [
            'Non Confermato',
            'Non Ricevuto',
            'Non Gestito',
            'Annullato',
            'Rifissato',
        ],
        Ingestibile: [
            'Infattibilità Tecnica',
            'Solo Informazioni',
            'Prodotto non Conforme',
            'Fuori Target',
        ],
    };

    return {
        getAllowedForStatus: function (status) {
            return allowedMap[status] || [];
        },
        allowedMap: allowedMap,
    };
});
