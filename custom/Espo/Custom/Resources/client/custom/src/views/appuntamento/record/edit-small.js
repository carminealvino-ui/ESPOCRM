/* global define */

define('custom:views/appuntamento/record/edit-small', ['crm:views/meeting/record/edit-small'], function (Dep) {

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

            const defaultSeconds = 5400;
            const dateEnd = this.getDateTime()
                .toMoment(dateStart)
                .add(defaultSeconds, 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);

            this.model.set({
                dateEnd: dateEnd,
            });
        },
    });
});
