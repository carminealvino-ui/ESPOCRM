/* global define */

define('custom:views/modals/working-time-calendar-edit', ['views/modals/edit'], function (Dep) {

    return Dep.extend({

        layoutName: 'edit',

        fullFormDisabled: true,
    });
});
