/* global define */

define('custom:views/fields/appuntamento-parent', [
    'views/fields/link-parent',
    'custom:views/appuntamento/helpers/prospect-sync',
], function (Dep, ProspectSync) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:parentId change:parentType', () => {
                this.runProspectSync();
            });
        },

        runProspectSync: function () {
            if (this.model.get('parentType') !== 'Prospect' || !this.model.get('parentId')) {
                return;
            }

            const recordView = this.getRecordView();

            if (recordView) {
                ProspectSync.syncFromProspect(recordView);
            }
        },
    });
});
