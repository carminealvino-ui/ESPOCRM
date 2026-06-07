define('custom:views/fields/phone-create-call', [
    'views/fields/phone',
    'custom:helpers/call-create-from-record',
], function (Dep, CallCreateHelper) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.events['click [data-action="dial"]'] = function (event) {
                this.actionDialCreateCall(event);
            };
        },

        actionDialCreateCall: function (event) {
            const entityType = this.model && this.model.entityType;
            const allowed = CallCreateHelper.allowedEntityTypes;

            if (!entityType || allowed.indexOf(entityType) === -1) {
                if (typeof Dep.prototype.actionDial === 'function') {
                    Dep.prototype.actionDial.call(this, event);
                }

                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const phoneNumber = ($(event.currentTarget).attr('data-phone-number') || '').trim();

            CallCreateHelper.openCreateCallModal(this, this.model, phoneNumber);
        },
    });
});
