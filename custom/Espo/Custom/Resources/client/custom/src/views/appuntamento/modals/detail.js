/* global define */

define('custom:views/appuntamento/modals/detail', ['views/modals/detail'], function (Dep) {

    const Parent = Dep.default || Dep;

    return Parent.extend({

        setup: function () {
            Parent.prototype.setup.call(this, arguments);

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
