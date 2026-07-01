/* global define */

define('custom:views/appuntamento/fields/duration', ['views/fields/duration', 'moment'], function (Dep, moment) {

    return Dep.extend({

        _enforcingDefaultDuration: false,

        getDefaultDurationSeconds: function () {
            const fromField = this.model.getFieldParam(this.name, 'default');

            if (fromField !== null && fromField !== undefined && fromField !== '') {
                return parseInt(fromField, 10);
            }

            return 5400;
        },

        getExpectedDateEnd: function (dateStart, seconds) {
            const endUnix = moment.utc(dateStart).unix() + seconds;

            return moment.unix(endUnix).utc().format(this.getDateTime().internalDateTimeFormat);
        },

        getDateTimeUnix: function (value) {
            if (!value) {
                return null;
            }

            return moment.utc(value).unix();
        },

        isSameDateTime: function (left, right) {
            const leftUnix = this.getDateTimeUnix(left);
            const rightUnix = this.getDateTimeUnix(right);

            if (leftUnix === null || rightUnix === null) {
                return left === right;
            }

            return leftUnix === rightUnix;
        },

        shouldEnforceDefaultDuration: function () {
            return this.model.isNew() && !this.model.get('isAllDay');
        },

        enforceDefaultDuration: function () {
            if (!this.shouldEnforceDefaultDuration() || this._enforcingDefaultDuration) {
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

            if (this.isSameDateTime(currentEnd, expectedEnd)) {
                return;
            }

            this._enforcingDefaultDuration = true;

            try {
                this.model.set(this.endField, expectedEnd, {updatedByDuration: true});
            } finally {
                this._enforcingDefaultDuration = false;
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
