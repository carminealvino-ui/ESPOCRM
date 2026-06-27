/* global define */

define('custom:views/appuntamento/helpers/rifissato', [], function () {

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

    const openCreateModal = function (view, sourceModel, originalDateStart, options) {
        if (!sourceModel || !sourceModel.id) {
            return;
        }

        if (view._rifissatoModalOpen) {
            return;
        }

        view._rifissatoModalOpen = true;

        const modalOptions = {
            sourceId: sourceModel.id,
            originalDateStart: originalDateStart || sourceModel.get('dateStart'),
            assignedUsersIds: options && options.assignedUsersIds
                ? options.assignedUsersIds
                : (sourceModel.get('assignedUsersIds') || []),
        };

        view.createView('rifissatoAppuntamentoDialog', 'custom:views/appuntamento/modals/rifissato-create', modalOptions, modalView => {
            modalView.render();

            modalView.once('close', () => {
                view._rifissatoModalOpen = false;
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
            const triggerRifissato = shouldTriggerAfterSave(model, attributes);
            const preservedAssignedUsersIds = triggerRifissato
                ? (model.get('assignedUsersIds') || []).slice()
                : null;

            return originalSave(attributes, options).then(result => {
                if (triggerRifissato) {
                    openCreateModal(view, model, model.get('dateStart'), {
                        assignedUsersIds: preservedAssignedUsersIds,
                    });
                }

                return result;
            });
        };
    };

    const setupRecordHandling = function (view) {
        setupModelHandling(view.model, view);
    };

    return {
        openCreateModal: openCreateModal,
        setupModelHandling: setupModelHandling,
        setupRecordHandling: setupRecordHandling,
    };
});
