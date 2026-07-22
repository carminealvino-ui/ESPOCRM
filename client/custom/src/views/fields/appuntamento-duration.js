/* global define */

/**
 * Durata Appuntamento: su nuovo record allinea sempre dateEnd a 1h30
 * finché l'utente non cambia manualmente la durata.
 * Evita lo stato inconsistente "Durata 1h30 / orario 30m" dal calendario.
 */
define('custom:views/fields/appuntamento-duration', [
    'views/fields/duration',
    'custom:helpers/appuntamento-duration',
], function (Dep, AppuntamentoDurationHelper) {

    const DEFAULT_DURATION_SECONDS = 5400;

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this._userChangedDuration = false;
            this._enforcingDefaultDuration = false;

            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            const enforce = () => this.forceDefaultDurationEnd();

            this.once('after:render', enforce);
            [50, 150, 350, 700, 1200, 2000].forEach(ms => {
                setTimeout(enforce, ms);
            });

            this.listenTo(this.model, 'change:dateStart', (model, value, o) => {
                if (!this.model.isNew() || this.model.get('isAllDay')) {
                    return;
                }

                if (o && (o.fromField === this.name || o.updatedByDuration)) {
                    return;
                }

                if (this._userChangedDuration) {
                    return;
                }

                this.forceDefaultDurationEnd();
            });

            this.listenTo(this.model, 'change:dateEnd', (model, value, o) => {
                if (!this.model.isNew() || this.model.get('isAllDay') || this._userChangedDuration) {
                    return;
                }

                if (o && (o.fromField === this.name || o.updatedByDuration || o.ui === false)) {
                    return;
                }

                if (this.needsDefaultDateEnd()) {
                    this.forceDefaultDurationEnd();
                }
            });
        },

        getDefaultDurationSeconds: function () {
            const fromField = this.model.getFieldParam(this.name, 'default');

            if (fromField !== null && fromField !== undefined && fromField !== '') {
                return parseInt(fromField, 10) || DEFAULT_DURATION_SECONDS;
            }

            return DEFAULT_DURATION_SECONDS;
        },

        needsDefaultDateEnd: function () {
            if (!this.model.isNew() || this.model.get('isAllDay') || this._userChangedDuration) {
                return false;
            }

            const dateStart = this.model.get(this.startField);
            const dateEnd = this.model.get(this.endField);

            if (!dateStart || !dateEnd) {
                return !!dateStart;
            }

            const expected = AppuntamentoDurationHelper.addSecondsToSystemDateTime(
                this.getDateTime(),
                dateStart,
                this.getDefaultDurationSeconds()
            );

            return expected && dateEnd !== expected;
        },

        /**
         * Su nuovo: mostra sempre 1h30 e sincronizza dateEnd.
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
            if (
                this._enforcingDefaultDuration ||
                !this.model.isNew() ||
                this.model.get('isAllDay') ||
                this._userChangedDuration
            ) {
                return;
            }

            const dateStart = this.model.get(this.startField);

            if (!dateStart) {
                return;
            }

            const seconds = this.getDefaultDurationSeconds();
            const dateEnd = AppuntamentoDurationHelper.addSecondsToSystemDateTime(
                this.getDateTime(),
                dateStart,
                seconds
            );

            if (!dateEnd) {
                return;
            }

            this.seconds = seconds;

            if (this.model.get(this.endField) === dateEnd) {
                if (typeof this.updateDuration === 'function') {
                    this.updateDuration();
                }

                return;
            }

            this._enforcingDefaultDuration = true;
            this.blockDateEndChangeListener = true;

            this.model.set(this.endField, dateEnd, {
                updatedByDuration: true,
                ui: true,
            });

            setTimeout(() => {
                this.blockDateEndChangeListener = false;
                this._enforcingDefaultDuration = false;

                if (typeof this.updateDuration === 'function') {
                    this.updateDuration();
                }

                // Aggiorna anche la view del campo Date End se già renderizzata
                const recordView = this.getParentView && this.getParentView();

                if (recordView && typeof recordView.getFieldView === 'function') {
                    const endView = recordView.getFieldView(this.endField);

                    if (endView && typeof endView.reRender === 'function') {
                        endView.reRender();
                    }
                }
            }, 50);
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

            return AppuntamentoDurationHelper.addSecondsToSystemDateTime(
                this.getDateTime(),
                start,
                seconds
            ) || start;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (!this.isEditMode() || !this.$duration || !this.$duration.length) {
                return;
            }

            this.$duration.off('change.appuntamentoDefault').on('change.appuntamentoDefault', () => {
                this._userChangedDuration = true;
            });

            if (this.model.isNew() && !this._userChangedDuration) {
                this.forceDefaultDurationEnd();
            }
        },
    });
});
