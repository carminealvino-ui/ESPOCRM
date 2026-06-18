define('custom:helpers/call-esito-popup-defaults', [], function () {

    const TIPOLOGIA_RICHIAMO_OPPORTUNITA = 'Richiamo su Opportunità Generata';
    const LEGACY_TIPOLOGIA = 'Contatto dopo Prima Visita';
    const AUTO_PENDING_NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    const DESCRIPTION_STANDARD =
        'Salve, sono Carmine Alvino di ARIEL ENERGIA, mi fa sapere entro la giornata di oggi '
        + 'poi cosa ha deciso rispetto alla proposta che le ho fatto, Grazie';

    const isAutoPendingCall = function (model) {
        const nota = model.get('nota') || '';

        return nota.indexOf(AUTO_PENDING_NOTA_PREFIX) !== -1
            || model.get('tipologia') === LEGACY_TIPOLOGIA;
    };

    const applyDefaults = function (model) {
        if (!isAutoPendingCall(model)) {
            return;
        }

        model.set('tipologia', TIPOLOGIA_RICHIAMO_OPPORTUNITA);

        if (!(model.get('description') || '').trim()) {
            model.set('description', DESCRIPTION_STANDARD);
        }
    };

    const applyWhatsAppDescription = function (model) {
        if (model.get('tipologia') !== TIPOLOGIA_RICHIAMO_OPPORTUNITA) {
            return;
        }

        if (!(model.get('description') || '').trim()) {
            model.set('description', DESCRIPTION_STANDARD);
        }
    };

    return {
        applyDefaults: applyDefaults,
        applyWhatsAppDescription: applyWhatsAppDescription,
        TIPOLOGIA_RICHIAMO_OPPORTUNITA: TIPOLOGIA_RICHIAMO_OPPORTUNITA,
        DESCRIPTION_STANDARD: DESCRIPTION_STANDARD,
    };
});
