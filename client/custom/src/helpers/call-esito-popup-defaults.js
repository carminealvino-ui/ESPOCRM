define('custom:helpers/call-esito-popup-defaults', [], function () {

    const TIPOLOGIA_RICHIAMO_OPPORTUNITA = 'Richiamo su Opportunità Generata';
    const LEGACY_TIPOLOGIA = 'Contatto dopo Prima Visita';
    const AUTO_PENDING_NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    const TESTO_STANDARD =
        'Salve, sono Carmine Alvino di ARIEL ENERGIA, mi fa sapere entro la giornata di oggi '
        + 'poi cosa ha deciso rispetto alla proposta che le ho fatto, Grazie';
    const AUTO_PENDING_DESCRIPTION_PREFIX = 'Richiamo automatico per appuntamento Pending del';

    const CANALE_LABELS = {
        call: 'Chiamata',
        whatsapp: 'WhatsApp',
    };

    const getDaRichiamareLabel = function (status) {
        status = normalize(status);

        if (status === 'Held' || status === 'Not Held') {
            return 'Crea nuova chiamata';
        }

        return 'Rinvia richiamo';
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
            || tipologia === TIPOLOGIA_RICHIAMO_OPPORTUNITA
            || tipologia === LEGACY_TIPOLOGIA
            || containsLegacyTipologia(name)
            || containsLegacyTipologia(popupName);
    };

    const isAutoPendingDescription = function (value) {
        return normalize(value).indexOf(AUTO_PENDING_DESCRIPTION_PREFIX) !== -1;
    };

    const extractTelefonoFromName = function (name) {
        const parts = normalize(name).split(' - ');

        if (parts.length < 4) {
            return null;
        }

        return normalize(parts[parts.length - 1]);
    };

    const ensureContactFields = function (model) {
        if (!model) {
            return false;
        }

        let telefono = normalize(model.get('telefono'));

        if (!telefono) {
            telefono = extractTelefonoFromName(model.get('name')) || '';

            if (telefono) {
                model.set('telefono', telefono);
            }
        }

        if (!normalize(model.get('whatsAppNumero')) && telefono) {
            const digits = telefono.replace(/\D+/g, '');

            if (digits) {
                model.set('whatsAppNumero', 'https://wa.me/+39' + digits);
            }
        }

        return Boolean(telefono);
    };

    const buildUpdatedName = function (model) {
        const currentName = normalize(model.get('name'));
        const tipologia = normalize(model.get('tipologia'));

        if (!currentName || !tipologia) {
            return null;
        }

        const parts = currentName.split(' - ');

        if (parts.length < 4) {
            return null;
        }

        parts[1] = tipologia;

        return parts.join(' - ').toUpperCase();
    };

    const normalizeMisplacedFields = function (model) {
        if (!model) {
            return false;
        }

        let changed = false;
        const description = normalize(model.get('description'));
        const testo = normalize(model.get('testo'));
        const nota = normalize(model.get('nota'));

        if (description && isAutoPendingDescription(description)) {
            if (nota.indexOf(description) === -1) {
                model.set('nota', nota ? nota + '\n' + description : description);
            }

            model.set('description', '');
            changed = true;
        } else if (description === TESTO_STANDARD) {
            if (!testo) {
                model.set('testo', TESTO_STANDARD);
            }

            model.set('description', '');
            changed = true;
        }

        return changed;
    };

    const resolveDateTime = function (view, dateTime) {
        if (dateTime && typeof dateTime.getNowMoment === 'function') {
            return dateTime;
        }

        let current = view;

        while (current) {
            if (typeof current.getDateTime === 'function') {
                const resolved = current.getDateTime();

                if (resolved && typeof resolved.getNowMoment === 'function') {
                    return resolved;
                }
            }

            current = current.getParentView && current.getParentView();
        }

        return null;
    };

    const buildDefaultDataRichiamo = function (dateTime) {
        if (!dateTime || typeof dateTime.getNowMoment !== 'function') {
            const d = new Date();

            d.setDate(d.getDate() + 1);
            d.setHours(9, 0, 0, 0);

            const pad = (value) => String(value).padStart(2, '0');

            return d.getFullYear() + '-'
                + pad(d.getMonth() + 1) + '-'
                + pad(d.getDate()) + ' '
                + pad(d.getHours()) + ':'
                + pad(d.getMinutes()) + ':00';
        }

        const moment = dateTime.getNowMoment().clone().add(1, 'days').hours(9).minutes(0).seconds(0);

        if (typeof dateTime.toUtc === 'function') {
            return dateTime.toUtc(moment);
        }

        if (typeof dateTime.toUtcDateTime === 'function') {
            return dateTime.toUtcDateTime(moment);
        }

        const format = dateTime.internalDateTimeFormat || 'YYYY-MM-DD HH:mm:ss';

        return moment.format(format);
    };

    const applyRinvioDefaults = function (model, dateTime, view) {
        if (!model) {
            return false;
        }

        let changed = false;
        const tipologia = normalize(model.get('tipologia'));

        if (!model.get('daRichiamare')) {
            return false;
        }

        if (tipologia && !normalize(model.get('richiamo'))) {
            model.set('richiamo', tipologia);
            changed = true;
        }

        if (!model.get('dataRichiamo')) {
            const resolvedDateTime = resolveDateTime(view, dateTime);

            model.set('dataRichiamo', buildDefaultDataRichiamo(resolvedDateTime));
            changed = true;
        }

        return changed;
    };

    const setupRinvioFieldListeners = function (recordView, model, dateTime, rootView) {
        if (!recordView || !model) {
            return;
        }

        const dateTimeView = rootView || recordView;

        const apply = () => {
            applyRinvioDefaults(model, dateTime, dateTimeView);
            refreshRecordFields(recordView, ['richiamo', 'dataRichiamo', 'daRichiamare']);
        };

        recordView.listenTo(model, 'change:daRichiamare', apply);
        recordView.listenTo(model, 'change:status', () => {
            refreshRecordFields(recordView, ['daRichiamare']);
            apply();
        });
        apply();
    };

    const getDefaultAttributes = function (model, notificationName) {
        if (!isAutoPendingCall(model, notificationName)) {
            return null;
        }

        const attributes = {
            direction: 'Outbound',
            whatsApp: true,
            vocale: false,
        };

        const tipologia = normalize(model.get('tipologia'));

        if (!tipologia || tipologia === LEGACY_TIPOLOGIA) {
            attributes.tipologia = TIPOLOGIA_RICHIAMO_OPPORTUNITA;
        }

        if (!normalize(model.get('testo'))) {
            attributes.testo = TESTO_STANDARD;
        }

        return attributes;
    };

    const applyDefaults = function (model, notificationName) {
        normalizeMisplacedFields(model);
        ensureContactFields(model);

        const attributes = getDefaultAttributes(model, notificationName);

        if (!attributes) {
            return false;
        }

        model.set(attributes);

        return true;
    };

    const applyWhatsAppTesto = function (model) {
        const tipologia = normalize(model.get('tipologia'));

        if (tipologia !== TIPOLOGIA_RICHIAMO_OPPORTUNITA
            && !containsLegacyTipologia(tipologia)
            && !containsLegacyTipologia(model.get('name'))) {
            return false;
        }

        if (normalize(model.get('testo'))) {
            return false;
        }

        model.set('testo', TESTO_STANDARD);

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
    };

    const forceDomValues = function (recordView) {
        if (!recordView || !recordView.$el || !recordView.model) {
            return;
        }

        const testo = normalize(recordView.model.get('testo'));

        if (testo) {
            recordView.$el
                .find('.field[data-name="testo"] textarea')
                .val(testo)
                .trigger('change');
        }
    };

    const applyWithRetry = function (recordView, notificationName) {
        const model = recordView.model;

        if (!model) {
            return false;
        }

        const applied = applyDefaults(model, notificationName);

        if (!applied) {
            return false;
        }

        refreshRecordFields(recordView, ['tipologia', 'direction', 'testo', 'telefono', 'whatsAppNumero']);
        forceDomValues(recordView);

        return true;
    };

    const ensureBeforeSave = function (model, notificationName) {
        if (!model) {
            return;
        }

        normalizeMisplacedFields(model);
        ensureContactFields(model);
        applyDefaults(model, notificationName);
        applyRinvioDefaults(model, null, null);

        const canale = model.get('canaleContatto');

        if (canale === 'whatsapp') {
            model.set({
                vocale: false,
                whatsApp: true,
            });
        } else if (canale === 'call') {
            model.set({
                vocale: true,
                whatsApp: false,
            });
        }
    };

    const getSaveAttributes = function (model, notificationName) {
        ensureBeforeSave(model, notificationName);

        const attributes = {
            status: model.get('status'),
            direction: model.get('direction'),
            tipologia: model.get('tipologia'),
            esito: model.get('esito'),
            description: model.get('description'),
            testo: model.get('testo'),
            vocale: model.get('vocale'),
            whatsApp: model.get('whatsApp'),
            daRichiamare: model.get('daRichiamare'),
            dataRichiamo: model.get('dataRichiamo'),
            richiamo: model.get('richiamo'),
        };

        if (isAutoPendingCall(model, notificationName)) {
            const updatedName = buildUpdatedName(model);

            if (updatedName) {
                attributes.name = updatedName;
            }
        }

        return attributes;
    };

    return {
        applyDefaults: applyDefaults,
        applyRinvioDefaults: applyRinvioDefaults,
        setupRinvioFieldListeners: setupRinvioFieldListeners,
        getDaRichiamareLabel: getDaRichiamareLabel,
        applyWhatsAppTesto: applyWhatsAppTesto,
        applyWhatsAppDescription: applyWhatsAppTesto,
        normalizeMisplacedFields: normalizeMisplacedFields,
        refreshRecordFields: refreshRecordFields,
        forceDomValues: forceDomValues,
        applyWithRetry: applyWithRetry,
        ensureBeforeSave: ensureBeforeSave,
        getSaveAttributes: getSaveAttributes,
        getCanaleLabel: function (value) {
            return CANALE_LABELS[value] || value;
        },
        TIPOLOGIA_RICHIAMO_OPPORTUNITA: TIPOLOGIA_RICHIAMO_OPPORTUNITA,
        TESTO_STANDARD: TESTO_STANDARD,
        DESCRIPTION_STANDARD: TESTO_STANDARD,
    };
});
