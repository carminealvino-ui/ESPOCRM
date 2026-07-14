/* global define */

define('custom:views/appuntamento/modals/detail', [
    'crm:views/meeting/modals/detail',
    'custom:helpers/appuntamento-duration',
], function (Dep, Helper) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this, arguments);

            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            this.once('after:render', () => {
                this.applyDefaultDuration();
            });
        },

        applyDefaultDuration: function () {
            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            const dateStart = this.model.get('dateStart');

            if (!dateStart) {
                return;
            }

            const dateEnd = Helper.addSecondsToSystemDateTime(
                this.getDateTime(),
                dateStart,
                Helper.FALLBACK_DURATION_SECONDS
            );

            if (!dateEnd) {
                return;
            }

            this.model.set({
                dateEnd: dateEnd,
            }, {updatedByDuration: true});
        },
    });
});
