/* global define */

define('custom:views/appuntamento/fields/duration', ['views/fields/duration'], function (Dep) {

    return Dep.extend({

        getDefaultDurationSeconds: function () {
            const fromField = this.model.getFieldParam(this.name, 'default');

            if (fromField !== null && fromField !== undefined && fromField !== '') {
                return parseInt(fromField, 10);
            }

            return 5400;
        },

        getExpectedDateEnd: function (dateStart, seconds) {
            return this.getDateTime()
                .toMoment(dateStart)
                .add(seconds, 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);
        },

        shouldEnforceDefaultDuration: function () {
            return this.model.isNew() && !this.model.get('isAllDay');
        },

        enforceDefaultDuration: function () {
            if (!this.shouldEnforceDefaultDuration()) {
                return;
            }

            const dateStart = this.model.get(this.startField);

            if (!dateStart) {
                this.seconds = this.getDefaultDurationSeconds();

                return;
            }

            const defaultSeconds = this.getDefaultDurationSeconds();
            const expectedEnd = this.getExpectedDateEnd(dateStart, defaultSeconds);
            const currentEnd = this.model.get(this.endField);

            this.seconds = defaultSeconds;

            if (currentEnd !== expectedEnd) {
                this.model.set(this.endField, expectedEnd, {updatedByDuration: true});
            }
        },

        calculateSeconds: function () {
            if (this.shouldEnforceDefaultDuration()) {
                this.enforceDefaultDuration();

                return;
            }

            Dep.prototype.calculateSeconds.call(this);
        },
    });
});
