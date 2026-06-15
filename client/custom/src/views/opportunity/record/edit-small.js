/* global define */

define('custom:views/opportunity/record/edit-small', [
    'crm:views/opportunity/record/edit-small',
    'custom:views/opportunity/helpers/appuntamento-sync',
], function (Dep, AppuntamentoSync) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            AppuntamentoSync.setup(this);
        },
    });
});
