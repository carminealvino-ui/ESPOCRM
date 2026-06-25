define('custom:helpers/call-appuntamento-sync', [], function () {

    const ESITO_OPPORTUNITA_ACCETTATA = 'Opportunità Accettata';

    const LEAD_COPY_FIELDS = [
        'azienda',
        'fornitorePartnerId',
        'fornitorePartnerName',
        'productBrandId',
        'productBrandName',
        'productCategoryId',
        'productCategoryName',
        'cAPId',
        'cAPName',
        'prospectId',
        'prospectName',
    ];

    const buildAttributesFromCall = function (call) {
        const parentType = call.get('parentType');
        const parentId = call.get('parentId');

        const attributes = {
            status: 'Planned',
            parentType: parentType || null,
            parentId: parentId || null,
            parentName: call.get('parentName') || null,
            prospectId: call.get('prospectId') || null,
            prospectName: call.get('prospectName') || null,
            telefono: call.get('telefono') || null,
            assignedUserId: call.get('assignedUserId') || null,
            assignedUserName: call.get('assignedUserName') || null,
        };

        if (parentType === 'Lead' && parentId) {
            attributes.leadId = parentId;
            attributes.leadName = call.get('parentName') || null;
        }

        if (parentType === 'Prospect' && parentId) {
            attributes.prospectId = parentId;
            attributes.prospectName = call.get('parentName') || null;
        }

        return attributes;
    };

    const enrichFromLead = function (attributes, lead) {
        if (!lead) {
            return attributes;
        }

        LEAD_COPY_FIELDS.forEach(fieldName => {
            const value = lead.get(fieldName);

            if (value !== null && value !== undefined && value !== '') {
                attributes[fieldName] = value;
            }
        });

        if (!attributes.telefono) {
            attributes.telefono = lead.get('phoneNumber') || lead.get('telefono') || null;
        }

        return attributes;
    };

    const resolveLeadId = function (call) {
        if (call.get('parentType') === 'Lead' && call.get('parentId')) {
            return call.get('parentId');
        }

        return null;
    };

    return {
        ESITO_OPPORTUNITA_ACCETTATA: ESITO_OPPORTUNITA_ACCETTATA,
        buildAttributesFromCall: buildAttributesFromCall,
        enrichFromLead: enrichFromLead,
        resolveLeadId: resolveLeadId,
    };
});
