define('custom:helpers/call-esito-popup-defaults', [], function () {

    const TIPOLOGIA_RICHIAMO_OPPORTUNITA = 'Richiamo su Opportunità Generata';
    const LEGACY_TIPOLOGIA = 'Contatto dopo Prima Visita';
    const AUTO_PENDING_NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    const TESTO_STANDARD =
        'Salve, sono Carmine Alvino di ARIEL ENERGIA, mi fa sapere entro la giornata di oggi '
        + 'poi cosa ha deciso rispetto alla proposta che le ho fatto, Grazie';
    const AUTO_PENDING_DESCRIPTION_PREFIX = 'Richiamo automatico per appuntamento Pending del';

    let cachedStandardTesto = null;

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

    const isAutoPendingDescription = function (value) {
        return normalize(value).indexOf(AUTO_PENDING_DESCRIPTION_PREFIX) !== -1;
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

    const getStandardTesto = function () {
        return cachedStandardTesto || TESTO_STANDARD;
    };

    const loadStandardTesto = function () {
        if (cachedStandardTesto) {
            return Promise.resolve(cachedStandardTesto);
        }

        return Espo.Ajax.getRequest('CallStandardTesto/action/read')
            .then(response => {
                cachedStandardTesto = normalize(response.testo) || TESTO_STANDARD;

                return cachedStandardTesto;
            })
            .catch(() => {
                cachedStandardTesto = TESTO_STANDARD;

                return cachedStandardTesto;
            });
    };

    const buildWhatsAppUrl = function (telefono) {
        const digits = normalize(telefono).replace(/\D+/g, '');

        return digits ? 'https://wa.me/+39' + digits : null;
    };

    const parseTelefonoFromName = function (name) {
        const parts = normalize(name).split(' - ');

        if (parts.length < 4) {
            return null;
        }

        return parts[parts.length - 1] || null;
    };

    const ensureContactFields = function (model) {
        if (!model) {
            return false;
        }

        let telefono = normalize(model.get('telefono'));
        let whatsAppNumero = normalize(model.get('whatsAppNumero'));
        let changed = false;

        if (!telefono) {
            const fromName = parseTelefonoFromName(model.get('name'));

            if (fromName) {
                telefono = fromName;
                model.set('telefono', telefono);
                changed = true;
            }
        }

        if (!whatsAppNumero && telefono) {
            const url = buildWhatsAppUrl(telefono);

            if (url) {
                model.set('whatsAppNumero', url);
                changed = true;
            }
        }

        return changed;
    };

    const ensureContactFieldsFromProspect = function (model) {
        if (!model || normalize(model.get('telefono'))) {
            return Promise.resolve(ensureContactFields(model));
        }

        const prospectId = model.get('prospectId');

        if (!prospectId) {
            return Promise.resolve(ensureContactFields(model));
        }

        return Espo.Ajax.getRequest('Prospect/' + prospectId, {
            select: 'phoneNumber,telefono,whatsApp,whatsApp39',
        }).then(prospect => {
            const telefono = normalize(prospect.phoneNumber || prospect.telefono);

            if (telefono) {
                model.set('telefono', telefono);
            }

            const whatsAppUrl = normalize(prospect.whatsApp || prospect.whatsApp39);

            if (whatsAppUrl && whatsAppUrl.indexOf('http') === 0) {
                model.set('whatsAppNumero', whatsAppUrl);
            }

            ensureContactFields(model);

            return true;
        }).catch(() => {
            ensureContactFields(model);

            return false;
        });
    };

    const getDefaultAttributes = function (model, notificationName, standardTesto) {
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

        const message = normalize(standardTesto) || getStandardTesto();

        if (!normalize(model.get('testo'))) {
            attributes.testo = message;
        }

        return attributes;
    };

    const applyDefaults = function (model, notificationName, standardTesto) {
        normalizeMisplacedFields(model);

        const attributes = getDefaultAttributes(model, notificationName, standardTesto);

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

        model.set('testo', getStandardTesto());

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

    const applyWithRetry = function (recordView, notificationName, standardTesto) {
        const model = recordView.model;

        if (!model) {
            return false;
        }

        const applied = applyDefaults(model, notificationName, standardTesto);

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
        applyDefaults(model, notificationName);

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
        loadStandardTesto: loadStandardTesto,
        getStandardTesto: getStandardTesto,
        ensureContactFields: ensureContactFields,
        ensureContactFieldsFromProspect: ensureContactFieldsFromProspect,
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
