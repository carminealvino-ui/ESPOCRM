/* global define */

define('custom:views/appuntamento/record/edit-small', [
    'views/record/edit-small',
    'moment',
], function (Dep, moment) {

    const DEFAULT_DURATION_SECONDS = 5400;
    const FULL_FORMAT = 'YYYY-MM-DD HH:mm:ss';
    const SHORT_FORMAT = 'YYYY-MM-DD HH:mm';

    function addSecondsUtc(dateStart, seconds) {
        if (!dateStart) {
            return null;
        }

        let m = moment.utc(dateStart, FULL_FORMAT, true);

        if (!m.isValid()) {
            m = moment.utc(dateStart, SHORT_FORMAT, true);
        }

        if (!m.isValid()) {
            m = moment.utc(dateStart);
        }

        if (!m.isValid()) {
            return null;
        }

        return m.add(seconds, 'seconds').format(FULL_FORMAT);
    }

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            this.listenTo(this.model, 'change:dateStart', () => {
                this.applyDefaultDuration();
            });

            this.once('after:render', () => {
                this.applyDefaultDuration();
            });

            // Dopo che i campi data sono pronti (calendario passa spesso 30m)
            setTimeout(() => this.applyDefaultDuration(), 200);
            setTimeout(() => this.applyDefaultDuration(), 500);
        },

        getDefaultDurationSeconds: function () {
            const fromField = this.model.getFieldParam('duration', 'default');

            if (fromField !== null && fromField !== undefined && fromField !== '') {
                return parseInt(fromField, 10) || DEFAULT_DURATION_SECONDS;
            }

            const fromMeta = this.getMetadata().get(
                ['entityDefs', 'Appuntamento', 'fields', 'duration', 'default']
            );

            if (fromMeta !== null && fromMeta !== undefined && fromMeta !== '') {
                return parseInt(fromMeta, 10) || DEFAULT_DURATION_SECONDS;
            }

            return DEFAULT_DURATION_SECONDS;
        },

        applyDefaultDuration: function () {
            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            const dateStart = this.model.get('dateStart');

            if (!dateStart) {
                return;
            }

            const seconds = this.getDefaultDurationSeconds();
            const dateEnd = addSecondsUtc(dateStart, seconds);

            if (!dateEnd) {
                return;
            }

            if (this.model.get('dateEnd') === dateEnd) {
                return;
            }

            this.model.set({
                dateEnd: dateEnd,
            }, {updatedByDuration: true, ui: true});
        },
    });
});
