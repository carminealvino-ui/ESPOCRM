/* global define */

define('custom:views/appuntamento/record/list', ['views/record/list'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.massSelectionDisabled = true;
        },
    });
});
