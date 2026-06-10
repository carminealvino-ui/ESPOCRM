/* global define */

define('custom:views/appuntamento/record/edit', [
    'views/record/edit',
    'custom:views/appuntamento/prospect-sync',
], function (Dep, ProspectSync) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            ProspectSync.setupProspectSync(this);
            ProspectSync.setupDefaultDuration(this);
        },
    });
});
