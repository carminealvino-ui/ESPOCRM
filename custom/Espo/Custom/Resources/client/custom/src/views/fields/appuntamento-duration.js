/* global define */

define('custom:views/fields/appuntamento-duration', [
    'views/fields/duration',
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

            this._userChangedDuration = false;

            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            this.once('after:render', () => {
                this.forceDefaultDurationEnd();
            });

            // Il calendario passa spesso Date End a +30m: riallinea dopo i timeout Espo
            setTimeout(() => this.forceDefaultDurationEnd(), 150);
            setTimeout(() => this.forceDefaultDurationEnd(), 350);
            setTimeout(() => this.forceDefaultDurationEnd(), 700);

            this.listenTo(this.model, 'change:dateStart', (model, value, o) => {
                if (!this.model.isNew() || this.model.get('isAllDay')) {
                    return;
                }

                if (o && o.fromField === this.name) {
                    return;
                }

                if (this._userChangedDuration) {
                    return;
                }

                this.forceDefaultDurationEnd();
            });
        },

        getDefaultDurationSeconds: function () {
            const fromField = this.model.getFieldParam(this.name, 'default');

            if (fromField !== null && fromField !== undefined && fromField !== '') {
                return parseInt(fromField, 10) || DEFAULT_DURATION_SECONDS;
            }

            return DEFAULT_DURATION_SECONDS;
        },

        /**
         * Su nuovo: ignora slot calendario (es. 30m) e usa sempre default 1h30
         * finché l'utente non cambia manualmente la durata.
         */
        calculateSeconds: function () {
            if (
                this.model.isNew() &&
                !this.model.get('isAllDay') &&
                !this._userChangedDuration
            ) {
                this.seconds = this.getDefaultDurationSeconds();

                return;
            }

            Dep.prototype.calculateSeconds.call(this);
        },

        forceDefaultDurationEnd: function () {
            if (!this.model.isNew() || this.model.get('isAllDay') || this._userChangedDuration) {
                return;
            }

            const dateStart = this.model.get(this.startField);

            if (!dateStart) {
                return;
            }

            const seconds = this.getDefaultDurationSeconds();
            const dateEnd = addSecondsUtc(dateStart, seconds);

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

                if (typeof this.updateDuration === 'function') {
                    this.updateDuration();
                }
            }, 150);
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

            return addSecondsUtc(start, seconds) || start;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (!this.isEditMode() || !this.$duration || !this.$duration.length) {
                return;
            }

            this.$duration.off('change.appuntamentoDefault').on('change.appuntamentoDefault', () => {
                this._userChangedDuration = true;
            });
        },
    });
});
