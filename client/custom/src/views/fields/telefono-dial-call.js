define('custom:views/fields/telefono-dial-call', [
    'views/fields/varchar',
    'custom:helpers/call-create-from-record',
], function (Dep, CallCreateHelper) {

    return Dep.extend({

        events: {
            'click [data-action="dialTelefono"]': function (event) {
                event.preventDefault();
                event.stopPropagation();

                const phoneNumber = (this.model.get(this.name) || '').trim();

                CallCreateHelper.openCreateCallModal(this, this.model, phoneNumber);
            },
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'detail' && this.mode !== 'list') {
                return;
            }

            const value = (this.model.get(this.name) || '').trim();

            if (!value) {
                return;
            }

            const allowed = CallCreateHelper.allowedEntityTypes;

            if (allowed.indexOf(this.model.entityType) === -1) {
                return;
            }

            const $target = this.$el.find('.field-value').first();

            if (!$target.length) {
                return;
            }

            $target.html(
                '<a href="javascript:void(0)" class="selectable" data-action="dialTelefono">'
                + _.escape(value)
                + '</a>'
            );
        },
    });
});
