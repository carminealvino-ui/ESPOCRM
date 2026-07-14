/* global define */

define('custom:views/appuntamento/modals/detail', [
    'crm:views/meeting/modals/detail',
    'moment',
], function (Dep, moment) {

    const DEFAULT_DURATION_SECONDS = 5400;
    const FULL_FORMAT = 'YYYY-MM-DD HH:mm:ss';

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

            let m = moment.utc(dateStart, FULL_FORMAT, true);

            if (!m.isValid()) {
                m = moment.utc(dateStart);
            }

            if (!m.isValid()) {
                return;
            }

            const dateEnd = m.add(DEFAULT_DURATION_SECONDS, 'seconds').format(FULL_FORMAT);

            this.model.set({
                dateEnd: dateEnd,
            }, {updatedByDuration: true});
        },
    });
});
