define('custom:helpers/call-esito-popup-defaults', [], function () {

    const TIPOLOGIA_RICHIAMO_OPPORTUNITA = 'Richiamo su Opportunità Generata';
    const LEGACY_TIPOLOGIA = 'Contatto dopo Prima Visita';
    const AUTO_PENDING_NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    const DESCRIPTION_STANDARD =
        'Salve, sono Carmine Alvino di ARIEL ENERGIA, mi fa sapere entro la giornata di oggi '
        + 'poi cosa ha deciso rispetto alla proposta che le ho fatto, Grazie';

    const CANALE_LABELS = {
        call: 'Chiamata',
        whatsapp: 'WhatsApp',
    };

    const normalize = function (value) {
        return (value || '').toString().trim();
    };

    const containsLegacyTipologia = function (value) {
        value = normalize(value).toUpperCase();

        return value.indexOf('CONTATTO DOPO PRIMA VISITA') !== -1;
    };

    const isAutoPendingCall = function (model, notificationName) {
        const nota = normalize(model.get('nota'));
        const tipologia = normalize(model.get('tipologia'));
        const name = normalize(model.get('name'));
        const popupName = normalize(notificationName);

        return nota.indexOf(AUTO_PENDING_NOTA_PREFIX) !== -1
            || tipologia === LEGACY_TIPOLOGIA
            || containsLegacyTipologia(name)
            || containsLegacyTipologia(popupName);
    };

    const getDefaultAttributes = function (model, notificationName) {
        if (!isAutoPendingCall(model, notificationName)) {
            return null;
        }

        const attributes = {
            tipologia: TIPOLOGIA_RICHIAMO_OPPORTUNITA,
            direction: 'Outbound',
        };

        if (!normalize(model.get('description'))) {
            attributes.description = DESCRIPTION_STANDARD;
        }

        return attributes;
    };

    const applyDefaults = function (model, notificationName) {
        const attributes = getDefaultAttributes(model, notificationName);

        if (!attributes) {
            return false;
        }

        model.set(attributes);

        return true;
    };

    const applyWhatsAppDescription = function (model) {
        const tipologia = normalize(model.get('tipologia'));

        if (tipologia !== TIPOLOGIA_RICHIAMO_OPPORTUNITA
            && !containsLegacyTipologia(tipologia)) {
            return false;
        }

        if (normalize(model.get('description'))) {
            return false;
        }

        model.set('description', DESCRIPTION_STANDARD);

        return true;
    };

    const refreshRecordFields = function (recordView, fieldNames) {
        if (!recordView) {
            return;
        }

        fieldNames.forEach(fieldName => {
            const fieldView = recordView.getFieldView && recordView.getFieldView(fieldName);

            if (fieldView && typeof fieldView.reRender === 'function') {
                fieldView.reRender();
            }
        });

        const description = recordView.model && recordView.model.get('description');

        if (description && recordView.$el) {
            recordView.$el
                .find('.field[data-name="description"] textarea')
                .val(description);
        }
    };

    return {
        applyDefaults: applyDefaults,
        applyWhatsAppDescription: applyWhatsAppDescription,
        refreshRecordFields: refreshRecordFields,
        getCanaleLabel: function (value) {
            return CANALE_LABELS[value] || value;
        },
        TIPOLOGIA_RICHIAMO_OPPORTUNITA: TIPOLOGIA_RICHIAMO_OPPORTUNITA,
        DESCRIPTION_STANDARD: DESCRIPTION_STANDARD,
    };
});
