define('custom:helpers/call-create-from-record', [], function () {

    const ALLOWED_ENTITY_TYPES = ['Prospect', 'Lead', 'Account', 'Contact'];

    const buildRecordName = function (model) {
        const name = (model.get('name') || '').trim();

        if (name) {
            return name;
        }

        return [
            model.get('firstName') || '',
            model.get('lastName') || '',
        ].join(' ').trim();
    };

    const buildAttributes = function (model, phoneNumber) {
        const entityType = model.entityType;
        const recordName = buildRecordName(model);
        const dialNumber = (phoneNumber || model.get('phoneNumber') || '').trim();

        const attributes = {
            parentId: model.id,
            parentType: entityType,
            parentName: recordName,
            telefono: dialNumber,
            status: 'Planned',
            direction: 'Outbound',
        };

        if (entityType === 'Prospect') {
            attributes.prospectId = model.id;
            attributes.prospectName = recordName;
        }

        if (entityType === 'Lead') {
            attributes.leadsIds = [model.id];
            attributes.leadsNames = {};
            attributes.leadsNames[model.id] = recordName;

            if (model.get('prospectId')) {
                attributes.prospectId = model.get('prospectId');
                attributes.prospectName = model.get('prospectName');
            }
        }

        if (entityType === 'Account') {
            attributes.accountId = model.id;
            attributes.accountName = recordName;

            if (model.get('prospectId')) {
                attributes.prospectId = model.get('prospectId');
                attributes.prospectName = model.get('prospectName');
            }
        }

        if (entityType === 'Contact') {
            attributes.contactsIds = [model.id];
            attributes.contactsNames = {};
            attributes.contactsNames[model.id] = recordName;

            if (model.get('accountId')) {
                attributes.accountId = model.get('accountId');
                attributes.accountName = model.get('accountName');
            }
        }

        return attributes;
    };

    const openCreateCallModal = function (hostView, model, phoneNumber) {
        if (!model || ALLOWED_ENTITY_TYPES.indexOf(model.entityType) === -1) {
            return;
        }

        if (!hostView || typeof hostView.createView !== 'function') {
            return;
        }

        const attributes = buildAttributes(model, phoneNumber);

        hostView.createView('createCallFromPhoneDialog', 'views/modals/edit', {
            scope: 'Call',
            layoutName: 'detail',
            attributes: attributes,
        }, function (view) {
            view.render();

            hostView.listenToOnce(view, 'after:save', function () {
                Espo.Ui.success(
                    hostView.translate('Create Call', 'labels', 'Call') || 'Contatto telefonico creato'
                );

                if (hostView.model && typeof hostView.model.fetch === 'function') {
                    hostView.model.fetch();
                }
            });
        });
    };

    return {
        allowedEntityTypes: ALLOWED_ENTITY_TYPES,
        buildAttributes: buildAttributes,
        openCreateCallModal: openCreateCallModal,
    };
});
