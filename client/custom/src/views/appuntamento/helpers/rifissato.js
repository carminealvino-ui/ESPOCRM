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

    const isRifissato = function (model) {
        return model.get('status') === 'Held' && model.get('sottostato') === 'Rifissato';
    };

    const shouldOpenAfterSave = function (model) {
        if (!isRifissato(model)) {
            return false;
        }

        return model.hasChanged('sottostato') || model.hasChanged('status');
    };

    const formatOriginalDateTime = function (dateTime, dateTimeUtil) {
        if (!dateTime || !dateTimeUtil) {
            return '';
        }

        const moment = dateTimeUtil.toMoment(dateTime);

        return moment.format('DD/MM/YYYY') + ' ore ' + moment.format('HH:mm');
    };

    const buildDescription = function (sourceModel, dateTimeUtil) {
        const formatted = formatOriginalDateTime(sourceModel.get('dateStart'), dateTimeUtil);

        return 'appuntamento rifissato del ' + formatted;
    };

    const buildNewAppuntamentoAttributes = function (sourceModel, dateTimeUtil) {
        const attributes = {
            status: 'Planned',
            description: buildDescription(sourceModel, dateTimeUtil),
        };

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

    const openCreateModal = function (view, sourceModel) {
        const attributes = buildNewAppuntamentoAttributes(sourceModel, view.getDateTime());

        view.createView('rifissatoAppuntamentoDialog', 'views/modals/edit', {
            scope: 'Appuntamento',
            attributes: attributes,
            layoutName: 'rifissatoCreate',
        }, modalView => {
            modalView.render();

            const model = modalView.model;

            const syncDuration = () => applyDefaultDuration(model, modalView.getDateTime());

            modalView.listenTo(model, 'change:dateStart', syncDuration);
            modalView.once('after:render', syncDuration);
        });
    };

    const setupRecordHandling = function (view) {
        if (view._rifissatoSaveWrapped) {
            return;
        }

        view._rifissatoSaveWrapped = true;

        const originalSave = view.model.save.bind(view.model);

        view.model.save = function (attributes, options) {
            const pendingRifissato = shouldOpenAfterSave(view.model);

            return originalSave(attributes, options).then(result => {
                if (pendingRifissato) {
                    openCreateModal(view, view.model);
                }

                return result;
            });
        };
    };

    return {
        isRifissato: isRifissato,
        shouldOpenAfterSave: shouldOpenAfterSave,
        buildNewAppuntamentoAttributes: buildNewAppuntamentoAttributes,
        buildDescription: buildDescription,
        openCreateModal: openCreateModal,
        setupRecordHandling: setupRecordHandling,
    };
});
