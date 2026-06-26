/* global define, Espo */

define('custom:views/appuntamento/helpers/rifissato', [], function () {

    const DEFAULT_DURATION_SECONDS = 5400;

    const COPY_ATTRIBUTES = [
        'name',
        'prospectId',
        'prospectName',
        'parentType',
        'parentId',
        'parentName',
        'leadId',
        'leadName',
        'azienda',
        'fornitorePartnerId',
        'fornitorePartnerName',
        'productBrandId',
        'productBrandName',
        'productCategoryId',
        'productCategoryName',
        'cAPId',
        'cAPName',
        'assignedUserId',
        'assignedUserName',
        'teamsIds',
        'teamsNames',
        'tipo',
        'callCenter',
        'indirizzoStreet',
        'indirizzoCity',
        'indirizzoPostalCode',
        'indirizzoState',
        'indirizzoCountry',
        'location',
    ];

    const CLEAR_OUTCOME_ATTRIBUTES = {
        status: 'Planned',
        sottostato: null,
        esito: null,
        noteEsito: null,
    };

    const isRifissatoState = function (status, sottostato) {
        return sottostato === 'Rifissato' && status && status !== 'Planned';
    };

    const isRifissato = function (model) {
        return isRifissatoState(model.get('status'), model.get('sottostato'));
    };

    const isBecomingRifissato = function (model, attributes) {
        if (attributes && Object.prototype.hasOwnProperty.call(attributes, 'dateStart')) {
            return false;
        }

        const previousSottostato = model.get('sottostato');
        const previousStatus = model.get('status');
        const nextSottostato = attributes && Object.prototype.hasOwnProperty.call(attributes, 'sottostato')
            ? attributes.sottostato
            : previousSottostato;
        const nextStatus = attributes && Object.prototype.hasOwnProperty.call(attributes, 'status')
            ? attributes.status
            : previousStatus;

        return nextSottostato === 'Rifissato'
            && previousSottostato !== 'Rifissato'
            && nextStatus !== 'Planned';
    };

    const shouldTriggerAfterSave = function (model, attributes) {
        if (attributes && Object.prototype.hasOwnProperty.call(attributes, 'dateStart')) {
            return false;
        }

        if (attributes && Object.keys(attributes).length) {
            return isBecomingRifissato(model, attributes);
        }

        return model.get('sottostato') === 'Rifissato'
            && model.hasChanged('sottostato')
            && model.get('status') !== 'Planned';
    };

    const formatOriginalDateTime = function (dateTime, dateTimeUtil) {
        if (!dateTime || !dateTimeUtil) {
            return '';
        }

        const moment = dateTimeUtil.toMoment(dateTime);

        return moment.format('DD/MM/YYYY') + ' ore ' + moment.format('HH:mm');
    };

    const buildDescription = function (sourceModel, dateTimeUtil, originalDateStart) {
        const dateStart = originalDateStart || sourceModel.get('dateStart');
        const formatted = formatOriginalDateTime(dateStart, dateTimeUtil);

        return 'appuntamento rifissato del ' + formatted;
    };

    const buildNewAppuntamentoAttributes = function (sourceModel, dateTimeUtil, originalDateStart) {
        const attributes = Object.assign({}, CLEAR_OUTCOME_ATTRIBUTES, {
            description: buildDescription(sourceModel, dateTimeUtil, originalDateStart),
        });

        COPY_ATTRIBUTES.forEach(fieldName => {
            const value = sourceModel.get(fieldName);

            if (value !== null && value !== undefined && value !== '') {
                attributes[fieldName] = value;
            }
        });

        if (!attributes.parentType && attributes.prospectId) {
            attributes.parentType = 'Prospect';
            attributes.parentId = attributes.prospectId;
            attributes.parentName = attributes.prospectName || null;
        }

        return attributes;
    };

    const applyDefaultDuration = function (model, dateTimeUtil) {
        const dateStart = model.get('dateStart');

        if (!dateStart || !dateTimeUtil) {
            return;
        }

        const dateEnd = dateTimeUtil
            .toMoment(dateStart)
            .add(DEFAULT_DURATION_SECONDS, 'seconds')
            .format(dateTimeUtil.internalDateTimeFormat);

        model.set('dateEnd', dateEnd);
    };

    const openCreateModal = function (view, sourceModel, originalDateStart) {
        const snapshotDateStart = originalDateStart || sourceModel.get('dateStart');
        const attributes = buildNewAppuntamentoAttributes(
            sourceModel,
            view.getDateTime(),
            snapshotDateStart
        );

        view.getModelFactory().create('Appuntamento')
            .then(newModel => {
                newModel.set(attributes);

                view.createView('rifissatoAppuntamentoDialog', 'custom:views/appuntamento/modals/rifissato-create', {
                    model: newModel,
                }, modalView => {
                    modalView.render();
                });
            });
    };

    const setupModelHandling = function (model, view) {
        if (!model || model._rifissatoSaveWrapped) {
            return;
        }

        model._rifissatoSaveWrapped = true;

        const originalSave = model.save.bind(model);

        model.save = function (attributes, options) {
            options = options || {};

            if (options.rifissatoCreate) {
                return originalSave(attributes, options);
            }

            const triggerRifissato = shouldTriggerAfterSave(model, attributes);

            return originalSave(attributes, options).then(result => {
                if (triggerRifissato) {
                    openCreateModal(view, model, model.get('dateStart'));
                }

                return result;
            });
        };
    };

    const setupRecordHandling = function (view) {
        setupModelHandling(view.model, view);
    };

    return {
        isRifissato: isRifissato,
        isBecomingRifissato: isBecomingRifissato,
        shouldTriggerAfterSave: shouldTriggerAfterSave,
        buildNewAppuntamentoAttributes: buildNewAppuntamentoAttributes,
        buildDescription: buildDescription,
        openCreateModal: openCreateModal,
        applyDefaultDuration: applyDefaultDuration,
        setupModelHandling: setupModelHandling,
        setupRecordHandling: setupRecordHandling,
    };
});
