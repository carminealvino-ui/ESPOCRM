/* global define */

define('custom:views/fields/appuntamento-parent', [
    'views/fields/link-parent',
    'custom:helpers/appuntamento-prospect-sync',
], function (Dep, ProspectSync) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:parentId change:parentType', () => {
                this.syncFromProspect();
            });
        },

        syncFromProspect: function () {
            const recordView = this.getRecordView();

            if (!recordView) {
                return;
            }

            ProspectSync.syncFromProspect(recordView);
        },
    });
});
