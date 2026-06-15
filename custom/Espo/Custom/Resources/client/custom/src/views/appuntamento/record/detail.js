/* global define */

define('custom:views/appuntamento/record/detail', [
    'crm:views/meeting/record/detail',
    'custom:views/opportunity/helpers/appuntamento-sync',
], function (Dep, AppuntamentoSync) {

    return Dep.extend({

        setupActionItems: function () {
            Dep.prototype.setupActionItems.call(this);

            if (!this.model || this.model.entityType !== 'Appuntamento') {
                return;
            }

            if (this.model.get('status') !== 'Held') {
                return;
            }

            this.dropdownItemList.push({
                label: 'Crea Opportunità',
                name: 'createOpportunity',
            });
        },

        actionCreateOpportunity: function () {
            this.notify('Apertura Opportunità...');

            const dateStart = this.model.get('dateStart');
            const attributes = AppuntamentoSync.buildAttributesFromAppuntamento({
                id: this.model.id,
                name: this.model.get('name'),
                dateStart: dateStart,
                azienda: this.model.get('azienda'),
                fornitorePartnerId: this.model.get('fornitorePartnerId'),
                fornitorePartnerName: this.model.get('fornitorePartnerName'),
                productBrandId: this.model.get('productBrandId'),
                productBrandName: this.model.get('productBrandName'),
                productCategoryId: this.model.get('productCategoryId'),
                productCategoryName: this.model.get('productCategoryName'),
                prospectId: this.model.get('prospectId'),
                prospectName: this.model.get('prospectName'),
                telefono: this.model.get('telefono'),
            });

            this.createView('createOpportunityDialog', 'views/modals/edit', {
                scope: 'Opportunity',
                attributes: attributes,
            }, view => {
                view.render();
            });
        },
    });
});
