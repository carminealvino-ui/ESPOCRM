define('custom:views/fields/whatsapp-create-call', [
    'views/fields/url',
    'custom:helpers/call-create-from-record',
], function (Dep, CallCreateHelper) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.events['click a'] = function (event) {
                this.actionWhatsAppCreateCall(event);
            };
        },

        actionWhatsAppCreateCall: function (event) {
            const entityType = this.model && this.model.entityType;
            const allowed = CallCreateHelper.allowedEntityTypes;

            if (!entityType || allowed.indexOf(entityType) === -1) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const urlValue = ($(event.currentTarget).attr('href') || this.model.get(this.name) || '').trim();

            CallCreateHelper.openCreateCallFromWhatsApp(this, this.model, urlValue);
        },
    });
});
