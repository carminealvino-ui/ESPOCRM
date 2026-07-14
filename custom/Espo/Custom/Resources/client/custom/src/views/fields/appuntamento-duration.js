/* global define */

define('custom:views/fields/appuntamento-duration', ['views/fields/duration', 'custom:helpers/appuntamento-duration'], function (Dep, Helper) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            this.once('after:render', () => {
                this.forceDefaultDurationEnd();
            });

            this.listenTo(this.model, 'change:dateStart', (model, value, o) => {
                if (!this.model.isNew() || this.model.get('isAllDay')) {
                    return;
                }

                if (o && o.fromField === this.name) {
                    return;
                }

                this.forceDefaultDurationEnd();
            });
        },

        getDefaultDurationSeconds: function () {
            const fromField = this.model.getFieldParam(this.name, 'default');

            if (fromField !== null && fromField !== undefined && fromField !== '') {
                return parseInt(fromField, 10);
            }

            return Helper.FALLBACK_DURATION_SECONDS;
        },

        /**
         * Su nuovo appuntamento: Date End = dateStart + durata default (UTC).
         * Corregge il valore errato passato dal calendario (+offset timezone).
         */
        forceDefaultDurationEnd: function () {
            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            const dateStart = this.model.get(this.startField);

            if (!dateStart) {
                return;
            }

            const seconds = this.getDefaultDurationSeconds();
            const dateEnd = Helper.addSecondsToSystemDateTime(this.getDateTime(), dateStart, seconds);

            if (!dateEnd) {
                return;
            }

            this.seconds = seconds;
            this.blockDateEndChangeListener = true;

            this.model.set(this.endField, dateEnd, {
                updatedByDuration: true,
                ui: true,
            });

            setTimeout(() => {
                this.blockDateEndChangeListener = false;
                this.updateDuration();
            }, 120);
        },

        _getDateEnd: function () {
            const seconds = this.seconds;
            const start = this.model.get(this.startField);

            if (!start) {
                return undefined;
            }

            if (!seconds) {
                return start;
            }

            return Helper.addSecondsToSystemDateTime(this.getDateTime(), start, seconds) || start;
        },
    });
});
